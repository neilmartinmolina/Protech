<?php
ob_start();
header('Content-Type: application/json');

set_exception_handler(function(Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
    ]);
    exit;
});

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, $errno, 0, $errfile, $errline);
});

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

function create_mailer(): PHPMailer
{
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->setFrom(SMTP_USER, FROM_NAME);
    $mail->isHTML(true);
    return $mail;
}

// ── Collect & validate input ──────────────────────────────────────────────────
$firstName    = trim($_POST['firstName']       ?? '');
$lastName     = trim($_POST['lastName']        ?? '');
$username     = trim($_POST['username']        ?? '');
$email        = trim($_POST['email']           ?? '');
$role         = trim($_POST['role']            ?? 'customer');
$storeName    = trim($_POST['storeName']       ?? '');
$password     = $_POST['password']             ?? '';
$confirm      = $_POST['confirmPassword']      ?? '';
$avatarUpload = $_FILES['avatar']              ?? null;
$avatarResult = ['success' => true, 'path' => null];

$errors = [];
if ($firstName === '')                                        $errors[] = 'First name is required.';
if ($lastName === '')                                         $errors[] = 'Last name is required.';
if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username))        $errors[] = 'Username must be 3–50 characters: letters, numbers, underscores only.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))               $errors[] = 'Invalid email address.';
if (!in_array($role, ['customer', 'seller'], true))           $errors[] = 'Invalid role selected.';
if ($role === 'seller' && $storeName === '')                  $errors[] = 'Store name is required for seller registration.';
if (strlen($password) < 6)                                   $errors[] = 'Password must be at least 6 characters.';
if ($password !== $confirm)                                   $errors[] = 'Passwords do not match.';

if ($errors) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

$conn = app_db();

// ── Check for duplicate email / username ──────────────────────────────────────
$stmt = $conn->prepare('SELECT userId, email, username FROM users WHERE email = ? OR username = ? LIMIT 1');
$stmt->bind_param('ss', $email, $username);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();
$stmt->close();

if ($existing) {
    $message = $existing['email'] === $email
        ? 'That email is already registered.'
        : 'That username is already taken.';
    echo json_encode(['success' => false, 'errors' => [$message]]);
    exit; // ← was missing before — user would fall through to insert
}

// ── Handle avatar upload ──────────────────────────────────────────────────────
if ($avatarUpload && ($avatarUpload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $avatarResult = app_store_avatar($avatarUpload);
    if (!$avatarResult['success']) {
        echo json_encode(['success' => false, 'errors' => [$avatarResult['message']]]);
        exit;
    }
}

$passwordHash = password_hash($password, PASSWORD_BCRYPT);
$sellerStatus = $role === 'seller' ? 'pending' : 'not_applicable';
$avatarPath   = $avatarResult['path'] ?? null;

// ── Transactional insert: user + verification token ───────────────────────────
$conn->begin_transaction();

try {
    $stmt = $conn->prepare('
        INSERT INTO users
            (first_name, last_name, username, email, password_hash,
             role, seller_status, store_name, avatar_path, is_verified, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())
    ');
    $stmt->bind_param(
        'sssssssss',
        $firstName, $lastName, $username, $email, $passwordHash,
        $role, $sellerStatus, $storeName, $avatarPath
    );

    if (!$stmt->execute()) {
        throw new RuntimeException('Could not create account: ' . $stmt->error);
    }

    $userId = (int) $conn->insert_id;
    $stmt->close();

    $verifyToken = bin2hex(random_bytes(32));
    $expiresAt   = date('Y-m-d H:i:s', time() + 3600);

    $stmt = $conn->prepare('INSERT INTO verification_tokens (userId, token, expires_at) VALUES (?, ?, ?)');
    $stmt->bind_param('iss', $userId, $verifyToken, $expiresAt);

    if (!$stmt->execute()) {
        throw new RuntimeException('Could not create verification token: ' . $stmt->error);
    }

    $stmt->close();
    $conn->commit();

} catch (Throwable $e) {
    $conn->rollback();
    error_log('Signup transaction failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Could not create account. Please try again.']);
    exit;
}

// ── Set session ───────────────────────────────────────────────────────────────
$verifyUrl = rtrim(SITE_URL, '/') . '/verify.php?token=' . urlencode($verifyToken);

$_SESSION['user'] = [
    'userId'        => $userId,
    'firstName'     => $firstName,
    'lastName'      => $lastName,
    'username'      => $username,
    'email'         => $email,
    'role'          => $role,
    'seller_status' => $sellerStatus,
    'store_name'    => $storeName,
    'avatar_path'   => $avatarPath,
];

// ── Send emails ───────────────────────────────────────────────────────────────
$displayName  = app_sanitize($firstName . ' ' . $lastName);
$safeEmail    = app_sanitize($email);
$safeUsername = app_sanitize($username);
$safeStore    = app_sanitize($storeName);
$signupTime   = date('F j, Y \a\t g:i A');

try {
    $mail = create_mailer();
    $mail->addAddress($email, $firstName . ' ' . $lastName);
    $mail->Subject = $role === 'seller'
        ? 'Verify your ProTech seller application'
        : 'Verify your ProTech account';
    $mail->Body = "
        <div style='font-family:sans-serif;max-width:520px;margin:auto;background:#141414;border-radius:12px;overflow:hidden;'>
            <div style='background:#ff7315;padding:24px;text-align:center;'>
                <h1 style='color:white;margin:0;font-size:1.5rem;'>Verify your email</h1>
            </div>
            <div style='padding:28px 24px;color:#e0e0e0;'>
                <p>Hi <strong>{$displayName}</strong>, please verify your email to activate your ProTech "
                . ($role === 'seller' ? 'seller application' : 'account') . ".</p>
                <p style='margin:24px 0;text-align:center;'>
                    <a href='{$verifyUrl}'
                       style='display:inline-block;background:#ff7315;color:#fff!important;padding:14px 32px;
                              border-radius:8px;font-weight:600;font-size:1rem;text-decoration:none;'>
                        Verify Email
                    </a>
                </p>
                <p style='color:#888;font-size:0.85rem;'>Email: {$safeEmail}<br>Username: {$safeUsername}</p>
                " . ($role === 'seller'
                    ? "<p style='color:#e0e0e0;'>Store: <strong>{$safeStore}</strong><br>Your seller account will stay pending until an admin approves it.</p>"
                    : '') . "
            </div>
        </div>
    ";
    $mail->AltBody = "Verify your email: {$verifyUrl}";
    $mail->send();

    $admin = create_mailer();
    $admin->addAddress(ADMIN_EMAIL, 'Admin');
    $admin->Subject = '[ProTech] New ' . ($role === 'seller' ? 'seller application' : 'signup') . ': ' . $firstName . ' ' . $lastName;
    $admin->Body = "
        <div style='font-family:sans-serif;max-width:520px;margin:auto;'>
            <h2 style='color:#ff7315;'>New " . ($role === 'seller' ? 'Seller Application' : 'User Signup') . "</h2>
            <table style='width:100%;border-collapse:collapse;'>
                <tr><td style='padding:10px;font-weight:bold;'>User ID</td><td style='padding:10px;'>{$userId}</td></tr>
                <tr><td style='padding:10px;font-weight:bold;'>Name</td><td style='padding:10px;'>{$displayName}</td></tr>
                <tr><td style='padding:10px;font-weight:bold;'>Username</td><td style='padding:10px;'>{$safeUsername}</td></tr>
                <tr><td style='padding:10px;font-weight:bold;'>Email</td><td style='padding:10px;'>{$safeEmail}</td></tr>
                <tr><td style='padding:10px;font-weight:bold;'>Role</td><td style='padding:10px;'>" . app_sanitize(ucfirst($role)) . "</td></tr>
                " . ($role === 'seller'
                    ? "<tr><td style='padding:10px;font-weight:bold;'>Store</td><td style='padding:10px;'>{$safeStore}</td></tr>"
                    : '') . "
                <tr><td style='padding:10px;font-weight:bold;'>Time</td><td style='padding:10px;'>{$signupTime}</td></tr>
            </table>
        </div>
    ";
    $admin->AltBody = "New signup — ID: {$userId} | Name: {$firstName} {$lastName} | Email: {$email} | Role: {$role}";
    $admin->send();

} catch (Exception $e) {
    // Email failure is non-fatal — user is already created
    error_log('Signup email failed: ' . $e->getMessage());
}

// ── Success ───────────────────────────────────────────────────────────────────
$message = $role === 'seller'
    ? 'Seller account created. Verify your email first, then wait for admin approval.'
    : 'Account created. Check your email and click the verification link to activate your account.';

echo json_encode(['success' => true, 'message' => $message]);