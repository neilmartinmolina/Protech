/**
 * reset-password.js
 * Handles the new-password form on reset-password.php
 * Reuses the same strength-meter pattern from signup.js
 */
(function () {
    'use strict';

    const form            = document.getElementById('rpForm');
    if (!form) return;

    const submitBtn       = document.getElementById('submitBtn');
    const btnText         = document.getElementById('btnText');
    const btnSpinner      = document.getElementById('btnSpinner');
    const serverMsg       = document.getElementById('serverMessage');
    const passwordInput   = document.getElementById('password');
    const confirmInput    = document.getElementById('confirmPassword');
    const strengthBar     = document.getElementById('strengthBar');
    const strengthLabel   = document.getElementById('strengthLabel');

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

    // ── Strength meter ──────────────────────────────────────────────────────
    function measureStrength(pw) {
        let score = 0;
        if (pw.length >= 6)  score++;
        if (pw.length >= 10) score++;
        if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score++;
        if (/[0-9]/.test(pw))   score++;
        if (/[^a-zA-Z0-9]/.test(pw)) score++;
        return score;
    }

    const levels = [
        { width: '0%',   color: 'transparent', label: '' },
        { width: '25%',  color: '#ef4444',      label: 'Weak' },
        { width: '50%',  color: '#f97316',      label: 'Fair' },
        { width: '75%',  color: '#eab308',      label: 'Good' },
        { width: '100%', color: '#10b981',      label: 'Strong' },
    ];

    passwordInput.addEventListener('input', () => {
        const pw    = passwordInput.value;
        const score = pw.length === 0 ? 0 : Math.min(measureStrength(pw), 4);
        const lv    = levels[score];

        strengthBar.style.width           = lv.width;
        strengthBar.style.backgroundColor = lv.color;
        strengthLabel.textContent         = lv.label;
        strengthLabel.style.color         = lv.color;

        const errEl = document.getElementById('passwordError');
        if (pw.length >= 6) {
            passwordInput.classList.remove('is-invalid');
            passwordInput.classList.add('is-valid');
            errEl.classList.remove('d-block');
        } else if (pw.length > 0) {
            passwordInput.classList.remove('is-valid');
            passwordInput.classList.add('is-invalid');
            errEl.classList.add('d-block');
        } else {
            passwordInput.classList.remove('is-valid', 'is-invalid');
            errEl.classList.remove('d-block');
        }

        // Re-evaluate confirm if already typed
        if (confirmInput.value) confirmInput.dispatchEvent(new Event('input'));
    });

    confirmInput.addEventListener('input', () => {
        const errEl = document.getElementById('confirmError');
        if (confirmInput.value === passwordInput.value && confirmInput.value !== '') {
            confirmInput.classList.remove('is-invalid');
            confirmInput.classList.add('is-valid');
            errEl.classList.remove('d-block');
        } else if (confirmInput.value !== '') {
            confirmInput.classList.remove('is-valid');
            confirmInput.classList.add('is-invalid');
            errEl.classList.add('d-block');
        } else {
            confirmInput.classList.remove('is-valid', 'is-invalid');
            errEl.classList.remove('d-block');
        }
    });

    // ── Helpers ─────────────────────────────────────────────────────────────
    function showMessage(text, type) {
        serverMsg.textContent = text;
        serverMsg.className   = type === 'success' ? 'msg-success' : 'msg-error';
    }

    function setLoading(loading) {
        submitBtn.disabled       = loading;
        btnText.style.display    = loading ? 'none' : 'inline';
        btnSpinner.style.display = loading ? 'inline-flex' : 'none';
    }

    // ── Submit ───────────────────────────────────────────────────────────────
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        serverMsg.className = '';

        let valid = true;

        const pw      = passwordInput.value;
        const pwErr   = document.getElementById('passwordError');
        const conf    = confirmInput.value;
        const confErr = document.getElementById('confirmError');

        if (pw.length < 6) {
            passwordInput.classList.add('is-invalid');
            pwErr.textContent = 'Must be at least 6 characters.';
            pwErr.classList.add('d-block');
            valid = false;
        }

        if (conf !== pw || conf === '') {
            confirmInput.classList.add('is-invalid');
            confErr.classList.add('d-block');
            valid = false;
        }

        if (!valid) return;

        setLoading(true);

        try {
            const payload = new FormData(form);

            const res  = await fetch('forgotpassword_handler.php', { method: 'POST', body: payload });
            const data = await res.json();

            showMessage(data.message, data.success ? 'success' : 'error');

            if (data.success) {
                form.style.display = 'none';
                setTimeout(() => {
                    window.location.href = data.redirect || 'login.php';
                }, 1500);
            } else {
                setLoading(false);
            }
        } catch (err) {
            showMessage('Something went wrong. Please try again.', 'error');
            setLoading(false);
        }
    });
})();