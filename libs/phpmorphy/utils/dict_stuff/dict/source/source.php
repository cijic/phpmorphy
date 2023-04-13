<?php
require_once(dirname(__FILE__) . '/../model.php');

interface phpMorphy_Dict_Source_Interface {
    /**
     * @return string
     */
    function getName();
    /**
     * ISO3166 country code separated by underscore(_) from ISO639 language code
     * ru_RU, uk_UA for example
     * @return string
     */
    function getLanguage();
    /**
     * Any string
     * @return string
     */
    function getDescription();
    
    /**
     * @return Iterator over objects of phpMorphy_Dict_Ancode
     */
    function getAncodes();
    /**
     * @return Iterator over objects of phpMorphy_Dict_FlexiaModel
     */
    function getFlexias();
    /**
     * @return Iterator over objects of phpMorphy_Dict_PrefixSet
     */
    function getPrefixes();
    /**
     * @return Iterator over objects of phpMorphy_Dict_AccentModel
     */
    function getAccents();
    /**
     * @return Iterator over objects of phpMorphy_Dict_Lemma
     */
    function getLemmas();
}

