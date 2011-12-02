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
     * for usability sake,
     * we declare a config class on \Core\App and \Core\App\Modules
     * namespaces both of which extends to this \Core\Config
     *
     * \Core\App\Modules\Config is special for it holds
     * all the configuration loaded by all modules ( a.k.a. collections of Config object )
     *
     * so everytime the $Module->Config() is invoked
     * it actually returns the \Core\App\Modules\Config::$Modules[module] config object
     *
     * two ways to access the config value
     * that is through the static call or through the method call
     *
     */

    class Config {

        protected $pconfig = array();

        /**
         *
         * @access
         * @var
         */
        public function import($file)
        {
            /**
             * encapsulate with output buffer
             */
            $config = self::IncludeFile($file);
            if (!is_string($config)) return false;
            $this->mutate($config);
        }

        /**
         *
         * @access
         * @var
         */
        public function mutate($config)
        {
            $param = $config;
            if (is_string($config))
                $config = json_decode($config, true);

            if (!is_array($config))
                throw new \Exception(__FILE__.'> failed to load config '.$param);

            $this->pconfig = Tools::ArrayMerge($this->pconfig, $config);
        }

        /**
         *
         * @access
         * @var
         */
        public function __call($var, $arg)
        {
            if (empty($this->pconfig))
                return null;
                //throw new Exception($var.'> failed to load config, make sure the $config is declared as protected');

            $var    = strtolower($var);
            $tree   = array();
            if (!empty($arg[0]))
                $tree = explode('/', strtolower($arg[0]));

            $retval = $this->pconfig[$var];

            if (!empty($tree))
                $retval = self::Recurse($retval, $tree);

            return $retval;
        }

        /**
         *
         *
         */
        protected static $Config = array();
        public static function Load($config)
        {

            if (is_string($config)){

                if (is_file($config))
                    $config = self::IncludeFile($config);

                $config = json_decode($config, true);

            }//end if
            
            if (!is_array($config))
                throw new Exception(__FILE__.'> failed to load config');

            if (!is_array(static::$Config))
                static::$Config = array();

            static::$Config = Tools::ArrayMerge(static::$Config, $config);
        }

        /**
         *
         */
        public static function Value()
        {
           return static::$Config;
        }

        public static function __callStatic($var, $arg)
        {
            if (empty(static::$Config))
                return null;
                //throw new Exception($var.'> failed to load config');

            $var    = strtolower($var);
            $tree   = array();
            if (!empty($arg[0]))
                $tree = explode('/', strtolower($arg[0]));

            $retval = static::$Config[$var];

            if (!empty($tree))
                $retval = self::Recurse($retval, $tree);

            return $retval;
        }

        /**
         *
         * @access
         * @var
         */
        public static function Recurse($array, $tree)
        {
            $var = array_shift($tree);
            
            $retval = $array[$var];
            
            if (is_array($retval))
                $retval = self::Recurse($retval, $tree);

            if (is_null($retval))
                return $array;

            return $retval;
        }

        public static function IncludeFile($param)
        {

            ob_start();
            $retval = @include $param;
            $buffer = ob_get_contents();
            ob_end_clean();

            if (is_string($retval))
                return $retval;

            if (!empty($buffer))
                return $buffer;

            return null;
        }

    }//end class

