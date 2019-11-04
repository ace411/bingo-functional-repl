<?php

declare(strict_types=1);

namespace Chemem\Bingo\Functional\Repl\Tests;

use \Eris\Generator;
use \PhpParser\Node\Expr\FuncCall;
use \Chemem\Bingo\Functional\{
    Algorithms as f,
    Functors\Monads\IO
};
use Chemem\Bingo\Functional\Repl\Parser as pr;

class ParserTest extends \PHPUnit\Framework\TestCase
{
    use \Eris\TestTrait;

    public function testGenerateAstOutputsSyntaxTree()
    {
        $this->forAll(Generator\elements('echo 12', 'add(12, 13)', '$x = 3'))->then(function (string $code) {
            $ast = pr\generateAst($code);

            $this->assertIsArray($ast);
        });
    }

    public function testGetFunctionMetadataYieldsFunctionInformation()
    {
        $this->forAll(Generator\elements('strtoupper', 'strtolower', 'json_encode', 'json_decode'))->then(function (string $function) {
            $data = pr\getFunctionMetadata($function);

            $this->assertIsObject($data);
            $this->assertInstanceOf(pr\FuncMetadata::class, $data);
            $this->assertIsArray($data->params);
            $this->assertIsInt($data->paramCount);
        });
    }

    public function testFunctionExistsOutputsIntelligibleBinaryState()
    {
        $this->forAll(Generator\elements('map', 'json_encode', 'json_decode'))->then(function (string $func) {
            [$nspcName, $regName] = pr\functionExists($func);

            $this->assertIsString($nspcName);
            $this->assertIsString($regName);
        });
    }

    public function testPrintPhpExprOutputsAstEquivalentCode()
    {
        $this->forAll(Generator\elements('$x = 12', 'strtoupper("foo")'))->then(function (string $code) {
            $ret    = f\compose(pr\generateAst, self::exprFromTree, pr\printPhpExpr);
            $expr   = $ret($code);

            $this->assertIsString($expr);
        });
    }

    public function testParseFuncArgumentsFormatsFunctionArguments()
    {
        $this->forAll(Generator\elements('map(function ($x) { return $x + 2; }, [3, 7])', 'identity(12)'))->then(function (string $funcCall) {
            $expr = f\compose(pr\generateAst, self::exprFromTree, f\partialRight(pr\nodeFinder, FuncCall::class), f\identity(function ($stmts) {
                return pr\parseFuncArguments($stmts->args);
            }));

            $this->assertIsArray($expr($funcCall));
        });
    }

    public function testPrintFuncExprOutputsFunctionExpression()
    {
        $this->forAll(Generator\associative([
            'func'  => Generator\elements('map', 'identity'),
            'args'  => Generator\tuple(Generator\elements('function ($x) { return $x + 2; }', 'strtoupper("ace411")'), Generator\constant('[1, 2, 3]'))
        ]))->then(function (array $funcData) {
            $pluck  = f\partial(f\pluck, $funcData);
            $expr   = pr\printFuncExpr($pluck('func'), $pluck('args'));
            
            $this->assertIsString($expr);
        });
    }

    public function testHandleFuncCallEnablesParsingOfFunctionCalls()
    {
        $this->forAll(Generator\elements('map(function ($x) { return $x + 2; }, $res)', 'identity(\'foo\')'))->then(function (string $funcCall) {
            $finder     = f\partial(pr\nodeFinder, f\compose(pr\generateAst, self::exprFromTree)($funcCall));
            $handler    = pr\handleFuncCall($finder, f\compose(f\identity, IO\IO));
            
            $this->assertInstanceOf(IO::class, $handler);
            $this->assertIsString($handler->exec());
        });
    }

    public function handleAssignEnablesParsingOfAssignmentOperations()
    {
        $this->forAll(Generator\elements('$add = function ($x) { return $x + 3; }', '$x = pow(2, 5)', '$y = new StdClass(13)', '$z = 12.5', '$str = "foo"'))->then(function (string $assign) {
            $finder     = f\partial(pr\nodeFinder, pr\generateAst($funcCall));
            $handler    = pr\handleAssign($finder, f\compose(f\identity, IO\IO));

            $this->assertInstanceOf(IO::class, $handler);
            $this->assertIsBoolean($handler->exec());
        });
    }

    public function tearDown(): void
    {
        pr\storeClear();
    }

    public static function exprFromTree(array $tree): object
    {
        $ret = f\compose(f\head, function (object $expr): object {
            return f\pluck($expr->jsonSerialize(), 'expr');
        });

        return $ret($tree);
    }

    public const exprFromTree = __CLASS__ . '::exprFromTree';
}
