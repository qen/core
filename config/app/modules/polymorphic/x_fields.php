<?php
namespace Core\App\Modules\Polymorphic;
use \Core\App\Module;
use \Core\Tools;
use \Core\Debug;

class XFields extends \Core\Model
{
    public static $Name             = '';
    public static $Table_Name       = '';
    public static $Find_Options     = array();
    public static $Sanitize         = array();

    public function validate()
    {
        /**
         * check if object is associated
         */
        if ($this->is_associated) {
            
            return true;
        }//end if
        
        $check = array();
        $check['required'] = array(
        );

        $this->doValidations($check);

    }

}