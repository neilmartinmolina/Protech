<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('log_errors', 1);

require_once __DIR__ . '/config.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/Exception.php';
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';

define('SMTP_HOST',   'smtp.gmail.com');
define('SMTP_PORT',   587);
define('SMTP_USER',   'neilmartinmolina@gmail.com');
define('SMTP_PASS',   'yyio jctx phof utie');
define('FROM_NAME',   'Protech');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ── Determine action: 'request' (send link) or 'reset' (set new password) ────
$action = trim($_POST['action'] ?? 'request');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    error_log('DB connect failed: ' . $conn->connect_error);
    echo json_encode(['success' => false, 'message' => 'Database error. Please try again.']);
    exit;
}
$conn->set_charset('utf8mb4');

// ── Ensure password_resets table exists ───────────────────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS password_resets (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        token CHAR(64) NOT NULL,
        expires_at DATETIME NOT NULL,
        used_at DATETIME DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_token (token),
        KEY idx_user (user_id),
        KEY idx_expires (expires_at),
        CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ═════════════════════════════════════════════════════════════════════════════
// ACTION: request — send reset email
// ═════════════════════════════════════════════════════════════════════════════
if ($action === 'request') {

    $email = trim($_POST['email'] ?? '');

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid email address.']);
        $conn->close();
        exit;
    }

    // Look up user — always return a generic success message to prevent
    // email enumeration (don't tell attacker whether email exists)
    $stmt = $conn->prepare('SELECT id, first_name, last_name FROM users WHERE email = ? LIMIT 1');
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();

    $genericSuccess = [
        'success' => true,
        'message' => 'If that email is registered, you\'ll receive a reset link shortly.'
    ];

    if (!$user) {
        // Return same message — don't leak whether email exists
        echo json_encode($genericSuccess);
        $conn->close();
        exit;
    }

    // ── Rate limit: max 3 reset requests per email per hour ──────────────────
    $hourAgo = date('Y-m-d H:i:s', time() - 3600);
    $stmtR   = $conn->prepare('SELECT COUNT(*) FROM password_resets WHERE userId = ? AND created_at > ? AND used_at IS NULL');
    $stmtR->bind_param('is', $user['userId'], $hourAgo);
    $stmtR->execute();
    $stmtR->bind_result($recentCount);
    $stmtR->fetch();
    $stmtR->close();

    if ($recentCount >= 3) {
        // Still return generic message
        echo json_encode($genericSuccess);
        $conn->close();
        exit;
    }

    // ── Invalidate any existing unused tokens for this user ───────────────────
    $stmtD = $conn->prepare('DELETE FROM password_resets WHERE userId = ? AND used_at IS NULL');
    $stmtD->bind_param('i', $user['userId']);
    $stmtD->execute();
    $stmtD->close();

    // ── Generate token (valid 1 hour) ─────────────────────────────────────────
    $token     = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);

    $stmtT = $conn->prepare('INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)');
    $stmtT->bind_param('iss', $user['userId'], $token, $expiresAt);
    $stmtT->execute();
    $stmtT->close();
    $conn->close();

    $resetUrl  = rtrim(SITE_URL, '/') . '/resetpassword.php?token=' . urlencode($token);
    $safeName  = htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8');
    $year      = date('Y');

    // ── Send email ────────────────────────────────────────────────────────────
    try {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->setFrom(SMTP_USER, FROM_NAME);
        $mail->addAddress($email, $user['first_name'] . ' ' . $user['last_name']);
        $mail->isHTML(true);
        $mail->Subject = 'Reset your Protech password';
        $mail->Body    = "
            <div style='font-family:sans-serif;max-width:520px;margin:auto;background:#141414;border-radius:12px;overflow:hidden;'>
                <div style='background:#ff7315;padding:24px;text-align:center;'>
                    <h1 style='color:white;margin:0;font-size:1.5rem;'>Password Reset</h1>
                </div>
                <div style='padding:28px 24px;color:#e0e0e0;'>
                    <p style='font-size:1rem;'>Hi <strong>{$safeName}</strong>,</p>
                    <p>We received a request to reset your password. Click the button below to set a new one. This link expires in <strong>1 hour</strong>.</p>
                    <p style='margin:28px 0;text-align:center;'>
                        <a href='{$resetUrl}' style='display:inline-block;background:#ff7315;color:#fff!important;padding:14px 32px;border-radius:8px;font-weight:600;font-size:1rem;text-decoration:none;'>Reset Password</a>
                    </p>
                    <p style='color:#888;font-size:0.85rem;'>Or copy this link:<br>
                        <a href='{$resetUrl}' style='color:#ff7315;word-break:break-all;'>{$resetUrl}</a>
                    </p>
                    <hr style='border:none;border-top:1px solid #2a2a2a;margin:24px 0;'>
                    <p style='color:#888;font-size:0.8rem;'>If you didn't request a password reset, you can safely ignore this email. Your password won't change.</p>
                </div>
                <div style='background:#0a0a0a;padding:16px;text-align:center;'>
                    <p style='color:#555;font-size:0.75rem;margin:0;'>Protech &copy; {$year}</p>
                </div>
            </div>
        ";
        $mail->AltBody = "Hi {$user['first_name']},\n\nReset your password here (expires in 1 hour):\n{$resetUrl}\n\nIf you didn't request this, ignore this email.";
        $mail->send();
    } catch (Exception $e) {
        error_log('PHPMailer reset error: ' . $e->getMessage());
        // Don't expose mail failure to user — log it and return generic success
    }

    echo json_encode($genericSuccess);
    exit;
}

// ═════════════════════════════════════════════════════════════════════════════
// ACTION: reset — validate token and update password
// ═════════════════════════════════════════════════════════════════════════════
if ($action === 'reset') {

    $token    = trim($_POST['token']    ?? '');
    $password = $_POST['password']      ?? '';
    $confirm  = $_POST['confirmPassword'] ?? '';

    if ($token === '') {
        echo json_encode(['success' => false, 'message' => 'Invalid or missing reset token.']);
        $conn->close();
        exit;
    }

    if (strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        $conn->close();
        exit;
    }

    if ($password !== $confirm) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        $conn->close();
        exit;
    }

    // ── Look up token ─────────────────────────────────────────────────────────
    $now  = date('Y-m-d H:i:s');
    $stmt = $conn->prepare('
        SELECT pr.id, pr.user_id
        FROM password_resets pr
        WHERE pr.token = ?
          AND pr.expires_at > ?
          AND pr.used_at IS NULL
        LIMIT 1
    ');
    $stmt->bind_param('ss', $token, $now);
    $stmt->execute();
    $result = $stmt->get_result();
    $reset  = $result->fetch_assoc();
    $stmt->close();

    if (!$reset) {
        echo json_encode(['success' => false, 'message' => 'This reset link is invalid or has expired. Please request a new one.']);
        $conn->close();
        exit;
    }

    // ── Update password ───────────────────────────────────────────────────────
    $newHash = password_hash($password, PASSWORD_BCRYPT);
    $stmtU   = $conn->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
    $stmtU->bind_param('si', $newHash, $reset['user_id']);
    $stmtU->execute();
    $stmtU->close();

    // ── Mark token as used ────────────────────────────────────────────────────
    $stmtM = $conn->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
    $stmtM->bind_param('i', $reset['id']);
    $stmtM->execute();
    $stmtM->close();

    $conn->close();

    echo json_encode([
        'success'  => true,
        'message'  => 'Password updated successfully. You can now sign in.',
        'redirect' => 'login.php',
    ]);
    exit;
}

// ── Unknown action ────────────────────────────────────────────────────────────
echo json_encode(['success' => false, 'message' => 'Unknown action.']);
$conn->close();