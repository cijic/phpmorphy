<?php
abstract class phpMorphy_Dict_Writer_Sql_Schema {
    protected
        $engine,
        $table,
        $quoted_table;

    function __construct(phpMorphy_Dict_Writer_Sql_Engine $engine, $table) {
        $this->engine = $engine;
        $this->table = $table;
        $this->quoted_table = $this->engine->quoteTableName($table);
    }

    abstract function dropIndex();
    abstract function dropConstraints();
    abstract function dropForeignKeys();

    protected function createBundle() {
        return $this->engine->createStatementsBundle();
    }
    
    protected function executeDrop($dropBundle, $restoreBundle) {
        try {
            $dropBundle->execute();
        } catch (Exception $e) {
            if(!$restoreBundle->safeExecute()) {
                throw new phpMorphy_Dict_Writer_Sql_Exception(
                    "An error occured while restoring keys: " . implode(', ', $restoreBundle->getLastErrors())
                );
            }
            
            throw $e;
        }
    }
}

class phpMorphy_Dict_Writer_Sql_StatementsBundle {
    protected
        $statements,
        $engine,
        $exceptions = array();

    function __construct(phpMorphy_Dict_Writer_Sql_Engine $engine) {
        $this->engine = $engine;

        $this->reset();
    }

    function reset() {
        $this->statements = array();
    }

    function prepend($sql) {
        array_unshift($this->statements, $sql);
    }
    
    function append($sql) {
        $this->statements[] = $sql;
    }

    function getLastErrors() {
        return $this->exceptions;
    }
    
    function execute() {
        return $this->doExecute(false);
    }
    
    function safeExecute() {
        return $this->doExecute(true);
    }
    
    protected function doExecute($supressExceptions = false) {
        $this->exceptions = array();
        
        foreach($this->statements as $sql) {
            $is_nested_sql = $sql instanceof phpMorphy_Dict_Writer_Sql_StatementsBundle;
            try {
                if($is_nested_sql) {
                    if($supressExceptions) {
                        if(!$sql->safeExecute()) {
                            $message = 'An error occured while executing bundle: ' . implode(', ', $sql->getLastErrors());

                            throw new phpMorphy_Dict_Writer_Sql_Exception($message);
                        }
                    } else {
                        $sql->execute();
                    }
                } else {
                    $this->engine->execute((string)$sql);
                }
            } catch (Exception $e) {
                if(!$supressExceptions) {
                    throw $e;
                } else {
                    $message = 'while execute ';

                    if($is_nested_sql) {
                        $message .= 'sql bundle';
                    } else {
                        $message .= '"' . $sql . '"';
                    }

                    $message .= ' error: "' . $e->getMessage() . '"';

                    $this->exceptions[] = new phpMorphy_Dict_Writer_Sql_Exception($message);
                }
            }
        }
        
        return count($this->exceptions) < 1;
    }
}

abstract class phpMorphy_Dict_Writer_Sql_Engine {
    protected
        $pdo,
        $table_name_rewriter,
        $logger;

    static function create(PDO $pdo, $tableNameRewriter = null, $logger = null) {
        $driver_name = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

        list($class, $file) = self::getClassAndFileFromDriverString($driver_name);

        if(!is_readable($file) || !is_file($file)) {
            throw new phpMorphy_Dict_Writer_Sql_Exception("Can`t get access to '$file' file, for $driver_name driver");
        }

        require_once($file);

        if(!class_exists($class, true)) {
            throw new phpMorphy_Dict_Writer_Sql_Exception("Class '$class' not defined in '$file' file, for $driver_name $driver");
        }

        return new $class($pdo, $tableNameRewriter, $logger);
    }

    protected static function getClassAndFileFromDriverString($string) {
        $lower_string = strtolower($string);

        return array(
            'phpMorphy_Dict_Writer_Sql_Engine_' . ucfirst($lower_string),
            dirname(__FILE__) . '/' . $lower_string . '.php'
        );
    }

    protected function __construct(PDO $pdo, $tableNameRewriter, $logger) {
        $this->pdo = $pdo;
        $this->table_name_rewriter = $tableNameRewriter;
        $this->logger = $logger;
    }

    protected function setUtf8Encoding() {
        $this->execute("SET NAMES " . $this->pdo->quote('utf8'));
    }
    function begin() {
        $this->pdo->beginTransaction();
    }

    function commit() {
        $this->pdo->commit();
    }

    function rollback() {
        $this->pdo->rollback();
    }

    function prepareInsert($table, $columns) {
        $columns = array_map(array($this, 'quoteIdentifier'), $columns);
        $values = implode(', ', array_fill(0, count($columns), '?'));

        $sql = 'INSERT INTO ' . $this->quoteTableName($table) . '(' . implode(', ', $columns) . ') VALUES (' . $values . ')';

        $this->log($sql);
        return $this->pdo->prepare($sql);
    }

    function execInsert($table, $values) {
        $stmt = $this->prepareInsert($table, array_keys($values));
        if(!$stmt->execute(array_values($values))) {
            throw new phpMorphy_Dict_Writer_Sql_Exception('Can`t exec insert sql'); 
        }
    }

    function getLastInsertId($table) {
        return $this->pdo->lastInsertId($this->getTableName($table));
    }

    function getCell($query, $idx = 0, $logQuery = true) {
        if($logQuery) {
            $this->log($query);
        }
        
        return $this->pdo->query($query, PDO::FETCH_COLUMN, $idx)->fetchColumn($idx);
    }

    protected function initPdoState($values) {
        $result = array();
        
        foreach($values as $name => $value) {
            $result[$name] = $this->pdo->getAttribute($name);
            $this->pdo->setAttribute($name, $value);
        }
        
        return $result;
    }
    
    protected function restorePdoState($state) {
        foreach($state as $name => $value) {
            $this->pdo->setAttribute($name, $value);
        }
    }
    
    function initState() {
        $old_pdo = $this->initPdoState(
            array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_ORACLE_NULLS => PDO::NULL_NATURAL,
                PDO::ATTR_AUTOCOMMIT => false,
                PDO::ATTR_CASE => PDO::CASE_LOWER,
            )
        );

        $old_encoding = $this->setUtf8Encoding();
        
        return array(
            'pdo' => $old_pdo,
            'encoding' => $old_encoding
        );
    }

    function restoreState($state) {
        $this->restoreOldEncoding($state['encoding']);
        $this->restorePdoState($state['pdo']);
    }
    
    function dropKeys($tableName) {
        $schema = $this->getSchema($tableName);
        $stmt = new phpMorphy_Dict_Writer_Sql_StatementsBundle($this);
        
        $stmt->prepend($schema->dropForeignKeys());
        $stmt->prepend($schema->dropConstraints());
        $stmt->prepend($schema->dropIndex());
        
        return $stmt;
    }

    function quote($value) {
        if(!isset($value)) {
            return 'NULL';
        }

        if(is_int($value)) {
            return $value;
        }

        return $this->pdo->quote((string)$value);
    }

    protected function getTableName($table) {
        if(isset($this->table_name_rewriter)) {
            return call_user_func($this->table_name_rewriter, $table);
        } else {
            return $table;
        }
    }

    function quoteTableName($table) {
        return $this->quoteIdentifier($this->getTableName($table));
    }

    function execute($sql, $logQuery = true) {
        if($logQuery) {
            $this->log($sql);
        }

        $this->pdo->exec($sql);
    }

    function query($sql, $logQuery = true) {
        if($logQuery) {
            $this->log($sql);
        }

        return $this->pdo->query($sql, PDO::FETCH_ASSOC);
    }

    protected function log($text) {
        if($this->logger) {
            call_user_func($this->logger, $text);
        }    
    }
    
    function createStatementsBundle() {
        return new phpMorphy_Dict_Writer_Sql_StatementsBundle($this);
    }

    abstract protected function restoreOldEncoding($encoding);
    abstract function getExplicitAutoincrementValue();
    abstract function quoteIdentifier($name);
    abstract function getBulkInserter($table, $columns);
    abstract function getSchema($table);    
}

abstract class phpMorphy_Dict_Writer_Sql_BulkInserter {
    protected
        $engine,
        $table,
        $columns,
        $statement;

    function __construct(phpMorphy_Dict_Writer_Sql_Engine $engine, $table, $columns) {
        $this->engine = $engine;
        $this->table = (string)$table;
        $this->columns = (array)$columns;
    }

    abstract function add($values);
    abstract function execute();
}
