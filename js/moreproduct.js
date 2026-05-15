/**
 * moreproduct.php — add to cart and buy now actions.
 */
(function () {
    'use strict';

    function getCartActionUrl() {
        if (window.__MORE_PRODUCT_PAGE__ && window.__MORE_PRODUCT_PAGE__.cartActionUrl) {
            return window.__MORE_PRODUCT_PAGE__.cartActionUrl;
        }
        return 'cart_action.php';
    }

    function getCartUrl() {
        if (window.__MORE_PRODUCT_PAGE__ && window.__MORE_PRODUCT_PAGE__.cartUrl) {
            return window.__MORE_PRODUCT_PAGE__.cartUrl;
        }
        return 'cart.php';
    }

    function getLoginUrl() {
        if (window.__MORE_PRODUCT_PAGE__ && window.__MORE_PRODUCT_PAGE__.loginUrl) {
            return window.__MORE_PRODUCT_PAGE__.loginUrl;
        }
        return 'login.php?cart_notice=login_to_add_cart';
    }

    function isLoggedIn() {
        return !!(window.__MORE_PRODUCT_PAGE__ && Number(window.__MORE_PRODUCT_PAGE__.isLoggedIn) === 1);
    }

    function getCsrfToken() {
        if (window.__MORE_PRODUCT_PAGE__ && window.__MORE_PRODUCT_PAGE__.csrfToken) {
            return window.__MORE_PRODUCT_PAGE__.csrfToken;
        }
        return '';
    }

    function getQuantity() {
        var qtyInput = document.getElementById('detailQty');
        if (!qtyInput) return 1;
        var value = parseInt(qtyInput.value, 10);
        var min = parseInt(qtyInput.min || '1', 10);
        var max = parseInt(qtyInput.max || '999999', 10);
        if (Number.isNaN(value) || value < min) value = min;
        if (!Number.isNaN(max) && value > max) value = max;
        qtyInput.value = String(value);
        return value;
    }

    async function addToCart(productId, quantity) {
        var payload = new FormData();
        payload.append('action', 'add');
        payload.append('product_id', String(productId));
        payload.append('quantity', String(quantity));
        payload.append('csrf_token', getCsrfToken());

        var res = await fetch(getCartActionUrl(), { method: 'POST', body: payload });
        return res.json();
    }

    function notifyError(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'error',
                title: 'Unable to update cart',
                text: message || 'Please try again.',
                confirmButtonText: 'OK'
            });
            return;
        }
        alert(message || 'Please try again.');
    }

    function notifySuccess(message) {
        if (typeof Swal !== 'undefined') {
            Swal.fire({
                icon: 'success',
                title: 'Done',
                text: message,
                timer: 1400,
                showConfirmButton: false
            });
            return;
        }
        alert(message);
    }

    async function promptLoginAndRedirect(message) {
        if (typeof Swal !== 'undefined') {
            await Swal.fire({
                icon: 'info',
                title: 'Sign in required',
                text: message || 'login to add to cart',
                confirmButtonText: 'Go to Login'
            });
        } else {
            alert(message || 'login to add to cart');
        }
        window.location.assign(getLoginUrl());
    }

    function init() {
        var addBtn = document.getElementById('detailAddToCartBtn');
        var buyBtn = document.getElementById('detailBuyNowBtn');
        if (!addBtn && !buyBtn) return;

        var lock = false;

        if (addBtn) {
            addBtn.addEventListener('click', async function () {
                if (lock) return;
                if (!isLoggedIn()) {
                    await promptLoginAndRedirect();
                    return;
                }
                lock = true;
                try {
                    var productId = parseInt(addBtn.dataset.productId || '0', 10);
                    var quantity = getQuantity();
                    var data = await addToCart(productId, quantity);
                    if (!data.success) {
                        if (data.requiresLogin) {
                            await promptLoginAndRedirect(data.message);
                            return;
                        }
                        notifyError(data.message || 'Unable to add item to cart.');
                        return;
                    }
                    notifySuccess('Item added to cart.');
                } catch (error) {
                    notifyError('Something went wrong. Please try again.');
                } finally {
                    lock = false;
                }
            });
        }

        if (buyBtn) {
            buyBtn.addEventListener('click', async function () {
                if (lock) return;
                if (!isLoggedIn()) {
                    await promptLoginAndRedirect();
                    return;
                }
                lock = true;
                try {
                    var productId = parseInt(buyBtn.dataset.productId || '0', 10);
                    var quantity = getQuantity();
                    var data = await addToCart(productId, quantity);
                    if (!data.success) {
                        if (data.requiresLogin) {
                            await promptLoginAndRedirect();
                            return;
                        }
                        notifyError(data.message || 'Unable to process Buy Now.');
                        return;
                    }
                    window.location.assign(getCartUrl());
                } catch (error) {
                    notifyError('Something went wrong. Please try again.');
                } finally {
                    lock = false;
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
