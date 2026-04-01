<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/mailer.php';

/**
 * Handle dashboard POST actions. Returns updated user, role, and optional flash message.
 *
 * @return array{flash: ?array{type: string, message: string}, user: array, role: string}
 */
function dashboard_process_post(mysqli $conn, array $user, string $role): array
{
    $flash = null;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return ['flash' => null, 'user' => $user, 'role' => $role];
    }

    $action = $_POST['action'] ?? '';

    // ── Approve seller application ────────────────────────────────────────────
    if ($action === 'approve_seller' && app_is_admin($user)) {
        $applicationId = (int) ($_POST['application_id'] ?? 0);

        $stmt = $conn->prepare("
            SELECT sa.sellerApplicationId AS app_id, sa.userId, sa.store_name,
                   u.first_name, u.last_name, u.email, u.username
            FROM seller_applications sa
            JOIN users u ON u.userId = sa.userId
            WHERE sa.sellerApplicationId = ? AND sa.status = 'pending'
            LIMIT 1
        ");
        $stmt->bind_param('i', $applicationId);
        $stmt->execute();
        $application = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($application) {
            $conn->begin_transaction();
            try {
                $reviewerId = (int) $user['userId'];
                $userId     = (int) $application['userId'];
                $storeName  = $application['store_name'];

                $stmt = $conn->prepare("
                    UPDATE seller_applications
                    SET status = 'approved', reviewedByUserId = ?, reviewed_at = NOW()
                    WHERE sellerApplicationId = ?
                ");
                $stmt->bind_param('ii', $reviewerId, $applicationId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("
                    UPDATE users
                    SET role = 'seller', seller_status = 'approved'
                    WHERE userId = ?
                ");
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                try {
                    $mail = dashboard_mailer();
                    $mail->addAddress($application['email'], $application['first_name'] . ' ' . $application['last_name']);
                    $mail->Subject = 'Your ProTech seller application has been approved!';
                    $mail->Body = "
                        <div style='font-family:sans-serif;max-width:520px;margin:auto;background:#141414;border-radius:12px;overflow:hidden;'>
                            <div style='background:#ff7315;padding:24px;text-align:center;'>
                                <h1 style='color:#fff;margin:0;font-size:1.4rem;'>You're now a seller!</h1>
                            </div>
                            <div style='padding:24px;color:#e0e0e0;line-height:1.6;'>
                                <p>Hi " . app_sanitize($application['first_name']) . ",</p>
                                <p>Your seller application for <strong>" . app_sanitize($storeName) . "</strong> has been approved. You can now log in to your dashboard and start listing products.</p>
                                <p style='color:#999;font-size:.85rem;'>Your login credentials remain the same — no password change required.</p>
                            </div>
                        </div>
                    ";
                    $mail->AltBody = "Hi {$application['first_name']}, your seller application for \"{$storeName}\" has been approved. Log in to access your seller dashboard.";
                    $mail->send();
                    $flash = ['type' => 'success', 'message' => 'Application approved — seller account activated and notification email sent.'];
                } catch (Exception $e) {
                    error_log('Seller approval email failed: ' . $e->getMessage());
                    $flash = ['type' => 'warning', 'message' => 'Application approved and seller account activated. Notification email could not be sent.'];
                }
            } catch (\Throwable $e) {
                $conn->rollback();
                error_log('Seller approval transaction failed: ' . $e->getMessage());
                $flash = ['type' => 'danger', 'message' => 'Something went wrong during approval. Please try again.'];
            }
        } else {
            $flash = ['type' => 'danger', 'message' => 'Application not found or already processed.'];
        }
    }

    // ── Reject seller application ─────────────────────────────────────────────
    if ($action === 'reject_seller' && app_is_admin($user)) {
        $applicationId   = (int) ($_POST['application_id'] ?? 0);
        $rejectionReason = trim($_POST['rejection_reason'] ?? '');

        $stmt = $conn->prepare("
            SELECT sa.sellerApplicationId AS app_id, sa.userId, sa.store_name,
                   u.first_name, u.last_name, u.email
            FROM seller_applications sa
            JOIN users u ON u.userId = sa.userId
            WHERE sa.sellerApplicationId = ? AND sa.status = 'pending'
            LIMIT 1
        ");
        $stmt->bind_param('i', $applicationId);
        $stmt->execute();
        $application = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($application) {
            $conn->begin_transaction();
            try {
                $reviewerId = (int) $user['userId'];
                $userId     = (int) $application['userId'];

                $stmt = $conn->prepare("
                    UPDATE seller_applications
                    SET status = 'rejected', rejection_reason = ?, reviewedByUserId = ?, reviewed_at = NOW()
                    WHERE sellerApplicationId = ?
                ");
                $stmt->bind_param('sii', $rejectionReason, $reviewerId, $applicationId);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare("UPDATE users SET seller_status = 'rejected' WHERE userId = ?");
                $stmt->bind_param('i', $userId);
                $stmt->execute();
                $stmt->close();

                $conn->commit();

                try {
                    $mail = dashboard_mailer();
                    $mail->addAddress($application['email'], $application['first_name'] . ' ' . $application['last_name']);
                    $mail->Subject = 'Update on your ProTech seller application';
                    $reasonHtml = $rejectionReason
                        ? '<p><strong>Reason:</strong> ' . app_sanitize($rejectionReason) . '</p>'
                        : '';
                    $mail->Body = "
                        <div style='font-family:sans-serif;max-width:520px;margin:auto;background:#141414;border-radius:12px;overflow:hidden;'>
                            <div style='background:#374151;padding:24px;text-align:center;'>
                                <h1 style='color:#fff;margin:0;font-size:1.4rem;'>Application Update</h1>
                            </div>
                            <div style='padding:24px;color:#e0e0e0;line-height:1.6;'>
                                <p>Hi " . app_sanitize($application['first_name']) . ",</p>
                                <p>Thank you for applying to sell on ProTech. Unfortunately, your application for <strong>" . app_sanitize($application['store_name']) . "</strong> was not approved at this time.</p>
                                {$reasonHtml}
                                <p>You're welcome to resubmit an application from your profile settings.</p>
                            </div>
                        </div>
                    ";
                    $mail->AltBody = "Hi {$application['first_name']}, your seller application was not approved." . ($rejectionReason ? " Reason: {$rejectionReason}" : '') . " You may resubmit from your profile.";
                    $mail->send();
                } catch (Exception $e) {
                    error_log('Seller rejection email failed: ' . $e->getMessage());
                }

                $flash = ['type' => 'warning', 'message' => 'Application rejected. The applicant has been notified and can resubmit.'];
            } catch (\Throwable $e) {
                $conn->rollback();
                error_log('Seller rejection transaction failed: ' . $e->getMessage());
                $flash = ['type' => 'danger', 'message' => 'Something went wrong. Please try again.'];
            }
        } else {
            $flash = ['type' => 'danger', 'message' => 'Application not found or already processed.'];
        }
    }

    // ── Save product ──────────────────────────────────────────────────────────
    if ($action === 'save_product' && ($role === 'seller' || $role === 'admin')) {
        $result = app_upsert_product($user, $_POST);
        $flash  = ['type' => $result['success'] ? 'success' : 'danger', 'message' => $result['message']];

        if ($result['success'] && !empty($_FILES['product_images']['name'][0])) {
            $productId   = (int) ($result['product_id'] ?? 0);
            $upload      = dirname(__DIR__, 2) . '/uploads/products/' . $productId . '/';
            $category    = trim($_POST['category'] ?? 'Product');
            $productName = trim($_POST['name'] ?? 'Product');
            $allowedMime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $maxBytes    = 5 * 1024 * 1024;

            if ($productId > 0) {
                if (!is_dir($upload)) {
                    mkdir($upload, 0755, true);
                }

                $saved   = 0;
                $skipped = 0;
                foreach ($_FILES['product_images']['tmp_name'] as $i => $tmpPath) {
                    if ($_FILES['product_images']['error'][$i] !== UPLOAD_ERR_OK) {
                        $skipped++;
                        continue;
                    }
                    if ($_FILES['product_images']['size'][$i] > $maxBytes) {
                        $skipped++;
                        continue;
                    }

                    $mime = mime_content_type($tmpPath);
                    if (!in_array($mime, $allowedMime, true)) {
                        $skipped++;
                        continue;
                    }

                    $ext = match ($mime) {
                        'image/png'  => 'png',
                        'image/webp' => 'webp',
                        'image/gif'  => 'gif',
                        default      => 'jpg',
                    };
                    $altText  = $productName . ' – ' . $category;
                    $filename = sprintf('%d_%s.%s', $productId, bin2hex(random_bytes(6)), $ext);
                    $dest     = $upload . $filename;

                    if (move_uploaded_file($tmpPath, $dest)) {
                        $webPath = 'uploads/products/' . $productId . '/' . $filename;
                        $stmt    = $conn->prepare('
                            INSERT INTO product_images (productId, image_path, alt_text, sort_order)
                            VALUES (?, ?, ?, ?)
                        ');
                        $sortOrder = $saved + 1;
                        $stmt->bind_param('issi', $productId, $webPath, $altText, $sortOrder);
                        $stmt->execute();
                        $stmt->close();
                        $saved++;
                    } else {
                        $skipped++;
                    }
                }

                if ($skipped > 0 && is_array($flash)) {
                    $flash['message'] .= " ({$skipped} image(s) skipped — invalid type, too large, or upload error.)";
                }
            }
        }
    }

    // ── Save user (admin) ─────────────────────────────────────────────────────
    if ($action === 'save_user' && app_is_admin($user)) {
        $targetUserId   = (int) ($_POST['user_id'] ?? 0);
        $firstName      = trim($_POST['first_name'] ?? '');
        $lastName       = trim($_POST['last_name'] ?? '');
        $username       = trim($_POST['username'] ?? '');
        $email          = trim($_POST['email'] ?? '');
        $newRole        = trim($_POST['role'] ?? 'customer');
        $sellerStatus   = trim($_POST['seller_status'] ?? 'not_applicable');
        $storeName      = trim($_POST['store_name'] ?? '');
        $passwordPlain  = (string) ($_POST['password'] ?? '');

        $allowedRoles  = ['customer', 'seller', 'admin'];
        $allowedSeller = ['not_applicable', 'pending', 'approved', 'rejected'];

        if ($firstName === '' || $lastName === '' || $username === '' || $email === '') {
            $flash = ['type' => 'danger', 'message' => 'First name, last name, username, and email are required.'];
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $flash = ['type' => 'danger', 'message' => 'Please enter a valid email address.'];
        } elseif (!in_array($newRole, $allowedRoles, true)) {
            $flash = ['type' => 'danger', 'message' => 'Invalid role selected.'];
        } elseif (!in_array($sellerStatus, $allowedSeller, true)) {
            $flash = ['type' => 'danger', 'message' => 'Invalid seller status.'];
        } else {
            $avatarFile = $_FILES['avatar'] ?? ['error' => UPLOAD_ERR_NO_FILE];
            $avatarPath = null;
            $reviewerId = (int) $user['userId'];

            if ($newRole !== 'seller') {
                $sellerStatus = 'not_applicable';
                $storeName    = '';
            } elseif ($sellerStatus === 'approved' && $storeName === '') {
                $flash = ['type' => 'danger', 'message' => 'Store name is required when approving a seller.'];
            }

            $checkUnique = static function (mysqli $c, string $field, string $value, int $excludeId) use (&$flash): bool {
                $stmt = $c->prepare("SELECT userId FROM users WHERE {$field} = ? AND userId != ? LIMIT 1");
                if (!$stmt) {
                    return false;
                }
                $stmt->bind_param('si', $value, $excludeId);
                $stmt->execute();
                $exists = (bool) $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if ($exists) {
                    $flash = ['type' => 'danger', 'message' => ucfirst($field) . ' is already taken.'];
                    return false;
                }
                return true;
            };

            $upsertSellerApplication = static function (mysqli $c, int $uid, string $status, string $store, int $adminId) : void {
                if ($store === '' || !app_table_exists($c, 'seller_applications')) {
                    return;
                }
                $appStatus = $status === 'approved'
                    ? 'approved'
                    : ($status === 'rejected' ? 'rejected' : 'pending');

                if ($appStatus === 'pending') {
                    $stmt = $c->prepare("
                        INSERT INTO seller_applications (userId, store_name, reason, status, created_at)
                        VALUES (?, ?, NULL, 'pending', NOW())
                    ");
                    $stmt->bind_param('is', $uid, $store);
                    $stmt->execute();
                    $stmt->close();
                    return;
                }

                $stmt = $c->prepare("
                    INSERT INTO seller_applications (userId, store_name, reason, status, reviewedByUserId, reviewed_at, created_at)
                    VALUES (?, ?, NULL, ?, ?, NOW(), NOW())
                ");
                $stmt->bind_param('issi', $uid, $store, $appStatus, $adminId);
                $stmt->execute();
                $stmt->close();
            };

            if ($targetUserId === 0) {
                if (strlen($passwordPlain) < 8) {
                    $flash = ['type' => 'danger', 'message' => 'Password must be at least 8 characters for new users.'];
                } elseif (!$checkUnique($conn, 'email', $email, 0) || !$checkUnique($conn, 'username', $username, 0)) {
                    // flash set inside
                } else {
                    if (($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                        $av = app_store_avatar($avatarFile);
                        if (!$av['success']) {
                            $flash = ['type' => 'danger', 'message' => $av['message'] ?? 'Avatar upload failed.'];
                        } else {
                            $avatarPath = $av['path'];
                        }
                    }
                    if ($flash === null) {
                        $hash = password_hash($passwordPlain, PASSWORD_BCRYPT);
                        $stmt = $conn->prepare('
                            INSERT INTO users
                                (first_name, last_name, username, email, password_hash,
                                 role, seller_status, avatar_path, is_verified, created_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())
                        ');
                        $avatarIns  = $avatarPath;
                        $stmt->bind_param(
                            'ssssssss',
                            $firstName,
                            $lastName,
                            $username,
                            $email,
                            $hash,
                            $newRole,
                            $sellerStatus,
                            $avatarIns
                        );
                        if ($stmt->execute()) {
                            $newUserId = (int) $conn->insert_id;
                            if ($newRole === 'seller') {
                                $upsertSellerApplication($conn, $newUserId, $sellerStatus, $storeName, $reviewerId);
                            }
                            $flash = ['type' => 'success', 'message' => 'User created successfully.'];
                        } else {
                            $flash = ['type' => 'danger', 'message' => 'Could not create user: ' . $stmt->error];
                        }
                        $stmt->close();
                    }
                }
            } else {
                if (!$checkUnique($conn, 'email', $email, $targetUserId) || !$checkUnique($conn, 'username', $username, $targetUserId)) {
                    // flash set
                } else {
                    $existingStmt = $conn->prepare('SELECT avatar_path FROM users WHERE userId = ? LIMIT 1');
                    $existingStmt->bind_param('i', $targetUserId);
                    $existingStmt->execute();
                    $existingRow = $existingStmt->get_result()->fetch_assoc();
                    $existingStmt->close();

                    if (!$existingRow) {
                        $flash = ['type' => 'danger', 'message' => 'User not found.'];
                    } else {
                        $currentAvatar = $existingRow['avatar_path'] ?? null;
                        if (($avatarFile['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                            $av = app_store_avatar($avatarFile, $currentAvatar);
                            if (!$av['success']) {
                                $flash = ['type' => 'danger', 'message' => $av['message'] ?? 'Avatar upload failed.'];
                            } else {
                                $avatarPath = $av['path'];
                            }
                        }

                        if ($flash === null) {
                            if ($passwordPlain !== '') {
                                if (strlen($passwordPlain) < 8) {
                                    $flash = ['type' => 'danger', 'message' => 'Password must be at least 8 characters.'];
                                } else {
                                    $hash = password_hash($passwordPlain, PASSWORD_BCRYPT);
                                    if ($avatarPath !== null) {
                                        $stmt = $conn->prepare('
                                            UPDATE users SET
                                                first_name = ?, last_name = ?, username = ?, email = ?,
                                                password_hash = ?, role = ?, seller_status = ?, avatar_path = ?
                                            WHERE userId = ?
                                        ');
                                        $stmt->bind_param(
                                            'ssssssssi',
                                            $firstName,
                                            $lastName,
                                            $username,
                                            $email,
                                            $hash,
                                            $newRole,
                                            $sellerStatus,
                                            $avatarPath,
                                            $targetUserId
                                        );
                                    } else {
                                        $stmt = $conn->prepare('
                                            UPDATE users SET
                                                first_name = ?, last_name = ?, username = ?, email = ?,
                                                password_hash = ?, role = ?, seller_status = ?
                                            WHERE userId = ?
                                        ');
                                        $stmt->bind_param(
                                            'sssssssi',
                                            $firstName,
                                            $lastName,
                                            $username,
                                            $email,
                                            $hash,
                                            $newRole,
                                            $sellerStatus,
                                            $targetUserId
                                        );
                                    }
                                    $stmt->execute();
                                    $stmt->close();
                                    if ($newRole === 'seller') {
                                        $upsertSellerApplication($conn, $targetUserId, $sellerStatus, $storeName, $reviewerId);
                                    }
                                    $flash = ['type' => 'success', 'message' => 'User updated successfully.'];
                                }
                            } else {
                                if ($avatarPath !== null) {
                                    $stmt = $conn->prepare('
                                        UPDATE users SET
                                            first_name = ?, last_name = ?, username = ?, email = ?,
                                            role = ?, seller_status = ?, avatar_path = ?
                                        WHERE userId = ?
                                    ');
                                    $stmt->bind_param(
                                        'sssssssi',
                                        $firstName,
                                        $lastName,
                                        $username,
                                        $email,
                                        $newRole,
                                        $sellerStatus,
                                        $avatarPath,
                                        $targetUserId
                                    );
                                } else {
                                    $stmt = $conn->prepare('
                                        UPDATE users SET
                                            first_name = ?, last_name = ?, username = ?, email = ?,
                                            role = ?, seller_status = ?
                                        WHERE userId = ?
                                    ');
                                    $stmt->bind_param(
                                        'sssssssi',
                                        $firstName,
                                        $lastName,
                                        $username,
                                        $email,
                                        $newRole,
                                        $sellerStatus,
                                        $targetUserId
                                    );
                                }
                                $stmt->execute();
                                $stmt->close();
                                if ($newRole === 'seller') {
                                    $upsertSellerApplication($conn, $targetUserId, $sellerStatus, $storeName, $reviewerId);
                                }
                                $flash = ['type' => 'success', 'message' => 'User updated successfully.'];
                            }
                        }
                    }
                }
            }
        }
    }

    // ── Delete user (admin) ───────────────────────────────────────────────────
    if ($action === 'delete_user' && app_is_admin($user)) {
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        if ($targetUserId <= 0) {
            $flash = ['type' => 'danger', 'message' => 'Invalid user.'];
        } elseif ($targetUserId === (int) $user['userId']) {
            $flash = ['type' => 'danger', 'message' => 'You cannot delete your own account.'];
        } else {
            $ordStmt = $conn->prepare('SELECT COUNT(*) AS c FROM orders WHERE userId = ?');
            $ordStmt->bind_param('i', $targetUserId);
            $ordStmt->execute();
            $orderCount = (int) ($ordStmt->get_result()->fetch_assoc()['c'] ?? 0);
            $ordStmt->close();

            $prodStmt = $conn->prepare('SELECT COUNT(*) AS c FROM products WHERE sellerUserId = ?');
            $prodStmt->bind_param('i', $targetUserId);
            $prodStmt->execute();
            $prodCount = (int) ($prodStmt->get_result()->fetch_assoc()['c'] ?? 0);
            $prodStmt->close();

            if ($orderCount > 0) {
                $flash = ['type' => 'danger', 'message' => 'Cannot delete a user who has placed orders.'];
            } elseif ($prodCount > 0) {
                $flash = ['type' => 'danger', 'message' => 'Cannot delete a seller who still has product listings.'];
            } else {
                $stmt = $conn->prepare('SELECT avatar_path FROM users WHERE userId = ? LIMIT 1');
                $stmt->bind_param('i', $targetUserId);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                if ($row) {
                    $rt = $conn->prepare('DELETE FROM remember_tokens WHERE userId = ?');
                    $rt->bind_param('i', $targetUserId);
                    $rt->execute();
                    $rt->close();

                    if (app_table_exists($conn, 'email_verifications')) {
                        $vt = $conn->prepare('DELETE FROM email_verifications WHERE userId = ?');
                        $vt->bind_param('i', $targetUserId);
                        $vt->execute();
                        $vt->close();
                    }

                    if (app_table_exists($conn, 'password_resets')) {
                        $pr = $conn->prepare('DELETE FROM password_resets WHERE userId = ?');
                        $pr->bind_param('i', $targetUserId);
                        $pr->execute();
                        $pr->close();
                    }

                    if (app_table_exists($conn, 'seller_applications')) {
                        $sa = $conn->prepare('DELETE FROM seller_applications WHERE userId = ?');
                        $sa->bind_param('i', $targetUserId);
                        $sa->execute();
                        $sa->close();
                    }

                    $del = $conn->prepare('DELETE FROM users WHERE userId = ?');
                    $del->bind_param('i', $targetUserId);
                    if ($del->execute() && $del->affected_rows > 0) {
                        if (!empty($row['avatar_path'])) {
                            $oldPath = __DIR__ . '/../../' . ltrim($row['avatar_path'], '/');
                            if (is_file($oldPath) && str_contains(str_replace('\\', '/', $oldPath), '/media/avatars/')) {
                                @unlink($oldPath);
                            }
                        }
                        $flash = ['type' => 'success', 'message' => 'User deleted.'];
                    } else {
                        $flash = ['type' => 'danger', 'message' => 'Could not delete user.'];
                    }
                    $del->close();
                } else {
                    $flash = ['type' => 'danger', 'message' => 'User not found.'];
                }
            }
        }
    }

    // ── Update order status ───────────────────────────────────────────────────
    if ($action === 'update_order_status' && ($role === 'seller' || $role === 'admin')) {
        $orderId         = (int) ($_POST['order_id'] ?? 0);
        $status          = trim($_POST['status'] ?? '');
        $allowedStatuses = ['placed', 'processing', 'completed', 'cancelled'];

        if (!in_array($status, $allowedStatuses, true)) {
            $flash = ['type' => 'danger', 'message' => 'Invalid order status selected.'];
        } else {
            if ($role === 'seller') {
                $stmt = $conn->prepare('
                    UPDATE orders o
                    JOIN order_items oi ON oi.orderId = o.orderId
                    SET o.status = ?
                    WHERE o.orderId = ? AND oi.sellerUserId = ?
                ');
                $stmt->bind_param('sii', $status, $orderId, $user['userId']);
            } else {
                $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE orderId = ?');
                $stmt->bind_param('si', $status, $orderId);
            }
            $stmt->execute();
            $affected = $stmt->affected_rows;
            $stmt->close();
            $flash = $affected
                ? ['type' => 'success', 'message' => 'Order status updated successfully.']
                : ['type' => 'danger',  'message' => 'Order not found or not allowed.'];
        }
    }

    $user = app_refresh_session_user((int) $user['userId']) ?? $user;
    $role = $user['role'] ?? $role;

    return ['flash' => $flash, 'user' => $user, 'role' => $role];
}
