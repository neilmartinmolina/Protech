<?php
require_once __DIR__ . '/app.php';

$user = app_require_login();
$conn = app_db();

$activeSection = $_GET['section'] ?? 'general';
$allowedSections = ['general', 'settings', 'security', 'orders'];
if (!in_array($activeSection, $allowedSections)) {
    $activeSection = 'general';
}

$avatarUrl = app_avatar_url($user);
$customerOrders = app_get_orders_for_customer((int) $user['userId']);

$rateableOrders = [];
foreach ($customerOrders as $order) {
    if (in_array($order['status'], ['placed', 'processing', 'delivered'])) {
        $rateableOrders[] = $order;
    }
}

$flash = null;
$errors = [];
$openEditForm = false;

require_once __DIR__ . '/includes/profile_handler.php';

$postResult = profile_process_post($conn, $user);
$flash = $postResult['flash'];
$errors = $postResult['errors'];
$openEditForm = $postResult['openEditForm'];
$user = $postResult['user'];

if ($flash && $flash['type'] === 'success') {
    $activeSection = $postResult['activeSection'];
}

$profileData = profile_get_data($conn, $user);
$savedAddresses = $profileData['savedAddresses'];
$paymentMethods = $profileData['paymentMethods'];
$sellerApplication = $profileData['sellerApplication'];

$pageTitle = 'My Profile — ProTech';
$pageCss = ['my_profile.css'];
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
                    <li class="sidebar-nav-section">Account Settings</li>
                    <li><a href="?section=general" class="<?= $activeSection === 'general' ? 'active' : '' ?>"><i class="fa-solid fa-user"></i> General</a></li>
                    <li><a href="?section=settings" class="<?= $activeSection === 'settings' ? 'active' : '' ?>"><i class="fa-solid fa-gear"></i> Settings</a></li>
                    <li><a href="?section=orders" class="<?= $activeSection === 'orders' ? 'active' : '' ?>"><i class="fa-solid fa-box"></i> Orders</a></li>
                    <li><a href="?section=security" class="<?= $activeSection === 'security' ? 'active' : '' ?>"><i class="fa-solid fa-lock"></i> Security</a></li>
                </ul>
            </aside>

            <main>
                <?php if ($flash): ?>
                    <div class="flash <?= app_sanitize($flash['type']) ?>"><?= app_sanitize($flash['message']) ?></div>
                <?php endif; ?>

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
                            <input type="hidden" name="csrf_token" value="<?= app_csrf_token() ?>">
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

                    <div class="row g-4">
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
                                                    <strong class="d-block mt-1">₱<?= number_format((float) $order['total_amount'], 2) ?></strong>
                                                    <div class="text-secondary mt-1" style="font-size:.85rem;"><?= (int) $order['item_count'] ?> item(s)</div>
                                                </div>
                                                <div class="d-flex align-items-center gap-2">
<a href="receipt.php?order_id=<?= (int)$order['orderId'] ?>" target="_blank" title="View Receipt" style="color:#ff7315;text-decoration:none;font-size:.85rem;"><i class="fa-solid fa-receipt"></i> Receipt</a>
                                                    <span class="order-status"><?= app_sanitize(ucfirst($order['status'])) ?></span>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

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
                                                    <strong class="d-block mt-1">₱<?= number_format((float) $order['total_amount'], 2) ?></strong>
                                                    <div class="text-secondary mt-1" style="font-size:.85rem;"><?= (int) $order['item_count'] ?> item(s)</div>
                                                </div>
                                                <button class="ghost-btn rate-order-btn" type="button" data-order-id="<?= (int) $order['orderId'] ?>" style="font-size:.8rem; padding:.3rem .75rem;">Rate</button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                <?php elseif ($activeSection === 'settings'): ?>

                    <div class="profile-card">
                        <div class="card-header-row">
                            <div>
                                <div class="section-label mb-1"><i class="fa-solid fa-location-dot"></i> Addresses</div>
                                <h2 class="section-title mb-0" style="font-size:1.4rem;">Saved Addresses</h2>
                            </div>
                            <button class="ghost-btn" type="button" data-bs-toggle="modal" data-bs-target="#addAddressModal">+ Add Address</button>
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
                                                <?= $addr['zip'] ? ' ' . app_sanitize($addr['zip']) : '' ?>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <?php if (!$addr['is_default']): ?>
                                                <input type="hidden" name="csrf_token" value="<?= app_csrf_token() ?>">
<form method="post" style="display:inline;">
                                                    <input type="hidden" name="action" value="set_default_address">
                                                    <input type="hidden" name="address_id" value="<?= (int) $addr['userAddressId'] ?>">
                                                    <button type="submit" class="ghost-btn" style="font-size:.75rem; padding:.25rem .5rem;">Set Default</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this address?');">
                                                <input type="hidden" name="action" value="delete_address">
                                                <input type="hidden" name="address_id" value="<?= (int) $addr['userAddressId'] ?>">
                                                <button type="submit" class="ghost-btn" style="font-size:.75rem; padding:.25rem .5rem; color:var(--danger);">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="profile-card">
                        <div class="card-header-row">
                            <div>
                                <div class="section-label mb-1"><i class="fa-solid fa-credit-card"></i> Payment</div>
                                <h2 class="section-title mb-0" style="font-size:1.4rem;">Mode of Payment</h2>
                            </div>
                            <button class="ghost-btn" type="button" data-bs-toggle="modal" data-bs-target="#addPaymentModal">+ Add Payment</button>
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
                                        <div class="d-flex gap-2">
                                            <?php if (!$method['is_default']): ?>
                                                <input type="hidden" name="csrf_token" value="<?= app_csrf_token() ?>">
<form method="post" style="display:inline;">
                                                    <input type="hidden" name="action" value="set_default_payment">
                                                    <input type="hidden" name="payment_id" value="<?= (int) $method['userPaymentMethodId'] ?>">
                                                    <button type="submit" class="ghost-btn" style="font-size:.75rem; padding:.25rem .5rem;">Set Default</button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Delete this payment method?');">
                                                <input type="hidden" name="action" value="delete_payment">
                                                <input type="hidden" name="payment_id" value="<?= (int) $method['userPaymentMethodId'] ?>">
                                                <button type="submit" class="ghost-btn" style="font-size:.75rem; padding:.25rem .5rem; color:var(--danger);">Delete</button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

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
                                    <input type="hidden" name="csrf_token" value="<?= app_csrf_token() ?>">
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
                                    <input type="hidden" name="csrf_token" value="<?= app_csrf_token() ?>">
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
                        </div>
                    <?php endif; ?>

                <?php elseif ($activeSection === 'orders'): ?>

                    <div class="profile-card">
                        <div class="card-header-row">
                            <div>
                                <div class="section-label mb-1"><i class="fa-solid fa-box"></i> Orders</div>
                                <h2 class="section-title mb-0" style="font-size:1.4rem;">My Orders</h2>
                            </div>
                        </div>
                        <?php if (empty($customerOrders)): ?>
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
                                            <strong class="d-block mt-1">₱<?= number_format((float) $order['total_amount'], 2) ?></strong>
                                            <div class="text-secondary mt-1" style="font-size:.85rem;"><?= (int) $order['item_count'] ?> item(s)</div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
<a href="report.php?order_id=<?= (int)$order['orderId'] ?>" target="_blank" title="View Report" style="color:#ff7315;text-decoration:none;font-size:.85rem;"><i class="fa-solid fa-receipt"></i> Report</a>
                                            <span class="order-status"><?= app_sanitize(ucfirst($order['status'])) ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php elseif ($activeSection === 'security'): ?>

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
                        <input type="hidden" name="csrf_token" value="<?= app_csrf_token() ?>">
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

                    <div class="profile-card">
                        <div class="card-header-row">
                            <div>
                                <div class="section-label mb-1"><i class="fa-solid fa-lock"></i> Password</div>
                                <h2 class="section-title mb-0" style="font-size:1.4rem;">Change Password</h2>
                            </div>
                        </div>
                        <p class="text-secondary mb-4" style="font-size:.9rem;">Password changes are handled separately from profile details to keep your account secure.</p>
                        <input type="hidden" name="csrf_token" value="<?= app_csrf_token() ?>">
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

<div class="modal" id="addAddressModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Address</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <input type="hidden" name="csrf_token" value="<?= app_csrf_token() ?>">
<form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_address">
                    <div class="mb-3">
                        <label class="form-label">Label <span class="text-secondary">(optional)</span></label>
                        <input class="form-control" type="text" name="label" placeholder="e.g. Home, Work">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Recipient Name <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="recipient_name" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                        <input class="form-control" type="tel" name="phone" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Street Address <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="street" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Barangay <span class="text-danger">*</span></label>
                        <input class="form-control" type="text" name="barangay" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">City <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="city" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Province</label>
                            <input class="form-control" type="text" name="province">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ZIP Code</label>
                        <input class="form-control" type="text" name="zip">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="set_default" id="setDefaultAddr">
                        <label class="form-check-label" for="setDefaultAddr">Set as default address</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ghost-btn" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="action-btn">Save Address</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal" id="addPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Payment Method</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <input type="hidden" name="csrf_token" value="<?= app_csrf_token() ?>">
<form method="post">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_payment">
                    <div class="mb-3">
                        <label class="form-label">Payment Type <span class="text-danger">*</span></label>
                        <select class="form-select" name="payment_type" id="paymentTypeSelect" required>
                            <option value="">Select type...</option>
                            <option value="gcash">GCash</option>
                            <option value="cod">Cash on Delivery</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Label <span class="text-secondary">(optional)</span></label>
                        <input class="form-control" type="text" name="label" placeholder="e.g. Personal GCash">
                    </div>
                    <div id="gcashFields" style="display:none;">
                        <div class="mb-3">
                            <label class="form-label">GCash Name <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="gcash_name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">GCash Number <span class="text-danger">*</span></label>
                            <input class="form-control" type="text" name="gcash_number" pattern="[0-9]{11}" placeholder="09123456789">
                        </div>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="set_default" id="setDefaultPay">
                        <label class="form-check-label" for="setDefaultPay">Set as default payment method</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="ghost-btn" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="action-btn">Save Payment Method</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/scripts.php'; ?>
<script>
(() => {
    const editShell = document.getElementById('editFormShell');
    const toggleBtn = document.getElementById('toggleEditBtn');
    const cancelBtn = document.getElementById('cancelEditBtn');
    toggleBtn?.addEventListener('click', () => editShell?.classList.toggle('open'));
    cancelBtn?.addEventListener('click', () => editShell?.classList.remove('open'));

    const sellerShell = document.getElementById('sellerFormShell');
    const sellerToggle = document.getElementById('toggleSellerBtn');
    const sellerChevron = document.getElementById('sellerChevron');
    let sellerOpen = false;

    sellerToggle?.addEventListener('click', () => {
        sellerOpen = !sellerOpen;
        sellerShell.style.display = sellerOpen ? 'block' : 'none';
        sellerToggle.innerHTML = sellerOpen
            ? '<i class="fa-solid fa-chevron-up"></i> Hide'
            : '<i class="fa-solid fa-chevron-down"></i> Show';
    });

    const paymentTypeSelect = document.getElementById('paymentTypeSelect');
    const gcashFields = document.getElementById('gcashFields');
    paymentTypeSelect?.addEventListener('change', () => {
        if (paymentTypeSelect.value === 'gcash') {
            gcashFields.style.display = 'block';
            gcashFields.querySelectorAll('input').forEach(i => i.required = true);
        } else {
            gcashFields.style.display = 'none';
            gcashFields.querySelectorAll('input').forEach(i => i.required = false);
        }
    });

    document.querySelectorAll('.rate-order-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const orderId = btn.dataset.orderId;
            alert('Rating for Order #' + orderId + ' — coming soon.');
        });
    });
})();
</script>
</body>
</html>