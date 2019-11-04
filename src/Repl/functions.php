<?php

declare(strict_types=1);

/**
 * REPL actions
 *
 * @author Lochemem Bruno Michael
 * @license Apache-2.0
 */

namespace Chemem\Bingo\Functional\Repl\Repl;

use \Chemem\Bingo\Functional\{
    Algorithms as f,
    Functors\Monads\IO,
    PatternMatching as p
};
use \JakubOnderka\PhpConsoleColor\ConsoleColor;
use \Chemem\Bingo\Functional\Repl\{Parser as pr, Printer as pp};
use \React\EventLoop\LoopInterface;
use \Clue\React\Stdio\Stdio;

/**
 * evalStmt
 * parses all REPL input
 *
 * evalStmt -> String -> Object -> Object -> Array -> IO()
 *
 * @param string        $input
 * @param Stdio         $stdio
 * @param LoopInterface $loop
 * @param array         $history
 *
 * @return IO
 */
const evalStmt      = __NAMESPACE__ . '\\evalStmt';

function evalStmt(
    string $input,
    Stdio $stdio,
    LoopInterface $loop,
    array $history = []
): IO {
    $ret = f\compose(f\partial('explode', ' '), f\partial(p\patternMatch, [
        '["doc", func]'     => function (string $func) use ($stdio) {
            [$function, ] = pr\functionExists($func);

            $stdio->write(
                empty($function) ?
                    pp\printError('nexists', $func) :
                    pr\printFuncMetadata($function)
            );

            return $stdio;
        },
        '["help"]'          => function () use ($stdio) {
            $color = f\compose(
                f\toPairs,
                f\partial(f\map, function (array $pair): array {
                    $col = f\compose(
                        f\head,
                        f\partialRight(pp\colorOutput, pr\COLORS['success'])
                    );

                    return [$col($pair), f\last($pair)];
                })
            );
            $stdio->write(pp\printer(['cmd', 'desc'], $color(pr\REPL_HELP), [2, 0]));
            
            return $stdio;
        },
        '["exit"]'          => function () use ($stdio) {
            $msg = 'Thanks for using the REPL';
            $stdio->end(pp\colorOutput($msg, pr\COLORS['neutral']));
            
            return $stdio;
        },
        '["history"]'       => function () use ($stdio, $history) {
            $ret = f\compose(
                f\toPairs,
                f\partial(f\map, function (array $pair) {
                    return [(f\head($pair) + 1), f\last($pair)];
                })
            );
            $stdio->write(pp\printer(['#', 'cmd'], $ret($history), [2, 0]));
            
            return $stdio;
        },
        '["howto"]'         => function () use ($stdio) {
            $stdio->write(pr\REPL_HOW);

            return $stdio;
        },
        '_'                 => function () use ($input, $stdio, $loop) {
            $res = pr\selectCode($input, $stdio, $loop);

            return $res instanceof IO ? $res->exec() : $res;
        }
    ]), IO\IO);

    return $ret($input);
}
