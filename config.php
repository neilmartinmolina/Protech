<?php
session_start();

define('DB_HOST',     'localhost');
define('DB_USER',     'root');
define('DB_PASS',     '');
define('DB_NAME',     'protech');
define('SITE_URL',    'http://localhost/Protech');
define('DEV_NAME',    'NeilMartin');

// Also mirror these into $_ENV / getenv so they still behave like env vars
$keys = [
    'DB_HOST', 'DB_USER', 'DB_PASS', 'DB_NAME',
];

foreach ($keys as $key) {
    $value = constant($key);

    if (!array_key_exists($key, $_ENV)) {
        $_ENV[$key] = $value;
    }

    if (function_exists('putenv')) {
        putenv($key . '=' . $value);
    }
}
