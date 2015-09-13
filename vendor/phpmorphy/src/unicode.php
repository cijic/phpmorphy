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

abstract class phpMorphy_UnicodeHelper {
    protected static $cache = array();
    
    static function create($encoding) {
        $encoding = $GLOBALS['__phpmorphy_strtolower']($encoding);
        
        if(isset(self::$cache[$encoding])) {
            return self::$cache[$encoding];
        }
        
        $result = self::doCreate($encoding);
        
        self::$cache[$encoding] = $result;
        
        return $result;
    }
    
    protected static function doCreate($encoding) {
        if(preg_match('~^(utf|ucs)(-)?([0-9]+)(-)?(le|be)?$~', $encoding, $matches)) {
            $utf_type = $matches[1];
            $utf_base = (int)$matches[3];
            $endiannes = '';
            
            switch($utf_type) {
                case 'utf':
                    if(!in_array($utf_base, array(8, 16, 32))) {
                        throw new phpMorphy_Exception('Invalid utf base');
                    }
                    
                    break;
                case 'ucs':
                    if(!in_array($utf_base, array(2, 4))) {
                        throw new phpMorphy_Exception('Invalid ucs base');
                    }
                    
                    break;
                default: throw new phpMorphy_Exception('Internal error');
            }
            
            if($utf_base > 8 || 'ucs' === $utf_type) {
                if(isset($matches[5])) {
                    $endiannes = $matches[5] == 'be' ? 'be' : 'le';
                } else {
                    $tmp = pack('L', 1);
                    $endiannes = ord($tmp[0]) == 0 ? 'be' : 'le';
                }
            }
            
            
            if($utf_type == 'ucs' || $utf_base > 8) {
                $encoding_name = "$utf_type-$utf_base$endiannes";
            } else {
                $encoding_name = "$utf_type-$utf_base";
            }
            
            $clazz = "phpMorphy_UnicodeHelper_" . str_replace('-', '_', $encoding_name);
            
            return new $clazz($encoding_name);
        } else {
            return new phpMorphy_UnicodeHelper_singlebyte($encoding);
        }
    }
    
    abstract function firstCharSize($str);
    abstract function strrev($str);
    abstract function strlen($str);
    abstract function fixTrailing($str);
}

abstract class phpMorphy_UnicodeHelper_Base extends phpMorphy_UnicodeHelper {
    protected static
        $ICONV,
        $MB,
        $STRLEN_FOO
        ;
        
    protected
        $encoding,
        $strlen_foo,
        $iconv,
        $mb
        ;
    
    protected function __construct($encoding) {
        $this->encoding = $encoding;
        
        if(!isset(self::$ICONV) || !isset(self::$MB)) {
            if(false !== (self::$ICONV = extension_loaded('iconv'))) {
                self::$STRLEN_FOO = 'iconv_strlen';
            } else if(false !== (self::$MB = extension_loaded('mbstring'))) {
                self::$STRLEN_FOO = 'mb_strlen';
            }
        }
    }

/*
    function fixTrailing($str) {
        $to = $this->encoding === 'utf-16' ? 'utf-32' : 'utf-16';
        
        if(self::ICONV) {
            $new = @iconv($this->encoding, $to, $str);
            return @iconv($to, $this->encoding, $new);
        } else if(self::MB) {
            $new = @mb_convert_encoding($str, $to, $this->encoding);
            return @mb_convert_encoding($str, $this->encoding, $to);
        } else {
            $this->php_fixTrailing($str);
        }
    }
*/

    function strlen($str) {
        if(isset(self::$STRLEN_FOO)) {
            $foo = self::$STRLEN_FOO;
            return $foo($str, $this->encoding);
        } else {
            return $this->php_strlen($str);
        }
    }
    
    protected abstract function php_strlen($str);
}

class phpMorphy_UnicodeHelper_MultiByteFixed extends phpMorphy_UnicodeHelper_Base {
    protected
        $size;
        
    protected function __construct($encoding, $size) {
        parent::__construct($encoding);
        $this->size = $size;
    }
    
    function firstCharSize($str) {
        return $this->size;
    }
    
    function strrev($str) {
        return implode('', array_reverse(str_split($str, $this->size)));
    }

    protected function php_strlen($str) {
        return $GLOBALS['__phpmorphy_strlen']($str) / $this->size;
    }
    
    function fixTrailing($str) {
        $len = $GLOBALS['__phpmorphy_strlen']($str);
        
        if(($len % $this->size) > 0) {
            return $GLOBALS['__phpmorphy_substr']($str, 0, floor($len / $this->size) * $this->size);
        }
        
        return $str;
    }
}

// single byte encoding
class phpMorphy_UnicodeHelper_singlebyte extends phpMorphy_UnicodeHelper_Base {
    function firstCharSize($str) {
        return 1;
    }
    
    function strrev($str) {
        return strrev($str);
    }
    
    function strlen($str) {
        return $GLOBALS['__phpmorphy_strlen']($str);
    }

    function fixTrailing($str) {
        return $str;
    }
    
    protected function php_strlen($str) {
        return $GLOBALS['__phpmorphy_strlen']($str);
    }
}

// utf8
class phpMorphy_UnicodeHelper_utf_8 extends phpMorphy_UnicodeHelper_Base {
    protected
        $tails_length;
        
    protected function __construct($encoding) {
        parent::__construct($encoding);
        
        $this->tails_length = $this->getTailsLength();
    }
    
    function firstCharSize($str) {
        return 1 + $this->tails_length[ord($str[0])];
    }
    
    function strrev($str) {
        preg_match_all('/./us', $str, $matches);
        return implode('', array_reverse($matches[0]));
        /*
        $result = array();
        
        for($i = 0, $c = $GLOBALS['__phpmorphy_strlen']($str); $i < $c;) {
            $len = 1 + $this->tails_length[ord($str[$i])];
            
            $result[] = $GLOBALS['__phpmorphy_substr']($str, $i, $len);
            
            $i += $len;
        }
        
        return implode('', array_reverse($result));
        */
    }
    
    function fixTrailing($str) {
        $strlen = $GLOBALS['__phpmorphy_strlen']($str);
        
        if(!$strlen) {
            return '';
        }
        
        $ord = ord($str[$strlen - 1]);
        
        if(($ord & 0x80) == 0) {
            return $str;
        }
        
        for($i = $strlen - 1; $i >= 0; $i--) {
            $ord = ord($str[$i]);
            
            if(($ord & 0xC0) == 0xC0) {
                $diff = $strlen - $i;
                $seq_len = $this->tails_length[$ord] + 1;
                
                $miss = $seq_len - $diff;
                
                if($miss) {
                    return $GLOBALS['__phpmorphy_substr']($str, 0, -($seq_len - $miss));
                } else {
                    return $str;
                }
            }
        }
        
        return '';
    }
    
    protected function php_strlen($str) {
        preg_match_all('/./us', $str, $matches);
        return count($matches[0]);
    }
    
    protected function getTailsLength() {
        return array(
            0,0,0,0,0,0,0,0, 0,0,0,0,0,0,0,0,
            0,0,0,0,0,0,0,0, 0,0,0,0,0,0,0,0,
            0,0,0,0,0,0,0,0, 0,0,0,0,0,0,0,0,
            0,0,0,0,0,0,0,0, 0,0,0,0,0,0,0,0,
            0,0,0,0,0,0,0,0, 0,0,0,0,0,0,0,0,
            0,0,0,0,0,0,0,0, 0,0,0,0,0,0,0,0,
            0,0,0,0,0,0,0,0, 0,0,0,0,0,0,0,0,
            0,0,0,0,0,0,0,0, 0,0,0,0,0,0,0,0,
            0,0,0,0,0,0,0,0, 0,0,0,0,0,0,0,0,
            0,0,0,0,0,0,0,0, 0,0,0,0,0,0,0,0,
            0,0,0,0,0,0,0,0, 0,0,0,0,0,0,0,0,
            0,0,0,0,0,0,0,0, 0,0,0,0,0,0,0,0,
            1,1,1,1,1,1,1,1, 1,1,1,1,1,1,1,1,
            1,1,1,1,1,1,1,1, 1,1,1,1,1,1,1,1,
            2,2,2,2,2,2,2,2, 2,2,2,2,2,2,2,2,
            3,3,3,3,3,3,3,3, 4,4,4,4,5,5,0,0
        );
    }
}

// utf16
class phpMorphy_UnicodeHelper_utf_16_Base extends phpMorphy_UnicodeHelper_Base {
    protected
        $is_be,
        $char_fmt;
    
    protected function __construct($encoding, $isBigEndian) {
        parent::__construct($encoding);
        
        $this->is_be = (bool)$isBigEndian;
        $this->char_fmt = $isBigEndian ? 'n' : 'v';
    }
    
    function firstCharSize($str) {
        list(, $ord) = unpack($this->char_fmt, $str);
        
        return $ord >= 0xD800 && $ord <= 0xDFFF ? 4 : 2;
    }
    
    function strrev($str) {
        $result = array();
        
        $count = $GLOBALS['__phpmorphy_strlen']($str) / 2;
        $fmt = $this->char_fmt . $count;
        
        $words = array_reverse(unpack($fmt, $str));
        
        for($i = 0; $i < $count; $i++) {
            $ord = $words[$i];
            
            if($ord >= 0xD800 && $ord <= 0xDFFF) {
                // swap surrogates
                $t = $words[$i];
                $words[$i] = $words[$i + 1];
                
                $i++;
                $words[$i] = $t;
            }
        }
        
        array_unshift($words, $fmt);
        
        return call_user_func_array('pack', $words);
    }
    
    function fixTrailing($str) {
        $strlen = $GLOBALS['__phpmorphy_strlen']($str);
        
        if($strlen & 1) {
            $strlen--;
            $str = $GLOBALS['__phpmorphy_substr']($str, 0, $strlen);
        }
        
        if($strlen < 2) {
            return '';
        }
        
        list(, $ord) = unpack($this->char_fmt, $GLOBALS['__phpmorphy_substr']($str, -2, 2));
        
        if($this->isSurrogate($ord)) {
            if($strlen < 4) {
                return '';
            }
            
            list(, $ord) = unpack($this->char_fmt, $GLOBALS['__phpmorphy_substr']($str, -4, 2));
            
            if($this->isSurrogate($ord)) {
                // full surrogate pair
                return $str;
            } else {
                return $GLOBALS['__phpmorphy_substr']($str, 0, -2);
            }
        }
        
        return $str;
    }
    
    protected function php_strlen($str) {
        $count = $GLOBALS['__phpmorphy_strlen']($str) / 2;
        $fmt = $this->char_fmt . $count;
        
        foreach(unpack($fmt, $str) as $ord) {
            if($ord >= 0xD800 && $ord <= 0xDFFF) {
                $count--;
            }
        }
        
        return $count;
    }
    
    protected function isSurrogate($ord) {
        return $ord >= 0xD800 && $ord <= 0xDFFF;
    }
}

class phpMorphy_UnicodeHelper_utf_16le extends phpMorphy_UnicodeHelper_utf_16_Base {
    protected function __construct($encoding) {
        parent::__construct($encoding, false);
    }
}

class phpMorphy_UnicodeHelper_utf_16be extends phpMorphy_UnicodeHelper_utf_16_Base {
    protected function __construct($encoding) {
        parent::__construct($encoding, true);
    }
}

// utf32
class phpMorphy_UnicodeHelper_utf_32_Base extends  phpMorphy_UnicodeHelper_MultiByteFixed {
    protected function __construct($encoding) { parent::__construct($encoding, 4); }
}

class phpMorphy_UnicodeHelper_utf_32le extends phpMorphy_UnicodeHelper_utf_32_Base { }

class phpMorphy_UnicodeHelper_utf_32be extends phpMorphy_UnicodeHelper_utf_32_Base { }

// ucs2, ucs4
class phpMorphy_UnicodeHelper_ucs_2le extends phpMorphy_UnicodeHelper_MultiByteFixed {
    protected function __construct($encoding) { parent::__construct($encoding, 2); }
}

class phpMorphy_UnicodeHelper_ucs_2be extends phpMorphy_UnicodeHelper_MultiByteFixed {
    protected function __construct($encoding) { parent::__construct($encoding, 2); }
}

class phpMorphy_UnicodeHelper_ucs_4le extends phpMorphy_UnicodeHelper_MultiByteFixed {
    protected function __construct($encoding) { parent::__construct($encoding, 4); }
}

class phpMorphy_UnicodeHelper_ucs_4be extends phpMorphy_UnicodeHelper_MultiByteFixed {
    protected function __construct($encoding) { parent::__construct($encoding, 4); }
}
