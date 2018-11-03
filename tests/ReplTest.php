<?php

use PHPUnit\Framework\TestCase;
use Chemem\Bingo\Functional\{
    Repl\IO,
    Repl\Constants,
    Algorithms as A,
    Functors\Monads\IO as IOMonad,
    Functors\Monads\Reader
};
use FunctionalPHP\PatternMatching as PM;

class ReplTest extends TestCase
{
    public function mimicRepl(callable $action)
    {
        return IOMonad::of($action)
            ->bind(IO\transformInput)
            ->flatMap(IO\printOutput);
    }

    public function output(string $condition, string $result)
    {
        return PM\match(
            [
                '"success"' => function () use ($result) {
                    return A\concat(' ', Constants\REPL_RESULT, $result . PHP_EOL);
                },
                '"failure"' => function () use ($result) {
                    return A\concat(' ', Constants\REPL_ERROR, $result . PHP_EOL);
                },
                '_' => function () {
                    return '';
                }
            ],
            $condition
        );
    }

    public function testMultiFunctionExpressionsRunInRepl()
    {
        $stmts = [
            'partialLeft(function ($a, $b) {return $a / $b;}, 10)(2)',
            'partialRight(function ($a, $b) {return $a / $b;}, 2)(10)',
            'compose(function ($a){return "Hello " . $a;}, "strtoupper")("World")',
            'curry(function ($a, $b) {return $a * $b;})(6)(2)',
            'curryN(2, function ($a, $b, $c = 3) {return $a + $b + $c;})(1)(2)'
        ];

        $results = A\map(
            function ($stmt) {
                return $this->mimicRepl(
                    A\constantFunction($stmt)
                );
            },
            $stmts
        );

        $this->assertEquals(
            $results,
            A\map(
                function ($stmt) {
                    return $stmt . PHP_EOL;
                },
                [
                    'Result: 5',
                    'Result: 5',
                    'Result: "HELLO WORLD"',
                    'Result: 12',
                    'Result: 6'
                ]
            )
        );
    }

    public function testSingleFunctionExpressionsRunInRepl()
    {
        $stmts = [
            'map(function ($val) {return $val * 2;}, [1, 2, 3, 4])',
            'filter(function ($val) {return $val > 2;}, [1, 2, 3, 4])',
            'fold(function ($acc, $val) {return $acc + $val;}, [1, 2, 3, 4], 1)',
            'concat("_", "functional", "programming")',
            'arrayKeysExist([1, 2, 3], 0, 1)',
            'constantFunction(12)',
            'dropLeft([2, 4, 6, 8], 2)',
            'dropRight([2, 4, 6, 8], 2)',
            'head([1, 2, 3])',
            'tail([1, 2, 3])'
        ];

        $results = A\map(
            function ($stmt) {
                return $this->mimicRepl(
                    A\constantFunction($stmt)
                );
            },
            $stmts
        );

        $this->assertEquals(
            $results,
            A\map(
                function ($val) {
                    return $val . PHP_EOL;
                },
                [
                    'Result: [2,4,6,8]',
                    'Result: {"2":3,"3":4}',
                    'Result: 11',
                    'Result: "functional_programming"',
                    'Result: true',
                    'Result: <Closure> {}',
                    'Result: {"2":6,"3":8}',
                    'Result: [2,4]',
                    'Result: 1',
                    'Result: [2,3]'
                ]
            )
        );
    }

    public function testMonadsRunInRepl()
    {
        $stmts = [
            'IO::of(function () {return 2;})',
            'State::of(1)->flatMap(function ($val) {return $val + 2;})',
            'ListMonad::of(1, 2, 3)',
            'Writer::of(1, "Init val")',
            'Reader::of(12)',
            'Either::right(12)->map(function ($val) {return $val * 2;})',
            'Maybe::fromValue(12)->map(function ($val) {return $val / 2;})->flatMap(function ($val) {return $val + 2;})'
        ];

        $results = A\map(
            function ($stmt) {
                return $this->mimicRepl(
                    A\constantFunction($stmt)
                );
            },
            $stmts
        );

        $this->assertEquals(
            $results,
            A\map(
                function ($stmt) {
                    return $stmt . PHP_EOL;
                },
                [
                    'Result: <IO> 2',
                    'Result: [1,3]',
                    'Result: <ListMonad> [1]',
                    'Result: <Writer> [1,"Init val"]',
                    'Result: <Reader> <Closure> no env value',
                    'Result: <Right> 24',
                    'Result: 8'
                ]
            )
        );
    }
}
