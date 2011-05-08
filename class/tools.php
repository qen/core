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

class Tools {

    /**
     *     hash function
     *
     */
    public static function Hash($var, $options = array()){
        $chunk  = true;
        $mode   = is_string($var) ? 'decode' : 'encode';
        extract($options, EXTR_IF_EXISTS);

        $hash = array();

        switch($mode){
            case "decode":
                $hash = @unserialize(gzuncompress(base64_decode($var)));
                break;

            case "encode":
            default:
                $hash = base64_encode(gzcompress(serialize($var),9));

                if ($chunk === true) $hash = chunk_split($hash);

                break;
        } // switch

        return $hash;
    }// end function hash

    /**
     *  function
     * @param
     * @return
     */
    public static function Hyphenate($string)
    {
        $patterns[] = '@[,\.:\*<>|"\?]@';
        $patterns[] = '@\\\@';
        $patterns[] = '@[&]@';
        $patterns[] = '@[\/]@';
        $replacements[] = '';
        $replacements[] = '';
        $replacements[] = 'and';
        $replacements[] = 'or';

        $retval = preg_replace($patterns, $replacements, strtolower(trim($string)));
        $retval = preg_replace('| |', '-', trim($retval));

        return $retval;
    }// end function 


    /**
     * check_data function
     *
     */
    public static function Validate(array $check_data, $field){
    /***************

        $check_data['required'] = array(
            'firstname'         => 'Your First Name is Required',
            'lastname'          => 'Your Last Name is Required',
        );

        $check_data['required_if_exists'] = array(
            'firstname'          => 'Your First Name is Required',
            'lastname'          => 'Your Last Name is Required',
        );

        // requires at least one field is supplied
        $check_data['required_optional'] array{
            'address'          => 'Address is Required',
            'city'          => 'City is Required',
            'zipcode'          => 'Zipcode is Required',
        };

        $check_data['email'] = array(
            'email'          => 'Please enter a valid email address.',
        );

        $check_data['password'] = array(
            'password1'          => 'Password has to be 6 characters long and must consist of numbers and letters.',
        );

        $check_data['url'] = array(
            'website'          => 'Please enter a valid website address. (ie: http://www.acnesource.com)',
        );

        $check_data['numeric'] = array(
            'amount'          => 'enter numeric data',
        );

        $check_data['nonzero'] = array(
            'amount'          => 'enter value greater than zero.',
        );

        $check_data['equality'] = array(
            'password'         => array('confirm', 'Please enter a valid website address.'),
        );

        $check_data['date'] = array(
            'date'          => 'Please enter a valid website address. (ie: http://www.acnesource.com)',
        );

        try{
            static::validate($check_data, $data);
        }catch(errors $e){
            $this->error($e->getMessages());
            return false;
        }//end try

    ***************/
    ############################################################################
        
        $err = array();

        if (array_key_exists('required', $check_data) !== false) {
            foreach($check_data['required'] AS $k=>$v) {
                if (!empty($field[$k])) continue;

                // check if there is any options passed
                list($param, $msg) = explode("|", $v);

                // if $msg then default to strict rules
                if (empty($msg)) {
                    $err[] = $v;
                    continue;
                }//end if

                // require the field only if the key exists
                if ((strpos('exists', $param) !== false) && (array_key_exists($k, $field) === false))
                    continue;

                $err[] = $msg;
            }//endfor
        }//endif

        if (array_key_exists('length', $check_data) !== false) {
            foreach($check_data['length'] AS $k=>$v) {
                // check if there is any options passed
                list($strlen, $msg) = explode("|", $v);

                $strlen = (int) $strlen;
                if (!(strlen($field[$k]) >= $strlen)) $err[] = $msg;

            }//endfor
        }//endif

        if (array_key_exists('email', $check_data) !== false) {
            foreach($check_data['email'] AS $k=>$v) {
                if (empty($field[$k])) continue;

                $rtn = preg_match('/^[a-z0-9_\-\+]+(\.[_a-z0-9\-\+]+)*@([_a-z0-9\-]+\.)+([a-z]{2}|aero|arpa|biz|com|coop|edu|gov|info|int|jobs|mil|museum|name|nato|net|org|pro|travel)$/i',$field[$k]);
                if ($rtn == 0) $err[] = $v;
            }//endfor
        }//endif

        if (array_key_exists('char', $check_data) !== false) {
            foreach($check_data['char'] AS $k=>$v) {
                if (empty($field[$k])) continue;

                // check if there is any options passed
                list($regex, $msg) = explode("|", $v);

                // if $msg then default to standard checking
                if (empty($msg)) {
                  $msg    = $regex;
                  $regex  = '^[0-9a-zA-Z_\.@\-/\+]+$';
                }//end if
                
                $rtn = preg_match("|{$regex}|i",$field[$k]);
                if ($rtn == 0) $err[] = $msg;

            }//endfor
        }#end if

        if (array_key_exists('url', $check_data) !== false) {
            foreach($check_data['url'] AS $k=>$v) {
              if (empty($field[$k])) continue;

              //$rtn = preg_match("/^((https?|ftp|news):\/\/)?([a-z]([a-z0-9\-]*\.)+([a-z]{2}|aero|arpa|biz|com|coop|edu|gov|info|int|jobs|mil|museum|name|nato|net|org|pro|travel)|(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]))(\/[a-z0-9_\-\.~]+)*(\/([a-z0-9_\-\.]*)(\?[a-z0-9+_\-\.%=&amp;]*)?)?(#[a-z][a-z0-9_]*)?$/i",$field[$k]);
              $rtn = preg_match("/^((https?):\/\/)?([a-z]([a-z0-9\-]*\.)+([a-z]{2}|aero|arpa|biz|com|coop|edu|gov|info|int|jobs|mil|museum|name|nato|net|org|pro|travel)|(([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5])\.){3}([0-9]|[1-9][0-9]|1[0-9]{2}|2[0-4][0-9]|25[0-5]))(\/[a-z0-9_\-\.~]+)*(\/([a-z0-9_\-\.]*)(\?[a-z0-9+_\-\.%=&amp;]*)?)?(#[a-z][a-z0-9_]*)?$/i",$field[$k]);
              if ($rtn == 0) $err[] = $v;
            }//endfor
        }//endif

        if (array_key_exists('numeric', $check_data) !== false) {
            foreach($check_data['numeric'] AS $k=>$v) {
                if (empty($field[$k])) continue;

                $check = str_replace(",","",$field[$k]);
                if (is_numeric($check) === false) $err[] = $v;
                unset($check);
            }//endfor
        }//endif

        if (array_key_exists('match', $check_data) !== false) {
            foreach($check_data['match'] AS $k=>$v) {
                // check if there is any options passed
                list($check, $msg) = explode("|", $v);

                // if $msg then default to standard checking
                if (empty($msg)) {
                    $msg   = $check;
                    $check = "{$k}_match";
                }//end if
                
                if ( $field[$k] != $field[$check] ) $err[] = $msg;

            }//endfor
        }//endif

        if (array_key_exists('date', $check_data) !== false) {
            foreach($check_data['date'] AS $k=>$v) {
                if (empty($field[$k])) continue;

                if (!strtotime($field[$k])) $err[] = $v;
            }//endfor
        }//endif

        if (array_key_exists('dir_exists', $check_data) !== false) {
            foreach($check_data['dir_exists'] AS $k=>$v) {
                if (!is_dir($field[$k])) $err[] = $v;
            }//endfor
        }//endif

        if (array_key_exists('is_writable', $check_data) !== false) {
            foreach($check_data['is_writable'] AS $k=>$v) {
                if (!is_writable ($field[$k])) $err[] = $v;
            }//endfor
        }//endif

        if (array_key_exists('file_exists', $check_data) !== false) {
            foreach($check_data['file_exists'] AS $k=>$v) {
                if (!is_file($field[$k])) $err[] = $v;
            }//endfor
        }//endif

        if (count($err) == 0) return true;

        throw new Exception($err);

        return false;
      }//end function

      /**
       *  function
       * @param
       * @return
       */
      public static function Sanitize(array $data, array $config = array(), $paranoid = true)
      {

        /**
         * paranoid mode
         * by default all fields is char-255
         */
        if ($paranoid === true) {
            $keys = array_keys($data);
            foreach($keys as $k=>$v){
                // skip if value of data is array
                if (is_array($data[$v])) continue;

                // skip if there is a defined config for the data
                if (array_key_exists($v, $config)) continue;

                $config[$v] = 'char';
            }//end foreach
        }//end if
        
        foreach($config as $idx=>$cmd){
            if (!array_key_exists($idx, $data)) continue;

            switch(true){
              case 'raw' == $cmd:
                  break;

              /**
               * force numeric type
               * else 0
               *
               */
              case 'numeric' ==  $cmd:
                  $data[$idx] = str_replace(',', '', $data[$idx]) ;
                  if (!is_numeric($data[$idx])) {
                      $data[$idx] = 0;
                      break;
                  }//end if

                  $data[$idx] = $data[$idx] * 1;
                  break;

              /**
               * if bit then check if value is array
               * if array then sum up the value
               */
              case 'bit' ==  $cmd:
                  if (is_array($data[$idx])) {
                      $bit = 0;
                      foreach($data[$idx] as $k=>$v){
                          if (!is_numeric($v)) continue;

                          $bit += $v;
                      }//end foreach
                      $data[$idx] = $bit;
                  }//end if

                  /**
                   * force numeric value
                   */
                  if (!is_numeric($data[$idx]))
                      $data[$idx] = 0;

                  break;

              /**
               * if hash then check if value is array
               * if it is then do data:hashit
               *
               */
              case 'hash' == $cmd:
                  if (is_array($data[$idx])) {
                      $data[$idx] = static::Hash($data[$idx]);
                      break;
                  }//end if
                  break;

              /**
               * force date format
               * else null
               *
               */
              case 'date' ==  $cmd:
                  if (!strtotime($data[$idx])) {
                      $data[$idx] = null;
                      break;
                  }//end if

                  $data[$idx] = date("Y-m-d", strtotime($data[$idx]));
                  break;

              /**
               * force time format
               * else null
               *
               */
              case 'time' ==  $cmd:
                  if (!strtotime($data[$idx])) {
                      $data[$idx] = null;
                      break;
                  }//end if

                  $data[$idx] = date("H:i:s", strtotime($data[$idx]));
                  break;

              /**
               * force date time format
               * else null
               */
              case 'datetime' ==  $cmd:
                  if (!strtotime($data[$idx])) {
                      $data[$idx] = null;
                      break;
                  }//end if

                  $data[$idx] = date("Y-m-d H:i:s", strtotime($data[$idx]));
                  break;

              /**
               * allow html but remove script tag
               *
               */
              case 'html' ==  $cmd:
                  $data[$idx] = preg_replace(
                      array(
                        // Remove invisible content
                          '@<head[^>]*?>.*?</head>@siu',
                          '@<meta[^>]*?/?>@siu',
                          '@<script[^>]*?.*?</script>@siu',
                          '@<applet[^>]*?.*?</applet>@siu',
                          '@<noframes[^>]*?.*?</noframes>@siu',
                          '@<noscript[^>]*?.*?</noscript>@siu',
                      ),
                      array(
                          '',
                          '',
                          '',
                          '',
                          '',
                          '',
                      ),
                      $data[$idx] );

                  $data[$idx] = trim($data[$idx]);
                  //$data[$idx] = trim(strip_tags($data[$idx]));
                  break;

              /**
               * allow html allow script tag
               *
               */
              case 'html-js' ==  $cmd:
                  $data[$idx] = preg_replace(
                      array(
                        // Remove invisible content
                          '@<head[^>]*?>.*?</head>@siu',
                          '@<meta[^>]*?/?>@siu',
                          '@<applet[^>]*?.*?</applet>@siu',
                          '@<noframes[^>]*?.*?</noframes>@siu',
                          '@<noscript[^>]*?.*?</noscript>@siu',
                      ),
                      array(
                          '',
                          '',
                          '',
                          '',
                          '',
                      ),
                      $data[$idx] );

                  $data[$idx] = trim($data[$idx]);
                  break;

              /**
               * remove any tag
               *
               */
              case preg_match("/^tag-/is", $cmd):
                  list(, $tag) = explode("-", $cmd, 2);
                  $data[$idx] = preg_replace("!<{$tag}[^>]*?>.*?</{$tag}>!is", '', $data[$idx]);
                  $data[$idx] = trim($data[$idx]);
                  break;

              /**
               * if is array
               * then it is value is limited of what is in the array
               * if not exists it will use the first element of the array as the default
               *
               */
              case (is_array($cmd) && count($cmd) != 0):
                  if (array_search($data[$idx], $cmd) === false) {
                      $data[$idx] = $cmd[0];
                  }//end if
                  break;


              /**
               * force character remove html and script tag
               * if the cmd is like char-100
               * it will trim the data to 100 in length
               *
               */
//              case preg_match("/^char/is", $cmd):
//                  list(, $length) = explode("-", $cmd, 2);
//                  if (!is_numeric($length))
//                      $length = 255;
              case preg_match("/char\(([0-9].+)\)/is", $cmd, $matches):
                  $mlength = $matches[1];

              case preg_match("/char/is", $cmd):
                  $length = 255;
                  if (!empty($mlength))
                      $length = $mlength;

              /**
               * text is like character but with no character length
               * restrictions
               */
              case preg_match("/^text/is", $cmd):

              default:
                  /*$data[$idx] = preg_replace("!<script[^>]*?>.*?</script>!is", '', $data[$idx]);*/

                  $data[$idx] = preg_replace(
                      array(
                        // Remove invisible content
                          '@<head[^>]*?>.*?</head>@siu',
                          '@<style[^>]*?>.*?</style>@siu',
                          '@<script[^>]*?.*?</script>@siu',
                          '@<object[^>]*?.*?</object>@siu',
                          '@<embed[^>]*?.*?</embed>@siu',
                          '@<applet[^>]*?.*?</applet>@siu',
                          '@<noframes[^>]*?.*?</noframes>@siu',
                          '@<noscript[^>]*?.*?</noscript>@siu',
                          '@<noembed[^>]*?.*?</noembed>@siu',
                        // Add line breaks before and after blocks
                          '@</?((address)|(blockquote)|(center)|(del))@iu',
                          '@</?((div)|(h[1-9])|(ins)|(isindex)|(p)|(pre))@iu',
                          '@</?((dir)|(dl)|(dt)|(dd)|(li)|(menu)|(ol)|(ul))@iu',
                          '@</?((table)|(th)|(td)|(caption))@iu',
                          '@</?((form)|(button)|(fieldset)|(legend)|(input))@iu',
                          '@</?((label)|(select)|(optgroup)|(option)|(textarea))@iu',
                          '@</?((frameset)|(frame)|(iframe))@iu',
                      ),
                      array(
                          ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ',
                          "\n\$0", "\n\$0", "\n\$0", "\n\$0", "\n\$0", "\n\$0",
                          "\n\$0", "\n\$0",
                      ),
                      $data[$idx] );

                  $data[$idx] = trim(strip_tags($data[$idx]));
                  
                  if (is_numeric($length))
                    $data[$idx] = substr($data[$idx], 0, $length);

                  break;
            }//end switch

        }//end foreach

        return $data;
    }// end function 

    /**
     *  function
     * @param
     * @return
     */
    public static function ArrayMerge(array $array1, array $array2)
    {
        $retval = $array1;
        if (count($array2) == 0) return $retval;

        foreach($array2 as $k=>$v){
            /**
             * if array2 value is null, then
             * do not include it in the $retval
             */
            if (is_null($v)) {
                unset($retval[$k]);
                continue;
            }//end if

            /**
             * check if key does not exists in array1
             */
            if (!array_key_exists($k, $array1)) {
                $retval[$k]=$v;
                continue;
            }//end if

            if (!is_array($array1[$k])) {
                $retval[$k] = $v;
                continue;
            }//end if

            /**
             * the value of $array1[$k]
             * is array from here on out
             */
            if (!is_array($v)) {
                $retval[$k] = $v;
                continue;
            }//end if

            /**
             * both $array[$k] is array and $v is array
             * recurse the array
             */
            $retval[$k] = static::ArrayMerge($array1[$k], $v);
        }//end foreach

        return $retval;
    }// end function

    /**
     *  function
     * @param
     * @return
     */
    public static function RandomString($length=8)
    {
        return substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz'),0,$length);
    }// end function */

    /**
     *
     * @access
     * @var
     */
    public static function Uuid() {
        mt_srand( intval( microtime( true ) * 1000 ) );
        $b = md5( uniqid( mt_rand(), true ), true );
        $b[6] = chr( ( ord( $b[6] ) & 0x0F ) | 0x40 );
        $b[8] = chr( ( ord( $b[8] ) & 0x3F ) | 0x80 );
        return implode( '-', unpack( 'H8a/H4b/H4c/H4d/H12e', $b ) );
    }// end function

    /**
     * ie:
     * $ckfile     = tempnam(sys_get_temp_dir(), "CURLCOOKIE");
     * $result     = curly('http://domainname.com/', array(
     *                  'parameters'    => array('x' => 1),
     *                  'cookie_file'   => $ckfile,
     *                  'return_header' => true,
     *                  'cookies'       => array('y' => 2),
     *              ));
     *
     */
    public static function Curly($url, array $options = array() )
    {

        $show               = false;
        $ssl_verify         = false;
        $ssl_cainfo_file    = "";

        $return_header      = false;
        $follow_location    = true;

        $cookie_file        = NULL;
        $cookies            = array();

        $http_referer       = $_SERVER['HTTP_REFERER'];
        $http_user_agent    = $_SERVER['HTTP_USER_AGENT'];

        $parameters         = array();
        $files              = array();
        $post               = false;
        $query              = array();

        $authuser           = null;
        $authpass           = null;

        $timeout            = 18;

        extract($options, EXTR_IF_EXISTS);

        /**
         * init stuff
         *
         */
        $ch             = curl_init();
        $parsed_url     = parse_url($url);
        $retval         = array(
            'response'  => NULL,
            'error'     => NULL,
            'headers'   => NULL,
        );

        /**
         * process parameters
         *
         */
        if ($post === true) {

            $post_fields = $parameters;
            $uploadcount = 0;

            if (count($files) != 0) {

                foreach($files as $k=>$v){
                    /**
                     * check if file upload
                     */
                    if (!is_file($v)) continue;

                    $post_fields[$k] = '@'.realpath($v);
                    $uploadcount++;
                }//end foreach

            }//end if

            curl_setopt($ch, CURLOPT_POST, true);

            if ($uploadcount == 0)
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_fields, '', '&'));
            else
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);


        } else {

            array_push($query, http_build_query($parameters, '', '&'));
            $query_str = implode('&', $query);

            if (strpos($url, '?') === false)
                $query_str = '?'.$query_str;

            $url = $url.$query_str;

        }//end if

        /**
         * process cookies
         *
         */
        if (count($cookies) != 0 && ($fp = fopen($cookie_file, "w"))) {

            $domain = $parsed_url['host'];

            foreach($cookies as $cookie_name => $cookie_value){
                $write = array(
                    $domain,
                    'FALSE',
                    '/',
                    'FALSE',
                    strtotime("+24 days", time()),
                    $cookie_name,
                    $cookie_value,
                );

                fwrite($fp, "\n".implode("\t", $write));
            }//end foreach

            fclose($fp);
            
        }//end if

        if (is_file($cookie_file)) {
            curl_setopt ($ch, CURLOPT_COOKIEJAR, $cookie_file);
            curl_setopt ($ch, CURLOPT_COOKIEFILE, $cookie_file);
        }//end if

        /**
         * curl settings
         *
         */
        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 9);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');

        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, $follow_location);
        curl_setopt($ch, CURLOPT_HEADER, $return_header);

        curl_setopt($ch, CURLOPT_USERAGENT, $http_user_agent);
        curl_setopt($ch, CURLOPT_REFERER, $http_referer);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);

        /**
         * setup headers
         *
         */
        $header = array();
        $header[] = "Accept: text/xml,application/xml,application/xhtml+xml,text/html;q=0.9,text/plain;q=0.8,image/png,* /*;q=0.5";
        $header[] = "Cache-Control: max-age=0";
        $header[] = "Connection: keep-alive";
        $header[] = "Keep-Alive: 300";
        $header[] = "Accept-Charset: ISO-8859-1,utf-8;q=0.7,*;q=0.7";
        $header[] = "Accept-Language: en-us,en;q=0.5";
        $header[] = "Pragma: ";

        if (!empty($authuser))
            $header[] = "Authorization: Basic " . base64_encode($authuser.":".$authpass);

        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

        if ($show === false)
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

        /**
         * SSL VERIFICATION
         *
         */
        switch($ssl_verify){
            case false:
                curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, false);
                break;

            case true:
                if (is_file($ssl_cainfo_file)) {
                    curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, TRUE);
                    curl_setopt ($ch, CURLOPT_CAINFO, $ssl_cainfo_file);
                }//end if
                break;

            case NULL:
                break;

        }//end switch

        /**
         * call curl, now na!
         *
         */
        $response           = curl_exec($ch);
        $retval['response'] = $response;

        /**
         * error check
         */
        $retval['error']    = array(curl_errno($ch), curl_error($ch));

        /**
         * close curl
         */
        curl_close($ch);

        /**
         * grab header
         */
        if ($show === false && $return_header) {
            $retval = array();

            // Extract headers from response
            $pattern = '#HTTP/\d\.\d.*?$.*?\r\n\r\n#ims';
            preg_match_all($pattern, $response, $matches);
            $headers = split("\r\n", str_replace("\r\n\r\n", '', array_pop($matches[0])));

            // Extract the version and status from the first header
            $version_and_status = array_shift($headers);
            preg_match('#HTTP/(\d\.\d)\s(\d\d\d)\s(.*)#', $version_and_status, $matches);
            $retval['headers']['Http-Version'] = $matches[1];
            $retval['headers']['Status-Code'] = $matches[2];
            $retval['headers']['Status'] = $matches[2].' '.$matches[3];

            // Convert headers into an associative array
            foreach ($headers as $header) {
                preg_match('#(.*?)\:\s(.*)#', $header, $matches);
                $retval['headers'][$matches[1]][] = $matches[2];
            }//end foreach

            // Remove the headers from the response body
            $retval['response'] = preg_replace($pattern, '', $response);
        }//end if

        return $retval;
    }

    public static function Numpage($recordcount, $selpage=1, $numview = 20){
        $recordcount = abs((int) $recordcount);
        if ($recordcount <= 0) return false;

        if ($recordcount != 0) {
            $pagecount = (double)floor($recordcount / $numview);
            $pagecount += (($recordcount % $numview) == 0)? 0: 1;
        }//end if

        $offset = 0;
        $countbatch = 9;
        $startbatch = 1;
        $endbatch = 1 + $countbatch;

        $numview = $numview;
        $countrecord = $recordcount;

        $selpage = ($selpage > $pagecount)? $pagecount: $selpage;
        $selpage = ($selpage < 1)? 1: $selpage;

        $offset = (($selpage - 1)*$numview);
        $offset = ($offset >= $recordcount)? $recordcount-$numview: $offset;
        $offset = ($offset < 0)? 0: $offset;

        $retval = array(
            "nav"       => self::Pagenav($selpage, $pagecount),
            "rows"      => array(
                'offset'    => $offset,
                'limit'     => $numview,
                'total'     => $recordcount,
            ),
        );

        $retval['nav']['page_rows'] = $recordcount;

        return $retval;

    }// end function numpage

    public static function Pagenav($current_page, $total_page){
        $page_threshold = 10;

        if ($total_page <= 1) return array();

        $current_page = ($current_page < 1)? 1: $current_page;
        $current_page = ($current_page > $total_page)? $total_page: $current_page;

        $center = floor($page_threshold / 2);

        $start = $current_page - $center;
        $end = $current_page + $center;

        $end = ($end < $page_threshold)? ($page_threshold+1): $end;
        $end = ($end > $total_page)? $total_page: $end;

        if ($end == $total_page) {
            $diff = $end - $start;
            $start = $start - (9-$diff);
        }#end if

        $start = ($start < 1)? 1: $start;

        $next_page = 0;
        $prev_page = 0;

        if ($current_page > 1) $prev_page = $current_page - 1;
        if ($current_page < $total_page) $next_page = $current_page + 1;

        $pagenav['page_current']    = $current_page;
        $pagenav['page_next']       = $next_page;
        $pagenav['page_prev']       = $prev_page;
        $pagenav['page_total']      = $total_page;
        $pagenav['page_start']      = $start;
        $pagenav['page_end']        = $end;

        return $pagenav;
    }//end function

}//end class