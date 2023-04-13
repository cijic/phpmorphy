<?php
class phpMorphy_Mwz_Exception extends Exception { }

class phpMorphy_Mwz_File {
    protected
        $mwz_path,
        $values = array();
    
    function __construct($filePath) {
        $this->mwz_path = $filePath;
        $this->parseFile($filePath);
    }
    
    function export() {
        return $this->values;
    }
    
    function keyExists($key) {
        return array_key_exists($key, $this->values);
    }
    
    function getValue($key) {
        if(!$this->keyExists($key)) {
            throw new phpMorphy_Mrd_Exception("Key $key not exists in mwz file '$this->mwz_path'");
        }
        
        return $this->values[$key];
    }
    
    function getMrdPath() {
        return dirname($this->mwz_path) . DIRECTORY_SEPARATOR . $this->getValue('MRD_FILE');
    }
    
    function getEncoding() {
        $lang = $this->getLanguage();
        
        if(false === ($default = $this->getEncodingForLang($lang))) {
            throw new phpMorphy_Mrd_Exception("Can`t determine encoding for '$lang' language");
        }
        
        return $default;
    }
    
    function getLanguage() {
        return strtolower($this->getValue('LANG'));
    }
    
    static function getEncodingForLang($lang) {
        switch(strtolower($lang)) {
            case 'russian':
                return 'windows-1251';
            case 'english':
                return 'windows-1250';
            case 'german':
                return 'windows-1252';
            default:
                return false;
        }
    }
    
    protected function parseFile($path) {
        try {
            $lines = iterator_to_array($this->openFile($path));
        } catch (Exception $e) {
            throw new phpMorphy_Mrd_Exception("Can`t open $path mwz file '$path': " . $e->getMessage());
        }
        
        foreach(array_map('trim', $lines) as $line) {
            $pos = strcspn($line, " \t");
            
            if($pos !== strlen($line)) {
                $key = trim(substr($line, 0, $pos));
                $value = trim(substr($line, $pos + 1));
                
                if(strlen($key)) {
                    $this->values[$key] = $value;
                }
            } elseif(strlen($line)) {
                $this->values[$line] = null;
            }
        }
    }
    
    protected function openFile($file) {
        return new SplFileObject($file);
    }
}
