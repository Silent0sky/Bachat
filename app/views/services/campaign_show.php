<?php
/** Group deal detail. Vars: $campaign, $participants, $myInterest, $canInterest, $canConfirm */
$sname = fn (array $s): string => (string) ($s['name_' . lang()] ?? '') !== '' ? (string) $s['name_' . lang()] : (string) $s['name_en'];
$pct = (int) $campaign['min_participants'] > 0
    ? min(100, (int) round($participants / (int) $campaign['min_participants'] * 100)) : 0;
$slotAt = !empty($campaign['slot_date'])
    ? human_date($campaign['slot_date']) . (!empty($campaign['slot_time']) ? ' · ' . substr((string) $campaign['slot_time'], 0, 5) : '')
    : null;
?>
<div class="container py-4">
  <a class="btn btn-soft btn-sm b-btn-pill mb-3" href="<?= url('/service-campaigns') ?>"><i data-lucide="arrow-left" width="14" height="14"></i> <?= e(t('common.back')) ?></a>

  <div class="row g-4">
    <div class="col-lg-7">
      <div class="b-card h-100">
        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap mb-2">
          <span class="<?= status_badge($campaign['status']) ?>">
            <?= e(match ($campaign['status']) {
                'open' => t('services.campaign_status_open'),
                'pricing' => t('services.campaign_status_pricing'),
                'confirm_open' => t('services.campaign_status_confirm_open'),
                'closed' => t('services.campaign_status_closed'),
                default => t('services.campaign_status_cancelled'),
            }) ?>
          </span>
          <?php if (!empty($campaign['group_discount_percent'])): ?>
            <span class="b-badge-amber"><?= e(qty($campaign['group_discount_percent'])) ?>% <?= e(t('services.group_discount')) ?></span>
          <?php endif; ?>
        </div>

        <h1 class="section-title mb-2"><?= e($campaign['title']) ?></h1>
        <div class="small text-muted mb-3">
          <i data-lucide="wrench" width="13" height="13"></i> <?= e($sname($campaign)) ?>
          · <?= e(t('services.min_participants')) ?>: <strong><?= (int) $campaign['min_participants'] ?></strong>
          <?php if (!empty($campaign['vendor_name'])): ?>
            · <?= e(t('services.by_vendor', ['vendor' => $campaign['vendor_name']])) ?>
          <?php endif; ?>
        </div>

        <?php if (!empty($campaign['description'])): ?>
          <p class="text-muted small"><?= nl2br(e($campaign['description'])) ?></p>
        <?php endif; ?>

        <div class="row g-2 text-center my-3">
          <div class="col-4">
            <div class="mini-stat">
              <span><?= e($campaign['final_price'] !== null ? money($campaign['final_price']) : money($campaign['estimated_price'] ?: $campaign['base_price'])) ?></span>
              <small><?= e($campaign['final_price'] !== null ? t('services.final_price') . ' ' . t('services.per_participant') : t('services.estimated_price')) ?></small>
            </div>
          </div>
          <div class="col-4">
            <div class="mini-stat"><span><?= e(money($campaign['base_price'])) ?></span><small><?= e(t('services.base_price')) ?></small></div>
          </div>
          <div class="col-4">
            <div class="mini-stat"><span><?= e(qty($campaign['group_discount_percent'])) ?>%</span><small><?= e(t('services.group_discount')) ?></small></div>
          </div>
        </div>

        <div class="progress b-progress mb-1" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
          <div class="progress-bar b-progress-bar" style="width:<?= $pct ?>%"></div>
        </div>
        <div class="d-flex justify-content-between small text-muted mb-3">
          <span><?= e(t('services.participants_progress', ['n' => (string) $participants, 'min' => (string) (int) $campaign['min_participants']])) ?></span>
          <span><i data-lucide="clock" width="13" height="13"></i> <?= e(t('services.deadline')) ?>: <?= $campaign['deadline'] ? e(human_dt($campaign['deadline'])) : '—' ?></span>
        </div>

        <div class="b-timeline">
          <div class="mb-2"><strong class="small"><?= e(t('services.participants_title')) ?></strong>
            <div class="small text-muted"><?= e(t('services.participants_count', ['n' => (string) $participants])) ?></div>
          </div>
          <div class="small text-muted">
            <i data-lucide="calendar" width="13" height="13"></i>
            <?= e(t('services.scheduled_slot')) ?>: <?= $slotAt ? e($slotAt) : e(t('services.slot_not_set')) ?>
          </div>
        </div>
      </div>
    </div>

    <div class="col-lg-5">
      <?php if ($canConfirm): ?>
        <!-- ============ Confirmation panel ============ -->
        <div class="b-card border-0" style="background: linear-gradient(140deg, var(--b-forest), var(--b-green-d)); color:#fff;">
          <h2 class="h5 fw-bold mb-1"><i data-lucide="party-popper" class="me-1"></i><?= e(t('services.confirm_panel_title')) ?></h2>
          <p class="small mb-3 opacity-90"><?= e(t('services.confirm_panel_body')) ?></p>
          <div class="mini-stat mb-3">
            <span style="color:#F4B942; font-size:22px;"><?= e(money($campaign['final_price'])) ?></span>
            <small><?= e(t('services.per_participant')) ?></small>
          </div>
          <form method="post" action="<?= url('/service-campaigns/' . $campaign['id'] . '/confirm') ?>">
            <?= csrf_field() ?>
            <div class="form-check mb-3">
              <input class="form-check-input" type="checkbox" id="confirm" name="confirm" value="1" required>
              <label class="form-check-label small" for="confirm"><?= e(t('services.confirm_checkbox')) ?></label>
            </div>
            <div class="small mb-3">
              <i data-lucide="calendar-check" width="14" height="14"></i>
              <?= e(t('services.scheduled_slot')) ?>: <?= $slotAt ? e($slotAt) : e(human_dt(date('Y-m-d H:i:s', strtotime('+7 days')))) ?>
            </div>
            <button type="submit" class="btn btn-light w-100 b-btn-pill fw-bold">
              <?= e(t('services.confirm_booking')) ?> · <?= e(money($campaign['final_price'])) ?>
            </button>
          </form>
        </div>
      <?php elseif ($canInterest): ?>
        <!-- ============ Interest panel ============ -->
        <div class="b-card">
          <h2 class="h5 fw-bold text-brand mb-1"><?= e(t('services.count_me_in')) ?></h2>
          <p class="text-muted small mb-3"><?= e(t('services.campaigns_sub')) ?></p>
          <div class="mini-stat mb-3">
            <span><?= e($campaign['estimated_price'] !== null ? money($campaign['estimated_price']) : '~' . money($campaign['base_price'])) ?></span>
            <small><?= e(t('services.estimated_price')) ?> · <?= e(t('services.per_participant')) ?></small>
          </div>
          <form method="post" action="<?= url('/service-campaigns/' . $campaign['id'] . '/interested') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-brand w-100 b-btn-pill">
              <i data-lucide="hand-metal" class="me-1"></i><?= e(t('services.interested_btn')) ?>
            </button>
          </form>
          <p class="small text-muted mb-0 mt-3"><i data-lucide="info" width="13" height="13"></i> <?= e(t('services.campaign_note')) ?></p>
        </div>
      <?php else: ?>
        <!-- ============ Status panel ============ -->
        <div class="b-card">
          <div class="empty-state py-4">
            <i data-lucide="<?= $campaign['status'] === 'confirm_open' ? 'lock' : 'hourglass' ?>"></i>
            <?php if ($myInterest && $myInterest['status'] === 'interested'): ?>
              <p class="fw-semibold text-dark"><?= e(t('services.already_interested')) ?></p>
              <p class="small"><?= e($campaign['status'] === 'pricing' ? t('services.campaign_status_pricing') . '…' : t('services.campaign_status_closed')) ?></p>
            <?php elseif ($myInterest && $myInterest['status'] === 'confirmed'): ?>
              <p class="fw-semibold text-dark"><?= e(t('services.confirmed')) ?></p>
              <a class="btn btn-soft btn-sm b-btn-pill" href="<?= url('/bookings') ?>"><?= e(t('services.my_bookings')) ?></a>
            <?php else: ?>
              <p class="small text-muted"><?= e(match ($campaign['status']) {
                  'open' => t('services.campaign_status_open'),
                  'pricing' => t('services.campaign_status_pricing'),
                  'confirm_open' => t('services.campaign_status_confirm_open'),
                  'closed' => t('services.campaign_status_closed'),
                  default => t('services.campaign_status_cancelled'),
              }) ?></p>
              <?php if ($campaign['status'] === 'open'): ?>
                <form method="post" action="<?= url('/service-campaigns/' . $campaign['id'] . '/interested') ?>">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-brand btn-sm b-btn-pill"><?= e(t('services.interested_btn')) ?></button>
                </form>
              <?php endif; ?>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
