<?php

// Console is required ! Of course :)
require 'Console.php';

// New instance
$console = new Console;

// Set PATH
$console->setPaths(array('$PATH'));

// Set Home directory
$console->setHome($_SERVER['DOCUMENT_ROOT']);

// Set prompt
// class "pwd" is needed for thrid argument :)
$console->setPrompt('<span class="green">%1$s@%2$s</span> <span class="blue pwd">%3$s</span><br/><span class="blue">&gt;&gt; </span>');

// Run !
$console->dispatch();


/**
 * You can edit CSS and HTML for your console.
 *
 * jQuery 1.6.0.4 is needed for Console.
 * It's possible that Console runs too with a lesser or greater version... Try !
 */
header('Content-Type: text/html; charset=utf-8;');
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo $console->getWorkingDirectory(true); ?></title>
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

    /* Bash colors */
    .color-31, .red { color: #f22; }
    .color-32, .green { color: #2da814; }
    .color-33, .orange { color: orange; }
    .color-34, .blue { color: #5542f2; }
    .color-35, .yellow { color: #ff3; }
    .color-36, .violet { color: violet; }
    .color-37, .grey { color: #cecece; }
    .color-43, .blackYellow { color: black; background-color: #ff3; }
    </style>

    <!-- jQuery -->
    <script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.6.4/jquery.min.js"></script>
</head>
<body>

<?php
// Dispatch form
// First param is the form action
echo $console->getFormHtml('./index.php');
?>

</body>
</html>
