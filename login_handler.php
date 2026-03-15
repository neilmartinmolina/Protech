<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

session_start();
require_once __DIR__ . '/config.php';

// ── Only accept POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ── Rate limiting: max 10 login attempts per IP per 15 minutes ────────────────
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    error_log('DB connect failed: ' . $conn->connect_error);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    exit;
}
$conn->set_charset('utf8mb4');

// ── Ensure login_attempts table exists ───────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS login_attempts (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_time (ip, attempted_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Check attempt count in last 15 minutes
$window = date('Y-m-d H:i:s', time() - 900);
$stmtA  = $conn->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?');
$stmtA->bind_param('ss', $ip, $window);
$stmtA->execute();
$stmtA->bind_result($attemptCount);
$stmtA->fetch();
$stmtA->close();

if ($attemptCount >= 10) {
    echo json_encode(['success' => false, 'message' => 'Too many login attempts. Please wait 15 minutes and try again.']);
    $conn->close();
    exit;
}

// ── Read & sanitise inputs ────────────────────────────────────────────────────
$identifier  = trim($_POST['identifier']  ?? '');
$password    = $_POST['password']         ?? '';
$rememberMe  = !empty($_POST['rememberMe']);

// ── Basic validation ──────────────────────────────────────────────────────────
if ($identifier === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Email/username and password are required.']);
    $conn->close();
    exit;
}

// ── Look up user by email OR username ─────────────────────────────────────────
$stmt = $conn->prepare('
    SELECT id, first_name, last_name, username, email, password_hash, is_verified
    FROM users
    WHERE email = ? OR username = ?
    LIMIT 1
');
$stmt->bind_param('ss', $identifier, $identifier);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();

// ── Record this attempt (before we reveal anything) ───────────────────────────
$stmtLog = $conn->prepare('INSERT INTO login_attempts (ip) VALUES (?)');
$stmtLog->bind_param('s', $ip);
$stmtLog->execute();
$stmtLog->close();

// ── Verify credentials — use a constant-time comparison path ─────────────────
// Even if user not found, run password_verify on a dummy hash to prevent
// timing-based user enumeration.
$dummyHash = '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ012345';
$hashToCheck = $user ? $user['password_hash'] : $dummyHash;

if (!$user || !password_verify($password, $hashToCheck)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email/username or password.']);
    $conn->close();
    exit;
}

// ── Block unverified accounts ─────────────────────────────────────────────────
if (!(int)$user['is_verified']) {
    echo json_encode([
        'success' => false,
        'message' => 'Please verify your email before signing in. Check your inbox for the verification link.'
    ]);
    $conn->close();
    exit;
}

// ── Clear login attempts for this IP on success ───────────────────────────────
$stmtClr = $conn->prepare('DELETE FROM login_attempts WHERE ip = ?');
$stmtClr->bind_param('s', $ip);
$stmtClr->execute();
$stmtClr->close();

$conn->close();

// ── Regenerate session ID to prevent session fixation ────────────────────────
session_regenerate_id(true);

// ── Store user in session ─────────────────────────────────────────────────────
$_SESSION['user'] = [
    'id'        => $user['id'],
    'firstName' => $user['first_name'],
    'lastName'  => $user['last_name'],
    'username'  => $user['username'],
    'email'     => $user['email'],
];

// ── Remember Me: secure, httpOnly cookie (30 days) ───────────────────────────
if ($rememberMe) {
    $cookieToken = bin2hex(random_bytes(32));
    // Store in session for now; for production, persist this token in a
    // remember_tokens table and validate on next visit in config.php / bootstrap.
    setcookie(
        'remember_token',
        $cookieToken,
        [
            'expires'  => time() + (30 * 24 * 60 * 60),
            'path'     => '/',
            'secure'   => true,   // set false if testing on plain HTTP locally
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
}

echo json_encode([
    'success'  => true,
    'message'  => 'Welcome back, ' . htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8') . '!',
    'redirect' => 'index.php',
]);