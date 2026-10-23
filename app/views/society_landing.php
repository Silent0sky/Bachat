<?php /** Society landing. Vars: $soc, $notices */ ?>
<section class="hero-society" style="<?= !empty($soc['banner']) ? 'background-image:url(' . e(upload_url($soc['banner'])) . ')' : '' ?>">
  <div class="container py-5">
    <div class="d-flex align-items-center gap-3 fade-up">
      <?php if (!empty($soc['logo'])): ?>
        <img src="<?= e(upload_url($soc['logo'])) ?>" alt="<?= e($soc['name']) ?> logo" width="64" height="64" class="rounded-4 shadow-sm">
      <?php else: ?>
        <div class="b-icon-tile b-icon-tile-lg"><i data-lucide="building-2"></i></div>
      <?php endif; ?>
      <div>
        <h1 class="mb-0 fw-bold"><?= e($soc['name']) ?></h1>
        <div class="small opacity-90"><i data-lucide="map-pin" width="13" height="13"></i> <?= e($soc['city'] ?? '') ?> <?= !empty($soc['state']) ? '· ' . e($soc['state']) : '' ?></div>
      </div>
    </div>
    <?php if (!empty($soc['landing_text'])): ?>
      <p class="lead mt-3 mb-0 fade-up d1" style="max-width:640px"><?= e($soc['landing_text']) ?></p>
    <?php endif; ?>
  </div>
</section>

<section class="container py-5">
  <div class="row g-4">
    <div class="col-lg-7">
      <h2 class="section-title">Latest notices</h2>
      <?php if (empty($notices)): ?>
        <div class="empty-state"><i data-lucide="megaphone"></i><p><?= e(t('notices.no_notices')) ?></p></div>
      <?php else: ?>
        <?php foreach ($notices as $n): ?>
          <div class="b-card mb-3 fade-up">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <h5 class="mb-1 fw-semibold"><?= e($n['title']) ?></h5>
              <span class="small text-muted"><?= e(human_date($n['publish_at'])) ?></span>
            </div>
            <p class="text-muted small mb-0"><?= e(mb_strimwidth((string) $n['body'], 0, 220, '…')) ?></p>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <div class="col-lg-5">
      <div class="b-card h-100">
        <h5 class="fw-bold mb-3"><i data-lucide="info" class="me-1"></i> About this society on Bachat</h5>
        <ul class="list-unstyled small text-muted mb-4">
          <li class="mb-2"><i data-lucide="shopping-basket" width="15" height="15" class="me-2"></i> Society-priced grocery catalogue</li>
          <li class="mb-2"><i data-lucide="users" width="15" height="15" class="me-2"></i> Bulk campaigns &amp; group services</li>
          <li class="mb-2"><i data-lucide="megaphone" width="15" height="15" class="me-2"></i> Official notices &amp; support</li>
          <?php if (!empty($soc['contact_email'])): ?><li class="mb-2"><i data-lucide="mail" width="15" height="15" class="me-2"></i> <?= e($soc['contact_email']) ?></li><?php endif; ?>
          <?php if (!empty($soc['contact_phone'])): ?><li class="mb-2"><i data-lucide="phone" width="15" height="15" class="me-2"></i> <?= e($soc['contact_phone']) ?></li><?php endif; ?>
        </ul>
        <a href="<?= url('/join') ?>" class="btn btn-brand w-100 b-btn-pill"><?= e(t('nav.join')) ?></a>
        <a href="<?= url('/login') ?>" class="btn btn-outline-brand w-100 mt-2 b-btn-pill"><?= e(t('nav.login')) ?></a>
      </div>
    </div>
  </div>
</section>
