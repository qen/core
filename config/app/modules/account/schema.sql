/*
SQLyog Community v8.63 
MySQL - 5.1.50 : Database - webrants
*********************************************************************
*/

/*!40101 SET NAMES utf8 */;

/*!40101 SET SQL_MODE=''*/;

/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
/*Table structure for table `mod_accounts` */

DROP TABLE IF EXISTS `mod_accounts`;

CREATE TABLE `mod_accounts` (
  `accountid` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `acnt_code` char(50) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `acnt_title` char(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `acnt_name_prefix` char(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `acnt_name` char(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `acnt_name_suffix` char(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `acnt_email` char(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `acnt_date_added` datetime DEFAULT NULL,
  `acnt_date_birth` date DEFAULT NULL,
  `acnt_street1` char(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `acnt_street2` char(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `acnt_city` char(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `acnt_state` char(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `acnt_country` char(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `acnt_zipcode` char(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `acnt_typestat` smallint(5) unsigned NOT NULL DEFAULT '0',
  `acnt_group` char(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `acnt_timezone` char(255) CHARACTER SET utf8 NOT NULL DEFAULT '0',
  `acnt_settings` longtext CHARACTER SET utf8,
  PRIMARY KEY (`accountid`),
  UNIQUE KEY `name` (`acnt_code`,`acnt_name`),
  KEY `etc` (`acnt_date_birth`,`acnt_group`)
) ENGINE=MyISAM AUTO_INCREMENT=52 DEFAULT CHARSET=latin1 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=FIXED;

/*Table structure for table `mod_accounts_access` */

DROP TABLE IF EXISTS `mod_accounts_access`;

CREATE TABLE `mod_accounts_access` (
  `accessid` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `accountid` bigint(20) unsigned NOT NULL DEFAULT '0',
  `referral` char(255) NOT NULL DEFAULT '0',
  `acc_uid` bigint(20) unsigned NOT NULL DEFAULT '0' COMMENT 'unique id',
  `acc_code` char(100) NOT NULL DEFAULT '',
  `acc_name` char(220) NOT NULL DEFAULT '',
  `acc_descr` char(255) NOT NULL DEFAULT '',
  `acc_date_added` datetime DEFAULT NULL,
  `acc_date_expire` date DEFAULT NULL,
  `acc_source` char(255) NOT NULL DEFAULT '',
  `acc_group` char(255) NOT NULL DEFAULT '',
  `acc_typestat` smallint(5) unsigned NOT NULL DEFAULT '0',
  `acc_settings` longtext,
  PRIMARY KEY (`accessid`,`acc_name`,`acc_uid`,`acc_code`),
  KEY `group` (`acc_group`),
  KEY `src` (`acc_source`),
  KEY `name` (`acc_name`),
  KEY `referral` (`referral`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=FIXED;

/*Table structure for table `mod_users` */

DROP TABLE IF EXISTS `mod_users`;

CREATE TABLE `mod_users` (
  `userid` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usr_uid` bigint(20) unsigned NOT NULL DEFAULT '0',
  `usr_email` char(200) NOT NULL DEFAULT '',
  `usr_username` char(100) NOT NULL DEFAULT '',
  `usr_fname` char(255) NOT NULL DEFAULT '',
  `usr_lname` char(255) NOT NULL DEFAULT '',
  `usr_password` char(255) NOT NULL DEFAULT '',
  `usr_typestat` smallint(5) unsigned NOT NULL DEFAULT '0' COMMENT 'pending = waiting email confirmation',
  `usr_date_added` datetime DEFAULT NULL,
  `usr_date_logged` datetime DEFAULT NULL,
  `usr_settings` longtext,
  `usr_ipaddy` char(255) NOT NULL DEFAULT '',
  `usr_md5` char(32) NOT NULL DEFAULT '',
  `usr_group` char(255) NOT NULL DEFAULT '' COMMENT 'use / for subgroup',
  PRIMARY KEY (`userid`,`usr_uid`,`usr_email`,`usr_username`),
  KEY `group` (`usr_group`,`usr_typestat`,`usr_md5`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
