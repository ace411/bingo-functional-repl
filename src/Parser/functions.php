<?php

/**
 * REPL parser functions
 *
 * @package bingo-functional-repl
 * @author  Lochemem Bruno Michael
 * @license Apache-2.0
 */

declare(strict_types=1);

namespace Chemem\Bingo\Functional\Repl\Parser;

use PhpParser\Node;
use PhpParser\Error;
use PhpParser\NodeFinder;
use PhpParser\PrettyPrinter;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use Clue\React\Stdio\Stdio;
use Chemem\Bingo\Functional as f;
use Chemem\Bingo\Functional\PatternMatching as p;
use Chemem\Bingo\Functional\Functors\Monads\State;
use Chemem\Bingo\Functional\Functors\Monads\IO;
use Chemem\Bingo\Functional\Repl;
use Chemem\Bingo\Functional\Repl\Printer as pp;
use React\ChildProcess\Process;
use React\EventLoop\LoopInterface;

/**
 * generateAst
 * Tokenizes PHP code
 *
 * generateAst :: String -> Array
 *
 * @param string $code
 */
function generateAst(string $code): array
{
  $ast = function (string $input) {
    return (new ParserFactory())
      ->create(ParserFactory::PREFER_PHP7)
      ->parse(f\concat(' ', '<?php', $input, '?>'));
  };

  return f\toException(
    $ast,
    function () use ($ast) {
      return $ast('identity("")');
    }
  )($code);
}
const generateAst                   = __NAMESPACE__ . '\\generateAst';

/**
 * commaSeparateMetadata
 * outputs function and object metadata list as comma-separated string
 *
 * commaSeparateMetadata :: Array -> String
 *
 * @param array $data
 */
function commaSeparateMetadata(array $data): string
{
  $separate = f\compose(
    f\partial(
      f\map,
      function ($param) {
        return $param->name;
      }
    ),
    function (array $params) {
      return f\concat(', ', ...$params);
    }
  );

  return $separate($data);
}
const commaSeparateMetadata         = __NAMESPACE__ . '\\commaSeparateMetadata';

/**
 * getUtilityMetadata
 * get function or object metadata
 *
 * getUtilityMetadata :: String -> Array
 *
 * @param string $utility
 */
function getUtilityMetadata(string $utility): array
{
  $ref = \function_exists($utility) ?
    'function' :
    (\class_exists($utility) ? 'class' : 'none');

  return p\patternMatch([
    '"function"'  => function () use ($utility) {
      return getFunctionMetadata($utility);
    },
    '"class"'     => function () use ($utility) {
      return getClassMetadata($utility);
    },
    '_'           => function () {
      return [];
    },
  ], $ref);
}
const getUtilityMetadata            = __NAMESPACE__ . '\\getUtilityMetadata';

/**
 * getFunctionMetadata
 * outputs PHP function metadata as array
 *
 * getFunctionMetadata :: String -> Array
 *
 * @param string $function
 */
function getFunctionMetadata(string $function): array
{
  $ref      = new \ReflectionFunction($function);
  $retType  = $ref->getReturnType();

  return [
    'paramCount'  => $ref->getNumberOfParameters(),
    'parameters'  => commaSeparateMetadata($ref->getParameters()),
    'returnType'  => \is_null($retType) ? 'any' : $retType->getName(),
  ];
}
const getFunctionMetadata           = __NAMESPACE__ . '\\getFunctionMetadata';

/**
 * getClassMetadata
 * outputs PHP class metadata as array
 *
 * getClassMetadata :: String -> Array
 *
 * @param string $class
 */
function getClassMetadata(string $class): array
{
  $ref            = new \ReflectionClass($class);
  $commaSeparate  = f\compose('array_values', commaSeparateMetadata);

  return [
    'implements'  => $commaSeparate($ref->getInterfaces()),
    'methods'     => $commaSeparate($ref->getMethods()),
  ];
}
const getClassMetadata              = __NAMESPACE__ . '\\getClassMetadata';

/**
 * isLibraryArtifact
 * checks if artifact is a bingo-functional utility
 *
 * isLibraryArtifact :: String -> Array
 *
 * @param string $artifact
 */
function isLibraryArtifact(string $artifact): array
{
  $check = State\gets(
    function (string $func) {
      return f\fold(
        function (array $acc, string $namespace) use ($func): array {
          $fullname = f\concat('', $namespace, $func);

          $check    = \function_exists($fullname) || \class_exists($fullname);
          if ($check) {
            $acc = [$check, $fullname];
          }

          return $acc;
        },
        Repl\BASE_NAMESPACES,
        []
      );
    }
  );

  return State\evalState($check, null)($artifact);
}
const isLibraryArtifact       = __NAMESPACE__ . '\\isLibraryArtifact';

/**
 * parseCode
 * parses REPL input as PHP code
 *
 * parseCode :: String -> Object -> Object -> IO ()
 *
 * @param string        $code
 * @param Stdio         $stdio
 * @param LoopInterface $loop
 */
function parseCode(
  string $code,
  Stdio $stdio,
  LoopInterface $loop
): IO {
  $node = f\head(generateAst(empty($code) ? '""' : $code));
  $expr = f\pluck($node->jsonSerialize(), 'expr');

  return p\patternMatch([
    // asynchronously evaluate PHP expression
    Node\Stmt\Expression::class => function () use ($node, $expr, $loop, $stdio) {
      return evalExpression($node, $expr, $stdio, $loop);
    },
    // output non-parsable error for non-expressions
    '_'                         => function () use ($stdio, $code) {
      return Repl\stdioWrite(
        $stdio,
        pp\printError('nparsable', f\concat('', '{', $code, '}'))
      );
    },
  ], $node);
}
const parseCode                     = __NAMESPACE__ . '\\parseCode';

/**
 * evalExpression
 * selectively evaluate PHP expressions
 *
 * evalExpression :: Object -> Object -> Object -> Object -> IO ()
 *
 * @param Node          $node
 * @param Node          $expr
 * @param Stdio         $stdio
 * @param LoopInterface $loop
 */
function evalExpression(
  Node $node, // expression object
  Node $expr, // expression type
  Stdio $stdio,
  LoopInterface $loop
): IO {
  $write  = f\partial(Repl\stdioWrite, $stdio);
  $concat = f\partialRight(
    f\partial(f\concat, '', '[', '"PhpParser","Node","Expr",'),
    ']',
  );

  return p\patternMatch(
    [
      // parse static method calls
      $concat('"StaticCall"')   => function () use ($node, $stdio, $loop) {
        return evalFunctionCall($node, $stdio, $loop);
      },
      // parse regular method calls
      $concat('"MethodCall"')   => function () use ($node, $stdio, $loop) {
        return evalFunctionCall($node, $stdio, $loop);
      },
      // parse binary expressions (add, divide, subtract, concat etc...)
      $concat('"BinaryOp",_')   => function () use ($node, $stdio, $loop) {
        return evalFunctionCall($node, $stdio, $loop);
      },
      // parse function calls
      $concat('"FuncCall"')     => function () use ($node, $stdio, $loop) {
        return evalFunctionCall($node, $stdio, $loop);
      },
      // parse variable assignments arbitrarily
      $concat('"Assign"')       => function () use ($node, $stdio) {
        return evalAssign($node, $stdio);
      },
      // dump stored variable contents
      $concat('"Variable"')     => function () use ($node, $stdio, $loop) {
        return evalVarDump($node, $stdio, $loop);
      },
      // everything else
      '_'                       => function () use ($write, $node) {
        return $write(pp\printError('nparsable', prettyPrint([$node])));
      },
    ],
    \explode('\\', (new \ReflectionClass($expr))->getName())
  );
}
const evalExpression                = __NAMESPACE__ . '\\evalExpression';

/**
 * findFirstInstanceOfNode
 * outputs first instance of specific AST node
 *
 * findFirstInstanceOfNode :: Object -> String -> Object
 *
 * @param Node    $node
 * @param string  $nodeType
 */
function findFirstInstanceOfNode(Node $node, string $nodeType): object
{
  $finder = f\compose(
    f\partial(f\extend, [$node]),
    f\partial(
      'call_user_func_array',
      [new NodeFinder(), 'findFirstInstanceOf']
    )
  );

  return $finder([$nodeType]);
}
const findFirstInstanceOfNode       = __NAMESPACE__ . '\\findFirstInstanceOfNode';

/**
 * replaceVariable
 * replaces variable with a corresponding value stored in the apcu cache
 *
 * replaceVariable :: Object -> Object
 *
 * @param Node $node
 */
function replaceVariable(Node $node): Node
{
  return storeFetchByVar($node->name) // fetch from APCU
    ->map(
      function ($var) {
        $ret = f\compose(generateAst, f\head);

        return $ret($var === false || \is_null($var) ? 'null' : $var);
      }
    )
    ->exec();
}
const replaceVariable                     = __NAMESPACE__ . '\\replaceVariable';

/**
 * modifyNodeName
 * prepends bingo-functional artifact namespace to artifact identifier
 *
 * modifyNodeName :: Object -> Object
 *
 * @param Node $node
 */
function modifyNodeName(Node $node): Node
{
  $handler = function (Node $node) {
    $funcName   = $node->toString();
    $funcCheck  = isLibraryArtifact($funcName);
    $meta       = f\head($funcCheck);

    // return node full name or original, unaltered node
    return empty($meta) ? $node : new Node\Name(f\last($meta));
  };

  return f\toException(
    $handler,
    function () use ($node) {
      return $node;
    }
  )($node);
}
const modifyNodeName                    = __NAMESPACE__ . '\\modifyNodeName';

/**
 * customTraverser
 * modifies a generated AST with custom rules
 *
 * customTraverser :: Object
 *
 * @return NodeTraverser
 */
function customTraverser(): NodeTraverser
{
  $traverser = new NodeTraverser();
  $traverser
    ->addVisitor(
      new class() extends NodeVisitorAbstract {
        public function leaveNode(Node $node): object
        {
          // return full artifact name if an entity is indeed a library artifact
          if ($node instanceof Node\Name) {
            return modifyNodeName($node);
          }

          // check if the node is a function argument
          if ($node instanceof Node\Arg) {
            $argType = $node->value;

            if ($argType instanceof Node\Expr\Variable) {
              return replaceVariable($node->value);
            }
          }

          // handle variables in method calls
          if ($node instanceof Node\Expr\MethodCall) {
            $var = $node->var;
            if ($var instanceof Node\Expr\Variable) {
              return new Node\Expr\MethodCall(
                replaceVariable($var)->expr,
                $node->name,
                $node->args
              );
            }
          }

          return $node;
        }
      }
    );

  return $traverser;
}
const customTraverser         = __NAMESPACE__ . '\\customTraverser';

/**
 * prettyPrint
 * pretty print a PHP expression from an AST
 *
 * prettyPrint :: Array -> String
 *
 * @param array $expr
 */
function prettyPrint(array $expr): string
{
  $printer = new PrettyPrinter\Standard();

  return $printer->prettyPrint($expr);
}
const prettyPrint                   = __NAMESPACE__ . '\\prettyPrint';

/**
 * evalFunctionCall
 * execute a function call expression
 *
 * evalFunctionCall :: Object -> Object -> Object -> IO ()
 *
 * @param Node          $node
 * @param Stdio         $stdio
 * @param LoopInterface $loop
 */
function evalFunctionCall(
  Node $node,
  Stdio $stdio,
  LoopInterface $loop
): IO {
  return phpExec(
    prettyPrint(customTraverser()->traverse([$node])),
    $stdio,
    $loop
  );
}
const evalFunctionCall              = __NAMESPACE__ . '\\evalFunctionCall';

/**
 * evalAssign
 * execute an assignment operation and store the assigned value in the APCU cache
 *
 * evalAssign :: Object -> Object -> IO ()
 *
 * @param Node  $node
 * @param Stdio $stdio
 */
function evalAssign(Node $node, Stdio $stdio): IO
{
  $assign   = findFirstInstanceOfNode($node, Node\Expr\Assign::class);
  $success  = f\compose(
    f\partialRight(f\pluck, 'success'),
    f\partial(pp\colorOutput, 'Success')
  );

  return storeAdd(
    $assign->var->name,
    prettyPrint(customTraverser()->traverse([$assign->expr]))
  )->bind(function (bool $status) use ($stdio, $success) {
    return Repl\stdioWrite(
      $stdio,
      $status ?
        $success(Repl\REPL_COLORS) :
        pp\printError('nexecutable', 'Could not assign')
    );
  });
}
const evalAssign                    = __NAMESPACE__ . '\\evalAssign';

/**
 * evalVarDump
 * dump a variable's contents
 *
 * evalVarDump :: Object -> Object -> Object -> IO ()
 *
 * @param Node          $node
 * @param Stdio         $stdio
 * @param LoopInterface $loop
 */
function evalVarDump(
  Node $node,
  Stdio $stdio,
  LoopInterface $loop
): IO {
  $var  = findFirstInstanceOfNode($node, Node\Expr\Variable::class);

  return storeFetchByVar($var->name)
    ->bind(
      function ($res) use ($stdio, $var, $loop) {
        return !$res ?
          Repl\stdioWrite(
            $stdio,
            pp\printError('nexists', f\concat('', '$', $var->name))
          ) :
          phpExec($res, $stdio, $loop);
      }
    );
}
const evalVarDump                   = __NAMESPACE__ . '\\evalVarDump';

/**
 * storeAdd function
 * store data in apcu cache
 *
 * storeAdd :: String -> String -> IO ()
 *
 * @param string $varName
 * @param string $expr
 */
function storeAdd(string $varName, string $expr): IO
{
  return IO\IO(
    \apcu_exists($varName) ? false : \apcu_store($varName, $expr)
  );
}
const storeAdd                      = __NAMESPACE__ . '\\storeAdd';

/**
 * storeFetchByVar function
 * fetch value assigned to variable from apcu cache
 *
 * storeFetchByVar :: String -> IO ()
 *
 * @param string $varName
 */
function storeFetchByVar(string $varName = ''): IO
{
  return IO\IO(
    \apcu_exists($varName) ? \apcu_fetch($varName) : null
  );
}
const storeFetchByVar               = __NAMESPACE__ . '\\storeFetchByVar';

/**
 * execCommand
 * execute a PHP command
 *
 * execCommand :: String -> Object -> Object -> IO ()
 *
 * @param string        $code
 * @param Stdio         $stdio
 * @param LoopInterface $loop
 */
function execCommand(
  string $code,
  Stdio $stdio,
  LoopInterface $loop
): IO {
  return IO\IO(
    function () use ($code, &$stdio, $loop): Stdio {
      $prefix = f\partial(f\concat, ' ', '=>');
      $proc   = new Process($code);
      $proc->start($loop);

      // asynchronously write the result of the process to STDOUT
      $proc->stdout->on('data', function (string $data) use (
        &$stdio,
        $prefix
      ) {
        $stdio->write($prefix($data));
      });

      // asynchronously write process error to STDERR
      $proc->stdout->on('error', function (\Throwable $err) use (
        &$stdio,
        $prefix
      ) {
        $print = f\compose(
          f\partialRight(f\pluck, 'error'),
          f\partial(pp\colorOutput, $err->getMessage()),
          $prefix
        );
        $stdio->write($print(Repl\REPL_COLORS));
      });

      return $stdio;
    }
  );
}
const execCommand                   = __NAMESPACE__ . '\\execCommand';

/**
 * phpExec
 * execute PHP code in command-line
 *
 * phpExec :: String -> Object -> Object -> IO ()
 *
 * @param string        $code
 * @param Stdio         $stdio
 * @param LoopInterface $loop
 */
function phpExec(
  string $code,
  Stdio $stdio,
  LoopInterface $loop
): IO {
  $exec = f\compose(
    pp\genCmdDirective,
    f\partialRight(execCommand, $loop, $stdio)
  );

  return $exec($code);
}
const phpExec                       = __NAMESPACE__ . '\\phpExec';
