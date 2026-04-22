<?php
// session cookie params must be set before session_start()
session_set_cookie_params([
    'lifetime' => 0,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'] ?? 'localhost',
    'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true);

if ($isLocal) {
    define('DB_HOST',  'localhost');
    define('DB_USER',  'root');
    define('DB_PASS',  '');
    define('DB_NAME',  'protech3nf');
    define('SITE_URL', 'http://localhost/Protech');
} else {
    define('DB_HOST',  'localhost');
    define('DB_USER',  'u845277124_protech');
    define('DB_PASS',  '562572Bojo');
    define('DB_NAME',  'u845277124_protech');
    define('SITE_URL', 'https://protech.argy.host/');
}

define('DEV_NAME',    'NeilMartin');
define('SMTP_HOST',   'smtp.gmail.com');
define('SMTP_PORT',   587);
define('SMTP_USER',   'neilmartinmolina@gmail.com');
define('SMTP_PASS',   'yyio jctx phof utie');
define('ADMIN_EMAIL',    'neilmartinmolina@gmail.com');
define('SUPERADMIN_EMAIL', 'neilmartinmolina@gmail.com');
define('FROM_NAME',   'Protech');
