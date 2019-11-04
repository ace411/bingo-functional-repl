<?php

declare(strict_types=1);

namespace Chemem\Bingo\Functional\Repl\Tests;

use \Eris\Generator;
use \Chemem\Bingo\Functional\Repl\{
    Repl as r,
    IO as IO_,
    Printer as pp,
    Parser as pr
};
use \Chemem\Bingo\Functional\{
    Algorithms as f,
    Functors\Monads\IO
};

class ReplTest extends \PHPUnit\Framework\TestCase
{
    use \Eris\TestTrait;

    public function tearDown(): void
    {
        pr\storeClear();
    }

    public function testReplParsesDocCommand()
    {
        $this->forAll(Generator\map(f\partial(f\concat, ' ', 'doc'), Generator\elements(
            'map',
            'zip',
            'filter',
            'identity'
        )))->then(function (string $cmd) {
            $res = repl($cmd);

            $this->assertInstanceOf(IO::class, $res);
            $this->assertIsString($res->exec());
        });
    }

    public function testReplParsesHistoryCommand()
    {
        $this->forAll(Generator\tuple(
            Generator\elements('howto', 'identity("foo")', 'history', 'help'),
            Generator\constant('filter(function ($x) { return $x > 3; }, [2, 6])')
        ))->then(function (array $history) {
            $output = repl('history', $history);

            $this->assertIsString($output->exec());
            $this->assertRegExp('/[#\a-z\A-Z]+/', $output->exec());
        });
    }

    public function testReplParsesHowToCommand()
    {
        $this->forAll(Generator\constant('howto'))->then(function (string $cmd) {
            $output = repl($cmd);

            $this->assertInstanceOf(IO::class, $output);
            $this->assertIsString($output->exec());
            $this->assertEquals(pr\REPL_HOW, $output->exec());
        });
    }

    public function testReplParsesExitCommand()
    {
        $this->forAll(Generator\constant('exit'))->then(function (string $cmd) {
            $output = repl($cmd);

            $this->assertInstanceOf(IO::class, $output);
            $this->assertIsString($output->exec());
            $this->assertEquals(
                pp\colorOutput(
                    'Thanks for using the REPL',
                    pr\COLORS['neutral']
                ),
                $output->exec()
            );
        });
    }

    public function testReplParsesAssignmentExpressions()
    {
        $this->forAll(Generator\elements(
            '$a = 12',
            '$b = function ($x) { return $x * 3; }',
            '$c = "foo"',
            '$d = 3.521',
            '$e = new StdClass("foo-bar")'
        ))->then(function (string $expr) {
            $output = repl($expr);

            $this->assertInstanceOf(IO::class, $output);
            $this->assertIsString($output->exec());
        });
    }

    public function testReplParsesFuncCallExpressions()
    {
        $this->forAll(Generator\elements(
            'map("strtoupper", ["foo", "bar"])',
            'filter(function ($x) { return $x % 2 == 0; }, [3, 7, 8])',
            'identity("foo")'
        ))->then(function (string $expr) {
            $output = repl($expr);

            $this->assertInstanceOf(IO::class, $output);
            $this->assertIsString($output->exec());
        });
    }
}
