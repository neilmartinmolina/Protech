<?php
require_once __DIR__ . '/config.php';

$token  = trim($_GET['token'] ?? '');
$status = 'invalid';
$name   = '';

if (!empty($token)) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn->connect_error) {
        $conn->set_charset('utf8mb4');
        $stmt = $conn->prepare('
            SELECT vt.user_id, vt.expires_at, u.first_name
            FROM verification_tokens vt
            JOIN users u ON u.id = vt.user_id
            WHERE vt.token = ?
        ');
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $row    = $result->fetch_assoc();
        $stmt->close();

        if ($row) {
            if (strtotime($row['expires_at']) < time()) {
                $del = $conn->prepare('DELETE FROM verification_tokens WHERE token = ?');
                $del->bind_param('s', $token);
                $del->execute();
                $del->close();
                $status = 'expired';
            } else {
                $userId = (int) $row['user_id'];
                $upd = $conn->prepare('UPDATE users SET is_verified = 1 WHERE id = ?');
                $upd->bind_param('i', $userId);
                $upd->execute();
                $upd->close();
                $del = $conn->prepare('DELETE FROM verification_tokens WHERE token = ?');
                $del->bind_param('s', $token);
                $del->execute();
                $del->close();
                $name   = htmlspecialchars($row['first_name']);
                $status = 'success';
            }
        }
        $conn->close();
    }
}

$pageTitle = 'Email Verification - ProTech';
$pageCss   = ['verify.css'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/header.php'; ?>
</head>
<body>
    <div class="verify-card">

        <?php if ($status === 'success'): ?>
            <div class="verify-icon success"><i class="fa-solid fa-check"></i></div>
            <h2>You're verified, <?= $name ?>!</h2>
            <p>Your email has been confirmed successfully. You can now log in to your ProTech account and start exploring.</p>
            <a href="<?= rtrim(SITE_URL, '/') ?>/login.php" class="btn-verify">Go to Login <i class="fa-solid fa-arrow-right"></i></a>

        <?php elseif ($status === 'expired'): ?>
            <div class="verify-icon expired"><i class="fa-solid fa-clock"></i></div>
            <h2>Link Expired</h2>
            <p>Your verification link has expired (valid for 1 hour). Please sign up again to receive a new verification email.</p>
            <a href="<?= rtrim(SITE_URL, '/') ?>/signup.php" class="btn-verify">Sign Up Again <i class="fa-solid fa-arrow-right"></i></a>

        <?php else: ?>
            <div class="verify-icon invalid"><i class="fa-solid fa-xmark"></i></div>
            <h2>Invalid Link</h2>
            <p>This verification link is invalid or has already been used. If you believe this is an error, please try signing up again.</p>
            <a href="<?= rtrim(SITE_URL, '/') ?>/signup.php" class="btn-verify">Back to Sign Up <i class="fa-solid fa-arrow-right"></i></a>
        <?php endif; ?>

        <div class="verify-footer">
            <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Back to Home</a>
        </div>
    </div>
<?php include __DIR__ . '/scripts.php'; ?>
</body>
</html>
