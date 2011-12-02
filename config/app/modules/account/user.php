<?php
namespace Core\App\Modules\Account;
use \Core\App\Module;
use \Core\Tools;
use \Core\Debug;
use \Core\Exception;
use \Core\Model;

class User extends Model
{
    public static $Table_Name    = 'mod_users';
    public static $Find_Options  = array(
        'conditions'    => array(
            '&acnt_typestat' => 1
        )
    );

    public $set_password = '';
    
    /**
     *
     * @access
     * @var
     */
    protected function initialize()
    {
    }

    /**
     *
     * @access
     * @var
     */
    protected function sanitize($sanitize)
    {
        $sanitize['usr_settings']->hash = array();
        $sanitize['usr_typestat']->bit = 0;

        $self = $this;
        $sanitize->addRule('set_password', function($value, $options) use ($self) {
            if (empty($self->set_password) && empty($self['userid'])) return sha1($value);
            
            # return 3 null array to remove the field from the data to be saved
            //if (empty($self->set_password)) array(null, null, null);
            
            return array(null, null, null);
        });

        $sanitize['usr_password']->set_password = sha1($this->set_password);

    }

    /**
     *
     * @access
     * @var
     */
    protected function validate($validate)
    {
        $self = $this;
        $validate->addRule('set_username', function($value, $options) use ($self) {
            try {
                $self->checkUsername($value, $self['userid']);
                return true;
            } catch (Exception $exc) {
                return false;
            }//end try
        });
        $validate['usr_username']->require  = 'Username is required';
        $validate['usr_username']->username = 'Must be at least 8 characters long and must consist of numbers and/or letters only.';
        $validate['usr_username']->set_username = 'Username already exists';

        $validate['usr_email']->require = 'User email is required';
        $validate['usr_email']->email = 'Email address is invalid';
        
        $validate->addRule('set_password', function($value, $options) use ($self) {
            # require password if new user only
            if (empty($self->set_password) && empty($self['userid'])) return false;
            
            return false;
        });
        $validate['usr_password']->password = 'Must be at least 8 characters long and must consist of numbers and/or letters only.';
        $validate['usr_password']->set_password = 'Password is required';

    }

    /**
     *
     * @access
     * @var
     */
    public function checkUsername($username, $userid = 0)
    {
        $username_check['sql'] = 'select userid from mod_users where usr_username = ? and userid != ?';
        $username_check['var'] = array($username, $userid);

        if (empty($userid)) {
            $username_check['sql'] = 'select userid from mod_users where usr_username = ?';
            $username_check['var'] = array($username);
        }//end if

        $result = $this->sqlSelect($username_check['sql'], $username_check['var']);
        if (empty($result)) return true;

        throw new Exception('Username '.$username.' already exists');
    }

    /**
     *
     * @access
     * @var
     */
    public function verification($username, $password)
    {
        if ($this->isEmpty())
            throw new Exception('Invalid user account');
        
        $password = sha1(trim($password));
        $username = trim($username);
        if ($this['usr_username'] != $username || $this['usr_password'] != $password)
            throw new Exception('Invalid user account');
        
    }

}