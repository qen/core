<?php
/**
 * script/module.php Transaction\\Items
 *
 */
list($moduledir, $klass) = explode('\\', $argv[1]);
if (empty($klass)) $klass = 'base';

$moduledir  = ucfirst($moduledir);
$klass      = ucfirst($klass);

preg_match_all('/([A-Z][a-z0-9]+)/', $moduledir, $matches);
$moduledirpath = $path.'/'.strtolower(implode("_", $matches[1])).'/';

if (!is_dir($moduledirpath))
    mkdir($moduledirpath);

preg_match_all('/([A-Z][a-z0-9]+)/', $klass, $matches);
$name       = strtolower(implode("_", $matches[1]));
$klassfile  = $moduledirpath.$name.'.php';

if (is_file($klassfile)) {
    echo "\n{$klassfile} already exists\n\n";
    exit;
}//end if

$dump   = explode("_", $name);
$extend = "\Core\Model";
if (count($dump) > 1)
    $extend = ucfirst($dump[0]);

$data   = <<<EOF
<?php
namespace Core\App\Modules\\$moduledir;
use \Core\App\Module;
use \Core\Tools;
use \Core\Debug;
use \Core\Exception;

class $klass extends $extend
{
    public static \$Name            = '$klass';
    public static \$Table_Name      = '';
    public static \$Find_Options    = array();
    public static \$Sanitize        = array(
    );

    protected function setup()
    {
        \$self = \$this;
    }

    public function hasAssociations()
    {
        \$self = \$this;
    }
    
    public function validate()
    {
        \$check = array();
        \$check['required'] = array(
        );

        \$this->doValidations(\$check);

    }

}
EOF;

file_put_contents($klassfile, $data);

echo <<<EOF

Module Created!
Core\App\Modules\\$moduledir\\$klass

$klassfile


EOF;
