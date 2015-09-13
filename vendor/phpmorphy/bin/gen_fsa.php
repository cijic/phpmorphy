#!/usr/bin/php
<?php
if(2 == (ini_get('mbstring.func_overload') & 2)) {
    die("don`t overload string functions in mbstring extension, see mbstring.func_overload option");
}

require_once(dirname(__FILE__) . '/../utils/autogen/fsa/gen.php');

$fsa_dir = dirname(__FILE__) . '/../src/fsa/access';

try {
    generate_fsa_files($fsa_dir);
} catch (Exception $e) {
    echo $e;
    exit(1);
}
