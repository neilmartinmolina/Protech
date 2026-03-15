/**
 * forgot-password.js
 * Handles the email request form on forgot-password.php
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

    function showMessage(text, type) {
        serverMsg.textContent = text;
        serverMsg.className   = type === 'success' ? 'msg-success' : 'msg-error';
    }

    function setLoading(loading) {
        submitBtn.disabled       = loading;
        btnText.style.display    = loading ? 'none' : 'inline';
        btnSpinner.style.display = loading ? 'inline-flex' : 'none';
    }

    function setInvalid(input, msgEl, message) {
        input.classList.remove('is-valid');
        input.classList.add('is-invalid');
        if (msgEl) { msgEl.textContent = message; msgEl.classList.add('d-block'); }
    }

    function setValid(input, msgEl) {
        input.classList.remove('is-invalid');
        input.classList.add('is-valid');
        if (msgEl) msgEl.classList.remove('d-block');
    }

    emailInput.addEventListener('input', () => {
        const errEl = document.getElementById('emailError');
        if (emailInput.value.trim()) {
            setValid(emailInput, errEl);
        } else {
            emailInput.classList.remove('is-valid', 'is-invalid');
            errEl.classList.remove('d-block');
        }
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        serverMsg.className = '';
        serverMsg.style.display = 'none';

        const email  = emailInput.value.trim();
        const errEl  = document.getElementById('emailError');
        const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

        if (!email || !emailRe.test(email)) {
            setInvalid(emailInput, errEl, 'Please enter a valid email address.');
            return;
        }

        setValid(emailInput, errEl);
        setLoading(true);

        try {
            const payload = new FormData();
            payload.append('action', 'request');
            payload.append('email', email);

            const res  = await fetch('forgotpassword_handler.php', { method: 'POST', body: payload });
            const data = await res.json();

            showMessage(data.message, data.success ? 'success' : 'error');

            if (data.success) {
                // Disable form after successful send to prevent spamming
                emailInput.disabled = true;
                submitBtn.disabled  = true;
            } else {
                setLoading(false);
            }
        } catch (err) {
            showMessage('Something went wrong. Please try again.', 'error');
            setLoading(false);
        }
    });
})();