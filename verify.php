<?php
session_start();

define('SITE_URL', 'http://localhost/protech');

$token  = trim($_GET['token'] ?? '');
$status = 'invalid';
$name   = '';

if (!empty($token)) {
    $pending = $_SESSION['pending_verifications'][$token] ?? null;

    if (!$pending) {
        $status = 'invalid';
    } elseif (time() > $pending['expires']) {
        unset($_SESSION['pending_verifications'][$token]);
        $status = 'expired';
    } else {
        $name = htmlspecialchars($pending['firstName']);
        $status = 'success';
        unset($_SESSION['pending_verifications'][$token]);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - ProTech</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://kit.fontawesome.com/e65444583f.js" crossorigin="anonymous"></script>
    <style>
        :root {
            --primary: #ff7315;
            --primary-hover: #e66612;
            --primary-glow: rgba(255, 115, 21, 0.15);
            --dark-bg: #0a0a0a;
            --surface: #141414;
            --border: #2a2a2a;
            --text-primary: #ffffff;
            --text-secondary: #b0b0b0;
            --text-muted: #666;
            --radius-lg: 16px;
            --radius-sm: 8px;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--dark-bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            -webkit-font-smoothing: antialiased;
        }

        body::before {
            content: '';
            position: fixed;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 40% 40%, var(--primary-glow) 0%, transparent 50%);
            pointer-events: none;
        }

        .verify-card {
            position: relative;
            z-index: 1;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 3rem 2.5rem;
            max-width: 460px;
            width: 100%;
            text-align: center;
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.4);
        }

        .verify-icon {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 1.75rem;
        }

        .verify-icon.success {
            background: rgba(16, 185, 129, 0.1);
            border: 2px solid rgba(16, 185, 129, 0.2);
            color: #10b981;
        }

        .verify-icon.expired {
            background: rgba(245, 158, 11, 0.1);
            border: 2px solid rgba(245, 158, 11, 0.2);
            color: #f59e0b;
        }

        .verify-icon.invalid {
            background: rgba(239, 68, 68, 0.1);
            border: 2px solid rgba(239, 68, 68, 0.2);
            color: #ef4444;
        }

        .verify-card h2 {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
        }

        .verify-card p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            line-height: 1.7;
            margin-bottom: 2rem;
        }

        .btn-verify {
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            padding: 0.7rem 2rem;
            font-weight: 600;
            font-size: 0.95rem;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background 0.2s, transform 0.2s, box-shadow 0.2s;
        }

        .btn-verify:hover {
            background: var(--primary-hover);
            color: #fff;
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(255, 115, 21, 0.25);
        }

        .verify-footer {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }

        .verify-footer a {
            color: var(--text-muted);
            text-decoration: none;
            font-size: 0.85rem;
            font-weight: 500;
            transition: color 0.15s;
        }

        .verify-footer a:hover { color: var(--primary); }
    </style>
</head>
<body>
    <div class="verify-card">

        <?php if ($status === 'success'): ?>
            <div class="verify-icon success"><i class="fa-solid fa-check"></i></div>
            <h2>You're verified, <?= $name ?>!</h2>
            <p>Your email has been confirmed successfully. You can now log in to your ProTech account and start exploring.</p>
            <a href="<?= SITE_URL ?>/login.php" class="btn-verify">Go to Login <i class="fa-solid fa-arrow-right"></i></a>

        <?php elseif ($status === 'expired'): ?>
            <div class="verify-icon expired"><i class="fa-solid fa-clock"></i></div>
            <h2>Link Expired</h2>
            <p>Your verification link has expired (valid for 1 hour). Please sign up again to receive a new verification email.</p>
            <a href="<?= SITE_URL ?>/signup.html" class="btn-verify">Sign Up Again <i class="fa-solid fa-arrow-right"></i></a>

        <?php else: ?>
            <div class="verify-icon invalid"><i class="fa-solid fa-xmark"></i></div>
            <h2>Invalid Link</h2>
            <p>This verification link is invalid or has already been used. If you believe this is an error, please try signing up again.</p>
            <a href="<?= SITE_URL ?>/signup.html" class="btn-verify">Back to Sign Up <i class="fa-solid fa-arrow-right"></i></a>
        <?php endif; ?>

        <div class="verify-footer">
            <a href="index.php"><i class="fa-solid fa-arrow-left"></i> Back to Home</a>
        </div>
    </div>
</body>
</html>
