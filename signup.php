<?php session_start();
// No closing ?> on purpose — any whitespace after ?> gets sent to the browser
// and corrupts the JSON response from server.php causing "network error"
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - ProTech</title>
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
            --surface-light: #1c1c1c;
            --border: #2a2a2a;
            --text-primary: #ffffff;
            --text-secondary: #b0b0b0;
            --text-muted: #666;
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 16px;
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
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle at 70% 30%, var(--primary-glow) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }

        .signup-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 480px;
        }

        .signup-header { text-align: center; margin-bottom: 2rem; }

        .signup-header .logo {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--primary);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .signup-header .logo span { color: var(--text-primary); }

        .signup-header h1 {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }

        .signup-header p { color: var(--text-muted); font-size: 0.9rem; }

        .signup-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 2rem;
            box-shadow: 0 16px 48px rgba(0, 0, 0, 0.4);
        }

        .form-label {
            font-weight: 500;
            font-size: 0.85rem;
            color: var(--text-secondary);
            margin-bottom: 0.4rem;
        }

        .form-control {
            background: var(--surface-light);
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            color: var(--text-primary);
            padding: 0.65rem 0.85rem;
            font-size: 0.9rem;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .form-control:focus {
            background: var(--surface-light);
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--primary-glow);
            color: var(--text-primary);
        }

        .form-control::placeholder { color: var(--text-muted); }

        .form-control.is-invalid {
            border-color: #ef4444 !important;
            background: var(--surface-light);
            background-image: none;
        }

        .form-control.is-valid {
            border-color: #10b981 !important;
            background: var(--surface-light);
            background-image: none;
        }

        .invalid-feedback { color: #f87171; font-size: 0.78rem; }
        .valid-feedback   { color: #34d399; font-size: 0.78rem; }

        .password-wrapper { position: relative; }
        .password-wrapper .form-control { padding-right: 2.75rem; }

        .toggle-password {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--text-muted);
            font-size: 0.95rem;
            padding: 0;
            line-height: 1;
            z-index: 10;
            transition: color 0.15s;
        }

        .toggle-password:hover { color: var(--primary); }

        .strength-track {
            background: var(--surface-light);
            border-radius: 3px;
            height: 4px;
            margin-top: 0.5rem;
        }

        #strengthBar {
            height: 4px;
            border-radius: 3px;
            width: 0%;
            transition: width 0.3s, background-color 0.3s;
        }

        #strengthLabel {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
            display: block;
        }

        .btn-signup {
            width: 100%;
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: var(--radius-sm);
            padding: 0.75rem;
            font-weight: 600;
            font-size: 1rem;
            margin-top: 0.5rem;
            cursor: pointer;
            transition: background 0.2s, transform 0.2s, box-shadow 0.2s;
        }

        .btn-signup:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(255, 115, 21, 0.25);
        }

        .btn-signup:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .signup-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: var(--text-muted);
            font-size: 0.85rem;
        }

        .signup-footer a {
            color: var(--primary);
            text-decoration: none;
            font-weight: 600;
        }

        .signup-footer a:hover { text-decoration: underline; }

        #serverMessage {
            display: none;
            border-radius: var(--radius-sm);
            padding: 0.75rem 1rem;
            margin-bottom: 1.25rem;
            font-size: 0.85rem;
            font-weight: 500;
        }

        #serverMessage.msg-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.2);
            color: #34d399;
        }

        #serverMessage.msg-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.2);
            color: #f87171;
        }
    </style>
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

            <form id="signupForm" novalidate>
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

    <script>
    (() => {
        'use strict';

        const form          = document.getElementById('signupForm');
        const emailInput    = document.getElementById('email');
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');
        const confirmInput  = document.getElementById('confirmPassword');
        const strengthBar   = document.getElementById('strengthBar');
        const strengthLabel = document.getElementById('strengthLabel');
        const submitBtn     = document.getElementById('submitBtn');
        const serverMsg     = document.getElementById('serverMessage');

        const setValid   = el => { el.classList.remove('is-invalid'); el.classList.add('is-valid'); };
        const setInvalid = el => { el.classList.remove('is-valid');   el.classList.add('is-invalid'); };
        const clearState = el => el.classList.remove('is-valid', 'is-invalid');

        function showMessage(text, type) {
            serverMsg.textContent   = text;
            serverMsg.className     = type === 'success' ? 'msg-success' : 'msg-error';
            serverMsg.style.display = 'block';
        }

        const isValidEmail    = v => /^[^\s@]+@[^\s@]+\.[a-zA-Z]{2,}$/.test(v.trim());
        const isValidUsername = v => /^[a-zA-Z0-9_]{3,50}$/.test(v.trim());

        emailInput.addEventListener('input', () => {
            const val   = emailInput.value.trim();
            const errEl = document.getElementById('emailError');
            if (!val) { clearState(emailInput); return; }
            if (isValidEmail(val)) {
                setValid(emailInput);
            } else {
                setInvalid(emailInput);
                if (!val.includes('@'))                     errEl.textContent = "Missing '@' — e.g. name@example.com";
                else if (!val.split('@')[1]?.includes('.')) errEl.textContent = "Missing domain dot — e.g. name@example.com";
                else                                        errEl.textContent = "Please enter a valid email.";
            }
        });

        usernameInput.addEventListener('input', () => {
            const val = usernameInput.value.trim();
            if (!val) { clearState(usernameInput); return; }
            isValidUsername(val) ? setValid(usernameInput) : setInvalid(usernameInput);
        });

        function getStrength(pw) {
            let s = 0;
            if (pw.length >= 6)          s++;
            if (pw.length >= 10)         s++;
            if (/[A-Z]/.test(pw))        s++;
            if (/[0-9]/.test(pw))        s++;
            if (/[^A-Za-z0-9]/.test(pw)) s++;
            return s;
        }

        passwordInput.addEventListener('input', () => {
            const val = passwordInput.value;
            if (!val) {
                clearState(passwordInput);
                strengthBar.style.width   = '0%';
                strengthLabel.textContent = '';
                return;
            }
            val.length < 6 ? setInvalid(passwordInput) : setValid(passwordInput);

            const levels = [
                { label: '',            color: 'transparent', pct: '0%'   },
                { label: 'Weak',        color: '#ef4444',     pct: '20%'  },
                { label: 'Weak',        color: '#ef4444',     pct: '40%'  },
                { label: 'Fair',        color: '#f59e0b',     pct: '60%'  },
                { label: 'Strong',      color: '#10b981',     pct: '80%'  },
                { label: 'Very Strong', color: '#3b82f6',     pct: '100%' },
            ];
            const lvl = levels[getStrength(val)];
            strengthBar.style.width           = lvl.pct;
            strengthBar.style.backgroundColor = lvl.color;
            strengthLabel.textContent         = lvl.label;
            strengthLabel.style.color         = lvl.color;

            if (confirmInput.value) validateConfirm();
        });

        function validateConfirm() {
            const val = confirmInput.value;
            if (!val) { clearState(confirmInput); return; }
            val === passwordInput.value ? setValid(confirmInput) : setInvalid(confirmInput);
        }
        confirmInput.addEventListener('input', validateConfirm);

        document.querySelectorAll('.toggle-password').forEach(btn => {
            btn.addEventListener('click', () => {
                const input    = document.getElementById(btn.getAttribute('data-target'));
                const isHidden = input.type === 'password';
                input.type     = isHidden ? 'text' : 'password';
                btn.innerHTML  = isHidden
                    ? '<i class="fa-regular fa-eye-slash"></i>'
                    : '<i class="fa-regular fa-eye"></i>';
            });
        });

        form.addEventListener('submit', async e => {
            e.preventDefault();
            serverMsg.style.display = 'none';

            const firstName = document.getElementById('firstName');
            const lastName  = document.getElementById('lastName');
            let valid = true;

            if (!firstName.value.trim())               { setInvalid(firstName);     valid = false; } else setValid(firstName);
            if (!lastName.value.trim())                { setInvalid(lastName);      valid = false; } else setValid(lastName);
            if (!isValidUsername(usernameInput.value)) { setInvalid(usernameInput); valid = false; } else setValid(usernameInput);
            if (!isValidEmail(emailInput.value))       { setInvalid(emailInput);    valid = false; } else setValid(emailInput);
            if (passwordInput.value.length < 6)        { setInvalid(passwordInput); valid = false; } else setValid(passwordInput);
            if (!confirmInput.value || confirmInput.value !== passwordInput.value) {
                setInvalid(confirmInput); valid = false;
            } else {
                setValid(confirmInput);
            }

            if (!valid) return;

            submitBtn.disabled    = true;
            submitBtn.textContent = 'Creating account…';

            try {
                const res = await fetch('server.php', {
                    method: 'POST',
                    body:   new FormData(form)
                });

                const contentType = res.headers.get('content-type') || '';
                if (!contentType.includes('application/json')) {
                    throw new Error('Unexpected server response.');
                }

                const data = await res.json();

                if (data.success) {
                    showMessage(data.message, 'success');
                    form.reset();
                    form.querySelectorAll('.form-control').forEach(clearState);
                    strengthBar.style.width   = '0%';
                    strengthLabel.textContent = '';
                    submitBtn.textContent     = 'Account Created!';
                    // setTimeout(() => window.location.href = 'login.php', 2000);
                } else {
                    const msg = data.errors ? data.errors.join(' ') : data.message;
                    showMessage(msg, 'error');
                    submitBtn.disabled    = false;
                    submitBtn.textContent = 'Create Account';
                }

            } catch (err) {
                showMessage('Network error. Please try again.', 'error');
                submitBtn.disabled    = false;
                submitBtn.textContent = 'Create Account';
            }
        });

    })();
    </script>
</body>
</html>