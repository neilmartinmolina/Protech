<?php

declare(strict_types=1);

/**
 * Build all variables needed for dashboard views (admin / seller tabs, charts, lists).
 *
 * Changes vs original:
 *  - Added 'notifications' and 'activity_logs' tabs to BOTH roles.
 *  - Fixed raw $sellerId interpolation into SQL (was injectable).
 *  - Added $activityLogs, $notifications, $unreadCount to returned data.
 *
 * @return array<string, mixed>
 */
function dashboard_build_view_data(mysqli $conn, array $user, string $role): array
{
    $menus = [
        'admin' => [
            'dashboard'      => ['Dashboard',        'fa-solid fa-house'],
            'users'          => ['Users',            'fa-solid fa-users'],
            'sellers'        => ['Seller Approvals', 'fa-solid fa-user-check'],
            'orders'         => ['Orders',           'fa-solid fa-receipt'],
            'products'       => ['Products',         'fa-solid fa-box'],
            'notifications'  => ['Notifications',    'fa-solid fa-bell'],
            'activity_logs'  => ['Activity Logs',    'fa-solid fa-clock-rotate-left'],
            'general'        => ['General',          'fa-solid fa-gear'],
        ],
        'superadmin' => [
            'dashboard'      => ['Dashboard',        'fa-solid fa-house'],
            'users'          => ['Users',            'fa-solid fa-users'],
            'sellers'        => ['Seller Approvals', 'fa-solid fa-user-check'],
            'orders'         => ['Orders',           'fa-solid fa-receipt'],
            'products'       => ['Products',         'fa-solid fa-box'],
            'notifications'  => ['Notifications',    'fa-solid fa-bell'],
            'activity_logs'  => ['Activity Logs',    'fa-solid fa-clock-rotate-left'],
            'general'        => ['General',          'fa-solid fa-gear'],
        ],
        'seller' => [
            'dashboard'      => ['Dashboard',        'fa-solid fa-house'],
            'products'       => ['Products',         'fa-solid fa-box'],
            'orders'         => ['Orders',           'fa-solid fa-receipt'],
            'analytics'      => ['Analytics',        'fa-solid fa-chart-line'],
            'notifications'  => ['Notifications',    'fa-solid fa-bell'],
            'activity_logs'  => ['Activity Logs',    'fa-solid fa-clock-rotate-left'],
            'general'        => ['General',          'fa-solid fa-gear'],
        ],
    ];

    $allowedTabs = $menus[$role] ?? $menus['seller'];
    $tab         = $_GET['tab'] ?? array_key_first($allowedTabs);
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
    $activityLogs        = [];
    $notifications       = [];
    $unreadCount         = 0;
    $categories          = ['Laptops', 'Desktops', 'Peripherals', 'Networking'];
    $brands              = [];

    $brandResult = $conn->query('SELECT brandId, name FROM brands ORDER BY name ASC');
    if ($brandResult !== false) {
        while ($row = $brandResult->fetch_assoc()) {
            $brands[] = $row['name'];
        }
    }

    // ------------------------------------------------------------------
    // Notifications + activity logs (both roles)
    // ------------------------------------------------------------------
    $userId      = (int) $user['userId'];
    $unreadCount = app_count_unread_notifications($conn, $userId);

    // Only load the heavy data when the tab is actually requested —
    // avoids running expensive queries on every page load.
    if ($tab === 'notifications') {
        $notifications = app_get_notifications($conn, $userId);
        // Mark as read immediately upon viewing.
        app_mark_notifications_read($conn, $userId);
        $unreadCount = 0; // just marked them read
    }

    if ($tab === 'activity_logs') {
        $activityLogs = app_get_activity_logs($conn, $role, $userId);
    }

    // ------------------------------------------------------------------
    // Admin data
    // ------------------------------------------------------------------
    if ($role === 'admin' || $role === 'superadmin') {
        $queries = [
            'users'           => "SELECT COUNT(*) AS c FROM users WHERE role = 'customer'",
            'sellers'         => "SELECT COUNT(*) AS c FROM users WHERE role = 'seller' AND seller_status = 'approved'",
            'pending_sellers' => "SELECT COUNT(*) AS c FROM seller_applications WHERE status = 'pending'",
            'products'        => 'SELECT COUNT(*) AS c FROM products',
            'orders'          => 'SELECT COUNT(*) AS c FROM orders',
            'revenue'         => 'SELECT COALESCE(SUM(total_amount),0) AS c FROM orders',
        ];

        $result = $conn->query('
            SELECT sa.sellerApplicationId AS app_id, sa.store_name, sa.reason,
                   sa.created_at AS applied_at,
                   u.userId AS user_id, u.first_name, u.last_name, u.email, u.username
            FROM seller_applications sa
            JOIN users u ON u.userId = sa.userId
            WHERE sa.status = \'pending\'
            ORDER BY sa.created_at ASC
        ');
        if ($result !== false) {
            while ($row = $result->fetch_assoc()) {
                $pendingApplications[] = $row;
            }
        }

        $adminOrders = app_get_orders_for_seller(null);

        $result = $conn->query('
            SELECT p.productId, p.name,
                   b.name AS brand,
                   c.name AS category,
                   p.price, p.stock, p.is_active,
                   COALESCE(
                       (SELECT sa.store_name
                        FROM seller_applications sa
                        WHERE sa.userId = u.userId AND sa.status = \'approved\'
                        ORDER BY sa.reviewed_at DESC
                        LIMIT 1),
                       u.username,
                       \'Marketplace\'
                   ) AS seller_name
            FROM products p
            LEFT JOIN brands     b ON b.brandId    = p.brandId
            LEFT JOIN categories c ON c.categoryId = p.categoryId
            LEFT JOIN users      u ON u.userId     = p.sellerUserId
            ORDER BY p.created_at DESC
        ');
        $adminProducts = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

        if (app_column_exists($conn, 'users', 'created_at')) {
            $result = $conn->query('
                SELECT
                    u.userId, u.first_name, u.last_name, u.username, u.email, u.role, u.seller_status, u.avatar_path, u.created_at,
                    (SELECT sa.store_name
                     FROM seller_applications sa
                     WHERE sa.userId = u.userId AND sa.status = \'approved\'
                     ORDER BY sa.reviewed_at DESC
                     LIMIT 1) AS store_name
                FROM users u
                ORDER BY u.created_at DESC, u.userId DESC
            ');
        } else {
            $result = $conn->query('
                SELECT
                    u.userId, u.first_name, u.last_name, u.username, u.email, u.role, u.seller_status, u.avatar_path,
                    (SELECT sa.store_name
                     FROM seller_applications sa
                     WHERE sa.userId = u.userId AND sa.status = \'approved\'
                     ORDER BY sa.reviewed_at DESC
                     LIMIT 1) AS store_name
                FROM users u
                ORDER BY u.userId DESC
            ');
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

    // ------------------------------------------------------------------
    // Seller data — FIX: use prepared statements, not interpolation.
    // ------------------------------------------------------------------
    if ($role === 'seller') {
        $sellerId = (int) $user['userId'];

        // Stat counts via prepared statements.
        $statQueries = [
            'products'        => 'SELECT COUNT(*) AS c FROM products WHERE sellerUserId = ?',
            'active_products' => 'SELECT COUNT(*) AS c FROM products WHERE sellerUserId = ? AND is_active = 1',
            'orders'          => 'SELECT COUNT(DISTINCT o.orderId) AS c FROM orders o JOIN order_items oi ON oi.orderId = o.orderId WHERE oi.sellerUserId = ?',
            'revenue'         => 'SELECT COALESCE(SUM(oi.quantity * oi.unit_price),0) AS c FROM order_items oi WHERE oi.sellerUserId = ?',
        ];

        foreach ($statQueries as $key => $sql) {
            $stmt = $conn->prepare($sql);
            if ($stmt === false) {
                $sellerStats[$key] = 0;
                continue;
            }
            $stmt->bind_param('i', $sellerId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row    = $result ? $result->fetch_assoc() : null;
            $sellerStats[$key] = $key === 'revenue'
                ? (float) ($row['c'] ?? 0)
                : (int)   ($row['c'] ?? 0);
            $stmt->close();
        }

        $stmt = $conn->prepare('
            SELECT p.productId, p.name,
                   b.name AS brand,
                   c.name AS category,
                   p.description, p.price, p.stock, p.icon_class, p.is_active
            FROM products p
            LEFT JOIN brands     b ON b.brandId    = p.brandId
            LEFT JOIN categories c ON c.categoryId = p.categoryId
            WHERE p.sellerUserId = ?
            ORDER BY p.created_at DESC
        ');
        if ($stmt !== false) {
            $stmt->bind_param('i', $sellerId);
            $stmt->execute();
            $result         = $stmt->get_result();
            $sellerProducts = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
            $stmt->close();
        }

        $sellerOrders = app_get_orders_for_seller($sellerId);
    }

    // ------------------------------------------------------------------
    // Chart data
    // ------------------------------------------------------------------
    $adminOrdersByDay     = [];
    $adminStatusCounts    = ['placed' => 0, 'processing' => 0, 'completed' => 0, 'cancelled' => 0];
    $sellerRevenueByDay   = [];
    $sellerCategoryCounts = [];

    for ($i = 6; $i >= 0; $i--) {
        $label                      = date('M d', strtotime("-{$i} days"));
        $adminOrdersByDay[$label]   = 0;
        $sellerRevenueByDay[$label] = 0;
    }

    if ($role === 'admin' || $role === 'superadmin') {
        $chartRows = $conn->query('
            SELECT DATE(created_at) AS order_day, COUNT(*) AS total
            FROM orders
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(created_at)
        ');
        if ($chartRows !== false) {
            foreach ($chartRows->fetch_all(MYSQLI_ASSOC) as $row) {
                $adminOrdersByDay[date('M d', strtotime($row['order_day']))] = (int) $row['total'];
            }
        }

        $statusRows = $conn->query('SELECT status, COUNT(*) AS total FROM orders GROUP BY status');
        if ($statusRows !== false) {
            foreach ($statusRows->fetch_all(MYSQLI_ASSOC) as $row) {
                $adminStatusCounts[$row['status']] = (int) $row['total'];
            }
        }
    }

    if ($role === 'seller') {
        $sellerId = (int) $user['userId'];

        $stmt = $conn->prepare('
            SELECT DATE(o.created_at) AS order_day, COALESCE(SUM(oi.quantity * oi.unit_price),0) AS total
            FROM orders o
            JOIN order_items oi ON oi.orderId = o.orderId
            WHERE oi.sellerUserId = ?
              AND o.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
            GROUP BY DATE(o.created_at)
        ');
        if ($stmt !== false) {
            $stmt->bind_param('i', $sellerId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
                    $sellerRevenueByDay[date('M d', strtotime($row['order_day']))] = (float) $row['total'];
                }
            }
            $stmt->close();
        }

        $stmt = $conn->prepare('
            SELECT c.name AS category, COUNT(*) AS total
            FROM products p
            LEFT JOIN categories c ON c.categoryId = p.categoryId
            WHERE p.sellerUserId = ?
            GROUP BY c.name
        ');
        if ($stmt !== false) {
            $stmt->bind_param('i', $sellerId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result) {
                foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
                    $sellerCategoryCounts[$row['category']] = (int) $row['total'];
                }
            }
            $stmt->close();
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
        'activityLogs'          => $activityLogs,
        'notifications'         => $notifications,
        'unreadCount'           => $unreadCount,
        'categories'            => $categories,
        'brands'                => $brands,
        'adminOrdersByDay'      => $adminOrdersByDay,
        'adminStatusCounts'     => $adminStatusCounts,
        'sellerRevenueByDay'    => $sellerRevenueByDay,
        'sellerCategoryCounts'  => $sellerCategoryCounts,
    ];
}