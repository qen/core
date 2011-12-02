<?php
namespace Core\App\Controllers;

use Core\Db;
use Core\Debug;
use Core\Exception;
use Core\App\Route;
use Core\App\Path;
use Core\App\Module;

class Utils extends \Core\Controller
{

    public function index()
    {
        $webroot = Path::Uri('path');
        
        $text = <<<EOF
<p>
phpinfo:<br />
<a href='{$webroot}/phpinfo'>{$webroot}/phpinfo</a>
</p>
<p>
clear template:<br />
<a href='{$webroot}/clear/tpl'>{$webroot}/clear/tpl</a><br />
</p>
<p>
clear minify cache:<br />
<a href='{$webroot}/clear/minify'>{$webroot}/clear/minify</a><br />
</p>
<p>
clear image cache:<br />
<a href='{$webroot}/clear/file_images'>{$webroot}/clear/file_images</a><br />
</p>
<p>
twig generate countries array:<br />
<a href='{$webroot}/twig/generate/countries'>{$webroot}/twig/generate/countries</a><br />
</p>
<p>
twig generate timezone array:<br />
<a href='{$webroot}/twig/generate/timezones'>{$webroot}/twig/generate/timezones</a><br />
</p>
<p>
get json schema of a module:<br />
<a href='{$webroot}/getschema/account'>{$webroot}/getschema/account</a><br />
<a href='{$webroot}/getschema/account::access'>{$webroot}/getschema/account::access</a><br />
</p>
<p>
execute Unit Test ( simple test ):<br />
<a href='{$webroot}/simpletests/account_modules'>{$webroot}/simpletests/account_modules</a><br />
</p>
EOF;
        render()->inline = $text;
    }

    /**
     *
     * @access
     * @var
     */
    public function phpinfo()
    {
        phpinfo();
        exit;
    }

    public function clear($param)
    {
        $cleardir = function($dirpath, $pattern){

            $files = array();
            
            if (is_dir($dirpath) && is_writable($dirpath)) {
                $files = glob($dirpath.$pattern);
                if ($files === false || count($files) == 0)
                    return false;
                foreach($files as $f=>$file) {
                    @unlink($file);
                    rmdir($file);
                }
            }//end if

            return $files;
        };

        switch ($param) {

            case 'tpl':
                $dirpath    = Path::TempDir('tpl');
                $retval     = $cleardir($dirpath, '/*');
                break;

            case 'minify':
                $dirpath    = Path::TempDir('minify');
                $retval     = $cleardir($dirpath, '/*');
                break;

            case 'file_images':
                $dirpath    = Path::TempDir('file_images');
                $retval     = $cleardir($dirpath, '/*');
                break;
            
            default:
                break;
        }// end switch

        render()->inline = $retval;
        render()->content_type = 'text/plain';
    }

    public function twig($action, $param)
    {
        if ($action != 'generate') render()->status = 404;

        $output = array();
        switch ($param) {
            case 'countries':
                $Country = Module::Country();
                $Country->find('*');

                foreach ($Country as $k => $v) {
                    $output[] = "['name': '{$v['country']}', 'code': '{$v['code']}']";
                }// end foreach

                $output = implode(",\n", $output);
                break;

            case "timezones":
                $Country    = Module::Country();
                $timezones  = $Country->config()->timezones();

                foreach ($timezones as $k => $v) {
                    $output[] = "['name': '{$k}', 'time': '{$v}']";
                }// end foreach

                $output = implode(",\n", $output);
                break;

            default:
                render()->status = 404;
                break;
        }// end switch

        render()->inline = $output;
        render()->content_type = 'text/plain';
    }

    /**
     *
     * @access
     * @var
     */
    public function getschema($param)
    {
        list($module, $class) = explode('::', $param);

        $module = ucfirst($module);
        $class  = ucfirst($class);
        $object = Module::$module($class);

        $dbconfig = $object->config->db();
        if (is_null($dbconfig))
            throw new Exception("{$param} failed to connect to db please check it's connection settings");

        $db     = Db::Instance($dbconfig);
        $schema = $db->getSchema($object::$Table_Name, $object::$Sanitize);
        $schema = json_encode($schema);
        
        $text = <<<EOF
"{$object::$Table_Name}" : {$schema}
EOF;

        render()->inline = $texgt;
        render()->content_type = 'text/plain';
    }

}