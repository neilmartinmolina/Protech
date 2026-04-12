<?php
require_once dirname(__DIR__) . '/app.php';

$navUser = app_current_user();
$cartCount = app_cart_count();
$currentPage = basename($_SERVER['PHP_SELF'] ?? '');
$dashboardLabel = 'Dashboard';
$showDashboardLink = false;
$avatarUrl = app_avatar_url($navUser);
if ($navUser && ($navUser['role'] ?? '') === 'seller') {
    $dashboardLabel = 'Dashboard';
    $showDashboardLink = true;
} elseif ($navUser && (($navUser['role'] ?? '') === 'admin' || ($navUser['role'] ?? '') === 'superadmin')) {
    $dashboardLabel = 'Dashboard';
    $showDashboardLink = true;
}
?>
<nav class="navbar navbar-expand-lg pt-navbar" id="mainNavbar">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fa-solid fa-microchip" style="font-size: 1.2rem;"></i>
            Pro<span>Tech</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'index.php' ? 'active' : '' ?>" href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="index.php#about">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'product.php' ? 'active' : '' ?>" href="product.php">Products</a>
                </li>
                <?php if ($navUser && $showDashboardLink): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentPage === 'dashboard.php' ? 'active' : '' ?>" href="dashboard.php"><?= app_sanitize($dashboardLabel) ?></a>
                    </li>
                <?php endif; ?>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <?php if ($navUser): ?>
                    <a href="cart.php" class="nav-link position-relative" title="Cart">
                        <i class="fa-solid fa-cart-shopping fa-lg" style="color: var(--text-secondary);"></i>
                        <?php if ($cartCount > 0): ?>
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-warning text-dark"><?= (int) $cartCount ?></span>
                        <?php endif; ?>
                    </a>
                    <div class="dropdown">
                        <a href="#" role="button" class="nav-link" data-bs-toggle="dropdown" aria-expanded="false" title="<?= app_sanitize($navUser['email']) ?>">
                            <?php if ($avatarUrl): ?>
                                <img src="<?= app_sanitize($avatarUrl) ?>" alt="Profile" style="width: 28px; height: 28px; border-radius: 50%; object-fit: cover;">
                            <?php else: ?>
                                <i class="fa-solid fa-user fa-lg" style="color: var(--text-secondary);"></i>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow">
                            <?php if ($showDashboardLink): ?>
                                <li><a class="dropdown-item" href="dashboard.php"><i class="fa-solid fa-table-columns me-2"></i><?= app_sanitize($dashboardLabel) ?></a></li>
                            <?php endif; ?>
                            <li><a class="dropdown-item" href="myprofile.php"><i class="fa-solid fa-user me-2"></i>My Profile</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Logout</a></li>
                        </ul>
                    </div>
                <?php else: ?>
                    <a href="login.php" class="nav-link" title="Login"><i class="fa-solid fa-right-to-bracket fa-lg" style="color: var(--text-secondary);"></i></a>
                    <a href="signup.php" class="nav-cta">Get Started</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>

<script>
window.addEventListener('scroll', function() {
    const nav = document.getElementById('mainNavbar');
    if (window.scrollY > 50) {
        nav.classList.add('scrolled');
    } else {
        nav.classList.remove('scrolled');
    }
});
</script>
