<?php
require_once(dirname(__FILE__) . '/../../model.php');

class phpMorphy_Dict_Writer_Utils_AncodesSplitter {
	private
		$ancodes_map = array(),
		$poses_map = array(),
		$is_predict_map = array(),
		$grammems_map = array();
		
	function insert(phpMorphy_Dict_Ancode $ancode) {
		$pos_id = $this->insertPos($ancode->getPartOfSpeech(), $ancode->isPredict());
		$grammems_ids = $this->insertGrammems($ancode->getGrammems());
		
		$this->ancodes_map[$ancode->getId()] = array(
			'pos_id' => $pos_id,
			'grammems_ids' => $grammems_ids
		);
	}
	
	function getPoses() {
		$result = array();
		
		foreach($this->poses_map as $pos => $id) {
			$result[$id] = array(
				'pos' => $pos,
				'is_predict' => $this->is_predict_map[$id]
			);
		}
		
		return $result;
	}
	
	function getGrammems() {
		return array_keys($this->grammems_map);
	}
	
	function getAncodes() {
		return $this->ancodes_map;
	}
	
	protected function insertGrammems($grammems) {
		$result = array();
		
		foreach($grammems as $grammem) {
			if(!isset($this->grammems_map[$grammem])) {
				$id = count($this->grammems_map);
				$this->grammems_map[$grammem] = $id;
			}
			
			$result[] = $this->grammems_map[$grammem];
		}
		
		return $result;
	}
	
	protected function insertPos($pos, $isPredict) {
		if(!isset($this->poses_map[$pos])) {
			$id = count($this->poses_map);
			$this->poses_map[$pos] = $id;
			$this->is_predict_map[$id] = $isPredict;
		}
		
		return $this->poses_map[$pos];
	}
}
