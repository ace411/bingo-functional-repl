<?php

declare(strict_types=1);

namespace Chemem\Bingo\Functional\Repl\Tests;

use \Chemem\Bingo\Functional\Algorithms as f;
use \Chemem\Bingo\Functional\Repl\Printer as pr;
use \Eris\Generator;

class PrinterTest extends \PHPUnit\Framework\TestCase
{
    use \Eris\TestTrait;

    public function testColorOutputAddsStyleToStringData()
    {
        $this->forAll(Generator\associative([
            'output' => Generator\string(),
            'colors' => Generator\elements(
                'light_blue',
                'light_red',
                'underline',
                'light_yellow'
            )
        ]))->then(function (array $data) {
            $pluck  = f\partial(f\pluck, $data);
            $styled = pr\colorOutput(
                $pluck('output'),
                $pluck('colors')
            );

            $this->assertIsString($styled);
        });
    }

    public function testPrinterEvaluatesToTabularData()
    {
        $this->forAll(Generator\associative([
            'headerData'    => Generator\tuple(
                Generator\constant('#'),
                Generator\constant('cmd')
            ),
            'rowData'       => Generator\tuple(
                Generator\tuple(
                    Generator\choose(1, 5),
                    Generator\elements(
                        'history',
                        'doc',
                        'help',
                        'howto',
                        'exit'
                    )
                )
            ),
            'padding'       => Generator\tuple(
                Generator\choose(1, 3),
                Generator\choose(1, 3)
            ),
            'border'        => Generator\bool()
        ]))->then(function (array $data) {
            $pluck = f\partial(f\pluck, $data);
            $table = pr\printer(
                $data['headerData'],
                $data['rowData'],
                $data['padding'],
                $data['border']
            );

            $this->assertIsString($table);
        });
    }

    public function testPrefixOutputAppendsTextToSpecialPrefix()
    {
        $this->forAll(Generator\string())->then(function (string $output) {
            $prefixed = pr\prefixOutput($output);

            $this->assertRegExp('/[\=\>]+/', $prefixed);
            $this->assertIsString($prefixed);
        });
    }

    public function testHeaderTextMergesStringsIntoNewLineSeparatedText()
    {
        $this->forAll(Generator\tuple(
            Generator\string(),
            Generator\string()
        ))->then(function (array $txts) {
            $text = pr\headerText(...$txts);

            $this->assertRegExp('/\n+/', $text);
            $this->assertIsString($text);
        });
    }

    public function testPrintMsgEvaluatesToStyledErrorMessage()
    {
        $this->forAll(Generator\associative([
            'type'  => Generator\elements('nexists', 'repl'),
            'msg'   => Generator\string(),
            'warn'  => Generator\bool()
        ]))->then(function (array $opts) {
            $pluck  = f\partial(f\pluck, $opts);
            $msg    = pr\printMsg(
                $pluck('type'),
                $pluck('msg'),
                $pluck('warn')
            );

            $this->assertIsString($msg);
        });
    }
}
