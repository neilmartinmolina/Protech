<?php

function profile_process_post($conn, $user) {
    $result = [
        'flash' => null,
        'errors' => [],
        'activeSection' => 'general',
        'openEditForm' => false,
        'user' => $user,
    ];

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return $result;
    }

    if (!app_verify_csrf()) {
        $result['flash'] = ['type' => 'danger', 'message' => 'Invalid or missing CSRF token. Please refresh the page and try again.'];
        return $result;
    }

    $action = $_POST['action'] ?? '';
    $errors = [];
    $flash = null;
    $activeSection = 'general';
    $openEditForm = false;

    if ($action === 'update_profile') {
        $activeSection = 'general';
        $openEditForm = true;
        $firstName = trim($_POST['first_name'] ?? '');
        $lastName = trim($_POST['last_name'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $avatarFile = $_FILES['avatar'] ?? null;

        if ($firstName === '') $errors[] = 'First name is required.';
        if ($lastName === '') $errors[] = 'Last name is required.';
        if (!preg_match('/^[a-zA-Z0-9_]{3,50}$/', $username)) $errors[] = 'Username must be 3–50 characters (letters, numbers, underscores only).';

        $avatarResult = ['success' => true, 'path' => $user['avatar_path'] ?? null];

        if (!$errors) {
            $stmt = $conn->prepare('SELECT userId FROM users WHERE username = ? AND userId <> ? LIMIT 1');
            $stmt->bind_param('si', $username, $user['userId']);
            $stmt->execute();
            $exists = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($exists) $errors[] = 'That username is already taken.';
        }

        if (!$errors && $avatarFile && ($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $avatarResult = app_store_avatar($avatarFile, $user['avatar_path'] ?? null);
            if (!$avatarResult['success']) $errors[] = $avatarResult['message'];
        }

        if (!$errors) {
            $avatarPath = $avatarResult['path'] ?? ($user['avatar_path'] ?? null);
            $stmt = $conn->prepare('UPDATE users SET first_name=?, last_name=?, username=?, avatar_path=? WHERE userId=?');
            $stmt->bind_param('ssssi', $firstName, $lastName, $username, $avatarPath, $user['userId']);
            $stmt->execute();
            $stmt->close();

            $user = app_refresh_session_user((int) $user['userId']) ?? $user;
            $flash = ['type' => 'success', 'message' => 'Profile updated successfully.'];
            $openEditForm = false;
        }
    }

    if ($action === 'change_email') {
        $activeSection = 'security';
        $newEmail = trim($_POST['new_email'] ?? '');
        $confirmPassword = $_POST['confirm_password_email'] ?? '';

        if (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
        if ($confirmPassword === '') $errors[] = 'Please confirm your password to change email.';

        if (!$errors) {
            $stmt = $conn->prepare('SELECT userId FROM users WHERE email = ? AND userId <> ? LIMIT 1');
            $stmt->bind_param('si', $newEmail, $user['userId']);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) $errors[] = 'That email is already in use by another account.';
            $stmt->close();
        }

        if (!$errors) {
            $stmt = $conn->prepare('SELECT password_hash FROM users WHERE userId = ? LIMIT 1');
            $stmt->bind_param('i', $user['userId']);
            $stmt->execute();
            $account = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$account || !password_verify($confirmPassword, $account['password_hash'])) {
                $errors[] = 'Password is incorrect.';
            }
        }

        if (!$errors) {
            $stmt = $conn->prepare('UPDATE users SET email = ? WHERE userId = ?');
            $stmt->bind_param('si', $newEmail, $user['userId']);
            $stmt->execute();
            $stmt->close();
            $user = app_refresh_session_user((int) $user['userId']) ?? $user;
            $flash = ['type' => 'success', 'message' => 'Email updated successfully.'];
        }
    }

    if ($action === 'apply_seller') {
        $activeSection = 'settings';
        $applyStoreName = trim($_POST['store_name'] ?? '');
        $applyReason = trim($_POST['reason'] ?? '');

        if ($applyStoreName === '') $errors[] = 'Store name is required.';
        if (strlen($applyStoreName) > 150) $errors[] = 'Store name must be 150 characters or less.';

        if (!$errors) {
            $stmt = $conn->prepare("SELECT status FROM seller_applications WHERE userId = ? AND status = 'pending' LIMIT 1");
            $stmt->bind_param('i', $user['userId']);
            $stmt->execute();
            if ($stmt->get_result()->fetch_assoc()) $errors[] = 'You already have a pending application.';
            $stmt->close();
        }

        if (!$errors && ($user['role'] ?? '') !== 'customer') {
            $errors[] = 'Only customers can apply to become a seller.';
        }

        if (!$errors) {
            $stmt = $conn->prepare("INSERT INTO seller_applications (userId, store_name, reason, status) VALUES (?, ?, ?, 'pending')");
            $stmt->bind_param('iss', $user['userId'], $applyStoreName, $applyReason);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("UPDATE users SET seller_status = 'pending' WHERE userId = ?");
            $stmt->bind_param('i', $user['userId']);
            $stmt->execute();
            $stmt->close();

            $user = app_refresh_session_user((int) $user['userId']) ?? $user;
            $flash = ['type' => 'success', 'message' => 'Your seller application has been submitted. We\'ll review it shortly.'];
        }
    }

    if ($action === 'change_password') {
        $activeSection = 'security';
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($currentPassword === '') $errors[] = 'Current password is required.';
        if (strlen($newPassword) < 6) $errors[] = 'Password must be at least 6 characters.';
        if (!preg_match('/[A-Z]/', $newPassword)) $errors[] = 'Password must include at least one capital letter.';
        if (!preg_match('/[0-9]/', $newPassword)) $errors[] = 'Password must include at least one number.';
        if ($newPassword !== $confirmPassword) $errors[] = 'New password and confirmation do not match.';

        $stmt = $conn->prepare('SELECT password_hash FROM users WHERE userId = ? LIMIT 1');
        $stmt->bind_param('i', $user['userId']);
        $stmt->execute();
        $account = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$account || !password_verify($currentPassword, $account['password_hash'])) {
            $errors[] = 'Current password is incorrect.';
        }

        if (!$errors) {
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $stmt = $conn->prepare('UPDATE users SET password_hash = ?, temp_password = NULL WHERE userId = ?');
            $stmt->bind_param('si', $newHash, $user['userId']);
            $stmt->execute();
            $stmt->close();
            $flash = ['type' => 'success', 'message' => 'Password changed successfully.'];
        }
    }

    if ($action === 'add_address') {
        $activeSection = 'settings';
        $recipientName = trim($_POST['recipient_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $label = trim($_POST['label'] ?? '');
        $street = trim($_POST['street'] ?? '');
        $barangay = trim($_POST['barangay'] ?? '');
        $city = trim($_POST['city'] ?? '');
        $province = trim($_POST['province'] ?? '');
        $zip = trim($_POST['zip'] ?? '');
        $setDefault = isset($_POST['set_default']);

        if ($recipientName === '') $errors[] = 'Recipient name is required.';
        if ($phone === '') $errors[] = 'Phone number is required.';
        if ($street === '') $errors[] = 'Street address is required.';
        if ($barangay === '') $errors[] = 'Barangay is required.';
        if ($city === '') $errors[] = 'City is required.';

        if (!$errors) {
            if ($setDefault) {
                $stmt = $conn->prepare('UPDATE user_addresses SET is_default = 0 WHERE userId = ?');
                $stmt->bind_param('i', $user['userId']);
                $stmt->execute();
                $stmt->close();
            }

            $stmt = $conn->prepare("INSERT INTO user_addresses (userId, recipient_name, phone, label, street, barangay, city, province, zip, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $defaultFlag = $setDefault ? 1 : 0;
            $stmt->bind_param("issssssssi", $user['userId'], $recipientName, $phone, $label, $street, $barangay, $city, $province, $zip, $defaultFlag);
            $stmt->execute();
            $stmt->close();

            $flash = ['type' => 'success', 'message' => 'Address added successfully.'];
        }
    }

    if ($action === 'delete_address') {
        $activeSection = 'settings';
        $addressId = (int) ($_POST['address_id'] ?? 0);

        if ($addressId > 0) {
            $stmt = $conn->prepare('DELETE FROM user_addresses WHERE userAddressId = ? AND userId = ?');
            $stmt->bind_param('ii', $addressId, $user['userId']);
            $stmt->execute();
            $stmt->close();
            $flash = ['type' => 'success', 'message' => 'Address deleted successfully.'];
        }
    }

    if ($action === 'set_default_address') {
        $activeSection = 'settings';
        $addressId = (int) ($_POST['address_id'] ?? 0);

        if ($addressId > 0) {
            $stmt = $conn->prepare('UPDATE user_addresses SET is_default = 0 WHERE userId = ?');
            $stmt->bind_param('i', $user['userId']);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare('UPDATE user_addresses SET is_default = 1 WHERE userAddressId = ? AND userId = ?');
            $stmt->bind_param('ii', $addressId, $user['userId']);
            $stmt->execute();
            $stmt->close();
            $flash = ['type' => 'success', 'message' => 'Default address updated.'];
        }
    }

    if ($action === 'add_payment') {
        $activeSection = 'settings';
        $paymentType = $_POST['payment_type'] ?? '';
        $label = trim($_POST['label'] ?? '');
        $gcashName = trim($_POST['gcash_name'] ?? '');
        $gcashNumber = trim($_POST['gcash_number'] ?? '');
        $setDefault = isset($_POST['set_default']);

        if ($paymentType === '') $errors[] = 'Payment type is required.';
        if (!in_array($paymentType, ['gcash', 'cod'])) $errors[] = 'Invalid payment type.';
        if ($paymentType === 'gcash') {
            if ($gcashName === '') $errors[] = 'GCash name is required.';
            if ($gcashNumber === '') $errors[] = 'GCash number is required.';
        }

        if (!$errors) {
            if ($setDefault) {
                $stmt = $conn->prepare('UPDATE user_payment_methods SET is_default = 0 WHERE userId = ?');
                $stmt->bind_param('i', $user['userId']);
                $stmt->execute();
                $stmt->close();
            }

            $stmt = $conn->prepare("INSERT INTO user_payment_methods (userId, type, label, gcash_name, gcash_number, is_default) VALUES (?, ?, ?, ?, ?, ?)");
            $defaultFlag = $setDefault ? 1 : 0;
            $gcashNumForDb = $paymentType === 'gcash' ? $gcashNumber : null;
            $gcashNameForDb = $paymentType === 'gcash' ? $gcashName : null;
            $stmt->bind_param('issssi', $user['userId'], $paymentType, $label, $gcashNameForDb, $gcashNumForDb, $defaultFlag);
            $stmt->execute();
            $stmt->close();

            $flash = ['type' => 'success', 'message' => 'Payment method added successfully.'];
        }
    }

    if ($action === 'delete_payment') {
        $activeSection = 'settings';
        $paymentId = (int) ($_POST['payment_id'] ?? 0);

        if ($paymentId > 0) {
            $stmt = $conn->prepare('DELETE FROM user_payment_methods WHERE userPaymentMethodId = ? AND userId = ?');
            $stmt->bind_param('ii', $paymentId, $user['userId']);
            $stmt->execute();
            $stmt->close();
            $flash = ['type' => 'success', 'message' => 'Payment method deleted successfully.'];
        }
    }

    if ($action === 'set_default_payment') {
        $activeSection = 'settings';
        $paymentId = (int) ($_POST['payment_id'] ?? 0);

        if ($paymentId > 0) {
            $stmt = $conn->prepare('UPDATE user_payment_methods SET is_default = 0 WHERE userId = ?');
            $stmt->bind_param('i', $user['userId']);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare('UPDATE user_payment_methods SET is_default = 1 WHERE userPaymentMethodId = ? AND userId = ?');
            $stmt->bind_param('ii', $paymentId, $user['userId']);
            $stmt->execute();
            $stmt->close();
            $flash = ['type' => 'success', 'message' => 'Default payment method updated.'];
        }
    }

    if ($errors) {
        $flash = ['type' => 'danger', 'message' => implode(' ', $errors)];
    }

    return [
        'flash' => $flash,
        'errors' => $errors,
        'activeSection' => $activeSection,
        'openEditForm' => $openEditForm,
        'user' => $user,
    ];
}

function profile_get_data($conn, $user) {
    $data = [
        'savedAddresses' => [],
        'paymentMethods' => [],
        'sellerApplication' => null,
    ];

    $stmt = $conn->prepare('SELECT * FROM user_addresses WHERE userId = ? ORDER BY is_default DESC, created_at DESC');
    $stmt->bind_param('i', $user['userId']);
    $stmt->execute();
    $data['savedAddresses'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare('SELECT * FROM user_payment_methods WHERE userId = ? ORDER BY is_default DESC, created_at DESC');
    $stmt->bind_param('i', $user['userId']);
    $stmt->execute();
    $data['paymentMethods'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (($user['role'] ?? '') === 'customer') {
        $stmt = $conn->prepare('SELECT * FROM seller_applications WHERE userId = ? ORDER BY created_at DESC LIMIT 1');
        $stmt->bind_param('i', $user['userId']);
        $stmt->execute();
        $data['sellerApplication'] = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }

    return $data;
}