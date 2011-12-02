<?php
namespace Core\App\Controllers;
use Core\Exception;
use Core\Debug;
use Core\Tools;
use Core\View;
use Core\App\Path;
use Core\App\Module;

class Index extends \Core\Controller
{

    public function __construct()
    {

    }

    /**
     *
     * @access
     * @var
     */
    public function index($prefix = '')
    {
        static $greedy;
        static $method;

        $args = func_get_args();
        $controller = new Blog;

        switch ($prefix) {
            case 'post':
                unset($args[0]);
                break;

            case 'tag':
                $method = 'tag';
                return $controller;
                break;
            
            default:
                if (empty($prefix)) return $controller;
                break;
        }// end switch

        /**
         * Meta tag dynamic page url
         */
        $url = array();
        foreach ($args as $k => $v) {
            if (empty($v)) continue;
            $url[] = $v;
        }// end foreach
        $url = implode('/', $url);

        try {
            $Meta = Module::Polymorphic('Metatags');
            $Meta->find($url, array('search' => 'page_url'));
            $object = Module::CreateClass($Meta['meta_group']);
            $object->find($Meta['meta_uid']);
        } catch (Exception $exc) {
            render()->status = 404;
        }//end try

        render()->assign['Meta'] = $Meta;
        
        switch ($Meta['meta_group']) {
            case 'Blog.Post':
                $method = 'post';
                $controller->post = $object;
                break;

            case 'Blog.Page':
                $controller = Module::Blog('Controllers\\PageHandler');
                $controller->module = $object;
                break;
            
            default:
                break;
        }// end switch

        return $controller;
    }

    /**
     *
     * @access
     * @var
     */
    public function files()
    {
        return Module::Polymorphic('Controllers\\FileHandler');
    }

    /**
     *
     * @access
     * @var
     */
    public function minify()
    {
        static $greedy;
        require 'common/minify.php';
        return new Minify;
    }

    /**
     *
     * @access
     * @var
     */
    public function appmanager()
    {
        static $greedy;
        return new Appmanager;
    }

}