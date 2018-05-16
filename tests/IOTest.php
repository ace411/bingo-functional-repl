<?php

use PHPUnit\Framework\TestCase;
use Chemem\Bingo\Functional\{
    Repl\IO, 
    Repl\Constants,
    Algorithms as A,
    Functors\Either
};

class IOTest extends TestCase
{
    public function testTransformInputFunctionOutputsParserTokens()
    {
        $tokens = IO\transformInput('echo "Michael";');

        $this->assertTrue(is_array($tokens));
    }

    public function testCompileInputOutputsPhpParsedComputation()
    {
        $parsed = A\compose(
            IO\transformInput,
            function ($stmts) {
                return [
                    $stmts,
                    A\compose(
                        'json_encode',
                        A\partialRight('json_decode', true)
                    )($stmts)
                ];
            },
            IO\compileInput
        )('echo "Michael";');

        $this->assertEquals($parsed, 'Result: Michael');
    }

    public function testPrintOutputConveysParsedInput()
    {
        $toPrint = A\compose(
            IO\transformInput,
            IO\printOutput
        )('echo "Michael";');

        $this->assertEquals($toPrint, 'Result: Michael' . PHP_EOL);
    }

    public function testExecFunctorParsesFunctorCall()
    {
        $execFunctor = IO\execFunctor(
            'State', 
            IO\transformInput('State::of(1)->map(function ($val) {return $val + 2;});')
        );
        
        $this->assertTrue(is_string($execFunctor));
    }

    public function testExtractFuncArgsGetsFunctionArguments()
    {
        $args = A\compose(
            IO\transformInput,
            function ($stmts) {
                return [
                    $stmts,
                    A\compose(
                        'json_encode',
                        A\partialRight('json_decode', true)
                    )($stmts)
                ];
            },
            function (array $state) {
                list($objInput, $arrInput) = $state;

                return call_user_func_array(
                    IO\extractFuncArgs,
                    [
                        $arrInput[0]['expr']['args'],
                        $objInput
                    ]
                );
            }
        )('tail([1, 2, 3]);');

        $this->assertEquals($args, [[1, 2, 3]]);
    }

    public function testResolveInputOutputsExecutablePhpCode()
    {
        $nonExecutable = IO\resolveInput([]);

        $executable = A\compose(
            IO\transformInput,
            'json_encode',
            A\partialRight('json_decode', true),
            IO\resolveInput            
        )('echo "Michael";');

        $this->assertTrue(is_string($nonExecutable));
        $this->assertEquals($nonExecutable, '');
        $this->assertEquals($executable, 'Stmt_Echo');
    }

    public function testReplParsesStmtEchoParsableToken()
    {
        $stmt = A\compose(
            IO\transformInput,
            IO\printOutput
        )('echo "Michael";');

        $this->assertEquals($stmt, 'Result: Michael' . PHP_EOL);
    }

    public function testReplParsesExprFuncCallParsableToken()
    {
        $stmt = A\compose(
            IO\transformInput,
            IO\printOutput
        )('identity("Michael");');

        $this->assertEquals($stmt, 'Result: "Michael"' . PHP_EOL);
    }

    public function testReplParsesExpressionWithMultipleFunctionCalls()
    {
        $stmt = A\compose(
            IO\transformInput,
            IO\printOutput
        )('curry(function ($a, $b) {return $a / $b;})(6)(3);');

        $this->assertEquals($stmt, 'Result: 2' . PHP_EOL);
    }

    public function testReplParsesExprConstFetchParsableToken()
    {
        $stmt = A\compose(
            IO\transformInput,
            IO\printOutput
        )('version;');

        $this->assertEquals($stmt, 'Result: 1.0.0' . PHP_EOL);
    }

    public function testReplParsesExprStaticCallParsableToken()
    {
        $stmt = A\compose(
            IO\transformInput,
            IO\printOutput
        )('State::of(1);');

        $this->assertTrue(is_string($stmt));
    }

    public function testReplParsesExprMethodCallParsableToken()
    {
        $stmt = A\compose(
            IO\transformInput,
            IO\printOutput
        )('State::of(2)->map(function ($a) {return $a + 2;})->flatMap(function ($a) {return $a;});');

        $this->assertEquals($stmt, 'Result: [2,4]' . PHP_EOL);
    }

    public function testNonParsableTokenResultsInReplError()
    {
        $stmt = A\compose(
            IO\transformInput,
            IO\printOutput
        )('$var = "foo";');

        $this->assertEquals($stmt, 'Error: Cannot process expression' . PHP_EOL);
    }

    public function testConveyOutputPrettyPrintsObject()
    {
        $obj = Either\Either::right(12);

        $stmt = IO\conveyOutput($obj);

        $this->assertEquals($stmt, 'Result: <Right> 12');
    }

    public function testConveyOutputPrettyPrintsNonObject()
    {
        $stmt = IO\conveyOutput("Michael");

        $this->assertEquals($stmt, 'Result: "Michael"');
    }

    public function testNonParsableObjectIsConveyedAsNonParsable()
    {
        $obj = new Stdclass(12);

        $stmt = IO\conveyOutput($obj);

        $this->assertEquals($stmt, 'Result: <Object> Non-parsable');
    }
}