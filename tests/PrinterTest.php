<?php

declare(strict_types=1);

namespace Chemem\Bingo\Functional\Repl\Tests;

\error_reporting(0);

use Eris\Generator;
use Chemem\Bingo\Functional as f;
use Chemem\Bingo\Functional\Functors\Monads\Maybe;
use Chemem\Bingo\Functional\Repl\Printer as pp;

class PrinterTest extends \PHPUnit\Framework\TestCase
{
  use \Eris\TestTrait;

  /**
   * @test
   */
  public function colorOutputEvaluatesToColoredStringOutput()
  {
    $this
      ->forAll(
        Generator\suchThat(
          function (string $str) {
            return \mb_strlen($str, 'utf-8') >= 1;
          },
          Generator\string()
        ),
        Generator\elements(
          'light_yellow',
          'light_green',
          'light_blue',
          'light_red'
        )
      )
      ->then(function (string $str, string $color) {
        $color = pp\colorOutput($str, $color);

        $this->assertIsString($color);
      });
  }

  /**
   * @test
   */
  public function printTableEvaluatesToTabularString()
  {
    $this
      ->forAll(
        Generator\tuple(
          Generator\elements('header-x', 'header-y'),
          Generator\elements('another-header-x', 'another-header-y')
        ),
        Generator\tuple(
          Generator\tuple(
            Generator\names(),
            Generator\choose(1, 20)
          )
        ),
        Generator\tuple(
          Generator\choose(1, 4),
          Generator\choose(1, 4)
        )
      )
      ->then(function (array $header, array $data, array $padding) {
        $print    = f\partial(pp\printTable, $header, $data, $padding);
        $border   = $print(true);
        $noBorder = $print(false);

        $this->assertIsString($border);
        $this->assertIsString($noBorder);
      });
  }

  /**
   * @test
   */
  public function customTableRowsOutputsTableDataAsArray()
  {
    $this
      ->forAll(
        Generator\associative([
          'name'    => Generator\names(),
          'number'  => Generator\choose(1, 10),
        ])
      )
      ->then(function (array $data) {
        $plain      = pp\customTableRows($data, null);
        $wcallback  = pp\customTableRows(
          $data,
          function (array $pair) {
            return [\strtoupper(f\head($pair)), f\last($pair)];
          }
        );

        $this->assertIsArray($plain);
        $this->assertIsArray($wcallback);
      });
  }

  /**
   * @test
   */
  public function headerTextPrintsHeaderStyledText()
  {
    $this
      ->forAll(
        Generator\tuple(
          Generator\elements('foo', 'bar'),
          Generator\elements('bar', 'baz')
        )
      )
      ->then(function (array $items) {
        $text = pp\headerText(...$items);

        $this->assertIsString($text);
      });
  }

  /**
   * @test
   */
  public function printErrorReturnsErrorStyledText()
  {
    $this
      ->forAll(
        Generator\elements('nexists', 'nparsable', 'nexecutable'),
        Generator\elements(
          'spike()',
          'const foo = fn ($x) => $x + 2',
          '[$x,] = range(1, 2)'
        )
      )
      ->then(function (string $err, string $msg) {
        $res = pp\printError($err, $msg);

        $this->assertIsString($res);
      });
  }

  /**
   * @test
   */
  public function genCmdDirectiveOutputsValidCommandLineDirective()
  {
    $this
      ->forAll(
        Generator\elements(
          '$add = fn ($x, $y) => $x + $y',
          'identity(12)',
          'Maybe::just(2)->filter(fn ($x) => $x % 2 == 0)',
          'Collection::from(range(1, 5))->map(fn ($x) => $x ** 2)'
        )
      )
      ->then(function (string $expr) {
        $directive = pp\genCmdDirective($expr);

        $this->assertIsString($directive);
      });
  }

  /**
   * @test
   */
  public function printObjectOutputsObjectInfoAsString()
  {
    $this
      ->forAll(
        Generator\elements('foo', 'bar', 'baz')
      )
      ->then(function (string $item) {
        $std    = pp\printObject(new \StdClass($item));
        $monad  = pp\printObject(Maybe::just($item)->filter('is_string'));
        $regexp = '/(Object){1}([]){1}([\w\s]+)/';

        $this->assertIsString($std);
        $this->assertIsString($monad);
        $this->assertRegExp($regexp, $std);
        $this->assertRegExp($regexp, $monad);
      });
  }

  /**
   * @test
   */
  public function printArrayOutputsArrayDataAsString()
  {
    $this
      ->forAll(
        Generator\associative([
          'name'  => Generator\names(),
          'age'   => Generator\choose(14, 40),
        ])
      )
      ->then(function (array $arr) {
        $res = pp\printArray($arr);

        $this->assertIsString($res);
        $this->assertRegExp('/name+/', $res);
        $this->assertRegExp('/age+/', $res);
        $this->assertRegExp('/(Array){1}([\s\w\W]*)/', $res);
      });
  }

  /**
   * @test
   */
  public function printOtherOutputsStringRepresentationsOfNonArrayAndNonObjectValues()
  {
    $this
      ->forAll(
        Generator\choose(1, 5),
        Generator\bool(),
        Generator\string()
      )
      ->then(function (int $intv, bool $boolv, string $strv) {
        $str  = pp\printOther($strv);
        $int  = pp\printOther($intv);
        $bool = pp\printOther($boolv);

        $this->assertIsString($str);
        $this->assertRegExp('/(String){1}([\s\w\W]*)/', $str);
        $this->assertIsString($int);
        $this->assertRegExp('/(Integer){1}([\s\d]*)/', $int);
        $this->assertIsString($bool);
        $this->assertRegExp('/(Boolean){1}([\w]*)/', $bool);
      });
  }
}
