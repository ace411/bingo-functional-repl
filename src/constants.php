<?php

/**
 * Essential REPL constants
 *
 * @package bingo-functional-repl
 * @author  Lochemem Bruno Michael
 * @license Apache-2.0
 */

declare(strict_types=1);

namespace Chemem\Bingo\Functional\Repl;

/**
 * @var string BASE_NAMESPACE
 */
const BASE_NAMESPACE      = 'Chemem\\Bingo\\Functional\\';

/**
 * @var string MONAD_NAMESPACE
 */
const MONAD_NAMESPACE     = BASE_NAMESPACE . 'Functors\\Monads\\';

/**
 * @var string FUNCTOR_NAMESPACE
 */
const FUNCTOR_NAMESPACE   = BASE_NAMESPACE . 'Functors\\';

/**
 * @var array BASE_NAMESPACES
 */
const BASE_NAMESPACES     = [
  MONAD_NAMESPACE . 'IO\\',
  MONAD_NAMESPACE . 'State\\',
  MONAD_NAMESPACE . 'Reader\\',
  MONAD_NAMESPACE . 'Writer\\',
  MONAD_NAMESPACE . 'Either\\',
  MONAD_NAMESPACE . 'Maybe\\',
  BASE_NAMESPACE  . 'Immutable\\',
  BASE_NAMESPACE,
  BASE_NAMESPACE  . 'Algorithms\\',
  BASE_NAMESPACE  . 'PatternMatching\\',
  BASE_NAMESPACE  . 'Functors\\Monads\\',
  FUNCTOR_NAMESPACE,
  FUNCTOR_NAMESPACE . 'Lens\\',
  FUNCTOR_NAMESPACE . 'Applicative\\',
];

/**
 * @var string PARSE_EXPRESSION
 */
const PARSE_EXPRESSION    = <<<'EXPR'
foreach (
	[
		__DIR__ . '/../autoload.php',
		__DIR__ . '/../../autoload.php',
		__DIR__ . '/../vendor/autoload.php',
		__DIR__ . '/vendor/autoload.php',
		__DIR__ . '/../../vendor/autoload.php',
	] as $file
) {
  if (\file_exists($file)) {
    \define('AUTOLOAD_PHP_FILE', $file);
    break;
  }
}
require AUTOLOAD_PHP_FILE;
{expr}
EXPR;

/**
 * @var string REPL_PROMPT
 */
const REPL_PROMPT         = '$λ>>> ';

/**
 * @var array REPL_HELP
 */
const REPL_HELP           = [
  'history' => 'Show a list of previously typed commands',
  'howto'   => 'Displays information on how to interact with the REPL',
  'help'    => 'Show a list of available commands',
  'exit'    => 'Close the REPL',
  'doc'     => 'Show the documentation for a library function',
];

/**
 * @var string REPL_HOW
 */
const REPL_HOW            = <<<'DOC'
The REPL parses PHP expressions: binary operations, assignment, variable, static, method, and function calls.
eg. $val = 12;
    $val;
    2 + 2;
    'foo-' . strtoupper('bar');
    Maybe\maybe(null, function ($x) { return $x + 2; }, Maybe::just(12))
    IO::of(fn () => file_get_contents('path/to/file'));
    IO::of(fn () => file_get_contents('path/to/file'))->map('strtoupper');
    map(function ($x) { return $x + 2; }, [3, 7]);
    match(['(x:_)' => fn ($x) => $x + 2, '_' => fn () => 0])([3]);

Assigned values are immutable by default and can be used in subsequent function calls.
eg. $list = [1, 2, 3];
    filter(fn ($x) => $x % 2 == 0, $list);

It is possible to juxtapose bingo-functional helpers with native PHP functions.
eg. dropLeft(range(2, 9), 3);
    identity(strtoupper('foo'));
DOC;

/**
 * @var array REPL_COLORS
 */
const REPL_COLORS         = [
  'success' => 'light_green',
  'neutral' => 'light_blue',
  'warning' => 'light_yellow',
  'error'   => 'light_red',
];

/**
 * @var array REPL_ERRORS
 */
const REPL_ERRORS         = [
  'nexists'     => 'Sorry, {err} does not exist',
  'nparsable'   => 'Sorry, {err} cannot be parsed',
  'nexecutable' => 'Sorry, an error - {err} - occurred',
];

/**
 * @var string REPL_BANNER
 */
const REPL_BANNER         = <<<'BANNER'
   __   _                    ___              __  _                __
  / /  (_)__  ___ ____  ____/ _/_ _____  ____/ /_(_)__  ___  ___ _/ /
 / _ \/ / _ \/ _ `/ _ \/___/ _/ // / _ \/ __/ __/ / _ \/ _ \/ _ `/ / 
/_.__/_/_//_/\_, /\___/   /_/ \_,_/_//_/\__/\__/_/\___/_//_/\_,_/_/  
            /___/                                                    
BANNER;
