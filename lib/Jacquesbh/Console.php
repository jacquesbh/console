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
 * @author Jacques Bodin-Hullin <jacques@bodin-hullin.net>
 * @package Console
 * @version 1.1
 *
 * @github http://github.com/jacquesbh/console
 */

/**
 * Namespace
 */
namespace Jacquesbh;

/**
 * Console program
 *
 * @class Console
 * @namespace Jacquesbh
 */
class Console
{

    /**
     * Version
     *
     * @const string
     */
    const VERSION = '1.1';

    /**
     * Http Authentication constants
     */
    const HTTP_AUTH_FORCE = 1;
    const HTTP_AUTH_REALM = "Restricted area";
    const HTTP_AUTH_MESSAGE_CANCEL = "Access denied";
    const HTTP_AUTH_MESSAGE_BAD_USERNAME = "Access denied";

    /**
     * Session namespace
     *
     * @const string
     */
    const SESSION_NAMESPACE = 'Jacquesbh\Console';

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
    protected $_prompt = '%1$s@%2$s <span class="pwd">%3$s</span> $ ';

    /**
     * HTTP Auth Active
     *
     * @access protected
     * @var bool
     */
    protected $_httpAuthActive = true;

    /**
     * List of PATH used for binary execution
     *
     * @access protected
     * @var array
     */
    protected $_paths = ['$PATH'];

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
     * HTTP Authentication users
     * @access protected
     * @var array
     */
    protected $_users = [];

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
        $this->httpAuth();

        // Execute command line
        $result = [];
        if (false !== $command = $this->_handleData($result)) {
            echo json_encode([
                'command'   => '<div class="clear">' . $this->getPrompt() . htmlspecialchars($command) . '</div>',
                'result'    => $result,
                'pwd'       => $this->getWorkingDirectory(true)
            ]);
            exit;
        }

        // Init
        $this->setSession('pwd', $this->getPwd());

        // Dispatch Console
        return;
    }

    /**
     * Returns HTML form
     *
     * @param string $action Action form
     * @access public
     * @return string
     */
    public function getFormHtml($action)
    {
        $searchAndReplace = [
            '{{ACTION}}'    => urlencode($action),
            '{{PROMPT}}'    => $this->getPrompt()
        ];

        // Get content in this file
        $fp = fopen(__FILE__, 'r');
        fseek($fp, __COMPILER_HALT_OFFSET__);
        $content = stream_get_contents($fp);
        fclose($fp);

        unset($fp);

        // Get form only
        $content = preg_replace('`^\?>\s+<\!-- CONSOLE FORM -->`', '', trim($content));

        // Returs content with variables replacement
        return str_replace(
            array_keys($searchAndReplace),
            array_values($searchAndReplace),
            $content
        );
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
                $result = [];

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
     * Add a Http Auth user
     *
     * @param string $username
     * @param string $password
     * @access public
     * @return Console
     */
    public function addUser($username, $password)
    {
        $this->_users[$username] = $password;
        return $this;
    }

    /**
     * Remove a Http Auth user
     * @param string $username
     * @access public
     * @return Console
     */
    public function removeUser($username)
    {
        if (isset($this->_users[$username])) {
            unset($this->_users[$username]);
        }
        return $this;
    }

    /**
     * HTTP Authentication
     *
     * @access public
     * @return void
     */
    public function httpAuth($flag = 0)
    {
        if ($this->_httpAuthActive) {
            if (empty($_SERVER['PHP_AUTH_DIGEST']) || $flag & self::HTTP_AUTH_FORCE) {
                header('HTTP/1.1 401 Unauthorized');
                header('WWW-Authenticate: Digest realm="'
                    . self::HTTP_AUTH_REALM
                    . '",qop="auth",nonce="'
                    . uniqid()
                    . '",opaque="'
                    . md5(self::HTTP_AUTH_REALM)
                    . '"');
                echo self::HTTP_AUTH_MESSAGE_CANCEL;
                exit;
            }

            $http_digest_parse = function ($txt)
            {
                $needed_parts = [
                    'nonce'     => 1,
                    'nc'        => 1,
                    'cnonce'    => 1,
                    'qop'       => 1,
                    'username'  => 1,
                    'uri'       => 1,
                    'response'  => 1
                ];
                $data = [];
                $keys = implode('|', array_keys($needed_parts));
             
                preg_match_all('@(' . $keys . ')=(?:([\'"])([^\2]+?)\2|([^\s,]+))@', $txt, $matches, PREG_SET_ORDER);

                foreach ($matches as $m) {
                    $data[$m[1]] = $m[3] ? $m[3] : $m[4];
                    unset($needed_parts[$m[1]]);
                }

                return $needed_parts ? false : $data;
            };

            if (!($data = $http_digest_parse($_SERVER['PHP_AUTH_DIGEST']))
                || !isset($this->_users[$data['username']])
            ) {
                $this->httpAuth(self::HTTP_AUTH_FORCE);
                exit;
            }

            $A1 = md5($data['username'] . ':' . self::HTTP_AUTH_REALM . ':' . $this->_users[$data['username']]);
            $A2 = md5($_SERVER['REQUEST_METHOD'].':'.$data['uri']);
            $valid_response = md5($A1.':'.$data['nonce'].':'.$data['nc'].':'.$data['cnonce'].':'.$data['qop'].':'.$A2);

            if ($data['response'] != $valid_response) {
                $this->httpAuth(self::HTTP_AUTH_FORCE);
                exit;
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
        } elseif (strtolower($pwd) == strtolower($this->_home)) {
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
     * Change the prompt
     *
     * @param string $prompt
     * @access public
     * @return Console
     */
    public function setPrompt($prompt)
    {
        $this->_prompt = (string) $prompt;
        return $this;
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
        if (array_key_exists('SERVER_ADDR', $_SERVER)) {
            return $_SERVER['SERVER_ADDR'];
        }
        return 'localhost';
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
            //echo $cwd, '::', $this->_home;
            if (strtolower($cwd) == strtolower($this->_home)) {
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
     * @access public
     * @return Console
     */
    public function setHome($home)
    {
        $this->_home = (string) $home;
        return $this;
    }

    /**
     * Get the home directory
     *
     * @access public
     * @return string
     */
    public function getHome()
    {
        return $this->_home;
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

// Compiler Halt for use __COMPILER_HALT_OFFSET__ constant
__halt_compiler();
?>

<!-- CONSOLE FORM -->
<div id="console"></div>

<form id="prompt" action="{{ACTION}}" method="post" autocomplete="off">
    {{PROMPT}}
    <input type="text" name="command" id="command" />
</form>

<script type="text/javascript">
// <![CDATA[
(function ($) {

    // Document
    var $doc = $(document);

    // On ready... Go !
    $doc.ready(function () {

        // Focus command line input
        $('#command').focus();

        // Liste of key codes
        var keyCodeCtrl     = 17;
        var keyCodeL        = 76;
        var keyCodeTop      = 38;
        var keyCodeBottom   = 40;

        // Tab for keys
        var keys = [];

        // Commands History !
        var history = new Array();
        var historyCurrent = 0;

        // Declare vars for use
        var $console = $('#console');
        var $command = $('#command');
        var $prompt  = $('#prompt');

        // On submit command line :)
        $prompt.submit(function () {

            // Serialize data for ajax call before cleaning...
            var data = $(this).serialize();

            // Append history for history navigation
            if ($command.val().length > 0) {
                history.push($command.val());
            }

            // Clean prompt for next command and disable it
            $command.prop('disabled', 'disabled').val('');

            // Starts the loader for commands who take more time
            consoleLoaderStart();

            // Ajax call !
            $.ajax({
                type: 'post', // POST of course
                data: data, // serialized form
                url: $command.attr('action'), // Form action
                dataType: 'json', // Console returns JSON
                success: function (data) {
                    // Append result
                    $console.append(data['command']);
                    $console.append(data['result']);

                    // Scroll to bottom
                    $('html, body').animate({
                         scrollTop: $(document).height()
                     },
                     1000);

                    // Enable prompt and clean it
                    $command.prop('disabled', '').val('');

                    // History pointer
                    historyCurrent = 0;

                    // Change path prompt
                    $prompt.find('.pwd').html(data.pwd);
                    $doc.prop('title', data.pwd);

                    // Ending Loader
                    consoleLoaderEnd();
                }
            });

            return false;
        })

        // On key down
        .keydown(function (e) {

            // Add Key in keys tab
            keys[e.keyCode] = e.keyCode;

            // Catch Top & Bottom for History navigation
            if (e.keyCode == keyCodeTop) {
                // Move top on history
                historyCurrent = historyCurrent + 1;
                if (historyCurrent > history.length) {
                    historyCurrent = history.length;
                }
            } else if (e.keyCode == keyCodeBottom) {
                // Move bottom on history
                if (historyCurrent > 0) {
                    historyCurrent = historyCurrent - 1;
                }
            }
            // Change current history pointer and fill prompt input
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

        })

        // On key up
        .keyup(function (e) {
            // Remove key from keys tab
            keys[e.keyCode] = null;
        });


        // Move cursor to prompt
        jQuery('html').bind('click', function () {
            $command.focus();
        });
    });
})(jQuery);

// The Loader
var consoleLoader = null;
function consoleLoaderStart()
{
    consoleLoader = setInterval(function () {
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

function consoleLoaderEnd()
{
    clearInterval(consoleLoader);
    jQuery('#command').val('');
}

// ]]>
</script>

