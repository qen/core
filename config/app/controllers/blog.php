<?php
namespace Core\App\Controllers;

use Core\Controller;
use Core\Exception;
use Core\App\Module;
use Core\Tools;

class Blog extends Controller
{

    /**
     * assign a variable to template
     * render('foo', 'bar');
     * $render = render();
     * $render['foo'] = 'bar';
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
     * params($array)->validate(function($validate){});
     * $get_foo = params('get.foo')->sanitize(function($sanitize){});
     *
     * redirects and http status:
     * redirect('path');
     * redirect('path', true); // sends 301 Moved Permanently
     * redirect('path', array('foo' => 'bar'), false); // redirect with get parameters
     *
     */

    public $post;
    public $page;

    public function __construct()
    {
        $quotes = array(
            '"I would rather fail in a cause that will ultimately triumph than to triumph in a cause that will ultimately fail." - Wilson, Woodrow',
            '"The man who makes no mistakes does not usually make anything." - Edward Phelps',
            '"By three methods we may learn wisdom: First, by reflection, which is noblest; Second, by imitation, which is easiest; and third by experience, which is the bitterest." - Confucius',
            'If all you have is a hammer, every problem starts to look like a nail!',
            '"Simplicity" (meaning lack of features, or having hidden the features) and "Ease of Use" are not to be confused.',
            'When the people fear their government, there is tyranny; when the government fears the people, there is liberty.',
            'What you are is mainly determined by the situation you\'re in',
            'One rule of suits: Demand the moon, negotiate for the sky, but be willing to accept the ground.',
            '"First they ignore us. Then they laugh at us. Then they fight us. Then we win." - Ghandi',
            '"The whole of Science is nothing but the refinement of everyday thinking." - Albert Einstien',
            'To follow the path: look to the master, follow the master, walk with the master, see through the master, become the master."',
            'Downloaders! Tonight we Bittorent in Hell!',
            'True knowledge exists in knowing that you know nothing',
            'Efficiency means that the quality should be greater than the cost',
            'Who we are is who we were',
            'Must all be done by female coders as we all know men can\'t commit.',
            '"Americans can always be counted on to do the right thing...after they have exhausted all other possibilities." - Winston Churchill',
            '"We should forget about small efficiencies, say about 97% of the time: premature optimization is the root of all evil" - Donald Knuth',
            '"Success is going from failure to failure with no loss of enthusiasm." - Winston Churchill',
            'If you want to make God laugh, tell him your plans',
            'The greatest trick that the devil ever pulled was convincing the world that he doesn\'t exists',
            'It\'s not just about being first. It\'s also about timing and execution.',
        );

        $render = render();

        $render['quotes()'] = function() use ($quotes){
            $count = count($quotes) - 1;
            $index = rand(0, $count);
            return $quotes[$index];
        };

        require 'markdown/markdown.php';
        
        $render['markdown|'] = function($text){
            return Markdown($text);
        };
        
    }

    public function __destruct()
    {
        
    }

    public function index()
    {

        $Posts = Module::Blog('Post');
        try {
            $Posts->find('*', array(
                'search'        => '%blog_title',
                'limit'         => 5,
                'selpage'       => params()->get['p']
            ));
            render('blogs', $Posts);
        } catch (Exception $exc) {
        }//end try

    }

    /**
     *
     * @access
     * @var
     */
    public function post($year, $month, $day, $text)
    {
        static $template = '/post.html';
        static $constraints = array('|^\d+|', '|^\d+|', '|^\d+|', '|^.+|');
        
        if (empty($this->post)) render()->status = 404;

        render('blog', $this->post);
    }

    /**
     *
     * @access
     * @var
     */
    public function page()
    {
        var_dump($this->page);exit;
        if (empty($this->page)) render()->status = 404;
        
        $controller = new PageHandler;
        $controller->module = $this->page;
        return $controller;
    }

    /**
     *
     * @access
     * @var
     */
    public function tag($name)
    {
        static $template = '/index.html';

        $Blogs = Module::Blog('Post');
        $Blogs->Tags = true;
        try {
            $Blogs->Tags->object()->find($name, array( 'search' => 'tag_name' ));
        } catch (Exception $exc) {
            render()->status = 404;
        }//end try
        


        try {
            $Blogs->find('*', array(
                'search'        => '%blog_title',
                'limit'         => 5,
                'selpage'       => params()->get['p']
            ));
        } catch (Exception $exc) {
            render()->status = 404;
        }//end try

        //var_dump('todo fix error post not filtered by tag queried');exit;

        $render = render();

        $render['cur_tag'] = $name;
        $render['blogs']   = $Blogs;
        $render['numpage'] = $Blogs->numpage;

        $render->template = '/index.html';
    }

}
