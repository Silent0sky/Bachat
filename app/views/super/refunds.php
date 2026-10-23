<?php
/** Refunds. Vars: $rows, $orders, $paymentsList */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="section-title mb-1">Refunds</h1>
    <p class="text-muted small mb-0">Record processed refunds against orders and verified payments.</p>
  </div>
  <a class="btn btn-brand b-btn-pill" href="#refund-form"><i data-lucide="plus" class="me-1"></i> Record refund</a>
</div>

<div class="b-card mb-4 fade-up" id="refund-form">
  <h2 class="h6 fw-bold text-brand mb-3">Record a processed refund</h2>
  <form method="post" action="<?= url('/super/refunds') ?>" class="row g-3">
    <?= csrf_field() ?>
    <div class="col-md-3">
      <label class="form-label fw-semibold">Payment <span class="text-muted small">(verified payments)</span></label>
      <select class="form-select" name="payment_id">
        <option value="">— none —</option>
        <?php foreach ($paymentsList as $pl): ?>
          <option value="<?= (int) $pl['id'] ?>">#<?= (int) $pl['id'] ?> · <?= e($pl['order_no'] ?? '—') ?> · <?= money($pl['amount']) ?></option>
        <?php endforeach; ?>
      </select>
      <div class="form-text">Marking a payment refunds it (payment status → refunded).</div>
    </div>
    <div class="col-md-3">
      <label class="form-label fw-semibold">Order ID <span class="text-muted small">(if no payment chosen)</span></label>
      <select class="form-select" name="order_id">
        <option value="">— select order —</option>
        <?php foreach ($orders as $o): ?>
          <option value="<?= (int) $o['id'] ?>"><?= e($o['order_no']) ?> · <?= money($o['total']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label fw-semibold">Amount ₹ *</label>
      <input class="form-control" type="number" step="0.01" min="0.01" name="amount" required>
    </div>
    <div class="col-md-4">
      <label class="form-label fw-semibold">Reason</label>
      <input class="form-control" name="reason" maxlength="255" placeholder="e.g. item out of stock, cancelled before dispatch">
    </div>
    <div class="col-12">
      <button class="btn btn-brand b-btn-pill">Record refund (status: processed)</button>
    </div>
  </form>
</div>

<?php if ($rows === []): ?>
  <div class="b-card"><div class="empty-state"><i data-lucide="undo-2"></i><p>No refunds recorded yet.</p></div></div>
<?php else: ?>
<div class="b-table fade-up">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Order</th><th>Amount</th><th>Reason</th><th>Linked payment</th><th>Status</th><th>Processed by</th><th>Date</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="fw-semibold"><?= e($r['order_no'] ?? '—') ?></td>
            <td class="fw-semibold"><?= money($r['amount']) ?></td>
            <td class="small text-muted"><?= e($r['reason'] ?? '') ?></td>
            <td class="small"><?= $r['payment_id'] ? '#' . (int) $r['payment_id'] . ($r['paid_amount'] !== null ? ' (' . money($r['paid_amount']) . ')' : '') : '—' ?></td>
            <td><span class="<?= e(status_badge($r['status'])) ?>"><?= e($r['status']) ?></span></td>
            <td class="small"><?= e($r['processed_by_name'] ?? 'system') ?></td>
            <td class="small text-muted"><?= e(human_dt($r['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
