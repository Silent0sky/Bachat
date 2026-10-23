<?php
/** Audit logs. Vars: $rows, $q */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="section-title mb-1">Audit Logs</h1>
    <p class="text-muted small mb-0">Latest 200 sensitive actions. Passwords are never logged.</p>
  </div>
  <form method="get" action="<?= url('/super/audit') ?>" class="d-flex gap-2">
    <div class="input-icon">
      <i data-lucide="search"></i>
      <input class="form-control" name="q" placeholder="Filter by action or entity…" value="<?= e($q) ?>">
    </div>
    <button class="btn btn-forest">Filter</button>
    <?php if ($q !== ''): ?><a class="btn btn-soft" href="<?= url('/super/audit') ?>">Clear</a><?php endif; ?>
  </form>
</div>

<?php if ($rows === []): ?>
  <div class="b-card"><div class="empty-state"><i data-lucide="scroll-text"></i><p>No audit entries found.</p></div></div>
<?php else: ?>
<div class="b-table fade-up">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>IP</th><th style="min-width:260px">Details</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $a):
          $pretty = '';
          if (!empty($a['details'])) {
              $decoded = json_decode((string) $a['details'], true);
              $pretty = $decoded === null ? (string) $a['details'] : (string) json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
          }
        ?>
          <tr>
            <td class="small text-muted text-nowrap"><?= e(human_dt($a['created_at'])) ?></td>
            <td class="small">
              <?php if ($a['user_name']): ?>
                <span class="fw-semibold"><?= e($a['user_name']) ?></span><div class="text-muted"><?= e($a['user_email']) ?></div>
              <?php else: ?><span class="text-muted">system</span><?php endif; ?>
            </td>
            <td><span class="b-badge-blue"><?= e($a['action']) ?></span></td>
            <td class="small"><?= e($a['entity_type'] ?? '—') ?><?= $a['entity_id'] !== null ? ' #' . (int) $a['entity_id'] : '' ?></td>
            <td class="small text-muted"><?= e($a['ip'] ?? '—') ?></td>
            <td>
              <?php if ($pretty !== ''): ?>
                <details>
                  <summary class="small text-brand" style="cursor:pointer">view details</summary>
                  <pre class="small bg-brand-soft rounded-3 p-2 mt-1 mb-0" style="white-space:pre-wrap;max-height:200px;overflow:auto"><?= e($pretty) ?></pre>
                </details>
              <?php else: ?>
                <span class="text-muted small">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
