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

<pre id="getting the file" title="getting the file">
if (basename($_SERVER['argv'][0]) == 'php')
{
    // The script was run via $ php noweb.php, tune arguments
    array_shift($_SERVER['argv']);
}
if (count($_SERVER['argv']) != 3)
{
    die(&quot;Usage: noweb.php tangle &lt;textfile&gt;\n&quot;);
}
</pre>

We get the command and filename from the arguments:

<pre id="getting the file" title="getting the file">
$command = $_SERVER['argv'][1];
$readfile = $_SERVER['argv'][2];
</pre>

The we check that the given file actually exists:

<pre id="getting the file" title="getting the file">
if (!file_exists($readfile))
{
    die(&quot;File {$readfile} not found\n&quot;);
}
</pre>

And then we parse the file for any literate programming code, and extract the code. The workings of this is explained in detail later.

<pre id="getting the file" title="getting the file">
$noweb = new noweb();
$noweb-&gt;read_chunks($readfile);
</pre>

We check the command given by the user and perform it:

<pre id="running the command" title="running the command">
switch ($command)
{
    case 'list':
        $noweb-&gt;list_files();
        break;
    case 'tangle':
        $noweb-&gt;tangle_files(dirname($readfile));
        break;
    case 'weave':
        $noweb-&gt;weave($readfile);
        break;
    default:
        die(&quot;Unknown command {$command}. Try 'tangle'\n&quot;);
}
</pre>

## Reading the file

In a literate program the actual software code is stored in chunks inside the document. A chunk is defined by a name inside `<<` and `>>=`, and it ends on a line containing just `@`.

For parsing the chunks we will need two regular expressions:

<pre id="chunk regular expressions" title="chunk regular expressions">
private $chunk_start_regexp = '/^&lt;&lt;([^&gt;]+)&gt;&gt;=/';
private $chunk_end_regexp = '/^@$/';
</pre>

When reading a literate programming file, we handle it on line-by-line basis, keeping track of whether we're inside a chunk or documentation:

<pre id="reading the file" title="reading the file">
$lines = file($filename);
$chunk = null;
foreach ($lines as $line)
{
    &lt;&lt;reading line of the file&gt;&gt;
}
</pre>

With each line of the document we first check if starts a chunk:

<pre id="reading line of the file" title="reading line of the file">
$matches = array();
if (preg_match($this-&gt;chunk_start_regexp, $line, $matches))
{
    // Entering a chunk
    $chunk = $matches[1];
    if (!isset($this-&gt;chunks[$chunk]))
    {
        $this-&gt;chunks[$chunk] = '';
    }
    &lt;&lt;markdownizing chunk start&gt;&gt;
    continue;
}
</pre>

If this line did not start a chunk, and we're not inside a chunk we can ignore the line as it is documentation, not source code:

<pre id="reading line of the file" title="reading line of the file">
if (is_null($chunk))
{
    &lt;&lt;markdownizing documentation line&gt;&gt;
    continue;
}
</pre>

If this check passes we're inside a chunk of code. Therefore we need to check whether the line ends the chunk:

<pre id="reading line of the file" title="reading line of the file">
if (preg_match($this-&gt;chunk_end_regexp, $line))
{
    // Exiting a chunk
    $chunk = null;
    &lt;&lt;markdownizing chunk end&gt;&gt;
    continue;
}
</pre>

If the line didn't end the chunk then we append it to the chunk:

<pre id="reading line of the file" title="reading line of the file">
$this-&gt;chunks[$chunk] .= $line;
&lt;&lt;markdownizing chunk line&gt;&gt;
</pre>

## Expanding chunk macros

It is possible to include contents of another chunk inside a chunk by calling it with its name. This is done using the `<<chunkname>>` syntax.

We need a regular expression for matching this:

<pre id="chunk regular expressions" title="chunk regular expressions">
private $chunk_include_regexp = '/(\s*)&lt;&lt;([^&gt;]+)&gt;&gt;/';
</pre>

For expanding the macros we run the contents of each chunk through the regular expression. To ensure that line indentation works correctly we will also pass the whitespace that we found from a line before the chunk inclusion macro, and also expand recursively:

<pre id="expanding chunk macros" title="expanding chunk macros">
$noweb = $this;
$content = preg_replace_callback
(
    $this-&gt;chunk_include_regexp,
    function ($matches) use ($noweb)
    {
        // We want to indent the chunk with whitespace found
        $indent = substr($matches[1], 1);

        // Expand chunk inclusion request using given indent
        return &quot;\n&quot; . $noweb-&gt;expand_chunk($matches[2], $indent);
    },
    $this-&gt;chunks[$name]
);
</pre>

The actual indentation happens after any possible chunk inclusions have been processed. Finally we trim newlines from the end and return the result:

<pre id="expanding chunk macros" title="expanding chunk macros">
$indented = '';
foreach (preg_split(&quot;/(\r?\n)/&quot;, $content) as $line)
{
    $indented .= &quot;{$indent}{$line}\n&quot;;
}
return rtrim($indented, &quot; \n&quot;);
</pre>

## Checking if a code chunk is a file

We look at the names of code chunks to check if they should be written to a file. Code chunks with slashes (`/`) or dots (`.`) are treated as files.

<pre id="checking for files" title="checking for files">
if (   strpos($name, '.') !== false
    || strpos($name, '/') !== false)
{
    return true;
}
return false;
</pre>

## Listing code files

If user is interested in what files have been defined in the document we can list them.

<pre id="listing code files" title="listing code files">
foreach ($this-&gt;chunks as $name =&gt; $chunk)
{
    if (!$this-&gt;is_chunk_file($name))
    {
        continue;
    }
    echo &quot;{$name}\n&quot;;
}
</pre>

## Writing the code files

After we have found and expanded all chunks from the document we will look for chunks that have names mapping them to a file:

<pre id="writing code files" title="writing code files">
foreach ($this-&gt;chunks as $name =&gt; $chunk)
{
    if (!$this-&gt;is_chunk_file($name))
    {
        continue;
    }
    &lt;&lt;writing code file from chunk&gt;&gt;
}
</pre>

Once we have found filenames from the chunks, we will create the necessary directories for them:

<pre id="writing code file from chunk" title="writing code file from chunk">
if (substr($target_dir, -1) != '/')
{
    $target_dir .= '/';
}
$folder = $target_dir . dirname($name);
if (!file_exists($folder))
{
    if (!mkdir($folder, 0777, true))
    {
        die(&quot;Failed to create folder {$folder}\n&quot;);
    }
}
</pre>

And then we can just write the chunk to a file:

<pre id="writing code file from chunk" title="writing code file from chunk">
$file = $target_dir . $name;
if (!file_put_contents($file, $this-&gt;expand_chunk($name)))
{
    die(&quot;Failed to write to {$file}\n&quot;);
}
</pre>

## Writing documentation in HTML

As the format supported by `noweb.php` is almost-but-not-quite valid [Markdown](http://daringfireball.net/projects/markdown/), the tool has a `weave` command for converting the document to valid Markdown.

For this we need to convert chunk starts to HTML so that they will be valid:

<pre id="markdownizing chunk start" title="markdownizing chunk start">
$this-&gt;markdown .= &quot;&lt;pre id=\&quot;{$chunk}\&quot; title=\&quot;{$chunk}\&quot;&gt;\n&quot;;
</pre>

Since chunks are shown via HTML we need to escape them:

<pre id="markdownizing chunk line" title="markdownizing chunk line">
$this-&gt;markdown .= htmlentities($line);
</pre>

When chunk ends we close the HTML element:

<pre id="markdownizing chunk end" title="markdownizing chunk end">
$this-&gt;markdown .= &quot;&lt;/pre&gt;\n&quot;;
</pre>

Regular documentation lines don't need any special treatment:

<pre id="markdownizing documentation line" title="markdownizing documentation line">
$this-&gt;markdown .= $line;
</pre>

The actual `weave` command writes the Markdown to a `.markdown´ file. First we check that the original documentation file doesn't have the same name:

<pre id="writing documentation file" title="writing documentation file">
$fileinfo = pathinfo($file);
$new_extension = 'markdown';
if ($fileinfo['extension'] == 'markdown')
{
    $new_extension = 'markdown.generated';
}
</pre>

Then we write the file:

<pre id="writing documentation file" title="writing documentation file">
$new_filename = dirname($file) . &quot;/{$fileinfo['filename']}.{$new_extension}&quot;;
file_put_contents($new_filename, $this-&gt;markdown);
</pre>

## Appendix 1: Generating the script

To generate `noweb.php` from this document, you first need a tool to extract the code from it. The easiest way to do that is by fetching the pre-generated `noweb.php` from GitHub.

Then you can generate `noweb.php` from `README.txt` as follows:

    $ noweb.php tangle README.txt

## Appendix 2: Summary of the program

<pre id="noweb.php" title="noweb.php">
#!/usr/bin/php
&lt;?php
&lt;&lt;license information&gt;&gt;
class noweb
{
    &lt;&lt;chunk regular expressions&gt;&gt;
    public $chunks = array();
    public $markdown = '';

    public function read_chunks($filename)
    {
        &lt;&lt;reading the file&gt;&gt;
    }

    public function expand_chunk($name, $indent = '')
    {
        &lt;&lt;expanding chunk macros&gt;&gt;
    }

    public function is_chunk_file($name)
    {
        &lt;&lt;checking for files&gt;&gt;
    }

    public function list_files()
    {
        &lt;&lt;listing code files&gt;&gt;
    }

    public function tangle_files($target_dir)
    {
        &lt;&lt;writing code files&gt;&gt;
    }

    public function weave($file)
    {
        &lt;&lt;writing documentation file&gt;&gt;
    }
}
&lt;&lt;getting the file&gt;&gt;
&lt;&lt;running the command&gt;&gt;
</pre>

## Appendix 3: License and contributing

`noweb.php` is free software produced by [Henri Bergius](http://bergie.iki.fi). The project is [managed on GitHub](https://github.com/bergie/noweb.php) where you can send pull requests or file issues.

<pre id="license information" title="license information">
/**
 * @package noweb.php
 * @link https://github.com/bergie/noweb.php
 * @author Henri Bergius, http://bergie.iki.fi
 * @copyright Henri Bergius, http://bergie.iki.fi
 * @license http://www.gnu.org/licenses/lgpl.html GNU Lesser General Public License
 */
</pre>
