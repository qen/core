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

class View
{
    public static $Filters      = array();
    public static $Functions    = array();

    public static $Debug = true;

    private static $Parser      = null;
    private static $Instance    = null;

    private static $Template            = '';
    private static $Default_Template    = '';
    
    private static $Assignments = array();

    public function  __construct()
    {
        if (isset(self::$Instance))
            throw new Exception('Construct failed on Singleton class['.__CLASS__.']');

        //self::$engine = new Twig();
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
    public static function Parse($string, array $assign = array())
    {
        if (!isset(self::$Parser)) {
            self::$Parser = new ViewTwigEnvironment(new \Twig_Loader_String());
            
        }//end if
        return self::$Parser->loadTemplate($string)->render($assign);
    }

    /**
     *
     * @access
     * @var
     */
    public static function Assign($var, $value)
    {
        if (is_null($var))
            return self::$Assignments;

        if (is_null($value))
            return self::$Assignments[$var];

        self::$Assignments[$var] = $value;

        return $value;
    }

    /**
     *
     * @access
     * @var
     */
    public static function Template($template = null)
    {
        if (is_null($template))
            return self::$Template;
        
        self::$Template = $template;
    }

    public static function DefaultTemplate($template = null)
    {
        if (is_null($template))
            return self::$Default_Template;

        self::$Default_Template = $template;
    }

    /**
     *
     * @access :noextend
     * @var
     */
    public function response(\Core\Controller $self, $method)
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
        
        if (empty(self::$Template)) {
            
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
                self::$Template = $v;

                try {
                    $loader->isFresh(self::$Template, 0);
                    $verbose[] = "using {$v}";
                    break;
                } catch (\Exception $exc) {
                    self::$Template = null;
                    $verbose[] = "{$v} not found";
                }//end try
            }// end foreach

            /**
             * if still null use default_template
             */
            if (is_null(self::$Template)) {
                if (empty(self::$Default_Template))
                    self::$Default_Template = "{$uri['rootpath']}.html";
                    
                self::$Template = self::$Default_Template;
            }//end if
            
        }//end if

        if (!preg_match('|\.html?$|', self::$Template))
            self::$Template = self::$Template.'/'.Path::Uri('method').'.html';
        
        #self::$Assignments['App']['template'] = $template;
        
        $engine->loadTemplate(self::$Template)->display(self::$Assignments);

        if (self::$Debug) 
            logger(array($uri, self::$Template, $template_assumptions, $verbose), $_SERVER['REQUEST_URI']);

    }

    public function ErrorPage($code, $echo)
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
    }

}