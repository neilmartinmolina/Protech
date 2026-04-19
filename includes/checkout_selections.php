<?php

function checkout_render_addresses($addresses, $selectedId = null, $name = 'selected_address_id') {
    if (empty($addresses)) {
        return '<div class="empty-state"><i class="fa-solid fa-map-pin"></i> No saved addresses. <a href="profile.php?section=settings">Add an address</a>.</div>';
    }

    $html = '<div class="checkout-addresses">';
    foreach ($addresses as $addr) {
        $isSelected = $addr['is_default'] || ($selectedId && (int) $addr['userAddressId'] === (int) $selectedId);
        $html .= '<div class="checkout-address-item' . ($isSelected ? ' selected' : '') . '">' .
            '<label class="d-flex align-items-start gap-2">' .
            '<input type="radio" name="' . $name . '" value="' . (int) $addr['userAddressId'] . '"' . ($isSelected ? ' checked' : '') . '>' .
            '<div>' .
            '<strong>' . app_sanitize($addr['label'] ?: 'Address') . '</strong>' .
            ($addr['is_default'] ? ' <span class="stub-badge ms-1">Default</span>' : '') .
            '<div class="text-secondary" style="font-size:.85rem;">' .
            app_sanitize($addr['recipient_name']) . ' &bull; ' . app_sanitize($addr['phone']) .
            '</div>' .
            '<div class="text-secondary" style="font-size:.85rem;">' .
            app_sanitize($addr['street']) . ', ' . app_sanitize($addr['barangay']) . ', ' .
            app_sanitize($addr['city']) .
            ($addr['province'] ? ', ' . app_sanitize($addr['province']) : '') .
            ($addr['zip'] ? ' ' . app_sanitize($addr['zip']) : '') .
            '</div>' .
            '</div>' .
            '</label>' .
            '</div>';
    }
    $html .= '</div>';
    return $html;
}

function checkout_render_payment_methods($methods, $selectedId = null, $name = 'selected_payment_id') {
    if (empty($methods)) {
        return '<div class="empty-state"><i class="fa-solid fa-credit-card"></i> No payment methods. <a href="profile.php?section=settings">Add a payment method</a>.</div>';
    }

    $html = '<div class="checkout-payments">';
    foreach ($methods as $method) {
        $isSelected = $method['is_default'] || ($selectedId && (int) $method['userPaymentMethodId'] === (int) $selectedId);
        $html .= '<div class="checkout-payment-item' . ($isSelected ? ' selected' : '') . '">' .
            '<label class="d-flex align-items-start gap-2">' .
            '<input type="radio" name="' . $name . '" value="' . (int) $method['userPaymentMethodId'] . '"' . ($isSelected ? ' checked' : '') . '>' .
            '<div>' .
            '<strong>' . app_sanitize($method['label'] ?: ucfirst($method['type'])) . '</strong>' .
            ($method['is_default'] ? ' <span class="stub-badge ms-1">Default</span>' : '') .
            '<div class="text-secondary" style="font-size:.85rem;">';
        if ($method['type'] === 'gcash') {
            $html .= 'GCash &bull; ' . app_sanitize($method['gcash_name'] ?? '');
            if ($method['gcash_number']) {
                $html .= ' &bull; ' . app_sanitize($method['gcash_number']);
            }
        } else {
            $html .= 'Cash on Delivery';
        }
        $html .= '</div>' .
            '</div>' .
            '</label>' .
            '</div>';
    }
    $html .= '</div>';
    return $html;
}

function checkout_get_selections($conn, $userId) {
    $selections = ['addresses' => [], 'payments' => []];

    $stmt = $conn->prepare('SELECT * FROM user_addresses WHERE userId = ? ORDER BY is_default DESC, created_at DESC');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $selections['addresses'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare('SELECT * FROM user_payment_methods WHERE userId = ? ORDER BY is_default DESC, created_at DESC');
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $selections['payments'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $selections;
}