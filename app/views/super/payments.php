<?php
/** Payment verification (platform). Vars: $rows, $fStatus, $fSociety, $societies */
$statuses = ['submitted' => 'To verify', 'verified' => 'Verified', 'pending' => 'Pending', 'failed' => 'Failed', 'refunded' => 'Refunded', 'all' => 'All'];
?>
<div class="mb-4">
  <h1 class="section-title mb-2">Payment Verification</h1>
  <div class="d-flex flex-wrap gap-2 align-items-center">
    <?php foreach ($statuses as $k => $lbl): ?>
      <a class="chip <?= $fStatus === $k ? 'chip-active' : '' ?>" href="<?= url('/super/payments?status=' . $k . ($fSociety !== null ? '&society=' . $fSociety : '')) ?>"><?= e($lbl) ?></a>
    <?php endforeach; ?>
    <form method="get" action="<?= url('/super/payments') ?>" class="d-flex gap-2 ms-auto">
      <input type="hidden" name="status" value="<?= e($fStatus) ?>">
      <select class="form-select form-select-sm" name="society" onchange="this.form.submit()">
        <option value="">All societies</option>
        <?php foreach ($societies as $s): ?>
          <option value="<?= (int) $s['id'] ?>" <?= (string) $fSociety === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</div>

<?php if ($rows === []): ?>
  <div class="b-card"><div class="empty-state"><i data-lucide="badge-check"></i><p>Nothing here — no <?= e($statuses[$fStatus] ?? strtolower($fStatus)) ?> payments.</p></div></div>
<?php else: ?>
<div class="b-table fade-up">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Ref</th><th>Paid by</th><th>Society</th><th>Method</th><th>Amount</th><th>UTR / Ref</th><th>Proof</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $p): ?>
          <tr>
            <td class="small">
              <?php if ($p['order_no']): ?><span class="fw-semibold"><?= e($p['order_no']) ?></span><div class="text-muted">order</div>
              <?php elseif ($p['booking_no']): ?><span class="fw-semibold"><?= e($p['booking_no']) ?></span><div class="text-muted">booking</div>
              <?php else: ?>—<?php endif; ?>
              <div class="text-muted"><?= e(human_dt($p['created_at'])) ?></div>
            </td>
            <td class="small"><?= e($p['payer_name']) ?><div class="text-muted"><?= e($p['payer_email']) ?></div></td>
            <td class="small"><?= e($p['society_name'] ?? '—') ?></td>
            <td><span class="b-badge-grey"><?= e(strtoupper($p['method'])) ?></span></td>
            <td class="fw-semibold"><?= money($p['amount']) ?></td>
            <td class="small text-muted"><?= e($p['utr'] ?? $p['txn_ref'] ?? '—') ?></td>
            <td class="small">
              <?php if (!empty($p['proof_image'])): ?>
                <a class="btn btn-sm btn-soft" href="<?= e(upload_url($p['proof_image'])) ?>" target="_blank" rel="noopener">View</a>
              <?php else: ?>—<?php endif; ?>
            </td>
            <td><span class="<?= e(status_badge($p['status'])) ?>"><?= e($p['status']) ?></span>
              <?php if ($p['verified_at']): ?><div class="small text-muted"><?= e(human_dt($p['verified_at'])) ?></div><?php endif; ?>
            </td>
            <td class="text-end">
              <?php if ($p['status'] === 'submitted'): ?>
                <div class="d-inline-flex flex-column gap-1 align-items-end">
                  <form method="post" action="<?= url('/super/payments/' . $p['id'] . '/verify') ?>" data-confirm="Verify this payment of <?= e(money($p['amount'])) ?>?">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-brand">Verify</button>
                  </form>
                  <form method="post" action="<?= url('/super/payments/' . $p['id'] . '/reject') ?>" class="d-flex gap-1" data-confirm="Reject this payment? The resident will be notified with your reason.">
                    <?= csrf_field() ?>
                    <input class="form-control form-control-sm" name="reason" placeholder="reason" maxlength="120" style="width:110px">
                    <button class="btn btn-sm btn-outline-brand text-danger">Reject</button>
                  </form>
                </div>
              <?php else: ?>
                <span class="small text-muted">—</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
