<?php
require_once(dirname(__FILE__) . '/../../libs/collections.php');

class phpMorphy_Dict_Ancode {
    protected
        $id,
        $grammems,
        $pos,
        $is_predict;
    
    function __construct($id, $pos, $isPredict, $grammems = null) {
        //self::checkAncodeId($id, "Invalid ancode_id specified in ancode ctor");
        
        $this->grammems = new phpMorphy_Collection();
        
        if(is_string($grammems)) {
            $this->setGrammemsFromString($grammems);
        } elseif(is_array($grammems)) {
            $this->grammems->import(new ArrayIterator($grammems));
        } elseif(!is_null($grammems)) {
            throw new phpMorphy_Exception('Invalid grammems given');
        }
        
        $this->id = $id;
        $this->pos = $pos;
        $this->is_predict = (bool)$isPredict;
    }
    
/*
    static function checkAncodeId($id, $prefix) {
        if(strlen($id) != 2) {
            throw new Exception("$prefix: Ancode must be exact 2 bytes long, '$id' given");
        }
    }
*/

    function getGrammems() {
        return $this->grammems->getIterator();
    }
    
    function setGrammemsFromString($grammems, $separator = ',') {
        $this->grammems->import(new ArrayIterator(array_map('trim', explode(',', $grammems))));
    }
    
    function addGrammem($grammem) {
        $this->grammems->append($grammem);
    }
    
    function getId() { return $this->id; }
    function getPartOfSpeech() { return $this->pos; }
    function isPredict() { return $this->is_predict; }
    
    /*
    protected function createStorageCollection() {
        return new phpMorphy_Collection();
    }
    */
}

interface phpMorphy_Dict_Flexia_Interface {
    function getPrefix();
    function getSuffix();
    function getAncodeId();
    function setPrefix($prefix);
}

class phpMorphy_Dict_Flexia implements phpMorphy_Dict_Flexia_Interface {
    protected
        $prefix,
        $suffix,
        $ancode_id;
    
    function __construct($prefix, $suffix, $ancodeId) {
        //phpMorphy_Dict_Ancode::checkAncodeId($ancodeId, "Invalid ancode specified for flexia");

        $this->prefix = (string)$prefix;
        $this->suffix = (string)$suffix;
        $this->ancode_id = $ancodeId;
    }
    
    function getPrefix() { return $this->prefix; }
    function getSuffix() { return $this->suffix; }
    function getAncodeId() { return $this->ancode_id; }
    
    function setPrefix($prefix) { $this->prefix = $prefix; }
}

class phpMorphy_Dict_FlexiaModel extends phpMorphy_Collection/*_Typed */{
    protected
        $id;
        
    function __construct($id) {
        parent::__construct(/*$this->createStorageCollection(), 'phpMorphy_Dict_Flexia'*/);
        $this->id = (int)$id;

        if($this->id < 0) {
            throw new Exception("Flexia model id must be positive int");
        }
    }
    
    function getId() {
        return $this->id;
    }
    
    function getFlexias() {
        return iterator_to_array($this);
    }
    
    /*
    protected function createStorageCollection() {
        return new phpMorphy_Collection();
    }
    */
}

class phpMorphy_Dict_PrefixSet extends phpMorphy_Collection/*_Typed*/ {
    protected
        $id;
    
    function __construct($id) {
        parent::__construct(/*$this->createStorageCollection(), 'string'*/);
        
        $this->id = (int)$id;

        if($this->id < 0) {
            throw new Exception("Prefix set id must be positive int");
        }
    }
    
    function getId() {
        return $this->id;
    }
    
    function getPrefixes() {
        return $this->getIterator();
    }
    
    /*
    protected function createStorageCollection() {
        return new phpMorphy_Collection();
    }
    */
}

class phpMorphy_Dict_AccentModel extends phpMorphy_Collection/*_Typed*/ {
    protected
        $id;
    
    function __construct($id) {
        parent::__construct(/*$this->createStorageCollection(), array('integer', 'NULL')*/);
        
        $this->id = (int)$id;

        if($this->id < 0) {
            throw new Exception("Accent model id must be positive int");
        }
    }
    
    function append($offset) {
        if($offset === null) {
            $this->addEmptyAccent();
        } else {
            parent::append((int)$offset);
        }
    }
    
    function addEmptyAccent() {
        parent::append(null);
    }
    
    static function isEmptyAccent($accent) {
        return null === $accent;
    }
    
    function getId() {
        return $this->id;
    }
    
    function getAccents() {
        return $this->getIterator();
    }
    
    /*
    protected function createStorageCollection() {
        return new phpMorphy_Collection();
    }
    */
}

interface phpMorphy_Dict_Lemma_Interface {
    function setPrefixId($prefixId);
    function setAncodeId($ancodeId);
    function getBase();
    function getFlexiaId();
    function getAccentId();
    function getPrefixId();
    function getAncodeId();
    function hasPrefixId();
    function hasAncodeId();
}

class phpMorphy_Dict_Lemma implements phpMorphy_Dict_Lemma_Interface {
    protected
        $base,
        $flexia_id,
        $accent_id,
        $prefix_id,
        $ancode_id;
    
    function __construct($base, $flexiaId, $accentId) {
        $this->base = (string)$base;
        $this->flexia_id = (int)$flexiaId;
        $this->accent_id = (int)$accentId;

        if($this->flexia_id < 0) {
            throw new Exception("flexia_id must be positive int");
        }

        if($this->accent_id < 0) {
            throw new Exception("accent_id must be positive int");
        }
    }
    
    function setPrefixId($prefixId) {
        if(is_null($prefixId)) {
            throw new phpMorphy_Exception("NULL id specified");
        }
        
        $this->prefix_id = (int)$prefixId;

        if($this->prefix_id < 0) {
            throw new Exception("prefix_id must be positive int");
        }
    }
    
    function setAncodeId($ancodeId) {
        if(is_null($ancodeId)) {
            throw new Exception("NULL id specified");
        }

        //phpMorphy_Dict_Ancode::checkAncodeId($ancodeId, "Invalid ancode specified for lemma");

        
        $this->ancode_id = $ancodeId;
    }
    
    function getBase() { return $this->base; }
    function getFlexiaId() { return $this->flexia_id; }
    function getAccentId() { return $this->accent_id; }
    function getPrefixId() { return $this->prefix_id; }
    function getAncodeId() { return $this->ancode_id; }
    
    function hasPrefixId() { return isset($this->prefix_id); }
    function hasAncodeId() { return isset($this->ancode_id); }
}
