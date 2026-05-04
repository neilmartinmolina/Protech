<?php
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (is_readable($autoloadPath)) {
    require_once $autoloadPath;
}

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

function env_value(string $key, string $default = ''): string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null) {
        return $default;
    }

    return $value;
}

function env_first(array $keys, string $default = ''): string {
    foreach ($keys as $key) {
        $value = env_value($key);

        if ($value !== '') {
            return $value;
        }
    }

    return $default;
}

function load_dotenv(string $directory): void {
    if (class_exists(Dotenv\Dotenv::class)) {
        try {
            Dotenv\Dotenv::createImmutable($directory)->safeLoad();
            return;
        } catch (Throwable $exception) {
            error_log('Dotenv load failed: ' . $exception->getMessage());
        }
    }

    $envPath = rtrim($directory, DIRECTORY_SEPARATOR . '/') . DIRECTORY_SEPARATOR . '.env';
    if (!is_readable($envPath)) {
        return;
    }

    foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);

        if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
            continue;
        }

        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");

        if (env_value($key) === '') {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
}

if ($isLocal) {
    load_dotenv(__DIR__);

    define('DB_HOST',  'localhost');
    define('DB_USER',  'root');
    define('DB_PASS',  '');
    define('DB_NAME',  'protech3nf');
    define('SITE_URL', 'http://localhost/Protech');
    define('DEV_NAME', 'NeilMartin');
    define('SMTP_HOST', 'smtp.gmail.com');
    define('SMTP_PORT', 587);
    define('SMTP_USER', env_value('SMTPUSERProtech'));
    define('SMTP_PASS', env_value('SMTPPASSProtech'));
    define('ADMIN_EMAIL', 'neilmartinmolina@gmail.com');
    define('SUPERADMIN_EMAIL', 'neilmartinmolina@gmail.com');
    define('FROM_NAME', 'Protech');
} else {
    load_dotenv(dirname(__DIR__, 2));

    define('DB_HOST',  'localhost');
    define('DB_USER',  'u845277124_protech');
    define('DB_PASS',  env_value('DBPASSProtech'));
    define('DB_NAME',  'u845277124_protech');
    define('SITE_URL', 'https://protech.argy.host/');
    define('DEV_NAME', 'NeilMartin');
    define('SMTP_HOST', 'smtp.gmail.com');
    define('SMTP_PORT', 587);
    define('SMTP_USER', env_value('SMTPUSERProtech'));
    define('SMTP_PASS', env_value('SMTPPASSProtech'));
    define('ADMIN_EMAIL', 'neilmartinmolina@gmail.com');
    define('SUPERADMIN_EMAIL', 'neilmartinmolina@gmail.com');
    define('FROM_NAME', 'Protech');
}
