<?php

use PHPUnit\Framework\TestCase;
use Chemem\Bingo\Functional\{
    Repl\IO, 
    Repl\Constants,
    Algorithms as A,
    Functors\Monads\IO as IOMonad,
    Functors\Monads\Reader
};

class ReplTest extends TestCase
{
    public function reconstructRepl(callable $action)
    {
        return IOMonad::of($action)
            ->bind(IO\transformInput)
            ->flatMap(IO\printOutput);
    }

    public function runCommand($cmd, ...$args)
    {
        $cmdHandler = function ($cmd) {
            return Reader::of(
                function ($args) use ($cmd) {
                    return $cmd . (is_array($args) ? implode(' ', $args) : $args);
                }
            );
        };

        return Reader::of($cmd)
            ->withReader($cmdHandler)
            ->run($args);
    }

    public function testIdentityFunctionRunsInRepl()
    {
        $cmdRun = $this->runCommand('identity -> ', $GLOBALS['STR_TYPE']);

        $this->assertEquals(
            $this->reconstructRepl(
                function () use ($cmdRun) {
                    return $cmdRun;
                }
            ),
            A\concat(' ', Constants\REPL_RESULT, 'foo') . PHP_EOL
        );
    }

    public function testPluckFunctionRunsInRepl()
    {
        $cmdRun = $this->runCommand('pluck -> ', 2, $GLOBALS['ARR_INT']);

        $this->assertEquals(
            $this->reconstructRepl(
                function () use ($cmdRun) {
                    return $cmdRun;
                }
            ),
            A\concat(' ', Constants\REPL_RESULT, '3') . PHP_EOL
        );
    }

    public function testPickFunctionRunsInRepl()
    {
        $cmdRun = $this->runCommand('pick -> ', 'foo', $GLOBALS['ARR_STR']);

        $this->assertEquals(
            $this->reconstructRepl(
                function () use ($cmdRun) {
                    return $cmdRun;
                }
            ),
            A\concat(' ', Constants\REPL_RESULT, 'foo') . PHP_EOL
        );
    }

    public function testIsArrayOfFunctionRunsInRepl()
    {
        $cmdRun = $this->runCommand('isArrayOf -> ', $GLOBALS['ARR_STR']);

        $this->assertEquals(
            $this->reconstructRepl(
                function () use ($cmdRun) {
                    return $cmdRun;
                }
            ),
            A\concat(' ', Constants\REPL_RESULT, 'string') . PHP_EOL
        );
    }

    public function testHeadFunctionRunsInRepl()
    {
        $cmdRun = $this->runCommand('head -> ', $GLOBALS['ARR_INT']);

        $this->assertEquals(
            $this->reconstructRepl(
                function () use ($cmdRun) {
                    return $cmdRun;
                }
            ),
            A\concat(' ', Constants\REPL_RESULT, '1') . PHP_EOL
        );
    }

    public function testTailFunctionRunsInRepl()
    {
        $cmdRun = $this->runCommand('tail -> ', $GLOBALS['ARR_INT']);

        $this->assertEquals(
            $this->reconstructRepl(
                function () use ($cmdRun) {
                    return $cmdRun;
                }
            ),
            A\concat(' ', Constants\REPL_RESULT, json_encode([2,3])) . PHP_EOL
        );
    }

    public function testPartitionFunctionRunsInRepl()
    {
        $cmdRun = $this->runCommand('partition -> ', 2, $GLOBALS['ARR_INT']);

        $this->assertEquals(
            $this->reconstructRepl(
                function () use ($cmdRun) {
                    return $cmdRun;
                }
            ),
            A\concat(' ', Constants\REPL_RESULT, json_encode([[1,2], [3]])) . PHP_EOL
        );
    }

    public function testConcatFunctionRunsInRepl()
    {
        $cmdRun = $this->runCommand('concat -> ', '-', $GLOBALS['STR_TYPE'], 'bar');

        $this->assertEquals(
            $this->reconstructRepl(
                function () use ($cmdRun) {
                    return $cmdRun;
                }
            ),
            A\concat(' ', Constants\REPL_RESULT, 'foo-bar') . PHP_EOL
        );
    }

    public function testZipFunctionRunsInRepl()
    {
        $cmdRun = $this->runCommand('zip -> ', 'null', $GLOBALS['ARR_INT'], $GLOBALS['ARR_STR']);

        $this->assertEquals(
            $this->reconstructRepl(
                function () use ($cmdRun) {
                    return $cmdRun;
                }
            ),
            A\concat(
                ' ', 
                Constants\REPL_RESULT, 
                json_encode([
                    [1, 'foo'], 
                    [2, 'bar'], 
                    [3, 'baz']
                ])
            ) . PHP_EOL
        );
    }

    public function testExtendFunctionRunsInRepl()
    {
        $cmdRun = $this->runCommand('extend -> ', $GLOBALS['ARR_INT'], $GLOBALS['ARR_STR']);

        $this->assertEquals(
            $this->reconstructRepl(
                function () use ($cmdRun) {
                    return $cmdRun;
                }
            ),
            A\concat(
                ' ', 
                Constants\REPL_RESULT, 
                json_encode([1, 2, 3, 'foo', 'bar', 'baz'])
            ) . PHP_EOL
        );
    }

    public function testVersionCommandPrintsVersionNumber()
    {
        $cmdRun = $this->runCommand('version', '');

        $this->assertEquals(
            $this->reconstructRepl(
                function () use ($cmdRun) {
                    return $cmdRun;
                }
            ),
            A\concat(' ', Constants\REPL_RESULT, Constants\REPL_VERSION) . PHP_EOL
        );
    }

    public function testListCommandPrintsListOfSupportedHelpers()
    {
        $cmdRun = $this->runCommand('list', '');

        $this->assertEquals(
            $this->reconstructRepl(
                function () use ($cmdRun) {
                    return $cmdRun;
                }
            ),
            A\concat(
                ' ', 
                Constants\REPL_RESULT, 
                A\concat(
                    PHP_EOL, 
                    'The following helpers are supported:', 
                    implode(PHP_EOL, Constants\REPL_SUPPORTED_HELPERS)
                )
            ) . PHP_EOL
        );
    }

    public function testHelpCommandPrintsHowToUseInformation()
    {
        $cmdRun = $this->runCommand('help', '');

        $this->assertEquals(
            $this->reconstructRepl(
                function () use ($cmdRun) {
                    return $cmdRun;
                }
            ),
            A\concat(
                ' ', 
                Constants\REPL_RESULT, 
                A\concat(
                    PHP_EOL, 
                    Constants\REPL_COMMAND_HELPER, 
                    Constants\REPL_ARGUMENT_HELPER
                )
            ) . PHP_EOL
        );
    }
}