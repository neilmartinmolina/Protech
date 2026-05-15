<?php
require_once __DIR__ . '/app.php';

$pageTitle = 'Cart - ProTech';
$pageCss  = ['cart.css'];
$flash    = null;

if (!app_current_user()) {
    unset($_SESSION['cart']);
    $_SESSION['login_flash'] = [
        'type' => 'info',
        'message' => 'login to add to cart',
    ];
    header('Location: login.php?cart_notice=login_to_add_cart');
    exit;
}

$items = app_cart_items();
$subtotal = app_cart_total();
?>    
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
</head>
<body>
<?php include __DIR__ . '/includes/navbar.php'; ?>

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
                         <div class="cart-header mb-3">
                             <h5>Select items to checkout:</h5>
                             <div class="form-check form-check-inline">
                                 <input class="form-check-input" type="checkbox" id="selectAllItems">
                                 <label class="form-check-label" for="selectAllItems">Select All</label>
                             </div>
                         </div>
                         <?php foreach ($items as $item): ?>
                             <div class="cart-item d-flex justify-content-between gap-3 flex-wrap" data-product-id="<?= (int) $item['productId'] ?>">
                                 <div class="form-check me-3">
                                     <input class="form-check-input item-select" type="checkbox" name="selected_items[]" value="<?= (int) $item['productId'] ?>" checked>
                                 </div>
                                 <div>
                                     <div class="text-muted small">#<?= (int) $item['productId'] ?> • <?= app_sanitize($item['brand']) ?></div>
                                     <h5 class="text-white mb-1"><?= app_sanitize($item['name']) ?></h5>
                                     <div class="text-secondary"><?= app_sanitize($item['category']) ?></div>
                                     <div class="text-secondary small mt-2">₱<?= number_format((float) $item['price'], 2) ?> each</div>
                                 </div>
                                 <div class="text-end">
                                     <input class="qty-input mb-2 item-qty" type="number" min="1" max="<?= (int) $item['stock'] ?>" value="<?= (int) $item['quantity'] ?>">
                                     <div class="d-flex gap-2 justify-content-end">
                                         <button class="action-btn update-item">Update</button>
                                         <button class="action-btn remove-item">Remove</button>
                                     </div>
                                     <div class="text-white fw-bold mt-3">₱<?= number_format((float) $item['line_total'], 2) ?></div>
                                 </div>
                             </div>
                         <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-lg-4">
<div class="cart-summary">
<h4 class="text-white mb-3">Summary</h4>
                         <div class="d-flex justify-content-between mb-2"><span>Items</span><span id="summaryItems"><?= app_cart_count() ?></span></div>
                         <div class="d-flex justify-content-between mb-2"><span>Subtotal</span><span id="summarySubtotal">₱<?= number_format($subtotal, 2) ?></span></div>
                         <div class="d-flex justify-content-between fs-5 text-white mb-4"><strong>Total</strong><strong id="summaryTotal">₱<?= number_format($subtotal, 2) ?></strong></div>
                         <?php if (app_current_user()): ?>
                             <a href="checkout.php" class="action-btn primary w-100 d-inline-flex justify-content-center text-decoration-none text-center" id="checkoutBtn">Checkout Now</a>
                         <?php else: ?>
                             <a href="login.php" class="action-btn primary w-100 d-inline-flex justify-content-center text-decoration-none">Sign In to Checkout</a>
                         <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
<?php include __DIR__ . '/includes/scripts.php'; ?>
<script <?= csp_nonce_attr() ?>>
(() => {
    async function cartAction(action, productId, quantity = 1) {
        const payload = new FormData();
        payload.append('action', action);
        payload.append('product_id', productId);
        payload.append('quantity', quantity);
        payload.append('csrf_token', <?= json_encode(app_csrf_token()) ?>);
        const res = await fetch('cart_action.php', { method: 'POST', body: payload });
        const data = await res.json();
        if (!data.success) {
            if (data.requiresLogin) {
                await Swal.fire({
                    icon: 'info',
                    title: 'Sign in required',
                    text: data.message || 'login to add to cart',
                    confirmButtonText: 'Go to Login'
                });
                window.location.assign(data.loginUrl || 'login.php?cart_notice=login_to_add_cart');
                return;
            }
            Swal.fire({
                icon: 'error',
                title: 'Cart update failed',
                text: data.message || 'Unable to update cart.',
                confirmButtonText: 'OK'
            });
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

    // Select all functionality
    const selectAllCheckbox = document.getElementById('selectAllItems');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const isChecked = this.checked;
            document.querySelectorAll('.item-select').forEach(checkbox => {
                checkbox.checked = isChecked;
            });
            updateSummary();
        });
    }

    // Individual select/deselect all when all items are selected/deselected
    document.querySelectorAll('.item-select').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const allChecked = document.querySelectorAll('.item-select').length === 
                             document.querySelectorAll('.item-select:checked').length;
            if (selectAllCheckbox) {
                selectAllCheckbox.checked = allChecked;
            }
            updateSummary();
        });
    });

    // Update summary (subtotal/total) when quantities change
    document.querySelectorAll('.item-qty').forEach(input => {
        input.addEventListener('input', function() {
            // Update line total for this item
            const cartItem = this.closest('.cart-item');
            const priceText = cartItem.querySelector('.text-secondary.small.mt-2')?.textContent || '';
            const priceMatch = priceText.match(/₱([\d,.]+)/);
            
            if (priceMatch) {
                const price = parseFloat(priceMatch[1].replace(/,/g, ''));
                const quantity = parseInt(this.value) || 1;
                const lineTotal = price * quantity;
                
                const lineTotalEl = cartItem.querySelector('.text-white.fw-bold.mt-3');
                if (lineTotalEl) {
                    lineTotalEl.textContent = `₱${lineTotal.toFixed(2)}`;
                }
            }
            
            updateSummary();
        });
    });

    function updateSummary() {
        let subtotal = 0;
        let selectedCount = 0;
        
        document.querySelectorAll('.cart-item').forEach(cartItem => {
            const checkbox = cartItem.querySelector('.item-select');
            const isChecked = checkbox ? checkbox.checked : false;
            
            if (isChecked) {
                selectedCount++;
                const qtyInput = cartItem.querySelector('.item-qty');
                const quantity = qtyInput ? parseInt(qtyInput.value) || 1 : 1;
                
                // Get price from the line total element instead
                const lineTotalEl = cartItem.querySelector('.text-white.fw-bold.mt-3');
                if (lineTotalEl) {
                    const lineText = lineTotalEl.textContent || '';
                    const lineMatch = lineText.match(/₱([\d,.]+)/);
                    if (lineMatch) {
                        const lineTotal = parseFloat(lineMatch[1].replace(/,/g, ''));
                        subtotal += lineTotal;
                    }
                }
            }
        });
        
        // Update summary elements by ID
        const itemCountEl = document.getElementById('summaryItems');
        const subtotalEl = document.getElementById('summarySubtotal');
        const totalEl = document.getElementById('summaryTotal');
        
        if (itemCountEl) itemCountEl.textContent = selectedCount;
        if (subtotalEl) subtotalEl.textContent = `₱${subtotal.toFixed(2)}`;
        if (totalEl) totalEl.textContent = `₱${subtotal.toFixed(2)}`;
    }

    // Initial summary update on page load
    document.addEventListener('DOMContentLoaded', function() {
        updateSummary();
    });
})();
</script>
</body>
</html>
