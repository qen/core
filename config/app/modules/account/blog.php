<?php
namespace Core\App\Modules\Account;
use \Core\App\Module;
use \Core\Tools;
use \Core\Debug;
use \Core\Db;
use \Core\Model;

/**
 *
 * @author
 *
 */
class Blog extends Model
{

    const STATUS_ACTIVE     = 1;

    public static $Table_Name    = 'mod_accounts';
    public static $Find_Options  = array();

    protected function initialize()
    {
        $self = $this;

        $this->Post = Model::HasMany('Blog.Post', 'blog_authorid',
            array(
                'limit' => 100
            )
        );

        $this->Access = Model::HasMany('Account.Access', 'accountid',
            array(
                'limit'     => 100
            )
        );

        $this->User = Model::HasMany('Account.User', 'usr_uid',
            array(
                'limit'     => 1
            )
        );
    }

    /**
     *
     * @access
     * @var
     */
    protected function sanitize($sanitize)
    {
        $sanitize['acnt_settings']->hash = array();
        $sanitize['acnt_typestat']->bit = 0;
    }

    /**
     *
     * @access
     * @var
     */
    protected function validate($validate)
    {
        
        $validate['acnt_name_prefix']->require = 'First Name is Required';
        $validate['acnt_name_suffix']->require = 'Last Name is Required';
        $validate['acnt_email']->require = 'Email is Required';

        $validate['acnt_email']->email = 'Email is invalid';

    }


    /**
     *
     * @access
     * @var
     */
    public function authenticate($username, $password)
    {

        $this->User->find($username, array('search' => 'usr_username'));

        /**
         * new user do sha1 check only
         */
        $this->User->verification($username, $password);

        $this->find();
        
        return false;
    }

}