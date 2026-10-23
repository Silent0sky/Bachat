<?php
/** Society admin dashboard. Vars: $kpis, $rev14, $statusRows, $latestOrders, $pendingMembers */
$kpis = $kpis ?? ['members_active' => 0, 'pending' => 0, 'orders_today' => 0, 'month_revenue' => 0, 'tickets_open' => 0, 'campaigns_live' => 0, 'pay_pending' => 0];
$rev14 = $rev14 ?? [];
$statusRows = $statusRows ?? [];
$latestOrders = $latestOrders ?? [];
$pendingMembers = $pendingMembers ?? [];
$societyName = current_society()['name'] ?? 'your society';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="section-title mb-1">Society Dashboard</h1>
    <p class="text-muted small mb-0"><?= e($societyName) ?> · operations at a glance</p>
  </div>
  <div class="d-flex flex-wrap gap-2">
    <a class="btn btn-soft b-btn-pill" href="<?= url('/admin/reports') ?>"><i data-lucide="bar-chart-3" class="me-1"></i> Reports</a>
    <a class="btn btn-brand b-btn-pill" href="<?= url('/admin/payments') ?>">
      <i data-lucide="badge-indian-rupee" class="me-1"></i> Verify payments (<?= (int) ($kpis['pay_pending'] ?? 0) ?>)
    </a>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-md-4 col-xl">
    <a class="text-decoration-none d-block h-100" href="<?= url('/admin/members') ?>">
      <div class="b-card stat-card h-100 fade-up">
        <div class="stat-label">Active members</div>
        <div class="stat-value"><?= (int) $kpis['members_active'] ?></div>
        <div class="stat-icon icon-sky"><i data-lucide="users"></i></div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-4 col-xl">
    <a class="text-decoration-none d-block h-100" href="<?= url('/admin/members?tab=pending') ?>">
      <div class="b-card stat-card h-100 fade-up d1">
        <div class="stat-label">Pending approvals</div>
        <div class="stat-value"><?= (int) $kpis['pending'] ?></div>
        <div class="stat-icon icon-amber"><i data-lucide="user-plus"></i></div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-4 col-xl">
    <a class="text-decoration-none d-block h-100" href="<?= url('/admin/orders') ?>">
      <div class="b-card stat-card h-100 fade-up d1">
        <div class="stat-label">Orders today</div>
        <div class="stat-value"><?= (int) $kpis['orders_today'] ?></div>
        <div class="stat-icon icon-emerald"><i data-lucide="package"></i></div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-6 col-xl">
    <a class="text-decoration-none d-block h-100" href="<?= url('/admin/reports') ?>">
      <div class="b-card stat-card h-100 fade-up d2">
        <div class="stat-label">Revenue this month</div>
        <div class="stat-value"><?= money($kpis['month_revenue']) ?></div>
        <div class="stat-icon icon-brand"><i data-lucide="badge-indian-rupee"></i></div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-6 col-xl">
    <a class="text-decoration-none d-block h-100" href="<?= url('/admin/tickets') ?>">
      <div class="b-card stat-card h-100 fade-up d3">
        <div class="stat-label">Open tickets</div>
        <div class="stat-value"><?= (int) $kpis['tickets_open'] ?></div>
        <div class="stat-icon icon-rose"><i data-lucide="life-buoy"></i></div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-6 col-xl">
    <a class="text-decoration-none d-block h-100" href="<?= url('/admin/campaigns') ?>">
      <div class="b-card stat-card h-100 fade-up d4">
        <div class="stat-label">Live campaigns</div>
        <div class="stat-value"><?= (int) $kpis['campaigns_live'] ?></div>
        <div class="stat-icon icon-brand"><i data-lucide="megaphone"></i></div>
      </div>
    </a>
  </div>
</div>

<div class="row g-4 mb-4">
  <div class="col-lg-7">
    <div class="b-card h-100 fade-up">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0 text-brand">Sales — last 14 days</h2>
        <span class="b-badge-green">excludes cancelled orders</span>
      </div>
      <div class="chart-box"><canvas id="revChart"></canvas></div>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="b-card h-100 fade-up d1">
      <h2 class="h6 fw-bold mb-3 text-brand">Orders by status</h2>
      <?php if ($statusRows === []): ?>
        <div class="empty-state"><i data-lucide="package-open"></i><p>No orders yet.</p></div>
      <?php else: ?>
        <div class="chart-box"><canvas id="statusChart"></canvas></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="b-card h-100 fade-up">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0 text-brand">Latest orders</h2>
        <a class="small text-decoration-none text-brand fw-semibold" href="<?= url('/admin/orders') ?>">All orders <i data-lucide="arrow-right" width="14" height="14"></i></a>
      </div>
      <?php if ($latestOrders === []): ?>
        <div class="empty-state"><i data-lucide="package-open"></i><p>No orders yet — residents of <?= e($societyName) ?> haven't ordered yet.</p></div>
      <?php else: ?>
        <div class="b-table">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead><tr><th>Order</th><th>Resident</th><th>Total</th><th>Status</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($latestOrders as $o): ?>
                  <tr>
                    <td class="fw-semibold"><?= e($o['order_no']) ?><div class="small text-muted"><?= e(human_dt($o['created_at'])) ?></div></td>
                    <td><?= e($o['user_name']) ?></td>
                    <td class="fw-semibold"><?= money($o['total']) ?></td>
                    <td><span class="<?= e(status_badge($o['status'])) ?>"><?= e(str_replace('_', ' ', $o['status'])) ?></span></td>
                    <td class="text-end"><a class="btn btn-sm btn-soft" href="<?= url('/admin/orders/' . $o['id']) ?>">Open</a></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="col-lg-5">
    <div class="b-card h-100 fade-up d1">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="h6 fw-bold mb-0 text-brand">Pending approvals</h2>
        <a class="small text-decoration-none text-brand fw-semibold" href="<?= url('/admin/members?tab=pending') ?>">All <i data-lucide="arrow-right" width="14" height="14"></i></a>
      </div>
      <?php if ($pendingMembers === []): ?>
        <div class="empty-state"><i data-lucide="user-check"></i><p>No pending membership requests. </p></div>
      <?php else: ?>
        <div class="d-flex flex-column gap-2">
          <?php foreach ($pendingMembers as $m): ?>
            <div class="list-tile">
              <div class="flex-grow-1">
                <div class="fw-semibold"><?= e($m['user_name']) ?></div>
                <div class="small text-muted">
                  <?= e(trim(($m['block'] !== null && $m['block'] !== '' ? $m['block'] . '-' : '') . ($m['flat_number'] ?? '')) ?: 'No flat chosen') ?>
                  · <?= e(ucfirst($m['member_role'])) ?> · <?= e(human_date($m['requested_at'])) ?>
                </div>
              </div>
              <form method="post" action="<?= url('/admin/memberships/' . $m['id'] . '/approve') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-brand">Approve</button>
              </form>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<?php $revLabels = json_encode(array_map(static fn($r) => date('d M', strtotime($r['d'])), $rev14));
      $revVals   = json_encode(array_map(static fn($r) => round((float) $r['v'], 2), $rev14));
      $stL       = json_encode(array_map(static fn($r) => str_replace('_', ' ', $r['status']), $statusRows));
      $stV       = json_encode(array_map(static fn($r) => (int) $r['c'], $statusRows));
?>
<script>
document.addEventListener('DOMContentLoaded', function () {
  if (typeof Chart === 'undefined') return;
  var G = '#28A66A', F = '#16452F', A = '#F4B942', LG = '#7CC98F', DA = '#E5A72E';
  var palette = [G, A, LG, F, DA, '#5BB98C', '#C58BD9', '#7FA8E8'];

  var rc = document.getElementById('revChart');
  if (rc) {
    new Chart(rc, {
      type: 'line',
      data: { labels: <?= $revLabels ?>, datasets: [{ label: 'Sales ₹', data: <?= $revVals ?>, borderColor: G, backgroundColor: 'rgba(40,166,106,.12)', fill: true, tension: .35, pointRadius: 3, borderWidth: 2 }] },
      options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
  }
  var sc = document.getElementById('statusChart');
  if (sc) {
    new Chart(sc, {
      type: 'doughnut',
      data: { labels: <?= $stL ?>, datasets: [{ data: <?= $stV ?>, backgroundColor: palette, borderWidth: 2, borderColor: '#fff' }] },
      options: { maintainAspectRatio: false, cutout: '58%', plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 10 } } } } }
    });
  }
});
</script>
