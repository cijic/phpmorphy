#!/usr/bin/php
<?php
if(2 == (ini_get('mbstring.func_overload') & 2)) {
    die("don`t overload string functions in mbstring extension, see mbstring.func_overload option");
}

if($argc < 3) {
    echo "Usage " . $argv[0] . " MORPH_DATA_FILE OUT_DIR";
    exit;
}

require_once(dirname(__FILE__) . '/../src/common.php');

$file = $argv[1];
$out_dir = $argv[2];
$use_references = true;

try {
    $factory = new phpMorphy_Storage_Factory();
    $graminfo = phpMorphy_GramInfo::create($factory->open(PHPMORPHY_STORAGE_FILE, $file, false), false);
    
    $ancodes_map = new Map('ancodes');
    $flexias_map = new Map('affixes');
    $i = 0;

    foreach($graminfo->readAllFlexia() as $id => $flexia) {
        $offset = $flexia['header']['offset'];// + $graminfo->getGramInfoHeaderSize();

        $ancodes_map->update($flexia, $offset);
        //$flexias_map->update($flexia, $offset);

        $i++;
    }

    echo "Total flexias = $i, unique ancodes = " . count($ancodes_map->getMap()) . ', unique flexias = ' . count($flexias_map->getMap()) . PHP_EOL;

    $out_file_format = $out_dir . '/%s.' . strtolower($graminfo->getLocale()) . '.bin';

    file_put_contents(sprintf($out_file_format, 'morph_data_ancodes_cache'), serialize($ancodes_map->compose($use_references)));
    //file_put_contents(sprintf($out_file_format, 'morph_data_flexias_cache'), serialize($flexias_map->compose($use_references)));
} catch (Exception $e) {
    echo $e;
    exit(1);
}

class Map {
    protected
        $key,
        $offsets = array(),
        $map = array();

    function __construct($key) {
        $this->key = $key;
    }

    function update($flexia, $offset) {
        $flexia = $flexia[$this->key];
        $md5 = md5(serialize($flexia));

        if(isset($this->map[$md5])) {
            if($this->map[$md5] != $flexia) {
                // colission detected
                $new_idx = count($this->map);
                $this->map[$new_idx] = $flexia;
                $this->offsets[$new_idx] = array($offset);
            } else {
                // equal flexias
                $this->offsets[$md5][] = $offset;
            }
        } else {
            $this->map[$md5] = $flexia;
            $this->offsets[$md5] = array($offset);
        }
    }

    function getMap() {
        return $this->map;
    }

    function getOffsets() {
        return $this->offsets;
    }

    function compose($useReferences) {
        $result = array();

        foreach($this->map as $md5 => $flexia) {
            $offset = $this->offsets[$md5];

            $first_offset = $offset[0];
            $result[$first_offset] = $flexia;

            for($i = 1, $c = count($offset); $i < $c; $i++) {
                if($useReferences) {
                    $result[$offset[$i]] =& $result[$first_offset];
                } else {
                    $result[$offset[$i]] = $flexia;
                }
            }
        }

        return $result;
    }
}
