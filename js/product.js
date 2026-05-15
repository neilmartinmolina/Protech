/**
 * product.php — filters (server-side via URL) and add-to-cart.
 */
(function () {
    'use strict';

    const SEARCH_DEBOUNCE_MS = 500;

    function getCartActionUrl() {
        if (window.__PRODUCT_PAGE__ && window.__PRODUCT_PAGE__.cartActionUrl) {
            return window.__PRODUCT_PAGE__.cartActionUrl;
        }
        return 'cart_action.php';
    }

    function getCartRedirectUrl() {
        if (window.__PRODUCT_PAGE__ && window.__PRODUCT_PAGE__.cartUrl) {
            return window.__PRODUCT_PAGE__.cartUrl;
        }
        return 'cart.php';
    }

    function getLoginUrl() {
        if (window.__PRODUCT_PAGE__ && window.__PRODUCT_PAGE__.loginUrl) {
            return window.__PRODUCT_PAGE__.loginUrl;
        }
        return 'login.php?cart_notice=login_to_add_cart';
    }

    function getCsrfToken() {
        if (window.__PRODUCT_PAGE__ && window.__PRODUCT_PAGE__.csrfToken) {
            return window.__PRODUCT_PAGE__.csrfToken;
        }
        return '';
    }

    function isLoggedIn() {
        return !!(window.__PRODUCT_PAGE__ && Number(window.__PRODUCT_PAGE__.isLoggedIn) === 1);
    }

    async function promptLogin(message) {
        if (typeof Swal !== 'undefined') {
            await Swal.fire({
                icon: 'info',
                title: 'Sign in required',
                text: message || 'login to add to cart',
                confirmButtonText: 'Go to Login',
            });
        } else {
            alert(message || 'login to add to cart');
        }
        window.location.assign(getLoginUrl());
    }

    function initBrandToggle() {
        const toggleBtn = document.getElementById('brandToggleBtn');
        const section = document.getElementById('brandCheckboxSection');
        if (!toggleBtn || !section) return;

        toggleBtn.addEventListener('click', () => {
            const willShow = section.classList.contains('is-hidden');
            section.classList.toggle('is-hidden');
            toggleBtn.setAttribute('aria-expanded', willShow ? 'true' : 'false');
            toggleBtn.textContent = willShow ? 'Hide Brands' : 'Show Brands';
        });
    }

    function applyFilters() {
        const searchInput = document.getElementById('productSearch');
        const brandCbs    = [...document.querySelectorAll('.brand-filter')];
        const priceCbs    = [...document.querySelectorAll('.price-filter')];

        const u = new URL(window.location.href);

        const q = (searchInput?.value ?? '').trim();
        if (q) u.searchParams.set('q', q);
        else u.searchParams.delete('q');

        const checkedBrands = brandCbs.filter((cb) => cb.checked).map((cb) => cb.value);
        const checkedPrices = priceCbs.filter((cb) => cb.checked).map((cb) => cb.value);
        if (checkedBrands.length) u.searchParams.set('brands', checkedBrands.join(','));
        else u.searchParams.delete('brands');
        if (checkedPrices.length) u.searchParams.set('prices', checkedPrices.join(','));
        else u.searchParams.delete('prices');

        u.searchParams.delete('page');

        window.location.assign(u.toString());
    }

    let searchTimer;

    function initFilters() {
        const searchInput = document.getElementById('productSearch');
        const brandCbs    = [...document.querySelectorAll('.brand-filter')];
        const priceCbs    = [...document.querySelectorAll('.price-filter')];

        searchInput?.addEventListener('input', () => {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(applyFilters, SEARCH_DEBOUNCE_MS);
        });

        brandCbs.forEach((cb) => cb.addEventListener('change', applyFilters));
        priceCbs.forEach((cb) => cb.addEventListener('change', applyFilters));
    }

    function initPagination() {
        document.querySelectorAll('[data-pagination-url]').forEach((link) => {
            link.addEventListener('click', (e) => {
                e.preventDefault();
                window.location.assign(link.dataset.paginationUrl);
            });
        });
    }

    async function updateCart(productId) {
        if (!isLoggedIn()) {
            await promptLogin();
            return;
        }

        const payload = new FormData();
        payload.append('action', 'add');
        payload.append('product_id', productId);
        payload.append('quantity', '1');
        payload.append('csrf_token', getCsrfToken());

        try {
            const res = await fetch(getCartActionUrl(), { method: 'POST', body: payload });
            const data = await res.json();
            if (!data.success) {
                if (data.requiresLogin) {
                    await promptLogin(data.message);
                    return;
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        icon: 'error',
                        title: 'Could not add to cart',
                        text: data.message || 'Unable to add item to cart.',
                        confirmButtonText: 'OK',
                    });
                } else {
                    alert(data.message || 'Unable to add item to cart.');
                }
                return;
            }
            window.location.href = getCartRedirectUrl();
        } catch {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'error',
                    title: 'Request failed',
                    text: 'Something went wrong. Please try again.',
                    confirmButtonText: 'OK',
                });
            } else {
                alert('Something went wrong. Please try again.');
            }
        }
    }

    function initAddToCart() {
        document.querySelectorAll('.add-to-cart-btn').forEach((btn) => {
            btn.addEventListener('click', () => updateCart(btn.dataset.productId));
        });
    }

    function init() {
        initBrandToggle();
        initFilters();
        initPagination();
        initAddToCart();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
