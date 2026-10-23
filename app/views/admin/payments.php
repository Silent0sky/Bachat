<?php
/** Payment verification (society). Vars: $rows, $fStatus, $verifier */
$statuses = ['submitted' => 'To verify', 'verified' => 'Verified', 'pending' => 'Pending', 'failed' => 'Failed', 'refunded' => 'Refunded', 'all' => 'All'];
$locked = $verifier === 'super_admin';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="section-title mb-1">Payment Verification</h1>
    <p class="text-muted small mb-0">Verify UPI screenshots / transaction references submitted by your residents.</p>
  </div>
  <?php if ($locked): ?>
    <span class="b-badge-amber"><i data-lucide="lock" width="13" height="13"></i>&nbsp;Verification reserved to platform admin</span>
  <?php endif; ?>
</div>

<?php if ($locked): ?>
  <div class="b-note mb-3">
    <i data-lucide="info"></i>
    <span>This society is configured so the <strong>platform (super) admin</strong> verifies payments. You can review submissions below, but verification buttons are disabled. Change this in Society Settings → "Payment verified by".</span>
  </div>
<?php endif; ?>

<div class="d-flex flex-wrap gap-2 mb-3">
  <?php foreach ($statuses as $k => $lbl): ?>
    <a class="chip <?= $fStatus === $k ? 'chip-active' : '' ?>" href="<?= url('/admin/payments?status=' . $k) ?>"><?= e($lbl) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($rows === []): ?>
  <div class="b-card"><div class="empty-state"><i data-lucide="badge-check"></i><p>Nothing here — no <?= e($statuses[$fStatus] ?? $fStatus) ?> payments for your society.</p></div></div>
<?php else: ?>
<div class="b-table fade-up">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Ref</th><th>Paid by</th><th>Method</th><th>Amount</th><th>UTR / Ref</th><th>Proof</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
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
              <?php if ($p['status'] === 'submitted' && !$locked): ?>
                <div class="d-inline-flex flex-column gap-1 align-items-end">
                  <form method="post" action="<?= url('/admin/payments/' . $p['id'] . '/verify') ?>" data-confirm="Verify this payment of <?= e(money($p['amount'])) ?>?">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-brand">Verify</button>
                  </form>
                  <form method="post" action="<?= url('/admin/payments/' . $p['id'] . '/reject') ?>" class="d-flex gap-1" data-confirm="Reject this payment? The resident will be notified with your reason.">
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
