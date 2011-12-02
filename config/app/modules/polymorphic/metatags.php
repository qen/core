<?php
namespace Core\App\Modules\Polymorphic;
use \Core\App\Module;
use \Core\Tools;
use \Core\Debug;

class Metatags extends \Core\Model
{
    public static $Name            = 'Metatags';
    public static $Table_Name      = 'mod_metatags';
    public static $Find_Options    = array();

    protected function sanitize($sanitize)
    {
        $sanitize['meta_analytics']->html['allow_js'] = '';
    }

    protected function validate($validate)
    {

        $self = $this;
        $validate->addRule('urlexists', function($url, $options) use ($self) {
            $result = $self->sqlSelect('select metaid from mod_metatags where page_url = ? ', array($url));
            if ($result[0]['cnt'] != 0) return false;
            return true;
        });
        
        $validate['page_url']->urlexists = 'Page url already exists';

    }

}