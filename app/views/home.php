<?php
/** Landing page. Vars: $societies, $stats */
$societies = $societies ?? [];
$stats = $stats ?? ['societies' => 0, 'products' => 0, 'services' => 0];
?>
<section class="hero-grad text-white">
  <div class="container py-5">
    <div class="row align-items-center g-5 py-lg-4">
      <div class="col-lg-6">
        <span class="chip chip-light fade-up"><i data-lucide="sparkles" width="15" height="15"></i> <?= e(t('app.tagline')) ?></span>
        <h1 class="hero-title fade-up d1"><?= e(t('app.name')) ?> <span class="hero-hl">बचत</span></h1>
        <p class="hero-sub fade-up d2">
          One smart platform for your society — group grocery buying, home services, bulk deals and
          verified savings. Built for Indian residential societies, in English, हिन्दी and मराठी.
        </p>
        <div class="d-flex flex-wrap gap-3 fade-up d3">
          <?php if (auth_check()): ?>
            <a class="btn btn-light btn-lg b-btn-pill" href="<?= url(auth_role() === 'resident' ? '/dashboard' : (auth_role() === 'super_admin' ? '/super/dashboard' : (auth_role() === 'society_admin' ? '/admin/dashboard' : (auth_role() === 'vendor' ? '/vendor/dashboard' : '/delivery/dashboard')))) ?>">
              <i data-lucide="layout-dashboard" class="me-2"></i><?= e(t('nav.dashboard')) ?>
            </a>
          <?php else: ?>
            <a class="btn btn-light btn-lg b-btn-pill" href="<?= url('/join') ?>"><i data-lucide="building-2" class="me-2"></i><?= e(t('nav.join')) ?></a>
            <a class="btn btn-outline-light btn-lg b-btn-pill" href="<?= url('/login') ?>"><i data-lucide="log-in" class="me-2"></i><?= e(t('nav.login')) ?></a>
          <?php endif; ?>
        </div>
        <div class="hero-stats fade-up d4">
          <div><strong class="count-up" data-count="<?= (int) $stats['societies'] ?>">0</strong><span><?= e(t('nav.societies')) ?></span></div>
          <div><strong class="count-up" data-count="<?= (int) $stats['products'] ?>">0</strong><span>Products</span></div>
          <div><strong class="count-up" data-count="<?= (int) $stats['services'] ?>">0</strong><span>Services</span></div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="hero-card fade-up d2">
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-semibold"><i data-lucide="users" class="me-1"></i> Bulk deal · Basmati Rice 5kg</span>
            <span class="b-badge-green">Live</span>
          </div>
          <div class="progress b-progress mb-2" role="progressbar" aria-valuenow="78" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar b-progress-bar" style="width:78%"></div>
          </div>
          <div class="d-flex justify-content-between small text-muted mb-3"><span>78 of 100 packs</span><span>3 days left</span></div>
          <div class="row g-2">
            <div class="col-4"><div class="mini-stat"><span>₹92</span><small>per kg</small></div></div>
            <div class="col-4"><div class="mini-stat"><span>18%</span><small>below MRP</small></div></div>
            <div class="col-4"><div class="mini-stat"><span>₹4,600</span><small>society saving</small></div></div>
          </div>
          <p class="small text-muted mb-0 mt-3"><i data-lucide="info" width="14" height="14"></i> Illustration only — sample data.</p>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="container py-5">
  <h2 class="section-title">Everything your society needs</h2>
  <div class="row g-4 mt-1">
    <?php
    $features = [
        ['shopping-basket', 'Smart grocery shopping', 'Category → product → brand → pack size. Favourites, lists, repeat orders and reminders.'],
        ['users', 'Bulk buying power', 'Aggregate demand across flats, compare vendor quotations and unlock real wholesale prices.'],
        ['wrench', 'Trusted home services', 'AC service, plumbing, cleaning, pest control — book solo or as a group for bigger discounts.'],
        ['megaphone', 'Society operations', 'Notices, memberships, deliveries, payment verification and complaints — all in one panel.'],
        ['shield-check', 'Safe by design', 'Role-based access, society data isolation, verified payments and full audit logs.'],
        ['bar-chart-3', 'Real reports', 'Sales, demand, savings, commissions and subscription reports with charts and CSV export.'],
    ];
    foreach ($features as $f): [$icon, $title, $desc] = $f; ?>
      <div class="col-md-6 col-lg-4">
        <div class="b-card b-card-hover h-100 fade-up">
          <div class="b-icon-tile"><i data-lucide="<?= $icon ?>"></i></div>
          <h5 class="mt-3 mb-1 fw-bold"><?= e($title) ?></h5>
          <p class="text-muted mb-0 small"><?= e($desc) ?></p>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php if (!empty($societies)): ?>
<section class="py-5 bg-soft">
  <div class="container">
    <h2 class="section-title">Societies on Bachat</h2>
    <p class="text-muted">Every society gets its own branding, members and prices.</p>
    <div class="row g-4 mt-1">
      <?php foreach ($societies as $s): ?>
        <div class="col-sm-6 col-lg-4">
          <a class="text-decoration-none" href="<?= url('/society/' . $s['slug']) ?>">
            <div class="b-card b-card-hover h-100 d-flex align-items-center gap-3">
              <?php if (!empty($s['logo'])): ?>
                <img src="<?= e(upload_url($s['logo'])) ?>" alt="<?= e($s['name']) ?> logo" width="52" height="52" class="rounded-3">
              <?php else: ?>
                <div class="b-icon-tile mb-0"><i data-lucide="building-2"></i></div>
              <?php endif; ?>
              <div>
                <div class="fw-bold text-dark"><?= e($s['name']) ?></div>
                <div class="small text-muted"><i data-lucide="map-pin" width="13" height="13"></i> <?= e($s['city'] ?? '') ?></div>
              </div>
            </div>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="container py-5">
  <div class="b-card cta-card text-white">
    <div class="row align-items-center g-4">
      <div class="col-lg-8">
        <h3 class="fw-bold mb-1">Ready for smarter savings?</h3>
        <p class="mb-0 text-muted">Join through your society — your Society Admin approves members, so shopping stays trusted and local.</p>
      </div>
      <div class="col-lg-4 text-lg-end">
        <a class="btn btn-brand btn-lg b-btn-pill" href="<?= url('/join') ?>"><i data-lucide="arrow-right" class="me-2"></i><?= e(t('nav.join')) ?></a>
      </div>
    </div>
  </div>
</section>
