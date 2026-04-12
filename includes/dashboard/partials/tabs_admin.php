<?php
/** @var string $tab */
/** @var array $adminStats */
/** @var array $pendingApplications */
/** @var array $adminOrders */
/** @var array $adminProducts */
/** @var array $adminUsers */
/** @var array $activityLogs */
/** @var array $notifications */
/** @var array $currentUser */
?>
<?php if ($tab === 'dashboard'): ?>
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-users"></i></div><div class="stat-value"><?= $adminStats['users'] ?></div><div class="stat-label">Customers</div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon orange"><i class="fa-solid fa-store"></i></div><div class="stat-value"><?= $adminStats['sellers'] ?></div><div class="stat-label">Approved Sellers</div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon blue"><i class="fa-solid fa-receipt"></i></div><div class="stat-value"><?= $adminStats['orders'] ?></div><div class="stat-label">Orders</div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon red"><i class="fa-solid fa-dollar-sign"></i></div><div class="stat-value">₱<?= number_format($adminStats['revenue'], 2) ?></div><div class="stat-label">Revenue</div></div></div>
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

<?php elseif ($tab === 'users'): ?>
    <div class="table-card">
        <div class="table-card-header">
            <h5>All Users <span class="badge-count"><?= count($adminUsers) ?></span></h5>
            <button class="add-product-btn" type="button"
                data-modal-target="#userModal"
                data-modal-title="Add User"
                data-modal-message="Create a new user account."
                data-modal-confirm="Save User">
                Add User
            </button>
        </div>
        <div class="table-card-body table-responsive">
            <?php if (!$adminUsers): ?>
                <div class="p-4">No users found.</div>
            <?php else: ?>
                <table id="adminUsersTable" class="table table-sm w-100 mb-0">
                    <thead>
                        <tr>
                            <th>Photo</th><th>ID</th><th>Name</th><th>Email</th><th>Username</th>
                            <th>Role</th><th>Seller status</th><th>Store</th><th>Joined</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($adminUsers as $u): ?>
                        <tr>
                            <td class="admin-user-photo-cell">
                                <?php if (!empty($u['avatar_path'])): ?>
                                    <img src="<?= app_sanitize($u['avatar_path']) ?>" alt="" class="admin-user-thumb" width="40" height="40">
                                <?php else: ?>
                                    <div class="admin-user-thumb admin-user-thumb--placeholder" aria-hidden="true"><i class="fa-solid fa-user"></i></div>
                                <?php endif; ?>
                            </td>
                            <td>#<?= (int) $u['userId'] ?></td>
                            <td><?= app_sanitize(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))) ?></td>
                            <td><?= app_sanitize($u['email'] ?? '') ?></td>
                            <td><?= app_sanitize($u['username'] ?? '') ?></td>
                            <td><span class="pill <?= app_sanitize(in_array($u['role'], ['admin', 'superadmin']) ? $u['role'] : ($u['role'] ?? '')) ?>"><?= app_sanitize(ucfirst($u['role'] ?? '')) ?></span></td>
                            <td><?= app_sanitize($u['seller_status'] ?? '') ?></td>
                            <td><?= app_sanitize($u['store_name'] ?? '') ?></td>
                            <td><?= app_sanitize(isset($u['created_at']) ? date('M j, Y', strtotime($u['created_at'])) : '') ?></td>
                            <td>
                                <div class="action-stack">
                                    <button class="edit-btn" type="button" title="Edit"
                                        data-modal-target="#userModal"
                                        data-modal-title="Edit User"
                                        data-modal-message="Update this user account."
                                        data-modal-confirm="Save User"
                                        data-modal-payload='<?= app_sanitize(json_encode([
                                            'user_id'       => (int) $u['userId'],
                                            'first_name'    => $u['first_name'] ?? '',
                                            'last_name'     => $u['last_name'] ?? '',
                                            'username'      => $u['username'] ?? '',
                                            'email'         => $u['email'] ?? '',
                                            'role'          => $u['role'] ?? 'customer',
                                            'seller_status' => $u['seller_status'] ?? 'not_applicable',
                                            'store_name'    => $u['store_name'] ?? '',
                                            'password'      => '',
                                        ], JSON_UNESCAPED_UNICODE)) ?>'><i class="fa-solid fa-pencil"></i></button>
                                    <?php if (app_can_delete_user($currentUser, $u)): ?>
                                    <button class="reject-btn" type="button" title="Delete"
                                        data-modal-target="#deleteUserModal"
                                        data-modal-title="Delete User"
                                        data-modal-message="Permanently delete <?= app_sanitize($u['username'] ?? 'this user') ?>? This cannot be undone."
                                        data-modal-confirm="Delete"
                                        data-modal-payload='<?= app_sanitize(json_encode(['user_id' => (int) $u['userId']])) ?>'><i class="fa-solid fa-trash"></i></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($tab === 'sellers'): ?>
    <div class="table-card">
        <div class="table-card-header">
            <h5>Pending Seller Applications <span class="badge-count"><?= count($pendingApplications) ?></span></h5>
        </div>
        <div class="table-card-body table-responsive">
            <?php if (!$pendingApplications): ?>
                <div class="p-4 text-secondary">No pending seller applications. You're all caught up.</div>
            <?php else: ?>
                <table id="sellerRequestsTable" class="table table-sm w-100 mb-0">
                    <thead><tr><th>Applicant</th><th>Store Name</th><th>Email</th><th>Username</th><th>Reason</th><th>Applied</th><th>Status</th><th>Action</th></tr></thead>
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
                                    <button class="approve-btn" type="button" title="Approve"
                                        data-modal-target="#sellerActionModal"
                                        data-modal-title="Approve Seller Application"
                                        data-modal-message="Approve <?= app_sanitize($app['first_name'] . ' ' . $app['last_name']) ?> for store <?= app_sanitize($app['store_name']) ?>? Their account will be promoted to seller and they'll receive a notification email."
                                        data-modal-confirm="Approve"
                                        data-modal-payload='<?= app_sanitize(json_encode(['action' => 'approve_seller', 'application_id' => (int) $app['app_id']])) ?>'><i class="fa-solid fa-check"></i></button>
                                    <button class="reject-btn" type="button" title="Reject"
                                        data-modal-target="#rejectSellerModal"
                                        data-modal-title="Reject Application"
                                        data-modal-message="Reject <?= app_sanitize($app['first_name'] . ' ' . $app['last_name']) ?>'s application for <?= app_sanitize($app['store_name']) ?>? You can provide a reason — the applicant will be notified and may resubmit."
                                        data-modal-confirm="Reject Application"
                                        data-modal-payload='<?= app_sanitize(json_encode(['action' => 'reject_seller', 'application_id' => (int) $app['app_id']])) ?>'><i class="fa-solid fa-xmark"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($tab === 'orders'): ?>
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
                            <td>#<?= (int) $order['orderId'] ?></td>
                            <td><?= app_sanitize($order['customer_name']) ?></td>
                            <td><?= app_sanitize($order['seller_name']) ?></td>
                            <td><?= (int) $order['item_count'] ?></td>
                            <td>₱<?= number_format((float) $order['total_amount'], 2) ?></td>
                            <td><span class="pill <?= app_sanitize($order['status']) ?>"><?= app_sanitize(ucfirst($order['status'])) ?></span></td>
                            <td><?= app_sanitize($order['created_at']) ?></td>
                            <td>
                                <button class="status-btn" type="button" title="Update Status"
                                    data-modal-target="#orderStatusModal"
                                    data-modal-title="Update Order Status"
                                    data-modal-message="Change the status for order #<?= (int) $order['orderId'] ?>."
                                    data-modal-confirm="Save Status"
                                    data-modal-payload='<?= app_sanitize(json_encode(['action' => 'update_order_status', 'order_id' => (int) $order['orderId'], 'status' => $order['status']])) ?>'><i class="fa-solid fa-pen-to-square"></i></button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($tab === 'products'): ?>
    <div class="table-card">
        <div class="table-card-header"><h5>All Products <span class="badge-count"><?= count($adminProducts) ?></span></h5></div>
        <div class="table-card-body table-responsive">
            <?php if (!$adminProducts): ?>
                <div class="p-4">No products available.</div>
            <?php else: ?>
                <table id="productsTable" class="table table-sm w-100 mb-0">
                    <thead><tr><th>ID</th><th>Name</th><th>Seller</th><th>Brand</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($adminProducts as $product): ?>
                        <tr>
                            <td>#<?= (int) $product['productId'] ?></td>
                            <td><?= app_sanitize($product['name']) ?></td>
                            <td><?= app_sanitize($product['seller_name']) ?></td>
                            <td><?= app_sanitize($product['brand']) ?></td>
                            <td><?= app_sanitize($product['category']) ?></td>
                            <td>₱<?= number_format((float) $product['price'], 2) ?></td>
                            <td><?= (int) $product['stock'] ?></td>
                            <td><span class="pill <?= (int) $product['is_active'] ? 'completed' : 'cancelled' ?>"><?= (int) $product['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                            <td>
                                <div class="action-stack">
                                    <?php if ((int) $product['is_active']): ?>
                                        <button class="reject-btn" type="button" title="Hide"
                                            data-modal-target="#hideProductModal"
                                            data-modal-title="Hide Product"
                                            data-modal-message="Hide <?= app_sanitize($product['name']) ?>? This will remove it from the store. Provide a reason."
                                            data-modal-confirm="Hide Product"
                                            data-modal-payload='<?= app_sanitize(json_encode(['action' => 'hide_product', 'product_id' => (int) $product['productId'], 'hide' => '1'])) ?>'><i class="fa-solid fa-eye-slash"></i></button>
                                    <?php else: ?>
                                        <button class="approve-btn" type="button" title="Unhide"
                                            data-modal-target="#hideProductModal"
                                            data-modal-title="Unhide Product"
                                            data-modal-message="Unhide <?= app_sanitize($product['name']) ?>? This will make it visible in the store again."
                                            data-modal-confirm="Unhide Product"
                                            data-modal-payload='<?= app_sanitize(json_encode(['action' => 'hide_product', 'product_id' => (int) $product['productId'], 'hide' => '0'])) ?>'><i class="fa-solid fa-eye"></i></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

<?php elseif ($tab === 'notifications'): ?>
    <?php include __DIR__ . '/tab_notifications.php'; ?>

<?php elseif ($tab === 'activity_logs'): ?>
    <?php include __DIR__ . '/tab_activity_logs.php'; ?>

<?php else: ?>
    <div class="panel-card"><h4>General</h4><p class="mb-0">Admin account tools can be extended here.</p></div>
<?php endif; ?>