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
use Core\App\Config as AppConfig;

class Mailer {

    public $MAIL_RETURN_PATH;
    public $MAIL_DEFAULT_FROM;
    public $MAIL_DEFAULT_FROM_NAME;
    public $MAIL_DEFAULT_REPLYTO;

    ############################################################################
    function __construct(){
    ############################################################################
        $conf = AppConfig::Mailer();
        $this->MAIL_RETURN_PATH         = $conf['return_path'];
        $this->MAIL_DEFAULT_REPLYTO     = $conf['replyto'];
        $this->MAIL_DEFAULT_FROM        = $conf['from'];
        $this->MAIL_DEFAULT_FROM_NAME   = $conf['from_name'];
    }#end function

    ############################################################################
    private function mailHeaders(){
    ############################################################################

        $retval = "MIME-Version: 1.0\n";
        $retval .= "X-Sender: <". $this->MAIL_RETURN_PATH .">\n";
        $retval .= "X-Mailer: core.v5\n";
        $retval .= "X-Priority: 0\n";
        $retval .= "Return-Path: <". $this->MAIL_RETURN_PATH .">\n";

        return $retval;
    }#end function

    ############################################################################
    public function sendMail($mail){
    /***********************************
    Example:

    $mail['body'] = stripslashes($content);
    $mail['subject'] = $subject;
    $mail["replyto"] = $USERINFO["email"];
    $mail["from"] = $USERINFO["email"];

    $mail['attachments'] = array();
    foreach($_FILES as $k=>$v){
        if (!is_uploaded_file($v['tmp_name'])) continue;
        $mail['attachments'][] = $v;
    }#endif

    $mail['to'] = "qen@markjoyner.name";
    send_mail($mail);
    ***********************************/
    ############################################################################
        if (empty($mail['to'])) return;

        $from = $this->MAIL_DEFAULT_FROM;
        if (!empty($mail['from']) ) $from = $mail['from'];

        $from_name = "{$from}";
        if ($this->MAIL_DEFAULT_FROM_NAME != '')
            $from_name = $this->MAIL_DEFAULT_FROM_NAME."<{$from}>";

        if (!empty($mail['from_name']) ) $from_name="{$mail['from_name']}<$from>";

        $mail['headers'] = "From: $from_name\r\n";
        if (!empty($mail['cc']) )
            $mail['headers'] .= "CC: ". $mail['cc'] ."\r\n";
        if (!empty($mail['bcc']))
            $mail['headers'] .= "BCC: ". $mail['bcc'] ."\r\n";


        if (!empty($mail['replyto']))
            $mail['headers'] .= "Reply-To: ". $mail['replyto'] ."\r\n";
        else
            $mail['headers'] .= "Reply-To: ". $this->MAIL_DEFAULT_REPLYTO ."\r\n";

        $mail['headers'] .= $this->mailHeaders();

        if (count($mail["attachments"]) == 0) {
            if (strlen(strip_tags($mail["body"])) == strlen($mail["body"]))
                $mail['headers'] .= "Content-type: text/plain; charset=utf-8\n";
            else
                $mail['headers'] .= "Content-type: text/html; charset=utf-8\n";
        }else{

            $eol = "\n";
            $mime_boundary=md5(time());
            $body = $mail["body"];

            $mail['headers'] .= "Content-Type: multipart/related; boundary=\"".$mime_boundary."\"".$eol;
            $mail["body"] = "";

            $mail["body"] .= "--".$mime_boundary.$eol;

            if (strlen(strip_tags($body)) == strlen($body))
                $mail["body"] .= "Content-type: text/plain; charset=iso-8859-1".$eol;
            else
                $mail["body"] .= "Content-type: text/html; charset=iso-8859-1".$eol;

            $mail["body"] .= "Content-Disposition: inline;".$eol;
            $mail["body"] .= "Content-Transfer-Encoding: 8bit".$eol;
            $mail["body"] .= $body.$eol.$eol;

            # Attachment
            if (count($mail["attachments"]) != 0) {

                foreach($mail["attachments"] as $k=>$v){

                    $f_name = $v['tmp_name'];
                    $handle = fopen($f_name, 'rb');

                    $f_contents = fread($handle, filesize($f_name));
                    $f_contents = chunk_split(base64_encode($f_contents));
                    
                    fclose($handle);

                    $mail["body"] .= "--".$mime_boundary.$eol;
                    $mail["body"] .= "Content-Type: ".$v["type"]."; name=\"".$v["name"]."\"".$eol;
                    $mail["body"] .= "Content-Transfer-Encoding: base64".$eol;
                    $mail["body"] .= "Content-Disposition: attachment; filename=\"".$v["name"]."\"".$eol.$eol;
                    $mail["body"] .= $f_contents.$eol.$eol;

                }#end foreach

            }#endif

            #Finished
            $mail["body"] .= "--".$mime_boundary."--".$eol.$eol;

        }#endif

        if (!mail($mail['to'],$mail['subject'],$mail['body'],$mail['headers']))
            throw new Exception("Failed sending email");
        
        return true;
    }#end function

}#end class