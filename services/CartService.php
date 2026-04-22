<?php
class CartService
{
    private mysqli $conn;

    public function __construct(mysqli $conn)
    {
        $this->conn = $conn;
    }

    /** Get cart items with product details */
    public function getItems(int $userId = 0): array
    {
        // If userId provided, try DB-first, fall back to session
        if ($userId > 0) {
            $items = $this->getCartFromDB($userId);
            if ($items) {
                return $items;
            }
        }
        // Default: session cart
        return $this->getCartFromSession();
    }

    /** Get raw session cart array [productId => quantity] */
    private function getCartFromSession(): array
    {
        return $_SESSION['cart'] ?? [];
    }

    /** Get session cart with product details enriched */
    private function getCartFromSessionEnriched(): array
    {
        $cart  = $this->getCartFromSession();
        if (!$cart) {
            return [];
        }
        $productIds = array_keys($cart);
        return $this->enrichWithProductData($productIds, $cart);
    }

    /** Get persistent cart from DB for user */
    private function getCartFromDB(int $userId): array
    {
        $stmt = $this->conn->prepare(
            'SELECT product_id, quantity FROM cart WHERE user_id = ? AND active = 1'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $rows  = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (!$rows) {
            return [];
        }

        $cart         = [];
        $productIds   = [];
        foreach ($rows as $row) {
            $pid           = (int) $row['product_id'];
            $cart[$pid]    = (int) $row['quantity'];
            $productIds[]  = $pid;
        }

        return $this->enrichWithProductData($productIds, $cart);
    }

    /** Merge session cart to DB cart on login */
    public function mergeToUser(int $userId): void
    {
        $sessionCart = $this->getCartFromSession();
        if (!$sessionCart) {
            return;
        }

        $stmt = $this->conn->prepare(
            'INSERT INTO cart (user_id, product_id, quantity, updated_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), updated_at = NOW()'
        );

        foreach ($sessionCart as $productId => $qty) {
            $stmt->bind_param('iii', $userId, $productId, $qty);
            $stmt->execute();
        }
        $stmt->close();
    }

    /** Enrich raw product IDs with product data */
    private function enrichWithProductData(array $productIds, array $quantityMap): array
    {
        if (empty($productIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $types        = str_repeat('i', count($productIds));

        $stmt = $this->conn->prepare("
            SELECT p.productId, p.name, p.description, p.price, p.stock, p.icon_class,
                   b.name AS brand, c.name AS category
            FROM   products    p
            LEFT JOIN brands     b ON b.brandId    = p.brandId
            LEFT JOIN categories c ON c.categoryId = p.categoryId
            WHERE  p.productId IN ($placeholders)
              AND  p.is_active = 1
        ");
        $stmt->bind_param($types, ...$productIds);
        $stmt->execute();
        $result = $stmt->get_result();

        $items = [];
        while ($row = $result->fetch_assoc()) {
            $qty               = max(1, (int) ($quantityMap[$row['productId']] ?? 1));
            $row['quantity']   = $qty;
            $row['line_total'] = $qty * (float) $row['price'];
            $items[]           = $row;
        }
        $stmt->close();

        usort($items, fn($a, $b) => $a['productId'] <=> $b['productId']);
        return $items;
    }

    /** Add item to cart (session) */
    public function add(int $productId, int $quantity = 1, int $userId = 0): bool
    {
        $cart = $this->getCartFromSession();
        $cart[$productId] = max(0, ($cart[$productId] ?? 0) + $quantity);

        if ($cart[$productId] <= 0) {
            unset($cart[$productId]);
        }

        $_SESSION['cart'] = $cart;

        if ($userId > 0) {
            $this->syncToDB($userId);
        }
        return true;
    }

    /** Remove item from cart (session) */
    public function remove(int $productId, int $userId = 0): bool
    {
        $cart = $this->getCartFromSession();
        unset($cart[$productId]);
        $_SESSION['cart'] = $cart;

        if ($userId > 0) {
            $stmt = $this->conn->prepare('DELETE FROM cart WHERE user_id = ? AND product_id = ?');
            $stmt->bind_param('ii', $userId, $productId);
            $stmt->execute();
            $stmt->close();
        }
        return true;
    }

    /** Update quantity (session) */
    public function update(int $productId, int $quantity, int $userId = 0): bool
    {
        $cart = $this->getCartFromSession();
        if ($quantity <= 0) {
            unset($cart[$productId]);
        } else {
            $cart[$productId] = $quantity;
        }
        $_SESSION['cart'] = $cart;

        if ($userId > 0) {
            $this->syncToDB($userId);
        }
        return true;
    }

    /** Clear cart entirely */
    public function clear(int $userId = 0): void
    {
        $_SESSION['cart'] = [];
        if ($userId > 0) {
            $stmt = $this->conn->prepare('DELETE FROM cart WHERE user_id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    /** Get cart total from enriched items */
    public function getTotal(): float
    {
        $total = 0.0;
        foreach ($this->getItems() as $item) {
            $total += (float) $item['line_total'];
        }
        return $total;
    }

    /** Get item count (sum of quantities) */
    public function getCount(): int
    {
        return array_sum(array_map('intval', $this->getCartFromSession()));
    }

    /** Persist session cart to DB for user */
    private function syncToDB(int $userId): void
    {
        $sessionCart = $this->getCartFromSession();
        if (!$sessionCart) {
            $stmt = $this->conn->prepare('DELETE FROM cart WHERE user_id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $stmt->close();
            return;
        }

        $stmt = $this->conn->prepare(
            'INSERT INTO cart (user_id, product_id, quantity, updated_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), updated_at = NOW()'
        );

        foreach ($sessionCart as $productId => $qty) {
            $stmt->bind_param('iii', $userId, $productId, $qty);
            $stmt->execute();
        }
        $stmt->close();
    }
}
