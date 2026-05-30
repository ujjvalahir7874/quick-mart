<?php
// Vercel PHP entrypoint: route cleanly to the existing XAMPP-style PHP files.
$root = realpath(__DIR__ . '/..');
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$path = rawurldecode($path);

if ($path === '/' || $path === '/api/index.php') {
    $path = '/index.php';
} elseif (substr($path, -1) === '/') {
    $path .= 'index.php';
}

if (!preg_match('/\.php$/i', $path)) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

$relativePath = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, ltrim($path, '/'));
$target = realpath($root . DIRECTORY_SEPARATOR . $relativePath);
$blockedDirs = ['config', 'database', 'includes'];
$firstSegment = strtok(str_replace('\\', '/', ltrim($path, '/')), '/');

if (
    !$target ||
    strpos($target, $root) !== 0 ||
    in_array($firstSegment, $blockedDirs, true) ||
    !is_file($target)
) {
    http_response_code(404);
    echo 'Not found';
    exit;
}

chdir(dirname($target));
$_SERVER['SCRIPT_FILENAME'] = $target;
$_SERVER['SCRIPT_NAME'] = $path;
$_SERVER['PHP_SELF'] = $path;

require $target;
