<?php
/** Platform reports. Vars: $from, $to, $fSociety, $societies, $revSeries, $bySociety,
 *  $topProducts, $byMethod, $commissionTotals, $subRevenue, $grossSales */
$exportQS = 'from=' . urlencode($from) . '&to=' . urlencode($to) . ($fSociety !== null ? '&society=' . $fSociety : '');
$totalCommission = array_sum(array_map(static fn($r) => (float) $r['total'], $commissionTotals));
$totalSubs = array_sum(array_map(static fn($r) => (float) $r['total'], $subRevenue));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="section-title mb-1">Platform Reports</h1>
    <p class="text-muted small mb-0"><?= e(human_date($from)) ?> — <?= e(human_date($to)) ?><?= $fSociety !== null ? ' · filtered to one society' : ' · all societies' ?></p>
  </div>
  <div class="d-flex flex-wrap gap-2 no-print">
    <button class="btn btn-soft b-btn-pill" onclick="window.print()"><i data-lucide="printer" class="me-1"></i> Print</button>
    <?php foreach (['orders', 'users', 'products', 'commissions', 'payments'] as $t): ?>
      <a class="btn btn-soft b-btn-pill" href="<?= url('/super/reports/export?type=' . $t . '&' . $exportQS) ?>"><i data-lucide="download" class="me-1"></i> <?= e(ucfirst($t)) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<div class="b-card mb-4 no-print fade-up">
  <form method="get" action="<?= url('/super/reports') ?>" class="row g-2 align-items-end">
    <div class="col-6 col-md-3">
      <label class="form-label small fw-semibold">From</label>
      <input class="form-control" type="date" name="from" value="<?= e($from) ?>">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label small fw-semibold">To</label>
      <input class="form-control" type="date" name="to" value="<?= e($to) ?>">
    </div>
    <div class="col-8 col-md-4">
      <label class="form-label small fw-semibold">Society</label>
      <select class="form-select" name="society">
        <option value="">All societies</option>
        <?php foreach ($societies as $s): ?>
          <option value="<?= (int) $s['id'] ?>" <?= (string) $fSociety === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-4 col-md-2 d-grid">
      <button class="btn btn-forest">Apply</button>
    </div>
  </form>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-4 col-xl-3">
    <div class="b-card stat-card h-100 fade-up">
      <div class="stat-label">Gross sales (not profit)</div>
      <div class="stat-value"><?= money($grossSales) ?></div>
      <div class="stat-icon bg-brand-soft"><i data-lucide="shopping-cart" class="text-brand"></i></div>
      <div class="small text-muted mt-1">excludes cancelled orders</div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-3">
    <div class="b-card stat-card h-100 fade-up d1">
      <div class="stat-label">Commissions (platform revenue)</div>
      <div class="stat-value"><?= money($totalCommission) ?></div>
      <div class="stat-icon bg-brand-soft"><i data-lucide="percent" class="text-brand"></i></div>
    </div>
  </div>
  <div class="col-6 col-md-4 col-xl-3">
    <div class="b-card stat-card h-100 fade-up d2">
      <div class="stat-label">Subscription revenue</div>
      <div class="stat-value"><?= money($totalSubs) ?></div>
      <div class="stat-icon bg-brand-soft"><i data-lucide="credit-card" class="text-brand"></i></div>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="b-card h-100 fade-up">
      <h2 class="h6 fw-bold mb-3 text-brand">Revenue per day</h2>
      <div class="chart-box"><canvas id="revChart"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="b-card h-100 fade-up d1">
      <h2 class="h6 fw-bold mb-3 text-brand">Top products by qty</h2>
      <?php if ($topProducts === []): ?>
        <div class="empty-state"><i data-lucide="package-open"></i><p>No sales in this period.</p></div>
      <?php else: ?>
        <div class="chart-box"><canvas id="topChart"></canvas></div>
      <?php endif; ?>
      <?php if ($topProducts !== []): ?>
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

<div class="row g-4 mb-4">
  <div class="col-lg-6">
    <div class="b-card h-100 fade-up">
      <h2 class="h6 fw-bold mb-3 text-brand">Orders by society</h2>
      <div class="chart-box"><canvas id="socChart"></canvas></div>
      <div class="b-table mt-3">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Society</th><th>Orders</th><th>Gross sales</th></tr></thead>
          <tbody>
            <?php foreach ($bySociety as $bs): ?>
              <tr><td class="small"><?= e($bs['name']) ?></td><td class="small"><?= (int) $bs['c'] ?></td><td class="small fw-semibold"><?= money($bs['rev']) ?></td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="b-card h-100 fade-up d1">
      <h2 class="h6 fw-bold mb-3 text-brand">Payment methods (verified amounts)</h2>
      <?php if ($byMethod === []): ?>
        <div class="empty-state"><i data-lucide="wallet"></i><p>No payments in this period.</p></div>
      <?php else: ?>
        <div class="chart-box"><canvas id="payChart"></canvas></div>
      <?php endif; ?>
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
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-6">
    <div class="b-card h-100 fade-up">
      <h2 class="h6 fw-bold mb-3 text-brand">Commissions by society <span class="text-muted small fw-normal">(platform revenue)</span></h2>
      <div class="b-table">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Society</th><th>Total</th><th>Recorded</th></tr></thead>
          <tbody>
            <?php if ($commissionTotals === []): ?>
              <tr><td colspan="3" class="text-muted small">No commissions in this period.</td></tr>
            <?php else: foreach ($commissionTotals as $ct): ?>
              <tr><td class="small"><?= e($ct['name']) ?></td><td class="small fw-semibold"><?= money($ct['total']) ?></td><td class="small"><?= money($ct['recorded']) ?></td></tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
  <div class="col-lg-6">
    <div class="b-card h-100 fade-up d1">
      <h2 class="h6 fw-bold mb-3 text-brand">Subscription revenue by plan</h2>
      <div class="b-table">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Plan</th><th>Subscriptions</th><th>Revenue</th></tr></thead>
          <tbody>
            <?php if ($subRevenue === []): ?>
              <tr><td colspan="3" class="text-muted small">No paid subscriptions in this period.</td></tr>
            <?php else: foreach ($subRevenue as $sr): ?>
              <tr><td class="small text-capitalize"><?= e($sr['plan']) ?></td><td class="small"><?= (int) $sr['c'] ?></td><td class="small fw-semibold"><?= money($sr['total']) ?></td></tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
      <div class="b-note mt-3">
        <i data-lucide="info"></i>
        <span>Gross sales are transaction volume — NOT profit. Platform revenue = commissions + subscriptions.</span>
      </div>
    </div>
  </div>
</div>

<?php
$revLabels = json_encode(array_map(static fn($r) => date('d M', strtotime($r['d'])), $revSeries));
$revVals   = json_encode(array_map(static fn($r) => round((float) $r['v'], 2), $revSeries));
$topL      = json_encode(array_map(static fn($r) => $r['product_name'], $topProducts));
$topV      = json_encode(array_map(static fn($r) => (float) $r['qty'], $topProducts));
$socL      = json_encode(array_map(static fn($r) => $r['name'], $bySociety));
$socV      = json_encode(array_map(static fn($r) => (int) $r['c'], $bySociety));
$payL      = json_encode(array_map(static fn($r) => strtoupper($r['method']), $byMethod));
$payV      = json_encode(array_map(static fn($r) => round((float) $r['verified_amt'], 2), $byMethod));
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof Chart === 'undefined') return;
  var G = '#28A66A', F = '#16452F', A = '#F4B942', LG = '#7CC98F', DA = '#E5A72E';
  var palette = [G, A, LG, F, DA, '#5BB98C', '#C58BD9', '#7FA8E8'];
  var base = { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } };

  var rc = document.getElementById('revChart');
  if (rc) new Chart(rc, { type: 'line', data: { labels: <?= $revLabels ?>, datasets: [{ label: 'Gross sales ₹', data: <?= $revVals ?>, borderColor: G, backgroundColor: 'rgba(40,166,106,.12)', fill: true, tension: .35, pointRadius: 2, borderWidth: 2 }] }, options: base });

  var tc = document.getElementById('topChart');
  if (tc) new Chart(tc, { type: 'doughnut', data: { labels: <?= $topL ?>, datasets: [{ data: <?= $topV ?>, backgroundColor: palette, borderWidth: 2, borderColor: '#fff' }] },
    options: { maintainAspectRatio: false, cutout: '58%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } } } });

  var sc = document.getElementById('socChart');
  if (sc) new Chart(sc, { type: 'bar', data: { labels: <?= $socL ?>, datasets: [{ label: 'Orders', data: <?= $socV ?>, backgroundColor: [G, A, LG, F, DA], borderRadius: 6, maxBarThickness: 48 }] }, options: base });

  var pc = document.getElementById('payChart');
  if (pc) new Chart(pc, { type: 'doughnut', data: { labels: <?= $payL ?>, datasets: [{ data: <?= $payV ?>, backgroundColor: [G, A, F], borderWidth: 2, borderColor: '#fff' }] },
    options: { maintainAspectRatio: false, cutout: '58%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } } } });
});
</script>
