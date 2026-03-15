<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

require_once __DIR__ . '/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$conn = app_db();

$window = date('Y-m-d H:i:s', time() - 900);
$stmt = $conn->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ? AND attempted_at > ?');
$stmt->bind_param('ss', $ip, $window);
$stmt->execute();
$stmt->bind_result($attemptCount);
$stmt->fetch();
$stmt->close();

if ((int) $attemptCount >= 10) {
    echo json_encode(['success' => false, 'message' => 'Too many login attempts. Please wait 15 minutes and try again.']);
    exit;
}

$identifier = trim($_POST['identifier'] ?? '');
$password = $_POST['password'] ?? '';
$rememberMe = !empty($_POST['rememberMe']);

if ($identifier === '' || $password === '') {
    echo json_encode(['success' => false, 'message' => 'Email/username and password are required.']);
    exit;
}

$stmt = $conn->prepare('
    SELECT id, first_name, last_name, username, email, password_hash, is_verified, role, seller_status, store_name, avatar_path
    FROM users
    WHERE email = ? OR username = ?
    LIMIT 1
');
$stmt->bind_param('ss', $identifier, $identifier);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$stmt = $conn->prepare('INSERT INTO login_attempts (ip) VALUES (?)');
$stmt->bind_param('s', $ip);
$stmt->execute();
$stmt->close();

$dummyHash = '$2y$10$abcdefghijklmnopqrstuuABCDEFGHIJKLMNOPQRSTUVWXYZ012345';
$hashToCheck = $user ? $user['password_hash'] : $dummyHash;

if (!$user || !password_verify($password, $hashToCheck)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email/username or password.']);
    exit;
}

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

$stmt = $conn->prepare('DELETE FROM login_attempts WHERE ip = ?');
$stmt->bind_param('s', $ip);
$stmt->execute();
$stmt->close();

session_regenerate_id(true);

$_SESSION['user'] = [
    'id' => (int) $user['id'],
    'firstName' => $user['first_name'],
    'lastName' => $user['last_name'],
    'username' => $user['username'],
    'email' => $user['email'],
    'role' => $user['role'],
    'seller_status' => $user['seller_status'],
    'store_name' => $user['store_name'],
    'avatar_path' => $user['avatar_path'],
];

if ($rememberMe) {
    setcookie(
        'remember_token',
        bin2hex(random_bytes(32)),
        [
            'expires' => time() + (30 * 24 * 60 * 60),
            'path' => '/',
            'secure' => false,
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
}

echo json_encode([
    'success' => true,
    'message' => 'Welcome back, ' . app_sanitize($user['first_name']) . '!',
    'redirect' => app_dashboard_redirect($_SESSION['user']),
]);
