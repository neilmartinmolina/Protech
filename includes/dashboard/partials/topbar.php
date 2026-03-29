<?php
/** @var array $user */
/** @var string $role */
/** @var array $allowedTabs */
/** @var string $tab */
?>
<div class="admin-topbar">
    <div>
        <h1><?= app_sanitize($allowedTabs[$tab][0]) ?></h1>
        <span class="breadcrumb-text"><?= $role === 'admin' ? 'Overview of platform users, sellers, products, orders, and approvals' : 'Store: ' . app_sanitize($user['store_name'] ?: $user['username']) ?></span>
    </div>
    <div class="topbar-actions">
        <button class="topbar-btn d-lg-none" id="sidebarToggle"><i class="fa-solid fa-bars"></i></button>
        <a href="index.php" class="topbar-btn text-decoration-none"><i class="fa-solid fa-house"></i></a>
        <a href="logout.php" class="topbar-btn text-decoration-none"><i class="fa-solid fa-arrow-right-from-bracket"></i></a>
    </div>
</div>
