<?php
header('Content-Type: application/json');

require_once __DIR__ . '/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$action = $_POST['action'] ?? 'add';
$productId = (int) ($_POST['product_id'] ?? 0);
$quantity = max(1, (int) ($_POST['quantity'] ?? 1));

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product.']);
    exit;
}

$conn = app_db();
$stmt = $conn->prepare('SELECT id, stock FROM products WHERE id = ? AND is_active = 1 LIMIT 1');
$stmt->bind_param('i', $productId);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found.']);
    exit;
}

$currentQty = (int) ($_SESSION['cart'][$productId] ?? 0);

if ($action === 'update') {
    if ($quantity > (int) $product['stock']) {
        echo json_encode(['success' => false, 'message' => 'Requested quantity exceeds stock.']);
        exit;
    }
    $_SESSION['cart'][$productId] = $quantity;
} elseif ($action === 'remove') {
    unset($_SESSION['cart'][$productId]);
} else {
    $newQty = $currentQty + $quantity;
    if ($newQty > (int) $product['stock']) {
        echo json_encode(['success' => false, 'message' => 'Requested quantity exceeds stock.']);
        exit;
    }
    $_SESSION['cart'][$productId] = $newQty;
}

echo json_encode([
    'success' => true,
    'message' => $action === 'remove' ? 'Item removed from cart.' : 'Cart updated.',
    'cartCount' => app_cart_count(),
]);
