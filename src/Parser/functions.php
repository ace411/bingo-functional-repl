<?php

declare(strict_types=1);

/**
 * REPL parser functions
 *
 * @author Lochemem Bruno Michael
 * @license Apache-2.0
 */

namespace Chemem\Bingo\Functional\Repl\Parser;

use \PhpParser\{NodeDumper, Error, ParserFactory};
use \React\EventLoop\LoopInterface;
use \PhpParser\{
    Node,
    NodeFinder,
    PrettyPrinter
};
use \Chemem\Bingo\Functional\{
    Algorithms as f,
    PatternMatching as p,
    Functors\Monads\State as s,
    Functors\Monads as m,
    Functors\Monads\IO
};
use \Chemem\Bingo\Functional\Repl\Printer as pr;
use \Clue\React\Stdio\Stdio;

/**
 * generateAst
 * create AST from parsable PHP syntax
 *
 * generateAst :: String -> Array
 *
 * @param string $code
 *
 * @return array
 */
const generateAst           = __NAMESPACE__ . '\\generateAst';

function generateAst(string $code): array
{
    return (new ParserFactory())
        ->create(ParserFactory::PREFER_PHP7)
        ->parse(f\concat(' ', '<?php', $code, '?>'));
}

/**
 * getFunctionMetadata
 * stores function property information in object
 *
 * getFunctionMetadata :: String -> Object
 *
 * @param string $function
 *
 * @return FuncMetadata
 */
const getFunctionMetadata   = __NAMESPACE__ . '\\getFunctionMetadata';

function getFunctionMetadata(string $function): FuncMetadata
{
    $ref                = (new \ReflectionFunction($function));
    $meta               = new FuncMetadata();

    $meta->paramCount   = $ref->getNumberOfParameters();
    $meta->params       = json_decode(json_encode($ref->getParameters()), true);

    return $meta;
}

/**
 * printFuncMetadata
 * evaluates to a tabular representation of function metadata
 *
 * printFuncMetadata :: String -> String
 *
 * @param string $function
 *
 * @return string
 */
const printFuncMetadata     = __NAMESPACE__ . '\\printFuncMetadata';

function printFuncMetadata(string $func): string
{
    $ret = f\compose(getFunctionMetadata, function (FuncMetadata $data) use ($func) {
        $paramHandler = f\compose(f\flatten, f\partial('implode', ', '));

        return pr\printer(
            ['#', 'function', 'argCount', 'argParams'],
            [['1', $func, $data->paramCount, $paramHandler($data->params)]],
            [2, 0]
        );
    });

    return $ret($func);
}

/**
 * functionExists
 * outputs two-value list useful in verifying that function exists in library
 *
 * functionExists :: String -> Array
 *
 * @param string $function
 *
 * @return array
 */
const functionExists        = __NAMESPACE__ . '\\functionExists';

function functionExists(string $function): array
{
    $ret = s\gets(function (string $func): string {
        return f\fold(function (string $final, string $nspc) use ($func) {
            $full = f\concat('', $nspc, $func);
            $final .= !function_exists($full) ? '' : $full;
            return $final;
        }, NAMESPACES, '');
    });

    return s\evalState($ret, null)($function);
}

/**
 * printPhpExpr
 * converts AST expression object to code
 *
 * printPhpExpr :: Object -> String
 *
 * @param object $expr
 *
 * @return string
 */
const printPhpExpr          = __NAMESPACE__ . '\\printPhpExpr';

function printPhpExpr(object $expr): string
{
    return (new PrettyPrinter\Standard())->prettyPrintExpr($expr);
}

/**
 * parseFuncArguments
 * converts function arguments to usable code fragments
 *
 * parseFuncArguments :: Array -> Array
 *
 * @param array $args
 *
 * @return array
 */
const parseFuncArguments    = __NAMESPACE__ . '\\parseFuncArguments';

function parseFuncArguments(array $args): array
{
    return f\map(function (object $node) {
        $val = $node->value;
        $ret = f\compose(
            f\partial(p\patternMatch, [
                Node\Expr\Variable::class   => function () use ($val) {
                    return storeFetch($val->name, printPhpExpr($val))->exec();
                },
                '_'                         => function () use ($val) {
                    return printPhpExpr($val);
                }
            ]),
            f\partial('str_replace', '\'', '"')
        );

        return $ret($val);
    }, $args);
}

/**
 * printFuncExpr
 * combines a function name and its arguments into a function call expression
 *
 * printFuncExpr :: String -> Array -> String
 *
 * @param string $func
 * @param array $args
 *
 * @return string
 */
const printFuncExpr         = __NAMESPACE__ . '\\printFuncExpr';

function printFuncExpr(string $func, array $args): string
{
    $ret = f\compose(
        f\partial('implode', ','),
        f\partialRight(f\partial(f\concat, '', '(', $func, '('), '));')
    );

    return $ret($args);
}

/**
 * handleExpression
 * parses REPL input expressions
 *
 * handleExpression :: Object -> Object -> Object -> Object -> IO()
 *
 * @param object        $code
 * @param object        $type
 * @param Stdio         $stdio
 * @param LoopInterface $loop
 *
 * @return IO
 */
const handleExpression       = __NAMESPACE__ . '\\handleExpression';

function handleExpression(
    object $code,
    object $type,
    Stdio $stdio,
    LoopInterface $loop
): IO {
    $finder = f\partial(nodeFinder, $code);
    
    return p\patternMatch([
        Node\Expr\FuncCall::class   => function () use (
            $finder,
            $stdio,
            $loop
        ) {
            return handleFuncCall(
                $finder,
                f\partialRight(evalCode, $loop, $stdio)
            );
        },
        Node\Expr\Assign::class     => function () use ($finder, $stdio) {
            return handleAssign($finder, function (bool $result) use ($stdio): IO {
                $output = function (string $msg, string $style): string {
                    $ret = f\compose(
                        f\partialRight(pr\colorOutput, $style),
                        pr\prefixOutput
                    );

                    return $ret($msg);
                };

                $stdio->write(
                    $result ?
                        $output('Assign', 'underline') :
                        $output('No Assign', COLORS['error'])
                );

                return IO\IO($stdio);
            });
        },
        Node\Expr\Variable::class   => function () use ($stdio, $finder, $loop) {
            return handleVar($finder, function (string $val) use ($loop, $stdio) {
                $ret = f\compose(
                    f\partialRight(f\partial(f\concat, '', '('), ');'),
                    f\partialRight(evalCode, $loop, $stdio)
                );

                return $ret($val);
            });
        },
        '_'                         => function () use ($stdio) {
            return IO\IO(function () use ($stdio) {
                $stdio->write(pr\printError('repl', 'Cannot parse the operation'));
                return $stdio;
            });
        }
    ], $type);
}

/**
 * handleFuncCall
 * parses a function call expression
 *
 * handleFuncCall :: (String -> Object) -> (String -> IO()) -> Bool -> IO()
 *
 * @param callable  $nodeFinder
 * @param callable  $transform
 * @param bool      $strict
 *
 * @return IO
 */
const handleFuncCall        = __NAMESPACE__ . '\\handleFuncCall';

function handleFuncCall(
    callable $nodeFinder,
    callable $transform,
    bool $strict = true
): IO {
    $stmts          = $nodeFinder(Node\Expr\FuncCall::class);
    $func           = ($stmts->name)->getLast();
    $fnExists       = s\evalState(s\gets(function (string $function) {
        return function_exists($function) ?
            $function :
            f\head(functionExists($function));
    }), null);

    [$function, ]   = $strict ? functionExists($func) : $fnExists($func);
    $ret            = f\compose(
        parseFuncArguments,
        f\partial(printFuncExpr, $function)
    );

    return IO\IO(
        !empty($function) ?
            $ret($stmts->args) :
            f\concat('', '("', pr\printError('nexists', $func), '");')
    )->bind($transform);
}

/**
 * handleAssign
 * parse a variable assignment expression
 *
 * handleAssign :: (String -> Object) -> (String -> IO()) -> IO()
 *
 * @param callable $nodeFinder
 * @param callable $transform
 *
 * @return IO
 */
const handleAssign          = __NAMESPACE__ . '\\handleAssign';

function handleAssign(callable $nodeFinder, callable $transform): IO
{
    $stmts  = $nodeFinder(Node\Expr\Assign::class);
    $expr   = $stmts->expr;
    $var    = ($stmts->var)->name;

    return storeAdd($var, p\patternMatch([
        Node\Expr\Closure::class    => function () use ($expr) {
            return printPhpExpr($expr);
        },
        Node\Expr\Array_::class     => function () use ($expr) {
            return printPhpExpr($expr);
        },
        Node\Expr\FuncCall::class   => function () use ($expr, $nodeFinder) {
            return handleFuncCall(
                $nodeFinder, 
                f\compose(f\partial('str_replace', ';', ''), IO\IO), 
                false
            )->exec();
        },
        '_'                         => function () use ($expr) {
            return isset($expr->value) ? $expr->value : printPhpExpr($expr);
        }
    ], $expr))->bind($transform);
}

/**
 * handleVar
 * prints a variable's contents if assigned a value; an error message otherwise
 *
 * handleVar :: (String -> Object) -> (String -> IO()) -> IO()
 *
 * @param callable $nodeFinder
 * @param callable $transform
 *
 * @return IO
 */
const handleVar             = __NAMESPACE__ . '\\handleVar';

function handleVar(callable $nodeFinder, callable $transform): IO
{
    $stmts  = $nodeFinder(Node\Expr\Variable::class);
    $name   = $stmts->name;

    return storeFetch($name, f\concat('', '"', $name, ' was not declared"'))
        ->bind($transform);
}

/**
 * selectCode
 * selectively parses PHP input
 *
 * selectCode :: String -> Object -> Object -> IO()
 *
 * @param string        $code
 * @param Stdio         $stdio
 * @param LoopInterface $loop
 *
 * @return IO
 */
const selectCode            = __NAMESPACE__ . '\\selectCode';

function selectCode(string $code, Stdio $stdio, LoopInterface $loop): IO
{
    [$instance, ]   = s\evalState(s\gets(f\head), null)(generateAst($code));
    $err            = IO\IO(function () use (&$stdio): callable {
        return function (string $msg) use (&$stdio): Stdio {
            $stdio->write($msg);
            return $stdio;
        };
    });

    return !$instance ?
        $err->ap(IO\IO('Cannot parse empty input')) :
        p\patternMatch([
            Node\Stmt\Expression::class => function () use ($code, $loop, $stdio, $instance) {
                return handleExpression(
                    $instance,
                    f\pluck($instance->jsonSerialize(), 'expr'),
                    $stdio,
                    $loop
                );
            },
            '_'                         => function () use ($err) {
                return $err->ap(IO\IO('Cannot parse non-expression'));
            }
        ], $instance);
}

/**
 * evalCode
 * executes PHP expression
 *
 * evalCode :: String -> Object -> Object -> IO()
 *
 * @param string        $stmt
 * @param Stdio         $stdio
 * @param LoopInterface $loop
 *
 * @return IO
 */
const evalCode              = __NAMESPACE__ . '\\evalCode';

function evalCode(string $stmt, Stdio $stdio, LoopInterface $loop): IO
{
    $expr = f\compose(
        f\partial(f\concat, '', 'print_r'),
        f\partialRight(f\partial('str_replace', '{expr}'), PARSE_EXPR)
    );

    return IO\IO($expr($stmt))
        ->map(f\partialRight(f\partial(f\concat, '', 'php -r \''), '\''))
        ->bind(f\partialRight(initProcess, $loop, $stdio));
}

/**
 * nodeFinder
 * traverses AST and returns the first instance of a specified node
 *
 * nodeFinder :: Object -> String -> Object
 *
 * @param object $code
 * @param string $nodeType
 *
 * @return object
 */
const nodeFinder            = __NAMESPACE__ . '\\nodeFinder';

function nodeFinder(object $code, string $nodeType): object
{
    $ret = f\compose(
        f\partial(f\extend, [$code]),
        f\partial('call_user_func_array', [new NodeFinder(), 'findFirstInstanceOf'])
    );

    return $ret([$nodeType]);
}

/**
 * initProcess
 * performs shell command execution
 *
 * initProcess :: String -> Object -> Object -> IO()
 *
 * @param string        $expr
 * @param Stdio         $stdio
 * @param LoopInterface $loop
 *
 * @return IO
 */
const initProcess           = __NAMESPACE__ . '\\initProcess';

function initProcess(string $cmd, Stdio $stdio, LoopInterface $loop): IO
{
    return IO\IO(function () use ($cmd, $loop, &$stdio) {
        $proc = new \React\ChildProcess\Process($cmd);
        $proc->start($loop);

        $proc->stdout->on('data', function (string $result) use (&$stdio) {
            $stdio->write(pr\prefixOutput($result));
        });

        $proc->stdout->on('error', function (\Error $exp) use (&$stdio) {
            $err = f\compose(
                f\partial('str_replace', PHP_EOL, ''),
                f\partial(pr\printWarning, 'repl')
            );

            $stdio->write($err($exp->getMessage()));
        });

        return $stdio;
    });
}

/**
 * storeAdd
 * store data in APCU cache
 *
 * storeAdd :: String -> a -> IO()
 *
 * @param string $key
 * @param mixed $data
 *
 * @return IO
 */
const storeAdd              = __NAMESPACE__ . '\\storeAdd';

function storeAdd(string $key, $val): IO
{
    return IO\IO(apcu_add($key, $val));
}

/**
 * storeFetch
 * fetch data from APCU cache if it exists; returns default value otherwise
 *
 * storeFetch :: String -> String -> IO()
 *
 * @param string $key
 * @param mixed $default
 *
 * @return IO
 */
const storeFetch            = __NAMESPACE__ . '\\storeFetch';

function storeFetch(string $key, string $default = ''): IO
{
    return IO\IO(apcu_exists($key) ? apcu_fetch($key) : $default);
}

/**
 * storeClear
 * clear APCU cache
 *
 * storeClear :: IO()
 *
 * @return IO
 */
const storeClear            = __NAMESPACE__ . '\\storeClear';

function storeClear(): IO
{
    return IO\IO(apcu_clear_cache());
}
