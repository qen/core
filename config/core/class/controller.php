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

use \ArrayAccess;
use Core\Tools;
use Core\App;
use Core\App\Path;
use Core\App\Route;
use Core\App\Config as AppConfig;
use Core\View;

/**
 *
 * @author
 * we moved the static helper functions ( to Controllers namespace ) as it limits
 * the number of available url name for the controller to respond to
 *
 */
class Controller
{
    public static $Debug    = false;
    public static $Method   = null;

    /**
     *
     * @access
     * @var
     */
    public function __construct()
    {
    }

    /**
     *
     * @access
     * @var
     */
    public function  __destruct()
    {
    }

    public function index()
    {

    }

}
