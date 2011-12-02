<?php
namespace Core\App\Modules\Polymorphic\Controllers;

use Core\Debug;
use Core\Tools;
use Core\Exception;
use Core\App\Path;
use Core\App\Module;

class FileHandler extends \Core\Controller
{

    public $restricted = array('uploads', 'canvas');
    public $module = null;

    public function __construct()
    {
        $this->module = Module::Polymorphic('Files');
    }
    
    public function __desctruct()
    {
    }

    /**
     *
     * @param :allow_file_extensions
     */
    public function index($id, $name = '')
    {
        
        $Files = $this->module;

        try {
            $Files->find($id, array(
                'select_fields' => null,
                'conditions'    => array(
                    '%file_group' => 'wysiwyg/%'
                )
            ));
        } catch (Exception $exc) {
            render()->status = 404;
        }//end try
        
        $resize = params()->get['d'];
        if (empty($resize))
            $resize = '800x600';
        
        try {
            $Files->touch();
            $Files->fetch(array('resize' => $resize));
            
            render()->last_modified = $Files['file_date_updated'];
            render()->content_type = $Files['file_mime'];
            render()->inline = function() use ($Files) {
                return $Files['file_data'];
            };
        } catch (Exception $exc) {
            var_export($exc->getMessages());exit;
            render()->status = 404;
        }//end try

        render()->status = 200;
    }

    /**
     *
     * @access
     * @var
     */
    public function upload()
    {
        if (in_array('uploads', $this->restricted))
            render()->status = 404;
        
        $Files = $this->module;

        $check = $Files->addFile($_FILES['file'], array(
            'file_uid'      => 100,
            'file_group'    => "wysiwyg/".Tools::Uuid()
        ));
        
        $retval = 'Upload failed';

        if ($check === true) {
            try {
                $Files->save();
                $retval = '/files/'.$Files['fileid'].'/'.$Files['file_name'];
            } catch (Exception $exc) {
                $retval = $exc->getMessage();
            }//end try
        }//end if

        render()->inline = $retval;

    }

}
