<?php
/**
 * SPL don`t like expections... ;)
 */

/**
 * This replacement for IteratorIterator, because IteratorIterator often crashes PHP ;)
 * this because any exception raised in SPL method crashes php
 */
class phpMorphy_Iterator_Decorator implements OuterIterator {
	private $it;
	
	function __construct(Iterator $it) {
		if(!isset($it)) {
			throw new phpMorphy_Exception("NULL as iterator given");
		}
		
		$this->it = $it;
	}
	
	function getInnerIterator() {
		return $this->it;
	}
	
	function rewind() {
		$this->it->rewind();
	}
	
	function next() {
		$this->it->next();
	}
	
	function valid() {
		return $this->it->valid();
	}
	
	function key() {
		return $this->it->key();
	}
	
	function current() {
		return $this->it->current();
	}
}

/**
 * Iterates over other iterator, but skips empty items
 */
abstract class phpMorphy_Iterator_NotEmpty implements OuterIterator {
	private
		$inner_it,
		$cur_item_no,
		$has_data
		;
		
	function __construct(Iterator $inner) {
		$this->inner_it = $inner;
	}
	
	function getInnerIterator() {
		return $this->inner_it;
	}
	
	function rewind() {
		$this->has_data = false;
		$this->inner_it->rewind();
		$this->cur_item_no = 0;
		
		$this->seekToNonEmptyItem();
	}
	
	function valid() {
		return $this->inner_it->valid() || $this->has_data;
	}
	
	function current() {
		return $this->inner_it->current();
	}
	
	function next() {
		$this->has_data = false;
		$this->inner_it->next();
		$this->seekToNonEmptyItem();
	}
	
	protected function seekToNonEmptyItem() {
		$it = $this->inner_it;
		
		while($it->valid()) {
			$item = $it->current();
			
			$this->cur_item_no++;
			
			if(!$this->isEmptyItem($item)) {
				$this->has_data = true;
				
				break;
			}
			
			$it->next();
		}
	}
	
	function key() {
		return $this->getPosition();
	}
	
	function getPosition() {
		return $this->cur_item_no - 1;
	}
	
	abstract protected function isEmptyItem($item);
}

/**
 * Iterates over other iterator and skips empty lines
 */
class phpMorphy_Iterator_NotEmptyLines extends phpMorphy_Iterator_NotEmpty {
	protected function isEmptyItem($item) {
		return !is_string($item) || 0 == strlen(trim($item));
	}
}

abstract class phpMorphy_Iterator_Transform extends IteratorIterator {
	function __construct(Iterator $it) {
		parent::__construct($it);
	}
	
	function current() {
		return $this->transformItem(parent::current(), parent::key());
	}
	
	abstract protected function transformItem($item, $key);
}

class phpMorphy_Iterator_TransformCallback extends phpMorphy_Iterator_Transform {
	const CALL_WITH_KEY = 1;
	const CALL_WITHOUT_KEY = 2;
	
	private
		$callback,
		$flags;
	
	function __construct(Iterator $it, $callback, $flags = self::CALL_WITH_KEY) {
		if(!is_callable($callback)) {
			throw new Exception("Invalid callback specified");
		}
		
		parent::__construct($it);
		$this->setFlags($flags);
		
		$this->callback = $callback;
	}
	
	function setFlags($flags) {
		$this->flags = $flags;
	}
	
	protected function transformItem($item, $key) {
		if($this->flags & self::CALL_WITH_KEY) {
			return call_user_func($this->callback, $item, $key);
		} else {
			return call_user_func($this->callback, $item);
		}
	}
}

/**
 * Iterates over other iterator, and convert each line via iconv()
 *
 */
class phpMorphy_Iterator_Iconv extends phpMorphy_Iterator_Transform {
	private
		$encoding,
		$int_encoding;
	
	function __construct(Iterator $it, $encoding = null, $internalEncoding = 'UTF-8') {
		parent::__construct($it);
		
		$this->setEncoding($encoding);
		$this->setInternalEncoding($internalEncoding);
	}
	
	function ignoreUnknownChars() {
		$this->insertEncModifier('IGNORE');
	}
	
	function translitUnknownChars() {
		$this->insertEncModifier('IGNORE');
	}
	
	protected function insertEncModifier($modifier) {
		$enc = $this->getEncodingWithoutModifiers();
		
		$this->setEncoding("{$enc}//$modifier");
	}
	
	protected function getEncodingWithoutModifiers() {
		$enc = $this->encoding;
		
		if(false !== ($pos = strrpos($enc, '//'))) {
			return substr($enc, 0, $pos);
		} else {
			return $enc;
		}
	}
	
	function setEncoding($encoding) {
		$this->encoding = $encoding;
	}
	
	function getEncoding() {
		return $this->getEncodingWithoutModifiers();
	}
	
	function setInternalEncoding($encoding) {
		$this->int_encoding = $encoding;
	}
	
	function getInternalEncoding() {
		return $this->int_encoding;
	}
	
	protected function transformItem($string, $key) {
		if(isset($this->encoding)) {
			$result = iconv($this->encoding, $this->int_encoding, $string);
			//$result = mb_convert_encoding($string, $this->int_encoding, $this->encoding);
			
			if(!is_string($result)) {
				throw new phpMorphy_Exception(
					"Can`t convert '$string' " . $this->getEncoding() . ' -> ' . $this->getInternalEncoding()
				);
			}
			
			return $result;
		} else {
			return $string;
		}
	}
}

// Some hack classes
function phpmorphy_get_inner_iterator_of_type(Iterator $it, $type) {
	while(null !== $it && !$it instanceof $type) {
		if($it instanceof IteratorAggregate) {
			$it = $it->getIterator();
		} elseif($it instanceof OuterIterator) {
			$it = $it->getInnerIterator();
		} else {
			$it = null;
		}
	}
	
	return null === $it ? false : $it;
}

class phpMorphy_Iterator_DeepSeekable extends IteratorIterator implements SeekableIterator {
	private $it;
	
	function __construct(Iterator $it) {
		$this->it = $this->findFirstSeekable($it);
		
		parent::__construct($it);
	}
	
	function seek($offset) {
		return $this->it->seek($offset);
	}
	
	protected function findFirstSeekable(Iterator $it) {
		if(false === ($it = phpmorphy_get_inner_iterator_of_type($it, 'SeekableIterator'))) {
			throw new phpMorphy_Exception("Seekable interface must be available through IteratorAggregate, OuterIterator interfaces");
		}
		
		return $it;
	}
}

/**
 * this "crash-free" version of SPL iterator_to_array(), generally for debugging
 */
function phpmorphy_iterator_to_array(Iterator $it) {
	if(!isset($it)) {
		throw new phpMorphy_Exception("NULL instead of iterator given");
	}
	
	$it->rewind();
	while($it->valid()) {
		$result[$it->key()] = $it->current();
		
		$it->next();
	}
	
	return $result;
}
