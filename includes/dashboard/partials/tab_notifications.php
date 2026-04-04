<?php
/** @var array $notifications */
/** @var string $role */
// Notifications are already marked as read by view_data.php when this tab is loaded.
?>
<div class="table-card">
    <div class="table-card-header">
        <h5>Notifications <span class="badge-count"><?= count($notifications) ?></span></h5>
    </div>
    <div class="table-card-body">
        <?php if (!$notifications): ?>
            <div class="p-4 text-secondary" style="font-size:.9rem;">
                <i class="fa-solid fa-bell-slash me-2 opacity-50"></i>
                No notifications yet — you're all caught up.
            </div>
        <?php else: ?>
            <ul class="notif-list list-unstyled mb-0">
                <?php foreach ($notifications as $n): ?>
                    <li class="notif-item <?= (int) $n['is_read'] === 0 ? 'notif-item--unread' : '' ?>">
                        <div class="notif-dot" aria-hidden="true"></div>
                        <div class="notif-body">
                            <div class="notif-title"><?= app_sanitize($n['title']) ?></div>
                            <div class="notif-text"><?= app_sanitize($n['body']) ?></div>
                            <?php if (!empty($n['link'])): ?>
                                <a href="<?= app_sanitize($n['link']) ?>" class="notif-link">View &rarr;</a>
                            <?php endif; ?>
                        </div>
                        <div class="notif-time"><?= app_sanitize(date('M j, Y · g:i a', strtotime($n['created_at']))) ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
</div>