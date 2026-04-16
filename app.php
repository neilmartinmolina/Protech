<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/activity_log.php';

function app_db(): mysqli{
    static $conn = null;
    if ($conn instanceof mysqli) {
        return $conn;
    }

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
    http_response_code(500);
    header('Content-Type: application/json');
    exit(json_encode(['success' => false, 'message' => 'Database connection failed.']));
    }

    $conn->set_charset('utf8mb4');
    app_ensure_schema($conn);

    return $conn;
}

function app_column_exists(mysqli $conn, string $table, string $column): bool
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = ?
          AND COLUMN_NAME  = ?
        LIMIT 1
    ");
    $stmt->bind_param('ss', $table, $column);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function app_table_exists(mysqli $conn, string $table): bool
{
    $stmt = $conn->prepare("
        SELECT 1
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = ?
        LIMIT 1
    ");
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $stmt->store_result();
    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function app_ensure_schema(mysqli $conn): void
{
    static $ready = false;

    if ($ready) {
        return;
    }

    // ── users: add any missing columns ───────────────────────────────────────
    if (!app_column_exists($conn, 'users', 'role')) {
        $conn->query("ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'customer' AFTER password_hash");
    }
    if (!app_column_exists($conn, 'users', 'seller_status')) {
        $conn->query("ALTER TABLE users ADD COLUMN seller_status VARCHAR(20) NOT NULL DEFAULT 'not_applicable' AFTER role");
    }
    if (!app_column_exists($conn, 'users', 'temp_password')) {
        $conn->query("ALTER TABLE users ADD COLUMN temp_password VARCHAR(255) DEFAULT NULL AFTER seller_status");
    }
    if (!app_column_exists($conn, 'users', 'avatar_path')) {
        $conn->query("ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) DEFAULT NULL AFTER temp_password");
    }

    $conn->query("ALTER TABLE users MODIFY COLUMN role VARCHAR(20) NOT NULL DEFAULT 'customer'");
    $conn->query("UPDATE users SET role = 'customer' WHERE role = 'user' OR role = '' OR role IS NULL");

    // ── login_attempts ────────────────────────────────────────────────────────
    $conn->query("
        CREATE TABLE IF NOT EXISTS login_attempts (
            loginAttemptId INT UNSIGNED    AUTO_INCREMENT PRIMARY KEY,
            ip             VARCHAR(45)     NOT NULL,
            identifier     VARCHAR(255)    NOT NULL DEFAULT '',
            attempted_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_la_ip_time       (ip, attempted_at),
            INDEX idx_la_ip_identifier (ip, identifier)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    // ── remember_tokens ───────────────────────────────────────────────────────
    $conn->query("
        CREATE TABLE IF NOT EXISTS remember_tokens (
            rememberTokenId INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
            userId          INT UNSIGNED  NOT NULL,
            token_hash      CHAR(64)      NOT NULL,
            expires_at      DATETIME      NOT NULL,
            created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_rt_token_hash (token_hash),
            KEY fk_rt_userId (userId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // ── email_verifications (normalized schema) ───────────────────────────────
    $conn->query("
        CREATE TABLE IF NOT EXISTS email_verifications (
            emailVerificationId INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            userId              INT(10) UNSIGNED NOT NULL,
            token               CHAR(64)         NOT NULL,
            expires_at          DATETIME         NOT NULL,
            used_at             DATETIME         DEFAULT NULL,
            UNIQUE KEY uq_ev_token (token),
            KEY fk_ev_userId (userId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // ── categories ────────────────────────────────────────────────────────────
    $conn->query("
        CREATE TABLE IF NOT EXISTS categories (
            categoryId INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name       VARCHAR(100)    NOT NULL,
            slug       VARCHAR(100)    NOT NULL,
            UNIQUE KEY uq_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // ── brands ────────────────────────────────────────────────────────────────
    $conn->query("
        CREATE TABLE IF NOT EXISTS brands (
            brandId INT(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name    VARCHAR(100)    NOT NULL,
            UNIQUE KEY uq_brand_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Seed categories
    foreach ([
        ['Laptops',     'laptops'],
        ['Desktops',    'desktops'],
        ['Peripherals', 'peripherals'],
        ['Networking',  'networking'],
    ] as [$catName, $catSlug]) {
        $s = $conn->prepare("INSERT IGNORE INTO categories (name, slug) VALUES (?, ?)");
        $s->bind_param('ss', $catName, $catSlug);
        $s->execute();
        $s->close();
    }

    // Seed brands
    foreach (['Lenovo','Acer','ASUS','HP','MSI','Dell','Logitech','Razer','Anker','TP-Link','Cisco','Ubiquiti'] as $brandName) {
        $s = $conn->prepare("INSERT IGNORE INTO brands (name) VALUES (?)");
        $s->bind_param('s', $brandName);
        $s->execute();
        $s->close();
    }

    // ── products ──────────────────────────────────────────────────────────────
    $conn->query("
        CREATE TABLE IF NOT EXISTS products (
            productId    INT(10) UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
            sellerUserId INT(10) UNSIGNED  DEFAULT NULL,
            name         VARCHAR(150)      NOT NULL,
            brandId      INT(10) UNSIGNED  DEFAULT NULL,
            categoryId   INT(10) UNSIGNED  DEFAULT NULL,
            description  TEXT              DEFAULT NULL,
            price        DECIMAL(10,2)     NOT NULL DEFAULT 0.00,
            stock        INT               NOT NULL DEFAULT 0,
            icon_class   VARCHAR(100)      NOT NULL DEFAULT 'fa-solid fa-box-open',
            is_active    TINYINT(1)        NOT NULL DEFAULT 1,
            created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_prod_brandId     (brandId),
            INDEX idx_prod_categoryId  (categoryId),
            INDEX idx_prod_price       (price),
            INDEX idx_prod_sellerUserId (sellerUserId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    if (!app_column_exists($conn, 'products', 'updated_at')) {
        $conn->query("ALTER TABLE products ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
    }

    // ── orders ────────────────────────────────────────────────────────────────
    $conn->query("
        CREATE TABLE IF NOT EXISTS orders (
            orderId      INT(10) UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
            userId       INT(10) UNSIGNED  NOT NULL,
            total_amount DECIMAL(10,2)     NOT NULL DEFAULT 0.00,
            status       VARCHAR(30)       NOT NULL DEFAULT 'placed',
            created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ord_userId       (userId),
            INDEX idx_ord_status       (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    $conn->query("ALTER TABLE orders MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'placed'");

    // ── order_items ───────────────────────────────────────────────────────────
    $conn->query("
        CREATE TABLE IF NOT EXISTS order_items (
            orderItemId  INT(10) UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
            orderId      INT(10) UNSIGNED  NOT NULL,
            productId    INT(10) UNSIGNED  NOT NULL,
            sellerUserId INT(10) UNSIGNED  DEFAULT NULL,
            product_name VARCHAR(150)      NOT NULL DEFAULT '',
            quantity     INT               NOT NULL DEFAULT 1,
            unit_price   DECIMAL(10,2)     NOT NULL DEFAULT 0.00,
            created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_oi_orderId      (orderId),
            INDEX idx_oi_productId    (productId),
            INDEX idx_oi_sellerUserId (sellerUserId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    // ── seller_applications ───────────────────────────────────────────────────
    $conn->query("
        CREATE TABLE IF NOT EXISTS seller_applications (
            sellerApplicationId INT(10) UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
            userId              INT(10) UNSIGNED  NOT NULL,
            store_name          VARCHAR(150)      NOT NULL,
            reason              TEXT              DEFAULT NULL,
            status              ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
            rejection_reason    VARCHAR(255)      DEFAULT NULL,
            reviewedByUserId    INT(10) UNSIGNED  DEFAULT NULL,
            reviewed_at         DATETIME          DEFAULT NULL,
            created_at          DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY idx_sapp_userId           (userId),
            KEY idx_sapp_status           (status),
            KEY idx_sapp_reviewedByUserId (reviewedByUserId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // ── user_payment_methods ──────────────────────────────────────────────────
    $conn->query("
        CREATE TABLE IF NOT EXISTS user_payment_methods (
            userPaymentMethodId INT(10) UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
            userId              INT(10) UNSIGNED  NOT NULL,
            type                ENUM('gcash','cod') NOT NULL,
            label               VARCHAR(100)      DEFAULT NULL,
            gcash_name          VARCHAR(150)      DEFAULT NULL,
            gcash_number        VARCHAR(20)       DEFAULT NULL,
            is_default          TINYINT(1)        NOT NULL DEFAULT 0,
            created_at          TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY fk_upm_userId (userId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // ── user_addresses ────────────────────────────────────────────────────────
    $conn->query("
        CREATE TABLE IF NOT EXISTS user_addresses (
            userAddressId  INT(10) UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
            userId         INT(10) UNSIGNED  NOT NULL,
            recipient_name VARCHAR(150)      NOT NULL,
            phone          VARCHAR(20)       NOT NULL,
            label          VARCHAR(100)      DEFAULT NULL,
            street         TEXT              NOT NULL,
            barangay       VARCHAR(100)      NOT NULL,
            city           VARCHAR(100)      NOT NULL,
            province       VARCHAR(100)      DEFAULT NULL,
            zip            VARCHAR(20)       DEFAULT NULL,
            is_default     TINYINT(1)        NOT NULL DEFAULT 0,
            created_at     TIMESTAMP         NOT NULL DEFAULT CURRENT_TIMESTAMP,
            KEY fk_ua_userId (userId)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // ── Promote superadmin user ─────────────────────────────────────────────────
    $superadminEmail = defined('SUPERADMIN_EMAIL') ? SUPERADMIN_EMAIL : 'neilmartinmolina@gmail.com';
    $stmt = $conn->prepare("UPDATE users SET role = 'superadmin', seller_status = 'approved' WHERE email = ?");
    $stmt->bind_param('s', $superadminEmail);
    $stmt->execute();
    $stmt->close();

    // ── Seed products ─────────────────────────────────────────────────────────
    $seedProducts = [
        ['ProBook X1 Ultra',         'Lenovo',   'Laptops',     '15.6-inch 4K OLED with Intel i9, 32GB RAM, and 1TB SSD.',                  1499.00, 15, 'fa-solid fa-laptop'],
        ['SlimBook Air 14',          'Acer',     'Laptops',     'Thin and light work laptop with all-day battery life.',                      899.00, 24, 'fa-solid fa-laptop'],
        ['ZenWork Studio 16',        'ASUS',     'Laptops',     '16-inch creator laptop with RTX graphics and color-accurate display.',      1899.00,  9, 'fa-solid fa-laptop'],
        ['TravelMate Lite 13',       'HP',       'Laptops',     'Compact business laptop with strong battery life for daily travel.',         749.00, 28, 'fa-solid fa-laptop'],
        ['PowerEdge Gamer X',        'MSI',      'Laptops',     'Gaming laptop with fast refresh panel and high-end cooling.',               1699.00, 11, 'fa-solid fa-laptop'],
        ['TowerMax Pro 5000',        'ASUS',     'Desktops',    'Ryzen 9 desktop with RTX graphics for demanding workflows.',                2299.00,  8, 'fa-solid fa-desktop'],
        ['CompactDesk Mini',         'Dell',     'Desktops',    'Small-form-factor workstation for office and home setups.',                 1099.00, 18, 'fa-solid fa-server'],
        ['CreatorCube X',            'HP',       'Desktops',    'Quiet desktop tower tuned for design, editing, and multitasking.',          1399.00, 13, 'fa-solid fa-computer'],
        ['OfficeCore SFF',           'Lenovo',   'Desktops',    'Reliable desktop for teams needing solid everyday performance.',              799.00, 31, 'fa-solid fa-desktop'],
        ['RenderStation Z8',         'Acer',     'Desktops',    'High-memory workstation desktop designed for 3D and CAD workloads.',        2599.00,  6, 'fa-solid fa-server'],
        ['MechStrike RGB Keyboard',  'Logitech', 'Peripherals', 'Mechanical keyboard with RGB lighting and hot-swappable switches.',          149.00, 45, 'fa-solid fa-keyboard'],
        ['PrecisionGlide Mouse',     'Razer',    'Peripherals', 'Wireless ergonomic mouse with high-DPI sensor.',                             79.00, 56, 'fa-solid fa-computer-mouse'],
        ['VisionPro 4K Monitor',     'Dell',     'Peripherals', '27-inch 4K IPS monitor with USB-C docking support.',                        499.00, 22, 'fa-solid fa-display'],
        ['ClearVoice Headset',       'Logitech', 'Peripherals', 'Noise-cancelling headset for support, meetings, and streaming.',             119.00, 39, 'fa-solid fa-headset'],
        ['DockHub Thunderbolt',      'Anker',    'Peripherals', 'Multi-port dock with dual display support and fast charging.',               199.00, 27, 'fa-solid fa-plug'],
        ['NetPro Wi-Fi 7 Router',    'TP-Link',  'Networking',  'Tri-band router with mesh-ready Wi-Fi 7 performance.',                      349.00, 20, 'fa-solid fa-wifi'],
        ['SwitchPro 24-Port',        'Cisco',    'Networking',  'Managed gigabit switch with VLAN and PoE support.',                         249.00, 12, 'fa-solid fa-ethernet'],
        ['MeshLink AX3000',          'TP-Link',  'Networking',  'Dual-node mesh kit for whole-home wireless coverage.',                      279.00, 17, 'fa-solid fa-network-wired'],
        ['SecureGate Firewall',      'Cisco',    'Networking',  'Small business firewall appliance with VPN and threat filtering.',           599.00,  7, 'fa-solid fa-shield-halved'],
        ['CloudBridge Access Point', 'Ubiquiti', 'Networking',  'Ceiling-mount access point for stable office Wi-Fi.',                       189.00, 26, 'fa-solid fa-tower-broadcast'],
    ];

    $checkStmt  = $conn->prepare("SELECT productId FROM products WHERE name = ? LIMIT 1");
    $getBrandId = $conn->prepare("SELECT brandId FROM brands WHERE name = ? LIMIT 1");
    $getCatId   = $conn->prepare("SELECT categoryId FROM categories WHERE name = ? LIMIT 1");
    $insertStmt = $conn->prepare("
        INSERT INTO products (name, brandId, categoryId, description, price, stock, icon_class)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($seedProducts as [$name, $brand, $category, $description, $price, $stock, $iconClass]) {
        $checkStmt->bind_param('s', $name);
        $checkStmt->execute();
        if ($checkStmt->get_result()->fetch_assoc()) {
            continue;
        }

        $getBrandId->bind_param('s', $brand);
        $getBrandId->execute();
        $brandRow = $getBrandId->get_result()->fetch_assoc();
        $brandId  = $brandRow['brandId'] ?? null;

        $getCatId->bind_param('s', $category);
        $getCatId->execute();
        $catRow     = $getCatId->get_result()->fetch_assoc();
        $categoryId = $catRow['categoryId'] ?? null;

        $insertStmt->bind_param('siisdis', $name, $brandId, $categoryId, $description, $price, $stock, $iconClass);
        $insertStmt->execute();
    }

    $checkStmt->close();
    $getBrandId->close();
    $getCatId->close();
    $insertStmt->close();

    $ready = true;
    // ── END app_ensure_schema ─────────────────────────────────────────────────
}

function app_current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function app_refresh_session_user(int $userId): ?array
{
    $conn = app_db();
    $stmt = $conn->prepare("
        SELECT userId, first_name, last_name, username, email,
               role, seller_status, avatar_path
        FROM users
        WHERE userId = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user) {
        unset($_SESSION['user']);
        return null;
    }

    $storeName = null;
    if (($user['role'] ?? '') === 'seller') {
        $stmt = $conn->prepare("
            SELECT store_name
            FROM seller_applications
            WHERE userId = ?
              AND (
                (? = 'approved' AND status = 'approved')
                OR
                (? != 'approved')
              )
            ORDER BY
              CASE WHEN status = 'approved' THEN reviewed_at ELSE created_at END DESC
            LIMIT 1
        ");
        $sellerStatus = (string) ($user['seller_status'] ?? '');
        $stmt->bind_param('iss', $userId, $sellerStatus, $sellerStatus);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $storeName = $row['store_name'] ?? null;
    }

    $_SESSION['user'] = [
        'userId'        => (int) $user['userId'],
        'firstName'     => $user['first_name'],
        'lastName'      => $user['last_name'],
        'username'      => $user['username'],
        'email'         => $user['email'],
        'role'          => $user['role'],
        'seller_status' => $user['seller_status'],
        'store_name'    => $storeName,
        'avatar_path'   => $user['avatar_path'] ?? null,
    ];

    return $_SESSION['user'];
}

function app_require_login(): array{
    $user = app_current_user();

    if (!$user) {
        header('Location: login.php');
        exit;
    }

    return app_refresh_session_user((int) $user['userId']) ?? $user;
}

function app_is_admin(array $user): bool{
    $role = $user['role'] ?? '';
    return $role === 'admin' || $role === 'superadmin';
}

function app_is_superadmin(array $user): bool{
    return ($user['role'] ?? '') === 'superadmin';
}

function app_can_add_admin(array $actor): bool{
    return app_is_superadmin($actor);
}

function app_can_delete_user(array $actor, array $target): bool
{
    $actorRole = $actor['role'] ?? '';
    $targetRole = $target['role'] ?? '';

    if (app_is_superadmin($actor)) {
        return $targetRole !== 'superadmin';
    }

    if (app_is_admin($actor)) {
        return $targetRole !== 'admin' && $targetRole !== 'superadmin';
    }

    return false;
}

function app_is_seller(array $user): bool
{
    return ($user['role'] ?? '') === 'seller' && ($user['seller_status'] ?? '') === 'approved';
}

function app_dashboard_redirect(array $user): string
{
    if (app_is_admin($user) || app_is_seller($user)) {
        return 'dashboard.php';
    }

    return 'index.php';
}

function app_cart_count(): int
{
    return array_sum(array_map('intval', $_SESSION['cart'] ?? []));
}

function app_cart_items(): array
{
    $cart = $_SESSION['cart'] ?? [];
    if (!$cart) {
        return [];
    }

    $productIds   = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $types        = str_repeat('i', count($productIds));

    $conn = app_db();
    $stmt = $conn->prepare("
        SELECT p.productId, p.name, p.description, p.price, p.stock, p.icon_class,
               b.name AS brand,
               c.name AS category
        FROM   products    p
        LEFT JOIN brands     b ON b.brandId    = p.brandId
        LEFT JOIN categories c ON c.categoryId = p.categoryId
        WHERE  p.productId IN ($placeholders)
          AND  p.is_active = 1
    ");
    $stmt->bind_param($types, ...$productIds);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $qty               = max(1, (int) ($cart[$row['productId']] ?? 1));
        $row['quantity']   = $qty;
        $row['line_total'] = $qty * (float) $row['price'];
        $items[]           = $row;
    }
    $stmt->close();

    usort($items, fn ($a, $b) => $a['productId'] <=> $b['productId']);

    return $items;
}

function app_cart_total(): float
{
    $total = 0.0;
    foreach (app_cart_items() as $item) {
        $total += (float) $item['line_total'];
    }

    return $total;
}

function app_sanitize(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function app_avatar_upload_dir(): string
{
    $dir = __DIR__ . '/media/avatars';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    return $dir;
}

function app_store_avatar(array $file, ?string $existingPath = null): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'path' => $existingPath];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Avatar upload failed. Please try a different image.'];
    }

    $tmpName = $file['tmp_name'] ?? '';
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['success' => false, 'message' => 'Invalid avatar upload.'];
    }

    $imageInfo = @getimagesize($tmpName);
    if ($imageInfo === false) {
        return ['success' => false, 'message' => 'Avatar must be a valid image file.'];
    }

    if (($file['size'] ?? 0) > 2 * 1024 * 1024) {
        return ['success' => false, 'message' => 'Avatar must be 2MB or smaller.'];
    }

    $mime    = $imageInfo['mime'] ?? '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    if (!isset($allowed[$mime])) {
        return ['success' => false, 'message' => 'Avatar must be JPG, PNG, WEBP, or GIF.'];
    }

    $fileName   = 'avatar_' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    $targetDir  = app_avatar_upload_dir();
    $targetPath = $targetDir . '/' . $fileName;

    if (!move_uploaded_file($tmpName, $targetPath)) {
        return ['success' => false, 'message' => 'Could not save avatar. Please try again.'];
    }

    if ($existingPath) {
        $oldPath = __DIR__ . '/' . ltrim($existingPath, '/');
        if (is_file($oldPath) && str_contains(str_replace('\\', '/', $oldPath), '/media/avatars/')) {
            @unlink($oldPath);
        }
    }

    return ['success' => true, 'path' => 'media/avatars/' . $fileName];
}

function app_avatar_url(?array $user): ?string
{
    return $user['avatar_path'] ?? null;
}

function app_seller_owns_product(array $user, int $productId): bool
{
    if (!app_is_seller($user)) {
        return false;
    }

    $conn = app_db();
    $stmt = $conn->prepare('
        SELECT productId FROM products
        WHERE productId = ? AND sellerUserId = ?
        LIMIT 1
    ');
    $stmt->bind_param('ii', $productId, $user['userId']);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (bool) $exists;
}

function app_get_orders_for_customer(int $userId): array
{
    $conn = app_db();
    $stmt = $conn->prepare("
        SELECT o.orderId, o.total_amount, o.status, o.created_at,
               COUNT(oi.orderItemId) AS item_count
        FROM       orders      o
        LEFT JOIN  order_items oi ON oi.orderId = o.orderId
        WHERE o.userId = ?
        GROUP BY o.orderId, o.total_amount, o.status, o.created_at
        ORDER BY o.created_at DESC
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $orders;
}

function app_get_orders_for_seller(?int $sellerId = null): array
{
    $conn = app_db();

    if ($sellerId === null) {
        $stmt = $conn->prepare("
            SELECT
                o.orderId,
                o.userId,
                o.total_amount,
                o.status,
                o.created_at,
                CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
                COUNT(oi.orderItemId)                  AS item_count,
                GROUP_CONCAT(DISTINCT COALESCE(
                    (SELECT sa.store_name
                     FROM seller_applications sa
                     WHERE sa.userId = s.userId AND sa.status = 'approved'
                     ORDER BY sa.reviewed_at DESC
                     LIMIT 1),
                    s.username,
                    'Marketplace'
                ) SEPARATOR ', ') AS seller_name
            FROM orders o
            JOIN users u ON u.userId = o.userId
            LEFT JOIN order_items oi ON oi.orderId = o.orderId
            LEFT JOIN users s ON s.userId = oi.sellerUserId
            GROUP BY o.orderId, o.userId, o.total_amount, o.status, o.created_at, u.first_name, u.last_name
            ORDER BY o.created_at DESC
        ");
        $stmt->execute();
        $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $orders;
    }

    $stmt = $conn->prepare("
        SELECT
            o.orderId,
            o.userId,
            o.total_amount,
            o.status,
            o.created_at,
            CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
            COUNT(oi.orderItemId)                  AS item_count
        FROM orders o
        JOIN users u ON u.userId = o.userId
        JOIN order_items oi ON oi.orderId = o.orderId
        WHERE oi.sellerUserId = ?
        GROUP BY o.orderId, o.userId, o.total_amount, o.status, o.created_at, u.first_name, u.last_name
        ORDER BY o.created_at DESC
    ");
    $stmt->bind_param('i', $sellerId);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $orders;
}

function app_checkout(int $userId): array
{
    $items = app_cart_items();

    if (!$items) {
        return ['success' => false, 'message' => 'Your cart is empty.'];
    }

    $conn = app_db();
    $conn->begin_transaction();

    try {
        $productIds   = array_map(fn ($item) => (int) $item['productId'], $items);
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $types        = str_repeat('i', count($productIds));

        $stmt = $conn->prepare("
            SELECT productId, sellerUserId, stock, price, is_active, name
            FROM products
            WHERE productId IN ($placeholders)
            FOR UPDATE
        ");
        $stmt->bind_param($types, ...$productIds);
        $stmt->execute();
        $result     = $stmt->get_result();
        $productMap = [];
        while ($row = $result->fetch_assoc()) {
            $productMap[(int) $row['productId']] = $row;
        }
        $stmt->close();

        $grouped = [];
        foreach ($items as $item) {
            $product = $productMap[(int) $item['productId']] ?? null;
            if (!$product || !(int) $product['is_active']) {
                throw new RuntimeException('One of the products in your cart is no longer available.');
            }
            if ((int) $product['stock'] < (int) $item['quantity']) {
                throw new RuntimeException('Insufficient stock for ' . $product['name'] . '.');
            }

            $sellerKey = $product['sellerUserId'] !== null
                ? (string) (int) $product['sellerUserId']
                : 'marketplace';

            if (!isset($grouped[$sellerKey])) {
                $grouped[$sellerKey] = [
                    'sellerUserId' => $product['sellerUserId'] !== null ? (int) $product['sellerUserId'] : null,
                    'items'        => [],
                    'total'        => 0.0,
                ];
            }

            $lineTotal = (float) $product['price'] * (int) $item['quantity'];
            $grouped[$sellerKey]['items'][] = [
                'productId'  => (int) $product['productId'],
                'quantity'   => (int) $item['quantity'],
                'unit_price' => (float) $product['price'],
                'name'       => $product['name'],
            ];
            $grouped[$sellerKey]['total'] += $lineTotal;
        }

        $orderStmt = $conn->prepare("INSERT INTO orders (userId, total_amount, status) VALUES (?, ?, 'placed')");
        $itemStmt             = $conn->prepare("INSERT INTO order_items (orderId, productId, sellerUserId, product_name, quantity, unit_price) VALUES (?, ?, ?, ?, ?, ?)");
        $stockStmt            = $conn->prepare("UPDATE products SET stock = stock - ? WHERE productId = ?");

        $createdOrderIds = [];
        foreach ($grouped as $group) {
            $sellerId = $group['sellerUserId'];
            $total    = $group['total'];

            $orderStmt->bind_param('id', $userId, $total);
            $orderStmt->execute();
            $orderId           = (int) $conn->insert_id;
            $createdOrderIds[] = $orderId;

            foreach ($group['items'] as $item) {
                $itemStmt->bind_param(
                    'iiisid',
                    $orderId,
                    $item['productId'],
                    $sellerId,
                    $item['name'],
                    $item['quantity'],
                    $item['unit_price']
                );
                $itemStmt->execute();
                $stockStmt->bind_param('ii', $item['quantity'], $item['productId']);
                $stockStmt->execute();
            }
        }

        $orderStmt->close();
        $itemStmt->close();
        $stockStmt->close();

        $_SESSION['cart'] = [];
        $conn->commit();

        return ['success' => true, 'message' => 'Checkout complete.', 'order_ids' => $createdOrderIds];

    } catch (Throwable $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function app_upsert_product(array $user, array $data): array
{
    if (!app_is_seller($user) && !app_is_admin($user)) {
        return ['success' => false, 'message' => 'Unauthorized product action.'];
    }

    $productId    = (int)   ($data['product_id']  ?? 0);
    $name         = trim(    $data['name']         ?? '');
    $brandName    = trim(    $data['brand']        ?? '');
    $categoryName = trim(    $data['category']     ?? '');
    $description  = trim(    $data['description']  ?? '');
    $price        = (float) ($data['price']        ?? 0);
    $stock        = max(0, (int) ($data['stock']   ?? 0));
    $iconClass    = trim(    $data['icon_class']   ?? 'fa-solid fa-box-open');
    $isActive     = !empty(  $data['is_active'])  ? 1 : 0;

    if ($name === '' || $brandName === '' || $categoryName === '' || $description === '') {
        return ['success' => false, 'message' => 'Name, brand, category, and description are required.'];
    }
    if ($price <= 0) {
        return ['success' => false, 'message' => 'Price must be greater than zero.'];
    }

    $conn = app_db();

    // Resolve brandId — insert if new
    $stmt = $conn->prepare("SELECT brandId FROM brands WHERE name = ? LIMIT 1");
    $stmt->bind_param('s', $brandName);
    $stmt->execute();
    $brandRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($brandRow) {
        $brandId = (int) $brandRow['brandId'];
    } else {
        $s = $conn->prepare("INSERT INTO brands (name) VALUES (?)");
        $s->bind_param('s', $brandName);
        $s->execute();
        $brandId = (int) $conn->insert_id;
        $s->close();
    }

    // Resolve categoryId — insert if new
    $stmt = $conn->prepare("SELECT categoryId FROM categories WHERE name = ? LIMIT 1");
    $stmt->bind_param('s', $categoryName);
    $stmt->execute();
    $catRow = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($catRow) {
        $categoryId = (int) $catRow['categoryId'];
    } else {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $categoryName));
        $s = $conn->prepare("INSERT IGNORE INTO categories (name, slug) VALUES (?, ?)");
        $s->bind_param('ss', $categoryName, $slug);
        $s->execute();
        $categoryId = (int) $conn->insert_id;
        $s->close();
    }

    $sellerId = app_is_admin($user)
        ? (($data['seller_id'] ?? '') !== '' ? (int) $data['seller_id'] : null)
        : (int) $user['userId'];

    if ($productId > 0) {
        if (app_is_seller($user) && !app_seller_owns_product($user, $productId)) {
            return ['success' => false, 'message' => 'You can only edit your own products.'];
        }

        $stmt = $conn->prepare("
            UPDATE products
            SET name = ?, brandId = ?, categoryId = ?, description = ?,
                price = ?, stock = ?, icon_class = ?, is_active = ?, sellerUserId = ?
            WHERE productId = ?
        ");
        $stmt->bind_param('siisdisiii', $name, $brandId, $categoryId, $description, $price, $stock, $iconClass, $isActive, $sellerId, $productId);
        $stmt->execute();
        $stmt->close();

        return ['success' => true, 'message' => 'Product updated successfully.', 'product_id' => $productId];
    }

    $stmt = $conn->prepare("
        INSERT INTO products (sellerUserId, name, brandId, categoryId, description, price, stock, icon_class, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('isiisdisi', $sellerId, $name, $brandId, $categoryId, $description, $price, $stock, $iconClass, $isActive);
    $stmt->execute();
    $newId = (int) $conn->insert_id;
    $stmt->close();

    return ['success' => true, 'message' => 'Product created successfully.', 'product_id' => $newId];
}