<?php
/**
 * Project: PHP CORE Framework
 *
 * This file is part of PHP CORE Framework.
 *
 * PHP CORE Framework is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
 *
 * PHP CORE Framework is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with PHP CORE Framework.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @version v0.05.18b
 * @copyright 2010-2011
 * @author Qen Empaces,
 * @email qen.empaces@gmail.com
 * @date 2011.05.30
 *
 */
class ImageResizer {
    
    private $source_im;

    private $img_w, $img_h, $img_t;
    private $new_w, $new_h;


    function __construct()
    {

    }// end function

    public function loadImage($imagefile, $type='')
    {
        $this->source_im = null;

        if (is_file($imagefile)){

            list($this->img_w, $this->img_h, $this->img_t) = getimagesize($imagefile);

            switch ($this->img_t) {
                case IMAGETYPE_JPEG:
                    $this->source_im = imagecreatefromjpeg($imagefile);
                    $this->img_t = 'image/jpeg';
                    break;

                case IMAGETYPE_GIF:
                    $this->source_im = imagecreatefromgif($imagefile);
                    $this->img_t = 'image/jpeg';
                    break;

                case IMAGETYPE_PNG:
                    $this->source_im = imagecreatefrompng($imagefile);
                    $this->img_t = 'image/png';
                    break;

                default:
                    // this will trigger error save method
                    $this->img_t = '';
                    break;
            }// end switch

            $this->new_w = $this->img_w;
            $this->new_h = $this->img_h;
            return true;
        }#endif

        ini_set( 'memory_limit', '64M' );
        $this->source_im = imagecreatefromstring($imagefile);

        if ($this->source_im === false) return false;

        list($this->img_w, $this->img_h, $this->img_t) = array( imagesx($this->source_im), imagesy($this->source_im), $type );
        
        $this->new_w = $this->img_w;
        $this->new_h = $this->img_h;

        return true;
    }// end function

    public function save($filename)
    {
        if (empty($this->source_im))
            return false;


        // Load
        $thumb = imagecreatetruecolor($this->new_w, $this->new_h);

        switch(true){

            case preg_match('|^image/p?jpe?g|i',  $this->img_t):
                $func = "imagejpeg";
                break;

            case preg_match('|^image/gif|i',  $this->img_t):
                $func = "imagegif";
                break;

            case preg_match('|^image/png|i',  $this->img_t):
                $func = "imagepng";
                break;

            default:
                trigger_error("[ImageResizer] image type (".$this->img_t.") not supported... ", E_USER_ERROR );
                break;
        }#end switch

        // Resize
        imagecopyresampled($thumb, $this->source_im, 0, 0, 0, 0, $this->new_w, $this->new_h, $this->img_w, $this->img_h);

        // if fucking safe mode is on, touch the file first so that when imagejpeg access it, it has the same uid
        touch($filename);

        // resize
        $func($thumb, $filename);

        return true;
    }// end function

    public function resize($width = 0, $height = 0)
    {
        $width  = (int) $width;
        $height = (int) $height;

        if (($width+$height) <= 0)
            trigger_error("[ImageResizer] both width and height cannot be 0 ", E_USER_ERROR );

        if ($width >= $height)
            $this->resizeWidth($width);
        else
            $this->resizeHeight($height);

        return $this;
    }// end function

    private function resizeWidth($max)
    {
        $this->new_w = $this->img_w;
        $this->new_h = $this->img_h;

        if (!($this->img_w > $max)) return true;

        $sizefactor = (double) ($max / $this->img_w);

        $this->new_w = (int) ($this->img_w * $sizefactor);
        $this->new_h = (int) ($this->img_h * $sizefactor);

        return true;
    }// end function
    
    private  function resizeHeight($max)
    {
        $this->new_w = $this->img_w;
        $this->new_h = $this->img_h;

        if (!($this->img_h > $max)) return true;

        $sizefactor = (double) ($max / $this->img_h);

        $this->new_w = (int) ($this->img_w * $sizefactor);
        $this->new_h = (int) ($this->img_h * $sizefactor);

        return true;
    }// end function

}//end class

