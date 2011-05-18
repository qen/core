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
 * @date 2010.10.19
 *
 */
namespace Core;
use Core\App\AppPath;

class Debug {
    
    /**
     *
     * @access
     * @var
     */
    public static function Trace($msg, $die = false)
    {
        $output = "";
        $trace  = array_reverse( debug_backtrace() );
        $indent = '';
        $func   = '';

        $output .= "[{$msg}]\n";

        foreach( $trace as $val){
            //$output .= $indent.str_replace(PATH, '', $val['file']).' on line '.$val['line'];
            $output .= $indent.$val['file'].' on line '.$val['line'];

            if( $func ) $output .= ' in function '.$func;

            switch ($val['function']) {
                case 'include':
                case 'require':
                case 'include_once':
                case 'require_once':
                    $func = '';
                    break;

                default:
                    $func = $val['function'].'(';

                    if( isset( $val['args'][0] ) ){
                        $func .= ' ';
                        $comma = '';
                        foreach( $val['args'] as $val ) $comma = ', ';
                        $func .= ' ';
                    }//end if

                    $func .= ')';
                    break;
            }// end switch

            $output .= "\n";

            $indent .= "";
        }//end foreach

        $eol = "\r\n";

        if ($die) {
            echo $eol."<!--".$eol.$output.$eol."-->".$eol;
            exit;
        }//end if

        return $output;
    }

    /**
     *
     * @access
     * @var
     */
    public static function Log($text, $filename = 'debug')
    {
        $file = AppPath::TempDir('logs').'/'.$filename.'-'.date("Ymd").'.log';
        touch($file);
        $retval = file_put_contents($file, $text."\n", FILE_APPEND | LOCK_EX );
    }

    /**
     *
     * @access
     * @var
     */
    public static function Dump()
    {
        $debug = func_get_args();
        if (count($debug) == 0) return false;

        $eol = "\r\n";
        echo $eol."<pre>".$eol;

        foreach($debug as $k=>$var){
            $output = var_export( $var, true );
            echo $eol.$output.$eol;
        }//end foreach

        echo $eol."</pre>".$eol;
        
    }

}