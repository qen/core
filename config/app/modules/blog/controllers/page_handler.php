<?php
namespace Core\App\Modules\Blog\Controllers;

use Core\Debug;
use Core\Tools;
use Core\Exception;
use Core\App\Path;
use Core\App\Module;

require_once 'common/mailer.php';
use \Mailer as Mailer;

class PageHandler extends \Core\Controller
{

    public $module = null;
    
    public function __construct()
    {
        params()->session();
    }
    

    /**
     *
     * @method :greedy
     */
    public function index()
    {
        static $greedy;
        
        static $template = '/inc/page/default.html';
        try {
            $Page = $this->module;
            if (is_null($Page))
                throw new Exception('wtf');
        } catch (Exception $exc) {
            render()->status = 404;
        }//end try

        render('page', $Page);
        
        if (!empty($Page['page_settings']['form']) && !empty(params()->post['f']))
            return $this->formSubmit($Page['page_settings']);
            
        render()->flash['page_form_uuid'] = Tools::Uuid();

    }

    protected function formSubmit($param)
    {
        $form = $param['form'];
        $uuid = $this['@flash']['page_form_uuid'];

        if ($uuid != $this['@post']['formid']) {
            $this['@notify']['errors'] = "{$uuid}";
            $this['@notify']['errors'] = "{$this['@post']['formid']}";
            return true;
        }

        $validate = array();
        foreach ($form as $name => $input) {
            if (empty($input['validate'])) continue;

            foreach ($input['validate'] as $criteria => $v) 
                $validate[$criteria][$name] = $v;
            
        }// end foreach
        
        try {
            Tools::Validate($validate, $this['@post']['f']);
           
        } catch (Exception $exc) {
            $uuid = Tools::Uuid();
           
            
            $this['@flash']['form_post'] = $this['@post']['f'];

            $this['@flash']['page_form_uuid'] = $uuid;
            
            
            $this['@notify']['errors'] = $exc->getMessages();
            return true;
        }//end try

        /**
         * work on form submit
         */
        $post = $this['@post']['f'];
        $notify = array();
        foreach ($param['form'] as $k => $v) {
            if ($v['type'] == 'textarea') {
                $notify[] = "{$v['title']}\n\n{$post[$k]}";
                continue;
            }//end if
            $notify[] = "{$v['title']}: {$post[$k]}";

        }// end foreach

        $notify = implode("\n\n", $notify);
        
        $mail['to']         = $param['form_notify']['email'];
        $mail['subject']    = "Poke by {$post['fname']} ";
        $mail['body']       = "\n\n{$notify}\n\n";
        $mail['from']       = $param['form_notify']['from_email'];
        $mail['from_name']  = $param['form_notify']['from_name'];

        $mailer = new Mailer;
        $mailer->sendMail($mail);

        /**
         * redirect here
         */
        if (empty($param['form_notify']['message']))
            $param['form_notify']['message'] = 'Inquiry sent';

        $this['@notify']['status'] = $param['form_notify']['message'];
        $this('redirect');
    }

}