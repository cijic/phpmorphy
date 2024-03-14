<?php
require_once(dirname(__FILE__) . '/../../libs/iterators.php');
require_once(dirname(__FILE__) . '/../dict/model.php');

class phpMorphy_Mrd_Exception extends Exception { }

abstract class phpMorphy_Mrd_Section implements Iterator, Countable {
	const INTERNAL_ENCODING = 'utf-8';
	
	protected
		$file_it,
		$encoding,
		$start_line,
		$current_line,
		$section_size;
		
	function __construct(SeekableIterator $file, $encoding, $startLine) {
		$this->file_it = $file;
		
		$this->encoding = $this->prepareEncoding($encoding);
		$this->start_line = $startLine;
		$this->section_size = $this->readSectionSize($file);
	}
	
	protected function prepareEncoding($encoding) {
		$encoding = strtolower($encoding);
		
		if($encoding == 'utf8') {
			$encoding = 'utf-8';
		}
		
		return $encoding;
	}
	
	protected function openFile($fileName) {
		return new SplFileObject($fileName);
	}
	
	function getSectionLinesCount() {
		return $this->count() + 1;
	}
	
	function count() {
		return $this->section_size;
	}
	
	function key() {
		return $this->current_line;
	}
	
	function getPosition() {
		return $this->current_line;
	}
	
	function rewind() {
		$this->current_line = 0;
		$this->file_it->seek($this->start_line + 1);
	}
	
	function valid() {
		if($this->current_line >= $this->section_size) {
			return false;
		}
		
		if(!$this->file_it->valid()) {
			throw new phpMorphy_Mrd_Exception(
				"Too small section {$this->current_line} lines gathered, $this->section_size expected"
			);
		}
		
		return true;
	}
	
	function current() {
		return $this->processLine(rtrim($this->file_it->current()));
	}
	
	function next() {
		$this->file_it->next();
		$this->current_line++;
	}
	
	protected function iconv($string) {
		if($this->encoding == self::INTERNAL_ENCODING) {
			return $string;
		}
		
		return iconv($this->encoding, self::INTERNAL_ENCODING, $string);
	}
	
	protected function readSectionSize(SeekableIterator $it) {
		$it->seek($this->start_line);
		
		if(!$it->valid()) {
			throw new phpMorphy_Mrd_Exception("Can`t read section size, iterator not valid");
		}
		
		$size = trim($it->current());
		
		if(!preg_match('~^[0-9]+$~', $size)) {
			throw new phpMorphy_Mrd_Exception("Invalid section size: $size");
		}
		
		return (int)$size;
	}
	
	protected function processLine($line) {
		return $line;
	}
}

class phpMorphy_Mrd_Section_Flexias extends phpMorphy_Mrd_Section {
	const COMMENT_STRING = 'q//q';
	
	protected function processLine($line) {
		$line = $this->iconv($this->removeComment($line));
		
		$model = new phpMorphy_Dict_FlexiaModel($this->getPosition());
		
		foreach(explode('%', substr($line, 1)) as $token) {
			//$parts = array_map('trim', explode('*', $token));
			$parts = explode('*', $token);
			
			switch(count($parts)) {
				case 2:
					$ancode = $parts[1];
					$prefix = '';
					break;
				case 3:
					$ancode = $parts[1];
					$prefix = $parts[2];
					break;
				default:
					throw new phpMorphy_Mrd_Exception("Invalid flexia string($token) in str($line)");
			}

			$flexia = $parts[0];
			
			$model->append(
				new phpMorphy_Dict_Flexia(
					$prefix, //$this->iconv($prefix),
					$flexia, //$this->iconv($flexia),
					$ancode
				)
			);
		}
		
		return $model;
	}

	protected function removeComment($line) {
		if(false !== ($pos = strrpos($line, self::COMMENT_STRING))) {
			return rtrim(substr($line, 0, $pos));
		} else {
			return $line;
		}
	}
}

class phpMorphy_Mrd_Section_Accents extends phpMorphy_Mrd_Section {
	const UNKNOWN_ACCENT_VALUE = 255;
	
	protected function processLine($line) {
		if(substr($line, -1, 1) == ';') {
			$line = substr($line, 0, -1);
		}

		$result = new phpMorphy_Dict_AccentModel($this->getPosition());
		$result->import(
			new ArrayIterator(
				array_map(
					array($this, 'processAccentValue'),
					explode(';', $line)
				)
			)
		);
		
		return $result;
	}
	
	protected function processAccentValue($item) {
		$item = (int)$item;
		
		if($item == self::UNKNOWN_ACCENT_VALUE) {
			$item = null;
		}
		
		return $item;
	}
}

class phpMorphy_Mrd_Section_Sessions extends phpMorphy_Mrd_Section {
}

class phpMorphy_Mrd_Section_Prefixes extends phpMorphy_Mrd_Section {
	protected function processLine($line) {
		$line = $this->iconv($line);
		
		$result = new phpMorphy_Dict_PrefixSet($this->getPosition());
		
		$result->import(
			new ArrayIterator(
				array_map('trim', explode(',', $line))
			)
		);
		
		return $result;
	}
}

class phpMorphy_Mrd_Section_Lemmas extends phpMorphy_Mrd_Section {
	protected function processLine($line) {
		//if(6 != count($tokens = array_map('trim', explode(' ', $line)))) {
		$line = $this->iconv($line);

		if(6 != count($tokens = explode(' ', $line))) {
			throw new phpMorphy_Mrd_Exception("Invalid lemma str('$line'), too few tokens");
		}

		$base = trim($tokens[0]);
		
		if($base === '#') {
			$base = '';
		}
		
		$lemma = new phpMorphy_Dict_Lemma(
			$base, //$this->iconv(trim($tokens[0])), // base
			(int)$tokens[1], // flexia_id
			(int)$tokens[2] // accent_id
		);
		
		if('-' !== $tokens[4]) {
			$lemma->setAncodeId($tokens[4]);
		}
		
		if('-' !== $tokens[5]) {
			$lemma->setPrefixId((int)$tokens[5]);
		}
		
		return $lemma;
	}
}

class phpMorphy_Mrd_File {
	protected 
		$flexias,
		$accents,
		$sessions,
		$prefixes,
		$lemmas
		;
	
	function __construct($fileName, $encoding) {
		$line = 0;
		$this->initSections($line, $fileName, $encoding);
	}
	
	protected function initSections(&$startLine, $fileName, $encoding) {
		foreach($this->getSectionsNames() as $sectionName) {
			try {
				$section = $this->createNewSection(
					$sectionName,
					$fileName,
					$encoding,
					$startLine
				);
				
				$this->$sectionName = $section;
			} catch(Exception $e) {
				throw new phpMorphy_Mrd_Exception("Can`t init '$sectionName' section: " . $e->getMessage());
			}
		}
	}
	
	protected function createNewSection($sectionName, $fileName, $encoding, &$lineNo) {
		$sect_clazz = $this->getSectionClassName($sectionName);
		
		$section = new $sect_clazz($this->openFile($fileName), $encoding, $lineNo);
		$lineNo += $section->getSectionLinesCount();
		
		return $section;
	}
	
	protected function getSectionsNames() {
		return array(
			'flexias',
			'accents',
			'sessions',
			'prefixes',
			'lemmas'
		);
	}
	
	protected function openFile($fileName) {
		return new SplFileObject($fileName);
	}
	
	protected function getSectionClassName($sectionName) {
		return 'phpMorphy_Mrd_Section_' . ucfirst(strtolower($sectionName));
	}
	
	function __get($propName) {
		if(!preg_match('/^\w+_section$/', $propName)) {
			throw new phpMorphy_Mrd_Exception("Unsupported prop name given $propName");
		}
		
		list($sect_name) = explode('_', $propName);
		
		if(!isset($this->$sect_name)) {
			throw new phpMorphy_Mrd_Exception("Invalid section name given $propName");
		}
		
		return $this->$sect_name;
	}
}
