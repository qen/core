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
 * @version rc7
 * @date 2010.10.19
 *
 * MODIFICATION DONE ON TWIG
 *
 * Twig_Template->getAttributes
 * Twig_Node_Block->compile
 *
 *
 */
namespace Core;

use Core\App\Path;
use Core\App\Config as AppConfig;

class View extends Base{

    private static $Parser      = null;
    private static $Instance    = null;

    private static $Template            = '';
    private static $Default_Template    = '';
    
    private static $Assignments = array();

    /**
     *
     * @access
     * @var
     */
    protected function initialize()
    {
        if (isset(self::$Instance))
            throw new Exception('Construct failed on Singleton class['.__CLASS__.']');

        //self::$engine = new Twig();
    }
    
    /**
     *
     * @access
     * @var
     */
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
        if (!isset(self::$Parser))
            self::$Parser = new \Twig_EnvironmentCoreApp(new \Twig_Loader_String());
        
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
    public function response($method)
    {
        $default = array(
            'strict_variables'  => false,
            'auto_reload'       => true,
            'debug'             => true,
        );
        
        $config = AppConfig::Twig();
        $this->fireEvent('response', array($config));
        
        if (!is_array($config))
            $config = array();

        $config = Tools::ArrayMerge($default, $config);
        $config['cache'] = Path::TempDir('tpl');

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
        
        $loader = new \Twig_Loader_CoreAppFilesystem(Path::ViewDir(), \Core\App\PATH);
        $engine = new \Twig_EnvironmentCoreApp($loader, $config);

        if (empty(self::$Template)) {
            $uri = Path::Uri();
            $uri['rootpath']        = str_replace($uri['root'], '', $uri['path']);
            $tplfolder              = ($uri['method'] == 'index') ? '/': '/'.$uri['method'] ;
            $template_assumptions   = array(
                'check folder/index.html'   => "{$uri['rootpath']}{$tplfolder}index.html",
                'check folder.html'         => "{$uri['rootpath']}{$tplfolder}.html",
                'check request.html'        => "{$uri['request']}.html",
            );

            foreach ($template_assumptions as $k => $v) {
                self::$Template = $v;
                //Debug::Dump($k, self::$Template, $uri);
                try {
                    $loader->isFresh(self::$Template, 0);
                    break;
                } catch (\Exception $exc) {
                    self::$Template = null;
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
        
        self::$Assignments['App']['template'] = $template;

        $engine->loadTemplate(self::$Template)->display(self::$Assignments);

    }

}