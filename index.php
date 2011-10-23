<?php
/**
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program.  If not, see
 * <http://www.gnu.org/licenses/>.
 *
 *
 * @license GNU Lesser General Public License
 * @author Jacques Bodin-Hullin <jacques (at) bodin-hullin (dot) net>
 * @package Console
 * @version 1.0
 *
 * @github http://github.com/jacquesbh/console
 */


/**
 * Console Exception
 *
 * @class Console_Exception
 */
class Console_Exception extends Exception
{}


/**
 * Console program
 *
 * @class Console
 */
class Console
{

    /**
     * Session namespace
     *
     * @const string
     */
    const SESSION_NAMESPACE = 'Console';

    protected $_session;

    /**
     * Console prompt
     *
     * <ul>
     * <li>First is username</li>
     * <li>Second is hostname</li>
     * <li>Third is working directory</li>
     * </ul>
     *
     * @access protected
     * @var string
     */
    protected $_prompt = '<span class="green">%1$s@%2$s</span> <span class="pwd blue">%3$s</span><br/><span class="blue">&gt;&gt; </span>';

    /**
     * HTTP Auth Active
     *
     * @access protected
     * @var bool
     */
    protected $_httpAuthActive = true;

    /**
     * HTTP Auth Name
     *
     * @access protected
     * @var string
     */
    protected $_httpAuthRealm = 'Restricted area';

    /**
     * HTTP Auth Cancel message
     *
     * @access protected
     * @var string
     */
    protected $_httpAuthCancel = 'Access denied';

    /**
     * List of PATH used for binary execution
     *
     * @access protected
     * @var array
     */
    protected $_paths = array(
        '$PATH'
    );

    /**
     * Home directory used for this instance
     *
     * @access protected
     * @var string
     */
    protected $_home = '/';

    /**
     * Home directory Replacement
     *
     * @access protected
     * @var string
     */
    protected $_homeReplacement = '~/';

    /**
     * Constructor
     *
     * @access public
     * @return void
     */
    public function __construct()
    {
        $this->startSession();
    }

    /**
     * Destructor
     *
     * @access public
     * @return void
     */
    public function __destruct()
    {
        $this->setSession('currentWorkingPwd', null);
        $this->writeSession();
    }

    /**
     * Dispatch/Start Console
     *
     * @return void
     */
    public function dispatch()
    {
        //$this->httpAuth(); // TODO

        // Execute command line
        $result = array();
        if (false !== $command = $this->_handleData($result)) {
            echo json_encode(array(
                'command' => '<div class="clear">' . $this->getPrompt() . htmlspecialchars($command) . '</div>',
                'result' => $result,
                'pwd' => $this->getWorkingDirectory(true)
            ));
            exit;
        }

        // Init
        $this->setSession('pwd', $this->getPwd());

        // Dispatch Console
        return;
    }

    /**
     * Handle data
     *
     * @param array $result
     * @access protected
     * @return mixed
     */
    protected function _handleData(array &$result)
    {
        if (null !== $command = $this->getParam('command')) {
            $result = $this->_executeCommandLine($command);
            return $command;
        }
        return false;
    }

    /**
     * Execute command line
     *
     * @param string $command
     * @access protected
     * @return mixed
     */
    protected function _executeCommandLine($command)
    {
        if ($this->getSession('pwd')) {

            // Set PATH
            putenv('PATH=' . implode(':', $this->getPaths()));

            // Set TERM
            putenv('TERM=xterm-256color');

            // Set CLICOLOR_FORCE
            putenv('CLICOLOR_FORCE=1');

            // Change dir
            $this->changeDir($this->getSession('pwd'));

            // Set current directory for prompt
            $this->setSession('currentWorkingPwd', $this->getPwd());

            // Separate commands
            $commands = array_map('trim', explode(';', $originalCommand = $command));

            $resultText = '';

            foreach ($commands as $command) {
                $result = array();

                // CHANGE DIRECTORY PROXY
                if (substr($command, 0, 2) == 'cd') {
                    $dir = substr($command, 3);
                    if ($dir) {
                        if ($dir == '-') {
                            if ($this->getSession('oldpwd')) {
                                $old = $this->getPwd();
                                $this->changeDir($this->getSession('oldpwd'));
                                $this->setSession('oldpwd', $old);
                                unset($old);
                            }
                        } elseif (substr($dir, 0, 1) != '~') {
                            $this->setSession('oldpwd', $this->getPwd());
                            $this->changeDir($dir);
                        }
                    }
                    if (!$dir || substr($dir, 0, 1) == '~') {
                        $this->setSession('oldpwd', $this->getPwd());
                        $this->changeDir($this->_home);
                    }
                    $this->setSession('pwd', $this->getPwd());
                }

                // OTHER COMMAND
                else {
                    $this->exec($command . ' 2>&1', $result);
                }
                $resultText .= $this->parseCommandExecutionResult($result);
            }
            return $resultText;
        }

        return false;
    }

    /**
     * Change PWD directory
     *
     * @param type $directory
     * @return bool Success or failure
     */
    public function changeDir($directory)
    {
        return chdir($directory);
    }

    /**
     * Returns Pwd
     *
     * @access public
     * @return string
     */
    public function getPwd()
    {
        return getcwd();
    }

    /**
     * Change Http Auth active flag
     *
     * @param bool $flag
     * @access public
     * @return Console
     */
    public function activeHttpAuth($flag = true)
    {
        $this->_httpAuthActive = (bool) $flag;
        return $this;
    }

    /**
     * HTTP Authentication
     *
     * @access public
     * @return void
     */
    //public function httpAuth()
    private function httpAuth()
    {
        // TODO Finish this method and change to public
        if ($this->_httpAuthActive) {
            if (empty($_SERVER['PHP_AUTH_DIGEST'])) {
                header('HTTP/1.1 401 Unauthorized');
                header('WWW-Authenticate: Digest realm="'
                    . $this->_httpAuthRealm
                    . '",qop="auth",nonce="'
                    . uniqid()
                    . '",opaque="'
                    . md5($this->_httpAuthRealm)
                    . '"');
                die($this->_httpAuthCancel);
            }
        }
    }

    /**
     * Start PHP session
     *
     * @access public
     * @return Console
     */
    public function startSession()
    {
        if (!session_id()) {
            session_start();
        }
        return $this;
    }

    /**
     * Write Session
     *
     * @access public
     * @return Console
     */
    public function writeSession()
    {
        $_SESSION[self::SESSION_NAMESPACE] = $this->getSession();
        return $this;
    }

    /**
     * Return the Console session
     *
     * <p>Return reference</p>
     *
     * @return array
     */
    public function &getSession($name = null)
    {
        if (is_null($this->_session)) {
            if (!isset($_SESSION[self::SESSION_NAMESPACE])) {
                $_SESSION[self::SESSION_NAMESPACE] = array();
            }
            $this->_session = $_SESSION[self::SESSION_NAMESPACE];
        }
        if (!is_null($name)) {
            if (array_key_exists($name, $this->_session)) {
                return $this->_session[$name];
            }
            $null = null;
            return $null;
        }
        return $this->_session;
    }

    /**
     * Sets a SESSION variable for Console
     *
     * @param string $name
     * @param mixed $value
     * @return Console
     */
    public function setSession($name, $value)
    {
        $session =& $this->getSession();
        $session[$name] = $value;
        return $this;
    }

    /**
     * Parse the command line execution result
     *
     * <p>This method parse the result of command line execution for standard HTML output.<br/>
     * It replace most of colors with a 'span' with a specific css class.</p>
     *
     * @param array $result Result of exec() (or other)
     * @access public
     * @return string
     */
    public function parseCommandExecutionResult(array $result)
    {
        // Get a simple string without HTML
        $result = htmlspecialchars(implode("\n", $result));

//        echo $result;
//        echo "\n\n";

        // Spaces
        $result = str_replace(' ', '&nbsp;', $result);

        // Colors replacement with the 'span' tag
        $result = preg_replace('`\[(?:([0-9]{1,})\;)?([1-9]{1}[0-9]{0,})m`isU', '<span class="color-$2">', $result);
        $result = preg_replace('`\[0?m`isU', '</span>', $result);
        $result = preg_replace('`(<span(?:.*)>(?:[^/<]+)?)*(<span)`isU', '$1</span>$2', $result);

        // Returns the result with 'br' tags
        return nl2br($result);
    }

    /**
     * Return the Console prompt
     *
     * @access public
     * @return string
     */
    public function getPrompt()
    {
        /**
         * Args list :
         * 1: Username
         * 2: Hostname
         * 3: Working directory
         */

        $pwd = $this->getSession('currentWorkingPwd');
        if (!$pwd) {
            $pwd = $this->getWorkingDirectory(true);
        } elseif ($pwd == $this->_home) {
            $pwd = $this->_homeReplacement;
        }

        return sprintf(
            $this->_prompt,
            $this->getUsername(),
            $this->getHostname(),
            $pwd
        );
    }

    /**
     * Return current Username
     *
     * @access public
     * @return string
     */
    public function getUsername()
    {
        return 'console';
    }

    /**
     * Return current Hostname
     *
     * @access public
     * @return string
     */
    public function getHostname()
    {
        return $_SERVER['SERVER_ADDR'];
    }

    /**
     * Return working directory as string
     *
     * @access public
     * @return string
     */
    public function getWorkingDirectory($replaceHome = false)
    {
        $cwd = $this->getPwd();
        if ($replaceHome) {
            if ($cwd == $this->_home) {
                $cwd = $this->_homeReplacement;
            }
        }
        return $cwd;
    }

    /**
     * Return authorized commands list
     *
     * @access public
     * @return array
     */
    public function getAuthorizedCommands()
    {
        return array_map('trim', explode(' ', self::AUTHORIZED_COMMANDS));
    }

    /**
     * Return result for command line execution
     *
     * @param string $commandLine
     * @param &array $result
     * @access public
     * @return string
     */
    public function exec($commandLine, &$result)
    {
        return exec($commandLine, $result);
    }

    /**
     * Change the home directory
     *
     * @param string $home
     * @return Console
     */
    public function setHome($home)
    {
        $this->_home = (string) $home;
        return $this;
    }

    /**
     * Add PATH for binary execution use
     *
     * @param string $path
     * @access public
     * @return Console
     */
    public function addPath($path)
    {
        if (!in_array($path, $this->_paths)) {
            $this->_paths[] = $path;
        }
        return $this;
    }

    /**
     * Set all PATH used for binary execution
     *
     * @param array $paths
     * @access public
     * @return Console
     */
    public function setPaths(array $paths)
    {
        $this->_paths = $paths;
        return $this;
    }

    /**
     * Return All PATH used for binary execution
     *
     * @access public
     * @return array
     */
    public function getPaths()
    {
        if (false !== $key = array_search('$PATH', $this->_paths)) {
            unset($this->_paths[$key]);
            $paths = explode(':', getenv('PATH'));
            foreach ($paths as $path) {
                $this->addPath($path);
            }
        }
        return $this->_paths;
    }

    /**
     * Return POST or GET parameter with $name as key.
     *
     * @param type $name
     * @access public
     * @return mixed
     */
    public function getParam($name)
    {
        if (array_key_exists($name, $_POST)) {
            return $_POST[$name];
        } elseif (array_key_exists($name, $_GET)) {
            return $_GET[$name];
        }
        return null;
    }
}

$console = new Console;

$console
    ->setHome($_SERVER['DOCUMENT_ROOT'])
    ->setPaths(array('$PATH', '/opt/local/bin'))
    ->dispatch();

// text/html header
header('Content-Type: text/html; charset=utf-8;');
?>
<!DOCTYPE html>
<html>
<head>
    <title>Console</title>
    <style type="text/css">
    body {
        background: #333;
        color: #fff;
        padding: 10px;
        margin: 0;
        font-size: 11px;
        font-family: monospace;
        line-height: 13px;
    }
    #console {
        margin: 0;
    }
    form {
        width: 100%;
    }
    input {
        width: 400px;
        background: #333;
        color: white;
        border: none;
        outline: none;
    }
    .clear { clear: both; }
    .color-31, .red { color: #f22; } /* red */
    .color-32, .green { color: #2da814; } /* green */
    .color-33 { color: orange; }
    .color-34, .blue { color: #5542f2; } /* blue */
    .color-35 { color: #ff3; } /* yellow */
    .color-36 { color: violet; }
    .color-37 { color: #cecece; } /* grey */
    .color-43 { color: black; background-color: #ff3; }
    .login { color: #2da814; }
    </style>

    <!-- jQuery -->
    <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.6.4/jquery.min.js"></script>
</head>
<body>

<div id="console"></div>

<form id="prompt" action="./index.php" method="post" autocomplete="off">
    <?php echo $console->getPrompt(); ?>
    <input type="text" name="command" id="command" />
</form>

<script type="text/javascript">
// <![CDATA[
(function ($) {

    var $doc = $(document);

    $doc.ready(function () {

        $('#command').focus();

        var keyCodeCtrl     = 17;
        var keyCodeL        = 76;
        var keyCodeTop      = 38;
        var keyCodeBottom   = 40;

        var keys = [];

        var history = new Array();
        var historyCurrent = 0;

        var $body    = $('#body');
        var $console = $('#console');
        var $command = $('#command');
        var $prompt  = $('#prompt');
    
        $prompt.submit(function () {


            var data = $(this).serialize();
            history.push($command.val());
            $command.prop('disabled', 'disabled').val('');

            loaderStart();

            $.ajax({
                type: 'post',
                data: data,
                url: $command.attr('action'),
                dataType: 'json',
                success: function (data) {
                    $console.append(data['command']);
                    $console.append(data['result']);
                    $('html, body').animate({
                         scrollTop: $(document).height()
                     },
                     1000);
                    $command.prop('disabled', '').val('');
                    historyCurrent = 0;
                    $prompt.find('.pwd').html(data.pwd);
                    loaderEnd();
                }
            });

            return false;
        }).keydown(function (e) {
            keys[e.keyCode] = e.keyCode;

            // Catch Top & Bottom
            if (e.keyCode == keyCodeTop) {
                historyCurrent = historyCurrent + 1;
                if (historyCurrent > history.length) {
                    historyCurrent = history.length;
                }
            } else if (e.keyCode == keyCodeBottom) {
                if (historyCurrent > 0) {
                    historyCurrent = historyCurrent - 1;
                }
            }
            if (e.keyCode == keyCodeTop || e.keyCode == keyCodeBottom) {
                if (historyCurrent == 0) {
                    history.push($command.val());
                }
                var newCmd = history[history.length - historyCurrent];
                $command.val(newCmd);
                if (newCmd) {
                    $command.prop('selectionEnd', newCmd.length-1);
                }
            }

            // Catch <Ctrl-L>
            if (e.keyCode == keyCodeL) {
                if (keys[keyCodeCtrl] && keys[keyCodeCtrl] == keyCodeCtrl) {
                    // Clean console
                    $console.html('');
                }
            }

        }).keyup(function (e) {
            keys[e.keyCode] = null;
        });


        // Move cursor to prompt
        jQuery('html').bind('click', function () {
            $command.focus();
        });
    });
})(jQuery);

var loader = null;

function loaderStart()
{
    loader = setInterval(function () {
        var v = jQuery('#command').val();
        if (v == '/') {
            v = '-';
        } else if (v == '-') {
            v = '\\';
        } else if (v == '\\') {
            v = '|';
        } else {
            v = '/';
        }
        jQuery('#command').val(v);
    }, 100);
}

function loaderEnd()
{
    clearInterval(loader);
    jQuery('#command').val('');
}

// ]]>
</script>

</body>
</html>
