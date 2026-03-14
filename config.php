<?php

// Central configuration file acting like an .env
// Include this file once at the start of any script that needs config.

// Prevent re-running if already loaded
if (defined('APP_CONFIG_LOADED')) {
    return;
}

define('APP_CONFIG_LOADED', true);

// Define config as global constants (easy for tools like Intelephense to see)
define('DB_HOST',     'localhost');
define('DB_USER',     'root');
define('DB_PASS',     '');
define('DB_NAME',     'protech');

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
