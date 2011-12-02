<?php
namespace Core\App\Controllers;
use Core\Debug;
use Core\View;
use Core\App\Path;
use Core\App;

/**
 *
 * @author
 *
 */
class Minify extends \Core\Controller
{
    private $debug = false;
    
    /**
     *
     * @access 
     * @var 
     */
    private function filepath($name)
    {
        if ($name{0} == DIRECTORY_SEPARATOR)
            $name = substr($name, 1);
        
        $paths = Path::ViewDir();
        if (params()->get['debug'])
            echo "// - ".implode("\n// - ", $paths)."\n\n\n";

        foreach ($paths as $k => $path) {
            $file = $path.DIRECTORY_SEPARATOR.$name;

            if (params()->get['debug'])
                echo "// {$file} ";

            if (!is_file($file)) {
                if (params()->get['debug']) echo " [not found] ";
                continue;
            }//end if
            
            if (params()->get['debug'])
                echo "\n";

            $file = realpath($file);

            return $file;
        }// end foreach

        /**
         * check if modules/[name]/public files available
         */
        if (preg_match('|^modules/([^/]+)/(.*)|i', $name, $matches)) {
            $path = App\PATH;

            $file = $path.DIRECTORY_SEPARATOR."modules/{$matches[1]}/public/{$matches[2]}";

            if (params()->get['debug'])
                echo "// {$file} \n";

            if (is_file($file)) {
                $file = realpath($file);
                return $file;
            }//end if

        }//end if
        
        return false;
    }

    /**
     *
     * @access
     * @var
     */
    protected function minify($param, $type, $klass)
    {
        $all = explode(',',base64_decode($param));
        array_walk($all, function(&$value, $key){
            $value = trim($value);
        });

        $root       = Path::WebrootDir();
        $tmpfile    = Path::TempDir('minify').'/'.md5($param).'.'.$type;
        $tmpfile_ts = (int)@filemtime($tmpfile);
        $file_ts    = $tmpfile_ts;
        $files      = array();

        $content_type = (($type == 'css')? 'text/css;' : 'text/javascript;').' charset=UTF-8';
        
        foreach ($all as $k => $file) {
            if (empty($file)) continue;
            /**
             * security
             * check for css or js file extension only
             */
            if (!preg_match("|\.{$type}$|", $file))  continue;

            $filepath = $this->filepath($file);
            //$filepath = "{$root}/{$file}";

            if ($filepath === false) {
                echo "// {$file} does not exists \n";
                continue;
            }//end if
            
            $file_ts = max(@filemtime($filepath), $file_ts);
            $files[$file] = $filepath;
        }// end foreach



        if (empty($files))
            $this('http_status', 404);
        
        if ($this->debug === false) {
            
            render()->last_modified = $file_ts;
            
            /**
             * check if tempfile exists and
             * file timestamp is still equal to tempfile timestamp
             */
            if (is_file($tmpfile) && $tmpfile_ts == $file_ts) {
                $output = file_get_contents($tmpfile);
                render()->inline = $output;
                render()->content_type = $content_type;
            }//end if
            
        }//end if

        $content = array();
        $docache = true;
        foreach ($files as $file => $filepath) {
            if (!is_file($filepath)) {
                $content[$file] = "// not found {$file}";
                $docache        = false;
                continue;
            }//end if
            
            $content[$file] = file_get_contents($filepath);

            if (!empty($klass))
                $content[$file] = $klass::minify($content[$file]);

        }// end foreach

        /**
         * if type is css
         * rewrite the url path if it's relative
         */
        if ($type == 'css') {

            $webroot = Path::Uri('root').'/';
            foreach ($content as $file => $value) {
                $pathinfo   = pathinfo($file);
                $rootpath   = $webroot.$pathinfo['dirname'];

                if (!preg_match('|/$|', $rootpath))
                    $rootpath .= '/';
                
                //preg_match_all("|url\(['\"]?([^\)].*?)['\"]?\)|is", $value, $matches);
                $content[$file] = preg_replace("|url\(['\"]?([^\)].*?)['\"]?\)|is", 'url("'.$rootpath.'${1}")', $value);

            }// end foreach

        }//end if

        $output = implode("\n", $content);
        if ($docache) 
            file_put_contents($tmpfile, $output);

        //$this('render_text', $output, $content_type);

        render()->inline = $output;
        render()->content_type = $content_type;

    }

    /**
     *
     * @access
     * @var
     */
    public function js($param)
    {
        require_once 'common/js_min_plus.php';
        return $this->minify($param, 'js', 'JsMinPlus');
    }

    public function css($param)
    {
        require_once 'common/css_min.php';
        return $this->minify($param, 'css', 'CssMin' );
    }

    

}

