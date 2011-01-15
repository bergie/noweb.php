Literate programming with PHP
=============================

`noweb.php` is a PHP implementation of the tool needed for [literate programming](http://en.wikipedia.org/wiki/Literate_programming). Wikipedia says the following about literate programming:

> The literate programming paradigm, as conceived by Knuth, represents a move away from writing programs in the manner and order imposed by the computer, and instead enables programmers to develop programs in the order demanded by the logic and flow of their thoughts. Literate programs are written as an uninterrupted exposition of logic in an ordinary human language, much like the text of an essay, in which macros which hide abstractions and traditional source code are included. Literate programming tools are used to obtain two representations from a literate source file: one suitable for further compilation or execution by a computer, the "tangled" code, and another for viewing as formatted documentation, which is said to be "woven" from the literate source. While the first generation of literate programming tools were computer language-specific, the later ones are language-agnostic and exist above the programming languages.

`noweb.php` is able to facilitate this model of working by being able to extract program code from textual documents describing how the program ought to work. This document itself is such a description, and `noweb.php` PHP code can be generated from it.

The inspiration for creating `noweb.php` comes from Jonathan Aquino's work on implementing the same with Python. See his [world's first executable blog post](http://jonaquino.blogspot.com/2010/04/nowebpy-or-worlds-first-executable-blog.html) and the actual [noweb.py project on GitHub](https://github.com/JonathanAquino/noweb.py).

## Download

If you're interested in doing literate programming with PHP, grab the software produced by this document from GitHub:

<https://github.com/bergie/noweb.php>

`noweb.php` requires PHP 5.3 or newer.

## Usage

`noweb.php` is a PHP tool that reads a text file with [noweb-style](http://en.wikipedia.org/wiki/Noweb#Noweb.27s_input) annotated software code macros in it, parses it and writes the files defined in the document into the file system.

    $ noweb.php tangle README.txt

The resulting code files will be written into the same directory where the document resides.

If you just want to see what code files the document defines, you can also run:

    $ noweb.php list README.txt

## Getting a file

When `noweb.php` starts, we check for the command line arguments to get a file. If no argument is found, we abort and give users instructions:

<<getting the file>>=
if (basename($_SERVER['argv'][0]) == 'php')
{
    // The script was run via $ php noweb.php, tune arguments
    array_shift($_SERVER['argv']);
}
if (count($_SERVER['argv']) != 3)
{
    die("Usage: noweb.php tangle <textfile>\n");
}
@

We get the command and filename from the arguments:

<<getting the file>>=
$command = $_SERVER['argv'][1];
$readfile = $_SERVER['argv'][2];
@

The we check that the given file actually exists:

<<getting the file>>=
if (!file_exists($readfile))
{
    die("File {$readfile} not found\n");
}
@

And then we parse the file for any literate programming code, and extract the code. The workings of this is explained in detail later.

<<getting the file>>=
$noweb = new noweb();
$noweb->read_chunks($readfile);
@

We check the command given by the user and perform it:

<<running the command>>=
switch ($command)
{
    case 'list':
        $noweb->list_files();
        break;
    case 'tangle':
        $noweb->tangle_files(dirname($readfile));
        break;
    case 'weave':
        $noweb->weave($readfile);
        break;
    default:
        die("Unknown command {$command}. Try 'tangle'\n");
}
@

## Reading the file

In a literate program the actual software code is stored in chunks inside the document. A chunk is defined by a name inside `<<` and `>>=`, and it ends on a line containing just `@`.

For parsing the chunks we will need two regular expressions:

<<chunk regular expressions>>=
private $chunk_start_regexp = '/^<<([^>]+)>>=/';
private $chunk_end_regexp = '/^@$/';
@

When reading a literate programming file, we handle it on line-by-line basis, keeping track of whether we're inside a chunk or documentation:

<<reading the file>>=
$lines = file($filename);
$chunk = null;
foreach ($lines as $line)
{
    <<reading line of the file>>
}
@

With each line of the document we first check if starts a chunk:

<<reading line of the file>>=
$matches = array();
if (preg_match($this->chunk_start_regexp, $line, $matches))
{
    // Entering a chunk
    $chunk = $matches[1];
    if (!isset($this->chunks[$chunk]))
    {
        $this->chunks[$chunk] = '';
    }
    <<markdownizing chunk start>>
    continue;
}
@

If this line did not start a chunk, and we're not inside a chunk we can ignore the line as it is documentation, not source code:

<<reading line of the file>>=
if (is_null($chunk))
{
    <<markdownizing documentation line>>
    continue;
}
@

If this check passes we're inside a chunk of code. Therefore we need to check whether the line ends the chunk:

<<reading line of the file>>=
if (preg_match($this->chunk_end_regexp, $line))
{
    // Exiting a chunk
    $chunk = null;
    <<markdownizing chunk end>>
    continue;
}
@

If the line didn't end the chunk then we append it to the chunk:

<<reading line of the file>>=
$this->chunks[$chunk] .= $line;
<<markdownizing chunk line>>
@

## Expanding chunk macros

It is possible to include contents of another chunk inside a chunk by calling it with its name. This is done using the `<<chunkname>>` syntax.

We need a regular expression for matching this:

<<chunk regular expressions>>=
private $chunk_include_regexp = '/(\s*)<<([^>]+)>>/';
@

For expanding the macros we run the contents of each chunk through the regular expression. To ensure that line indentation works correctly we will also pass the whitespace that we found from a line before the chunk inclusion macro, and also expand recursively:

<<expanding chunk macros>>=
$noweb = $this;
$content = preg_replace_callback
(
    $this->chunk_include_regexp,
    function ($matches) use ($noweb)
    {
        // We want to indent the chunk with whitespace found
        $indent = substr($matches[1], 1);

        // Expand chunk inclusion request using given indent
        return "\n" . $noweb->expand_chunk($matches[2], $indent);
    },
    $this->chunks[$name]
);
@

The actual indentation happens after any possible chunk inclusions have been processed. Finally we trim newlines from the end and return the result:

<<expanding chunk macros>>=
$indented = '';
foreach (preg_split("/(\r?\n)/", $content) as $line)
{
    $indented .= "{$indent}{$line}\n";
}
return rtrim($indented, " \n");
@

## Checking if a code chunk is a file

We look at the names of code chunks to check if they should be written to a file. Code chunks with slashes (`/`) or dots (`.`) are treated as files.

<<checking for files>>=
if (   strpos($name, '.') !== false
    || strpos($name, '/') !== false)
{
    return true;
}
return false;
@

## Listing code files

If user is interested in what files have been defined in the document we can list them.

<<listing code files>>=
foreach ($this->chunks as $name => $chunk)
{
    if (!$this->is_chunk_file($name))
    {
        continue;
    }
    echo "{$name}\n";
}
@

## Writing the code files

After we have found and expanded all chunks from the document we will look for chunks that have names mapping them to a file:

<<writing code files>>=
foreach ($this->chunks as $name => $chunk)
{
    if (!$this->is_chunk_file($name))
    {
        continue;
    }
    <<writing code file from chunk>>
}
@

Once we have found filenames from the chunks, we will create the necessary directories for them:

<<writing code file from chunk>>=
if (substr($target_dir, -1) != '/')
{
    $target_dir .= '/';
}
$folder = $target_dir . dirname($name);
if (!file_exists($folder))
{
    if (!mkdir($folder, 0777, true))
    {
        die("Failed to create folder {$folder}\n");
    }
}
@

And then we can just write the chunk to a file:

<<writing code file from chunk>>=
$file = $target_dir . $name;
if (!file_put_contents($file, $this->expand_chunk($name)))
{
    die("Failed to write to {$file}\n");
}
@

## Writing documentation in HTML

As the format supported by `noweb.php` is almost-but-not-quite valid [Markdown](http://daringfireball.net/projects/markdown/), the tool has a `weave` command for converting the document to valid Markdown.

For this we need to convert chunk starts to HTML so that they will be valid:

<<markdownizing chunk start>>=
$this->markdown .= "<pre id=\"{$chunk}\" title=\"{$chunk}\">\n";
@

Since chunks are shown via HTML we need to escape them:

<<markdownizing chunk line>>=
$this->markdown .= htmlentities($line);
@

When chunk ends we close the HTML element:

<<markdownizing chunk end>>=
$this->markdown .= "</pre>\n";
@

Regular documentation lines don't need any special treatment:

<<markdownizing documentation line>>=
$this->markdown .= $line;
@

The actual `weave` command writes the Markdown to a `.markdown´ file. First we check that the original documentation file doesn't have the same name:

<<writing documentation file>>=
$fileinfo = pathinfo($file);
$new_extension = 'markdown';
if ($fileinfo['extension'] == 'markdown')
{
    $new_extension = 'markdown.generated';
}
@

Then we write the file:

<<writing documentation file>>=
$new_filename = dirname($file) . "/{$fileinfo['filename']}.{$new_extension}";
file_put_contents($new_filename, $this->markdown);
@

## Appendix 1: Generating the script

To generate `noweb.php` from this document, you first need a tool to extract the code from it. The easiest way to do that is by fetching the pre-generated `noweb.php` from GitHub.

Then you can generate `noweb.php` from `README.txt` as follows:

    $ noweb.php tangle README.txt

## Appendix 2: Summary of the program

<<noweb.php>>=
#!/usr/bin/php
<?php
<<license information>>
class noweb
{
    <<chunk regular expressions>>
    public $chunks = array();
    public $markdown = '';

    public function read_chunks($filename)
    {
        <<reading the file>>
    }

    public function expand_chunk($name, $indent = '')
    {
        <<expanding chunk macros>>
    }

    public function is_chunk_file($name)
    {
        <<checking for files>>
    }

    public function list_files()
    {
        <<listing code files>>
    }

    public function tangle_files($target_dir)
    {
        <<writing code files>>
    }

    public function weave($file)
    {
        <<writing documentation file>>
    }
}
<<getting the file>>
<<running the command>>
@

## Appendix 3: License and contributing

`noweb.php` is free software produced by [Henri Bergius](http://bergie.iki.fi). The project is [managed on GitHub](https://github.com/bergie/noweb.php) where you can send pull requests or file issues.

<<license information>>=
/**
 * @package noweb.php
 * @author Henri Bergius, http://bergie.iki.fi
 * @copyright Henri Bergius, http://bergie.iki.fi
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
@
