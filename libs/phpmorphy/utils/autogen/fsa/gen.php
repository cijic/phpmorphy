<?php
require_once(dirname(__FILE__) . '/../lib/autogen.php');

class Helper extends Helper_Base {
    function parentClassName() { return 'phpMorphy_Fsa'; }

    function getFsaStartOffset() { return '$fsa_start'; }
    
    function checkTerm($var) { return "($var & 0x0100)"; }
    function getChar($var) { return "($var & 0xFF)"; }
    
    function prolog() {
        if(strlen($prolog = $this->storage->prolog())) {
            $prolog .= '; ';
        }
        
        $prolog .= '$fsa_start = $this->fsa_start';
        
        return $prolog;
    }

    function unpackTrans($expression) { return "unpack('V', $expression)"; }
    
    function getTransSize() { return 4; }
    
    function idx2offset($idxVar) {
        $trans_size = $this->getTransSize();
        
        if(($trans_size & ($trans_size - 1)) == 0) {
            // if trans size is power of two
            $multiple = '<< ' . (int)log($trans_size, 2);
        } else {
            $multiple = "* $trans_size";
        }
        
        return "(($idxVar) $multiple)";
    }
    
    function readTrans($transVar, $charVar) {
        $read = $this->storage->read($this->getOffsetByTrans($transVar, $charVar), $this->getTransSize());
        return $this->unpackTrans($read);
    }
    
    function seekTrans($transVar, $charVar) {
        return $this->storage->seek($this->getOffsetByTrans($transVar, $charVar));
    }
    
    function readAnnotTrans($transVar) {
        $read = $this->storage->read($this->getAnnotOffsetByTrans($transVar), $this->getTransSize());
        return $this->unpackTrans($read);
    }
    
    function seekAnnotTrans($transVar) {
        return $this->storage->seek($this->getAnnotOffsetByTrans($transVar));
    }
    
    function getOffsetByTrans($transVar, $charVar) {
        return $this->getOffsetInFsa(
            $this->idx2offset($this->_getIndexByTrans($transVar, $charVar))
        );
    }
    
    function getAnnotOffsetByTrans($transVar) {
        return $this->getOffsetInFsa(
            $this->idx2offset($this->_getAnnotIndexByTrans($transVar))
        );
    }
    
    function getOffsetInFsa($offset) {
        return sprintf('%s + %s', $this->getFsaStartOffset(), $offset);
    }
    
    function _processTpl($name, $opts = array()) {
        $opts['helper'] = $this;
        
        return $this->tpl->get($this->name() . '/' . $name, $opts);
    }
    
    function tplFindCharInState() { return $this->_processTpl('find_char_in_state'); }
    function tplUnpackTrans() { return $this->_processTpl('unpack_trans'); }
    function tplReadState() { return $this->_processTpl('read_state'); }
    function tplExtraFuncs() { return $this->_processTpl('extra_funcs'); }
    function tplExtraProps() { return $this->_processTpl('extra_props'); }
    
    // abstract
    function getRootTransOffset() { return '--ABSTRACT--'; }
    function getDest($var) { return '--ABSTRACT--'; }
    function getAnnotIdx($var) { return '--ABSTRACT--'; }
    function _getIndexByTrans($transVar, $charVar) { return '--ABSTRACT--'; }
    function _getAnnotIndexByTrans($transVar) { return '--ABSTRACT--'; }
};

class Helper_Sparse extends Helper {
    function checkEmpty($var) { return "($var & 0x0200)"; }
    
    function getRootTransOffset() { return $this->getOffsetInFsa($this->getTransSize()); }
    function getDest($var) { return "(($var) >> 10) & 0x3FFFFF"; }
    function getAnnotIdx($var) { return "(($var & 0xFF) << 22) | (($var >> 10) & 0x3FFFFF)"; }
    function _getIndexByTrans($transVar, $charVar) { return "(($transVar >> 10) & 0x3FFFFF) + $charVar + 1"; }
    function _getAnnotIndexByTrans($transVar) { return "($transVar >> 10) & 0x3FFFFF"; }
};

class Helper_Tree extends Helper {
    function checkLLast($var) { return "($var & 0x0200)"; }
    function checkRLast($var) { return "($var & 0x0400)"; }
    
    function getRootTransOffset() { return $this->getOffsetInFsa(0); }
    function getAnnotIdx($var) { return "(($var & 0xFF) << 21) | (($var >> 11) & 0x1FFFFF)"; }
    function getDest($var) { return "(($var) >> 11) & 0x1FFFFF"; }
    function _getIndexByTrans($transVar, $charVar) { return "($transVar >> 11) & 0x1FFFFF"; }
    function _getAnnotIndexByTrans($transVar) { return $this->_getIndexByTrans($transVar, '--INVALID--'); }
};

function generate_fsa_files($outDir) {
    $helpers_ary = array('Sparse', 'Tree');
    $storage_ary = array('File', 'Mem', 'Shm');
    
    $tpl = new Tpl(dirname(__FILE__) . '/tpl');
    
    foreach($helpers_ary as $helper_name) {
        $helper_class = "Helper_" . ucfirst($helper_name);
        
        foreach($storage_ary as $storage_name) {
            $storage_class = "StorageHelper_" . ucfirst($storage_name);
            $helper = new $helper_class($tpl, new $storage_class);
            
            $result = $tpl->get('fsa', array('helper' => $helper));
            
            $file_name = "$outDir/fsa_" . strtolower($helper_name) . '_' . strtolower($storage_name) . '.php';
            file_put_contents($file_name, $result);
                
            unset($helper);
        }
    }
}

