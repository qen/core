<?php
namespace Core\App\Modules\Account\Controllers;

use Core\Debug;
use Core\Exception;
use Core\App\Path;
use Core\App\Module;

class Base extends \Core\Controller
{

    public function captcha()
    {

        render()->nocache = true;

    	// Set the content-type
        render()->content_type = 'image/png';

        $text = $this->generateText();
        
        render()->inline = function() use ($text) {
            // Create the image
            $im     = imagecreatetruecolor(110, 24);

            // Create some colors
            $white  = imagecolorallocate($im, 255, 255, 255);
            $grey   = imagecolorallocate($im, 128, 128, 128);
            $black  = imagecolorallocate($im, 33, 33, 33);
            imagefilledrectangle($im, 0, 0, 399, 29, $white);
            imagecolortransparent($im, $white);

            // Add some shadow to the text
            //imagettftext($im, 14, 0, 11, 18, $grey, $font, $text);
            //imagestring($im, 5, 1, 4, $text, $grey);

            // Add the text
            //imagettftext($im, 14, 0, 10, 18, $black, $font, $text);
            imagestring($im, 5, 0, 3, $text, $black);

            // Using imagepng() results in clearer text compared with imagejpeg()
            imagepng($im);
            imagedestroy($im);
        };

    }

    /**
     *
     * @access
     * @var
     */
    private function generateText()
    {
        $num1   = round(rand(200,700), -1);
        $num2   = rand(100,199);

        params()->flash['signup_captcha'] = ($num1 + $num2);

        $retval = " " . number_format($num1,0) . " + " . $num2 . " = ";

        return $retval;
    }

    /**
     *
     * @access
     * @var
     */
    protected function verifyCaptcha($value)
    {
        $check = params()->flash['signup_captcha'];
        
        if ($check != $value) 
            throw new \Core\Exception('Please enter the correct security number');

        return true;
    }



}