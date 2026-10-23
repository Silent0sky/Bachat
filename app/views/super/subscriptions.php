<?php
/** Society subscriptions. Vars: $subs, $expired, $societies */
$activeCount = array_sum(array_map(static fn($s) => $s['status'] === 'active' ? 1 : 0, $subs));
$monthRunRate = array_sum(array_map(static function ($s) {
    if ($s['status'] !== 'active') return 0.0;
    return $s['plan'] === 'yearly' ? (float) $s['amount'] / 12 : (float) $s['amount'];
}, $subs));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="section-title mb-1">Society Subscriptions</h1>
    <p class="text-muted small mb-0">SaaS plans per society — this is platform revenue, never mix it with sales.</p>
  </div>
  <a class="btn btn-brand b-btn-pill" href="#sub-form"><i data-lucide="plus" class="me-1"></i> Activate subscription</a>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-4 col-xl-3">
    <div class="b-card stat-card h-100 fade-up">
      <div class="stat-label">Active subscriptions</div>
      <div class="stat-value"><?= (int) $activeCount ?></div>
      <div class="stat-icon bg-brand-soft"><i data-lucide="credit-card" class="text-brand"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-3">
    <div class="b-card stat-card h-100 fade-up d1">
      <div class="stat-label">Monthly run-rate</div>
      <div class="stat-value"><?= money($monthRunRate) ?></div>
      <div class="stat-icon bg-brand-soft"><i data-lucide="trending-up" class="text-brand"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-3">
    <div class="b-card stat-card h-100 fade-up d2">
      <div class="stat-label">Expired (to renew)</div>
      <div class="stat-value"><?= count($expired) ?></div>
      <div class="stat-icon" style="background:#FDF3DC"><i data-lucide="clock-4" style="color:#8A6410"></i></div>
    </div>
  </div>
</div>

<div class="b-card mb-4 fade-up" id="sub-form">
  <h2 class="h6 fw-bold text-brand mb-3">Activate / record a subscription</h2>
  <form method="post" action="<?= url('/super/subscriptions') ?>" class="row g-3">
    <?= csrf_field() ?>
    <div class="col-md-4">
      <label class="form-label fw-semibold">Society *</label>
      <select class="form-select" name="society_id" required>
        <option value="">— select —</option>
        <?php foreach ($societies as $s): ?>
          <option value="<?= (int) $s['id'] ?>" data-plan="<?= e($s['subscription_plan']) ?>" data-amount="<?= e($s['subscription_amount']) ?>">
            <?= e($s['name']) ?> (<?= e(ucfirst($s['subscription_plan'])) ?> · <?= money($s['subscription_amount']) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label fw-semibold">Plan *</label>
      <select class="form-select" name="plan" id="subPlan">
        <option value="monthly">Monthly</option>
        <option value="yearly">Yearly</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label fw-semibold">Amount (₹) *</label>
      <input class="form-control" type="number" step="0.01" min="0" name="amount" id="subAmount" required>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label fw-semibold">Starts</label>
      <input class="form-control" type="date" name="starts_at" value="<?= date('Y-m-d') ?>">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label fw-semibold">Ends <span class="text-muted small">(auto)</span></label>
      <input class="form-control" type="date" name="ends_at">
    </div>
    <div class="col-md-6">
      <label class="form-label fw-semibold">Note</label>
      <input class="form-control" name="note" maxlength="255" placeholder="e.g. UPI ref 40213…">
    </div>
    <div class="col-md-6 d-flex align-items-end">
      <button class="btn btn-brand b-btn-pill">Activate (marks paid today)</button>
    </div>
  </form>
</div>

<?php if ($subs === []): ?>
  <div class="b-card"><div class="empty-state"><i data-lucide="credit-card"></i><p>No subscriptions recorded yet.</p></div></div>
<?php else: ?>
<div class="b-table mb-4 fade-up">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Society</th><th>Plan</th><th>Amount</th><th>Period</th><th>Paid at</th><th>Status</th><th>Note</th></tr></thead>
      <tbody>
        <?php foreach ($subs as $sb): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= e($sb['society_name']) ?></div>
              <div class="small text-muted"><?= e($sb['society_code']) ?></div>
            </td>
            <td class="text-capitalize"><?= e($sb['plan']) ?></td>
            <td class="fw-semibold"><?= money($sb['amount']) ?></td>
            <td class="small text-muted"><?= e(human_date($sb['starts_at'])) ?> → <?= e(human_date($sb['ends_at'])) ?></td>
            <td class="small"><?= e(human_dt($sb['paid_at'])) ?></td>
            <td><span class="<?= e(status_badge($sb['status'])) ?>"><?= e(str_replace('_', ' ', $sb['status'])) ?></span></td>
            <td class="small text-muted"><?= e($sb['note'] ?? '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if ($expired !== []): ?>
<h2 class="h6 fw-bold text-brand mb-2">Expired subscriptions</h2>
<div class="b-table fade-up">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Society</th><th>Plan</th><th>Amount</th><th>Ended</th><th>Status</th></tr></thead>
      <tbody>
        <?php foreach ($expired as $sb): ?>
          <tr>
            <td class="fw-semibold"><?= e($sb['society_name']) ?></td>
            <td class="text-capitalize"><?= e($sb['plan']) ?></td>
            <td><?= money($sb['amount']) ?></td>
            <td class="small text-muted"><?= e(human_date($sb['ends_at'])) ?></td>
            <td><span class="<?= e(status_badge($sb['status'])) ?>"><?= e($sb['status']) ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
