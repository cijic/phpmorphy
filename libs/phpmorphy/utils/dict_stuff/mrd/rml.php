<?php
require_once(dirname(__FILE__) . '/../../libs/iterators.php');

class phpMorphy_Rml_Exception extends Exception { }

class phpMorphy_Rml_IniFile {
	const RML_PLACEHOLDER = '$RML';
	const RML_ENV_VAR = 'RML';
	
	protected
		$ini,
		$rml;

	function __construct() {
		$this->ini = $this->parseFile($this->getIniPath());
	}
	
	function getGramTabPath($language) {
		return $this->getValue($this->getGramTabPathKey($language));
	}
	
	function export() {
		return $this->ini;
	}
	
	function keyExists($key) {
		return array_key_exists($key, $this->ini);
	}
	
	function getValue($key) {
		if(!$this->keyExists($key)) {
			throw new phpMorphy_Rml_Exception("Key $key not exists in rml.ini");
		}
		
		return $this->ini[$key];
	}
	
	protected function getGramTabPathKey($language) {
		if(!strlen($language)) {
			throw new phpMorphy_Rml_Exception("You must specify language for gram tab file");
		}
		
		$uc_lang = ucfirst(strtolower($language));
		$first_char = $uc_lang[0];
		
		return 'Software\\Dialing\\Lemmatizer\\' . $uc_lang . '\\' . $first_char . 'gramtab';
	}
	
	protected function parseFile($file) {
		$result = array();
		
		try {
			$lines = iterator_to_array($this->createIterators($file));
		} catch (Exception $e) {
			throw new phpMorphy_Rml_Exception("Can`t open $file file: " . $e->getMessage());
		}
		
		foreach($lines as $line) {
			if(false !== ($pos = strpos($line, ' ')) || false !== ($pos = strpos($line, "\t"))) {
				$key = trim(substr($line, 0, $pos));
				$value = $this->replaceRmlVar(trim(substr($line, $pos + 1)));
				
				if(strlen($key)) {
					$result[$key] = $value;
				}
			}
		}
		
		return $result;
	}
	
	protected function createIterators($file) {
		return new phpMorphy_Iterator_NotEmptyLines($this->openFile($file));
	}
	
	protected function openFile($file) {
		return new SplFileObject($file);
	}
	
	protected function replaceRmlVar($line) {
		return str_replace(self::RML_PLACEHOLDER, $this->getRmlVar(), $line);
	}
	
	protected function getRmlVar() {
		if(!isset($this->rml)) {
			if(false === ($this->rml = getenv(self::RML_ENV_VAR))) {
				throw new phpMorphy_Rml_Exception("Can`t find RML environment variable");
			}
		}
		
		return $this->rml;
	}
	
	protected function getIniPath() {
		return $this->getRmlVar() . '/Bin/rml.ini';
	}
}