<?php
require_once __DIR__ . '/app.php';
require_once __DIR__ . '/includes/modal.php';

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
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port       = SMTP_PORT;
    $mail->setFrom(SMTP_USER, FROM_NAME);
    $mail->isHTML(true);
    return $mail;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Approve seller application ──────────────────────────
    if ($action === 'approve_seller' && app_is_admin($user)) {
        $applicationId = (int) ($_POST['application_id'] ?? 0);

        // Pull the application + the applicant's user record in one join
        $stmt = $conn->prepare("
            SELECT sa.id AS app_id, sa.user_id, sa.store_name,
                   u.first_name, u.last_name, u.email, u.username
            FROM seller_applications sa
            JOIN users u ON u.id = sa.user_id
            WHERE sa.id = ? AND sa.status = 'pending'
            LIMIT 1
        ");
        $stmt->bind_param('i', $applicationId);
        $stmt->execute();
        $application = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($application) {
            $conn->begin_transaction();
            try {
                $reviewerId = (int) $user['id'];
                $userId     = (int) $application['user_id'];
                $storeName  = $application['store_name'];

                // 1. Mark application approved
                $stmt = $conn->prepare("
                    UPDATE seller_applications
                    SET status = 'approved', reviewed_by = ?, reviewed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param('ii', $reviewerId, $applicationId);
                $stmt->execute();
                $stmt->close();

                // 2. Promote the user — do NOT touch password_hash, they already have one
                $stmt = $conn->prepare("
                    UPDATE users
                    SET role = 'seller', seller_status = 'approved', store_name = ?
                    WHERE id = ?
                ");
                $stmt->bind_param('si', $storeName, $userId);
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                // 3. Send notification email (non-fatal)
                try {
                    $mail = dashboard_mailer();
                    $mail->addAddress($application['email'], $application['first_name'] . ' ' . $application['last_name']);
                    $mail->Subject = 'Your ProTech seller application has been approved!';
                    $mail->Body = "
                        <div style='font-family:sans-serif;max-width:520px;margin:auto;background:#141414;border-radius:12px;overflow:hidden;'>
                            <div style='background:#ff7315;padding:24px;text-align:center;'>
                                <h1 style='color:#fff;margin:0;font-size:1.4rem;'>You're now a seller!</h1>
                            </div>
                            <div style='padding:24px;color:#e0e0e0;line-height:1.6;'>
                                <p>Hi " . app_sanitize($application['first_name']) . ",</p>
                                <p>Your seller application for <strong>" . app_sanitize($storeName) . "</strong> has been approved. You can now log in to your dashboard and start listing products.</p>
                                <p style='color:#999;font-size:.85rem;'>Your login credentials remain the same — no password change required.</p>
                            </div>
                        </div>
                    ";
                    $mail->AltBody = "Hi {$application['first_name']}, your seller application for \"{$storeName}\" has been approved. Log in to access your seller dashboard.";
                    $mail->send();
                    $flash = ['type' => 'success', 'message' => 'Application approved — seller account activated and notification email sent.'];
                } catch (Exception $e) {
                    error_log('Seller approval email failed: ' . $e->getMessage());
                    $flash = ['type' => 'warning', 'message' => 'Application approved and seller account activated. Notification email could not be sent.'];
                }

            } catch (\Throwable $e) {
                $conn->rollback();
                error_log('Seller approval transaction failed: ' . $e->getMessage());
                $flash = ['type' => 'danger', 'message' => 'Something went wrong during approval. Please try again.'];
            }
        } else {
            $flash = ['type' => 'danger', 'message' => 'Application not found or already processed.'];
        }
    }

    // ── Reject seller application ───────────────────────────
    if ($action === 'reject_seller' && app_is_admin($user)) {
        $applicationId   = (int) ($_POST['application_id'] ?? 0);
        $rejectionReason = trim($_POST['rejection_reason'] ?? '');

        $stmt = $conn->prepare("
            SELECT sa.id AS app_id, sa.user_id, sa.store_name,
                   u.first_name, u.last_name, u.email
            FROM seller_applications sa
            JOIN users u ON u.id = sa.user_id
            WHERE sa.id = ? AND sa.status = 'pending'
            LIMIT 1
        ");
        $stmt->bind_param('i', $applicationId);
        $stmt->execute();
        $application = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($application) {
            $conn->begin_transaction();
            try {
                $reviewerId = (int) $user['id'];
                $userId     = (int) $application['user_id'];

                // 1. Mark application rejected with reason
                $stmt = $conn->prepare("
                    UPDATE seller_applications
                    SET status = 'rejected', rejection_reason = ?, reviewed_by = ?, reviewed_at = NOW()
                    WHERE id = ?
                ");
                $stmt->bind_param('sii', $rejectionReason, $reviewerId, $applicationId);
                $stmt->execute();
                $stmt->close();

                // 2. Reset seller_status on users so they can re-apply
                $stmt = $conn->prepare("UPDATE users SET seller_status = 'rejected' WHERE id = ?");
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                // 3. Send rejection email (non-fatal)
                try {
                    $mail = dashboard_mailer();
                    $mail->addAddress($application['email'], $application['first_name'] . ' ' . $application['last_name']);
                    $mail->Subject = 'Update on your ProTech seller application';
                    $reasonHtml = $rejectionReason
                        ? '<p><strong>Reason:</strong> ' . app_sanitize($rejectionReason) . '</p>'
                        : '';
                    $mail->Body = "
                        <div style='font-family:sans-serif;max-width:520px;margin:auto;background:#141414;border-radius:12px;overflow:hidden;'>
                            <div style='background:#374151;padding:24px;text-align:center;'>
                                <h1 style='color:#fff;margin:0;font-size:1.4rem;'>Application Update</h1>
                            </div>
                            <div style='padding:24px;color:#e0e0e0;line-height:1.6;'>
                                <p>Hi " . app_sanitize($application['first_name']) . ",</p>
                                <p>Thank you for applying to sell on ProTech. Unfortunately, your application for <strong>" . app_sanitize($application['store_name']) . "</strong> was not approved at this time.</p>
                                {$reasonHtml}
                                <p>You're welcome to resubmit an application from your profile settings.</p>
                            </div>
                        </div>
                    ";
                    $mail->AltBody = "Hi {$application['first_name']}, your seller application was not approved." . ($rejectionReason ? " Reason: {$rejectionReason}" : '') . " You may resubmit from your profile.";
                    $mail->send();
                } catch (Exception $e) {
                    error_log('Seller rejection email failed: ' . $e->getMessage());
                }

                $flash = ['type' => 'warning', 'message' => 'Application rejected. The applicant has been notified and can resubmit.'];

            } catch (\Throwable $e) {
                $conn->rollback();
                error_log('Seller rejection transaction failed: ' . $e->getMessage());
                $flash = ['type' => 'danger', 'message' => 'Something went wrong. Please try again.'];
            }
        } else {
            $flash = ['type' => 'danger', 'message' => 'Application not found or already processed.'];
        }
    }

    if ($action === 'save_product' && ($role === 'seller' || $role === 'admin')) {
        $result = app_upsert_product($user, $_POST);
        $flash = ['type' => $result['success'] ? 'success' : 'danger', 'message' => $result['message']];
    }

    if ($action === 'update_order_status' && ($role === 'seller' || $role === 'admin')) {
        $orderId = (int) ($_POST['order_id'] ?? 0);
        $status  = trim($_POST['status'] ?? '');
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
            $flash = $affected
                ? ['type' => 'success', 'message' => 'Order status updated successfully.']
                : ['type' => 'danger',  'message' => 'Order not found or not allowed.'];
        }
    }

    $user = app_refresh_session_user((int) $user['id']) ?? $user;
    $role = $user['role'] ?? $role;
}

$menus = [
    'admin' => [
        'dashboard' => ['Dashboard',        'fa-solid fa-house'],
        'sellers'   => ['Seller Approvals', 'fa-solid fa-user-check'],
        'orders'    => ['Orders',           'fa-solid fa-receipt'],
        'products'  => ['Products',         'fa-solid fa-box'],
        'general'   => ['General',          'fa-solid fa-gear'],
    ],
    'seller' => [
        'dashboard'     => ['Dashboard',     'fa-solid fa-house'],
        'products'      => ['Products',      'fa-solid fa-box'],
        'orders'        => ['Orders',        'fa-solid fa-receipt'],
        'analytics'     => ['Analytics',     'fa-solid fa-chart-line'],
        'general'       => ['General',       'fa-solid fa-gear'],
        'notifications' => ['Notifications', 'fa-solid fa-bell'],
    ],
];

$allowedTabs = $menus[$role] ?? $menus['seller'];
$tab = $_GET['tab'] ?? array_key_first($allowedTabs);
if (!isset($allowedTabs[$tab])) $tab = array_key_first($allowedTabs);

$avatarUrl      = app_avatar_url($user);
$pendingApplications = [];
$adminStats     = [];
$adminOrders    = [];
$adminProducts  = [];
$sellerStats    = [];
$sellerProducts = [];
$sellerOrders   = [];
$categories     = ['Laptops', 'Desktops', 'Peripherals', 'Networking'];
$brands         = [];

$brandResult = $conn->query('SELECT id, name FROM brands ORDER BY name ASC');
if ($brandResult === false) {
    $brands = [];
} else {
    while ($row = $brandResult->fetch_assoc()) {
        $brands[] = $row['name'];
    }
}

if ($role === 'admin') {
    $queries = [
        'users'           => "SELECT COUNT(*) AS c FROM users WHERE role = 'customer'",
        'sellers'         => "SELECT COUNT(*) AS c FROM users WHERE role = 'seller' AND seller_status = 'approved'",
        'pending_sellers' => "SELECT COUNT(*) AS c FROM seller_applications WHERE status = 'pending'",
        'products'        => "SELECT COUNT(*) AS c FROM products",
        'orders'          => "SELECT COUNT(*) AS c FROM orders",
        'revenue'         => "SELECT COALESCE(SUM(total_amount),0) AS c FROM orders",
    ];

    $result = $conn->query("
        SELECT sa.id AS app_id, sa.store_name, sa.reason, sa.created_at AS applied_at,
               u.id AS user_id, u.first_name, u.last_name, u.email, u.username
        FROM seller_applications sa
        JOIN users u ON u.id = sa.user_id
        WHERE sa.status = 'pending'
        ORDER BY sa.created_at ASC
    ");
    if ($result !== false) {
        while ($row = $result->fetch_assoc()) {
            $pendingApplications[] = $row;
        }
    }

    // ── Admin orders + products ───────────────────────────
    $adminOrders   = app_get_orders_for_seller(null);
    $adminProducts = $conn->query("
        SELECT p.id, p.name, b.name AS brand, c.name AS category,
               p.price, p.stock, p.is_active,
               COALESCE(u.store_name, u.username, 'Marketplace') AS seller_name
        FROM products p
        LEFT JOIN brands b     ON b.id = p.brand_id
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN users u      ON u.id = p.seller_id
        ORDER BY p.created_at DESC
    ");
    $adminProducts = $adminProducts ? $adminProducts->fetch_all(MYSQLI_ASSOC) : [];

    foreach ($queries as $key => $sql) {
        $result = $conn->query($sql);
        if ($result === false) {
            error_log("Admin stat query failed [{$key}]: " . $conn->error);
            $adminStats[$key] = 0;
        } else {
            $adminStats[$key] = $key === 'revenue'
                ? (float) ($result->fetch_assoc()['c'] ?? 0)
                : (int)   ($result->fetch_assoc()['c'] ?? 0);
        }
    }
}

if ($role === 'seller') {
    $sellerId = (int) $user['id'];
    $sellerStats['products']        = (int) ($conn->query("SELECT COUNT(*) AS c FROM products WHERE seller_id = {$sellerId}")->fetch_assoc()['c'] ?? 0);
    $sellerStats['active_products'] = (int) ($conn->query("SELECT COUNT(*) AS c FROM products WHERE seller_id = {$sellerId} AND is_active = 1")->fetch_assoc()['c'] ?? 0);
    $sellerStats['orders']          = (int) ($conn->query("SELECT COUNT(*) AS c FROM orders WHERE seller_id = {$sellerId}")->fetch_assoc()['c'] ?? 0);
    $sellerStats['revenue']         = (float) ($conn->query("SELECT COALESCE(SUM(total_amount),0) AS c FROM orders WHERE seller_id = {$sellerId}")->fetch_assoc()['c'] ?? 0);
    $sellerProducts = $conn->query("
    SELECT p.id, p.name, b.name AS brand, c.name AS category,
           p.description, p.price, p.stock, p.icon_class, p.is_active
    FROM products p
    LEFT JOIN brands b     ON b.id = p.brand_id
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.seller_id = {$sellerId}
    ORDER BY p.created_at DESC
    ")->fetch_all(MYSQLI_ASSOC);
    $sellerOrders = app_get_orders_for_seller($sellerId);
}

$adminOrdersByDay   = [];
$adminStatusCounts  = ['placed' => 0, 'processing' => 0, 'completed' => 0, 'cancelled' => 0];
$sellerRevenueByDay = [];
$sellerCategoryCounts = [];

for ($i = 6; $i >= 0; $i--) {
    $label = date('M d', strtotime("-{$i} days"));
    $adminOrdersByDay[$label]   = 0;
    $sellerRevenueByDay[$label] = 0;
}

if ($role === 'admin') {
    $chartRows = $conn->query("
        SELECT DATE(created_at) AS order_day, COUNT(*) AS total
        FROM orders WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
    ")->fetch_all(MYSQLI_ASSOC);
    foreach ($chartRows as $row) {
        $adminOrdersByDay[date('M d', strtotime($row['order_day']))] = (int) $row['total'];
    }
    $statusRows = $conn->query("SELECT status, COUNT(*) AS total FROM orders GROUP BY status")->fetch_all(MYSQLI_ASSOC);
    foreach ($statusRows as $row) {
        $adminStatusCounts[$row['status']] = (int) $row['total'];
    }
}

if ($role === 'seller') {
    $sellerId  = (int) $user['id'];
    $chartRows = $conn->query("
        SELECT DATE(created_at) AS order_day, COALESCE(SUM(total_amount),0) AS total
        FROM orders WHERE seller_id = {$sellerId} AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
    ")->fetch_all(MYSQLI_ASSOC);
    foreach ($chartRows as $row) {
        $sellerRevenueByDay[date('M d', strtotime($row['order_day']))] = (float) $row['total'];
    }
    $categoryRows = $conn->query("
    SELECT c.name AS category, COUNT(*) AS total 
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.seller_id = {$sellerId} 
    GROUP BY c.name
        ")->fetch_all(MYSQLI_ASSOC);
    foreach ($categoryRows as $row) {
        $sellerCategoryCounts[$row['category']] = (int) $row['total'];
    }
}

require_once __DIR__ . '/includes/modal.php';

$pageTitle   = ucfirst($role) . ' Dashboard — ProTech';
$pageCss     = ['admin.css', 'dashboard.css'];
$pageCssExt  = ['https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
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
                <?php if (($adminStats['pending_sellers'] ?? 0) > 0): ?>
                    <div class="alert mb-4" style="background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.25);border-radius:12px;color:#ffd479;padding:.85rem 1rem;font-size:.9rem;">
                        <i class="fa-solid fa-clock me-2"></i>
                        <strong><?= (int) $adminStats['pending_sellers'] ?></strong> seller application<?= $adminStats['pending_sellers'] !== 1 ? 's' : '' ?> awaiting review.
                        <a href="dashboard.php?tab=sellers" style="color:#ffd479;font-weight:600;margin-left:.5rem;">Review now &rarr;</a>
                    </div>
                <?php endif; ?>
                <div class="row g-4">
                    <div class="col-xl-7"><div class="panel-card chart-card"><h4>Orders in the Last 7 Days</h4><div class="chart-wrap"><canvas id="adminOrdersChart"></canvas></div></div></div>
                    <div class="col-xl-5"><div class="panel-card chart-card"><h4>Order Status Breakdown</h4><div class="chart-wrap"><canvas id="adminStatusChart"></canvas></div></div></div>
                </div>

            <?php elseif ($role === 'admin' && $tab === 'sellers'): ?>
                <div class="table-card">
                    <div class="table-card-header">
                        <h5>Pending Seller Applications <span class="badge-count"><?= count($pendingApplications) ?></span></h5>
                    </div>
                    <div class="table-card-body table-responsive">
                        <?php if (!$pendingApplications): ?>
                            <div class="p-4 text-secondary">No pending seller applications. You're all caught up.</div>
                        <?php else: ?>
                            <table id="sellerRequestsTable" class="table table-sm w-100 mb-0">
                                <thead>
                                    <tr>
                                        <th>Applicant</th>
                                        <th>Store Name</th>
                                        <th>Email</th>
                                        <th>Username</th>
                                        <th>Reason</th>
                                        <th>Applied</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($pendingApplications as $app): ?>
                                    <tr>
                                        <td><?= app_sanitize($app['first_name'] . ' ' . $app['last_name']) ?></td>
                                        <td><?= app_sanitize($app['store_name']) ?></td>
                                        <td><?= app_sanitize($app['email']) ?></td>
                                        <td><?= app_sanitize($app['username']) ?></td>
                                        <td>
                                            <?php if (!empty($app['reason'])): ?>
                                                <span class="reason-cell" title="<?= app_sanitize($app['reason']) ?>"><?= app_sanitize($app['reason']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted" style="font-size:.8rem;">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= app_sanitize(date('M d, Y', strtotime($app['applied_at']))) ?></td>
                                        <td><span class="pill pending">Pending</span></td>
                                        <td>
                                            <div class="action-stack">
                                                <button class="approve-btn" type="button"
                                                    data-modal-target="#sellerActionModal"
                                                    data-modal-title="Approve Seller Application"
                                                    data-modal-message="Approve <strong><?= app_sanitize($app['first_name'] . ' ' . $app['last_name']) ?></strong> for store <strong><?= app_sanitize($app['store_name']) ?></strong>? Their account will be promoted to seller and they'll receive a notification email."
                                                    data-modal-confirm="Approve"
                                                    data-modal-payload='<?= app_sanitize(json_encode(['action' => 'approve_seller', 'application_id' => (int) $app['app_id']])) ?>'>
                                                    Approve
                                                </button>
                                                <button class="reject-btn" type="button"
                                                    data-modal-target="#rejectSellerModal"
                                                    data-modal-title="Reject Application"
                                                    data-modal-message="Reject <strong><?= app_sanitize($app['first_name'] . ' ' . $app['last_name']) ?></strong>&rsquo;s application for <strong><?= app_sanitize($app['store_name']) ?></strong>? You can provide a reason — the applicant will be notified and may resubmit."
                                                    data-modal-confirm="Reject Application"
                                                    data-modal-payload='<?= app_sanitize(json_encode(['action' => 'reject_seller', 'application_id' => (int) $app['app_id']])) ?>'>
                                                    Reject
                                                </button>
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
                            <div class="p-4">No orders placed yet.</div>
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
                                        <td>
                                            <button class="status-btn" type="button"
                                                data-modal-target="#orderStatusModal"
                                                data-modal-title="Update Order Status"
                                                data-modal-message="Change the status for order #<?= (int) $order['id'] ?>."
                                                data-modal-confirm="Save Status"
                                                data-modal-payload='<?= app_sanitize(json_encode(['action' => 'update_order_status', 'order_id' => (int) $order['id'], 'status' => $order['status']])) ?>'>
                                                Update
                                            </button>
                                        </td>
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
                    <div class="col-xl-7"><div class="panel-card chart-card"><h4>Revenue in the Last 7 Days</h4><div class="chart-wrap"><canvas id="sellerRevenueChart"></canvas></div></div></div>
                    <div class="col-xl-5"><div class="panel-card chart-card"><h4>Product Category Mix</h4><div class="chart-wrap"><canvas id="sellerCategoryChart"></canvas></div></div></div>
                </div>

            <?php elseif ($role === 'seller' && $tab === 'products'): ?>
                <div class="table-card">
                    <div class="table-card-header">
                        <h5>Your Products <span class="badge-count"><?= count($sellerProducts) ?></span></h5>
                        <button class="add-product-btn" type="button"
                            data-modal-target="#productModal"
                            data-modal-title="Add Product"
                            data-modal-message="Create a new product for your store."
                            data-modal-confirm="Save Product"
                            data-modal-payload='<?= app_sanitize(json_encode(['action' => 'save_product', 'product_id' => '', 'name' => '', 'brand' => '', 'category' => 'Laptops', 'description' => '', 'price' => '', 'stock' => '0', 'icon_class' => 'fa-solid fa-box-open', 'is_active' => '1'])) ?>'>
                            Add Product
                        </button>
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
                                        <td>
                                            <button class="edit-btn" type="button"
                                                data-modal-target="#productModal"
                                                data-modal-title="Edit Product"
                                                data-modal-message="Update this product."
                                                data-modal-confirm="Save Product"
                                                data-modal-payload='<?= app_sanitize(json_encode(['action' => 'save_product', 'product_id' => (int) $product['id'], 'name' => $product['name'], 'brand' => $product['brand'], 'category' => $product['category'], 'description' => $product['description'], 'price' => $product['price'], 'stock' => (int) $product['stock'], 'icon_class' => $product['icon_class'], 'is_active' => (int) $product['is_active']])) ?>'>
                                                Edit
                                            </button>
                                        </td>
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
                            <div class="p-4">No orders yet for this store.</div>
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
                                        <td>
                                            <button class="status-btn" type="button"
                                                data-modal-target="#orderStatusModal"
                                                data-modal-title="Update Order Status"
                                                data-modal-message="Change the status for order #<?= (int) $order['id'] ?>."
                                                data-modal-confirm="Save Status"
                                                data-modal-payload='<?= app_sanitize(json_encode(['action' => 'update_order_status', 'order_id' => (int) $order['id'], 'status' => $order['status']])) ?>'>
                                                Update
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>

            <?php elseif ($role === 'seller' && $tab === 'analytics'): ?>
                <div class="row g-4">
                    <div class="col-xl-7"><div class="panel-card chart-card"><h4>Revenue Trend</h4><div class="chart-wrap"><canvas id="sellerRevenueChartAnalytics"></canvas></div></div></div>
                    <div class="col-xl-5"><div class="panel-card chart-card"><h4>Category Mix</h4><div class="chart-wrap"><canvas id="sellerCategoryChartAnalytics"></canvas></div></div></div>
                </div>

            <?php else: ?>
                <div class="panel-card"><h4><?= app_sanitize($allowedTabs[$tab][0]) ?></h4><p class="mb-0">This section is ready for additional tools.</p></div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modals -->
<?php render_system_modal('sellerActionModal', 'Confirm Seller Action', 'Review this action before continuing.', 'Confirm'); ?>
<?php render_system_modal('rejectSellerModal', 'Reject Application', 'Provide a reason for rejection (optional).', 'Reject Application'); ?>
<?php render_system_modal('productModal',      'Save Product',         'Create or update a product listing.',     'Save Product'); ?>
<?php render_system_modal('orderStatusModal',  'Update Order Status',  'Update the status for this order.',       'Save Status'); ?>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script src="js/modal.js"></script>
<script>
document.getElementById('sidebarToggle')?.addEventListener('click', () => {
    document.getElementById('sidebar').classList.toggle('open');
});

[
    ['#sellerRequestsTable', [[5, 'asc']], [6, 7], 'Search applications...'],
    ['#sellerProductsTable', [[0, 'desc']], [7],    'Search products...'],
    ['#adminOrdersTable',    [[6, 'desc']], [7],    'Search orders...'],
    ['#sellerOrdersTable',   [[5, 'desc']], [6],    'Search orders...'],
    ['#productsTable',       [[0, 'desc']], [],     'Search all products...'],
].forEach(([selector, order, disabledTargets, placeholder]) => {
    if (!document.querySelector(selector)) return;
    $(selector).DataTable({
        pageLength: 10,
        lengthMenu: [10, 25, 50],
        order,
        columnDefs: disabledTargets.length ? [{ orderable: false, targets: disabledTargets }] : [],
        language: { search: '', searchPlaceholder: placeholder },
    });
});

function mountModalForms() {
    // Seller approve modal — just hidden inputs, no extra fields
    const sellerModal = document.getElementById('sellerActionModal');
    if (sellerModal) {
        sellerModal.querySelector('.app-modal__slot').innerHTML = `
            <form method="post" id="sellerActionForm">
                <input type="hidden" name="action" value="">
                <input type="hidden" name="application_id" value="">
            </form>
        `;
    }

    // Reject modal — includes optional rejection reason textarea
    const rejectModal = document.getElementById('rejectSellerModal');
    if (rejectModal) {
        rejectModal.querySelector('.app-modal__slot').innerHTML = `
            <form method="post" id="rejectSellerForm" class="modal-form-grid">
                <input type="hidden" name="action" value="reject_seller">
                <input type="hidden" name="application_id" value="">
                <div>
                    <label>Reason for rejection <span style="font-weight:400;color:var(--text-muted);">(optional — shown to applicant)</span></label>
                    <textarea name="rejection_reason" placeholder="e.g. Incomplete store information, please resubmit with more detail..."></textarea>
                </div>
            </form>
        `;
    }

    // Product modal
    const productModal = document.getElementById('productModal');
    if (productModal) {
        productModal.querySelector('.app-modal__slot').innerHTML = `
            <form method="post" id="productForm" class="modal-form-grid two-col">
                <input type="hidden" name="action" value="save_product">
                <input type="hidden" name="product_id" value="">
                <div><label>Name</label><input name="name" type="text" required></div>
                <div><label>Brand</label><input name="brand" type="text" list="brandList" required></div>
                <div><label>Category</label>
                    <select name="category" required>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= app_sanitize($category) ?>"><?= app_sanitize($category) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>Icon Class</label><input name="icon_class" type="text" required></div>
                <div><label>Price</label><input name="price" type="number" step="0.01" min="0.01" required></div>
                <div><label>Stock</label><input name="stock" type="number" min="0" required></div>
                <div><label>Status</label>
                    <select name="is_active">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="full"><label>Description</label><textarea name="description" required></textarea></div>
            </form>
            <datalist id="brandList">
                <?php foreach ($brands as $brand): ?>
                    <option value="<?= app_sanitize($brand) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        `;
    }

    // Order status modal
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

// Chart data from PHP
const adminOrdersLabels  = <?= json_encode(array_keys($adminOrdersByDay)) ?>;
const adminOrdersData    = <?= json_encode(array_values($adminOrdersByDay)) ?>;
const adminStatusLabels  = <?= json_encode(array_map('ucfirst', array_keys($adminStatusCounts))) ?>;
const adminStatusData    = <?= json_encode(array_values($adminStatusCounts)) ?>;
const sellerRevenueLabels = <?= json_encode(array_keys($sellerRevenueByDay)) ?>;
const sellerRevenueData   = <?= json_encode(array_values($sellerRevenueByDay)) ?>;
const sellerCategoryLabels = <?= json_encode(array_keys($sellerCategoryCounts)) ?>;
const sellerCategoryData   = <?= json_encode(array_values($sellerCategoryCounts)) ?>;

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
        data: { labels, datasets: [{ data, backgroundColor: ['#ff7315','#3b82f6','#10b981','#ef4444','#f59e0b'] }] },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { labels: { color: '#b0b0b0' } } } }
    });
}

makeLineChart('adminOrdersChart',            adminOrdersLabels,    adminOrdersData,    'Orders',  'rgba(255,115,21,1)');
makeDoughnutChart('adminStatusChart',        adminStatusLabels,    adminStatusData);
makeLineChart('sellerRevenueChart',          sellerRevenueLabels,  sellerRevenueData,  'Revenue', 'rgba(16,185,129,1)');
makeLineChart('sellerRevenueChartAnalytics', sellerRevenueLabels,  sellerRevenueData,  'Revenue', 'rgba(16,185,129,1)');
makeDoughnutChart('sellerCategoryChart',     sellerCategoryLabels, sellerCategoryData);
makeDoughnutChart('sellerCategoryChartAnalytics', sellerCategoryLabels, sellerCategoryData);
</script>
</body>
</html>