#!/bin/bash


#script_dirname=`dirname $0`;
# get where the core directory resides
core_path="$( readlink -f "$( dirname "$0" )/" )";
core_parent_path="$( readlink -f "$( dirname "$0" )/../" )";

uri='';
path='app';
help=0;
for p in $*;
    do
      [[ $p =~ ^--path=(.*) ]] && path="${BASH_REMATCH[1]}";
      [[ $p =~ ^/(.*) ]] && uri=$p;
      [[ $p =~ ^--help ]] && help=1;
    done;

if [ $help -eq 1 ]
then
  cat <<EOF
Generate Controller:
  core/controller.sh [--path=<application path>] [uri]

Example
  core/controller.sh /products/details
  core/controller.sh --path='/var/sites/app' /products/lists

EOF
  exit;
fi

if [ -z $uri ]
then
  echo "$uri is required and must start with a /"
  exit
fi

path="$( readlink -f "$( basename "$path" )" )";
pathname=`basename $path`;
controller_path="$path/controllers";

# array uri
classname=(`echo $uri | tr '[A-Z]' '[a-z]' | sed -e 's/\/./\U&\E/g' | tr "/" " " `)
classname=$(printf "%s" "${classname[@]}")
arrayuri=(`echo $uri | tr '[A-Z]' '[a-z]' | tr "/" " "`)

filename=$(printf "_%s" "${arrayuri[@]}")
filenamepath="$controller_path/${filename:1}.php"

if [ -f $filenamepath ]
then
  echo -e "\n* $filenamepath already exists\n"
  exit
fi

cat > $filenamepath <<EOF
<?php
namespace Core\App\Controllers;

use Core\Controller;
use Core\Exception;
use Core\App\Module;
use Core\Tools;

class $classname extends Controller
{

    /**
     * assign a variable to template
     * render('foo', 'bar');
     * \$render = render();
     * \$render['foo'] = 'bar';
     *
     * assign view function
     * render('foo()', function(){});
     * assign view filter
     * render('foo|', function(){});
     * assign test filter
     * render('foo?', function(){});
     *
     * getting post/get variable
     * params()->post['foo'];
     * params()->get['foo'];
     *
     * get, assign cookie variable
     * params()->cookie['foo'];
     * params()->cookie['foo'] = 'bar';
     * params()->cookie['foo'] = null; // expire cookie
     * params()->cookies(array('expire' => '+1 day', 'path' => '/')); // setup cookie settings
     *
     * session start
     * params()->session(); // start session
     * params()->session('mysession'); // start named session
     * 
     * session vars
     * echo params()->session['foo'];
     * params()->session['foo'] = 'bar'; // assign a session var 'foo' with value 'bar'
     * params()->flash['foo'] = 'bar'; // assign a flash session var 'foo' with value 'bar'
     *
     * render template
     * render()->template = 'template'; // assign template
     * render()->default = 'default_template'; // assign default template
     * render content
     * render()->inline = 'hello world'; // output string, lambda function can also be passed
     * render()->json = array('[status]', '[result]', '[notice]', '[response]'); // output json, lambda function can also be passed
     * render()->file = 'path to file';// raw content a.k.a. file, lambda function can also be passed
     * render()->content_type = 'mime here'; // default is text/html
     * render()->nocache = true;
     * render()->attachment = 'filnamehere.txt';
     * render()->last_modified = <timestamp>; // send if modified header
     * render()->status = 200;
     *
     * params validation, sanitization
     * params(\$array)->validate(function(\$validate){});
     * \$get_foo = params('get.foo')->sanitize(function(\$sanitize){});
     *
     * redirects and http status:
     * redirect('path');
     * redirect('path', true); // sends 301 Moved Permanently
     * redirect('path', array('foo' => 'bar'), false); // redirect with get parameters
     *
     */

    protected function __construct()
    {
        
    }

    protected function __destruct()
    {
        
    }

    public function index()
    {
      // static \$greedy;                 // method is greedy
      // static \$method;                 // if function returned is controller, this variable will route to a method, by default it's index
      // static \$reserved;               // public method should not be directly available through request
      // static \$template;               // assign default template without calling render()->template
      // static \$constraints;            // an array of regular expression string that would be check against parameters passed
    }

}
EOF

cat <<EOF

Controller Class $classname Created
In $filenamepath
EOF