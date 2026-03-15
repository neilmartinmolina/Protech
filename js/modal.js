(() => {
    'use strict';

    function getModal(target) {
        if (!target) return null;
        return document.querySelector(target);
    }

    function closeModal(modal) {
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    }

    function openModal(modal, trigger) {
        if (!modal) return;

        const title = trigger?.dataset.modalTitle;
        const message = trigger?.dataset.modalMessage;
        const confirmLabel = trigger?.dataset.modalConfirm;
        const payload = trigger?.dataset.modalPayload;

        if (title) {
            const titleEl = modal.querySelector('.app-modal__title');
            if (titleEl) titleEl.textContent = title;
        }

        if (message) {
            const messageEl = modal.querySelector('.app-modal__message');
            if (messageEl) messageEl.textContent = message;
        }

        if (confirmLabel) {
            const confirmEl = modal.querySelector('.app-modal__confirm');
            if (confirmEl) confirmEl.textContent = confirmLabel;
        }

        const form = modal.querySelector('form');
        if (form) {
            form.reset();
            form.querySelectorAll('input[type="hidden"]').forEach(input => {
                if (input.name === 'action' && input.defaultValue) input.value = input.defaultValue;
            });
        }

        if (form && payload) {
            try {
                const values = JSON.parse(payload);
                Object.entries(values).forEach(([key, value]) => {
                    const input = form.querySelector(`[name="${key}"]`);
                    if (!input) return;
                    if (input.type === 'checkbox') {
                        input.checked = value === true || value === '1' || value === 1;
                    } else {
                        input.value = value;
                    }
                });
            } catch (error) {
                console.error('Invalid modal payload', error);
            }
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
    }

    document.addEventListener('click', event => {
        const trigger = event.target.closest('[data-modal-target]');
        if (trigger) {
            event.preventDefault();
            openModal(getModal(trigger.dataset.modalTarget), trigger);
            return;
        }

        const closeTrigger = event.target.closest('[data-modal-close]');
        if (closeTrigger) {
            closeModal(closeTrigger.closest('.app-modal'));
            return;
        }

        const confirmTrigger = event.target.closest('.app-modal__confirm');
        if (confirmTrigger) {
            const modal = confirmTrigger.closest('.app-modal');
            const form = modal?.querySelector('form');
            if (form) form.submit();
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.app-modal.is-open').forEach(closeModal);
    });
})();
