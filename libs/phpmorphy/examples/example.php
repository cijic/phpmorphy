<?php
error_reporting(E_ALL | E_STRICT);

// first we include phpmorphy library
require_once(dirname(__FILE__) . '/../src/common.php');

// set some options
$opts = array(
	// storage type, follow types supported
	// PHPMORPHY_STORAGE_FILE - use file operations(fread, fseek) for dictionary access, this is very slow...
	// PHPMORPHY_STORAGE_SHM - load dictionary in shared memory(using shmop php extension), this is preferred mode
	// PHPMORPHY_STORAGE_MEM - load dict to memory each time when phpMorphy intialized, this useful when shmop ext. not activated. Speed same as for PHPMORPHY_STORAGE_SHM type
	'storage' => PHPMORPHY_STORAGE_FILE,
	// Extend graminfo for getAllFormsWithGramInfo method call
	'with_gramtab' => false,
	// Enable prediction by suffix
	'predict_by_suffix' => true, 
	// Enable prediction by prefix
	'predict_by_db' => true
);

// Path to directory where dictionaries located
$dir = dirname(__FILE__) . '/../dicts';

// Create descriptor for dictionary located in $dir directory with russian language
$dict_bundle = new phpMorphy_FilesBundle($dir, 'rus');

// Create phpMorphy instance
try {
	$morphy = new phpMorphy($dict_bundle, $opts);
} catch(phpMorphy_Exception $e) {
	die('Error occured while creating phpMorphy instance: ' . $e->getMessage());
}

// All words in dictionary in UPPER CASE, so don`t forget set proper locale
// Supported dicts and locales:
//  *------------------------------*
//  | Dict. language | Locale name |
//  |------------------------------|
//  | Russian        | cp1251      |
//  |------------------------------|
//  | English        | cp1250      |
//  |------------------------------|
//  | German         | cp1252      |
//  *------------------------------*
// $codepage = $morphy->getCodepage();
// setlocale(LC_CTYPE, array('ru_RU.CP1251', 'Russian_Russia.1251'));

// Hint: in this example words $word_one, $word_two are in russian language(cp1251 encoding)
$word_one = 'ÏÐÎÂÅÐÊÀ';
$word_two = 'ÌÎÐÔÎËÎÃÈÇÀÒÎÐÀ';

echo "Testing single mode...\n";

try {
	// word by word processing
	// each function return array with result or FALSE when no form(s) for given word found(or predicted)
	$base_form = $morphy->getBaseForm($word_one);
	$all_forms = $morphy->getAllForms($word_one);
	$pseudo_root = $morphy->getPseudoRoot($word_one);
	
	if(false === $base_form || false === $all_forms || false === $pseudo_root) {
		die("Can`t find or predict $word_one word");
	}
	
	echo 'base form = ' . implode(', ', $base_form) . "\n";
	echo 'all forms = ' . implode(', ', $all_forms) . "\n";
	
	echo "Testing bulk mode...\n";
	
	// bulk mode speed-ups processing up to 50-100%(mainly for getBaseForm method)
	// in bulk mode all function always return array
	$bulk_words = array($word_one, $word_two);
	$base_form = $morphy->getBaseForm($bulk_words);
	$all_forms = $morphy->getAllForms($bulk_words);
	$pseudo_root = $morphy->getPseudoRoot($bulk_words);
	
	// Bulk result format:
	// array(
	//   INPUT_WORD1 => array(OUTWORD1, OUTWORD2, ... etc)
	//   INPUT_WORD2 => FALSE <-- when no form for word found(or predicted) 
	// )
	echo 'bulk mode base form = ' . implode(', ', $base_form[$word_one]) . ' ' . implode(', ', $base_form[$word_two]) . "\n";
	echo 'bulk mode all forms = ' . implode(', ', $all_forms[$word_one]) . ' ' . implode(', ', $all_forms[$word_two]) . "\n";
	
	// You can also retrieve all word forms with graminfo via getAllFormsWithGramInfo method call
	// $all_forms_with_gram = $morphy->getAllFormsWithGramInfo($word_one);
} catch(phpMorphy_Exception $e) {
	die('Error occured while text processing: ' . $e->getMessage());
}
