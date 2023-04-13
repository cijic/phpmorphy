<?php
require_once(dirname(__FILE__) . '/writer.php');
require_once(dirname(__FILE__) . '/utils/validator.php');
require_once(dirname(__FILE__) . '/../source/source_normalized.php');

class phpMorphy_Dict_Writer_Xml extends phpMorphy_Dict_Writer_Base {
    const DUMP_EVERY_FLEXIA_MODEL = 64;
    const DUMP_EVERY_LEMMA = 1024;

    private
        $path,
        $validator
        ;
    
    function __construct($outPath) {
        parent::__construct();
        $this->path = $outPath;
    }
    
    function write(phpMorphy_Dict_Source_Interface $source) {
        $this->getObserver()->onStart();

        try {
            $source = phpMorphy_Dict_Source_Normalized_Ancodes::wrap($source);
            
            $xml_opts = $this->getXmlOptions();
            $writer = $this->createXmlWriter($this->path);
            
            $validator = new phpMorphy_Dict_Writer_Utils_Validator();
            $validator->setAncodes($source->getAncodesNormalized());
            $this->validator = $validator;
            
            $writer->startDocument($xml_opts['xml_version'], $xml_opts['xml_encoding']);
            {
                $writer->writeDtd('phpmorphy', $xml_opts['dtd_pub_id'], $xml_opts['dtd_sys_id']);
                $writer->writeComment('This file generated with ' . __CLASS__ . ' at ' . date('r'));
                
                // morphy
                $writer->startElement('phpmorphy');
                {
                    $this->writeOptions($writer, $source);
                    $this->writePoses($writer, $source->getPoses());
                    $this->writeGrammems($writer, $source->getGrammems());
                    $this->writeAncodes($writer, $source->getAncodesNormalized());
                    $validator->setFlexiasCount($this->writeFlexias($writer, $source->getFlexiasNormalized()));
                    $validator->setPrefixesCount($this->writePrefixes($writer, $source->getPrefixes()));
                    $this->writeLemmas($writer, $source->getLemmasNormalized());
                }
                $writer->endElement();
            }
            
            $writer->endDocument();
        } catch (Exception $e) {
            $this->getObserver()->onEnd();
            throw $e;
        }

        $this->getObserver()->onEnd();
    }
    
    private function writeDummy(XMLWriter $writer) {
        //$writer->writeComment('hi');
    }
    
    private function writeOptions(XMLWriter $writer, phpMorphy_Dict_Source_Interface $source) {
        $this->log(__METHOD__);

        $writer->startElement('options');
        {
            $this->writeDummy($writer);         
            $writer->startElement('locale');
            {
                $writer->writeAttribute('name', $source->getLanguage());
            }           
            $writer->endElement();
        }
        $writer->endElement();
    }
    
    private function writeFlexias(XMLWriter $writer, $it) {
        $this->log(__METHOD__);
        $count = 0;
        
        $this->log("Count flexia models");
        if(!iterator_count($it)) return;
        $this->log("done");

        $writer->startElement('flexias');
        {
            $this->writeDummy($writer);
            foreach($it as $flexia_model) {
                $writer->startElement('flexia_model');
                {
                    $writer->writeAttribute('id', $flexia_model->getId());
                    
                    $this->writeDummy($writer);
                    foreach($flexia_model as $flexia) {
                        $writer->startElement('flexia');
                        {   
                            $ancode_id = $flexia->getAncodeId();
                            
                            if(!$this->validator->validateAncodeId($ancode_id)) {
                                throw new Exception("Unknown ancode_id '$ancode_id' found");
                            }
                            
                            $writer->writeAttribute('prefix', $flexia->getPrefix());
                            $writer->writeAttribute('suffix', $flexia->getSuffix());
                            $writer->writeAttribute('ancode_id', $ancode_id);
                        }
                        $writer->endElement();
                    }
                }
                $writer->endElement();
                
                $count++;

                if(0 == ($count % self::DUMP_EVERY_FLEXIA_MODEL)) {
                    $this->log("$count flexia models done");
                }
            }
        }
        $writer->endElement();
        
        return $count;
    }
    
    private function writePrefixes(XMLWriter $writer, $it) {
        $this->log(__METHOD__);

        $count = 0;

        if(!iterator_count($it)) return;
        
        $writer->startElement('prefixes');
        {
            $this->writeDummy($writer);
            foreach($it as $prefix_set) {
                $writer->startElement('prefix_model');
                {
                    $writer->writeAttribute('id', $prefix_set->getId());
                    
                    $this->writeDummy($writer);
                    foreach($prefix_set as $prefix) {
                        $writer->startElement('prefix');
                        {
                            $writer->writeAttribute('value', $prefix);
                        }
                        $writer->endElement();
                    }
                }
                $writer->endElement();
                
                $count++;
            }
        }
        $writer->endElement();
        
        return $count;
    }
    
    private function writeAccents(XMLWriter $writer, $it) {
        $this->log(__METHOD__);

        $count = 0;
        
        if(!iterator_count($it)) return;

        $writer->startElement('accents');
        {
            $this->writeDummy($writer);
            foreach($it as $accent_model) {
                $writer->startElement('accent_model');
                {
                    $writer->writeAttribute('id', $accent_model->getId());
                    
                    $this->writeDummy($writer);
                    foreach($accent_model as $accent) {
                        $writer->startElement('accent');
                        {
                            if(!$accent_model->isEmptyAccent($accent)) {
                                $writer->writeAttribute('value', $accent);
                            }
                        }
                        $writer->endElement();
                    }
                }
                $writer->endElement();
                
                $count++;
            }
        }
        $writer->endElement();
        
        return $count;
    }
    
    private function writeLemmas(XMLWriter $writer, $it) {
        $this->log(__METHOD__);

        $this->log("Count lemmas");
        if(!iterator_count($it)) return;
        $this->log("done");
        $count = 0;

        $writer->startElement('lemmas');
        {
            $this->writeDummy($writer);
            foreach($it as $lemma) {
                $writer->startElement('lemma');
                {
                    $flexia_id = $lemma->getFlexiaId();
                    
                    if(!$this->validator->validateFlexiaId($flexia_id)) {
                        throw new Exception("Unknown flexia_id '$flexia_id' found");
                    }
                    
                    $writer->writeAttribute('base', $lemma->getBase());
                    $writer->writeAttribute('flexia_id', $flexia_id);
                    
                    if($lemma->hasPrefixId()) {
                        $prefix_id = $lemma->getPrefixId();
                        
                        if(!$this->validator->validatePrefixId($prefix_id)) {
                            throw new Exception("Unknown prefix_id '$prefix_id' found");
                        }
                        
                        $writer->writeAttribute('prefix_id', $prefix_id);
                    }
                    
                    if($lemma->hasAncodeId()) {
                        $ancode_id = $lemma->getAncodeId();
                        
                        if(!$this->validator->validateAncodeId($ancode_id)) {
                            throw new Exception("Unknown common_ancode_id '$ancode_id' found");
                        }
                        
                        $writer->writeAttribute('ancode_id', $ancode_id);
                    }
                }
                $writer->endElement();

                $count++;

                if(0 == ($count % self::DUMP_EVERY_LEMMA)) {
                    $this->log("$count lemmas done");
                }
            }
        }
        $writer->endElement();
    }
    
    private function writePoses(XMLWriter $writer, $it) {
        $this->log(__METHOD__);

        $writer->startElement('poses');
        {
            $this->writeDummy($writer);
            foreach($it as $pos) {
                $writer->startElement('pos');
                {
                    $writer->writeAttribute('id', $pos->getId());
                    $writer->writeAttribute('name', $pos->getName());
                    $writer->writeAttribute('is_predict', $pos->isPredict() ? '1' : '0');
                }
                $writer->endElement();
            }
        }
        $writer->endElement();
    }
    
    private function writeGrammems(XMLWriter $writer, $it) {
        $this->log(__METHOD__);

        $writer->startElement('grammems');
        {
            $this->writeDummy($writer);
            foreach($it as $grammem) {
                $writer->startElement('grammem');
                {
                    $writer->writeAttribute('id', $grammem->getId());
                    $writer->writeAttribute('name', $grammem->getName());
                    $writer->writeAttribute('shift', $grammem->getShift());
                }
                $writer->endElement();
            }
        }
        $writer->endElement();
    }
    
    private function writeAncodes(XMLWriter $writer, $it) {
        $this->log(__METHOD__);

        $writer->startElement('ancodes');
        {
            $this->writeDummy($writer);
            foreach($it as $ancode) {
                $writer->startElement('ancode');
                {
                    $writer->writeAttribute('id', $ancode->getId());
                    $writer->writeAttribute('name', $ancode->getName());
                    $writer->writeAttribute('pos_id', $ancode->getPartOfSpeechId());

                    $this->writeDummy($writer);
                    foreach($ancode->getGrammemsIds() as $id) {
                        $writer->startElement('grammem');
                        {
                            $writer->writeAttribute('id', $id);
                        }
                        $writer->endElement();
                    }
                }
                $writer->endElement();
            }
        }
        $writer->endElement();
    }
    
    private function getXmlOptions() {
        return array(
            'xml_version' => '1.0',
            'xml_encoding' => 'utf-8',
            'dtd_pub_id' => false,
            'dtd_sys_id' => false, //'morphy.dtd'
        );
    }
    
    private function createXmlWriter($fileName) {
        $writer = new XMLWriter();
        
        if(false === $writer->openUri($fileName)) {
            throw new Exception("Can`t create $fileName xml file for XMLWriter");
        }
        
        $writer->setIndentString("\t");
        $writer->setIndent(4);
        
        return $writer;
    }
}
