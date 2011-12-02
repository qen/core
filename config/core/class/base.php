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
use \ReflectionMethod;
use \ReflectionProperty;

class Base {

    protected $construct    = null;
    protected $respondto    = null;
    protected $class        = '';
    protected $extended     = array(
        'parent'    => array(),
        'children'  => array()
    );
    
    private static $respondto_classes = array();
    private $stored = array();
    private $events = array();

    public function __construct()
    {
        $this->class        = get_class($this);
        $this->respondto    = self::$respondto_classes[$this->class];
        
        $this->initialize();

        if (!empty($this->respondto)) return true;
        
        $this->fireEvent('constructStart');
        
        $respondto = array(
            'methods'       => array(),
            'properties'    => array(),
        );
        
        /**
         * get all class public methods and properties
         */
        $methods    = get_class_methods($this);
        $self       = $this;
        
        $include_respondto = function($reflek, $name, $idx) use(&$respondto, $self)
        {
            /**
             * automatically skip if name starts with __
             */
            if (preg_match('/^__/', $name)) return false;
            
            $self->fireEvent('constructReflections', array($reflek, $name, $idx));

            /**
             * skip static methods/propeties too
             */
            if ($reflek->isStatic()) return false;
            
            /**
             * skip if it's not public methods/properties too
             */
            if (!$reflek->isPublic()) return false;

            /**
             * skip if its tagged as
             * @access :noextend on the doc comment
             */
            $doc = $reflek->getDocComment();
            if (preg_match('/(@access).+(:noextend)/', $doc)) return false;

            /**
             * add to repondto $idx
             */
            $respondto[$idx][] = $name;
        };

        if (!empty($methods)){
            foreach($methods as $k=>$method){
                $reflek = new ReflectionMethod($this, $method);
                $include_respondto($reflek, $method, 'methods');
            }//end foreach
        }//end if

        /**
         * get all class properties
         */
        $vars = get_class_vars($this->class);
        unset($vars['construct']);
        unset($vars['extended']);
        unset($vars['class']);
        unset($vars['respondto']);
        unset($vars['respondto_classes']);
        unset($vars['stored']);
        unset($vars['events']);
        if (!empty($vars)) {
            foreach ($vars as $varname => $value) {
                $reflek = new ReflectionProperty($this, $varname);
                $include_respondto($reflek, $varname, 'properties');
            }// end foreach
        }//end if

        $this->respondto = $respondto;

        /**
         * save to base static vars
         */
        self::$respondto_classes[$this->class] = $respondto;

        $this->fireEvent('constructEnd');
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
     * @param <type> $attr
     * @param <type> $type
     * @access :noextend
     */
    public final function respondTo($attr, $type)
    {
        if (array_search($attr, $this->respondto[$type]) === false) return false;
        return true;
    }
    /**
     *
     * @access
     * @var
     */
    public final function store($var, $value)
    {
        $this->stored[$var] = $value;
        return $this;
    }

    /**
     *
     * @access
     * @var
     */
    public final function retrieve($var)
    {
        return $this->stored[$var];
    }

    /**
     *
     * @access
     * @var
     */
    public final function vars()
    {
        $args = func_get_args();
        if (count($args) == 1)
            return $this->$args[0];
        
        $this->$args[0] = $args[1];
        return $this->$args[0];
    }

    /**
     *  function
     * @param
     * @return
     * @access :noextend
     */
    public final function call($method, array $args=array())
    {
        return call_user_func_array(array(&$this, $method), $args);
    }// end function 

    /**
     *
     * @param <type> $method
     * @param <type> $args
     * @return <type> 
     */
    public function __call($method, $args)
    {
        if (empty($method)) return false;

        /**
         * prioritize to call aggregrated children
         */
        if (!empty($this->extended['children'])) {
            foreach ($this->extended['children'] as $aggr){
                if (!$aggr->respondTo($method, 'methods')) continue;
                return $aggr->call($method, $args);
            }//end foreach
        }//end if

        if (!empty($this->extended['parent']))
            return $this->extended['parent']->call($method, $args);

        $exc = new Exception($this->class."> failed to call '{$method}', does not exists.");
        $exc->traceup();
        $exc->traceup();
        throw $exc;
    }// end function 

    /**
     *
     * @param <type> $varname
     * @return <type> 
     */
    public function __get($varname){

        /**
         * prioritize to call aggregrated children
         */
        if (!empty($this->extended['children'])) {
            foreach ($this->extended['children'] as $aggr){
                if (!$aggr->respondTo($varname, 'properties')) continue;
                return $aggr->vars($varname);
            }//end foreach
        }//end if

        if (!empty($this->extended['parent']))
            return $this->extended['parent']->vars($varname);

        return null;
    }// end function 

    /**
     *
     * @param <type> $varname
     * @param <type> $value
     * @return <type> 
     */
    public function __set($varname, $value){

        /**
         * prioritize to call aggregrated children
         */
        if (!empty($this->extended['children'])) {
            foreach ($this->extended['children'] as $aggr){
                if (!$aggr->respondTo($varname, 'properties')) continue;
                return $aggr->vars($varname, $value);
            }//end foreach
        }//end if

        if (!empty($this->extended['parent']))
            return $this->extended['parent']->vars($varname, $value);

        /**
         * for consistency throw error if property does not exists
         * you must define the property to be used, NO DYNAMIC ASSIGNMENT!
         */
        throw new Exception($this->class."> '{$varname}' undefined property ");
        
    }// end function

    /**
     * @access :noextend
     */
    public final function extendDebug(){
        Debug::Dump('parent > '. get_class($this->extended['parent']));
        Debug::Dump('children #'. count($this->extended['children']), array_keys($this->extended['children']));
    }// end function

    /**
     * 
     * this will aggregate ALL PUBLIC METHODS and PROPERTIES
     * from the argument child obj to self obj,
     * and all PUBLIC METHODS and PROPERTIES in self obj to child obj
     * 
     * @access :noextend
     */
    public final function extend($obj, $is_parent = false)
    {
        $name = get_class($obj);
        
        if ($obj instanceof \Core\Base){
            
            if ($is_parent === false) {
                $this->extended['children'][$name] = $obj;
                $obj->extend($this, true);
            } else {
                $this->extended['parent'] = $obj;
            }//end if

            return true;
        }//end if
        
        throw new Exception($this->class."> failed to extend class[{$name}], must be an instance of \Core\Base.");
    }// end function 

    /**
     * check if the object has been aggregated by the class
     * @access :noextend
     */
    public final function extendedBy($class){

        if (!empty($this->extended['children'])){

            if (array_key_exists($class, $this->extended['children']))
                return $this->extended['children'][$class];
            
        }//end if

        if (!empty($this->extended['parent'])){
            
            if (array_key_exists($class, $this->extended['parent']))
                return $this->extended['parent'][$class];

        }//end if

        return false;
    }// end function

    /**
     *
     * @access
     * @var
     */
    public final function addEvent($name, $func)
    {
        if (!is_callable($func))
            throw new Exception($this->class.'> addEvent 2nd parameter must be a function');
        
        @next($this->events[$name]);
        $this->events[$name][] = $func;
        $idx = key($this->events[$name]);
        
        return $idx;
    }

    /**
     *
     * @access
     * @var
     */
    public final function removeEvent($name, $idx = null)
    {
        if (is_null($idx)) {
            unset($this->events[$name]);
            return true;
        }//end if
        
        unset($this->events[$name][$idx]);
        return true;
    }

    /**
     *
     * @access
     * @var
     */
    public final function fireEvent($name, array $args = array())
    {

        if (empty($this->events[$name])) return false;

        $retval = null;
        foreach ($this->events[$name] as $k => $func) {
            $val = call_user_func_array($func, $args);
            if (!empty($val)) $retval = $val;
        }//foreach
        
        return $retval;
    }
}