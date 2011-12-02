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
/*Table structure for table `mod_categories` */

DROP TABLE IF EXISTS `mod_categories`;

CREATE TABLE `mod_categories` (
  `categoryid` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `cat_name` char(100) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `cat_descr` char(255) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `cat_group` char(220) CHARACTER SET utf8 NOT NULL DEFAULT '',
  `cat_typestat` smallint(5) unsigned NOT NULL DEFAULT '0',
  `languages` char(5) CHARACTER SET utf8 NOT NULL DEFAULT '',
  PRIMARY KEY (`categoryid`,`cat_name`,`cat_group`),
  KEY `name` (`cat_name`,`languages`,`cat_typestat`)
) ENGINE=MyISAM AUTO_INCREMENT=68 DEFAULT CHARSET=latin1;

/*Table structure for table `mod_categories_joins` */

DROP TABLE IF EXISTS `mod_categories_joins`;

CREATE TABLE `mod_categories_joins` (
  `categoryid` bigint(20) unsigned NOT NULL DEFAULT '0',
  `joinid` bigint(20) unsigned NOT NULL DEFAULT '0',
  `category_sort` smallint(5) unsigned NOT NULL DEFAULT '0',
  `link_sort` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`categoryid`,`joinid`),
  KEY `link` (`joinid`,`link_sort`),
  KEY `cat` (`categoryid`,`category_sort`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*Table structure for table `mod_customfields` */

DROP TABLE IF EXISTS `mod_customfields`;

CREATE TABLE `mod_customfields` (
  `fieldid` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `field_uid` bigint(20) unsigned NOT NULL COMMENT 'unique id',
  `field_group` char(200) NOT NULL DEFAULT '',
  `field_name` char(100) NOT NULL DEFAULT '',
  `field_value` char(255) NOT NULL DEFAULT '',
  `field_hash` longtext,
  `field_typestat` smallint(6) NOT NULL DEFAULT '0',
  `field_sort` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`fieldid`,`field_uid`,`field_name`,`field_group`),
  KEY `value` (`field_value`),
  KEY `idx` (`field_group`,`field_sort`),
  KEY `name` (`field_uid`,`field_name`,`field_typestat`)
) ENGINE=MyISAM AUTO_INCREMENT=1416 DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;

/*Table structure for table `mod_fileattachments` */

DROP TABLE IF EXISTS `mod_fileattachments`;

CREATE TABLE `mod_fileattachments` (
  `fileid` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `file_uid` bigint(20) unsigned NOT NULL,
  `file_title` char(255) NOT NULL DEFAULT '',
  `file_name` char(200) NOT NULL DEFAULT '',
  `file_descr` char(255) NOT NULL DEFAULT '',
  `file_alt` char(255) NOT NULL DEFAULT '',
  `file_mime` char(100) NOT NULL DEFAULT '' COMMENT 'MIME type',
  `file_code` char(255) NOT NULL DEFAULT '',
  `file_group` char(100) NOT NULL DEFAULT '' COMMENT ' use / for subgroup etc',
  `file_typestat` smallint(6) NOT NULL DEFAULT '0' COMMENT 'visible | hidden',
  `file_sort` smallint(5) unsigned NOT NULL DEFAULT '0',
  `file_date_created` datetime DEFAULT NULL,
  `file_date_updated` datetime DEFAULT NULL,
  `file_data` mediumblob,
  PRIMARY KEY (`fileid`,`file_uid`,`file_group`,`file_name`),
  KEY `fcode-status` (`file_code`,`file_typestat`),
  KEY `fgroup` (`file_group`,`file_sort`),
  KEY `linkid` (`file_uid`,`file_mime`)
) ENGINE=MyISAM AUTO_INCREMENT=1268 DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=FIXED;

/*Table structure for table `mod_metatags` */

DROP TABLE IF EXISTS `mod_metatags`;

CREATE TABLE `mod_metatags` (
  `metaid` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `meta_uid` bigint(20) unsigned NOT NULL,
  `meta_title` mediumtext,
  `meta_descr` mediumtext,
  `meta_keywords` mediumtext,
  `meta_robots` mediumtext,
  `meta_analytics` mediumtext,
  `page_title` char(255) NOT NULL DEFAULT '',
  `page_url` char(220) NOT NULL DEFAULT '',
  `meta_group` char(100) NOT NULL DEFAULT '' COMMENT 'use / for subgroup',
  `languages` char(5) NOT NULL DEFAULT 'en',
  PRIMARY KEY (`metaid`,`meta_uid`,`meta_group`),
  KEY `lingo` (`languages`),
  KEY `idx` (`meta_uid`,`page_url`),
  KEY `group` (`meta_group`)
) ENGINE=MyISAM AUTO_INCREMENT=1091 DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED;

/*Table structure for table `mod_tags` */

DROP TABLE IF EXISTS `mod_tags`;

CREATE TABLE `mod_tags` (
  `tagid` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `tag_name` char(100) CHARACTER SET utf8 NOT NULL,
  `tag_group` char(220) CHARACTER SET utf8 NOT NULL DEFAULT '',
  PRIMARY KEY (`tagid`,`tag_name`,`tag_group`)
) ENGINE=MyISAM AUTO_INCREMENT=35 DEFAULT CHARSET=latin1;

/*Table structure for table `mod_tags_joins` */

DROP TABLE IF EXISTS `mod_tags_joins`;

CREATE TABLE `mod_tags_joins` (
  `tagid` bigint(20) unsigned NOT NULL,
  `tag_uid` bigint(20) unsigned NOT NULL,
  `tag_sort` smallint(5) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`tagid`,`tag_uid`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
