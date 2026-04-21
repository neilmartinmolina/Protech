<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    app_no_html_redirect();
}
header('Content-Type: application/json');

$ip         = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$identifier = trim($_POST['identifier'] ?? '');
$password   = $_POST['password'] ?? '';
$rememberMe = !empty($_POST['rememberMe']);
$conn       = app_db();

// ── Early empty check ─────────────────────────────────────────────────────────
if ($identifier === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Email/username and password are required.']);
    exit;
}

// ── Rate limit: IP + identifier ───────────────────────────────────────────────
$window = date('Y-m-d H:i:s', time() - 900);
$stmt = $conn->prepare('
    SELECT COUNT(*) FROM login_attempts
    WHERE ip = ? AND identifier = ? AND attempted_at > ?
');
$stmt->bind_param('sss', $ip, $identifier, $window);
$stmt->execute();
$stmt->bind_result($attemptCount);
$stmt->fetch();
$stmt->close();

if ((int) $attemptCount >= 10) {
    echo json_encode(['success' => false, 'message' => 'Too many login attempts. Please wait 15 minutes and try again.']);
    exit;
}

// ── Lookup ────────────────────────────────────────────────────────────────────
$stmt = $conn->prepare('
    SELECT userId, first_name, last_name, username, email, password_hash,
           is_verified, role, seller_status, avatar_path
    FROM users
    WHERE email = ? OR username = ?
    LIMIT 1
');
$stmt->bind_param('ss', $identifier, $identifier);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// ── Record attempt BEFORE verifying (prevents timing-based user enumeration) ──
$stmt = $conn->prepare('INSERT INTO login_attempts (ip, identifier) VALUES (?, ?)');
$stmt->bind_param('ss', $ip, $identifier);
$stmt->execute();
$stmt->close();

// ── Constant-time check ───────────────────────────────────────────────────────
$dummyHash   = password_hash('dummy_' . random_bytes(8), PASSWORD_BCRYPT);
$hashToCheck = $user ? $user['password_hash'] : $dummyHash;

if (!$user || !password_verify($password, $hashToCheck)) {
    // Only log if the user actually exists — avoids polluting logs with
    // enumeration probes against nonexistent accounts
    if ($user) {
        app_log_activity($conn, (int) $user['userId'], 'user.login_failed',
            "Failed login attempt for '{$identifier}' from {$ip}.", [
            'entity_type' => 'user',
            'entity_id'   => (int) $user['userId'],
            'severity'    => 'warning',
            'context'     => [
                'identifier' => $identifier,
                'ip'         => $ip,
            ],
        ]);
    }
    echo json_encode(['success' => false, 'message' => 'Invalid email/username or password.']);
    exit;
}

// ── Account state checks ──────────────────────────────────────────────────────
if (!(int) $user['is_verified']) {
    echo json_encode([
        'success' => false,
        'message' => 'Please verify your email before signing in. Check your inbox for the verification link.',
    ]);
    exit;
}

if ($user['role'] === 'seller' && $user['seller_status'] !== 'approved') {
    echo json_encode([
        'success' => false,
        'message' => 'Your seller account is still waiting for admin approval.',
    ]);
    exit;
}

// ── Success: clear only THIS identifier's attempts from THIS IP ───────────────
$stmt = $conn->prepare('DELETE FROM login_attempts WHERE ip = ? AND identifier = ?');
$stmt->bind_param('ss', $ip, $identifier);
$stmt->execute();
$stmt->close();

// ── Session ───────────────────────────────────────────────────────────────────
session_regenerate_id(true);

$_SESSION['user'] = [
    'userId'        => (int) $user['userId'],
    'firstName'     => $user['first_name'],
    'lastName'      => $user['last_name'],
    'username'      => $user['username'],
    'email'         => $user['email'],
    'role'          => $user['role'],
    'seller_status' => $user['seller_status'],
    'store_name'    => null,
    'avatar_path'   => $user['avatar_path'],
];

// Derive store name (normalized schema) from seller_applications
if ($user['role'] === 'seller') {
    $status = (string) ($user['seller_status'] ?? '');
    $stmt = $conn->prepare("
        SELECT store_name
        FROM seller_applications
        WHERE userId = ?
          AND (
            (? = 'approved' AND status = 'approved')
            OR
            (? != 'approved')
          )
        ORDER BY
          CASE WHEN status = 'approved' THEN reviewed_at ELSE created_at END DESC
        LIMIT 1
    ");
    $uid = (int) $user['userId'];
    $stmt->bind_param('iss', $uid, $status, $status);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $_SESSION['user']['store_name'] = $row['store_name'] ?? null;
}

// ── Remember Me ───────────────────────────────────────────────────────────────
if ($rememberMe) {
    $token      = bin2hex(random_bytes(32));
    $tokenHash  = hash('sha256', $token);
    $expires    = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));

    // Invalidate any existing remember tokens for this user before issuing a new one.
    // Prevents token accumulation if the user logs in on multiple devices.
    $stmt = $conn->prepare('DELETE FROM remember_tokens WHERE userId = ?');
    $stmt->bind_param('i', $user['userId']);
    $stmt->execute();
    $stmt->close();

    // Store the hash — never the raw token — in the DB.
    $stmt = $conn->prepare('
        INSERT INTO remember_tokens (userId, token_hash, expires_at)
        VALUES (?, ?, ?)
    ');
    $stmt->bind_param('iss', $user['userId'], $tokenHash, $expires);
    $stmt->execute();
    $stmt->close();

    setcookie('remember_token', $token, [
        'expires'  => time() + (30 * 24 * 60 * 60),
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

// ── Log successful login ──────────────────────────────────────────────────
app_log_activity($conn, (int) $user['userId'], 'user.login',
    "User '{$user['username']}' logged in from {$ip}.", [
    'entity_type' => 'user',
    'entity_id'   => (int) $user['userId'],
    'severity'    => 'info',
    'context'     => [
        'ip'         => $ip,
        'remember_me' => $rememberMe,
    ],
]);

echo json_encode([
    'success'  => true,
    'message'  => 'Welcome back, ' . app_sanitize($user['first_name']) . '!',
    'redirect' => app_dashboard_redirect($_SESSION['user']),
]);