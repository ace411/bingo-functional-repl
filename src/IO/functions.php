<?php

/**
 * IO functions for bingo-functional-repl
 * 
 * @package bingo-functional-repl
 * @author Lochemem Bruno Michael
 * @license Apache 2.0
 */

namespace Chemem\Bingo\Functional\Repl\IO;

use PhpParser\{ParserFactory, NodeDumper, PrettyPrinter, Node, NodeFinder};
use Chemem\Bingo\Functional\Repl\Constants;
use Chemem\Bingo\Functional\{Algorithms as A};
use FunctionalPHP\PatternMatching as PM;
use Chemem\Bingo\Functional\Functors\{
    Monads\IO, 
    Monads\State,
    Either
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
    $input = State::of($stmts)
        ->map(
            function (array $stmts) {
                $compile = A\compose(
                    'json_encode',
                    A\partialRight('json_decode', true)
                );

                return $compile($stmts);
            }
        )
        ->flatMap(A\identity);

    return A\concat(PHP_EOL, compileInput($input), '');
} 

const compileInput = 'Chemem\\Bingo\\Functional\\Repl\\IO\\compileInput';

function compileInput(array $input) : string
{
    list($objInput, $arrInput) = $input;

    return PM\match(
        [
            '"Stmt_Echo"' => function () use ($arrInput) {
                $output = A\fold(
                    function ($prefix, $value) {
                        return PM\match(
                            [
                                '"Scalar_String"' => function () use ($prefix, $value) {
                                    return A\concat(
                                        ' ',
                                        $prefix,
                                        $value['exprs'][0]['value']
                                    );
                                },
                                '"Scalar_LNumber"' => function () use ($prefix, $value) {
                                    return A\concat(
                                        ' ',
                                        $prefix,
                                        $value['exprs'][0]['value']
                                    );
                                },
                                '"Scalar_DNumber"' => function () use ($prefix, $value) {
                                    return A\concat(
                                        ' ',
                                        $prefix,
                                        $value['exprs'][0]['value']
                                    );
                                },
                                '_' => function () use ($prefix, $value) {
                                    return A\concat(
                                        ' ', 
                                        Constants\REPL_ERROR,
                                        A\concat(' ', 'Cannot output value')
                                    );
                                }
                            ],
                            $value['exprs'][0]['nodeType']
                        );                        
                    },
                    $arrInput,
                    Constants\REPL_RESULT
                );

                return $output;
            },
            '"Stmt_Expression"' => function () use ($arrInput, $objInput) {
                return PM\match(
                    [
                        '"Expr_FuncCall"' => function () use ($objInput, $arrInput) {
                            $func = (new NodeFinder)
                                ->findFirstInstanceOf($objInput, Node\Expr\FuncCall::class);

                            $funcProperties = A\compose(
                                'json_encode',
                                A\partialRight('json_decode', true)
                            )($func);

                            $fnStatus = isset($funcProperties['name']['parts'][0]) ? 
                                'single-expr' :
                                'multi-expr';
                            
                            return PM\match(
                                [
                                    '["multi", "expr"]' => function () use ($objInput) {
                                        $toCompile = A\concat(
                                            '',
                                            Constants\HELPER_NAMESPACE,
                                            (new PrettyPrinter\Standard)->prettyPrint($objInput)
                                        );

                                        $checkFnExists = function (string $stmt) {
                                            $trueFn = A\compose(
                                                A\partialLeft('explode', '('),
                                                A\head
                                            )($stmt);

                                            $toCompile = function_exists($trueFn) ? 
                                                A\concat(' ', '$success =', $stmt . ';') :
                                                '$error = "Error: Function does not exist";';

                                            eval($toCompile);

                                            return isset($success) ? 
                                                conveyOutput($success) : 
                                                $error;
                                        };

                                        return $checkFnExists($toCompile);
                                    },
                                    '["single", "expr"]' => function () use ($arrInput, $objInput) {
                                        $compileArgs = extractFuncArgs(
                                            $arrInput[0]['expr']['args'],
                                            $objInput
                                        );

                                        $fnName = A\concat(
                                            '', 
                                            Constants\HELPER_NAMESPACE, 
                                            $arrInput[0]['expr']['name']['parts'][0]
                                        );

                                        return function_exists($fnName) ?
                                            conveyOutput(
                                                call_user_func_array($fnName, $compileArgs)
                                            ) :
                                            A\concat(
                                                ' ',
                                                Constants\REPL_ERROR,
                                                'Could not evaluate function'
                                            );
                                    },
                                    '_' => function () {
                                        return A\concat(
                                            ' ',
                                            Constants\REPL_ERROR,
                                            'Could not evaluate function'
                                        );
                                    }
                                ],
                                explode('-', $fnStatus)
                            );
                        },
                        '"Expr_ConstFetch"' => function () use ($objInput, $arrInput) {
                            $constName = $arrInput[0]['expr']['name']['parts'][0];

                            return isset(Constants\REPL_CONSTANTS[$constName]) ? 
                                A\concat(
                                    ' ',
                                    Constants\REPL_RESULT,
                                    Constants\REPL_CONSTANTS[$constName]
                                ) :
                                A\concat(
                                    ' ',
                                    Constants\REPL_ERROR,
                                    'Cannot parse the constant',
                                    $constName
                                );
                        },
                        '"Expr_StaticCall"' => function () use ($objInput, $arrInput) {
                            $compiled = execFunctor(
                                $arrInput[0]['expr']['class']['parts'][0],
                                $objInput
                            );

                            return $compiled;
                        },
                        '"Expr_MethodCall"' => function () use ($arrInput, $objInput) {
                            $class = (new NodeFinder)
                                ->findInstanceOf($objInput, Node\Expr\StaticCall::class);
                            
                            $classArr = A\compose(
                                'json_encode',
                                A\partialRight('json_decode', true)
                            )($class);

                            $compiled = execFunctor(
                                $classArr[0]['class']['parts'][0],
                                $objInput
                            );

                            return $compiled;
                        },
                        '_' => function () {
                            return A\concat(
                                ' ',
                                Constants\REPL_ERROR,
                                'Cannot process expression'
                            );
                        }
                    ],
                    $arrInput[0]['expr']['nodeType']
                );
            },
            '_' => function () {
                return A\concat(
                    ' ',
                    Constants\REPL_ERROR,
                    'Could not process input'
                );
            }
        ],
        resolveInput($arrInput)
    );
}

const execFunctor = 'Chemem\\Bingo\\Functional\\Repl\\IO\\execFunctor';

function execFunctor(string $functorName, $objInput) : string
{
    $functorNamespaceGen = A\compose(
        A\partialLeft(
            A\filter,
            function ($functor) use ($functorName) {
                return $functor == $functorName;
            }
        ),
        function (array $match) {
            return !empty($match) ? 
                A\concat(
                    '',
                    Constants\FUNCTOR_NAMESPACE,
                    Constants\FUNCTORS[A\head($match)]
                ) :
                '';
        }
    );

    $functorNamespace = $functorNamespaceGen(array_keys(Constants\FUNCTORS));

    $toCompile = !empty($functorNamespace) ?
        A\concat(
            ' ',
            '$success =',
            $functorNamespace . (new PrettyPrinter\Standard)->prettyPrint($objInput)
        ) :
        A\concat(' ', '$error =', '"Error: Invalid Functor";');

    eval($toCompile);

    return isset($success) ? 
        conveyOutput($success) : 
        $error;
}

const resolveInput = 'Chemem\\Bingo\\Functional\\Repl\\IO\\resolveInput';

function resolveInput(array $input) : string
{
    return Either\Either::right($input)
        ->filter(
            function (array $input) {
                return isset($input[0]['nodeType']);
            },
            []
        )
        ->orElse(Either\Either::right([]))
        ->flatMap(
            function (array $input) {
                return !empty($input) ? $input[0]['nodeType'] : '';
            }
        );
}

const extractFuncArgs = 'Chemem\\Bingo\\Functional\\Repl\\IO\\extractFuncArgs';

function extractFuncArgs(array $fnArgs, array $objInput) : array
{
    $fnArgCount = count($fnArgs);

    $traversal = function (int $init = 0, array $compileArgs = []) use (
        $fnArgs,
        $objInput, 
        $fnArgCount,
        &$traversal
    ) {
        if ($init >= $fnArgCount) {
            return $compileArgs;
        }

        $compileArgs[] = PM\match(
            [
                '"Expr_Array"' => function () use ($fnArgs, $init) {
                    $values = array_column(
                        $fnArgs[$init]['value']['items'],
                        'value'
                    );

                    return array_column($values, 'value');
                },
                '"Scalar_String"' => function () use ($fnArgs, $init) {
                    return $fnArgs[$init]['value']['value'];
                },
                '"Scalar_LNumber"' => function () use ($fnArgs, $init) {
                    return $fnArgs[$init]['value']['value'];
                },
                '"Expr_Closure"' => function () use ($objInput) {
                    $closure = (new NodeFinder)
                        ->findInstanceOf($objInput, Node\Expr\Closure::class);
                    
                    $closureObj = A\concat(
                        ' = ',
                        '$closureItem',
                        str_replace(
                            "    ",
                            "",
                            str_replace("\n", "", (new PrettyPrinter\Standard)->prettyPrint($closure) . ';')
                        )
                    );

                    eval(addSlashes($closureObj));

                    return $closureItem;
                },
                '_' => function () {
                    return null;
                }
            ],
            $fnArgs[$init]['value']['nodeType']
        );

        return $traversal($init + 1, $compileArgs);
    };

    return $traversal();
}

const conveyOutput = 'Chemem\\Bingo\\Functional\\Repl\\IO\\conveyOutput';

function conveyOutput($output) : string
{
    $printObject = function ($object) {
        $refObj = new \ReflectionObject($object);

        return A\concat(' ', A\concat('', '<', $refObj->getShortName(), '>'));
    };

    $evalUnionType = function () use ($output, $printObject) {
        return $output
            ->flatMap(
                function ($val) use ($printObject) {
                    return is_object($val) ? $printObject($val) : $val;
                }
            );
    };

    $toPrint = is_object($output) ?
        PM\match(
            [
                '"IO"' => function () use ($output, $printObject) {
                    $val = $output->exec();
                    return A\concat(
                        ' ', 
                        '<IO>', 
                        json_encode(is_object($val) ? $printObject($val) : $val)
                    );
                },
                '"State"' => function () use ($output, $printObject) {
                    return A\concat(
                        ' ', 
                        '<State>', 
                        json_encode(
                            A\map(
                                function ($val) use ($printObject) {
                                    return is_object($val) ? $printObject($val) : $val;
                                }, 
                                $output->exec()
                            )
                        )
                    );
                },
                '"Reader"' => function () use ($output) {
                    return A\concat(' ', '<Reader>', '<Closure>', 'no env value');
                },
                '"ListMonad"' => function () use ($output, $printObject) {
                    return A\concat(
                        ' ', 
                        '<ListMonad>', 
                        json_encode(
                            A\map(
                                function ($val) use ($printObject) {
                                    return is_object($val) ? $printObject($val) : $val;
                                },
                                $output->extract()
                            )
                        )
                    );
                },
                '"Writer"' => function () use ($output, $printObject) {
                    return A\concat(
                        ' ', 
                        '<Writer>', 
                        json_encode(
                            A\map(
                                function ($val) use ($printObject) {
                                    return is_object($val) ? $printObject($val) : $val;
                                }, 
                                $output->run()
                            )
                        )
                    );
                },
                '"Applicative"' => function () use ($output) {
                    $val = $output->getValue();
                    return A\concat(
                        ' ', 
                        '<Applicative>', 
                        json_encode(is_object($val) ? $printObject($val) : $val)
                    );
                },
                '"Right"' => function () use ($evalUnionType) {
                    return A\concat(' ', '<Right>', json_encode($evalUnionType()));
                },
                '"Just"' => function () use ($evalUnionType) {
                    return A\concat(' ', '<Just>', json_encode($evalUnionType()));
                },
                '"Left"' => function () use ($output, $printObject) {
                    $val = $output->flatMap(A\identity);
                    return A\concat(
                        ' ', 
                        '<Left>', 
                        json_encode(is_object($val) ? $printObject($val) : $val)
                    );
                },
                '"Nothing"' => function () {
                    return A\concat(' ', '<Nothing>', 'null');
                },
                '"Closure"' => function () {
                    return '<Closure> {}';
                },
                '_' => function () {
                    return '<Object> Non-parsable';
                }
            ],
            (new \ReflectionObject($output))->getShortName()
        ) :
        json_encode($output);

    return A\concat(' ', Constants\REPL_RESULT, $toPrint);
}