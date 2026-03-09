<!DOCTYPE html>
<html lang="en">
<head>
    <?php include "header.php" ?>
</head>
<body>

<?php include "navbar.php" ?>

<!-- Page Header -->
<div class="product-listing-header">
    <div class="container">
        <div class="section-label"><i class="fa-solid fa-box-open"></i> Our Catalog</div>
        <h1 class="section-title">Products</h1>
        <p class="section-desc mx-auto">Explore our curated selection of premium technology products.</p>
    </div>
</div>

<!-- Filter + Products -->
<section class="products-section pb-5">
    <div class="container">

        <!-- Filter Bar -->
        <div class="product-filter-bar">
            <div style="position: relative; flex: 1; min-width: 200px;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 0.85rem; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 0.85rem;"></i>
                <input type="text" class="search-input" id="productSearch" placeholder="Search products...">
            </div>
            <button class="filter-btn active" data-filter="all">All</button>
            <button class="filter-btn" data-filter="laptops">Laptops</button>
            <button class="filter-btn" data-filter="desktops">Desktops</button>
            <button class="filter-btn" data-filter="peripherals">Peripherals</button>
            <button class="filter-btn" data-filter="networking">Networking</button>
        </div>

        <!-- Product Grid -->
        <div class="row g-4" id="productGrid">

            <div class="col-sm-6 col-lg-4 col-xl-3 product-item" data-category="laptops">
                <div class="product-card">
                    <div class="card-img-top"><i class="fa-solid fa-laptop"></i></div>
                    <div class="card-body">
                        <div class="card-category">Laptops</div>
                        <h5 class="card-title">ProBook X1 Ultra</h5>
                        <p class="card-text">15.6" 4K OLED, Intel i9, 32GB RAM, 1TB SSD. Built for professionals who demand the best.</p>
                        <div class="card-price">$1,499.00</div>
                    </div>
                    <div class="card-footer-custom">
                        <button class="btn-card btn-card-primary">Add to Cart</button>
                        <button class="btn-card btn-card-outline">Details</button>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4 col-xl-3 product-item" data-category="desktops">
                <div class="product-card">
                    <div class="card-img-top"><i class="fa-solid fa-desktop"></i></div>
                    <div class="card-body">
                        <div class="card-category">Desktops</div>
                        <h5 class="card-title">TowerMax Pro 5000</h5>
                        <p class="card-text">AMD Ryzen 9, RTX 4080, 64GB RAM, liquid cooled. Ultimate power for creators and gamers.</p>
                        <div class="card-price">$2,299.00</div>
                    </div>
                    <div class="card-footer-custom">
                        <button class="btn-card btn-card-primary">Add to Cart</button>
                        <button class="btn-card btn-card-outline">Details</button>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4 col-xl-3 product-item" data-category="peripherals">
                <div class="product-card">
                    <div class="card-img-top"><i class="fa-solid fa-keyboard"></i></div>
                    <div class="card-body">
                        <div class="card-category">Peripherals</div>
                        <h5 class="card-title">MechStrike RGB Keyboard</h5>
                        <p class="card-text">Hot-swappable mechanical switches, per-key RGB, aircraft-grade aluminum frame.</p>
                        <div class="card-price">$149.00</div>
                    </div>
                    <div class="card-footer-custom">
                        <button class="btn-card btn-card-primary">Add to Cart</button>
                        <button class="btn-card btn-card-outline">Details</button>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4 col-xl-3 product-item" data-category="networking">
                <div class="product-card">
                    <div class="card-img-top"><i class="fa-solid fa-wifi"></i></div>
                    <div class="card-body">
                        <div class="card-category">Networking</div>
                        <h5 class="card-title">NetPro Wi-Fi 7 Router</h5>
                        <p class="card-text">Tri-band Wi-Fi 7, 10Gbps Ethernet, mesh-ready. Blanket your space in high-speed connectivity.</p>
                        <div class="card-price">$349.00</div>
                    </div>
                    <div class="card-footer-custom">
                        <button class="btn-card btn-card-primary">Add to Cart</button>
                        <button class="btn-card btn-card-outline">Details</button>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4 col-xl-3 product-item" data-category="laptops">
                <div class="product-card">
                    <div class="card-img-top"><i class="fa-solid fa-laptop"></i></div>
                    <div class="card-body">
                        <div class="card-category">Laptops</div>
                        <h5 class="card-title">SlimBook Air 14</h5>
                        <p class="card-text">Ultra-thin 14" FHD, Intel i5, 16GB RAM, 512GB SSD. All-day battery for work on the go.</p>
                        <div class="card-price">$899.00</div>
                    </div>
                    <div class="card-footer-custom">
                        <button class="btn-card btn-card-primary">Add to Cart</button>
                        <button class="btn-card btn-card-outline">Details</button>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4 col-xl-3 product-item" data-category="peripherals">
                <div class="product-card">
                    <div class="card-img-top"><i class="fa-solid fa-computer-mouse"></i></div>
                    <div class="card-body">
                        <div class="card-category">Peripherals</div>
                        <h5 class="card-title">PrecisionGlide Mouse</h5>
                        <p class="card-text">25K DPI sensor, wireless, 80-hour battery, ergonomic contour design for long sessions.</p>
                        <div class="card-price">$79.00</div>
                    </div>
                    <div class="card-footer-custom">
                        <button class="btn-card btn-card-primary">Add to Cart</button>
                        <button class="btn-card btn-card-outline">Details</button>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4 col-xl-3 product-item" data-category="desktops">
                <div class="product-card">
                    <div class="card-img-top"><i class="fa-solid fa-server"></i></div>
                    <div class="card-body">
                        <div class="card-category">Desktops</div>
                        <h5 class="card-title">CompactDesk Mini</h5>
                        <p class="card-text">Intel i7, 32GB RAM, 1TB NVMe. Powerful desktop in a tiny form factor for tight workspaces.</p>
                        <div class="card-price">$1,099.00</div>
                    </div>
                    <div class="card-footer-custom">
                        <button class="btn-card btn-card-primary">Add to Cart</button>
                        <button class="btn-card btn-card-outline">Details</button>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-lg-4 col-xl-3 product-item" data-category="networking">
                <div class="product-card">
                    <div class="card-img-top"><i class="fa-solid fa-ethernet"></i></div>
                    <div class="card-body">
                        <div class="card-category">Networking</div>
                        <h5 class="card-title">SwitchPro 24-Port</h5>
                        <p class="card-text">Managed Gigabit switch with PoE+, VLAN support, and a silent fanless design for offices.</p>
                        <div class="card-price">$249.00</div>
                    </div>
                    <div class="card-footer-custom">
                        <button class="btn-card btn-card-primary">Add to Cart</button>
                        <button class="btn-card btn-card-outline">Details</button>
                    </div>
                </div>
            </div>

        </div>
    </div>
</section>

<!-- Footer -->
<footer class="site-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-lg-4">
                <div class="footer-brand"><i class="fa-solid fa-microchip"></i> Pro<span>Tech</span></div>
                <p class="footer-desc">Tech that transforms, service that delivers. Your trusted partner for technology solutions.</p>
            </div>
            <div class="col-6 col-lg-2">
                <h6>Company</h6>
                <ul class="footer-links">
                    <li><a href="index.php#about">About Us</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Contact</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h6>Products</h6>
                <ul class="footer-links">
                    <li><a href="product.php">All Products</a></li>
                    <li><a href="#">New Arrivals</a></li>
                    <li><a href="#">Best Sellers</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h6>Support</h6>
                <ul class="footer-links">
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">Warranty</a></li>
                    <li><a href="#">Returns</a></li>
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <h6>Legal</h6>
                <ul class="footer-links">
                    <li><a href="#">Privacy Policy</a></li>
                    <li><a href="#">Terms of Service</a></li>
                    <li><a href="#">Cookie Policy</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; 2026 ProTech. All rights reserved.</p>
            <div class="social-links">
                <a href="#"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#"><i class="fa-brands fa-twitter"></i></a>
                <a href="#"><i class="fa-brands fa-instagram"></i></a>
                <a href="#"><i class="fa-brands fa-linkedin-in"></i></a>
            </div>
        </div>
    </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
    const searchInput = document.getElementById('productSearch');
    const filterBtns = document.querySelectorAll('.filter-btn');
    const items = document.querySelectorAll('.product-item');
    let activeFilter = 'all';

    function applyFilters() {
        const query = searchInput.value.toLowerCase().trim();
        items.forEach(item => {
            const cat = item.dataset.category;
            const text = item.textContent.toLowerCase();
            const matchFilter = activeFilter === 'all' || cat === activeFilter;
            const matchSearch = !query || text.includes(query);
            item.style.display = matchFilter && matchSearch ? '' : 'none';
        });
    }

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            filterBtns.forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            activeFilter = btn.dataset.filter;
            applyFilters();
        });
    });

    searchInput.addEventListener('input', applyFilters);
})();
</script>
</body>
</html>
