<?php

/**
 * IO functions for bingo-functional-repl
 * 
 * @package bingo-functional-repl
 * @author Lochemem Bruno Michael
 * @license Apache 2.0
 */

namespace Chemem\Bingo\Functional\Repl\IO;

use PhpParser\{Error, ParserFactory, NodeDumper};
use Chemem\Bingo\Functional\Repl\Constants;
use Chemem\Bingo\Functional\{Algorithms as A, Common\Callbacks as CB};
use FunctionalPHP\PatternMatching as PM;
use Chemem\Bingo\Functional\Functors\{
    Monads\IO, 
    Monads\Reader, 
    Monads\State, 
    Monads\Writer,
    Either\Either,
    Either\Left
};

const getInput = 'Chemem\\Bingo\\Functional\\Repl\\IO\\getInput';

function getInput() : IO
{
    return IO::of(
        function () {
            return printf(
                A\concat(' ', Constants\REPL_PREFIX, ''), 
                '%s'
            );
        }
    )
        ->map(
            function ($strlen) {
                return trim(fgets(STDIN));
            }
        );
}

const transformInput = 'Chemem\\Bingo\\Functional\\Repl\\IO\\transformInput';

function transformInput(string $input) : array
{
    $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);

    return $parser->parse(
        A\concat('<?php ', '', $input . ' ?>')
    );
}

const printOutput = 'Chemem\\Bingo\\Functional\\Repl\\IO\\printOutput';

function printOutput(array $stmts) : string
{
    $dumper = new NodeDumper;

    return $dumper->dump($stmts);
} 

const compileInput = 'Chemem\\Bingo\\Functional\\Repl\\IO\\compileInput';

function compileInput($input)
{
    return PM\match(
        [
            '"Stmt_Echo"' => function () use ($input) {
                
            },
            '_' => function () {
                return A\concat(
                    ' ', 
                    Constants\REPL_ERROR, 
                    'Nothing of interest provided'
                );
            }
        ],
        $input[0]
    );
}