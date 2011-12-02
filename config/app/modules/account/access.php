<?php
namespace Core\App\Modules\Account;
use Core\Exception;
use Core\Model;

/**
 *
 * @author
 */
class Access extends Model
{
    const STAT_ACTIVE       = 1;
    const STAT_SUSPENDED    = 2;
    const STAT_PENDING      = 4;
    
    public static $Table_Name    = 'mod_accounts_access';
    public static $Find_Options  = array();

    protected function initialize()
    {
        $this->addEvent('read', function(&$data){
            $data['status'] = 'Inactive';
            if (Access::STAT_ACTIVE & $data['acc_typestat'])
                $data['status'] = 'Active';
        });
    }
    
    /**
     *
     * @access 
     * @var 
     */
    protected function sanitize($sanitize)
    {
        $sanitize['acc_typestat']->bit = 0;
        $sanitize['acc_settings']->hash = array();
    }

    /**
     *
     * @access
     * @var
     */
    protected function validate($validate)
    {
        $validate['acc_name']->require  = 'Access Name is Required';
        $validate['acc_uid']->require   = 'Access Unique Id is Required';
        $validate['accountid']->require = 'Account Id is Required';
    }

    /**
     *
     * @access
     * @var
     */
    public function getPetal($domain)
    {
        $this->find($domain, array(
            'search' => 'acc_name',
            'conditions' => array(
                '&acc_typestat' => self::STAT_ACTIVE,
                'acc_code'      => 'petal'
            )
        ));
    }



}