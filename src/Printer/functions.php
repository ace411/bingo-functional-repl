<?php

declare(strict_types=1);

/**
 * String print functions
 *
 * @author Lochemem Bruno Michael
 * @license Apache-2.0
 */

namespace Chemem\Bingo\Functional\Repl\Printer;

use \Mmarica\DisplayTable;
use \Chemem\Bingo\Functional\Algorithms as f;
use \JakubOnderka\PhpConsoleColor\ConsoleColor;
use \Chemem\Bingo\Functional\Repl\Parser as p;

/**
 * colorOutput
 * add style to string data
 *
 * colorOutput :: String -> String -> String
 *
 * @param string $text
 * @param string $color
 *
 * @return string
 */
const colorOutput   = __NAMESPACE__ . '\\colorOutput';

function colorOutput(string $text, string $color = 'none'): string
{
    return (new ConsoleColor())->apply($color, $text);
}

/**
 * printer
 * print list data as table
 *
 * printer :: Array -> Array -> Array -> Bool -> String
 *
 * @param array $headerRow
 * @param array $dataRow
 * @param array $padding
 * @param bool  $border
 *
 * @return string
 */
const printer       = __NAMESPACE__ . '\\printer';

function printer(
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

/**
 * prefixOutput
 * prefix console output string with =>
 *
 * prefixOutput :: String -> String
 *
 * @param string $output
 *
 * @return string
 */
const prefixOutput  = __NAMESPACE__ . '\\prefixOutput';

function prefixOutput(string $output): string
{
    $ret = f\compose(
        f\partialRight(colorOutput, 'bold'),
        f\partialRight(f\partial(f\concat, ' '), $output)
    );

    return $ret('=>');
}

/**
 * headerText
 * Merge strings into new-line-separated text block
 *
 * headerText :: String -> String -> String
 *
 * @param string $txts,...
 *
 * @return string
 */
const headerText    = __NAMESPACE__ . '\\headerText';

function headerText(string ...$txts): string
{
    $ret = f\compose(
        f\partial(f\map, f\partialRight(colorOutput, 'light_blue')),
        f\identity(function (array $colorTxt): string {
            return f\concat(PHP_EOL, ...$colorTxt);
        })
    );

    return $ret($txts);
}

/**
 * printMsg
 * Prints styled fault message
 *
 * printMsg :: String -> String -> Bool -> String
 *
 * @param string $type
 * @param string $errorMsg
 * @param bool $isWarning
 *
 * @return string
 */
function printMsg(
    string $type,
    string $errorMsg,
    bool $isWarning = true
): string {
    $ret = f\compose(
        f\partial(f\pluck, p\ERRORS),
        f\partial('str_replace', '{err}', $errorMsg),
        f\partialRight(
            colorOutput,
            p\COLORS[($isWarning ? 'warning' : 'error')]
        )
    );

    return $ret($type);
}


/**
 * printError
 * Prints styled error message
 *
 * printError :: String -> String -> Bool -> String
 *
 * @param string $type
 * @param string $errorMsg
 *
 * @return string
 */
const printError    = __NAMESPACE__ . '\\printError';

function printError(string $type, string $errorMsg): string
{
    return printMsg($type, $errorMsg);
}

/**
 * printWarning
 * Prints styled warning
 *
 * printWarning :: String -> String -> Bool -> String
 *
 * @param string $type
 * @param string $errorMsg
 *
 * @return string
 */
const printWarning  = __NAMESPACE__ . '\\printWarning';

function printWarning(string $type, string $errorMsg): string
{
    return printMsg($type, $errorMsg, false);
}
