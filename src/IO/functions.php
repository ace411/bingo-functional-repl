<?php

/**
 * IO functions for bingo-functional-repl
 * 
 * @package bingo-functional-repl
 * @author Lochemem Bruno Michael
 * @license Apache 2.0
 */

namespace Chemem\Bingo\Functional\Repl\IO;

use Chemem\Bingo\Functional\Repl\Constants;
use Chemem\Bingo\Functional\{Algorithms as A, Common\Callbacks as CB};
use FunctionalPHP\PatternMatching as PM;
use Chemem\Bingo\Functional\Functors\{
    Monads\IO, 
    Monads\Reader, 
    Monads\State, 
    Monads\Writer,
    Either\Either,
    Either\Left
};

/**
 * getInput function
 * 
 * @return object IO
 */

const getInput = "Chemem\\Bingo\\Functional\\Repl\\IO\\getInput";

function getInput() : IO
{
    return IO::of(
        function () {
            echo A\concat(' ', Constants\REPL_PREFIX, '');
            return true;
        }
    )
        ->map(
            function ($output) {
                return trim(fgets(STDIN));
            }
        );
}

/**
 * transformInput function
 * 
 * @param mixed $input
 * @return mixed $output
 */

const transformInput = "Chemem\\Bingo\\Functional\\Repl\\IO\\transformInput";

function transformInput($input)
{
    return PM\match(
        [
            '[helper, args]' => function ($helper, $args) {
                $helperFn = helperRequiresCb($helper);
                $newArgs = parseArgs(explode(" ", $args));

                return !empty($newArgs) && is_callable($helperFn) ?
                    call_user_func_array($helperFn, $newArgs) :
                    A\concat(' ', Constants\REPL_ERROR, 'Could not call ' . $helperFn);
            },
            '["version"]' => function () {
                return Constants\REPL_VERSION;
            },
            '["help"]' => function () {
                return A\concat(
                    PHP_EOL, 
                    Constants\REPL_COMMAND_HELPER, 
                    Constants\REPL_ARGUMENT_HELPER
                );
            },
            '["list"]' => function () {
                return A\concat(
                    PHP_EOL,
                    'The following helpers are supported:',
                    implode(PHP_EOL, Constants\REPL_SUPPORTED_HELPERS)
                );
            },
            '_' => function () {
                return "Nothing useful provided!";
            }
        ],
        explode(" -> ", stripslashes($input))
    );
}

/**
 * printOutput function
 * 
 * @param mixed $output
 * @return string $result  
 */

const printOutput = "Chemem\\Bingo\\Functional\\Repl\\IO\\printOutput";

function printOutput($output) : string
{
    list($result, $log) = Writer::of($output, 'Result: ')
        ->run();
    
    return $log . (is_array($result) ? json_encode($result) : $result) . PHP_EOL;
}

/**
 * helperRequiresCb function
 * 
 * @param callable $helper
 * @return callable $modifiedHelper
 */

const helperRequiresCb = "Chemem\\Bingo\\Functional\\Repl\\IO\\helperRequiresCb";

function helperRequiresCb($helper)
{
    return PM\match(
        [
            '"isArrayOf"' => function () {
                return A\partialRight(
                    A\curryN(2, A\isArrayOf),
                    CB\emptyArray
                );
            },
            '"pluck"' => function () {
                return A\partialRight(
                    A\curryN(3, A\pluck),
                    CB\invalidArrayKey
                );
            },
            '"pick"' => function () {
                return A\partialRight(
                    A\curryN(3, A\pick),
                    CB\invalidArrayValue
                );
            },
            'func' => function ($func) {
                return Constants\HELPER_NAMESPACE . $func;
            },
            '_' => function () {
                return A\constantFunction("No Input Provided");
            }
        ],
        $helper
    );
}

/**
 * resolveInputArgs function
 * 
 * @param mixed $arg
 * @return mixed $modifiedArg
 */

const resolveInputArgs = "Chemem\\Bingo\\Functional\\Repl\\IO\\resolveInputArgs";

function resolveInputArgs($arg)
{
    return PM\match(
        [
            '["Arr", input]' => function ($input) {
                return array_map(
                    function ($val) {
                        return is_numeric($val) ? (int) $val : $val;
                    }, 
                    explode(',', $input)
                );
            },
            '["Int", input]' => function ($input) {
                return (int) $input;
            },
            '["Str", input]' => function ($input) {
                return (string) $input;
            },
            '[input]' => function ($input) {
                return $input == "null" ? null : $input;
            },
            '_' => function () {
                return A\identity("1");
            }
        ],
        explode('(', str_replace(')', '', $arg))
    );
}

/**
 * parseArgs function
 * 
 * @param array $args
 * @return array $modifiedArgs
 */

const parseArgs = "Chemem\\Bingo\\Functional\\Repl\\IO\\parseArgs";

function parseArgs(array $args) : array
{
    return Either::right($args)
        ->filter(
            function ($args) {
                return !empty($args);
            },
            ['']
        )
        ->map(
            function ($val) {
                return $val instanceof Left ?
                    $val->getLeft() :
                    $val;
            }
        )
        ->flatMap(
            function ($val) {
                return array_map(
                    function ($arr) {
                        return preg_match("/([a-zA-Z]*)([(\)]*)([a-zA-Z0-9\,\ \)]*)/", $arr) ? 
                            resolveInputArgs($arr) : 
                            $val;
                    },
                    $val
                );
            }
        );
}
