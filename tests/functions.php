<?php

declare(strict_types=1);

namespace Chemem\Bingo\Functional\Repl\Tests;

use \Chemem\Bingo\Functional\{
  Repl,
  Algorithms as f,
  Repl\Parser as pr,
  Repl\Printer as pp,
  Functors\Monads\IO,
  PatternMatching as p
};
use \PhpParser\Node;

/**
 * @see Chemem\Bingo\Functional\Repl\Parser\evalFunctionCall
 */
function evalFunctionCall(Node $node): IO
{
  return IO\IO(fn (): string => pr\prettyPrint(
    pr\customTraverser()->traverse([$node])
  ))->bind(fn (string $code) => phpExec($code));
}

const evalFunctionCall = __NAMESPACE__ . '\\evalFunctionCall';

/**
 * @see Chemem\Bingo\Functional\Repl\Parser\evalAssign
 */
function evalAssign(Node $node): IO
{
  $assign   = pr\findFirstInstanceOfNode($node, Node\Expr\Assign::class);
  $success  = f\compose(
    f\partialRight(f\pluck, 'success'),
    f\partial(pp\colorOutput, 'Success')
  );

  return pr\storeAdd(
    $assign->var->name,
    pr\prettyPrint(
      pr\customTraverser()->traverse([$assign->expr])
    )
  )->bind(fn (bool $status) => IO\IO(
    $status ?
      $success(Repl\REPL_COLORS) :
      pp\printError('nexecutable', 'Could not assign')
  ));
}

const evalAssign = __NAMESPACE__ . '\\evalAssign';

/**
 * @see Chemem\Bingo\Functional\Repl\Parser\evalVarDump
 */
function evalVarDump(Node $node): IO
{
  $var = pr\findFirstInstanceOfNode($node, Node\Expr\Variable::class);

  return pr\storeFetchByVar($var->name)
    ->bind(fn ($res): IO => !$res ?
      IO\IO(fn ()        => pp\printError('nexists', f\concat('', '$', $var->name))) :
      phpExec($res)
    );
}

const evalVarDump = __NAMESPACE__ . '\\evalVarDump';

/**
 * @see Chemem\Bingo\Functional\Repl\Parser\evalExpression
 */
function evalExpression(Node $node, Node $expr): IO
{
  $err    = f\compose(
    pr\prettyPrint,
    f\partial(pp\printError, 'nparsable'),
    IO\IO
  );
  $concat = f\partialRight(
    f\partial(f\concat, '', '[', '"PhpParser","Node","Expr",'),
    ']',
  );

  return p\patternMatch([
    $concat('"StaticCall"')   => fn () => evalFunctionCall($node),
    $concat('"MethodCall"')   => fn () => evalFunctionCall($node),
    $concat('"BinaryOp",_')   => fn () => evalFunctionCall($node),
    $concat('"FuncCall"')     => fn () => evalFunctionCall($node),
    $concat('"Assign"')       => fn () => evalAssign($node),
    $concat('"Variable"')     => fn () => evalVarDump($node),
    '_'                       => fn () => $err([$node]),
  ], \explode('\\', (new \ReflectionClass($expr))->getName()));
}

const evalExpression = __NAMESPACE__ . '\\evalExpression';

/**
 * @see Chemem\Bingo\Functional\Repl\Parser\parseCode
 */
function parseCode(string $code): IO
{
  $node = f\head(pr\generateAst(empty($code) ? '""' : $code));
  $expr = f\pluck($node->jsonSerialize(), 'expr');

  return p\patternMatch([
    Node\Stmt\Expression::class => fn () => evalExpression($node, $expr),
    '_'                         => fn () => (
      IO\IO(pp\printError('nparsable', f\concat('', '{', $code, '}')))
    ),
  ], $node);
}

const parseCode = __NAMESPACE__ . '\\parseCode';

/**
 * @see Chemem\Bingo\Functional\Repl\parse
 */
function parse(string $input, array $history = []): IO
{
  $parser = p\match([
    '(x:xs:_)' => fn (string $cmd, string $func) => (
      $cmd == 'doc' ?
        docFuncCmd($func) :
        parseCode($input)
    ),
    '(x:_)' => fn (string $cmd) => p\patternMatch([
      '"history"' => fn () => historyCmd($history),
      '"howto"'   => fn () => howtoCmd(),
      '"help"'    => fn () => helpCmd(),
      '"exit"'    => fn () => exitCmd(),
      '_'         => fn () => parseCode($input),
    ], $cmd),
    '_'     => fn () => parseCode($input),
  ]);

  return $parser(\explode(' ', $input));
}

const parse = __NAMESPACE__ . '\\parse';

/**
 * @see Chemem\Bingo\Functional\Repl\exitCmd
 */
function exitCmd(): IO
{
  $genMsg = f\compose(
    f\partialRight(f\pluck, 'neutral'),
    f\partial(pp\colorOutput, 'Thanks for using the REPL')
  );

  return IO\IO(fn (): string => $genMsg(Repl\REPL_COLORS));
}

const exitCmd = __NAMESPACE__ . '\\exitCmd';

/**
 * @see Chemem\Bingo\Functional\Repl\historyCmd
 */
function historyCmd(array $history): IO
{
  $mapper = fn (array $pair): array => [f\head($pair) + 1, f\last($pair)];

  return IO\IO(fn (): string => pp\printTable(
    ['#', 'cmd'],
    pp\customTableRows($history, $mapper),
    [2, 0]
  ));
}

const historyCmd = __NAMESPACE__ . '\\historyCmd';

/**
 * @see Chemem\Bingo\Functional\Repl\howToCmd
 */
function howtoCmd(): IO
{
  return IO\IO(fn (): string => Repl\REPL_HOW);
}

const howtoCmd = __NAMESPACE__ . '\\howtoCmd';

/**
 * @see Chemem\Bingo\Functional\Repl\helpCmd
 */
function helpCmd(): IO
{
  $color  = fn (string $text): callable => f\compose(
    f\partialRight(f\pluck, 'success'),
    f\partial(pp\colorOutput, $text)
  );
  $mapper = fn (array $pair): array => [
    $color(f\head($pair))(Repl\REPL_COLORS),
    f\last($pair),
  ];

  return IO\IO(fn (): string => pp\printTable(
    ['cmd', 'desc'],
    pp\customTableRows(Repl\REPL_HELP, $mapper),
    [2, 0]
  ));
}

const helpCmd = __NAMESPACE__ . '\\helpCmd';

/**
 * @see Chemem\Bingo\Functional\Repl\docFuncCmd
 */
function docFuncCmd(string $function): IO
{
  $color    = fn (string $text): callable => f\compose(
    f\partialRight(f\pluck, 'success'),
    f\partial(pp\colorOutput, $text)
  );
  $mapper   = fn (array $pair): array => [
    $color(f\head($pair))(Repl\REPL_COLORS),
    f\last($pair),
  ];
  $metadata = f\compose(
    pr\isLibraryArtifact,
    fn (array $state): string => empty(f\head($state)) ?
      pp\printError('nexists', f\last($state)) :
      pp\printTable(
        ['item', 'info'],
        pp\customTableRows(
          pr\getUtilityMetadata(f\last(f\head($state))),
          $mapper
        ),
        [2, 0]
      )
  );

  return IO\IO(fn (): string => $metadata($function));
}

const docFuncCmd = __NAMESPACE__ . '\\docFuncCmd';

/**
 * @see Chemem\Bingo\Functional\Repl\Parser\phpExec
 */
function phpExec(string $cmd): IO
{
  $exec = f\compose(
    pp\genCmdDirective,
    f\partialRight('popen', 'r'),
    f\partialRight('fread', 8192),
    f\partial(f\concat, '', '=>'),
    IO\IO,
  );

  return $exec($cmd);
}

const phpExec = __NAMESPACE__ . '\\phpExec';
