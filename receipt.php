<?php
require_once __DIR__ . '/app.php';

$orderId = max(0, (int) ($_GET['order_id'] ?? 0));

if ($orderId <= 0) {
    http_response_code(400);
    exit('Invalid order ID.');
}

$conn = app_db();
$order = app_get_order_with_items($orderId);

if (!$order) {
    http_response_code(404);
    exit('Order not found.');
}

// Verify user owns this order, is admin/superadmin, or is a seller with items in the order
$user = app_current_user();
if (!$user) {
    header('Location: login.php');
    exit;
}

$isAdmin = app_is_admin($user);
$isOrderOwner = ($order['userId'] ?? 0) === ($user['userId'] ?? 0);

// Check if seller has items in this order
$isSellerWithItems = false;
if (app_is_seller($user)) {
    $sellerId = (int) $user['userId'];
    $checkStmt = $conn->prepare("SELECT 1 FROM order_items WHERE orderId = ? AND sellerUserId = ? LIMIT 1");
    $checkStmt->bind_param('ii', $orderId, $sellerId);
    $checkStmt->execute();
    $isSellerWithItems = $checkStmt->get_result()->num_rows > 0;
    $checkStmt->close();
}

if (!$isAdmin && !$isOrderOwner && !$isSellerWithItems) {
    http_response_code(403);
    exit('Access denied.');
}

$pageTitle = 'Receipt - Order #' . $orderId;
$pageCss = ['receipt.css']; // We can create a specific receipt style or reuse checkout

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
    <style>
        /* Receipt-specific styles */
        .receipt-container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #ff7315;
        }
        
        .receipt-title {
            color: #ff7315;
            font-size: 2.5rem;
            margin: 0;
        }
        
        .receipt-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .info-section {
            flex: 1;
        }
        
        .info-section h4 {
            color: #333;
            margin-bottom: 15px;
        }
        
        .customer-info, .order-details {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .order-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .order-table th,
        .order-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .order-table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        
        .order-table tbody tr:hover {
            background-color: #f1f1f1;
        }
        
        .total-row {
            font-weight: bold;
            font-size: 1.2rem;
            background-color: #fff3cd;
        }
        
        .footer-note {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            color: #666;
            font-style: italic;
        }
        
        .btn-action {
            display: inline-block;
            padding: 12px 24px;
            margin: 10px 5px;
            background-color: #ff7315;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 500;
            transition: background-color 0.3s;
        }
        
        .btn-action:hover {
            background-color: #e66813;
        }
        
        .btn-secondary {
            background-color: #6c757d;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        @media print {
            body {
                background: white;
            }
            .navbar, .footer, .btn-action, .no-print {
                display: none !important;
            }
            .receipt-container {
                box-shadow: none;
                margin: 0;
                padding: 20px;
                max-width: 100%;
                background: white;
            }
        }

        @media (max-width: 768px) {
            .receipt-info {
                flex-direction: column;
                gap: 20px;
            }
            
            .receipt-container {
                margin: 20px;
                padding: 15px;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="receipt-container">
    <div class="receipt-header">
        <h1 class="receipt-title">ProTech Receipt</h1>
        <p>Order #<?= (int) $order['orderId'] ?></p>
    </div>
    
    <div class="receipt-info">
        <div class="info-section">
            <h4>Customer Information</h4>
            <p><strong>Name:</strong> <?= app_sanitize($order['first_name'] . ' ' . $order['last_name']) ?></p>
            <p><strong>Phone:</strong> <?= app_sanitize($order['phone'] ?? 'Not provided') ?></p>
        </div>
        
        <div class="info-section">
            <h4>Order Details</h4>
            <p><strong>Date:</strong> <?= date('F j, Y, g:i A', strtotime($order['created_at'])) ?></p>
            <p><strong>Payment Method:</strong> <?= app_sanitize($order['payment_method'] ?? 'Not specified') ?></p>
        </div>
    </div>
    
    <div class="customer-info">
        <h4>Shipping Address</h4>
        <p><?= nl2br(app_sanitize($order['shipping_address'] ?? 'Not provided')) ?></p>
    </div>
    
    <div class="order-details">
        <h4>Order Items</h4>
        <table class="order-table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Unit Price</th>
                    <th>Total</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $grandTotal = 0;
                foreach ($order['items'] as $item): ?>
                    <tr>
                        <td><?= app_sanitize($item['product_name']) ?></td>
                        <td><?= (int) $item['quantity'] ?></td>
                        <td>₱<?= number_format((float) $item['unit_price'], 2) ?></td>
                        <td>₱<?= number_format((float) $item['unit_price'] * (int) $item['quantity'], 2) ?></td>
                    </tr>
                    <?php
                    $grandTotal += (float) $item['unit_price'] * (int) $item['quantity'];
                endforeach; ?>
            </tbody>
            <tfoot>
                <tr class="total-row">
                    <td colspan="3">Subtotal:</td>
                    <td>₱<?= number_format($grandTotal, 2) ?></td>
                </tr>
                <?php if (!empty($order['tax'])): ?>
                <tr>
                    <td colspan="3">Tax:</td>
                    <td>₱<?= number_format((float) $order['tax'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($order['shipping_cost'])): ?>
                <tr>
                    <td colspan="3">Shipping:</td>
                    <td>₱<?= number_format((float) $order['shipping_cost'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="total-row">
                    <td colspan="3">Amount Due:</td>
                    <td>₱<?= number_format((float) $order['total_amount'], 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
    
    <div class="footer-note">
        <p>Thank you for shopping with ProTech!</p>
        <p>This receipt is for your records. Please save it for future reference.</p>
        <p><small>Order placed on <?= date('F j, Y, g:i A', strtotime($order['created_at'])) ?></small></p>
    </div>
    
    <div class="text-center mt-4">
        <button onclick="window.print()" class="btn-action no-print">Print Receipt</button>
        <a href="product.php" class="btn-action">Continue Shopping</a>
        <a href="profile.php?section=orders" class="btn-action btn-secondary">View Order History</a>
        <?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/Protech/receipt_' . $orderId . '.pdf')): ?>
            <a href="download_receipt.php?order_id=<?= $orderId ?>" class="btn-action">Download PDF Receipt</a>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
<?php include __DIR__ . '/includes/scripts.php'; ?>
</body>
</html>