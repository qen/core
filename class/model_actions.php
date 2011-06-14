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

use \Core\Exception;
use \Core\Tools;
use \Core\Db;

class ModelActions
{
    public static $Debug = false;
    
    private static $Instance = null;
    /**
     * stores model schema etc.
     */
    private $models     = array();
    /**
     * stores module configurations
     */
    private $modules    = array();
    /**
     *
     * stores cache results
     */
    public $cached_results = array();
    
    /**
     * stores uids models modified configuration such as associations stuff
     */
    private $objects    = array();
    
    /**
     *
     * @access 
     * @var 
     */

    public function Instance()
    {
        if (is_null(self::$Instance)) {
            $klass = __CLASS__;
            self::$Instance = new $klass;
        }//end if

        return self::$Instance;
    }

    private function __construct()
    {
    }


    /**
     *
     * @access
     * @var
     */
    private function ids($model)
    {
        $fullclass  = get_class($model);
        $namespace  = explode("\\", $fullclass);
        $declared   = array_pop($namespace);
        $namespace  = implode("\\", $namespace);
        return array($fullclass, $namespace, $declared);
    }
    
    /**
     *
     * @access
     * @var
     */
    public function load(Model $obj, array $default_dbconfig = array())
    {
        $this->objects[$obj->uid] = array(
            'associations' => array()
        );
        list($class, $modulens, $name) = $this->ids($obj);
        
        if (array_key_exists($class, $this->models)) {
            return true;
        }//end if

        $model = array(
            'cacheid'   => Tools::Uuid(),
            'parent'    => ''
        );

        $this->cached_results[$class] = array();
        
        if (empty($class::$Table_Name))
            throw new Exception($class.' static::$Table_Name is empty ');
        
        /**
         * module configuration load
         */
        $module = array_pop(explode("\\", $modulens));
        $config = new \Core\Config;
        $load   = 'modules/'.$module.'/config/default.php';

        /**
         * load some default config into the module config
         */
        $config->mutate(array(
            'db' => $default_dbconfig
        ));

        /**
         * then load the actual module config
         */
        $config->import($load);

        /**
         * load environment config file
         */
        $env    = \Core\App\ENVIRONMENT;
        $load   = 'modules/'.$module.'/config/'.$env.'.php';
        $config->import($env);

        /**
         * instantiate module db
         */
        $dbconfig = $config->db();
        $db = Db::Instance($dbconfig);

        $this->modules[$modulens]['config'] = $config;
        $this->modules[$modulens]['db'] = $db;
        
        $model['schema']['self']    = $db->getSchema($class::$Table_Name, $class::$Sanitize);
        $model['schema']['parent']  = array();
        $allfields = array();
        $allfields[$class::$Table_Name] = array_keys($model['schema']['self']['fields']);

        if (empty($model['schema']['self']['pkeys'])) {
            $exc = new Exceptions("Please define a primary key index for tablle {$class::$Table_Name}");
            $exc->traceup();
            throw $exc;
        }//end if
        
        $pclass = get_parent_class($class);
        
        if ($pclass != 'Core\\Model') {
            $model['parent'] = $pclass;
            $model['schema']['parent'] = $db->getSchema($pclass::$Table_Name, $pclass::$Sanitize);
            $allfields[$pclass::$Table_Name] = array_keys($model['schema']['parent']['fields']);
            
            if (empty($model['schema']['parent']['pkeys'])) {
                $exc = new Exceptions("Please define a primary key index for tablle {$pclass::$Table_Name}");
                $exc->traceup();
                throw $exc;
            }//end if

            /**
             * modify class $Find_Options, join the parent class table
             */
            $class::$Find_Options['join'][$pclass::$Table_Name] = "`{$model['schema']['self']['table']}`.`{$model['schema']['self']['pkeys'][0]['name']}` = `{$model['schema']['parent']['table']}`.`{$model['schema']['parent']['pkeys'][0]['name']}`";
        }

        $model['schema']['fields'] = array();
        foreach ($allfields as $table => $fields) {
            foreach ($fields as $k => $field) $model['schema']['fields']["{$table}.{$field}"] = $field;
            $model['schema']['fields']["{$table}.*"] = "{$table}.*";
        }// end foreach

        $this->models[$class] = $model;
    }

    /**
     *
     * @access
     * @var
     */
    public function fieldExists(Model $obj, $field_name)
    {
        list($class) = $this->ids($obj);

        /**
         * include field_name only if its part of the $fields parameter
         */
        $skey = array_search($field_name, $this->models[$class]['schema']['fields']);
        if ($skey !== false) return true;

        $skey = array_key_exists($field_name, $this->models[$class]['schema']['fields']);
        if ($skey) return true;

        return false;
    }

    /**
     *
     * @access
     * @var
     */
    public function associate(Model $obj, Model $with, array $find = array())
    {
        list($class) = $this->ids($obj);
        
        $query  = (!empty($find['query']))? $find['query'] : $this->models[$class]['schema']['self']['pkeys'][0]['name'];
        $var    = array();
        $var['query']  = $query;

        list($class, $modulens, $name) = $this->ids($with);

        $db     = $this->modules[$modulens]['db'];
        $uid    = $with->uid;
        $schema = $this->models[$class]['schema'];
        $fkey   = $find['foreign_key'];

        unset($find['query']);
        unset($find['foreign_key']);

        $this->objects[$uid]['find'] = Tools::ArrayMerge($class::$Find_Options, $find);

        $associate = array( 'uid' => $uid );
        $associate['find']      = $find;
        $associate['fkey']      = $fkey;
        $associate['sqlquery']  = function($params, $criteria) use ($schema, $with) {
            if ($with->isEmpty()) return null;
            
            /**
             * build sql string for the existing results
             * if there is any
             */
            $searchkey = empty($params)? $schema['self']['pkeys'][0]['name'] : $params;

            $sql = '';

            if (!empty($schema['parent'])) {
                /**
                 * if there is parent schema
                 * disregard the passed searchkey, and use our the
                 * 2nd ordinal primary keys
                 */
                $searchkey = $schema['self']['pkeys'][1]['name'];

                /**
                 * inner join with parent table
                 * using the first ordinal primary keys
                 */
                $join = "
                inner join `{$schema['parent']['table']}` on
                `{$schema['self']['table']}`.`{$schema['self']['pkeys'][0]['name']}` = `{$schema['parent']['table']}`.`{$schema['parent']['pkeys'][0]['name']}`
                ";

            }//end if

            $sql = "
            select `{$schema['self']['table']}`.`{$searchkey}`
            from `{$schema['self']['table']}`
            {$join}
            {$criteria}
            ";

            return $sql;
        };//end function
        $associate['sqljoin']   = function($params) use ($schema){
            $retval = array();
            $conditions = array();

            if (empty($params))
                return $retval;

            /**
             * @TODO test this
             */
            foreach ($params as $k => $v) {
                if (!array_key_exists($k, $schema['self']['fields'])) continue;
                $conditions[] = "`{$schema['self']['table']}`.`{$k}` = {$v}";
            }// end foreach
            
            $retval[$schema['self']['table']] = implode(" AND ", $conditions);

            if (!empty($schema['parent'])) {
                $conditions = array();
                $conditions[] = "`{$schema['self']['table']}`.`{$schema['self']['pkeys'][0]['name']}` = `{$schema['parent']['table']}`.`{$schema['parent']['pkeys'][0]['name']}`";
                foreach ($params as $k => $v) {
                    if (!array_key_exists($k, $schema['parent']['fields'])) continue;
                    $conditions[] = "`{$schema['parent']['table']}`.`{$k}` = {$v}";
                }// end foreach

                $retval[$schema['parent']['table']] = implode(" AND ", $conditions);
            }//end if

            return $retval;
        };//end function
        
        $this->objects[$obj->uid]['associations'][$with::$Name] = $associate;

        $eager_find = $this->objects[$uid]['find'];
        
        $singleton  = $this;
        $buildsql   = array();
        
        if (!empty($eager_find['conditions'])) 
             $buildsql = self::BuildSearchSql($schema['fields'], $eager_find['conditions']);

        $var['eagerload'] = function($allkeys) use ($schema, $fkey, $eager_find, $buildsql, $with, $singleton) {
            if (empty($allkeys)) return false;
    
            /**
             * add conditions config
             */
            $where      = array();
            $criteria   = '';
            if (!empty($buildsql))
                $criteria = "(".implode( " and ", $buildsql).") and ";

            $where[]    = "(`{$schema['self']['table']}`.`{$fkey}` IN (".implode(', ', $allkeys)."))";
            $join       = '';

            if (!empty($schema['parent']))
                $join = "inner join `{$schema['parent']['table']}` on `{$schema['self']['table']}`.`{$schema['self']['pkeys'][0]['name']}` = `{$schema['parent']['table']}`.`{$schema['parent']['pkeys'][0]['name']}`";

            $sql = "
            select *
            from `{$schema['self']['table']}`
            {$join}
            where (".implode( " and ", $where).")
            order by `{$schema['self']['table']}`.`{$fkey}`
            ";

            $loop = $with->sqlSelect($sql);
            if (empty($loop)) return false;

            $results    = array();
            $resultkeys = array();

            /**
             * group results per foreign_key
             */
            foreach ($loop as $k => $v) {
                $idx    = $v[$fkey];
                $value  = $v;

                $results[$idx][]    = $value;
                $resultkeys[$idx][] = $value[$schema['self']['pkeys'][0]['name']];
            }//end foreach

            $eager_find['search'] = $fkey;

            foreach ($results as $query => $all) {

                $key = md5(Tools::Hash(array("{$query}", $eager_find, $schema['self'])));
                
                $value = array(
                    'results'   => $all,
                    'numpage'   => array(),
                    'count'     => count($all),
                    'sql'       => $sql,
                    'criteria'  => "where {$criteria} `{$schema['self']['table']}`.`{$fkey}` = ".Db::BindVariable("eager_{$fkey}", $query)
                );

                $singleton->cache($with, $key, $value);
                
            }// end foreach
        };//end function
        
        return $var;
    }

    /**
     *
     * @access
     * @var
     */
    public function disassociate(Model $obj, Model $with)
    {
        unset($this->objects[$obj->uid]['associations'][$with::$Name]);
    }

    /**
     *
     * @access
     * @var
     */
    public function save(Model $obj)
    {
        list($class, $modulens, $name) = $this->ids($obj);
        
        $db     = $this->modules[$modulens]['db'];
        $data   = $obj->result;
        $schema = $this->models[$class]['schema']['self'];

        if ($obj->is_parent === true)
            $schema = $this->models[$class]['schema']['parent'];
        
        $conditions = array();

        /**
         * if create is false, then do update sql
         * check for pkey value and parent pkey value if necessary
         */
        $conditions = array();
        foreach ($schema['pkeys'] as $k => $v) {
            /**
             * we require primary keys that is not set for auto increment
             */
            if ( (array_key_exists($v['name'], $data) === false) && $v['auto_increment'] === false )
                throw new Exception($class.'> save failed, ['.$v['name'].'] does not exists in data array ');

            $idx = 'ukey';
            if ($v['auto_increment'] === true)
                $idx = 'autoid';

            /**
             * add to condition if not empty
             */
            if (isset($data[$v['name']]))
                $conditions[$idx][] = "`{$schema['table']}`.`{$v['name']}` = ".$db->bind($v['name'], $data[$v['name']]);

            /**
             * clear auto increment field
             */
            if ($v['auto_increment'] === true)
                unset($data[$v['name']]);

        }// end foreach

        if (!empty($conditions)) {
            $check = array(
                'autoid'    => array( array('cnt' => 0) ),
                'ukey'      => array( array('cnt' => 0) )
            );

            /**
             * check if there is an existing record conditions
             * under the ukey and autoid conditions
             */
            if (!empty($conditions['autoid'])) {
                $sql = "select count(*) as cnt from {$schema['table']} where ".implode(' and ', $conditions['autoid']);
                $check['autoid'] = $db->query($sql);
                /**
                 * use autoid conditions if record is found
                 */
                if ($check['autoid'][0]['cnt'] != 0)
                    $conditions = $conditions['autoid'];
            }//end if

            if (!empty($conditions['ukey'])) {
                $sql = "select count(*) as cnt from {$schema['table']} where ".implode(' and ', $conditions['ukey']);
                $check['ukey'] = $db->query($sql);
                /**
                 * use ukey conditions if record is found
                 */
                if ($check['ukey'][0]['cnt'] != 0)
                    $conditions = $conditions['ukey'];
            }//end if

            /**
             * if result count on ukey and autoid conditions is 0
             * then remove conditions to force insert
             */
            if ($check['autoid'][0]['cnt'] == 0 && $check['ukey'][0]['cnt'] == 0)
                $conditions = array();

        }//end if

        /**
         * write data to db
         * Build Write SQL
         */
        $build = self::BuildWriteSql($data, $schema, $conditions);

        $db->execute($build['sql']);

        if (self::$Debug) 
            logger(array($build, $data) , "{$class} > ".__FUNCTION__, 'model_actions.log');

        if (empty($conditions)) {
            foreach ($schema['pkeys'] as $k => $v) {
                if ($v['auto_increment'] === false) continue;
                $data[$v['name']] = $db->getInsertId();
            }// end foreach
        }//end if

        /**
         * clear cache result for the class
         * since the table has been modifieid
         */
        if ($build['action'] == 'insert')
            $this->cached_results[$class] = array();

        return $data;
    }

    /**
     *
     * @param array $vars
     * @param array $schema
     * @param <type> $db
     * @param array $conditions if this is passed it will do an update
     */
    private final static function BuildWriteSql(array $vars, array $schema, array $conditions = array())
    {
        $table      = $schema['table'];
        $fieldnames = $schema['fields'];
        $values     = array();
        $columns    = array();

        if (empty($conditions)) {

            $retval["action"] = "insert";

            foreach($vars as $k=>$v) {

                if (!array_key_exists($k, $fieldnames)) continue;

                #$values[]   = (is_null($v)? 'NULL' : $db->escape($v));
                $columns[]  = "`{$k}`";

                if (is_null($v)) {
                    $values[] = 'NULL';
                    continue;
                }//end if

                $values[]   = Db::BindVariable($k, $v);

            }//end foreach

            $retval["sql"] = "INSERT INTO `{$table}` (".implode(", ", $columns).")  VALUES (".implode(", ", $values).")";

        } else {

            $retval["action"] = "update";

            foreach($vars as $k=>$v) {

                if (!array_key_exists($k, $fieldnames)) continue;

                #$values[] = "`{$k}` = ".(is_null($v)? 'NULL' : $db->escape($v));

                if (is_null($v)) {
                    $values[] = "`{$k}` = NULL";
                    continue;
                }//end if

                $values[]   = "`{$k}` = ".Db::BindVariable($k, $v);

            }//end foreach

            if (count($values) == 0) return false;

            $retval["sql"] = "UPDATE `{$table}` SET ".implode(", ", $values)."  where ".implode(" and ", $conditions);
        }//end if

        return $retval;
    }// end function


    /**
     *
     * @access
     * @var
     */
    public function remove(Model $obj)
    {
        list($class, $modulens, $name) = $this->ids($obj);

        $db     = $this->modules[$modulens]['db'];
        $data   = $obj->result;
        $schema = $this->models[$class]['schema']['self'];

        if ($obj->is_parent === true)
            $schema = $this->models[$class]['schema']['parent'];
        
        /**
         * check that the value for primary keys is not empty
         */
        $conditions = array();
        $dump       = array(
            'ukey'      => array(),
            'autoid'    => array()
        );
        foreach ($schema['pkeys'] as $k => $v) {
            /**
             * we require primary keys that is not set for auto increment
             */
            //if (empty($data[$v['name']]))
            if ((array_key_exists($v['name'], $data) === false))
                throw new Exception($this->class.'> remove failed, primarykey ['.$v['name'].'] cannot be empty ');

            $idx = 'ukey';
            if ($v['auto_increment'] === true)
                $idx = 'autoid';

            $dump[$idx][] = "`{$schema['table']}`.`{$v['name']}` = ".Db::BindVariable($v['name'], $data[$v['name']]);
        }// end foreach

        $conditions = $dump['ukey'];
        if (!empty($dump['autoid']))
            $conditions = $dump['autoid'];

        /**
         * finally call delete here
         */
        $sql = "delete from `{$schema['table']}` where ".implode(' and ', $conditions);
        $db->execute($sql);

        if (self::$Debug)
            logger(array($conditions, $sql) , "{$class} > ".__FUNCTION__, 'model_actions.log');
            
        /**
         * clear cache result for the class
         * since the table has been modifieid
         */
        $this->cached_results[$class] = array();
        
        return $debug;
    }

    /**
     *
     * @access
     * @var
     */
    public function find(Model $obj, $query = '*', array $options = array())
    {
        list($class, $modulens, $name) = $this->ids($obj);

        $db     = $this->modules[$modulens]['db'];
        $uid    = $obj->uid;
        $schema = $this->models[$class]['schema'];
        $find   = $class::$Find_Options;

        if (is_array($this->objects[$uid]['find'])) $find = $this->objects[$uid]['find'];
        
        $find = Tools::ArrayMerge($find, $options);

        if ($obj->is_parent === true) {
            $schema['self'] = $schema['parent'];
            unset($find['join'][$schema['self']['table']]);
            foreach ($schema['fields'] as $k => $v) {
                if (preg_match("/^{$schema['self']['table']}\./i", $k)) continue;
                unset($schema['fields'][$k]);
            }// end foreach
        }//end if

        if (!is_null($collectby) && !array_key_exists($collectby, $schema['self']['fields']))
            throw new Exception($this->class.'> ['.$schema['self']['table'].'.'.$collectby.'] collectby is not a valid field name');

        /**
         * process associations here
         * if the associations has results
         */
        if (!empty($this->objects[$uid]['associations']) && $obj->isEmpty()) {
            foreach ($this->objects[$uid]['associations'] as $k => $v) {
                $sql = $v['sqlquery']($v['fkey'], $this->objects[$v['uid']]['criteria'] );
                if (empty($sql)) continue;
                $find['subquery'][] = $sql;
            }// end foreach
        }//end if

        if (!empty($find['join'])) {
            
            $loop = $find['join'];
            foreach ($loop as $k => $v) {
                if (!array_key_exists($k, $this->objects[$uid]['associations'])) continue;

                /**
                 * check if $k is associated module name
                 */
                $conditions = $v['find']['conditions'];
                foreach ($conditions as $idx => $val) $conditions[$idx] = Db::BindVariable($idx, $val);

                $conditions[$v['fkey']] = "`{$schema['self']['table']}`.`{$schema['self']['pkeys'][0]['name']}`";
                $jointable = $v['sqljoin']($conditions);
                unset($find['join'][$k]);

                if (!empty($v)) $v = "AND {$v}";
                foreach ($jointable as $table => $criteria)
                    $find['join'][$table] = "{$criteria} {$v}";

            }// end foreach

        }//end if

        /**
         * check if cache exists
         */
        $cachekey = md5(Tools::Hash(array("{$query}", $find, $schema['self'])));
        
        if (array_key_exists($cachekey, $this->cached_results["{$class}"])) {
            $retval = $this->cached_results[$class][$cachekey];
            $retval['cached'] = true;
            /**
             * restore the last select criteria
             */
            $this->objects[$uid]['criteria'] = $retval['criteria'];
            return $retval;
        }//end if

        if ( $obj->is_parent === false && empty($find['columns']) ) {
            $find['columns'][] = "{$this->models[$class]['schema']['parent']['table']}.*";
            $find['columns'][] = "{$this->models[$class]['schema']['self']['table']}.*";
        }//end if

        /**
         * call search here
         */
        $empty = true;
        
        if (empty($find['custom_fields']))
            $find['custom_fields'] = $obj::$Custom_Fields;

        try {
            $retval = self::Search($query, $schema, $db, $find);
            $empty = false;
        } catch (Exception $exc) {
            $empty = true;
            $exc = new Exception("{$class}> ".$exc->getMessage());
            $exc->traceup();
            $exc->traceup();
            throw $exc;
        }//end try

        /**
         * store the last select criteria
         */
        $this->objects[$uid]['criteria'] = $retval['criteria'];
        $retval['schema'] = $schema['self'];

        /**
         * cache results
         */
        $this->cached_results[$class][$cachekey] = $retval;
        $retval['cached'] = false;

        if (self::$Debug)
            logger($retval, "{$class} > ".__FUNCTION__, 'model_actions.log');

        return $retval;
    }

    /**
     *
     * @access
     * @var
     */
    private final static function Search($query, $schema, $db, array $options = array())
    {
        $find_options = array(
            'selpage'       => 1, // set to 0 to remove limit
            'limit'         => 20, // set to 0 to remove limit
            'search'        => "",
            'orderby'       => "",
            'groupby'       => "",
            'where'         => array(), // default search criteria
            'conditions'    => array(), // additional search criteria
            'subquery'      => array(),
            'count'         => false,
            'columns'       => array(),
            'join'          => array(),
            'custom_fields' => array()
        );

		extract($find_options, EXTR_SKIP);

        $options = Tools::ArrayMerge($find_options,  $options);

        /**
         * just in case some1 gets smart,
         * protect these variables
         */
        unset($options['find_options']);
        unset($options['query']);
        unset($options['options']);
        unset($options['schema']);
        unset($options['db']);

        extract($options, EXTR_IF_EXISTS);

        $sql = array();

        /**
         * default search is primarykey
         */
        if (empty($search))
            $search = $schema['self']['pkeys'][0]['name'];

		if (!is_numeric($selpage))
            $selpage = 1;

		if (!empty($query) && $query != '*')
            $where["{$search}"] = $query;

		$match  = array();
		$values = array();
        $cmatch = array(); // custom field search 

        $find = Tools::ArrayMerge($conditions, $where);
        $table_fields = $schema['fields'];

        if (count($find) != 0)
            $match = self::BuildSearchSql($table_fields, $find, $custom_fields);
        
		if (count($subquery) != 0) {
			foreach($subquery as $k=>$v) {
				if (!is_string($v)) continue;
				$match[] = "({$schema['self']['pkeys'][0]['name']} IN ({$v}))";
			}//end foreach
		}//end if

        $where_conditions = '';
		if (count($match) != 0)
			$where_conditions = "where (".implode( " and ", $match).") ";

        /**
         * insert join table code here
         * @TODO support for left and right join
         */
        $jointable = '';
        if (!empty($join)) {
            foreach ($join as $k => $v) {
                $jointable .= "inner join {$k} on {$v} ";
            }// end foreach
        }//end if

        /**
         * create count sql
         */
		$sql['count'] = "select {$schema['self']['pkeys'][0]['name']} from {$schema['self']['table']} {$jointable} {$where_conditions}";

		$cnt = $db->queryCount($sql['count']);
		if ($cnt === false)
			throw new Exception("Query count failed");

		if ($count === true)
			return $cnt;

        if ($cnt == 0)
            throw new Exception($schema['self']['table'].' [ '.$sql['count'].' ] results empty');

        /**
         * page numbering here
         */
		$limitclause = "";
		if ($selpage > 0 && $limit > 0) {
			$numpage        = Tools::Numpage($cnt, $selpage, $limit);
			$limitclause    = "limit {$numpage['rows']['offset']}, {$numpage['rows']['limit']}";
		}//end if

        if (!empty($orderby)){
            if (is_array($orderby)) $orderby = implode(', ', $orderby);
            $orderby = "order by {$orderby}";
        }//end if

        if (!empty($groupby)){
            if (is_array($groupby)) $groupby = implode(', ', $groupby);
            $groupby = "group by {$groupby}";
        }//end if
        
        /**
         * column selection
         */
        $cols = array("{$schema['self']['table']}.*");
        
        if (!empty($columns)) {
            $cols = array();
            
            if (!is_array($columns)) $columns = explode(',', $columns);
            
            foreach ($columns as $k => $v) {
                $v = trim($v);
                if (!array_key_exists($v, $schema['fields']) && !in_array($v, $schema['fields'])) continue;
                $cols[] = $v;
            }// end foreach

        }//end if

        if (!empty($custom_fields)) {
            foreach ($custom_fields as $k => $v)
                $cols[] = "{$v} as {$k}";
        }//end if
        
        $cols = implode(", ", $cols);

		$sql['select'] = "
        select {$cols}
		from {$schema['self']['table']}
        {$jointable}
		{$where_conditions}
		{$orderby}
        {$groupby}
		{$limitclause}
        ";

		$results = $db->query($sql['select']);

		if ($results === false) return false;

        $retval = array(
            'results'   => $results,
            'numpage'   => $numpage,
            'count'     => $cnt,
            'sql'       => $sql,
            'criteria'  => $where_conditions
        );
        return $retval;
    }

    private final static function BuildSearchSql(array $fields, array $find, array $custom_fields = array())
    {
        /**
         * $fields is expected to be on the form
         * array(
         *   'talbename.fieldname' => 'fieldname'
         * )
         */
        $where  = array();
        $values = array();

        if (count($find)==0) return array($where, $values);

        $idx = 0;
        foreach($find as $k => $v){
            $condition  = '=';
            $field_name = substr($k,1);
            /**
             * check first character for condition flag
             */
            switch(true){
                case ($k{0} == "%"): $condition = 'like';       break;
                case ($k{0} == "^"): $condition = 'not like';   break;
                case ($k{0} == "!"): $condition = "!=";         break;
                case ($k{0} == ">"): $condition = ">=";         break;
                case ($k{0} == "<"): $condition = "<=";         break;
                case ($k{0} == "~"): $condition = "REGEXP";     break;
                case ($k{0} == "&"): $condition = "&";          break;
                default:
                    $field_name = $k;
                    break;
            }//end switch

            /**
             * include field_name only if its part of the $fields parameter
             */
            $vname  = '';
            $skey   = array_search($field_name, $fields);
            if ($skey !== false) {
                list($table, $field_name) = explode('.', $skey);
                $vname = "`{$table}`.`{$field_name}`";
            }//end if

            /**
             * check if field_name exists on custom_fields
             */
            if (empty($vname)) {
                $skey = array_key_exists($field_name, $custom_fields);
                if ($skey) {
                    $vname = "{$custom_fields[$field_name]}";
                    $table = 'customfield';
                }//end if
            }//end if

            if (empty($vname)) {
                $skey = array_key_exists($field_name, $fields);
                if ($skey) {
                    list($table, $field_name) = explode('.', $field_name);
                    $vname = "`{$table}`.`{$field_name}`";
                }//end if
            }//end if

            /**
             * still variable name check is empty?
             */
            if (empty($vname)) continue;

            /**
             * check if value is array
             */
            if (is_array($v)) {
                $condition = ($condition == '!=') ? 'NOT IN': 'IN';
                foreach($v as $fkey=>$fval){
                    if (preg_match("|^select|is", $fval))
                        $v[$fkey] = $fval;
                    else
                        $v[$fkey] = Db::BindVariable("{$table}_{$field_name}_{$fkey}", $fval);
                }//end foreach

                $where[] = " ( {$vname} {$condition} (".implode(", ", $v).") ) ";
                continue;
            }//end if

            /**
             * check if value is null
             */
            if (is_null($v)) {
                $condition  = ( $condition == '!=' ) ? 'IS NOT': 'IS';
                $where[]    = " ( {$vname} {$condition}  NULL) ";
                continue;
            }//end if


            /**
             * check value if there || exists
             */
            $split_or   = explode('||', $v);
            $split_and  = explode('&&', $v);

            $split      = $split_or;
            $split_cond = "OR";

            /**
             * use split_and if split_or is exactly 1
             */
            if (count($split_or) == 1) {
                $split      = $split_and;
                $split_cond = "AND";
            }//end if


            $split_values = array();
            foreach($split as $x => $y){
                $idx++;
                switch($condition){
                    case '&':
                        /**
                         * if condition is bit dont go through the
                         * apply raw value this is to allow ~ NOT bit condition
                         * ie (4 & ~2)
                         *
                         */

                        if ($y{0} == '~') // check if NOT condition
                            $split_values[] = "~{$vname} {$condition} ".substr($y,1);
                        else
                            $split_values[] = "{$vname} {$condition} {$y}";

                        continue 2;
                        break;

                    case '>=':
                    case '<=':
                    case '=':

                        /**
                         * check if first characteer is @
                         * this means raw condition value
                         *
                         * so that you can have an expression conditional value
                         */
                        if ($y{0} == '@') {
                            $y = substr($y,1);
                            $split_values[] = "{$vname} {$condition} {$y}";
                            continue 2;
                            break;
                        }//end if

                        break;

                    default:
                        break;
                }//end switch

                $bind_name      = Db::BindVariable($field_name, $y);
                $split_values[] = "{$vname} {$condition} {$bind_name}";

            }//end foreach

            $where[]= " ((".implode(") {$split_cond} (", $split_values).")) ";

        }//end foreach

        return $where;
    }// end function

    /**
     *
     * @access
     * @var
     */
    public function clear(Model $obj)
    {
        list($class, $modulens, $name) = $this->ids($obj);
        $this->cached_results[$class] = array();
    }

    /**
     *
     * @access
     * @var
     */
    public function cache(Model $obj, $key, $cache)
    {
        list($class, $modulens, $name) = $this->ids($obj);
        $this->cached_results[$class][$key] = $cache;
    }

    /**
     *
     * @access
     * @var
     */
    public function db(Model $obj)
    {
        list($class, $modulens, $name) = $this->ids($obj);
        return $this->modules[$modulens]['db'];
    }

    /**
     *
     * @access
     * @var
     */
    public function config(Model $obj)
    {
        list($class, $modulens, $name) = $this->ids($obj);
        return $this->modules[$modulens]['config'];
    }

    /**
     *
     * @access
     * @var
     */
    public function sanitize(Model $obj, array $raw)
    {
        list($class, $modulens, $name) = $this->ids($obj);
        $schema = $this->models[$class]['schema']['self'];

        $raw = Tools::Sanitize($raw, $schema['fields']);

        return $raw;
    }

    /**
     *
     * @access
     * @var
     */
    public function validate(Model $obj, array $validations)
    {
        list($class, $modulens, $name) = $this->ids($obj);
        $schema = $this->models[$class]['schema']['self'];
        
        if ($obj->is_parent === true)
            $schema = $this->models[$class]['schema']['parent'];

        /**
         * automatically include primary keys that is not
         * for autoincrement as required if not included
         */
        foreach ($schema['pkeys'] as $k => $v) {

            /**
             * we require primary keys that is not set for auto increment
             */
            if ($v['auto_increment'] === true) continue;

            if (!empty($validations['required'][$v['name']])) continue;

            $validations['required'][$v['name']] = "{$v['name']} is required";

        }// end foreach

        Tools::Validate($validations, $obj->result);
    }

    /**
     *
     * @access
     * @var
     */
    public function getSchema(Model $obj)
    {
        list($class, $modulens, $name) = $this->ids($obj);
        $schema = $this->models[$class]['schema']['self'];
        return $schema;
    }

    /**
     *
     * @access
     * @var
     */
    public function pkey(Model $obj)
    {
        list($class, $modulens, $name) = $this->ids($obj);
        return $this->models[$class]['schema']['self']['pkeys'][0]['name'];
    }
}

