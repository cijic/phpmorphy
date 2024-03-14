<?php
require_once(dirname(__FILE__) . '/source.php');

abstract class phpMorphy_Dict_Source_Xml_Section implements Iterator {
    private
        $reader,
        $section_name,
        $xml_file;
    
    function __construct($xmlFile) {
        $this->xml_file = $xmlFile;
        $this->section_name = $this->getSectionName();
    }
    
    private function createReader() {
        $reader = new XMLReader();
        if(false === ($reader->open($this->xml_file))) {
            throw new Exception("Can`t open '$this->xml_file' xml file");
        }
        
        return $reader;
    }
    
    private function closeReader() {
        $this->reader->close();
        $this->reader = null;
    }
    
    private function getReader($section) {
        $reader = $this->createReader();
        
        while($reader->read()) {
            if($reader->localName === 'options') {
                break;
            }
        }
        
        if($section !== 'options') {
            if(false === ($reader->next($section))) {
                //throw new Exception("Can`t seek to '$section' element in '{$this->xml_file}' file");
            }
        }
        
        return $reader;
    }
    
    function current() {
        return $this->getCurrentValue();
    }
    
    function next() {
        $this->readNext($this->reader);
        /*
        if($this->valid()) {
            $this->read();
        }
        */
    }
    
    function key() {
        return $this->getCurrentKey();
    }
    
    function rewind() {
        if(!is_null($this->reader)) {
            $this->reader->close();
        }
        
        $this->reader = $this->getReader($this->section_name);
        
        $this->next();
    }
    
    function valid() {
        return !is_null($this->reader);
    }
    
    protected function read() {
        if(
            !$this->reader->read() ||
            ($this->reader->nodeType == XMLReader::END_ELEMENT && $this->reader->localName === $this->section_name)
        ) {
            $this->closeReader();
            return false;
        }
        
        return true;
    }
    
    protected function isStartElement($name) {
        return $this->reader->nodeType == XMLReader::ELEMENT && $this->reader->localName === $name;
    }
    
    protected function isEndElement($name) {
        return
            ($this->reader->nodeType == XMLReader::ELEMENT || $this->reader->nodeType == XMLReader::END_ELEMENT)&&
            $this->reader->localName === $name;
    }
    
    abstract protected function getSectionName();
    abstract protected function readNext(XMLReader $reader);
    abstract protected function getCurrentKey();
    abstract protected function getCurrentValue();
}

class phpMorphy_Dict_Source_Xml_Section_Options extends phpMorphy_Dict_Source_Xml_Section {
    protected
        $current;
    
    protected function getSectionName() {
        return 'options';
    }
    
    protected function readNext(XMLReader $reader) {
        do {
            if($this->isStartElement('locale')) {
                if(!$this->current = $reader->getAttribute('name')) {
                    throw new Exception('Empty locale name found');
                }
                
                $this->read();
                
                break;
            }
        } while($this->read());
    }
    
    function rewind() {
        $this->current = null;
        
        parent::rewind();
    }
    
    protected function getCurrentKey() {
        return 'locale';
    }
    
    protected function getCurrentValue() {
        return $this->current;
    }
}

class phpMorphy_Dict_Source_Xml_Section_Flexias extends phpMorphy_Dict_Source_Xml_Section {
    protected
        $current;
    
    protected function getSectionName() {
        return 'flexias';
    }
    
    function rewind() {
        $this->current = null;
        
        parent::rewind();
    }
    
    protected function readNext(XMLReader $reader) {
        do {
            if($this->isStartElement('flexia_model')) {
                $flexia_model = new phpMorphy_Dict_FlexiaModel($reader->getAttribute('id'));
                
                while($this->read()) {
                    if($this->isStartElement('flexia')) {
                            $flexia_model->append(
                                new phpMorphy_Dict_Flexia(
                                    $reader->getAttribute('prefix'),
                                    $reader->getAttribute('suffix'),
                                    $reader->getAttribute('ancode_id')
                                )
                            );
                    } elseif($this->isEndElement('flexia_model')) {
                        break;
                    }
                }
                
                unset($this->current);
                $this->current = $flexia_model;
                
                break;
            }
        } while($this->read());
    }
    
    protected function getCurrentKey() {
        return $this->current->getId();
    }
    
    protected function getCurrentValue() {
        return $this->current;
    }
}

class phpMorphy_Dict_Source_Xml_Section_Prefixes extends phpMorphy_Dict_Source_Xml_Section {
    protected
        $current;
    
    protected function getSectionName() {
        return 'prefixes';
    }
    
    function rewind() {
        $this->current = null;
        
        parent::rewind();
    }
    
    protected function readNext(XMLReader $reader) {
        do {
            if($this->isStartElement('prefix_model')) {
                $prefix_model = new phpMorphy_Dict_PrefixSet($reader->getAttribute('id'));
                
                while($this->read()) {
                    if($this->isStartElement('prefix')) {
                            $prefix_model->append($reader->getAttribute('value'));
                    } elseif($this->isEndElement('prefix_model')) {
                        break;
                    }
                }
                
                unset($this->current);
                $this->current = $prefix_model;
                
                break;
            }
        } while($this->read());
    }
    
    protected function getCurrentKey() {
        return $this->current->getId();
    }
    
    protected function getCurrentValue() {
        return $this->current;
    }
}

class phpMorphy_Dict_Source_Xml_Section_Lemmas extends phpMorphy_Dict_Source_Xml_Section {
    protected
        $count,
        $current;
    
    protected function getSectionName() {
        return 'lemmas';
    }
    
    function rewind() {
        $this->count = 0;
        $this->current = null;
        
        parent::rewind();
    }
    
    protected function readNext(XMLReader $reader) {
        do {
            if($this->isStartElement('lemma')) {
                unset($this->current);

                $this->current = new phpMorphy_Dict_Lemma(
                    $reader->getAttribute('base'),
                    $reader->getAttribute('flexia_id'),
                    0
                );
                
                $prefix_id = $reader->getAttribute('prefix_id');
                $ancode_id = $reader->getAttribute('ancode_id');
                
                if(!is_null($prefix_id)) {
                    $this->current->setPrefixId($prefix_id);
                }
                
                if(!is_null($ancode_id)) {
                    $this->current->setAncodeId($ancode_id);
                }
                
                $this->count++;
                
                $this->read();
                
                break;
            }
        } while($this->read());
    }
    
    protected function getCurrentKey() {
        return $this->count - 1;
    }
    
    protected function getCurrentValue() {
        return $this->current;
    }
}

class phpMorphy_Dict_Source_Xml_Section_Poses extends phpMorphy_Dict_Source_Xml_Section {
    protected
        $current;
    
    protected function getSectionName() {
        return 'poses';
    }
    
    function rewind() {
        $this->current = null;
        
        parent::rewind();
    }
    
    protected function readNext(XMLReader $reader) {
        do {
            if($this->isStartElement('pos')) {
                $this->current = array(
                    'id' => (int)$reader->getAttribute('id'),
                    'name' => $reader->getAttribute('name'),
                    'is_predict' => (bool)$reader->getAttribute('is_predict')
                );
                
                $this->read();
                
                break;
            }
        } while($this->read());
    }
    
    protected function getCurrentKey() {
        return $this->current['id'];
    }
    
    protected function getCurrentValue() {
        return $this->current;
    }
}

class phpMorphy_Dict_Source_Xml_Section_Grammems extends phpMorphy_Dict_Source_Xml_Section {
    protected
        $current;
    
    protected function getSectionName() {
        return 'grammems';
    }
    
    function rewind() {
        $this->current = null;
        
        parent::rewind();
    }
    
    protected function readNext(XMLReader $reader) {
        do {
            if($this->isStartElement('grammem')) {
                $this->current = array(
                    'id' => (int)$reader->getAttribute('id'),
                    'name' => $reader->getAttribute('name')
                );
                
                $this->read();
                
                break;
            }
        } while($this->read());
    }
    
    protected function getCurrentKey() {
        return $this->current['id'];
    }
    
    protected function getCurrentValue() {
        return $this->current;
    }
}

class phpMorphy_Dict_Source_Xml_Section_Ancodes extends phpMorphy_Dict_Source_Xml_Section {
    protected
        $poses,
        $grammems,
        
        $current;
    
    function __construct($xmlFile) {
        $this->poses = iterator_to_array(new phpMorphy_Dict_Source_Xml_Section_Poses($xmlFile));
        $this->grammems = iterator_to_array(new phpMorphy_Dict_Source_Xml_Section_Grammems($xmlFile));
        
        parent::__construct($xmlFile);
    }
    
    protected function getSectionName() {
        return 'ancodes';
    }
    
    function rewind() {
        $this->current = null;
        
        parent::rewind();
    }
    
    protected function readNext(XMLReader $reader) {
        do {
            if($this->isStartElement('ancode')) {
                $pos_id = (int)$reader->getAttribute('pos_id');
                
                if(!isset($this->poses[$pos_id])) {
                    throw new Exception("Invalid pos id '$pos_id' found in ancode '" . $reader->getAttribute('id') . "'");
                }
                
                $pos = $this->poses[$pos_id];
                
                $ancode = new phpMorphy_Dict_Ancode(
                    $reader->getAttribute('id'),
                    $pos['name'],
                    $pos['is_predict']
                );
                
                while($this->read()) {
                    if($this->isStartElement('grammem')) {
                        $grammem_id = (int)$reader->getAttribute('id');
                        
                        if(!isset($this->grammems[$grammem_id])) {
                            throw new Exception("Invalid grammem id '$grammem_id' found in ancode '" . $ancode->getId() . "'");
                        }
                        
                        $ancode->addGrammem($this->grammems[$grammem_id]['name']);
                    } elseif($this->isEndElement('ancode')) {
                        break;
                    }
                }
                
                unset($this->current);
                $this->current = $ancode;
                
                break;
            }
        } while($this->read());
    }
    
    protected function getCurrentKey() {
        return $this->current->getId();
    }
    
    protected function getCurrentValue() {
        return $this->current;
    }
}

class phpMorphy_Dict_Source_Xml implements phpMorphy_Dict_Source_Interface {
    protected
        $xml_file,
        $locale;
        
    function __construct($xmlFile) {
        $this->xml_file = $xmlFile;
        
        foreach(new phpMorphy_Dict_Source_Xml_Section_Options($xmlFile) as $key => $value) {
            if('locale' === $key) {
                $this->locale = $value;
                break;
            }
        }
        
        if(!strlen($this->locale)) {
            throw new Exception("Can`t find locale in '{$xmlFile}' file");
        }
    }
    
    function getName() {
        return 'morphyXml';
    }
    
    function getLanguage() {
        return $this->locale;
    }
    
    function getDescription() {
        return "Morphy xml file '{$this->xml_file}'";
    }
    
    function getAncodes() {
        return new phpMorphy_Dict_Source_Xml_Section_Ancodes($this->xml_file);
    }
    
    function getFlexias() {
        return new phpMorphy_Dict_Source_Xml_Section_Flexias($this->xml_file);
    }
    
    function getPrefixes() {
        return new phpMorphy_Dict_Source_Xml_Section_Prefixes($this->xml_file);
    }
    
    function getLemmas() {
        return new phpMorphy_Dict_Source_Xml_Section_Lemmas($this->xml_file);
    }
    
    function getAccents() {
        // HACK: all lemmas points to accent model with 0 index and length = 4096
        $accent_model = new phpMorphy_Dict_AccentModel(0);
        $accent_model->import(new ArrayIterator(array_fill(0, 4096, null)));
        
        return new ArrayIterator(array( 0 => $accent_model));
    }
}
