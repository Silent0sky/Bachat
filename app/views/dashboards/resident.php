<?php
/**
 * Resident dashboard (Agent A).
 * Vars: $stats, $recentOrders, $campaigns, $notices, $bookings.
 */
$user = $user ?? auth_user();
$stats = $stats ?? ['orders' => 0, 'delivered' => 0, 'saved' => 0, 'bookings' => 0];
$campaigns = $campaigns ?? [];
$recentOrders = $recentOrders ?? [];
$notices = $notices ?? [];
$bookings = $bookings ?? [];
?>
<div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-4">
  <div>
    <h1 class="section-title"><?= e(t('dash.hello', ['name' => explode(' ', (string) ($user['name'] ?? ''))[0]])) ?></h1>
    <p class="text-muted mb-0"><?= e(t('dash.sub')) ?></p>
  </div>
  <a href="<?= url('/shop') ?>" class="btn btn-brand b-btn-pill">
    <i data-lucide="shopping-basket" class="me-1"></i><?= e(t('nav.shop')) ?>
  </a>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <a class="text-decoration-none d-block h-100" href="<?= url('/orders') ?>">
      <div class="b-card stat-card fade-up h-100">
        <div class="stat-value count-up" data-count="<?= (int) $stats['orders'] ?>">0</div>
        <div class="stat-label"><?= e(t('dash.total_orders')) ?></div>
        <span class="stat-icon icon-brand"><i data-lucide="package"></i></span>
      </div>
    </a>
  </div>
  <div class="col-6 col-lg-3">
    <a class="text-decoration-none d-block h-100" href="<?= url('/orders') ?>">
      <div class="b-card stat-card fade-up d1 h-100">
        <div class="stat-value count-up" data-count="<?= (int) $stats['delivered'] ?>">0</div>
        <div class="stat-label"><?= e(t('dash.delivered')) ?></div>
        <span class="stat-icon icon-emerald"><i data-lucide="check-circle-2"></i></span>
      </div>
    </a>
  </div>
  <div class="col-6 col-lg-3">
    <a class="text-decoration-none d-block h-100" href="<?= url('/orders') ?>">
      <div class="b-card stat-card fade-up d2 h-100">
        <div class="stat-value"><?= e(money($stats['saved'])) ?></div>
        <div class="stat-label"><?= e(t('dash.total_saved')) ?></div>
        <span class="stat-icon icon-amber"><i data-lucide="piggy-bank"></i></span>
      </div>
    </a>
  </div>
  <div class="col-6 col-lg-3">
    <a class="text-decoration-none d-block h-100" href="<?= url('/bookings') ?>">
      <div class="b-card stat-card fade-up d3 h-100">
        <div class="stat-value count-up" data-count="<?= (int) $stats['bookings'] ?>">0</div>
        <div class="stat-label"><?= e(t('dash.active_bookings')) ?></div>
        <span class="stat-icon icon-sky"><i data-lucide="calendar-check"></i></span>
      </div>
    </a>
  </div>
</div>

<!-- Quick links -->
<h2 class="fs-6 fw-bold text-uppercase text-muted mb-2"><?= e(t('dash.quick')) ?></h2>
<div class="row g-3 mb-4">
  <?php
  $tiles = [
      ['shop', 'dash.shop_tile', 'dash.shop_tile_sub', 'shopping-basket'],
      ['campaigns', 'dash.bulk_tile', 'dash.bulk_tile_sub', 'users'],
      ['services', 'dash.services_tile', 'dash.services_tile_sub', 'wrench'],
      ['tickets', 'dash.support_tile', 'dash.support_tile_sub', 'life-buoy'],
  ];
  foreach ($tiles as $i => [$path, $titleKey, $subKey, $icon]): ?>
  <div class="col-6 col-lg-3">
    <a href="<?= url('/' . $path) ?>" class="text-decoration-none">
      <div class="list-tile h-100 fade-up<?= $i < 3 ? ' d' . ($i + 1) : '' ?>">
        <span class="b-avatar flex-shrink-0"><i data-lucide="<?= e($icon) ?>"></i></span>
        <span class="min-w-0">
          <span class="d-block fw-semibold text-dark"><?= e(t($titleKey)) ?></span>
          <span class="d-block small text-muted"><?= e(t($subKey)) ?></span>
        </span>
      </div>
    </a>
  </div>
  <?php endforeach; ?>
</div>

<div class="row g-4">
  <!-- Left column -->
  <div class="col-12 col-lg-7">

    <!-- Active campaigns -->
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h2 class="fs-6 fw-bold text-uppercase text-muted mb-0"><?= e(t('dash.campaigns')) ?></h2>
      <a href="<?= url('/campaigns') ?>" class="small text-brand text-decoration-none"><?= e(t('dash.view_all')) ?> →</a>
    </div>
    <?php if ($campaigns): foreach ($campaigns as $c): ?>
      <div class="b-card mb-3 fade-up">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div class="min-w-0">
            <div class="fw-semibold text-truncate"><?= e($c['title']) ?></div>
            <div class="small text-muted">
              <i data-lucide="clock" style="width:13px;height:13px;vertical-align:-2px;"></i>
              <?= e(t('dash.closes_on', ['when' => human_dt($c['deadline'] ?? null)])) ?>
            </div>
          </div>
          <span class="<?= e(status_badge((string) $c['my_status'])) ?> flex-shrink-0"><?= e(t('dash.my_status')) ?>: <?= e(ucfirst((string) $c['my_status'])) ?></span>
        </div>
        <div class="d-flex justify-content-between small mt-2 mb-1">
          <span><?= e(qty($c['achieved'])) ?> / <?= e(qty($c['target'])) ?></span>
          <span class="text-muted"><?= e(t('dash.progress', ['pct' => (int) $c['pct']])) ?></span>
        </div>
        <div class="b-progress progress-thin"><div class="b-progress-bar" style="width:<?= (int) $c['pct'] ?>%;"></div></div>
        <a href="<?= url('/campaigns/' . (int) $c['id']) ?>" class="btn btn-soft btn-sm b-btn-pill mt-2"><?= e(t('common.details')) ?></a>
      </div>
    <?php endforeach; else: ?>
      <div class="b-card empty-state mb-3">
        <i data-lucide="users"></i>
        <p><?= e(t('dash.no_campaigns')) ?></p>
      </div>
    <?php endif; ?>

    <!-- Recent orders -->
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h2 class="fs-6 fw-bold text-uppercase text-muted mb-0"><?= e(t('dash.recent_orders')) ?></h2>
      <a href="<?= url('/orders') ?>" class="small text-brand text-decoration-none"><?= e(t('dash.view_all')) ?> →</a>
    </div>
    <?php if ($recentOrders): ?>
    <div class="b-table table-responsive fade-up">
      <table class="table table-hover align-middle mb-0">
        <thead><tr>
          <th><?= e(t('orders.order_no')) ?></th>
          <th><?= e(t('orders.placed_on')) ?></th>
          <th><?= e(t('orders.items')) ?></th>
          <th><?= e(t('common.total')) ?></th>
          <th><?= e(t('common.status')) ?></th>
        </tr></thead>
        <tbody>
        <?php foreach ($recentOrders as $o): ?>
          <tr>
            <td><a href="<?= url('/orders/' . (int) $o['id']) ?>" class="fw-semibold text-brand text-decoration-none"><?= e($o['order_no']) ?></a></td>
            <td class="small"><?= e(human_date($o['placed_at'] ?? $o['created_at'])) ?></td>
            <td><?= (int) $o['item_count'] ?></td>
            <td class="fw-semibold"><?= e(money($o['total'])) ?></td>
            <td><span class="<?= e(status_badge((string) $o['status'])) ?>"><?= e(ucwords(str_replace('_', ' ', (string) $o['status']))) ?></span></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php else: ?>
      <div class="b-card empty-state">
        <i data-lucide="package"></i>
        <p><?= e(t('dash.no_orders')) ?></p>
        <a href="<?= url('/shop') ?>" class="btn btn-brand btn-sm b-btn-pill mt-2"><?= e(t('dash.shop_tile')) ?></a>
      </div>
    <?php endif; ?>
  </div>

  <!-- Right column -->
  <div class="col-12 col-lg-5">

    <!-- Pinned notices -->
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h2 class="fs-6 fw-bold text-uppercase text-muted mb-0"><?= e(t('dash.pinned')) ?></h2>
      <a href="<?= url('/notices') ?>" class="small text-brand text-decoration-none"><?= e(t('dash.view_all')) ?> →</a>
    </div>
    <?php if ($notices): foreach ($notices as $n): ?>
      <div class="b-card mb-3 fade-up">
        <div class="d-flex align-items-center gap-2 mb-1">
          <i data-lucide="megaphone" style="width:16px;height:16px;color:var(--b-amber);"></i>
          <span class="fw-semibold"><?= e($n['title']) ?></span>
          <?php if ((int) $n['is_pinned'] === 1): ?><span class="b-badge-amber"><?= e(t('notices.pinned')) ?></span><?php endif; ?>
        </div>
        <p class="small text-muted mb-0" style="display:-webkit-box;-webkit-line-clamp:2;line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
          <?= e(mb_substr(strip_tags((string) $n['body']), 0, 160)) ?>…
        </p>
      </div>
    <?php endforeach; else: ?>
      <div class="b-card empty-state mb-3"><i data-lucide="megaphone"></i><p><?= e(t('dash.no_notices')) ?></p></div>
    <?php endif; ?>

    <!-- Upcoming bookings -->
    <div class="d-flex align-items-center justify-content-between mb-2">
      <h2 class="fs-6 fw-bold text-uppercase text-muted mb-0"><?= e(t('dash.upcoming_bookings')) ?></h2>
      <a href="<?= url('/bookings') ?>" class="small text-brand text-decoration-none"><?= e(t('dash.view_all')) ?> →</a>
    </div>
    <?php if ($bookings): foreach ($bookings as $b): ?>
      <div class="b-card mb-3 fade-up">
        <div class="d-flex justify-content-between align-items-start gap-2">
          <div class="min-w-0">
            <div class="fw-semibold text-truncate"><?= e($b['service_name']) ?></div>
            <div class="small text-muted">
              <i data-lucide="calendar" style="width:13px;height:13px;vertical-align:-2px;"></i>
              <?= e(t('dash.slot')) ?>: <?= e(human_dt($b['slot_at'])) ?>
            </div>
          </div>
          <span class="<?= e(status_badge((string) $b['status'])) ?> flex-shrink-0"><?= e(ucfirst((string) $b['status'])) ?></span>
        </div>
      </div>
    <?php endforeach; else: ?>
      <div class="b-card empty-state">
        <i data-lucide="calendar-check"></i>
        <p><?= e(t('dash.no_bookings')) ?></p>
        <a href="<?= url('/services') ?>" class="btn btn-soft btn-sm b-btn-pill mt-2"><?= e(t('nav.services')) ?></a>
      </div>
    <?php endif; ?>
  </div>
</div>
