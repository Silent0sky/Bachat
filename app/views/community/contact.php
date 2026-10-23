<?php
/**
 * Public support contact page.
 * Vars: $supportEmail, $supportPhone.
 */
?>
<div class="row justify-content-center">
  <div class="col-12 col-lg-9 col-xl-7">
    <div class="text-center mb-4 fade-up">
      <h1 class="section-title mb-1"><?= e(t('tickets.contact_title')) ?></h1>
      <p class="text-muted mb-0"><?= e(t('tickets.contact_sub')) ?></p>
    </div>

    <div class="row g-3 mb-4">
      <div class="col-12 col-sm-6">
        <div class="b-card b-card-hover h-100 fade-up d1">
          <div class="d-flex align-items-center gap-3">
            <span class="b-avatar"><i data-lucide="mail" style="width:16px;height:16px"></i></span>
            <div style="min-width:0">
              <div class="text-muted small"><?= e(t('tickets.contact_email')) ?></div>
              <?php if ($supportEmail !== ''): ?>
                <a class="fw-semibold text-decoration-none text-brand" href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a>
              <?php else: ?>
                <span class="fw-semibold"><?= e(t('common.na')) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12 col-sm-6">
        <div class="b-card b-card-hover h-100 fade-up d2">
          <div class="d-flex align-items-center gap-3">
            <span class="b-avatar"><i data-lucide="phone" style="width:16px;height:16px"></i></span>
            <div style="min-width:0">
              <div class="text-muted small"><?= e(t('tickets.contact_phone')) ?></div>
              <?php if ($supportPhone !== ''): ?>
                <a class="fw-semibold text-decoration-none text-brand" href="tel:<?= e(preg_replace('/[^\d+\- ]/', '', $supportPhone)) ?>"><?= e($supportPhone) ?></a>
              <?php else: ?>
                <span class="fw-semibold"><?= e(t('common.na')) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="b-card text-center fade-up d3">
      <div class="b-note mb-3 justify-content-center">
        <i data-lucide="clock"></i><span><?= e(t('tickets.contact_hours')) ?></span>
      </div>
      <p class="text-muted small"><?= e(t('tickets.contact_note')) ?></p>
      <?php if (auth_check()): ?>
        <a class="btn btn-brand b-btn-pill" href="<?= url('/tickets/new') ?>">
          <i data-lucide="life-buoy" style="width:15px;height:15px"></i> <?= e(t('tickets.contact_cta')) ?>
        </a>
      <?php else: ?>
        <a class="btn btn-brand b-btn-pill" href="<?= url('/login') ?>">
          <i data-lucide="log-in" style="width:15px;height:15px"></i> <?= e(t('tickets.contact_login')) ?>
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>
