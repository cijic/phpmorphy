<?php
class phpMorphy_GrammemsProvider_ru_RU extends phpMorphy_GrammemsProvider_ForFactory {
    static protected $self_encoding = 'windows-1251';
    static protected $instances = array();

    static protected $grammems_map = array( 
        'род' => array('МР', 'ЖР', 'СР'), 
        'одушевленность' => array('ОД', 'НО'), 
        'число' => array('ЕД', 'МН'), 
        'падеж' => array('ИМ', 'РД', 'ДТ', 'ВН', 'ТВ', 'ПР', 'ЗВ', '2'), 
        'залог' => array('ДСТ', 'СТР'), 
        'время' => array('НСТ', 'ПРШ', 'БУД'), 
        'повелительная форма' => array('ПВЛ'), 
        'лицо' => array('1Л', '2Л', '3Л'), 
        'краткость' => array('КР'), 
        'сравнительная форма' => array('СРАВН'), 
        'превосходная степень' => array('ПРЕВ'),
        'вид' => array('СВ', 'НС'),
        'переходность' => array('ПЕ', 'НП'),
        'безличный глагол' => array('БЕЗЛ'),
    ); 

    function getSelfEncoding() {
        return 'windows-1251';
    }

    function getGrammemsMap() {
        return self::$grammems_map;
    }

    static function instance(phpMorphy $morphy) {
        $key = $morphy->getEncoding();

        if(!isset(self::$instances[$key])) {
            $class = __CLASS__;
            self::$instances[$key] = new $class($key);
        }

        return self::$instances[$key];
    }
}
