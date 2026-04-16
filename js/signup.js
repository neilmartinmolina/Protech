(() => {
    'use strict';

    const form          = document.getElementById('signupForm');
    const emailInput    = document.getElementById('email');
    const usernameInput = document.getElementById('username');
    const roleInput     = document.getElementById('role');
    const storeNameWrap = document.getElementById('storeNameWrap');
    const storeNameInput = document.getElementById('storeName');
    const avatarInput   = document.getElementById('avatar');
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
    const isValidAvatar   = file => !file || (file.type.startsWith('image/') && file.size <= 2 * 1024 * 1024);
    const toggleStoreName = () => {
        const isSeller = roleInput.value === 'seller';
        storeNameWrap.style.display = isSeller ? 'block' : 'none';
        storeNameInput.required = isSeller;
        if (!isSeller) {
            clearState(storeNameInput);
            storeNameInput.value = '';
        }
    };

    roleInput.addEventListener('change', toggleStoreName);
    toggleStoreName();

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

    avatarInput?.addEventListener('change', () => {
        const file = avatarInput.files?.[0];
        if (!file) { clearState(avatarInput); return; }
        isValidAvatar(file) ? setValid(avatarInput) : setInvalid(avatarInput);
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
        if (!isValidAvatar(avatarInput.files?.[0])) { setInvalid(avatarInput); valid = false; } else if (avatarInput.files?.[0]) { setValid(avatarInput); }
        if (roleInput.value === 'seller' && !storeNameInput.value.trim()) {
            setInvalid(storeNameInput); valid = false;
        } else if (roleInput.value === 'seller') {
            setValid(storeNameInput);
        }
        if (passwordInput.value.length < 6)        { setInvalid(passwordInput); valid = false; } else setValid(passwordInput);
        if (!confirmInput.value || confirmInput.value !== passwordInput.value) {
            setInvalid(confirmInput); valid = false;
        } else {
            setValid(confirmInput);
        }

        if (!valid) return;

        submitBtn.disabled    = true;
        submitBtn.textContent = 'Creating account…';

        let res, data;

        try {
            res = await fetch('server.php', {
                method: 'POST',
                body:   new FormData(form)
            });
        } catch (networkErr) {
            showMessage('Network error. Check your connection and try again.', 'error');
            submitBtn.disabled    = false;
            submitBtn.textContent = 'Create Account';
            return;
        }

        try {
            const contentType = res.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                throw new Error('Unexpected server response.');
            }
            data = await res.json();
        } catch (parseErr) {
            showMessage('Server returned an unexpected response. Please try again.', 'error');
            submitBtn.disabled    = false;
            submitBtn.textContent = 'Create Account';
            return;
        }

        if (data.success) {
            await Swal.fire({
                icon: 'success',
                title: 'Account created',
                text: data.message || 'Your account has been created successfully.',
                confirmButtonText: 'Go to Login'
            });
            window.location.href = 'login.php';
        } else {
            const msg = data.errors ? data.errors.join(' ') : (data.message || 'Something went wrong.');
            await Swal.fire({
                icon: 'error',
                title: 'Signup failed',
                text: msg,
                confirmButtonText: 'OK'
            });
            submitBtn.disabled    = false;
            submitBtn.textContent = 'Create Account';
        }
    });

})();
