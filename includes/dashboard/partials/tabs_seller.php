<?php
/** @var string $tab */
/** @var array $allowedTabs */
/** @var array $sellerStats */
/** @var array $sellerProducts */
/** @var array $sellerOrders */
/** @var array $activityLogs */
/** @var array $notifications */
?>
<?php if ($tab === 'dashboard'): ?>
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon orange"><i class="fa-solid fa-box"></i></div><div class="stat-value"><?= $sellerStats['products'] ?></div><div class="stat-label">Store Products</div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon green"><i class="fa-solid fa-box-open"></i></div><div class="stat-value"><?= $sellerStats['active_products'] ?></div><div class="stat-label">Active Products</div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon blue"><i class="fa-solid fa-receipt"></i></div><div class="stat-value"><?= $sellerStats['orders'] ?></div><div class="stat-label">Orders</div></div></div>
        <div class="col-sm-6 col-xl-3"><div class="stat-card"><div class="stat-icon red"><i class="fa-solid fa-dollar-sign"></i></div><div class="stat-value">₱<?= number_format($sellerStats['revenue'], 2) ?></div><div class="stat-label">Revenue</div></div></div>
    </div>
    <div class="row g-4">
        <div class="col-xl-7"><div class="panel-card chart-card"><h4>Revenue in the Last 7 Days</h4><div class="chart-wrap"><canvas id="sellerRevenueChart"></canvas></div></div></div>
        <div class="col-xl-5"><div class="panel-card chart-card"><h4>Product Category Mix</h4><div class="chart-wrap"><canvas id="sellerCategoryChart"></canvas></div></div></div>
    </div>

<?php elseif ($tab === 'products'): ?>
    <div class="table-card">
        <div class="table-card-header">
            <h5>Your Products <span class="badge-count"><?= count($sellerProducts) ?></span></h5>
            <button class="add-product-btn" type="button"
                data-modal-target="#productModal"
                data-modal-title="Add Product"
                data-modal-message="Create a new product for your store."
                data-modal-confirm="Save Product"
                data-modal-payload='<?= app_sanitize(json_encode(['action' => 'save_product', 'product_id' => '', 'name' => '', 'brand' => '', 'category' => 'Laptops', 'description' => '', 'price' => '', 'stock' => '0', 'icon_class' => 'fa-solid fa-box-open', 'is_active' => '1'])) ?>'>
                Add Product
            </button>
        </div>
        <div class="table-card-body table-responsive">
            <?php if (!$sellerProducts): ?>
                <div class="p-4">No products yet. Use the Add Product button to create your first listing.</div>
            <?php else: ?>
                <table id="sellerProductsTable" class="table table-sm w-100 mb-0">
                    <thead><tr><th>ID</th><th>Name</th><th>Brand</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($sellerProducts as $product): ?>
                        <tr>
                            <td>#<?= (int) $product['productId'] ?></td>
                            <td><?= app_sanitize($product['name']) ?></td>
                            <td><?= app_sanitize($product['brand']) ?></td>
                            <td><?= app_sanitize($product['category']) ?></td>
                            <td>₱<?= number_format((float) $product['price'], 2) ?></td>
                            <td><?= (int) $product['stock'] ?></td>
                            <td><span class="pill <?= (int) $product['is_active'] ? 'completed' : 'cancelled' ?>"><?= (int) $product['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                            <td>
                                <button class="edit-btn" type="button" title="Edit"
                                    data-modal-target="#productModal"
                                    data-modal-title="Edit Product"
                                    data-modal-message="Update this product."
                                    data-modal-confirm="Save Product"
                                    data-modal-payload='<?= app_sanitize(json_encode(['action' => 'save_product', 'product_id' => (int) $product['productId'], 'name' => $product['name'], 'brand' => $product['brand'], 'category' => $product['category'], 'description' => $product['description'], 'price' => $product['price'], 'stock' => (int) $product['stock'], 'icon_class' => $product['icon_class'], 'is_active' => (int) $product['is_active']])) ?>'><i class="fa-solid fa-pencil"></i></button>
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
        <div class="table-card-header"><h5>Store Orders <span class="badge-count"><?= count($sellerOrders) ?></span></h5></div>
        <div class="table-card-body table-responsive">
            <?php if (!$sellerOrders): ?>
                <div class="p-4">No orders yet for this store.</div>
            <?php else: ?>
                <table id="sellerOrdersTable" class="table table-sm w-100 mb-0">
                    <thead><tr><th>Order</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th>Date</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($sellerOrders as $order): ?>
                        <tr>
                            <td>#<?= (int) $order['orderId'] ?></td>
                            <td><?= app_sanitize($order['customer_name']) ?></td>
                            <td><?= (int) $order['item_count'] ?></td>
                            <td>₱<?= number_format((float) $order['total_amount'], 2) ?></td>
                            <td><span class="pill <?= app_sanitize($order['status']) ?>"><?= app_sanitize(ucfirst($order['status'])) ?></span></td>
                            <td><?= app_sanitize($order['created_at']) ?></td>
                            <td>
                                <a href="report.php?order_id=<?= (int)$order['orderId'] ?>" class="status-btn" target="_blank" title="View Report"><i class="fa-solid fa-receipt"></i></a>
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

<?php elseif ($tab === 'analytics'): ?>
    <div class="row g-4">
        <div class="col-xl-7"><div class="panel-card chart-card"><h4>Revenue Trend</h4><div class="chart-wrap"><canvas id="sellerRevenueChartAnalytics"></canvas></div></div></div>
        <div class="col-xl-5"><div class="panel-card chart-card"><h4>Category Mix</h4><div class="chart-wrap"><canvas id="sellerCategoryChartAnalytics"></canvas></div></div></div>
    </div>

<?php elseif ($tab === 'notifications'): ?>
    <?php include __DIR__ . '/tab_notifications.php'; ?>

<?php elseif ($tab === 'activity_logs'): ?>
    <?php include __DIR__ . '/tab_activity_logs.php'; ?>

<?php else: ?>
    <div class="panel-card"><h4><?= app_sanitize($allowedTabs[$tab][0]) ?></h4><p class="mb-0">This section is ready for additional tools.</p></div>
<?php endif; ?>