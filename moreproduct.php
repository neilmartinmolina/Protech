<?php
require_once __DIR__ . '/app.php';

$pageTitle = 'Product Details - ProTech';
$pageCss   = ['moreproduct.css'];
$conn      = app_db();
$jsBase    = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
$jsBase    = ($jsBase === '' || $jsBase === '/' || $jsBase === '\\') ? '' : $jsBase;
$jsBase    = $jsBase ?: '';

$productId = max(0, (int) ($_GET['product_id'] ?? 0));
$product   = null;
$images    = [];
$seller    = null;

if ($productId > 0) {
    $hasProductImagesTable = app_table_exists($conn, 'product_images');

    if ($hasProductImagesTable) {
        $productSQL = "
            SELECT
                p.productId,
                p.name,
                p.description,
                p.price,
                p.stock,
                p.icon_class,
                p.sellerUserId,
                b.name AS brand,
                c.name AS category,
                cover.image_path AS cover_image,
                cover.alt_text AS cover_alt
            FROM products p
            LEFT JOIN brands b ON b.brandId = p.brandId
            LEFT JOIN categories c ON c.categoryId = p.categoryId
            LEFT JOIN (
                SELECT pi.productId, pi.image_path, pi.alt_text
                FROM product_images pi
                INNER JOIN (
                    SELECT productId, MIN(imageId) AS minId
                    FROM product_images
                    GROUP BY productId
                ) first_img ON first_img.productId = pi.productId
                           AND first_img.minId = pi.imageId
            ) cover ON cover.productId = p.productId
            WHERE p.productId = ? AND p.is_active = 1
            LIMIT 1
        ";
    } else {
        $productSQL = "
            SELECT
                p.productId,
                p.name,
                p.description,
                p.price,
                p.stock,
                p.icon_class,
                p.sellerUserId,
                b.name AS brand,
                c.name AS category,
                NULL AS cover_image,
                NULL AS cover_alt
            FROM products p
            LEFT JOIN brands b ON b.brandId = p.brandId
            LEFT JOIN categories c ON c.categoryId = p.categoryId
            WHERE p.productId = ? AND p.is_active = 1
            LIMIT 1
        ";
    }

    $stmt = $conn->prepare($productSQL);
    if ($stmt) {
        $stmt->bind_param('i', $productId);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } else {
        error_log('moreproduct.php prepare failed: ' . $conn->error);
    }

    if ($product && $hasProductImagesTable) {
        $imgStmt = $conn->prepare("
            SELECT image_path, alt_text
            FROM product_images
            WHERE productId = ?
            ORDER BY imageId ASC
        ");
        if ($imgStmt) {
            $imgStmt->bind_param('i', $productId);
            $imgStmt->execute();
            $images = $imgStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $imgStmt->close();
        }
    }

    // Fetch seller info + active product count
    if ($product && !empty($product['sellerUserId'])) {
        $sellerStmt = $conn->prepare("
            SELECT
                u.store_name,
                u.avatar_path,
                COUNT(p2.productId) AS product_count
            FROM users u
            LEFT JOIN products p2
                ON p2.sellerUserId = u.userId
                AND p2.is_active = 1
            WHERE u.userId = ?
            GROUP BY u.userId
            LIMIT 1
        ");
        if ($sellerStmt) {
            $sellerStmt->bind_param('i', $product['sellerUserId']);
            $sellerStmt->execute();
            $seller = $sellerStmt->get_result()->fetch_assoc();
            $sellerStmt->close();
        } else {
            error_log('moreproduct.php seller query failed: ' . $conn->error);
        }
    }
}

// Generate 1-2 letter initials from store name as avatar fallback
function seller_initials(string $name): string {
    $words = preg_split('/\s+/', trim($name));
    if (count($words) >= 2) {
        return strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
    }
    return strtoupper(mb_substr($name, 0, 2));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include __DIR__ . '/includes/header.php'; ?>
</head>
<body>
<?php include __DIR__ . '/includes/navbar.php'; ?>

<section class="py-5 pt-5 mt-4">
    <div class="container">
        <?php if (!$product): ?>
            <div class="detail-empty text-center p-5">
                <h2 class="h4 mb-3">Product not found</h2>
                <p class="text-muted mb-4">The product may have been removed or is unavailable.</p>
                <a href="product.php" class="btn btn-primary">Back to Products</a>
            </div>
        <?php else: ?>
            <div class="mb-4">
                <a href="product.php" class="btn btn-outline-secondary btn-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Products
                </a>
            </div>

            <div class="row g-4 align-items-start">

                <!-- Image Column -->
                <div class="col-lg-6">
                    <div class="detail-image-card">
                        <?php if (!empty($images)): ?>
                            <div id="detailCarousel" class="carousel slide h-100" data-bs-ride="carousel">
                                <div class="carousel-inner h-100">
                                    <?php foreach ($images as $i => $img): ?>
                                        <div class="carousel-item h-100 <?= $i === 0 ? 'active' : '' ?>">
                                            <img src="<?= app_sanitize($img['image_path']) ?>"
                                                 alt="<?= app_sanitize($img['alt_text'] ?: $product['name']) ?>"
                                                 class="detail-main-image">
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <?php if (count($images) > 1): ?>
                                    <button class="carousel-control-prev" type="button" data-bs-target="#detailCarousel" data-bs-slide="prev">
                                        <span class="carousel-control-prev-icon"></span>
                                    </button>
                                    <button class="carousel-control-next" type="button" data-bs-target="#detailCarousel" data-bs-slide="next">
                                        <span class="carousel-control-next-icon"></span>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php elseif (!empty($product['cover_image'])): ?>
                            <img src="<?= app_sanitize($product['cover_image']) ?>"
                                 alt="<?= app_sanitize($product['cover_alt'] ?: $product['name']) ?>"
                                 class="detail-main-image">
                        <?php else: ?>
                            <div class="detail-fallback-icon">
                                <i class="<?= app_sanitize($product['icon_class']) ?>"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($images) && count($images) > 1): ?>
                        <div class="detail-thumbs mt-3">
                            <?php foreach ($images as $i => $img): ?>
                                <img src="<?= app_sanitize($img['image_path']) ?>"
                                     alt="<?= app_sanitize($img['alt_text'] ?: $product['name']) ?>"
                                     class="detail-thumb <?= $i === 0 ? 'active' : '' ?>"
                                     data-bs-target="#detailCarousel"
                                     data-bs-slide-to="<?= $i ?>">
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Info Column -->
                <div class="col-lg-6">
                    <div class="detail-info-card">
                        <div class="text-muted small mb-2">
                            <?= app_sanitize($product['brand'] ?? 'Unknown brand') ?>
                            <?php if (!empty($product['category'])): ?>
                                • <?= app_sanitize($product['category']) ?>
                            <?php endif; ?>
                        </div>

                        <h1 class="h3 mb-3"><?= app_sanitize($product['name']) ?></h1>

                        <?php if ($seller): ?>
                            <div class="detail-seller-card mb-3">
                                <div class="detail-seller-left">
                                    <div class="detail-seller-avatar">
                                        <?php if (!empty($seller['avatar_path'])): ?>
                                            <img src="<?= app_sanitize($seller['avatar_path']) ?>"
                                                 alt="<?= app_sanitize($seller['store_name'] ?? 'Seller') ?>">
                                        <?php else: ?>
                                            <span><?= app_sanitize(seller_initials($seller['store_name'] ?? 'S')) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="detail-seller-info">
                                        <div class="detail-seller-name">
                                            <?= app_sanitize($seller['store_name'] ?? 'Unknown Store') ?>
                                        </div>
                                        <div class="detail-seller-meta">Official Store</div>
                                    </div>
                                </div>
                                <div class="detail-seller-count">
                                    <div class="detail-seller-count-num"><?= (int) $seller['product_count'] ?></div>
                                    <div class="detail-seller-count-label">Products</div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="detail-price mb-3">₱<?= number_format((float) $product['price'], 2) ?></div>
                        <div class="detail-stock mb-3"><?= (int) $product['stock'] ?> in stock</div>

                        <div class="d-flex flex-wrap gap-2 mt-4 align-items-center">
                            <input type="number"
                                   id="detailQty"
                                   class="form-control"
                                   min="1"
                                   max="<?= (int) $product['stock'] ?>"
                                   value="1"
                                   style="max-width: 110px;">
                            <button type="button"
                                    class="btn btn-detail-primary"
                                    id="detailAddToCartBtn"
                                    data-product-id="<?= (int) $product['productId'] ?>">
                                Add to Cart
                            </button>
                            <button type="button"
                                    class="btn btn-detail-primary"
                                    id="detailBuyNowBtn"
                                    data-product-id="<?= (int) $product['productId'] ?>">
                                Buy Now
                            </button>
                        </div>
                    </div>
                        <!-- Description Section -->
                        <div class="detail-description-section mt-5">
                            <h2 class="detail-description-title">Product Description</h2>
                            <p class="detail-description-body"><?= app_sanitize($product['description'] ?? '') ?></p>
                        </div>
                </div>
            </div>

        <?php endif; ?>
    </div>
</section>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script>
window.__MORE_PRODUCT_PAGE__ = <?= json_encode([
    'cartActionUrl' => ($jsBase ? $jsBase . '/' : '') . 'cart_action.php',
    'cartUrl'       => ($jsBase ? $jsBase . '/' : '') . 'cart.php',
    'loginUrl'      => ($jsBase ? $jsBase . '/' : '') . 'login.php',
    'isLoggedIn'    => app_current_user() ? 1 : 0,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<?php $pageScripts = ['js/moreproduct.js']; ?>
<?php include __DIR__ . '/includes/scripts.php'; ?>
<script>
document.querySelector('#detailCarousel')?.addEventListener('slide.bs.carousel', e => {
    document.querySelectorAll('.detail-thumb').forEach((t, i) => {
        t.classList.toggle('active', i === e.to);
    });
});
</script>
</body>
</html>