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


class Sanitize implements \ArrayAccess
{
    private static $Instance = null;

    public static $Debug = false;

    private $rules = array('numeric', 'char', 'bit', 'date', 'time', 'datetime', 'html', 'url', 'hash', 'regex', 'raw', 'hypenate');

    private $newrules = array();

    private $clean = array();
    private $key = null;
    private $idx = null;
    private $persist = false;
    private $profile = '__default__';
    
    public function __clone() {
        $this->profile = '__default__';
        $this->clean = array( '__default__' => array() );
        $this->newrules = array();
        $this->key = null;
        $this->idx = null;
    }

    public function __construct()
    {
        $this->clean = array( "{$this->profile}" => array() );
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
        if (self::$Debug === true) var_dump($this->clean);
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
            $exc->from = 'Sanitize';
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
            $exc->from = 'Sanitize';
            $exc->traceup();
            throw $exc;
        }//end if

        if (self::$Debug === true) echo "__set $name $errormessage {$this->idx} <br>";

        $this->clean[$this->profile][$this->key] = array( "{$name}" => array( $value ) );

        $this->key = null;
        $this->idx = null;
    }

    public final function offsetSet($offset, $value)
    {
        if (self::$Debug === true) echo "offsetset $offset {$value} {$this->idx}<br>";
        if (empty($this->key) || empty($this->idx)) return false;

        $this->clean[$this->profile][$this->key] = array( "{$this->idx}" => array( $value, "{$offset}" ) );

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
    public function __invoke(array $data)
    {
        if (empty($this->clean[$this->profile])) return $data;

        $retval = array();
        foreach ($data as $index => $value) {

            $cleaner = $this->clean[$this->profile][$index];
            if (is_null($cleaner) || empty($cleaner)) {
                $retval[$index] = $value;
                continue;
            }
            
            /**
             * skip raw rule
             */
            if (array_key_exists('raw', $cleaner)) {
                $retval[$index] = $value;
                continue;
            }//end if

            foreach ($cleaner as $rule => $settings) {

                list($default, $options) = $settings;
                
                if (array_key_exists($rule, $this->newrules)) {
                    $func   = $this->newrules[$rule];
                    $clone  = clone $this;
                    $value  = $func($value, $options, $clone, $default);
                    
                    $retval[$index] = $value;
                    continue;
                }//end if

                if (empty($value)) {
                    $value = $default;
                    if (is_callable($default)) {
                        $clone = clone $this;
                        $value = $default($clone, $options);
                    }//end if
                    
                    $retval[$index] = $value;
                    continue;
                }//end if

                switch ($rule) {
                    case 'numeric':
                        $rule   = null;
                        
                        $value = str_replace(',', '', $value) ;
                        if (!is_numeric($value)) {
                          $value = $default;
                          break;
                        }//end if

                        list($min, $max) = explode(',', $options);
                        $min = (integer) $min;
                        $max = (integer) $max;

                        if (!empty($min) && min($min, $value) == $value)
                            $value = $min;
                            
                        if (!empty($max) && max($max, $value) == $value)
                            $value = $max;
                        
                        $value = $value * 1;
                        continue;
                        break;

                    
                    case 'date':
                        $format = 'Y-m-d';
                        
                    case 'time':
                        if ($rule == 'time') $format = 'H:i:s';
                        
                    case 'datetime':
                        if ($rule == 'datetime') $format = 'Y-m-d H:i:s';

                        $rule = null;
                        
                        if (!empty($options)) $format = $options;

                        if (!strtotime($value)) $value = 'now';

                        $value = date($format, strtotime($value));
                        break;

                    case 'bit':
                        $rule = null;
                        
                        if (is_array($value)) {
                            $bit = 0;
                            foreach ($value as $k => $v) {
                                if (!is_numeric($v)) continue;
                                $bit = $bit ^ $v;
                            }// end foreach
                           $value = $bit;
                        }//end if

                        if (!is_numeric($value)) $value = $default;
                        break;
                    
                    case 'hash':
                        $rule = null;
                        /**
                         * hash sanitization
                         * if array is passed, it will serialize
                         * if string is passed, it will desrialize
                         */
                        $value = Tools::Hash($value);
                        break;

                    default:
                        break;
                }// end switch

                if (is_null($rule)) {

                    if ($value == array(null, null, null)) {
                        unset($retval[$index]);
                        continue;
                    }//end if

                    $retval[$index] = $value;
                    continue;
                }//end if

                /**
                 * html, char goes here
                 */
                $preg_replace = array(
                    // Remove invisible content
                    '@<head[^>]*?>.*?</head>@siu'       => ' ',
                    '@<style[^>]*?>.*?</style>@siu'     => ' ',
                    '@<script[^>]*?.*?</script>@siu'    => ' ',
                    '@<object[^>]*?.*?</object>@siu'    => ' ',
                    '@<embed[^>]*?.*?</embed>@siu'      => ' ',
                    '@<applet[^>]*?.*?</applet>@siu'    => ' ',
                    '@<noframes[^>]*?.*?</noframes>@siu'=> ' ',
                    '@<noscript[^>]*?.*?</noscript>@siu'=> ' ',
                    '@<noembed[^>]*?.*?</noembed>@siu'  => ' ',
                    // Add line breaks before and after blocks
                    '@</?((address)|(blockquote)|(center)|(del))@iu'            => "\n\$0",
                    '@</?((div)|(h[1-9])|(ins)|(isindex)|(p)|(pre))@iu'         => "\n\$0",
                    '@</?((dir)|(dl)|(dt)|(dd)|(li)|(menu)|(ol)|(ul))@iu'       => "\n\$0",
                    '@</?((table)|(th)|(td)|(caption))@iu'                      => "\n\$0",
                    '@</?((form)|(button)|(fieldset)|(legend)|(input))@iu'      => "\n\$0",
                    '@</?((label)|(select)|(optgroup)|(option)|(textarea))@iu'  => "\n\$0",
                    '@</?((frameset)|(frame)|(iframe))@iu'                      => "\n\$0",
                );

                if ($rule == 'html') {
                    $options = explode(',', $options);
                    unset($preg_replace['@<head[^>]*?>.*?</head>@siu']);
                    unset($preg_replace['@<meta[^>]*?/?>@siu']);
                    unset($preg_replace['@<applet[^>]*?.*?</applet>@siu']);
                    unset($preg_replace['@<noframes[^>]*?.*?</noframes>@siu']);
                    unset($preg_replace['@<noscript[^>]*?.*?</noscript>@siu']);

                    # remove javascript tag if allow_js is not found in the $options
                    if (!in_array('allow_js', $options))
                        unset($preg_replace['@<script[^>]*?.*?</script>@siu']);
                    
                }//end if

                $pattern    = array_keys($preg_replace);
                $replace    = array_values($preg_replace);
                
                $value      = preg_replace($pattern, $replace, $value);
                $value      = trim(strip_tags($value));
                
                if ($rule == 'char' && !empty($options)) {
                    $modifiers = explode(',', $options);
                    foreach ($modifiers as $k => $v) {
                        $v = trim($v);
                        if (is_numeric($v))     $value = substr($value, 0, $v);
                        if ($v == 'hypenate')   $value = Tools::Hyphenate($value);
                        if ($v == 'lowercase')  $value = strtolower($value);
                        if ($v == 'uppercase')  $value = strtoupper($value);
                    }// end foreach
                }//end if
                
                $retval[$index] = $value;
            }// end foreach

        }// end foreach

        if ($this->persist === false)
            $this->clean[$this->profile] = array();

        $this->key = null;
        $this->idx = null;

        return $retval;
    }

    /**
     *
     * @access
     * @var
     */
    public function data(array $param)
    {
        try {
            return $this($param);
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
    /**
     *
     * @access
     * @var
     */
    public function persist($param = true, $clear = false)
    {
        $this->persist = $param;

        if ($clear === true)
            $this->clean[$this->profile] = array();
    }

    /**
     *
     * @access
     * @var
     */
    public function profile($name)
    {
        if (empty($name)) $name = '__default__';
        $this->profile = $name;
        $this->persist = true;
    }

}
