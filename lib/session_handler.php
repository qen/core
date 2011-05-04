<?php

use Core\Db;

/*
 
CREATE TABLE `core_sessions` (
  `sessionid` CHAR(32) NOT NULL DEFAULT '',
  `session_ip` CHAR(32) NOT NULL DEFAULT '',
  `session_data` TEXT,
  `date_created` DATETIME DEFAULT NULL,
  `date_updated` INT(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`sessionid`,`session_ip`),
  KEY `lastupdate` (`date_updated`)
) ENGINE=INNODB DEFAULT CHARSET=utf8;

*/
session_set_save_handler(
    // open
    function($save_path, $session_name) {
        return true;
    },
    // close
    function() {
        return true;
    },
    // read
    function($id) {

        if (preg_match('~^[A-Za-z0-9]{16,32}$~', $id) == 0)
            return false;

        $db     = Db::Instance();
        $sql    = "select session_data from core_sessions where sessionid = ".$db->escape($id)." and session_ip = ".$db->escape($_SERVER["REMOTE_ADDR"])." limit 1";
        $result = $db->query($sql);

        if (empty($result)) {
            $sql = "insert into core_sessions (sessionid, session_ip, date_created, date_updated) values (".$db->escape($id).", ".$db->escape($_SERVER["REMOTE_ADDR"]).", NOW(), ".time().")";
            $db->execute($sql);
            return false;
        }//end if

        return $result[0]['session_data'];
    },
    // write
    function($id, $data) {
        
        if (preg_match('~^[A-Za-z0-9]{16,32}$~', $id) == 0)
            return false;

        $db     = Db::Instance();
        $sql    = "update core_sessions set session_data = ".$db->escape($data).", date_updated = ".time()." where sessionid = ".$db->escape($id)." and session_ip = ".$db->escape($_SERVER["REMOTE_ADDR"])." limit 1";
        $result = $db->execute($sql);

        return true;
    },
    // destroy
    function($id) {
        if (preg_match('~^[A-Za-z0-9]{16,32}$~', $id) == 0)
            return false;

        $db     = Db::Instance();
        $sql    = "delete core_sessions where sessionid = ".$db->escape($id)." and session_ip = ".$db->escape($_SERVER["REMOTE_ADDR"])." limit 1";
        $result = $db->execute($sql);

        return true;
    },
    // gc
    function($maxlifetime) {
        $db     = Db::Instance();
        $sql    = "delete from core_sessions where date_updated < " . (time() - $maxlifetime);
        $result = $db->execute($sql);

        return true;
    });

