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

use Core\App\Path;
use Core\App\Config as AppConfig;

class Params implements \ArrayAccess
{
    private static $Instance = null;
    
    private $settings = array(
        'cookies' => array(
            'expire' => 1,
            'path' => '/',
            'domain' => null,
        ),

        'session' => array(
            'name' => '~sid'
        )
    );

    private $validate = null;
    private $sanitize = null;

    private $sources    = array('get', 'post', 'files', 'session', 'cookie', 'flash', 'uri', 'config', 'server');

    private $cookies    = array();
    private $flashvars  = array();
    private $is_session = false;

    private $flashstore = '@@flash.store@@';

    private $data       = array();
    private $assigned   = array() ;
  
    private function __construct()
    {
        $this->validate = new Validate;
        $this->sanitize = new Sanitize;
        
        foreach ($_COOKIE as $k => $v) $_COOKIE[$k] = Tools::Hash($v);

        $this->cookies      = $_COOKIE;        
    }

    public static function Instance()
    {
        if (is_null(self::$Instance)) {
            $klass = __CLASS__;
            self::$Instance = new $klass;
        }//end if

        return self::$Instance;
    }

    public function &__get($name)
    {
        switch ($name) {
            case 'get':
                $retval = $_GET;
                return $retval;
                break;

            case 'post':
                $retval = $_POST;
                return $retval;
                break;
            
            case 'files':
                $retval = $_FILES;
                return $retval;
                break;

            case 'server':
                $retval = $_SERVER;
                return $retval;
                break;

            case 'cookie':
                return $_COOKIE;
                break;
            
            case 'session':
                if ($this->is_session === false) {
                    return null;
                    $exc = new Exception("Session is not started");
                    $exc->traceup();
                    throw $exc;
                }
                return $_SESSION;
                break;

            case 'flash':
                if ($this->is_session === false) {
                    return null;
                    $exc = new Exception("Session is not started");
                    $exc->traceup();
                    throw $exc;
                }
                return $_SESSION[$this->flashstore];
                break;

            case 'uri':
                $value = Path::$Uri;
                return $value;
                break;
            
            case 'config':
                $value = AppConfig::Value();
                return $value;
                break;

            default:
                if (!in_array($name, $this->assigned)) return null;
                return $this->assigned[$name];
                break;
        }// end switch

    }

    public function __set($name, $value)
    {
        $this->assigned[$name] = $value;
        return true;
    }

    public final function offsetExists($offset)
    {
        if (!in_array($offset, $this->sources)) return false;
        return true;
    }

    public final function offsetGet($offset)
    {
        return $this->__get($offset);
    }

    public final function offsetSet($offset, $value)
    {
        return null;
    }

    public final function offsetUnset($offset)
    {
        return null;
    }

    /**
     *
     */
    public function session($name = '~sid')
    {
        $sid = session_id();
        if (empty($sid)) {

            if (is_string($value) && !empty($value))
                $this->settings['session']['name'] = "~{$value}";

            session_name($this->settings['session']['name']);
            session_start();

            if (empty($_SESSION[$this->flashstore]))
                $_SESSION[$this->flashstore] = array();

            $this->flashvars = $_SESSION[$this->flashstore];
            
            $sid = session_id();
            $this->is_session = true;
        }//end if

        if ($value == 1 && is_numeric($value)) {
            session_regenerate_id();
            $sid = session_id();
        }//end if
        
        return array($sid, $this->settings['session']['name']);
    }

    public function cookies($settings = array())
    {
        foreach ($this->settings['cookies'] as $k => $v) {
            if (empty($settings[$k])) continue;
            $this->settings['cookies'][$k] = $settings[$k];
        }// end foreach
    }

    /**
     *
     * @access
     * @var
     */
    public function flushFlash()
    {
        /**
         * loop to flashvars and compare with flashstore
         */
        foreach ($this->flashvars as $k => $v) {
            /**
             * if variable value didn't match skip
             */
            if ($_SESSION[$this->flashstore][$k] != $v) continue;

            /**
             * clear variable if it matched
             */
            unset($_SESSION[$this->flashstore][$k]);
        }// end foreach

    }

    /**
     *
     * @access
     * @var
     */
    public function validate($func)
    {
        if (!is_callable($func)) return false;
        
        $validate = $this->validate;

        $func($validate);

        if (!empty($this->data)) {
            try {
                $validate($this->data);
            } catch (Exception $exc) {
                $exc->traceup();
                throw $exc;
            }//end try
        }//end if

        return true;
    }

    /**
     *
     * @access
     * @var
     */
    public function sanitize($func)
    {
        if (!is_callable($func)) return false;

        $sanitize = $this->sanitize;

        $func($sanitize);

        if (!empty($this->data)) {
            try {
                $retval = $sanitize($this->data);
            } catch (Exception $exc) {
                $exc->traceup();
                throw $exc;
            }//end try
        }//end if

        return $retval;
    }

    /**
     *
     * @access
     * @var
     */
    public function from($param)
    {
        $this->data = array();
        
        if (is_array($param)) {
            $this->data = $param;
            return true;
        }//end if

        if (is_string($param)) {
            $traverse   = explode('.', $param);
            $retval     = null;
            foreach ($traverse as $k => $v) {
                if ($k == 0) {
                    $retval = $this->$v;
                    continue;
                }//end if
                
                if (is_null($retval)) break;

                $retval = $retval[$v];

                if (!is_array($retval)) break;
                
            }// end foreach

            $this->data = (empty($retval)) ? array() : $retval;
        }//end if
        
        return $this;
    }

    /**
     *
     * @access
     * @var
     */
    public function flushCookies()
    {
        /**
         * loop to this.cookies and compare it with $_COOKIE
         */
        $config = $this->settings['cookies'];

        /**
         * check session name here
         */
        if ($this->is_session === true) {
            $session_name = $this->settings['session']['name'];
            unset($this->cookies[$session_name]);
            unset($_COOKIE[$session_name]);
        }//end if

        foreach ($_COOKIE as $cuky_name => $value) {

            // @todo fix params flush cookies
            if (is_null($value)) {
                setcookie($cuky_name, null, time() - 3600, $config['path']);
                continue;
            }//end if

            if ($value == $this->cookies[$cuky_name]) continue;
            
            $cuky_value = Tools::Hash($value);
            
            if (Controller::$Debug === true)
                logger(array($cuky_name, $cuky_value, $config['expire'], $config['path']), 'Params :: SetCookie');
            
            setcookie($cuky_name, $cuky_value, time() + (3600 * $config['expire']), $config['path']);

            $this->cookies[$cuky_name] = $value;
        }// end foreach
        
    }

    /**
     *
     * @access
     * @var
     */
    public function debug()
    {
        $args = func_get_args();
        echo "<pre>";
        var_export($args);
        echo "</pre>";
        exit;
    }

    /**
     *
     * @access
     * @var
     */
    public function dump()
    {
        $args = func_get_args();
        echo "<pre>";
        var_dump($args);
        echo "</pre>";
        exit;
    }
    
}//end class

