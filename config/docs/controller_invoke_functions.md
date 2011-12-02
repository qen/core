# Controller Invoke Functions

## Redirect
Redirects the page to the specified path

#### Syntax

    $this('redirect', path) ;

#### Arguments
- path - (_String_) the uri path to redirect

#### Example
    class Index extends \Core\Controller
    {

        public function doInitializeController() { }

        public function doFinalizeController() { }

        public function index() {
            $this('redirect', '/page');
        }

    }

## Session
Starts a session

#### Syntax

    $this('session'[, value]);

#### Arguments
- value - (_String_, optional)
    Boolen True: will start a session
    String: will start a named session
    Numeric: if session is started, session id will be regenerated
    Empty: if sessions is started, session id is returned

#### Returns
Returns Session id

#### Example
    class Index extends \Core\Controller
    {

        public function doInitializeController() {
            $this('session', true);
        }

        public function doFinalizeController() { }

        public function index() {
            echo $this('session');
        }

    }


## Render Content
Renders a content, usually used for serving up a file content or dynamic image resizes

#### Syntax

    $this('render_content', value[, options]);

#### Arguments
- value - (_String_) content to render
- options - (_Array_, optional) array with the ff  possible values:
    - attachement - (_String_) if non empty string is passed content will be downloaded using the string value as a filename
    - nocache - (_Boolean_, default false) if true content is cached in the browser
    - type - (_String_) valid mime type

#### Example
    class Index extends \Core\Controller
    {

        public function doInitializeController() {
        }

        public function doFinalizeController() {
        }

        public function index() {
            $filename = "/usr/local/foobar.jpg";
            $handle = fopen($filename, "r");
            $contents = fread($handle, filesize($filename));

            $this('render_content', $content, array('type' => 'image/jpeg'));
        }

    }


## Render Json
Renders a structured json string

#### Syntax

    $this('render_json', status, result, notice, response);

#### Arguments
- status - (_String_) usual value is success or failed
- result - (_Mixed_) content of the request
- notice - (_String_) additional message for the content, like error message whatsoever
- response - (_Mixed_) additionl content of the request

#### Example
    class Index extends \Core\Controller
    {

        public function doInitializeController() {
        }

        public function doFinalizeController() {
        }

        public function index() {
            $this('render_json', 'success', array(1, 2, 3), '', '');
        }

    }

## Render Text
Renders a pure text content

#### Syntax

    $this('render_text', value);

#### Arguments
- value - (_String_) string to render

#### Example
    class Index extends \Core\Controller
    {

        public function doInitializeController() {
        }

        public function doFinalizeController() {
        }

        public function index() {
            $this('render_text', 'foobar yey!');
        }

    }

## Http Status
Output a valid http status header,
if the value passed have a corresponding template ie. 400.html, then
the template will be rendered

#### Syntax

    $this('http_status', value);

#### Arguments
- value - (_Numeric_) the http status, ie: 400, 200, 500

#### Example
    class Index extends \Core\Controller
    {

        public function doInitializeController() {
        }

        public function doFinalizeController() {
        }

        public function index() {
            $this('http_status', 400);
        }

    }

## Http Modified
This will evaluate the header If-Modified-Since, if header is latest
then the code execution will stup and will output a 304 http status.

Useful for dynamically generated images

#### Syntax

    $this('http_modified', value[, cachelifetime]);

#### Example
    class Index extends \Core\Controller
    {

        public function doInitializeController() {
        }

        public function doFinalizeController() {
        }

        public function index() {
            $filename = "/usr/local/foobar.jpg";
            $handle = fopen($filename, "r");
            $contents = fread($handle, filesize($filename));

            $this('http_modified', filemtime($filename));

            $this('render_content', $content, array('type' => 'image/jpeg'));
        }

    }

## Set View Directory
Adds another template directory look up for the views (twig engine)

#### Syntax

    $this('set_view_dir', path);

#### Example
    class Index extends \Core\Controller
    {

        public function doInitializeController() {
            $this('set_view_dir', 'webrants');
        }

        public function doFinalizeController() {
        }

        public function index() {
        }

    }

#### Arguments
- path - (_String_) a valid folder path that exists on app directory

## Validate
Validates data from the passed array validatatiosn

#### Syntax

    $this('validate', validations, data);

#### Arguments
- validations - (_Array_) an array of validations, see validations
- data - (_Array_) array of data to validate

#### Validations
 - todo

#### Example
    class Index extends \Core\Controller
    {

        public function doInitializeController() {
        }

        public function doFinalizeController() {
        }

        public function index() {
            $this('validate', validations, data);
        }

    }