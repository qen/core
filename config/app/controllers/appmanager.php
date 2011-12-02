<?php
namespace Core\App\Controllers;
use Core\Exception;
use Core\Debug;
use Core\Tools;
use Core\App\Path;
use Core\App\Module;

/**
 *
 * @author
 *
 */
class Appmanager extends \Core\Controller
{

    public function __construct()
    {
        params()->session();
        params()->cookies(array( 'expire' => 24 ));
    }

    private function auth()
    {
        $controller = Module::Account('Controllers\\Login');
        $controller->module = Module::Account('Blog');
        $do_login = $controller->doAction('authorize');
        if ($do_login){
            params()->flash['notify'] = 'Please login';
            redirect('/appmanager/login');
        }//end if

        return $controller->module;
    }
    
    public function index()
    {
        static $greedy;
        
        $args = func_get_args();
        if (!empty($args)) render()->status = 404;

        $user = params()->session['account'];
        if (!empty($user)) redirect('blogs');
        
        params()->session['login_redirect'] = params()->uri['full'];
        render()->template = '/appmanager/login.html';
    }

    public function login()
    {
        static $greedy;
        
        if (empty(params()->session['login_redirect']))
            params()->session['login_redirect'] = '/appmanager';

        $controller = new Login;
        $controller->Account = Module::Account('Blog');

        /**
         * do initilize controller will return true
         * if authorization fails
         */
        if ($controller->doAction('authorize') === false)  redirect('/appmanager');
        
        return $controller;
    }

    public function logout()
    {
        params()->session['User']       = null;
        params()->session['authuser']   = null;
        
        redirect('login');
    }

    /**
     *
     * @access
     * @var
     */
    public function account($mode = '')
    {
        $Account = $this->auth();

        if ('save' == $mode) {

            try {
                //$this('validate', $check, $this['d']);
                //params('post.d')->validate(function($validate){});
                $Account->from(params()->post['d']);
                $Account->save();
            } catch (Exception $exc) {
                params()->flash['errors'] = $exc->getMessages();
                return true;
            }//end try

            $this['@notify.status'] = 'Account saved';
            $this('redirect', 'account');
        }//end if

        $this['details'] = $Account;

    }

    public function blogs()
    {
        static $greedy;
        
        $this->auth();
        $controller = Module::Blog('Controllers\\PostManager');
        $controller->module = Module::Blog('Post');
        $controller->files  = Module::Polymorphic('Files');
        return $controller;
    }

    public function cms()
    {
        static $greedy;
        
        $this->auth();
        $controller = Module::Blog('Controllers\\PageManager');
        #$controller->files = Module::Blog('Files');
        $controller->module = Module::Blog('Page');
        $controller->allow['add'] = true;
        return $controller;
    }


    public function utils()
    {
        static $greedy;
        require 'common/utils.php';
        return new Utils;
    }

}