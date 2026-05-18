<?php
// Increase session time to 3 months (90 days)
$session_timeout = 90 * 24 * 60 * 60;
$session_dir = __DIR__ . '/data/sessions';
if (!is_dir($session_dir)) {
    mkdir($session_dir, 0777, true);
}
ini_set('session.save_path', $session_dir);
ini_set('session.gc_maxlifetime', $session_timeout);
session_set_cookie_params([
    'lifetime' => $session_timeout,
    'path' => '/',
    'samesite' => 'Lax'
]);
session_start();

function loadEnv($path) {
    if (!file_exists($path)) return;
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        list($name, $value) = explode('=', $line, 2);
        $_ENV[trim($name)] = trim($value);
    }
}

loadEnv(__DIR__ . '/.env');

function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

function checkLogin($password) {
    $adminPassword = $_ENV['ADMIN_PASSWORD'] ?? 'admin';
    if ($password === $adminPassword) {
        $_SESSION['logged_in'] = true;
        return true;
    }
    return false;
}

function logout() {
    session_destroy();
}
