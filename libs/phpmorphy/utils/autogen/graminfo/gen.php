<?php
require_once(dirname(__FILE__) . '/../lib/autogen.php');

class Helper extends Helper_Base {
	function parentClassName() { return 'phpMorphy_Graminfo'; }
	function className() { return $this->parentClassName() . '_' . ucfirst($this->storage->name()); }
	
	function prolog() { return $this->storage->prolog(); }
	function getInfoHeaderSize() { return 20; }
	function getStartOffset() { return '0x100'; }
}

function generate_graminfo_files($outDir) {
	$tpl = new Tpl(dirname(__FILE__) . '/tpl');
	
	$storage_ary = array('File', 'Mem', 'Shm');
	
	$tpl = new Tpl(dirname(__FILE__) . '/tpl');
	$helper_class = "Helper";
	
	foreach($storage_ary as $storage_name) {
		$storage_class = "StorageHelper_" . ucfirst($storage_name);
		$helper = new $helper_class($tpl, new $storage_class);
		
		$result = $tpl->get('graminfo', array('helper' => $helper));
		
		$file_name = "$outDir/graminfo_" . strtolower($storage_name) . '.php';
		file_put_contents($file_name, $result);
			
		unset($helper);
	}
}
