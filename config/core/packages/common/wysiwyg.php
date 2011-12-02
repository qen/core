<?php
namespace Core\App\Controllers;

use Core\Debug;
use Core\Tools;
use Core\Exception;
use Core\App\Path;
use Core\App\Module;

class Wysiwyg extends \Core\Controller
{

    public $restricted = array('uploads', 'canvas');
    public $module = null;

    public function doInitializeController()
    {
    }
    
    public function doFinalizeController()
    {
    }

    public function index($id, $name = '')
    {
        static $Response = array('rules'  => 'allow_file_extensions' );
        
        $Files = $this->module;

        try {
            $Files->find($id, array(
                'select_fields' => null,
                'conditions'    => array(
                    '%file_group' => 'wysiwyg/%'
                )
            ));
        } catch (Exception $exc) {
            
            $this('http_status', 404);
        }//end try
        
        $resize = $this['d'];
        if (empty($resize))
            $resize = '800x600';
        
        try {
            $Files->touch();
            $Files->fetch(array('resize' => $resize));
            
            $this('http_modified', $Files['file_date_updated']);
            
            $this('render_content', $Files['file_data'], $Files['file_mime']);
            
        } catch (Exception $exc) {
            var_export($exc->getMessages());exit;
            $this('http_status', 404);
        }//end try

        $this('http_status', 200);
    }

    /**
     *
     * @access
     * @var
     */
    public function canvas()
    {
        if (in_array('canvas', $this->restricted))
            $this('http_status', 404);

        $this['@template.render'] = '/wysiwyg/canvas.html';
    }

    /**
     *
     * @access
     * @var
     */
    public function upload()
    {
        if (in_array('uploads', $this->restricted))
            $this('http_status', 404);
        
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

        $this('render_text', $retval);

    }

}
