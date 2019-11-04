<?php

declare(strict_types=1);

/**
 * Important REPL state
 * 
 * @author Lochemem Bruno Michael
 * @license Apache-2.0
 */

namespace Chemem\Bingo\Functional\Repl\Parser;

/**
 * base namespace
 * 
 * @var string NSP_BASE
 */
const NSP_BASE      = 'Chemem\\Bingo\\Functional\\';

/**
 * bingo-functional function namespaces
 * 
 * @var array NAMESPACES
 */
const NAMESPACES    = [
    NSP_BASE . 'Algorithms\\',
    NSP_BASE . 'PatternMatching\\',
    NSP_BASE . 'Functors\\Monads\\',
    NSP_BASE . 'Functors\\Monads\\IO\\',
    NSP_BASE . 'Functors\\Monads\\State\\',
    NSP_BASE . 'Functors\\Monads\\Writer\\',
    NSP_BASE . 'Functors\\Monads\\Reader\\',
    NSP_BASE . 'Functors\\Monads\\ListMonad\\',
];

/**
 * PHP code execution directive
 * 
 * @var string PARSE_EXPR
 */
const PARSE_EXPR    = 'require __DIR__ . "/vendor/autoload.php"; {expr}';

/**
 * REPL input prompt
 * 
 * @var string REPL_PROMPT
 */
const REPL_PROMPT   = '$λ>>> ';

/**
 * REPL help information
 * 
 * @var array REPL_HELP
 */
const REPL_HELP     = [
    'history'   => 'Show a list of previously typed commands',
    'howto'     => 'Displays information on how to interact with the REPL',
    'help'      => 'Show a list of available commands',
    'exit'      => 'Close the REPL',
    'doc'       => 'Show the documentation for a library function'
];

/**
 * REPL how-to-use information
 * 
 * @var string REPL_HOW
 */
const REPL_HOW      = <<<'DOC'
The REPL parses PHP expressions: assignment and function calls.

eg. $x = 12
    map(function ($x) { return $x + 2; }, [3, 7])

Assigned values can be used in subsequent function calls.

eg. $x = [1, 2, 3]
    filter(function ($x) { return $x % 2 == 0; }, $x)

*** Integers, floats, strings, function calls, and arrays are assignable. 
DOC;

/**
 * REPL colors
 * 
 * @var array COLORS
 */
const COLORS        = [
    'success'   => 'light_green',
    'neutral'   => 'light_blue',
    'warning'   => 'light_yellow',
    'error'     => 'light_red'
];

/**
 * REPL error message templates
 * 
 * @var array ERRORS
 */
const ERRORS        = [
    'nexists'   => 'Sorry, {err} does not exist.',
    'repl'      => 'Sorry, an error - {err} - occurred.'
];
