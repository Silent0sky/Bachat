<?php
/** Super admin dashboard. Vars: $kpis, $rev14, $ordersBySociety, $topProducts, $latestOrders */
$kpis = $kpis ?? ['societies' => 0, 'users' => 0, 'orders_30d' => 0, 'revenue_30d' => 0, 'pay_pending' => 0, 'vendors_pending' => 0, 'tickets_open' => 0];
$rev14 = $rev14 ?? [];
$ordersBySociety = $ordersBySociety ?? [];
$topProducts = $topProducts ?? [];
$latestOrders = $latestOrders ?? [];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="section-title mb-1">Platform Dashboard</h1>
    <p class="text-muted small mb-0">Bachat across all societies — live overview</p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-soft b-btn-pill" href="<?= url('/super/reports') ?>"><i data-lucide="bar-chart-3" class="me-1"></i> Reports</a>
    <a class="btn btn-brand b-btn-pill" href="<?= url('/super/payments?status=submitted') ?>">
      <i data-lucide="badge-indian-rupee" class="me-1"></i> Verify payments (<?= (int) ($kpis['pay_pending'] ?? 0) ?>)
    </a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-4 col-xl">
    <a class="text-decoration-none d-block h-100" href="<?= url('/super/societies') ?>">
      <div class="b-card stat-card h-100 fade-up">
        <div class="stat-label">Active societies</div>
        <div class="stat-value"><?= (int) $kpis['societies'] ?></div>
        <div class="stat-icon icon-brand"><i data-lucide="building-2"></i></div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-4 col-xl">
    <a class="text-decoration-none d-block h-100" href="<?= url('/super/users') ?>">
      <div class="b-card stat-card h-100 fade-up d1">
        <div class="stat-label">Total users</div>
        <div class="stat-value"><?= (int) $kpis['users'] ?></div>
        <div class="stat-icon icon-sky"><i data-lucide="users"></i></div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-4 col-xl">
    <a class="text-decoration-none d-block h-100" href="<?= url('/super/reports') ?>">
      <div class="b-card stat-card h-100 fade-up d2">
        <div class="stat-label">Orders (30 days)</div>
        <div class="stat-value"><?= (int) $kpis['orders_30d'] ?></div>
        <div class="stat-icon icon-emerald"><i data-lucide="package"></i></div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-6 col-xl">
    <a class="text-decoration-none d-block h-100" href="<?= url('/super/reports') ?>">
      <div class="b-card stat-card h-100 fade-up d2">
        <div class="stat-label">Gross sales (30 days)</div>
        <div class="stat-value"><?= money($kpis['revenue_30d']) ?></div>
        <div class="stat-icon icon-brand"><i data-lucide="badge-indian-rupee"></i></div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-6 col-xl">
    <a class="text-decoration-none d-block h-100" href="<?= url('/super/payments?status=submitted') ?>">
      <div class="b-card stat-card h-100 fade-up d3">
        <div class="stat-label">Payments to verify</div>
        <div class="stat-value"><?= (int) $kpis['pay_pending'] ?></div>
        <div class="stat-icon icon-amber"><i data-lucide="clock-4"></i></div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-6 col-xl">
    <a class="text-decoration-none d-block h-100" href="<?= url('/super/vendors') ?>">
      <div class="b-card stat-card h-100 fade-up d3">
        <div class="stat-label">Vendor approvals</div>
        <div class="stat-value"><?= (int) $kpis['vendors_pending'] ?></div>
        <div class="stat-icon icon-amber"><i data-lucide="store"></i></div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-6 col-xl">
    <a class="text-decoration-none d-block h-100" href="<?= url('/super/tickets') ?>">
      <div class="b-card stat-card h-100 fade-up d4">
        <div class="stat-label">Open tickets</div>
        <div class="stat-value"><?= (int) $kpis['tickets_open'] ?></div>
        <div class="stat-icon icon-rose"><i data-lucide="life-buoy"></i></div>
      </div>
    </a>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-8">
    <div class="b-card h-100 fade-up">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0 text-brand">Gross sales — last 14 days</h2>
        <span class="b-badge-green">excludes cancelled orders</span>
      </div>
      <div class="chart-box"><canvas id="revChart"></canvas></div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="b-card h-100 fade-up d1">
      <h2 class="h6 fw-bold mb-3 text-brand">Top products — by qty delivered</h2>
      <?php if ($topProducts === []): ?>
        <div class="empty-state"><i data-lucide="package-open"></i><p>No delivered orders yet.</p></div>
      <?php else: ?>
        <div class="chart-box"><canvas id="topChart"></canvas></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-5">
    <div class="b-card h-100 fade-up">
      <h2 class="h6 fw-bold mb-3 text-brand">Orders by society</h2>
      <?php if ($ordersBySociety === []): ?>
        <div class="empty-state"><i data-lucide="building-2"></i><p>No societies yet.</p></div>
      <?php else: ?>
        <div class="chart-box"><canvas id="socChart"></canvas></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-7">
    <div class="b-card h-100 fade-up d1">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0 text-brand">Latest orders</h2>
        <a class="small text-decoration-none text-brand fw-semibold" href="<?= url('/super/reports') ?>">All reports <i data-lucide="arrow-right" width="14" height="14"></i></a>
      </div>
      <?php if ($latestOrders === []): ?>
        <div class="empty-state"><i data-lucide="package-open"></i><p>No orders yet — they will appear here the moment residents start shopping.</p></div>
      <?php else: ?>
        <div class="b-table">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead><tr><th>Order</th><th>Society</th><th>Resident</th><th>Total</th><th>Status</th></tr></thead>
              <tbody>
                <?php foreach ($latestOrders as $o): ?>
                  <tr>
                    <td class="fw-semibold"><?= e($o['order_no']) ?><div class="small text-muted"><?= e(human_dt($o['created_at'])) ?></div></td>
                    <td><?= e($o['society_name']) ?></td>
                    <td><?= e($o['user_name']) ?></td>
                    <td class="fw-semibold"><?= money($o['total']) ?></td>
                    <td><span class="<?= e(status_badge($o['status'])) ?>"><?= e(str_replace('_', ' ', $o['status'])) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php $revLabels = json_encode(array_map(static fn($r) => date('d M', strtotime($r['d'])), $rev14));
      $revVals   = json_encode(array_map(static fn($r) => round((float) $r['v'], 2), $rev14));
      $topL      = json_encode(array_map(static fn($r) => $r['product_name'], $topProducts));
      $topV      = json_encode(array_map(static fn($r) => (float) $r['q'], $topProducts));
      $socL      = json_encode(array_map(static fn($r) => $r['name'], $ordersBySociety));
      $socV      = json_encode(array_map(static fn($r) => (int) $r['c'], $ordersBySociety));
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof Chart === 'undefined') return;
  var lime = '#A3E635', emerald = '#10B981', amber = '#F59E0B', sky = '#38BDF8', violet = '#A78BFA', rose = '#FB7185';
  var palette = [lime, sky, amber, emerald, violet, rose];

  Chart.defaults.color = '#94A3B8';
  Chart.defaults.font.family = "'Inter', sans-serif";

  var rc = document.getElementById('revChart');
  if (rc) {
    new Chart(rc, {
      type: 'line',
      data: {
        labels: <?= $revLabels ?>,
        datasets: [{
          label: 'Gross sales (₹)',
          data: <?= $revVals ?>,
          borderColor: lime,
          backgroundColor: 'rgba(163,230,53,.08)',
          fill: true,
          tension: .35,
          pointRadius: 4,
          pointBackgroundColor: lime,
          pointBorderColor: '#0F1218',
          pointBorderWidth: 2,
          borderWidth: 2.5
        }]
      },
      options: {
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: '#161B26',
            borderColor: 'rgba(255,255,255,0.1)',
            borderWidth: 1,
            titleColor: '#fff',
            bodyColor: lime,
            padding: 10,
            cornerRadius: 10
          }
        },
        scales: {
          x: { grid: { color: 'rgba(255,255,255,0.04)' } },
          y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' } }
        }
      }
    });
  }
  var tc = document.getElementById('topChart');
  if (tc) {
    new Chart(tc, {
      type: 'doughnut',
      data: { labels: <?= $topL ?>, datasets: [{ data: <?= $topV ?>, backgroundColor: palette, borderWidth: 2, borderColor: '#121622' }] },
      options: {
        maintainAspectRatio: false,
        cutout: '68%',
        plugins: {
          legend: { position: 'bottom', labels: { boxWidth: 10, color: '#94A3B8', font: { size: 11 } } }
        }
      }
    });
  }
  var sc = document.getElementById('socChart');
  if (sc) {
    new Chart(sc, {
      type: 'bar',
      data: { labels: <?= $socL ?>, datasets: [{ label: 'Orders', data: <?= $socV ?>, backgroundColor: lime, borderRadius: 8, maxBarThickness: 38 }] },
      options: {
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          x: { grid: { display: false } },
          y: { beginAtZero: true, grid: { color: 'rgba(255,255,255,0.04)' }, ticks: { precision: 0 } }
        }
      }
    });
  }
});
</script>
