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
 *******************************************************************************
 *
 * CONVENTIONS
 * - if defined model is extened, this means the delcaring
 *   class will join with the parent model
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
 * - parent(function($parent){
 *      $parent->save();
 *      $parent->remove();
 *      $parent->find();
 * });
 *
 * Method calls on
 * - save()    = validate()
 * - remove()  = erase()
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

use \Core\Exception;
use \Core\Db;

class Model extends \Core\Base implements ArrayAccess, IteratorAggregate
{

    /**
     * this is the one that gets modified
     * and returned when the model->result or model->results
     */
    private $results    = array();
    private $resultkeys = array();
    /**
     * $iterator->key() tells which results element should be
     * returnted or modified
     */
    private $iterator   = array();
    private $ismodified = false;
    private $isparent   = false;

    /**
     * collections of all associated models
     */
    private $associations   = array();

    /**
     * these static variables are the only
     * required configuration for the class
     */
    public static $Name          = '';
    public static $Table_Name    = '';
    public static $Sanitize      = array();
    public static $Custom_Fields = array();
    public static $Find_Options  = array(
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

    /**
     * if is_cached = null, then it skip the cached results checked
     */
    public $is_cached   = false;
    public $numpage     = array();

    /**
     * read only variables
     */
    private $count      = 0;
    private $uid        = '';

    // ** start ** required interface functions
    public final function offsetExists($offset)
    {
        if (is_string($offset)) {
            $check = ModelActions::Instance()->fieldExists($this, $offset);
            if ($check) return true;

            $idx = $this->iterator->key();
            return isset($this->results[$idx][$offset]);
        }// endif

        $result = isset($this->results[$offset]);

        return isset($this->results[$offset]);
    }

    public final function offsetGet($offset)
    {
        $idx = $this->iterator->key();
        
        if (is_string($offset) && $this->offsetExists($offset))
            return $this->results[$idx][$offset];

        if ($this->offsetExists($offset))
            return $this->results[$offset];
        
        return null;
    }

    public final function offsetSet($offset, $value)
    {
        $cleaned = ModelActions::Instance()->sanitize($this, array( "{$offset}" => $value ));
        $idx = $this->iterator->key();
        $this->ismodified = true;
        $this->results[$idx][$offset] = $cleaned[$offset];
    }

    public final function offsetUnset($offset)
    {
        $idx = $this->iterator->key();
        unset($this->results[$idx][$offset]);
    }

    public final function getIterator()
    {
        return $this->iterator;
    }
    // ** end ** required iterator functions

    public final function __invoke($args = null, $params = null)
    {

        if (empty($args)) {
            $exc = new Exception('please use $obj->result');
            $exc->traceup();
            throw $exc;
        }//end if

        switch ($args) {
            case 'key':
                $exc = new Exception('please use $obj->resultkey');
                $exc->traceup();
                throw $exc;
                break;

            case 'all':
                $exc = new Exception('please use $obj->results');
                $exc->traceup();
                throw $exc;
                break;

            case 'count':
                $exc = new Exception('wtf you use this ?');
                $exc->traceup();
                throw $exc;
                break;

            case 'keys':
                $exc = new Exception('please use $obj->resultkeys');
                $exc->traceup();
                throw $exc;
                break;

            default:
                return null;
                break;
        }// end switch

    }

    public function __call($varname, $args)
    {

        /**
         * this will allow the call like
         * $this->AssociateModel(array('limit' => 20))->each()
         */
        if (array_key_exists($varname, $this->associations)) {
            $assocate = $this->associations[$varname];

            /**
             * if arguments is empty or null
             */
            if (empty($args) || $this->isEmpty())
                return $assocate['model'];

            if ($args[0] === true)
                $args = array();

            /**
             * get the current query value by default its the primary key
             */
            $query = $this[$assocate['query']];

            /**
             * if options is exactly all, pass all results query
             */
            if ('all' == $args[0]) {
                $allresults = $this->results;
                $query = array();
                foreach ($allresults as $k => $v) {
                    $query[] = $v[$assocate['query']];
                }// end foreach
            }//end if

            /**
             * otherwise use each function to modify all returned
             * value from the associated model
             */
            if (!empty($query)) {

                $options = (is_array($args[0]))? $args[0] : array();

                $options['search'] = $assocate['fkey'];

                /**
                 * ignore error if there is no results
                 */
                try {
                    $assocate['model']->find($query, $options);
                } catch (Exception $exc) {
                }//end try

            }//end if

            return $assocate['model'];
        }//end if

        parent::__call($varname, $args);
    }

    public function __get($varname)
    {

        /**
         * varname association access gets priority over other variables
         */
        if (array_key_exists($varname, $this->associations)) {
            $idx = $this->iterator->key();
            if ($idx !== $this->associations[$varname]['model']->retrieve('parentidx')) {
                $this->associations[$varname]['model']->store('parentidx', $idx);
                /**
                 * trigger the query call by passing a value true
                 */
                $this->$varname(true);
            }//end if

            return $this->associations[$varname]['model'];
        }//end if
        
        switch ($varname) {
            case 'result':
                $idx = $this->iterator->key();
                return $this->results[$idx];
                break;

            case 'results':
                return $this->results;
                break;

            case 'resultkey':
                $idx    = $this->iterator->key();
                $pkey   = ModelActions::Instance()->pkey($this);
                return $this->results[$idx][$pkey];
                break;

            case 'resultkeys':
                return $this->resultkeys;
                break;

            case 'uid':
                return $this->uid;
                break;

            case 'count':
                return $this->count;
                break;

            case 'is_parent':
                return $this->isparent;
                break;

            case 'config':
                return ModelActions::Instance()->config($this);
                break;

            default:
                break;
        }// end switch

        return parent::__get($varname);
    }

    public function __isset($name)
    {
        if (array_key_exists($name, $this->associations)) return true;

        $respondto = array();
        $respondto[] = 'result';
        $respondto[] = 'uid';
        $respondto[] = 'count';
        $respondto[] = 'results';
        $respondto[] = 'resultkey';
        $respondto[] = 'resultkeys';
        $respondto[] = 'is_parent';
        $respondto[] = 'config';

        return in_array($name, $respondto);
    }

    public function __set($name, $model)
    {
        if ($model instanceof Model) {
            $associate  = $this->fireEvent('associate', array($name, $model));
            $fkey       = $associate['foreign_key'];

            if (empty($fkey)) {
                $exc = new Exception($this::$Name.'> Please define a foreign_key for '.$name);
                $exc->traceup();
                throw $exc;
            }//end if

            $var = ModelActions::Instance()->associate($this, $model, $associate);
            $var['fkey']    = $fkey;
            $var['model']   = $model;

            $this->associations[$name] = $var;

            return $this;
        }//end if

        /**
         * if associated model and value is null, remove association
         * on model only
         */
        if (array_key_exists($name, $this->associations) && is_null($model)) {
            ModelActions::Instance()->disassociate($this, $this->$name());
            $this->associations[$varname] = null;
            return true;
        }//end if

        return parent::__set($name);
    }

    /**
     *
     * @access
     * @var
     */
    public final function __construct(array $default_dbconnection)
    {
        $this->uid = Tools::Uuid();
        
        ModelActions::Instance()->load($this, $default_dbconnection);

        /**
         * isparent null means that the model
         * is not extended from another model
         *
         * isparent is boolean either false or tru
         * then the model is extended from a model
         */
        $this->isparent = null;
        $class  = get_class($this);
        $pclass = get_parent_class($class);

        if ($pclass != 'Core\\Model') $this->isparent = false;
        
        $this::$Name = ucfirst($this::$Name);
        if (empty($this::$Name)) {
            $name = explode('\\', $class);
            
            if (!is_null($this->isparent))
                $name = explode('\\', $pclass);
            
            $this::$Name = array_pop($name);

            if ($this::$Name == 'Base')
                $this::$Name = array_pop($name);
            
        }//end if

        $this->iterator = new ArrayIterator($this->results);
        parent::__construct();

    }

    /**
     *
     * @access
     * @var
     */
    protected function initialize()
    {

    }

    /**
     *
     * @access
     * @var
     */
    public final function sqlSelect($sql, $bindvars = array())
    {
        return ModelActions::Instance()->db($this)->query($sql, $bindvars);
    }

    /**
     *
     * @access
     * @var
     */
    public final function sqlExecute($sql, $bindvars = array())
    {
        return ModelActions::Instance()->db($this)->execute($sql, $bindvars);
    }

    /**
     *
     * @access
     * @var
     */
    public final function sqlEscape($param)
    {
        return ModelActions::Instance()->db($this)->escape($param);
    }

    /**
     *
     * @access
     * @var
     */
    public final function sqlBind($name, $value)
    {
        return ModelActions::Instance()->db($this)->bind($name, $value);
    }

    /**
     *
     * @access
     * @var
     *
     * you put all model associations here
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
    protected final function isAssociated($check)
    {
        if ($check instanceof Model)
            $check = $obj::$Name;

        if (array_key_exists($check, $this->associations)) return true;
        return false;
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
        $this->ismodified   = false;

        if ($include_cache === true)
            ModelActions::Instance()->clear($this);

        if (!empty($this->associations)) {
            foreach ($this->associations as $k => $v) {
                $v['model']->store('parentidx', null)->clear($include_cache);
            }// end foreach
        }//end if

    }

    /**
     *
     * @access
     * @var
     */
    public final function add(array $data = array())
    {
        array_push($this->results, array());
        end($this->results);

        $pos    = key($this->results);
        $self   = $this;

        $this->iterator = new ArrayIterator($this->results);
        $this->iterator->seek($pos);

        if (!empty($data))
            $this->from($data);

        $this->fireEvent('add', array($self));

        return $this;
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
             * check if $k is associated model name
             */
            if (array_key_exists($k, $this->associations)) {
                $this->associations[$k]['model']->from($v);
                continue;
            }//end if

            $this[$k] = $v;
        }//end foreach

        return $this;
    }

    /**
     *
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
     */
    public final function save()
    {

        if ($this->isEmpty())
            throw new Exception($this->class.'> No data to save');

        $self = $this;
        $this->fireEvent('save', array($self));

        /**
         * validate data
         */
        $this->validate();

        /**
         * prepare to write the data to db
         */
        $data = ModelActions::Instance()->save($this);

        foreach ($data as $k => $v) $this[$k] = $v;

        /**
         * loop to all associations and then call save
         * pass $data to method call
         */
        if (!empty($this->associations)) {

            foreach ($this->associations as $k => $v) {

                if ($v['model']->isEmpty()) continue;

                /**
                 * loop to all model results and then call save
                 */
                $v['model']->each(function($curr) use ($v){
                    $v['model']->save();
                });

            }// end foreach

        }//end if

        $this->ismodified = false;
        return $this;
    }

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
     */
    public final function remove()
    {
        $debug  = array();
        $self   = $this;

        $data   = $this->result;
        if (empty($data))
            throw new Exception($this->class.'> No data to remove');

        $this->fireEvent('remove', array($self));
        $this->erase();

        /**
         * loop to all associations and then call remove to all results
         */
        if (!empty($this->associations)) {

            foreach ($this->associations as $k => $v) {
                $model = $this->$k(true);

                if ($model->isEmpty()) continue;

                $model->each(function($data) use ($v) {
                    $v['model']->remove()->store('parentidx', null);
                });

            }// end foreach

        }//end if

        ModelActions::Instance()->remove($this);

        return $this;
    }

    /**
     * test documentation
     * @access
     * @var
     *
     */
    public final function find($query = '*', array $options = array())
    {
        if (empty($query)) {
            $exc = new Exception('query can\'t be empty');
            $exc->traceup();
            throw $exc;
        }//end if
        /**
         * this will give you access to change the
         * options parameters
         */
        $this->fireEvent('find', array($options));

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

        /**
         * if is_cached is null then clear cache results
         */
        if (is_null($this->is_cached)) {
            ModelActions::Instance()->clear($this);
            $this->is_cached = false;
        }//end if

        /**
         * find criteria
         */
        $retval = ModelActions::Instance()->find($this, $query, $options);

        // option count is true, thus it returns a numeric value
        if (is_numeric($retval))
            return $retval;

        if (empty($retval))
            return false;

        /**
         * distribute returned value to its correct property
         */
        $this->count        = $retval['count'];
        $this->numpage      = $retval['numpage'];
        $this->is_cached    = $retval['cached'];
        $loop               = $retval['results'];
        $schema             = $retval['schema'];

        /**
         * LOOP results
         */
        $hashfields = @array_keys($schema['fields'], 'hash');
        $resultkeys = array();
        $results    = array();
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

            $pkeys = $resultkeys;
            foreach ($includes as $k => $varname) {
                if (!array_key_exists($varname, $this->associations)) continue;

                $this->associations[$varname]['model']->clear();
                $this->associations[$varname]['eagerload']($pkeys);

            }// end foreach

        }//end if

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
    public final function collect($field_name)
    {
        $this->iterator = new ArrayIterator($this->results);
        $retval = array();
        foreach ($this as $k => $v)
            $retval[] = $v[$field_name];

        $this->iterator->rewind();

        return $retval;

    }

    protected function doValidations(array $validations)
    {
        ModelActions::Instance()->validate($this, $validations);
    }


    /**
     *
     * @access
     * @var
     */
    public final function parent($function)
    {
        if (!is_callable($function)) return false;
        
        if (is_null($this->isparent)) {
            $function($this);
            return $this;
        }//end if
        
        $this->isparent = true;
        $function($this);
        $this->isparent = false;

        return $this;
    }
}

