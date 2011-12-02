<?php
require_once 'simpletest/autorun.php';

use \Core\App\Module;
use \Core\Tools;
use \Core\Debug;
use \Core\Exception;
use \Core\Db;
use \Core\ModelActions;

class TestValidation extends UnitTestCase {
    private $vars = array();
    private $validate = null;

    public function setUp()
    {
        $this->validate = new \Core\Validate;
        
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
    public function testMatchRequire()
    {
        $validate = $this->validate;
        # test minum char 8
        $validate['x']->require = 'x is required';
        //$this->validate['id']->email    = 'id must be email';
        $passed = true;
        try {
            $validate($this->vars);
        } catch (Exception $exc) {
            $passed = false;
        }//end try
        # should fail
        $this->assertFalse($passed);

        $this->vars['x'] = 1;
        $this->vars['y'] = 1;
        $validate['x']->require = 'x is required';
        $validate['x']->match['y'] = 'x must match y';
        //$this->validate['id']->email    = 'id must be email';
        $passed = true;
        try {
            $validate($this->vars);
        } catch (Exception $exc) {
            $passed = false;
        }//end try
        # should pass
        $this->assertTrue($passed);
    }

    /**
     *
     * @access
     * @var
     */
    public function testCustomRuleCustomRegex()
    {
        $validate = $this->validate;
        $validate->addRule('will_always_fail', function($var, $options){
            if (empty($options)) return false;
            return true;
        });

        $this->vars['x'] = '123';
        $validate['x']->will_always_fail = 'fail wtf';
        $passed = true;
        try {
            $validate($this->vars);
        } catch (Exception $exc) {
            $passed = false;
        }//end try
        # should fail, custom function always fail
        $this->assertFalse($passed);

        # pass any value on the index so that it will not fail
        $this->vars['x'] = '123';
        $validate['x']->will_always_fail['do not fail bitch'] = 'fail wtf';
        $passed = true;
        try {
            $validate($this->vars);
        } catch (Exception $exc) {
            $passed = false;
        }//end try
        # should pass, sicne we pass an index in will_always_fail
        $this->assertTrue($passed);
        

        $validate['x']->regex['/^ [0-9]*$/'] = 'regex wtf';
        $passed = true;
        try {
            $validate($this->vars);
        } catch (Exception $exc) {
            $passed = false;
        }//end try
        # should fail, custom regex should start with space
        $this->assertFalse($passed);
    }

    /**
     *
     * @access
     * @var
     */
    public function testNumeric()
    {
        $this->validate['id']->numeric = 'id must be numeric';
        //$this->validate['id']->email    = 'id must be email';
        $passed = true;
        try {
            $this->validate->data($this->vars);
        } catch (Exception $exc) {
            $passed = false;
        }//end try

        $this->assertTrue($passed);

        $this->vars['id'] = 10;
        # set minum value 11
        $this->validate['id']->numeric['11'] = 'id must have a minum value of 11';

        $passed = true;
        try {
            $this->validate->data($this->vars);
        } catch (Exception $exc) {
            $passed = false;
        }//end try
        #should fail
        $this->assertFalse($passed);

        $this->vars['id'] = 21;
        # set minum value 11 and max 20
        $this->validate['id']->numeric['11,20'] = 'id must be between 11 and 20';

        $passed = true;
        try {
            $this->validate->data($this->vars);
        } catch (Exception $exc) {
            $passed = false;
        }//end try
        #should fail
        $this->assertFalse($passed);
        
    }

    /**
     *
     * @access
     * @var
     */
    public function testChar()
    {
        $this->vars['x'] = 'asdf';
        # test minum char 8
        $this->validate['x']->char[8] = 'character must be at least 8 characters long';
        //$this->validate['id']->email    = 'id must be email';
        $passed = true;
        try {
            $this->validate->data($this->vars);
        } catch (Exception $exc) {
            $passed = false;
        }//end try
        # should fail
        $this->assertFalse($passed);

        $this->vars['x'] = 'asdfzxcvy';
        # test minum char 8 and max 9
        $this->validate['x']->char['8,9'] = 'character must be at least 8 characters long and less than 9 char long';
        //$this->validate['id']->email    = 'id must be email';
        $passed = true;
        try {
            $this->validate->data($this->vars);
        } catch (Exception $exc) {
            $passed = false;
        }//end try
        # should pass
        $this->assertTrue($passed);
        
    }

}

