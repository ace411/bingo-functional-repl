<?php

/**
 * Constants for bingo-functional-repl
 * 
 * @package bingo-functional-repl
 * @author Lochemem Bruno Michael
 * @license Apache 2.0
 */

namespace Chemem\Bingo\Functional\Repl\Constants;

/**
 * @var string REPL_VERSION
 */

const REPL_VERSION = "v1.0.0";

/**
 * @var string REPL_PREFIX
 */

const REPL_PREFIX = "bingo-functional >";

/**
 * @var string REPL_ERROR
 */

const REPL_ERROR = "Error:";

/**
 * @var string REPL_RESULT
 */

const REPL_RESULT = "Result:";

/**
 * @var string REPL_EXCEPTION
 */

const REPL_EXCEPTION = "Exception:";

/**
 * @var string REPL_WELCOME
 */

const REPL_WELCOME = "Welcome to the bingo-functional REPL" . PHP_EOL . "Designed by Lochemem Bruno Michael";

/**
 * @var string REPL_COMMAND_HELPER
 */

const REPL_COMMAND_HELPER = "Command format: <function> -> <arguments>" . PHP_EOL . "Arguments are comma separated.";

/**
 * @var string REPL_ARGUMENT_HELPER
 */

const REPL_ARGUMENT_HELPER = "Supported types: String, Int, Arr" . PHP_EOL . 
    "Arg format: Type(value)" . PHP_EOL .
    "Defaults to string if not specified";

/**
 * @var array REPL_SUPPORTED_HELPERS
 */

const REPL_SUPPORTED_HELPERS = [
    'identity',
    'isArrayOf',
    'pluck',
    'pick',
    'head',
    'tail',
    'partition',
    'concat',
    'extend',
    'zip'
];

/**
 * @var string HELPER_NAMESPACE
 */

const HELPER_NAMESPACE = "Chemem\\Bingo\\Functional\\Algorithms\\";