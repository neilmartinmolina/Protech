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

// ── Security Headers ──────────────────────────────────────────────────────────
$isSecure = (!empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off');
$scheme   = $isSecure ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl  = $scheme . '://' . $host;

// Generate nonce for inline scripts/styles
$cspNonce = base64_encode(random_bytes(32));
$_SESSION['csp_nonce'] = $cspNonce;

/**
 * Get CSP nonce attribute for inline script/style tags
 */
function csp_nonce_attr(): string {
    return 'nonce="' . ($_SESSION['csp_nonce'] ?? '') . '"';
}

// Content Security Policy — strict, no unsafe-inline
$csp = [
    "default-src"    => ["'self'"],
    "script-src"     => ["'self'", "'nonce-{$cspNonce}'", "https://kit.fontawesome.com", "https://cdn.jsdelivr.net", "https://cdn.datatables.net", "https://code.jquery.com"],
    "style-src"      => ["'self'", "'unsafe-inline'", "https://fonts.googleapis.com", "https://cdn.jsdelivr.net", "https://cdn.datatables.net", "https://code.jquery.com"],
    "img-src"        => ["'self'", "data:", "https:"],
    "font-src"       => ["'self'", "https:", "https://kit.fontawesome.com"],
    "connect-src"    => ["'self'", "https:"],
    "object-src"     => ["'none'"],
    "base-uri"       => ["'self'"],
    "form-action"    => ["'self'"],
    "frame-ancestors" => ["'none'"],
];
$cspParts = [];
foreach ($csp as $directive => $sources) {
    $cspParts[] = $directive . ' ' . implode(' ', $sources);
}
header('Content-Security-Policy: ' . implode('; ', $cspParts));

// Clickjacking protection
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');

// Referrer policy
header('Referrer-Policy: strict-origin-when-cross-origin');

// Permissions policy (formerly Feature Policy)
header('Permissions-Policy: geolocation=(self), microphone=(), camera=()');

// Cross-Domain / CORS - strict same-origin by default
header('Access-Control-Allow-Origin: ' . $baseUrl);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-CSRF-Token');
header('Access-Control-Max-Age: 86400');

// Remove X-Powered-By to reduce fingerprinting
header_remove('X-Powered-By');

$isLocal = in_array($_SERVER['SERVER_NAME'] ?? '', ['localhost', '127.0.0.1'], true);

function load_env_file(string $path): void {
    if (!is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");

        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function env_value(string $key, string $default = ''): string {
    return $_ENV[$key] ?? getenv($key) ?: $default;
}

if ($isLocal) {
    define('DB_HOST',  'localhost');
    define('DB_USER',  'root');
    define('DB_PASS',  '');
    define('DB_NAME',  'protech3nf');
    define('SITE_URL', 'http://localhost/Protech');
} else {
    load_env_file(dirname(__DIR__, 2) . '/.env');

    define('DBHOSTProtech',  env_value('DBHOSTProtech'));
    define('DBUSERProtech',  env_value('DBUSERProtech'));
    define('DBPASSProtech',  env_value('DBPASSProtech'));
    define('DBNAMEProtech',  env_value('DBNAMEProtech'));
    define('SITEURLProtech', env_value('SITEURLProtech'));
}


define('SMTPUSERProtech',   env_value('SMTPUSERProtech'));
define('SMTPPassProtech',   env_value('SMTPPassProtech'));


define('DEV_NAME',    'NeilMartin');
define('SMTP_HOST',   'smtp.gmail.com');
define('SMTP_PORT',   587);
define('ADMIN_EMAIL',    'neilmartinmolina@gmail.com');
define('SUPERADMIN_EMAIL', 'neilmartinmolina@gmail.com');
define('FROM_NAME',   'Protech');
