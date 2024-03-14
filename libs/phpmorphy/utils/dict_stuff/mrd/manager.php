<?php
require_once(dirname(__FILE__) . '/gramtab.php');
require_once(dirname(__FILE__) . '/reader.php');
require_once(dirname(__FILE__) . '/mwz.php');
require_once(dirname(__FILE__) . '/rml.php');
require_once(dirname(__FILE__) . '/../dict/model.php');

class phpMorphy_MrdManager_Exception extends Exception { }

class phpMorphy_MrdManager {
	protected
		$opened = false,
		$language,
		$encoding,
		$mrd,
		$gram_info;
	
	function open($filePath) {
		$mwz = $this->openMwz($filePath);
		$this->encoding = $mwz->getEncoding();
		$mrd_path = $mwz->getMrdPath();
		$language = $mwz->getLanguage();
		
		$this->mrd = $this->openMrd($mrd_path, $this->encoding);
		
		$this->gram_info = $this->convertFromGramtabToDict(
			$this->openGramTab($language, $this->encoding)
		);
		
		$this->language = $language;
		$this->opened = true;
	}
		
	function isOpened() {
		return $this->opened;
	}
	
	protected function checkOpened() {
		if(!$this->isOpened()) {
			throw new phpMorphy_MrdManager_Exception(__CLASS__ . " not initialized, use open() method");
		}
	}
	
	function getEncoding() {
		$this->checkOpened();
		return $this->getEncoding();
	}
	
	function getLanguage() {
		$this->checkOpened();
		return $this->language;
	}
	
	function getMrd() {
		$this->checkOpened();
		return $this->mrd;
	}
	
	function getGramInfo() {
		$this->checkOpened();
		return $this->gram_info;
	}
	
	protected function convertFromGramtabToDict($ancodes) {
		$result = array();
		
		foreach($ancodes as $ancode) {
			$ancode_id = $ancode->getAncode();
			
			$result[$ancode_id] = new phpMorphy_Dict_Ancode(
				$ancode_id,
				$ancode->getPartOfSpeech(),
				$ancode->isPredictPartOfSpeech(),
				$ancode->getGrammems()
			);
		}
		
		return new ArrayIterator($result);
	}
	
	protected function openMwz($wmzFile) {
		return new phpMorphy_Mwz_File($wmzFile);
	}
	
	protected function openMrd($path, $encoding) {
		return new phpMorphy_Mrd_File($path, $encoding);
	}
	
	protected function openGramTab($lang, $encoding) {
		try {
			return $this->createGramTabFile(
				$this->getGramTabPath($lang),
				$encoding,
				$this->createGramInfoFactory($lang)
			);
		} catch(Exception $e) {
			throw new phpMorphy_MrdManager_Exception('Can`t parse gramtab file: ' . $e->getMessage());
		}
	}
	
	protected function getGramTabPath($lang) {
		$rml = new phpMorphy_Rml_IniFile();
		
		return $rml->getGramTabPath($lang);
	}
	
	protected function createGramInfoFactory($lang) {
		return new phpMorphy_GramTab_GramInfoFactory($lang);
	}
	
	protected function createGramTabFile($file, $encoding, phpMorphy_GramTab_GramInfoFactory $factory) {
		return new phpMorphy_GramTab_File($file, $encoding, $factory);
	}
}
