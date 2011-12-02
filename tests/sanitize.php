<?php
require_once 'simpletest/autorun.php';

use \Core\App\Module;
use \Core\Tools;
use \Core\Debug;
use \Core\Exception;
use \Core\Db;
use \Core\ModelActions;

class TestSanitization extends UnitTestCase {
    private $vars = array();
    private $sanitize = null;

    public function setUp()
    {
        $this->sanitize = new \Core\Sanitize;
        
        $this->vars = array();
        $this->vars['id'] = 1;
        $this->vars['char'] = "lorem ipsum";
        $this->vars['numeric'] = "12,000.00";
        $this->vars['email'] = "myemail@domain.com";
        $this->vars['html'] = "<p>lorem ipsum</p>";
    }

    /**
     *
     * @access
     * @var
     */
    public function testChar()
    {
        $sanitize = $this->sanitize;
        # test minum char 8
        $sanitize['x']->char[10] = 'X';
        $sanitize->persist();

        $var1 = array( 'x' => '' );
        $var2 = array( 'x' => 'asdf' );
        $var3 = array( 'x' => 'asdfzxcvqwer' );

        $check = $sanitize($var1);
        $this->assertEqual($check['x'], 'X');

        $check = $sanitize($var2);
        $this->assertEqual($check['x'], 'asdf');

        $check = $sanitize($var3);
        $this->assertEqual($check['x'], 'asdfzxcvqw');

        /**
         * clear all rules
         */
        $sanitize->persist(false, true);
        

        $sanitize['x']->char['10'] = function($sanitize, $options) {
            $value = 'asdf asdf asdf';
            $sanitize['x']->char['hypenate,lowercase'] = $value;
            $clean = $sanitize(array('x' => $value));
            $prefix = date('Y/m/d');
            return $prefix.$clean['x'];
        };
        
        $check = $sanitize(array('x'=>''));
        $this->assertEqual($check['x'], '2011/09/26asdf-asdf-asdf');

    }

    /**
     *
     * @access
     * @var
     */
    public function testCustomRule()
    {
        $sanitize = $this->sanitize;
        $sanitize->addRule('always_one', function($value, $options, $sanitize, $default){
            if (!empty($options)) return 'two';
            if ($default == 'four') return 'four';
            return 'one';
        });

        $sanitize['x']->always_one = 'three';
        $sanitize['y']->always_one = 'four';
        $sanitize['z']->always_one['wtf'] = 'three';

        $var = array(
            'x' => 'value will be one',
            'y' => 'value will be four',
            'z' => 'value will be two',
            'a' => ''
        );

        $check = $sanitize($var);
        $this->assertEqual($check['x'], 'one');
        $this->assertEqual($check['y'], 'four');
        $this->assertEqual($check['z'], 'two');
        $this->assertEqual($check['a'], '');

        $sanitize->addRule('blog_url', function($value, $options, $sanitize, $default) {
            
            $text   = $value;
            $prefix = '';
            if (preg_match('|^\d+/\d+/\d+/|', $value)) {
                list($y, $m, $d, $text) = explode('/', $value);
                $prefix = "{$y}/{$m}/{$d}";
            }//end if

            if (!empty($text)) {
                $sanitize['x']->char['245,hypenate,lowercase'] = $text;
                $clean = $sanitize(array('x' => $text));
                return $prefix.'/'.$clean['x'];
            }//end if

            $options    = empty($options) ? 'now' : $options;
            $text       = $default;

            list($y, $m, $d) = explode('/', date('Y/m/d', strtotime($options)));
            $prefix = "{$y}/{$m}/{$d}";

            $sanitize['x']->char['245,hypenate,lowercase'] = $text;
            $clean = $sanitize(array('x' => $text));

            return $prefix.'/'.$clean['x'];

        });

        $prefix = date('Y/m/d');
        $default = 'lorem ipsum wtf';
        $sanitize['a']->blog_url["{$prefix}"] = $default;
        $check = $sanitize($var);
        $this->assertEqual($check['a'], $prefix.'/lorem-ipsum-wtf');

        $var['a'] = $prefix.'/QWEQWE XCVXCV';
        $sanitize['a']->blog_url["{$prefix}"] = $default;
        $check = $sanitize($var);
        $this->assertEqual($check['a'], $prefix.'/qweqwe-xcvxcv');
    }

    /**
     *
     * @access
     * @var
     */
    public function testDate()
    {
        $sanitize = $this->sanitize;

        $sanitize['x']->date = 'invalid-default-date';
        $sanitize['y']->date['Y-m-d'] = date('Y-m-d');
        $sanitize['z']->date['H:i:s'] = date('Y-m-d');
        $sanitize['a']->date['Y-m-d H:i:s'] = date('Y-m-d H:i:s');

        $var = array(
            'x' => '',
            'y' => '-1 day',
            'z' => '+1 hour',
            'a' => '-1 month',
        );

        $check = $sanitize->data($var);
        $this->assertEqual($check['x'], 'invalid-default-date');
        $this->assertEqual($check['y'], date('Y-m-d',strtotime('-1 day')));
        $this->assertEqual($check['z'], date('H:i:s',strtotime('+1 hour')));
        $this->assertEqual($check['a'], date('Y-m-d H:i:s',strtotime('-1 month')));
        
    }

}

