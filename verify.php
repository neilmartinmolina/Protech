<?php
session_start();

// ─── CONFIG ───────────────────────────────────────────────────────────────────
define('SITE_URL', 'http://localhost/protech');

$token  = trim($_GET['token'] ?? '');
$status = 'invalid'; // default
$name   = '';

if (!empty($token)) {
    $pending = $_SESSION['pending_verifications'][$token] ?? null;

    if (!$pending) {
        // Token not found
        $status = 'invalid';

    } elseif (time() > $pending['expires']) {
        // Token expired — clean it up
        unset($_SESSION['pending_verifications'][$token]);
        $status = 'expired';

    } else {
        // ✅ Valid token
        $name = htmlspecialchars($pending['firstName']);
        $status = 'success';

        // 🔁 SWAP THIS for a DB update when you add MySQL:
        //    UPDATE users SET verified = 1 WHERE token = ?
        //    Then log the user in or redirect to login

        // Clean up the session token
        unset($_SESSION['pending_verifications'][$token]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - Protech</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root { --brand-orange: #ff7315; --dark-bg: #1a1a1a; }
        body { background-color: var(--dark-bg); font-family: sans-serif; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
        .card { border-radius: 20px; padding: 50px 40px; max-width: 480px; width: 100%; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .icon { font-size: 4rem; margin-bottom: 20px; }
        .btn-orange { background-color: var(--brand-orange); color: white; border: none; border-radius: 8px; padding: 10px 30px; font-weight: bold; text-decoration: none; }
        .btn-orange:hover { background-color: #e66612; color: white; }
    </style>
</head>
<body>
    <div class="card bg-white">

        <?php if ($status === 'success'): ?>
            <div class="icon">✅</div>
            <h2 style="color:#198754;">You're verified, <?= $name ?>!</h2>
            <p class="text-muted">Your email has been confirmed. You can now log in to your Protech account.</p>
            <a href="<?= SITE_URL ?>/login.php" class="btn btn-orange mt-3">Go to Login</a>

        <?php elseif ($status === 'expired'): ?>
            <div class="icon">⏰</div>
            <h2 style="color:#ffc107;">Link Expired</h2>
            <p class="text-muted">Your verification link has expired (valid for 1 hour). Please sign up again.</p>
            <a href="<?= SITE_URL ?>/signup.html" class="btn btn-orange mt-3">Sign Up Again</a>

        <?php else: ?>
            <div class="icon">❌</div>
            <h2 style="color:#dc3545;">Invalid Link</h2>
            <p class="text-muted">This verification link is invalid or has already been used.</p>
            <a href="<?= SITE_URL ?>/signup.html" class="btn btn-orange mt-3">Back to Sign Up</a>
        <?php endif; ?>

    </div>
</body>
</html>