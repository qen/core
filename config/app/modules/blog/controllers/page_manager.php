<?php
namespace Core\App\Modules\Blog\Controllers;

require "spyc/spyc.php";
use \Spyc;

use Core\Debug;
use Core\Exception;
use Core\App\Path;
use Core\App\Module;

use Core\App\Controllers\Wysiwyg;

class PageManager extends \Core\Controller
{
    //public static $Dump_Vars = true;
    //public static $Session   = array( 'name' => '' );
    public $module = null;
    public $files = null;
    public $allow = array(
        'add' => false
    );
    
    public function doInitializeController()
    {
    }
    
    public function doFinalizeController()
    {
    }

    /**
     *
     * @method :greedy
     */
    public function index()
    {
        
        $Page = $this->module;

        try {
            $Page->find('*', array(
                'search'        => '%page_title',
                'limit'         => 10,
                'selpage'       => $get['p'],
                'orderby'       => 'page_url, page_sort'
            ));
        } catch (Exception $exc) {
            if ($this->allow['add'] === true) {
                $this('redirect', "page/add");
            }//end if
            $this('http_status', 404);
        }//end try

        $this['result'] = $Page;
        $this['numpage'] = $Page->numpage;
    }

    public function page($mode)
    {
        
        $Page = $this->module;

        try {
            if (empty($this['url']))
                throw new Exception('Not found');
            
            $Page->find($this['url'], array(
                'search'        => 'page_url',
            ));
            
        } catch (Exception $exc) {
            
            if ($this->allow['add'] !== true)
                $this('http_status', 404);

            if (empty($this['@post']['*'])) {
                $this['@template']['render'] = 'details.html';
                return true;
            }//end if

            $Page->add();
            $Page['page_url']   = $this['d']['page_url'];
            $Page['page_group'] = 'public';
            $Page['page_typestat'] = $Page::STATUS_VISIBLE;
        }//end try

        switch ($mode) {
            case 'save':
                $this->save($Page);
                break;

            case 'edit':
                $Page['page_settings'] = Spyc::YAMLDump($Page['page_settings']);

                $this['details'] = $Page;
                $this['@template']['render'] = 'details.html';
                break;

            default:
                $this('http_status', 404);
                break;
        }// end switch
        
    }

    protected function save($Page)
    {
        $Page->Metatags = true;
        $Page->from($this['d']);

        $this['details'] = $this['d'];
        $this['@template']['render'] = 'details.html';

        try {
            $Page['page_settings'] = Spyc::YAMLLoad($Page['page_settings']);
            $Page->save();
        } catch (Exception $exc) {
            $this['@notify']['errors'] = $exc->getMessages();
            return true;
        }//end try

        $this['@notify']['status'] = "Page saved";
        $this('redirect', "page/edit", array('url' => $Page['page_url']));
    }

    /**
     *
     * @access
     * @var
     * @method :greedy
     */
    public function wysiwyg()
    {
        $controller = new Wysiwyg;
        $controller->restricted = array();
        $controller->module = $this->files;
        return $controller;
    }

}