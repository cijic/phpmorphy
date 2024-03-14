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

interface phpMorphy_GramTab_Interface {
    function getGrammems($ancodeId);
    function getPartOfSpeech($ancodeId);
    function resolveGrammemIds($ids);
    function resolvePartOfSpeechId($id);
    function includeConsts();
    function ancodeToString($ancodeId, $commonAncode = null);
    function stringToAncode($string);
    function toString($partOfSpeechId, $grammemIds);
}

class phpMorphy_GramTab_Empty implements phpMorphy_GramTab_Interface {
    function getGrammems($ancodeId) { return array(); }
    function getPartOfSpeech($ancodeId) { return 0; }
    function resolveGrammemIds($ids) { return is_array($ids) ? array() : ''; }
    function resolvePartOfSpeechId($id) { return ''; }
    function includeConsts() { }
    function ancodeToString($ancodeId, $commonAncode = null) { return ''; }
    function stringToAncode($string) { return null; }
    function toString($partOfSpeechId, $grammemIds) { return ''; }
}

class phpMorphy_GramTab_Proxy implements phpMorphy_GramTab_Interface {
    protected $storage;
    
    function __construct(phpMorphy_Storage $storage) {
        $this->storage = $storage;
    }
    
    function getGrammems($ancodeId) {
        return $this->__obj->getGrammems($ancodeId);
    }
    
    function getPartOfSpeech($ancodeId) {
        return $this->__obj->getPartOfSpeech($ancodeId);
    }
    
    function resolveGrammemIds($ids) {
        return $this->__obj->resolveGrammemIds($ids);
    }
    
    function resolvePartOfSpeechId($id) {
        return $this->__obj->resolvePartOfSpeechId($id);
    }
    
    function includeConsts() {
        return $this->__obj->includeConsts();
    }

    function ancodeToString($ancodeId, $commonAncode = null) {
        return $this->__obj->ancodeToString($ancodeId, $commonAncode);
    }
    
    function stringToAncode($string) {
        return $this->__obj->stringToAncode($string);
    }

    function toString($partOfSpeechId, $grammemIds) {
        return $this->__obj->toString($partOfSpeechId, $grammemIds);
    }

    function __get($name) {
        if($name === '__obj') {
            $this->__obj = phpMorphy_GramTab::create($this->storage);
            unset($this->storage);
            
            return $this->__obj;
        }
        
        throw new phpMorphy_Exception("Invalid prop name '$name'");
    }
}

class phpMorphy_GramTab implements phpMorphy_GramTab_Interface {
    protected
        $data,
        $ancodes,
        $grammems,
        // $__ancodes_map,
        $poses;
    
    protected function __construct(phpMorphy_Storage $storage) {
        $this->data = unserialize($storage->read(0, $storage->getFileSize()));
        
        if(false === $this->data) {
            throw new phpMorphy_Exception("Broken gramtab data");
        }
        
        $this->grammems = $this->data['grammems'];
        $this->poses = $this->data['poses'];
        $this->ancodes = $this->data['ancodes'];
    }
    
    // TODO: remove this
    static function create(phpMorphy_Storage $storage) {
        return new phpMorphy_GramTab($storage);
    }
    
    function getGrammems($ancodeId) {
        if(!isset($this->ancodes[$ancodeId])) {
            throw new phpMorphy_Exception("Invalid ancode id '$ancodeId'");
        }
        
        return $this->ancodes[$ancodeId]['grammem_ids'];
    }

    function getPartOfSpeech($ancodeId) {
        if(!isset($this->ancodes[$ancodeId])) {
            throw new phpMorphy_Exception("Invalid ancode id '$ancodeId'");
        }
        
        return $this->ancodes[$ancodeId]['pos_id'];
    }
    
    function resolveGrammemIds($ids) {
        if(is_array($ids)) {
            $result = array();
            
            foreach($ids as $id) {
                if(!isset($this->grammems[$id])) {
                    throw new phpMorphy_Exception("Invalid grammem id '$id'");
                }
                
                $result[] = $this->grammems[$id]['name'];
            }
            
            return $result;
        } else {
            if(!isset($this->grammems[$ids])) {
                throw new phpMorphy_Exception("Invalid grammem id '$ids'");
            }
            
            return $this->grammems[$ids]['name'];
        }
    }
    
    function resolvePartOfSpeechId($id) {
        if(!isset($this->poses[$id])) {
            throw new phpMorphy_Exception("Invalid part of speech id '$id'");
        }
        
        return $this->poses[$id]['name'];
    }
    
    function includeConsts() {
        require_once(PHPMORPHY_DIR . '/gramtab_consts.php');
    }

    function ancodeToString($ancodeId, $commonAncode = null) {
        if(isset($commonAncode)) {
            $commonAncode = implode(',', $this->getGrammems($commonAncode)) . ',';
        }

        return
            $this->getPartOfSpeech($ancodeId) . ' ' .
            $commonAncode .
            implode(',', $this->getGrammems($ancodeId));
    }

    protected function findAncode($partOfSpeech, $grammems) {
    }

    function stringToAncode($string) {
        if(!isset($string)) {
            return null;
        }

        if(!isset($this->__ancodes_map[$string])) {
            throw new phpMorphy_Exception("Ancode with '$string' graminfo not found");
        }

        return $this->__ancodes_map[$string];
    }

    function toString($partOfSpeechId, $grammemIds) {
        return $partOfSpeechId . ' ' . implode(',', $grammemIds);
    }

    protected function buildAncodesMap() {
        $result = array();

        foreach($this->ancodes as $ancode_id => $data) {
            $key = $this->toString($data['pos_id'], $data['grammem_ids']);

            $result[$key] = $ancode_id;
        }

        return $result;
    }

    function __get($propName) {
        switch($propName) {
            case '__ancodes_map':
                $this->__ancodes_map = $this->buildAncodesMap();
                return $this->__ancodes_map;
        }

        throw new phpMorphy_Exception("Unknown '$propName' property");
    }
}
