<?php
namespace Core\App\Modules\Blog\Controllers;

use Core\Debug;
use Core\Exception;
use Core\App\Path;
use Core\App\Module;
use Core\App\Lib\Facebook\Api as Facebook;

use Core\App\Controllers\Lib\Wysiwyg;
use Core\App\Modules\Polymorphic\Controllers\CategoryManager;
use Core\App\Modules\Polymorphic\Controllers\TagManager;

class PostManager extends \Core\Controller
{
    public $module  = null;
    public $files   = null;
    
    public function __construct()
    {

    }


    /**
     *
     * @method :greedy
     */
    public function index($param = '', $mode = '', $arg = '')
    {
        if (is_numeric($param))
            return $this->details($param, $mode, $arg);

        if (!empty($param))
            redirect();

        $Blog = $this->module;

        $search = trim(params()->get['q']);
        $where  = array();

        if (empty($search))
            $search = '*';
        else
            $search .= '%';

        if (preg_match('/^status:hidden ?(.+)?/', $search, $matches)) {
            $search = $matches[1];
            $where['&blog_typestat'] = '~'.$Blog::STAT_VISIBLE;
        }//end if

        try {
            $Blog->find($search, array(
                'conditions'    => NULL,
                'search'        => '%blog_title',
                'limit'         => 10,
                'selpage'       => params()->get['p'],
                'where'         => $where
            ));
        } catch (Exception $exc) {

            if (empty($search)) redirect('add');

            params()->notify['errors'] = "You're search is too leet, please dumb it down a little bit";
            redirect();
        }//end try

        render()->assign['result'] = $Blog;
        render()->assign['numpage'] = $Blog->numpage;
        params()->flash['notify'];
        
    }

    protected function details($id, $mode, $arg)
    {

        $Blog = $this->module;

        try {
            $Blog->find($id, array('conditions' => NULL));
        } catch (Exception $exc) {
            render()->status = 404;
        }//end try
        
        switch ($mode) {
            case 'delete':
                try {
                    $Blog->remove();
                } catch (Exception $exc) {
                    params()->dump($exc->getMessages());
                }//end try

                params()->flash['notify'] = 'Blog post deleted';
                redirect();
                break;
                
            default:
                if (!empty($mode)) redirect($Blog['blogid']);

                break;
        }// end switch

        render()->assign['details'] = $Blog;
        render()->template = 'details.html';
        
    }

    /**
     *
     * @access
     * @var
     */
    public function add()
    {
        $Blog = $this->module;

        render()->assign['details'] = $Blog;
        render()->template = 'details.html';

    }

    public function save()
    {
        $Blog = $this->module;
        /**
         * initialize associate model
         */
        $Blog->Categories   = true;
        $Blog->Tags         = true;
        $Blog->Metatags     = true;

        try {
            $Blog->find(params()->post['d']['blogid'], array('conditions' => null));
        } catch (Exception $exc) {
        }//end try
        
        $Blog->from(params()->post['d']);

        render()->assign['details'] = params()->post['d'];
        render()->template = 'details.html';

        if (!empty(params()->post['Tag']))
            $tagids = $Blog->Tags->query(params()->post['Tag']);
        
        /**
         * first clear all categories, except the selected category id
         */
        foreach ($Blog->Categories as $k => $v) $v->remove();
        $Blog->Categories->clear(true);
        
        if (!empty(params()->post['CategoryId'])) {

            /**
             * disable query on call
             */
            $Blog->Categories = false;

            try {
                $categoryid = params()->post['CategoryId'];
                $Blog->Categories->find($categoryid, array('limit' => 1));
            } catch (Exception $exc) {
                params()->flash['notify'] = $exc->getMessages();
                return true;
            }//end try
            
            /**
             * enable query on call
             */
            $Blog->Categories = true;
            
        }//end if
        
        try {
            $Blog->save();
        } catch (Exception $exc) {
            params()->flash['errors'] = $exc->getMessages();
            return true;
        }//end try

        if (!empty($tagids)) {

            try {
                foreach ($Blog->Tags as $k => $v) {
                    if (in_array($v['tagid'], $tagids)) continue;
                    $Blog->Tags->remove();
                }// end foreach
                $Blog->Tags->cleanup();
            } catch (Exception $exc) {
            }//end try

        }//end if

        params()->flash['status'] = 'Blog Post saved';
        redirect($Blog['blogid']);
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
        $controller->module     = $this->files;
        return $controller;
    }

    /**
     *
     * @access
     * @var
     */
    public function category()
    {
        static $greedy;

        $Blog =  $this->module;
        
        $controller = new CategoryManager;
        $controller->module = $Blog->Categories->object();
        return $controller;
    }

    /**
     *
     * @method :greedy
     */
    public function tag()
    {
        static $greedy;
        
        $Blog =  $this->module;
        
        $controller = new TagManager;
        $controller->module = $Blog->Tags->object();
        return $controller;
    }

}
