<?php
class OrderService
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /**
     * Create order(s) from cart items
     * @return array {success: bool, message: string, order_ids: array|null}
     */
    public function checkout(int $userId, array $sessionCheckoutData): array
    {
        $items = app_cart_items(); // uses session cart

        if (!$items) {
            return ['success' => false, 'message' => 'Your cart is empty.'];
        }

        $this->conn->begin_transaction();

        try {
            $productIds = array_map(fn($i) => (int)$i['productId'], $items);
            if (empty($productIds)) {
                throw new RuntimeException('Empty cart.');
            }

            // Lock products
            $placeholders = implode(',', array_fill(0, count($productIds), '?'));
            $types        = str_repeat('i', count($productIds));

            $stmt = $this->conn->prepare("
                SELECT productId, sellerUserId, stock, price, is_active, name
                FROM products
                WHERE productId IN ($placeholders)
                FOR UPDATE
            ");
            $stmt->bind_param($types, ...$productIds);
            $stmt->execute();
            $result     = $stmt->get_result();
            $productMap = [];
            while ($row = $result->fetch_assoc()) {
                $productMap[(int)$row['productId']] = $row;
            }
            $stmt->close();

            // Group items by seller
            $grouped = [];
            foreach ($items as $item) {
                $product = $productMap[(int)$item['productId']] ?? null;
                if (!$product || !(int)$product['is_active']) {
                    throw new RuntimeException('Product no longer available: ' . $item['name']);
                }
                if ((int)$product['stock'] < (int)$item['quantity']) {
                    throw new RuntimeException('Insufficient stock for ' . $product['name']);
                }

                $sellerKey = $product['sellerUserId']
                    ? (string)(int)$product['sellerUserId']
                    : 'marketplace';

                $grouped[$sellerKey] ??= [
                    'sellerUserId' => $product['sellerUserId'] ? (int)$product['sellerUserId'] : null,
                    'items'        => [],
                    'total'        => 0.0,
                ];

                $grouped[$sellerKey]['items'][] = [
                    'productId'  => (int)$product['productId'],
                    'quantity'   => (int)$item['quantity'],
                    'unit_price' => (float)$product['price'],
                    'name'       => $product['name'],
                ];
                $grouped[$sellerKey]['total'] += (float)$product['price'] * (int)$item['quantity'];
            }

            // Insert orders
            $orderStmt = $this->conn->prepare(
                'INSERT INTO orders (userId, total_amount, status, phone, shipping_address, tax, shipping_cost, payment_method)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $itemStmt  = $this->conn->prepare(
                'INSERT INTO order_items (orderId, productId, sellerUserId, product_name, quantity, unit_price)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stockStmt = $this->conn->prepare('UPDATE products SET stock = stock - ? WHERE productId = ?');

            $phone           = $_SESSION['checkout_phone']           ?? '';
            $shippingAddress = $sessionCheckoutData['address']        ?? '';
            $tax             = (float)($sessionCheckoutData['tax']    ?? 0);
            $shippingCost    = (float)($sessionCheckoutData['shipping'] ?? 0);
            $paymentMethod   = $sessionCheckoutData['payment']        ?? 'cod';
            $status          = 'placed';

            $createdOrderIds = [];

            foreach ($grouped as $group) {
                $orderStmt->bind_param('idsssdds',
                    $userId, $group['total'], $status, $phone,
                    $shippingAddress, $tax, $shippingCost, $paymentMethod
                );
                $orderStmt->execute();
                $orderId = (int)$this->conn->insert_id;
                $createdOrderIds[] = $orderId;

                foreach ($group['items'] as $item) {
                    $itemStmt->bind_param(
                        'iiisid',
                        $orderId,
                        $item['productId'],
                        $group['sellerUserId'],
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

            $this->conn->commit();

            // Store order IDs in session for confirmation
            $_SESSION['checkout_order_ids'] = $createdOrderIds;
            unset($_SESSION['cart']); // clear cart

            return ['success' => true, 'message' => 'Order placed.', 'order_ids' => $createdOrderIds];

        } catch (Throwable $e) {
            $this->conn->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /** Get order + items by ID */
    public function getOrderWithItems(int $orderId): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT o.*, u.first_name, u.last_name, u.email
            FROM orders o JOIN users u ON u.userId = o.userId
            WHERE o.orderId = ? LIMIT 1
        ");
        $stmt->bind_param('i', $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) {
            return null;
        }

        $itemStmt = $this->conn->prepare('SELECT product_name, quantity, unit_price FROM order_items WHERE orderId = ?');
        $itemStmt->bind_param('i', $orderId);
        $itemStmt->execute();
        $order['items'] = $itemStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $itemStmt->close();

        return $order;
    }

    /** Get orders for customer */
    public function getCustomerOrders(int $userId): array
    {
        $stmt = $this->conn->prepare("
            SELECT o.orderId, o.total_amount, o.status, o.created_at,
                   COUNT(oi.orderItemId) AS item_count
            FROM orders o
            LEFT JOIN order_items oi ON oi.orderId = o.orderId
            WHERE o.userId = ?
            GROUP BY o.orderId
            ORDER BY o.created_at DESC
        ");
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $orders;
    }
}
