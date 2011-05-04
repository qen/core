#!/usr/bin/php
<?php

/**
 * try to locate app directory
 */
$pwd = getcwd();
$appdir = realpath("{$pwd}/../app");

if (!is_dir($appdir)) 
    $appdir = "{$pwd}/app";

if (!is_dir($appdir)) {
    echo "Failed to locate app directory!";
    exit;
}

switch (@$argv[1]) {
    case 'module':
        $path = "{$appdir}/modules";
        if (!is_dir($path)) {
            echo "\nFailed to locate app/modules directory in:\n{$appdir}\n";
            exit;
        }//end if
        
        array_shift($argv);
        include('create/module.php');
        break;

    case 'controller':
        $path = "{$appdir}/controllers";
        if (!is_dir($path)) {
            echo "\nFailed to locate app/controllers directory in:\n{$appdir}\n";
            exit;
        }//end if
        
        array_shift($argv);
        include('create/controller.php');
        break;
    
    default:
echo <<<EOF

Example Usages

Generate Module:
    script/generate.php module [namespace\\class]
    script/generate.php module Transaction
    script/generate.php module Transaction\\\Items

Generate Controller:
    script/generate.php controller [uri] [extends]
    script/generate.php controller appmanager/products
    script/generate.php controller appmanager/products AppManager\\\Test
    
    script/generate.php controller [namespace\\\class]
    script/generate.php controller AppManager\\\Test
    
EOF;
        break;
}// end switch

echo "\n\n";
