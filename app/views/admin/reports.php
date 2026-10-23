<?php
/** Society reports. Vars: $from, $to, $revSeries, $topProducts, $byMethod, $bookingsByStatus, $savings, $grossSales */
$exportQS = 'from=' . urlencode($from) . '&to=' . urlencode($to);
$verifiedTotal = array_sum(array_map(static fn($r) => (float) $r['verified_amt'], $byMethod));
$bookingsTotal = array_sum(array_map(static fn($r) => (int) $r['c'], $bookingsByStatus));
$bookingsCancelled = 0;
foreach ($bookingsByStatus as $bs) {
    if ($bs['status'] === 'cancelled') {
        $bookingsCancelled = (int) $bs['c'];
    }
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="section-title mb-1">Society Reports</h1>
    <p class="text-muted small mb-0"><?= e(human_date($from)) ?> — <?= e(human_date($to)) ?></p>
  </div>
  <div class="d-flex flex-wrap gap-2 no-print">
    <button class="btn btn-soft b-btn-pill" onclick="window.print()"><i data-lucide="printer" class="me-1"></i> Print</button>
    <?php foreach (['orders', 'bookings', 'payments', 'members'] as $t): ?>
      <a class="btn btn-soft b-btn-pill" href="<?= url('/admin/reports/export?type=' . $t . '&' . $exportQS) ?>"><i data-lucide="download" class="me-1"></i> <?= e(ucfirst($t)) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="b-card mb-4 no-print fade-up">
  <form method="get" action="<?= url('/admin/reports') ?>" class="row g-2 align-items-end">
    <div class="col-6 col-md-4">
      <label class="form-label small fw-semibold">From</label>
      <input class="form-control" type="date" name="from" value="<?= e($from) ?>">
    </div>
    <div class="col-6 col-md-4">
      <label class="form-label small fw-semibold">To</label>
      <input class="form-control" type="date" name="to" value="<?= e($to) ?>">
    </div>
    <div class="col-12 col-md-4 d-grid">
      <button class="btn btn-forest">Apply range</button>
    </div>
  </form>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-4">
    <div class="b-card stat-card h-100 fade-up">
      <div class="stat-label">Gross sales (not profit)</div>
      <div class="stat-value"><?= money($grossSales) ?></div>
      <div class="stat-icon bg-brand-soft"><i data-lucide="shopping-cart" class="text-brand"></i></div>
      <div class="small text-muted mt-1">excludes cancelled orders</div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="b-card stat-card h-100 fade-up d1">
      <div class="stat-label">Verified payments</div>
      <div class="stat-value"><?= money($verifiedTotal) ?></div>
      <div class="stat-icon bg-brand-soft"><i data-lucide="badge-indian-rupee" class="text-brand"></i></div>
      <div class="small text-muted mt-1">all methods in range</div>
    </div>
  </div>
  <div class="col-6 col-md-4">
    <div class="b-card stat-card h-100 fade-up d2">
      <div class="stat-label">Group-buy savings delivered</div>
      <div class="stat-value"><?= money($savings) ?></div>
      <div class="stat-icon bg-brand-soft"><i data-lucide="piggy-bank" class="text-brand"></i></div>
      <div class="small text-muted mt-1">campaign orders vs reference price</div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-7">
    <div class="b-card h-100 fade-up">
      <h2 class="h6 fw-bold mb-3 text-brand">Revenue per day</h2>
      <div class="chart-box"><canvas id="revChart"></canvas></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="b-card h-100 fade-up d1">
      <h2 class="h6 fw-bold mb-3 text-brand">Top products by qty</h2>
      <?php if ($topProducts === []): ?>
        <div class="empty-state"><i data-lucide="package-open"></i><p>No sales in this period.</p></div>
      <?php else: ?>
        <div class="chart-box"><canvas id="topChart"></canvas></div>
        <div class="b-table mt-3">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Product</th><th>Qty</th><th>Revenue</th></tr></thead>
            <tbody>
              <?php foreach ($topProducts as $tp): ?>
                <tr><td class="small"><?= e($tp['product_name']) ?></td><td class="small"><?= e(qty($tp['qty'])) ?></td><td class="small fw-semibold"><?= money($tp['revenue']) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="b-card h-100 fade-up">
      <h2 class="h6 fw-bold mb-3 text-brand">Payment methods (verified amounts)</h2>
      <?php if ($byMethod === []): ?>
        <div class="empty-state"><i data-lucide="wallet"></i><p>No payments in this period.</p></div>
      <?php else: ?>
        <div class="chart-box"><canvas id="payChart"></canvas></div>
        <div class="b-table mt-3">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Method</th><th>Payments</th><th>Verified ₹</th></tr></thead>
            <tbody>
              <?php foreach ($byMethod as $bm): ?>
                <tr><td class="small text-uppercase"><?= e($bm['method']) ?></td><td class="small"><?= (int) $bm['c'] ?></td><td class="small fw-semibold"><?= money($bm['verified_amt']) ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="b-card h-100 fade-up d1">
      <h2 class="h6 fw-bold mb-3 text-brand">Service bookings by status</h2>
      <?php if ($bookingsByStatus === []): ?>
        <div class="empty-state"><i data-lucide="calendar-check"></i><p>No bookings in this period.</p></div>
      <?php else: ?>
        <div class="chart-box"><canvas id="bookChart"></canvas></div>
        <div class="b-table mt-3">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Status</th><th>Bookings</th></tr></thead>
            <tbody>
              <?php foreach ($bookingsByStatus as $bs): ?>
                <tr><td><span class="<?= e(status_badge((string) $bs['status'])) ?>"><?= e(ucfirst(str_replace('_', ' ', (string) $bs['status']))) ?></span></td><td class="fw-semibold"><?= (int) $bs['c'] ?></td></tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php if ($bookingsCancelled > 0): ?>
          <p class="small text-muted mb-0 mt-2"><?= (int) $bookingsCancelled ?> of <?= $bookingsTotal ?> bookings were cancelled.</p>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php
$revLabels = json_encode(array_map(static fn($r) => date('d M', strtotime($r['d'])), $revSeries));
$revVals   = json_encode(array_map(static fn($r) => round((float) $r['v'], 2), $revSeries));
$topL      = json_encode(array_map(static fn($r) => $r['product_name'], $topProducts));
$topV      = json_encode(array_map(static fn($r) => (float) $r['qty'], $topProducts));
$payL      = json_encode(array_map(static fn($r) => strtoupper((string) $r['method']), $byMethod));
$payV      = json_encode(array_map(static fn($r) => round((float) $r['verified_amt'], 2), $byMethod));
$bookL     = json_encode(array_map(static fn($r) => ucfirst(str_replace('_', ' ', (string) $r['status'])), $bookingsByStatus));
$bookV     = json_encode(array_map(static fn($r) => (int) $r['c'], $bookingsByStatus));
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof Chart === 'undefined') return;
  var G = '#28A66A', F = '#16452F', A = '#F4B942', LG = '#7CC98F', DA = '#E5A72E';
  var palette = [G, A, LG, F, DA, '#5BB98C', '#C58BD9', '#7FA8E8'];
  var base = { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } };

  var rc = document.getElementById('revChart');
  if (rc) new Chart(rc, { type: 'line', data: { labels: <?= $revLabels ?>, datasets: [{ label: 'Sales ₹', data: <?= $revVals ?>, borderColor: G, backgroundColor: 'rgba(40,166,106,.12)', fill: true, tension: .35, pointRadius: 2, borderWidth: 2 }] }, options: base });

  var tc = document.getElementById('topChart');
  if (tc) new Chart(tc, { type: 'doughnut', data: { labels: <?= $topL ?>, datasets: [{ data: <?= $topV ?>, backgroundColor: palette, borderWidth: 2, borderColor: '#fff' }] },
    options: { maintainAspectRatio: false, cutout: '58%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } } } });

  var pc = document.getElementById('payChart');
  if (pc) new Chart(pc, { type: 'doughnut', data: { labels: <?= $payL ?>, datasets: [{ data: <?= $payV ?>, backgroundColor: [G, A, F], borderWidth: 2, borderColor: '#fff' }] },
    options: { maintainAspectRatio: false, cutout: '58%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } } } });

  var bc = document.getElementById('bookChart');
  if (bc) new Chart(bc, { type: 'bar', data: { labels: <?= $bookL ?>, datasets: [{ label: 'Bookings', data: <?= $bookV ?>, backgroundColor: [G, A, LG, F, DA, '#5BB98C', '#C58BD9'], borderRadius: 6, maxBarThickness: 42 }] }, options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } } });
});
</script>
