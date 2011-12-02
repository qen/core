<?php
namespace Core\App\Modules\Blog;
use \Core\App\Module;
use \Core\Tools;
use \Core\Debug;
use \Core\Model;

class Page extends Model
{
    const STATUS_VISIBLE    = 1;
    
    public static $Name            = 'Pages';
    public static $Table_Name      = 'mod_pages';
    public static $Find_Options    = array(
        'conditions' => array(
            'page_typestat' => '1'
        ),
        'orderby' => 'page_sort'
    );
    
    public static $Sanitize        = array(
        'page_settings' => 'hash',
        'page_typestat' => 'bit',
        'page_details'  => 'html-js',
        'page_url'      => 'url'
    );

    protected function initialize()
    {
        $name   = $this::$Name;
        $self   = $this;
        
        /**
         * associate has many meta tags
         */
        $this->Metatags = Model::HasMany( 'Polymorphic.Metatags', 'meta_uid',
            array(
                'limit'         => 1,
                'conditions'    => array( 'meta_group' => (string) $self )
            ),
            function($model) use ($name, $self) {
                $model->addEvent('save', function($meta) use ($name, $self) {
                    $meta['meta_group'] = (string) $self;
                });
            }
        );

    }

    /**
     *
     * @access
     * @var
     */
    protected function sanitize($sanitize)
    {
        $sanitize['page_typestat']->bit             = 0;
        $sanitize['page_settings']->hash            = array();
        $sanitize['page_details']->html['allow_js'] = '';

    }

    protected function validate($validate)
    {
        $validate['page_url']->require = 'page_url is required';
        $validate['page_title']->require = 'page_title is required';
        $validate['page_details']->require = 'page_details is required';
    }

}