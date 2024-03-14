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

interface phpMorphy_Fsa_Interface {
    /**
     * Return root transition of fsa
     * @return array
     */
    function getRootTrans();

    /**
     * Returns root state object
     * @return
     */
    function getRootState();

    /**
     * Returns alphabet i.e. all chars used in automat
     * @return array
     */
    function getAlphabet();

    /**
     * Return annotation for given transition(if annotation flag is set for given trans)
     *
     * @param array $trans
     * @return string
     */
    function getAnnot($trans);

    /**
     * Find word in automat
     *
     * @param mixed $trans starting transition
     * @param string $word
     * @param bool $readAnnot read annot or simple check if word exists in automat
     * @return bool TRUE if word is found, FALSE otherwise
     */
    function walk($trans, $word, $readAnnot = true);

    /**
     * Traverse automat and collect words
     * For each found words $callback function invoked with follow arguments:
     * call_user_func($callback, $word, $annot)
     * when $readAnnot is FALSE then $annot arg is always NULL
     *
     * @param mixed $startNode
     * @param mixed $callback callback function(in php format callback i.e. string or array(obj, method) or array(class, method)
     * @param bool $readAnnot read annot
     * @param string $path string to be append to all words
     */
    function collect($startNode, $callback, $readAnnot = true, $path = '');

    /**
     * Read state at given index
     *
     * @param int $index
     * @return array
     */
    function readState($index);

    /**
     * Unpack transition from binary form to array
     *
     * @param mixed $rawTranses may be array for convert more than one transitions
     * @return array
     */
    function unpackTranses($rawTranses);
}

abstract class phpMorphy_Fsa implements phpMorphy_Fsa_Interface {
    const HEADER_SIZE = 128;

    protected
        $resource,
        $header,
        $fsa_start,
        $root_trans,
        $alphabet;

    protected function __construct($resource, $header) {
        $this->resource = $resource;
        $this->header = $header;
        $this->fsa_start = $header['fsa_offset'];
        $this->root_trans = $this->readRootTrans();
    }

    // static
    static function create(phpMorphy_Storage $storage, $lazy) {
        if($lazy) {
            return new phpMorphy_Fsa_Proxy($storage);
        }

        $header = phpMorphy_Fsa::readHeader(
            $storage->read(0, self::HEADER_SIZE, true)
        );

        if(!phpMorphy_Fsa::validateHeader($header)) {
            throw new phpMorphy_Exception('Invalid fsa format');
        }

        if($header['flags']['is_sparse']) {
            $type = 'sparse';
        } else if($header['flags']['is_tree']) {
            $type = 'tree';
        } else {
            throw new phpMorphy_Exception('Only sparse or tree fsa`s supported');
        }

        $storage_type = $storage->getTypeAsString();
        $file_path = dirname(__FILE__) . "/access/fsa_{$type}_{$storage_type}.php";
        $clazz = 'phpMorphy_Fsa_' . ucfirst($type) . '_' . ucfirst($storage_type);

        require_once($file_path);
        return new $clazz(
            $storage->getResource(),
            $header
        );
    }

    function getRootTrans() { return $this->root_trans; }

    function getRootState() {
        return $this->createState($this->getRootStateIndex());
    }

    function getAlphabet() {
        if(!isset($this->alphabet)) {
            $this->alphabet = str_split($this->readAlphabet());
        }

        return $this->alphabet;
    }

    protected function createState($index) {
        require_once(PHPMORPHY_DIR . '/fsa/fsa_state.php');
        return new phpMorphy_State($this, $index);
    }

    static protected function readHeader($headerRaw) {
        if($GLOBALS['__phpmorphy_strlen']($headerRaw) != self::HEADER_SIZE) {
            throw new phpMorphy_Exception('Invalid header string given');
        }

        $header = unpack(
            'a4fourcc/Vver/Vflags/Valphabet_offset/Vfsa_offset/Vannot_offset/Valphabet_size/Vtranses_count/Vannot_length_size/' .
            'Vannot_chunk_size/Vannot_chunks_count/Vchar_size/Vpadding_size/Vdest_size/Vhash_size',
            $headerRaw
        );

        if(false === $header) {
            throw new phpMorphy_Exception('Can`t unpack header');
        }

        $flags = array();
        $raw_flags = $header['flags'];
        $flags['is_tree'] =  $raw_flags & 0x01 ? true : false;
        $flags['is_hash'] =  $raw_flags & 0x02 ? true : false;
        $flags['is_sparse'] = $raw_flags & 0x04 ? true : false;
        $flags['is_be'] =  $raw_flags & 0x08 ? true : false;

        $header['flags'] = $flags;

        $header['trans_size'] = $header['char_size'] + $header['padding_size'] + $header['dest_size'] + $header['hash_size'];

        return $header;
    }

    // static
    static protected function validateHeader($header) {
        if(
            'meal' != $header['fourcc'] ||
            3 != $header['ver'] ||
            $header['char_size'] != 1 ||
            $header['padding_size'] > 0 ||
            $header['dest_size'] != 3 ||
            $header['hash_size'] != 0 ||
            $header['annot_length_size'] != 1 ||
            $header['annot_chunk_size'] != 1 ||
            $header['flags']['is_be'] ||
            $header['flags']['is_hash'] ||
            1 == 0
        ) {
            return false;
        }

        return true;
    }

    protected function getRootStateIndex() { return 0; }

    abstract protected function readRootTrans();
    abstract protected function readAlphabet();
};

class phpMorphy_Fsa_WordsCollector {
    protected
        $items = array(),
        $limit;

    function __construct($collectLimit) {
        $this->limit = $collectLimit;
    }

    function collect($word, $annot) {
        if(count($this->items) < $this->limit) {
            $this->items[$word] = $annot;
            return true;
        } else {
            return false;
        }
    }

    function getItems() { return $this->items; }
    function clear() { $this->items = array(); }
    function getCallback() { return array($this, 'collect'); }
};

class phpMorphy_Fsa_Decorator implements phpMorphy_Fsa_Interface {
    protected $fsa;

    function __construct(phpMorphy_Fsa_Interface $fsa) {
        $this->fsa = $fsa;
    }

    function getRootTrans() { return $this->fsa->getRootTrans(); }
    function getRootState() { return $this->fsa->getRootState(); }
    function getAlphabet() { return $this->fsa->getAlphabet(); }
    function getAnnot($trans) { return $this->fsa->getAnnot($trans); }
    function walk($start, $word, $readAnnot = true) { return $this->fsa->walk($start, $word, $readAnnot); }
    function collect($start, $callback, $readAnnot = true, $path = '') { return $this->fsa->collect($start, $callback, $readAnnot, $path); }
    function readState($index) { return $this->fsa->readState($index); }
    function unpackTranses($transes) { return $this->fsa->unpackTranses($transes); }
};

class phpMorphy_Fsa_Proxy extends phpMorphy_Fsa_Decorator {
    protected $storage;

    function __construct(phpMorphy_Storage $storage) {
        $this->storage = $storage;
        unset($this->fsa);
    }

    function __get($propName) {
        if($propName == 'fsa') {
            $this->fsa = phpMorphy_Fsa::create($this->storage, false);

            unset($this->storage);
            return $this->fsa;
        }

        throw new phpMorphy_Exception("Unknown prop name '$propName'");
    }
}
