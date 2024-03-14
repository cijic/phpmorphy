<?php
require(dirname(__FILE__) . '/../../libs/iterators.php');

// Requires mb extension, assumes internal_encoding as UTF-8 !!!
// This limited implementation, many features not implemented (such as affix aliases and etc)

class phpMorphy_Hunspell_Exception extends Exception { }

abstract class phpMorphy_Hunspell_Affix {
	protected
		$remove_len,
		$remove,
		$append,
		$find,
		$find_len,
		$morph,
		$reg,
		$is_simple,
		$is_empty
		;
	
	function __construct($find, $remove, $append, $morph = null) {
		$this->remove_len = mb_strlen((string)$remove);
		$this->remove = $remove;
		$this->append = $append;
		$this->morph = $morph;
		$this->find = $find;
		$this->find_len = mb_strlen($find);
		$this->is_simple = $this->isSimple($find);
		$this->is_empty = $this->isEmpty($find);
		
		$this->reg = $this->getRegExp($find);
	}
	
	function getRemoveLength() { return $this->remove_len; }
	function isMorphDescription() { return isset($this->morph); }
	function getMorphDescription() { return $this->morph; }
	
	function isMatch($word) {
		if($this->is_empty) {
			return true;
		}
		
		if($this->is_simple) {
			return $this->simpleMatch($word);
		} else {
			//return false;
			return preg_match($this->reg, $word) > 0;
			//return mb_ereg_match($this->reg, $word);
		}
	}
	
	protected function isSimple($find) {
		return strpos($find, '[') === false && strpos($find, '.') === false;
	}
	
	protected function isEmpty($find) {
		return $find === '.';
	}
	
	abstract function generateWord($word);
	
	abstract protected function simpleMatch($word);
	abstract protected function getRegExp($find);
}

class phpMorphy_Hunspell_Prefix extends phpMorphy_Hunspell_Affix {
	protected function getRegExp($find) {
		return "~^{$find}~iu";
	}
	
	function generateWord($word) {
		if(!$this->isMatch($word)) {
			return false;
		}
		
		if($this->remove_len && mb_strlen($word) >= $this->remove_len) {
			$word = mb_substr($word, $this->remove_len);
		}
		
		return "{$this->append}$word";
	}
	
	protected function simpleMatch($word) {
		return mb_substr($word, 0, $this->find_len) == $this->find;
	}
}

class phpMorphy_Hunspell_Suffix extends phpMorphy_Hunspell_Affix {
	protected function getRegExp($find) {
		//return $find;
		return "~{$find}$~iu";
	}
	
	function generateWord($word) {
		if(!$this->isMatch($word)) {
			return false;
		}
		
		if($this->remove_len && mb_strlen($word) >= $this->remove_len) {
			$tail = mb_substr($word, -$this->remove_len);
			
			if($tail != $this->remove) {
				vd("Try to remove $tail from $word");
				vd($this);
				exit;
			}
			
			$word = mb_substr($word, 0, -$this->remove_len);
		}
		
		return "$word{$this->append}";
	}
	
	protected function simpleMatch($word) {
		return mb_substr($word, -$this->find_len) == $this->find;
	}
}

abstract class phpMorphy_Hunspell_AffixFlag {
	protected
		$name,
		$cross_product,
		$affixes = array();
	
	protected function __construct($name, $cross) {
		$this->name = $name;
		$this->cross_product = $cross;
	}
	
	static function create($type, $name, $cross) {
		$affix_class = $type == 'SFX' ? 'phpMorphy_Hunspell_SuffixFlag' : 'phpMorphy_Hunspell_PrefixFlag';
		
		return new $affix_class($name, $cross);
	}
	
	function getName() {
		return $this->name;
	}
	
	function isCrossProduct() {
		return $this->cross_product;
	}
	
	function generateWords($word, &$words, $wordMorph = null, &$morphs = null) {
		$maxRemoveLength = 0;
		
		foreach($this->affixes as $affix) {
			if(false !== ($new_word = $affix->generateWord($word))) {
				$words[] = $new_word;
				
				if(isset($morphs)) {
					$morphs[] = $wordMorph . $affix->getMorphDescription();
				}
				
				$maxRemoveLength = max($maxRemoveLength, $affix->getRemoveLength());
			}
		}
		
		return $maxRemoveLength;
	}
	
	function addAffix($find, $remove, $append, $morph = null) {
		$this->affixes[] = $this->createAffix(
			$find, $remove, $append, $morph
		);
	}
	
	abstract protected function createAffix($find, $remove, $append, $morph);
	abstract function isSuffix();
}

class phpMorphy_Hunspell_SuffixFlag extends phpMorphy_Hunspell_AffixFlag {
	protected function createAffix($find, $remove, $append, $morph) {
		return new phpMorphy_Hunspell_Suffix(
			$find,
			$remove,
			$append,
			$morph
		);
	}
	
	function isSuffix() { return true; }
}

class phpMorphy_Hunspell_PrefixFlag extends phpMorphy_Hunspell_AffixFlag {
	protected function createAffix($find, $remove, $append, $morph) {
		return new phpMorphy_Hunspell_Prefix(
			$find,
			$remove,
			$append,
			$morph
		);
	}
	
	function isSuffix() { return false; }
}

class phpMorphy_Hunspell_AffixFile_Reader extends phpMorphy_Iterator_Transform {
	function __construct($fileName, $defaultEncoding) {
		$obj = $this->createIterators($fileName);
		
		parent::__construct($this->createIterators($fileName));
		
		$this->setEncoding($defaultEncoding);
	}
	
	function setEncoding($enc) {
		$this->getInnerIterator()->setEncoding($enc);
	}
	
	protected function createIterators($fileName) {
		return new phpMorphy_Iterator_Iconv(
			new phpMorphy_Iterator_NotEmptyLines(
				$this->createFileIterator($fileName)
			)
		);
	}
	
	protected function createFileIterator($fileName) {
		return new SplFileObject($fileName);
	}
	
	protected function transformItem($item, $key) {
		return explode(
			' ',
			preg_replace('~\s{2,}~', ' ', trim($item))
		);
	}
}

class phpMorphy_Hunspell_AffixFile {
	protected
		$flags = array(),
		$options = array();
	
	function __construct($fileName, $options = array()) {
		$this->options = $options;
		$this->parseFile($fileName);
	}
	
	function isFlagExists($name) {
		return array_key_exists($name, $this->flags);
	}
	
	function getFlag($name) {
		if(!$this->isFlagExists($name)) {
			throw new phpMorphy_Hunspell_Exception("Unknown $name flag");
			
			return false;
		}
		
		return $this->flags[$name];
	}
	
	function getOptions() {
		return $this->options;
	}
	
	function isOptionExists($name) {
		return array_key_exists($name, $this->options);
	}
	
	function getOption($name) {
		if(!$this->isOptionExists($name)) {
			throw new phpMorphy_Hunspell_Exception("Unknown $name option");
		}
		
		return $this->options[$name];
	}
	
	function getEncoding() {
		try {
			return $this->getOption('SET');
		} catch(Exception $e) {
			throw new phpMorphy_Hunspell_Exception("Can`t return encoding, because SET option not exists");
		}
	}
	
	protected function parseFile($fileName) {
		$default_enc = $this->isOptionExists('SET') ? $this->getOption('SET') : null;
		
		$reader = $this->createAffixReader($fileName, $default_enc);
		$reader->rewind();
		
		try {
			while($reader->valid()) {
				$tokens = $reader->current();
				
				$this->processLine($tokens, $reader);
				
				$reader->next();
				
				// HACK: $this->options['SET'] for perfomance
				if(!isset($default_enc) && isset($this->options['SET'])) {
					$default_enc = $this->getOption('SET');
					
					$reader->setEncoding($default_enc);
				}
			}
		} catch(Exception $e) {
			throw new phpMorphy_Hunspell_Exception("Can`t parse $fileName affix file, error at " . $reader->key() . " line: " . $e->getMessage());
		}
	}
	
	protected function createAffixReader($fileName, $defaultEncoding) {
		return new phpMorphy_Hunspell_AffixFile_Reader($fileName, $defaultEncoding);
	}
	
	protected function processLine($tokens, Iterator $reader) {
		$type = $tokens[0];
		
		if($type == 'SFX' || $type == 'PFX') {
			if(count($tokens) < 4) {
				throw new phpMorphy_Hunspell_Exception("Invalid affix header");
			}
			
			$this->readAffixBlock($reader, $type, $tokens[1], $tokens[3], $tokens[2]);
		} else {
			array_shift($tokens);
			$this->handleOption($type, $tokens);
		}
	}
	
	protected function readAffixBlock(Iterator $reader, $type, $flagName, $count, $crossProduct) {
		$affix_flag = $this->createAffixFlag($type, $flagName, $crossProduct == 'Y');
		
		for($i = 0; $i < $count; $i++) {
			$reader->next();
			
			if(!$reader->valid()) {
				throw new phpMorphy_Hunspell_Exception("Unexpected file end while reading '" . $flagName . "' flag, " . ($count - $i) . " items needed");
			}
			
			$tokens = $reader->current();
			
			if(count($tokens) < 5 || $tokens[0] != $type || $tokens[1] != $flagName) {
				throw new phpMorphy_Hunspell_Exception("Invalid line type given, proper affix expected");
			}
			
			$append = $tokens[3] == '0' ? '' : $tokens[3];
			if(strpos($append, '/') !== false) {
				throw new phpMorphy_Hunspell_Exception("Affix continuation not supported");
			}
			
			$affix_flag->addAffix(
				$tokens[4],
				$tokens[2] == '0' ? '' : $tokens[2],
				$append,
				isset($tokens[5]) ? $tokens[5] : null
			);
		}
		
		$this->flags[$flagName] = $affix_flag;
	}
	
	protected function createAffixFlag($type, $flagName, $crossProduct) {
		return phpMorphy_Hunspell_AffixFlag::create(
			$type,
			$flagName,
			$crossProduct == 'Y'
		);
	}
	
	protected function handleOption($type, $options) {
		if(!$this->isAllowedOption($type, $options)) {
			throw new phpMorphy_Hunspell_Exception("Sorry, option '$type' not supported now");
		}
		
		if(count($options) == 1) {
			$options = $options[0];
		}
		
		/*
		if(!array_key_exists($type, $this->options)) {
			$this->options[$type] = $options;
		}
		*/
		$this->options[$type] = $options;
	}
	
	protected function isAllowedOption($type, $options) {
		return !in_array(
			$type,
			array(
				'FLAG', // FLAGS not supported
				'AF',
				'AM'
			)
		);
	}
}

class phpMorphy_Hunspell_DictFile_Reader extends phpMorphy_Iterator_Transform {
	function __construct($fileName, $encoding) {
		parent::__construct($this->createIterators($fileName, $encoding));
	}
	
	protected function createIterators($fileName, $encoding) {
		return new phpMorphy_Iterator_Iconv(
			new phpMorphy_Iterator_NotEmptyLines($this->createFileIterator($fileName)),
			$encoding
		);
	}
	
	protected function createFileIterator($fileName) {
		return new SplFileObject($fileName);
	}
	
	protected function transformItem($item, $key) {
		$line = trim($item);
		
		$word = '';
		$flags = '';
		$morph = '';
		
		if(false !== ($pos = mb_strpos($line, "\t"))) {
			$morph = trim(mb_substr($line, $pos + 1));
			$line = rtrim(mb_substr($line, 0, $pos));
		}
		
		if(false !== ($pos = mb_strpos($line, '/'))) {
			$word = rtrim(mb_substr($line, 0, $pos));
			$flags = ltrim(mb_substr($line, $pos + 1));
		} else {
			$word = $line;
		}
		
		return array(
			'word' => $word,
			'flags' => $this->parseFlags($flags),
			'morph' => $morph
		);
	}
	
	protected function parseFlags($flags) {
		// TODO: May be long(two chars?) or numeric format(aka compressed)
		// But i support only basic syntax now
		return strlen($flags) ? str_split($flags) : array();
	}
}

class phpMorphy_Hunspell_DictFile {
	protected
		$file_name,
		$affix,
		$encoding
		;
		
	function __construct($fileName, phpMorphy_Hunspell_AffixFile $affixFile, $encoding = null) {
		$this->file_name = $fileName;
		$this->affix = $affixFile;
		
		if($encoding === null) {
			try {
				$encoding = $affixFile->getEncoding();
			} catch(Exception $e) {
				throw new phpMorphy_Hunspell_Exception("You must explicit specifiy encoding, because affix file dosn`t contain encoding");
			}
		}
		
		$this->encoding = $encoding;
	}
	
	protected function createDictReader() {
		return new phpMorphy_Hunspell_DictFile_Reader($this->file_name, $this->encoding);
	}
	
	function export($callback) {
		$reader = $this->createDictReader();
		$reader->rewind();
		
		if($reader->valid()) {
			$tokens = $reader->current();
			
			if(preg_match('~^[0-9]+$~', $tokens['word'])) {
				$reader->next();
			}
		}
		
		while($reader->valid()) {
			$result = $reader->current();
			$reader->next();
			
			$all_words = $this->generateWordForms($result['word'], $result['morph'], $result['flags']);
			
			if(false === call_user_func($callback, $result['word'], $all_words['lemma'], $all_words['words'], $all_words['morphs'])) {
				break;
			}
		}
	}
	
	protected function generateWordForms($base, $baseMorph, $flagsList) {
		$prefix_flags = array();
		$suffix_flags = array();
		
		foreach($flagsList as $flag) {
			if($this->affix->isFlagExists($flag)) {
				$flag_obj = $this->affix->getFlag($flag);
				
				if($flag_obj->isSuffix()) {
					$suffix_flags[$flag] = $flag_obj;
				} else {
					$prefix_flags[$flag] = $flag_obj;
				}
			}
		}
		
		$words = array($base);
		$morphs = array($baseMorph);
		$lemma = '';
		
		// process prefixes
		$max_prefix_removed = $this->generateWordsForAffixes($base, $prefix_flags, $words, $baseMorph, $morphs);
		// process suffixes
		$max_suffix_removed = $this->generateWordsForAffixes($base, $suffix_flags, $words, $baseMorph, $morphs);
		
		if($max_suffix_removed) {
			$lemma = mb_substr($base, $max_prefix_removed, -$max_suffix_removed);
		} else {
			$lemma = mb_substr($base, $max_prefix_removed);
		}
		
		// process cross product
		if(count($prefix_flags) && count($suffix_flags)) {
			foreach($prefix_flags as $prefix) {
				if($prefix->isCrossProduct()) {
					$prefixed_bases = array();
					$prefixed_morphs = array();
					$prefix->generateWords($base, $prefixed_bases, $baseMorph, $prefixed_morphs);
					
					if(count($prefixed_bases)) {
						foreach($suffix_flags as $suffix) {
							if($suffix->isCrossProduct()) {
								$i = 0;
								foreach($prefixed_bases as $prefixed_base) {
									$suffix->generateWords($prefixed_base, $words, $prefixed_morphs[$i], $morphs);
									$i++;
								}
							}
						}
					}
				}
			}
		}
		
		return array(
			'words' => $words,
			'morphs' => $morphs,
			'lemma' => $lemma
		);
	}
	
	protected function generateWordsForAffixes($base, $affixes, &$words, $wordMorph, &$morphs) {
		$max_removed = 0;
		
		foreach($affixes as $affix) {
			$removed_length = $affix->generateWords($base, $words, $wordMorph, $morphs);
			
			$max_removed = max($removed_length, $max_removed);
		}
		
		return $max_removed;
	}
}
