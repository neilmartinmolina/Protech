<?php
    
declare(strict_types=1);

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/includes/dashboard/post_handler.php';
require_once __DIR__ . '/includes/dashboard/view_data.php';

$user = app_require_login();
$conn = app_db();
$role = $user['role'] ?? 'customer';

if ($role === 'customer') {
    header('Location: index.php');
    exit;
}

$postResult = dashboard_process_post($conn, $user, $role);
$flash      = $postResult['flash'];
$user       = $postResult['user'];
$role       = $postResult['role'];

$view = dashboard_build_view_data($conn, $user, $role);
$currentUser = $user;
extract($view, EXTR_SKIP);

$pageTitle  = ucfirst($role) . ' Dashboard — ProTech';
$pageCss    = ['admin.css', 'dashboard.css'];
$pageCssExt = ['https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css'];

$dashboardPayload = [
    'role'   => $role,
    'flash'  => $flash,
    'categories' => $categories,
    'brands'     => $brands,
    'adminOrdersLabels'      => array_keys($adminOrdersByDay),
    'adminOrdersData'        => array_values($adminOrdersByDay),
    'adminStatusLabels'      => array_map('ucfirst', array_keys($adminStatusCounts)),
    'adminStatusData'        => array_values($adminStatusCounts),
    'sellerRevenueLabels'    => array_keys($sellerRevenueByDay),
    'sellerRevenueData'      => array_values($sellerRevenueByDay),
    'sellerCategoryLabels'   => array_keys($sellerCategoryCounts),
    'sellerCategoryData'     => array_values($sellerCategoryCounts),
    'dataTables'             => [
        ['selector' => '#activityLogsTable', 'order' => [[0, 'desc']], 'disabledTargets' => [], 'placeholder' => 'Search logs...'],
        ['selector' => '#sellerRequestsTable', 'order' => [[5, 'asc']], 'disabledTargets' => [6, 7], 'placeholder' => 'Search applications...'],
        ['selector' => '#sellerProductsTable', 'order' => [[0, 'desc']], 'disabledTargets' => [7], 'placeholder' => 'Search products...'],
        ['selector' => '#adminOrdersTable',    'order' => [[6, 'desc']], 'disabledTargets' => [7], 'placeholder' => 'Search orders...'],
        ['selector' => '#sellerOrdersTable',   'order' => [[5, 'desc']], 'disabledTargets' => [6], 'placeholder' => 'Search orders...'],
        ['selector' => '#productsTable',       'order' => [[0, 'desc']], 'disabledTargets' => [8],  'placeholder' => 'Search all products...'],
        ['selector' => '#adminUsersTable',     'order' => [[1, 'desc']], 'disabledTargets' => [9], 'placeholder' => 'Search users...'],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
</head>

    <!-- Test webhook deployment notification -->
     
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/includes/dashboard/partials/sidebar.php'; ?>

    <div class="admin-main">
        <?php include __DIR__ . '/includes/dashboard/partials/topbar.php'; ?>

        <div class="admin-content">
            <?php if ($role === 'admin' || $role === 'superadmin'): ?>
                <?php include __DIR__ . '/includes/dashboard/partials/tabs_admin.php'; ?>
            <?php else: ?>
                <?php include __DIR__ . '/includes/dashboard/partials/tabs_seller.php'; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/dashboard/partials/modals.php'; ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="js/modal.js"></script>
<script <?= csp_nonce_attr() ?>>
window.__DASHBOARD__ = <?= json_encode($dashboardPayload, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="js/dashboard.js"></script>
</body>
</html>
