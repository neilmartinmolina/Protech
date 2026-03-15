<?php
require_once __DIR__ . '/config.php';

$pageTitle   = 'Sign Up - ProTech';
$pageCss     = ['signup.css'];
$pageScripts = ['js/signup.js'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/header.php'; ?>
</head>
<body>
    <div class="signup-wrapper">

        <div class="signup-header">
            <a href="index.php" class="logo">
                <i class="fa-solid fa-microchip"></i> Pro<span>Tech</span>
            </a>
            <h1>Create your account</h1>
            <p>Join ProTech and explore premium technology</p>
        </div>

        <div class="signup-card">
            <div id="serverMessage"></div>

            <form id="signupForm" novalidate enctype="multipart/form-data">
                <div class="row">
                    <div class="col-6 mb-3">
                        <label class="form-label" for="firstName">First name</label>
                        <input type="text" class="form-control" id="firstName" name="firstName"
                               placeholder="John" required autocomplete="given-name">
                        <div class="invalid-feedback">Required.</div>
                    </div>
                    <div class="col-6 mb-3">
                        <label class="form-label" for="lastName">Last name</label>
                        <input type="text" class="form-control" id="lastName" name="lastName"
                               placeholder="Doe" required autocomplete="family-name">
                        <div class="invalid-feedback">Required.</div>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="username">Username</label>
                    <input type="text" class="form-control" id="username" name="username"
                           placeholder="johndoe" required autocomplete="username">
                    <div class="invalid-feedback" id="usernameError">3–50 chars, letters/numbers/underscores only.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input type="text" class="form-control" id="email" name="email"
                           placeholder="john@example.com" required autocomplete="email">
                    <div class="invalid-feedback" id="emailError">Please enter a valid email.</div>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="role">Register as</label>
                    <select class="form-control" id="role" name="role" required>
                        <option value="customer">Customer</option>
                        <option value="seller">Seller</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label" for="avatar">Profile photo</label>
                    <input type="file" class="form-control" id="avatar" name="avatar" accept="image/*">
                    <div class="invalid-feedback" id="avatarError">Please upload a valid image up to 2MB.</div>
                </div>

                <div class="mb-3" id="storeNameWrap" style="display: none;">
                    <label class="form-label" for="storeName">Store name</label>
                    <input type="text" class="form-control" id="storeName" name="storeName"
                           placeholder="Your store name">
                    <div class="invalid-feedback" id="storeNameError">Store name is required for sellers.</div>
                </div>

                <div class="mb-1">
                    <label class="form-label" for="password">Password</label>
                    <div class="password-wrapper">
                        <input type="password" class="form-control" id="password" name="password"
                               placeholder="Min. 6 characters" minlength="6" required autocomplete="new-password">
                        <button type="button" class="toggle-password" data-target="password" aria-label="Show password">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback" id="passwordError">Must be at least 6 characters.</div>
                </div>

                <div class="mb-3">
                    <div class="strength-track">
                        <div id="strengthBar"></div>
                    </div>
                    <small id="strengthLabel"></small>
                </div>

                <div class="mb-4">
                    <label class="form-label" for="confirmPassword">Confirm password</label>
                    <div class="password-wrapper">
                        <input type="password" class="form-control" id="confirmPassword" name="confirmPassword"
                               placeholder="Re-enter password" required autocomplete="new-password">
                        <button type="button" class="toggle-password" data-target="confirmPassword" aria-label="Show confirm password">
                            <i class="fa-regular fa-eye"></i>
                        </button>
                    </div>
                    <div class="invalid-feedback" id="confirmError">Passwords do not match.</div>
                </div>

                <button type="submit" class="btn-signup" id="submitBtn">Create Account</button>
            </form>
        </div>

        <div class="signup-footer">
            Already have an account? <a href="login.php">Sign in</a>
        </div>

    </div>

<?php include __DIR__ . '/scripts.php'; ?>
</body>
</html>
