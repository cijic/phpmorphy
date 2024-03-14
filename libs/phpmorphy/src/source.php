<?php
/**
 * This file is part of phpMorphy library
 *
 * Copyright c 2007-2008 Kamaev Vladimir <heromantor@users.sourceforge.net>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the
 * Free Software Foundation, Inc., 59 Temple Place - Suite 330,
 * Boston, MA 02111-1307, USA.
 */

define('PHPMORPHY_SOURCE_FSA', 'fsa');
define('PHPMORPHY_SOURCE_DBA', 'dba');
define('PHPMORPHY_SOURCE_SQL', 'sql');

interface phpMorphy_Source_Interface {
    function getValue($key);
}

class phpMorphy_Source_Fsa implements phpMorphy_Source_Interface {
    protected
        $fsa,
        $root;
    
    function __construct(phpMorphy_Fsa_Interface $fsa) {
        $this->fsa = $fsa;
        $this->root = $fsa->getRootTrans();
    }
    
    function getFsa() {
    	return $this->fsa;
    }
    
    function getValue($key) {
        if(false === ($result = $this->fsa->walk($this->root, $key, true)) || !$result['annot']) {
            return false;
        }
        
        return $result['annot'];
    }
}

class phpMorphy_Source_Dba implements phpMorphy_Source_Interface {
    const DEFAULT_HANDLER = 'db3';
    
    protected $handle;
    
    function __construct($fileName, $options = null) {
        $this->handle = $this->openFile($fileName, $this->repairOptions($options));
    }
    
    function close() {
        if(isset($this->handle)) {
            dba_close($this->handle);
            $this->handle = null;
        }
    }
    
    static function getDefaultHandler() {
        return self::DEFAULT_HANDLER;
    }
    
    protected function openFile($fileName, $options) {
        if(false === ($new_filename = realpath($fileName))) {
            throw new phpMorphy_Exception("Can`t get realpath for '$fileName' file");
        }
        
        $lock_mode = $options['lock_mode'];
        $handler = $options['handler'];
        $func = $options['persistent'] ? 'dba_popen' : 'dba_open';
        
        if(false === ($result = $func($new_filename, "r$lock_mode", $handler))) {
            throw new phpMorphy_Exception("Can`t open '$fileFile' file");
        }
        
        return $result;
    }
    
    protected function repairOptions($options) {
        $defaults = array(
            'lock_mode' => 'd',
            'handler' => self::getDefaultHandler(),
            'persistent' => false
        );
        
        return (array)$options + $defaults;
    }
    
    function getValue($key) {
        return dba_fetch($key, $this->handle);
    }
}
