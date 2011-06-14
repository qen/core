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
namespace Core {

    const PATH      = __DIR__;
    const VERSION   = '0.05.18b';

    include 'class/base.php';
    include 'class/config.php';
    include 'class/controller.php';
    include 'class/debug.php';
    include 'class/exception.php';
    include 'class/tools.php';
    include 'class/view.php';
    include 'class/model.php';
    include 'class/model_actions.php';
    include 'class/db.php';

    function logger($value, $title = '', $file = 'messages.log')
    {
        $logfile    = App\Path::TempDir('').'/'.$file;
        $datetime   = date("Y-m-d H:i:s");
        $dump       = var_export($value, true);
        error_log("\n[{$datetime}] {$title}\n{$dump}\n", 3, $logfile);
    }
    
}// end namespace

namespace Core\App {

    use Core\Exception;
    use Core\Debug;
    use Core\View;
    use Core\Tools;
    
    class Config extends \Core\Config
    {

        public static function Load($config = null)
        {
            if (is_null($config)) {
                $env = strtolower(ENVIRONMENT);
                if (empty($env))
                    $env = 'development';

                $config = require Path::ConfigDir() . '/' . $env . '.php';
            }//end if
            
            parent::Load($config);
        }

    }

    class Module
    {

        private static $klass   = array();

        public static function __callStatic($module, array $params = array())
        {

            $klass = array_shift($params);
            if (empty($klass))
                $klass = 'Base';

            if (preg_match('/^load:(.*)/i', $klass, $matches)) {
                return self::loadKlassFile($module, $matches[1]);
            }//end if

            return self::loadKlass($module, $klass);
        }

        private static function loadKlassFile($module, $klass)
        {
            $filepath = core_app_filename('', '', "{$module}\\{$klass}");
            require_once "modules/".$filepath;
        }

        private static function loadKlass($module, $klass = 'Base')
        {
            
            $nsmodule = '\\Core\\App\\Modules\\' . $module . '\\' . $klass;

//            if (!isset(self::$klass[$nsmodule])) {
//                self::$klass[$nsmodule] = new $nsmodule(Config::Db());
//            }
//            $retval = clone self::$klass[$nsmodule];

            return new $nsmodule(Config::Db());;
        }

    }

    class Path
    {
        public static $Temp_Folder = '';
        public static $Webroot_Uri = NULL;
        public static $Uri = array(
            'root' => '',
            'request' => '',
            'path' => '',
            'params' => '',
            'full' => '',
            'fullurl' => '',
        );

        private static $View_Dirs = array();

        public static function WebrootDir()
        {
            return WEBROOT;
        }

        public static function ModuleDir()
        {
            return PATH . '/modules';
        }

        public static function ControllerDir()
        {
            return PATH . '/controllers';
        }

        public static function ConfigDir()
        {
            return \Core\PATH . '/config';
        }

        public static function TempDir($param)
        {
            $tmpdir = \Core\PATH . '/tmp/'.\Core\App\ENVIRONMENT;
            
            if (!empty(self::$Temp_Folder))
                $tmpdir = \Core\PATH . '/tmp/'.self::$Temp_Folder;

            /**
             * try to create tmp dir
             */
            if (!\is_dir($tmpdir))
                mkdir($tmpdir, 0777, true);

            /**
             * if still does not exists throw error
             */
            if (!\is_dir($tmpdir))
                throw new Exception($tmpdir . ' does not exists');

            /**
             * tmpdir should be writable
             */
            if (!\is_writable($tmpdir))
                throw new Exception($tmpdir . ' does not have write permission');

            $tmpdir = $tmpdir.'/'. $param;
            if (!\is_dir($tmpdir))
                mkdir($tmpdir, 0777, true);

            if (!\is_dir($tmpdir))
                throw new Exception('Failed to create directory: ' . $tmpdir);

            return $tmpdir;
        }

        public static function Uri($param = null)
        {
            if (is_null($param) || !array_key_exists($param, static::$Uri))
                return static::$Uri;

            return static::$Uri[$param];
        }

        /**
         *
         * @access
         * @var
         */
        public static function ViewDir($dir = '')
        {
            if (empty($dir)) {
                $retval = self::$View_Dirs;
                $retval[] = WEBROOT;
                return array_unique($retval);
            }//end if

            if ($dir{0} != '/')
                $dir = PATH.'/'.$dir;

            $check = realpath($dir);
            
            if (!is_dir($check))
                throw new Exception("[{$dir}] View directory does not exists.");

            self::$View_Dirs[] = $dir;
        }

    }

    class Route
    {

        public static $Controller   = null;
        public static $View         = null;
        public static $Count        = 0;
        public static $Pathinfo     = '';
        public static $Debug        = '';
        public static $Param        = array(
            'allow_file_extensions' => false,
            'reserved_methods'      => array(
                'offsetSet',
                'offsetGet',
                'offsetExists',
                'offsetUnset',
                'doInitializeController',
                'doFinalizeController',
            )
        );

        /**
         * @access
         * @var
         */
        private static function init()
        {
            list($ruri) = explode('?', $_SERVER['REQUEST_URI']);
            
            Path::$Uri['root'] = Path::$Webroot_Uri;
            if (is_null(Path::$Uri['root'])) 
                Path::$Uri['root'] = str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname($_SERVER['SCRIPT_FILENAME']));

            if (Path::$Uri['root'] == '/')
                Path::$Uri['root'] = '';

            Path::$Uri['request'] = str_replace(Path::$Uri['root'], '', $ruri);
            Path::$Uri['path']    = Path::$Uri['root'];
            
            //Path::$Uri['fullurl']   = "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}";

            if (preg_match('|/$|', Path::$Uri['request']))
                Path::$Uri['request'] = substr(Path::$Uri['request'], 0, -1);

            self::$Pathinfo = pathinfo(Path::$Uri['request']);
        }

        public static function Request($controller = null, $params = array())
        {
            self::$Count++;
            
            if (is_null(Path::WebrootDir()))
                throw new Exception("\Core\App\Webroot\PATH is not defined");

            static::$Controller = $controller;

            if (is_null(static::$Controller)) {
                self::init();

                $params         = explode('/', Path::$Uri['request']);
                $checkprefix    = '';
                array_shift($params);

                if (defined('\Core\App\CONTROLLER')){
                    preg_match_all('/([A-Z][a-z0-9]+)/', \Core\App\CONTROLLER, $matches);
                    $checkprefix = strtolower(implode("_", $matches[1])).'/';
                }//end if
                
                $loop   = count($params);
                $klass  = array();

                for($i = 1; $i <= $loop; $i++ ){
                    $sliced_klass   = array_slice($params, 0, $i);
                    
                    $check          = implode("_", $sliced_klass);
                    $filename       = Path::ControllerDir() . "/{$checkprefix}{$check}.php";
                    
                    if (is_file($filename)) 
                        $klass  = $sliced_klass;
                }//end for
                
                $params = array_slice($params, count($klass));

                if (!empty($klass))
                    Path::$Uri['path'] = Path::$Uri['root']. '/' . implode("/", $klass);
                
                Path::$Uri['params']    = '/' . implode("/", $params);
                Path::$Uri['full']      = $_SERVER['REQUEST_URI'];

                if (preg_match('|/$|', Path::$Uri['full']))
                    Path::$Uri['full'] = substr(Path::$Uri['full'], 0, -1);

                if (empty($klass))
                    $klass = array('index');

                $controller_klass = '';
                array_walk($klass, function($name) use (&$controller_klass) {
                        $controller_klass .= ucfirst($name);
                    });

                if (empty($controller_klass))
                    throw new Exception('critical error: failed to retrieve controller class');

                /**
                 * check if there is Controllers namespace defined
                 */
                if (defined('\Core\App\CONTROLLER'))
                    $controller_klass = \Core\App\CONTROLLER.'\\'.$controller_klass;

                $controller_klass   = "\Core\App\Controllers\\{$controller_klass}";
                
                static::$Controller = new $controller_klass;
                static::$View       = View::Instance();

                
            }//end if

            if (!(static::$Controller instanceof \Core\Controller))
                throw new Exception($controller_klass . '> must be an instanceof \\Core\\Controller');

            $controller_klass   = get_class(static::$Controller);
            $method             = 'index';

            /**
             * check if first params
             * is an existing public method
             */
            if (method_exists(static::$Controller, $params[0]))
                $method = array_shift($params);

            /**
             * check if method is a reserved methods, otherwise echo 404
             */
            if (in_array($method, self::$Param['reserved_methods']))
                http_status(404, 'The page reserve method that you have requested could not be found.');

            /**
             * method must not start with _ or do
             */
            if (preg_match('/^(_|do).*/', $method))
                http_status(404, 'The page name method that you have requested could not be found.');

            $response = new \ReflectionMethod(static::$Controller, $method);

            if (!$response->isPublic())
                http_status(404);

            $count['required'] = $response->getNumberOfRequiredParameters();
            $count['expected'] = $response->getNumberOfParameters();
            $count['optional'] = $count['expected'] - $count['required'];
            $count['passed']   = count($params);
            $count['notempty'] = $count['passed'];

            $controller_klass::$Method = $method;

            $method_doc_options = $response->getDocComment();
            
            /**
             * method is greedy if the doc comment @method :greedy
             *
             * if @method :greedy comment DOES NOT EXISTS
             * 
             */
            if (!preg_match('/(@method).+(:greedy)/i', $method_doc_options)) {
                /**
                 * count the number of params that is not empty
                 */
                array_walk($params, function($item, $key) use (&$count) {
                        if (empty($item))
                            $count['notempty']--;
                    });

                /**
                 * check the number of required parameters
                 * compare it with the $params array
                 *
                 * if passed params is greater than the expected parameters
                 * call 404
                 */
                if ($count['expected'] < $count['notempty']
                    || $count['required'] > $count['notempty']) {
                    static::$Controller->doFinalizeController();
                    http_status(404);
                }//end if
            } else {
                /**
                 * FOR GREEDY methods
                 * pad params with null to meet
                 * the expected parameters
                 */
                $params = array_pad($params, $count['expected'], NULL);
            }//end if

            /**
             * first check if pathinfo extensions is not empty
             * second check if the method allows parameters to have a file extensions
             * @param :allow_file_extensions
             */
            if (self::$Param['allow_file_extensions'] !== true && !empty(self::$Pathinfo['extension'])) {

                /**
                 * if @param :allow_file_extensions comment DOES NOT EXISTS return 404 page
                 */
                if (!preg_match('/(@param).+(:allow_file_extensions)/i', $method_doc_options))
                    http_status(404, 'The page file extension that you have requested could not be found.');

                /**
                 * the controller method allows file extensions in its
                 * parameters
                 */
                self::$Param['allow_file_extensions'] = true;
            }//end if

            Path::$Uri['method'] = $method;

            /**
             * start buffer
             */
            ob_start();

            /**
             * initialize
             */
            static::$Controller->doInitializeController();

            /**
             * CALL CONTROLLER METHOD
             */
            $output = $response->invokeArgs(static::$Controller, $params);

            /**
             * finalize
             */
            static::$Controller->doFinalizeController();

            /**
             * check if controller debug is true
             */
            if (\Core\Controller::$Debug === true) {
                $debugbuffer = ob_get_contents();
                $debugbuffer = "\n{$controller_klass} --\n{$debugbuffer}\n-- {$controller_klass}\n";
                \Core\logger($debugbuffer, implode('/', Path::$Uri['path']));
            }//end if

            /**
             * clear up buffer
             */
            @ob_end_clean();

            /**
             * if output is Core\Controller object
             * reroute the request to the returned value
             */
            if ($output instanceof \Core\Controller) {

                $addpath    = $params;
                $params     = array_splice($addpath, $count['expected']);
                $method     = ($method == 'index') ? '' : "/{$method}";

                array_unshift($addpath, $method);
                
                if (preg_match('|/$|', Path::$Uri['path']))
                    Path::$Uri['path'] = substr(Path::$Uri['path'], 0, -1);
                
                Path::$Uri['path']      .= implode("/", $addpath);
                Path::$Uri['params']    = "/" . implode("/", $params);

                if (preg_match('|/$|', Path::$Uri['path']))
                    Path::$Uri['path'] = substr(Path::$Uri['path'], 0, -1);

                Route::Request($output, $params);
                return true;
            }//end if

            /**
             * expire flash variables
             */
            $self = static::$Controller;
            $self('flash_expire', 'vars');

            View::Assign('this', $self);

            /**
             * call parse template here
             */
            static::$View->response($method);

            $self('flash_expire', 'notify');
            
        }

        public static function SimpleTests($param = '')
        {
            self::init();

            require_once('lib/simpletest/autorun.php');
            $test = 'tests/' . $param . '.php';
            Debug::Dump($test);

            @include($test);
        }

        /**
         *
         * @access
         * @var
         */
        public static function Url($url, $delay = 0)
        {
            
            if (!headers_sent() || $delay > 0) {
                ob_end_clean();
                header("Location: " . $url);
                exit;
            }//end if

            header("refresh: {$delay}; url={$url}");
            exit;
        }

    }

    /**
     * app functions
     */
    function redirect($arg = '', array $get=array())
    {
        $path = $arg;

        /**
         * specify if root path is specified
         */
        if (!preg_match('|^/|', $arg)) {
            $path = Path::$Uri['path'];
            $full = Path::$Uri['full'];
            $path = (empty($path) && !empty($full)? $full : $path);
            
            if (!preg_match('|/$|', $path))
                $path .= '/';

//            /**
//             * if parent path is defined,
//             * remove the last path part to get to the parent
//             */
//            if (preg_match('|(\.\./)|', $arg)) {
//                $path   = explode('/', $path);
//                array_pop($path);
//                array_pop($path);
//                $path   = implode('/', $path);
//                $arg    = substr($arg, 2);
//            }//end if

            $path .= $arg;

        }//end if

        if (!empty($get))
            $path .= '?' . http_build_query($get);
        
        Route::Url($path);
    }

    function http_status($code,  $arg = 'The page that you have requested could not be found.')
    {
        $exit_text = '';
        if ($code == 404){
            $code       = '404 Not Found';
            $exit_text  = '<h1>404 Not Found</h1>'.$arg;
            while (@ob_end_clean());
        }//end if

        if ($code == 301) {
            while (@ob_end_clean());
            @header("HTTP/1.1 301 Moved Permanently");
            redirect($arg);
        }//end if

        /**
         * 404
         * 403
         * 200
         */
        while (@ob_end_flush());

        @header("HTTP/1.1 ".$code);
        echo $exit_text;
        exit;
    }

    function json($status, $result, $notice = null, $response=null)
    {
        $retval = array(
            'status'    => $status,
            'result'    => $result,
            'notice'    => $notice,
            'response'  => $response,
        );
        return json_encode($retval);
    }

    function http_headers()
    {
        foreach ($_SERVER as $name => $value) {
            if (substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }//end if
        }//end foreach
        
        return $headers;
    }

    function core_app_filename($klass_prefix, $prefix, $klass) {
        $path = explode("\\", str_replace($klass_prefix, '', $klass));

        foreach ($path as $k => $v) {
            preg_match_all('/([A-Z][a-z0-9]+)/', $v, $matches);
            $path[$k] = strtolower(implode("_", $matches[1]));
        }// end foreach

        $class = array_pop($path);

        $path = $prefix."/" . strtolower(implode("/", $path));

        $filename = $path . '/' . $class . '.php';

        return $filename;
    }

    /**
     * set error reporting to all since there might be some errors
     * during initializations
     */
    error_reporting(E_ALL ^ E_NOTICE);

    /**
     * setup script time limit
     */
    $tl = 240;
    if (defined('\Core\App\TIMELIMIT'))
        $tz = \Core\App\TIMELIMIT;

    set_time_limit($tl);

    /**
     * setup script time zone
     */
    //$tz = 'Greenwich';
    $tz = 'Asia/Manila';
    if (defined('\Core\App\TIMEZONE'))
        $tz = \Core\App\TIMEZONE;

    date_default_timezone_set($tz);

    if (!defined('\Core\App\ENVIRONMENT'))
        throw new Exception('Please define App ENVIRONMENT');
    
    /**
     * autoloader / apploader
     * 
     * initialize include path, search in app path first
     * then in the core path
     */
    $paths      = explode(PATH_SEPARATOR, get_include_path());
    $app_path   = PATH;
    $core_path  = \Core\PATH;

    array_unshift($paths, $app_path, $core_path);

    set_include_path(implode(PATH_SEPARATOR, $paths));

    /**
     * register autoloader
     */
    spl_autoload_register(function($klass) {
        $err_suffix = '';

        switch (true) {
            case preg_match('|^Core\\\App\\\Controllers|', $klass):
                $filename   = core_app_filename('Core\\App\\Controllers\\', 'controllers', $klass);
                $err_suffix = " | controller namespace is [".@constant('\Core\App\CONTROLLER')."]";
                break;

            case preg_match('|^Core\\\App\\\Modules|', $klass):
                $filename   = core_app_filename('Core\\App\\Modules\\', 'modules', $klass);
                break;

            case preg_match('|^Core\\\App\\\Lib|', $klass):
                $filename   = core_app_filename('Core\\App\\Lib\\', 'lib', $klass);
                break;

            case preg_match('|^Twig|', $klass):
                list(, $file) = explode('_', $klass, 2);
                $filename = 'lib/twig/' . str_replace('_', '/', $file) . '.php';
                include $filename;
                return true;
                break;

            default:
                preg_match_all('/([A-Z][a-z0-9]+)/', $klass, $matches);
                $file       = strtolower(implode('_', $matches[1]));
                $filename   = "lib/{$file}.php";
                break;

        }// end switch

        require $filename;
        
        if (!class_exists($klass))
            \trigger_error("{$klass} does not exists in {$filename}{$err_suffix}", E_USER_ERROR);

        return true;
    });

    /**
     * load configuration
     */
    Config::Load();

    /**
     * setup script error reporting level
     */
    //$err = E_ALL ^ E_NOTICE ^ E_WARNING;// paranoid
    $err = 0; // no error output
    if (ENVIRONMENT == 'development')
        $err = E_ALL ^ E_NOTICE;

    error_reporting($err);

    set_error_handler(function($errno, $errstr, $errfile, $errline){
        if ($errno & (\E_NOTICE ^ \E_WARNING ^ E_STRICT) ) return true;

        \Core\logger("[{$datetime}]Error {$errno} | {$errfile} @line {$errline}\n{$errstr}\n\n", 'PHP :: set_error_handler', 'error.log');
        return true;
    });
    
    \Core\Db::$Debug == false;

}// end namespace

