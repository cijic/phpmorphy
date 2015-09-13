#!/usr/bin/php
<?php
if(2 == (ini_get('mbstring.func_overload') & 2)) {
    die("don`t overload string functions in mbstring extension, see mbstring.func_overload option");
}

if($argc < 4) {
    echo "Usage " . $argv[0] . " XML_FILE OUT_DIR ENCODING [WITH_FORM_NO - 1/0] [BUILD_DIALING_ANCODES_MAP - 1/0]";
    exit;
}

define('BIN_DIR', dirname(__FILE__));
define('MORPHY_DIR', getenv('MORPHY_DIR'));
define('MORPHY_BUILDER', MORPHY_DIR . '/bin/morphy_builder');
define('PHP_BIN', getenv('PHPRC') . '/php');

function doError($msg) {
    echo $msg;
    exit(1);
}

class ShellArgsEscaper {
    protected static $need_wrap;

    static function escape($arg) {
        if(!isset(self::$need_wrap)) {
            self::$need_wrap = self::needWrap();
        }

        if(self::$need_wrap) {
            // double slashes at end of argument
            $orig_len = strlen($arg);
            $slashes = $orig_len - strlen(rtrim($arg, '\\'));
            $arg .= str_repeat('\\', $slashes);
        }

        return escapeshellarg($arg);
    }

    static protected function needWrap() {
        if(substr(PHP_OS, 0, 3) == 'WIN') {
            $test = '\a\b\c\\';

            $result = escapeshellarg($test);

            return substr($result, -3, 2) != '\\\\';
        }

        return false;
    }
}

function doExec($title, $file, $args) {
    echo $title . "\n";
    
    $ext = pathinfo($file, PATHINFO_EXTENSION);
    
    $cmd = '';
    switch(strtolower($ext)) {
        case 'php':
            $cmd = PHP_BIN . ' -f ' . ShellArgsEscaper::escape($file) . ' --';
            break;
        default:
            $cmd = ShellArgsEscaper::escape($file);
            
    }
    
    foreach($args as $k => $v) {
        if(is_null($v)) {
            if(is_string($k)) {
                $cmd .= ' ' . $k;
            }
        } else {
            if(is_string($k)) {
                $cmd .= ' ' . $k . '=' . ShellArgsEscaper::escape($v);
            } else {
                $cmd .= ' ' . ShellArgsEscaper::escape($v);
            }
        }
    }
    
    $desc = array(
        1 => array("pipe", "w"),  // stdout
        2 => array("pipe", "w") // stderr
    );
    
    $opts = array(
        'binary_pipes' => true,
        'bypass_shell' => true
    );
    
    $pipes = array();
    
    if(false === ($handle = proc_open($cmd, $desc, $pipes, null, null, $opts))) {
        doError('Can`t execute \'' . $cmd . '\' command');
    }
    
    if(1) {
        while(!feof($pipes[1])) {
            fputs(STDOUT, fgets($pipes[1]));
        }
    } else {
        stream_copy_to_stream($pipes[1], STDOUT);
    }
    
    $stderr = trim(stream_get_contents($pipes[2]));
    
    fclose($pipes[1]);
    fclose($pipes[2]);
    $errorcode = proc_close($handle);
    
    if($errorcode) {
        doError(
            "\n\nCommand '" . $cmd .'\' exit with code = ' . $errorcode . ', error = \'' . $stderr . '\''
        );
    }
    
    echo "OK.\n";
}

function get_locale($xml) {
    $reader = new XMLReader();
    if(false === $reader->open($xml)) {
        return false;
    }
    
    while($reader->read()) {
        if($reader->nodeType == XMLReader::ELEMENT) {
            if($reader->localName === 'locale') {
                $result = $reader->getAttribute('name');
                
                $result = strlen($result) ? $result : false;
                break;
            }
        }
    }
    
    $reader->close();
    
    return $result;
}

function locale_to_dialing($locale) {
    static $map = array(
        'ru_RU' => 'Russian',
        'en_EN' => 'English',
        'de_DE' => 'German',
    );

    if(isset($map[$locale])) {
        return $map[$locale];
    }

    return false;
}

if(false === ($locale = get_locale($argv[1]))) {
    doError("Can`t retrieve locale name from '" . $argv[1] . "' file");
}

$out_dir = $argv[2];
$morph_data_file = $out_dir . '/morph_data.' . strtolower($locale) . '.bin';

echo "Found '$locale' locale in $argv[1]\n";

$args = array(
    '--xml' => $argv[1],
    '--out-dir' => $argv[2],
    '--out-encoding' => $argv[3],
    '--force-encoding-single-byte' => null,
    '--verbose' => null,
    '--case' => 'upper',
);

if(@$argv[4]) {
    $args['--with-form-no'] = 'yes';
}

doExec('Build dictionary', MORPHY_BUILDER, $args);

doExec('Extract gramtab', BIN_DIR . '/extract_gramtab.php', array($morph_data_file, $out_dir));
doExec('Extract graminfo header', BIN_DIR . '/extract_graminfo_header.php', array($morph_data_file, $out_dir));
doExec('Create ancodes cache', BIN_DIR . '/extract_ancodes.php', array($morph_data_file, $out_dir));

if(@$argv[5]) {
    if(false !== ($language = locale_to_dialing($locale))) {
        doExec('Create dialing ancodes map', BIN_DIR . '/extract_ancodes_map.php', array($morph_data_file, $language, $out_dir));
    } else {
        echo "Locale '$locale' unsupported for dialing dictionaries. Skip ancodes map." . PHP_EOL;
    }
}
