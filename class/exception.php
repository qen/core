<?php
/**
 * Project:     CORE FRAMEWORK
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 *
 * @author Qen Empaces
 * @email qen.empaces@gmail.com
 * @version rc7
 * @date 2010.10.19
 *
 */
namespace Core;


/**
 *
 * @author
 *
 */
class Exception extends \Exception
{

    private $details = array();

    /**
     * Constructor
     * @access protected
     */
    function __construct($messages, $code = 0) {
        // some code
        if (is_array($messages))
            $messages = implode(";", $messages);

        // make sure everything is assigned properly
        parent::__construct($messages, $code);

    }// end function

    // custom string representation of object
    public function __toString() {
        return __CLASS__ . ": [{$this->code}]: {$this->message}\n";
    }

    /**
     *   function
     * @param
     * @return
     */
    public function getMessages()
    {
        $retval = explode(';', $this->message);
        return $retval;
    }// end function

    /**
     *
     * @access
     * @var
     */
    public function logError()
    {
        $err = "[".$e->getFile()."]\n@line#".$this->getLine().' > '. $this->getMessages()."\n\n";
        Debug::Log($err, 'errors');
    }// end function

    /**
     *
     * @access
     * @var
     */
    public function setErrorDetails(array $param)
    {
        $this->details = $param;
    }// end function

    /**
     *
     * @access
     * @var
     */
    public function getErrorDetails()
    {
        return $this->details;
    }// end function

}