<?php
require_once __DIR__ . '/app.php';

$pageTitle = 'Cart - ProTech';
$flash = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'checkout') {
    $user = app_require_login();
    $result = app_checkout((int) $user['id']);
    $flash = [
        'type' => $result['success'] ? 'success' : 'danger',
        'message' => $result['success']
            ? 'Checkout complete. Your orders were placed successfully.'
            : ($result['message'] ?? 'Checkout failed.'),
    ];
}

$items = app_cart_items();
$subtotal = app_cart_total();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/header.php'; ?>
    <style>
        .cart-shell { padding: 7rem 0 4rem; min-height: 100vh; }
        .cart-card, .cart-summary {
            background: #111;
            border: 1px solid #222;
            border-radius: 18px;
            padding: 1.25rem;
        }
        .cart-item + .cart-item { border-top: 1px solid #222; margin-top: 1rem; padding-top: 1rem; }
        .qty-input {
            width: 84px;
            background: #0d0d0d;
            border: 1px solid #222;
            border-radius: 10px;
            color: var(--text-primary);
            padding: 0.55rem 0.65rem;
        }
        .action-btn {
            border: 1px solid #333;
            background: transparent;
            color: var(--text-secondary);
            border-radius: 10px;
            padding: 0.55rem 0.85rem;
        }
        .action-btn.primary { background: var(--primary); border-color: var(--primary); color: #fff; }
        .empty-cart {
            text-align: center;
            padding: 4rem 1.5rem;
            background: #111;
            border: 1px dashed #333;
            border-radius: 18px;
        }
        .flash {
            border-radius: 14px;
            padding: 0.95rem 1rem;
            margin-bottom: 1rem;
        }
        .flash.success { background: rgba(16,185,129,.12); color: #7cf2bf; }
        .flash.danger { background: rgba(239,68,68,.12); color: #ffaaaa; }
    </style>
</head>
<body>
<?php include __DIR__ . '/navbar.php'; ?>

<section class="cart-shell">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
            <div>
                <div class="section-label"><i class="fa-solid fa-cart-shopping"></i> Shopping Cart</div>
                <h1 class="section-title mb-0">Your Cart</h1>
            </div>
            <a href="product.php" class="nav-cta">Continue Shopping</a>
        </div>

        <?php if ($flash): ?>
            <div class="flash <?= app_sanitize($flash['type']) ?>"><?= app_sanitize($flash['message']) ?></div>
        <?php endif; ?>

        <?php if (!$items): ?>
            <div class="empty-cart">
                <h3 class="text-white">Your cart is empty</h3>
                <p class="text-secondary mb-4">Add products from the catalog to start building your order.</p>
                <a href="product.php" class="nav-cta">Browse Products</a>
            </div>
        <?php else: ?>
            <div class="row g-4">
                <div class="col-lg-8">
                    <div class="cart-card">
                        <?php foreach ($items as $item): ?>
                            <div class="cart-item d-flex justify-content-between gap-3 flex-wrap" data-product-id="<?= (int) $item['id'] ?>">
                                <div>
                                    <div class="text-muted small">#<?= (int) $item['id'] ?> • <?= app_sanitize($item['brand']) ?></div>
                                    <h5 class="text-white mb-1"><?= app_sanitize($item['name']) ?></h5>
                                    <div class="text-secondary"><?= app_sanitize($item['category']) ?></div>
                                    <div class="text-secondary small mt-2">$<?= number_format((float) $item['price'], 2) ?> each</div>
                                </div>
                                <div class="text-end">
                                    <input class="qty-input mb-2 item-qty" type="number" min="1" max="<?= (int) $item['stock'] ?>" value="<?= (int) $item['quantity'] ?>">
                                    <div class="d-flex gap-2 justify-content-end">
                                        <button class="action-btn update-item">Update</button>
                                        <button class="action-btn remove-item">Remove</button>
                                    </div>
                                    <div class="text-white fw-bold mt-3">$<?= number_format((float) $item['line_total'], 2) ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="cart-summary">
                        <h4 class="text-white mb-3">Summary</h4>
                        <div class="d-flex justify-content-between mb-2"><span>Items</span><span><?= app_cart_count() ?></span></div>
                        <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><span>$<?= number_format($subtotal, 2) ?></span></div>
                        <div class="d-flex justify-content-between mb-4"><span>Shipping</span><span>Calculated later</span></div>
                        <div class="d-flex justify-content-between fs-5 text-white mb-4"><strong>Total</strong><strong>$<?= number_format($subtotal, 2) ?></strong></div>
                        <?php if (app_current_user()): ?>
                            <form method="post">
                                <input type="hidden" name="action" value="checkout">
                                <button class="action-btn primary w-100" type="submit">Checkout Now</button>
                            </form>
                        <?php else: ?>
                            <a href="login.php" class="action-btn primary w-100 d-inline-flex justify-content-center text-decoration-none">Sign In to Checkout</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/footer.php'; ?>
<script>
(() => {
    async function cartAction(action, productId, quantity = 1) {
        const payload = new FormData();
        payload.append('action', action);
        payload.append('product_id', productId);
        payload.append('quantity', quantity);
        const res = await fetch('cart_action.php', { method: 'POST', body: payload });
        const data = await res.json();
        if (!data.success) {
            alert(data.message || 'Unable to update cart.');
            return;
        }
        window.location.reload();
    }

    document.querySelectorAll('.cart-item').forEach(item => {
        const productId = item.dataset.productId;
        const qtyInput = item.querySelector('.item-qty');
        item.querySelector('.update-item')?.addEventListener('click', () => cartAction('update', productId, qtyInput.value));
        item.querySelector('.remove-item')?.addEventListener('click', () => cartAction('remove', productId, 1));
    });
})();
</script>
</body>
</html>
