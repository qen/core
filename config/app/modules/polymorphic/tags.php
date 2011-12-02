<?php
namespace Core\App\Modules\Polymorphic;
use \Core\App\Module;
use \Core\Tools;
use \Core\Debug;
use \Core\Exception;

class Tags extends \Core\Model
{
    public static $Name            = 'Tags';
    public static $Table_Name      = 'mod_tags';
    public static $Find_Options    = array();

    /**
     *
     * @access
     * @var
     */
    public function cleanup()
    {
        $parent = parent::$Table_Name;
        $self   = static::$Table_Name;
        
        $sql = "delete from {$parent} 
        where {$parent}.tagid NOT IN (select tagid from {$self} )
        ";

        $this->sqlExecute($sql);
        return false;
    }

    /**
     *
     * @access
     * @var
     */
    public function query($tags)
    {
        $retval = array();

        try {
            $this->name($tags);
            if (!$this->isEmpty()) {
                $found = $this->results;
                $retval = $this->resultkeys;
                foreach ($this as $k => $v) {
                    $idx = array_search($v['tag_name'], $tags);
                    if ($ids === false) continue;
                    unset($tags[$idx]);
                }// end foreach
            }//end if
        } catch (Exception $exc) {
        }//end try

        if (!empty($tags)) {
            foreach ($tags as $k => $v) {
                $this->object()->add();
                $this['tag_name'] = $v;
                $this->object()->save();
                $retval[] = $this['tagid'];
            }// end foreach
            $this->is_cached = null;
        }//end if

        if (empty($retval))
            $retval = false;

        $this->find($retval);

        return $retval;
    }

    public function name($param)
    {
     
        $search = 'tag_name';
        if (!is_array($param)) {
            $param  = $param.'%';
            $search = '%tag_name';
        }//end if
        
        $this->find($param, array( 'search' => $search ));
    }

}