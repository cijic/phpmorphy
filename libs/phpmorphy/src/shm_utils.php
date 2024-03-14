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

if(!defined('PHPMORPHY_SHM_SEGMENT_SIZE')) {
    define('PHPMORPHY_SHM_SEGMENT_SIZE', 1024 * 1024 * 24);
}

if(!defined('PHPMORPHY_SHM_SEGMENT_ID')) {
    define('PHPMORPHY_SHM_SEGMENT_ID', 0x54358308);
}

if(!defined('PHPMORPHY_SEMAPHORE_KEY')) {
    define('PHPMORPHY_SEMAPHORE_KEY', PHPMORPHY_SHM_SEGMENT_ID + 1);
}

if(!defined('PHPMORPHY_SHM_HEADER_MAX_SIZE')) {
    define('PHPMORPHY_SHM_HEADER_MAX_SIZE', 1024 * 32);
}

interface phpMorphy_Shm_Cache_Interface {
    function close();
    function get($filePath);
    function clear();
    function delete($filePath);
    function reload($filePath);
    function reloadIfExists($filePath);
    function free();
}

class phpMorphy_Shm_Cache_FileDescriptor {
    private
        $shm_id,
        $file_size,
        $offset;
    
    function __construct($shmId, $fileSize, $offset) {
        $this->shm_id = $shmId;
        $this->file_size = $fileSize;
        $this->offset = $offset;
    }
    
    function getShmId() { return $this->shm_id; }
    function getFileSize() { return $this->file_size; }
    function getOffset() { return $this->offset; }
}

abstract class phpMorphy_Semaphore {
    abstract function lock();
    abstract function unlock();
    
    static function create($key, $empty = false) {
        if(!$empty) {
            if (0 == strcasecmp($GLOBALS['__phpmorphy_substr'](PHP_OS, 0, 3), 'WIN')) {
                $clazz = 'phpMorphy_Semaphore_Win';
            } else {
                $clazz = 'phpMorphy_Semaphore_Nix';
            }
        } else {
            $clazz = 'phpMorphy_Semaphore_Empty';
        }
        
        return new $clazz($key);
    }
};

class phpMorphy_Semaphore_Empty extends phpMorphy_Semaphore {
    function lock() { }
    function unlock() { }
    function remove() { }
};

// TODO: implement this
class phpMorphy_Semaphore_Win extends phpMorphy_Semaphore {
    const DIR_NAME = 'phpmorphy_semaphore';
    const USLEEP_TIME = 100000; // 0.1s
    const MAX_SLEEP_TIME = 5000000; // 5sec
    
    protected $dir_path;
    
    protected function __construct($key) {
        $this->dir_path = $this->getTempDir() . DIRECTORY_SEPARATOR . self::DIR_NAME . "_$key";
        
        register_shutdown_function(array($this, 'unlock'));
    }
    
    protected function getTempDir() {
        if(false === ($result = getenv('TEMP'))) {
            if(false === ($result = getenv('TMP'))) {
                throw new phpMorphy_Exception("Can`t get temporary directory");
            }
        }
        
        return $result;
    }
    
    function lock() {
        for($i = 0; $i < self::MAX_SLEEP_TIME; $i += self::USLEEP_TIME) {
            if(!file_exists($this->dir_path)) {
                if(false !== @mkdir($this->dir_path, 0644)) {
                    return true;
                }
            }
            
            usleep(self::USLEEP_TIME);
        }
        
        throw new phpMorphy_Exception("Can`t acquire semaphore");
    }
    
    function unlock() {
        @rmdir($this->dir_path);
    }
    
    function remove() {
    }
}

class phpMorphy_Semaphore_Nix extends phpMorphy_Semaphore {
    const DEFAULT_PERM = 0644;
    
    private $sem_id = false;
    
    protected function __construct($key) {
        if(false === ($this->sem_id = sem_get($key, 1, self::DEFAULT_PERM, true))) {
            throw new phpMorphy_Exception("Can`t get semaphore for '$key' key");
        }
    }
    
    function lock() {
        if(false === sem_acquire($this->sem_id)) {
            throw new phpMorphy_Exception("Can`t acquire semaphore");
        }
    }
    
    function unlock() {
        if(false === sem_release($this->sem_id)) {
            throw new phpMorphy_Exception("Can`t release semaphore");
        }
    }
    
    function remove() {
        sem_remove($this->sem_id);
    }
}

class phpMorphy_Shm_Header {
    protected
        $max_size,
        $segment_id,
        $files_map = array(),
        $free_map = array();
    
    function __construct($segmentId, $maxSize) {
        $this->max_size = (int)$maxSize;
        $this->segment_id = $segmentId;
        
        $this->clear();
    }
        
    function lookup($filePath) {
        if(!$this->exists($filePath)) {
            throw new phpMorphy_Exception("'$filePath' not found in shm");
        }
        
        return $this->files_map[$this->normalizePath($filePath)];
    }
    
    function exists($filePath) {
        return isset($this->files_map[$this->normalizePath($filePath)]);
    }
    
    function register($filePath, $fh) {
        if($this->exists($filePath)) {
            throw new phpMorphy_Exception("Can`t register, '$filePath' already exists");
        }
        
        if(false === ($stat = fstat($fh))) {
            throw new phpMorphy_Exception("Can`t fstat '$filePath' file");
        }
        
        $file_size = $stat['size'];
        
        $offset = $this->getBlock($file_size);
        
        $entry = array(
            'offset' => $offset,
            'mtime' => $stat['mtime'],
            'size' => $file_size,
            'shm_id' => $this->segment_id
        );
        
        $this->files_map[$this->normalizePath($filePath)] = $entry;
        
        return $entry;
    }
    
    function delete($filePath) {
        $data = $this->lookup($filePath);
        
        unset($this->files_map[$this->normalizePath($filePath)]);
        
        $this->freeBlock($data['offset'], $data['size']);
    }
    
    function clear() {
        $this->files_map = array();
        $this->free_map = array(0 => $this->max_size);
    }
    
    function getAllFiles() {
        return $this->files_map;
    }
    
    protected function registerBlock($offset, $size) {
        $old_size = $this->free_map[$offset];
        
        if($old_size < $size) {
            throw new phpMorphy_Exception("Too small free block for register(free = $old_size, need = $size)");
        }
        
        unset($this->free_map[$offset]);
        
        if($old_size > $size) {
            $this->free_map[$offset + $size] = $old_size - $size;
        }
    }
    
    protected function freeBlock($offset, $size) {
        $this->free_map[$offset] = $size;
        $this->defrag();
    }
    
    protected function defrag() {
        ksort($this->free_map);
        
        $map_count = count($this->free_map);
        
        if($map_count < 2) {
            return;
        }
        
        $keys = array_keys($this->free_map);
        $i = 0;
        $prev_offset = $keys[$i];
        
        for($i++; $i < $map_count; $i++) {
            $offset = $keys[$i];
            
            if($prev_offset + $this->free_map[$prev_offset] == $offset) {
                // merge
                $this->free_map[$prev_offset] += $this->free_map[$offset];
                
                unset($this->free_map[$offset]);
            } else {
                $prev_offset = $offset;
            }
        }
    }
    
    protected function getBlock($fileSize) {
        foreach($this->free_map as $offset => $size) {
            if($size >= $fileSize) {
                $this->registerBlock($offset, $fileSize);
                
                return $offset;
            }
        }
        
        throw new phpMorphy_Exception("Can`t find free space for $size block");
    }
    
    protected function normalizePath($path) {
        return $path;
    }
}

class phpMorphy_Shm_Cache implements phpMorphy_Shm_Cache_Interface {
    const DEFAULT_MODE = 0644;
    const READ_BLOCK_SIZE = 8192;

    protected static $EXTENSION_PRESENT = null;
    
    protected
        $options,
        $semaphore,
        $segment
        ;
    
    function __construct($options = array(), $clear = false) {
        if(!isset(self::$EXTENSION_PRESENT)) {
            self::$EXTENSION_PRESENT = extension_loaded('shmop');
        }
        
        if(!self::$EXTENSION_PRESENT) {
            throw new phpMorphy_Exception("shmop extension needed");
        }
        
        $this->options = $options = $this->repairOptions($options);
        
        $this->semaphore = phpMorphy_Semaphore::create($options['semaphore_key'], $options['no_lock']);
        
        $this->segment = $this->getSegment($options['segment_id'], $options['segment_size']);
        
        if($clear) {
            $this->semaphore->remove();
            $this->initHeaderObject($this->segment);
        }
    }
    
    static function clearSemaphore($semaphoreId = null) {
        $semaphoreId = isset($semaphoreId) ? $semaphoreId : PHPMORPHY_SEMAPHORE_KEY;
        
        $sem = phpMorphy_Semaphore::create($semaphoreId);
        return $sem->remove();
    }
    
    protected function repairOptions($options) {
        $defaults = array(
            'semaphore_key' => PHPMORPHY_SEMAPHORE_KEY,
            'segment_id' => PHPMORPHY_SHM_SEGMENT_ID,
            'segment_size' => PHPMORPHY_SHM_SEGMENT_SIZE,
            'with_mtime' => false,
            'header_max_size' => PHPMORPHY_SHM_HEADER_MAX_SIZE,
            'no_lock' => false,
        );
        
        return (array)$options + $defaults;
    }
    
    function close() {
        if(isset($this->segment)) {
            shmop_close($this->segment);
            $this->segment = null;
        }
    }
    
    protected function safeInvoke($filePath, $method) {
        $this->lock();
        
        try {
            $header = $this->readHeader();
            
            $result = $this->$method($filePath, $header);
            
            // writeHeader is atomic
            $this->writeHeader($this->segment, $header);
            
            $this->unlock();
            
            return $result;
        } catch (Exception $e) {
            $this->unlock();
            
            throw $e;
        }
    }
    
    protected function doGet($filePath, $header) {
        $result = array();
        foreach((array)$filePath as $file) {
            $result[$file] = $this->getSingleFile($header, $file);
        }
        
        if(!is_array($filePath)) {
            $result = $result[$filePath];
        }
        
        return $result;
    }
    
    function get($filePath) {
        if(!is_array($filePath)) {
            return $this->createFileDescriptor($this->safeInvoke($filePath, 'doGet'));
        } else {
            $result = array();
            
            foreach($this->safeInvoke($filePath, 'doGet') as $file => $item) {
                $result[$file] = $this->createFileDescriptor($item);
            }
            
            return $result;
        }
    }

    
    protected function getSingleFile($header, $filePath) {
        try {
            $fh = false;
            
            if(false !== $header->exists($filePath)) {
                $result = $header->lookup($filePath);
                
                if(!$this->options['with_mtime']) {
                    return $result;
                }
                
                if(false === ($mtime = filemtime($filePath))) {
                    throw new phpMorphy_Exception("Can`t get mtime attribute for '$filePath' file");
                }
                
                if($result['mtime'] === $mtime) {
                    return $result;
                }
                
                $fh = $this->openFile($filePath);
                
                // update
                $header->delete($filePath);
                $result = $header->register($filePath, $fh);
                
                $this->saveFile($fh, $result['offset']);
                
                fclose($fh);
                
                return $result;
            }
            
            // register
            $fh = $this->openFile($filePath);
            
            $result = $header->register($filePath, $fh);
            
            $this->saveFile($fh, $result['offset']);
            
            fclose($fh);
            
            return $result;
        } catch (Exception $e) {
            if(isset($fh) && $fh !== false) {
                fclose($fh);
            }
            
            throw $e;
        }
    }
    
    protected function doClear($filePath, $header) {
        $header->clear();
    }
    
    function clear() {
        $this->safeInvoke(null, 'doClear');
    }
    
    protected function doDelete($filePath, $header) {
        foreach((array)$filePath as $file) {
            $hdr->delete($file);
        }
    }
    
    function delete($filePath) {
        $this->safeInvoke($filePath, 'doDelete');
    }
    
    protected function doReload($filePath, $header) {
        $return = array();
                
        foreach((array)$filePath as $file) {
            $fh = $this->openFile($file);
            
            // update
            $hdr->delete($file);
            $result = $hdr->register($file, $fh);
            
            $this->saveFile($fh, $result['offset']);
            
            fclose($fh);
            $fh = false;
            
            $return[$file] = $result;
        }
        
        if(!is_array($filePath)) {
            $return = $return[$filePath];
        }
        
        return $return;
    }
    
    function reload($filePath) {
        if(!is_array($filePath)) {
            return $this->createFileDescriptor($this->safeInvoke($filePath, 'doReload'));
        } else {
            $result = array();
            
            foreach($this->safeInvoke($filePath, 'doReload') as $file => $item) {
                $result[$file] = $this->createFileDescriptor($item);
            }
            
            return $result;
        }
    }
    
    function reloadIfExists($filePath) {
        try {
            return $this->reload($filePath);
        } catch (Exception $e) {
            return false;
        }
    }
    
    function free() {
        $this->lock();
        if(false === shmop_delete($this->segment)) {
            throw new phpMorphy_Exception("Can`t delete $this->segment segment");
        }
        
        $this->close();
        
        $this->unlock();
    }
    
    function getFilesList() {
        $this->lock();
        
        $result = $this->readHeader()->getAllFiles();
        
        $this->unlock();
        
        return $result;
    }
    
    protected function createFileDescriptor($result) {
        return new phpMorphy_Shm_Cache_FileDescriptor($this->segment, $result['size'], $this->options['header_max_size'] + $result['offset']);
    }
    
    protected function openFile($filePath) {
        if(false === ($fh = fopen($filePath, 'rb'))) {
            throw new phpMorphy_Exception("Can`t open '$filePath' file");
        }
        
        return $fh;
    }
    
    protected function lock() {
        $this->semaphore->lock();
    }
    
    protected function unlock() {
        $this->semaphore->unlock();
    }
    
    protected function getFilesOffset() {
        return $this->options['header_max_size'];
    }
    
    protected function getMaxOffset() {
        return $this->options['segment_size'] - 1;
    }
    
    protected function saveFile($fh, $offset) {
        if(false === ($stat = fstat($fh))) {
            throw new phpMorphy_Exception("Can`t fstat '$filePath'");
        }
        
        $file_size = $stat['size'];
        $chunk_size = self::READ_BLOCK_SIZE;
        
        $max_offset = $offset + $file_size;
        
        if($max_offset >= $this->getMaxOffset()) {
            throw new phpMorphy_Exception("Can`t write '$filePath' file to $offset offset, not enough space");
        }
        
        $i = 0;
        while(!feof($fh)) {
            $data = fread($fh, $chunk_size);
            if(false === (shmop_write($this->segment, $data, $this->getFilesOffset() + $offset + $i))) {
                throw new phpMorphy_Exception("Can`t write chunk of file '$filePath' to shm");
            }
            
            $i += $chunk_size;
        }
    }
    
    protected function getSegment($segmentId, $segmentSize) {
        $this->lock();
        
        try {
            $shm_id = $this->openSegment($segmentId, $segmentSize, $is_new);
            
            if($is_new) {
                $this->initHeaderObject($shm_id, false);
            }
        } catch (Exception $e) {
            $this->unlock();
            throw $e;
        }
        
        $this->unlock();
        
        return $shm_id;
    }
    
    protected function initHeaderObject($shmId, $lock = true) {
        if($lock) {
            $this->lock();
            $this->writeHeader($shmId, $this->createHeader($shmId));
            $this->unlock();
        } else {
            $this->writeHeader($shmId, $this->createHeader($shmId));
        }
    }
    
    protected function readHeader() {
        if(false === ($data = shmop_read($this->segment, 0, $this->getFilesOffset()))) {
            throw new phpMorphy_Exception("Can`t read header for " . $this->segment);
        }
        
        if(false === ($result = unserialize($data))) {
            throw new phpMorphy_Exception("Can`t unserialize header for " . $this->segment);
        }
        
        return $result;
    }
    
    protected function writeHeader($shmId, phpMorphy_Shm_Header $header) {
        $data = serialize($header);
        
        if($GLOBALS['__phpmorphy_strlen']($data) > $this->getFilesOffset()) {
            throw new phpMorphy_Exception("Too long header, try increase PHPMORPHY_SHM_HEADER_MAX_SIZE");
        }
        
        if(false === shmop_write($shmId, $data, 0)) {
            throw new phpMorphy_Exception("Can`t write shm header");
        }
    }
    
    protected function createHeader($shmId) {
        return new phpMorphy_Shm_Header($shmId, $this->options['segment_size']);
    }
    
    protected function openSegment($segmentId, $size, &$new = null) {
        $new = false;
        
        if(false === ($handle = @shmop_open($segmentId, 'w', 0, 0))) {
            if(false === ($handle = shmop_open($segmentId, 'n', self::DEFAULT_MODE, $size))) {
                throw new phpMorphy_Exception("Can`t create SHM segment with '$segmentId' id and $size size");
            }
            
            $new = true;
        }
        
        return $handle;
    }
}

