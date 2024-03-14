<?php
class Tpl {
    var $dir;

    function __construct($dir) {
        $this->dir = $dir;
    }

    function get($tpl, $opts) {
        ob_start();

        extract($opts);

        include("$this->dir/$tpl.tpl.php");

        $content = ob_get_contents();
        ob_end_clean();

        return $content;
    }
};

class Helper_Base {
    var $tpl;
    var $storage;

    function __construct($tpl, $storage) {
        $this->tpl = $tpl;
        $this->storage = $storage;
    }

    function out($str, $suffix) {
        if(strlen($str)) {
            echo $str, $suffix;
        }
    }

    function name() { return strtolower(str_replace('Helper_', '', get_class($this))); }

    function className() {
        $suffix = ucfirst($this->name());
        $storage_prefix = ucfirst($this->storage->name());
        $class = $this->parentClassName();

        return "{$class}_{$suffix}_{$storage_prefix}";
    }

    function parentClassName() { }
}

class StorageHelper {
    function name() {
        return strtolower(str_replace(__CLASS__ . '_', '', get_class($this)));
    }

    function prolog() { }
    function seek($offset) { }
    function read($offset, $len) { }
}

class StorageHelper_File extends StorageHelper {
    function prolog() { return '$__fh = $this->resource'; }
    function seek($offset) { return 'fseek($__fh, ' . $offset . ')'; }
    function read($offset, $len) { return "fread(\$__fh, $len)"; }
}

class StorageHelper_Shm extends StorageHelper {
    function prolog() { return '$__shm = $this->resource[\'shm_id\']; $__offset = $this->resource[\'offset\']'; }
    function seek($offset) { return ''; }
    function read($offset, $len) { return "shmop_read(\$__shm, \$__offset + ($offset), $len)"; }
}

class StorageHelper_Mem extends StorageHelper {
    function prolog() { return '$__mem = $this->resource'; }
    function seek($offset) { return ''; }
    function read($offset, $len) { return "\$GLOBALS['__phpmorphy_substr'](\$__mem, $offset, $len)"; }
}

