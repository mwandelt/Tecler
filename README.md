# What is Tecler?

Tecler is a simple framework for building tailor-made PHP template compilers. Compilers built with Tecler are quick and generate very efficient, self-contained code which can be stored and run later without initializing the compiler again.


# Usage

Include the Tecler class file, register some libraries, which define tags,
expressions and filters, and compile some template code:

```php
   require_once 'tecler.class.php';
   require_once 'my_tags.class.php';
   require_once 'my_filters.class.php';

   $tecler = new Tecler();
   $tecler->register_class('my_tags');
   $tecler->register_class('my_filters');

   $code = $tecler->compile( file_get_contents( 'my_template.html' ) );
   eval( '?>' . $code );
```


# Concepts

Tecler parses the template code and replaces tags found in it by small chunks of
PHP code. Which tags are replaced by what code is defined by special PHP
classes, called "Tecler libraries".

## Tags

A tag is a string within the template code which is enclosed between double
curly brackets (or between `tagStartString` and `tagEndString`). If it's not a
directive (see below) Tecler tries to find a library function which can handle
that tag. On success the tag is replaced by the PHP code which that function
returns.

A tag can be extended with a parameter string in order to describe in more
detail how the replacement code should be generated. The parameter string is
separated by a colon (or by the `tagParamSeparator`) from the tag name. Tecler
does not parse parameter strings but hands them over to the library functions
"as is".

For temporarily disabling a tag put a hyphen (`-`) in front of the name.

You can speed up the compilation process by denoting non-expression tags with an
exclamation mark, e.g. `{{!repeat: products}}`. The compiler will skip all
expression handling routines for this kind of tags.

## Expressions

An expression is a special kind of tag. It is meant to be replaced by a PHP
expression, i.e., a bit of PHP code that can be evaluated to produce a value (as
opposed to PHP code which represents control structures like repeat,
if-then-else etc.) If a tag is an expression, or not, is defined by the library
which handles that tag. If it is, you can apply filters to it. Additionaly, some
libraries might support expressions within parameter strings, which means that
you can write things like this: `{{my_outer_tag: {my_inner_tag} }}`.

## Filters

Filters are PHP functions which can be added to expressions in order to apply
further processing to the code returned by the tag handling function. A filter
is added at the end of the tag, before the closing braces, and contains a pipe
symbol (or the `tagFilterSeparator`) followed by the filter name. Filters can
(some must) be extended with a parameter string which is separated by a colon
from the filter name. You can concatenate as many filters as you want. They are
applied in order from left to right. The special filter `-` (hyphen) may be used
to stop the processing of further filters, including global filters set by a
`#filter` directive.

## Directives

Tecler supports a small range of built-in tags, so-called "directives". They can
be distinguished by normal tags by the hash at the beginning of their name. See
the section "Directives" below to get more details.


# Class properties

#### `filterParamSeparator` (string)

The string that marks the borderline between name and parameter string within a
filter call. By default, this is the colon character: `:`.

#### `globalFilters` (array)

List of global filters that will be applied automatically to all expressions.
See `#filter` directive for more details.

#### `idleMode` (bool)

Status flag which indicates if the compiler is in idle mode, or not. See `#idle`
directive for more details.

#### `includesDirectory` (string)

Base directory for resolving relative file paths in `#include` directives.

#### `removeHtmlCommentMarkers` (bool)

Flag which tells the compiler to remove HTML comment markers that *immediately*
precede or succeed a tag. Immediately means that there are no other characters
(including whitespace) between the comment markers and the beginning or ending
of the tag.

#### `requiredFiles` (array)

List of external files that have to be included in order to execute the
generated code. This property will be empty on start and filled during the
compilation process.

#### `scriptMode` (bool)

Status flag which indicates if the compiler is in script mode, or not. See
`#script` directive for more details.

#### `tagFilterSeparator` (string)

The string that marks the beginning of a filter call within a tag. By default,
this is the pipe character: `|`.

#### `tagParamSeparator` (string)

The string that marks the borderline between name and parameter string within a
tag. By default, this is the colon character: `:`.

#### `tagStartString` (string), `tagEndString` (string)

The strings that mark the beginning and ending of a tag when parsing template
code. By default, these are opening and closing double curly brackets: `{{ ... }}`.

#### `tagStartStringExpr` (string), `tagEndStringExpr` (string)

The strings that mark the beginning and ending of a tag when parsing an
expression. By default, these are opening and closing single curly brackets: `{ ... }`.


# Class methods

#### `register_class( $className )`

Adds a class with tag and filter definitions to the compiler library.

#### `compile( $templateCode )`

Compiles some template code and returns a complete PHP script. This script is
"self-contained", i.e. it contains `require_once` statements for all required
external files.

#### `compile_expression( $templateCode )`

Compiles some template code and returns a PHP expression that can be embedded in
a PHP script. This method is meant to be called by library functions that
support the use of tags within other tag's parameter strings.

#### `add_global_filter( $filter, $param )`

Adds a filter to the list of global filters. Parameters may be supplied as second argument.

#### `remove_global_filter( $filter )`

Removes a filter from the list of global filters.

#### `reset_global_filters()`

Removes all global filters.

#### `include_file( $path )`

Compiles an external file and includes the generated code. 

#### `start_idle_mode()`

Sets the compiler in idle mode.

#### `stop_idle_mode()`

Stops idle mode.

#### `start_script_mode()`

Sets the compiler in script mode.

#### `stop_script_mode()`

Stops script mode.


# Directives (built-in tags)

#### `#filter`

Defines a global filter or a set of global filters to be applied to all
following expressions. When defining a filter set the filter names must be
separated by pipe characters (`|`). If some expressions have individual filters
those are applied first. To switch off any global filtering use this directive
without parameters. If you want to prevent global filters to be applied to a
particular tag, just add the special filter `-` to the end of the tag.

#### `#include`

Compiles an external file and includes the generated code. You have to provide a
file path as parameter. If it's a relative path it will be resolved from the
base directory (see `includesDirectory` property). Pay attention to not including
a template file in itself, because this would cause an infinite loop.

#### `#idle ... #endidle`

Switches idle mode on/off, i.e. skips a part of the template code. Everything
between these two directives will be ignored by the compiler. This way you
can temporarily "uncomment" code for testing and debugging purposes.

#### `#script ... #endscript`

Switches script mode on/off. Script mode is beneficial when using a complex
template language which provides macro-like comands. When not in script mode,
Tecler will enclose the PHP code blocks generated by the individual tag handling
functions in their own PHP start/end tags. When in script mode, Tecler will
combine the individual PHP code blocks in a single PHP start/end tag. This is
more efficient and easier to read. Moreover, in script mode only tags and
directives are processed while text and whitespace between them will be removed
from the generated code.


# Writing your own libraries

A Tecler library is an ordinary PHP class with one or more static methods. Each
method defines a handler for a tag, an expression or a filter. 

## Defining tag handlers

A tag handler is a static method whose name starts with `tag_`, followed by the
name of the tag which is handled by this method. It takes one argument, which is
the parameter string of the tag, and returns a chunk of PHP code.

## Defining expression handlers

An expression handler is a static method whose name starts with `expression_`,
followed by the name of the expression which is handled by this method. It takes
one argument, which is the parameter string of the tag, and returns a PHP
expression, i.e., a chunk of PHP code that can be evaluated to produce a value. 

## Defining filter handlers

A filter handler is a static method whose name starts with `filter_`, followed
by the name of the filter which is handled by this method. It takes two
arguments: the PHP expression which has to be filtered, and the parameter string
of the filter. It returns a PHP expression, usually the original expression
embedded in a function call.

## Providing default handlers

A Tecler library may define default handlers for tags, expressions and filters.
These are named `tag__default`, `expression__default` and `filter__default`,
respectively. Please note that there are two underline characters in each name.

## Managing external files

If a handler returns PHP code which relies on external files to be loaded at
runtime, the compiler must be informed about it. This is accomplished by
providing a method named `get_requirements` which returns an array of file
paths. The compiler will generate a `require_once` statement for each of these
paths.

## Calling compiler methods

Tecler will provide a reference to itself as additional argument when calling a
handler method. If you want to call a compiler method from your handler method,
just declare a second or third argument and use the compiler object, which is handed 
over.

