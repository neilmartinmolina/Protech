<?php
require_once __DIR__ . '/app.php';

$user = app_require_login();
$conn = app_db();
$flash = null;
$errors = [];
$openEditForm = false;
$avatarUrl = app_avatar_url($user);
$customerOrders = app_get_orders_for_customer((int) $user['id']);

// Active section from query string; default to 'general'
$activeSection = $_GET['section'] ?? 'general';
$allowedSections = ['general', 'account', 'security'];
if (!in_array($activeSection, $allowedSections)) $activeSection = 'general';

// Fetch latest seller application for this user (if any)
$sellerApplication = null;
if (($user['role'] ?? '') === 'customer') {
    $stmt = $conn->prepare('SELECT * FROM seller_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
    $stmt->bind_param('i', $user['id']);
    $stmt->execute();
    $sellerApplication = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

// --- TODO: Replace with real DB queries once tables exist ---
$paymentMethods = [];
$savedAddresses  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Update profile ──────────────────────────────────────
    if ($action === 'update_profile') {
        $activeSection = 'general';
        $openEditForm  = true;
        $firstName  = trim($_POST['first_name']  ?? '');
        $lastName   = trim($_POST['last_name']   ?? '');
        $username   = trim($_POST['username']    ?? '');
        $email      = trim($_POST['email']       ?? '');
        $storeName  = trim($_POST['store_name']  ?? '');
        $avatarFile = $_FILES['avatar'] ?? null;

        if ($firstName === '') $errors[] = 'First name is required.';
        if ($lastName  === '') $errors[] = 'Last name is required.';
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) $errors[] = 'Username must be 3–50 characters (letters, numbers, underscores only).';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))        $errors[] = 'A valid email address is required.';
        if (($user['role'] ?? '') === 'seller' && $storeName === '') $errors[] = 'Store name is required for sellers.';

        $avatarResult = ['success' => true, 'path' => $user['avatar_path'] ?? null];

        if (!$errors) {
            $stmt = $conn->prepare('SELECT id FROM users WHERE (email = ? OR username = ?) AND id <> ? LIMIT 1');
            $stmt->bind_param('ssi', $email, $username, $user['id']);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($exists) $errors[] = 'That email or username is already in use by another account.';
        }

        if (!$errors && $avatarFile && ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $avatarResult = app_store_avatar($avatarFile, $user['avatar_path'] ?? null);
            if (!$avatarResult['success']) $errors[] = $avatarResult['message'];
        }

        if (!$errors) {
            $avatarPath = $avatarResult['path'] ?? ($user['avatar_path'] ?? null);
            $stmt = $conn->prepare('UPDATE users SET first_name=?, last_name=?, username=?, email=?, store_name=?, avatar_path=? WHERE id=?');
            $stmt->bind_param('ssssssi', $firstName, $lastName, $username, $email, $storeName, $avatarPath, $user['id']);
            $stmt->execute();
            $stmt->close();

            $user      = app_refresh_session_user((int) $user['id']) ?? $user;
            $avatarUrl = app_avatar_url($user);
            $flash     = ['type' => 'success', 'message' => 'Profile updated successfully.'];
            $openEditForm = false;
        }
    }

    // ── Seller application ──────────────────────────────────
    if ($action === 'apply_seller') {
        $activeSection = 'account';
        $applyStoreName = trim($_POST['store_name'] ?? '');
        $applyReason    = trim($_POST['reason']     ?? '');

        if ($applyStoreName === '') $errors[] = 'Store name is required.';
        if (strlen($applyStoreName) > 150) $errors[] = 'Store name must be 150 characters or less.';

        // Block if already pending or approved
        if (!$errors) {
            $stmt = $conn->prepare("SELECT status FROM seller_applications WHERE user_id = ? AND status = 'pending' LIMIT 1");
            $stmt->bind_param('i', $user['id']);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($existing) $errors[] = 'You already have a pending application.';
        }

        if (!$errors && ($user['role'] ?? '') !== 'customer') {
            $errors[] = 'Only customers can apply to become a seller.';
        }

        if (!$errors) {
            $stmt = $conn->prepare('INSERT INTO seller_applications (user_id, store_name, reason, status) VALUES (?, ?, ?, \'pending\')');
            $stmt->bind_param('iss', $user['id'], $applyStoreName, $applyReason);
            $stmt->execute();
            $stmt->close();

            // Mark seller_status on users so the rest of the app can fast-check
            $stmt = $conn->prepare("UPDATE users SET seller_status = 'pending' WHERE id = ?");
            $stmt->bind_param('i', $user['id']);
            $stmt->execute();
            $stmt->close();

            $user = app_refresh_session_user((int) $user['id']) ?? $user;

            // Re-fetch application so the UI reflects it immediately
            $stmt = $conn->prepare('SELECT * FROM seller_applications WHERE user_id = ? ORDER BY created_at DESC LIMIT 1');
            $stmt->bind_param('i', $user['id']);
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

        $stmt = $conn->prepare('SELECT password_hash FROM users WHERE id=? LIMIT 1');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$account || !password_verify($currentPassword, $account['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        }

        if (!$errors) {
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $conn->prepare('UPDATE users SET password_hash=?, temp_password=NULL WHERE id=?');
            $stmt->bind_param('si', $newHash, $user['id']);
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/header.php'; ?>
    <style>
        /* ── Shell ───────────────────────────────────────────── */
        .profile-shell { padding: 7rem 0 4rem; min-height: 100vh; }

        /* ── Layout ──────────────────────────────────────────── */
        .profile-layout {
            display: grid;
            grid-template-columns: 260px 1fr;
            gap: 1.5rem;
            align-items: start;
        }
        @media (max-width: 991.98px) {
            .profile-layout { grid-template-columns: 1fr; }
        }

        /* ── Sidebar ─────────────────────────────────────────── */
        .profile-sidebar {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 1.25rem;
            box-shadow: var(--shadow-sm);
            position: sticky;
            top: 5.5rem;
        }
        .sidebar-user {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            padding-bottom: 1.25rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 1.25rem;
        }
        .sidebar-avatar {
            width: 72px; height: 72px;
            border-radius: 50%;
            background: rgba(255,115,21,.12);
            color: var(--primary);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.8rem;
            margin-bottom: .75rem;
            overflow: hidden; flex-shrink: 0;
        }
        .sidebar-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .sidebar-name { font-weight: 700; color: var(--text-primary); font-size: 1rem; line-height: 1.3; margin-bottom: .2rem; }
        .sidebar-role { font-size: .8rem; color: var(--text-muted); text-transform: capitalize; }
        .sidebar-nav { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: .25rem; }
        .sidebar-nav-section { font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; color: var(--text-muted); padding: .75rem .75rem .35rem; }
        .sidebar-nav a {
            display: flex; align-items: center; gap: .75rem;
            padding: .65rem .85rem; border-radius: 12px;
            color: var(--text-secondary); text-decoration: none;
            font-size: .9rem; font-weight: 500;
            transition: background .15s, color .15s;
        }
        .sidebar-nav a i { width: 16px; text-align: center; opacity: .7; }
        .sidebar-nav a:hover { background: var(--surface-light); color: var(--text-primary); }
        .sidebar-nav a.active { background: rgba(255,115,21,.1); color: var(--primary); font-weight: 600; }
        .sidebar-nav a.active i { opacity: 1; }
        @media (max-width: 991.98px) {
            .profile-sidebar { position: static; padding: 1rem; }
            .sidebar-user { display: none; }
            .sidebar-nav { flex-direction: row; overflow-x: auto; gap: .5rem; padding-bottom: .25rem; }
            .sidebar-nav-section { display: none; }
            .sidebar-nav a { white-space: nowrap; padding: .5rem .9rem; border-radius: 999px; font-size: .83rem; }
        }

        /* ── Cards ───────────────────────────────────────────── */
        .profile-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 1.75rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 1.5rem;
        }
        .profile-card:last-child { margin-bottom: 0; }
        .card-header-row {
            display: flex; align-items: center; justify-content: space-between;
            gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem;
        }

        /* ── Detail grid ─────────────────────────────────────── */
        .detail-grid { display: grid; grid-template-columns: repeat(2, minmax(0,1fr)); gap: 1rem; }
        @media (max-width: 575.98px) { .detail-grid { grid-template-columns: 1fr; } }
        .detail-item { background: var(--surface-light); border: 1px solid var(--border); border-radius: 14px; padding: 1rem; }
        .detail-item small { color: var(--text-muted); display: block; margin-bottom: .35rem; font-size: .78rem; }
        .detail-item strong { color: var(--text-primary); font-size: .95rem; }

        /* ── Empty states ────────────────────────────────────── */
        .empty-state { text-align: center; padding: 2.5rem 1rem; border: 2px dashed var(--border); border-radius: 14px; color: var(--text-muted); }
        .empty-state i { font-size: 2rem; margin-bottom: .75rem; opacity: .4; display: block; }

        /* ── Forms ───────────────────────────────────────────── */
        .form-shell { display: none; margin-top: 1.25rem; }
        .form-shell.open { display: block; }
        .form-control {
            background: var(--surface-light); border: 1px solid var(--border);
            color: var(--text-primary); border-radius: 12px; padding: .8rem .95rem;
        }
        .form-control:focus {
            background: var(--surface-light); color: var(--text-primary);
            border-color: var(--primary); box-shadow: 0 0 0 4px rgba(255,115,21,.1);
        }
        textarea.form-control { resize: vertical; min-height: 90px; }

        /* ── Buttons ─────────────────────────────────────────── */
        .action-btn {
            background: var(--primary); color: #fff; border: none;
            border-radius: 12px; padding: .75rem 1.25rem;
            font-weight: 600; font-size: .9rem; cursor: pointer; transition: opacity .15s;
        }
        .action-btn:hover { opacity: .88; }
        .ghost-btn {
            background: transparent; color: var(--text-secondary);
            border: 1px solid var(--border); border-radius: 12px;
            padding: .75rem 1.25rem; font-weight: 600; font-size: .9rem;
            cursor: pointer; transition: background .15s, color .15s;
        }
        .ghost-btn:hover { background: var(--surface-light); color: var(--text-primary); }

        /* ── Flash ───────────────────────────────────────────── */
        .flash { border-radius: 14px; padding: .95rem 1rem; margin-bottom: 1.25rem; font-size: .9rem; }
        .flash.success { background: rgba(16,185,129,.12); color: #7cf2bf; }
        .flash.danger  { background: rgba(239,68,68,.12);  color: #ffaaaa; }

        /* ── Order badge ─────────────────────────────────────── */
        .order-status {
            display: inline-block; font-size: .72rem; font-weight: 600;
            padding: .2rem .6rem; border-radius: 999px;
            background: rgba(255,115,21,.12); color: var(--primary);
            text-transform: capitalize;
        }

        /* ── Application status banner ───────────────────────── */
        .app-status-banner {
            border-radius: 14px; padding: 1.25rem 1.5rem;
            display: flex; align-items: flex-start; gap: 1rem;
        }
        .app-status-banner.pending  { background: rgba(245,158,11,.1);  border: 1px solid rgba(245,158,11,.25); }
        .app-status-banner.approved { background: rgba(16,185,129,.1);  border: 1px solid rgba(16,185,129,.25); }
        .app-status-banner.rejected { background: rgba(239,68,68,.1);   border: 1px solid rgba(239,68,68,.25); }
        .app-status-banner .banner-icon { font-size: 1.5rem; flex-shrink: 0; margin-top: .1rem; }
        .app-status-banner.pending  .banner-icon { color: #f59e0b; }
        .app-status-banner.approved .banner-icon { color: #10b981; }
        .app-status-banner.rejected .banner-icon { color: #ef4444; }
        .app-status-banner .banner-title { font-weight: 700; color: var(--text-primary); margin-bottom: .2rem; }
        .app-status-banner .banner-sub   { font-size: .85rem; color: var(--text-secondary); }

        /* ── Seller CTA card ─────────────────────────────────── */
        .seller-cta {
            border: 1px dashed rgba(255,115,21,.4);
            border-radius: 16px; padding: 2rem;
            text-align: center; background: rgba(255,115,21,.04);
        }
        .seller-cta-icon { font-size: 2.5rem; color: var(--primary); margin-bottom: 1rem; opacity: .8; }
        .seller-cta h3 { font-size: 1.15rem; font-weight: 700; color: var(--text-primary); margin-bottom: .5rem; }
        .seller-cta p { font-size: .88rem; color: var(--text-muted); margin-bottom: 1.5rem; max-width: 380px; margin-left: auto; margin-right: auto; }

        /* ── Stub badge ──────────────────────────────────────── */
        .stub-badge {
            display: inline-block; font-size: .7rem; padding: .15rem .55rem;
            border-radius: 999px; background: rgba(99,102,241,.12); color: #a5b4fc;
            font-weight: 600; margin-left: .5rem; vertical-align: middle;
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>

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
                    <li class="sidebar-nav-section">Menu</li>
                    <li><a href="?section=general"  class="<?= $activeSection === 'general'  ? 'active' : '' ?>"><i class="fa-solid fa-user"></i> General</a></li>
                    <li><a href="?section=account"  class="<?= $activeSection === 'account'  ? 'active' : '' ?>"><i class="fa-solid fa-gear"></i> Account</a></li>
                    <li><a href="?section=security" class="<?= $activeSection === 'security' ? 'active' : '' ?>"><i class="fa-solid fa-lock"></i> Security</a></li>
                </ul>
            </aside>

            <!-- ═══ MAIN CONTENT ═══════════════════════════════ -->
            <main>
                <?php if ($flash): ?>
                    <div class="flash <?= app_sanitize($flash['type']) ?>"><?= app_sanitize($flash['message']) ?></div>
                <?php endif; ?>

                <!-- ── GENERAL ──────────────────────────────── -->
                <?php if ($activeSection === 'general'): ?>

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
                                        <input class="form-control" type="email" name="email" value="<?= app_sanitize($user['email']) ?>" required>
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

                    <!-- My Orders -->
                    <div class="profile-card">
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
                                            <small>Order #<?= (int) $order['id'] ?> &bull; <?= app_sanitize($order['created_at']) ?></small>
                                            <strong class="d-block mt-1">$<?= number_format((float) $order['total_amount'], 2) ?></strong>
                                            <div class="text-secondary mt-1" style="font-size:.85rem;"><?= (int) $order['item_count'] ?> item(s)</div>
                                        </div>
                                        <span class="order-status"><?= app_sanitize(ucfirst($order['status'])) ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                <!-- ── ACCOUNT ──────────────────────────────── -->
                <?php elseif ($activeSection === 'account'): ?>

                    <!-- Become a Seller — only for customers -->
                    <?php if (($user['role'] ?? '') === 'customer'): ?>
                        <div class="profile-card">
                            <div class="card-header-row">
                                <div>
                                    <div class="section-label mb-1"><i class="fa-solid fa-store"></i> Seller</div>
                                    <h2 class="section-title mb-0" style="font-size:1.4rem;">Become a Seller</h2>
                                </div>
                            </div>

                            <?php
                            $appStatus = $sellerApplication['status'] ?? null;
                            ?>

                            <?php if ($appStatus === 'pending'): ?>
                                <!-- Pending state -->
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
                                <!-- Approved — shouldn't normally show since role would change, safety net -->
                                <div class="app-status-banner approved">
                                    <div class="banner-icon"><i class="fa-solid fa-circle-check"></i></div>
                                    <div>
                                        <div class="banner-title">Application approved</div>
                                        <div class="banner-sub">Your seller account is active. If your role hasn't updated, please log out and back in.</div>
                                    </div>
                                </div>

                            <?php elseif ($appStatus === 'rejected'): ?>
                                <!-- Rejected — show reason and allow re-application -->
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
                                <!-- Fall through to show the form -->
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
                                <!-- No application yet — show CTA + form -->
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
                        </div>
                    <?php endif; ?>

                    <!-- Payment methods -->
                    <div class="profile-card">
                        <div class="card-header-row">
                            <div>
                                <div class="section-label mb-1"><i class="fa-solid fa-credit-card"></i> Payment</div>
                                <h2 class="section-title mb-0" style="font-size:1.4rem;">Mode of Payment <span class="stub-badge">Coming soon</span></h2>
                            </div>
                        </div>
                        <?php if (empty($paymentMethods)): ?>
                            <div class="empty-state">
                                <i class="fa-solid fa-credit-card"></i>
                                No saved payment methods yet.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Saved addresses -->
                    <div class="profile-card">
                        <div class="card-header-row">
                            <div>
                                <div class="section-label mb-1"><i class="fa-solid fa-location-dot"></i> Addresses</div>
                                <h2 class="section-title mb-0" style="font-size:1.4rem;">Saved Addresses <span class="stub-badge">Coming soon</span></h2>
                            </div>
                        </div>
                        <?php if (empty($savedAddresses)): ?>
                            <div class="empty-state">
                                <i class="fa-solid fa-map-pin"></i>
                                No saved addresses yet.
                            </div>
                        <?php endif; ?>
                    </div>

                <!-- ── SECURITY ──────────────────────────────── -->
                <?php elseif ($activeSection === 'security'): ?>

                    <div class="profile-card">
                        <div class="card-header-row">
                            <div>
                                <div class="section-label mb-1"><i class="fa-solid fa-lock"></i> Security</div>
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

<?php include __DIR__ . '/footer.php'; ?>
<script>
(() => {
    const editShell = document.getElementById('editFormShell');
    const toggleBtn = document.getElementById('toggleEditBtn');
    const cancelBtn = document.getElementById('cancelEditBtn');
    toggleBtn?.addEventListener('click', () => editShell?.classList.toggle('open'));
    cancelBtn?.addEventListener('click', () => editShell?.classList.remove('open'));
})();
</script>
</body>
</html>