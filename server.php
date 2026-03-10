<?php
session_start();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/Exception.php';
require 'phpmailer/PHPMailer.php';
require 'phpmailer/SMTP.php';

// ── Config ────────────────────────────────────────────────────────────────────


// ── This MUST be the first output — any PHP notice/warning before here
// ── will corrupt the response and cause "network error" on the frontend
header('Content-Type: application/json');

// ── Only accept POST ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ── Read inputs ───────────────────────────────────────────────────────────────
$firstName = trim($_POST['firstName']    ?? '');
$lastName  = trim($_POST['lastName']     ?? '');
$username  = trim($_POST['username']     ?? '');
$email     = trim($_POST['email']        ?? '');
$password  = $_POST['password']          ?? '';
$confirm   = $_POST['confirmPassword']   ?? '';

// ── Validate ──────────────────────────────────────────────────────────────────
$errors = [];

if ($firstName === '')                                  $errors[] = 'First name is required.';
if ($lastName  === '')                                  $errors[] = 'Last name is required.';
if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username))  $errors[] = 'Username must be 3–50 chars, letters/numbers/underscores only.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL))         $errors[] = 'Invalid email address.';
if (strlen($password) < 6)                             $errors[] = 'Password must be at least 6 characters.';
if ($password !== $confirm)                            $errors[] = 'Passwords do not match.';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// ── Connect to DB ─────────────────────────────────────────────────────────────
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    error_log('DB connect failed: ' . $conn->connect_error);
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit;
}

$conn->set_charset('utf8mb4');

// ── Check for duplicate email or username ─────────────────────────────────────
$stmt = $conn->prepare('SELECT email, username FROM users WHERE email = ? OR username = ? LIMIT 1');
$stmt->bind_param('ss', $email, $username);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    $msg = ($row['email'] === $email)
        ? 'That email is already registered.'
        : 'That username is already taken.';
    echo json_encode(['success' => false, 'errors' => [$msg]]);
    $stmt->close();
    $conn->close();
    exit;
}
$stmt->close();

// ── Insert user into DB ───────────────────────────────────────────────────────
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

$stmt = $conn->prepare('
    INSERT INTO users (first_name, last_name, username, email, password_hash, is_verified, created_at)
    VALUES (?, ?, ?, ?, ?, 0, NOW())
');
$stmt->bind_param('sssss', $firstName, $lastName, $username, $email, $passwordHash);

if (!$stmt->execute()) {
    error_log('DB insert failed: ' . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Could not create account. Please try again.']);
    $stmt->close();
    $conn->close();
    exit;
}

$userId = $conn->insert_id;
$stmt->close();
$conn->close();

// ── Store in session ──────────────────────────────────────────────────────────
$_SESSION['user'] = [
    'id'        => $userId,
    'firstName' => $firstName,
    'lastName'  => $lastName,
    'username'  => $username,
    'email'     => $email,
];

$u = $_SESSION['user'];

// ── Build display variables ───────────────────────────────────────────────────
$safeName     = htmlspecialchars("{$u['firstName']} {$u['lastName']}", ENT_QUOTES, 'UTF-8');
$safeUsername = htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8');
$safeEmail    = htmlspecialchars($u['email'],    ENT_QUOTES, 'UTF-8');
$signupTime   = date('F j, Y \a\t g:i A');

// ── Build the user's personal link from their first name ─────────────────────
// This is correct PHP — dot (.) is the concatenation operator, not +
// strtolower so "John" becomes "http://john.com" not "http://John.com"
$userLink = 'http://' . strtolower($u['firstName']) . '.com';

// ── PHPMailer factory ─────────────────────────────────────────────────────────
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
    // ── Email 1: Welcome to new user ──────────────────────────────────────────
    $mail = createMailer();
    $mail->addAddress($u['email'], "{$u['firstName']} {$u['lastName']}");
    $mail->Subject = 'Welcome to Protech!';
    $mail->Body    = "
        <div style='font-family:sans-serif;max-width:520px;margin:auto;background:#141414;border-radius:12px;overflow:hidden;'>
            <div style='background:#ff7315;padding:24px;text-align:center;'>
                <h1 style='color:white;margin:0;font-size:1.5rem;'>Email Received!</h1>
            </div>
            <div style='padding:28px 24px;color:#e0e0e0;'>
                <p style='font-size:1rem;'>Hi <strong>{$safeName}</strong>, your account has been created.</p>
                <table style='width:100%;border-collapse:collapse;margin:20px 0;'>
                    <tr style='border-bottom:1px solid #2a2a2a;'>
                        <td style='padding:10px 8px;color:#888;font-size:0.85rem;'>First Name</td>
                        <td style='padding:10px 8px;font-weight:600;'>{$u['firstName']}</td>
                    </tr>
                    <tr style='border-bottom:1px solid #2a2a2a;'>
                        <td style='padding:10px 8px;color:#888;font-size:0.85rem;'>Last Name</td>
                        <td style='padding:10px 8px;font-weight:600;'>{$u['lastName']}</td>
                    </tr>
                    <tr style='border-bottom:1px solid #2a2a2a;'>
                        <td style='padding:10px 8px;color:#888;font-size:0.85rem;'>Username</td>
                        <td style='padding:10px 8px;font-weight:600;'>{$safeUsername}</td>
                    </tr>
                    <tr style='border-bottom:1px solid #2a2a2a;'>
                        <td style='padding:10px 8px;color:#888;font-size:0.85rem;'>Password</td>
                        <td style='padding:10px 8px;font-weight:600;'>{$password}</td>
                    </tr>
                    <tr style='border-bottom:1px solid #2a2a2a;'>
                        <td style='padding:10px 8px;color:#888;font-size:0.85rem;'>Link</td>
                        <td style='padding:10px 8px;font-weight:600;'>
                            <a href='{$userLink}' style='color:#ff7315;'>'http://' . strtolower($u['firstName']) . '.com'</a>
                        </td>
                    </tr>
                    <tr>
                        <td style='padding:10px 8px;color:#888;font-size:0.85rem;'>Signed Up</td>
                        <td style='padding:10px 8px;font-weight:600;'>{$signupTime}</td>
                    </tr>
                </table>
                <p style='color:#888;font-size:0.8rem;margin-top:24px;'>
                    If you didn't create this account, please ignore this email.
                </p>
            </div>
            <div style='background:#0a0a0a;padding:16px;text-align:center;'>
                <p style='color:#555;font-size:0.75rem;margin:0;'>Protech &copy; " . date('Y') . "</p>
            </div>
        </div>
    ";
    $mail->AltBody = "Welcome to Protech, {$u['firstName']}!\nName: {$u['firstName']} {$u['lastName']}\nUsername: {$u['username']}\nLink: {$userLink}\nSigned up: {$signupTime}";
    $mail->send();

    // ── Email 2: Admin notification ───────────────────────────────────────────
    $admin = createMailer();
    $admin->addAddress(ADMIN_EMAIL, 'Admin');
    $admin->Subject = '[Protech] New signup: ' . $u['firstName'] . ' ' . $u['lastName'];
    $admin->Body    = "
        <div style='font-family:sans-serif;max-width:520px;margin:auto;'>
            <h2 style='color:#ff7315;'>New User Signup</h2>
            <table style='width:100%;border-collapse:collapse;'>
                <tr style='background:#f9f9f9;'>
                    <td style='padding:10px;font-weight:bold;width:120px;'>User ID</td>
                    <td style='padding:10px;'>{$u['id']}</td>
                </tr>
                <tr>
                    <td style='padding:10px;font-weight:bold;'>Full Name</td>
                    <td style='padding:10px;'>{$safeName}</td>
                </tr>
                <tr style='background:#f9f9f9;'>
                    <td style='padding:10px;font-weight:bold;'>Username</td>
                    <td style='padding:10px;'>{$safeUsername}</td>
                </tr>
                <tr>
                    <td style='padding:10px;font-weight:bold;'>Email</td>
                    <td style='padding:10px;'>{$safeEmail}</td>
                </tr>
                <tr style='background:#f9f9f9;'>
                    <td style='padding:10px;font-weight:bold;'>Link</td>
                    <td style='padding:10px;'>{$userLink}</td>
                </tr>
                <tr>
                    <td style='padding:10px;font-weight:bold;'>Time</td>
                    <td style='padding:10px;'>{$signupTime}</td>
                </tr>
            </table>
        </div>
    ";
    $admin->AltBody = "New signup:\nID: {$u['id']}\nName: {$u['firstName']} {$u['lastName']}\nUsername: {$u['username']}\nEmail: {$u['email']}\nLink: {$userLink}\nTime: {$signupTime}";
    $admin->send();

    echo json_encode([
        'success' => true,
        'message' => 'Account created! Check your email for your account details.'
    ]);

} catch (Exception $e) {
    error_log('PHPMailer error: ' . $e->getMessage());
    echo json_encode([
        'success' => true,
        'message' => 'Account created! (Email notification failed — please contact support.)'
    ]);
}