<?php
require_once __DIR__ . '/app.php';

$pageTitle = 'Products - ProTech';
$pageCss   = ['product.css'];
$conn      = app_db();

// ── Active filters from GET ──────────────────────────────────────────────────
$filterBrands = array_values(array_filter(array_map('trim', explode(',', $_GET['brands'] ?? ''))));
$filterPrices = array_values(array_filter(array_map('trim', explode(',', $_GET['prices'] ?? ''))));
$filterSearch = trim($_GET['q'] ?? '');

// ── Price range definitions ───────────────────────────────────────────────────
$priceRanges = [
    'under-100'   => ['label' => 'Under $100',    'min' => 0,       'max' => 99.99],
    '100-500'     => ['label' => '$100 – $500',   'min' => 100,     'max' => 500],
    '500-1000'    => ['label' => '$500 – $1,000', 'min' => 500,     'max' => 1000],
    'above-1000'  => ['label' => 'Above $1,000',  'min' => 1000.01, 'max' => PHP_INT_MAX],
];

// ── Build WHERE clause ────────────────────────────────────────────────────────
$where  = ['p.is_active = 1'];
$params = [];
$types  = '';

if ($filterSearch !== '') {
    $like     = '%' . $conn->real_escape_string($filterSearch) . '%';
    $where[]  = '(p.name LIKE ? OR b.name LIKE ? OR c.name LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types   .= 'sss';
}

if ($filterBrands) {
    $ph      = implode(',', array_fill(0, count($filterBrands), '?'));
    $where[] = "b.name IN ({$ph})";
    foreach ($filterBrands as $br) {
        $params[] = $br;
        $types   .= 's';
    }
}

if ($filterPrices) {
    $priceClauses = [];
    foreach ($filterPrices as $key) {
        if (isset($priceRanges[$key])) {
            $r              = $priceRanges[$key];
            $priceClauses[] = '(p.price >= ? AND p.price <= ?)';
            $params[]       = $r['min'];
            $params[]       = $r['max'];
            $types         .= 'dd';
        }
    }
    if ($priceClauses) {
        $where[] = '(' . implode(' OR ', $priceClauses) . ')';
    }
}

$whereSQL = 'WHERE ' . implode(' AND ', $where);

// ── Fetch all matching products (no pagination) ─────────────────────────────
$productSQL = "
    SELECT
        p.productId,
        p.name,
        p.description,
        p.price,
        p.stock,
        p.icon_class,
        b.name  AS brand,
        b.brandId,
        c.name  AS category,
        cover.image_path AS cover_image,
        cover.alt_text   AS cover_alt
    FROM products p
    LEFT JOIN brands     b     ON b.brandId    = p.brandId
    LEFT JOIN categories c     ON c.categoryId = p.categoryId
    LEFT JOIN (
        SELECT pi.productId, pi.image_path, pi.alt_text
        FROM product_images pi
        INNER JOIN (
            SELECT productId, MIN(imageId) AS minId
            FROM product_images
            GROUP BY productId
        ) first_img ON first_img.productId = pi.productId
                   AND first_img.minId     = pi.imageId
    ) cover ON cover.productId = p.productId
    {$whereSQL}
    ORDER BY p.created_at DESC, p.productId DESC
";

$products = [];
$stmt     = $conn->prepare($productSQL);
if ($stmt === false) {
    error_log('product.php prepare failed: ' . $conn->error);
} else {
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$totalProducts = count($products);

// ── Brands from DB (for sidebar filter) ──────────────────────────────────────
$brandRows = $conn->query('SELECT brandId, name FROM brands ORDER BY name ASC');
$allBrands = $brandRows ? $brandRows->fetch_all(MYSQLI_ASSOC) : [];

$jsBase = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
$jsBase = ($jsBase === '' || $jsBase === '/' || $jsBase === '\\') ? '' : $jsBase;
$jsBase = $jsBase ?: '';
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
                    <?php foreach ($allBrands as $brand): ?>
                        <label class="filter-check">
                            <input type="checkbox"
                                   class="brand-filter"
                                   value="<?= app_sanitize($brand['name']) ?>"
                                   <?= in_array($brand['name'], $filterBrands, true) ? 'checked' : '' ?>>
                            <span><?= app_sanitize($brand['name']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <div class="filter-group">
                    <h5>Product Pricing</h5>
                    <?php foreach ($priceRanges as $key => $range): ?>
                        <label class="filter-check">
                            <input type="checkbox"
                                   class="price-filter"
                                   value="<?= app_sanitize($key) ?>"
                                   <?= in_array($key, $filterPrices, true) ? 'checked' : '' ?>>
                            <span><?= app_sanitize($range['label']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>

                <?php if ($filterBrands || $filterPrices || $filterSearch): ?>
                    <a href="product.php" class="btn btn-sm btn-outline-secondary w-100 mt-2 filter-clear-btn">
                        <i class="fa-solid fa-xmark me-1"></i> Clear Filters
                    </a>
                <?php endif; ?>
            </aside>

            <div>
                <div class="product-topbar">
                    <input type="text"
                           class="search-input-wide"
                           id="productSearch"
                           placeholder="Search products by name, brand, or category"
                           value="<?= app_sanitize($filterSearch) ?>">
                    <div class="result-count">
                        <span id="productCount"><?= $totalProducts ?></span> product<?= $totalProducts !== 1 ? 's' : '' ?>
                    </div>
                </div>

                <div class="row g-4" id="productGrid">
                    <?php if (!$products): ?>
                        <div class="col-12">
                            <div class="p-5 text-center text-muted product-empty-state">
                                <i class="fa-solid fa-box-open product-empty-state__icon"></i>
                                No products match your filters.
                                <a href="product.php" class="product-empty-state__link">Clear filters</a>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php foreach ($products as $product): ?>
                        <div class="col-md-6 col-xl-4 product-item"
                             data-product-id="<?= (int) $product['productId'] ?>"
                             data-brand="<?= app_sanitize(strtolower($product['brand'] ?? '')) ?>"
                             data-price="<?= (float) $product['price'] ?>"
                             data-search="<?= app_sanitize(strtolower($product['name'] . ' ' . ($product['brand'] ?? '') . ' ' . ($product['category'] ?? ''))) ?>">
                            <div class="product-card h-100">

                                <div class="card-img-top">
                                    <?php if (!empty($product['cover_image'])): ?>
                                        <img src="<?= app_sanitize($product['cover_image']) ?>"
                                             alt="<?= app_sanitize($product['cover_alt'] ?: $product['name'] . ' – ' . $product['category']) ?>"
                                             class="product-cover-img"
                                             loading="lazy">
                                    <?php else: ?>
                                        <i class="<?= app_sanitize($product['icon_class']) ?>"></i>
                                    <?php endif; ?>
                                </div>

                                <div class="card-body">
                                    <div class="card-category">#<?= (int) $product['productId'] ?> • <?= app_sanitize($product['brand'] ?? '') ?></div>
                                    <h5 class="card-title"><?= app_sanitize($product['name']) ?></h5>
                                    <p class="card-text"><?= app_sanitize($product['description']) ?></p>
                                    <div class="card-price">$<?= number_format((float) $product['price'], 2) ?></div>
                                    <div class="stock-note"><?= (int) $product['stock'] ?> in stock</div>
                                </div>

                                <div class="card-footer-custom">
                                    <button class="btn-card btn-card-primary add-to-cart-btn"
                                            data-product-id="<?= (int) $product['productId'] ?>">Add to Cart</button>
                                    <button class="btn-card btn-card-outline" type="button">ID <?= (int) $product['productId'] ?></button>
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

<script>
window.__PRODUCT_PAGE__ = <?= json_encode([
    'cartActionUrl' => ($jsBase ? $jsBase . '/' : '') . 'cart_action.php',
    'cartUrl'         => ($jsBase ? $jsBase . '/' : '') . 'cart.php',
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<?php
$pageScripts = ['js/product.js'];
include __DIR__ . '/includes/scripts.php';
?>
</body>
</html>
