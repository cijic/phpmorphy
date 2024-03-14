<?php
class phpMorphy_Dict_Writer_Sql_Schema_Mysql extends phpMorphy_Dict_Writer_Sql_Schema {
    function dropIndex() {
        $return_bundle = $this->createBundle();
        $drop_bundle = $this->createBundle();
        $quoted_table = $this->quoted_table;

        $indices = array();
        $stmt = $this->engine->query("SHOW INDEX FROM $quoted_table");

        foreach($stmt as $row) {
            if($row['key_name'] === 'PRIMARY') continue; // skip primary key definition

            $key_name = $row['key_name'];
            $indices[$key_name]['columns'][(int)$row['seq_in_index'] - 1] = $row['column_name'];
            $indices[$key_name]['unique'] = $row['non_unique'] == '0';
            $indices[$key_name]['type'] = $row['index_type'];
        }
        
        $stmt->closeCursor();

        foreach($indices as $key_name => $data) {
            ksort($data['columns']);

            $drop_bundle->append("ALTER TABLE $quoted_table DROP INDEX " . $this->engine->quoteIdentifier($key_name));
            $return_bundle->append($this->getCreateIndexStatement($key_name, $data['columns'], $data['type'], $data['unique']));
        }
        
        $this->executeDrop($drop_bundle, $return_bundle); // drop indices

        return $return_bundle;
    }
    
    function dropConstraints() {
        // mysql support only unique constraints (drop in dropIndex()) and FK`s (drop in dropForeignKeys())
        return $this->createBundle();
    }
    
    function dropForeignKeys() {
        $return_bundle = $this->createBundle();
        $drop_bundle = $this->createBundle();
        $alter_table = 'ALTER TABLE ' . $this->quoted_table;
        
        $sql = $this->engine->getCell("SHOW CREATE TABLE " . $this->quoted_table, 1);
        
        $lines = array_filter(array_map('trim', explode("\n", $sql)), 'strlen');
        foreach($lines as $line) {
            $line = trim($line, ',');

            // TODO: test more this regexp
            if(preg_match('~^CONSTRAINT\s+`([^`]+)`~', $line, $matches)) {
                $name = $matches[1];
                
                $drop_bundle->append("$alter_table DROP FOREIGN KEY " . $this->engine->quoteIdentifier($name));
                $return_bundle->append("$alter_table ADD $line");
            }
        }
        
        $this->executeDrop($drop_bundle, $return_bundle); // drop FK`s
        
        return $return_bundle;
    }

    protected function getCreateIndexStatement($keyName, $columns, $type, $isUnique) {
        $index_name = $isUnique ? 'CONSTRAINT UNIQUE' : 'INDEX';

        return 
            'ALTER TABLE ' . $this->quoted_table . " ADD $index_name " . $this->engine->quoteIdentifier($keyName) . ' ' .
            'USING ' . $type . ' ' .
            '(' . implode(', ', array_map(array($this->engine, 'quoteIdentifier'), $columns)) . ')';
    }
}

class phpMorphy_Dict_Writer_Sql_Engine_Mysql extends phpMorphy_Dict_Writer_Sql_Engine {
    protected function setServerVariable_Statement($name, $value) {
        return 'SET ' . $this->quoteIdentifier($name) . ' = ' . $this->quote($value);
    }

    protected function setServerVariable($name, $value) {
        $this->execute($this->setServerVariable_Statement($name, $value));
    }
    
    protected function getServerVariables($names) {
        $result = array();
        
        foreach((array)$names as $name) {
            $result[$name] = $this->getCell('SELECT @@' . $this->quoteIdentifier($name));
        }
        
        return $result;
    }

    protected function setUtf8Encoding() {
        $state = $this->getServerVariables(
            array(
                'character_set_client',
                'character_set_results',
                'character_set_connection'
            )
        );

        parent::setUtf8Encoding();
        
        return $state;
    }

    protected function restoreOldEncoding($encoding) {
        foreach($encoding as $name => $value) {
            $this->setServerVariable($name, $value);
        }
    }

    function dropKeys($tableName) {
        //return parent::dropKeys($tableName);

        $return_bundle = $this->createStatementsBundle();
        $bundle = $this->createStatementsBundle();
        foreach($this->getServerVariables(array('unique_checks', 'foreign_key_checks')) as $name => $value) {
            $bundle->append($this->setServerVariable_Statement($name, 0));

            $return_bundle->prepend($this->setServerVariable_Statement($name, (int)$value));
        }

        $alter_statement_format = 'ALTER TABLE ' . $this->quoteIdentifier($tableName) . ' %s KEYS';

        $bundle->append(sprintf($alter_statement_format, 'DISABLE'));
        $return_bundle->prepend(sprintf($alter_statement_format, 'ENABLE'));

        try {
            $bundle->execute();
        } catch (Exception $e) {
            if(!$return_bundle->safeExecute()) {
                throw new phpMorphy_Dict_Writer_Sql_Exception(
                    "An error occured while restore old state of mysql variables: " . implode(', ', $return_bundle->getLastErrors())
                );
            }

            throw $e;
        }

        return $return_bundle;
    }

    function getExplicitAutoincrementValue() {
        return null;
    }

    function quoteIdentifier($name) {
        return "`$name`";
    }

    function getBulkInserter($table, $columns) {
        return new phpMorphy_Dict_Writer_Sql_BulkInserter_Mysql($this, $table, $columns);
    }

    function getSchema($table) {
        return new phpMorphy_Dict_Writer_Sql_Schema_Mysql($this, $table);
    }
}

class phpMorphy_Dict_Writer_Sql_BulkInserter_Mysql extends phpMorphy_Dict_Writer_Sql_BulkInserter {
    protected
        $statement;

    function __construct(phpMorphy_Dict_Writer_Sql_Engine $engine, $table, $columns) {
        parent::__construct($engine, $table, $columns);

        $this->resetStatement();
    }

    protected function resetStatement() {
        $this->statement = null;
    }

    function add($values) {
        if(!isset($this->statement)) {
            $this->statement = $this->getInsertStatementBegin();
        } else {
            $this->statement .= ', ';
        }

        $this->statement .= 
            '(' .
            implode(
                ', ',
                array_map(array($this->engine, 'quote'), $values)
            ) .
            ')';

        $this->count++;
    }

    function execute() {
        if(isset($this->statement)) {
            $this->engine->execute($this->statement, false);
        }

        $this->resetStatement();
    }

    protected function getInsertStatementBegin() {
        $columns = array_map(array($this->engine, 'quoteIdentifier'), $this->columns);

        return 'INSERT INTO ' . $this->engine->quoteTableName($this->table) . '(' . implode(', ', $columns) . ') VALUES ';
    }
}
