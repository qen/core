<?php
namespace Core\App\Modules\Polymorphic\Controllers;

use Core\Debug;
use Core\Exception;
use Core\App\Path;
use Core\App\Module;

class TagManager extends \Core\Controller
{
    //public static $Dump_Vars = true;
    //public static $Session   = array( 'name' => '' );

    public $module = null;

    public function __construct()
    {
    }
    
    
    public function index()
    {

    }

    public function query()
    {
        $Tag = $this->module;
        try {
            /**
             * result expires for 1 minute
             */
            $Tag->name(params()->get['search']);
            //$this('http_modified', date('Y-m-d H:i:00'));
        } catch (Exception $exc) {
            render()->json = array('status' => 'failed', 'result' => null, 'notice' => 'category lists', 'response' => $exc->getMessages());
        }//end try

        render()->json = array('status' => 'success', 'result' => $Tag->results, 'notice' => 'tagnames', 'response' => null);
    }

}