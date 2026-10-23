<?php
/** Group service deals list (resident). Vars: $campaigns, $myInterests (campaign_id => status) */
$sname = fn (array $s): string => (string) ($s['name_' . lang()] ?? '') !== '' ? (string) $s['name_' . lang()] : (string) $s['name_en'];
$statusLabel = [
    'open'         => 'services.campaign_status_open',
    'pricing'      => 'services.campaign_status_pricing',
    'confirm_open' => 'services.campaign_status_confirm_open',
    'closed'       => 'services.campaign_status_closed',
    'cancelled'    => 'services.campaign_status_cancelled',
];
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4">
    <div>
      <h1 class="section-title mb-1"><?= e(t('services.campaigns_title')) ?></h1>
      <p class="text-muted small mb-0"><?= e(t('services.campaigns_sub')) ?></p>
    </div>
    <a class="btn btn-soft btn-sm b-btn-pill" href="<?= url('/services') ?>">
      <i data-lucide="wrench" width="14" height="14"></i> <?= e(t('services.title')) ?>
    </a>
  </div>

  <?php if (empty($campaigns)): ?>
    <div class="empty-state b-card">
      <i data-lucide="users"></i>
      <p><?= e(t('services.no_campaigns')) ?></p>
    </div>
  <?php else: ?>
  <div class="row g-3 g-md-4">
    <?php foreach ($campaigns as $c):
        $pct   = (int) $c['min_participants'] > 0 ? min(100, (int) round($c['participants'] / (int) $c['min_participants'] * 100)) : 0;
        $mine  = $myInterests[(int) $c['id']] ?? null; ?>
      <div class="col-md-6 col-lg-4">
        <div class="b-card b-card-hover h-100 d-flex flex-column">
          <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <span class="<?= status_badge($c['status']) ?>"><?= e(t($statusLabel[$c['status']] ?? 'common.status')) ?></span>
            <?php if ($mine === 'interested'): ?>
              <span class="b-badge-blue"><?= e(t('services.my_interest')) ?>: <?= e(t('services.count_me_in')) ?></span>
            <?php elseif ($mine === 'confirmed'): ?>
              <span class="b-badge-green"><?= e(t('services.confirm_booking')) ?> ✓</span>
            <?php endif; ?>
          </div>

          <h5 class="fw-bold mb-1"><?= e($c['title']) ?></h5>
          <div class="small text-muted mb-2">
            <i data-lucide="wrench" width="13" height="13"></i> <?= e($sname($c)) ?>
            <?php if (!empty($c['vendor_name'])): ?> · <?= e(t('services.by_vendor', ['vendor' => $c['vendor_name']])) ?><?php endif; ?>
          </div>

          <?php if (!empty($c['description'])): ?>
            <p class="small text-muted mb-2"><?= e(mb_substr((string) $c['description'], 0, 110)) ?><?= mb_strlen((string) $c['description']) > 110 ? '…' : '' ?></p>
          <?php endif; ?>

          <div class="row g-2 text-center mb-3">
            <div class="col-4"><div class="mini-stat"><span><?= e($c['final_price'] !== null ? money($c['final_price']) : money($c['estimated_price'] ?: $c['base_price'])) ?></span><small><?= e($c['final_price'] !== null ? t('services.final_price') : t('services.estimated_price')) ?></small></div></div>
            <div class="col-4"><div class="mini-stat"><span><?= e(qty($c['group_discount_percent'])) ?>%</span><small><?= e(t('services.group_discount')) ?></small></div></div>
            <div class="col-4"><div class="mini-stat"><span><?= (int) $c['participants'] ?>/<?= (int) $c['min_participants'] ?></span><small><?= e(t('bookings.campaign_participants')) ?></small></div></div>
          </div>

          <div class="progress b-progress mb-1" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100" style="height:8px;">
            <div class="progress-bar b-progress-bar" style="width:<?= $pct ?>%"></div>
          </div>
          <div class="d-flex justify-content-between small text-muted mb-3">
            <span><i data-lucide="users" width="13" height="13"></i> <?= e(t('services.participants_count', ['n' => (string) (int) $c['participants']])) ?></span>
            <span><i data-lucide="clock" width="13" height="13"></i> <?= $c['deadline'] ? e(human_dt($c['deadline'])) : '—' ?></span>
          </div>

          <?php if (!empty($c['slot_date'])): ?>
            <div class="small text-muted mb-3">
              <i data-lucide="calendar" width="13" height="13"></i> <?= e(t('services.scheduled_slot')) ?>:
              <?= e(human_date($c['slot_date'])) ?><?= $c['slot_time'] ? ' · ' . e(substr((string) $c['slot_time'], 0, 5)) : '' ?>
            </div>
          <?php endif; ?>

          <a class="btn <?= $c['status'] === 'confirm_open' ? 'btn-amber' : 'btn-brand' ?> b-btn-pill mt-auto" href="<?= url('/service-campaigns/' . $c['id']) ?>">
            <?= e($c['status'] === 'confirm_open' ? t('services.confirm_booking') : t('services.view_details')) ?>
            <i data-lucide="arrow-right" width="14" height="14"></i>
          </a>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="b-note mt-4">
    <i data-lucide="info"></i>
    <span><?= e(t('services.campaign_note')) ?></span>
  </div>
</div>
