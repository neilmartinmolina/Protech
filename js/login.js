/**
 * login.js – client-side validation & AJAX submit for login.php
 * Mirrors the patterns used in signup.js.
 */
(function () {
    'use strict';

    const form        = document.getElementById('loginForm');
    const submitBtn   = document.getElementById('submitBtn');
    const btnText     = document.getElementById('btnText');
    const btnSpinner  = document.getElementById('btnSpinner');
    const serverMsg   = document.getElementById('serverMessage');

    const identifierInput = document.getElementById('identifier');
    const passwordInput   = document.getElementById('password');

    // ── Password visibility toggles ────────────────────────────────────────
    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = document.getElementById(btn.dataset.target);
            const icon   = btn.querySelector('i');
            if (target.type === 'password') {
                target.type = 'text';
                icon.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                target.type = 'password';
                icon.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    });

    // ── Helpers ─────────────────────────────────────────────────────────────
    function setValid(input) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
    }

    function setInvalid(input, msgEl) {
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');
        if (msgEl) msgEl.classList.add('d-block');
    }

    function clearState(input, msgEl) {
        input.classList.remove('is-valid', 'is-invalid');
        if (msgEl) msgEl.classList.remove('d-block');
    }

    function hideServerMessage() {
        serverMsg.style.display = 'none';
        serverMsg.className     = '';
    }

    function setLoading(loading) {
        submitBtn.disabled    = loading;
        btnText.style.display = loading ? 'none' : 'inline';
        btnSpinner.style.display = loading ? 'inline-flex' : 'none';
    }

    // ── Live validation ─────────────────────────────────────────────────────
    identifierInput.addEventListener('input', () => {
        const val = identifierInput.value.trim();
        const errEl = document.getElementById('identifierError');
        if (val.length > 0) {
            setValid(identifierInput);
            errEl.classList.remove('d-block');
        } else {
            clearState(identifierInput, errEl);
        }
    });

    passwordInput.addEventListener('input', () => {
        const val = passwordInput.value;
        const errEl = document.getElementById('passwordError');
        if (val.length > 0) {
            setValid(passwordInput);
            errEl.classList.remove('d-block');
        } else {
            clearState(passwordInput, errEl);
        }
    });

    // ── Form validation ─────────────────────────────────────────────────────
    function validate() {
        let valid = true;

        const identifier = identifierInput.value.trim();
        const identifierErr = document.getElementById('identifierError');
        if (!identifier) {
            setInvalid(identifierInput, identifierErr);
            identifierErr.textContent = 'Please enter your email or username.';
            valid = false;
        } else {
            setValid(identifierInput);
        }

        const password = passwordInput.value;
        const passwordErr = document.getElementById('passwordError');
        if (!password) {
            setInvalid(passwordInput, passwordErr);
            passwordErr.textContent = 'Please enter your password.';
            valid = false;
        } else {
            setValid(passwordInput);
        }

        return valid;
    }

    // ── Submit ───────────────────────────────────────────────────────────────
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideServerMessage();

        if (!validate()) return;

        setLoading(true);

        try {
            const payload = new FormData(form);

            const res  = await fetch('login_handler.php', {
                method: 'POST',
                body: payload,
            });

            const data = await res.json();

            if (data.success) {
                await Swal.fire({
                    icon: 'success',
                    title: 'Login successful',
                    text: data.message || 'Redirecting to your account...',
                    confirmButtonText: 'Continue'
                });
                window.location.href = data.redirect || 'index.php';
            } else {
                await Swal.fire({
                    icon: 'error',
                    title: 'Login failed',
                    text: data.message || 'Invalid credentials. Please try again.',
                    confirmButtonText: 'OK'
                });
                setLoading(false);
            }
        } catch (err) {
            await Swal.fire({
                icon: 'error',
                title: 'Request failed',
                text: 'Something went wrong. Please try again.',
                confirmButtonText: 'OK'
            });
            setLoading(false);
        }
    });
})();