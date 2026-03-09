<?php
session_start();

// ─── Load PHPMailer (manual install, no Composer) ─────────────────────────────
// Make sure these 3 files exist in a folder called /phpmailer/ next to this file
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/Exception.php';
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';

// ─── CONFIG ───────────────────────────────────────────────────────────────────
// ⚠️ NEVER hardcode real credentials in production — use environment variables
define('SMTP_HOST',     'smtp.gmail.com');
define('SMTP_PORT',     587);
define('SMTP_USER',     'neilmartinmolina@gmail.com');     // ← your Gmail
define('SMTP_PASS',     'yyio jctx phof utie');  // ← Gmail App Password (NOT your real password)
define('ADMIN_EMAIL',   'neilmartinmolina@gmail.com');       // ← where admin notifications go
define('SITE_URL',      'http://localhost/ProtechNew/'); // ← your local project root URL
define('FROM_NAME',     'Protech');

// ─── Only handle POST requests ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

header('Content-Type: application/json');

// ─── Sanitize & validate inputs server-side ───────────────────────────────────
// NEVER trust client-side validation alone
$firstName = trim(htmlspecialchars($_POST['firstName'] ?? ''));
$lastName  = trim(htmlspecialchars($_POST['lastName']  ?? ''));
$email     = trim($_POST['email']    ?? '');
$password  = $_POST['password']      ?? '';
$confirm   = $_POST['confirmPassword'] ?? '';

$errors = [];

if (empty($firstName))                      $errors[] = 'First name is required.';
if (empty($lastName))                       $errors[] = 'Last name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
if (strlen($password) < 6)                  $errors[] = 'Password must be at least 6 characters.';
if ($password !== $confirm)                 $errors[] = 'Passwords do not match.';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// ─── Generate secure verification token ──────────────────────────────────────
// random_bytes gives cryptographically secure randomness
$token = bin2hex(random_bytes(32)); // 64-char hex string

// Store token in session keyed by email
// 🔁 SWAP THIS OUT for a DB insert when you add MySQL later:
//    INSERT INTO users (first_name, last_name, email, password_hash, token, verified)
//    VALUES (?, ?, ?, ?, ?, 0)
$_SESSION['pending_verifications'][$token] = [
    'firstName' => $firstName,
    'lastName'  => $lastName,
    'email'     => $email,
    'password'  => password_hash($password, PASSWORD_BCRYPT), // always hash passwords
    'expires'   => time() + 3600, // token valid for 1 hour
];

$verifyLink = SITE_URL . '/verify.php?token=' . $token;

// ─── Send emails ──────────────────────────────────────────────────────────────
function createMailer(): PHPMailer {
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

try {
    // ── Email 1: Verification email to the new user ──────────────────────────
    $mail = createMailer();
    $mail->addAddress($email, "$firstName $lastName");
    $mail->Subject = 'Verify your Protech account';
    $mail->Body    = "
        <div style='font-family:sans-serif;max-width:500px;margin:auto;'>
            <h2 style='color:#ff7315;'>Welcome to Protech, {$firstName}!</h2>
            <p>Thanks for signing up. Here is your information.</p>
            <p>Firstname: {$firstName}</p>
            <p>Lastname: {$lastName}</p>
            <p>Username: </p>
            <p>Password: {$password}</p>
            <p style='text-align:center;margin:30px 0;'>
                <a href='{$verifyLink}'
                   style='background:#ff7315;color:white;padding:12px 30px;
                          border-radius:8px;text-decoration:none;font-weight:bold;'>
                   Verify My Email
                </a>
            </p>
            <p style='color:#888;font-size:0.85rem;'>
                This link expires in 1 hour.<br>
                If you didn't sign up, ignore this email.
            </p>
            <hr style='border:none;border-top:1px solid #eee;'>
            <p style='color:#aaa;font-size:0.75rem;text-align:center;'>Protech &copy; 2025</p>
        </div>
    ";
    $mail->AltBody = "Welcome to Protech, {$firstName}!\n\nVerify your email here:\n{$verifyLink}\n\nThis link expires in 1 hour.";
    $mail->send();

    // ── Email 2: Admin notification ───────────────────────────────────────────
    $adminMail = createMailer();
    $adminMail->addAddress(ADMIN_EMAIL, 'Admin');
    $adminMail->Subject = '[Protech] New signup: ' . $firstName . ' ' . $lastName;
    $adminMail->Body    = "
        <div style='font-family:sans-serif;max-width:500px;margin:auto;'>
            <h2 style='color:#ff7315;'>New User Signup</h2>
            <table style='width:100%;border-collapse:collapse;'>
                <tr><td style='padding:8px;font-weight:bold;'>Name</td>
                    <td style='padding:8px;'>{$firstName} {$lastName}</td></tr>
                <tr style='background:#f9f9f9;'>
                    <td style='padding:8px;font-weight:bold;'>Email</td>
                    <td style='padding:8px;'>{$email}</td></tr>
                <tr><td style='padding:8px;font-weight:bold;'>Time</td>
                    <td style='padding:8px;'>" . date('Y-m-d H:i:s') . "</td></tr>
                <tr style='background:#f9f9f9;'>
                    <td style='padding:8px;font-weight:bold;'>Status</td>
                    <td style='padding:8px;'>Pending verification</td></tr>
            </table>
        </div>
    ";
    $adminMail->AltBody = "New signup:\nName: {$firstName} {$lastName}\nEmail: {$email}\nStatus: Pending verification";
    $adminMail->send();

    echo json_encode(['success' => true, 'message' => 'Registration successful! Check your email to verify your account.']);

} catch (Exception $e) {
    // Log the real error server-side, never expose SMTP details to the browser
    error_log('PHPMailer error for ' . $email . ': ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Could not send verification email. Please try again later.']);
}