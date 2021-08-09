<?php

declare(strict_types=1);

namespace Chemem\Bingo\Functional\Repl\Tests;

use Chemem\Bingo\Functional as f;
use Chemem\Bingo\Functional\Repl;
use Chemem\Bingo\Functional\Repl\Parser as pr;
use Chemem\Bingo\Functional\Repl\Printer as pp;
use Chemem\Bingo\Functional\Functors\Monads\IO;
use Chemem\Bingo\Functional\PatternMatching as p;
use PhpParser\Node;

/**
 * @see Chemem\Bingo\Functional\Repl\Parser\evalFunctionCall
 */
function evalFunctionCall(Node $node): IO
{
  return IO\IO(
    pr\prettyPrint(
      pr\customTraverser()->traverse([$node])
    )
  )->bind(
    function (string $code) {
      return phpExec($code);
    }
  );
}

const evalFunctionCall = __NAMESPACE__ . '\\evalFunctionCall';

/**
 * @see Chemem\Bingo\Functional\Repl\Parser\evalAssign
 */
function evalAssign(Node $node): IO
{
  $assign   = pr\findFirstInstanceOfNode($node, Node\Expr\Assign::class);
  $success  = f\compose(
    f\partialRight(f\pluck, 'success'),
    f\partial(pp\colorOutput, 'Success')
  );

  return pr\storeAdd(
    $assign->var->name,
    pr\prettyPrint(
      pr\customTraverser()->traverse([$assign->expr])
    )
  )->bind(
    function (bool $status) use ($success) {
      return IO\IO(
        $status ?
          $success(Repl\REPL_COLORS) :
          pp\printError('nexecutable', 'Could not assign')
      );
    }
  );
}

const evalAssign = __NAMESPACE__ . '\\evalAssign';

/**
 * @see Chemem\Bingo\Functional\Repl\Parser\evalVarDump
 */
function evalVarDump(Node $node): IO
{
  $var = pr\findFirstInstanceOfNode($node, Node\Expr\Variable::class);

  return pr\storeFetchByVar($var->name)
    ->bind(
      function ($res) use ($var) {
        return !$res ?
          IO\IO(pp\printError('nexists', f\concat('', '$', $var->name))) :
          phpExec($res);
      }
    );
}

const evalVarDump = __NAMESPACE__ . '\\evalVarDump';

/**
 * @see Chemem\Bingo\Functional\Repl\Parser\evalExpression
 */
function evalExpression(Node $node, Node $expr): IO
{
  $err    = f\compose(
    pr\prettyPrint,
    f\partial(pp\printError, 'nparsable'),
    IO\IO
  );
  $concat = f\partialRight(
    f\partial(f\concat, '', '[', '"PhpParser","Node","Expr",'),
    ']'
  );

  return p\patternMatch([
    $concat('"StaticCall"')   => function () use ($node) {
      return evalFunctionCall($node);
    },
    $concat('"MethodCall"')   => function () use ($node) {
      return evalFunctionCall($node);
    },
    $concat('"BinaryOp",_')   => function () use ($node) {
      return evalFunctionCall($node);
    },
    $concat('"FuncCall"')     => function () use ($node) {
      return evalFunctionCall($node);
    },
    $concat('"Assign"')       => function () use ($node) {
      return evalAssign($node);
    },
    $concat('"Variable"')     => function () use ($node) {
      return evalVarDump($node);
    },
    '_'                       => function () use ($err, $node) {
      return $err([$node]);
    },
  ], \explode('\\', (new \ReflectionClass($expr))->getName()));
}

const evalExpression = __NAMESPACE__ . '\\evalExpression';

/**
 * @see Chemem\Bingo\Functional\Repl\Parser\parseCode
 */
function parseCode(string $code): IO
{
  $node = f\head(pr\generateAst(empty($code) ? '""' : $code));
  $expr = f\pluck($node->jsonSerialize(), 'expr');

  return p\patternMatch([
    Node\Stmt\Expression::class => function () use ($node, $expr) {
      return evalExpression($node, $expr);
    },
    '_'                         => function () use ($code) {
      return IO\IO(pp\printError('nparsable', f\concat('', '{', $code, '}')));
    },
  ], $node);
}

const parseCode = __NAMESPACE__ . '\\parseCode';

/**
 * @see Chemem\Bingo\Functional\Repl\parse
 */
function parse(string $input, array $history = []): IO
{
  $parser = p\cmatch([
    '(x:xs:_)' => function (string $cmd, string $func) use ($input) {
      return $cmd == 'doc' ?
        docFuncCmd($func) :
        parseCode($input);
    },
    '(x:_)' => function (string $cmd) use ($history, $input) {
      return p\patternMatch(
        [
          '"history"' => function () use ($history) {
            return historyCmd($history);
          },
          '"howto"'   => function () {
            return howtoCmd();
          },
          '"help"'    => function () {
            return helpCmd();
          },
          '"exit"'    => function () {
            return exitCmd();
          },
          '_'         => function () use ($input) {
            return parseCode($input);
          },
        ],
        $cmd
      );
    },
    '_'     => function () use ($input) {
      return parseCode($input);
    },
  ]);

  return $parser(\explode(' ', $input));
}

const parse = __NAMESPACE__ . '\\parse';

/**
 * @see Chemem\Bingo\Functional\Repl\exitCmd
 */
function exitCmd(): IO
{
  $genMsg = f\compose(
    f\partialRight(f\pluck, 'neutral'),
    f\partial(pp\colorOutput, 'Thanks for using the REPL')
  );

  return IO\IO($genMsg(Repl\REPL_COLORS));
}

const exitCmd = __NAMESPACE__ . '\\exitCmd';

/**
 * @see Chemem\Bingo\Functional\Repl\historyCmd
 */
function historyCmd(array $history): IO
{
  $mapper = function (array $pair) {
    return [f\head($pair) + 1, f\last($pair)];
  };

  return IO\IO(
    pp\printTable(
      ['#', 'cmd'],
      pp\customTableRows($history, $mapper),
      [2, 0]
    )
  );
}

const historyCmd = __NAMESPACE__ . '\\historyCmd';

/**
 * @see Chemem\Bingo\Functional\Repl\howToCmd
 */
function howtoCmd(): IO
{
  return IO\IO(Repl\REPL_HOW);
}

const howtoCmd = __NAMESPACE__ . '\\howtoCmd';

/**
 * @see Chemem\Bingo\Functional\Repl\helpCmd
 */
function helpCmd(): IO
{
  $color  = function (string $text) {
    return f\compose(
      f\partialRight(f\pluck, 'success'),
      f\partial(pp\colorOutput, $text)
    );
  };
  $mapper = function (array $pair) use ($color) {
    return [
      $color(f\head($pair))(Repl\REPL_COLORS),
      f\last($pair),
    ];
  };

  return IO\IO(
    pp\printTable(
      ['cmd', 'desc'],
      pp\customTableRows(Repl\REPL_HELP, $mapper),
      [2, 0]
    )
  );
}

const helpCmd = __NAMESPACE__ . '\\helpCmd';

/**
 * @see Chemem\Bingo\Functional\Repl\docFuncCmd
 */
function docFuncCmd(string $function): IO
{
  $color    = function (string $text) {
    return f\compose(
      f\partialRight(f\pluck, 'success'),
      f\partial(pp\colorOutput, $text)
    );
  };
  $mapper   = function (array $pair) use ($color) {
    return [
      $color(f\head($pair))(Repl\REPL_COLORS),
      f\last($pair),
    ];
  };

  $metadata = f\compose(
    pr\isLibraryArtifact,
    function (array $state) {
      return empty(f\head($state)) ?
        pp\printError('nexists', f\last($state)) :
        pp\printTable(
          ['item', 'info'],
          pp\customTableRows(
            pr\getUtilityMetadata(f\last(f\head($state))),
            $mapper
          ),
          [2, 0]
        );
    }
  );

  return IO\IO($metadata($function));
}

const docFuncCmd = __NAMESPACE__ . '\\docFuncCmd';

/**
 * @see Chemem\Bingo\Functional\Repl\Parser\phpExec
 */
function phpExec(string $cmd): IO
{
  $exec = f\compose(
    pp\genCmdDirective,
    f\partialRight('popen', 'r'),
    f\partialRight('fread', 8192),
    f\partial(f\concat, '', '=>'),
    IO\IO
  );

  return $exec($cmd);
}

const phpExec = __NAMESPACE__ . '\\phpExec';
