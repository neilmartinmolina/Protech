<?php
require_once __DIR__ . '/app.php';

$user = app_require_login();
$conn = app_db();

$items = app_cart_items();
$subtotal = app_cart_total();

$flash = null;

// Fetch user addresses
$addresses = [];
$result = $conn->query("SELECT * FROM user_addresses WHERE userId = " . (int)$user['userId'] . " ORDER BY is_default DESC, created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $addresses[] = $row;
    }
}

// Fetch user payment methods
$paymentMethods = [];
$result = $conn->query("SELECT * FROM user_payment_methods WHERE userId = " . (int)$user['userId'] . " ORDER BY is_default DESC, created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $paymentMethods[] = $row;
    }
}

// Handle checkout form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedAddressId = (int)($_POST['address_id'] ?? 0);
    $selectedPaymentId = (int)($_POST['payment_id'] ?? 0);
    
    if ($selectedAddressId <= 0 || $selectedPaymentId <= 0) {
        $flash = ['type' => 'danger', 'message' => 'Please select a shipping address and payment method.'];
    } else {
        // Get address details
        $addrStmt = $conn->prepare("SELECT * FROM user_addresses WHERE userAddressId = ? AND userId = ? LIMIT 1");
        $addrStmt->bind_param('ii', $selectedAddressId, $user['userId']);
        $addrStmt->execute();
        $address = $addrStmt->get_result()->fetch_assoc();
        $addrStmt->close();
        
        // Get payment method details
        $payStmt = $conn->prepare("SELECT * FROM user_payment_methods WHERE userPaymentMethodId = ? AND userId = ? LIMIT 1");
        $payStmt->bind_param('ii', $selectedPaymentId, $user['userId']);
        $payStmt->execute();
        $paymentMethod = $payStmt->get_result()->fetch_assoc();
        $payStmt->close();
        
        if ($address && $paymentMethod) {
            // Store checkout details in session
            $_SESSION['checkout_address_id'] = $selectedAddressId;
            $_SESSION['checkout_payment_id'] = $selectedPaymentId;
            $_SESSION['checkout_address'] = $address['street'] . ', ' . $address['barangay'] . ', ' . $address['city'] . ', ' . $address['province'];
            $_SESSION['checkout_phone'] = $address['phone'];
            $_SESSION['checkout_payment_method'] = $paymentMethod['type'];
            
            // Process checkout
            $result = app_checkout((int)$user['userId']);
            
            if ($result['success']) {
                // Redirect to confirmation
                header('Location: checkout.php?confirmed=1');
                exit;
            } else {
                $flash = ['type' => 'danger', 'message' => $result['message'] ?? 'Checkout failed.'];
            }
        } else {
            $flash = ['type' => 'danger', 'message' => 'Invalid address or payment method.'];
        }
    }
}

// Check if this is a confirmed checkout (show order confirmation)
$showConfirmation = isset($_GET['confirmed']) && $_GET['confirmed'] == '1';
$orderIds = $_SESSION['checkout_order_ids'] ?? [];
unset($_SESSION['checkout_order_ids']);

if ($showConfirmation && !empty($orderIds)) {
    $ordersWithItems = [];
    foreach ($orderIds as $orderId) {
        $order = app_get_order_with_items($orderId);
        if ($order) {
            $ordersWithItems[] = $order;
        }
    }
}

$conn->close();

$pageTitle = 'Checkout - ProTech';
$pageCss = ['checkout.css'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
</head>
<body>
<?php include __DIR__ . '/includes/navbar.php'; ?>

<section class="checkout-shell py-5">
    <div class="container">
        <?php if ($flash): ?>
            <div class="flash <?= app_sanitize($flash['type']) ?>"><?= app_sanitize($flash['message']) ?></div>
        <?php endif; ?>
        
        <?php if ($showConfirmation && !empty($orderIds)): ?>
            <!-- Order Confirmation -->
            <div class="section-label"><i class="fa-solid fa-check-circle"></i> Order Confirmed</div>
            <h1 class="section-title">Thank You for Your Purchase!</h1>
            <p class="section-desc mx-auto mb-4">Your order has been successfully placed.</p>
            
            <?php foreach ($ordersWithItems as $order): ?>
                <div class="order-card mb-4">
                    <div class="order-header">
                        <h3>Order #<?= (int) $order['orderId'] ?></h3>
                        <div class="order-date"><?= date('F j, Y, g:i A', strtotime($order['created_at'])) ?></div>
                        <span class="status-pill status-<?= strtolower($order['status']) ?>"><?= ucfirst($order['status']) ?></span>
                    </div>
                    <div class="order-details">
                        <table class="table table-sm">
                            <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
                            <tbody>
                                <?php foreach ($order['items'] as $item): ?>
                                    <tr>
                                        <td><?= app_sanitize($item['product_name']) ?></td>
                                        <td><?= (int) $item['quantity'] ?></td>
                                        <td>₱<?= number_format((float) $item['unit_price'], 2) ?></td>
                                        <td>₱<?= number_format((float) $item['unit_price'] * (int) $item['quantity'], 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                    <td><strong>₱<?= number_format((float) $order['total_amount'], 2) ?></strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <div class="text-center">
                <a href="product.php" class="action-btn primary">Continue Shopping</a>
                <a href="profile.php?section=orders" class="action-btn secondary">View Orders</a>
            </div>
            
        <?php elseif (!$items): ?>
            <!-- Empty Cart -->
            <h2>Your cart is empty</h2>
            <a href="product.php" class="nav-cta">Browse Products</a>
            
        <?php else: ?>
            <!-- Checkout Form -->
            <div class="section-label"><i class="fa-solid fa-credit-card"></i> Checkout</div>
            <h1 class="section-title">Complete Your Order</h1>
            
            <form method="post" class="checkout-form">
                <div class="row g-4">
                    <!-- Shipping Address -->
                    <div class="col-md-6">
                        <div class="checkout-card">
                            <h4><i class="fa-solid fa-location-dot"></i> Shipping Address</h4>
                            
                            <?php if (empty($addresses)): ?>
                                <p class="text-secondary">No addresses saved.</p>
                                <a href="profile.php?section=settings" class="btn btn-sm btn-outline">Add Address</a>
                            <?php else: ?>
                                <?php foreach ($addresses as $addr): ?>
                                    <div class="address-option">
                                        <input type="radio" name="address_id" value="<?= (int) $addr['userAddressId'] ?>" 
                                               id="addr_<?= (int) $addr['userAddressId'] ?>" 
                                               <?= $addr['is_default'] ? 'checked' : '' ?>>
                                        <label for="addr_<?= (int) $addr['userAddressId'] ?>">
                                            <strong><?= app_sanitize($addr['label'] ?? 'Address') ?></strong>
                                            <?php if ($addr['is_default']): ?> <span class="badge bg-primary">Default</span><?php endif; ?>
                                            <p class="mb-0 text-secondary">
                                                <?= app_sanitize($addr['recipient_name']) ?> • <?= app_sanitize($addr['phone']) ?><br>
                                                <?= app_sanitize($addr['street']) ?>, <?= app_sanitize($addr['barangay']) ?>, <?= app_sanitize($addr['city']) ?>
                                            </p>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Payment Method -->
                    <div class="col-md-6">
                        <div class="checkout-card">
                            <h4><i class="fa-solid fa-credit-card"></i> Payment Method</h4>
                            
                            <?php if (empty($paymentMethods)): ?>
                                <p class="text-secondary">No payment methods saved.</p>
                                <a href="profile.php?section=settings" class="btn btn-sm btn-outline">Add Payment Method</a>
                            <?php else: ?>
                                <?php foreach ($paymentMethods as $pm): ?>
                                    <div class="payment-option">
                                        <input type="radio" name="payment_id" value="<?= (int) $pm['userPaymentMethodId'] ?>" 
                                               id="pm_<?= (int) $pm['userPaymentMethodId'] ?>" 
                                               <?= $pm['is_default'] ? 'checked' : '' ?>>
                                        <label for="pm_<?= (int) $pm['userPaymentMethodId'] ?>">
                                            <strong><?= ucfirst($pm['type']) ?></strong>
                                            <?php if ($pm['is_default']): ?> <span class="badge bg-primary">Default</span><?php endif; ?>
                                            <p class="mb-0 text-secondary">
                                                <?php if ($pm['type'] === 'gcash'): ?>
                                                    GCash: <?= app_sanitize($pm['gcash_name'] ?? '') ?> (<?= app_sanitize($pm['gcash_number'] ?? '') ?>)
                                                <?php elseif ($pm['type'] === 'bank'): ?>
                                                    <?= app_sanitize($pm['bank_name'] ?? '') ?>: <?= app_sanitize($pm['account_number'] ?? '') ?>
                                                <?php else: ?>
                                                    Cash on Delivery
                                                <?php endif; ?>
                                            </p>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="col-12">
                        <div class="checkout-card">
                            <h4><i class="fa-solid fa-bag-shopping"></i> Order Summary</h4>
                            <table class="table">
                                <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th></tr></thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td><?= app_sanitize($item['name']) ?></td>
                                            <td><?= (int) $item['quantity'] ?></td>
                                            <td>₱<?= number_format((float) $item['price'], 2) ?></td>
                                            <td>₱<?= number_format((float) $item['line_total'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                        <td><strong>₱<?= number_format($subtotal, 2) ?></strong></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Place Order Button -->
                    <div class="col-12 text-center">
                        <button type="submit" class="action-btn primary w-50">Place Order</button>
                    </div>
                </div>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
<?php include __DIR__ . '/includes/scripts.php'; ?>
<script>
document.querySelector('.checkout-form')?.addEventListener('submit', function(event) {
    const addressSelected = document.querySelector('input[name="address_id"]:checked');
    const paymentSelected = document.querySelector('input[name="payment_id"]:checked');
    
    if (!addressSelected || !paymentSelected) {
        event.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Missing Information',
            text: 'Please select both a shipping address and payment method.',
            confirmButtonText: 'OK'
        });
    }
});
</script>
</body>
</html>