<?php

/**
 * REPL functions
 *
 * @package bingo-functional-repl
 * @author  Lochemem Bruno Michael
 * @license Apache-2.0
 */

declare(strict_types=1);

namespace Chemem\Bingo\Functional\Repl;

use React\EventLoop\LoopInterface;
use Chemem\Bingo\Functional as f;
use Chemem\Bingo\Functional\PatternMatching as p;
use Chemem\Bingo\Functional\Functors\Monads\IO;
use Chemem\Bingo\Functional\Repl as r;
use Chemem\Bingo\Functional\Repl\Printer as pp;
use Chemem\Bingo\Functional\Repl\Parser as pr;
use Clue\React\Stdio\Stdio;

/**
 * parse
 * parse REPL input and evaluate it
 *
 * parse :: String -> Object -> Object -> Array -> IO ()
 *
 * @param string        $input
 * @param Stdio         $stdio
 * @param LoopInterface $loop
 * @param array         $history
 */
function parse(
  string $input,
  Stdio $stdio,
  LoopInterface $loop,
  array $history = []
): IO {
  $parser = p\cmatch([
    '(x:xs:_)'  => function (string $cmd, string $func) use ($stdio, $loop) {
      return $cmd === 'doc' ?
        docFuncCmd($stdio, $func) :
        pr\parseCode($input, $stdio, $loop);
    },
    '(x:_)'     => function (string $cmd) use ($stdio, $loop, $input, $history) {
      return p\patternMatch(
        [
          '"history"' => function () use ($stdio, $history) {
            return historyCmd($stdio, $history);
          },
          '"howto"'   => function () use ($stdio) {
            return howtoCmd($stdio);
          },
          '"help"'    => function () use ($stdio) {
            return helpCmd($stdio);
          },
          '"exit"'    => function () use ($stdio) {
            return exitCmd($stdio);
          },
          '_'         => function () use ($stdio, $loop, $input) {
            return pr\parseCode($input, $stdio, $loop);
          },
        ],
        $cmd
      );
    },
    '_'         => function () use ($stdio, $loop, $input) {
      return pr\parseCode($input, $stdio, $loop);
    },
  ]);

  return $parser(\explode(' ', $input));
}
const parse               = __NAMESPACE__ . '\\parse';

/**
 * exitCmd
 * terminate the REPL and print termination message
 *
 * exitCmd :: Object -> IO ()
 *
 * @param Stdio $stdio
 */
function exitCmd(Stdio $stdio): IO
{
  return IO\IO(function () use ($stdio): Stdio {
    $genMsg = f\compose(
      f\partialRight(f\pluck, 'neutral'),
      f\partial(pp\colorOutput, 'Thanks for using the REPL'),
    );

    $stdio->end($genMsg(REPL_COLORS));
    IO\IO(\apcu_clear_cache())->exec(); // clear the cache upon exit

    return $stdio;
  });
}
const exitCmd             = __NAMESPACE__ . '\\exitCmd';

/**
 * historyCmd
 * print the REPL history
 *
 * historyCmd :: Object -> Array -> IO ()
 *
 * @param Stdio $stdio
 * @param array $history
 */
function historyCmd(Stdio $stdio, array $history): IO
{
  $mapper = function (array $pair) {
    return [f\head($pair) + 1, f\last($pair)];
  };

  return stdioWrite(
    $stdio,
    pp\printTable(
      ['#', 'cmd'],
      pp\customTableRows($history, $mapper),
      [2, 0],
    ),
  );
}
const historyCmd          = __NAMESPACE__ . '\\historyCmd';

/**
 * howtoCmd
 * prints a how-to guide
 *
 * howtoCmd :: Object -> IO ()
 *
 * @param Stdio $stdio
 */
function howtoCmd(Stdio $stdio): IO
{
  return stdioWrite($stdio, REPL_HOW);
}
const howtoCmd            = __NAMESPACE__ . '\\howtoCmd';

/**
 * helpDataTable
 * evaluates to a table containing all help instructions
 *
 * helpDataTable :: String
 *
 * @return string
 */
function helpDataTable(): string
{
  // set text color to green
  $color  = function (string $text) {
    return f\compose(
      f\partialRight(f\pluck, 'success'),
      f\partial(pp\colorOutput, $text)
    );
  };

  // set text color for first column entries to green
  $mapper = function (array $pair) use ($color) {
    return [
      $color(f\head($pair))(REPL_COLORS),
      f\last($pair),
    ];
  };

  return pp\printTable(
    ['cmd', 'desc'],
    pp\customTableRows(REPL_HELP, $mapper),
    [2, 0]
  );
}
const helpDataTable       = __NAMESPACE__ . '\\helpDataTable';

/**
 * helpCmd
 * prints a list of supported commands
 *
 * helpCmd :: Object -> IO ()
 *
 * @param Stdio $stdio
 */
function helpCmd(Stdio $stdio): IO
{
  $docs = f\compose(
    function ($input = null) {
      return f\concat(PHP_EOL, r\REPL_BANNER, helpDataTable($input));
    },
    f\partial(stdioWrite, $stdio)
  );

  return $docs(null);
}
const helpCmd             = __NAMESPACE__ . '\\helpCmd';

/**
 * docDataTable
 * evaluates to a table containing library artifact metadata
 *
 * docDataTable :: String -> String
 *
 * @param string $artifact
 * @return string
 */
function docDataTable(string $artifact): string
{
  $color    = function (string $text) {
    return f\compose(
      f\partialRight(f\pluck, 'success'),
      f\partial(pp\colorOutput, $text)
    );
  };

  $mapper   = function (array $pair) use ($color) {
    return [
      $color(f\head($pair))(REPL_COLORS),
      f\last($pair),
    ];
  };

  $metadata = f\compose(
    pr\isLibraryArtifact,
    function (array $state) use ($mapper) {
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

  return $metadata($artifact);
}
const docDataTable    = __NAMESPACE__ . '\\docDataTable';

/**
 * docFuncCmd
 * prints library utility metadata
 *
 * docFuncCmd :: Object -> String -> IO ()
 *
 * @param Stdio   $stdio
 * @param string  $artifact
 */
function docFuncCmd(Stdio $stdio, string $artifact): IO
{
  $docs = f\compose(docDataTable, f\partial(stdioWrite, $stdio));

  return $docs($artifact);
}
const docFuncCmd          = __NAMESPACE__ . '\\docFuncCmd';

/**
 * stdioWrite
 * asynchronously writes string to standard output device
 *
 * stdioWrite :: Object -> String -> IO ()
 *
 * @param Stdio   $stdio
 * @param string  $txt
 */
function stdioWrite(Stdio $stdio, string $txt): IO
{
  return IO\IO(
    function () use ($stdio, $txt): Stdio {
      $stdio->write($txt);

      return $stdio;
    }
  );
}
const stdioWrite          = __NAMESPACE__ . '\\stdioWrite';

/**
 * printHeaderTxt
 * asynchronously write REPL header text to standard output device
 *
 * printHeaderTxt :: Object -> String -> IO ()
 *
 * @param Stdio   $stdio
 * @param string  $frags,...
 */
function printHeaderTxt(Stdio $stdio, string ...$frags): IO
{
  return stdioWrite($stdio, pp\headerText(...$frags));
}
const printHeaderTxt      = __NAMESPACE__ . '\\printHeaderTxt';

/**
 * getInput
 * asynchronously gets input from standard input device
 *
 * getInput :: Object -> Object -> IO ()
 *
 * @param Stdio         $stdio
 * @param LoopInterface $loop
 */
function getInput(Stdio $stdio, LoopInterface $loop): IO
{
  return IO\IO(
    function () use ($stdio, $loop): Stdio {
      $stdio->on('data', function (string $line) use ($stdio, $loop) {
        $input 		    = \rtrim($line, "\r\n");
        $history 	    = $stdio->listHistory();
        // compute a unique key value
        $key          = \md5('var-key');
        // get previously cached input in multi-line sequence
        $prev         = \apcu_fetch($key);
        // function to remove spaces in parsable and storable input contexts
        $resolveInput = function ($prev, $next) {
          // function to check if final input character is a space
          $endSpace     = f\partialRight(f\endsWith, ' ');

          return f\concat(
            '',
            $prev === false ? '' : ($endSpace($prev) ? \rtrim($prev, ' ') : $prev),
            $next
          );
        };
        $current  = $resolveInput($prev, $input);

        // check if any of the input doesn't contain any salient PHP function syntactic elements
        // run it as a regular command if it doesn't
        // parse the multi-line input terminated with a semi-colon otherwise
        if (!(bool) \preg_match('/([\(\)\{\}\;\$]{1,})/', $input)) {
          // execute parser
          parse($input, $stdio, $loop, $history)->exec();

          if (!empty($input) && $input !== f\last($history)) {
            $stdio->addHistory($input);
          }
        } else {
          $contains = f\partial(f\contains, $current);

          // check if statement is syntactically incomplete
          // store, in the APCU cache, the new line along with the original key-associable APCU-stored variable contents if it isn't
          // parse the key-associable APCU contents merged with those in a new input line otherwise
          if (
            $contains('{') && !$contains('}') ||
            $contains('(') && !$contains(')') ||
            !f\endsWith($current, ';')
          ) {
            // change REPL prompt to suggest continuation
            $stdio->setPrompt('... ');
            // $prev = \apcu_fetch($key);

            // store neat input
            \apcu_store($key, $resolveInput($prev, $input));
          } else {
            parse($current, $stdio, $loop, $history)->exec();

            // only add full function logs to history
            if (!empty($current) && $current !== f\last($history)) {
              $stdio->addHistory($current);
            }

            // delete key contents from APCU cache
            \apcu_delete($key);

            // reset the REPL input prompt
            $stdio->setPrompt(r\REPL_PROMPT);
          }
        }
      });

      return $stdio;
    }
  );
}
const getInput            = __NAMESPACE__ . '\\getInput';
