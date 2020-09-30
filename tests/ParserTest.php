<?php

declare(strict_types=1);

namespace Chemem\Bingo\Functional\Repl\Tests;

\error_reporting(0);

use \Eris\Generator;
use Chemem\Bingo\Functional\{
  Repl\Parser as p,
  Algorithms as f,
  Functors\Monads\State,
  Functors\Monads\IO,
};

class ParserTest extends \PHPUnit\Framework\TestCase
{
  use \Eris\TestTrait;

  public function tearDown(): void
  {
    \apcu_clear_cache();
  }

  /**
   * @test
   */
  public function generateAstCreatesAbstractSyntaxTree()
  {
    $this
      ->forAll(
        Generator\elements(
          'echo 12',
          'fn ($x) => $x ** 2;',
          'array_merge(range(1, 4), range(5, 7))',
          ''
        )
      )
      ->then(function (string $code) {
        $ast = p\generateAst($code);

        $this->assertIsArray($ast);
        if (!empty($ast)) {
          $expr = f\head($ast);

          $this->assertIsObject($expr);
          $this->assertInstanceOf(\PhpParser\Node\Stmt::class, $expr);
        }
      });
  }

  /**
   * @test
   */
  public function getFunctionMetadataOutputsFunctionReflectionData()
  {
    $this
      ->forAll(
        Generator\elements(
          'is_array',
          f\identity,
          f\keysExist,
          f\map,
        )
      )
      ->then(function (string $func) {
        $meta = p\getFunctionMetadata($func);

        $this->assertIsArray($meta);
        $this->assertTrue(f\keysExist($meta, 'paramCount', 'parameters', 'returnType'));
      });
  }

  /**
   * @test
   */
  public function getClassMetadataOutputsClassReflectionData()
  {
    $this
      ->forAll(
        Generator\elements(
          \StdClass::class,
          IO::class,
          State::class,
        )
      )
      ->then(function (string $class) {
        $meta = p\getClassMetadata($class);

        $this->assertIsArray($meta);
        $this->assertTrue(f\keysExist($meta, 'implements', 'methods'));
      });
  }

  /**
   * @test
   */
  public function getUtilityMetadataOutputsLibraryArtifactMetadata()
  {
    $this
      ->forAll(
        Generator\elements(
          f\map,
          f\fold,
          IO::class,
          State::class,
          'foo',
          'foo_bar',
        )
      )
      ->then(function (string $artifact) {
        $meta   = p\getUtilityMetadata($artifact);
        $check  = f\partial(f\keysExist, $meta);

        $this->assertIsArray($meta);
        if (!empty($meta)) {
          $this->assertTrue(
            $check('paramCount', 'parameters', 'returnType') ||
            $check('implements', 'methods')
          );
        }
      });
  }

  /**
   * @test
   */
  public function isLibraryArtifactChecksIfCodeArtifactExistsInBingoFunctionalLibrary()
  {
    $this
      ->forAll(
        Generator\elements(
          'foo',
          'map',
          'concat',
          'Collection',
          'Maybe',
          'Applicative',
          'Asterisk',
          'foo_bar',
        )
      )
      ->then(function (string $artifact) {
        $check = p\isLibraryArtifact($artifact);
        [$metadata, $name] = $check;

        $this->assertIsArray($check);
        $this->assertIsArray($metadata);
        $this->assertIsString($name);
      });
  }

  /**
   * @test
   */
  public function storeAddStoresVariableDataInApcuCache()
  {
    $this
      ->forAll(
        Generator\elements('x', 'y', 'z'),
        Generator\elements(
          'fn ($x) => $x ** 2',
          f\concat('', f\identity, '(2)')
        ),
      )
      ->then(function (string $var, string $expr) {
        $add = p\storeAdd($var, $expr);

        $this->assertInstanceOf(IO::class, $add);
        $this->assertIsBool($add->exec());
      });
  }

  /**
   * @test
   */
  public function storeFetchByVarFetchesValueFromStore()
  {
    $this
      ->forAll(
        Generator\elements('x', 'y', 'z', 'foo', 'bar')
      )
      ->then(function (string $var) {
        $fetch  = p\storeFetchByVar($var);
        $res    = $fetch->exec();

        $this->assertInstanceOf(IO::class, $fetch);
        $this->assertTrue(\is_string($res) || \is_null($res));
      });
  }

  public static function extractExpr(string $code): array
  {
    $evalExpr = f\compose(
      p\generateAst,
      f\head,
      State\evalState(State\gets(fn (object $root): object => (
        $root->jsonSerialize()['expr']
      )), null),
    );

    return $evalExpr(empty($code) ? '""' : $code);
  }

  /**
   * @test
   */
  public function evalFunctionCallExecutesFunctionCall()
  {
    $this
      ->forAll(
        Generator\elements(
          'identity(12 + 2)',
          'map(fn ($x) => $x ** 2, range(1, 9))',
          'Collection::from(range(3, 8))',
          'is_array(range(4, 5))',
        )
      )
      ->then(function (string $stmt) {
        $parse    = f\compose(
          fn ($code) => f\head(self::extractExpr($code)),
          evalFunctionCall,
        );
        $parsable = $parse($stmt);
        
        $this->assertInstanceOf(IO::class, $parsable);
        $this->assertIsString($parsable->exec());
      });
  }

  /**
   * @test
   */
  public function evalAssignParsesAssignmentOperation()
  {
    $this
      ->forAll(
        Generator\elements(
          '$x = 2',
          '$y = fn ($x) => $x ** 2',
          '$y = "foo-bar"',
          '$z = strtoupper("FOO")',
        )
      )
      ->then(function (string $stmt) {
        $parse    = f\compose(
          fn ($code) => f\head(self::extractExpr($code)),
          evalAssign,
        );
        $parsable = $parse($stmt);

        $this->assertInstanceOf(IO::class, $parsable);
        $this->assertIsString($parsable->exec());
      });
  }

  /**
   * @test
   */
  public function evalVarDumpPrintsDataAssignedToVariable()
  {
    $this
      ->forAll(
        Generator\elements('$x', '$y', '$z'),
      )
      ->then(function (string $var) {
        [$expr,]  = self::extractExpr($var);
        $parsable = evalVarDump($expr);

        $this->assertInstanceOf(IO::class, $parsable);
        $this->assertIsString($parsable->exec());
      });
  }

  /**
   * @test
   */
  public function evalExpressionSelectivelyEvaluatesPHPExpressions()
  {
    $this
      ->forAll(
        Generator\elements(
          '$x = "FOO"',
          'Collection::from(range(2, 5))->map(fn ($x) => $x ** 2)',
          'is_array([1, 4, "foo", "bar"])',
          'filter(fn ($x) => $x % 2 == 0, range(5, 15))',
          '2 + 2',
          '"foo-" . strtoupper("bar")',
          '4 == "4"',
          '',
        )
      )
      ->then(function (string $stmt) {
        [$expr, $node] = self::extractExpr($stmt);
        $parsable = evalExpression($node, $expr);
         
        $this->assertInstanceOf(IO::class, $parsable);
        $this->assertIsString($parsable->exec());
      });
  }
}
