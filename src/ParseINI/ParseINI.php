<?php

namespace IjorTengab\ParseINI;

use IjorTengab\Tools\Abstracts\AbstractAnalyzeCharacter;
use IjorTengab\Tools\Functions\CamelCase;
use IjorTengab\Tools\Functions\ArrayHelper;

/**
 * Parser for INI file format.
 */
class ParseINI extends AbstractAnalyzeCharacter
{
    /**
     * Informasi step saat ini yang digunakan untuk menganalisis karakter saat
     * ini. Property ini akan menjadi acuan untuk method yang digunakan.
     * Contoh jika nilai property ini adalah 'init', maka method yang digunakan
     * adalah 'analyzeStepInit()', jika nilai property ini adalah 'build_value',
     * maka method yang digunakan adalah 'analyzeStepBuildValue()'.
     */
    protected $current_step = 'init';

    /**
     * Informasi untuk step berikutnya. Nilai ini diubah oleh method-method
     * dengan prefix 'analyzeStep'. Nilai dari property ini akan mengisi
     * property $current_step ketika akan menganalisis karakter berikutnya.
     */
    protected $next_step;

    /**
     * Informasi bahwa property parent::$raw telah dilakukan parsing.
     */
    protected $has_parsed = false;

    /**
     * Hasil parsing.
     */
    protected $data;

    /**
     * Menyimpan segmentasi informasi yang ada pada property parent::$raw.
     */
    protected $segmen = [];

    /**
     * Mapping antara key data dengan line pada file.
     * Array sederhana satu dimensi, dimana key merupakan array simplify
     * dan value merupakan baris pada file.
     * Contoh:
     * [
     *   "key[child][0]" => 1,
     *   "key[child][1]" => 2,
     *   "key[child][2]" => 3,
     *   "other-key" => 4,
     * ]
     */
    protected $keys = [];

    /**
     * Penampungan sementara key yang bersifat scalar, untuk nantinya dihitung
     * index autoincrement saat dilakukan parsing.
     * Contoh:
     * [
     *   "key[child][]",
     *   "key[child][]",
     *   "key[child][]",
     * ]
     * Pada array diatas, maka key[child][2] adalah index trtinggi.
     */
    protected $sequence_of_scalar = [];

    /**
     * Kondisi baris saat ini untuk menjadi referensi bagi property $segmen.
     */
    protected $current_line_populate_segmen = 1;

    /**
     * Kondisi character saat ini.
     */
    protected $is_alphanumeric = false;
    protected $is_space = false; // All whitespace except \r \n.
    protected $is_quote = false;
    protected $is_quote_single = false;
    protected $is_quote_double = false;
    protected $is_separator = false;
    protected $is_commentsign = false;

    /**
     * Property Sementara.
     */
    protected $is_ongoing_wrapped_by_quote = false;
    protected $quote_wrapper;

    /**
     * Melakukan parsing.
     */
    public function parse()
    {
        if (false === $this->has_parsed) {
            $this->has_parsed = true;
            return $this->looping();
        }
    }

    /**
     *
     */
    public function getResult()
    {
        return $this->data;
    }

    /**
     * Implements abstact analyzeCurrentLine().
     */
    protected function analyzeCurrentLine()
    {
        if ($this->current_step !== 'init') {
            return;
        }
        if ($this->current_line_string === '') {
            return;
        }
        $current_line_string = $this->current_line_string;
        // Jika semuanya adalah spasi
        if (ctype_space($current_line_string)) {
            $this->setCharacter('key_prepend', $current_line_string);
            $this->current_character += strlen($current_line_string);
            return;
        }
    }

    /**
     *
     */
    protected function afterLooping()
    {
        $this->buildData();
    }

    /**
     *
     */
    protected function buildData()
    {
        $segmen = $this->segmen;
        do {
            $line = key($segmen);
            $line_info = $segmen[$line];
            $key = isset($line_info['key']) ? $line_info['key']: null;
            $value = isset($line_info['value']) ? $line_info['value']: null;
            if (isset($value)) {
                if (!isset($line_info['quote_value'])) {
                    $value = $this->convertStringValue($value);
                }
            }
            $array_type = null;
            if (isset($key)) {
                $array_type = (preg_match('/(.*)\[\]$/', $key, $m)) ? 'indexed' : 'associative';
            }
            switch ($array_type) {
                case 'indexed':
                    if (array_key_exists($key, $this->sequence_of_scalar)) {
                        $count = $this->sequence_of_scalar[$key];
                    }
                    else {
                        $count = $this->sequence_of_scalar[$key] = 0;
                    }
                    $_k = $m[1] . '[' . $count . ']';
                    $this->keys[$_k] = [
                        'line' => $line,
                        'value' => $value,
                        'array_type' => $array_type,
                    ];
                    $this->sequence_of_scalar[$key] += 1;
                    $data_expand = ArrayHelper::dimensionalExpand([$_k => $value]);
                    $this->data = array_replace_recursive((array) $this->data, $data_expand);
                    break;

                case 'associative':
                    $this->keys[$key] = [
                        'line' => $line,
                        'value' => $value,
                        'array_type' => $array_type,
                    ];
                    $data_expand = ArrayHelper::dimensionalExpand([$key => $value]);
                    $this->data = array_replace_recursive((array) $this->data, $data_expand);
                    break;
            }
        }
        while (next($segmen));
    }

    /**
     *
     */
    protected function assignCurrentCharacter()
    {
        parent::assignCurrentCharacter();
        $ch = $this->current_character_string;
        if (ctype_alnum($ch)) {
            $this->is_alphanumeric = true;
        }
        elseif (ctype_space($ch) && !in_array($ch, ["\r", "\n", "\r\n"])) {
            $this->is_space = true;
        }
        elseif ($ch === '"') {
            $this->is_quote = true;
            $this->is_quote_double = true;
        }
        elseif ($ch === "'") {
            $this->is_quote = true;
            $this->is_quote_single = true;
        }
        elseif ($ch === '=') {
            $this->is_separator = true;
        }
        elseif ($ch === ';') {
            $this->is_commentsign = true;
        }
    }

    /**
     *
     */
    protected function resetAssignCharacter()
    {
        parent::resetAssignCharacter();
        $this->is_alphanumeric = false;
        $this->is_space = false;
        $this->is_quote = false;
        $this->is_quote_single = false;
        $this->is_quote_double = false;
        $this->is_separator = false;
        $this->is_commentsign = false;
    }

    /**
     *
     */
    protected function analyzeCurrentCharacter()
    {
        $this->runStep();
    }

    /**
     *
     */
    protected function runStep()
    {
        static $cache;
        if (isset($cache[$this->current_step])) {
            return $this->{$cache[$this->current_step]}();
        }
        $_method = 'analyze_step_' . $this->current_step;
        $method = CamelCase::convertFromUnderScore($_method);
        $cache[$this->current_step] = $method;
        $this->{$method}();
    }

    /**
     *
     */
    protected function afterAnalyzeCurrentCharacter()
    {
    }

    /**
     *
     */
    protected function analyzeStepInit()
    {
        // $current_character = $this->current_character;
        // $debugname = 'current_character'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";

        // $current_character_string = $this->current_character_string;
        // $debugname = 'current_character_string'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";

        // $is_last = $this->is_last;
        // $debugname = 'is_last'; echo "\r\n<pre>" . __FILE__ . ":" . __LINE__ . "\r\n". 'var_dump(' . $debugname . '): '; var_dump($$debugname); echo "</pre>\r\n";

        // die;

        $default = false;
        if ($this->is_alphanumeric) {
            $default = true;
        }
        elseif ($this->is_break) {
            $this->setCurrentCharacterAs('eol');
        }
        elseif ($this->is_space) {
            $this->setCurrentCharacterAs('key_prepend');
            $this->next_step = 'build_key_prepend';
        }
        elseif ($this->is_quote_double) {
            $this->error('Unexpected characters.');
        }
        elseif ($this->is_separator) {
            $this->error('Unexpected characters.');
        }
        elseif ($this->is_commentsign) {
            $this->setCurrentCharacterAs('comment');
            $this->next_step = 'build_comment';
        }
        elseif ($this->is_last) {
            $default = false;
        }
        else {
            $default = true;
        }
        if ($default) {
            $this->setCurrentCharacterAs('key');
            $this->next_step = 'build_key';
        }
    }

    /**
     *
     */
    protected function analyzeStepBuildKeyPrepend()
    {
        $default = false;
        if ($this->is_alphanumeric) {
            $default = true;
        }
        elseif ($this->is_break) {
            $this->setCurrentCharacterAs('eol');
        }
        elseif ($this->is_space) {
            $this->setCurrentCharacterAs('key_prepend');
        }
        elseif ($this->is_quote_double) {
            $this->error('Unexpected characters.');
        }
        elseif ($this->is_separator) {
            $this->error('Unexpected characters.');
        }
        elseif ($this->is_commentsign) {
            $this->setCurrentCharacterAs('comment');
            $this->next_step = 'build_comment';
        }
        else {
            $default = true;
        }
        if ($default) {
            $this->setCurrentCharacterAs('key');
            $this->next_step = 'build_key';
        }
    }

    /**
     *
     */
    protected function analyzeStepBuildKey()
    {
        $default = false;
        if ($this->is_alphanumeric) {
            $default = true;
        }
        elseif ($this->is_break) {
            $this->setCurrentCharacterAs('eol');
            $this->next_step = 'init';
        }
        elseif ($this->is_quote_double) {
            $this->error('Unexpected characters.');
        }
        elseif ($this->is_separator) {
            $this->cleaningTrailingWhiteSpace('key');
            $this->setCurrentCharacterAs('separator');
            $this->next_step = 'build_value_prepend';
        }
        elseif ($this->is_commentsign) {
            $this->setCurrentCharacterAs('comment');
            $this->next_step = 'build_comment';
        }
        else {
            $default = true;
        }
        if ($default) {
            $this->setCurrentCharacterAs('key');
            $this->next_step = 'build_key';
        }
    }

    /**
     *
     */
    protected function analyzeStepBuildValuePrepend()
    {
        $default = false;
        if ($this->is_alphanumeric) {
            $default = true;
        }
        elseif ($this->is_break) {
            // Contoh kasus:
            // ```
            // aa =
            // ```
            // Fungsi PHP parse_ini_string, memberikan value empty string, maka:
            $this->setCharacter('value', '');
            $this->setCurrentCharacterAs('eol');
            $this->next_step = 'init';
        }
        elseif ($this->is_space) {
            $this->setCurrentCharacterAs('value_prepend');
        }
        elseif ($this->is_quote) {
            $this->setCurrentCharacterAs('quote_value');
            $this->next_step = 'build_value';
            $this->toggleQuote();
        }
        elseif ($this->is_separator) {
            $this->error('Unexpected characters.');
        }
        elseif ($this->is_commentsign) {
            $this->setCharacter('value', '');
            $this->setCurrentCharacterAs('comment');
            $this->next_step = 'build_comment';
        }
        else {
            $default = true;
        }
        if ($default) {
            $this->setCurrentCharacterAs('value');
            $this->next_step = 'build_value';
        }
    }

    /**
     *
     */
    protected function analyzeStepBuildValue()
    {
        $default = false;
        if ($this->is_alphanumeric) {
            $default = true;
        }
        elseif ($this->is_break) {
            if ($this->is_ongoing_wrapped_by_quote) {
                $default = true;
            }
            else {
                $this->cleaningTrailingWhiteSpace('value');
                $this->setCurrentCharacterAs('eol');
                $this->next_step = 'init';
            }
        }
        elseif ($this->is_quote && $this->is_ongoing_wrapped_by_quote) {
            $quote = $this->quote_wrapper;
            if ($quote === $this->current_character_string) {
                // Pada kasus sepert ini:
                // ```
                // description = ''
                // ```
                // Maka solusinya adalah tambah value kosong.
                $this->setCharacter('value', '');
                $this->next_step = 'build_value_append';
                $this->toggleQuote();
            }
            else {
                $default = true;
            }
        }
        elseif ($this->is_separator) {
            if ($this->is_ongoing_wrapped_by_quote) {
                $default = true;
            }
            else {
                $this->error('Unexpected characters.');
            }
        }
        elseif ($this->is_commentsign) {
            if ($this->is_ongoing_wrapped_by_quote) {
                $default = true;
            }
            else {
                // Pada kasus sepert ini:
                // ```
                // key = ;[\r][\n]
                //
                // ```
                // key dianggap memiliki value berupa empty string oleh
                // parse_ini_string(), oleh karena itu, kita perlu menambah
                // empty string sebagai penyesuaian.
                //
                $this->setCharacter('value', '');
                $this->cleaningTrailingWhiteSpace('value');
                $this->setCurrentCharacterAs('comment');
                $this->next_step = 'build_comment';
            }
        }
        else {
            $default = true;
        }
        if ($default) {
            $this->setCurrentCharacterAs('value');
            $this->next_step = 'build_value';
        }
    }

    /**
     *
     */
    protected function analyzeStepBuildValueAppend()
    {
        if ($this->is_break) {
            $this->setCurrentCharacterAs('eol');
            $this->next_step = 'init';
        }
        elseif ($this->is_space) {
            $this->setCurrentCharacterAs('value_append');
        }
        elseif ($this->is_commentsign) {
            $this->setCurrentCharacterAs('comment');
            $this->next_step = 'build_comment';
        }
        else {
            $this->error('Unexpected characters.');
        }
    }

    /**
     *
     */
    protected function analyzeStepBuildComment()
    {
        $default = false;
        if ($this->is_break) {
            $this->setCurrentCharacterAs('eol');
            $this->next_step = 'init';
        }
        else {
            $default = true;
        }
        if ($default) {
            $this->setCurrentCharacterAs('comment');
        }
    }

    /**
     *
     */
    protected function toggleQuote()
    {
        $current = $this->is_ongoing_wrapped_by_quote;
        $toggle = !$current;
        switch ($toggle) {
            case true:
                $this->is_ongoing_wrapped_by_quote = true;
                $this->quote_wrapper = $this->current_character_string;
                break;

            case false:
                $this->is_ongoing_wrapped_by_quote = false;
                $this->quote_wrapper = null;
                break;
        }
    }

    /**
     *
     */
    protected function setCharacter($key, $value)
    {
        if (!isset($this->segmen[$this->current_line_populate_segmen][$key])) {
            $this->segmen[$this->current_line_populate_segmen][$key] = '';
        }
        $this->segmen[$this->current_line_populate_segmen][$key] .= $value;
    }

    /**
     *
     */
    protected function setCurrentCharacterAs($key)
    {
        return $this->setCharacter($key, $this->current_character_string);
    }

    /**
     *
     */
    protected function getCharacter($key)
    {
        if (isset($this->segmen[$this->current_line][$key])) {
            return $this->segmen[$this->current_line][$key];
        }
    }

    /**
     *
     */
    protected function prepareNextLoop()
    {
        parent::prepareNextLoop();
        if ($this->next_step !== null) {
            $this->current_step = $this->next_step;
            $this->next_step = null;
        }
        if ($this->is_break && $this->current_step != 'build_value') {
            $this->current_line_populate_segmen = $this->current_line;
        }
    }

    /**
     *
     */
    protected function cleaningTrailingWhiteSpace($type)
    {
        $quote = $this->getCharacter('quote_' . $type);
        if (!empty($quote)) {
            return;
        }
        $current = $this->getCharacter($type);
        $test = rtrim($current);
        if ($current !== $test) {
            $this->segmen[$this->current_line][$type . '_append'] = substr($current, strlen($test));
            $this->segmen[$this->current_line][$type] = $test;
        }
    }

    /**
     *
     */
    protected function convertStringValue($string)
    {
        switch ($string) {
            case 'NULL':
            case 'null':
                return null;
            case 'TRUE':
            case 'true':
                return true;
            case 'FALSE':
            case 'false':
                return false;
            case '0':
            case '1':
            case '2':
            case '3':
            case '4':
            case '5':
            case '6':
            case '7':
            case '8':
            case '9':
                return (int) $string;
        }
        if (is_numeric($string)) {
            return (int) $string;
        }
        return $string;
    }

    /**
     *
     */
    protected function error($msg)
    {
        switch ($msg) {
            case 'Unexpected characters.':
                throw new RuntimeException('Unexpected characters "' . $this->current_character_string . '". Line: ' . $this->current_line . ', column: ' . $this->current_column . ', string: "' . $this->current_line_string . '".');
            default:
                throw new RuntimeException($msg);
        }
    }
//
}
