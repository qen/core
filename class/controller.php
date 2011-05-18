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
 * @date 2010.10.19
 *
 */
namespace Core;

use \ArrayAccess;
use Core\Tools;
use Core\App;
use Core\App\Path;
use Core\App\Route;
use Core\App\Config as AppConfig;

/**
 *
 * @author
 * we moved the static helper functions ( to Controllers namespace ) as it limits
 * the number of available url name for the controller to respond to
 *
 */
//class Controller //extends Base
class Controller implements ArrayAccess
{

    public static $Method    = null;

    private $datasource     = '';
    private $datasources    = array('@session', '@flash', '@notify', '@post', '@get', '@cookie', '@uri', '@server', '@template', '@config');
    private $datasessions   = array('@session', '@flash', '@notify');
    private $class          = '';

    /**
     * @todo settings use for post/get, do data validate or sanitize
     */
    public $options = array(
        '@post'     => array(),
        '@get'      => array(),
        '@cookie'   => array(
            'expire'    => 1,
            'path'      => ''
        )
    );

    /**
     *
     * @access
     * @var
     */
    public function __construct()
    {
        $this->class = get_class($this);
    }

    /**
     * app functions goes here
     */    
    public function __invoke($name, $value = null, $arg = null)
    {

        switch ($name) {

            case 'redirect':
                $uri = is_null($value)? '': $value;
                $get = array();
                if (!empty($arg) && is_array($arg))
                    $get = $arg;

                $this->doFinalizeController();
                App\redirect($uri, $get);
                break;

            /**
             * if value is string and session is not started,
             * start a session with the value string
             *
             * if session is started and value is numeric equals to 1
             * regenerate session id
             */
            case 'session':
                $sid = \session_id();
                if (empty($sid)) {

                    if (is_string($value) && !empty($value))
                        \session_name($value);

                    \session_start();

                    return \session_id();
                }//end if

                if ($value == 1 && is_numeric($value)) {
                    \session_regenerate_id();
                    $sid = \session_id();
                }//end if
                
                return $sid;
                break;

            case 'render_content':
                $this->doFinalizeController();
                /**
                 * do buffer clean here since the content is raw
                 */
                while (@ob_end_clean());
                
                $config = array(
                    'attachment' => null,
                    'nocache'    => false,
                    'type'       => 'text/plain'
                );
                
                if (is_array($arg) && !empty($arg)) {
                    foreach ($arg as $k => $v) {
                        if (empty($v)) continue;

                        if (!array_key_exists($k)) {
                            $trace = debug_backtrace();
                            $caller = $trace[0];
                            throw new Exception("{$k} not recognized as option, available is (attachment, nocache, type) | {$caller['file']} @line {$caller['line']}");
                        }//end if

                        $config[$k] = $v;
                    }// end foreach
                }//end if

                if (is_string($arg) && !empty($arg) )
                    $config['type'] = $arg;

                if (true === $config['nocache']) {
                    // fix for IE catching or PHP bug issue
                    header("Pragma: no-cache");
                    header("cache-control: private"); //IE 6 Fix
                    header("cache-Control: no-store, no-cache, must-revalidate");
                    header("cache-Control: post-check=0, pre-check=0", false);
                    header("Expires: Thu, 24 May 2001 05:00:00 GMT"); // Date in the past
                }//end if

                if (is_string($config['attachment']) && !empty($config['attachment']))
                    header("Content-Disposition: attachment; filename={$config['attachment']}");
                else
                    header("Content-Disposition: inline;");
                
                header('Content-type: '. $config['type']);
                if (is_file($value))
                    readfile($value);
                else
                    echo $value;
                
                App\http_status(200);
                break;

            case 'render_json':
                $this->doFinalizeController();
                
                $args = func_get_args();
                
                list($dump, $status, $result, $notice, $response) = $args;
                
                echo App\json($status, $result, $notice, $response);
                App\http_status(200);
                break;

            case 'render_text':
                $this->doFinalizeController();
                
                if (!empty($arg) || is_string($arg)) 
                    header("Content-Type: {$arg}");
                
                echo $value;
                App\http_status(200);
                break;

            case 'http_status':
                $this->doFinalizeController();
                App\http_status($value, $arg);
                break;

            case 'http_modified':
                $lastmodified   = $value;
                $cachelifetime  = '+24 days';
                
                if (is_string($arg) && !empty($arg))
                    $cachelifetime = $arg;

                if (is_string($lastmodified))
                    $lastmodified = strtotime($lastmodified);

                header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastmodified) . ' GMT');
                header('Cache-Control: max-age='.(strtotime($cachelifetime)).', must-revalidate, public');

                $headers = App\http_headers();
                if (isset($headers['If-Modified-Since'])) {
                    $modifiedSince = explode(';', $headers['If-Modified-Since']);
                    $modifiedSince = strtotime($modifiedSince[0]);

                    if (max($lastmodified, $modifiedSince) ==  $modifiedSince){
                        $this->doFinalizeController();
                        App\http_status('304 Not Modified');
                        exit;
                    }//end if
                }//end if
                break;
                
            case 'debug':
                $trace = debug_backtrace();
                $caller = $trace[0];
                var_export($caller);
                
                Debug::Dump('Sessions', $_SESSION);
                Debug::Dump('Template', View::Template());
                Debug::Dump('Route Count', Route::$Count);
                Debug::Dump('Controller Class', $this->class);
                Debug::Dump('Uri', Path::Uri());
                Debug::Dump('Cookies', $_COOKIE);
                Debug::Dump('Assignments', View::Assign(null, null));
                
                break;
            
            case 'dump':
                while (@ob_end_flush());
                $trace = debug_backtrace();
                $caller = $trace[0];
                var_export($value);
                echo "\n<br>";
                var_export("{$caller['file']} @line {$caller['line']}");
                exit;
                break;

            case 'logger':
                $trace = debug_backtrace();
                $caller = $trace[0];
                App\logger(array("{$caller['file']} @line {$caller['line']}", $value), $this->class);
                break;

            case 'flash_expire':

                if ('vars' == $value) {
                    $store = $_SESSION['flash.store'];

                    if (is_array($store) && !empty($store)) {
                        foreach ($_SESSION['flash.vars'] as $k => $v) {
                            if (in_array($k, $store)) continue;
                            unset($_SESSION['flash.vars'][$k]);
                        }// end foreach
                    }//end if

                    $_SESSION['flash.store'] = array();
                }//end if

                if ('notify' == $value) {
                    $_SESSION['flash.notify'] = array();
                }//end if
                
                break;
                
            case 'set_view_dir':
                Path::ViewDir($value);
                break;

            case 'validate':
                Tools::Validate($value, $arg);
                return true;
                break;

            default:
                $trace = debug_backtrace();
                $caller = $trace[0];
                throw new Exception("Controller function {$name} is not recognized | {$caller['file']} @line {$caller['line']}");
                break;
        }// end switch

    }

    public final function offsetSet($offset, $value) {

        /**
         * check for short notation
         * @datasource.offset
         */
        list($datasource, $varname) = explode('.', $offset, 2);
        if (!empty($varname) && in_array($datasource, $this->datasources)) {
            $this->datasource = $datasource;
            $offset = $varname;
        }//end if

        if (!empty($this->datasource) && in_array($this->datasource, $this->datasessions)) {
            $sid = $this('session');
            if (empty($sid))
                throw new Exception('Please initialize controller session, $self("session")');
            
        }//end if
        
        $datasource = $this->datasource;
        $this->datasource = '';
        
        switch ($datasource) {
            case '@session':
                $_SESSION['vars'][$offset] = $value;
                break;

            case '@flash':
                if (empty($_SESSION['flash.store']))
                    $_SESSION['flash.store'] = array();
                
                if (@!in_array($offset, $_SESSION['flash.store']))
                    $_SESSION['flash.store'][] = $offset;
                
                if (empty($_SESSION['flash.vars']))
                    $_SESSION['flash'] = array();
                
                $_SESSION['flash.vars'][$offset] = $value;
                break;
            
            case '@notify':
                //$pathuri = Path::Uri('full');
                
                if (!is_array($_SESSION['flash.notify'][$offset]))
                    $_SESSION['flash.notify'][$offset] = array();

                if (!is_array($value))
                    $value = array($value);

                foreach ($value as $k => $v) {
                    if (@in_array($v, $_SESSION['flash.notify'][$offset]))
                        continue;
                    $_SESSION['flash.notify'][$offset][] = $v;
                }// end foreach
                break;

            case '@cookie':
                $cuky_name  = $offset;
                $cuky_value = $value;
                $config     = $this->options['@cookie'];
                
                $expire = $config['expire'];
                $path   = $config['path'];

                if (empty($expire) || !is_numeric($expire))
                    $expire = 1;

                if (empty($path))
                    $config['path'] = Path::Uri('root');

                $config['expire'] = time() + (3600 * $expire);
                if (is_null($cuky_value))
                    $config['expire'] = time() - 3600;
                else
                    $cuky_value = Tools::Hash($cuky_value, array('mode' => 'encode'));

                $_COOKIE[$cuky_name] = $cuky_value;
                setcookie($cuky_name, $cuky_value, $config['expire'], $config['path']);
                break;

            case '@template':
                if ('default' == $offset) {
                    View::DefaultTemplate($value);
                    break;
                }//end if

                if ('render' == $offset) {
                    $template = $value;
                    /**
                     * if / is not defined in the template
                     * then append relative path
                     */
                    if (!preg_match('|^/|', $template)){
                        $uri    = Path::Uri();
                        $reldir = str_replace($uri['root'], '', $uri['path']);
                        $shown  = View::Template();

                        /**
                         * see if there is an existing template assigned
                         * if true use the dir from the template assigned
                         */
                        if (!empty($shown)) {
                            $pathinfo   = pathinfo($shown);
                            $reldir     = "{$pathinfo['dirname']}/{$pathinfo['filename']}";
                            
                        }//end if

                        $template = $reldir."/{$template}";
                    }//end if

                    View::Template($template);
                    break;
                }//end if

                $trace = debug_backtrace();
                $caller = $trace[0];
                
                throw new Exception("Valid offset for @template is render and default | {$caller['file']} @line {$caller['line']}");
                break;
                
            default:
                View::Assign($offset, $value);
                break;
                
        }// end switch

        return true;
    }

    public final function offsetGet($offset) {
        
        /**
         * check for short notation
         * @datasource.offset
         */
        list($datasource, $varname) = explode('.', $offset, 2);
        if (!empty($varname) && in_array($datasource, $this->datasources)) {
            $this->datasource = $datasource;
            $offset = $varname;
        }//end if

        if (in_array($offset, $this->datasources) && empty($this->datasource))  {
            $this->datasource = $offset;
            return $this;
        }//end if

        $datasource = $this->datasource;
        $this->datasource = '';
        
        switch ($datasource) {
            case '@session':
                if ($offset == '*') return $_SESSION['vars'];
                return $_SESSION['vars'][$offset];
                break;

            case '@flash':
                if ($offset == '*') return $_SESSION['flash.vars'];
                return $_SESSION['flash.vars'][$offset];
                break;
            
            case '@notify':
                //$pathuri = 'notify:'.Path::Uri('full');

                if ($offset == '*') return $_SESSION['flash.notify'];
                return $_SESSION['flash.notify'][$offset];
                break;

            case '@post':
                if ($offset == '*') return $_POST;
                return $_POST[$offset];
                break;

            case '@get':
                if ($offset == '*') return $_GET;
                return $_GET[$offset];
                break;

            case '@cookie':
                if ($offset == '*') return $_COOKIE;
                if (empty($_COOKIE[$offset])) return '';
                
                return Tools::Hash($_COOKIE[$offset], array('mode' => 'decode'));
                break;

            case '@uri':
                if ($offset == '*') $offset = null;
                return Path::Uri($offset);
                break;

            case '@server':
                if ($offset == '*') return $_SERVER;
                return $_SERVER[$offset];
                break;

            case '@template':
                if ('render' == $offset) 
                    return View::Template();
                
                if ('default' == $offset)
                    return View::DefaultTemplate();

                return array(
                    'default'   => View::DefaultTemplate(),
                    'render'    => View::Template()
                );
                break;

            case '@config':
                return AppConfig::$offset();
                break;

            default:
                /**
                 * i am not sure if this is ideal or not because
                 * the user might get the notion that the return variable is the
                 * one that he assigned on the template like
                 *
                 * $self['Account'] = 1; and echo $self['Account'];
                 *
                 * would actually return different result, since the later part will
                 * return variable from either get or post
                 *
                 * assume that datasource is either get or post
                 */
                $var = $_GET[$offset];

                if (array_key_exists($offset, $_POST))
                    $var = $_POST[$offset];

                /**
                 * @todo sanitize Post and Get here
                 */

                return $var;
                break;
        }// end switch

    }
    
    public final function offsetExists($offset) { return true; }
    public final function offsetUnset($offset) {}
    
    public function doInitializeController()
    {

    }

    public function doFinalizeController()
    {

    }

    public function index()
    {

    }

}
