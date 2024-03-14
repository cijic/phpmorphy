<?php
require_once(dirname(__FILE__) . '/iterators.php');

interface phpMorphy_Collection_Interface extends IteratorAggregate, ArrayAccess, Countable {
	function import(Traversable $values);
	function append($value);
	function clear();
}

class phpMorphy_Collection implements phpMorphy_Collection_Interface {
	protected
		$data;
		
	function __construct() {
		$this->clear();
	}
	
	function getIterator() {
		return new ArrayIterator($this->data);
	}
	
	function import(Traversable $values) {
		if(!$values instanceof Iterator) {
			throw new phpMorphy_Exception("Iterator only");
		}
		
		foreach($values as $v) {
			$this->append($v);
		}
	}
	
	function append($value) {
		$this->data[] = $value;
	}
	
	function clear() {
		$this->data = array();
	}
	
	function offsetExists($offset) {
		return array_key_exists($offset, $this->data);
	}
	
	function offsetGet($offset) {
		if(!$this->offsetExists($offset)) {
			throw new phpMorphy_Exception("Invalid offset($offset) given");
		}
		
		return $this->data[$offset];
	}
	
	function offsetSet($offset, $value) {
		$this->data[$offset] = $value;
	}
	
	function offsetUnset($offset) {
		unset($this->data[$offset]);
	}
	
	function count() {
		return count($this->data);
	}
}

class phpMorphy_Collection_Decorator implements phpMorphy_Collection_Interface {
	protected $inner;
		
	function __construct(phpMorphy_Collection_Interface $inner) {
		$this->inner = $inner;
	}
	
	function getIterator() {
		return $this->inner->getIterator();
	}
	
	function import(Traversable $values) {
		$this->inner->import($values);
	}
	
	function append($value) {
		$this->inner->append($value);
	}
	
	function clear() {
		$this->inner->clear();
	}
	
	function offsetExists($offset) {
		return $this->inner->offsetExists($offset);
	}
	
	function offsetGet($offset) {
		return $this->inner->offsetGet($offset);
	}
	
	function offsetSet($offset, $value) {
		$this->inner->offsetSet($offset, $value);
	}
	
	function offsetUnset($offset) {
		$this->inner->offsetUnset($offset);
	}
	
	function count() {
		return $this->inner->count();
	}
}

class phpMorphy_Collection_Immutable extends phpMorphy_Collection_Decorator {
	function append($value) {
		throw new phpMorphy_Exception("Collection is immutable");
	}
	
	function clear() {
		throw new phpMorphy_Exception("Collection is immutable");
	}
	
	function offsetSet($offset, $value) {
		throw new phpMorphy_Exception("Collection is immutable");
	}
	
	function offsetUnset($offset) {
		throw new phpMorphy_Exception("Collection is immutable");
	}
}

abstract class phpMorphy_Collection_Transform extends phpMorphy_Collection_Decorator {
	function offsetGet($offset) {
		return $this->transformItem(parent::offsetGet($offset), $offset);
	}
	
	function getIterator() {
		return new phpMorphy_Iterator_TransformCallback(
			parent::getIterator(),
			array($this, 'transformItem')
		);
	}
	
	abstract function transformItem($item, $key);
}

class phpMorphy_Collection_Cache extends phpMorphy_Collection_Decorator {
	const UNSET_BEHAVIOUR = 1;
	const NORMAL_BEHAVIOUR = 2;
	
	protected
		$flags = 0,
		$items = array();
		
	function __construct(phpMorphy_Collection_Interface $inner, $flags = null) {
		parent::__construct($inner);
		
		if(isset($flags)) {
			$this->setFlags($flags);
		} else {
			$this->setFlags(self::NORMAL_BEHAVIOUR);
		}
	}
	
	function count() {
		if($this->flags & self::UNSET_BEHAVIOUR) {
			return parent::count() + count($this->items);
		} else {
			return parent::count();
		}
	}
	
	function setFlags($flags) {
		$this->flags = $flags;
	}
		
	function offsetGet($offset) {
		if(!isset($this->items[$offset])) {
			$this->items[$offset] = parent::offsetGet($offset);
			
			if($this->flags & self::UNSET_BEHAVIOUR) {
				parent::offsetUnset($offset);
			}
		}
		
		return $this->items[$offset];
	}
}

class phpMorphy_Collection_Typed extends phpMorphy_Collection_Decorator {
	private $valid_types;
	
	function __construct(phpMorphy_Collection_Interface $inner, $types) {
		parent::__construct($inner);
		
		$this->valid_types = (array)$types;
	}
	
	function append($value) {
		$this->assertType($value);
		parent::append($value);
	}
	
	function offsetSet($offset, $value) {
		$this->assertType($value);
		parent::offsetSet($offset, $value);
	}
	
	protected function assertType($value) {
		$types = $this->getType($value);
		
		if(count(array_intersect($types, $this->valid_types))) {
			return;
		}
		
		throw new phpMorphy_Exception(
			"Invalid argument type(" . implode(', ', $types) . "), [" . $this->getTypesAsString() . "] expected"
		);
	}
	
	protected function getType($value) {
		$type = gettype($value);
		
		if($type === 'object') {
			$class = get_class($value);
			return array('object', strtolower($class), $class);
		} else {
			return array($type);
		}
	}
	
	protected function getTypesAsString() {
		return implode(', ', $this->valid_types);
	}
}
