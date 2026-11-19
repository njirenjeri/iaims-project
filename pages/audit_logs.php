<tbody>
    <?php if(count($logs) > 0): ?>
        <?php foreach ($logs as $log): ?>
        <tr>
            <!-- Using your exact column names here -->
            <td class="text-muted small">
                <?= date('M d, Y - h:i A', strtotime($log['occurred_at'])) ?>
            </td>
            <td>
                <span class="badge bg-secondary">#<?= htmlspecialchars($log['actor_user_id']) ?></span>
            </td>
            <td class="fw-bold text-dark">
                <?= htmlspecialchars($log['actor_email']) ?> 
                <small class="text-muted">(<?= htmlspecialchars($log['actor_roles']) ?>)</small>
            </td>
            <td><?= htmlspecialchars($log['action']) ?></td>
            <td>
                <span class="badge bg-<?= $log['outcome'] == 'Success' ? 'success' : 'danger' ?> bg-opacity-10 text-<?= $log['outcome'] == 'Success' ? 'success' : 'danger' ?> border border-<?= $log['outcome'] == 'Success' ? 'success' : 'danger' ?> rounded-pill">
                    <?= htmlspecialchars($log['outcome']) ?>
                </span>
            </td>
            <td class="text-muted small"><code><?= htmlspecialchars($log['ip_address']) ?></code></td>
        </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="6" class="text-center py-4 text-muted">No audit logs found.</td>
        </tr>
    <?php endif; ?>
</tbody>