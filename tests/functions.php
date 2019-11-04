<?php

declare(strict_types=1);

namespace Chemem\Bingo\Functional\Repl\Tests;

use \Chemem\Bingo\Functional\{
    Algorithms as f,
    Functors\Monads\IO,
    Functors\Monads\State as s,
    PatternMatching as p
};
use Chemem\Bingo\Functional\Repl\{
    Parser as pr,
    Printer as pp
};

const repl              = __NAMESPACE__ . '\\repl';

function repl(string $input, array $history = []): IO
{
    $ret = f\compose(f\partial('explode', ' '), f\partial(p\patternMatch, [
        '["doc", func]'     => function (string $func) {

            [$function, ] = pr\functionExists($func);
            return IO\IO(empty($function) ?
                pp\printError('nexists', $func) :
                pr\printFuncMetadata($function));
        },
        '["help"]'          => function () {
            $color = f\compose(f\toPairs, f\partial(f\map, function (array $pair): array {
                $col = f\compose(f\head, f\partialRight(pp\colorOutput, pr\COLORS['success']));
                return [$col($pair), f\last($pair)];
            }));

            return IO\IO(pp\printer(['cmd', 'desc'], $color(pr\REPL_HELP), [2, 0]));
        },
        '["history"]'       => function () use ($history) {
            $ret = f\compose(f\toPairs, f\partial(f\map, function (array $pair) {
                return [(f\head($pair) + 1), f\last($pair)];
            }));

            return IO\IO(pp\printer(['#', 'cmd'], $ret($history), [2, 0]));
        },
        '["exit"]'          => function () {
            return IO\IO(pp\colorOutput('Thanks for using the REPL', pr\COLORS['neutral']));
        },
        '["howto"]'         => function () {
            return IO\IO(pr\REPL_HOW);
        },
        '_'                 => function () use ($input) {
            return handleCode($input);
        }
    ]));
    return $ret($input);
}

const handleCode        = __NAMESPACE__ . '\\handleCode';

function handleCode(string $input): IO
{
    [$instance, ]   = s\evalState(s\gets(f\head), null)(pr\generateAst($input));
    $err            = IO\IO(function () {
        return function (string $msg) {
            return $msg;
        };
    });
    return !$instance ?
        $err->ap(IO\IO('Cannot parse empty input')) :
        p\patternMatch([
            Node\Stmt\Expression::class     => function () use ($instance) {
                return handleExpression($instance, f\pluck($instance->jsonSerialize(), 'expr'));
            },
            '_'                             => function () use ($err) {
                return $err->ap(IO\IO('Cannot parse non-expression'));
            }
        ], $instance);
}

const handleExpression   = __NAMESPACE__ . '\\handleExpression';

function handleExpression(string $code, object $type): IO
{
    $finder = f\partial(pr\nodeFinder, $code);
    return p\patternMatch([
        Node\Expr\FuncCall::class       => function () use ($finder) {
            return pr\handleFuncCall($finder, evalCode);
        },
        Node\Expr\Assign::class         => function () use ($finder) {
            return pr\handleAssign($finder, function (bool $result) {
                return IO\IO($result ? 'Assign' : 'No Assign');
            });
        },
        Node\Expr\Variable::class       => function () use ($finder) {
            return pr\handleVar($finder, f\compose(f\identity, IO\IO));
        },
        '_'                             => function () {
            return IO\IO('Cannot parse the operation');
        }
    ], $type);
}

const evalCode          = __NAMESPACE__ . '\\evalCode';

function evalCode(string $stmt): IO
{
    $expr = f\compose(f\partial('str_replace', '"', '\"'), f\partial(f\concat, '', 'print_r'), f\partialRight(f\partial('str_replace', '{expr}'), PARSE_EXPR));
    
    return IO\IO($expr($stmt))
        ->map(f\partialRight(f\partial(f\concat, '', 'php -r "'), '"'))
        ->bind(initProcess);
}

const initProcess       = __NAMESPACE__ . '\\initProcess';

function initProcess(string $expr): IO
{
    return IO\IO(function () use ($expr) {
        $exec = @exec(escapeshellcmd($expr), $output);
        return f\head($output);
    });
}
