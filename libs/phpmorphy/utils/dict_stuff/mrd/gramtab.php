<?php
require_once(dirname(__FILE__) . '/../../libs/iterators.php');
require_once(dirname(__FILE__) . '/../../libs/collections.php');

class phpMorphy_GramTab_Exception extends Exception { }

abstract class phpMorphy_GramTab_GramInfo {
    const GRAMMEMS_SEPARATOR = ',';
    
    protected
        $ancode,
        $pos,
        $grammems;
        
    function __construct($partOfSpeech, $grammems, $ancode) {
/*
        if(strlen($ancode) != 2) {
            throw new phpMorphy_GramTab_Exception("Invalid ancode '$ancode' given, ancode length must be 2 bytes long");
        }
*/
        
        $this->ancode = $ancode;
        $this->pos = $this->isUnknownPartOfSpeech($partOfSpeech) ? null : $partOfSpeech;
        
        $grammems = array_unique(
            array_values(
                array_filter(
                    array_map(
                        'trim',
                        explode(self::GRAMMEMS_SEPARATOR, $grammems)
                    ),
                    'strlen'
                )
            )
        );
        
        // TODO: Locale needed for toupper() operation on unicode string?
        $default = mb_internal_encoding();
        mb_internal_encoding('utf-8');
        $this->grammems = array_map('mb_strtolower', $grammems);
        mb_internal_encoding($default);
    }
    
    function getPartOfSpeech() {
        return $this->pos;
    }
    
    function getPartOfSpeechLong() {
        return $this->pos;
    }
    
    function getAncode() {
        return $this->ancode;
    }
    
    function getGrammems() {
        return $this->grammems;
    }
    
    function isPredictPartOfSpeech() {
        return in_array($this->pos, $this->getPredictPoses());
    }
    
    protected function isUnknownPartOfSpeech($pos) {
        return $pos == '*';
    }
    
    abstract protected function getPredictPoses();
};

class phpMorphy_GramTab_GramInfo_Russian extends phpMorphy_GramTab_GramInfo {
    function __construct($partOfSpeech, $grammems, $ancode) {
        parent::__construct($partOfSpeech, $grammems, $ancode);
        
        $this->processPos();
    }
    
    private function processPos() {
        if($this->pos == 'Г') {
            if(in_array('прч', $this->grammems)) {
                $this->pos = 'ПРИЧАСТИЕ';
            } elseif(in_array('дпр', $this->grammems)) {
                $this->pos = 'ДЕЕПРИЧАСТИЕ';
            } elseif(in_array('инф', $this->grammems)) {
                $this->pos = 'ИНФИНИТИВ';
            }
        }
    }
    
    protected function getPredictPoses() {
        return array(
            "С",
            "ИНФИНИТИВ",
            "П",
            "Н",
        );
    }
};

class phpMorphy_GramTab_GramInfo_English extends phpMorphy_GramTab_GramInfo {
    protected function getPredictPoses() {
        return array(
            "NOUN",
            "VERB",
            "ADJECTIVE",
            "ADVERB",
        );
    }
};

class phpMorphy_GramTab_GramInfo_German extends phpMorphy_GramTab_GramInfo {
    protected function getPredictPoses() {
        return array(
            "SUB",
            "VER",
            "ADJ",
            "ADV",
        );
    }
};

class phpMorphy_GramTab_GramInfo_Empty extends phpMorphy_GramTab_GramInfo {
    function __construct($ancode) {
        //parent::__construct('*', '', $ancode);
        parent::__construct('UNKNOWN', 'unknown, grammems', $ancode);
    }
    
    function isPredictPartOfSpeech() {
        return true;
    }
    
    protected function getPredictPoses() {
        return array();
    }
}

class phpMorphy_GramTab_GramInfoFactory {
    protected
        $info_clazz;
    
    function __construct($language) {
        $this->info_clazz = $this->determineInfoClazz($language);
    }

    function create($partOfSpeech, $grammems, $ancode) {
        return new $this->info_clazz($partOfSpeech, $grammems, $ancode);
    }
    
    private function determineInfoClazz($lang) {
        $lang = strtolower($lang);
        
        if(!$this->isValidLanguage($lang)) {
            throw new phpMorphy_GramTab_Exception("Invalid language($lang) given");
        }
        
        return sprintf('phpMorphy_GramTab_GramInfo_%s', ucfirst($lang));
    }
    
    private function isValidLanguage($lang) {
        return in_array(
            $lang,
            array(
                'russian',
                'english',
                'german'
            )
        );
    }
}

class phpMorphy_GramTab_WithoutComments_Iterator extends phpMorphy_Iterator_NotEmpty {
    protected function isEmptyItem($item) {
        $item = trim($item);
        
        if(!strlen($item)) return true;
        if(substr($item, 0, 2) == '//') return true;
        
        return false;
    }
}

class phpMorphy_GramTab_Reader extends phpMorphy_Iterator_Transform {
    const TOKENS_SEPARATOR = ' ';
    
    private
        $factory,
        $encoding;
    
    function __construct($fileName, $encoding, phpMorphy_GramTab_GramInfoFactory $factory) {
        parent::__construct($this->createIterators($fileName, $encoding));
        
        $this->factory = $factory;
        $this->encoding = $encoding;
    }
    
    protected function createIterators($fileName, $encoding) {
        /*
        return new phpMorphy_Iterator_Iconv(
            new phpMorphy_GramTab_WithoutComments_Iterator(
                $this->openFile($fileName)
            ),
            $encoding
        );
        */
        return new phpMorphy_GramTab_WithoutComments_Iterator(
            $this->openFile($fileName)
        );
    }
    
    protected function openFile($fileName) {
        return new SplFileObject($fileName);
    }
    
    protected function transformItem($item, $key) {
        return $this->processTokens($this->splitTokens(trim($item)));
    }
    
    protected function splitTokens($line) {
        // split by ' '(space) and \t
        $line = preg_replace('~[\x20\x09]+~', ' ', $line);
        
        $result = explode(self::TOKENS_SEPARATOR, $line);
        $items_count = count($result);
        
        if($items_count < 3) {
            throw new phpMorphy_GramTab_Exception("Can`t split [$line] line, too few tokens");
        }
        
        return $result;
    }
    
    protected function processTokens($tokens) {
        return $this->factory->create(
            isset($tokens[2]) ? iconv($this->encoding, 'utf-8', $tokens[2]) : '',
            isset($tokens[3]) ? iconv($this->encoding, 'utf-8', $tokens[3]) : '',
            iconv($this->encoding, 'utf-8', $tokens[0])
        );
    }
}

class phpMorphy_GramTab_File extends phpMorphy_Collection_Immutable {
    protected $collection;
    
    function __construct($fileName, $encoding, phpMorphy_GramTab_GramInfoFactory $factory) {
        $this->collection = $this->createStorageCollection();
        
        parent::__construct($this->collection);
        
        $this->parse($this->createReader($fileName, $encoding, $factory));
    }
    
    protected function createStorageCollection() {
        return new phpMorphy_Collection();
    }
    
    protected function createReader($fileName, $encoding, phpMorphy_GramTab_GramInfoFactory $factory) {
        return new phpMorphy_GramTab_Reader($fileName, $encoding, $factory);
    }
    
    protected function parse(Iterator $it) {
        foreach($it as $value) {
            if(!$value instanceof phpMorphy_GramTab_GramInfo) {
                throw new phpMorphy_GramTab_Exception("Invalid value returned from reader");
            }
            
            $this->collection[$value->getAncode()] = $value;
        }
    }
}

class phpMorphy_GramTab_File_Explicit extends phpMorphy_Collection_Immutable {
    function __construct(Traversable $ancodes) {
        parent::__construct($this->initCollection($ancodes));
    }
    
    protected function initCollection(Traversable $ancodes) {
        $collection = new phpMorphy_Collection();
        
        foreach($ancodes as $ancode) {
            $collection[$ancode] = $this->createEmptyObject($ancode);
        }
        
        return $collection;
    }
    
    protected function createEmptyObject($ancode) {
        return new phpMorphy_GramTab_GramInfo_Empty($ancode);
    }
}
