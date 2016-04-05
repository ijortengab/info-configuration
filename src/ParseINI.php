<?php

namespace IjorTengab;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Parser for INI file format.
 */
class ParseINI
{
    public $filename;

    public $raw;

    public $log;

    public $data = [];

    protected $key_scalar = [];

    public $data_map = [];

    protected $most_eol = "\n";

    public $process_sections = false;

    /**
     * INI_SCANNER_NORMAL
     * INI_SCANNER_RAW
     * INI_SCANNER_TYPED
     */
    public $scanner_mode = 'INI_SCANNER_NORMAL';

    protected $line_storage_default = [
        'key prepend' => '',
        'key' => '',
        'key append' => '',
        'equals' => '',
        'quote' => '',
        'value prepend' => '',
        'value' => '',
        'value append' => '',
        'comment' => '',
        'eol' => '',
    ];

    protected $line_storage = [];

    function __construct(Array $info = [], LoggerInterface $log = null)
    {
        if (null === $log) {
            $this->log = new NullLogger;
        }
        else {
            $this->log = $log;
        }

        if (array_key_exists('filename', $info)) {
            $this->filename = $info['filename'];
            $this->raw = file_get_contents($this->filename);
        }
        if (array_key_exists('raw', $info)) {
            $this->raw = $info['raw'];
        }
    }

    /**
     *
     */
    public function setLog(LoggerInterface $log)
    {
        // Clear memory for instance NullLogger if exists.
        unset($this->log);
        $this->log = $log;
    }

    /**
     *
     */
    public function getLog()
    {
        return $this->log;
    }

    /**
     *
     */
    public function parse()
    {
        $input = $this->raw;
        if (is_string($input)) {
            $this->parseString($input);
        }
        else {
            $file = $this->filename;
            if (is_readable($file)) {
                $this->raw = file_get_contents($this->filename);
                $this->parseString($this->raw);
            }
            else {
                return false;
            }
        }
        return true;
    }

    /**
     *
     */
    protected function parseString($string)
    {
        $strlen = strlen($string);

        $step = 'init';
        $line_storage = $this->line_storage_default;
        $register_character = false;
        $register_line = false;
        $register_data = false;

        $line_number = $_line_number = 1;
        $eols = [
            "\r" => 0,
            "\n" => 0,
            "\r\n" => 0,
        ];

        // Walking.
        for ($x = 0; $x < $strlen; $x++) {
            $ch = $string[$x];
            $nch = isset($string[$x+1]) ? $string[$x+1] : false;
            $pch = isset($string[$x-1]) ? $string[$x-1] : false;

            switch ($step) {
                case 'init':
                    if (ctype_space($ch) && !in_array($ch, ["\r","\n"])) {
                        $register_character = 'key prepend';
                    }
                    elseif(in_array($ch, ["\r","\n"])) {
                        if ($ch == "\r" && $nch == "\n") {
                            $ch = "\r\n";
                            $x++;
                        }
                        $register_character = 'eol';
                        $register_line = true;
                        $step = 'init';
                    }
                    elseif ($ch == ';') {
                        $register_character = 'comment';
                        $step = 'build comment';
                    }
                    else {
                        $register_character = 'key';
                        $step = 'build key init';
                    }
                    break;

                case 'build key init':
                    if (ctype_space($ch) && !in_array($ch, ["\r","\n"])) {
                        $register_character = 'key prepend';
                    }
                    elseif(in_array($ch, ["\r","\n"])) {
                        if ($ch == "\r" && $nch == "\n") {
                            $ch = "\r\n";
                            $x++;
                        }
                        $register_character = 'eol';
                        $register_line = true;
                        $register_data = [$line_storage['key'], '', $line_number];
                        $step = 'init';
                    }
                    elseif ($ch == ';') {
                        $register_data = [$line_storage['key'], '', $line_number];
                        $register_character = 'comment';
                        $step = 'build comment';
                    }
                    else {
                        $register_character = 'key';
                        $step = 'build key';
                    }
                    break;

                case 'build key':
                    if(in_array($ch, ["\r","\n"])) {
                        if ($ch == "\r" && $nch == "\n") {
                            $ch = "\r\n";
                            $x++;
                        }
                        $register_character = 'eol';
                        $register_line = true;
                        $register_data = [$line_storage['key'], '', $line_number];
                        $step = 'init';
                    }
                    elseif ($ch == ';') {
                        $register_data = [$line_storage['key'], '', $line_number];
                        $register_character = 'comment';
                        $step = 'build comment';
                    }
                    elseif ($ch == '=') {
                        $test = rtrim($line_storage['key']);
                        if ($line_storage['key'] !== $test) {
                            $line_storage['key append'] = substr($line_storage['key'], strlen($test));
                            $line_storage['key'] = $test;
                        }
                        $register_character = 'equals';
                        $step = 'build value init';
                    }
                    else {
                        $register_character = 'key';
                    }
                    break;

                case 'build value init':
                    if (ctype_space($ch) && !in_array($ch, ["\r","\n"])) {
                        $register_character = 'value prepend';
                    }
                    elseif ($ch == ';') {
                        $register_data = [$line_storage['key'], '', $line_number];
                        $register_character = 'comment';
                        $step = 'build comment';
                    }
                    elseif(in_array($ch, ["\r","\n"])) {
                        if ($ch == "\r" && $nch == "\n") {
                            $ch = "\r\n";
                            $x++;
                        }
                        $register_character = 'eol';
                        $register_line = true;
                        $register_data = [$line_storage['key'], '', $line_number];
                        $step = 'init';
                    }
                    elseif(in_array($ch, ["'",'"'])) {
                        $step = 'build value';
                        $register_character = 'quote';
                    }
                    else {
                        $step = 'build value';
                        $register_character = 'value';
                    }
                    break;

                case 'build value':
                    if(in_array($ch, ["\r","\n"])) {
                        if ($ch == "\r" && $nch == "\n") {
                            $ch = "\r\n";
                            $x++;
                        }
                        // Jika tidak ada quote, maka rtrim
                        if (empty($line_storage['quote'])) {
                            $test = rtrim($line_storage['value']);
                            if ($line_storage['value'] !== $test) {
                                $line_storage['value append'] = substr($line_storage['value'], strlen($test));
                                $line_storage['value'] = $test;
                            }
                            $register_character = 'eol';
                            $register_line = true;
                            $register_data = [$line_storage['key'], $line_storage['value'], $line_number];
                            $step = 'init';
                        }
                        else {
                            $register_character = 'value';
                            $_line_number++;
                        }
                    }
                    elseif ($ch == ';') {
                        if (empty($line_storage['quote'])) {
                            $test = rtrim($line_storage['value']);
                            if ($line_storage['value'] !== $test) {
                                $line_storage['value append'] = substr($line_storage['value'], strlen($test));
                                $line_storage['value'] = $test;
                            }
                            $register_data = [$line_storage['key'], $line_storage['value'], $line_number];
                            $register_character = 'comment';
                            $step = 'build comment';
                        }
                        else {
                            $register_character = 'value';
                        }
                    }
                    elseif(in_array($ch, ["'",'"'])) {
                        if ($pch == '\\') {
                            $register_character = 'value';
                        }
                        // Jika sebelumnya tidak ada quote, maka
                        // jadikan ini sebagai quote seperti yang dilakukan
                        // oleh parse_ini_file.
                        elseif (empty($line_storage['quote'])) {
                            $register_character = 'quote';
                        }
                        elseif ($ch == $line_storage['quote']) {
                            // Skip.
                            $step = 'need a break';
                        }
                        else {
                            $register_character = 'value';
                        }
                    }
                    else {
                        $register_character = 'value';
                    }
                    break;

                case 'need a break':
                    if (ctype_space($ch) && !in_array($ch, ["\r","\n"])) {
                        $register_character = 'value append';
                    }
                    elseif ($ch == ';') {
                        $register_data = [$line_storage['key'], $line_storage['value'], $line_number];
                        $register_character = 'comment';
                        $step = 'build comment';
                    }
                    elseif(in_array($ch, ["\r","\n"])) {
                        if ($ch == "\r" && $nch == "\n") {
                            $ch = "\r\n";
                            $x++;
                        }
                        $register_character = 'eol';
                        $register_line = true;
                        $register_data = [$line_storage['key'], $line_storage['value'], $line_number];
                        $step = 'init';
                    }
                    elseif(in_array($ch, ["'",'"'])) {
                        // Ganti quote-nya.
                        $line_storage['quote'] = $ch;
                        // Value append dimasukkan ke value.
                        $ch = $line_storage['value append'];
                        $register_character = 'value';
                        $step = 'build value';
                    }
                    else {
                        // Value append perlu dimerge ke value.
                        $ch = $line_storage['value append'] . $ch;
                        $line_storage['value append'] = '';
                        $register_character = 'value';
                    }
                    break;

                case 'build comment':
                    if(in_array($ch, ["\r","\n"])) {
                        if ($ch == "\r" && $nch == "\n") {
                            $ch = "\r\n";
                            $x++;
                        }
                        $register_character = 'eol';
                        $register_line = true;
                    }
                    else {
                        $register_character = 'comment';
                    }
                    break;

                default:
                    // Do something.
                    break;
            }

            if ($register_character) {
                $line_storage[$register_character] .= $ch;
                $register_character = false;
            }

            // If End of file.
            if ($nch == false) {
                $register_line = true;
                if ($step == 'build value' && !empty($line_storage['quote'])) {
                    if ($line_storage['quote'] == "'") {
                        // Todo Log.
                        // $log = 'Warning: syntax error, unexpected $end in test.ini on line 1 in __FILE__ on line __LINE__';
                        // $debugname = 'log'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
                    }
                    else {
                        // Todo Log.
                        // $log = 'Warning: syntax error, unexpected $end, expecting TC_DOLLAR_CURLY or TC_QUOTED_STRING or \'"\' in test.ini on line 2 in C:\Users\X220\GitHub\tmp\test.php on line 26';
                        // $debugname = 'log'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";
                    }
                    // Destroy data.
                    $this->data = false;
                }
                elseif(!empty($line_storage['key'])) {
                    $register_data = [$line_storage['key'], $line_storage['value'], $line_number];
                }
            }

            if ($register_line) {
                $this->line_storage[$line_number] = $line_storage;

                $line_storage = $this->line_storage_default;
                $register_line = false;
                $step = 'init';
                // Variable $_line_number mengalami penambahan
                // jika value memiliki break.
                if ($_line_number > $line_number) {
                    $line_number = $_line_number;
                }
                $line_number++;
                $_line_number = $line_number;
            }

            if ($register_data) {
                list($key, $value, $line) = $register_data;
                if (preg_match('/(.*)\[\]$/', $key, $m)) {
                    $f = 'array_merge_recursive';
                    $count = array_count_values($this->key_scalar);
                    $c = isset($count[$key]) ? $count[$key] : 0;
                    $_k = $m[1] . '[' . $c . ']';
                    $this->data_map[$_k] = $line;
                    $this->key_scalar[] = $key;
                }
                else {
                    $f = 'array_replace_recursive';
                    $this->data_map[$key] = $line;
                }
                $data_expand = $this->arrayDimensionalExpand([$key => $value]);
                $this->data = $f($this->data, $data_expand);
                $register_data = false;
            }
        }

        arsort($eols);
        $this->most_eol = array_shift(array_keys($eols));
    }

    /**
     * Copy dari IjorTengab\Tools\Functions\ArrayDimensional::expand()
     * untuk menghindari require ijortengab/tools.
     */
    public function arrayDimensionalExpand($array_simple)
    {
        $info = [];
        foreach ($array_simple as $key => $value) {
            $keys = preg_split('/\]?\[/', rtrim($key, ']'));
            $last = array_pop($keys);
            $parent = &$info;
            // Create nested arrays.
            foreach ($keys as $key) {
                if ($key == '') {
                    $key = count($parent);
                }
                if (!isset($parent[$key]) || !is_array($parent[$key])) {
                    $parent[$key] = array();
                }
                $parent = &$parent[$key];
            }
            // Insert actual value.
            if ($last == '') {
                $last = count($parent);
            }
            $parent[$last] = $value;
        }
        return $info;
    }
}
