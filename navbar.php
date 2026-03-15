<nav class="navbar navbar-expand-lg pt-navbar" id="mainNavbar">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fa-solid fa-microchip" style="font-size: 1.2rem;"></i>
            Pro<span>Tech</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarMain">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="index.php#about">About</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="product.php">Products</a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-2">
                <a href="admindash.php" class="nav-link" title="Admin"><i class="fa-solid fa-user fa-lg" style="color: var(--text-secondary);"></i></a>
                <a href="cart.php" class="nav-link" title="Cart"><i class="fa-solid fa-cart-shopping fa-lg" style="color: var(--text-secondary);"></i></a>
                <a href="signup.php" class="nav-cta">Get Started</a>
            </div>
        </div>
    </div>
</nav>

<script>
window.addEventListener('scroll', function() {
    const nav = document.getElementById('mainNavbar');
    if (window.scrollY > 50) {
        nav.classList.add('scrolled');
    } else {
        nav.classList.remove('scrolled');
    }
});
</script>
