#!/usr/bin/php
<?php
if(2 == (ini_get('mbstring.func_overload') & 2)) {
    die("don`t overload string functions in mbstring extension, see mbstring.func_overload option");
}

require_once(dirname(__FILE__) . '/../utils/autogen/gramtab/gen.php');

$gramtab_consts_file = dirname(__FILE__) . '/../src/gramtab_consts.php';

try {
    generate_gramtab_consts_file($gramtab_consts_file);
} catch (Exception $e) {
    echo $e;
    exit(1);
}
