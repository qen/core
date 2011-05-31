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