#!/bin/bash

#script_dirname=`dirname $0`;
# get where the core directory resides
core_path="$( readlink -f "$( dirname "$0" )/" )";
core_parent_path="$( readlink -f "$( dirname "$0" )/../" )";

path='app';
controller='';
webroots='webroot';
env='development';
help=0;
for p in $*;
    do
      [[ $p =~ ^--path=(.*) ]] && path="${BASH_REMATCH[1]}";
      [[ $p =~ ^--controller=(.*) ]] && controller="${BASH_REMATCH[1]}";
      [[ $p =~ ^--webroots=(.*) ]] && webroots="${BASH_REMATCH[1]}";
      [[ $p =~ ^--environment=(.*) ]] && env="${BASH_REMATCH[1]}";
      [[ $p =~ ^--help ]] && help=1;
    done;

if [ $help -eq 1 ]
then
  cat <<EOF
Executing
    core/install.sh
    will initialize the .htaccess, init.php and the app folder structure, containing subfolders controllers, modules, webroots

Syntax
    core/install.sh --path=~/folder [--controller=controller_namespace] [--webroots=folder1,folder2] [--environment=development]

Example Usages
    core/install.sh --path=~/folder --controller=WebMain --webroots=webroot,web_main

EOF
  exit;
fi

# create app directory if it doesn't exists yet
path="$( readlink -f "$( basename "$path" )" )";
pathname=`basename $path`;

[ ! -d $path ] && mkdir $path;

# create controllers directory
if [ ! -d "$path/controllers" ]
then
  mkdir "$path/controllers";
  $core_path/controller.sh --path="$path" /index
fi
 

# create modules directory
[ ! -d "$path/modules" ] && mkdir "$path/modules";

# create webroot_lists directory
webroot_lists=(`echo $webroots | tr "," " "`)

# create htaccess file
cat > .htaccess <<EOF
Options FollowSymLinks
RewriteEngine On
RewriteBase /

RewriteCond %{REQUEST_URI} (.+\.(gif|jpe?g|png|css|js|pdf|doc|xml|txt|ico|swf|flv|cur|zip|htc?))$
RewriteRule (.*) - [E=assets:yes]
EOF

# create init.php file
cat > init.php <<EOF
<?php
namespace Core\App;

use Core\Db;
use Core\View;
use Core\Controller;
use Core\ModelActions;

##############################################################
# do not touch this not unless you know what you are doin
##############################################################
define('Core\App\PATH', '$path');
define('Core\App\WEBROOT', PATH.'/${webroot_lists[0]}');
##############################################################

const ENVIRONMENT   = '$env';
const CONTROLLER    = '$controller'; # controllers namespace

//const TIMELIMIT     = 240;
//const TIMEZONE      = 'Asia/Manila';

require "$core_path/bootstrap.php";

/**
 * template lookup directories
 * Path::ViewDir('[folder name only]');
 */
EOF

# loop to all webroot lists
for webroot in "${webroot_lists[@]}"
do
  [ ! -d "$path/$webroot" ] && mkdir "$path/$webroot";
  cat >> .htaccess <<EOF

# -- $webroot --
RewriteCond %{ENV:assets} =yes
RewriteCond $path/$webroot%{REQUEST_URI} -f
RewriteRule ^(.*) $pathname/$webroot%{REQUEST_URI} [L]

EOF

  #[ "$webroot" != "${webroot_lists[0]}" ] && echo "Path::ViewDir('$webroot');" >> init.php;
  echo -e "Path::ViewDir('$webroot');" >> init.php;

done

# finalize htaccess file
cat >> .htaccess <<EOF

# if file does not exists check if prefixed by [modules]
RewriteCond %{ENV:assets} =yes
RewriteCond %{REQUEST_URI} ^/modules/([^/]+)/(.+)$
RewriteCond $path/modules/%1/public/%2 -f
RewriteRule ^(.*) $pathname/modules/%1/public/%2 [L]

RewriteCond %{ENV:assets} =yes
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^(.*) - [L]

RewriteRule !init\.php - [C]
RewriteRule (.*) init.php [L]
EOF

# finalize init.php file
cat >> init.php <<EOF

/**
 * clear whitespace on compiled twig templates
 */
define('TWIG_CLEAR_WHITESPACE', 1);

include 'common/session_handler.php';

/**
 * for development purposes
 */
error_reporting(E_ALL ^ E_NOTICE);
Db::\$Debug             = true;  // set to true to dump all sql statement to db.log
View::\$Debug           = true;  // set to true to dump stuff to messages.log
Controller::\$Debug     = false; // set to true to echo echo to messages.log
ModelActions::\$Debug   = true;  // set to true to dump debug to stuff on model_actions.log

# finally route the request here
Route::Request();

EOF



cat <<EOF

Core application path in $path

`pwd`/.htaccess created
`pwd`/init.php created

EOF

# create config file
config="$core_path/config/$env.php";
if [ ! -f $config ]
then
  touch $config
  cat > $config <<EOF
<?php

echo "<h1>$env.php is not configured</h1>"; exit;

return <<<CONFIG_JSON
{
    "db" : {
        "dsn"       : "mysql:dbname=DATABSENAME;host=HOST",
        "usr"       : "USER",
        "pwd"       : "PASS",
        "schemas"   : {
        }
    }

}
CONFIG_JSON;

EOF

  echo -e "Configuration Needed!\n$config\n"

fi
