<?php
/**
 * script/controller.php AppManager\\Test
 * script/controller.php appmanager/products AppManager\\Test
 */

$file   = '';
$klass  = '';
$subns  = '';
$use    = '';
$extend = "\Core\Controller";

// check if upper case
if ($argv[1]{0} == ucfirst($argv[1]{0})) {
    
    list($subns, $klass) = explode('\\', $argv[1]);
    
    preg_match_all('/([A-Z][a-z0-9]+)/', $subns, $matches);
    $path .= '/'.strtolower(implode("_", $matches[1])).'/';

    preg_match_all('/([A-Z][a-z0-9]+)/', $klass, $matches);
    $file = $path.strtolower(implode("_", $matches[1])).'.php';

    $subns  = "\\".$subns;

// if not then its uri
}else{
    $uri    = $argv[1];
    if (preg_match('|^/|', $uri))
        $uri = substr($uri, 1);

    if (preg_match('|/$|', $uri))
        $uri = substr($uri, 0, -1);

    $dump   = explode("/", $uri);
    $file   = $path.'/'.implode("_", $dump).'.php';

    foreach ($dump as $k => $v)
        $klass .= ucfirst($v);

}//end if

$extend = (!empty($argv[2]))? $argv[2] : $extend;

if (is_file($file)) {
    echo "\n{$file} already exists\n\n";
    exit;
}//end if

$data = <<<EOF
<?php
namespace Core\App\Controllers$subns;

use Core\Debug;
use Core\Exception;
use Core\Tools;
use Core\App\Module;

class $klass extends $extend
{

    /**
     * assign a variable to template
     * \$this['foo'] = 'bar';
     * getting post/get variable
     * echo \$this['foo']; // note that the return value is not 'bar'
     *
     * session start
     * \$this('session', true); // start session
     * \$this('session', 'mysession'); // start named session
     * echo \$this('session'); // get sessionid
     * session vars
     * \$this['@session.foo'] = 'bar'; // assign a session var 'foo' with value 'bar'
     * echo \$this['@session.foo']; // get a session var 'foo' with value 'bar'
     * \$this['@flash.foo'] = 'bar'; // assign a flash session var 'foo' with value 'bar'
     * \$this['@notify.foo'] = 'bar'; // assign a flash notify session var 'foo' with value 'bar'
     *
     * render template
     * \$this['@template.render'] = 'template'; // assign template
     * \$this['@template.default'] = 'default_template'; // assign default template
     * render functions
     * \$this('render_text', 'hello world'); // output string
     * \$this('render_json', array('[status]', '[result]', '[notice]', '[response]')); // output json
     * \$this('render_content', '[raw content]', '[content type]'); // raw content a.k.a. file
     * \$this('render_content', '[raw content]', array('attachment' => '[filenmae]', 'nocache' => '[bool]', 'type' => '[content _type]'));
     *
     * redirects and http status:
     * \$this('redirect', 'path');
     * \$this('http_status', 200); // send http status 200
     * \$this('http_modified', strtotime('now'), '+24 days'); // send http modified
     *
     */

    public function doInitializeController()
    {

    }
    
    public function doFinalizeController()
    {
        // \$this('dump');
    }

    /**
     * @param :deny_file_extensions
     */
    public function index()
    {

    }

}
EOF;

file_put_contents($file, $data);

echo <<<EOF

Controller Created!
Core\App\Controllers\\$klass

$file


EOF;
