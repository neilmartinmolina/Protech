<?php
require_once __DIR__ . '/config.php';

$pageTitle   = 'Forgot Password - ProTech';
$pageCss     = ['auth.css'];
$pageScripts = ['js/forgot_password.js'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
</head>
<body>
    <div class="fp-wrapper">

        <div class="fp-header">
            <a href="index.php" class="logo">
                <i class="fa-solid fa-microchip"></i> Pro<span>Tech</span>
            </a>
            <h1>Reset your password</h1>
            <p>Enter your email and we'll send you a reset link</p>
        </div>

        <div class="fp-card">
            <div id="serverMessage"></div>

            <form id="fpForm" novalidate>

                <div class="mb-4">
                    <label class="form-label" for="email">Email address</label>
                    <input type="text" class="form-control" id="email" name="email"
                           placeholder="john@example.com" required autocomplete="email">
                    <div class="invalid-feedback" id="emailError">Please enter a valid email address.</div>
                </div>

                <button type="submit" class="btn-fp" id="submitBtn">
                    <span id="btnText">Send Reset Link</span>
                    <span id="btnSpinner" style="display:none;">
                        <i class="fa-solid fa-circle-notch fa-spin"></i>
                    </span>
                </button>

            </form>
        </div>

        <div class="fp-footer">
            <a href="login.php"><i class="fa-solid fa-arrow-left"></i> Back to Sign In</a>
        </div>

    </div>

<?php include __DIR__ . '/includes/scripts.php'; ?>
</body>
</html>