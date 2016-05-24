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

interface phpMorphy_GramInfo_Interace {
    /**
     * Returns langugage for graminfo file
     * @return string
     */
    function getLocale();

    /**
     * Return encoding for graminfo file
     * @return string
     */
    function getEncoding();

    /**
     * Return size of character (cp1251 - 1, utf8 - 1, utf16 - 2, utf32 - 4 etc)
     * @return int
     */
    function getCharSize();

    /**
     * Return end of string value (usually string with \0 value of char_size + 1 length)
     * @return string
     */
    function getEnds();

    /**
     * Reads graminfo header
     *
     * @param int $offset
     * @return array
     */
    function readGramInfoHeader($offset);

    /**
     * Returns size of header struct
     */
    function getGramInfoHeaderSize();

    /**
     * Read ancodes section for header retrieved with readGramInfoHeader
     *
     * @param array $info
     * @return array
     */
    function readAncodes($info);

    /**
     * Read flexias section for header retrieved with readGramInfoHeader
     *
     * @param array $info
     * @return array
     */
    function readFlexiaData($info);

    /**
     * Read all graminfo headers offsets, which can be used latter for readGramInfoHeader method
     * @return array
     */
    function readAllGramInfoOffsets();

    function getHeader();
    function readAllPartOfSpeech();
    function readAllGrammems();
    function readAllAncodes();
}

abstract class phpMorphy_GramInfo implements phpMorphy_GramInfo_Interace {
    const HEADER_SIZE = 128;

    protected
        $resource,
        $header,
        $ends,
        $ends_size;

    protected function __construct($resource, $header) {
        $this->resource = $resource;
        $this->header = $header;

        $this->ends = str_repeat("\0", $header['char_size'] + 1);
        $this->ends_size = $GLOBALS['__phpmorphy_strlen']($this->ends);
    }

    static function create(phpMorphy_Storage $storage, $lazy) {
        if($lazy) {
            return new phpMorphy_GramInfo_Proxy($storage);
        }

        $header = phpMorphy_GramInfo::readHeader(
            $storage->read(0, self::HEADER_SIZE)
        );

        if(!phpMorphy_GramInfo::validateHeader($header)) {
            throw new phpMorphy_Exception('Invalid graminfo format');
        }

        $storage_type = $storage->getTypeAsString();
        $file_path = dirname(__FILE__) . "/access/graminfo_{$storage_type}.php";
        $clazz = 'phpMorphy_GramInfo_' . ucfirst($storage_type);

        require_once($file_path);
        return new $clazz($storage->getResource(), $header);
    }

    function getLocale() {
        return $this->header['lang'];
    }

    function getEncoding() {
        return $this->header['encoding'];
    }

    function getCharSize() {
        return $this->header['char_size'];
    }

    function getEnds() {
        return $this->ends;
    }

    function getHeader() {
        return $this->header;
    }

    static protected function readHeader($headerRaw) {
        $header = unpack(
            'Vver/Vis_be/Vflex_count_old/' .
            'Vflex_offset/Vflex_size/Vflex_count/Vflex_index_offset/Vflex_index_size/' .
            'Vposes_offset/Vposes_size/Vposes_count/Vposes_index_offset/Vposes_index_size/' .
            'Vgrammems_offset/Vgrammems_size/Vgrammems_count/Vgrammems_index_offset/Vgrammems_index_size/' .
            'Vancodes_offset/Vancodes_size/Vancodes_count/Vancodes_index_offset/Vancodes_index_size/' .
            'Vchar_size/',
            $headerRaw
        );

        $offset = 24 * 4;
        $len = ord($GLOBALS['__phpmorphy_substr']($headerRaw, $offset++, 1));
        $header['lang'] = rtrim($GLOBALS['__phpmorphy_substr']($headerRaw, $offset, $len));

        $offset += $len;

        $len = ord($GLOBALS['__phpmorphy_substr']($headerRaw, $offset++, 1));
        $header['encoding'] = rtrim($GLOBALS['__phpmorphy_substr']($headerRaw, $offset, $len));

        return $header;
    }

    static protected function validateHeader($header) {
        if(
            3 != $header['ver'] ||
            1 == $header['is_be']
        ) {
            return false;
        }

        return true;
    }

    protected function cleanupCString($string) {
        if(false !== ($pos = $GLOBALS['__phpmorphy_strpos']($string, $this->ends))) {
            $string = $GLOBALS['__phpmorphy_substr']($string, 0, $pos);
        }

        return $string;
    }

    abstract protected function readSectionIndex($offset, $count);

    protected function readSectionIndexAsSize($offset, $count, $total_size) {
        if(!$count) {
            return array();
        }

        $index = $this->readSectionIndex($offset, $count);
        $index[$count] = $index[0] + $total_size;

        for($i = 0; $i < $count; $i++) {
            $index[$i] = $index[$i + 1] - $index[$i];
        }

        unset($index[$count]);

        return $index;
    }
};

class phpMorphy_GramInfo_Decorator implements phpMorphy_GramInfo_Interace {
    protected $info;

    function __construct(phpMorphy_GramInfo_Interace $info) {
        $this->info = $info;
    }

    function readGramInfoHeader($offset) { return $this->info->readGramInfoHeader($offset); }
    function getGramInfoHeaderSize() { return $this->info->getGramInfoHeaderSize($offset); }
    function readAncodes($info) { return $this->info->readAncodes($info); }
    function readFlexiaData($info) { return $this->info->readFlexiaData($info); }
    function readAllGramInfoOffsets() { return $this->info->readAllGramInfoOffsets(); }
    function readAllPartOfSpeech() { return $this->info->readAllPartOfSpeech(); }
    function readAllGrammems() { return $this->info->readAllGrammems(); }
    function readAllAncodes() { return $this->info->readAllAncodes(); }

    function getLocale()  { return $this->info->getLocale(); }
    function getEncoding()  { return $this->info->getEncoding(); }
    function getCharSize()  { return $this->info->getCharSize(); }
    function getEnds() { return $this->info->getEnds(); }
    function getHeader() { return $this->info->getHeader(); }
}

class phpMorphy_GramInfo_Proxy extends phpMorphy_GramInfo_Decorator {
    protected $storage;

    function __construct(phpMorphy_Storage $storage) {
        $this->storage = $storage;
        unset($this->info);
    }

    function __get($propName) {
        if($propName == 'info') {
            $this->info = phpMorphy_GramInfo::create($this->storage, false);
            unset($this->storage);
            return $this->info;
        }

        throw new phpMorphy_Exception("Unknown prop name '$propName'");
    }
}

class phpMorphy_GramInfo_Proxy_WithHeader extends phpMorphy_GramInfo_Proxy {
    protected
        $cache,
        $ends;

    function __construct(phpMorphy_Storage $storage, $cacheFile) {
        parent::__construct($storage);

        $this->cache = $this->readCache($cacheFile);
        $this->ends = str_repeat("\0", $this->getCharSize() + 1);
    }

    protected function readCache($fileName) {
        if(!is_array($result = include($fileName))) {
            throw new phpMorphy_Exception("Can`t get header cache from '$fileName' file'");
        }

        return $result;
    }

    function getLocale()  {
        return $this->cache['lang'];
    }

    function getEncoding()  {
        return $this->cache['encoding'];
    }

    function getCharSize()  {
        return $this->cache['char_size'];
    }

    function getEnds() {
        return $this->ends;
    }

    function getHeader() {
        return $this->cache;
    }
}

class phpMorphy_GramInfo_RuntimeCaching extends phpMorphy_GramInfo_Decorator {
    protected
        $flexia = array(),
        $ancodes = array();

    function readFlexiaData($info) {
        $offset = $info['offset'];

        if(!isset($this->flexia_all[$offset])) {
            $this->flexia_all[$offset] = $this->info->readFlexiaData($info);
        }

        return $this->flexia_all[$offset];
    }
}

class phpMorphy_GramInfo_AncodeCache extends phpMorphy_GramInfo_Decorator {
    public
        $hits = 0,
        $miss = 0;

    protected
        $cache;

    function __construct(phpMorphy_GramInfo_Interace $inner, $resource) {
        parent::__construct($inner);

        if(false === ($this->cache = unserialize($resource->read(0, $resource->getFileSize())))) {
            throw new phpMorphy_Exception("Can`t read ancodes cache");
        }
    }

    function readAncodes($info) {
        $offset = $info['offset'];

        if(isset($this->cache[$offset])) {
            $this->hits++;

            return $this->cache[$offset];
        } else {
            // in theory misses never occur
            $this->miss++;

            return parent::readAncodes($info);
        }
    }
}
