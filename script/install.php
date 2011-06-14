#!/usr/bin/php
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
$app = array(
    'path'          => '',
    'controller'    => '',
    'webroots'      => 'webroot',
    'env'           => 'development',
);

foreach ($argv as $k => $v) {
    @list($var, $value) = @explode("=", $v, 2);
    $app[$var] = $value;
}// end foreach

if ($app['path'] == 'app' && !is_dir($app['path'])) {
    $pwd = getcwd();
    $app['path'] = "{$pwd}/app";
    if (!is_dir("{$pwd}/app"))
        mkdir("{$pwd}/app", 0775);
}//end if

if (!is_dir($app['path'])) {
echo <<<EOF

Example Usages

Executing
    script/install.php path=app
    Will install/create the app directory on you're current working directory or where the script is executed
    it will also create .htaccess and init.php file in you're current working directory if the app folder and the PHP-CoreApp folder
    are on the same parent directory
    
Setup Folder:
    script/install.php path=~/folder [controller=controller_namespace] [webroots=folder1,folder2] [env=development]
    script/install.php path=~/folder controller=WebMain webroots=webroot,web_main

EOF;

echo "\n\n";
    exit;
}//end if

$appdir     = realpath($app['path']);
$coredir    = realpath(dirname(__FILE__).'/../');

$appdir_parent  = realpath("{$appdir}/../");
$coredir_parent = realpath("{$coredir}/../");
$lookup_prefix  = '';
$lookup_prefix_slashed = '';

if ($appdir_parent == $coredir_parent) {
    $lookup_prefix = str_replace($appdir_parent.'/', '', $appdir).'/';
    $lookup_prefix_slashed = '/'.$lookup_prefix;
}//end if

if (is_dir("{$appdir}/app")) {
    $appdir_parent  = $appdir ;
    $appdir         = "{$appdir}/app";
    $lookup_prefix  = 'app/';
    $lookup_prefix_slashed = '/'.$lookup_prefix;
}//end if

echo <<<EOF
appdir  = {$appdir}
coredir = {$coredir}
prefix  = {$lookup_prefix}

EOF;

if (!is_dir("{$appdir}/controllers") && is_dir("{$coredir}/app/controllers"))
    symlink("{$coredir}/app/controllers", "{$appdir}/controllers");
elseif ( !is_dir("{$appdir}/controllers") )
    mkdir("{$appdir}/controllers", 0775);

if (!is_dir("{$appdir}/modules") && is_dir("{$coredir}/app/modules"))
    symlink("{$coredir}/app/modules", "{$appdir}/modules");
elseif ( !is_dir("{$appdir}/modules") )
    mkdir("{$appdir}/modules", 0775);

if (empty($app['webroots'])) $app['webroots'] = 'webroot';
$app['webroots'] = explode(',', $app['webroots']);

foreach ($app['webroots'] as $k => $v) {
    
    if (is_dir("{$appdir}/{$v}")) continue;

    if (is_dir("{$coredir}/app/{$v}")) {
        symlink("{$coredir}/app/{$v}", "{$appdir}/{$v}");
        continue;
    }//end if

    mkdir("{$appdir}/{$v}", 0775);
    
}// end foreach

$init_controller = '';
if (!empty($app['controller'])) 
    $init_controller = "const CONTROLLER    = '{$app['controller']}'; # controllers namespace";

$templates = array(
    'init'      => array(),
    'htaccess'  => array()
);
foreach ($app['webroots'] as $k => $v) {
    if ($k == 0) continue;

    $templates['init'][] = "Path::ViewDir('{$v}');";

    $templates['htaccess'][] = <<<EOF
# -- {$v} --
RewriteCond %{ENV:assets} =yes
RewriteCond {$appdir}/{$v}%{REQUEST_URI} -f
RewriteRule ^(.*) {$lookup_prefix}{$v}%{REQUEST_URI} [L]

EOF;
}// end foreach

$templates['init']      = implode("\n", $templates['init']);
$templates['htaccess']  = implode("\n\n", $templates['htaccess']);

if (!empty($templates['htaccess'])) {    
    $templates['htaccess'] = <<<EOF
# ========================================================
# insert additional folder assumptions here, before {$app['webroots'][0]} is assumed
{$templates['htaccess']}
# ========================================================
EOF;
}//end if

$apppath = "__DIR__";
if (!empty($lookup_prefix)) {
    $apppath = substr($lookup_prefix, 0, -1);
    $apppath = "__DIR__.'/{$apppath}'";
}//end if

$data = <<<EOF
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
 */

namespace Core\App;

use Core\Db;
use Core\View;
use Core\Controller;
use Core\ModelActions;

##############################################################
# do not touch this not unless you know what you are doin
##############################################################
define('Core\App\PATH', {$apppath});
define('Core\\App\\WEBROOT', PATH.'/{$app['webroots'][0]}');
##############################################################

const ENVIRONMENT   = '{$app['env']}';

//const TIMELIMIT     = 240;
//const TIMEZONE      = 'Asia/Manila';
{$init_controller}

require "$coredir/load.php";

/**
 * add secondary template lookup directory
 * Path::ViewDir('[folder name only]');
 */
{$templates['init']}

/**
 * clear whitespace on compiled twig templates
 */
define('TWIG_CLEAR_WHITESPACE', 1);

include 'lib/session_handler.php';

/**
 * for development purposes
 */
error_reporting(E_ALL ^ E_NOTICE);
Db::\$Debug             = false; // set to true to dump all sql statement to db.log
View::\$Debug           = true; // set to true to dump stuff to messages.log
Controller::\$Debug     = false; // set to true to echo echo to messages.log
ModelActions::\$Debug   = false; // set to true to dump debug to stuff on model_actions.log

Route::Request();

EOF;

$public_folder = (empty($lookup_prefix))? $appdir : $appdir_parent;

file_put_contents("{$public_folder}/init.php", $data);

/*******************************************************************************
 *
 * OLD MOD REWRITE
 *

RewriteEngine on

# if filename ends with php, htm or html redirect to init.php handler
RewriteCond %{REQUEST_FILENAME} (php|html?)$
RewriteRule ^(.*) init.php [L]

# ========================================================
# insert additional folder assumptions here, before webroot is assumed
# -- clientadmin demo --
#RewriteCond %{REQUEST_FILENAME} !-f
#RewriteCond %{REQUEST_URI} ^/clientadmin(.+\.(gif|jpe?g|png|css|js|pdf|doc|xml|txt|ico|swf|flv|cur|zip))$
#RewriteRule ^clientadmin/(.+) webpetalmanager/$1 [NC]

# -- clientsite demo --
#RewriteCond %{REQUEST_FILENAME} !-f
#RewriteCond %{REQUEST_URI} ^/clientsite(.+\.(gif|jpe?g|png|css|js|pdf|doc|xml|txt|ico|swf|flv|cur|zip))$
#RewriteRule ^clientsite/(.+) webpetalpublic/$1 [NC]

# -- webrants --
#RewriteCond %{REQUEST_FILENAME} !-f
#RewriteRule ^(.+) webrants/$1 [NC]

# -- webpetalmain --
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.+) webpetalmain/$1 [NC]
# ========================================================


# if file does not exists check if prefixed by [modules]
RewriteCond %{REQUEST_FILENAME} !-f
# check if uri pattern is modules/[name], if so assume it exists on modules/[name]/public folder
RewriteCond %{REQUEST_URI} ^/modules/([^/]+)/(.+\.(gif|jpe?g|png|css|js|pdf|doc|xml|txt|ico|swf|flv|cur|zip))$
RewriteRule ^(.*) modules/%1/public/%2 [NC]

# else check if exists on [webroot] folder
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_URI} !^/webroot
RewriteCond %{REQUEST_URI} (.+\.(gif|jpe?g|png|css|js|pdf|doc|xml|txt|ico|swf|flv|cur|zip))$
RewriteRule ^(.*)$ webroot%1 [NC]

# if still does not exists move up to one folder see if that works
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_URI} !^/webroot
RewriteCond %{REQUEST_URI} ^/([^/]+)/(.+\.(gif|jpe?g|png|css|js|pdf|doc|xml|txt|ico|swf|flv|cur|zip))$
RewriteRule ^(.*)$ webroot/%2 [NC]

#### debug ####
#RewriteCond %{REQUEST_FILENAME} -f
#RewriteRule ^(.*) debug.php?f=%{REQUEST_FILENAME}&u=%{REQUEST_URI}&rule=$1 [L]
###############

# fallback script
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*) init.php [L]

 *
 *
 * ANOTHER VERSION WTF???
 * 
 *

RewriteEngine On
RewriteBase /

# else check if exists on {$app['webroots'][0]} folder
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_URI} (.+\.(gif|jpe?g|png|css|js|pdf|doc|xml|txt|ico|swf|flv|cur|zip|htc?))$
RewriteRule ^(.*)$ {$lookup_prefix}{$app['webroots'][0]}%{REQUEST_URI} [NC]
RewriteCond %{REQUEST_FILENAME} -f
RewriteCond %{REQUEST_URI} (.+\.(gif|jpe?g|png|css|js|pdf|doc|xml|txt|ico|swf|flv|cur|zip|htc?))$
RewriteRule (.*) - [L]

# if file does not exists check if prefixed by [modules]
RewriteCond %{REQUEST_FILENAME} !-f
# check if uri pattern is modules/[name], if so assume it exists on modules/[name]/public folder
RewriteCond %{REQUEST_URI} ^/modules/([^/]+)/(.+\.(gif|jpe?g|png|css|js|pdf|doc|xml|txt|ico|swf|flv|cur|zip|htc?))$
RewriteRule ^(.*) {$lookup_prefix}modules/%1/public/%2 [NC]
RewriteCond %{REQUEST_FILENAME} -f
RewriteCond %{REQUEST_URI} (.+\.(gif|jpe?g|png|css|js|pdf|doc|xml|txt|ico|swf|flv|cur|zip|htc?))$
RewriteRule (.*) - [L]

# everything else redirect to init.php
RewriteCond %{REQUEST_FILENAME} !-f [OR]
RewriteCond %{REQUEST_URI} !\.(gif|jpe?g|png|css|js|pdf|doc|xml|txt|ico|swf|flv|cur|zip|htc?)$
RewriteRule ^(.*) init.php  [L]

*******************************************************************************/

$data = <<<EOF
Options FollowSymLinks
RewriteEngine On
RewriteBase /

RewriteCond %{REQUEST_URI} (.+\.(gif|jpe?g|png|css|js|pdf|doc|xml|txt|ico|swf|flv|cur|zip|htc?))$
RewriteRule (.*) - [E=assets:yes]

{$templates['htaccess']}

# else check if exists on {$app['webroots'][0]} folder
RewriteCond %{ENV:assets} =yes
RewriteCond {$appdir}/{$app['webroots'][0]}%{REQUEST_URI} -f
RewriteRule ^(.*) {$lookup_prefix}{$app['webroots'][0]}%{REQUEST_URI} [L]

# if file does not exists check if prefixed by [modules]
RewriteCond %{ENV:assets} =yes
RewriteCond %{REQUEST_URI} ^/modules/([^/]+)/(.+)$
RewriteCond {$appdir}/modules/%1/public/%2 -f
RewriteRule ^(.*) {$lookup_prefix}modules/%1/public/%2 [L]

RewriteCond %{ENV:assets} =yes
RewriteCond %{REQUEST_FILENAME} -f
RewriteRule ^(.*) - [L]

RewriteRule !init\.php - [C]
RewriteRule (.*) init.php [L]

EOF;

file_put_contents("{$public_folder}/.htaccess", $data);

$message = <<<EOF
********************************************************************************
Application path {$appdir}
Application environment {$app['env']}, {$coredir}/config/{$app['env']}.php
Also check that the apache has write access to {$coredir}/tmp folder
********************************************************************************

EOF;

if (!is_file("{$coredir}/config/{$app['env']}.php")) {
    $data = <<<EOF
<?php
return <<<CONFIG_JSON
{
    "db" : {
        "dsn"       : "mysql:dbname=[dbname];host=[host];charset=utf8",
        "usr"       : "[usr]",
        "pwd"       : "[password]",
        "schemas"   : {
        }
    }

}
CONFIG_JSON;

EOF;
    file_put_contents("{$coredir}/config/{$app['env']}.php", $data);

    $message = <<<EOF
********************************************************************************
Application path {$appdir}
Application environment {$app['env']}
Please configure you're new environment {$coredir}/config/{$app['env']}.php
Also check that the apache has write access to {$coredir}/tmp folder
********************************************************************************

EOF;
}//end if

echo $message;
