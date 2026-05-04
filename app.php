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
            phone        VARCHAR(20)       DEFAULT NULL,
            shipping_address TEXT          DEFAULT NULL,
            tax          DECIMAL(10,2)     DEFAULT 0.00,
            shipping_cost DECIMAL(10,2)    DEFAULT 0.00,
            payment_method VARCHAR(50)     DEFAULT 'cod',
            created_at   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ord_userId       (userId),
            INDEX idx_ord_status       (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
    ");

    // Add missing columns if they don't exist
    $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS phone VARCHAR(20) DEFAULT NULL");
    $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_address TEXT DEFAULT NULL");
    $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS tax DECIMAL(10,2) DEFAULT 0.00");
    $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_cost DECIMAL(10,2) DEFAULT 0.00");
    $conn->query("ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50) DEFAULT 'cod'");
    
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

    // Seed sample users up to 50 total accounts for dashboard/testing data.
    $userCountResult = $conn->query("SELECT COUNT(*) AS total FROM users");
    $userCountRow    = $userCountResult ? $userCountResult->fetch_assoc() : ['total' => 0];
    $currentUserCount = (int) ($userCountRow['total'] ?? 0);

    if ($currentUserCount < 50) {
        $firstNames = [
            'Alden', 'Bianca', 'Carlo', 'Daphne', 'Enzo', 'Faye', 'Gian', 'Hanna', 'Ivan', 'Jessa',
            'Kian', 'Lara', 'Marco', 'Nina', 'Owen', 'Paula', 'Quinn', 'Rhea', 'Sandro', 'Talia',
            'Uriel', 'Vera', 'Warren', 'Xandra', 'Yuri', 'Zia', 'Mika', 'Rafael', 'Sofia', 'Tristan',
            'Alexa', 'Bryce', 'Clara', 'Derek', 'Elise', 'Franco', 'Gwen', 'Harvey', 'Iris', 'Jonas',
            'Kara', 'Luis', 'Mara', 'Nico', 'Olivia', 'Pablo', 'Queen', 'Ramon', 'Selene', 'Theo',
        ];
        $lastNames = [
            'Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia', 'Mendoza', 'Torres', 'Flores', 'Ramos', 'Aquino',
            'Castillo', 'Rivera', 'Dela Cruz', 'Villanueva', 'Navarro', 'Morales', 'Domingo', 'Valdez', 'Gonzales', 'Mercado',
            'Salazar', 'Gutierrez', 'Pascual', 'Aguilar', 'Fernandez', 'Lim', 'Tan', 'Co', 'Sy', 'Chua',
            'Luna', 'Padilla', 'Rosario', 'Marquez', 'Velasco', 'Soriano', 'Ocampo', 'Panganiban', 'Sarmiento', 'Delos Santos',
            'Cabrera', 'Montemayor', 'Macapagal', 'Abad', 'Bernardo', 'Manalo', 'Escobar', 'Tuazon', 'Lazaro', 'Jacinto',
        ];
        $roles = [
            'customer', 'customer', 'customer', 'seller', 'customer',
            'customer', 'seller', 'customer', 'customer', 'customer',
        ];
        $seedPasswordHash = password_hash('Protech@123', PASSWORD_BCRYPT);

        $checkUserStmt = $conn->prepare("SELECT userId FROM users WHERE email = ? OR username = ? LIMIT 1");
        $insertUserStmt = $conn->prepare("
            INSERT INTO users
                (first_name, last_name, username, email, password_hash, role, seller_status, avatar_path, is_verified, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NULL, 1, NOW())
        ");
        $insertSellerAppStmt = $conn->prepare("
            INSERT INTO seller_applications (userId, store_name, reason, status, reviewed_at, created_at)
            VALUES (?, ?, 'Seed seller account for Protech Store testing.', 'approved', NOW(), NOW())
        ");

        for ($i = 1; $currentUserCount < 50 && $i <= 100; $i++) {
            $firstName = $firstNames[($i - 1) % count($firstNames)];
            $lastName  = $lastNames[(($i * 7) - 1) % count($lastNames)];
            $username  = sprintf('protech_user_%02d', $i);
            $email     = sprintf('protech.user%02d@example.test', $i);
            $role      = $roles[($i - 1) % count($roles)];
            $sellerStatus = $role === 'seller' ? 'approved' : 'not_applicable';

            $checkUserStmt->bind_param('ss', $email, $username);
            $checkUserStmt->execute();
            if ($checkUserStmt->get_result()->fetch_assoc()) {
                continue;
            }

            $insertUserStmt->bind_param('sssssss', $firstName, $lastName, $username, $email, $seedPasswordHash, $role, $sellerStatus);
            $insertUserStmt->execute();
            $newSeedUserId = (int) $conn->insert_id;

            if ($role === 'seller') {
                $storeName = $firstName . ' ' . $lastName . ' Tech Store';
                $insertSellerAppStmt->bind_param('is', $newSeedUserId, $storeName);
                $insertSellerAppStmt->execute();
            }

            $currentUserCount++;
        }

        $checkUserStmt->close();
        $insertUserStmt->close();
        $insertSellerAppStmt->close();
    }

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

    // ── Get superadmin user ID for seed products ────────────────────────────────
    $superadminId = null;
    $saStmt = $conn->prepare("SELECT userId FROM users WHERE email = ? LIMIT 1");
    $saStmt->bind_param('s', $superadminEmail);
    $saStmt->execute();
    $saResult = $saStmt->get_result()->fetch_assoc();
    if ($saResult) {
        $superadminId = (int) $saResult['userId'];
    }
    $saStmt->close();

    if ($superadminId !== null) {
        $conn->query("UPDATE products SET sellerUserId = {$superadminId} WHERE sellerUserId IS NULL");
    }

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
        ['ThinkPad Edge 15',         'Lenovo',   'Laptops',     'Durable 15-inch business laptop with spill-resistant keyboard.',            1049.00, 19, 'fa-solid fa-laptop'],
        ['SwiftCore 14 Plus',        'Acer',     'Laptops',     'Portable productivity laptop with 16GB RAM and fast NVMe storage.',          829.00, 33, 'fa-solid fa-laptop'],
        ['VivoBook Creator 15',      'ASUS',     'Laptops',     'Creator-friendly laptop with OLED display and dedicated graphics.',         1299.00, 14, 'fa-solid fa-laptop'],
        ['EliteDesk Flex 800',       'HP',       'Desktops',    'Expandable office desktop with quiet cooling and tool-less access.',         929.00, 21, 'fa-solid fa-desktop'],
        ['OptiCore Micro 7010',      'Dell',     'Desktops',    'Ultra-compact desktop for reception desks and small workspaces.',            679.00, 36, 'fa-solid fa-computer'],
        ['ROG Vector Tower',         'ASUS',     'Desktops',    'Gaming desktop with liquid cooling and high-refresh graphics support.',     2199.00, 10, 'fa-solid fa-desktop'],
        ['Prestige Render Mini',     'MSI',      'Desktops',    'Compact creator workstation for editing and rendering.',                    1749.00,  8, 'fa-solid fa-server'],
        ['Latitude Go 13',           'Dell',     'Laptops',     'Lightweight 13-inch laptop with fingerprint sign-in and USB-C charging.',     999.00, 18, 'fa-solid fa-laptop'],
        ['Spectra Work 14',          'HP',       'Laptops',     'Business ultrabook with privacy camera shutter and bright display.',         1199.00, 16, 'fa-solid fa-laptop'],
        ['NitroPulse 17',            'Acer',     'Laptops',     '17-inch gaming laptop with performance cooling and RGB keyboard.',           1399.00, 12, 'fa-solid fa-laptop'],
        ['MX Keys Pro Combo',        'Logitech', 'Peripherals', 'Premium wireless keyboard and mouse combo for multi-device workflows.',        179.00, 42, 'fa-solid fa-keyboard'],
        ['DeathAdder Studio Mouse',  'Razer',    'Peripherals', 'Lightweight wired mouse with precise tracking for work and play.',             69.00, 64, 'fa-solid fa-computer-mouse'],
        ['UltraSharp 32 Dock',       'Dell',     'Peripherals', '32-inch monitor with USB-C hub, slim bezels, and accurate color.',            749.00, 15, 'fa-solid fa-display'],
        ['BrioStream Webcam',        'Logitech', 'Peripherals', 'Full HD webcam with autofocus for video calls and streaming.',                99.00, 51, 'fa-solid fa-camera'],
        ['Kraken Lite Headset',      'Razer',    'Peripherals', 'Comfortable headset with clear mic for gaming and meetings.',                 89.00, 47, 'fa-solid fa-headset'],
        ['PowerPort GaN 100W',       'Anker',    'Peripherals', 'Compact multi-port USB-C charger for laptops, tablets, and phones.',          59.00, 73, 'fa-solid fa-plug'],
        ['CableFlow USB-C Kit',      'Anker',    'Peripherals', 'Braided USB-C cable bundle with adapters for daily device setups.',            39.00, 88, 'fa-solid fa-plug'],
        ['Archer Pro AXE5400',       'TP-Link',  'Networking',  'Wi-Fi 6E router for faster home and small office wireless coverage.',         229.00, 24, 'fa-solid fa-wifi'],
        ['JetStream 16-Port PoE',    'TP-Link',  'Networking',  'Smart PoE switch for cameras, access points, and office devices.',           189.00, 20, 'fa-solid fa-ethernet'],
        ['Catalyst Lite 8-Port',     'Cisco',    'Networking',  'Compact managed switch with VLAN features for branch offices.',              159.00, 17, 'fa-solid fa-network-wired'],
        ['UniFi Mesh Beacon',        'Ubiquiti', 'Networking',  'Mesh access point for extending stable wireless coverage.',                  139.00, 29, 'fa-solid fa-tower-broadcast'],
        ['EdgeRouter Secure X',      'Ubiquiti', 'Networking',  'Security router with VPN support and advanced traffic controls.',            299.00, 11, 'fa-solid fa-shield-halved'],
        ['ProDesk POS Terminal',     'HP',       'Desktops',    'Retail-ready point-of-sale desktop with compact footprint.',                 849.00, 13, 'fa-solid fa-cash-register'],
        ['IdeaCentre Home 5',        'Lenovo',   'Desktops',    'Family desktop for browsing, office documents, and school projects.',         699.00, 27, 'fa-solid fa-desktop'],
        ['CreatorView 27 QHD',       'ASUS',     'Peripherals', '27-inch QHD monitor with ergonomic stand and vivid color output.',            329.00, 25, 'fa-solid fa-display'],
        ['ProClick Silent Mouse',    'Logitech', 'Peripherals', 'Quiet wireless mouse designed for shared workspaces.',                        49.00, 69, 'fa-solid fa-computer-mouse'],
        ['Huntsman Mini Board',      'Razer',    'Peripherals', 'Compact optical keyboard with fast actuation and RGB lighting.',             129.00, 34, 'fa-solid fa-keyboard'],
        ['OmniCharge Dock 12',       'Anker',    'Peripherals', 'Desk dock with HDMI, Ethernet, SD reader, and pass-through charging.',        149.00, 31, 'fa-solid fa-plug'],
        ['Business Mesh AX1800',     'Cisco',    'Networking',  'Office mesh kit with secure guest network and simple management.',           399.00,  9, 'fa-solid fa-wifi'],
        ['UniFi Camera Bridge',      'Ubiquiti', 'Networking',  'Network bridge for connecting security cameras and edge devices.',           119.00, 22, 'fa-solid fa-network-wired'],
    ];

    $checkStmt  = $conn->prepare("SELECT productId FROM products WHERE name = ? LIMIT 1");
    $getBrandId = $conn->prepare("SELECT brandId FROM brands WHERE name = ? LIMIT 1");
    $getCatId   = $conn->prepare("SELECT categoryId FROM categories WHERE name = ? LIMIT 1");
    $insertStmt = $conn->prepare("
        INSERT INTO products (sellerUserId, name, brandId, categoryId, description, price, stock, icon_class)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $protechProductCount = 0;
    if ($superadminId !== null) {
        $countStmt = $conn->prepare("SELECT COUNT(*) AS total FROM products WHERE sellerUserId = ?");
        $countStmt->bind_param('i', $superadminId);
        $countStmt->execute();
        $countRow = $countStmt->get_result()->fetch_assoc();
        $protechProductCount = (int) ($countRow['total'] ?? 0);
        $countStmt->close();
    }

    foreach ($seedProducts as [$name, $brand, $category, $description, $price, $stock, $iconClass]) {
        if ($superadminId !== null && $protechProductCount >= 50) {
            break;
        }

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

        $insertStmt->bind_param('isiisdis', $superadminId, $name, $brandId, $categoryId, $description, $price, $stock, $iconClass);
        $insertStmt->execute();
        if ($superadminId !== null) {
            $protechProductCount++;
        }
    }

    // Link existing seed products (NULL sellerUserId) to superadmin store
    if ($superadminId !== null) {
        $conn->query("UPDATE products SET sellerUserId = {$superadminId} WHERE sellerUserId IS NULL");
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
        
        if (empty($productIds)) {
            throw new RuntimeException('Your cart is empty.');
        }
        
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $types        = str_repeat('i', count($productIds));

        $stmt = $conn->prepare("
            SELECT productId, sellerUserId, stock, price, is_active, name
            FROM products
            WHERE productId IN ($placeholders)
            FOR UPDATE
        ");
        
        if (!$stmt) {
            throw new RuntimeException('Failed to prepare statement: ' . $conn->error);
        }
        
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

$orderStmt = $conn->prepare("INSERT INTO orders (userId, total_amount, status, phone, shipping_address, tax, shipping_cost, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");        
        if (!$orderStmt) {
            throw new RuntimeException('Failed to prepare order statement: ' . $conn->error);
        }
        
        // Default values for order metadata
        $phone = $_SESSION['checkout_phone'] ?? '';
        $shippingAddress = $_SESSION['checkout_address'] ?? '';
        $tax = (float)($_SESSION['checkout_tax'] ?? 0);
        $shippingCost = (float)($_SESSION['checkout_shipping_cost'] ?? 0);
        $paymentMethod = $_SESSION['checkout_payment_method'] ?? 'cod';
        $status = 'placed';
        
        // Clear session checkout data after use
        unset($_SESSION['checkout_phone'], $_SESSION['checkout_address'], $_SESSION['checkout_tax'], $_SESSION['checkout_shipping_cost'], $_SESSION['checkout_payment_method']);
        
        $itemStmt = $conn->prepare("INSERT INTO order_items (orderId, productId, sellerUserId, product_name, quantity, unit_price) VALUES (?, ?, ?, ?, ?, ?)");
        if (!$itemStmt) {
            throw new RuntimeException('Failed to prepare item statement: ' . $conn->error);
        }
        
        $stockStmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE productId = ?");
        if (!$stockStmt) {
            throw new RuntimeException('Failed to prepare stock statement: ' . $conn->error);
        }

        $createdOrderIds = [];
        foreach ($grouped as $group) {
            $sellerId = $group['sellerUserId'];
            $total    = (float) $group['total'];

            $orderStmt->bind_param('idsssdds', $userId, $total, $status, $phone, $shippingAddress, $tax, $shippingCost, $paymentMethod);
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

        // Store order IDs in session for checkout confirmation page
        $_SESSION['checkout_order_ids'] = $createdOrderIds;

        return ['success' => true, 'message' => 'Checkout complete.', 'order_ids' => $createdOrderIds];

    } catch (Throwable $e) {
        $conn->rollback();
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

function app_get_order_with_items(int $orderId): ?array
{
    $conn = app_db();
    $stmt = $conn->prepare("
        SELECT o.orderId, o.userId, o.total_amount, o.status, o.created_at,
               o.phone, o.shipping_address, o.payment_method, o.tax, o.shipping_cost,
               u.first_name, u.last_name, u.email
        FROM orders o
        JOIN users u ON u.userId = o.userId
        WHERE o.orderId = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$order) {
        return null;
    }

    $itemStmt = $conn->prepare("
        SELECT product_name, quantity, unit_price
        FROM order_items
        WHERE orderId = ?
    ");
    $itemStmt->bind_param('i', $orderId);
    $itemStmt->execute();
    $items = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $itemStmt->close();

    $order['items'] = $items;
    return $order;
}

function app_send_receipt_email(int $orderId): bool
{
    require_once __DIR__ . '/vendor/autoload.php';
    require_once __DIR__ . '/includes/dashboard/mailer.php';

    $order = app_get_order_with_items($orderId);
    if (!$order) {
        return false;
    }

    $customerName = $order['first_name'] . ' ' . $order['last_name'];
    $customerEmail = $order['email'];
    $orderDate = date('F j, Y', strtotime($order['created_at']));
    $orderTime = date('g:i A', strtotime($order['created_at']));
    $orderDateTime = $order['created_at'];

    $itemsHtml = '';
    $subtotal = 0;
    foreach ($order['items'] as $item) {
        $lineTotal = (float) $item['unit_price'] * (int) $item['quantity'];
        $subtotal += $lineTotal;
        $itemsHtml .= "
            <tr>
                <td style='padding:12px 8px;border-bottom:1px solid #2a2a2a;'>{$item['product_name']}</td>
                <td style='padding:12px 8px;border-bottom:1px solid #2a2a2a;text-align:center;'>{$item['quantity']}</td>
                <td style='padding:12px 8px;border-bottom:1px solid #2a2a2a;text-align:right;'>₱" . number_format($item['unit_price'], 2) . "</td>
                <td style='padding:12px 8px;border-bottom:1px solid #2a2a2a;text-align:right;'>₱" . number_format($lineTotal, 2) . "</td>
            </tr>
        ";
    }

    $totalAmount = number_format((float) $order['total_amount'], 2);

    $receiptHtml = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Receipt - Order #{$orderId}</title>
    </head>
    <body style='margin:0;padding:0;background:#141414;font-family:sans-serif;'>
        <div style='max-width:520px;margin:auto;background:#141414;border-radius:12px;overflow:hidden;'>
            <div style='background:#ff7315;padding:24px;text-align:center;'>
                <h1 style='color:white;margin:0;font-size:1.5rem;'>ProTech Receipt</h1>
                <p style='color:white;margin:8px 0 0;font-size:0.9rem;'>Order #{$orderId}</p>
            </div>
            <div style='padding:24px;color:#e0e0e0;'>
                <table style='width:100%;border-collapse:collapse;margin-bottom:20px;'>
                    <tr>
                        <td style='padding:8px 0;color:#888;font-size:0.85rem;'>Date</td>
                        <td style='padding:8px 0;text-align:right;'>{$orderDate}</td>
                    </tr>
                    <tr>
                        <td style='padding:8px 0;color:#888;font-size:0.85rem;'>Time</td>
                        <td style='padding:8px 0;text-align:right;'>{$orderTime}</td>
                    </tr>
                    <tr>
                        <td style='padding:8px 0;color:#888;font-size:0.85rem;'>Status</td>
                        <td style='padding:8px 0;text-align:right;text-transform:capitalize;'>{$order['status']}</td>
                    </tr>
                </table>

                <h3 style='color:#ff7315;font-size:1rem;margin:24px 0 12px;border-bottom:1px solid #2a2a2a;padding-bottom:8px;'>Items</h3>
                <table style='width:100%;border-collapse:collapse;'>
                    <tr style='color:#888;font-size:0.8rem;'>
                        <th style='padding:8px;text-align:left;'>Product</th>
                        <th style='padding:8px;text-align:center;'>Qty</th>
                        <th style='padding:8px;text-align:right;'>Price</th>
                        <th style='padding:8px;text-align:right;'>Total</th>
                    </tr>
                    {$itemsHtml}
                </table>

                <div style='margin-top:20px;text-align:right;'>
                    <span style='color:#888;'>Total: </span>
                    <span style='color:#ff7315;font-size:1.25rem;font-weight:bold;'>₱{$totalAmount}</span>
                </div>

                <p style='margin-top:32px;color:#888;font-size:0.8rem;text-align:center;'>
                    Thank you for shopping with ProTech!
                </p>
            </div>
        </div>
    </body>
    </html>
    ";

    $dompdf = new Dompdf\Dompdf();
    $dompdf->loadHtml($receiptHtml);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $pdfContent = $dompdf->output();

    try {
        $mail = dashboard_mailer();
        $mail->addAddress($customerEmail, $customerName);
        $mail->Subject = 'Your ProTech Receipt - Order #' . $orderId;
        $mail->Body = "
            <div style='font-family:sans-serif;max-width:520px;margin:auto;background:#141414;border-radius:12px;overflow:hidden;'>
                <div style='background:#ff7315;padding:24px;text-align:center;'>
                    <h1 style='color:white;margin:0;font-size:1.5rem;'>Order Confirmed!</h1>
                </div>
                <div style='padding:24px;color:#e0e0e0;'>
                    <p>Hi <strong>{$customerName}</strong>,</p>
                    <p>Thank you for your order! Your receipt is attached to this email.</p>
                    <p style='color:#888;font-size:0.85rem;'>Order Date: {$orderDate}<br>Order Time: {$orderTime}</p>
                    <p style='margin-top:24px;color:#888;font-size:0.8rem;'>
                        If you have any questions, feel free to contact us.
                    </p>
                </div>
            </div>
        ";
        $mail->AltBody = "Your ProTech order receipt - Order #{$orderId} - Total: ₱{$totalAmount}";

        $tempFile = sys_get_temp_dir() . '/receipt_' . $orderId . '.pdf';
        file_put_contents($tempFile, $pdfContent);
        $mail->addAttachment($tempFile, 'receipt_' . $orderId . '.pdf', 'application/pdf');
        $mail->send();

        if (file_exists($tempFile)) {
            unlink($tempFile);
        }

        return true;
    } catch (Exception $e) {
        error_log('Receipt email failed: ' . $e->getMessage());
        if (file_exists($tempFile)) {
            unlink($tempFile);
        }
        return false;
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
        // Superadmins and admins can edit any product, sellers can only edit their own
        if (app_is_seller($user) && !app_seller_owns_product($user, $productId) && !app_is_admin($user)) {
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

function app_no_html_redirect(string $target = 'index.php'): void
{
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta http-equiv="refresh" content="0;url=' . $target . '"><script>' . htmlspecialchars('location.href=' . json_encode($target), ENT_QUOTES) . '</script></head><body></body></html>';
    exit;
}
// =============================================================================
// CSRF & Session Hardening
// =============================================================================

/**
 * Generate or retrieve CSRF token for the current session.
 * Token expires after 1 hour.
 *
 * @return string The CSRF token
 */
function app_csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) ||
        empty($_SESSION['csrf_token_expires']) ||
        $_SESSION['csrf_token_expires'] < time()) {
        $_SESSION['csrf_token']        = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_expires'] = time() + 3600;
    }
    return $_SESSION['csrf_token'];
}

/**
 * Output hidden CSRF input field.
 */
function app_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(app_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify a CSRF token (POST or X-CSRF-Token header).
 */
function app_verify_csrf(?string $token = null): bool
{
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? null;
    }
    if ($token === null) {
        $headers = getallheaders();
        $token  = $headers['X-CSRF-Token'] ?? $headers['X-Csrf-Token'] ?? null;
    }
    $sessionToken = $_SESSION['csrf_token'] ?? null;
    return $sessionToken !== null && hash_equals($sessionToken, $token);
}

/**
 * Require valid CSRF token on POST or exit with JSON error.
 */
function app_require_csrf(): void
{
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !app_verify_csrf()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => 'Invalid or missing CSRF token. Please refresh the page and try again.'
        ]);
        exit;
    }
}

// =============================================================================
// Rate Limiting & Login Security
// =============================================================================

/**
 * Check if login allowed from IP.
 * @return array{allowed:bool, remaining:int, blocked_until:string|null}
 */
function app_check_login_allowed(mysqli $conn, string $ip): array
{
    $window   = date('Y-m-d H:i:s', time() - 300); // 5 minutes
    $stmt     = $conn->prepare('SELECT attempted_at FROM login_attempts WHERE ip = ? AND attempted_at > ? ORDER BY attempted_at ASC');
    $stmt->bind_param('ss', $ip, $window);
    $stmt->execute();
    $result   = $stmt->get_result();
    $attempts = [];
    while ($row = $result->fetch_assoc()) {
        $attempts[] = $row['attempted_at'];
    }
    $stmt->close();

    $count   = count($attempts);
    $blocked = $count >= 3;
    $blockedTs = $blocked ? strtotime($attempts[0]) + 300 : null;
    $remaining = $blocked ? max(0, ceil(($blockedTs - time()) / 60)) : (3 - $count);

    return [
        'allowed'      => !$blocked,
        'attempts'     => $count,
        'remaining'    => $remaining,
        'blocked_until'=> $blockedTs ? date('Y-m-d H:i:s', $blockedTs) : null,
    ];
}

function app_record_login_attempt(mysqli $conn, string $ip, string $identifier): void
{
    $stmt = $conn->prepare('INSERT INTO login_attempts (ip, identifier) VALUES (?, ?)');
    $stmt->bind_param('ss', $ip, $identifier);
    $stmt->execute();
    $stmt->close();
}

function app_clear_login_attempts(mysqli $conn, string $ip): void
{
    $stmt = $conn->prepare('DELETE FROM login_attempts WHERE ip = ?');
    $stmt->bind_param('s', $ip);
    $stmt->execute();
    $stmt->close();
}

/**
 * Send failed login notification email.
 */
function app_send_failed_login_notification(string $email, string $name, string $ip, string $time): bool
{
    require_once __DIR__ . '/vendor/autoload.php';
    require_once __DIR__ . '/includes/dashboard/mailer.php';

    try {
        $mail = dashboard_mailer();
        $mail->addAddress($email, $name);
$mail->Subject = 'Security Alert - Failed Login Attempt';
        $mail->Body = <<<HTML
            <div style='font-family:sans-serif;max-width:520px;margin:auto;background:#141414;border-radius:12px;overflow:hidden;'>
                <div style='background:#ff7315;padding:24px;text-align:center;'>
                    <h1 style='color:white;margin:0;font-size:1.5rem;'>Security Alert</h1>
                </div>
                <div style='padding:24px;color:#e0e0e0;'>
                    <p>Hi <strong>{$name}</strong>,</p>
                    <p>We detected a failed login attempt on your ProTech account.</p>
                    <table style='width:100%;border-collapse:collapse;margin:16px 0;'>
                        <tr><td style='padding:8px;color:#888;'>Time</td><td style='padding:8px;text-align:right;'>{$time}</td></tr>
                        <tr><td style='padding:8px;color:#888;'>IP Address</td><td style='padding:8px;text-align:right;'>{$ip}</td></tr>
                    </table>
                    <p style='color:#ff7315;'>If this wasn't you, please reset your password immediately.</p>
                    <p style='margin-top:24px;color:#888;font-size:0.85rem;'>
                        If you recognize this activity, you can ignore this message.
                    </p>
                </div>
            </div>
HTML;
        $mail->AltBody = "Security alert: Failed login attempt from IP {$ip} at {$time}. If not you, reset your password.";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('Failed login notification email failed: ' . $e->getMessage());
        return false;
    }
}

// =============================================================================
// Service Layer
// =============================================================================
require_once __DIR__ . '/services/CartService.php';
require_once __DIR__ . '/services/OrderService.php';
require_once __DIR__ . '/services/UserService.php';
// === END_INITIAL_FUNCTIONS ===
