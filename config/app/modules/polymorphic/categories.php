<?php
namespace Core\App\Modules\Polymorphic;
use \Core\App\Module;
use \Core\Tools;
use \Core\Debug;

class Categories extends \Core\Model
{
    public static $Name             = 'Categories';
    public static $Table_Name       = 'mod_categories';
    public static $Find_Options     = array();

    protected function initialize()
    {
        $this->addEvent('read', function(&$data){
            $data['cat_group_full'] = $data['cat_group'];

            $group = explode('/', $data['cat_group']);
            array_shift($group);
            $data['cat_group'] = '/'.implode('/', $group);
            $data['children_group'] = "{$data['cat_group']}{$data['categoryid']}/";

            $data['children_group_full'] = "{$data['cat_group_full']}{$data['categoryid']}/";

        });

    }
    
    protected function sanitize($sanitize)
    {
        $sanitize['cat_typestat']->bit = 0;
    }

    protected function validate($validate)
    {
        /**
         * check if object is associated
         */
        if ($this->is_associated) return true;
        
        $validate['cat_name']->require = 'cat_name is required';
        $validate['cat_group']->require = 'cat_group is required';
    }
    
    public final function tree($query = '*', array $options = array())
    {
        if (empty($options['limit']))
            $options['limit'] = 100;

        $options['collectby'] = 'cat_group';

        $this->find($query, $options);

        $all = $this->results;

        $retval = array();
        foreach ($all['/'] as $k => $v) {
            $v['children'] = $this->children($v['children_group']);
            $retval[] = $v;
        }// end foreach

        return $retval;
    }

    private function children($group)
    {
        $all    = $this->results;
        $loop   = $all[$group];
        if (empty($loop)) return null;

        foreach ($loop as $k => $v) {
            $loop[$k]['children'] = $this->children($v['children_group']);
        }// end foreach

        return $loop;
    }

    public function deleteRecursively()
    {

        $categoryid = $model['categoryid'];
        $children   = $model['children_group_full'];

        /**
         * first delete the current category
         */
        $this->remove();

        /**
         * then query if there is any children category
         * loop and delete them all
         */
        try {

            $this->find("{$children}%", array(
                'search' => '%cat_group'
            ));

            foreach ($model as $k => $v) {
                $model->remove();
            }// end foreach

        } catch (Exception $exc) {
        }//end try

        return true;
    }

    public function findRecursively($query = '*', array $options = array())
    {

        $this->find($query, $options);

        $catgroup   = $this['cat_group_full'];
        $query      = $this['cat_group_full'].'%';
        $options['search'] = '%cat_group';

        $this->find($query, $options);

        return true;

    }

}