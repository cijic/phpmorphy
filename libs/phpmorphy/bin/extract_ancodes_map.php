#!/usr/bin/php
<?php
if(2 == (ini_get('mbstring.func_overload') & 2)) {
    die("don`t overload string functions in mbstring extension, see mbstring.func_overload option");
}

if($argc < 3) {
    echo "Usage " . $argv[0] . " MORPH_DATA_FILE LANGUAGE OUT_DIR";
    exit;
}

require_once(dirname(__FILE__) . '/../src/common.php');
require_once(dirname(__FILE__) . '/../utils/dict_stuff/mrd/gramtab.php');
require_once(dirname(__FILE__) . '/../utils/dict_stuff/mrd/rml.php');
require_once(dirname(__FILE__) . '/../utils/dict_stuff/mrd/mwz.php');

$graminfo_file = $argv[1];
$language = $argv[2];
$out_dir = $argv[3];


try {
    $factory = new phpMorphy_Storage_Factory();
    $graminfo = phpMorphy_GramInfo::create($factory->open(PHPMORPHY_STORAGE_FILE, $graminfo_file, false), false);
    $out_file = $out_dir . '/morph_data_ancodes_map.' . strtolower($graminfo->getLocale()) . '.bin';

    $gramtab_map = get_gramtab_map($language);
    $valid_ancodes = array_flip(array_values($gramtab_map));
    $ancodes_map = array();
    foreach(get_all_ancodes($graminfo) as $id => $value) {
        if(isset($gramtab_map[$value])) {
            $orig_ancode = $gramtab_map[$value];

            $ancodes_map[$id] = $orig_ancode;
        } else {
            // TODO: typically ancodes don`t contain digits, so we can generate mapping to char + digit ancodes

            do {
                $new_ancode = chr(mt_rand(ord('a'), ord('z'))) . chr(mt_rand(ord('a'), ord('z')));
            } while(isset($valid_ancodes[$new_ancode]));

            echo "'$value' not found in gramtab, assume $new_ancode" . PHP_EOL;

            $ancodes_map[$id] = $new_ancode;
        }
    }

    foreach($ancodes_map as &$ancode) {
        $ancode = iconv('utf-8', $graminfo->getEncoding(), $ancode);

        unset($ancode); // remove reference from array
    }
    unset($ancode);

    file_put_contents($out_file, serialize($ancodes_map));
} catch (Exception $e) {
    echo $e;
    exit(1);
}

function get_all_ancodes($graminfo) {
    $grammems = array();
    $poses = array();

    foreach($graminfo->readAllPartOfSpeech() as $id => $pos) {
        $poses[$id] = $pos['name'];
    }

    foreach($graminfo->readAllGrammems() as $id => $grammem) {
        $grammems[$id] = $grammem['name'];
    }

    $result = array();
    foreach($graminfo->readAllAncodes() as $id => $ancode) {
        if(!isset($poses[$ancode['pos_id']])) {
            throw new Exception("Unknown pos id '" . $ancode['pos_id'] . "'");
        }

        $pos = iconv($graminfo->getEncoding(), 'utf-8', $poses[$ancode['pos_id']]);
        $gram = array();

        foreach($ancode['grammem_ids'] as $grammem) {
            if(!isset($grammems[$grammem])) {
                throw new Exception("Unknown grammem id '$grammem'");
            }

            $gram[] = iconv($graminfo->getEncoding(), 'utf-8', $grammems[$grammem]);
        }

        sort($gram);

        $result[$id] = mb_strtoupper($pos . ' ' . implode(',', $gram));
    }
    
    return $result;
}

function get_gramtab_map($language) {
    $rml = new phpMorphy_Rml_IniFile();
    $gramtab_file = $rml->getGramTabPath($language);

    $gramtab = new phpMorphy_GramTab_File(
        $gramtab_file,
        phpMorphy_Mwz_File::getEncodingForLang($language),
        new phpMorphy_GramTab_GramInfoFactory($language)
    );

    $gramtab_map = array();

    foreach($gramtab as $ancode => $obj) {
        $grammems = $obj->getGrammems();
        sort($grammems);

        $key = $obj->getPartOfSpeech() . ' ' . implode(',', $grammems);

        if(isset($gramtab_map[$key])) {
            throw new Exception("Duplicate ancode contents for $ancode => $key");
        }

        $key = mb_strtoupper($key, 'utf-8');
        $gramtab_map[$key] = $ancode;
    }

    return $gramtab_map;
}
