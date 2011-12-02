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
    include 'class/db.php';
    //include 'class/debug.php'; # not a required file to include
    include 'class/exception.php';
    include 'class/model.php';
    include 'class/model_actions.php';
    include 'class/model_iterator.php';
    include 'class/tools.php'; # not a required file to include
    include 'class/view.php';

    function logger($value, $title = '', $file = 'messages.log')
    {
        $logfile    = App\Path::TempDir('').'/'.$file;
        $datetime   = date("Y-m-d H:i:s");
        $dump       = var_export($value, true);
        error_log("\n[{$datetime}] {$title}\n{$dump}\n", 3, $logfile);
    }
    
}// end namespace

namespace {
    
    /*
     * global functions
     */

    function params($arg1 = '')
    {
        $params = \Core\Params::Instance();

        if (!empty($arg1)) {
            return $params->from($arg1);
        }//end if

        return $params;
    }

    function render($arg1 = '', $arg2 = '')
    {
        $render = \Core\View::Instance();
        
        if ($arg1 instanceof \Core\Controller && !empty($arg2)) {
            $render($arg1, $arg2);
            return true;
        }//end if

        if (!empty($arg1))
            $render[$arg1] = $arg2;

        return $render;
    }

    function redirect()
    {
        $args = func_get_args();
        $arg = $args[0];
        $get = array();
        $is_permanent = false;

        /**
         * possible calls
         * redirect('path', true);
         * redirect('path', array('hi' => 1), false);
         * redirect('path', false, array('hi' => 1));
         */
        foreach ($args as $k => $v) {
            if (is_bool($v)) $is_permanent = $v;
            if (is_array($v)) $get = $v;
        }// end foreach

        $path = $arg;

        /**
         * specify if root path is specified
         */
        if (!preg_match('|^/|', $arg)) {
            $path = \Core\App\Path::$Uri['path'];
            $full = \Core\App\Path::$Uri['full'];
            $path = (empty($path) && !empty($full)? $full : $path);

            if (!preg_match('|/$|', $path)) $path .= '/';

            $path .= $arg;

        }//end if

        if (!empty($get))
            $path .= '?' . http_build_query($get);

        if ($is_permanent === true) 
            @header("HTTP/1.1 301 Moved Permanently");

        $header_redirect = "Location: " . $path;
        if (headers_sent()) {
            while (@ob_end_clean());
            $header_redirect = "refresh: 1; url={$path}";
        }//end if
        
        header($header_redirect);
        Core\App\shutdown();
    }

    function logger($message, $title = '')
    {
        if (!is_string($message)) $message = var_export($message, true);
        \Core\logger($message, $title);
    }

}

namespace Core\App {

    use Core\Exception;
    use Core\Debug;
    use Core\View;
    use Core\Tools;
    use Core\Db;
    use Core\Params;
    
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

        public static function __callStatic($module, array $args = array())
        {
            
            $klass = array_shift($args);
            if (empty($klass))
                $klass = 'Base';
            /**
             * if passed parameters ends with .php
             * then its a convention to just include the php file
             * relative to the modules directory
             */
            if (preg_match('/\.php$/i', $klass)) {
                preg_match_all('/([A-Z][a-z0-9]+)/', $module, $matches);
                $module     = strtolower(implode("_", $matches[1]));
                $is_loaded  = include "modules/{$module}/{$klass}";
                if ($is_loaded) return true;
                
                $exc =  new Exception("file include failed: [ modules/{$module}/{$klass} ] does not exists");
                $exc->traceup();
                throw $exc;
                return true;
            }//end if

            return self::loadKlass($module, $klass);
        }

        /**
         * creates a module class parameter expected is a string
         * "<Module Name>.<Class Name>"
         */
        public function CreateClass($string)
        {

            list($module, $klass) = explode('.', $string, 2);
            if (empty($klass)) $klass = 'Base';

            if (empty($module)) {
                $exc = new Exception("Failed to create class module={$module} and model={$model_klass}");
                $exc->traceup()->traceup();
                throw $exc;
            }//end if

            $module = trim($module);
            $klass  = trim($klass);
            return self::$module($klass);
            
        }

        private static function loadKlassFile($module, $klass)
        {
            $filepath = core_app_filename('', '', "{$module}\\{$klass}");
            require_once "modules/".$filepath;
        }

        private static function loadKlass($module, $klass = 'Base')
        {
            
            $nsmodule = '\\Core\\App\\Modules\\' . $module . '\\' . $klass;

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
        public static $Count        = 0;
        public static $Pathinfo     = '';
        public static $Debug        = '';
        public static $Settings     = array(
            'allow_file_extensions' => false,
            'reserved_methods'      => array(
                'offsetSet',
                'offsetGet',
                'offsetExists',
                'offsetUnset',
            )
        );

        private static $Arguments   = array();
        private static $Method      = 'index';
        private static $Countargs   = array();

        private static function Init()
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

        /**
         *
         * @access
         * @var
         */
        public static function Request()
        {
            if (is_null(Path::WebrootDir()))
                throw new Exception("\Core\App\Webroot\PATH is not defined");

            self::Init();

            $args = explode('/', Path::$Uri['request']);
            $index_controller = constant('\Core\App\CONTROLLER');

            if (empty($index_controller))
                $index_controller = '\Core\App\Controllers\Index';
            else
                $index_controller = '\Core\App\Controllers\\'.$index_controller;

            array_shift($args);

            /**
             * if there is file extenstions, then separate
             * the file extensions as it's own parameter
             */
            if (!empty(self::$Pathinfo['extension'])) {
                array_pop($args);
                $args[] = self::$Pathinfo['filename'];
                $args[] = '.'.self::$Pathinfo['extension'];
            }//end if

            self::$Arguments = $args;

            /**
             * trigger __construct
             */
            $controller = new $index_controller;

            /**
             * loop while the result is an instance of core controller
             */
            do {
                $result = self::Response($controller);
                if (!($result instanceof \Core\Controller)) break;
                $controller = $result;
                self::Controller($controller);
            } while (true);

            /**
             * expire flash variables
             */
            params()->flushFlash();

            /**
             * setcookies
             */
            params()->flushCookies();

            /**
             * render call here
             */
            try {
                if (empty(static::$Method)) static::$Method = 'index';
                render(static::$Controller, static::$Method);
            } catch (\Exception $exc) {
                $message    = (string) $exc;
                $datetime   = date("Y:m:d H:i:s");
                $errno      = $exc->getCode();
                $errfile    = $exc->getFile();
                $errline    = $exc->getLine();
                $errstr     = $message;

                \Core\logger("[{$datetime}]Error {$errno} | {$errfile} @line {$errline}\n{$errstr}\n\n", 'Controller :: '.$controller_klass, 'error.log');

                $echo = "{$message}<pre>{$exc->getTraceAsString()}</pre>";

                http_status(500, $message);
            }//end try
            
            /**
             * trigger the __deconstruct
             * and flush cookies
             */
            shutdown();
        }

        private static function Response(\Core\Controller $controller, array $args)
        {

//            if (is_null(static::$Controller)) {
//                self::Init();
//
//                $args = explode('/', Path::$Uri['request']);
//                $index_controller = constant('\Core\App\CONTROLLER');
//                if (empty($index_controller))
//                    $index_controller = '\Core\App\Controllers\Index';
//                else
//                    $index_controller = '\Core\App\Controllers\\'.$index_controller;
//
//                array_shift($args);
//
//                /**
//                 * trigger __construct
//                 */
//                $controller = new $index_controller;
//                self::Request($controller, $args);
//                return true;
//------------------------------------------------------------------------------
//                $args           = explode('/', Path::$Uri['request']);
//                $checkprefix    = '';
//                array_shift($args);
//                $constant_controller = constant('\Core\App\CONTROLLER');
//
//                if (!empty($constant_controller)){
//                    preg_match_all('/([A-Z][a-z0-9]+)/', \Core\App\CONTROLLER, $matches);
//                    $checkprefix = strtolower(implode("_", $matches[1])).'/';
//                }//end if
//
//                $loop   = count($args);
//                $klass  = array();
//                $klass_ = 0;
//
//                for($i = 1; $i <= $loop; $i++ ){
//                    $sliced_klass   = array_slice($args, 0, $i);
//
//                    $check          = implode("_", $sliced_klass);
//                    $filename       = Path::ControllerDir() . "/{$checkprefix}{$check}.php";
//
//                    if (is_file($filename)) {
//                        $klass  = explode('_', $check);
//                        $klass_ = count($sliced_klass);
//                        Path::$Uri['path'] = Path::$Uri['root']. '/' . implode("/", $sliced_klass);
//                    }//end if
//
//                }//end for
//
//                $args = array_slice($args, $klass_);
//                unset($klass_);
//
//                Path::$Uri['params']    = '/' . implode("/", $args);
//                Path::$Uri['full']      = $_SERVER['REQUEST_URI'];
//
//                if (preg_match('|/$|', Path::$Uri['full']))
//                    Path::$Uri['full'] = substr(Path::$Uri['full'], 0, -1);
//
//                if (empty($klass))
//                    $klass = array('index');
//
//                $controller_klass = '';
//                array_walk($klass, function($name) use (&$controller_klass) {
//                        $controller_klass .= ucfirst($name);
//                    });
//
//                if (empty($controller_klass))
//                    throw new Exception('critical error: failed to retrieve controller class');
//
//                /**
//                 * check if there is Controllers namespace defined
//                 */
//                if (!empty($constant_controller))
//                    $controller_klass = \Core\App\CONTROLLER.'\\'.$controller_klass;
//
//                $controller_klass   = "\Core\App\Controllers\\{$controller_klass}";
//
//                /**
//                 * trigger __construct
//                 */
//                static::$Controller = new $controller_klass;
//
//            }//end if

            self::$Count++;
            static::$Controller = $controller;
            
            if (!(static::$Controller instanceof \Core\Controller))
                throw new Exception($controller_klass . '> must be an instanceof \\Core\\Controller');

            $args               = self::$Arguments;
            $controller_klass   = get_class(static::$Controller);
            $method             = 'index';

            /**
             * check if first params
             * is an existing public method
             */
            if (method_exists(static::$Controller, $args[0]))
                $method = array_shift($args);

            /**
             * check if method is a reserved methods, otherwise echo 404
             */
            if (in_array($method, self::$Settings['reserved_methods']))
                http_status(404, 'The page reserve method that you have requested could not be found.');

            /**
             * method must not start with a letter
             */
            //if (preg_match('/^[a-z]+.*/i', $method))
            if (!preg_match('/^[a-z]+[a-z0-9_-]*/i', $method))
                http_status(404, 'The page name method that you have requested could not be found.');

            /**
             * for method name, replace - to underscore
             */
            $method     = str_replace('-', '_', $method);
            $response   = new \ReflectionMethod(static::$Controller, $method);
            $static     = $response->getStaticVariables();

            /**
             * if static reserved variable is declared, return 404
             */
            if (array_key_exists('reserved', $static, true))
                http_status(404);
            
            /**
             * get / initialize response rules
             */ 
            if (!$response->isPublic())
                http_status(404);

            $count['required'] = $response->getNumberOfRequiredParameters();
            $count['expected'] = $response->getNumberOfParameters();
            $count['optional'] = $count['expected'] - $count['required'];
            $count['passed']   = count($args);
            $count['notempty'] = $count['passed'];

            /**
             * method is greedy if static greedy is declared
             */
            if (!array_key_exists('greedy', $static)) {
                /**
                 * count the number of params that is not empty
                 */
                array_walk($args, function($item, $key) use (&$count) {
                        if (empty($item))
                            $count['notempty']--;
                    });

                /**
                 * check the number of required parameters
                 * compare it with the $args array
                 *
                 * if passed params is greater than the expected parameters
                 * call 404
                 */
                if ($count['expected'] < $count['notempty']
                    || $count['required'] > $count['notempty']) {
                    static::$Controller->__destruct();
                    http_status(404, "The page {$method} that you have requested parameters not be found.");
                }//end if
            } else {
                /**
                 * FOR GREEDY methods
                 * pad params with null to meet
                 * the expected parameters
                 */
//                if ($count['expected'] > 0 && $count['expected'] < count($args)) {
//
//                    var_dump($count['expected']);
//                    //var_dump($args);
//                    var_dump(array(1));
//                    var_dump(array_slice($args, $count['expected']));
//                    //$args = array_pad($args, $count['expected'], NULL);
//                    //var_dump($args);
//
//                    exit;
//                }//end if
                $args = array_pad($args, $count['expected'], NULL);
                
            }//end if

            /**
             * check if static constraints is not empty
             */
            if (!empty($static['constraints']) && is_array($static['constraints'])) {
                foreach ($static['constraints'] as $k => $regex) {
                    if (empty($regex)) continue;
                    if (!preg_match($regex, $args[$k])) {
                        static::$Controller->__destruct();
                        http_status(404, "The page {$method} that you have requested failed on constraint ( {$k} )");
                    }//end if
                }// end foreach
            }//end if

            Path::$Uri['method'] = $method;
            $render = render();
            $params = params();
            $render['params'] = $params;
            
            if (empty($render->default) && !empty($static['template'])) 
                $render->default = $static['template'];

            /**
             * start buffer
             */
            ob_start();

            /**
             * CALL CONTROLLER METHOD
             */
            try {
                $output = $response->invokeArgs(static::$Controller, $args);
            } catch (Exception $exc) {
                $message = (string) $exc;

                if ($message == 'render_status') {
                    http_status($exc->parameters);
                    shutdown();
                }//end if

                $datetime   = date("Y:m:d H:i:s");
                $errno      = $exc->getCode();
                $errfile    = $exc->getFile();
                $errline    = $exc->getLine();
                $errstr     = $message;

                \Core\logger("[{$datetime}]Error {$errno} | {$errfile} @line {$errline}\n{$errstr}\n\n", 'Controller :: '.$controller_klass, 'error.log');

                $echo = "{$message}<pre>{$exc->getTraceAsString()}</pre>";

                http_status(500, $message);
                
                shutdown();
            }//end try

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
                /**
                 * retrieve static variable again, once the method is executed
                 */
                $static = $response->getStaticVariables();
                
                if (!empty($static['method'])) {
                    array_unshift($args, $static['method']);
                    $method = $static['method'];
                }//end if

                self::$Arguments    = $args;
                self::$Countargs    = $count;
                self::$Method       = $method;
                return $output;
                //self::Controller($output, $args, $count, $method);
                //return true;
            }//end if

            return true;
        }

        private static function Controller(\Core\Controller $output)
        {
            $args       = self::$Arguments;
            $count      = self::$Countargs;
            $method     = self::$Method;
            
            $addpath    = $args;
            $args       = array_splice($addpath, $count['expected']);
            $method     = ($method == 'index') ? '' : "/{$method}";

            array_unshift($addpath, $method);

            if (preg_match('|/$|', Path::$Uri['path']))
                Path::$Uri['path'] = substr(Path::$Uri['path'], 0, -1);

            Path::$Uri['path']      .= implode("/", $addpath);
            Path::$Uri['params']    = "/" . implode("/", $args);

            if (preg_match('|/$|', Path::$Uri['path']))
                Path::$Uri['path'] = substr(Path::$Uri['path'], 0, -1);

            /**
             * trigger the __deconstruct
             */
            static::$Controller->__destruct();

            self::$Arguments    = $args;
            self::$Method       = $method;

            //self::Response($output, $args);
            //return true;
        }

    }

    /**
     * app functions
     */
    function http_status($code,  $arg = 'The page that you have requested could not be found.')
    {
        if ($code != 200) while (@ob_end_clean());

        /**
         * 404
         * 403
         * 200
         */
        while (@ob_end_flush());
        
        switch ($code) {
            case 404:
                @header("HTTP/1.1 404 Not Found");
                render()->errorPage(404, $arg);
                break;

            case 500:
                @header("HTTP/1.1 500 Internal Server Error");
                render()->errorPage(500, $arg);
                break;

            default:
                shutdown();
                break;
        }// end switch
        
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

    function shutdown()
    {
        params()->flushCookies();
        \Core\App\Route::$Controller->__destruct();
        exit;
    }

    function core_app_filename($klass_prefix, $prefix, $klass)
    {
        $path = explode("\\", str_replace($klass_prefix, '', $klass));

        foreach ($path as $k => $v) {
            preg_match_all('/([A-Z][a-z0-9]+)/', $v, $matches);
            $path[$k] = strtolower(implode("_", $matches[1]));
        }// end foreach

        $class = array_pop($path);
        
        $path = $prefix. "/" . strtolower(implode("/", $path));

        if (!preg_match("|/$|", $path)) $path .= '/';

        $filename = $path . $class . '.php';
        
        if(empty($class)) {
            $exc = new Exception("Failed to determine class file name for {$klass}");
            throw $exc;
            exit;
        }//end if

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
    $pack_path  = "{$core_path}/packages";

    array_unshift($paths, $app_path, $core_path, $pack_path);
    set_include_path(implode(PATH_SEPARATOR, $paths));
    
    /**
     * packages autoload and add to includes path
     */
    $packages = dir($pack_path);
    while (false !== ($entry = $packages->read())) {
        if ('.' == $entry || '..' == $entry) continue;

        /**
         * initialization of the packages is the same name of its folder
         * include it if it exists in the packages folder
         */
        $package_init = "{$packages->path}/{$entry}.php";
        if (!is_file($package_init)) continue;

        require $package_init;
    }//end while
    $packages->close();

    /**
     * twig is the template engine, make sure that it is included
     */
    require_once 'twig/Autoloader.php';
    \Twig_Autoloader::register();
    
    /**
     * register core autoloader
     */
    spl_autoload_register(function($klass) {
        $err_suffix = '';
        
        if (0 !== strpos($klass, 'Core')) return;

        switch (true) {
            case preg_match('|^Core\\\App\\\Controllers|', $klass):
                $filename   = core_app_filename('Core\\App\\Controllers\\', 'controllers', $klass);
                $err_suffix = " | controller namespace is [".@constant('\Core\App\CONTROLLER')."]";
                break;

            case preg_match('|^Core\\\App\\\Modules|', $klass):
                $filename   = core_app_filename('Core\\App\\Modules\\', 'modules', $klass);
                break;

            case preg_match('|^Core\\\[A-Z][a-z0-9]+|', $klass):
                $filename   = core_app_filename('Core\\', 'class', $klass);
                break;
            
            default:
                preg_match_all('/([A-Z][a-z0-9]+)/', $klass, $matches);
                $file       = strtolower(implode('_', $matches[1]));
                $filename   = "{$file}.php";
                break;

        }// end switch

        require $filename;
        
        if (!class_exists($klass))
            throw new Exception("{$klass} does not exists in {$filename}{$err_suffix}");

        return true;
    });


    /**
     * load configuration
     */
    Config::Load();

    /**
     * set the default db configuration
     */
    Db::$Config = Config::Db();

    /**
     * setup script error reporting level
     */
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
