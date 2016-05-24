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

if(!defined('PHPMORPHY_DIR')) {
    define('PHPMORPHY_DIR', dirname(__FILE__));
}

require_once(PHPMORPHY_DIR . '/fsa/fsa.php');
require_once(PHPMORPHY_DIR . '/graminfo/graminfo.php');
require_once(PHPMORPHY_DIR . '/morphiers.php');
require_once(PHPMORPHY_DIR . '/gramtab.php');
require_once(PHPMORPHY_DIR . '/storage.php');
require_once(PHPMORPHY_DIR . '/source.php');
require_once(PHPMORPHY_DIR . '/langs_stuff/common.php');

class phpMorphy_Exception extends Exception { }

// we need byte oriented string functions
// with namespaces support we only need overload string functions in current namespace
// but currently use this ugly hack.
function phpmorphy_overload_mb_funcs($prefix) {
    $GLOBALS['__phpmorphy_strlen'] = "{$prefix}strlen";
    $GLOBALS['__phpmorphy_strpos'] = "{$prefix}strpos";
    $GLOBALS['__phpmorphy_strrpos'] = "{$prefix}strrpos";
    $GLOBALS['__phpmorphy_substr'] = "{$prefix}substr";
    $GLOBALS['__phpmorphy_strtolower'] = "{$prefix}strtolower";
    $GLOBALS['__phpmorphy_strtoupper'] = "{$prefix}strtoupper";
    $GLOBALS['__phpmorphy_substr_count'] = "{$prefix}substr_count";
}

if(2 == (ini_get('mbstring.func_overload') & 2)) {
    phpmorphy_overload_mb_funcs('mb_orig_');
} else {
    phpmorphy_overload_mb_funcs('');
}

class phpMorphy_FilesBundle {
    protected
        $dir,
        $lang;

    function __construct($dirName, $lang) {
        $this->dir = rtrim($dirName, "\\/" . DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $this->setLang($lang);
    }

    function getLang() {
        return $this->lang;
    }

    function setLang($lang) {
        $this->lang = $GLOBALS['__phpmorphy_strtolower']($lang);
    }

    function getCommonAutomatFile() {
        return $this->genFileName('common_aut');
    }

    function getPredictAutomatFile() {
        return $this->genFileName('predict_aut');
    }

    function getGramInfoFile() {
        return $this->genFileName('morph_data');
    }

    function getGramInfoAncodesCacheFile() {
        return $this->genFileName('morph_data_ancodes_cache');
    }

    function getAncodesMapFile() {
        return $this->genFileName('morph_data_ancodes_map');
    }

    function getGramTabFile() {
        return $this->genFileName('gramtab');
    }

    function getGramTabFileWithTextIds() {
        return $this->genFileName('gramtab_txt');
    }

    function getDbaFile($type) {
        if(!isset($type)) {
            $type = 'db3';
        }

        return $this->genFileName("common_dict_$type");
    }

    function getGramInfoHeaderCacheFile() {
        return $this->genFileName('morph_data_header_cache');
    }

    protected function genFileName($token, $extraExt = null) {
        return $this->dir . $token . '.' . $this->lang . (isset($extraExt) ? '.' . $extraExt : '') . '.bin';
    }
};

class phpMorphy_WordDescriptor_Collection_Serializer {
    function serialize(phpMorphy_WordDescriptor_Collection $collection, $asText) {
        $result = array();

        foreach($collection as $descriptor) {
            $result[] = $this->processWordDescriptor($descriptor, $asText);
        }

        return $result;
    }

    protected function processWordDescriptor(phpMorphy_WordDescriptor $descriptor, $asText) {
        $forms = array();
        $all = array();

        foreach($descriptor as $word_form) {
            $forms[] = $word_form->getWord();
            $all[] = $this->serializeGramInfo($word_form, $asText);
        }

        return array(
            'forms' => $forms,
            'all' => $all,
            'common' => '',
        );
    }

    protected function serializeGramInfo(phpMorphy_WordForm $wordForm, $asText) {
        if($asText) {
            return $wordForm->getPartOfSpeech() . ' ' . implode(',', $wordForm->getGrammems());
        } else {
            return array(
                'pos' => $wordForm->getPartOfSpeech(),
                'grammems' => $wordForm->getGrammems()
            );
        }
    }
}

class phpMorphy {
    const RESOLVE_ANCODES_AS_TEXT = 0;
    const RESOLVE_ANCODES_AS_DIALING = 1;
    const RESOLVE_ANCODES_AS_INT = 2;

    const NORMAL = 0;
    const IGNORE_PREDICT = 2;
    const ONLY_PREDICT = 3;

    const PREDICT_BY_NONE = 'none';
    const PREDICT_BY_SUFFIX = 'by_suffix';
    const PREDICT_BY_DB = 'by_db';

    protected
        $storage_factory,
        $common_fsa,
        $common_source,
        $predict_fsa,
        $options,

        // variables with two underscores uses lazy paradigm, i.e. initialized at first time access
        //$__common_morphier,
        //$__predict_by_suf_morphier,
        //$__predict_by_db_morphier,
        //$__bulk_morphier,
        //$__word_descriptor_serializer,

        $helper,
        $last_prediction_type
        ;

    function __construct($dir, $lang = null, $options = array()) {
        $this->options = $options = $this->repairOptions($options);

        // TODO: use two versions of phpMorphy class i.e. phpMorphy_v3 { } ... phpMorphy_v2 extends phpMorphy_v3
        if($dir instanceof phpMorphy_FilesBundle && is_array($lang)) {
            $this->initOldStyle($dir, $lang);
        } else {
            $this->initNewStyle($this->createFilesBundle($dir, $lang), $options);
        }

        $this->last_prediction_type = self::PREDICT_BY_NONE;
    }

    /**
    * @return phpMorphy_Morphier_Interface
    */
    function getCommonMorphier() {
        return $this->__common_morphier;
    }

    /**
    * @return phpMorphy_Morphier_Interface
    */
    function getPredictBySuffixMorphier() {
        return $this->__predict_by_suf_morphier;
    }

    /**
    * @return phpMorphy_Morphier_Interface
    */
    function getPredictByDatabaseMorphier() {
        return $this->__predict_by_db_morphier;
    }

    /**
    * @return phpMorphy_Morphier_Bulk
    */
    function getBulkMorphier() {
        return $this->__bulk_morphier;
    }

    /**
    * @return string
    */
    function getEncoding() {
        return $this->helper->getGramInfo()->getEncoding();
    }

    /**
    * @return string
    */
    function getLocale() {
        return $this->helper->getGramInfo()->getLocale();
    }

    /**
     * @return phpMorphy_GrammemsProvider_Base
     */
    function getGrammemsProvider() {
        return clone $this->__grammems_provider;
    }

    /**
     * @return phpMorphy_GrammemsProvider_Base
     */
    function getDefaultGrammemsProvider() {
        return $this->__grammems_provider;
    }

    /**
    * @return phpMorphy_Shm_Cache
    */
    function getShmCache() {
        return $this->storage_factory->getShmCache();
    }

    /**
    * @return bool
    */
    function isLastPredicted() {
        return self::PREDICT_BY_NONE !== $this->last_prediction_type;
    }

    function getLastPredictionType() {
        return $this->last_prediction_type;
    }

    /**
    * @param mixed $word - string or array of strings
    * @param mixed $type - prediction managment
    * @return phpMorphy_WordDescriptor_Collection
    */
    function findWord($word, $type = self::NORMAL) {
        if(is_array($word)) {
            $result = array();

            foreach($word as $w) {
                $result[$w] = $this->invoke('getWordDescriptor', $w, $type);
            }

            return $result;
        } else {
            return $this->invoke('getWordDescriptor', $word, $type);
        }
    }

    /**
    * Alias for getBaseForm
    *
    * @param mixed $word - string or array of strings
    * @param mixed $type - prediction managment
    * @return array
    */
    function lemmatize($word, $type = self::NORMAL) {
        return $this->getBaseForm($word, $type);
    }

    /**
    * @param mixed $word - string or array of strings
    * @param mixed $type - prediction managment
    * @return array
    */
    function getBaseForm($word, $type = self::NORMAL) {
        return $this->invoke('getBaseForm', $word, $type);
    }

    /**
    * @param mixed $word - string or array of strings
    * @param mixed $type - prediction managment
    * @return array
    */
    function getAllForms($word, $type = self::NORMAL) {
        return $this->invoke('getAllForms', $word, $type);
    }

    /**
    * @param mixed $word - string or array of strings
    * @param mixed $type - prediction managment
    * @return array
    */
    function getPseudoRoot($word, $type = self::NORMAL) {
        return $this->invoke('getPseudoRoot', $word, $type);
    }

    /**
    * @param mixed $word - string or array of strings
    * @param mixed $type - prediction managment
    * @return array
    */
    function getPartOfSpeech($word, $type = self::NORMAL) {
        return $this->invoke('getPartOfSpeech', $word, $type);
    }

    /**
    * @param mixed $word - string or array of strings
    * @param mixed $type - prediction managment
    * @return array
    */
    function getAllFormsWithAncodes($word, $type = self::NORMAL) {
        return $this->invoke('getAllFormsWithAncodes', $word, $type);
    }

    /**
    * @param mixed $word - string or array of strings
    * @paradm bool $asText - represent graminfo as text or ancodes
    * @param mixed $type - prediction managment
    * @return array
    */
    function getAllFormsWithGramInfo($word, $asText = true, $type = self::NORMAL) {
        if(false === ($result = $this->findWord($word, $type))) {
            return false;
        }

        $asText = (bool)$asText;

        if(is_array($word)) {
            $out = array();

            foreach($result as $w => $r) {
                if(false !== $r) {
                    $out[$w] = $this->processWordsCollection($r, $asText);
                } else {
                    $out[$w] = false;
                }
            }

            return $out;
        } else {
            return $this->processWordsCollection($result, $asText);
        }
    }

    /**
    * @param mixed $word - string or array of strings
    * @param mixed $type - prediction managment
    * @return array
    */
    function getAncode($word, $type = self::NORMAL) {
        return $this->invoke('getAncode', $word, $type);
    }

    /**
    * @param mixed $word - string or array of strings
    * @param mixed $type - prediction managment
    * @return array
    */
    function getGramInfo($word, $type = self::NORMAL) {
        return $this->invoke('getGrammarInfo', $word, $type);
    }

    /**
    * @param mixed $word - string or array of strings
    * @param mixed $type - prediction managment
    * @return array
    */
    function getGramInfoMergeForms($word, $type = self::NORMAL) {
        return $this->invoke('getGrammarInfoMergeForms', $word, $type);
    }

    protected function getAnnotForWord($word, $type) {
        return $this->invoke('getAnnot', $word, $type);
    }

    /**
    * @param string $word
    * @param mixed $ancode
    * @param mixed $commonAncode
    * @param bool $returnOnlyWord
    * @param mixed $callback
    * @param mixed $type
    * @return array
    */
    function castFormByAncode($word, $ancode, $commonAncode = null, $returnOnlyWord = false, $callback = null, $type = self::NORMAL) {
        $resolver = $this->helper->getAncodesResolver();

        $common_ancode_id = $resolver->unresolve($commonAncode);
        $ancode_id = $resolver->unresolve($ancode);

        $data = $this->helper->getGrammemsAndPartOfSpeech($ancode_id);

        if(isset($common_ancode_id)) {
            $data[1] = array_merge($data[1], $this->helper->getGrammems($common_ancode_id));
        }

        return $this->castFormByGramInfo(
            $word,
            $data[0],
            $data[1],
            $returnOnlyWord,
            $callback,
            $type
        );
    }

    /**
    * @param string $word
    * @param mixed $partOfSpeech
    * @param array $grammems
    * @param bool $returnOnlyWord
    * @param mixed $callback
    * @param mixed $type
    * @return array
    */
    function castFormByGramInfo($word, $partOfSpeech, $grammems, $returnOnlyWord = false, $callback = null, $type = self::NORMAL) {
        if(false === ($annot = $this->getAnnotForWord($word, $type))) {
            return false;
        }

        return $this->helper->castFormByGramInfo($word, $annot, $partOfSpeech, $grammems, $returnOnlyWord, $callback);
    }

    /**
    * @param string $word
    * @param string $patternWord
    * @param mixed $essentialGrammems
    * @param bool $returnOnlyWord
    * @param mixed $callback
    * @param mixed $type
    * @return array
    */
    function castFormByPattern($word, $patternWord, phpMorphy_GrammemsProvider_Interface $grammemsProvider = null, $returnOnlyWord = false, $callback = null, $type = self::NORMAL) {
        if(false === ($word_annot = $this->getAnnotForWord($word, $type))) {
            return false;
        }

        if(!isset($grammemsProvider)) {
            $grammemsProvider = $this->__grammems_provider;
        }

        $result = array();

        foreach($this->getGramInfo($patternWord, $type) as $paradigm) {
            foreach($paradigm as $grammar) {
                $pos = $grammar['pos'];

                $essential_grammems = $grammemsProvider->getGrammems($pos);

                $grammems =  false !== $essential_grammems ?
                    array_intersect($grammar['grammems'], $essential_grammems):
                    $grammar['grammems'];

                $res = $this->helper->castFormByGramInfo(
                    $word,
                    $word_annot,
                    $pos,
                    $grammems,
                    $returnOnlyWord,
                    $callback,
                    $type
                );

                if(count($res)) {
                    $result = array_merge($result, $res);
                }
            }
        }

        return $returnOnlyWord ? array_unique($result) : $result;
    }

    // public interface end

    protected function processWordsCollection(phpMorphy_WordDescriptor_Collection $collection, $asText) {
        return $this->__word_descriptor_serializer->serialize($collection, $asText);
    }

    protected function invoke($method, $word, $type) {
        $this->last_prediction_type = self::PREDICT_BY_NONE;

        if($type === self::ONLY_PREDICT) {
            if(is_array($word)) {
                $result = array();

                foreach($word as $w) {
                    $result[$w] = $this->predictWord($method, $w);
                }

                return $result;
            } else {
                return $this->predictWord($method, $word);
            }
        }

        if(is_array($word)) {
            $result = $this->__bulk_morphier->$method($word);

            if($type !== self::IGNORE_PREDICT) {
                $not_found = $this->__bulk_morphier->getNotFoundWords();

                for($i = 0, $c = count($not_found); $i < $c; $i++) {
                    $word = $not_found[$i];

                    $result[$word] = $this->predictWord($method, $word);
                }
            } else {
                for($i = 0, $c = count($not_found); $i < $c; $i++) {
                    $result[$not_found[$i]] = false;
                }
            }

            return $result;
        } else {
            if(false === ($result = $this->__common_morphier->$method($word))) {
                if($type !== self::IGNORE_PREDICT) {
                    return $this->predictWord($method, $word);
                }
            }

            return $result;
        }
    }

    protected function predictWord($method, $word) {
        if(false !== ($result = $this->__predict_by_suf_morphier->$method($word))) {
            $this->last_prediction_type = self::PREDICT_BY_SUFFIX;

            return $result;
        }

        if(false !== ($result = $this->__predict_by_db_morphier->$method($word))) {
            $this->last_prediction_type = self::PREDICT_BY_DB;

            return $result;
        }

        return false;
    }

    ////////////////
    // init code
    ////////////////
    protected function initNewStyle(phpMorphy_FilesBundle $bundle, $options) {
        $this->options = $options = $this->repairOptions($options);
        $storage_type = $options['storage'];

        $storage_factory = $this->storage_factory = $this->createStorageFactory($options['shm']);
        $graminfo_as_text = $this->options['graminfo_as_text'];

        // fsa
        $this->common_fsa = $this->createFsa($storage_factory->open($storage_type, $bundle->getCommonAutomatFile(), false), false); // lazy
        $this->predict_fsa = $this->createFsa($storage_factory->open($storage_type, $bundle->getPredictAutomatFile(), true), true);  // lazy

        // graminfo
        $graminfo = $this->createGramInfo($storage_factory->open($storage_type, $bundle->getGramInfoFile(), true), $bundle); // lazy

        // gramtab
        $gramtab = $this->createGramTab(
            $storage_factory->open(
                $storage_type,
                $graminfo_as_text ? $bundle->getGramTabFileWithTextIds() : $bundle->getGramTabFile(),
                true
            )
        ); // always lazy

        // common source
        //$this->__common_source = $this->createCommonSource($bundle, $this->options['common_source']);

        $this->helper = $this->createMorphierHelper($graminfo, $gramtab, $graminfo_as_text, $bundle);
    }

    protected function createCommonSource(phpMorphy_FilesBundle $bundle, $opts) {
        $type = $opts['type'];

        switch($type) {
            case PHPMORPHY_SOURCE_FSA:
                return new phpMorphy_Source_Fsa($this->common_fsa);
            case PHPMORPHY_SOURCE_DBA:
                return new phpMorphy_Source_Dba(
                    $bundle->getDbaFile($this->getDbaHandlerName(@$opts['opts']['handler'])),
                        $opts['opts']
                    );
            default:
                throw new phpMorphy_Exception("Unknown source type given '$type'");
        }
    }

    protected function getDbaHandlerName($name) {
        return isset($name) ? $name : phpMorphy_Source_Dba::getDefaultHandler();
    }

    protected function initOldStyle(phpMorphy_FilesBundle $bundle, $options) {
        $options = $this->repairOptions($options);

        switch($bundle->getLang()) {
            case 'rus':
                $bundle->setLang('ru_RU');
                break;
            case 'eng':
                $bundle->setLang('en_EN');
                break;
            case 'ger':
                $bundle->setLang('de_DE');
                break;
        }

        $this->initNewStyle($bundle, $options);
    }

    protected function repairOldOptions($options) {
        $defaults = array(
            'predict_by_suffix' => false,
            'predict_by_db' => false,
        );

        return (array)$options + $defaults;
    }

    protected function repairSourceOptions($options) {
        $defaults = array(
            'type' => PHPMORPHY_SOURCE_FSA,
            'opts' => null
        );

        return (array)$options + $defaults;
    }

    protected function repairOptions($options) {
        $defaults = array(
            'shm' => array(),
            'graminfo_as_text' => true,
            'storage' => PHPMORPHY_STORAGE_FILE,
            'common_source' => $this->repairSourceOptions(@$options['common_source']),
            'predict_by_suffix' => true,
            'predict_by_db' => true,
            'use_ancodes_cache' => false,
            'resolve_ancodes' => self::RESOLVE_ANCODES_AS_TEXT
        );

        return (array)$options + $defaults;
    }

    function __get($name) {
        switch($name) {
            case '__predict_by_db_morphier':
                $this->__predict_by_db_morphier = $this->createPredictByDbMorphier(
                    $this->predict_fsa,
                    $this->helper
                );

                break;
            case '__predict_by_suf_morphier':
                $this->__predict_by_suf_morphier = $this->createPredictBySuffixMorphier(
                    $this->common_fsa,
                    $this->helper
                );

                break;
            case '__bulk_morphier':
                $this->__bulk_morphier = $this->createBulkMorphier(
                    $this->common_fsa,
                    $this->helper
                );

                break;
            case '__common_morphier':
                $this->__common_morphier = $this->createCommonMorphier(
                    $this->common_fsa,
                    $this->helper
                );

                break;

            case '__word_descriptor_serializer':
                $this->__word_descriptor_serializer = $this->createWordDescriptorSerializer();
                break;
            case '__grammems_provider':
                $this->__grammems_provider = $this->createGrammemsProvider();
                break;
            default:
                throw new phpMorphy_Exception("Invalid prop name '$name'");
        }

        return $this->$name;
    }

    ////////////////////
    // factory methods
    ////////////////////
    function createGrammemsProvider() {
        return phpMorphy_GrammemsProvider_Factory::create($this);
    }

    protected function createWordDescriptorSerializer() {
        return new phpMorphy_WordDescriptor_Collection_Serializer();
    }

    protected function createFilesBundle($dir, $lang) {
        return new phpMorphy_FilesBundle($dir, $lang);
    }

    protected function createStorageFactory($options) {
        return new phpMorphy_Storage_Factory($options);
    }

    protected function createFsa(phpMorphy_Storage $storage, $lazy) {
        return phpMorphy_Fsa::create($storage, $lazy);
    }

    protected function createGramInfo(phpMorphy_Storage $graminfoFile, phpMorphy_FilesBundle $bundle) {
        //return new phpMorphy_GramInfo_RuntimeCaching(new phpMorphy_GramInfo_Proxy($storage));
        //return new phpMorphy_GramInfo_RuntimeCaching(phpMorphy_GramInfo::create($storage, false));

        $result = new phpMorphy_GramInfo_RuntimeCaching(
            new phpMorphy_GramInfo_Proxy_WithHeader(
                $graminfoFile,
                $bundle->getGramInfoHeaderCacheFile()
            )
        );

        if($this->options['use_ancodes_cache']) {
            return new phpMorphy_GramInfo_AncodeCache(
                $result,
                $this->storage_factory->open(
                    $this->options['storage'],
                    $bundle->getGramInfoAncodesCacheFile(),
                    true
                ) // always lazy open
            );
        } else {
            return $result;
        }
    }

    protected function createGramTab(phpMorphy_Storage $storage) {
        return new phpMorphy_GramTab_Proxy($storage);
    }

    protected function createAncodesResolverInternal(phpMorphy_GramTab_Interface $gramtab, phpMorphy_FilesBundle $bundle) {
        switch($this->options['resolve_ancodes']) {
            case self::RESOLVE_ANCODES_AS_TEXT:
                return array(
                    'phpMorphy_AncodesResolver_ToText',
                    array($gramtab)
                );
            case self::RESOLVE_ANCODES_AS_INT:
                return array(
                    'phpMorphy_AncodesResolver_AsIs',
                    array()
                );
            case self::RESOLVE_ANCODES_AS_DIALING:
                return array(
                    'phpMorphy_AncodesResolver_ToDialingAncodes',
                    array(
                        $this->storage_factory->open(
                            $this->options['storage'],
                            $bundle->getAncodesMapFile(),
                            true
                        ) // always lazy open
                    )
                );
            default:
                throw new phpMorphy_Exception("Invalid resolve_ancodes option, valid values are RESOLVE_ANCODES_AS_DIALING, RESOLVE_ANCODES_AS_INT, RESOLVE_ANCODES_AS_TEXT");
        }
    }

    protected function createAncodesResolver(phpMorphy_GramTab_Interface $gramtab, phpMorphy_FilesBundle $bundle, $lazy) {
        $result = $this->createAncodesResolverInternal($gramtab, $bundle);

        if($lazy) {
            return new phpMorphy_AncodesResolver_Proxy($result[0], $result[1]);
        } else {
            return phpMorphy_AncodesResolver_Proxy::instantinate($result[0], $result[1]);
        }
    }

    protected function createMorphierHelper(
        phpMorphy_GramInfo_Interace $graminfo,
        phpMorphy_GramTab_Interface $gramtab,
        $graminfoAsText,
        phpMorphy_FilesBundle $bundle
    ) {
        return new phpMorphy_Morphier_Helper(
            $graminfo,
            $gramtab,
            $this->createAncodesResolver($gramtab, $bundle, true),
            $graminfoAsText
        );
    }

    protected function createCommonMorphier(phpMorphy_Fsa_Interface $fsa, phpMorphy_Morphier_Helper $helper) {
        return new phpMorphy_Morphier_Common($fsa, $helper);
    }

    protected function createBulkMorphier(phpMorphy_Fsa_Interface $fsa, phpMorphy_Morphier_Helper $helper) {
        return new phpMorphy_Morphier_Bulk($fsa, $helper);
    }

    protected function createPredictByDbMorphier(phpMorphy_Fsa_Interface $fsa, phpMorphy_Morphier_Helper $helper) {
        if($this->options['predict_by_db']) {
                return new phpMorphy_Morphier_Predict_Database($fsa, $helper);
        } else {
            return new phpMorphy_Morphier_Empty();
        }
    }

    protected function createPredictBySuffixMorphier(phpMorphy_Fsa_Interface $fsa, phpMorphy_Morphier_Helper $helper) {
        if($this->options['predict_by_suffix']) {
            return new phpMorphy_Morphier_Predict_Suffix($fsa, $helper);
        } else {
            return new phpMorphy_Morphier_Empty();
        }
    }
};
