<?php
require_once __DIR__ . '/app.php';
$conn = app_db();

$token  = trim($_GET['token'] ?? '');
$status = 'invalid';
$name   = '';

if (!empty($token)) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn->connect_error) {
        $conn->set_charset('utf8mb4');
        $stmt = $conn->prepare('
            SELECT ev.userId, ev.expires_at, ev.used_at, u.first_name
            FROM email_verifications ev
            JOIN users u ON u.userId = ev.userId
            WHERE ev.token = ?
            LIMIT 1
        ');
        $stmt->bind_param('s', $token);
        $stmt->execute();
        $result = $stmt->get_result();
        $row    = $result->fetch_assoc();
        $stmt->close();

        if ($row) {
            if (!empty($row['used_at'])) {
                $status = 'invalid';
            } elseif (strtotime($row['expires_at']) < time()) {
                $status = 'expired';
            } else {
                $userId = (int) $row['userId'];
                $upd = $conn->prepare('UPDATE users SET is_verified = 1 WHERE userId = ?');
                $upd->bind_param('i', $userId);
                $upd->execute();
                $upd->close();

                $mark = $conn->prepare('UPDATE email_verifications SET used_at = NOW() WHERE token = ?');
                $mark->bind_param('s', $token);
                $mark->execute();
                $mark->close();

                $name   = htmlspecialchars($row['first_name']);
                $status = 'success';
            }
        }

    }
}

$pageTitle = 'Email Verification - ProTech';
$pageCss   = ['verify.css'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
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
<?php include __DIR__ . '/includes/scripts.php'; ?>
</body>
</html>
