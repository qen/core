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

use Core\App;
use Core\App\Path;
use Core\App\Config as AppConfig;

class View implements \ArrayAccess
{

    public static $Debug = true;
    
    private static $Filters      = array();
    private static $Functions    = array();
    private static $Tests        = array();
    private static $Assignments     = array();
    
    private static $Parser      = null;
    private static $Instance    = null;

    private $mode_options   = array('template', 'json', 'file', 'inline');
    private $mode           = 'template';
    private $content        = '';
    
    private $status         = 200;
    
    public $nocache         = null;
    public $content_type    = null;
    public $attachment      = null;
    public $default         = ''; // default template to render

    private function  __construct()
    {

    }

    public static function Instance()
    {
        if (!isset(self::$Instance)) {
            $c = __CLASS__;
            self::$Instance = new $c;
        }//end if

        return self::$Instance;
    }

    /**
     *
     * @access
     * @var
     */
    public function __get($name)
    {
        if ($this->mode == $name)
            return $this->content;

        if ($name == 'status')
            return $this->status;
        
        if ($name  == 'assign')
            return $this;

        return null;
    }

    /**
     *
     * @access
     * @var
     */
    public function __set($name, $value)
    {
        if (in_array($name, $this->mode_options)) {
            $this->mode     = $name;
            $this->content  = $value;
            return true;
        }//end if

        switch ($name) {
            
            case 'status':
                $this->status = $value;
                if ($value != 200) {
                    $exc = new Exception('render_status');
                    $exc->traceup();
                    $exc->parameters = $value;
                    throw $exc;
                }//end if
                break;

            case 'last_modified':
                if (!is_array($value))
                    $value = array($value);

                list($lastmodified, $cachelifetime) = $value;
                
                if (empty($cachelifetime)) $cachelifetime  = '+24 days';

                if (is_string($lastmodified))
                    $lastmodified = strtotime($lastmodified);

                header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastmodified) . ' GMT');
                header('Cache-Control: max-age='.(strtotime($cachelifetime)).', must-revalidate, public');

                $headers = App\http_headers();
                
                if (isset($headers['If-Modified-Since'])) {
                    $modifiedSince = explode(';', $headers['If-Modified-Since']);
                    $modifiedSince = strtotime($modifiedSince[0]);

                    if (max($lastmodified, $modifiedSince) ==  $modifiedSince){
                        @header("HTTP/1.1 304 Not Modified");
                        App\shutdown();
                    }//end if
                    
                }//end if
                break;
                
            default:
                break;
        }// end switch

        return true;
    }

    /**
     *
     * @access
     * @var
     */
    public function offsetSet($offset, $value)
    {
        /**
         * check if function, ends with ()
         * example: email()
         */
        if (preg_match('|[a-z]+[a-z0-9]*\(\)$|', $offset)) {
            $offset = str_replace('()', '', $offset);

            if (!is_callable($value)) {
                $exc = new Exception("view_helper_function {$offset} is not a callable function");
                $exc->traceup();
                throw $exc;
            }//end if

            self::$Functions[$offset] = $value;
            return true;
        }//end if

        /**
         * check if filter, ends with |
         * example: email|
         */
        if (preg_match('/[a-z]+[a-z0-9]*\|$/', $offset)) {
            $offset = str_replace('|', '', $offset);

            if (!is_callable($value)) {
                $exc = new Exception("view_helper_filter {$offset} is not a callable function");
                $exc->traceup();
                throw $exc;
            }//end if

            self::$Filters[$offset] = $value;
            return true;
        }//end if

        /**
         * check if test, ends with ?
         * example: email?
         */
        if (preg_match('/[a-z]+[a-z0-9]*\?$/', $offset)) {
            $offset = str_replace('?', '', $offset);

            if (!is_callable($value)) {
                $exc = new Exception("view_helper_test {$offset} is not a callable function");
                $exc->traceup();
                throw $exc;
            }//end if

            self::$Tests[$offset] = $value;
            return true;
        }//end if

        /**
         * core model integration
         */
        if ($value instanceof Model) {
            $get        = params()->get;
            $href       = '';
            unset($get['p']);
            foreach ($get as $k => $v) $href .= "{$k}={$v}&";
            $value->numpage['href'] = "?{$href}p=";
        }//end if

        self::$Assignments[$offset] = $value;
    }

    /**
     *
     * @access
     * @var
     */
    public function offsetGet($offset)
    {

    }

    /**
     *
     * @access
     * @var
     */
    public function offsetExists($offset)
    {

    }

    /**
     *
     * @access
     * @var
     */
    public function offsetUnset($offset)
    {

    }


    /**
     *
     * @access
     * @var
     */
    public function text($string, array $assign = array())
    {
        if (!isset(self::$Parser)) 
            self::$Parser = new ViewTwigEnvironment(new \Twig_Loader_String());
        
        return self::$Parser->loadTemplate($string)->render($assign);
    }

    /**
     *
     * @access
     * @var
     */
    public static function Functions()
    {
        return self::$Functions;
    }

    public static function Filters()
    {
        return self::$Filters;
    }

    public static function Tests()
    {
        return self::$Tests;
    }
    
    /**
     *
     * @access :noextend
     * @var
     */
    public function __invoke(\Core\Controller $self, $method)
    {
        
        if (!empty($this->content_type))
            header('Content-type: '. $this->content_type);

        if (!empty($this->attachment)) 
            header("Content-Disposition: attachment; filename={$this->attachment}");


        if ($this->nocache === true) {
            // fix for IE catching or PHP bug issue
            header("Pragma: no-cache");
            header("cache-control: private"); //IE 6 Fix
            header("cache-Control: no-store, no-cache, must-revalidate");
            header("cache-Control: post-check=0, pre-check=0", false);
            header("Expires: Thu, 24 May 2001 05:00:00 GMT"); // Date in the past
        }//end if
        
        /**
         * check if mode is json
         */
        switch ($this->mode) {
            case 'json':
                $content = $this->content;
                if (is_callable($content)) $content = $content();
                
                echo json_encode($content);
                break;

            case 'file':
                $content = $this->content;
                if (is_callable($content)) $content = $content();
                
                if (is_file($content))
                    readfile($this->content);
                else
                    App\http_status(500, 'file content does not exists');

                break;

            case 'inline':
                header("Content-Disposition: inline;");
                $content = $this->content;
                if (is_callable($content))
                    $content = $content();
                
                if (!empty($content)) echo $content;
                break;
            
            default:
                $this->mode = 'template';
                break;
        }// end switch
        
        if ($this->mode != 'template') return true;
        
        /**
         * template rendering here
         */
        $default = array(
            'strict_variables'  => false,
            'auto_reload'       => true,
            'debug'             => true,
        );
        
        $config = AppConfig::Twig();
        
        if (!is_array($config))
            $config = array();

        $config = Tools::ArrayMerge($default, $config);
        $config['cache'] = Path::TempDir('tpl');

        /**
         * this is reference from template is the controller object
         */
        self::$Assignments['this'] = $self;

        /**
         * debug: When set to true, the generated templates have a __toString() method that you can use to display the generated nodes (default to false).
         *
         * trim_blocks: Mimicks the behavior of PHP by removing the newline that follows instructions if present (default to false).
         *
         * charset: The charset used by the templates (default to utf-8).
         *
         * base_template_class: The base template class to use for generated templates (default to Twig_Template).
         *
         * cache: An absolute path where to store the compiled templates, or false to disable caching (which is the default).
         *
         * auto_reload: When developing with Twig, it's useful to recompile the template whenever the source code changes. If you don't provide a value for the auto_reload option, it will be determined automatically based on the debug value.
         *
         * strict_variables (new in Twig 0.9.7): If set to false, Twig will silently ignore invalid variables (variables and or attributes/methods that do not exist) and replace them with a null value. When set to true, Twig throws an exception instead (default to false).
         */
        
        $loader     = new ViewTwigLoader();
        $engine     = new ViewTwigEnvironment($loader, $config);
        
        $uri        = Path::Uri();
        $template   = $this->content;
        $default    = $this->default;
        if (empty($template)) {
            
            $uri['rootpath']        = str_replace($uri['root'], '', $uri['path']);
            $tplfolder              = ($uri['method'] == 'index') ? '/': '/'.$uri['method'] ;
            $template_assumptions   = array(
                "{$uri['rootpath']}{$tplfolder}index.html", // 'check folder/index.html'
                "{$uri['rootpath']}{$tplfolder}.html", // 'check folder.html'
                "{$uri['request']}.html", // 'check request.html'
            );

            /**
             * if tplfolder is / prioritize request folder assumptions
             */
            if ($tplfolder == '/') {
                array_unshift($template_assumptions, "{$uri['request']}.html"); // 'check request.html'
                array_unshift($template_assumptions, "{$uri['request']}/index.html"); // 'check request/index.html'
            }//end if

            $verbose = array();
            $verbose[] = "rootpah   = {$uri['rootpath']}";
            $verbose[] = "tplfolder = {$tplfolder}";
            
            foreach ($template_assumptions as $k => $v) {
                $template = $v;

                try {
                    $loader->isFresh($template, 0);
                    $verbose[] = "using {$v}";
                    break;
                } catch (\Exception $exc) {
                    $template = null;
                    $verbose[] = "{$v} not found";
                }//end try
            }// end foreach

            /**
             * if still null use default_template
             */
            if (is_null($template)) {
                if (empty($default)) $default = "{$uri['rootpath']}.html";
                $template = $default;
            }//end if
            
        }//end if

        if (!preg_match('|\.html?$|', $template)) $template = "{$template}/{$uri['method']}.html";

        if (!preg_match('|^/|', $template)) $template = "{$uri['path']}/{$template}";

        if (self::$Debug) logger(array($uri, $template, $template_assumptions, $verbose), $_SERVER['REQUEST_URI']);

        $engine->loadTemplate($template)->display(self::$Assignments);

    }

    /**
     *
     * @access
     * @var
     */
    public function controller(\Core\Controller $self, $method)
    {
        $this($self, $method);
    }

    public function errorPage($code, $echo)
    {
        $default = array(
            'strict_variables'  => false,
            'auto_reload'       => true,
            'debug'             => true,
        );

        $config = AppConfig::Twig();

        if (!is_array($config))
            $config = array();

        $config = Tools::ArrayMerge($default, $config);
        $config['cache'] = Path::TempDir('tpl');

        $loader     = new ViewTwigLoader();
        $template   = "{$code}.html";
        try {
            $loader->isFresh($template, 0);
            $engine = new ViewTwigEnvironment($loader, $config);
            self::$Assignments['ErrorPageMessage'] = $echo;
            $engine->loadTemplate($template)->display(self::$Assignments);
        } catch (\Exception $exc) {
            echo "<h1>{$code} Page Error</h1>{$echo}";
        }//end try

        App\shutdown();
    }

}