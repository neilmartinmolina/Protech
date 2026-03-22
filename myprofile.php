<?php
require_once __DIR__ . '/app.php';

$user = app_require_login();
$conn = app_db();
$flash = null;
$errors = [];
$openEditForm = false;
$avatarUrl = app_avatar_url($user);
$customerOrders = app_get_orders_for_customer((int) $user['userId']);

// Active section from query string; default to 'general'
$activeSection = $_GET['section'] ?? 'general';
$allowedSections = ['general', 'settings', 'security'];
if (!in_array($activeSection, $allowedSections)) $activeSection = 'general';

// Fetch latest seller application for this user (if any)
$sellerApplication = null;
if (($user['role'] ?? '') === 'customer') {
    $stmt = $conn->prepare('SELECT * FROM seller_applications WHERE userId = ? ORDER BY created_at DESC LIMIT 1');
    $stmt->bind_param('i', $user['userId']);
    $stmt->execute();
    $sellerApplication = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// Fetch saved addresses and payment methods
$savedAddresses = [];
$stmt = $conn->prepare('SELECT * FROM user_addresses WHERE userId = ? ORDER BY is_default DESC, created_at DESC');
$stmt->bind_param('i', $user['userId']);
$stmt->execute();
$savedAddresses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$paymentMethods = [];
$stmt = $conn->prepare('SELECT * FROM user_payment_methods WHERE userId = ? ORDER BY is_default DESC, created_at DESC');
$stmt->bind_param('i', $user['userId']);
$stmt->execute();
$paymentMethods = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch completed orders eligible for rating (placed/processing/delivered)
$rateableOrders = [];
foreach ($customerOrders as $order) {
    if (in_array($order['status'], ['placed', 'processing', 'delivered'])) {
        $rateableOrders[] = $order;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Update profile ──────────────────────────────────────
    if ($action === 'update_profile') {
        $activeSection = 'general';
        $openEditForm  = true;
        $firstName  = trim($_POST['first_name']  ?? '');
        $lastName   = trim($_POST['last_name']   ?? '');
        $username   = trim($_POST['username']    ?? '');
        $storeName  = trim($_POST['store_name']  ?? '');
        $avatarFile = $_FILES['avatar'] ?? null;

        if ($firstName === '') $errors[] = 'First name is required.';
        if ($lastName  === '') $errors[] = 'Last name is required.';
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) $errors[] = 'Username must be 3–50 characters (letters, numbers, underscores only).';
        if (($user['role'] ?? '') === 'seller' && $storeName === '') $errors[] = 'Store name is required for sellers.';

        $avatarResult = ['success' => true, 'path' => $user['avatar_path'] ?? null];

        if (!$errors) {
            $stmt = $conn->prepare('SELECT userId FROM users WHERE username = ? AND userId <> ? LIMIT 1');
            $stmt->bind_param('si', $username, $user['userId']);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($exists) $errors[] = 'That username is already taken.';
        }

        if (!$errors && $avatarFile && ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $avatarResult = app_store_avatar($avatarFile, $user['avatar_path'] ?? null);
            if (!$avatarResult['success']) $errors[] = $avatarResult['message'];
        }

        if (!$errors) {
            $avatarPath = $avatarResult['path'] ?? ($user['avatar_path'] ?? null);
            $stmt = $conn->prepare('UPDATE users SET first_name=?, last_name=?, username=?, store_name=?, avatar_path=? WHERE userId=?');
            $stmt->bind_param('sssssi', $firstName, $lastName, $username, $storeName, $avatarPath, $user['userId']);
            $stmt->execute();
            $stmt->close();

            $user      = app_refresh_session_user((int) $user['userId']) ?? $user;
            $avatarUrl = app_avatar_url($user);
            $flash     = ['type' => 'success', 'message' => 'Profile updated successfully.'];
            $openEditForm = false;
        }
    }

    // ── Change email (Security tab) ─────────────────────────
    if ($action === 'change_email') {
        $activeSection   = 'security';
        $newEmail        = trim($_POST['new_email']        ?? '');
        $confirmPassword = $_POST['confirm_password_email'] ?? '';

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
        if ($confirmPassword === '') $errors[] = 'Please confirm your password to change email.';

        if (!$errors) {
            $stmt = $conn->prepare('SELECT userId FROM users WHERE email = ? AND userId <> ? LIMIT 1');
            $stmt->bind_param('si', $newEmail, $user['userId']);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) $errors[] = 'That email is already in use by another account.';
            $stmt->close();
        }

        if (!$errors) {
            $stmt = $conn->prepare('SELECT password_hash FROM users WHERE userId = ? LIMIT 1');
            $stmt->bind_param('i', $user['userId']);
            $stmt->execute();
            $account = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$account || !password_verify($confirmPassword, $account['password_hash'])) {
                $errors[] = 'Password is incorrect.';
            }
        }

        if (!$errors) {
            $stmt = $conn->prepare('UPDATE users SET email = ? WHERE userId = ?');
            $stmt->bind_param('si', $newEmail, $user['userId']);
            $stmt->execute();
            $stmt->close();
            $user  = app_refresh_session_user((int) $user['userId']) ?? $user;
            $flash = ['type' => 'success', 'message' => 'Email updated successfully.'];
        }
    }

    // ── Seller application ──────────────────────────────────
    if ($action === 'apply_seller') {
        $activeSection  = 'settings';
        $applyStoreName = trim($_POST['store_name'] ?? '');
        $applyReason    = trim($_POST['reason']     ?? '');

        if ($applyStoreName === '') $errors[] = 'Store name is required.';
        if (strlen($applyStoreName) > 150) $errors[] = 'Store name must be 150 characters or less.';

        if (!$errors) {
            $stmt = $conn->prepare("SELECT status FROM seller_applications WHERE userId = ? AND status = 'pending' LIMIT 1");
            $stmt->bind_param('i', $user['userId']);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) $errors[] = 'You already have a pending application.';
            $stmt->close();
        }

        if (!$errors && ($user['role'] ?? '') !== 'customer') {
            $errors[] = 'Only customers can apply to become a seller.';
        }

        if (!$errors) {
            $stmt = $conn->prepare("INSERT INTO seller_applications (userId, store_name, reason, status) VALUES (?, ?, ?, 'pending')");
            $stmt->bind_param('iss', $user['userId'], $applyStoreName, $applyReason);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE users SET seller_status = 'pending' WHERE userId = ?");
            $stmt->bind_param('i', $user['userId']);
            $stmt->execute();
            $stmt->close();

            $user = app_refresh_session_user((int) $user['userId']) ?? $user;

            $stmt = $conn->prepare('SELECT * FROM seller_applications WHERE userId = ? ORDER BY created_at DESC LIMIT 1');
            $stmt->bind_param('i', $user['userId']);
            $stmt->execute();
            $sellerApplication = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $flash = ['type' => 'success', 'message' => 'Your seller application has been submitted. We\'ll review it shortly.'];
        }
    }

    // ── Change password ─────────────────────────────────────
    if ($action === 'change_password') {
        $activeSection   = 'security';
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password']     ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '')           $errors[] = 'Current password is required.';
        if (strlen($newPassword) < 6)          $errors[] = 'New password must be at least 6 characters.';
        if ($newPassword !== $confirmPassword) $errors[] = 'New password and confirmation do not match.';

        $stmt = $conn->prepare('SELECT password_hash FROM users WHERE userId = ? LIMIT 1');
        $stmt->bind_param('i', $user['userId']);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$account || !password_verify($currentPassword, $account['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        }

        if (!$errors) {
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $conn->prepare('UPDATE users SET password_hash = ?, temp_password = NULL WHERE userId = ?');
            $stmt->bind_param('si', $newHash, $user['userId']);
            $stmt->execute();
            $stmt->close();
            $flash = ['type' => 'success', 'message' => 'Password changed successfully.'];
        }
    }

    if ($errors) {
        $flash = ['type' => 'danger', 'message' => implode(' ', $errors)];
    }
}

$pageTitle = 'My Profile — ProTech';
$pageCss   = ['my_profile.css'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
</head>
<body>
<?php include __DIR__ . '/includes/navbar.php'; ?>

<section class="profile-shell">
    <div class="container">
        <div class="profile-layout">

            <!-- ═══ SIDEBAR ════════════════════════════════════ -->
            <aside class="profile-sidebar">
                <div class="sidebar-user">
                    <div class="sidebar-avatar">
                        <?php if ($avatarUrl): ?>
                            <img src="<?= app_sanitize($avatarUrl) ?>" alt="Avatar">
                        <?php else: ?>
                            <i class="fa-solid fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div class="sidebar-name"><?= app_sanitize($user['firstName'] . ' ' . $user['lastName']) ?></div>
                    <div class="sidebar-role"><?= app_sanitize($user['role']) ?> account</div>
                </div>
                <ul class="sidebar-nav">
                    <li class="sidebar-nav-section">MyAccount</li>
                    <li><a href="?section=general"  class="<?= $activeSection === 'general'  ? 'active' : '' ?>"><i class="fa-solid fa-user"></i> General</a></li>
                    <li><a href="?section=settings" class="<?= $activeSection === 'settings' ? 'active' : '' ?>"><i class="fa-solid fa-gear"></i> Settings</a></li>
                    <li><a href="?section=security" class="<?= $activeSection === 'security' ? 'active' : '' ?>"><i class="fa-solid fa-lock"></i> Security</a></li>
                </ul>
            </aside>

            <!-- ═══ MAIN CONTENT ═══════════════════════════════ -->
            <main>
                <?php if ($flash): ?>
                    <div class="flash <?= app_sanitize($flash['type']) ?>"><?= app_sanitize($flash['message']) ?></div>
                <?php endif; ?>

                <!-- ══════════════════════════════════════════════
                     GENERAL TAB
                ══════════════════════════════════════════════ -->
                <?php if ($activeSection === 'general'): ?>

                    <!-- Profile card -->
                    <div class="profile-card">
                        <div class="card-header-row">
                            <div>
                                <div class="section-label mb-1"><i class="fa-solid fa-id-card"></i> My Profile</div>
                                <h1 class="section-title mb-0" style="font-size:1.6rem;"><?= app_sanitize($user['firstName'] . ' ' . $user['lastName']) ?></h1>
                            </div>
                            <button class="ghost-btn" type="button" id="toggleEditBtn">Edit Details</button>
                        </div>
                        <div class="detail-grid">
                            <div class="detail-item"><small>First name</small><strong><?= app_sanitize($user['firstName']) ?></strong></div>
                            <div class="detail-item"><small>Last name</small><strong><?= app_sanitize($user['lastName']) ?></strong></div>
                            <div class="detail-item"><small>Username</small><strong><?= app_sanitize($user['username']) ?></strong></div>
                            <div class="detail-item"><small>Email</small><strong><?= app_sanitize($user['email']) ?></strong></div>
                            <div class="detail-item"><small>Role</small><strong><?= app_sanitize(ucfirst($user['role'])) ?></strong></div>
                            <?php if (($user['role'] ?? '') === 'seller'): ?>
                                <div class="detail-item"><small>Store</small><strong><?= app_sanitize($user['store_name'] ?: 'Not set') ?></strong></div>
                            <?php endif; ?>
                        </div>

                        <!-- Edit form — email removed, handled in Security -->
                        <div class="form-shell <?= $openEditForm ? 'open' : '' ?>" id="editFormShell">
                            <hr class="my-4" style="border-color:var(--border);">
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="update_profile">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label">Profile photo</label>
                                        <input class="form-control" type="file" name="avatar" accept="image/*">
                                        <small class="text-secondary">JPG, PNG, WEBP, or GIF — max 2 MB.</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">First name</label>
                                        <input class="form-control" type="text" name="first_name" value="<?= app_sanitize($user['firstName']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Last name</label>
                                        <input class="form-control" type="text" name="last_name" value="<?= app_sanitize($user['lastName']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Username</label>
                                        <input class="form-control" type="text" name="username" value="<?= app_sanitize($user['username']) ?>" required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Email</label>
                                        <input class="form-control" type="text" value="<?= app_sanitize($user['email']) ?>" disabled>
                                        <small class="text-secondary">To change your email, go to <a href="?section=security">Security</a>.</small>
                                    </div>
                                    <?php if (($user['role'] ?? '') === 'seller'): ?>
                                        <div class="col-12">
                                            <label class="form-label">Store name</label>
                                            <input class="form-control" type="text" name="store_name" value="<?= app_sanitize($user['store_name'] ?? '') ?>" required>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex gap-2 mt-4">
                                    <button class="action-btn" type="submit">Save Changes</button>
                                    <button class="ghost-btn" type="button" id="cancelEditBtn">Cancel</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- My Orders + Rate — side by side on larger screens -->
                    <div class="row g-4">

                        <!-- My Orders -->
                        <div class="col-lg-6">
                            <div class="profile-card h-100">
                                <div class="card-header-row">
                                    <div>
                                        <div class="section-label mb-1"><i class="fa-solid fa-receipt"></i> Orders</div>
                                        <h2 class="section-title mb-0" style="font-size:1.4rem;">My Orders</h2>
                                    </div>
                                </div>
                                <?php if (!$customerOrders): ?>
                                    <div class="empty-state">
                                        <i class="fa-solid fa-box-open"></i>
                                        You haven't placed any orders yet.
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex flex-column gap-3">
                                        <?php foreach ($customerOrders as $order): ?>
                                            <div class="detail-item d-flex justify-content-between align-items-start flex-wrap gap-2">
                                                <div>
                                                    <small>Order #<?= (int) $order['orderId'] ?> &bull; <?= app_sanitize($order['created_at']) ?></small>
                                                    <strong class="d-block mt-1">$<?= number_format((float) $order['total_amount'], 2) ?></strong>
                                                    <div class="text-secondary mt-1" style="font-size:.85rem;"><?= (int) $order['item_count'] ?> item(s)</div>
                                                </div>
                                                <span class="order-status"><?= app_sanitize(ucfirst($order['status'])) ?></span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Rate Products -->
                        <div class="col-lg-6">
                            <div class="profile-card h-100">
                                <div class="card-header-row">
                                    <div>
                                        <div class="section-label mb-1"><i class="fa-solid fa-star"></i> Reviews</div>
                                        <h2 class="section-title mb-0" style="font-size:1.4rem;">Rate Products</h2>
                                    </div>
                                </div>
                                <?php if (!$rateableOrders): ?>
                                    <div class="empty-state">
                                        <i class="fa-solid fa-star"></i>
                                        No orders to rate yet.
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex flex-column gap-3">
                                        <?php foreach ($rateableOrders as $order): ?>
                                            <div class="detail-item d-flex justify-content-between align-items-start flex-wrap gap-2">
                                                <div>
                                                    <small>Order #<?= (int) $order['orderId'] ?> &bull; <?= app_sanitize($order['created_at']) ?></small>
                                                    <strong class="d-block mt-1">$<?= number_format((float) $order['total_amount'], 2) ?></strong>
                                                    <div class="text-secondary mt-1" style="font-size:.85rem;"><?= (int) $order['item_count'] ?> item(s)</div>
                                                </div>
                                                <button
                                                    class="ghost-btn rate-order-btn"
                                                    type="button"
                                                    data-order-id="<?= (int) $order['orderId'] ?>"
                                                    style="font-size:.8rem; padding:.3rem .75rem;"
                                                >
                                                    Rate
                                                </button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div><!-- /row -->

                <!-- ══════════════════════════════════════════════
                     SETTINGS TAB
                ══════════════════════════════════════════════ -->
                <?php elseif ($activeSection === 'settings'): ?>

                    <!-- Saved Addresses -->
                    <div class="profile-card">
                        <div class="card-header-row">
                            <div>
                                <div class="section-label mb-1"><i class="fa-solid fa-location-dot"></i> Addresses</div>
                                <h2 class="section-title mb-0" style="font-size:1.4rem;">Saved Addresses</h2>
                            </div>
                            <button class="ghost-btn" type="button">+ Add Address</button>
                        </div>
                        <?php if (empty($savedAddresses)): ?>
                            <div class="empty-state">
                                <i class="fa-solid fa-map-pin"></i>
                                No saved addresses yet.
                            </div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($savedAddresses as $addr): ?>
                                    <div class="detail-item d-flex justify-content-between align-items-start flex-wrap gap-2">
                                        <div>
                                            <strong><?= app_sanitize($addr['label'] ?: 'Address') ?></strong>
                                            <?php if ($addr['is_default']): ?>
                                                <span class="stub-badge ms-1">Default</span>
                                            <?php endif; ?>
                                            <div class="text-secondary mt-1" style="font-size:.85rem;">
                                                <?= app_sanitize($addr['recipient_name']) ?> &bull; <?= app_sanitize($addr['phone']) ?>
                                            </div>
                                            <div class="text-secondary" style="font-size:.85rem;">
                                                <?= app_sanitize($addr['street']) ?>, <?= app_sanitize($addr['barangay']) ?>,
                                                <?= app_sanitize($addr['city']) ?>
                                                <?= $addr['province'] ? ', ' . app_sanitize($addr['province']) : '' ?>
                                                <?= $addr['zip']      ? ' ' . app_sanitize($addr['zip'])      : '' ?>
                                            </div>
                                        </div>
                                        <button class="ghost-btn" type="button" style="font-size:.8rem; padding:.3rem .75rem;">Edit</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Mode of Payment -->
                    <div class="profile-card">
                        <div class="card-header-row">
                            <div>
                                <div class="section-label mb-1"><i class="fa-solid fa-credit-card"></i> Payment</div>
                                <h2 class="section-title mb-0" style="font-size:1.4rem;">Mode of Payment</h2>
                            </div>
                            <button class="ghost-btn" type="button">+ Add Payment</button>
                        </div>
                        <?php if (empty($paymentMethods)): ?>
                            <div class="empty-state">
                                <i class="fa-solid fa-credit-card"></i>
                                No saved payment methods yet.
                            </div>
                        <?php else: ?>
                            <div class="d-flex flex-column gap-3">
                                <?php foreach ($paymentMethods as $method): ?>
                                    <div class="detail-item d-flex justify-content-between align-items-start flex-wrap gap-2">
                                        <div>
                                            <strong><?= app_sanitize($method['label'] ?: ucfirst($method['type'])) ?></strong>
                                            <?php if ($method['is_default']): ?>
                                                <span class="stub-badge ms-1">Default</span>
                                            <?php endif; ?>
                                            <div class="text-secondary mt-1" style="font-size:.85rem;">
                                                <?php if ($method['type'] === 'gcash'): ?>
                                                    GCash &bull; <?= app_sanitize($method['gcash_name'] ?? '') ?>
                                                    <?= $method['gcash_number'] ? ' &bull; ' . app_sanitize($method['gcash_number']) : '' ?>
                                                <?php else: ?>
                                                    Cash on Delivery
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <button class="ghost-btn" type="button" style="font-size:.8rem; padding:.3rem .75rem;">Edit</button>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Become a Seller — customers only, revealed by button -->
                    <?php if (($user['role'] ?? '') === 'customer'): ?>
                        <div class="profile-card">
                            <div class="card-header-row">
                                <div>
                                    <div class="section-label mb-1"><i class="fa-solid fa-store"></i> Seller</div>
                                    <h2 class="section-title mb-0" style="font-size:1.4rem;">Want to become one of our sellers?</h2>
                                </div>
                                <button class="ghost-btn" type="button" id="toggleSellerBtn">
                                    <i class="fa-solid fa-chevron-down" id="sellerChevron"></i> Show
                                </button>
                            </div>

                            <!-- Collapsed by default -->
                            <div id="sellerFormShell" style="display:none;">
                                <?php
                                $appStatus = $sellerApplication['status'] ?? null;
                                ?>

                                <?php if ($appStatus === 'pending'): ?>
                                    <div class="app-status-banner pending">
                                        <div class="banner-icon"><i class="fa-solid fa-clock"></i></div>
                                        <div>
                                            <div class="banner-title">Application under review</div>
                                            <div class="banner-sub">
                                                Your application for <strong><?= app_sanitize($sellerApplication['store_name']) ?></strong>
                                                was submitted on <?= app_sanitize(date('M d, Y', strtotime($sellerApplication['created_at']))) ?>.
                                                We'll notify you once it's been reviewed.
                                            </div>
                                        </div>
                                    </div>

                                <?php elseif ($appStatus === 'approved'): ?>
                                    <div class="app-status-banner approved">
                                        <div class="banner-icon"><i class="fa-solid fa-circle-check"></i></div>
                                        <div>
                                            <div class="banner-title">Application approved</div>
                                            <div class="banner-sub">Your seller account is active. If your role hasn't updated, please log out and back in.</div>
                                        </div>
                                    </div>

                                <?php elseif ($appStatus === 'rejected'): ?>
                                    <div class="app-status-banner rejected mb-4">
                                        <div class="banner-icon"><i class="fa-solid fa-circle-xmark"></i></div>
                                        <div>
                                            <div class="banner-title">Application not approved</div>
                                            <div class="banner-sub">
                                                <?php if (!empty($sellerApplication['rejection_reason'])): ?>
                                                    Reason: <?= app_sanitize($sellerApplication['rejection_reason']) ?>
                                                <?php else: ?>
                                                    Your previous application was not approved. You may submit a new one below.
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <form method="post">
                                        <input type="hidden" name="action" value="apply_seller">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label">Store name <span class="text-danger">*</span></label>
                                                <input class="form-control" type="text" name="store_name" maxlength="150" placeholder="e.g. TechHub PH" required>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Why do you want to sell on ProTech? <span class="text-secondary" style="font-weight:400;">(optional)</span></label>
                                                <textarea class="form-control" name="reason" placeholder="Tell us a bit about your store and what you plan to sell..."></textarea>
                                            </div>
                                        </div>
                                        <div class="mt-4">
                                            <button class="action-btn" type="submit">Re-submit Application</button>
                                        </div>
                                    </form>

                                <?php else: ?>
                                    <div class="seller-cta mb-4">
                                        <div class="seller-cta-icon"><i class="fa-solid fa-store"></i></div>
                                        <h3>Start selling on ProTech</h3>
                                        <p>Open your own store, list your products, and reach customers across the platform. Applications are reviewed by our admin team.</p>
                                    </div>
                                    <form method="post">
                                        <input type="hidden" name="action" value="apply_seller">
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label">Store name <span class="text-danger">*</span></label>
                                                <input class="form-control" type="text" name="store_name" maxlength="150" placeholder="e.g. TechHub PH" required>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Why do you want to sell on ProTech? <span class="text-secondary" style="font-weight:400;">(optional)</span></label>
                                                <textarea class="form-control" name="reason" placeholder="Tell us a bit about your store and what you plan to sell..."></textarea>
                                            </div>
                                        </div>
                                        <div class="mt-4">
                                            <button class="action-btn" type="submit">Submit Application</button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div><!-- /sellerFormShell -->
                        </div>
                    <?php endif; ?>

                <!-- ══════════════════════════════════════════════
                     SECURITY TAB
                ══════════════════════════════════════════════ -->
                <?php elseif ($activeSection === 'security'): ?>

                    <!-- Change Email -->
                    <div class="profile-card">
                        <div class="card-header-row">
                            <div>
                                <div class="section-label mb-1"><i class="fa-solid fa-envelope"></i> Email</div>
                                <h2 class="section-title mb-0" style="font-size:1.4rem;">Change Email</h2>
                            </div>
                        </div>
                        <p class="text-secondary mb-4" style="font-size:.9rem;">
                            Your current email is <strong><?= app_sanitize($user['email']) ?></strong>.
                            You must confirm your password to make this change.
                        </p>
                        <form method="post" style="max-width:480px;">
                            <input type="hidden" name="action" value="change_email">
                            <div class="mb-3">
                                <label class="form-label">New email address</label>
                                <input class="form-control" type="email" name="new_email" required autocomplete="email">
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Confirm your password</label>
                                <input class="form-control" type="password" name="confirm_password_email" required autocomplete="current-password">
                            </div>
                            <button class="action-btn" type="submit">Update Email</button>
                        </form>
                    </div>

                    <!-- Change Password -->
                    <div class="profile-card">
                        <div class="card-header-row">
                            <div>
                                <div class="section-label mb-1"><i class="fa-solid fa-lock"></i> Password</div>
                                <h2 class="section-title mb-0" style="font-size:1.4rem;">Change Password</h2>
                            </div>
                        </div>
                        <p class="text-secondary mb-4" style="font-size:.9rem;">Password changes are handled separately from profile details to keep your account secure.</p>
                        <form method="post" style="max-width:480px;">
                            <input type="hidden" name="action" value="change_password">
                            <div class="mb-3">
                                <label class="form-label">Current password</label>
                                <input class="form-control" type="password" name="current_password" required autocomplete="current-password">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">New password</label>
                                <input class="form-control" type="password" name="new_password" required autocomplete="new-password">
                            </div>
                            <div class="mb-4">
                                <label class="form-label">Confirm new password</label>
                                <input class="form-control" type="password" name="confirm_password" required autocomplete="new-password">
                            </div>
                            <button class="action-btn" type="submit">Update Password</button>
                        </form>
                    </div>

                <?php endif; ?>
            </main>

        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
<?php include __DIR__ . '/includes/scripts.php'; ?>
<script>
(() => {
    // ── Edit profile form toggle ──────────────────────────────
    const editShell = document.getElementById('editFormShell');
    const toggleBtn = document.getElementById('toggleEditBtn');
    const cancelBtn = document.getElementById('cancelEditBtn');
    toggleBtn?.addEventListener('click', () => editShell?.classList.toggle('open'));
    cancelBtn?.addEventListener('click', () => editShell?.classList.remove('open'));

    // ── Seller section reveal ─────────────────────────────────
    const sellerShell   = document.getElementById('sellerFormShell');
    const sellerToggle  = document.getElementById('toggleSellerBtn');
    const sellerChevron = document.getElementById('sellerChevron');
    let sellerOpen = false;

    sellerToggle?.addEventListener('click', () => {
        sellerOpen = !sellerOpen;
        sellerShell.style.display   = sellerOpen ? 'block' : 'none';
        sellerToggle.innerHTML      = sellerOpen
            ? '<i class="fa-solid fa-chevron-up"></i> Hide'
            : '<i class="fa-solid fa-chevron-down"></i> Show';
    });

    // ── Rate button stub ──────────────────────────────────────
    document.querySelectorAll('.rate-order-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const orderId = btn.dataset.orderId;
            // TODO: wire up to a rating modal or page
            alert('Rating for Order #' + orderId + ' — coming soon.');
        });
    });
})();
</script>
</body>
</html>