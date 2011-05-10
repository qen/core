#!/usr/bin/php
<?php
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
echo <<<EOF
appdir = {$appdir}
coredir = {$coredir}

EOF;

if ($appdir_parent == $coredir_parent) 
    $lookup_prefix = str_replace($appdir_parent.'/', '', $appdir).'/';

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
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.+) {$lookup_prefix}{$v}/$1 [NC]
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

namespace Core\App;

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
\Core\Db::\$Debug = false; // set to true to dump all sql statement in messages.log

Route::Request();

EOF;

$public_folder = (empty($lookup_prefix))? $appdir:  $appdir_parent;

file_put_contents("{$public_folder}/init.php", $data);


$data = <<<EOF
RewriteEngine on

# if request didn't end with file extension gif|jpe?g|png|css|js|pdf|doc|xml|txt|ico|swf|flv|cur|zip|htc, redirect to init.php handler
RewriteRule !\.(gif|jpe?g|png|css|js|pdf|doc|xml|txt|ico|swf|flv|cur|zip|htc?)$ init.php [L]

{$templates['htaccess']}

# if file does not exists check if prefixed by [modules]
RewriteCond %{REQUEST_FILENAME} !-f
# check if uri pattern is modules/[name], if so assume it exists on modules/[name]/public folder
RewriteCond %{REQUEST_URI} ^/modules/([^/]+)/(.+\.(gif|jpe?g|png|css|js|pdf|doc|xml|txt|ico|swf|flv|cur|zip|htc))$
RewriteRule ^(.*) {$lookup_prefix}modules/%1/public/%2 [NC]

# else check if exists on [{$app['webroots'][0]}] folder
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_URI} !^/webroot
RewriteCond %{REQUEST_URI} (.+\.(gif|jpe?g|png|css|js|pdf|doc|xml|txt|ico|swf|flv|cur|zip|htc))$
RewriteRule ^(.*)$ {$lookup_prefix}{$app['webroots'][0]}%1 [NC]

# if still does not exists move up to one folder see if that works
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_URI} !^/webroot
RewriteCond %{REQUEST_URI} ^/([^/]+)/(.+\.(gif|jpe?g|png|css|js|pdf|doc|xml|txt|ico|swf|flv|cur|zip|htc))$
RewriteRule ^(.*)$ {$lookup_prefix}{$app['webroots'][0]}/%2 [NC]

# fallback script
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^(.*) init.php [L]

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
        "dsn"       : "mysql:dbname=[dbname];host=[host]",
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
