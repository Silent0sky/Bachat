<?php
/** Commissions (platform revenue). Vars: $rows, $totals, $societies */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="section-title mb-1">Commissions</h1>
    <p class="text-muted small mb-0">Platform earnings from society orders &amp; bookings — this is real revenue, not sales.</p>
  </div>
  <a class="btn btn-brand b-btn-pill" href="#comm-form"><i data-lucide="plus" class="me-1"></i> Add entry</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="b-card stat-card h-100 fade-up">
      <div class="stat-label">Due (not yet recorded)</div>
      <div class="stat-value"><?= money($totals['due']) ?></div>
      <div class="stat-icon" style="background:#FDF3DC"><i data-lucide="hourglass" style="color:#8A6410"></i></div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="b-card stat-card h-100 fade-up d1">
      <div class="stat-label">Recorded</div>
      <div class="stat-value"><?= money($totals['recorded']) ?></div>
      <div class="stat-icon bg-brand-soft"><i data-lucide="check-circle-2" class="text-brand"></i></div>
    </div>
  </div>
</div>

<div class="b-card mb-4 fade-up" id="comm-form">
  <h2 class="h6 fw-bold text-brand mb-3">Add a commission entry</h2>
  <form method="post" action="<?= url('/super/commissions') ?>" class="row g-3">
    <?= csrf_field() ?>
    <div class="col-md-3">
      <label class="form-label fw-semibold">Society *</label>
      <select class="form-select" name="society_id" required>
        <option value="">— select —</option>
        <?php foreach ($societies as $s): ?>
          <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label fw-semibold">Order ID <span class="text-muted small">(optional)</span></label>
      <input class="form-control" type="number" min="1" name="order_id">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label fw-semibold">Booking ID <span class="text-muted small">(optional)</span></label>
      <input class="form-control" type="number" min="1" name="booking_id">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label fw-semibold">Type</label>
      <select class="form-select" name="type">
        <option value="percent">Percent</option>
        <option value="fixed">Fixed</option>
      </select>
    </div>
    <div class="col-6 col-md-1">
      <label class="form-label fw-semibold">Rate</label>
      <input class="form-control" type="number" step="0.01" min="0" name="rate">
    </div>
    <div class="col-6 col-md-1">
      <label class="form-label fw-semibold">Amount ₹ *</label>
      <input class="form-control" type="number" step="0.01" min="0" name="amount" required>
    </div>
    <div class="col-6 col-md-1">
      <label class="form-label fw-semibold">Status</label>
      <select class="form-select" name="status">
        <option value="due">Due</option>
        <option value="recorded">Recorded</option>
      </select>
    </div>
    <div class="col-12">
      <button class="btn btn-brand b-btn-pill">Record commission</button>
    </div>
  </form>
  <div class="b-note mt-3">
    <i data-lucide="info"></i>
    <span>Enter the internal numeric ID of the order or booking (visible in the orders/bookings tables). Leave both empty for a manual adjustment.</span>
  </div>
</div>

<?php if ($rows === []): ?>
  <div class="b-card"><div class="empty-state"><i data-lucide="percent"></i><p>No commission entries yet.</p></div></div>
<?php else: ?>
<div class="b-table fade-up">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Society</th><th>Ref</th><th>Type / Rate</th><th>Amount</th><th>Status</th><th>Created</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="fw-semibold"><?= e($r['society_name']) ?></td>
            <td class="small">
              <?php if ($r['order_no']): ?>Order <?= e($r['order_no']) ?>
              <?php elseif ($r['booking_no']): ?>Booking <?= e($r['booking_no']) ?>
              <?php else: ?><span class="text-muted">manual</span><?php endif; ?>
            </td>
            <td class="small"><?= e(ucfirst($r['type'])) ?><?= (float) $r['rate'] > 0 ? ' · ' . qty($r['rate']) . ($r['type'] === 'percent' ? '%' : '') : '' ?></td>
            <td class="fw-semibold"><?= money($r['amount']) ?></td>
            <td><span class="<?= $r['status'] === 'recorded' ? 'b-badge-green' : 'b-badge-amber' ?>"><?= e($r['status']) ?></span></td>
            <td class="small text-muted"><?= e(human_date($r['created_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
