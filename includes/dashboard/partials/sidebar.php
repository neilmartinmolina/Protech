<?php
/** @var array $user */
/** @var string $role */
/** @var array $allowedTabs */
/** @var string $tab */
/** @var string $avatarUrl */
/** @var array $adminStats */
?>
<aside class="admin-sidebar" id="sidebar">
    <a href="index.php" class="sidebar-brand">
        <div class="brand-icon"><i class="fa-solid fa-microchip"></i></div>
        <div class="brand-text">Pro<span>Tech</span></div>
    </a>
    <div class="sidebar-section-label"><?= app_sanitize(ucfirst($role)) ?> Panel</div>
    <ul class="sidebar-nav">
        <?php foreach ($allowedTabs as $key => [$label, $icon]): ?>
            <?php if ($key === 'about_dev') continue; ?>
            <li>
                <a href="dashboard.php?tab=<?= app_sanitize($key) ?>" class="nav-link <?= $tab === $key ? 'active' : '' ?>">
                    <i class="<?= app_sanitize($icon) ?>"></i> <?= app_sanitize($label) ?>
                    <?php if ($key === 'sellers' && ($adminStats['pending_sellers'] ?? 0) > 0): ?>
                        <span class="badge bg-warning text-dark ms-auto" style="font-size:.65rem;"><?= (int) $adminStats['pending_sellers'] ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if ($role === 'admin'): ?>
    <ul class="sidebar-nav mt-2">
        <li>
            <a href="aboutdev.php" class="nav-link <?= basename($_SERVER['PHP_SELF']) === 'aboutdev.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-circle-user"></i> About Dev
            </a>
        </li>
    </ul>
    <?php endif; ?>

    <div class="sidebar-footer">
    <div class="sidebar-footer">
        <div class="dropdown">
            <a href="#" class="user-card dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                <?php if ($avatarUrl): ?>
                    <img src="<?= app_sanitize($avatarUrl) ?>" alt="avatar" class="user-avatar">
                <?php else: ?>
                    <div class="user-avatar d-flex align-items-center justify-content-center" style="background:var(--primary-glow);color:var(--primary);"><i class="fa-solid fa-user"></i></div>
                <?php endif; ?>
                <div class="user-info">
                    <div class="user-name"><?= app_sanitize($user['firstName'] . ' ' . $user['lastName']) ?></div>
                    <div class="user-role"><?= app_sanitize(ucfirst($role)) ?></div>
                </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-dark shadow">
                <li><a class="dropdown-item" href="myprofile.php"><i class="fa-solid fa-user me-2"></i>View Profile</a></li>
                <li><a class="dropdown-item" href="myprofile.php?section=security"><i class="fa-solid fa-gear me-2"></i>Settings</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Logout</a></li>
            </ul>
        </div>
    </div>
</aside>
