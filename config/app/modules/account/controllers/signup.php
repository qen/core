<?php
namespace Core\App\Modules\Account\Controllers;

use Core\Tools;
use Core\Debug;
use Core\Exception;
use Core\App\Path;
use Core\App\Module;
use Core\App as app;

class Signup extends Base
{
    //public static $Dump_Vars = true;
    //public static $Session   = array( 'name' => '' );

    public $module = null;

    public $register_redirect = '';

    public function doInitializeController()
    {

    }
    
    public function doFinalizeController()
    {
    }

    public function index()
    {
     
        if ('Signup' == $this['do']) {

            try {
                $this->verifyCaptcha($this['verify']);
            } catch (Exception $exc) {
                $this['@notify.errors'] = $exc->getMessages();
                return true;
            }//end try

            $Account = $this->module;
            try {
                $Account->signup(array(
                    'usr_fname'     => $this['fname'],
                    'usr_lname'     => $this['lname'],
                    'usr_email'     => $this['email'],
                ));
                $this('redirect', 'confirm');
            } catch (Exception $exc) {
                $this['@notify.errors'] = $exc->getMessages();
            }//end try

        }//end if

    }

    /**
     *
     * @access
     * @var
     */
    public function confirm()
    {

    }

    /**
     *
     * @access
     * @var
     */
    public function register($md5)
    {
        $Account = $this->module;
        /**
         * @todo captcha validation
         */
        try {
            $User = $Account->register($md5);
        } catch (Exception $exc) {
            $this('http_status', 404);
        }//end try

        $post = $this['@post.f'];
        if (!empty($post)) {

            $validate['required']['usr_username'] = "Username is required";
            $validate['required']['usr_password'] = "Password is required";
            $validate['char']['usr_password'] = "^[0-9a-zA-Z_\.@\-/\+]{5,}$|Password must be at least 5 characters long and must consist of numbers and/or letters only.";
            $validate['match']['usr_password'] = "Password didn't match" ;
            
            try {
                $User->checkUsername($post['usr_username'], $User['userid']);
                Tools::Validate($validate, $post);
            } catch (Exception $exc) {
                $this['@notify.errors'] = $exc->getMessages();
                return true;
            }//end try

            $Account->register($md5, $post['usr_username'], $post['usr_password']);
            $this['@notify.status'] = 'Account Registered!';

            $Account->clear();
            try {
                $Account->authenticate($post['usr_username'], $post['usr_password']);
            } catch (\Core\Exception $exc) {
                $this('dump', $exc);
            }//end try

            $register_redirect = $this->register_redirect;
            if (empty($register_redirect)) $register_redirect = '/login';
            $this['@session.login_redirect'] = $register_redirect;

            $login = new Login;
            $login->Account = $Account;
            $login->doInitializeController('login');
            
        }//end if

        $this['user'] = $User;
        
    }

}