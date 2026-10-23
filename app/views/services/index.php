<?php
/** Services catalogue + open group deals. Vars: $services, $categories, $currentCat, $campaigns */

$catIcon = fn (string $c): string => match (true) {
    str_contains(strtolower($c), 'ac') || str_contains(strtolower($c), 'applian') => 'wind',
    str_contains(strtolower($c), 'plumb')  => 'droplets',
    str_contains(strtolower($c), 'electr') => 'zap',
    str_contains(strtolower($c), 'clean')  => 'sparkles',
    str_contains(strtolower($c), 'pest')   => 'bug',
    str_contains(strtolower($c), 'vehicle') || str_contains(strtolower($c), 'car') => 'car',
    default => 'wrench',
};
$sname = fn (array $s): string => (string) ($s['name_' . lang()] ?? '') !== '' ? (string) $s['name_' . lang()] : (string) $s['name_en'];
?>
<section class="hero-grad text-white" style="border-radius: 0 0 26px 26px;">
  <div class="container py-4 py-lg-5">
    <div class="row align-items-center g-3">
      <div class="col-lg-8">
        <span class="chip chip-light fade-up"><i data-lucide="wrench" width="15" height="15"></i> <?= e(t('nav.services')) ?></span>
        <h1 class="hero-title fade-up d1" style="font-size: clamp(26px, 5vw, 42px);"><?= e(t('services.title')) ?></h1>
        <p class="hero-sub mb-0 fade-up d2"><?= e(t('services.subtitle')) ?></p>
      </div>
      <div class="col-lg-4 text-lg-end fade-up d3">
        <a class="btn btn-light b-btn-pill" href="<?= url('/service-campaigns') ?>">
          <i data-lucide="users" class="me-1"></i><?= e(t('services.view_campaigns')) ?>
        </a>
      </div>
    </div>
  </div>
</section>

<div class="container py-4">

  <?php if (!empty($campaigns)): ?>
  <!-- ============ Open group deals ============ -->
  <div class="d-flex justify-content-between align-items-end mb-3 mt-2">
    <div>
      <h2 class="section-title mb-1"><?= e(t('services.open_deals')) ?></h2>
      <p class="text-muted small mb-0"><?= e(t('services.campaigns_sub')) ?></p>
    </div>
  </div>
  <div class="row g-3 mb-4">
    <?php foreach ($campaigns as $c):
        $pct = (int) $c['min_participants'] > 0 ? min(100, (int) round($c['participants'] / (int) $c['min_participants'] * 100)) : 0; ?>
      <div class="col-md-6">
        <a class="text-decoration-none" href="<?= url('/service-campaigns/' . $c['id']) ?>">
          <div class="b-card b-card-hover h-100">
            <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
              <div>
                <span class="b-badge-<?= $c['status'] === 'open' ? 'green' : ($c['status'] === 'confirm_open' ? 'amber' : 'grey') ?>">
                  <?= e($c['status'] === 'open' ? t('services.campaign_status_open') : ($c['status'] === 'pricing' ? t('services.campaign_status_pricing') : t('services.campaign_status_confirm_open'))) ?>
                </span>
                <h5 class="fw-bold mt-2 mb-1 text-dark"><?= e($c['title']) ?></h5>
                <div class="small text-muted">
                  <i data-lucide="wrench" width="13" height="13"></i> <?= e($sname($c)) ?>
                  · <i data-lucide="badge-percent" width="13" height="13"></i> <?= e(qty($c['group_discount_percent'])) ?>% <?= e(t('services.group_discount')) ?>
                </div>
              </div>
              <div class="text-end">
                <div class="price"><?= e($c['estimated_price'] !== null ? money($c['estimated_price']) : money($c['base_price'])) ?></div>
                <div class="small text-muted"><?= e(t('services.estimated_price')) ?></div>
              </div>
            </div>
            <div class="progress b-progress mb-1" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" style="height:8px;">
              <div class="progress-bar b-progress-bar" style="width:<?= $pct ?>%"></div>
            </div>
            <div class="d-flex justify-content-between small text-muted">
              <span><?= e(t('services.participants_progress', ['n' => (string) (int) $c['participants'], 'min' => (string) (int) $c['min_participants']])) ?></span>
              <span><i data-lucide="clock" width="13" height="13"></i>
                <?= $c['deadline'] ? e(human_dt($c['deadline'])) : e(t('common.deadline')) ?></span>
            </div>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ============ Category chips ============ -->
  <h2 class="section-title mb-3"><?= e(t('services.all_categories')) ?></h2>
  <div class="d-flex flex-wrap gap-2 mb-4">
    <a class="chip <?= $currentCat === '' ? 'chip-active' : '' ?>" href="<?= url('/services') ?>"><?= e(t('services.all_categories')) ?></a>
    <?php foreach ($categories as $cat): ?>
      <a class="chip <?= $currentCat === $cat ? 'chip-active' : '' ?>" href="<?= url('/services?cat=' . urlencode($cat)) ?>">
        <i data-lucide="<?= e($catIcon($cat)) ?>" width="14" height="14"></i> <?= e($cat) ?>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- ============ Services grid ============ -->
  <?php if (empty($services)): ?>
    <div class="empty-state">
      <i data-lucide="search-x"></i>
      <p><?= e(t('services.no_services')) ?></p>
    </div>
  <?php else: ?>
  <div class="row g-3 g-md-4">
    <?php foreach ($services as $s): ?>
      <div class="col-6 col-md-4 col-lg-3">
        <div class="b-card b-card-hover h-100 d-flex flex-column p-3 p-md-4 fade-up">
          <?php if (!empty($s['image'])): ?>
            <img src="<?= e(upload_url($s['image'])) ?>" alt="<?= e($sname($s)) ?>" class="rounded-3 mb-3 w-100" style="height:120px; object-fit:cover;">
          <?php else: ?>
            <div class="b-icon-tile b-icon-tile-lg mb-3"><i data-lucide="<?= e($catIcon((string) $s['category'])) ?>"></i></div>
          <?php endif; ?>
          <span class="b-badge-grey align-self-start mb-2"><?= e($s['category']) ?></span>
          <h6 class="fw-bold mb-1 flex-grow-0"><?= e($sname($s)) ?></h6>
          <div class="small text-muted mb-2">
            <i data-lucide="clock" width="13" height="13"></i> <?= e(t('services.duration_mins', ['n' => (string) (int) $s['duration_mins']])) ?>
            <?php if (!empty($s['vendor_name'])): ?>
              · <i data-lucide="store" width="13" height="13"></i> <?= e(t('services.by_vendor', ['vendor' => $s['vendor_name']])) ?>
            <?php endif; ?>
          </div>
          <div class="d-flex justify-content-between align-items-center mt-auto">
            <span class="price"><?= e(money($s['base_price'])) ?></span>
            <a class="btn btn-brand btn-sm b-btn-pill" href="<?= url('/services/' . $s['id']) ?>">
              <?= e(t('services.book_now')) ?> <i data-lucide="arrow-right" width="14" height="14"></i>
            </a>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
