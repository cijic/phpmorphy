#!/usr/bin/php
<?php
if(2 == (ini_get('mbstring.func_overload') & 2)) {
    die("don`t overload string functions in mbstring extension, see mbstring.func_overload option");
}

require_once(dirname(__FILE__) . '/../utils/dict_stuff/dict/source/mrd.php');
require_once(dirname(__FILE__) . '/../utils/dict_stuff/dict/writer/xml.php');

if($argc < 3) {
    echo "Usage " . $argv[0] . " MWZ_FILE OUT_DIR";
    exit;
}

$mwz_file = $argv[1];
$out_dir = $argv[2];

try {
    $source = new phpMorphy_Dict_Source_Mrd($mwz_file);
    $out = $out_dir . '/' . $source->getLanguage() . ".xml";
    
    $writer = new phpMorphy_Dict_Writer_Xml($out);
    $writer->setObserver(new phpMorphy_Dict_Writer_Observer_Standart('log_msg'));
    $writer->write($source);
} catch (Exception $e) {
    echo $e;
    exit(1);
}

function log_msg($msg) {
    echo $msg, PHP_EOL;
}
