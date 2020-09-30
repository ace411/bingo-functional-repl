<?php

/**
 * REPL printer functions
 * 
 * @package bingo-functional-repl
 * @author  Lochemem Bruno Michael
 * @license Apache-2.0
 */

declare(strict_types=1);

namespace Chemem\Bingo\Functional\Repl\Printer;

use Chemem\Bingo\Functional\{
  Algorithms as f,
  PatternMatching as p,
  Functors\Monads\IO,
};
use Chemem\Bingo\Functional\Repl;
use JakubOnderka\PhpConsoleColor\ConsoleColor;
use Mmarica\DisplayTable;

/**
 * colorOutput
 * apply color to standard output text
 * 
 * colorOutput :: String -> String -> String
 * 
 * @param string $text
 * @param string $color
 */
function colorOutput(string $text, string $color = 'none'): string
{
  return (new ConsoleColor())->apply($color, $text);
}
const colorOutput         = __NAMESPACE__ . '\\colorOutput';

/**
 * printTable 
 * conveys list data as a table
 * 
 * printTable :: Array -> Array -> Array -> Bool -> String
 * 
 * @param array $headerRow  Table header data
 * @param array $dataRow    Table rows
 * @param array $padding    Table padding value
 * @param bool  $border     Flag to indicate whether to include borders or not
 */
function printTable(
  array $headerRow,
  array $dataRow,
  array $padding = [2, 1],
  bool $border = false
): string {
  $table = DisplayTable::create()
    ->headerRow($headerRow)
    ->dataRows($dataRow)
    ->toText()
    ->hPadding(f\head($padding))->vPadding(f\last($padding));

  return !$border ?
    $table->noBorder()->generate() :
    $table->dottedBorder()->generate();
}
const printTable          = __NAMESPACE__ . '\\printTable';

/**
 * customTableRows
 * create custom table row data via customizable handler
 * 
 * customTableRows :: Array -> (Array -> Array) -> Array
 * 
 * @param array     $data
 * @param callable  $mapper
 */
function customTableRows(array $data, ?callable $mapper): array
{
  $toRows = f\compose(f\toPairs, f\partial(
    f\map,
    !\is_null($mapper) ?
      $mapper :
      fn (array $pair): array => [f\head($pair), f\last($pair)]
  ));

  return $toRows($data);
}
const customTableRows     = __NAMESPACE__ . '\\customTableRows';

/**
 * headerText
 * returns header-styled text
 * 
 * headerText :: String -> String
 * 
 * @param string $txts,...
 */
function headerText(string ...$txts): string
{
  $ret = f\compose(
    f\partial(f\map, f\partialRight(colorOutput, 'light_blue')),
    f\identity(fn (array $colorTxt): string => f\concat(PHP_EOL, ...$colorTxt))
  );

  return $ret($txts);
}
const headerText          = __NAMESPACE__ . '\\headerText';

/**
 * printMistake
 * returns mistake-styled text
 * 
 * printMistake :: String -> String -> Bool -> String
 * 
 * @param string  $type
 * @param string  $errorMsg
 * @param bool    $isWarning
 */
function printMistake(
  string $type,
  string $errorMsg,
  bool $isWarning = true
): string {
  $ret = f\compose(
    f\partial(f\pluck, Repl\REPL_ERRORS),
    f\partial('str_replace', '{err}', $errorMsg),
    f\partialRight(colorOutput, Repl\REPL_COLORS[($isWarning ? 'warning' : 'error')])
  );

  return $ret($type);
}
const printMistake       = __NAMESPACE__ . '\\printMistake';

/**
 * printError
 * returns error-styled text
 * 
 * printError :: String -> String -> String
 * 
 * @param string $type
 * @param string $errorMsg
 */
function printError(string $type, string $errorMsg): string
{
  return printMistake($type, $errorMsg, false);
}
const printError          = __NAMESPACE__ . '\\printError';

/*
function printWarning(string $type, string $errorMsg): string
{
  return printMistake($type, $errorMsg);
}
const printWarning        = __NAMESPACE__ . '\\printWarning';
*/

/**
 * genCmdDirective
 * returns executable PHP command line directive
 * 
 * genCmdDirective :: String -> String
 * 
 * @param string $code
 */
function genCmdDirective(string $code): string
{
  $expr = f\compose(
    // remove all semi-colons at the end of code sequences
    f\partial('str_replace', ';', ''),
    // enclose the expressions in parentheses
    f\partialRight(f\partial(f\concat, '', '('), ')'),
    // print the output via the printReplOutput function
    f\partialRight(f\partial(f\concat, '', printReplOutput), ';'),
    // replace {expr} with the expression to evaluate
    f\partialRight(f\partial('str_replace', '{expr}'), Repl\PARSE_EXPRESSION),
    // replace all single quotes with double quotes
    f\partial('str_replace', '\'', '"'),
    // enclose expression in single quotes
    f\partialRight(f\partial(f\concat, '', '\''), '\''),
    // append executable PHP code to php -r command
    f\partial(f\concat, ' ', 'php', '-r'),
    // replace carriage returns and new line characters with spaces
    f\partial('str_replace', PHP_EOL, ' '),
  );

  return $expr($code);
}
const genCmdDirective   = __NAMESPACE__ . '\\genCmdDirective';

/**
 * printObject
 * returns REPL-apt string representation of an object
 * 
 * printObject :: Object -> String
 * 
 * @param object $input
 */
function printObject(object $input): string
{
  $ref    = new \ReflectionClass($input);
  $type   = colorOutput('(Object)', 'magenta');

  // store object properties in array
  $props  = f\fold(function (array $acc, object $val) use ($ref, $input) {
    $name = $val->getName();
    $prop = $ref->getProperty($name);
    $prop->setAccessible(true);

    $acc[$name] = $prop->getValue($input);

    return $acc;
  }, $ref->getProperties(), []);

  return f\concat(' ', $type, $ref->getShortName(), printArray($props, '(Properties)'));
}
const printObject         = __NAMESPACE__ . '\\printObject';

/**
 * handleBool
 * returns boolean string representations; string-coercible non-boolean values otherwise
 * 
 * handleBool :: a -> String
 * 
 * @param int|bool|string|float $val
 */
function handleBool($val): string
{
  return (string) (!\is_bool($val) ? $val : ($val ? 'True' : 'False'));
}
const handleBool          = __NAMESPACE__ . '\\handleBool';

/**
 * printArray
 * returns REPL-apt string representation of an array
 * 
 * printArray :: Array -> String
 * 
 * @param array $input
 */
function printArray(array $input, string $alias = '(Array)'): string
{
  $print = f\compose(
    // convert the array input to JSON
    f\partialRight('json_encode', JSON_PRETTY_PRINT | JSON_NUMERIC_CHECK | JSON_ERROR_INF_OR_NAN | JSON_FORCE_OBJECT),
    // remove double quotes in string values
    f\partial('str_replace', '"', ''),
    // enclose keys in square brackets
    f\partial('preg_replace', '/([\"\w\d]*)(:){1}/', '[$1] =>$3'),
    // append array to type definition
    f\partial(f\concat, ' ', colorOutput($alias, 'magenta'))
  );

  return $print($input);
}
const printArray = __NAMESPACE__ . '\\printArray';

/**
 * printOther
 * returns REPL-apt string representations of non-array and non-object values
 * 
 * printOther :: a -> String
 * 
 * @param int|float|bool|string $input
 */
function printOther($input): string
{
  $format = f\compose(
    f\compose('gettype', 'ucfirst'),
    f\partialRight(f\partial(f\concat, '', '('), ')'),
    f\partialRight(colorOutput, 'magenta'),
    f\partialRight(f\partial(f\concat, ' '), handleBool($input))
  );

  return $format($input);
}
const printOther          = __NAMESPACE__ . '\\printOther';

/**
 * printReplOutput
 * conveys REPL-apt string representations of output values
 * 
 * printReplOutput :: a -> IO ()
 * 
 * @param int|float|bool|string|object|array $input
 */
function printReplOutput($input): IO
{
  $type   = \gettype($input);
  $print  = f\compose(IO\IO, IO\_print);
  
  return $print(
    $type === 'object' ?
      printObject($input) :
      ($type === 'array' ?
        printArray($input) :
        ($type === 'resource' ?
          f\concat(' ', '(Resource)', '{}') :
          printOther($input)))
  );
}
const printReplOutput     = __NAMESPACE__ . '\\printReplOutput';
