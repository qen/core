<?php
/**
 * Project:     CORE FRAMEWORK
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 *
 * @author Qen Empaces
 * @email qen.empaces@gmail.com
 * @version rc7
 * @date 2010.10.19
 *
 * CONVENTIONS
 * - if defined module is extened, this means the delcaring
 *   class will join with the parent module,
 *   * use of query() and create() will use the parent module schema
 *   * use of find() and save() will use the declaring class schema
 *
 * - you can create a method function that would change how find would behave, like
 *   > $module->publicView(); would limit the result / fieldnames automatically
 *   > $module->adminView(); would not limit the result
 *
 * NOTES
 * - Cached Results
 *   assign a null value to is_cached
 *   $obj->is_cached = null
 *   to clear the result cache
 *
 * LIMITATIONS
 * - the schema must have a primary key indexes defined
 *
 * FOR PARENT SCHEMA ACCESS
 * - use create() or update()   = save()
 * - use destroy()              = remove()
 * - use query()                = find()
 *
 * Method calls on
 * - save()/create()    = validate()
 * - destroy()/remove() = erase()
 *
 * Event Triggers
 * - find()     = find,read
 * - save()     = save
 * - remove()   = remove
 * - add()      = add
 */
namespace Core;

use \ArrayIterator;
use \IteratorAggregate;
use \ArrayAccess;

/**
 *
 * @author
 * the idea is that the module class will
 * spill out different instances of class which holds
 * the query results and it's associations
 *
 * all operations are statically called, search, save and delete
 * we pass minimum parameters to pass is $data, $schema, $dbobject
 */
class Model extends Base implements ArrayAccess, IteratorAggregate
{

    /**
     * this is the one that gets modified
     * and returned when the module is called like a function
     * without arguments
     * example: $Model();
     */
    private $results    = array();
    private $resultkeys = array();
    /**
     * $iterator->key() tells which results element should be
     * returnted or modified
     */
    private $iterator   = array();
    private $db         = null;
    private $debug      = null;
    private $sql        = array();
    private $name       = '';
    private $criteria   = '';
    private $cacheid    = null;
    private $ismodified = false;

    /**
     * holds all cache results
     */
    private static $Cache_Results   = array();


    /**
     * collections of all associated module
     */
    private $associations   = array();

    /**
     * these static variables are the only
     * required configuration for the class
     */
    public static $Name         = '';
    public static $Table_Name   = '';
    public static $Sanitize     = array();
    public static $Find_Options = array(
        'selpage'       => 1,
        'limit'         => 20,
        'search'        => "",
        'orderby'       => "",
        'groupby'       => "",
        'where'         => array(), // default search criteria
        'conditions'    => array(), // additional search criteria
        'subquery'      => array(),
        'count'         => false,
        'columns'       => array(),
        'join'          => array()
    );

    protected $find     = array();
    protected $create   = false;
    protected $schema   = array();
    protected $config   = null;

    public $numpage     = array();
    public $count       = 0;
    public $is_cached   = false;

    // ** start ** required interface functions
    public function offsetExists($offset)
    {
        $idx = $this->iterator->key();
        if (is_string($offset)){
            /**
             * check if $offset is associated module name
             */
            if (array_key_exists($offset, $this->associations)) return true;

            if(isset($this->results[$idx][$offset])) return true;

            /**
             * check if $offset exists in schema fields
             */
            if (array_key_exists($offset, $this->schema['self']['fields'])) return true;

            /**
             * how about in parent
             */
            if (!empty($this->schema['parent']))
                if (array_key_exists($offset, $this->schema['parent']['fields'])) return true;

        }//end if

        if(isset($this->results[$offset])) return true;

        return null;
    }

    public function offsetGet($offset)
    {
        $idx = $this->iterator->key();

        /**
         * use array access if we just want to access the current data from the associate
         * echo $Model[AssociateModel][fieldname]
         */
        if (array_key_exists($offset, $this->associations)) {

            if ($idx !== $this->associations[$offset]['module']->retrieve('parentidx')) {
                $this->associations[$offset]['module']->store('parentidx', $idx);
                $this->$offset();
            }//end if

            return $this->associations[$offset]['module'];
        }//end if

        if (is_string($offset) && $this->offsetExists($offset)) {
            /**
             * sanitize bit value
             */
            $retval = array(
                "{$offset}" => $this->results[$idx][$offset]
            );

            $schema = $this->schema['self']['fields'];
            $is_bit = ('bit' == $schema[$offset]) ? true : false;
            if ($is_bit === false && !empty($this->schema['parent'])) {
                $schema = $this->schema['parent']['fields'];
                $is_bit = ('bit' == $schema[$offset]) ? true : false;
            }//end if
            
            if ($is_bit) $retval = Tools::Sanitize($retval, $schema, false);
                
            return $retval[$offset];
        }//end if

        if ($this->offsetExists($offset)) {
            return $this->results[$offset];
        }//end if

        return null;
    }

    public function offsetSet($offset, $value)
    {

        if (!@array_key_exists($offset, $this->schema['self']['fields'])
            && !@array_key_exists($offset, $this->schema['parent']['fields'])
            ) return false;

        $idx = $this->iterator->key();
        $this->ismodified = true;
        $this->results[$idx][$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        $idx = $this->iterator->key();
        unset($this->results[$idx][$offset]);
    }

    public function getIterator()
    {
        return $this->iterator;
    }
    // ** end ** required iterator functions

    public final function __invoke($args=null, $params = null)
    {

        if (empty($args)) {
            $idx = $this->iterator->key();
            return $this->results[$idx];
        }//end if

        switch ($args) {
            case 'key':
                $idx    = $this->iterator->key();
                $pkey   = $this->schema['self']['pkeys'][0]['name'];
                return $this->results[$idx][$pkey];
                break;

            case 'all':
                return $this->results;
                break;

            case 'count':
                return count($this->results);
                break;

            case 'keys':
                return $this->resultkeys;
                break;

            case 'sqljoin':
                $retval = array();
                $conditions = array();

                if (empty($params))
                    return $retval;

                /**
                 * @TODO test this
                 */
                foreach ($params as $k => $v) {
                    if (!array_key_exists($k, $this->schema['self']['fields'])) continue;
                    $conditions[] = "`{$this->schema['self']['table']}`.`{$k}` = {$v}";
                }// end foreach
                $retval[$this->schema['self']['table']] = implode(" AND ", $conditions);

                if (!empty($this->schema['parent'])) {
                    $conditions = array();
                    $conditions[] = "`{$this->schema['self']['table']}`.`{$this->schema['self']['pkeys'][0]['name']}` = `{$this->schema['parent']['table']}`.`{$this->schema['parent']['pkeys'][0]['name']}`";
                    foreach ($params as $k => $v) {
                        if (!array_key_exists($k, $this->schema['parent']['fields'])) continue;
                        $conditions[] = "`{$this->schema['parent']['table']}`.`{$k}` = {$v}";
                    }// end foreach

                    $retval[$this->schema['parent']['table']] = implode(" AND ", $conditions);
                }//end if

                return $retval;
                break;

            case 'sqlquery':
                /**
                 * build sql string for the existing results
                 * if there is any
                 */
                if ($this->isEmpty()) return null;
                $searchkey = empty($params)? $this->schema['self']['pkeys'][0]['name'] : $params;

                $sql = '';

                if (!empty($this->schema['parent'])) {
                    /**
                     * if there is parent schema
                     * disregard the passed searchkey, and use our the
                     * 2nd ordinal primary keys
                     */
                    $searchkey = $this->schema['self']['pkeys'][1]['name'];

                    /**
                     * inner join with parent table
                     * using the first ordinal primary keys
                     */
                    $join = "
                    inner join `{$this->schema['parent']['table']}` on
                    `{$this->schema['self']['table']}`.`{$this->schema['self']['pkeys'][0]['name']}` = `{$this->schema['parent']['table']}`.`{$this->schema['parent']['pkeys'][0]['name']}`
                    ";

                }//end if

                $sql = "
                select `{$this->schema['self']['table']}`.`{$searchkey}`
                from `{$this->schema['self']['table']}`
                {$join}
                {$this->criteria}
                ";

                return $sql;
                break;

            /**
             * EAGER LOADING HERE
             */
            case 'load':
                list($fkey, $allkeys) = $params;

                if (empty($allkeys)) return false;

                $fkey = empty($fkey)? $this->schema['self']['pkeys'][0]['name'] : $fkey;

                /**
                 * add conditions config
                 */
                $where      = array();
                $criteria   = '';
                if (!empty($this->find['self']['conditions'])){
                    $where = self::BuildSearchSql($this->schema['self']['table'], array_keys($this->schema['self']['fields']), $this->find['self']['conditions'], $this->db);
                    $criteria = "(".implode( " and ", $where).") and ";
                }//end if

                $where[]    = "(`{$this->schema['self']['table']}`.`{$fkey}` IN (".implode(', ', $allkeys)."))";
                $join       = '';

                if (!empty($this->schema['parent']))
                    $join = "inner join `{$this->schema['parent']['table']}` on `{$this->schema['self']['table']}`.`{$this->schema['self']['pkeys'][0]['name']}` = `{$this->schema['parent']['table']}`.`{$this->schema['parent']['pkeys'][0]['name']}`";

                $sql = "
                select *
                from `{$this->schema['self']['table']}`
                {$join}
                where (".implode( " and ", $where).")
                order by `{$this->schema['self']['table']}`.`{$fkey}`
                ";

                $loop       = $this->sqlSelect($sql);
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
                    $resultkeys[$idx][] = $value[$this->schema['self']['pkeys'][0]['name']];
                }//end foreach

                $ops            = $this->find['self'];
                $ops['search']  = $fkey;

                foreach ($results as $query => $all) {

                    $cachekey = md5(Tools::Hash(array("{$query}", $ops, $this->schema['self'])));

                    $this->cacheResults($cachekey, array(
                        $all,
                        $resultkeys[$fkey],
                        array(), // numpage is empty since we get all records
                        count($all),
                        "where {$criteria}`{$this->schema['table']}`.`{$fkey}` = ".$this->db->escape($query)
                    ));

                }// end foreach

                break;

            case 'table':
                return $this->schema['self']['table'];
                break;

            case 'primarykey':
                return $this->schema['self']['pkeys'][0]['name'];
                break;

            case 'name':
                return $this->name;
                break;

            case 'class':
                $retval = str_replace('Core\\App\\Modules\\', '', $this->class);
                $retval = str_replace('\\', '', $retval);
                return $retval;
                break;

            /**
             * merge find property
             */
            case 'find':
                if (empty($params) || !is_array($params)) return false;

                $this->find['self']     = Tools::ArrayMerge((array)$this->find['self'], (array)$params);
                $this->find['parent']   = Tools::ArrayMerge((array)$this->find['parent'], (array)$params);

                return true;
                break;

            case 'dump':
                Debug::Dump('sql', $this->sql);
                Debug::Dump('find', $this->find);
                Debug::Dump('criteria', $this->criteria);
                Debug::Dump('schema', $this->schema);
                Debug::Dump('associations', @array_keys($this->associations));
                Debug::Dump('debug', $this->debug);
                break;

            case 'debug':
                Debug::Dump('sql', $this->sql);
                break;
            default:
                return null;
                break;
        }// end switch

    }

    public function __call($varname, $args){

        /**
         * this will allow the call like
         * $this->AssociateModel(array('limit' => 20))->each()
         */
        if (array_key_exists($varname, $this->associations)) {

            /**
             * get the current query value by default its the primary key
             */
            $query = $this[$this->associations[$varname]['query']];

            /**
             * if options is exactly all, pass all results query
             */
            if ('all' == $args[0]) {
                $allresults = $this->results;
                $query = array();
                foreach ($allresults as $k => $v) {
                    $query[] = $v[$this->associations[$varname]['query']];
                }// end foreach
            }//end if

            /**
             * otherwise use each function to modify all returned
             * value from the associated module
             */
            if (!empty($query)) {

                $options = (is_array($args[0]))? $args[0] : array();

                $options['search'] = $this->associations[$varname]['fkey'];

                /**
                 * ignore error if there is no results
                 */
                try {
                    $this->associations[$varname]['module']->find($query, $options);
                } catch (Exception $exc) {
                    //Debug::Dump($exc->getMessages());
                }//end try

            }//end if

            return $this->associations[$varname]['module'];
        }//end if

        parent::__call($varname, $args);
    }

    /**
     *
     * @access
     * @var
     */
    public function  __destruct()
    {
        unset(self::$Cache_Results[$this->cacheid]);
    }

    public function __get($varname) {
        /**
         * directly interact with associated module
         */
        if (array_key_exists($varname, $this->associations)) {
            /**
             * directly access the module
             */
            return $this->associations[$varname]['module'];
        }//end if

        parent::__get($varname);
    }

    protected final function initialize()
    {
        $this->find['self'] = static::$Find_Options;

        if (empty(static::$Table_Name))
                throw new Exception($this->class.' static::$Table_Name is empty ');

        /**
         * generate cache id, technically
         * this is only called once since succeeding instances
         * are cloned
         */
        $this->cacheid  = Tools::Uuid();
        self::$Cache_Results[$this->cacheid] = array();

        $this->name     = ucfirst(static::$Name);
        if (empty($this->name)) {
            $name       = explode('\\', $this->class);
            $this->name = array_pop($name);

            if ($this->name == 'Base')
                $this->name = array_pop($name);
        }//end if

    }

    public final function __clone()
    {

        $this->associations = array();
        $this->clear();
        $this->setup();
        return true;
    }

    /**
     *
     * @access
     * @var
     *
     * this is called on object cloned
     */
    protected function setup()
    {

    }

    /**
     *
     * @access
     * @var
     */
    public final function sqlSelect($sql, $bindvars = array())
    {
        return $this->db->query($sql, $bindvars);
    }

    /**
     *
     * @access
     * @var
     */
    public final function sqlExecute($sql, $bindvars = array())
    {
        return $this->db->execute($sql, $bindvars);
    }

    /**
     *
     * @access
     * @var
     */
    public final function sqlEscape($param)
    {
        return $this->db->escape($param);
    }

    /**
     *
     * @access
     * @var
     *
     * you put all module associations here
     * by calling $this->associate()
     *
     */
    public function hasAssociations()
    {
        throw new Exception('hasAssociations is not defined');
    }

    /**
     *
     * @access
     * @var
     */
    protected final function associate(Model $obj, array $find)
    {
        /**
         * don't need to clone the associate parameters
         * as the Model::[Modelname]() will always return a cloned module
         */
        $name   = $obj('name');
        $query  = (!empty($find['query']))? $find['query'] : $this->schema['self']['pkeys'][0]['name'];
        $fkey   = $find['foreign_key'];

        if (empty($fkey))
            throw new Exception($this->class.'> Please define a foreign_key for '.$name);

        unset($find['query']);
        unset($find['foreign_key']);

        $var = array();
        $var['module'] = $obj;
        $var['query']  = $query;
        $var['find']   = $find;
        $var['fkey']   = $fkey;
        $var['module']('find', $v['find']);

        $this->associations[$name] = $var;

        return $obj;
    }

    /**
     *
     * @access
     * @var
     */
    public final function clear($include_cache = false)
    {
        $this->results      = array();
        $this->resultkeys   = array();
        $this->iterator     = new ArrayIterator($this->results);
        $this->count        = 0;
        $this->numpage      = array();
        $this->sql          = array();
        $this->ismodified   = false;

        if ($include_cache === true)
            $this->cacheResults(null);

        if (!empty($this->associations)) {
            foreach ($this->associations as $k => $v) {
                $v['module']->store('parentidx', null)->clear($include_cache);
            }// end foreach
        }//end if

    }

    /**
     *
     * @access
     * @var
     * this method just clears and adds consistency
     * on how the object is used, since when the save method is called
     * it checks if the defined primary key/index value(s) already exists
     * and if it does it will update the record instead
     */
    public final function add(array $data = array())
    {
        //$this->clear();
        array_push($this->results, array());
        end($this->results);

        $pos    = key($this->results);
        $self   = $this;

        $this->iterator = new ArrayIterator($this->results);
        $this->iterator->seek($pos);

        $this->create = true;

        if (!empty($data))
            $this->from($data);

        $this->fireEvent('add', array($self));

        return $this;
    }

    /**
     *
     * @access :noextend
     * @var
     */
    public final function config($param = '')
    {

        if ($param instanceof Config) {
            $this->config = $param;

            /**
             * instantiate db class on config pass
             * and get the table schema
             *
             * ::IMPORTANT::
             * ordinal position of primary keys is important
             * as the first primary key value is always passed through it's associations
             *
             * and for child schemas the first primary key is the same as the parents first primary key
             */
            $dbconfig = $this->config->db();
            if (is_null($dbconfig))
                throw new Exception("{$this->class} failed to connect to db please check it's connection settings");
            //Debug::Dump($dbconfig);
            $this->db   = Db::Instance($dbconfig);
            $parent     = get_parent_class($this->class);

            $this->schema['self']   = $this->db->getSchema(static::$Table_Name, static::$Sanitize);
            $this->schema['parent'] = array();

            if ($parent != 'Core\\Model'){

                $this->schema['parent'] = $this->db->getSchema($parent::$Table_Name, $parent::$Sanitize);
                $this->find['parent']   = $parent::$Find_Options;

                $this->find['self']['join'][$parent::$Table_Name] = "`{$this->schema['self']['table']}`.`{$this->schema['self']['pkeys'][0]['name']}` = `{$this->schema['parent']['table']}`.`{$this->schema['parent']['pkeys'][0]['name']}`";
            }//end if

            return $this->config;
        }//end if

        if (!empty($param)) {
            try {
                return $this->config->$param;
            } catch (Exception $exc) {
                return $this->config;
            }//end try
        }//end if

        return $this->config;
    }

    /**
     *
     * @access
     * @var
     */
    public final function from($array)
    {
        if (empty($array))      return $this;
        if (!is_array($array))  return $this;

        foreach ($array as $k => $v) {
            /**
             * check if $k is associated module name
             */
            if (array_key_exists($k, $this->associations)) {
                $this->associations[$k]['module']->from($v);
                continue;
            }//end if

            $this[$k] = $v;
        }//end foreach

        return $this;
    }

    /**
     *
     * @access
     * @var
     * called on every save calls
     */
    public function validate()
    {
    }

    /**
     *
     * @access
     * @var
     */
    public final function isEmpty()
    {
        $idx = $this->iterator->key();

        return empty($this->results[$idx]);
    }

    /**
     *
     * @access
     * @var
     */
    public final function isModified()
    {
        return $this->ismodified;
    }

    /**
     *
     * @access
     * @var
     * this is the equivalent to query()/destroy() method
     */
    public final function create()
    {
        if (empty($this->schema['parent'])) {
            //throw new Exception($this->class.'> doesnt have a parent schema < use save() >');
            return $this->save();
        }//end if

        /**
         * save current schema and find values
         */
        $schema = $this->schema;
        $find   = $this->find;

        $this->schema['self']   = $schema['parent'];
        $this->find['self']     = $find['parent'];

        try {
            $retval = $this->save();
        } catch (Exception $exc) {
            $this->schema['self']   = $schema['self'];
            $this->find['self']     = $find['self'];
            throw $exc;
        }//end try

        $this->schema['self']   = $schema['self'];
        $this->find['self']     = $find['self'];

        return $this;
    }

    /**
     *
     * @access
     * @var
     * this is pretty much the "create"  method
     * just so that it makes sense to call update when actually update
     * rather than calling create when the purpose really was to update
     */
    public function update()
    {
        $func = 'create';
        if (empty($this->schema['parent']))
            $func = 'save';

        return $this->$func();
    }

    /**
     *
     * @access
     * @var
     */
    public final function save()
    {
        $schema = $this->schema['self'];

        if ($this->isEmpty())
            throw new Exception($this->class.'> No data to save');

        $self = $this;
        $this->fireEvent('save', array($self));

        /**
         * validate data
         */
        $this->validate();

        /**
         * data might have change during the validate call
         */
        $data = $this();

        /**
         * sanitize data here
         */
        $data = Tools::Sanitize($data, $schema['fields']);

        /**
         * prepare to write the data to db
         */
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
                throw new Exception($this->class.'> save failed, ['.$v['name'].'] does not exists in data array ');

            $idx = 'ukey';
            if ($v['auto_increment'] === true)
                $idx = 'autoid';

            /**
             * add to condition if not empty
             */
            if (isset($data[$v['name']]))
                $conditions[$idx][] = "`{$schema['table']}`.`{$v['name']}` = ".$this->db->escape($data[$v['name']]);

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
                $check['autoid'] = $this->db->query($sql);
                /**
                 * use autoid conditions if record is found
                 */
                if ($check['autoid'][0]['cnt'] != 0)
                    $conditions = $conditions['autoid'];
            }//end if

            if (!empty($conditions['ukey'])) {
                $sql = "select count(*) as cnt from {$schema['table']} where ".implode(' and ', $conditions['ukey']);
                $check['ukey'] = $this->db->query($sql);
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
         */
        list($data, $this->debug) =
            self::Write($data, $schema, $this->db, $conditions);

        foreach ($data as $k => $v)
            $this[$k] = $v;

        /**
         * loop to all associations and then call save
         * pass $data to method call
         */
        if (!empty($this->associations)) {

            foreach ($this->associations as $k => $v) {

                if ($v['module']->isEmpty()) continue;

                /**
                 * loop to all module results and then call save
                 */
                $v['module']->each(function($curr) use ($v){
                    $v['module']->save();
                });

            }// end foreach

        }//end if

        $this->create = false;
        $this->ismodified = false;
        return $this;
    }

    /**
     *
     * @param <type> $data
     * @param <type> $schema
     * @param <type> $db
     * @param array $conditions
     * @return <type>
     */
    private final static function Write(array $data, array $schema, $db, array $conditions = array())
    {
        $debug[__FUNCTION__] = array();

        /**
		 * add/update data here
		 */
        $dump = self::BuildWriteSql($data, $schema, $db, $conditions);

        $db->execute($dump['sql']);
        $debug[__FUNCTION__][] = $dump;
        $debug[__FUNCTION__][] = $data;

        if (empty($conditions)) {
            foreach ($schema['pkeys'] as $k => $v) {
                if ($v['auto_increment'] === false)
                    continue;
                $data[$v['name']] = $db->getInsertId();

            }// end foreach
        }//end if

        $retval = array($data, $debug);
        return $retval;
    }
    /**
     *
     * @param array $vars
     * @param array $schema
     * @param <type> $db
     * @param array $conditions if this is passed it will do an update
     */
    private final static function BuildWriteSql(array $vars, array $schema, $db, array $conditions = array())
    {
        $table      = $schema['table'];
        $fieldnames = $schema['fields'];
        $values     = array();
        $columns    = array();

        if (empty($conditions)) {

            $retval["action"] = "insert";

            foreach($vars as $k=>$v) {

                if (!array_key_exists($k, $fieldnames)) continue;

                $values[]   = (is_null($v)? 'NULL' : $db->escape($v));
                $columns[]  = "`{$k}`";

            }//end foreach

            $retval["sql"] = "INSERT INTO `{$table}` (".implode(", ", $columns).")  VALUES (".implode(", ", $values).")";

        } else {

            $retval["action"] = "update";

            foreach($vars as $k=>$v) {

                if (!array_key_exists($k, $fieldnames)) continue;

                $values[] = "`{$k}` = ".(is_null($v)? 'NULL' : $db->escape($v));

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
     * called on every data remove() / destroy()
     */
    public function erase()
    {

    }

    /**
     *
     * @access
     * @var
     * this is the equivalent to query()/create() method
     */
    public final function destroy()
    {
        if (empty($this->schema['parent'])) {
            //throw new Exception($this->class.'> doesnt have a parent schema < use remove() >');
            return $this->remove();
        }//end if

        /**
         * save current schema and find values
         */
        $schema = $this->schema;
        $find   = $this->find;

        $this->schema['self']   = $schema['parent'];
        $this->find['self']     = $find['parent'];

        try {
            $this->remove();
        } catch (Exception $exc) {
            $this->schema['self']   = $schema['self'];
            $this->find['self']     = $find['self'];
            throw $exc;
        }//end try

        $this->schema['self']   = $schema['self'];
        $this->find['self']     = $find['self'];

        /**
         * delete is from parent
         * also remove link data on current schema
         * manipulate the pkeys to only have the value of the primary pkeys
         */
        $this->schema['self']['pkeys'] = array(
            $schema['self']['pkeys'][0]
        );

        try {
            $this->remove();
        } catch (Exception $exc) {
            $this->schema['self']   = $schema['self'];
            throw $exc;
        }//end try

        $this->schema['self']   = $schema['self'];

        return $this;
    }

    /**
     *
     * @access
     * @var
     */
    public final function remove()
    {
        $schema = $this->schema['self'];
        $debug  = array();
        $self   = $this;

        $data   = $this();
        if (empty($data))
            throw new Exception($this->class.'> No data to remove');

        $this->fireEvent('remove', array($self));
        $this->erase();

        /**
         * loop to all associations and then call remove to all results
         */
        if (!empty($this->associations)) {

            foreach ($this->associations as $k => $v) {
                $module = $this[$k];

                if ($module->isEmpty()) continue;

                /**
                 * get module through array access
                 */
                $module->each(function($data) use ($v) {
                    $v['module']->remove()->store('parentidx', null);
                });

            }// end foreach

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
            //if (empty($data[$v['name']]))
            if ((array_key_exists($v['name'], $data) === false))
                throw new Exception($this->class.'> remove failed, primarykey ['.$v['name'].'] cannot be empty ');

            $idx = 'ukey';
            if ($v['auto_increment'] === true)
                $idx = 'autoid';

            $dump[$idx][] = "`{$schema['table']}`.`{$v['name']}` = ".$this->db->escape($data[$v['name']]);
        }// end foreach

        $conditions = $dump['ukey'];
        if (!empty($dump['autoid']))
            $conditions = $dump['autoid'];

        /**
         * finally call delete here
         */
        $this->debug = self::Delete($data, $schema, $this->db, $conditions);

        /**
         * every time this is called
         * reset or clear the cache results
         */
        $this->cacheResults(null);
        return $this;
    }

    /**
     *
     * @access
     * @var
     */
    private final static function Delete($data, $schema, $db, $conditions = array())
    {
        $debug[__FUNCTION__] = array();

        $sql = "delete from `{$schema['table']}` where ".implode(' and ', $conditions);

        $debug[__FUNCTION__] = $sql;
        $db->execute($sql);

        return $debug;
    }

    /**
     *
     * @access
     * @var
     */
    private final function cacheResults($key, $value = null)
    {
        $idx = $this->cacheid;

        if (is_null($key)){
            self::$Cache_Results[$idx] = array();
            return null;
        }//end if

        if (!is_null($value))
            self::$Cache_Results[$idx][$key] = $value;

        if (@array_key_exists($key, self::$Cache_Results[$idx]))
            return self::$Cache_Results[$idx][$key];

        return null;
    }
    /**
     *
     * @param <type> $query
     * @param array $options
     * this function will use the parent schema
     * as if it was called from the parent->find()
     *
     * same functionality as create() / destroy() methods
     */
    public final function query($query = '*', array $options = array())
    {
        if (empty($this->schema['parent'])){
            //throw new Exception($this->class.'> doesnt have a parent schema < use find() >');
            return $this->find($query, $options);;
        }//end if

        /**
         * save current schema and find values
         */
        $schema = $this->schema;
        $find   = $this->find;

        $this->schema['self']   = $schema['parent'];
        $this->find['self']     = $find['parent'];

        try {
            $retval = $this->find($query, $options);
        } catch (Exception $exc) {

            $this->schema['self']   = $schema['self'];
            $this->find['self']     = $find['self'];

            throw $exc;
        }//end try

        $this->schema['self']   = $schema['self'];
        $this->find['self']     = $find['self'];

        return $retval;
    }

    /**
     * test documentation
     * @access
     * @var
     *
     */
    public final function find($query = '*', array $options = array())
    {
        /**
         * this will give you access to change the
         * options parameters
         */
        $this->fireEvent('find', array($options));

        $schema     = $this->schema['self'];
        $resultkeys = array();
        $results    = array();

        /**
         * this will group results depending
         * on the value of the field specificied
         */
        $collectby = $options['collectby'];
        unset($options['collectby']);

        /**
         * eager load options
         */
        $includes = $options['includes'];
        unset($options['includes']);

        $find = Tools::ArrayMerge($this->find['self'],  $options);

        if (!is_null($collectby) && !array_key_exists($collectby, $schema['fields']))
            throw new Exception($this->class.'> ['.$schema['table'].'.'.$collectby.'] collectby is not a valid field name');

        /**
         * process associations here
         * if the associations has results
         */
        if (!empty($this->associations) && $this->isEmpty()) {
            foreach ($this->associations as $k => $v) {
                $sql = $v['module']('sqlquery', $v['fkey']);
                if (empty($sql)) continue;
                $find['subquery'][] = $sql;
            }// end foreach
        }//end if

        if (!empty($find['join'])) {
            $loop = $find['join'];
            foreach ($loop as $k => $v) {
                if (!array_key_exists($k, $this->associations)) continue;

                /**
                 * check if $k is associated module name
                 * @TODO join for associations
                 */
                $conditions = $this->associations[$k]['find']['conditions'];
                foreach ($conditions as $idx => $val) $conditions[$idx] = $this->db->bind($idx, $val);

                $conditions[$this->associations[$k]['fkey']] = "`{$schema['table']}`.`{$schema['pkeys'][0]['name']}`";
                $jointable = $this->associations[$k]['module']('sqljoin', $conditions);
                unset($find['join'][$k]);
                foreach ($jointable as $table => $criteria)
                    $find['join'][$table] = "{$criteria} {$v}";

            }// end foreach

        }//end if

        /**
         * cache check here
         */
        $fhash = $find;
        unset($fhash['each']);

        $cachekey   = md5(Tools::Hash(array("{$query}", $fhash, $schema)));
        $cache      = null;
        $this->clear();

        /**
         * if the property is_cached was set to null
         * do not check cached results
         */
        if (!is_null($this->is_cached))
            $cache = $this->cacheResults($cachekey);

        if (!is_null($cache)) {
            $this->is_cached = true;

            list(
                $this->results,
                $this->resultkeys,
                $this->numpage,
                $this->count,
                $this->criteria,
            ) = $cache;

            $this->iterator = new ArrayIterator($this->results);
            return $this;
        }//end if
        $this->is_cached = false;

        if (!empty($this->schema['parent'])
            && $this->schema['parent'] != $this->schema['self']
            && empty($find['columns'])
            ) {
            $find['columns'][] = "{$this->schema['parent']['table']}.*";
            $find['columns'][] = "{$this->schema['self']['table']}.*";
        }//end if

        /**
         * call search here
         */
        $retval = self::Search($query, $schema, $this->db, $find);

        if ($retval[0] === false){
            $this->sql      = $retval[1];
            throw new Exception($this->class.'> ['.$schema['table'].' : '.$retval[1].'] results empty');
        }//end if

        // option count is true, thus it returns a numeric value
        if (is_numeric($retval))
            return $retval;

        if (empty($retval))
            return false;

        /**
         * distribute returned value to its correct property
         */
        list($loop, $this->numpage, $this->count, $this->sql['find'], $this->criteria) = $retval;

        /**
         * LOOP results
         */
        $hashfields = @array_keys($schema['fields'], 'hash');

        foreach ($loop as $k => $data) {
            $resultkeys[]   = $data[$schema['pkeys'][0]['name']];

            /**
             * loop to hash fields and decode
             * if it has a string value
             */
            if (!empty($hashfields)) {
                foreach ($hashfields as $k => $v){
                    if (empty($data[$v])) {
                        $data[$v] = array();
                        continue;
                    }//end if

                    $data[$v] = Tools::Hash($data[$v]);
                }
            }//end if

            /**
             * call read event
             *
             * on the function declaration,
             * delcare the function parameter $data
             * to be passed by reference
             * if there is a field name to be modified
             */
            $this->fireEvent('read', array(&$data));

            if (!is_null($collectby)) {
                $idx                = $data[$collectby];
                $results[$idx][]    = $data;
                continue;
            }//end if

            $results[] = $data;
            usleep(24);
        }// end foreach

        $this->results      = $results;
        $this->resultkeys   = $resultkeys;

        $this->iterator     = new ArrayIterator($this->results);
        $this->iterator->rewind();

        /**
         * Eager loading here
         * it will select all records
         */
        if (!empty($includes)
            && is_array($includes)
            && !empty($this->associations) ) {

            $pkeys = $this('keys');
            foreach ($includes as $k => $varname) {
                if (!array_key_exists($varname, $this->associations)) continue;

                $this->associations[$varname]['module']->clear();
                $this->associations[$varname]['module']('load', array(
                    $this->associations[$varname]['fkey'],
                    $pkeys,
                ));
            }// end foreach

        }//end if

        $this->cacheResults($cachekey, array(
            $this->results,
            $this->resultkeys,
            $this->numpage,
            $this->count,
            $this->criteria
        ));

        $this->debug[]      = $this->sql['find'];
        $this->ismodified   = false;
        return $this;
    }

    /**
     *
     * @access
     * @var
     */
    public final function invoke($args=null, $params = null)
    {
        return $this($args, $params);
    }

    /**
     *
     * @access
     * @var
     */
    public final function each($each)
    {
        if (!is_callable($each)) return false;
        /**
         * loop to array iterator but pass the data
         * from the results property
         * as the it's possible to modify the $data when
         * $each declares the parameter by reference
         */
        #$this->iterator->rewind();
        $this->iterator = new ArrayIterator($this->results);

        foreach ($this as $k => $v) {
            $data = $this->results[$k];
            $each($data);
        }// end foreach

        $this->iterator->rewind();
    }

    /**
     *
     * @access
     * @var
     */
    public function collect($field_name)
    {
        $this->iterator = new ArrayIterator($this->results);
        $retval = array();
        foreach ($this as $k => $v)
            $retval[] = $v[$field_name];

        $this->iterator->rewind();

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
            $search = $schema['pkeys'][0]['name'];

		if (!is_numeric($selpage))
            $selpage = 1;

		if (!empty($query) && $query != '*')
            $where["{$search}"] = $query;

		$match  = array();
		$values = array();

        $find = Tools::ArrayMerge($conditions, $where);

		if (count($find) != 0)
            $match = self::BuildSearchSql($schema['table'], array_keys($schema['fields']), $find, $db);

		if (count($subquery) != 0) {
			foreach($subquery as $k=>$v) {
				if (!is_string($v)) continue;
				$match[] = "({$schema['pkeys'][0]['name']} IN ({$v}))";
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
		$sql['count'] = "select {$schema['pkeys'][0]['name']} from {$schema['table']} {$jointable} {$where_conditions}";

		$cnt = $db->queryCount($sql['count']);
		if ($cnt === false)
			throw new Exception("Query count failed");

		if ($count === true)
			return $cnt;

        if ($cnt == 0)
            return array(false, $sql['count']);

        /**
         * page numbering here
         */
		$limitclause = "";
		if ($selpage > 0 && $limit > 0) {
			$numpage        = Tools::Numpage($cnt, $selpage, $limit);
			$limitclause    = "limit {$numpage['rows']['offset']}, {$numpage['rows']['limit']}";
		}//end if

        if (!empty($orderby))
            $orderby = "order by {$orderby}";

        if (!empty($groupby))
            $groupby = "group by {$groupby}";

        if (!is_array($columns))
            $columns = array();

        /**
         * column selection
         */
        $cols = "{$schema['table']}.*";

        if (!empty($columns)) {
            $cols = array();

            foreach ($columns as $k => $v) {
                list($tb, $fd) = explode('.', $v);
                /**
                 * $fd is null if the $v is not in tablename.fieldname format
                 */
                if (!array_key_exists($v, $schema['fields']) && is_null($fd)) continue;
                $cols[] = $v;
            }// end foreach

            $cols = implode(", ", $cols);
        }//end if

		$sql['select'] = "
        select {$cols}
		from {$schema['table']}
        {$jointable}
		{$where_conditions}
		{$orderby}
        {$groupby}
		{$limitclause}
        ";

		$results = $db->query($sql['select']);

		if ($results === false) return false;

        $retval = array($results, $numpage, $cnt, $sql, $where_conditions);

        return $retval;
    }

    private final static function BuildSearchSql($ptable, array $fields, array $find, $db)
    {

        $where  = array();
        $values = array();

        if (count($find)==0) return array($where, $values);

        $idx = 0;
        foreach($find as $k => $v){
            $table      = $ptable;
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
            list($tb, $fd) = explode('.', $field_name);
            if (!in_array($field_name, $fields) && is_null($fd)) continue;
            if (!is_null($fd)) {
                $table      = $tb;
                $field_name = $fd;
            }//end if

            /**
             * check if value is array
             *
             */
            if (is_array($v)) {
                $condition = ($condition == '!=') ? 'NOT IN': 'IN';
                foreach($v as $fkey=>$fval){
                    if (preg_match("|^select|is", $fval))
                        $v[$fkey] = $fval;
                    else
                        $v[$fkey] = $db->escape($fval);
                }//end foreach

                $where[] = " ( `{$table}`.`{$field_name}` {$condition} (".implode(", ", $v).") ) ";
                continue;
            }//end if

            /**
             * check if value is null
             *
             */
            if (is_null($v)) {
                $condition = ($condition == '!=') ? 'IS NOT': 'IS';
                $where[] = " ( `{$table}`.`{$field_name}` {$condition}  NULL) ";
                continue;
            }//end if


            /**
             * check value if there || exists
             *
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
            foreach($split as $x=>$y){
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
                            $split_values[] = "~`{$table}`.`{$field_name}` {$condition} ".substr($y,1);
                        else
                            $split_values[] = "`{$table}`.`{$field_name}` {$condition} {$y}";

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
                            $split_values[] = "`{$table}`.`{$field_name}` {$condition} {$y}";
                            continue 2;
                            break;
                        }//end if

                        break;

                    default:
                        break;
                }//end switch

//                if ($absolute_value === true) {
//                    $split_values[] = "`{$table}`.`{$field_name}` {$condition} ".$db->escape($y);
//                    continue;
//                }//end if

                $bind_name      = $db->bind($field_name, $y);
                $split_values[] = "`{$table}`.`{$field_name}` {$condition} {$bind_name}";

            }//end foreach

            $where[]= " ((".implode(") {$split_cond} (", $split_values).")) ";

        }//end foreach

        return $where;
    }// end function

    protected function doValidations(array $validations) {

        $schema = $this->schema['self'];

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

        Tools::Validate($validations, $this);
    }

}

