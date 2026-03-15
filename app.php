<?php
require_once __DIR__ . '/config.php';

function app_db(): mysqli
{
    static $conn = null;

    if ($conn instanceof mysqli) {
        return $conn;
    }

    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conn->connect_error) {
        http_response_code(500);
        exit('Database connection failed.');
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
          AND TABLE_NAME = ?
          AND COLUMN_NAME = ?
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
          AND TABLE_NAME = ?
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

    if (!app_column_exists($conn, 'users', 'role')) {
        $conn->query("ALTER TABLE users ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'customer' AFTER password_hash");
    }

    if (!app_column_exists($conn, 'users', 'seller_status')) {
        $conn->query("ALTER TABLE users ADD COLUMN seller_status VARCHAR(20) NOT NULL DEFAULT 'not_applicable' AFTER role");
    }

    if (!app_column_exists($conn, 'users', 'store_name')) {
        $conn->query("ALTER TABLE users ADD COLUMN store_name VARCHAR(150) DEFAULT NULL AFTER seller_status");
    }

    if (!app_column_exists($conn, 'users', 'temp_password')) {
        $conn->query("ALTER TABLE users ADD COLUMN temp_password VARCHAR(255) DEFAULT NULL AFTER store_name");
    }

    if (!app_column_exists($conn, 'users', 'avatar_path')) {
        $conn->query("ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) DEFAULT NULL AFTER temp_password");
    }

    $conn->query("ALTER TABLE users MODIFY COLUMN role VARCHAR(20) NOT NULL DEFAULT 'customer'");
    $conn->query("UPDATE users SET role = 'customer' WHERE role = 'user' OR role = '' OR role IS NULL");

    $conn->query("
        CREATE TABLE IF NOT EXISTS verification_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at DATETIME NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_token (token),
            INDEX idx_expires (expires_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS login_attempts (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            ip VARCHAR(45) NOT NULL,
            attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_time (ip, attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS products (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            seller_id INT UNSIGNED DEFAULT NULL,
            name VARCHAR(150) NOT NULL,
            brand VARCHAR(100) NOT NULL,
            category VARCHAR(100) NOT NULL,
            description TEXT DEFAULT NULL,
            price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            stock INT NOT NULL DEFAULT 0,
            icon_class VARCHAR(100) NOT NULL DEFAULT 'fa-solid fa-box-open',
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_brand (brand),
            INDEX idx_category (category),
            INDEX idx_price (price),
            INDEX idx_seller (seller_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $conn->query("
        CREATE TABLE IF NOT EXISTS orders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            seller_id INT UNSIGNED DEFAULT NULL,
            total_amount DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            status VARCHAR(30) NOT NULL DEFAULT 'placed',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_seller (seller_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $conn->query("ALTER TABLE orders MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'placed'");

    if (!app_column_exists($conn, 'products', 'updated_at')) {
        $conn->query("ALTER TABLE products ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
    }

    $conn->query("
        CREATE TABLE IF NOT EXISTS order_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            unit_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_order (order_id),
            INDEX idx_product (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $adminEmail = defined('ADMIN_EMAIL') ? ADMIN_EMAIL : 'neilmartinmolina@gmail.com';
    $stmt = $conn->prepare("UPDATE users SET role = 'admin', seller_status = 'approved' WHERE email = ?");
    $stmt->bind_param('s', $adminEmail);
    $stmt->execute();
    $stmt->close();

    $seedProducts = [
        ['ProBook X1 Ultra', 'Lenovo', 'Laptops', '15.6-inch 4K OLED with Intel i9, 32GB RAM, and 1TB SSD.', 1499.00, 15, 'fa-solid fa-laptop'],
        ['SlimBook Air 14', 'Acer', 'Laptops', 'Thin and light work laptop with all-day battery life.', 899.00, 24, 'fa-solid fa-laptop'],
        ['ZenWork Studio 16', 'ASUS', 'Laptops', '16-inch creator laptop with RTX graphics and color-accurate display.', 1899.00, 9, 'fa-solid fa-laptop'],
        ['TravelMate Lite 13', 'HP', 'Laptops', 'Compact business laptop with strong battery life for daily travel.', 749.00, 28, 'fa-solid fa-laptop'],
        ['PowerEdge Gamer X', 'MSI', 'Laptops', 'Gaming laptop with fast refresh panel and high-end cooling.', 1699.00, 11, 'fa-solid fa-laptop'],

        ['TowerMax Pro 5000', 'ASUS', 'Desktops', 'Ryzen 9 desktop with RTX graphics for demanding workflows.', 2299.00, 8, 'fa-solid fa-desktop'],
        ['CompactDesk Mini', 'Dell', 'Desktops', 'Small-form-factor workstation for office and home setups.', 1099.00, 18, 'fa-solid fa-server'],
        ['CreatorCube X', 'HP', 'Desktops', 'Quiet desktop tower tuned for design, editing, and multitasking.', 1399.00, 13, 'fa-solid fa-computer'],
        ['OfficeCore SFF', 'Lenovo', 'Desktops', 'Reliable desktop for teams needing solid everyday performance.', 799.00, 31, 'fa-solid fa-desktop'],
        ['RenderStation Z8', 'Acer', 'Desktops', 'High-memory workstation desktop designed for 3D and CAD workloads.', 2599.00, 6, 'fa-solid fa-server'],

        ['MechStrike RGB Keyboard', 'Logitech', 'Peripherals', 'Mechanical keyboard with RGB lighting and hot-swappable switches.', 149.00, 45, 'fa-solid fa-keyboard'],
        ['PrecisionGlide Mouse', 'Razer', 'Peripherals', 'Wireless ergonomic mouse with high-DPI sensor.', 79.00, 56, 'fa-solid fa-computer-mouse'],
        ['VisionPro 4K Monitor', 'Dell', 'Peripherals', '27-inch 4K IPS monitor with USB-C docking support.', 499.00, 22, 'fa-solid fa-display'],
        ['ClearVoice Headset', 'Logitech', 'Peripherals', 'Noise-cancelling headset for support, meetings, and streaming.', 119.00, 39, 'fa-solid fa-headset'],
        ['DockHub Thunderbolt', 'Anker', 'Peripherals', 'Multi-port dock with dual display support and fast charging.', 199.00, 27, 'fa-solid fa-plug'],

        ['NetPro Wi-Fi 7 Router', 'TP-Link', 'Networking', 'Tri-band router with mesh-ready Wi-Fi 7 performance.', 349.00, 20, 'fa-solid fa-wifi'],
        ['SwitchPro 24-Port', 'Cisco', 'Networking', 'Managed gigabit switch with VLAN and PoE support.', 249.00, 12, 'fa-solid fa-ethernet'],
        ['MeshLink AX3000', 'TP-Link', 'Networking', 'Dual-node mesh kit for whole-home wireless coverage.', 279.00, 17, 'fa-solid fa-network-wired'],
        ['SecureGate Firewall', 'Cisco', 'Networking', 'Small business firewall appliance with VPN and threat filtering.', 599.00, 7, 'fa-solid fa-shield-halved'],
        ['CloudBridge Access Point', 'Ubiquiti', 'Networking', 'Ceiling-mount access point for stable office Wi-Fi.', 189.00, 26, 'fa-solid fa-tower-broadcast'],
    ];

    $checkStmt = $conn->prepare("SELECT id FROM products WHERE name = ? LIMIT 1");
    $insertStmt = $conn->prepare("
        INSERT INTO products (name, brand, category, description, price, stock, icon_class)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($seedProducts as $product) {
        [$name, $brand, $category, $description, $price, $stock, $iconClass] = $product;

        $checkStmt->bind_param('s', $name);
        $checkStmt->execute();
        $exists = $checkStmt->get_result()->fetch_assoc();

        if ($exists) {
            continue;
        }

        $insertStmt->bind_param('ssssdis', $name, $brand, $category, $description, $price, $stock, $iconClass);
        $insertStmt->execute();
    }

    $checkStmt->close();
    $insertStmt->close();

    if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    $ready = true;
}

function app_current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function app_refresh_session_user(int $userId): ?array
{
    $conn = app_db();
    $stmt = $conn->prepare("
        SELECT id, first_name, last_name, username, email, role, seller_status, store_name, avatar_path
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();

    if (!$user) {
        unset($_SESSION['user']);
        return null;
    }

    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'firstName' => $user['first_name'],
        'lastName' => $user['last_name'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role'],
        'seller_status' => $user['seller_status'],
        'store_name' => $user['store_name'],
        'avatar_path' => $user['avatar_path'] ?? null,
    ];

    return $_SESSION['user'];
}

function app_require_login(): array
{
    $user = app_current_user();

    if (!$user) {
        header('Location: login.php');
        exit;
    }

    return app_refresh_session_user((int) $user['id']) ?? $user;
}

function app_is_admin(array $user): bool
{
    return ($user['role'] ?? '') === 'admin';
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

    $productIds = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $types = str_repeat('i', count($productIds));

    $conn = app_db();
    $stmt = $conn->prepare("
        SELECT id, name, brand, category, description, price, stock, icon_class
        FROM products
        WHERE id IN ($placeholders) AND is_active = 1
    ");
    $stmt->bind_param($types, ...$productIds);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $qty = max(1, (int) ($cart[$row['id']] ?? 1));
        $row['quantity'] = $qty;
        $row['line_total'] = $qty * (float) $row['price'];
        $items[] = $row;
    }
    $stmt->close();

    usort($items, fn ($a, $b) => $a['id'] <=> $b['id']);

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

    $mime = $imageInfo['mime'] ?? '';
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($allowed[$mime])) {
        return ['success' => false, 'message' => 'Avatar must be JPG, PNG, WEBP, or GIF.'];
    }

    $fileName = 'avatar_' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    $targetDir = app_avatar_upload_dir();
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
    $path = $user['avatar_path'] ?? null;
    if (!$path) {
        return null;
    }

    return $path;
}

function app_seller_owns_product(array $user, int $productId): bool
{
    if (!app_is_seller($user)) {
        return false;
    }

    $conn = app_db();
    $stmt = $conn->prepare('SELECT id FROM products WHERE id = ? AND seller_id = ? LIMIT 1');
    $stmt->bind_param('ii', $productId, $user['id']);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (bool) $exists;
}

function app_get_orders_for_customer(int $userId): array
{
    $conn = app_db();
    $stmt = $conn->prepare("
        SELECT o.id, o.total_amount, o.status, o.created_at,
               COUNT(oi.id) AS item_count
        FROM orders o
        LEFT JOIN order_items oi ON oi.order_id = o.id
        WHERE o.user_id = ?
        GROUP BY o.id, o.total_amount, o.status, o.created_at
        ORDER BY o.created_at DESC
    ");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $orders;
}

function app_get_orders_for_seller(?int $sellerId = null): array
{
    $conn = app_db();

    if ($sellerId === null) {
        $sql = "
            SELECT o.id, o.user_id, o.seller_id, o.total_amount, o.status, o.created_at,
                   CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
                   COALESCE(s.store_name, s.username, 'Marketplace') AS seller_name,
                   COUNT(oi.id) AS item_count
            FROM orders o
            JOIN users u ON u.id = o.user_id
            LEFT JOIN users s ON s.id = o.seller_id
            LEFT JOIN order_items oi ON oi.order_id = o.id
            GROUP BY o.id, o.user_id, o.seller_id, o.total_amount, o.status, o.created_at, customer_name, seller_name
            ORDER BY o.created_at DESC
        ";
        return $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
    }

    $stmt = $conn->prepare("
        SELECT o.id, o.user_id, o.seller_id, o.total_amount, o.status, o.created_at,
               CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
               COUNT(oi.id) AS item_count
        FROM orders o
        JOIN users u ON u.id = o.user_id
        LEFT JOIN order_items oi ON oi.order_id = o.id
        WHERE o.seller_id = ?
        GROUP BY o.id, o.user_id, o.seller_id, o.total_amount, o.status, o.created_at, customer_name
        ORDER BY o.created_at DESC
    ");
    $stmt->bind_param('i', $sellerId);
    $stmt->execute();
    $result = $stmt->get_result();
    $orders = $result->fetch_all(MYSQLI_ASSOC);
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
        $productIds = array_map(fn ($item) => (int) $item['id'], $items);
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $types = str_repeat('i', count($productIds));
        $stmt = $conn->prepare("
            SELECT id, seller_id, stock, price, is_active, name
            FROM products
            WHERE id IN ($placeholders)
            FOR UPDATE
        ");
        $stmt->bind_param($types, ...$productIds);
        $stmt->execute();
        $result = $stmt->get_result();
        $productMap = [];
        while ($row = $result->fetch_assoc()) {
            $productMap[(int) $row['id']] = $row;
        }
        $stmt->close();

        $grouped = [];
        foreach ($items as $item) {
            $product = $productMap[(int) $item['id']] ?? null;
            if (!$product || !(int) $product['is_active']) {
                throw new RuntimeException('One of the products in your cart is no longer available.');
            }
            if ((int) $product['stock'] < (int) $item['quantity']) {
                throw new RuntimeException('Insufficient stock for ' . $product['name'] . '.');
            }

            $sellerKey = $product['seller_id'] !== null ? (string) (int) $product['seller_id'] : 'marketplace';
            if (!isset($grouped[$sellerKey])) {
                $grouped[$sellerKey] = [
                    'seller_id' => $product['seller_id'] !== null ? (int) $product['seller_id'] : null,
                    'items' => [],
                    'total' => 0.0,
                ];
            }

            $lineTotal = (float) $product['price'] * (int) $item['quantity'];
            $grouped[$sellerKey]['items'][] = [
                'product_id' => (int) $product['id'],
                'quantity' => (int) $item['quantity'],
                'unit_price' => (float) $product['price'],
            ];
            $grouped[$sellerKey]['total'] += $lineTotal;
        }

        $orderStmt = $conn->prepare("INSERT INTO orders (user_id, seller_id, total_amount, status) VALUES (?, ?, ?, 'placed')");
        $marketplaceOrderStmt = $conn->prepare("INSERT INTO orders (user_id, seller_id, total_amount, status) VALUES (?, NULL, ?, 'placed')");
        $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?, ?, ?, ?)");
        $stockStmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ?");

        $createdOrderIds = [];
        foreach ($grouped as $group) {
            $sellerId = $group['seller_id'];
            $total = $group['total'];
            if ($sellerId === null) {
                $marketplaceOrderStmt->bind_param('id', $userId, $total);
                $marketplaceOrderStmt->execute();
            } else {
                $orderStmt->bind_param('iid', $userId, $sellerId, $total);
                $orderStmt->execute();
            }
            $orderId = (int) $conn->insert_id;
            $createdOrderIds[] = $orderId;

            foreach ($group['items'] as $item) {
                $itemStmt->bind_param('iiid', $orderId, $item['product_id'], $item['quantity'], $item['unit_price']);
                $itemStmt->execute();
                $stockStmt->bind_param('ii', $item['quantity'], $item['product_id']);
                $stockStmt->execute();
            }
        }

        $orderStmt->close();
        $marketplaceOrderStmt->close();
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

function app_upsert_product(array $user, array $data, ?array $file = null): array
{
    if (!app_is_seller($user) && !app_is_admin($user)) {
        return ['success' => false, 'message' => 'Unauthorized product action.'];
    }

    $productId = (int) ($data['product_id'] ?? 0);
    $name = trim($data['name'] ?? '');
    $brand = trim($data['brand'] ?? '');
    $category = trim($data['category'] ?? '');
    $description = trim($data['description'] ?? '');
    $price = (float) ($data['price'] ?? 0);
    $stock = max(0, (int) ($data['stock'] ?? 0));
    $iconClass = trim($data['icon_class'] ?? 'fa-solid fa-box-open');
    $isActive = !empty($data['is_active']) ? 1 : 0;

    if ($name === '' || $brand === '' || $category === '' || $description === '') {
        return ['success' => false, 'message' => 'Name, brand, category, and description are required.'];
    }
    if ($price <= 0) {
        return ['success' => false, 'message' => 'Price must be greater than zero.'];
    }

    $conn = app_db();
    $sellerId = app_is_admin($user) ? (($data['seller_id'] ?? '') !== '' ? (int) $data['seller_id'] : null) : (int) $user['id'];

    if ($productId > 0) {
        if (app_is_seller($user) && !app_seller_owns_product($user, $productId)) {
            return ['success' => false, 'message' => 'You can only edit your own products.'];
        }
        $stmt = $conn->prepare("
            UPDATE products
            SET name = ?, brand = ?, category = ?, description = ?, price = ?, stock = ?, icon_class = ?, is_active = ?, seller_id = ?
            WHERE id = ?
        ");
        $stmt->bind_param('ssssdisiii', $name, $brand, $category, $description, $price, $stock, $iconClass, $isActive, $sellerId, $productId);
        $stmt->execute();
        $stmt->close();
        return ['success' => true, 'message' => 'Product updated successfully.'];
    }

    $stmt = $conn->prepare("
        INSERT INTO products (seller_id, name, brand, category, description, price, stock, icon_class, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param('issssdisi', $sellerId, $name, $brand, $category, $description, $price, $stock, $iconClass, $isActive);
    $stmt->execute();
    $stmt->close();

    return ['success' => true, 'message' => 'Product created successfully.'];
}
