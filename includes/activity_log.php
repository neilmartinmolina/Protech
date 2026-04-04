<?php

declare(strict_types=1);

/**
 * Write an activity log entry and, optionally, a notification for one or more recipients.
 *
 * @param mysqli      $conn
 * @param int         $actorUserId     Who triggered this action.
 * @param string      $action          Dot-namespaced action key, e.g. 'product.created'.
 * @param string      $description     Plain-English summary stored in the log.
 * @param array{
 *   entity_type?: string,
 *   entity_id?:   int,
 *   context?:     array<string,mixed>,
 *   severity?:    'info'|'warning'|'critical',
 *   notify?:      array<int, array{ title: string, body: string, link?: string }>
 * } $opts
 *
 * Usage examples
 * --------------
 * // Simple log with no notification:
 * app_log_activity($conn, $user['userId'], 'product.created', "Added product #{$productId}.", [
 *     'entity_type' => 'product',
 *     'entity_id'   => $productId,
 *     'context'     => ['name' => $name, 'price' => $price],
 * ]);
 *
 * // Log + notify admin (userId 1) and the seller:
 * app_log_activity($conn, $actorId, 'seller.approved', "Seller application #{$appId} approved.", [
 *     'entity_type' => 'seller_application',
 *     'entity_id'   => $appId,
 *     'severity'    => 'info',
 *     'notify'      => [
 *         $sellerId => [
 *             'title' => 'Your seller application was approved!',
 *             'body'  => 'Congratulations — your store is now live.',
 *             'link'  => 'dashboard.php?tab=dashboard',
 *         ],
 *     ],
 * ]);
 */
function app_log_activity(
    mysqli $conn,
    int    $actorUserId,
    string $action,
    string $description,
    array  $opts = []
): bool {
    $entityType = $opts['entity_type'] ?? null;
    $entityId   = isset($opts['entity_id']) ? (int) $opts['entity_id'] : null;
    $context    = isset($opts['context'])   ? json_encode($opts['context'], JSON_UNESCAPED_UNICODE) : null;
    $severity   = in_array($opts['severity'] ?? '', ['info', 'warning', 'critical'], true)
                    ? $opts['severity']
                    : 'info';

    $stmt = $conn->prepare(
        'INSERT INTO activity_logs
            (actor_userId, action, entity_type, entity_id, description, context, severity)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    if ($stmt === false) {
        error_log('app_log_activity prepare failed: ' . $conn->error);
        return false;
    }

    $stmt->bind_param('ississs',
        $actorUserId,
        $action,
        $entityType,
        $entityId,
        $description,
        $context,
        $severity
    );

    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        error_log('app_log_activity insert failed: ' . $conn->error);
        return false;
    }

    // Optional notifications
    if (!empty($opts['notify']) && is_array($opts['notify'])) {
        foreach ($opts['notify'] as $recipientId => $notif) {
            $recipientId = (int) $recipientId;
            $title       = mb_substr((string) ($notif['title'] ?? ''), 0, 128);
            $body        = mb_substr((string) ($notif['body']  ?? ''), 0, 512);
            $link        = isset($notif['link']) ? mb_substr((string) $notif['link'], 0, 255) : null;

            $ns = $conn->prepare(
                'INSERT INTO notifications (recipient_userId, title, body, link)
                 VALUES (?, ?, ?, ?)'
            );

            if ($ns === false) {
                error_log('app_log_activity notification prepare failed: ' . $conn->error);
                continue;
            }

            $ns->bind_param('isss', $recipientId, $title, $body, $link);
            $ns->execute();
            $ns->close();
        }
    }

    return true;
}

/**
 * Fetch activity logs.
 *
 * Admin:  all logs, newest first.
 * Seller: only logs where actor_userId = $sellerId
 *         OR entity_type IN ('product','order') AND entity belongs to seller.
 *
 * @return list<array<string,mixed>>
 */
function app_get_activity_logs(mysqli $conn, string $role, int $userId, int $limit = 200): array
{
    if ($role === 'admin') {
        $stmt = $conn->prepare(
            'SELECT l.*, u.username AS actor_username, u.first_name, u.last_name
             FROM activity_logs l
             LEFT JOIN users u ON u.userId = l.actor_userId
             ORDER BY l.created_at DESC
             LIMIT ?'
        );
        if ($stmt === false) return [];
        $stmt->bind_param('i', $limit);
    } else {
        // Seller scope:
        // 1. Logs they personally triggered.
        // 2. Logs on products they own.
        // 3. Logs on orders that contain their products.
        $stmt = $conn->prepare(
            'SELECT l.*, u.username AS actor_username, u.first_name, u.last_name
             FROM activity_logs l
             LEFT JOIN users u ON u.userId = l.actor_userId
             WHERE l.actor_userId = ?
                OR (l.entity_type = \'product\'
                    AND l.entity_id IN (
                        SELECT productId FROM products WHERE sellerUserId = ?
                    ))
                OR (l.entity_type = \'order\'
                    AND l.entity_id IN (
                        SELECT DISTINCT o.orderId
                        FROM orders o
                        JOIN order_items oi ON oi.orderId = o.orderId
                        WHERE oi.sellerUserId = ?
                    ))
             ORDER BY l.created_at DESC
             LIMIT ?'
        );
        if ($stmt === false) return [];
        $stmt->bind_param('iiii', $userId, $userId, $userId, $limit);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Fetch notifications for a user.
 *
 * @return list<array<string,mixed>>
 */
function app_get_notifications(mysqli $conn, int $userId, int $limit = 100): array
{
    $stmt = $conn->prepare(
        'SELECT * FROM notifications
         WHERE recipient_userId = ?
         ORDER BY created_at DESC
         LIMIT ?'
    );
    if ($stmt === false) return [];
    $stmt->bind_param('ii', $userId, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    return $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
}

/**
 * Count unread notifications for a user (for the sidebar badge).
 */
function app_count_unread_notifications(mysqli $conn, int $userId): int
{
    $stmt = $conn->prepare(
        'SELECT COUNT(*) FROM notifications WHERE recipient_userId = ? AND is_read = 0'
    );
    if ($stmt === false) return 0;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    return (int) $count;
}

/**
 * Mark all notifications as read for a user.
 */
function app_mark_notifications_read(mysqli $conn, int $userId): void
{
    $stmt = $conn->prepare(
        'UPDATE notifications SET is_read = 1 WHERE recipient_userId = ? AND is_read = 0'
    );
    if ($stmt === false) return;
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}