<?php
require_once __DIR__ . '/config.php';

// ── Validate token presence early — redirect if missing ──────────────────────
$token = trim($_GET['token'] ?? '');
if ($token === '') {
    header('Location: forgotpassword.php');
    exit;
}

// ── Verify token exists and isn't expired before rendering the page ───────────
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
$tokenValid = false;

if (!$conn->connect_error) {
    $conn->set_charset('utf8mb4');
    $now  = date('Y-m-d H:i:s');
    $stmt = $conn->prepare('
        SELECT id FROM password_resets
        WHERE token = ? AND expires_at > ? AND used_at IS NULL
        LIMIT 1
    ');
    $stmt->bind_param('ss', $token, $now);
    $stmt->execute();
    $stmt->get_result()->fetch_assoc() && ($tokenValid = true);
    $stmt->close();
    $conn->close();
}

$pageTitle   = 'Reset Password - ProTech';
$pageCss     = ['auth.css'];
$pageScripts = ['js/reset_password.js'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
</head>
<body>
    <div class="rp-wrapper">

        <div class="rp-header">
            <a href="index.php" class="logo">
                <i class="fa-solid fa-microchip"></i> Pro<span>Tech</span>
            </a>
            <?php if ($tokenValid): ?>
                <h1>Set new password</h1>
                <p>Choose a strong password for your account</p>
            <?php else: ?>
                <h1>Link expired</h1>
                <p>This reset link is invalid or has already been used</p>
            <?php endif; ?>
        </div>

        <div class="rp-card">
            <div id="serverMessage"></div>

            <?php if ($tokenValid): ?>
            <form id="rpForm" novalidate>
                <input type="hidden" name="action" value="reset">
                <input type="hidden" name="token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

                <div class="mb-1">
                    <label class="form-label" for="password">New Password</label>
                    <div class="password-wrapper">
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Min. 6 characters" required autocomplete="new-password">
                        <button type="button" class="toggle-password" data-target="password" aria-label="Show password">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback" id="passwordError">Must be at least 6 characters.</div>
                </div>

                <div class="mb-3">
                    <div class="strength-track"><div id="strengthBar"></div></div>
                    <small id="strengthLabel"></small>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="confirmPassword">Confirm New Password</label>
                    <div class="password-wrapper">
                        <input type="password" class="form-control" id="confirmPassword" name="confirmPassword"
                               placeholder="Re-enter password" required autocomplete="new-password">
                        <button type="button" class="toggle-password" data-target="confirmPassword" aria-label="Show confirm password">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback" id="confirmError">Passwords do not match.</div>
                </div>

                <button type="submit" class="btn-rp" id="submitBtn">
                    <span id="btnText">Update Password</span>
                    <span id="btnSpinner" style="display:none;">
                        <i class="fa-solid fa-circle-notch fa-spin"></i>
                    </span>
                </button>
            </form>

            <?php else: ?>
            <div style="text-align:center;padding:1rem 0;">
                <i class="fa-solid fa-triangle-exclamation" style="font-size:2.5rem;color:#ef4444;margin-bottom:1rem;display:block;"></i>
                <p style="color:var(--text-muted);margin-bottom:1.5rem;">Request a new link and try again.</p>
                <a href="forgot-password.php" class="btn-rp" style="display:block;text-decoration:none;text-align:center;">
                    Request New Link
                </a>
            </div>
            <?php endif; ?>
        </div>

        <div class="rp-footer">
            <a href="login.php"><i class="fa-solid fa-arrow-left"></i> Back to Sign In</a>
        </div>

    </div>

<?php include __DIR__ . '/includes/scripts.php'; ?>
</body>
</html>