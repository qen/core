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

use Core\App;
use Core\App\Path;

class ViewTwigLoader implements \Twig_LoaderInterface
{
    protected $paths;
    protected $cache;

    private $app_path;

    /**
     * Constructor.
     *
     * @param string|array $paths A path or an array of paths where to look for templates
     */
    public function __construct()
    {
        $this->setPaths(Path::ViewDir());
        $this->app_path = App\PATH;
    }

    /**
     * Returns the paths to the templates.
     *
     * @return array The array of paths where to look for templates
     */
    public function getPaths()
    {
        return $this->paths;
    }

    /**
     * Sets the paths where templates are stored.
     *
     * @param string|array $paths A path or an array of paths where to look for templates
     */
    public function setPaths($paths)
    {
        // invalidate the cache
        $this->cache = array();

        if (!is_array($paths)) {
            $paths = array($paths);
        }

        $this->paths = array();
        foreach ($paths as $path) {
            if (!is_dir($path)) {
                throw new \Twig_Error_Loader(sprintf('The "%s" directory does not exist.', $path));
            }

            $this->paths[] = realpath($path);
        }
    }

    /**
     * Gets the source code of a template, given its name.
     *
     * @param  string $name string The name of the template to load
     *
     * @return string The template source code
     */
    public function getSource($name)
    {
        return file_get_contents($this->findTemplate($name));
    }

    /**
     * Gets the cache key to use for the cache for a given template name.
     *
     * @param  string $name string The name of the template to load
     *
     * @return string The cache key
     */
    public function getCacheKey($name)
    {
        return $this->findTemplate($name);
    }

    /**
     * Returns true if the template is still fresh.
     *
     * @param string    $name The template name
     * @param timestamp $time The last modification time of the cached template
     */
    public function isFresh($name, $time)
    {
        return filemtime($this->findTemplate($name)) < $time;
    }

    protected function findTemplate($name)
    {
        if ($name{0} == DIRECTORY_SEPARATOR)
            $name = substr($name, 1);

        if (isset($this->cache[$name])) 
            return $this->cache[$name];
        
        foreach ($this->paths as $path) {
            
            $file = $path.DIRECTORY_SEPARATOR.$name;
            
            if (!file_exists($file) || is_dir($file)) 
                continue;
            
            $file = realpath($file);

            // simple security check
            if (0 !== strpos($file, $path)) {
                throw new \Twig_Error_Loader('Looks like you try to load a template outside configured directories.');
            }

            return $this->cache[$name] = $file;
        }

        /**
         * make modules/[name]/public files available
         */
        if (preg_match('|^modules/([^/]+)/(.*)|', $name, $matches)) {
            $path = $this->app_path;
            
            $file = $path.DIRECTORY_SEPARATOR."modules/{$matches[1]}/public/{$matches[2]}";

            if (file_exists($file)) {
                $file = realpath($file);
                
                // simple security check
                if (0 !== strpos($file, $path)) {
                    throw new \Twig_Error_Loader('Looks like you try to load a template outside configured directories.');
                }

                return $this->cache[$name] = $file;
            }//end if
            
        }//end if

        throw new \Twig_Error_Loader(sprintf('Unable to find template "%s" (looked into: %s).', $name, implode(', ', $this->paths)));
    }
}
