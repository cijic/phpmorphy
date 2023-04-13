<?php
require_once(dirname(__FILE__) . '/../../mrd/manager.php');
require_once(dirname(__FILE__) . '/source.php');
require_once(dirname(__FILE__) . '/../../../libs/collections.php');

class phpMorphy_Dict_Source_Mrd implements phpMorphy_Dict_Source_Interface {
    protected
        $manager;
    
    function __construct($mwzFilePath) {
        $this->manager = $this->createMrdManager($mwzFilePath);
    }
    
    protected function createMrdManager($mwzPath) {
        $manager = new phpMorphy_MrdManager();
        $manager->open($mwzPath);
        
        return $manager;
    }
    
    function getName() {
        return 'mrd';
    }
    
    // phpMorphy_Dict_Source_Interface
    function getLanguage() {
        $lang = strtolower($this->manager->getLanguage());
        
        switch($lang) {
            case 'russian':
                return 'ru_RU';
            case 'english':
                return 'en_EN';
            case 'german':
                return 'de_DE';
            default:
                return $this->manager->getLanguage();
        }
    }
    
    function getDescription() {
        return 'Dialing dictionary file for ' . $this->manager->getLanguage() . ' language';
    }
    
    function getAncodes() {
        return $this->manager->getGramInfo();
    }
    
    function getFlexias() {
        return $this->manager->getMrd()->flexias_section;
    }
    
    function getPrefixes() {
        return $this->manager->getMrd()->prefixes_section;
    }
    
    function getAccents() {
        return $this->manager->getMrd()->accents_section;
    }
    
    function getLemmas() {
        return $this->manager->getMrd()->lemmas_section;
    }
}
