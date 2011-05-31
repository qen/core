<?php
/**
 * Project: PHP CORE Framework
 *
 * This file is part of PHP CORE Framework.
 *
 * PHP CORE Framework is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 *
 * PHP CORE Framework is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PHP CORE Framework.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @version v0.05.18b
 * @copyright 2010-2011
 * @author Qen Empaces,
 * @email qen.empaces@gmail.com
 * @date 2011.05.30
 *
 */
namespace Core;
use Core\Debug;
use Core\Base;
use Core\App;
use Core\App\Config as AppConfig;
use Core\Exception;
use \PDO;

/**
 *
 * @author
 *
 */
class Db
{
    public static $Debug = true;
    /**
     *
     */
    public $sql_query   = "";
    public $sql_execute = "";

    /**
     *
     */
    private $rst        = array();
    private $cnn        = null;
    private $schemas    = array();
    private $dsn        = '';
    private $config     = array();
    
    private static $instance = array();
    private static $bindvars = array();

    public static $Stats = array();

    /**
     *
     */
    function __construct($dbconfig) {
        $this->dsn      = $dbconfig['dsn'];
        $this->config   = $dbconfig;
        
        $idx = md5($dsn);

        if (isset(self::$instance[$idx]))
            throw new Exception("Db instance alreay exists, {$idx}");

    }// end function

    /**
     *
     *
     */
    public static function Instance($dbconfig = array()) {

        if (empty($dbconfig))
            $dbconfig = AppConfig::Db();

        $dsn = $dbconfig['dsn'];
        $usr = $dbconfig['usr'];
        $pwd = $dbconfig['pwd'];
        $idx = md5($dsn);
        
        if (!isset(self::$instance[$idx])) {
            $c = __CLASS__;
            self::$instance[$idx] = new $c($dbconfig);
        }// end if

        self::$instance[$idx]->connect($dsn, $usr, $pwd);

        return self::$instance[$idx];
    }// end function


    /**
     * open function
     *
     */
    final function connect($dsn, $username = "", $password = "") {

        $this->cnn = new PDO($dsn, $username, $password);

        if (!($this->cnn instanceof PDO))
            throw new Exception("I think db connection failed OR! PDO object failed to instaniate.");

        if ($this->cnn->getAttribute(PDO::ATTR_DRIVER_NAME) == 'mysql')
            $this->cnn->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);

        return true;
    }// end function

    /**
     *
     */
    public final function close(){
        unset($this->cnn);
        $this->cnn = null;
    }// end function

    /**
     *
     * @access
     * @var
     */
    public function bind($name, $value)
    {
        return static::BindVariable($name, $value);
    }

    /**
     *
     * @access
     * @var
     */
    public function BindVariable($name, $value)
    {
        $key = array_search($value, self::$bindvars, true);

        if ($key === false) {
            $name   = str_replace('.', '_', $name);
            $key    = ":{$name}_" . substr(md5(time().$value.count(self::$bindvars).$name), 0, 18);
            self::$bindvars[$key] = $value;
        }//end if

        return $key;
    }

    /**
     *
     */
    public final function query($sql, array $args = array()){
        $this->sql_query     = $sql;
        self::$Stats['query'] += 1;
        /**
         * prepare sql statement here
         */
        $psql = trim($sql);
        $idx  = md5($psql);
        if (!array_key_exists($idx, $this->rst)) {
            $this->rst[$idx] = $this->cnn->prepare(
                $psql,
                array(
                    PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY,
                )
            );
        }//end if

        $params = array();
        foreach (self::$bindvars as $k => $v) {
            if (false === strpos($sql, $k)) continue;
            $params[$k] = $v;
        }// end foreach

        if (empty($params) && !empty($args)) 
            $params = $args;

        /**
         * execute sql statement here
         */
        if (self::$Debug) App\logger(array($psql, $params) , __CLASS__);
        
        $check = $this->rst[$idx]->execute($params);

        if ($check === false) {
            $qerrors = $this->rst[$idx]->errorInfo();
            $qerrors[] = "Failed to execute sql [{$sql}]";
            $qerrors[] = var_export(self::$bindvars, true);
            throw new Exception($qerrors);
        }//end if

        /**
         * return all results
         */
        $retval = $this->rst[$idx]->fetchAll(PDO::FETCH_ASSOC);

        return $retval;
    }// end function

    /**
     *
     */
    public final function queryCount($query){
        list($dump, $from) = explode("from", strtolower($query), 2);
        self::$Stats['queryCount'] += 1;

        $sql = "select count(*) as cnt from {$from}";
        
        if (preg_match("/group by/is", $query))
            $sql = "select count(*) as cnt from ({$query}) as counted";

        $retval = $this->query($sql);
        if ($retval === false) return false;

        return $retval[0]['cnt'];
    }// end function 

    /**
     *
     */
    public function execute($sql, array $args = array()) {
        $this->sql_execute = $sql;
        self::$Stats['execute'] += 1;

        $params = array();
        foreach (self::$bindvars as $k => $v) {
            if (false === strpos($sql, $k)) continue;
            $params[$k] = $v;
        }// end foreach

        if (empty($params) && !empty($args))
            $params = $args;

        $psql = trim($sql);
        $idx  = md5($psql);
        if (!array_key_exists($idx, $this->rst)) {
            $this->rst[$idx] = $this->cnn->prepare($psql);
        }//end if

        if (self::$Debug) App\logger(array($psql, $params), __CLASS__);
        
        #$count = $this->cnn->exec($sql);
        $result = $this->rst[$idx]->execute($params);
        if ($result === false) {
            $dump       = $this->cnn->errorInfo();
            $message    = "{$dump[0]} [{$dump[1]}] {$dump[2]} \n\r {$this->sql_execute} {$count}";
            throw new Exception($message);
        }//end if

        return $this->rst[$idx]->rowCount();
    }// end function 

    /**
     * quote function
     *
     */
    public function escape($string){
        return $this->cnn->quote($string);
    }// end function quote 

    /**
     *
     */
    public function getInsertId() {
        return $this->cnn->lastInsertId();
    }// end function

    /**
     *
     * @access
     * @var
     * @todo add schema configuration lookup
     * to reduce the number of sql query, ideal for production environment
     */
    public function getSchema($table, $sanitize = array())
    {
        self::$Stats['getSchema'] += 1;
        
        /**
         * check if table schemas is defined in the config
         */
        if (!empty($this->config['schemas'][$table]))
            return $this->config['schemas'][$table];
        
        if (!empty($this->schemas[$table]))
            return $this->schemas[$table];

        $field_types = array(
            'numeric'   => '/(int|float|real|decimal|double)/is',
            'char'      => '/(char)/is', # this include varchar as well
            'text'      => '/(text)/is',
            'bit'       => '/(bit)/is',
            'datetime'  => '/(date|time|datetime)/is',
        );
        
        $sql        = "SHOW FIELDS FROM `{$table}`";
        $results    = $this->query($sql);
        $fields     = array();
        foreach ($results as $k => $v) {
            $v['auto_increment']    = ($v['Extra'] == 'auto_increment'? true: false);

            if (empty($sanitize[$v['Field']])) {
                $sanitize[$v['Field']]  = 'raw';
                foreach ($field_types as $type => $regex) {
                    if (preg_match($regex, $v['Type'])) {
                        if ($type == 'char')
                            $type = $v['Type'];

                        $sanitize[$v['Field']] = $type;
                        break;
                    }//end if
                }// end foreach
            }//end if

            $fields[$v['Field']] = $v;
        }// end foreach
        
        $sql        = "SHOW KEYS FROM `{$table}` WHERE Key_name = 'PRIMARY'";
        $results    = $this->query($sql);
        if (empty($results))
            throw new Exception("{$table} doesn't have primary key / index defined");
        
        $pkeys      = array();
        foreach ($results as $k => $v) {
            $pkeys[] = array(
                'name'              => $v['Column_name'],
                'auto_increment'    => $fields[$v['Column_name']]['auto_increment']
            );
        }// end foreach

        $this->schemas[$table] = array(
            'table'     => $table,
            'fields'    => $sanitize,
            'pkeys'     => $pkeys,
        );
        
        if (self::$Debug) App\logger($this->schemas[$table], __CLASS__);

        return $this->schemas[$table];
    }

}