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

define('PHPMORPHY_STORAGE_FILE',    'file');
define('PHPMORPHY_STORAGE_MEM',     'mem');
define('PHPMORPHY_STORAGE_SHM',     'shm');

abstract class phpMorphy_Storage {
    protected
        $file_name,
        $resource;

    public function __construct($fileName) {
        $this->file_name = $fileName;
        $this->resource = $this->open($fileName);
    }

    public function getFileName() { return $this->file_name; }
    public function getResource() { return $this->resource; }
    public function getTypeAsString() { return $this->getType(); }
    public function read($offset, $len, $exactLength = true) {
        if($offset >= $this->getFileSize()) {
            throw new phpMorphy_Exception("Can`t read $len bytes beyond end of '" . $this->getFileName() . "' file, offset = $offset, file_size = " . $this->getFileSize());
        }

        try {
            $result = $this->readUnsafe($offset, $len);
        } catch (Exception $e) {
            throw new phpMorphy_Exception("Can`t read $len bytes at $offset offset, from '" . $this->getFileName() . "' file: " . $e->getMessage());
        }

        if($exactLength && $GLOBALS['__phpmorphy_strlen']($result) < $len) {
            throw new phpMorphy_Exception("Can`t read $len bytes at $offset offset, from '" . $this->getFileName() . "' file");
        }

        return $result;
    }

    abstract function readUnsafe($offset, $len);
    abstract function getFileSize();
    abstract function getType();
    abstract protected function open($fileName);
};

class phpMorphy_Storage_Proxy extends phpMorphy_Storage {
    protected
        $file_name,
        $type,
        $factory,
        $__obj;

    public function __construct($type, $fileName, $factory) {
        $this->file_name = $fileName;
        $this->type = $type;
        $this->factory = $factory;
    }

    public function getFileName() { return $this->getObj()->getFileName(); }
    public function getResource() { return $this->getObj()->getResource(); }
    public function getFileSize() { return $this->getObj()->getFileSize(); }
    public function getType() { return $this->getObj()->getType(); }
    public function readUnsafe($offset, $len) { return $this->getObj()->readUnsafe($offset, $len); }
    protected function open($fileName) { return $this->getObj()->open($fileName); }

    public function getObj() {
        if ($this->__obj !== null) {
            return $this->__obj;
        }
        $this->__obj = $this->factory->open($this->type, $this->file_name, false);

        unset($this->file_name);
        unset($this->type);
        unset($this->factory);

        return $this->__obj;
    }
}

class phpMorphy_Storage_File extends phpMorphy_Storage {
    public function getType() { return PHPMORPHY_STORAGE_FILE; }

    public function getFileSize() {
        if(false === ($stat = fstat($this->resource))) {
            throw new phpMorphy_Exception('Can`t invoke fstat for ' . $this->file_name . ' file');
        }

        return $stat['size'];
    }

    public function readUnsafe($offset, $len) {
        if(0 !== fseek($this->resource, $offset)) {
            throw new phpMorphy_Exception("Can`t seek to $offset offset");
        }

        return fread($this->resource, $len);
    }

    public function open($fileName) {
        if(false === ($fh = fopen($fileName, 'rb'))) {
            throw new phpMorphy_Exception("Can`t open $this->file_name file");
        }

        return $fh;
    }
}

class phpMorphy_Storage_Mem extends phpMorphy_Storage {
    public function getType() { return PHPMORPHY_STORAGE_MEM; }

    public function getFileSize() {
        return $GLOBALS['__phpmorphy_strlen']($this->resource);
    }

    public function readUnsafe($offset, $len) {
        return $GLOBALS['__phpmorphy_substr']($this->resource, $offset, $len);
    }

    public function open($fileName) {
        if(false === ($string = file_get_contents($fileName))) {
            throw new phpMorphy_Exception("Can`t read $fileName file");
        }

        return $string;
    }
}

class phpMorphy_Storage_Shm extends phpMorphy_Storage {
    protected
        $descriptor;

    public function __construct($fileName, $shmCache) {
        $this->cache = $shmCache;

        parent::__construct($fileName);
    }

    public function getFileSize() {
        return $this->descriptor->getFileSize();
    }

    public function getType() { return PHPMORPHY_STORAGE_SHM; }

    public function readUnsafe($offset, $len) {
        return shmop_read($this->resource['shm_id'], $this->resource['offset'] + $offset, $len);
    }

    public function open($fileName) {
        $this->descriptor = $this->cache->get($fileName);

        return array(
            'shm_id' => $this->descriptor->getShmId(),
            'offset' => $this->descriptor->getOffset()
        );
    }
}

class phpMorphy_Storage_Factory {
    protected
        $shm_cache,
        $shm_options;

    public function __construct($shmOptions = array()) {
        $this->shm_options = $shmOptions;
    }

    public function getShmCache() {
        if(!isset($this->shm_cache)) {
            $this->shm_cache = $this->createShmCache($this->shm_options);
        }

        return $this->shm_cache;
    }

    public function open($type, $fileName, $lazy) {
        switch($type) {
            case PHPMORPHY_STORAGE_FILE:
            case PHPMORPHY_STORAGE_MEM:
            case PHPMORPHY_STORAGE_SHM: break;
            default:
                throw new phpMorphy_Exception("Invalid storage type $type specified");
        }

        if($lazy) {
            return new phpMorphy_Storage_Proxy($type, $fileName, $this);
        }

        $clazz = 'phpMorphy_Storage_' . ucfirst($GLOBALS['__phpmorphy_strtolower']($type));

        if($type != PHPMORPHY_STORAGE_SHM) {
            return new $clazz($fileName);
        } else {
            return new $clazz($fileName, $this->getShmCache());
        }
    }

    protected function createShmCache($options) {
        require_once(PHPMORPHY_DIR . '/shm_utils.php');

        return new phpMorphy_Shm_Cache($options, !empty($options['clear_on_create']));
    }
}
