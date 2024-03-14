<?php
interface phpMorphy_GrammemsProvider_Interface {
    function getGrammems($partOfSpeech);
}

class phpMorphy_GrammemsProvider_Decorator implements phpMorphy_GrammemsProvider_Interface {
    protected $inner;

    function __construct(phpMorphy_GrammemsProvider_Interface $inner) {
        $this->inner = $inner;
    }

    function getGrammems($partOfSpeech) {
        return $this->inner->getGrammems($partOfSpeech);
    }
}

abstract class phpMorphy_GrammemsProvider_Base implements phpMorphy_GrammemsProvider_Interface {
    protected
        $all_grammems,
        $grammems = array();

    function __construct() {
        $this->all_grammems = $this->flatizeArray($this->getAllGrammemsGrouped());
    }

    abstract function getAllGrammemsGrouped();

    function includeGroups($partOfSpeech, $names) {
        $grammems = $this->getAllGrammemsGrouped();
        $names = array_flip((array)$names);

        foreach(array_keys($grammems) as $key) {
            if(!isset($names[$key])) {
                unset($grammems[$key]);
            }
        }

        $this->grammems[$partOfSpeech] = $this->flatizeArray($grammems);

        return $this;
    }

    function excludeGroups($partOfSpeech, $names) {
        $grammems = $this->getAllGrammemsGrouped();

        foreach((array)$names as $key) {
            unset($grammems[$key]);
        }

        $this->grammems[$partOfSpeech] = $this->flatizeArray($grammems);

        return $this;
    }

    function resetGroups($partOfSpeech) {
        unset($this->grammems[$partOfSpeech]);
        return $this;
    }

    function resetGroupsForAll() {
        $this->grammems = array();
        return $this;
    }

    static function flatizeArray($array) {
        return call_user_func_array('array_merge', $array);
    }

    function getGrammems($partOfSpeech) {
        if(isset($this->grammems[$partOfSpeech])) {
            return $this->grammems[$partOfSpeech];
        } else {
            return $this->all_grammems;
        }
    }
}

class phpMorphy_GrammemsProvider_Empty extends phpMorphy_GrammemsProvider_Base {
    function getAllGrammemsGrouped() {
        return array();
    }

    function getGrammems($partOfSpeech) {
        return false;
    }
}

abstract class phpMorphy_GrammemsProvider_ForFactory extends phpMorphy_GrammemsProvider_Base {
    protected
        $encoded_grammems;

    function __construct($encoding) {
        $this->encoded_grammems = $this->encodeGrammems($this->getGrammemsMap(), $encoding);

        parent::__construct();
    }

    abstract function getGrammemsMap();

    function getAllGrammemsGrouped() { 
        return $this->encoded_grammems;
    } 

    protected function encodeGrammems($grammems, $encoding) {
        $from_encoding = $this->getSelfEncoding();

        if($from_encoding == $encoding) {
            return $grammems;
        }

        $result = array();

        foreach($grammems as $key => $ary) {
            $new_key = iconv($from_encoding, $encoding, $key);
            $new_value = array();

            foreach($ary as $value) {
                $new_value[] = iconv($from_encoding, $encoding, $value);
            }

            $result[$new_key] = $new_value;
        }

        return $result;
    }
}

class phpMorphy_GrammemsProvider_Factory {
    protected static $included = array();

    static function create(phpMorphy $morphy) {
        $locale = $GLOBALS['__phpmorphy_strtolower']($morphy->getLocale());
        
        if(!isset(self::$included[$locale])) {
            $file_name = PHPMORPHY_DIR . "/langs_stuff/$locale.php";
            $class = "phpMorphy_GrammemsProvider_$locale";

            if(is_readable($file_name)) {
                require($file_name);

                if(!class_exists($class)) {
                    throw new phpMorphy_Exception("Class '$class' not found in '$file_name' file");
                }
                
                self::$included[$locale] = call_user_func(array($class, 'instance'), $morphy);
            } else {
                self::$included[$locale] = new phpMorphy_GrammemsProvider_Empty($morphy);
            }
        }


        return self::$included[$locale];
    }
}
