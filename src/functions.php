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

use \React\EventLoop\LoopInterface;
use \Chemem\Bingo\Functional\Repl\{
  Printer as pp,
  Parser as pr,
};
use \Chemem\Bingo\Functional\{
  Algorithms as f,
  PatternMatching as p,
  Functors\Monads\IO,
};
use \Clue\React\Stdio\Stdio;

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
  $parser = p\match([
    '(x:xs:_)'  => fn (string $cmd, string $func) => (
      $cmd === 'doc' ?
        docFuncCmd($stdio, $func) :
        pr\parseCode($input, $stdio, $loop)
    ),
    '(x:_)'     => fn (string $cmd) => p\patternMatch([
      '"history"' => fn () => historyCmd($stdio, $history),
      '"howto"'   => fn () => howtoCmd($stdio),
      '"help"'    => fn () => helpCmd($stdio),
      '"exit"'    => fn () => exitCmd($stdio),
      '_'         => fn () => pr\parseCode($input, $stdio, $loop),
    ], $cmd),
    '_'         => fn () => pr\parseCode($input, $stdio, $loop),
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
  $mapper = fn (array $pair) => [f\head($pair) + 1, f\last($pair)];

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
  $color  = fn (string $text): callable => f\compose(
    f\partialRight(f\pluck, 'success'),
    f\partial(pp\colorOutput, $text)
  );

  // set text color for first column entries to green
  $mapper = fn (array $pair): array => [
    $color(f\head($pair))(REPL_COLORS),
    f\last($pair),
  ];

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
  $docs = f\compose(helpDataTable, f\partial(stdioWrite, $stdio));

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
  $color    = fn (string $text): callable => f\compose(
    f\partialRight(f\pluck, 'success'),
    f\partial(pp\colorOutput, $text)
  );
  $mapper   = fn (array $pair): array => [
    $color(f\head($pair))(REPL_COLORS),
    f\last($pair),
  ];

  $metadata = f\compose(
    pr\isLibraryArtifact,
    fn (array $state): string => empty(f\head($state)) ?
      pp\printError('nexists', f\last($state)) :
      pp\printTable(
        ['item', 'info'],
        pp\customTableRows(
          pr\getUtilityMetadata(f\last(f\head($state))),
          $mapper
        ),
        [2, 0]
      )
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
  return IO\IO(function () use ($stdio, $txt): Stdio {
    $stdio->write($txt);

    return $stdio;
  });
}
const stdioWrite          = __NAMESPACE__ . '\\stdioWrite';

/*
function promptUser(Stdio $stdio, string $prompt): IO
{
	return IO\IO(function () use ($stdio, $prompt) {
		$stdio->setPrompt($prompt);
		return $stdio;
	});
}
const promptUser          = __NAMESPACE__ . '\\promptUser';
*/

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

/*
function handleReplError(Stdio $stdio): IO
{
	return IO\IO(function () use ($stdio) {
		$stdio->on('error', function (\Throwable $err) use ($stdio) {
			$genMsg = f\compose(
				f\partialRight(f\pluck, 'nexecutable'),
				f\partial('str_replace', '{err}', $err->getMessage())
			);
			
			$stdio->write($genMsg(Repl\REPL_ERRORS));
		});

		return $stdio;
	});
}
const handleReplError     = __NAMESPACE__ . '\\handleReplError';
*/

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
  return IO\IO(function () use ($stdio, $loop): Stdio {
    $stdio->on('data', function (string $line) use ($stdio, $loop) {
      $input 		= \rtrim($line, "\r\n");
      $history 	= $stdio->listHistory();

      // evaluate the input
      parse($input, $stdio, $loop, $history)
				->exec();

      if (!empty($input) && $input !== f\last($history)) {
        $stdio->addHistory($input);
      }
    });

    return $stdio;
  });
}
const getInput            = __NAMESPACE__ . '\\getInput';
