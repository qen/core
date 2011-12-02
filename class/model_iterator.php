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

use \ArrayIterator;
use \IteratorAggregate;
use \ArrayAccess;
use \Iterator;

/**
 * 
 */
class ModelIterator implements Iterator
{
    private $model = null;
    private $position = 0;
    private $is_associated = false;

    public function __construct($obj)
    {
        $this->model = $obj;
    }

    public function current()
    {
        if ($this->is_associated)
            return $this->model->associated();

        return $this->model->object();
    }

    public function key()
    {
        return $this->position;
    }

    public function next()
    {
        ++$this->position;
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function valid()
    {
        $position = (int) $this->position;
        return $this->model->offsetExists($position);
    }

    public function seek($position){
        $this->position = $position;
    }

    public function associated(){
        $this->is_associated = true;
    }

    public function object(){
        $this->is_associated = false;
    }


}