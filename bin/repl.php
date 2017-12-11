#!/usr/bin/env php
<?php

/**
 * REPL executable file
 * 
 * @package bingo-functional-repl
 * @author Lochemem Bruno Michael
 * @license Apache 2.0
 */

require dirname(__DIR__) . '/vendor/autoload.php';

use Chemem\Bingo\Functional\Repl\{IO as Impure, Constants};
use Chemem\Bingo\Functional\Algorithms as A;
use Chemem\Bingo\Functional\Functors\Monads\IO;

/**
 * REPL exception handler
 * 
 * @param callable $exceptionHandlerFunction
 */

set_exception_handler(
    static function ($exception) {
        echo A\concat(
            ' ',
            Constants\REPL_EXCEPTION, 
            $exception->getMessage()
        );
        return false;
    }
);

/**
 * REPL function error handler
 * 
 * @param callable $errorHandlerFunction
 */

set_error_handler(
    function ($errNo, $errStr) {
        echo A\concat(' ', Constants\REPL_ERROR, $errNo, $errStr, '');
    }
);

/**
 * main function
 * 
 * @return object IO
 */

function main() : IO
{
    return Impure\getInput()
        ->bind(Impure\transformInput)
        ->map(Impure\printOutput);
}

/**
 * execute function
 * 
 * @return void 
 */

function execute() : void
{
    printf('%s', A\concat(PHP_EOL, Constants\REPL_WELCOME, ''));
    while (true) {
        main()
            ->bind(
                A\partialLeft(
                    A\curryN(2, 'printf'),
                    '%s'
                )
            )
            ->exec();
    }
}

execute();
