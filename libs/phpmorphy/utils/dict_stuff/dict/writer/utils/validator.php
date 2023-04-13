<?php
require_once(dirname(__FILE__) . '/ancodes.php');

class phpMorphy_Dict_Writer_Utils_Validator {
    protected
        $flexias_count,
        $accents_count,
        $sessions_count,
        $prefixes_count,
        $ancodes_map;
    
    function setFlexiasCount($count) { $this->flexias_count = (int)$count; }
    function setAccentsCount($count) { $this->accents_count = (int)$count; }
    function setSessionsCount($count) { $this->sessions_count = (int)$count; }
    function setPrefixesCount($count) { $this->prefixes_count = (int)$count; }
    
    function setAncodes($it) {
        $this->ancodes_map = array();
        
        foreach($it as $ancode) {
            if(!$ancode instanceof phpMorphy_Dict_Ancode_Normalized) {
                throw new Exception("setAncodes() recieve iterator over objects of phpMorphy_Dict_Ancode_Normalized class");
            }
            
            $this->ancodes_map[$ancode->getId()] = 1;
        }
    }
    
    function validateFlexiaId($id) { return (int)$id < $this->flexias_count; }
    function validateAccentId($id) { return (int)$id < $this->accents_count; }
    function validateSessionId($id) { return (int)$id < $this->sessions_count; }
    function validatePrefixId($id) { return (int)$id < $this->prefixes_count; }
    function validateAncodeId($id) { return isset($this->ancodes_map[$id]); }
}
