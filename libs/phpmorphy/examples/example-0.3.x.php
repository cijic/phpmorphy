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
    // Enable prediction by suffix
    'predict_by_suffix' => true, 
    // Enable prediction by prefix
    'predict_by_db' => true,
    // TODO: comment this
    'graminfo_as_text' => true,
);

// Path to directory where dictionaries located
$dir = dirname(__FILE__) . '/../dicts';
$lang = 'ru_RU';

// Create phpMorphy instance
try {
    $morphy = new phpMorphy($dir, $lang, $opts);
} catch(phpMorphy_Exception $e) {
    die('Error occured while creating phpMorphy instance: ' . PHP_EOL . $e);
}

// All words in dictionary in UPPER CASE, so don`t forget set proper locale via setlocale(...) call
// $morphy->getEncoding() returns dictionary encoding

$words = array('ÊĞÀÊÎÇßÁËÈÊÈ', 'ÑÒÀËÈ', 'ÂÈÍÀ', 'È', 'ÄÓÕÈ', 'abc');

if(function_exists('iconv')) {
    foreach($words as &$word) {
        $word = iconv('windows-1251', $morphy->getEncoding(), $word);
    }
    unset($word);
}

try {
    foreach($words as $word) {
        // by default, phpMorphy finds $word in dictionary and when nothig found, try to predict them
        // you can change this behaviour, via second argument to getXXX or findWord methods
        $base = $morphy->getBaseForm($word);
        $all = $morphy->getAllForms($word);
        $part_of_speech = $morphy->getPartOfSpeech($word);      

        // $base = $morphy->getBaseForm($word, phpMorphy::NORMAL); // normal behaviour
        // $base = $morphy->getBaseForm($word, phpMorphy::IGNORE_PREDICT); // don`t use prediction
        // $base = $morphy->getBaseForm($word, phpMorphy::ONLY_PREDICT); // always predict word

        $is_predicted = $morphy->isLastPredicted(); // or $morphy->getLastPredictionType() == phpMorphy::PREDICT_BY_NONE
        $is_predicted_by_db = $morphy->getLastPredictionType() == phpMorphy::PREDICT_BY_DB;
        $is_predicted_by_suffix = $morphy->getLastPredictionType() == phpMorphy::PREDICT_BY_SUFFIX;

        // this used for deep analysis
        $collection = $morphy->findWord($word);
        // or var_dump($morphy->getAllFormsWithGramInfo($word)); for debug

        if(false === $collection) { 
            echo $word, " NOT FOUND\n";
            continue;
        } else {
        }

        echo $is_predicted ? '-' : '+', $word, "\n";
        echo 'lemmas: ', implode(', ', $base), "\n";
        echo 'all: ', implode(', ', $all), "\n";
        echo 'poses: ', implode(', ', $part_of_speech), "\n";
        
        echo "\n";
        // $collection collection of paradigm for given word

        // TODO: $collection->getByPartOfSpeech(...);
        foreach($collection as $paradigm) {
            // TODO: $paradigm->getBaseForm();
            // TODO: $paradigm->getAllForms();
            // TODO: $paradigm->hasGrammems(array('', ''));
            // TODO: $paradigm->getWordFormsByGrammems(array('', ''));
            // TODO: $paradigm->hasPartOfSpeech('');
            // TODO: $paradigm->getWordFormsByPartOfSpeech('');

            
            echo "lemma: ", $paradigm[0]->getWord(), "\n";
            foreach($paradigm->getFoundWordForm() as $found_word_form) {
                echo
                    $found_word_form->getWord(), ' ',
                    $found_word_form->getPartOfSpeech(), ' ',
                    '(', implode(', ', $found_word_form->getGrammems()), ')',
                    "\n";
            }
            echo "\n";
            
            foreach($paradigm as $word_form) {
                // TODO: $word_form->getWord();
                // TODO: $word_form->getFormNo();
                // TODO: $word_form->getGrammems();
                // TODO: $word_form->getPartOfSpeech();
                // TODO: $word_form->hasGrammems(array('', ''));
            }
        }

        echo "--\n";
    }
} catch(phpMorphy_Exception $e) {
    die('Error occured while text processing: ' . $e->getMessage());
}
