<?php
namespace Core\App\Modules\Blog;
use \Core\Tools;
use \Core\Debug;
use \Core\Exception;
use \Core\Model;

class Post extends Model
{
    const STAT_VISIBLE  = 1;
    const STAT_FEATURED = 2;
    
    public static $Name            = 'Blogs';
    public static $Table_Name      = 'mod_blog_posts';
    public static $Find_Options    = array(
        'orderby' => 'blog_date desc, blogid desc',
        'conditions' => array (
            '&blog_typestat' => self::STAT_VISIBLE
        )
    );

    protected function initialize()
    {
     
        $this->addEvent('read', function(&$data) {
            $data['status'] = ($data['blog_typestat'] & 1)? 'Shown': 'Hidden';
        });

        $name   = $this::$Name;
        $myname = (string)$this;
        $self   = $this;

        /**
         * HasMany Associations
         *
         * $this->(name) = Model::HasMany( '<Module>.<Class>', '[<foreign table>.]<foreign key>', find_options_array, initialize_function );
         */

        /**
         * BelongsTo Associations
         *
         * $this->(name) = Model::BelongsTo( '<Module>.<Class>', '[<foreign table>.]<foreign key>', find_options_array, initialize_function );
         */
        
        /**
         * associate has many categories
         */
        $this->Categories = Model::HasMany( 'Polymorphic.Categories', 'mod_categories_joins.joinid',
            array(
                'limit'         => 100,
                'conditions'    => array( '%cat_group' => 'Blogs/%' )
            ),
            function($model) use ($name) {
                $model->addEvent('save', function($catg) use ($name) {
                    $catg['cat_group']   = $name.'/';
                });
            }
        );

        /**
         * associate has many meta tags
         */
        $this->Metatags = Model::HasMany( 'Polymorphic.Metatags', 'meta_uid',
            array(
                'limit'         => 1,
                'conditions'    => array( 'meta_group' => (string) $self )
            ),
            function($model) use ($name, $myname, $self) {

                $prefix = empty($self['blog_date']) ? 'now' : $self['blog_date'];
                $title  = $self['blog_title'];

                $model->addEvent('save', function($meta, $sanitize) use ($name, $myname, $prefix, $title) {

                    $meta['meta_group'] = $myname;

                    $sanitize->addRule('page_url', function($value, $options, $sanitize, $default) {

                        $text   = $value;
                        $prefix = '';
                        if (preg_match('|^\d+/\d+/\d+/|', $value)) {
                            list($y, $m, $d, $text) = explode('/', $value);
                            $prefix = "{$y}/{$m}/{$d}/";
                        }//end if

                        if (!empty($text)) {
                            $sanitize['x']->char['245,hypenate,lowercase'] = $text;
                            $clean = $sanitize(array('x' => $text));
                            return $prefix.$clean['x'];
                        }//end if

                        $options    = empty($options) ? 'now' : $options;
                        $text       = $default;

                        list($y, $m, $d) = explode('/', date('Y/m/d', strtotime($options)));
                        $prefix = "{$y}/{$m}/{$d}/";

                        $sanitize['x']->char['245,hypenate,lowercase'] = $text;
                        $clean = $sanitize(array('x' => $text));

                        return $prefix.$clean['x'];

                    });
                    
                    $sanitize['page_url']->page_url["{$prefix}"] = $title;
                    
                });

            }
        );

        /**
         * associate has many tags
         */
        $this->Tags = Model::HasMany( 'Polymorphic.Tags', 'mod_tags_joins.tag_uid',
            array(
                'limit'         => 20,
                'conditions'    => array( 'tag_group' => 'Blogs' )
            ),
            function($model) use ($name) {
                $model->addEvent('save', function($catg) use ($name) {
                    $catg['tag_group']   = $name;
                });
            }

        );

        /**
         * associate belongs to account blog
         */
        $this->Author = Model::BelongsTo( 'Account.Blog', 'blog_authorid',
            array(
                'limit'         => 1,
                'conditions'    => array()
            )
        );

    }

    protected function sanitize($sanitize)
    {
        $sanitize['blog_descr']->char[220]  = $this['blog_details'];
        $sanitize['blog_sort']->numeric     = 1;
        $sanitize['blog_typestat']->bit     = 0;
        $sanitize['blog_details']->html     = '';
        $sanitize['blog_settings']->hash    = array();

        $sanitize->addRule('blog_url', function($value, $options, $sanitize, $default) {

            $text   = $value;
            $prefix = '';
            if (preg_match('|^\d+/\d+/\d+/|', $value)) {
                list($y, $m, $d, $text) = explode('/', $value);
                $prefix = "{$y}/{$m}/{$d}/";
            }//end if

            if (!empty($text)) {
                $sanitize['x']->char['245,hypenate,lowercase'] = $text;
                $clean = $sanitize(array('x' => $text));
                return $prefix.$clean['x'];
            }//end if

            $options    = empty($options) ? 'now' : $options;
            $text       = $default;

            list($y, $m, $d) = explode('/', date('Y/m/d', strtotime($options)));
            $prefix = "{$y}/{$m}/{$d}/";

            $sanitize['x']->char['245,hypenate,lowercase'] = $text;
            $clean = $sanitize(array('x' => $text));

            return $prefix.$clean['x'];

        });

        $sanitize['blog_url']->blog_url["{$this['blog_date']}"] = $this['blog_title'];
        
    }

    protected function validate($validate)
    {
        $blogid = $this['blogid'];
        $self   = $this;
        $validate['blog_url']->addRule('urlexists', function($url, $options) use ($blogid, $self){
            $result = $self->sqlSelect("select count(*) as cnt from mod_blog_posts where blog_url = ? and blogid != ?", array($url, $blogid));
            if ($result[0]['cnt'] != 0) return false;
            return true;
        });
        
        $validate['blog_title']->require    = 'Title is required';
        $validate['blog_details']->require  = 'Details is required';
        $validate['blog_url']->urlexists    = 'Blog url already exists';
    }

}
