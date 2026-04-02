<?php
/** @var array $activityLogs */
/** @var string $role */

// Severity → pill class mapping (reuses existing pill classes where possible).
$severityPill = [
    'info'     => 'completed',   // green
    'warning'  => 'pending',     // amber
    'critical' => 'cancelled',   // red
];
?>
<div class="table-card">
    <div class="table-card-header">
        <h5>
            <?= $role === 'admin' ? 'System Activity Logs' : 'Your Activity Logs' ?>
            <span class="badge-count"><?= count($activityLogs) ?></span>
        </h5>
        <?php if ($role === 'seller'): ?>
            <span style="font-size:.8rem;color:var(--text-muted,#6c757d);">
                Showing actions by you and changes to your products &amp; orders.
            </span>
        <?php endif; ?>
    </div>
    <div class="table-card-body table-responsive">
        <?php if (!$activityLogs): ?>
            <div class="p-4 text-secondary" style="font-size:.9rem;">
                <i class="fa-solid fa-clock-rotate-left me-2 opacity-50"></i>
                No activity recorded yet.
            </div>
        <?php else: ?>
            <table id="activityLogsTable" class="table table-sm w-100 mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <?php if ($role === 'admin'): ?>
                            <th>Actor</th>
                        <?php endif; ?>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Description</th>
                        <th>Severity</th>
                        <th>When</th>
                        <?php if ($role === 'admin'): ?>
                            <th>Context</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($activityLogs as $log): ?>
                    <tr>
                        <td><?= (int) $log['logId'] ?></td>
                        <?php if ($role === 'admin'): ?>
                            <td><?= app_sanitize(
                                trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''))
                                ?: ($log['actor_username'] ?? '#' . $log['actor_userId'])
                            ) ?></td>
                        <?php endif; ?>
                        <td><code style="font-size:.78rem;"><?= app_sanitize($log['action']) ?></code></td>
                        <td>
                            <?php if (!empty($log['entity_type'])): ?>
                                <span class="pill <?= app_sanitize($log['entity_type']) ?>" style="text-transform:capitalize;">
                                    <?= app_sanitize($log['entity_type']) ?>
                                    <?= !empty($log['entity_id']) ? '#' . (int) $log['entity_id'] : '' ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted" style="font-size:.8rem;">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="max-width:260px;"><?= app_sanitize($log['description']) ?></td>
                        <td>
                            <span class="pill <?= $severityPill[$log['severity']] ?? 'completed' ?>">
                                <?= app_sanitize(ucfirst($log['severity'])) ?>
                            </span>
                        </td>
                        <td style="white-space:nowrap;">
                            <?= app_sanitize(date('M j, Y · g:i a', strtotime($log['created_at']))) ?>
                        </td>
                        <?php if ($role === 'admin'): ?>
                            <td>
                                <?php if (!empty($log['context'])): ?>
                                    <?php $ctx = json_decode($log['context'], true); ?>
                                    <?php if (is_array($ctx)): ?>
                                        <button class="edit-btn" type="button"
                                            style="font-size:.75rem;padding:.2rem .55rem;"
                                            onclick="alert(<?= htmlspecialchars(json_encode(
                                                json_encode($ctx, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                                                JSON_UNESCAPED_UNICODE
                                            )) ?>)">
                                            View
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted" style="font-size:.8rem;">—</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted" style="font-size:.8rem;">—</span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>