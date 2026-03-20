<?php
require_once __DIR__ . '/app.php';

$pageTitle = 'Products - ProTech';
$pageCss  = ['product.css'];
$conn = app_db();

$products = [];
$brands = [];
$result = $conn->query("
    SELECT p.id, p.name, p.description, p.price, p.stock, p.icon_class,
           b.name AS brand,
           c.name AS category
    FROM products p
    LEFT JOIN brands b     ON b.id = p.brand_id
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE p.is_active = 1
    ORDER BY p.created_at DESC, p.id DESC
");

while ($row = $result->fetch_assoc()) {
    $products[] = $row;
    $brands[$row['brand']] = true;
}

$brandList = array_keys($brands);
sort($brandList, SORT_NATURAL | SORT_FLAG_CASE);

$priceRanges = [
    ['label' => 'Under $100', 'min' => 0, 'max' => 99.99, 'key' => 'under-100'],
    ['label' => '$100 - $500', 'min' => 100, 'max' => 500, 'key' => '100-500'],
    ['label' => '$500 - $1,000', 'min' => 500, 'max' => 1000, 'key' => '500-1000'],
    ['label' => 'Above $1,000', 'min' => 1000.01, 'max' => 1000000, 'key' => 'above-1000'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
</head>
<body>
<?php include __DIR__ . '/includes/navbar.php'; ?>

<div class="product-listing-header">
    <div class="container">
        <div class="section-label"><i class="fa-solid fa-box-open"></i> Our Catalog</div>
        <h1 class="section-title">Products</h1>
        <p class="section-desc mx-auto">Explore our curated selection of premium technology products.</p>
    </div>
</div>

<section class="products-section pb-5">
    <div class="container">
        <div class="product-layout">
            <aside class="filter-sidebar">
                <div class="filter-group">
                    <h5>Product Brand</h5>
                    <?php foreach ($brandList as $brand): ?>
                        <label class="filter-check">
                            <input type="checkbox" class="brand-filter" value="<?= app_sanitize($brand) ?>">
                            <span><?= app_sanitize($brand) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
                <div class="filter-group">
                    <h5>Product Pricing</h5>
                    <?php foreach ($priceRanges as $range): ?>
                        <label class="filter-check">
                            <input type="checkbox" class="price-filter" data-min="<?= $range['min'] ?>" data-max="<?= $range['max'] ?>" value="<?= app_sanitize($range['key']) ?>">
                            <span><?= app_sanitize($range['label']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </aside>

            <div>
                <div class="product-topbar">
                    <input type="text" class="search-input-wide" id="productSearch" placeholder="Search products by name, brand, or category">
                    <div class="result-count"><span id="productCount"><?= count($products) ?></span> products</div>
                </div>

                <div class="row g-4" id="productGrid">
                    <?php foreach ($products as $product): ?>
                        <div
                            class="col-md-6 col-xl-4 product-item"
                            data-product-id="<?= (int) $product['id'] ?>"
                            data-brand="<?= app_sanitize(strtolower($product['brand'])) ?>"
                            data-price="<?= (float) $product['price'] ?>"
                            data-search="<?= app_sanitize(strtolower($product['name'] . ' ' . $product['brand'] . ' ' . $product['category'])) ?>"
                        >
                            <div class="product-card h-100">
                                <div class="card-img-top"><i class="<?= app_sanitize($product['icon_class']) ?>"></i></div>
                                <div class="card-body">
                                    <div class="card-category">#<?= (int) $product['id'] ?> • <?= app_sanitize($product['brand']) ?></div>
                                    <h5 class="card-title"><?= app_sanitize($product['name']) ?></h5>
                                    <p class="card-text"><?= app_sanitize($product['description']) ?></p>
                                    <div class="card-price">$<?= number_format((float) $product['price'], 2) ?></div>
                                    <div class="stock-note"><?= (int) $product['stock'] ?> in stock</div>
                                </div>
                                <div class="card-footer-custom">
                                    <button class="btn-card btn-card-primary add-to-cart-btn" data-product-id="<?= (int) $product['id'] ?>">Add to Cart</button>
                                    <button class="btn-card btn-card-outline" type="button">ID <?= (int) $product['id'] ?></button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
    const searchInput = document.getElementById('productSearch');
    const brandFilters = [...document.querySelectorAll('.brand-filter')];
    const priceFilters = [...document.querySelectorAll('.price-filter')];
    const items = [...document.querySelectorAll('.product-item')];
    const countEl = document.getElementById('productCount');

    function filterProducts() {
        const query = searchInput.value.trim().toLowerCase();
        const activeBrands = brandFilters.filter(input => input.checked).map(input => input.value.toLowerCase());
        const activePrices = priceFilters.filter(input => input.checked).map(input => ({
            min: Number(input.dataset.min),
            max: Number(input.dataset.max)
        }));

        let visibleCount = 0;

        items.forEach(item => {
            const brand = item.dataset.brand;
            const price = Number(item.dataset.price);
            const search = item.dataset.search;
            const matchesSearch = !query || search.includes(query);
            const matchesBrand = activeBrands.length === 0 || activeBrands.includes(brand);
            const matchesPrice = activePrices.length === 0 || activePrices.some(range => price >= range.min && price <= range.max);
            const visible = matchesSearch && matchesBrand && matchesPrice;
            item.style.display = visible ? '' : 'none';
            if (visible) visibleCount += 1;
        });

        countEl.textContent = visibleCount;
    }

    async function updateCart(productId) {
        const payload = new FormData();
        payload.append('action', 'add');
        payload.append('product_id', productId);
        payload.append('quantity', '1');

        const res = await fetch('cart_action.php', { method: 'POST', body: payload });
        const data = await res.json();

        if (!data.success) {
            alert(data.message || 'Unable to add item to cart.');
            return;
        }

        window.location.href = 'cart.php';
    }

    searchInput.addEventListener('input', filterProducts);
    brandFilters.forEach(input => input.addEventListener('change', filterProducts));
    priceFilters.forEach(input => input.addEventListener('change', filterProducts));
    document.querySelectorAll('.add-to-cart-btn').forEach(button => {
        button.addEventListener('click', () => updateCart(button.dataset.productId));
    });
})();
</script>
</body>
</html>
