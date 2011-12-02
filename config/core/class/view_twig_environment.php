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

class ViewTwigEnvironment extends \Twig_Environment
{
    protected $cache = true;
    
    public function __construct(\Twig_LoaderInterface $loader = null, $options = array())
    {
        $options['autoescape']          = true;
        $options['strict_variables']    = false;

        parent::__construct($loader, $options);

        $this->addExtension(new \Twig_Extension_Text());
        $this->addExtension(new ViewTwigExtension());

        /**
         * let's use ruby style
         */
        $lexer = new \Twig_Lexer($this, array(
          'tag_comment'  => array('<%#', '%>'),
          'tag_block'    => array('<%', '%>'),
          'tag_variable' => array('<%=', '%>'),
        ));

        $this->setLexer($lexer);

    }
    
    public function compileSource($source, $name = null)
    {
        $source = self::TrimWhiteSpace($source);
        return $this->compile($this->parse($this->tokenize($source, $name)));
    }

    public function getCacheFilename($name)
    {
        if (false === $this->cache) {
            return false;
        }

        $prefix = str_replace('/', '_', $name);

        $class = substr($this->getTemplateClass($name), strlen($this->templateClassPrefix));

        return $this->getCache()."/{$prefix}_{$class}.php";

    }

    protected function writeCacheFile($file, $content)
    {
        if (!is_dir(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }

        $tmpFile = tempnam(dirname($file), basename($file));
        if (false !== @file_put_contents($tmpFile, $content)) {
            // rename does not work on Win32 before 5.2.6
            if (@rename($tmpFile, $file) || (@copy($tmpFile, $file) && unlink($tmpFile))) {
                chmod($file, 0644);

                return;
            }
        }

        throw new \Twig_Error_Runtime(sprintf('Failed to write cache file "%s".', $file));
    }

    public static function TrimWhiteSpace($source)
    {
        if (!defined('TWIG_CLEAR_WHITESPACE')) return $source;

        $pull   = array('pre', 'textarea', 'script');
        $blocks = array();

        foreach ($pull as $k => $v) {
            preg_match_all("!<{$v}[^>]*?>.*?</{$v}>!is", $source, $match);
            $blocks[$v] = $match[0];
            $source     = preg_replace("!<{$v}[^>]*?>.*?</{$v}>!is", "@@@TWIG:TRIM:{$v}@@@", $source);
        }// end foreach

        # remove all leading spaces, tabs and carriage returns NOT
        # preceeded by a php close tag.
        $source = trim(preg_replace('/((?<!\?>)\n)[\s]+/m', '\1', $source));

        $trimwhitespaceReplace = function ($search_str, $replace, &$subject)
        {
            $_len = strlen($search_str);
            $_pos = 0;
            for ($_i=0, $_count=count($replace); $_i<$_count; $_i++)
                if (($_pos=strpos($subject, $search_str, $_pos))!==false)
                    $subject = substr_replace($subject, $replace[$_i], $_pos, $_len);
                else
                    break;
        };

        foreach ($pull as $k => $v) {
            if (empty($blocks[$v])) continue;
            $trimwhitespaceReplace("@@@TWIG:TRIM:{$v}@@@", $blocks[$v], $source);
        }// end foreach

        return $source;
    }// end function
}
