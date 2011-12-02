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

/**
 *
 * @author
 *
 */
class Validate implements \ArrayAccess
{
    private static $Instance = null;
    
    public static $Debug = false;
    
    private $rules = array('require', 'char', 'numeric', 'date', 'email', 'url', 'username', 'password', 'regex', 'match');

    private $newrules = array();

    private $verify = array();
    private $key = null;
    private $idx = null;
    private $persist = false;
    private $profile = '__default__';

    private $regex = array(
        'email' => '/^[a-z0-9_\-\+]+(\.[_a-z0-9\-\+]+)*@([_a-z0-9\-]+\.)+([a-z]{2}|aero|arpa|biz|com|coop|edu|gov|info|int|jobs|mil|museum|name|nato|net|org|pro|travel)$/i',
        'url'   => '/^((https?):\/\/)?([a-z]([a-z0-9\-]*\.)+([a-z]{2}|aero|arpa|biz|com|coop|edu|gov|info|int|jobs|mil|museum|name|nato|net|org|pro|travel)|(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]))(\/[a-z0-9_\-\.~]+)*(\/([a-z0-9_\-\.]*)(\?[a-z0-9+_\-\.%=&amp;]*)?)?(#[a-z][a-z0-9_]*)?$/i',
        'username'  => '|^[0-9a-z_\.@\-/\+]{8,}$|i',
        'password'  => '|^[0-9a-z_\.@\-/\+]{8,}$|i',
        'char'      => '/^[\d\w ]+$/',
        'numeric'   => '/^[\d]+(\.[\d]+)?$/',
    );

    public function __construct()
    {
        $this->verify = array( "{$this->profile}" => array() );
    }

    public static function Instance()
    {
        if (is_null(self::$Instance)) {
            $klass = __CLASS__;
            self::$Instance = new $klass;
        }//end if

        return self::$Instance;
    }

    /**
     *
     * @access
     * @var
     */
    public function addRule($name, $function)
    {
        if (!preg_match('/^[a-z]+[a-z_0-9]*$/', $name)) {
            $exc = new Exception('rule name should must match the pattern /^[a-z][a-z_0-9]*$/');
            $exc->traceup();
            throw $exc;
        }//end if

        if (!is_callable($function)){
            $exc = new Exception('Second parameter must be a callable function');
            $exc->traceup();
            throw $exc;
        }//end if

        $this->rules[] = $name;
        $this->newrules[$name] = $function;
    }

    /**
     *
     * @access
     * @var
     */
    public function dump()
    {
        if (self::$Debug === true) var_dump($this->verify);
    }

    /**
     *
     * @access
     * @var
     */
    public function __get($rule)
    {
        if (!in_array($rule, $this->rules)) {
            $exc = new Exception("{$rule} rule not recognize. Accepted rules are: ". implode(' | ', $this->rules));
            $exc->from = 'Validate';
            $exc->traceup();
            throw $exc;
        }//end if

        if (self::$Debug === true) echo "__get $rule <br>";

        $this->idx = $rule;
        return $this;
    }
    
    public final function offsetGet($offset)
    {
        if (self::$Debug === true) echo "offsetget $offset {$this->idx}<br>";
        if (!is_null($this->idx)) {
            $this->idx = $offset;
            return $this;
        }//end if

        $this->key = $offset;
        $this->idx = 0;
        return $this;
    }

    public final function offsetExists($offset)
    {
        return array_key_exists($offset, $this->rules);
    }

    public function __set($name, $value)
    {
        if (empty($this->key)) return false;

        $exc = null;
        if (!in_array($name, $this->rules)) 
            $exc = new Exception("{$name} rule not recognize. Accepted rules are: ". implode(' | ', $this->rules));
           
        if (in_array($name, array('match', 'regex')))
            $exc = new Exception("please specify a key for {$name}");
            
        if (!empty($exc)) {
            $exc->from = 'Validate';
            $exc->traceup();
            throw $exc;
        }//end if

        if (self::$Debug === true) echo "__set $name $errormessage {$this->idx} <br>";

        $this->verify[$this->profile][$this->key][$name] = array( $value );

        $this->key = null;
        $this->idx = null;
    }

    public final function offsetSet($offset, $value)
    {
        if (self::$Debug === true) echo "offsetset $offset {$value} {$this->idx}<br>";
        if (empty($this->key) || empty($this->idx)) return false;
        
        $this->verify[$this->profile][$this->key][$this->idx] = array( $value, "{$offset}");

        $this->key = null;
        $this->idx = null;
        return true;
    }

    public final function offsetUnset($offset)
    {
        unset($this->rules[$offset]);
        return true;
    }

    /**
     *
     * @access
     * @var
     */
    public function __invoke(array $data, $select = null)
    {
        
        $error = array();

        if (empty($this->verify[$this->profile])) return true;

        $verify = $this->verify[$this->profile];

        foreach ($verify as $index => $settings ) {
            
            foreach ($settings as $rule => $value) {
                list($message, $options) = $value;

                /**
                 * if 2nd parameter is passed, run validation to selected
                 * fields only
                 */
                if (!is_null($select)) if (!in_array($index, $select)) continue;

                if ($rule == 'require') {
                    if (array_key_exists($index, $data) && $options == 'ifexists') continue;
                    //if (strlen($data[$index]) == 0 ) $error[] = $message;
                    if ( !isset($data[$index]) ) $error[] = $message;
                    continue;
                }//end if

                /**
                 * from here on out ... skip check if empty data or data is array
                 */
                if (empty($data[$index]) || is_array($data[$index])) continue;

                switch ($rule) {
                    
                    case 'email':
                    case 'url':
                    case 'username':
                    case 'password':
                        $break = true;
                    case 'numeric':
                        /**
                         * to successfully convert string to length
                         * remove , and do x1 operation
                         */
                        $data[$index] = str_replace(',', '', $data[$index]) * 1;
                        $len = $data[$index];
                        
                    case 'char':
                        if (empty($len)) $len = strlen($data[$index]);

                        /**
                         * get the regular explression value
                         */
                        $regex = $this->regex[$rule];
                        
                    case 'regex':
                        if (empty($regex)) $regex = $options;
                        
                        $check = preg_match($regex, $data[$index]);
                        if ($check == 0) $error[] = $message.' not passed '.$regex;

                        if ($break === true) break;

                        /**
                         * below here is for char and numeric rule only
                         * 
                         * min and max character length for char rule
                         * min and max value for numeric rule
                         */
                        list($min, $max) = explode(',', $options);
                        $min = (integer) $min;
                        $max = (integer) $max;

                        if (!empty($min) && min(($min - 1), $len) == $len) {
                            $error[] = $message;
                            break;
                        }//end if

                        if (!empty($max) && max(($max + 1), $len) == $len) {
                            $error[] = $message;
                            break;
                        }//end if

                        break;

                    case 'match':
                        if ($data[$index] != $data[$options]) $error[] = $message;
                        break;

                    case 'date':
                        $check = strtotime($data[$index]);
                        if ($check === false) $error[] = $message;
                        break;

                    default:
                        if (array_key_exists($rule, $this->newrules)) {
                            $func   = $this->newrules[$rule];
                            $check  = $func($data[$index], $options);
                            if ($check !== true) $error[] = $message;
                        }//end if
                        break;
                }// end switch

            }// end foreach

        }// end foreach

        if (!empty($error)) {
            $exc = new Exception($error);
            $exc->traceup();
            throw $exc;
        }//end if
        
        if ($this->persist === false)
            $this->verify[$this->profile] = array();
        
        $this->key = null;
        $this->idx = null;

    }

    /**
     *
     * @access
     * @var
     */
    public function data(array $param)
    {
        try {
            $this($param);
        } catch (Exception $exc) {
            $exc->traceup();
            throw $exc;
        }//end try
    }

    /**
     *
     * @access
     * @var
     */
    public function persist($param = true, $clear = false)
    {
        $this->persist = $param;

        if ($clear === true)
            $this->verify[$this->profile] = array();
    }

    /**
     *
     * @access
     * @var
     */
    public function profile($name = null)
    {
        if (empty($name)) {
            $name = '__default__';
            $this->persist = false;
            return true;
        }//end if
        
        $this->profile = $name;
        $this->persist = true;
    }

}