#!/usr/bin/php
<?php
if(2 == (ini_get('mbstring.func_overload') & 2)) {
    die("don`t overload string functions in mbstring extension, see mbstring.func_overload option");
}

require_once(dirname(__FILE__) . '/../utils/dict_stuff/dict/source/xml.php');
require_once(dirname(__FILE__) . '/../utils/dict_stuff/dict/writer/xml.php');

$search =  array('Ё', 'ё');
$replace = array('Е', 'е');

if($argc < 3) {
    echo "Usage $argv[0] IN_XML OUT_XML";
    exit(1);
}

$in = fopen($argv[1], 'rt');
$out = fopen($argv[2], 'wt');

if(false === $in || false === $out) {
    echo "Can`t open in or out file[s]";
    exit(1);
}

while(!feof($in)) {
    fputs($out, str_replace($search, $replace, fgets($in)));
}

fclose($in);
fclose($out);

exit(0);

function convert_jo($string) {
    // FIXME: This wrong, need valid processing of utf8
    return str_replace(
        $GLOBALS['search'],
        $GLOBALS['replace'],
        $string
    );
}

try {
    $source = new phpMorphy_Dict_Source_Xml($argv[1]);
    $writer = new phpMorphy_Dict_Writer_Xml($argv[2], 'convert_jo');
    
    $writer->write($source);
} catch (Exception $e) {
    echo $e;
    exit(1);
}
