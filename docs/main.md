# bingo-functional REPL

The bingo-functional REPL is a Read Evaluate Print Loop utility for the [bingo-functional](https://github.com/ace411/bingo-functional) library. The subsequent text is documentation of the package which should help you, the reader, understand how to go about using it.

## Installation

Before you can use the bingo-functional library, you should have either Git or Composer installed on your system of preference. To install the package via Composer, type the following in your preferred command line interface:

```
composer require chemem/bingo-functional-repl
```

To install via Git, type:

```
git clone https://github.com/ace411/bingo-functional-repl.git
```

## Usage

Since the REPL is predicated on the functions in the [bingo-functional library](https://github.com/ace411/bingo-functional), referring to that [documentation](https://github.com/ace411/bingo-functional/blob/master/docs/main.md) is prudent. The REPL is, however, operated in a console window so familiarizing yourself with the command line argument pattern is also important. 

### Usage Pattern

The REPL follows the following pattern:

```
<command> -> <arguments>
```

The command in this case can be one of either a helper function or a REPL-designated command.

#### REPL commands

Listed below are REPL commands:

- ```version``` prints the version of the REPL in use.

- ```help``` prints fundamental usage information: usage patterns and supported argument types.

- ```list``` prints a list of supported bingo-functional helper functions.

### Example Usage

![Sample REPL usage](https://github.com/ace411/bingo-functional-repl/blob/master/docs/bingo-functional-repl-action.gif)
