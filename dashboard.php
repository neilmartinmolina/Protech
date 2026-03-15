<?php
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/modal.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

$user = app_require_login();
$conn = app_db();
$flash = null;
$role = $user['role'] ?? 'customer';

if ($role === 'customer') {
    header('Location: index.php');
    exit;
}

function dashboard_mailer(): PHPMailer
{
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;
    $mail->setFrom(SMTP_USER, FROM_NAME);
    $mail->isHTML(true);
    return $mail;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'approve_seller' && app_is_admin($user)) {
        $sellerId = (int) ($_POST['seller_id'] ?? 0);
        $stmt = $conn->prepare("
            SELECT id, first_name, last_name, email, username, store_name, temp_password
            FROM users
            WHERE id = ? AND role = 'seller' AND seller_status = 'pending'
            LIMIT 1
        ");
        $stmt->bind_param('i', $sellerId);
        $stmt->execute();
        $seller = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($seller) {
            $tempPassword = $seller['temp_password'] ?: ('seller' . str_pad((string) $sellerId, 4, '0', STR_PAD_LEFT));
            $passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET seller_status = 'approved', temp_password = ?, password_hash = ? WHERE id = ?");
            $stmt->bind_param('ssi', $tempPassword, $passwordHash, $sellerId);
            $stmt->execute();
            $stmt->close();

            try {
                $mail = dashboard_mailer();
                $mail->addAddress($seller['email'], $seller['first_name'] . ' ' . $seller['last_name']);
                $mail->Subject = 'Your ProTech seller account has been approved';
                $mail->Body = "<div style='font-family:sans-serif;max-width:520px;margin:auto;background:#141414;border-radius:12px;overflow:hidden;'><div style='background:#ff7315;padding:24px;text-align:center;'><h1 style='color:#fff;margin:0;'>Seller Approval</h1></div><div style='padding:24px;color:#e0e0e0;'><p>Hi " . app_sanitize($seller['first_name']) . ", your seller account for <strong>" . app_sanitize($seller['store_name'] ?: $seller['username']) . "</strong> is now approved.</p><p>Email: <strong>" . app_sanitize($seller['email']) . "</strong><br>Default Password: <strong>" . app_sanitize($tempPassword) . "</strong></p></div></div>";
                $mail->AltBody = "Seller approved.\nEmail: {$seller['email']}\nDefault Password: {$tempPassword}";
                $mail->send();
                $flash = ['type' => 'success', 'message' => 'Seller approved and approval email sent.'];
            } catch (Exception $e) {
                error_log('Seller approval email failed: ' . $e->getMessage());
                $flash = ['type' => 'warning', 'message' => 'Seller approved, but the approval email could not be sent.'];
            }
        } else {
            $flash = ['type' => 'danger', 'message' => 'Seller account not found or already approved.'];
        }
    }

    if ($action === 'disapprove_seller' && app_is_admin($user)) {
        $sellerId = (int) ($_POST['seller_id'] ?? 0);
        $stmt = $conn->prepare("UPDATE users SET seller_status = 'disapproved' WHERE id = ? AND role = 'seller' AND seller_status = 'pending'");
        $stmt->bind_param('i', $sellerId);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        $flash = $affected ? ['type' => 'warning', 'message' => 'Seller request disapproved.'] : ['type' => 'danger', 'message' => 'Seller request not found or already processed.'];
    }

    if ($action === 'save_product' && ($role === 'seller' || $role === 'admin')) {
        $result = app_upsert_product($user, $_POST);
        $flash = ['type' => $result['success'] ? 'success' : 'danger', 'message' => $result['message']];
    }

    if ($action === 'update_order_status' && ($role === 'seller' || $role === 'admin')) {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $allowedStatuses = ['placed', 'processing', 'completed', 'cancelled'];

        if (!in_array($status, $allowedStatuses, true)) {
            $flash = ['type' => 'danger', 'message' => 'Invalid order status selected.'];
        } else {
            if ($role === 'seller') {
                $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE id = ? AND seller_id = ?');
                $stmt->bind_param('sii', $status, $orderId, $user['id']);
            } else {
                $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE id = ?');
                $stmt->bind_param('si', $status, $orderId);
            }
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            $flash = $affected ? ['type' => 'success', 'message' => 'Order status updated successfully.'] : ['type' => 'danger', 'message' => 'Order not found or not allowed.'];
        }
    }

    $user = app_refresh_session_user((int) $user['id']) ?? $user;
    $role = $user['role'] ?? $role;
}

$menus = [
    'admin' => [
        'dashboard' => ['Dashboard', 'fa-solid fa-house'],
        'sellers' => ['Seller Approvals', 'fa-solid fa-user-check'],
        'orders' => ['Orders', 'fa-solid fa-receipt'],
        'products' => ['Products', 'fa-solid fa-box'],
        'general' => ['General', 'fa-solid fa-gear'],
    ],
    'seller' => [
        'dashboard' => ['Dashboard', 'fa-solid fa-house'],
        'products' => ['Products', 'fa-solid fa-box'],
        'orders' => ['Orders', 'fa-solid fa-receipt'],
        'analytics' => ['Analytics', 'fa-solid fa-chart-line'],
        'general' => ['General', 'fa-solid fa-gear'],
        'notifications' => ['Notifications', 'fa-solid fa-bell'],
    ],
];

$allowedTabs = $menus[$role] ?? $menus['seller'];
$tab = $_GET['tab'] ?? array_key_first($allowedTabs);
if (!isset($allowedTabs[$tab])) {
    $tab = array_key_first($allowedTabs);
}

$avatarUrl = app_avatar_url($user);
$pendingSellers = [];
$adminStats = [];
$adminOrders = [];
$adminProducts = [];
$sellerStats = [];
$sellerProducts = [];
$sellerOrders = [];
$categories = ['Laptops', 'Desktops', 'Peripherals', 'Networking'];
$brands = [];

$brandResult = $conn->query('SELECT DISTINCT brand FROM products ORDER BY brand ASC');
while ($brandRow = $brandResult->fetch_assoc()) {
    $brands[] = $brandRow['brand'];
}

if ($role === 'admin') {
    $adminStats['users'] = (int) ($conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'customer'")->fetch_assoc()['total'] ?? 0);
    $adminStats['sellers'] = (int) ($conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'seller' AND seller_status = 'approved'")->fetch_assoc()['total'] ?? 0);
    $adminStats['pending_sellers'] = (int) ($conn->query("SELECT COUNT(*) AS total FROM users WHERE role = 'seller' AND seller_status = 'pending'")->fetch_assoc()['total'] ?? 0);
    $adminStats['products'] = (int) ($conn->query("SELECT COUNT(*) AS total FROM products")->fetch_assoc()['total'] ?? 0);
    $adminStats['orders'] = (int) ($conn->query("SELECT COUNT(*) AS total FROM orders")->fetch_assoc()['total'] ?? 0);
    $adminStats['revenue'] = (float) ($conn->query("SELECT COALESCE(SUM(total_amount), 0) AS total FROM orders")->fetch_assoc()['total'] ?? 0);

    $result = $conn->query("SELECT id, first_name, last_name, email, username, store_name, created_at FROM users WHERE role = 'seller' AND seller_status = 'pending' ORDER BY created_at ASC");
    while ($row = $result->fetch_assoc()) {
        $pendingSellers[] = $row;
    }

    $adminOrders = app_get_orders_for_seller(null);
    $adminProducts = $conn->query("
        SELECT p.id, p.name, p.brand, p.category, p.price, p.stock, p.is_active,
               COALESCE(u.store_name, u.username, 'Marketplace') AS seller_name
        FROM products p
        LEFT JOIN users u ON u.id = p.seller_id
        ORDER BY p.created_at DESC
    ")->fetch_all(MYSQLI_ASSOC);
}

if ($role === 'seller') {
    $sellerId = (int) $user['id'];
    $sellerStats['products'] = (int) ($conn->query("SELECT COUNT(*) AS total FROM products WHERE seller_id = {$sellerId}")->fetch_assoc()['total'] ?? 0);
    $sellerStats['active_products'] = (int) ($conn->query("SELECT COUNT(*) AS total FROM products WHERE seller_id = {$sellerId} AND is_active = 1")->fetch_assoc()['total'] ?? 0);
    $sellerStats['orders'] = (int) ($conn->query("SELECT COUNT(*) AS total FROM orders WHERE seller_id = {$sellerId}")->fetch_assoc()['total'] ?? 0);
    $sellerStats['revenue'] = (float) ($conn->query("SELECT COALESCE(SUM(total_amount), 0) AS total FROM orders WHERE seller_id = {$sellerId}")->fetch_assoc()['total'] ?? 0);
    $sellerProducts = $conn->query("
        SELECT id, name, brand, category, description, price, stock, icon_class, is_active
        FROM products
        WHERE seller_id = {$sellerId}
        ORDER BY created_at DESC
    ")->fetch_all(MYSQLI_ASSOC);
    $sellerOrders = app_get_orders_for_seller($sellerId);
}

$adminOrdersByDay = [];
$adminStatusCounts = ['placed' => 0, 'processing' => 0, 'completed' => 0, 'cancelled' => 0];
$sellerRevenueByDay = [];
$sellerCategoryCounts = [];
for ($i = 6; $i >= 0; $i--) {
    $label = date('M d', strtotime("-{$i} days"));
    $adminOrdersByDay[$label] = 0;
    $sellerRevenueByDay[$label] = 0;
}

if ($role === 'admin') {
    $chartRows = $conn->query("
        SELECT DATE(created_at) AS order_day, COUNT(*) AS total
        FROM orders
        WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
    ")->fetch_all(MYSQLI_ASSOC);
    foreach ($chartRows as $row) {
        $key = date('M d', strtotime($row['order_day']));
        $adminOrdersByDay[$key] = (int) $row['total'];
    }
    $statusRows = $conn->query("SELECT status, COUNT(*) AS total FROM orders GROUP BY status")->fetch_all(MYSQLI_ASSOC);
    foreach ($statusRows as $row) {
        $adminStatusCounts[$row['status']] = (int) $row['total'];
    }
}

if ($role === 'seller') {
    $sellerId = (int) $user['id'];
    $chartRows = $conn->query("
        SELECT DATE(created_at) AS order_day, COALESCE(SUM(total_amount), 0) AS total
        FROM orders
        WHERE seller_id = {$sellerId} AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
    ")->fetch_all(MYSQLI_ASSOC);
    foreach ($chartRows as $row) {
        $key = date('M d', strtotime($row['order_day']));
        $sellerRevenueByDay[$key] = (float) $row['total'];
    }
    $categoryRows = $conn->query("
        SELECT category, COUNT(*) AS total
        FROM products
        WHERE seller_id = {$sellerId}
        GROUP BY category
    ")->fetch_all(MYSQLI_ASSOC);
    foreach ($categoryRows as $row) {
        $sellerCategoryCounts[$row['category']] = (int) $row['total'];
    }
}

$pageTitle = ucfirst($role) . ' Dashboard - ProTech';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/header.php'; ?>
    <link rel="stylesheet" href="admin.css">
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .panel-card { background: var(--surface); border: 1px solid var(--border); border-radius: var(--radius-md); padding: 1.25rem; }
        .panel-card h4 { color: var(--text-primary); font-size: 1rem; margin-bottom: 1rem; }
        .pill { display: inline-block; padding: 0.25rem 0.6rem; border-radius: 999px; font-size: 0.72rem; font-weight: 600; }
        .pill.pending { background: rgba(245,158,11,.12); color: var(--warning); }
        .pill.placed, .pill.processing { background: rgba(59,130,246,.12); color: #8ec5ff; }
        .pill.completed, .pill.approved { background: rgba(16,185,129,.12); color: var(--success); }
        .pill.cancelled { background: rgba(239,68,68,.12); color: #ff9e9e; }
        .flash { border-radius: 12px; padding: 0.9rem 1rem; margin-bottom: 1rem; }
        .flash.success { background: rgba(16,185,129,.12); color: #7cf2bf; }
        .flash.warning { background: rgba(245,158,11,.12); color: #ffd479; }
        .flash.danger { background: rgba(239,68,68,.12); color: #ff9e9e; }
        .approve-btn, .disapprove-btn, .edit-btn, .status-btn, .add-product-btn { border-radius: 10px; padding: 0.55rem 0.85rem; font-size: 0.82rem; font-weight: 600; border: none; }
        .approve-btn, .edit-btn, .add-product-btn, .status-btn { background: var(--primary); color: #fff; }
        .disapprove-btn { background: transparent; border: 1px solid rgba(239, 68, 68, 0.45); color: #ff9e9e; }
        .action-stack { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .chart-card { min-height: 360px; }
        .chart-wrap { position: relative; height: 280px; }
        .modal-form-grid { display: grid; gap: 0.9rem; }
        .modal-form-grid.two-col { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .modal-form-grid .full { grid-column: 1 / -1; }
        .modal-form-grid label { color: var(--text-secondary); font-size: 0.85rem; margin-bottom: 0.35rem; display: block; }
        .modal-form-grid input, .modal-form-grid select, .modal-form-grid textarea { width: 100%; background: var(--surface-light); border: 1px solid var(--border); color: var(--text-primary); border-radius: 10px; padding: 0.75rem 0.85rem; }
        .modal-form-grid textarea { min-height: 110px; resize: vertical; }
        @media (max-width: 767.98px) { .modal-form-grid.two-col { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
<div class="admin-layout">
    <aside class="admin-sidebar" id="sidebar">
        <a href="index.php" class="sidebar-brand">
            <div class="brand-icon"><i class="fa-solid fa-microchip"></i></div>
            <div class="brand-text">Pro<span>Tech</span></div>
        </a>
        <div class="sidebar-section-label"><?= app_sanitize(ucfirst($role)) ?> Panel</div>
        <ul class="sidebar-nav">
            <?php foreach ($allowedTabs as $key => [$label, $icon]): ?>
                <li><a href="dashboard.php?tab=<?= app_sanitize($key) ?>" class="nav-link <?= $tab === $key ? 'active' : '' ?>"><i class="<?= app_sanitize($icon) ?>"></i> <?= app_sanitize($label) ?></a></li>
            <?php endforeach; ?>
        </ul>
        <div class="sidebar-footer">
            <div class="dropdown">
                <a href="#" class="user-card dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php if ($avatarUrl): ?><img src="<?= app_sanitize($avatarUrl) ?>" alt="avatar" class="user-avatar"><?php else: ?><div class="user-avatar d-flex align-items-center justify-content-center" style="background: var(--primary-glow); color: var(--primary);"><i class="fa-solid fa-user"></i></div><?php endif; ?>
                    <div class="user-info">
                        <div class="user-name"><?= app_sanitize($user['firstName'] . ' ' . $user['lastName']) ?></div>
                        <div class="user-role"><?= app_sanitize(ucfirst($role)) ?></div>
                    </div>
                </a>
                <ul class="dropdown-menu dropdown-menu-dark shadow">
                    <li><a class="dropdown-item" href="my_profile.php"><i class="fa-solid fa-user me-2"></i>View Profile</a></li>
                    <li><a class="dropdown-item" href="my_profile.php#passwordSection"><i class="fa-solid fa-gear me-2"></i>Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="logout.php"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Logout</a></li>
                </ul>
            </div>
        </div>
    </aside>

    <div class="admin-main">
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

        <div class="admin-content">
            <?php if ($flash): ?>
                <div class="flash <?= app_sanitize($flash['type']) ?>"><?= app_sanitize($flash['message']) ?></div>
            <?php endif; ?>

            <?php if ($role === 'admin' && $tab === 'dashboard'): ?>
                <div class="row g-3 mb-4">
                    <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-users"></i></div><div class="stat-value"><?= $adminStats['users'] ?></div><div class="stat-label">Customers</div></div></div>
                    <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon orange"><i class="fa-solid fa-store"></i></div><div class="stat-value"><?= $adminStats['sellers'] ?></div><div class="stat-label">Approved Sellers</div></div></div>
                    <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon blue"><i class="fa-solid fa-receipt"></i></div><div class="stat-value"><?= $adminStats['orders'] ?></div><div class="stat-label">Orders</div></div></div>
                    <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon red"><i class="fa-solid fa-dollar-sign"></i></div><div class="stat-value">$<?= number_format($adminStats['revenue'], 2) ?></div><div class="stat-label">Revenue</div></div></div>
                </div>
                <div class="row g-4">
                    <div class="col-xl-7">
                        <div class="panel-card chart-card">
                            <h4>Orders in the Last 7 Days</h4>
                            <div class="chart-wrap"><canvas id="adminOrdersChart"></canvas></div>
                        </div>
                    </div>
                    <div class="col-xl-5">
                        <div class="panel-card chart-card">
                            <h4>Order Status Breakdown</h4>
                            <div class="chart-wrap"><canvas id="adminStatusChart"></canvas></div>
                        </div>
                    </div>
                </div>

            <?php elseif ($role === 'admin' && $tab === 'sellers'): ?>
                <div class="table-card">
                    <div class="table-card-header"><h5>Pending seller applications <span class="badge-count"><?= count($pendingSellers) ?></span></h5></div>
                    <div class="table-card-body table-responsive">
                        <?php if (!$pendingSellers): ?>
                            <div class="p-4">No pending seller applications.</div>
                        <?php else: ?>
                            <table id="sellerRequestsTable" class="table table-sm w-100 mb-0">
                                <thead><tr><th>Name</th><th>Store</th><th>Email</th><th>Username</th><th>Registered</th><th>Status</th><th>Action</th></tr></thead>
                                <tbody>
                                <?php foreach ($pendingSellers as $seller): ?>
                                    <tr>
                                        <td><?= app_sanitize($seller['first_name'] . ' ' . $seller['last_name']) ?></td>
                                        <td><?= app_sanitize($seller['store_name'] ?: $seller['username']) ?></td>
                                        <td><?= app_sanitize($seller['email']) ?></td>
                                        <td><?= app_sanitize($seller['username']) ?></td>
                                        <td><?= app_sanitize($seller['created_at']) ?></td>
                                        <td><span class="pill pending">Pending</span></td>
                                        <td>
                                            <div class="action-stack">
                                                <button class="approve-btn" type="button" data-modal-target="#sellerActionModal" data-modal-title="Approve Seller Application" data-modal-message="Approve <?= app_sanitize($seller['first_name'] . ' ' . $seller['last_name']) ?> for store <?= app_sanitize($seller['store_name'] ?: $seller['username']) ?>? This will send login credentials by email." data-modal-confirm="Approve Seller" data-modal-payload='<?= app_sanitize(json_encode(['action' => 'approve_seller', 'seller_id' => (int) $seller['id']])) ?>'>Approve</button>
                                                <button class="disapprove-btn" type="button" data-modal-target="#sellerActionModal" data-modal-title="Disapprove Seller Application" data-modal-message="Disapprove <?= app_sanitize($seller['first_name'] . ' ' . $seller['last_name']) ?>? This will remove the request from the pending list." data-modal-confirm="Disapprove Seller" data-modal-payload='<?= app_sanitize(json_encode(['action' => 'disapprove_seller', 'seller_id' => (int) $seller['id']])) ?>'>Disapprove</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($role === 'admin' && $tab === 'orders'): ?>
                <div class="table-card">
                    <div class="table-card-header"><h5>All Orders <span class="badge-count"><?= count($adminOrders) ?></span></h5></div>
                    <div class="table-card-body table-responsive">
                        <?php if (!$adminOrders): ?>
                            <div class="p-4">No orders have been placed yet.</div>
                        <?php else: ?>
                            <table id="adminOrdersTable" class="table table-sm w-100 mb-0">
                                <thead><tr><th>Order</th><th>Customer</th><th>Seller</th><th>Items</th><th>Total</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
                                <tbody>
                                <?php foreach ($adminOrders as $order): ?>
                                    <tr>
                                        <td>#<?= (int) $order['id'] ?></td>
                                        <td><?= app_sanitize($order['customer_name']) ?></td>
                                        <td><?= app_sanitize($order['seller_name']) ?></td>
                                        <td><?= (int) $order['item_count'] ?></td>
                                        <td>$<?= number_format((float) $order['total_amount'], 2) ?></td>
                                        <td><span class="pill <?= app_sanitize($order['status']) ?>"><?= app_sanitize(ucfirst($order['status'])) ?></span></td>
                                        <td><?= app_sanitize($order['created_at']) ?></td>
                                        <td><button class="status-btn" type="button" data-modal-target="#orderStatusModal" data-modal-title="Update Order Status" data-modal-message="Change the status for order #<?= (int) $order['id'] ?>." data-modal-confirm="Save Status" data-modal-payload='<?= app_sanitize(json_encode(['action' => 'update_order_status', 'order_id' => (int) $order['id'], 'status' => $order['status']])) ?>'>Update</button></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($role === 'admin' && $tab === 'products'): ?>
                <div class="table-card">
                    <div class="table-card-header"><h5>All Products <span class="badge-count"><?= count($adminProducts) ?></span></h5></div>
                    <div class="table-card-body table-responsive">
                        <?php if (!$adminProducts): ?>
                            <div class="p-4">No products available.</div>
                        <?php else: ?>
                            <table id="productsTable" class="table table-sm w-100 mb-0">
                                <thead><tr><th>ID</th><th>Name</th><th>Seller</th><th>Brand</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th></tr></thead>
                                <tbody>
                                <?php foreach ($adminProducts as $product): ?>
                                    <tr>
                                        <td>#<?= (int) $product['id'] ?></td>
                                        <td><?= app_sanitize($product['name']) ?></td>
                                        <td><?= app_sanitize($product['seller_name']) ?></td>
                                        <td><?= app_sanitize($product['brand']) ?></td>
                                        <td><?= app_sanitize($product['category']) ?></td>
                                        <td>$<?= number_format((float) $product['price'], 2) ?></td>
                                        <td><?= (int) $product['stock'] ?></td>
                                        <td><span class="pill <?= (int) $product['is_active'] ? 'completed' : 'cancelled' ?>"><?= (int) $product['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($role === 'admin'): ?>
                <div class="panel-card"><h4>General</h4><p class="mb-0">Admin account tools can be extended here.</p></div>

            <?php elseif ($role === 'seller' && $tab === 'dashboard'): ?>
                <div class="row g-3 mb-4">
                    <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon orange"><i class="fa-solid fa-box"></i></div><div class="stat-value"><?= $sellerStats['products'] ?></div><div class="stat-label">Store Products</div></div></div>
                    <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-box-open"></i></div><div class="stat-value"><?= $sellerStats['active_products'] ?></div><div class="stat-label">Active Products</div></div></div>
                    <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon blue"><i class="fa-solid fa-receipt"></i></div><div class="stat-value"><?= $sellerStats['orders'] ?></div><div class="stat-label">Orders</div></div></div>
                    <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon red"><i class="fa-solid fa-dollar-sign"></i></div><div class="stat-value">$<?= number_format($sellerStats['revenue'], 2) ?></div><div class="stat-label">Revenue</div></div></div>
                </div>
                <div class="row g-4">
                    <div class="col-xl-7">
                        <div class="panel-card chart-card">
                            <h4>Revenue in the Last 7 Days</h4>
                            <div class="chart-wrap"><canvas id="sellerRevenueChart"></canvas></div>
                        </div>
                    </div>
                    <div class="col-xl-5">
                        <div class="panel-card chart-card">
                            <h4>Product Category Mix</h4>
                            <div class="chart-wrap"><canvas id="sellerCategoryChart"></canvas></div>
                        </div>
                    </div>
                </div>

            <?php elseif ($role === 'seller' && $tab === 'products'): ?>
                <div class="table-card">
                    <div class="table-card-header">
                        <h5>Your Products <span class="badge-count"><?= count($sellerProducts) ?></span></h5>
                        <button class="add-product-btn" type="button" data-modal-target="#productModal" data-modal-title="Add Product" data-modal-message="Create a new product for your store." data-modal-confirm="Save Product" data-modal-payload='<?= app_sanitize(json_encode(['action' => 'save_product', 'product_id' => '', 'name' => '', 'brand' => '', 'category' => 'Laptops', 'description' => '', 'price' => '', 'stock' => '0', 'icon_class' => 'fa-solid fa-box-open', 'is_active' => '1'])) ?>'>Add Product</button>
                    </div>
                    <div class="table-card-body table-responsive">
                        <?php if (!$sellerProducts): ?>
                            <div class="p-4">No products yet. Use the Add Product button to create your first listing.</div>
                        <?php else: ?>
                            <table id="sellerProductsTable" class="table table-sm w-100 mb-0">
                                <thead><tr><th>ID</th><th>Name</th><th>Brand</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Action</th></tr></thead>
                                <tbody>
                                <?php foreach ($sellerProducts as $product): ?>
                                    <tr>
                                        <td>#<?= (int) $product['id'] ?></td>
                                        <td><?= app_sanitize($product['name']) ?></td>
                                        <td><?= app_sanitize($product['brand']) ?></td>
                                        <td><?= app_sanitize($product['category']) ?></td>
                                        <td>$<?= number_format((float) $product['price'], 2) ?></td>
                                        <td><?= (int) $product['stock'] ?></td>
                                        <td><span class="pill <?= (int) $product['is_active'] ? 'completed' : 'cancelled' ?>"><?= (int) $product['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                        <td><button class="edit-btn" type="button" data-modal-target="#productModal" data-modal-title="Edit Product" data-modal-message="Update this product for your store." data-modal-confirm="Save Product" data-modal-payload='<?= app_sanitize(json_encode(['action' => 'save_product', 'product_id' => (int) $product['id'], 'name' => $product['name'], 'brand' => $product['brand'], 'category' => $product['category'], 'description' => $product['description'], 'price' => $product['price'], 'stock' => (int) $product['stock'], 'icon_class' => $product['icon_class'], 'is_active' => (int) $product['is_active']])) ?>'>Edit</button></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($role === 'seller' && $tab === 'orders'): ?>
                <div class="table-card">
                    <div class="table-card-header"><h5>Store Orders <span class="badge-count"><?= count($sellerOrders) ?></span></h5></div>
                    <div class="table-card-body table-responsive">
                        <?php if (!$sellerOrders): ?>
                            <div class="p-4">No orders yet for this seller.</div>
                        <?php else: ?>
                            <table id="sellerOrdersTable" class="table table-sm w-100 mb-0">
                                <thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
                                <tbody>
                                <?php foreach ($sellerOrders as $order): ?>
                                    <tr>
                                        <td>#<?= (int) $order['id'] ?></td>
                                        <td><?= app_sanitize($order['customer_name']) ?></td>
                                        <td><?= (int) $order['item_count'] ?></td>
                                        <td>$<?= number_format((float) $order['total_amount'], 2) ?></td>
                                        <td><span class="pill <?= app_sanitize($order['status']) ?>"><?= app_sanitize(ucfirst($order['status'])) ?></span></td>
                                        <td><?= app_sanitize($order['created_at']) ?></td>
                                        <td><button class="status-btn" type="button" data-modal-target="#orderStatusModal" data-modal-title="Update Order Status" data-modal-message="Change the status for order #<?= (int) $order['id'] ?>." data-modal-confirm="Save Status" data-modal-payload='<?= app_sanitize(json_encode(['action' => 'update_order_status', 'order_id' => (int) $order['id'], 'status' => $order['status']])) ?>'>Update</button></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($role === 'seller' && $tab === 'analytics'): ?>
                <div class="row g-4">
                    <div class="col-xl-7">
                        <div class="panel-card chart-card">
                            <h4>Revenue Trend</h4>
                            <div class="chart-wrap"><canvas id="sellerRevenueChartAnalytics"></canvas></div>
                        </div>
                    </div>
                    <div class="col-xl-5">
                        <div class="panel-card chart-card">
                            <h4>Category Mix</h4>
                            <div class="chart-wrap"><canvas id="sellerCategoryChartAnalytics"></canvas></div>
                        </div>
                    </div>
                </div>

            <?php else: ?>
                <div class="panel-card"><h4><?= app_sanitize($allowedTabs[$tab][0]) ?></h4><p class="mb-0">This section is ready for additional seller tools.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php render_system_modal('sellerActionModal', 'Confirm Seller Action', 'Review this seller action before continuing.', 'Confirm'); ?>
<?php render_system_modal('productModal', 'Save Product', 'Create or update a product listing.', 'Save Product'); ?>
<?php render_system_modal('orderStatusModal', 'Update Order Status', 'Update the current status for this order.', 'Save Status'); ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="js/modal.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', function () {
    document.getElementById('sidebar').classList.toggle('open');
});

[['#sellerRequestsTable', [[4, 'asc']], [5, 6], 'Search seller requests...'],
 ['#sellerProductsTable', [[0, 'desc']], [7], 'Search products...'],
 ['#adminOrdersTable', [[6, 'desc']], [7], 'Search orders...'],
 ['#sellerOrdersTable', [[5, 'desc']], [6], 'Search orders...'],
 ['#productsTable', [[0, 'desc']], [], 'Search all products...']]
    .forEach(([selector, order, disabledTargets, placeholder]) => {
        if (!document.querySelector(selector)) return;
        $(selector).DataTable({
            pageLength: 10,
            lengthMenu: [10, 25, 50],
            order,
            columnDefs: disabledTargets.length ? [{ orderable: false, targets: disabledTargets }] : [],
            language: { search: '', searchPlaceholder: placeholder }
        });
    });

function mountModalForms() {
    const sellerModal = document.getElementById('sellerActionModal');
    if (sellerModal) {
        sellerModal.querySelector('.app-modal__slot').innerHTML = `
            <form method="post" id="sellerActionForm">
                <input type="hidden" name="action" value="">
                <input type="hidden" name="seller_id" value="">
            </form>
        `;
    }

    const productModal = document.getElementById('productModal');
    if (productModal) {
        productModal.querySelector('.app-modal__slot').innerHTML = `
            <form method="post" id="productForm" class="modal-form-grid two-col">
                <input type="hidden" name="action" value="save_product">
                <input type="hidden" name="product_id" value="">
                <div><label>Name</label><input name="name" type="text" required></div>
                <div><label>Brand</label><input name="brand" type="text" list="brandList" required></div>
                <div><label>Category</label><select name="category" required><?php foreach ($categories as $category): ?><option value="<?= app_sanitize($category) ?>"><?= app_sanitize($category) ?></option><?php endforeach; ?></select></div>
                <div><label>Icon Class</label><input name="icon_class" type="text" required></div>
                <div><label>Price</label><input name="price" type="number" step="0.01" min="0.01" required></div>
                <div><label>Stock</label><input name="stock" type="number" min="0" required></div>
                <div><label>Status</label><select name="is_active"><option value="1">Active</option><option value="0">Inactive</option></select></div>
                <div class="full"><label>Description</label><textarea name="description" required></textarea></div>
            </form>
            <datalist id="brandList"><?php foreach ($brands as $brand): ?><option value="<?= app_sanitize($brand) ?>"></option><?php endforeach; ?></datalist>
        `;
    }

    const orderModal = document.getElementById('orderStatusModal');
    if (orderModal) {
        orderModal.querySelector('.app-modal__slot').innerHTML = `
            <form method="post" id="orderStatusForm" class="modal-form-grid">
                <input type="hidden" name="action" value="update_order_status">
                <input type="hidden" name="order_id" value="">
                <div><label>Status</label>
                    <select name="status">
                        <option value="placed">Placed</option>
                        <option value="processing">Processing</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
            </form>
        `;
    }
}

mountModalForms();

const adminOrdersLabels = <?= json_encode(array_keys($adminOrdersByDay)) ?>;
const adminOrdersData = <?= json_encode(array_values($adminOrdersByDay)) ?>;
const adminStatusLabels = <?= json_encode(array_map('ucfirst', array_keys($adminStatusCounts))) ?>;
const adminStatusData = <?= json_encode(array_values($adminStatusCounts)) ?>;
const sellerRevenueLabels = <?= json_encode(array_keys($sellerRevenueByDay)) ?>;
const sellerRevenueData = <?= json_encode(array_values($sellerRevenueByDay)) ?>;
const sellerCategoryLabels = <?= json_encode(array_keys($sellerCategoryCounts)) ?>;
const sellerCategoryData = <?= json_encode(array_values($sellerCategoryCounts)) ?>;

function makeLineChart(id, labels, data, label, color) {
    const el = document.getElementById(id);
    if (!el) return;
    new Chart(el, {
        type: 'line',
        data: { labels, datasets: [{ label, data, borderColor: color, backgroundColor: color.replace('1)', '.12)'), fill: true, tension: 0.35 }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { ticks: { color: '#b0b0b0' }, grid: { color: 'rgba(255,255,255,.05)' } }, y: { ticks: { color: '#b0b0b0' }, grid: { color: 'rgba(255,255,255,.05)' } } } }
    });
}

function makeDoughnutChart(id, labels, data) {
    const el = document.getElementById(id);
    if (!el) return;
    new Chart(el, {
        type: 'doughnut',
        data: { labels, datasets: [{ data, backgroundColor: ['#ff7315', '#3b82f6', '#10b981', '#ef4444', '#f59e0b'] }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#b0b0b0' } } } }
    });
}

makeLineChart('adminOrdersChart', adminOrdersLabels, adminOrdersData, 'Orders', 'rgba(255, 115, 21, 1)');
makeDoughnutChart('adminStatusChart', adminStatusLabels, adminStatusData);
makeLineChart('sellerRevenueChart', sellerRevenueLabels, sellerRevenueData, 'Revenue', 'rgba(16, 185, 129, 1)');
makeLineChart('sellerRevenueChartAnalytics', sellerRevenueLabels, sellerRevenueData, 'Revenue', 'rgba(16, 185, 129, 1)');
makeDoughnutChart('sellerCategoryChart', sellerCategoryLabels, sellerCategoryData);
makeDoughnutChart('sellerCategoryChartAnalytics', sellerCategoryLabels, sellerCategoryData);
</script>
</body>
</html>
