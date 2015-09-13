<?php
class ConstNames_Grammems_Unk {
	public
		$uUnknown = 0;
}

class ConstNames_Poses_Unk {
	public
		$uUnknown = 0;
}

class ConstNames_Unk extends ConstNames_Base {
	protected $poses = array(
		"UNKNOWN"
	);
	
	protected $grammems = array("");
	
	function getPartsOfSpeech() {
		return $this->combineObjAndArray(new ConstNames_Poses_Unk(), $this->poses);
	}
	
	function getGrammems() {
		return $this->combineObjAndArray(new ConstNames_Grammems_Unk(), $this->grammems);
	}
}
