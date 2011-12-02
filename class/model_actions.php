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
     *
     * @access 
     * @var 
     */

    public static function Instance()
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

        list($class, $modulens, $name) = $this->ids($obj);
        
        if (array_key_exists($class, $this->models)) {
            return true;
        }//end if

        /**
         * determine model name
         */
        $obj::$Name = ucfirst($obj::$Name);
        if (empty($obj::$Name)) {
            $name       = explode('\\', $class);
            $obj::$Name = array_pop($name);

            if ($obj::$Name == 'Base')
                $obj::$Name = array_pop($name);
        }//end if

        $model = array(
            'cacheid'       => Tools::Uuid(),
            'associations'  => array()
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
        
        $model['schema']['self']    = $db->getSchema($class::$Table_Name);
        $model['schema']['link']    = array();
        $allfields = array();
        $allfields[$class::$Table_Name] = array_keys($model['schema']['self']['fields']);

        if (empty($model['schema']['self']['pkeys'])) {
            $exc = new Exceptions("Please define a primary key index for tablle {$class::$Table_Name}");
            $exc->traceup();
            throw $exc;
        }//end if

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
    public function associate(Model $obj, Model $with, array $options)
    {
        list($obj_class, $obj_ns)   = $this->ids($obj);

        if (array_key_exists($with::$Name, $this->models[$obj_class]['associations'])) {
            $associate = $this->models[$obj_class]['associations'][$with::$Name];

            $var = array(
                'model'     => $with,
                'mode'      => $options['associateby'],
                'eagerload' => $associate['eagerload']
            );

            return $var;
        }//end if
        
        list($with_class, $with_ns) = $this->ids($with);

        $query = $options['query'];
        if (empty($query)) {
            $query  = $this->models[$obj_class]['schema']['self']['pkeys'][0]['name'];
            /**
             * associateby belongs_to
             * foreign_key becomes the query
             * and foreign_key value is the associated class primary key
             */
            if ('belongs_to' == $options['associateby']) {
                $options['query']          = $options['foreign_key'];
                $options['foreign_key']    = $this->models[$with_class]['schema']['self']['pkeys'][0]['name'];
            }//end if
        }//end if

        /**
         * check if foreign_table exists
         */
        $this->models[$with_class]['schema'][$with->uid] = $this->modules[$with_ns]['db']->getSchema($options['foreign_table']);

        $options['find_options'] = Tools::ArrayMerge($with_class::$Find_Options, $options['find_options']);
        
        $schema     =& $this->models[$with_class]['schema'];
        $singleton  = $this;
        $buildsql   = array();

        if (!empty($options['find_options']['conditions']))
             $buildsql = self::BuildSearchSql($schema['fields'], $options['find_options']['conditions']);

        /**
         * 
         * check if foreign_table is not equal to
         * the associated table name, if not then we have a 3rd table definition
         */
        $join = array(
            'link'  => array($obj::$Table_Name, $this->models[$obj_class]['schema']['self']['pkeys'][0]['name']),
            'self'  => array($with::$Table_Name, $this->models[$with_class]['schema']['self']['pkeys'][0]['name']),
        );
        
        if ($options['foreign_table'] != $with::$Table_Name) {
            $join['with'] = array($options['foreign_table'], $options['foreign_key']);
            
            $fields = array_keys($this->models[$with_class]['schema'][$with->uid]['fields']);
            
            foreach ($fields as $k => $field) $this->models[$with_class]['schema']['fields']["{$options['foreign_table']}.{$field}"] = $field;
            $this->models[$with_class]['schema']['fields']["{$options['foreign_table']}.*"] = "{$options['foreign_table']}.*";

            list($selftable, $selfkey) = $join['self'];
            list($jointable, $joinkey) = $join['with'];
            $options['find_options']['join'] = array(
                "{$jointable}" => "`{$selftable}`.`{$selfkey}` = `{$jointable}`.`{$selfkey}`"
            );

            $options['find_options']['columns'][] = "{$selftable}.*";
            $options['find_options']['columns'][] = "{$jointable}.*";
        }//end if

        $associate = array( 
            'qkey'  => $options['query'],
            'fkey'  => $options['foreign_key'],
            'ftab'  => $options['foreign_table'],
            'mode'  => $options['associateby'],
            'join'  => $join,
        );
        
        $associate['sqlquery']  = function($params, $criteria) use ($schema, $with, $join) {
            if ($with->isEmpty()) return null;
            $where = $with->retrieve('sqlcriteria');
            
            /**
             * build sql string for the existing results
             * if there is any
             */
            $fkey   = empty($params)? $schema['self']['pkeys'][0]['name'] : $params;
            $ftable = $schema['self']['table'];

            $sql = '';
            $innerjoin = '';
            
            if (!empty($join['with'])) {
                /**
                 * if there is link schema
                 * disregard the passed searchkey, and use our the
                 * 2nd ordinal primary keys
                 */
                list($ftable, $fkey) = $join['with'];

                /**
                 * inner join with associates_in table
                 * using the first ordinal primary keys
                 */
                $innerjoin = "
                inner join `{$join['with'][0]}` on
                `{$schema['self']['table']}`.`{$schema['self']['pkeys'][0]['name']}` = `{$ftable}`.`{$schema['self']['pkeys'][0]['name']}`
                ";

            }//end if

            $sql = "
            select `{$ftable}`.`{$fkey}`
            from `{$schema['self']['table']}`
            {$innerjoin}
            {$where}
            {$criteria}
            ";

            return $sql;
        };//end function

        $associate['eagerload'] = function($allkeys) use (&$schema, $options, $buildsql, $with, $singleton) {
            if (empty($allkeys)) return false;
            $fkey = $options['foreign_key'];
            $eager_find = $options['find_options'];
            
            /**
             * add conditions config
             */
            $criteria   = '';
            if (!empty($buildsql))
                $criteria = "(".implode( " and ", $buildsql).") and ";

            $where      = array("(`{$schema['self']['table']}`.`{$fkey}` IN (".implode(', ', $allkeys)."))");
            $join       = '';
            $orderby    = "order by `{$schema['self']['table']}`.`{$fkey}`";
            
            if (!empty($schema[$with->uid])){
                $join       = "inner join `{$schema[$with->uid]['table']}` on `{$schema['self']['table']}`.`{$schema['self']['pkeys'][0]['name']}` = `{$schema[$with->uid]['table']}`.`{$schema[$with->uid]['pkeys'][0]['name']}`";
                $where      = array("(`{$schema[$with->uid]['table']}`.`{$fkey}` IN (".implode(', ', $allkeys)."))");
                $orderby    = "order by `{$schema[$with->uid]['table']}`.`{$fkey}`";
            }//end if
            
            $sql = "
            select *
            from `{$schema['self']['table']}`
            {$join}
            where (".implode( " and ", $where).")
            {$orderby}
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
        
        $this->models[$obj_class]['associations'][$with::$Name] = $associate;

        $retval = array(
            'model'     => $with,
            'mode'      => $options['associateby'],
            'eagerload' => $associate['eagerload'],
            'join'      => $associate['join'],
            'fkey'      => $associate['fkey'],
            'find'      => $options['find_options'],
        );
        
        return $retval;
    }

    /**
     *
     * @access
     * @var
     */
    public function save(Model $obj, array $data)
    {
        list($class, $modulens, $name) = $this->ids($obj);
        
        $db     = $this->modules[$modulens]['db'];
        #$data   = $obj->result;
        $schema = $this->models[$class]['schema']['self'];

        if ($obj->is_associated === true)
            $schema = $this->models[$class]['schema'][$obj->uid];
            
        $conditions = array();

        /**
         * if create is false, then do update sql
         * check for pkey value and associates_in pkey value if necessary
         */
        $conditions = array();

        $pkey = $schema['pkeys'][0]['name'];
        foreach ($schema['pkeys'] as $k => $v) {

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

        if ($obj->is_associated === true){
            $schema = $this->models[$class]['schema'][$obj->uid];
            if (empty($schema)) $schema = $this->models[$class]['schema']['self'];
        }//end if
        
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
            if ((array_key_exists($v['name'], $data) === false)){
                $exc = new Exception($this->class.'> remove failed, primarykey ['.$v['name'].'] cannot be empty ');
                $exc->traceup()->traceup();
                throw $exc;
            }
                

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
         * since the table has been modified
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
        
        $find = Tools::ArrayMerge($find, $options);

        if (!is_null($collectby) && !array_key_exists($collectby, $schema['self']['fields']))
            throw new Exception($this->class.'> ['.$schema['self']['table'].'.'.$collectby.'] collectby is not a valid field name');

        /**
         * process associations here
         * if the associations has results
         */
        if (!empty($this->models[$class]['associations']) && $obj->isEmpty()) {
            foreach ($this->models[$class]['associations'] as $k => $v) {
                $sql = $v['sqlquery']($v['fkey'], $obj->retrieve('sqlcriteria'));
                if (empty($sql)) continue;
                $find['subquery'][] = array(
                    'sql' => $sql,
                    'key' => ( ($v['mode'] == 'belongs_to') ? $v['qkey'] : $schema['self']['pkeys'][0]['name'] )
                );
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
            $obj->store('sqlcriteria', $retval['criteria']);
            return $retval;
        }//end if

        /**
         * call search here
         */
        $empty = true;
        
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
        $obj->store('sqlcriteria', $retval['criteria']);
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
            'join'          => array(),
            'select_fields' => array()
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
            $match = self::BuildSearchSql($table_fields, $find, $select_fields);
        
		if (count($subquery) != 0) {
			foreach($subquery as $k=>$v) {
				if (!is_string($v['sql']) && !empty($v['sql']) && !empty($v['key'])) continue;
				$match[] = "({$v['key']} IN ({$v['sql']}))";
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
        //$cols = array("{$schema['self']['table']}.*");
        $cols = array("*");

        if (!empty($select_fields)) {
            $cols = array();

            if (!is_array($select_fields)) $select_fields = explode(',', $select_fields);

            foreach ($select_fields as $k => $v) {
                
                if (is_string($k)) {
                    $cols[] = "{$v} as {$k}";
                    continue;
                }//end if

                //if (!array_key_exists($v, $schema['fields']) && !in_array($v, $schema['fields'])) continue;
                $cols[] = trim($v);
            }// end foreach

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
//    public function sanitize(Model $obj, array $raw)
//    {
//        list($class, $modulens, $name) = $this->ids($obj);
//        $schema = $this->models[$class]['schema']['self'];
//
//        $raw = Tools::Sanitize($raw, $schema['fields']);
//
//        return $raw;
//    }

    /**
     *
     * @access
     * @var
     */
//    public function validate(Model $obj, array $validations)
//    {
//        list($class, $modulens, $name) = $this->ids($obj);
//        $schema = $this->models[$class]['schema']['self'];
//
//        if ($obj->is_associated === true)
//            $schema = $this->models[$class]['schema'][$obj->uid];
//
//        /**
//         * automatically include primary keys that is not
//         * for autoincrement as required if not included
//         */
//        foreach ($schema['pkeys'] as $k => $v) {
//
//            /**
//             * we require primary keys that is not set for auto increment
//             */
//            if ($v['auto_increment'] === true) continue;
//
//            if (!empty($validations['required'][$v['name']])) continue;
//
//            $validations['required'][$v['name']] = "{$v['name']} is required";
//
//        }// end foreach
//
//        Tools::Validate($validations, $obj->result);
//    }

    /**
     *
     * @access
     * @var
     */
    public function getSchema(Model $obj)
    {
        list($class, $modulens, $name) = $this->ids($obj);
        $schema = $this->models[$class]['schema']['self'];

        if ($obj->is_associated === true) $schema = $this->models[$class]['schema'][$obj->uid];

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

