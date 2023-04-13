<?php
require_once(dirname(__FILE__) . '/writer.php');
require_once(dirname(__FILE__) . '/../source/source_normalized.php');
require_once(dirname(__FILE__) . '/sql/common.php');

class phpMorphy_Dict_Writer_Sql_Exception extends Exception { }

class phpMorphy_Dict_Writer_Sql_Context_Resolver {
    protected
        $name,
        $forward_map = array();

    function __construct($name) {
        $this->name = $name;
    }

    function register($original, $db) {
        $this->forward_map[$original] = (int)$db;
    }

    function resolve($original) {
        if(!isset($this->forward_map[$original])) {
            throw new phpMorphy_Dict_Writer_Sql_Exception("'$original' not in $this->name map");
        }

        return $this->forward_map[$original];
    }
}

class phpMorphy_Dict_Writer_Sql_Context {
    protected
        $dict_id,
        $poses_map,
        $grammems_map,
        $ancodes_map,
        $prefixes_map,
        $flexias_map;

    function __construct() {
        $this->poses_map = $this->createResolver('PartOfSpeech'); 
        $this->grammems_map = $this->createResolver('Grammem'); 
        $this->ancodes_map = $this->createResolver('Ancode'); 
        $this->prefixes_map = $this->createResolver('Prefixes');
        $this->flexias_map = $this->createResolver('Flexia'); 
    }

    function setDictId($id) {
        $this->dict_id = (int)$id;
    }

    function getDictId() {
        return $this->dict_id;
    }

    protected function createResolver($name) {
        return new phpMorphy_Dict_Writer_Sql_Context_Resolver($name);
    }

    function getPartOfSpeechMap() {
        return $this->poses_map;
    }

    function getGrammemsMap() {
        return $this->grammems_map;
    }

    function getAncodesMap() {
        return $this->ancodes_map;
    }

    function getPrefixesMap() {
        return $this->prefixes_map;
    }

    function getFlexiasMap() {
        return $this->flexias_map;
    }
}

class phpMorphy_Dict_Writer_Sql extends phpMorphy_Dict_Writer_Base {
    const COMMIT_EVERY_LEMMA = 16384;
    const DUMP_EVERY_LEMMA = 1024;
    const DUMP_EVERY_FLEXIA = 1024;
    const COMMIT_EVERY_FLEXIA = 16384;

    protected
        $engine,
        $table_prefix;
    
    function __construct(PDO $pdo, $tablePrefix = '') {
        parent::__construct();

        $this->engine = phpMorphy_Dict_Writer_Sql_Engine::create(
            $pdo,
            strlen($tablePrefix) ? array($this, 'rewriteTableName_ByPrefix') : null,
            array($this, 'log')
        );

        $this->table_prefix = $tablePrefix . '_';
    }
    
    function rewriteTableName_ByPrefix($table) {
        return $this->table_prefix . $table;
    }

    protected function createContext() {
        return new phpMorphy_Dict_Writer_Sql_Context();
    }

    protected function getTablesToProcess() {
       return array(
            'grammems',
            'poses',
            'ancodes',
            'ancodes2grammems',
            'flexias',
            'prefixes',
            'lemmas'
        );
    }

    function write(phpMorphy_Dict_Source_Interface $source) {
        $source = phpMorphy_Dict_Source_Normalized_Ancodes::wrap($source);

        $context = $this->createContext();
        $tables = $this->getTablesToProcess();

        $old_time_limit = ini_get('max_execution_time');
        set_time_limit(0);
        $b = microtime(true);

        $restore_keys_stmt = new phpMorphy_Dict_Writer_Sql_StatementsBundle($this->engine);
        
        $e = null;
        try {
            $old_state = $this->engine->initState();
            
            try {
                // drop keys
                foreach($tables as $table_name) {
                   $restore_keys_stmt->prepend($this->engine->dropKeys($table_name));
                }

                $this->engine->begin();
                
                // protect transaction
                try {
                    $context->setDictId($this->createNewDict($source));
                    
                    foreach($tables as $table) {
                        $this->loadSection($table, $source, $context);
                    }
                    
                    $this->engine->commit();
                } catch (Exception $e) {
                    $this->engine->rollback();
                    throw $e;
                }
            } catch (Exception $e) { }
            /* finally */ {
               // restore keys
               if(!$restore_keys_stmt->safeExecute()) {
                   $message = "An error occured while restore keys: " . implode(', ', $restore_keys_stmt->getLastErrors());
                   
                   if(isset($e)) {
                       $message .= ' (prev. error = ' . $e->getMessage();
                   }
                   
                   throw new phpMorphy_Dict_Writer_Sql_Exception($message);
               }
                
               $this->engine->restoreState($old_state);
                
               if(isset($e)) throw $e;
            }
        } catch (Exception $e) { }
        /* finally */ {
            set_time_limit($old_time_limit);
            $this->log(sprintf("Total time taken = %0.2f", microtime(true) - $b));
            
            if(isset($e)) throw $e;
        }
        
        return $context->getDictId();
    }
    
    protected function trim($string, $length) {
        // TODO: refactor this
        static $func = null;

        if(!isset($func)) {
            if(function_exists('iconv_substr')) {
                $func = 'iconv_substr';
            } else if(function_exists('mb_substr')) {
                $func = 'mb_substr';
            } else {
                throw new phpMorphy_Dict_Writer_Sql_Exception("iconv or mb extensions required");
            }
        }
        
        return $func($string, 0, $length, 'utf-8');
    }

    protected function createNewDict(phpMorphy_Dict_Source_Interface $source) {
        $r = $this->engine->execInsert(
            'dicts',
            array(
                'name' => $this->trim($source->getName(), 64),
                'desc' => $this->trim($source->getDescription(), 255),
                'locale' => $this->trim($source->getLanguage(), 64),
            )
        );

        $result = $this->engine->getLastInsertId('dicts');

        return $result;
    }

    protected function loadSection($name, phpMorphy_Dict_Source_Normalized_Ancodes $source, $context) {
        $name = strtolower($name);
        $method_name = 'load' . ucfirst($name);
        $table_name = $name;
        $dictId = $context->getDictId();

        $this->log("$method_name...");
        
        $begin = microtime(true);

        $this->$method_name($source, $context, $dictId);

        $this->log(sprintf('Time for "%s" method = %0.2f', $method_name, microtime(true) - $begin));
    }

    protected function loadPoses(phpMorphy_Dict_Source_Normalized_Ancodes $source, $context, $dictId) {
        $stmt = $this->engine->prepareInsert('poses', array('dict_id', 'pos', 'is_predict'));
        $map = $context->getPartOfSpeechMap();

        foreach($source->getPoses() as $pos) {
            $stmt->execute(
                array(
                    $dictId,
                    $this->trim($pos->getName(), 16),
                    $pos->isPredict() ? 1 : 0
                )
            );

            $map->register($pos->getId(), $this->engine->getLastInsertId('poses'));
        }
    }

    protected function loadGrammems(phpMorphy_Dict_Source_Normalized_Ancodes $source, $context, $dictId) {
        $stmt = $this->engine->prepareInsert('grammems', array('dict_id', 'grammem'));
        $map = $context->getGrammemsMap();

        foreach($source->getGrammems() as $grammem) {
            $stmt->execute(
                array(
                    $dictId,
                    $this->trim($grammem->getName(), 16)
                )
            );

            $map->register($grammem->getId(), $this->engine->getLastInsertId('grammems'));
        }
    }

    protected function loadAncodes(phpMorphy_Dict_Source_Normalized_Ancodes $source, $context, $dictId) {
        $stmt = $this->engine->prepareInsert('ancodes', array('dict_id', 'pos_id'));
        $map = $context->getAncodesMap();
        $poses_map = $context->getPartOfSpeechMap();

        foreach($source->getAncodesNormalized() as $ancode) {
            $stmt->execute(
                array(
                    $dictId,
                    $poses_map->resolve($ancode->getPartOfSpeechId())
                )
            );

            $map->register($ancode->getId(), $this->engine->getLastInsertId('ancodes'));
        }
    }

    protected function loadAncodes2Grammems(phpMorphy_Dict_Source_Normalized_Ancodes $source, $context, $dictId) {
        $stmt = $this->engine->prepareInsert('ancodes2grammems', array('ancode_id', 'grammem_id'));
        $ancodes_map = $context->getAncodesMap();
        $grammems_map = $context->getGrammemsMap();

        foreach($source->getAncodesNormalized() as $ancode) {
            foreach($ancode->getGrammemsIds() as $grammem_id) {
                $stmt->execute(
                    array(
                        $ancodes_map->resolve($ancode->getId()),
                        $grammems_map->resolve($grammem_id)
                    )
                );
            }
        }
    }

    protected function loadFlexias(phpMorphy_Dict_Source_Normalized_Ancodes $source, $context, $dictId) {
        $fields = array('dict_id', 'flexia_model_id', 'form_no', 'suffix', 'prefix', 'ancode_id');
        $stmt = $this->engine->prepareInsert('flexias', $fields);
        $bulk = $this->engine->getBulkInserter('flexias', $fields);

        $map = $context->getFlexiasMap();
        $ancodes_map = $context->getAncodesMap();

        $total_flexias = 0;
        $total_models = 0;
        $prev_time = microtime(true);

        foreach($source->getFlexias() as $flexia_model) {
            $form_no = 0;
            $flexia_model_id = 0; // this updated latter

            foreach($flexia_model->getFlexias() as $flexia) {
                if(($total_flexias % self::DUMP_EVERY_FLEXIA) == 0) {
                    $time = microtime(true) - $prev_time;

                    $this->log(sprintf("$total_models/$total_flexias flexias done, %0.2f fps", $total_flexias / $time));
                }

                if(($total_flexias % self::COMMIT_EVERY_FLEXIA) == 0) {
                    $this->log("Flush packet of inserts");
                    $bulk->execute();
                }

                $data = array(
                    $dictId,
                    $flexia_model_id,
                    $form_no,
                    $this->trim($flexia->getSuffix(), 32),
                    $this->trim($flexia->getPrefix(), 16),
                    $ancodes_map->resolve($flexia->getAncodeId()) // TODO: source must handle ancode_id
                );


                if(!$form_no) {
                    $stmt->execute($data);

                    $flexia_model_id = (int)$this->engine->getLastInsertId('flexias');

                    // TODO: use separate sequence (emulate in mysql?)
                    $sql = 
                        'UPDATE ' . $this->engine->quoteTableName('flexias') . 
                        ' SET flexia_model_id = ' . $flexia_model_id .
                        ' WHERE id = ' . $flexia_model_id;

                    $this->engine->execute($sql, false);
                } else {
                    $bulk->add($data);
                }

                $form_no++;
                $total_flexias++;
            }

            if(!$flexia_model_id) {
                throw new phpMorphy_Dict_Writer_Sql_Exception("New flexia model without id");
            }

            $map->register($flexia_model->getId(), $flexia_model_id);
            $total_models++;
        }

        $bulk->execute();
    }

    protected function loadPrefixes(phpMorphy_Dict_Source_Normalized_Ancodes $source, $context, $dictId) {
        $stmt = $this->engine->prepareInsert('prefixes', array('dict_id', 'id', 'prefix_no', 'prefix',));
        $map = $context->getPrefixesMap();

        foreach($source->getPrefixes() as $prefix_set) {
            $no = 0;
            $prefix_set_id = $this->engine->getExplicitAutoincrementValue();

            foreach($prefix_set->getPrefixes() as $prefix) {
                $stmt->execute(
                    array(
                        $dictId,
                        $prefix_set_id,
                        $no,
                        $this->trim($prefix, 16)
                    )
                );

                if(!isset($prefix_set_id)) {
                    $prefix_set_id = $this->engine->getLastInsertId('prefixes');
                }

                $no++;
            }

            if(!isset($prefix_set_id)) {
                throw new phpMorphy_Dict_Writer_Sql_Exception("New prefix set without id");
            }

            $map->register($prefix_set->getId(), $prefix_set_id);
        }
    }

    protected function loadLemmas(phpMorphy_Dict_Source_Normalized_Ancodes $source, $context, $dictId) {
        //$stmt = $this->engine->prepareInsert('lemmas', array('dict_id', 'base_str', 'flexia_id', 'accent_id', 'prefix_id', 'common_ancode_id'));
        $inserter = $this->engine->getBulkInserter('lemmas', array('dict_id', 'base_str', 'flexia_id', 'accent_id', 'prefix_id', 'common_ancode_id'));
        $flexias_map = $context->getFlexiasMap();
        $ancodes_map = $context->getAncodesMap();
        $prefixes_map = $context->getPrefixesMap();

        $i = 0;
        foreach($source->getLemmas() as $lemma) {
            if(($i % self::COMMIT_EVERY_LEMMA) == 0) {
                $this->log("Flush packet of inserts");
                $inserter->execute();
            }

            if(($i % self::DUMP_EVERY_LEMMA) == 0) {
                $this->log("$i lemmas done");
            }

            $ancode_id = $lemma->hasAncodeId() ? $ancodes_map->resolve($lemma->getAncodeId()) : null;
            $prefix_id = $lemma->hasPrefixId() ? $prefixes_map->resolve($lemma->getPrefixId()) : null;
            $accent_id = null;
            $flexia_model_id = $flexias_map->resolve($lemma->getFlexiaId());
            $base = $this->trim($lemma->getBase(), 64);

            $inserter->add(
                array(
                    $dictId,
                    $base,
                    $flexia_model_id,
                    $accent_id,
                    $prefix_id,
                    $ancode_id
                )
            );

            $i++;
        }

        $inserter->execute();
    }
}

/*
Сделать обработку ancode в нормализации (decorator - lemma, flexia)
*110*20#
*/
