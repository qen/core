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

use \IteratorAggregate;
use \ArrayAccess;

use Core\App\Module;

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
    private $iterator       = array();
    private $isassociated   = false;
    private $options        = array();

    /**
     * collections of all associated models
     */
    private $associations   = array();
    private $associates     = array();

    /**
     * validate and sanitize objects
     */
    private $validate       = null;
    private $sanitize       = null;

    /**
     * these static variables are the only
     * required configuration for the class
     */
    public static $Name          = '';
    public static $Table_Name    = '';
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
        'join'          => array(),
        'select_fields' => array(), // numeric index is table field name, string index is custom field name
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
    private $changed    = array();
    
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
        //$cleaned = ModelActions::Instance()->sanitize($this, array( "{$offset}" => $value ));
        $sanitize   = $this->sanitize;
        $cleaned    = $sanitize(array("{$offset}" => $value));

        $idx = $this->iterator->key();
        $this->results[$idx][$offset] = $cleaned[$offset];
        $this->changed[] = $offset;
    }

    public final function offsetUnset($offset)
    {
        $idx = $this->iterator->key();
        unset($this->results[$idx][$offset]);
    }

    public final function getIterator()
    {
        if ($this->is_associated)
            $this->iterator->associated();
        else
            $this->iterator->object();
        
        return $this->iterator;
    }
    // ** end ** required iterator functions

    public final function __get($varname)
    {
        
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
            
            case 'is_associated':
                return $this->isassociated;
                break;

            case 'uid':
                return $this->uid;
                break;

            case 'count':
                return $this->count;
                break;

            case 'config':
                return ModelActions::Instance()->config($this);
                break;

            case 'join':
                $this->options['join'] = true;
                return $this;
                break;

            case 'load':
                $this->options['load'] = true;
                return $this;
                break;
            
            default:
                break;
        }// end switch

        /**
         * join associate model
         */
        if ($this->options['join'] === true) {
            $this->options['join'] = false;
            
            if (!array_key_exists($varname, $this->associations)) {
                $exc = new Exception("{$varname} is not a valid associate model");
                $exc->traceup();
                throw $exc;
            }//end if

            $join = $this->associations[$varname]['join'];
            $fkey = $this->associations[$varname]['fkey'];
            list($linktable, $linkkey) = $join['link'];
            list($selftable, $selfkey) = $join['self'];
            
            if (!empty($join['with'])) {
                list($jointable, $joinkey) = $join['with'];
                
                $this->options['find']['join']["{$jointable}"] = "`{$linktable}`.`{$linkkey}` = `{$jointable}`.`{$fkey}`";
                $this->options['find']['join']["{$selftable}"] = "`{$selftable}`.`{$selfkey}` = `{$jointable}`.`{$selfkey}`";
            }else{
                
            }//end if

            return $this;
        }//end if

        /**
         * eager loading
         */
        if ($this->options['load'] === true) {
            $this->options['load'] = false;

            if (!array_key_exists($varname, $this->associations)) {
                $exc = new Exception("{$varname} is not a valid associate model");
                $exc->traceup();
                throw $exc;
            }//end if

            $this->options['find']['includes'][] = $varname;
            return $this;
        }//end if

        /**
         * if varname exists in associates but not in associations,
         * initialize associate model
         */
        if (array_key_exists($varname, $this->associates) && !array_key_exists($varname, $this->associations)) 
            $this->associate($varname);

        /**
         * check if varname association access
         */
        if (array_key_exists($varname, $this->associations)) {

            /**
             * if the returned parentidx is exactly false, then the intended
             * result is to skip the query call and just return the associated model
             *
             * $this->AssociatedModel = false;
             * $this->AssociatedModel->each();
             *
             * you must set the value to true to enable the query call on access like
             * $this->AssociateModel = true;
             * $this->AssociatedModel['id'];
             */
            $parentidx = $this->associations[$varname]['model']->retrieve('parentidx');
            
            if (false === $parentidx) return $this->associations[$varname]['model']->associated();
            
            $idx = $this->iterator->key();

            /**
             * query call here
             */
            if ($idx !== $parentidx)
                $this->__set($varname, array());
            
            return $this->associations[$varname]['model']->associated();
        }//end if

        return parent::__get($varname);
    }

    public final function __isset($name)
    {
        if (array_key_exists($name, $this->associates)) return true;

        $respondto = array();
        $respondto[] = 'result';
        $respondto[] = 'uid';
        $respondto[] = 'count';
        $respondto[] = 'results';
        $respondto[] = 'resultkey';
        $respondto[] = 'resultkeys';
        $respondto[] = 'is_associated';
        $respondto[] = 'config';

        return in_array($name, $respondto);
    }

    public final function __set($name, $value)
    {
        
        /**
         * if varname exists in associates but not in associations,
         * initialize associate model
         */
        if (array_key_exists($name, $this->associates) && !array_key_exists($name, $this->associations))
            $this->associate($name);

        /**
         * check if ModelAssociates
         */
        if ($value instanceof ModelAssociates) {
            $this->associates[$name] = $value;
            return true;
        }//end if
            
        
        /**
         * if associated model
         */
        if (array_key_exists($name, $this->associations)) {
            $associate = $this->associations[$name];
            /**
             * if value is null remove association
             */
            if (is_null($value)) {
                $this->associations[$name] = null;
                unset($associate);
                unset($this->associations[$name]);
                return true;
            }//end if

            /**
             * association query call if value is array
             * 
             * this will allow the call like
             * $this->AssociateModel = array('limit' => 20);
             * $this->AssociateModel->each();
             * 
             */
            if (is_array($value)) {
                $idx        = $this->iterator->key();
                $args       = $value;

                $associate['model']->store('parentidx', $idx);

                /**
                 * if empty or null
                 */
                if ($this->isEmpty()) return true;
                
                /**
                 * get the current query value by default its the primary key
                 */
                //$query = $this[$associate['fkey']];
                $query = $this->resultkey;

                /**
                 * if options is exactly all, pass all results query
                 */
                if ('all' == $args[0]) {
                    $allresults = $this->results;
                    $query = array();
                    foreach ($allresults as $k => $v) {
                        $query[] = $v[$associate['query']];
                    }// end foreach
                }//end if

                /**
                 * otherwise use each function to modify all returned
                 * value from the associated model
                 */
                if (!empty($query)) {

                    $options = $associate['find'];
                    if (is_array($args[0]) && !empty($args[0]))
                        $options = Tools::ArrayMerge($associate['find'], $args[0]);
                    
                    $options['search'] = $associate['fkey'];
                    
                    /**
                     * ignore error if there is no results
                     */
                    try {
                        $associate['model']->find($query, $options);
                    } catch (Exception $exc) {
                    }//end try

                }//end if

                return true;
            }//end if

            /**
             * value is exactly false, this is like disable the query call on access
             */
            if (false === $value) {
                $associate['model']->store('parentidx', false);
                return true;
            }//end if

            /**
             * value is exactly true, enable query call on access
             */
            if (true === $value) {
                $associate['model']->store('parentidx', true);
                return true;
            }//end if

            return true;
        }//end if

        return parent::__set($name, $value);
    }

    /**
     * CONSTRUCTOR
     */
    public final function __construct(array $default_dbconnection)
    {

        $this->uid = Tools::Uuid();
        $this->iterator = new ModelIterator($this);
        
        ModelActions::Instance()->load($this, $default_dbconnection);
        
        $this->validate = new Validate;
        $this->sanitize = new Sanitize;

        parent::__construct();

        /**
         * sanitization setup 
         */
        $schema = ModelActions::Instance()->getSchema($this);
        
        /**
         * default sanitization for field types
         */
        $sanitize = $this->sanitize;
        foreach ($schema['fields'] as $name => $type) {
            switch ($type) {
                case 'date':
                    $sanitize["{$name}"]->date = 'now';
                    break;

                case 'time':
                    $sanitize["{$name}"]->time = 'now';
                    break;

                case 'datetime':
                    $sanitize["{$name}"]->datetime = 'now';
                    break;

                case 'numeric':
                    $sanitize["{$name}"]->numeric = 0;
                    break;

                default:
                    if (preg_match("/char\(([0-9].+)\)/is", $type, $matches)) {
                        $length = is_numeric($matches[1])? $matches[1] : 255;
                        $sanitize["{$name}"]->char[$length] = '';
                        continue;
                    }//end if
                    break;
            }// end switch
        }// end foreach

        $sanitize->persist();
        $this->sanitize($sanitize);
        $sanitize->persist();

    }

    public final function __toString() {
        list(, $name) = explode("Modules\\", $this->class);
        $name = str_replace("\\", '.', $name);
        return $name;
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
    public final function associated()
    {
        $this->isassociated = true;
        return $this;
    }

    /**
     *
     * @access
     * @var
     */
    private function associate($varname, $doassociate = array())
    {

        if (!empty($doassociate['has_many']) || !empty($doassociate['belongs_to'])) 
            $this->associates[$varname] =  $doassociate;
        
        /**
         * has_many association will trigger save and delete
         */
        $module_model   = $this->associates[$varname]['has_many'];
        $associateby    = 'has_many';
        if (empty($module_model)) {
            /**
             * belongs_to will NOT trigger save and delete, it will only query the data associated,
             * foreign_key definitions for belongs_to must be an existing field name of the current class
             */
            $module_model   = $this->associates[$varname]['belongs_to'];
            $associateby    = 'belongs_to';
        }//end if

        /**
         * skip model instantiation if object model is passed
         */
        if (!($module_model instanceof Model)) {

            list($module_name, $model_klass) = explode('.', $module_model, 2);
            if (empty($module_name) || empty($model_klass)) {
                $exc = new Exception("Failed to do associations with {$varname}, module={$module_name} and model={$model_klass}");
                $exc->traceup()->traceup();
                throw $exc;
            }//end if

            $module_name = trim($module_name);
            $model_klass = trim($model_klass);

            try {
                $model = Module::$module_name($model_klass);
                $this->associates[$varname]['initialize']($model);
            } catch (Exception $exc) {
                $exc = new Exception("Failed to instantiate {$varname}, module={$module_name} and model={$model_klass}");
                $exc->traceup();
                throw $exc;
            }//end try
            
        }//end if

        $associate_options = array(
            'associateby'   => $associateby,
            'foreign_key'   => $this->associates[$varname]['foreign_key'],
            'foreign_table' => '',
            'find_options'  => $this->associates[$varname]['find_options'],
        );

        /**
         * check for table definition in foreign_key value
         */
        list($ftable, $fkey) = explode('.', $associate_options['foreign_key']);
        if (empty($fkey)) {
            $fkey = $ftable; // the only passed value is the field name, expect the foreign key
            $ftable = $model::$Table_Name; // set the model table name as the foreign table
        }//end if

        $associate_options['foreign_key'] = trim($fkey);
        $associate_options['foreign_table'] = trim($ftable);

        if (empty($associate_options['foreign_key'])) {
            $exc = new Exception($this::$Name.'> Please define a foreign_key for '.$varname);
            $exc->traceup();
            throw $exc;
        }//end if

        $this->fireEvent('associate', array($varname, $model));

        $this->associations[$varname] = ModelActions::Instance()->associate($this, $model, $associate_options);

        /**
         * fireEvent associatedby
         */
        $self = $this;
        if ($associateby == 'has_many') 
            $model->fireEvent('belongs_to', array($model::$Name, $self));
        else
            $model->fireEvent('has_many', array($model::$Name, $self));


        return $this;
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
     */
    public final function clear($include_cache = false)
    {
        $this->results      = array();
        $this->resultkeys   = array();
        $this->count        = 0;
        $this->numpage      = array();
        $this->changed      = array();
        $this->isassociated = false;

        if ($include_cache === true)
            ModelActions::Instance()->clear($this);

        if (!empty($this->associations)) {
            foreach ($this->associations as $k => $v) {
                $v['model']->store('parentidx', null)->clear($include_cache);
            }// end foreach
        }//end if

        return $this;
    }

    /**
     *
     * @access
     * @var
     */
    public final function object()
    {
        $this->isassociated = false;
        return $this;
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
        
        $position = key($this->results);
        $this->iterator->seek($position);

        if (!empty($data))
            $this->from($data);

        $self = $this;
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
    protected function validate(Validate $validate)
    {
        
    }

    protected function sanitize(Sanitize $sanitize)
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
        return !empty($this->changed);
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

        /**
         * loop to association and find
         * belongs_to associate
         */
        if (!empty($this->associations)) {

            foreach ($this->associations as $k => $v) {

                if ($v['model']->isEmpty() || $v['mode'] == 'has_many') continue;
                /**
                 * automatically assign the foreign_key to the primary key
                 * of the belongs_to associate model
                 */
                $associate = $this->associates[$k];
                $this["{$associate['foreign_key']}"] = $v['model']->resultkey;
            }// end foreach

        }//end if

        $pkey       = ModelActions::Instance()->pkey($this);
        $raw_idx    = $this->iterator->key();

        $self = $this;
        $this->fireEvent('save', array($self, $this->sanitize));

        /**
         * no changed data, return here
         */
        if (!empty($this->changed)) {

            /**
             * quite paranoid sanitization of data again
             */
            $sanitize = $this->sanitize;
            $this->sanitize($sanitize);
            $sanitize->persist();

            $clean = $sanitize($this->results[$raw_idx]);
            foreach ($clean as $k => $v) $this->results[$raw_idx][$k] = $v;

            $save_data = $this->results[$raw_idx];
            /**
             * if pkey is passed then check if there are changed data
             * and include field to update for those that was changed only
             */
            if (!empty($save_data[$pkey])) {

                $save_data          = array();
                $save_data[$pkey]   = $this->results[$raw_idx][$pkey];
                foreach ($this->changed as $k => $fieldname)
                    $save_data[$fieldname] = $this->results[$raw_idx][$fieldname];

            }//end if

            /**
             * validate raw data
             */
            $validate = $this->validate;

            /**
             * default validation
             *
             * automatically include primary keys that is not
             * for autoincrement as required if not included
             */
            $schema = ModelActions::Instance()->getSchema($this);
            foreach ($schema['pkeys'] as $k => $v) {

                /**
                 * skip auto increment primary key
                 */
                if ($v['auto_increment'] === true)  continue;

                /**
                 * skip require unique key if it was NOT changed
                 */
                if (!in_array($v['name'], $this->changed)) continue;

                /**
                 * skip unique key requirement if primary key is NOT EMPTY
                 */
                if (!empty($save_data[$pkey])) continue;

                $validate["{$v['name']}"]->require = "{$v['name']} is required";
                $save_data["{$v['name']}"] = $this->results[$raw_idx]["{$v['name']}"];

            }// end foreach

            $this->validate($validate);
            try {
                $validate($this->results[$raw_idx], $this->changed);
            } catch (Exception $exc) {
                $exc->traceup();
                throw $exc;
            }//end try

            /**
             * prepare to write the data to db
             */
            $data = ModelActions::Instance()->save($this, $save_data);

            //foreach ($data as $k => $v) $this[$k] = $v;
            foreach ($data as $k => $v) $this->results[$raw_idx][$k] = $v;
            
        }//end if
        

        /**
         * loop to all associations and then call save
         * pass $data to method call
         */
        if (!empty($this->associations)) {
            $self = $this;
            foreach ($this->associations as $k => $v) {
                
                if ($v['model']->isEmpty() || $v['mode'] == 'belongs_to') continue;
                
                /**
                 * loop to all model results and then call save
                 */
                $v['model']->each(function($curr) use ($v, $self){
                    $v['model']["{$v['fkey']}"] = $self->resultkey;
                    $v['model']->associated()->save();
                });

            }// end foreach

        }//end if

        $this->changed      = array();
        $this->isassociated = false;
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
         * loop to all has_many associations and then call remove to all results
         */
        if (!empty($this->associations)) {

            foreach ($this->associations as $k => $v) {

                if ($v['mode'] == 'belongs_to') continue;

                $this->__set($k, array());

                if ($this->$k->isEmpty()) continue;

                $v['model']->each(function($data) use ($v) {
                    $v['model']->associated()->remove();
                })->store('parentidx', null);;

                $this->$k = null;
                
            }// end foreach

        }//end if

        ModelActions::Instance()->remove($this);

        $this->isassociated = false;
        return $this;
    }

    /**
     * 
     * @access
     * @var
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

        if (is_array($this->options['find']) && !empty($this->options['find'])) 
            $options = Tools::ArrayMerge($options, $this->options['find']);

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

        $this->changed      = array();
        $this->isassociated = false;
        $this->options['find'] = array();
        return $this;
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
        $this->iterator->rewind();

        $isassociated = $this->isassociated;
        foreach ($this as $k => $v) {
            $data = $this->results[$k];
            $this->isassociated = $isassociated;
            $each($data);
        }// end foreach

        $this->iterator->rewind();
        
        return $this;
    }

    /**
     *
     * @access
     * @var
     */
    public final function collect($field_name)
    {
        $this->iterator->rewind();
        
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
    public static final function HasMany($class, $foreign_key, array $find_options = array(), $initialize = null)
    {
        return new HasMany($class, $foreign_key, $find_options, $initialize);
    }

    /**
     *
     * @access
     * @var
     */
    public static final function BelongsTo($class, $foreign_key, array $find_options = array(), $initialize = null)
    {
        return new BelongsTo($class, $foreign_key, $find_options, $initialize);
    }

    /**
     *
     * @access
     * @var
     */
    public function toArray()
    {
        $idx = $this->iterator->key();
        return $this->results[$idx];
    }

}


/**
 *
 * Abstract Model Associates
 * 
 */
abstract class ModelAssociates implements ArrayAccess
{

    protected $parameters = array(
        'class'         => null,
        'foreign_key'   => null,
        'find_options'  => null,
        'initialize'    => null,
    );

    abstract protected function type();

    public function offsetExists ( $offset )
    {
        if ($offset == $this->type()) return true;

        $keys = array('foreign_key', 'find_options', 'initialize');
        if (in_array($offset, $keys)) return true;

        return false;
    }
    
    public function offsetGet ( $offset ) {
        if (!$this->offsetExists($offset)) return null;
        
        if ($offset == $this->type()) return $this->parameters['class'];

        return $this->parameters[$offset];
    }

    public function offsetSet ( $offset , $value ) { }
    public function offsetUnset ( $offset ) {}

    public function __construct($class, $foreign_key, $find_options, $initialize = null)
    {
        $this->parameters['class']          = $class;
        $this->parameters['foreign_key']    = $foreign_key;
        $this->parameters['find_options']   = $find_options;
        $this->parameters['initialize']     = $find_options;

        if (!is_callable($initialize)) $initialize = function(){};

        $this->parameters['initialize']     = $initialize;
    }

}

class HasMany extends ModelAssociates {

    public function type() { return 'has_many'; }
}

class BelongsTo extends ModelAssociates {
    public function type() { return 'belongs_to'; }
}

