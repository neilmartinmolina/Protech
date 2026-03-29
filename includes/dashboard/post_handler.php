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
                    SET role = 'seller', seller_status = 'approved', store_name = ?
                    WHERE userId = ?
                ");
                $stmt->bind_param('si', $storeName, $userId);
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

    // ── Update order status ───────────────────────────────────────────────────
    if ($action === 'update_order_status' && ($role === 'seller' || $role === 'admin')) {
        $orderId         = (int) ($_POST['order_id'] ?? 0);
        $status          = trim($_POST['status'] ?? '');
        $allowedStatuses = ['placed', 'processing', 'completed', 'cancelled'];

        if (!in_array($status, $allowedStatuses, true)) {
            $flash = ['type' => 'danger', 'message' => 'Invalid order status selected.'];
        } else {
            if ($role === 'seller') {
                $stmt = $conn->prepare('UPDATE orders SET status = ? WHERE orderId = ? AND sellerUserId = ?');
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
