<?php

declare(strict_types=1);

namespace Chemem\Bingo\Functional\Repl\Tests;

use Eris\Generator;
use Chemem\Bingo\Functional\Repl;
use Chemem\Bingo\Functional\Functors\Monads\IO;

class ReplTest extends \PHPUnit\Framework\TestCase
{
  use \Eris\TestTrait;

  /**
   * @test
   */
  public function historyCmdPrintsReplHistory()
  {
    $this
      ->forAll(
        Generator\tuple(
          Generator\constant('history'),
          Generator\constant('doc foo'),
          Generator\constant('map("strtoupper", ["foo", "bar", "baz"])'),
        )
      )
      ->then(function (array $history) {
        $output = historyCmd($history);

        $this->assertInstanceOf(IO::class, $output);
        $this->assertIsString($output->exec());
      });
  }

  /**
   * @test
   */
  public function howtoCmdPrintsGuideToReplUsage()
  {
    $this
      ->forAll(
        Generator\constant(howtoCmd),
      )
      ->then(function (string $func) {
        $howto = $func();

        $this->assertInstanceOf(IO::class, $howto);
        $this->assertEquals(Repl\REPL_HOW, $howto->exec());
      });
  }

  /**
   * @test
   */
  public function helpCmdPrintsHelpInformation()
  {
    $this
      ->forAll(
        Generator\constant(helpCmd),
      )
      ->then(function (string $func) {
        $help = $func();

        $this->assertInstanceOf(IO::class, $help);
        $this->assertIsString($help->exec());
        $this->assertRegExp('/cmd+/', $help->exec());
        $this->assertRegExp('/desc+/', $help->exec());
      });
  }

  /**
   * @test
   */
  public function docFuncCmdPrintsLibraryFunctionMetadata()
  {
    $this
      ->forAll(
        Generator\elements(
          'foo',
          'map',
          'filter',
          'State',
          'Collection',
        ),
      )
      ->then(function (string $entity) {
        $doc = docFuncCmd($entity);

        $this->assertInstanceOf(IO::class, $doc);
        $this->assertIsString($doc->exec());
      });
  }

  /**
   * @test
   */
  public function parseSimulatesRepl()
  {
    $this
      ->forAll(
        Generator\elements(
          'foo',
          'help',
          'howto',
          'history',
          'doc map',
          '$x = 12',
          '12 - 3',
          '"foo" . "bar"',
          '"foo-" . strtoupper("baz")',
          'map(fn ($x) => $x ** 2, range(5, 11))',
          'map(function ($x) { return $x . "foo"; }, ["baz", "bar"])',
          'Collection::from(["foo", "bar"])->tail()',
          'exit',
        ),
      )
      ->then(function (string $cmd) {
        $repl = parse($cmd, [$cmd]);

        $this->assertInstanceOf(IO::class, $repl);
        $this->assertIsString($repl->exec());
      });
  }
}
