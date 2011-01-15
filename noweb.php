#!/usr/bin/php
<?php
class noweb
{
    private $chunk_start_regexp = '/^<<([^>]+)>>=/';
    private $chunk_end_regexp = '/^@$/';
    private $chunk_include_regexp = '/(\s*)<<([^>]+)>>/';
    public $chunks = array();

    public function read_chunks($filename)
    {
        $lines = file($filename);
        $chunk = null;
        foreach ($lines as $line)
        {
            $matches = array();
            if (preg_match($this->chunk_start_regexp, $line, $matches))
            {
                // Entering a chunk
                $chunk = $matches[1];
                if (!isset($this->chunks[$chunk]))
                {
                    $this->chunks[$chunk] = '';
                }
                continue;
            }
            if (is_null($chunk))
            {
                continue;
            }
            if (preg_match($this->chunk_end_regexp, $line))
            {
                // Exiting a chunk
                $chunk = null;
                continue;
            }
            $this->chunks[$chunk] .= $line;
        }
    }

    public function expand_chunk($name, $indent = '')
    {
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
        $indented = '';
        foreach (preg_split("/(\r?\n)/", $content) as $line)
        {
            $indented .= "{$indent}{$line}\n";
        }
        return rtrim($indented, " \n");
    }

    public function write_files($target_dir)
    {
        foreach ($this->chunks as $name => $chunk)
        {
            if (   strpos($name, '.') === false
                && strpos($name, '/') === false)
            {
                // This chunk doesn't look like a file, skip
                continue;
            }
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
            $file = $target_dir . $name;
            if (!file_put_contents($file, $this->expand_chunk($name)))
            {
                die("Failed to write to {$file}\n");
            }
        }
    }
}
if (count($_SERVER['argv']) != 2)
{
    die("Usage: noweb.php textfile\n");
}
$readfile = $_SERVER['argv'][1];
if (!file_exists($readfile))
{
    die("File {$readfile} not found\n");
}
$noweb = new noweb();
$noweb->read_chunks($readfile);
$noweb->write_files(dirname($readfile));