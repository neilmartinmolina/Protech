/**
 * forgot_password.js
 * Handles the email request form on forgot_password.php
 */
(function () {
    'use strict';

    const form       = document.getElementById('fpForm');
    if (!form) return;

    const submitBtn  = document.getElementById('submitBtn');
    const btnText    = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');
    const serverMsg  = document.getElementById('serverMessage');
    const emailInput = document.getElementById('email');
    const emailErrEl = document.getElementById('emailError');

    const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

    // ── UI helpers ────────────────────────────────────────────────────────────

    function setLoading(loading) {
        submitBtn.disabled       = loading;
        btnText.style.display    = loading ? 'none'        : 'inline';
        btnSpinner.style.display = loading ? 'inline-flex' : 'none';
    }

    function setInvalid(input, msgEl, message) {
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');
        if (msgEl) {
            msgEl.textContent = message;
            msgEl.classList.add('d-block');
        }
    }

    function setValid(input, msgEl) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
        if (msgEl) msgEl.classList.remove('d-block');
    }

    function clearFieldState(input, msgEl) {
        input.classList.remove('is-valid', 'is-invalid');
        if (msgEl) msgEl.classList.remove('d-block');
    }

    /**
     * Renders a feedback banner inside #serverMessage.
     * @param {'success'|'error'|'info'} type
     * @param {string} message
     */
    function showServerMessage(type, message) {
        const icons = {
            success : 'fa-circle-check',
            error   : 'fa-circle-exclamation',
            info    : 'fa-circle-info',
        };
        const alertClass = {
            success : 'alert-success',
            error   : 'alert-danger',
            info    : 'alert-info',
        };

        serverMsg.innerHTML = `
            <div class="alert ${alertClass[type]} d-flex align-items-center gap-2 mb-3" role="alert">
                <i class="fa-solid ${icons[type]} flex-shrink-0"></i>
                <span>${message}</span>
            </div>`;
    }

    function clearServerMessage() {
        serverMsg.innerHTML = '';
    }

    // ── Live validation ───────────────────────────────────────────────────────

    emailInput.addEventListener('input', () => {
        const val = emailInput.value.trim();

        if (!val) {
            clearFieldState(emailInput, emailErrEl);
            return;
        }

        if (emailRe.test(val)) {
            setValid(emailInput, emailErrEl);
        } else {
            setInvalid(emailInput, emailErrEl, 'Please enter a valid email address.');
        }
    });

    // ── Submit ────────────────────────────────────────────────────────────────

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        clearServerMessage();

        const email = emailInput.value.trim();

        if (!email || !emailRe.test(email)) {
            setInvalid(emailInput, emailErrEl, 'Please enter a valid email address.');
            return;
        }

        setValid(emailInput, emailErrEl);
        setLoading(true);

        try {
            const payload = new FormData();
            payload.append('action', 'request');
            payload.append('email', email);

            const res = await fetch('forgotpassword_handler.php', {
                method: 'POST',
                body: payload,
            });

            if (!res.ok) {
                throw new Error(`Server returned ${res.status}`);
            }

            const data = await res.json();

            if (data.success) {
                showServerMessage(
                    'success',
                    data.message || 'If that email is registered, a reset link was sent.'
                );
                // Lock form — prevents spam re-submits after a successful request
                emailInput.disabled = true;
                submitBtn.disabled  = true;
                btnText.style.display    = 'inline';
                btnSpinner.style.display = 'none';
            } else {
                showServerMessage(
                    'error',
                    data.message || 'Unable to send reset link. Please try again.'
                );
                setLoading(false);
            }

        } catch (err) {
            console.error('[forgot_password]', err);
            showServerMessage(
                'error',
                'Something went wrong. Please check your connection and try again.'
            );
            setLoading(false);
        }
    });

})();