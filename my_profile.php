<?php
require_once __DIR__ . '/app.php';

$user = app_require_login();
$conn = app_db();
$flash = null;
$errors = [];
$openEditForm = false;
$focusPasswordSection = false;
$avatarUrl = app_avatar_url($user);
$customerOrders = app_get_orders_for_customer((int) $user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $openEditForm = true;
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $storeName = trim($_POST['store_name'] ?? '');
        $avatarFile = $_FILES['avatar'] ?? null;

        if ($firstName === '') $errors[] = 'First name is required.';
        if ($lastName === '') $errors[] = 'Last name is required.';
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) $errors[] = 'Username must be 3-50 characters and use only letters, numbers, or underscores.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
        if (($user['role'] ?? '') === 'seller' && $storeName === '') $errors[] = 'Store name is required for sellers.';

        $avatarResult = ['success' => true, 'path' => $user['avatar_path'] ?? null];

        if (!$errors) {
            $stmt = $conn->prepare('SELECT id FROM users WHERE (email = ? OR username = ?) AND id <> ? LIMIT 1');
            $stmt->bind_param('ssi', $email, $username, $user['id']);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($exists) {
                $errors[] = 'That email or username is already being used by another account.';
            }
        }

        if (!$errors) {
            if ($avatarFile && ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                $avatarResult = app_store_avatar($avatarFile, $user['avatar_path'] ?? null);
                if (!$avatarResult['success']) {
                    $errors[] = $avatarResult['message'];
                }
            }
        }

        if (!$errors) {
            $avatarPath = $avatarResult['path'] ?? ($user['avatar_path'] ?? null);
            $stmt = $conn->prepare('UPDATE users SET first_name = ?, last_name = ?, username = ?, email = ?, store_name = ?, avatar_path = ? WHERE id = ?');
            $stmt->bind_param('ssssssi', $firstName, $lastName, $username, $email, $storeName, $avatarPath, $user['id']);
            $stmt->execute();
            $stmt->close();

            $user = app_refresh_session_user((int) $user['id']) ?? $user;
            $avatarUrl = app_avatar_url($user);
            $flash = ['type' => 'success', 'message' => 'Profile details updated successfully.'];
            $openEditForm = false;
        }
    }

    if ($action === 'change_password') {
        $focusPasswordSection = true;
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '') $errors[] = 'Current password is required.';
        if (strlen($newPassword) < 6) $errors[] = 'New password must be at least 6 characters.';
        if ($newPassword !== $confirmPassword) $errors[] = 'New password and confirmation do not match.';

        $stmt = $conn->prepare('SELECT password_hash FROM users WHERE id = ? LIMIT 1');
        $stmt->bind_param('i', $user['id']);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$account || !password_verify($currentPassword, $account['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        }

        if (!$errors) {
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $conn->prepare('UPDATE users SET password_hash = ?, temp_password = NULL WHERE id = ?');
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

$pageTitle = 'My Profile - ProTech';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/header.php'; ?>
    <style>
        .profile-shell { padding: 7rem 0 4rem; min-height: 100vh; }
        .profile-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 18px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }
        .profile-hero {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 1.5rem;
        }
        .profile-avatar {
            width: 72px;
            height: 72px;
            border-radius: 50%;
            background: rgba(255,115,21,.12);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 1rem;
        }
        .detail-item {
            background: var(--surface-light);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 1rem;
        }
        .detail-item small { color: var(--text-muted); display: block; margin-bottom: 0.35rem; }
        .detail-item strong { color: var(--text-primary); font-size: 0.98rem; }
        .form-shell { display: none; margin-top: 1.25rem; }
        .form-shell.open { display: block; }
        .form-control {
            background: var(--surface-light);
            border: 1px solid var(--border);
            color: var(--text-primary);
            border-radius: 12px;
            padding: 0.8rem 0.95rem;
        }
        .form-control:focus {
            background: var(--surface-light);
            color: var(--text-primary);
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(255,115,21,.1);
        }
        .action-btn {
            background: var(--primary);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 0.8rem 1rem;
            font-weight: 600;
        }
        .ghost-btn {
            background: transparent;
            color: var(--text-secondary);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 0.8rem 1rem;
            font-weight: 600;
        }
        .flash {
            border-radius: 14px;
            padding: 0.95rem 1rem;
            margin-bottom: 1rem;
        }
        .flash.success { background: rgba(16,185,129,.12); color: #7cf2bf; }
        .flash.danger { background: rgba(239,68,68,.12); color: #ffaaaa; }
        @media (max-width: 767.98px) {
            .detail-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>

<section class="profile-shell">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-7">
                <div class="profile-card">
                    <div class="profile-hero">
                        <div class="d-flex align-items-center gap-3">
                            <div class="profile-avatar" style="<?= $avatarUrl ? 'background: transparent; padding: 0;' : '' ?>">
                                <?php if ($avatarUrl): ?>
                                    <img src="<?= app_sanitize($avatarUrl) ?>" alt="Profile photo" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                                <?php else: ?>
                                    <i class="fa-solid fa-user"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <div class="section-label mb-2"><i class="fa-solid fa-id-card"></i> My Profile</div>
                                <h1 class="section-title mb-1"><?= app_sanitize($user['firstName'] . ' ' . $user['lastName']) ?></h1>
                                <p class="mb-0 text-secondary"><?= app_sanitize(ucfirst($user['role'])) ?> account</p>
                            </div>
                        </div>
                        <button class="ghost-btn" type="button" id="toggleEditBtn">Edit Details</button>
                    </div>

                    <?php if ($flash): ?>
                        <div class="flash <?= app_sanitize($flash['type']) ?>"><?= app_sanitize($flash['message']) ?></div>
                    <?php endif; ?>

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
                        <hr class="my-4" style="border-color: var(--border);">
                        <form method="post" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_profile">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Profile photo</label>
                                    <input class="form-control" type="file" name="avatar" accept="image/*">
                                    <small class="text-secondary">Upload JPG, PNG, WEBP, or GIF up to 2MB.</small>
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
            </div>

            <div class="col-lg-5">
                <div class="profile-card mb-4">
                    <div class="section-label mb-2"><i class="fa-solid fa-receipt"></i> Orders</div>
                    <h2 class="section-title" style="font-size: 1.8rem;">My Orders</h2>
                    <?php if (!$customerOrders): ?>
                        <p class="text-secondary mb-0">You have not placed any orders yet.</p>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ($customerOrders as $order): ?>
                                <div class="detail-item">
                                    <small>Order #<?= (int) $order['id'] ?> • <?= app_sanitize($order['created_at']) ?></small>
                                    <strong>$<?= number_format((float) $order['total_amount'], 2) ?></strong>
                                    <div class="text-secondary mt-2"><?= (int) $order['item_count'] ?> item(s) • <?= app_sanitize(ucfirst($order['status'])) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="profile-card" id="passwordSection">
                    <div class="section-label mb-2"><i class="fa-solid fa-lock"></i> Security</div>
                    <h2 class="section-title" style="font-size: 1.8rem;">Change Password</h2>
                    <p class="text-secondary">This is separate from your profile details so account info and password changes stay independent.</p>

                    <form method="post">
                        <input type="hidden" name="action" value="change_password">
                        <div class="mb-3">
                            <label class="form-label">Current password</label>
                            <input class="form-control" type="password" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New password</label>
                            <input class="form-control" type="password" name="new_password" required>
                        </div>
                        <div class="mb-4">
                            <label class="form-label">Confirm new password</label>
                            <input class="form-control" type="password" name="confirm_password" required>
                        </div>
                        <button class="action-btn w-100" type="submit">Update Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/footer.php'; ?>
<script>
(() => {
    const editShell = document.getElementById('editFormShell');
    const toggleBtn = document.getElementById('toggleEditBtn');
    const cancelBtn = document.getElementById('cancelEditBtn');

    toggleBtn?.addEventListener('click', () => editShell.classList.toggle('open'));
    cancelBtn?.addEventListener('click', () => editShell.classList.remove('open'));

    <?php if ($focusPasswordSection): ?>
    document.getElementById('passwordSection')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    <?php endif; ?>
})();
</script>
</body>
</html>
