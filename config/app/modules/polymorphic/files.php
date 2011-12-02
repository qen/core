<?php
namespace Core\App\Modules\Polymorphic;

use \Core\App\Path;
use \Core\App\Module;
use \Core\Tools;
use \Core\Debug;
use \Core\Exception;


class Files extends \Core\Model
{
    const TYPE_IMAGE    = 1;
    const TYPE_DOCUMENT = 2;

    const FILE_SIZE_LIMIT = 8000000; // 8mb
    
    public static $Name            = 'Files';
    public static $Table_Name      = 'mod_fileattachments';
    public static $Find_Options    = array(
        'select_fields' => array(
            'fileid',
            'file_uid',
            'file_title',
            'file_name',
            'file_descr',
            'file_alt',
            'file_mime',
            'file_code',
            'file_group',
            'file_typestat',
        )
    );

    private $cache_dir = '';

    protected function initialize()
    {
        $cache_dir          = Path::TempDir('file_images').'/';
        $this->cache_dir    = $cache_dir;
        
        $this->addEvent('remove', function($data) use ($cache_dir) {
            $files = glob($cache_dir."*.{$data['fileid']}");
            if ($files !== false)
                foreach($files as $f=>$file) @unlink($file);
        });
    }

    protected function sanitize($sanitize)
    {
        $sanitize['file_data']->raw = null;
    }

    protected function validate($validate)
    {
        if (empty($this['fileid'])) 
            $validate['file_data']->require = 'file_data is required';
    }

    /**
     *
     * @access
     * @var
     */
    public function addFile($upfile, array $data)
    {

        if ( is_uploaded_file ($upfile['tmp_name']) ) {

            $data['file_mime']  = $upfile['type'];
            $data['file_name']  = $upfile['name'];

            if (empty($data['file_title']))
                $data['file_title'] = $upfile['name'];

            if ($upfile['size'] > self::FILE_SIZE_LIMIT)
                throw new Exception('File exceed the file size limit ['.$upfile['size'].' > '.self::FILE_SIZE_LIMIT.']');
            
            $fp = fopen($upfile['tmp_name'], "rb");
            $data['file_data'] = fread($fp, filesize($upfile['tmp_name']));
            fclose($fp);

            if (preg_match('/\.(gif|jpe?g|png|bmp)/', $upfile['name']))
                $data['file_typestat'] = $data['file_typestat'] ^ self::TYPE_IMAGE;
            else
                $data['file_typestat'] = $data['file_typestat'] ^ self::TYPE_DOCUMENT;
        } else {

            if (empty($data['fileid'])) return false;

        }//end if

        $this->add($data);
        return true;
    }

    public function headerNoCache()
    {
        // fix for IE catching or PHP bug issue
        header("Pragma: no-cache");
        header("cache-control: private"); //IE 6 Fix
        header("cache-Control: no-store, no-cache, must-revalidate");
        header("cache-Control: post-check=0, pre-check=0", false);
        header("Expires: Thu, 24 May 2001 05:00:00 GMT"); // Date in the past
    }// end function

    public function download(array $options = array())
    {
        $attachment = false;
        extract($options, EXTR_IF_EXISTS);

        if (empty($this['file_data'])) 
            throw new Exception('Empty file_data');

        if ($attachment === true){
            $this->headerNoCache();
            header("Content-Disposition: attachment; filename={$this['file_name']}");
        }//end if

        header('Content-type: '. $this['file_type']);
        if (is_file($this['file_data']))
            readfile($this['file_data']);
        else
            echo $this['file_data'];

        exit;
    }// end function

    public function fetch(array $options = array())
    {
        $resize = '240x180';
        extract($options, EXTR_IF_EXISTS);
            
        /**
         * if file type does not start with image
         * then download the file
         */
        $imagesize = array(
            'width'     => 240,
            'height'    => 180,
        );
        
        $allowed_sizes = $this->config->files('images_sizes');
        if (!@array_search($resize, $allowed_sizes))
            $resize = '240x180';
        
        if (!empty($resize))
            list($imagesize['width'], $imagesize['height']) = explode('x', $resize);

        $checkwh = (int)$imagesize['width'] + (int)$imagesize['height'];

        $this['file_date_updated'] = date("D, d M Y H:i:s", strtotime($this['file_date_updated']));

        if (is_writable($this->cache_dir) && ( $checkwh > 0 )  ) {
            $cache_file = $this->cache_dir."cache-{$prefix}".md5("{$this['fileid']}{$this['file_uid']}{$this['file_group']}{$checkwh}").".{$this['fileid']}";
            
            switch(true){
                case preg_match('|^image/p?jpe?g|i', $this["file_mime"]):
                case preg_match('|^image/gif|i', $this["file_mime"]):
                case preg_match('|^image/png|i', $this["file_mime"]):

                    /**
                     * recreate the cache file
                     * if it does not exits
                     */
                    $do_create_cache = !is_file($cache_file);

                    /**
                     * or if its not updated
                     */
                    $do_create_cache = (max(@filemtime($cache_file), strtotime("{$this['file_date_updated']}")) == strtotime("{$this['file_date_updated']}"));

                    if ($do_create_cache) {
                        require_once "common/image_resize.php";
                        
                        $gd = new \ImageResizer;
                        $gd->loadImage($this['file_data'], $this['file_mime']);
                        $gd->resize($imagesize['width'], $imagesize['height'])->save($cache_file);

                    }//end if

                    $this['file_data'] = $cache_file;
                    $this['file_date_updated'] = date("D, d M Y H:i:s", filemtime($cache_file));

                    break;
            }//end switch

        }//end if

        return true;
    }// end function

    public function getMimeType($file)
    {

        $mime['htm']    = 'text/html';
        $mime['html']   = 'text/html';
        $mime['txt']    = 'text/plain';
        $mime['asc']    = 'text/plain';
        $mime['bmp']    = 'image/bmp';
        $mime['gif']    = 'image/gif';
        $mime['jpeg']   = 'image/jpeg';
        $mime['jpg']    = 'image/jpeg';
        $mime['jpe']    = 'image/jpeg';
        $mime['png']    = 'image/png';
        $mime['ico']    = 'image/vnd.microsoft.icon';
        $mime['mpeg']   = 'video/mpeg';
        $mime['mpg']    = 'video/mpeg';
        $mime['mpe']    = 'video/mpeg';
        $mime['qt']     = 'video/quicktime';
        $mime['mov']    = 'video/quicktime';
        $mime['avi']    = 'video/x-msvideo';
        $mime['wmv']    = 'video/x-ms-wmv';
        $mime['mp2']    = 'audio/mpeg';
        $mime['mp3']    = 'audio/mpeg';
        $mime['rm']     = 'audio/x-pn-realaudio';
        $mime['ram']    = 'audio/x-pn-realaudio';
        $mime['rpm']    = 'audio/x-pn-realaudio-plugin';
        $mime['ra']     = 'audio/x-realaudio';
        $mime['wav']    = 'audio/x-wav';
        $mime['css']    = 'text/css';
        $mime['zip']    = 'application/zip';
        $mime['pdf']    = 'application/pdf';
        $mime['doc']    = 'application/msword';
        $mime['bin']    = 'application/octet-stream';
        $mime['exe']    = 'application/octet-stream';
        $mime['class']  = 'application/octet-stream';
        $mime['dll']    = 'application/octet-stream';
        $mime['xls']    = 'application/vnd.ms-excel';
        $mime['ppt']    = 'application/vnd.ms-powerpoint';
        $mime['wbxml']  = 'application/vnd.wap.wbxml';
        $mime['wmlc']   = 'application/vnd.wap.wmlc';
        $mime['wmlsc']  = 'application/vnd.wap.wmlscriptc';
        $mime['dvi']    = 'application/x-dvi';
        $mime['spl']    = 'application/x-futuresplash';
        $mime['gtar']   = 'application/x-gtar';
        $mime['gzip']   = 'application/x-gzip';
        $mime['js']     = 'application/x-javascript';
        $mime['swf']    = 'application/x-shockwave-flash';
        $mime['tar']    = 'application/x-tar';
        $mime['xhtml']  = 'application/xhtml+xml';
        $mime['au']     = 'audio/basic';
        $mime['snd']    = 'audio/basic';
        $mime['midi']   = 'audio/midi';
        $mime['mid']    = 'audio/midi';
        $mime['m3u']    = 'audio/x-mpegurl';
        $mime['tiff']   = 'image/tiff';
        $mime['tif']    = 'image/tiff';
        $mime['rtf']    = 'text/rtf';
        $mime['wml']    = 'text/vnd.wap.wml';
        $mime['wmls']   = 'text/vnd.wap.wmlscript';
        $mime['xsl']    = 'text/xml';
        $mime['xml']    = 'text/xml';

        $dump = explode('.',$file);
        $extension = array_pop($dump);

        $type = 'text/html';
        if (array_key_exists($extension, $mime)) $type = $mime[$extension];

        return $type;
    }// end function

    /**
     *
     * @access
     * @var
     */
    public function touch()
    {
        $table_name     = self::$Table_Name;
        $lastmodified   = strtotime($this['file_date_updated']);
        $touch30days    = strtotime('-30 days');

        /**
         * if file is 30 days old
         * touch the file to indicate that it was recently requested
         */
        if (max($touch30days, $lastmodified) == $touch30days) 
            $this->sqlExecute("update {$table_name} set file_date_updated = NOW() where fileid = {$this['fileid']}");
    }

    /**
     *
     * @access
     * @var
     */
    public function expire($arg, array $options = array())
    {
        $options['conditions'] = array(
            '<file_date_updated'    => date("Y-m-d 00:00:00", strtotime('-90 days')),
            '%file_group'           => 'wysiwyg/%',
            'limit'                 => 10
        );
        
        try {
            $this->find($arg, $options);
        } catch (Exception $exc) {
            return false;
        }//end try

        foreach ($this as $k => $v) 
            $this->remove();
        
    }

}
