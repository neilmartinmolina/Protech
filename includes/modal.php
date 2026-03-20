<?php
function render_system_modal(
    string $id = 'systemModal',
    string $title = 'Confirm Action',
    string $message = 'Are you sure you want to continue?',
    string $confirmLabel = 'Confirm'
): void {
    ?>
    <div class="app-modal" id="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true">
        <div class="app-modal__backdrop" data-modal-close></div>
        <div class="app-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>Title">
            <div class="app-modal__header">
                <h3 class="app-modal__title" id="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>Title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3>
                <button class="app-modal__close" type="button" data-modal-close aria-label="Close modal">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="app-modal__body">
                <p class="app-modal__message"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></p>
                <div class="app-modal__slot"></div>
            </div>
            <div class="app-modal__footer">
                <button class="ghost-btn" type="button" data-modal-close>Cancel</button>
                <button class="action-btn app-modal__confirm" type="button"><?= htmlspecialchars($confirmLabel, ENT_QUOTES, 'UTF-8') ?></button>
            </div>
        </div>
    </div>
    <?php
}
