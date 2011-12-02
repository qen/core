<?php
namespace Core\App\Modules\Account\Controllers;

use Core\Debug;
use Core\Exception;
use Core\App\Path;
use Core\App\Module;
use Core\Tools;

class Login extends Base
{

    public $Account = null;
    
    public function doAction($action)
    {
        static $reserved;
        
        switch ($action) {
            case 'authorize':
                $account    = params()->session['account'];#$this['@session.account'];
                $authuser   = params()->session['authuser'];#$this['@session.authuser'];
                $cookie     = params()->cookie['authuser'];#$this['@cookie.authuser'];

                #params()->debug($account, $authuser, $cookie, $_COOKIE, $_SESSION);

                $do_login   = empty($account);
                if ($do_login) return $do_login;
                
                $do_login = !(($cookie['uuid'] == $authuser['uuid'])
                    && ($cookie['accountid'] == $authuser['accountid'])
                    && ($authuser['accountid'] == $account['accountid']));

                logger(array($account, $authuser, $cookie, $do_login), 'authorize');
                
                /**
                 * if cookie timestamp is 18min old
                 * reinitialize cookie timestamp
                 */
                $ts  = strtotime('+18 minutes', $cookie['ts']);
                $now = strtotime('now');
                
                #if ( true ) {
                if (max($ts, $now) == $now ) {

                    $uuid = Tools::Uuid();
                    params()->session['authuser']['uuid']   = $uuid;
                    params()->cookie['authuser']['uuid']    = $uuid;
                    params()->cookie['authuser']['ts']      = strtotime('now');

                    $account    = params()->session['account'];
                    $authuser   = params()->session['authuser'];
                    $cookie     = params()->cookie['authuser'];

                    logger(array($uuid, $account, $authuser, $cookie, $do_login), 're cookie');
                    
                }//end if
                
                if ($do_login) {
                    params()->session['account']    = null;
                    params()->session['authuser']   = null;
                    params()->cookie['authuser']    = null;
                }//end if

                if (!empty($this->Account))
                    $this->Account->find($account['accountid']);
                
                return $do_login;
                break;
            
            case 'logout':
                params()->session['account']    = null;
                params()->session['authuser']   = null;
                params()->cookie['authuser']    = null;
                return true;
                break;
            
            case 'login':                
                $Account = $this->Account;
                if (is_null($Account)) throw new Exception('login failed');
                
                $authuser = array(
                    'accountid'     => $Account['accountid'],
                    'lastlogin'     => $Account['User']['usr_date_logged'],
                    'uuid'          => Tools::Uuid(),
                    'ts'            => strtotime('now')
                );
                
                $User = $Account->User->object();
                $User['usr_date_logged'] = date("Y-m-d H:i:s");
                $User['usr_ipaddy']      = params()->server['REMOTE_ADDR'];
                $User->save();
                
                params()->cookie['authuser']        = $authuser;
                params()->session['authuser']       = $authuser;
                params()->session['account']        = $Account->result;
                params()->session['account_user']   = $User->result;

                $redirect = params()->session['login_redirect'];
                params()->session['login_redirect'] = null;

                params()->flash['notify'] = 'Login successfull';
                
                redirect($redirect);
                return true;
                break;
            
            default:
                break;
        }// end switch

    }

    public function index()
    {
        
        $Account = $this->Account;
        $post = params()->post;
        if (!empty($post)) {
            
            try {
                $Account->authenticate($post['username'], $post['password']);
            } catch (\Core\Exception $exc) {
                params()->flash['errors'] = 'Username and/or password is invalid';
                redirect();
            }//end try
            
            $this->doAction('login');

        }//end if

    }

    /**
     *
     * @access
     * @var
     */
    public function reset($confirm = '')
    {
        $Account    = $this->Account;
        $email      = params()->get['email'];
        if (!empty($email)) {

            try {
                $Account->resetRequest($email);
                params()->flash['notify'] = "Please check you're email for further instructions";
                redirect();
            } catch (Exception $exc) {
            }//end try
            
            return true;
        }//end if
        
        if (!empty($confirm)) {

            try {
                $User = $Account->reset($confirm);
            } catch (Exception $exc) {
                params()->flash['errors'] = 'Reset password request is invalid';
                redirect();
            }//end try

            if (!empty(params()->post)) {
                try {
                    $this->verifyCaptcha(params()->post['verify']);
                } catch (Exception $exc) {
                    params()->flash['errors'] = $exc->getMessages();
                    return true;
                }//end try

                try {
                    $Account->reset($confirm, $this['@post.*']);
                    params()->flash['notify'] = 'Password updated';
                    redirect('/login');
                } catch (Exception $exc) {
                    params()->flash['errors'] = $exc->getMessages();
                }//end try

            }//end if

            render()->template = 'password.html';
        }//end if
        
    }

}