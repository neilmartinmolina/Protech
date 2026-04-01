<?php

declare(strict_types=1);

/**
 * Build all variables needed for dashboard views (admin / seller tabs, charts, lists).
 *
 * @return array<string, mixed>
 */
function dashboard_build_view_data(mysqli $conn, array $user, string $role): array
{
    $menus = [
        'admin' => [
            'dashboard' => ['Dashboard',        'fa-solid fa-house'],
            'users'     => ['Users',            'fa-solid fa-users'],
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
    $tab           = $_GET['tab'] ?? array_key_first($allowedTabs);
    if (!isset($allowedTabs[$tab])) {
        $tab = array_key_first($allowedTabs);
    }

    $avatarUrl           = app_avatar_url($user);
    $pendingApplications = [];
    $adminStats          = [];
    $adminOrders         = [];
    $adminProducts       = [];
    $adminUsers          = [];
    $sellerStats         = [];
    $sellerProducts      = [];
    $sellerOrders        = [];
    $categories          = ['Laptops', 'Desktops', 'Peripherals', 'Networking'];
    $brands              = [];

    $brandResult = $conn->query('SELECT brandId, name FROM brands ORDER BY name ASC');
    if ($brandResult !== false) {
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
            SELECT sa.sellerApplicationId AS app_id, sa.store_name, sa.reason,
                   sa.created_at AS applied_at,
                   u.userId AS user_id, u.first_name, u.last_name, u.email, u.username
            FROM seller_applications sa
            JOIN users u ON u.userId = sa.userId
            WHERE sa.status = 'pending'
            ORDER BY sa.created_at ASC
        ");
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                $pendingApplications[] = $row;
            }
        }

        $adminOrders = app_get_orders_for_seller(null);

        $result = $conn->query("
            SELECT p.productId, p.name,
                   b.name AS brand,
                   c.name AS category,
                   p.price, p.stock, p.is_active,
                   COALESCE(
                       (SELECT sa.store_name
                        FROM seller_applications sa
                        WHERE sa.userId = u.userId AND sa.status = 'approved'
                        ORDER BY sa.reviewed_at DESC
                        LIMIT 1),
                       u.username,
                       'Marketplace'
                   ) AS seller_name
            FROM products p
            LEFT JOIN brands     b ON b.brandId    = p.brandId
            LEFT JOIN categories c ON c.categoryId = p.categoryId
            LEFT JOIN users      u ON u.userId     = p.sellerUserId
            ORDER BY p.created_at DESC
        ");
        $adminProducts = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

        if (app_column_exists($conn, 'users', 'created_at')) {
            $result = $conn->query("
                SELECT
                    u.userId, u.first_name, u.last_name, u.username, u.email, u.role, u.seller_status, u.avatar_path, u.created_at,
                    (SELECT sa.store_name
                     FROM seller_applications sa
                     WHERE sa.userId = u.userId AND sa.status = 'approved'
                     ORDER BY sa.reviewed_at DESC
                     LIMIT 1) AS store_name
                FROM users u
                ORDER BY u.created_at DESC, u.userId DESC
            ");
        } else {
            $result = $conn->query("
                SELECT
                    u.userId, u.first_name, u.last_name, u.username, u.email, u.role, u.seller_status, u.avatar_path,
                    (SELECT sa.store_name
                     FROM seller_applications sa
                     WHERE sa.userId = u.userId AND sa.status = 'approved'
                     ORDER BY sa.reviewed_at DESC
                     LIMIT 1) AS store_name
                FROM users u
                ORDER BY u.userId DESC
            ");
        }
        $adminUsers = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

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
        $sellerId = (int) $user['userId'];

        $sellerStats['products']         = (int)   ($conn->query("SELECT COUNT(*) AS c FROM products WHERE sellerUserId = {$sellerId}")->fetch_assoc()['c'] ?? 0);
        $sellerStats['active_products'] = (int)   ($conn->query("SELECT COUNT(*) AS c FROM products WHERE sellerUserId = {$sellerId} AND is_active = 1")->fetch_assoc()['c'] ?? 0);
        $sellerStats['orders']          = (int)   ($conn->query("SELECT COUNT(DISTINCT o.orderId) AS c FROM orders o JOIN order_items oi ON oi.orderId = o.orderId WHERE oi.sellerUserId = {$sellerId}")->fetch_assoc()['c'] ?? 0);
        $sellerStats['revenue']         = (float) ($conn->query("SELECT COALESCE(SUM(oi.quantity * oi.unit_price),0) AS c FROM order_items oi WHERE oi.sellerUserId = {$sellerId}")->fetch_assoc()['c'] ?? 0);

        $result = $conn->query("
            SELECT p.productId, p.name,
                   b.name AS brand,
                   c.name AS category,
                   p.description, p.price, p.stock, p.icon_class, p.is_active
            FROM products p
            LEFT JOIN brands     b ON b.brandId    = p.brandId
            LEFT JOIN categories c ON c.categoryId = p.categoryId
            WHERE p.sellerUserId = {$sellerId}
            ORDER BY p.created_at DESC
        ");
        $sellerProducts = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

        $sellerOrders = app_get_orders_for_seller($sellerId);
    }

    $adminOrdersByDay     = [];
    $adminStatusCounts    = ['placed' => 0, 'processing' => 0, 'completed' => 0, 'cancelled' => 0];
    $sellerRevenueByDay   = [];
    $sellerCategoryCounts = [];

    for ($i = 6; $i >= 0; $i--) {
        $label                    = date('M d', strtotime("-{$i} days"));
        $adminOrdersByDay[$label]   = 0;
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
            $adminOrdersByDay[date('M d', strtotime($row['order_day']))] = (int) $row['total'];
        }

        $statusRows = $conn->query('SELECT status, COUNT(*) AS total FROM orders GROUP BY status')->fetch_all(MYSQLI_ASSOC);
        foreach ($statusRows as $row) {
            $adminStatusCounts[$row['status']] = (int) $row['total'];
        }
    }

    if ($role === 'seller') {
        $sellerId = (int) $user['userId'];

        $chartRows = $conn->query("
            SELECT DATE(o.created_at) AS order_day, COALESCE(SUM(oi.quantity * oi.unit_price),0) AS total
            FROM orders o
            JOIN order_items oi ON oi.orderId = o.orderId
            WHERE oi.sellerUserId = {$sellerId}
              AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(o.created_at)
        ")->fetch_all(MYSQLI_ASSOC);
        foreach ($chartRows as $row) {
            $sellerRevenueByDay[date('M d', strtotime($row['order_day']))] = (float) $row['total'];
        }

        $categoryRows = $conn->query("
            SELECT c.name AS category, COUNT(*) AS total
            FROM products p
            LEFT JOIN categories c ON c.categoryId = p.categoryId
            WHERE p.sellerUserId = {$sellerId}
            GROUP BY c.name
        ")->fetch_all(MYSQLI_ASSOC);
        foreach ($categoryRows as $row) {
            $sellerCategoryCounts[$row['category']] = (int) $row['total'];
        }
    }

    return [
        'allowedTabs'           => $allowedTabs,
        'tab'                   => $tab,
        'avatarUrl'             => $avatarUrl,
        'pendingApplications'   => $pendingApplications,
        'adminStats'            => $adminStats,
        'adminOrders'           => $adminOrders,
        'adminProducts'         => $adminProducts,
        'adminUsers'            => $adminUsers,
        'sellerStats'           => $sellerStats,
        'sellerProducts'        => $sellerProducts,
        'sellerOrders'          => $sellerOrders,
        'categories'            => $categories,
        'brands'                => $brands,
        'adminOrdersByDay'      => $adminOrdersByDay,
        'adminStatusCounts'     => $adminStatusCounts,
        'sellerRevenueByDay'    => $sellerRevenueByDay,
        'sellerCategoryCounts'  => $sellerCategoryCounts,
    ];
}
