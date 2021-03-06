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

require 'common/css_min.php';
require 'common/js_min_plus.php';

use \CssMin;
use \JsMinPlus;

class ViewTwigExtension extends \Twig_Extension
{

    public function __call($name, $args) {

        if (preg_match('/^helper_function_([a-z0-9_]+)/i', $name)) {
            list(,,$func) = explode("_", $name, 3);
            $call = View::Functions();
            if (!array_key_exists($func, $call)) {
                $exc = new Exception("{$func} is not a valid view_helper_function");
                throw $exc;
            }//end if
            
            $func = $call[$func];
            return call_user_func_array($func, $args);
        }

        if (preg_match('/^helper_filter_([a-z0-9_]+)/i', $name)) {
            list(,,$func) = explode("_", $name, 3);
            $call = View::Filters();
            if (!array_key_exists($func, $call)) {
                $exc = new Exception("{$func} is not a valid view_helper_filter");
                throw $exc;
            }//end if

            $func = $call[$func];
            return call_user_func_array($func, $args);
        }

        if (preg_match('/^helper_test_([a-z0-9_]+)/i', $name)) {
            list(,,$func) = explode("_", $name, 3);
            $call = View::Tests();
            
            if (!array_key_exists($func, $call)) {
                $exc = new Exception("{$func} is not a valid view_helper_test");
                throw $exc;
            }//end if

            $func = $call[$func];
            return call_user_func_array($func, $args);
        }
        
        return null;
    }
    
    /**
     * Returns a list of filters to add to the existing list.
     * @return array An array of filters
     */
    public function getFilters()
    {
        $filters = array(
            'pulltag'   => new \Twig_Filter_Method($this, 'twig_pulltag_filter'),
            'jsminify'  => new \Twig_Filter_Method($this, 'twig_jsminify_filter'),
            'cssminify' => new \Twig_Filter_Method($this, 'twig_cssminify_filter'),
            'base64'    => new \Twig_Filter_Method($this, 'twig_base64_filter'),
            'dump'      => new \Twig_Filter_Method($this, 'twig_dump_filter'),
            'hyphenate' => new \Twig_Filter_Method($this, 'twig_hyphenate_filter'),
            'url_decode' => new \Twig_Filter_Method($this, 'twig_url_decode_filter')
        );
        $assigned = View::Filters();
        if (!empty($assigned)) {
            foreach ($assigned as $k => $v) {
                if (!is_callable($v) || !preg_match('/[a-z0-9_]+/i', $k)) continue;
                $filters[$k] = new \Twig_Filter_Method($this, "helper_filter_{$k}");
            }//endforeach
        }//end if

        return $filters;
    }

    public function getFunctions()
    {
        $functions = array();
        $assigned = View::Functions();
        if (!empty($assigned)) {
            foreach ($assigned as $k => $v){
                if (!is_callable($v) || !preg_match('/^[a-z0-9_]+/i', $k)) continue;
                $functions[$k] = new \Twig_Function_Method($this, "helper_function_{$k}");
            }//end foreach
        }//end if

        if (!array_key_exists('numpage', $functions)) {
            $functions['numpage'] = new \Twig_Filter_Method($this, 'twig_numpage_function');
        }//end if

        return $functions;
    }

    public function getTests()
    {
        $tests = array(
            'email' => new \Twig_Test_Method($this, 'twig_test_email'),
            'blank' => new \Twig_Test_Method($this, 'twig_test_blank')
        );

        $assigned = View::Tests();

        if (!empty($assigned)) {
            foreach ($assigned as $k => $v){
                if (!is_callable($v) || !preg_match('/^[a-z0-9_]+/i', $k)) continue;
                $tests[$k] = new \Twig_Test_Method($this, "helper_test_{$k}");
            }//end foreach
        }//end if

        return $tests;
    }

    /**
     * Returns a list of operators to add to the existing list.
     *
     * @return array An array of operators
     */
    public function getOperators()
    {
        return array(
            array(),
            array(
                'AND'   => array('precedence' => 30, 'class' => 'Twig_Node_Expression_Binary_Bitwiseand', 'associativity'   => \Twig_ExpressionParser::OPERATOR_LEFT),
                'OR'    => array('precedence' => 30, 'class' => 'Twig_Node_Expression_Binary_Bitwiseor', 'associativity'    => \Twig_ExpressionParser::OPERATOR_LEFT),
                'XOR'   => array('precedence' => 30, 'class' => 'Twig_Node_Expression_Binary_Bitwisexor', 'associativity'   => \Twig_ExpressionParser::OPERATOR_LEFT),
            ),
        );
    }

    /**
     * Returns the name of the extension.
     *
     * @return string The extension name
     */
    public function getName()
    {
        return 'ViewTwigExtension';
    }

    function twig_pulltag_filter($str, $tag = '')
    {
        if (empty($tag)) return $str;
        preg_match_all('|<'.$tag.'[^>].*?>(.*?)</'.$tag.'>|is', $str, $script_blocks);

        if (!empty($script_blocks[0]))
            $str = implode("\n", $script_blocks[1]);

        return $str;
    }

    function twig_base64_filter($str)
    {
        return base64_encode($str);
    }

    function twig_jsminify_filter($str)
    {
        $cssfile    = md5($str);
        $tmpfile    = Path::TempDir('minify').'/'.md5($str).'.js';
        $retval     = @file_get_contents($tmpfile);

        if ($retval === false) {
            $retval = JsMinPlus::minify($str);
            file_put_contents($tmpfile, $retval);
        }//end if

        return $retval;
    }

    function twig_cssminify_filter($str)
    {
        $cssfile    = md5($str);
        $tmpfile    = Path::TempDir('minify').'/'.md5($str).'.css';
        $retval     = @file_get_contents($tmpfile);

        if ($retval === false) {
            $retval = CssMin::minify($str);
            file_put_contents($tmpfile, $retval);
        }//end if

        return $retval;
    }

    function twig_dump_filter($var)
    {
        return '<pre>'.var_export($var, true).'</pre>';
    }

    function twig_test_email($value)
    {
        return preg_match('/^[a-z0-9_\-\+]+(\.[_a-z0-9\-\+]+)*@([_a-z0-9\-]+\.)+([a-z]{2}|aero|arpa|biz|com|coop|edu|gov|info|int|jobs|mil|museum|name|nato|net|org|pro|travel)$/i', $value);
    }

    function twig_hyphenate_filter($var)
    {
        return Tools::Hyphenate($var);
    }

    function twig_url_decode_filter($var)
    {
        return urldecode($var);
    }

    function twig_test_blank($value)
    {
        return empty($value);
    }

    function twig_numpage_function($numpage)
    {
        if (!$numpage['nav']['page_current']) return array();
        
        $href = $_GET;

        $start = $numpage['nav']['page_start'];
        $end = $numpage['nav']['page_end'];

        $pages = array();

        $href['p'] = $start - 1;
        $pages["Prev"] = array(
            'href' => '',
            'class' => 'disabled'
        );

        $pages["1"] = array(
            'href' => '',
            'class' => 'first'
        );
        
        if ($numpage['nav']['page_current'] != 1) {
            $href['p'] = $numpage['nav']['page_current'] - 1;
            $pages["Prev"]['href'] = http_build_query($href);
            $pages["Prev"]['class'] = '';

            $href['p'] = 1;
            $pages["1"]['href'] = http_build_query($href);
        }//end if

        if ($start <= 2)
            $pages["1"]['class'] = '';
        
        for($i = $start; $i <= $end; $i++){
            $href['p'] = $i;
            $pages["{$i}"] = array(
                'href' => http_build_query($href),
            );
        }//end for

        $href['p'] = $numpage['nav']['page_total'];
        $pages["{$numpage['nav']['page_total']}"] = array(
            'href' => http_build_query($href),
            'class' => 'last',
        );

        $href['p'] = $numpage['nav']['page_current'] + 1;
        $pages["Next"] = array(
            'href' => http_build_query($href),
        );

        if ($numpage['nav']['page_current'] == $numpage['nav']['page_total']) {
            $pages["{$numpage['nav']['page_total']}"]['href'] = '';
            $pages["Next"]['href'] = '';
            $pages["Next"]['class'] = 'disabled';
        }//end if

        if ($numpage['nav']['page_total'] <= ($end + 1))
            $pages["{$numpage['nav']['page_total']}"]['class'] = '';


        $pages["{$numpage['nav']['page_current']}"]['class'] = 'current';

        return $pages;
    }
    
}
