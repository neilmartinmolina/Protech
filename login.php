<?php
require_once __DIR__ . '/config.php';

$pageTitle   = 'Sign In - ProTech';
$pageCss     = ['login.css'];
$pageScripts = ['js/login.js'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/header.php'; ?>
</head>
<body>
    <div class="login-wrapper">

        <div class="login-header">
            <a href="index.php" class="logo">
                <i class="fa-solid fa-microchip"></i> Pro<span>Tech</span>
            </a>
            <h1>Welcome back</h1>
            <p>Sign in to your ProTech account</p>
        </div>

        <div class="login-card">
            <div id="serverMessage"></div>

            <form id="loginForm" novalidate>

                <div class="mb-3">
                    <label class="form-label" for="identifier">Email or Username</label>
                    <input type="text" class="form-control" id="identifier" name="identifier"
                           placeholder="john@example.com" required autocomplete="username">
                    <div class="invalid-feedback" id="identifierError">Please enter your email or username.</div>
                </div>

                <div class="mb-1">
                    <div class="password-label-row">
                        <label class="form-label" for="password">Password</label>
                        <a href="forgotpassword.php" class="forgot-link">Forgot password?</a>
                    </div>
                    <div class="password-wrapper">
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Enter your password" required autocomplete="current-password">
                        <button type="button" class="toggle-password" data-target="password" aria-label="Show password">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback" id="passwordError">Please enter your password.</div>
                </div>

                <div class="mb-4 remember-row">
                    <label class="remember-label">
                        <input type="checkbox" id="rememberMe" name="rememberMe">
                        <span class="checkmark"></span>
                        Remember me for 30 days
                    </label>
                </div>

                <button type="submit" class="btn-login" id="submitBtn">
                    <span id="btnText">Sign In</span>
                    <span id="btnSpinner" class="btn-spinner" style="display:none;">
                        <i class="fa-solid fa-circle-notch fa-spin"></i>
                    </span>
                </button>

            </form>
        </div>

        <div class="login-footer">
            Don't have an account? <a href="signup.php">Sign up</a>
        </div>

    </div>

<?php include __DIR__ . '/scripts.php'; ?>
</body>
</html>