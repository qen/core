<?php

/*
 * This file is part of Twig.
 *
 * (c) 2009 Fabien Potencier
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
class Twig_Extension_CoreApp extends Twig_Extension
{

    /**
     * Returns a list of filters to add to the existing list.
     *
     * @return array An array of filters
     */
    public function getFilters()
    {
        $filters = array(
            'pulltag'   => new Twig_Filter_Function('twig_pulltag_filter'),
            'jsminify'  => new Twig_Filter_Function('twig_jsminify_filter'),
            'cssminify' => new Twig_Filter_Function('twig_cssminify_filter'),
            'base64'    => new Twig_Filter_Function('twig_base64_filter'),
            'dump'      => new Twig_Filter_Function('twig_dump_filter'),
            'hyphenate' => new Twig_Filter_Function('twig_hyphenate_filter'),
            'url_decode' => new Twig_Filter_Function('twig_url_decode_filter'),
        );

        return $filters;
    }

    public function getTests() {
        return array(
            'email' => new Twig_Test_Function('twig_test_email')
        );
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
                'AND'   => array('precedence' => 30, 'class' => 'Twig_Node_Expression_Binary_Bitwiseand', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                'OR'    => array('precedence' => 30, 'class' => 'Twig_Node_Expression_Binary_Bitwiseor', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
                'XOR'   => array('precedence' => 30, 'class' => 'Twig_Node_Expression_Binary_Bitwisexor', 'associativity' => Twig_ExpressionParser::OPERATOR_LEFT),
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
        return 'core_app';
    }
}

/**
 * core rc7
 */
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

/**
 * cache jsminify and cssminify
 */
use Core\App\Path;
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

use Core\Tools;
function twig_hyphenate_filter($var) {
    return Tools::Hyphenate($var);
}

function twig_url_decode_filter($var) {
    return urldecode($var);
}