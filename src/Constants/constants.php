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

const REPL_COMMAND_HELPER = "The REPL is built atop a php-parser. Idiomatic PHP code is accepted but" . PHP_EOL .
    "should be exclusive to the helper functions and monads in the bingo-functional library." . PHP_EOL .
    "Check out the bingo-functional docs at https://ace411.github.io/bingo-functional";

/**
 * @var array REPL_CONSTANTS
 */

const REPL_CONSTANTS = [
    'help' => REPL_COMMAND_HELPER,
    'version' => '1.0.0'
];

/**
 * @var string HELPER_NAMESPACE
 */

const HELPER_NAMESPACE = "Chemem\\Bingo\\Functional\\Algorithms\\";

/**
 * @var string FUNCTOR_NAMESPACE
 */

const FUNCTOR_NAMESPACE = 'Chemem\\Bingo\\Functional\\Functors\\';

/**
 * @var array FUNCTORS
 */

const FUNCTORS = [
    'IO' => 'Monads\\',
    'State' => 'Monads\\',
    'Writer' => 'Monads\\',
    'Reader' => 'Monads\\',
    'ListMonad' => 'Monads\\',
    'Either' => 'Either\\',
    'Maybe' => 'Maybe\\',
    'Applicative' => 'Applicatives\\'
];