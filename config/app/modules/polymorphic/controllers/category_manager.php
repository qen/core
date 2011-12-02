<?php
namespace Core\App\Modules\Polymorphic\Controllers;

use Core\Debug;
use Core\Exception;
use Core\App\Path;
use Core\App\Module;

class CategoryManager extends \Core\Controller
{
    //public static $Dump_Vars = true;
    //public static $Session   = array( 'name' => '' );

    public $module = null;

    public function __construct()
    {
    }

    /**
     *
     * @access
     * @var
     */
    public function index()
    {
        render()->status = 404;
    }

    public function lists()
    {
        $Category = $this->module;
        try {
            $tree = $Category->tree('*');
            render()->json = array('status' => 'success', 'result' => $tree, 'notice' => 'category lists', 'response' => null);
        } catch (Exception $exc) {
            render()->json = array('status' => 'failed', 'result' => null, 'notice' => 'category lists', 'response' => null);
        }//end try
    }

    /**
     *
     * @access
     * @var
     */
    public function save()
    {
        $Category = $this->module;
        $catg = array(
            'cat_name'  => params()->post['name']
        );
        
//        $parentid = $this['parentid'];
//        if (!empty($parentid)) {
//            try {
//                $Category->parent(function($model) use ($parentid) {
//                    $model->find($parentid);
//                });
//                $catg['cat_group'] = $Category['children_group'];
//            } catch (Exception $exc) {
//            }//end try
//        }//end if

        $notice = 'category added';
        try {
            $categoryid = params()->post['categoryid'];
            if (!empty($categoryid)){
                $Category->find($categoryid);
                
                if (params()->post['delete'] == 1) {
                    $Category->deleteRecursively();
                    $notice = 'category removed';
                    $catg = array();
                } else {
                    $Category['cat_name'] = params()->post['name'];
                    $Category->save();
                    $notice = 'category updated';
                    $catg = $Category->result;
                }//end if
            }else{
                $Category->add();
                $Category->from($catg);
                $Category->save();
                $catg = $Category->result;
            }//end if
        } catch (Exception $exc) {
            render()->json = array('status' => 'failed', 'result' => null, 'notice' => 'category lists', 'response' => $exc->getMessages());
        }//end try

        /**
         * query all categories
         */
        $tree = $Category->tree('*');
        render()->json = array('status' => 'success', 'result' => $tree, 'notice' => $notice, 'response' => $catg);
    }

}