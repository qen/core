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
/*Table structure for table `mod_blog_posts` */

DROP TABLE IF EXISTS `mod_blog_posts`;

CREATE TABLE `mod_blog_posts` (
  `blogid` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `blog_authorid` bigint(20) unsigned NOT NULL DEFAULT '0',
  `blog_group` char(255) NOT NULL DEFAULT '',
  `blog_url` char(255) NOT NULL DEFAULT '',
  `blog_title` char(100) NOT NULL DEFAULT '',
  `blog_author_name` char(255) NOT NULL DEFAULT '',
  `blog_author_link` char(255) NOT NULL DEFAULT '',
  `blog_descr` char(220) NOT NULL DEFAULT '',
  `blog_date` date NOT NULL,
  `blog_details` text,
  `blog_sort` smallint(5) unsigned NOT NULL DEFAULT '0',
  `blog_typestat` smallint(6) unsigned NOT NULL DEFAULT '0' COMMENT '1=visible,2=comment,4=trackback',
  `blog_settings` longtext,
  `languages` char(5) NOT NULL DEFAULT 'en',
  PRIMARY KEY (`blogid`,`blog_title`,`blog_date`,`blog_descr`,`blog_sort`),
  KEY `idxs` (`blog_url`,`blog_date`),
  KEY `find` (`blog_typestat`,`blog_authorid`,`blog_group`,`languages`)
) ENGINE=MyISAM AUTO_INCREMENT=208 DEFAULT CHARSET=utf8;

/*Table structure for table `mod_pages` */

DROP TABLE IF EXISTS `mod_pages`;

CREATE TABLE `mod_pages` (
  `pageid` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `page_group` char(20) NOT NULL DEFAULT '',
  `page_url` char(255) NOT NULL DEFAULT '',
  `page_title` char(255) NOT NULL DEFAULT '',
  `page_descr` char(255) NOT NULL DEFAULT '',
  `page_details` longtext,
  `page_sort` tinyint(3) unsigned NOT NULL DEFAULT '0',
  `page_date_add` date NOT NULL,
  `page_date_updated` datetime NOT NULL,
  `page_typestat` smallint(6) NOT NULL,
  `page_settings` longtext,
  `languages` char(25) NOT NULL DEFAULT 'en',
  PRIMARY KEY (`pageid`,`page_url`,`page_sort`,`page_group`),
  KEY `lingo` (`languages`),
  KEY `search` (`page_title`,`page_typestat`,`page_date_updated`)
) ENGINE=MyISAM AUTO_INCREMENT=1113 DEFAULT CHARSET=utf8 CHECKSUM=1 DELAY_KEY_WRITE=1 ROW_FORMAT=DYNAMIC;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
