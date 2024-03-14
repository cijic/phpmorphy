#!/usr/bin/php
<?php
if(2 == (ini_get('mbstring.func_overload') & 2)) {
    die("don`t overload string functions in mbstring extension, see mbstring.func_overload option");
}

if($argc < 3) {
    echo "Usage " . $argv[0] . " MORPH_DATA_FILE OUT_DIR [case - UPPER or LOWER]";
    exit;
}

require_once(dirname(__FILE__) . '/../src/common.php');
require_once(dirname(__FILE__) . '/../src/gramtab_consts.php');

$file = $argv[1];
$out_dir = $argv[2];

if(isset($argv[3])) {
    $new_case = strtolower($argv[3]) == 'upper' ? 'upper' : 'lower';
} else {
    $new_case = null;
}

try {
    extract_gramtab($file, $out_dir, true, $new_case);
    extract_gramtab($file, $out_dir, false, $new_case);
} catch (Exception $e) {
    echo $e;
    exit(1);
}

function replace_keys_with_name($map) {
    $result = array();
    
    foreach($map as $item) {
        $result[$item['name']] = $item;
    }
    
    if(count($map) != count($result)) {
        throw new Exception("Map contains non unique names");
    }
    
    return $result;
}

abstract class GrammemsProcessor {
    abstract function process($partOfSpeech, $grammems);

    static function create($locale) {
        $locale=  self::getNormalizedLocale($locale);

        $class = "GrammemsProcessor_$locale";

        if(!class_exists($class)) {
            return new GrammemsProcessor_Common();
        } else {
            return new $class();
        }
    }

    static protected function getNormalizedLocale($locale) {
        return $locale;
    }
}

class GrammemsProcessor_Common extends GrammemsProcessor {
    function process($partOfSpeech, $grammems) {
        return $grammems;
    }    
}

class GrammemsProcessor_ru_RU extends GrammemsProcessor {
    function process($partOfSpeech, $grammems) {
        if(in_array(PMY_RG_INDECLINABLE, $grammems)) {
            // неизменяемые слова как будто принадлежат всем падежам
            if($partOfSpeech !== PMY_RP_PREDK) {
               $grammems = array_merge($grammems, $this->getAllCases());   

               // слово 'пальто' не изменяется по числам, поэтому может
               // быть использовано в обоих числах
               if(!in_array(PMY_RG_SINGULAR, $grammems)) {
                   $grammems[] = PMY_RG_PLURAL;
                   $grammems[] = PMY_RG_SINGULAR;
               }
            }
            
            if($partOfSpeech === PMY_RP_PRONOUN_P) {
                $grammems = array_merge($grammems, $this->getAllGenders());
                $grammems = array_merge($grammems, $this->getAllNumbers());
            }
        }


        // слова общего рода ('сирота') могут  использованы как 
        // слова м.р., так и как слова ж.р.
        if(in_array(PMY_RG_MASC_FEM, $grammems)) {
            $grammems[] = PMY_RG_MASCULINUM;
            $grammems[] = PMY_RG_FEMINUM;
        }

        return array_unique($grammems);
    }

    protected function getAllCases() {
        return array(
            PMY_RG_NOMINATIV,
            PMY_RG_GENITIV,
            PMY_RG_DATIV,
            PMY_RG_ACCUSATIV,
            PMY_RG_INSTRUMENTALIS,
            PMY_RG_LOCATIV,
            PMY_RG_VOCATIV,
        );
    }

    protected function getAllGenders() {
        return array(
            PMY_RG_MASCULINUM,
            PMY_RG_FEMINUM,
            PMY_RG_NEUTRUM,
        );
    }

    protected function getAllNumbers() {
        return array(
            PMY_RG_PLURAL,
            PMY_RG_SINGULAR,
        );
    }
}

abstract class CaseConverter {
    protected $encoding;

    protected function __construct($encoding) {
        $this->encoding = $encoding;

        if(false === ($value = @mb_strtolower('a', $encoding))) {
            throw new Exception("Invalid encoding '$encoding'");
        }
    }

    static function create($encoding, $to) {
        if(!isset($to)) {
            $class = 'CaseConverter_AsIs';
        } else {
            $class = $to == 'lower' ? 'CaseConverter_Lower' : 'CaseConverter_Upper';
        }

        return new $class($encoding);
    }

    abstract function convert($str);
}

class CaseConverter_AsIs extends CaseConverter {
    function convert($str) {
        return $str;
    }
}

class CaseConverter_Upper extends CaseConverter {
    function convert($str) {
        return mb_strtoupper($str, $this->encoding);
    }
}

class CaseConverter_Lower extends CaseConverter {
    function convert($str) {
        return mb_strtolower($str, $this->encoding);
    }
}

function extract_gramtab($graminfoFile, $outDir, $asText, $case) {
    $factory = new phpMorphy_Storage_Factory();
    $graminfo = phpMorphy_GramInfo::create($factory->open(PHPMORPHY_STORAGE_FILE, $graminfoFile, false), false);
    $grammems_processor = GrammemsProcessor::create($graminfo->getLocale());

    $pos_case_converter = CaseConverter::create($graminfo->getEncoding(), 'upper');
    $grammems_case_converter = CaseConverter::create($graminfo->getEncoding(), $case);
    
    $poses = $graminfo->readAllPartOfSpeech();
    $grammems = $graminfo->readAllGrammems(); 
    $ancodes = $graminfo->readAllAncodes();

    foreach($poses as &$pos) {
        $pos['name'] = $pos_case_converter->convert($pos['name']);
    }
    unset($pos);

    foreach($grammems as &$grammem) {
        $grammem['name'] = $grammems_case_converter->convert($grammem['name']);
    }
    unset($grammem);

    foreach($ancodes as &$ancode) {
        $ancode['grammem_ids'] = $grammems_processor->process($ancode['pos_id'], $ancode['grammem_ids']);
    }
    unset($ancode);
    
    if($asText) {
        foreach($ancodes as &$ancode) {
            $pos_id = $ancode['pos_id'];
            
            if(!isset($poses[$pos_id])) {
                throw new Exception("Unknown pos_id '$pos_id' found");
            }
            
            $ancode['pos_id'] = $pos_case_converter->convert($poses[$pos_id]['name']);
            
            foreach($ancode['grammem_ids'] as &$grammem_id) {
                if(!isset($grammems[$grammem_id])) {
                    throw new Exception("Unknown grammem_id '$grammem_id' found");
                }
                
                $grammem_id = $grammems_case_converter->convert($grammems[$grammem_id]['name']);
            }
        }
        unset($ancode);
        
        //$poses = replace_keys_with_name($poses);
        //$grammems = replace_keys_with_name($grammems);
    }
    
    $result = array(
        'poses' => $poses,
        'grammems' => $grammems,
        'ancodes' => $ancodes
    );
    
    $type = $asText ? '_txt' : '';
    $out_file = 'gramtab' . $type . '.' . strtolower($graminfo->getLocale()) . '.bin';
    $out_file = $outDir . '/' . $out_file;
    
    if(false === file_put_contents($out_file, serialize($result))) {
        throw new Exception("Can`t write '$out_file'");
    }
}
