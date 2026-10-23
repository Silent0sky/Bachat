<?php
/**
 * Modern Blinkit-style footer with rich columns, trust guarantees,
 * active society badge, quick grocery categories, and mobile bottomnav clearance.
 * Vars available: $user, $society, $lang, $role
 */
$user = $user ?? (function_exists('auth_user') ? auth_user() : null);
$society = $society ?? (function_exists('current_society') ? current_society() : null);
$role = $user['role'] ?? null;
$isResident = $role === 'resident';
$active = $active ?? '';
$inAdminPanel = str_starts_with($active, 'super-') || str_starts_with($active, 'admin-') || str_starts_with($active, 'vendor-') || $role === 'delivery';
?>
<?php if ($inAdminPanel): ?>
<footer class="b-footer b-footer-admin py-3 border-top mt-auto">
  <div class="container-fluid px-3 px-md-4 d-flex flex-wrap justify-content-between align-items-center gap-2 small text-muted">
    <div>
      © <?= date('Y') ?> <strong><?= e(t('app.name')) ?> Technologies</strong> · Management Portal
    </div>
    <div class="d-flex align-items-center gap-3">
      <?php if ($society): ?>
        <span class="d-none d-sm-inline"><i data-lucide="building-2" style="width:14px;height:14px;"></i> <?= e($society['name']) ?></span>
      <?php endif; ?>
      <span>Active: <strong><?= e($user['email'] ?? '') ?></strong></span>
      <a href="<?= url('/profile') ?>" class="text-muted text-decoration-none">Profile</a>
    </div>
  </div>
</footer>
<?php return; endif; ?>
<footer class="b-footer mt-auto">
  <!-- Trust Guarantees Bar (Blinkit Style) -->
  <div class="b-footer-trust-wrap">
    <div class="container">
      <div class="row g-3 py-3">
        <div class="col-6 col-lg-3">
          <div class="b-trust-item">
            <div class="b-trust-icon"><i data-lucide="truck"></i></div>
            <div>
              <div class="b-trust-title">Society Delivery</div>
              <div class="b-trust-sub">Direct to your tower doorstep</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="b-trust-item">
            <div class="b-trust-icon"><i data-lucide="badge-percent"></i></div>
            <div>
              <div class="b-trust-title">Wholesale Prices</div>
              <div class="b-trust-sub">Direct bulk society rates</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="b-trust-item">
            <div class="b-trust-icon"><i data-lucide="shield-check"></i></div>
            <div>
              <div class="b-trust-title">100% Quality Assured</div>
              <div class="b-trust-sub">Authentic branded staples</div>
            </div>
          </div>
        </div>
        <div class="col-6 col-lg-3">
          <div class="b-trust-item">
            <div class="b-trust-icon"><i data-lucide="users"></i></div>
            <div>
              <div class="b-trust-title">Community Bachat</div>
              <div class="b-trust-sub">More flats buy, more you save</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Main Footer Content -->
  <div class="b-footer-main">
    <div class="container py-5">
      <div class="row g-4 justify-content-between">
        <!-- Brand & Society Column -->
        <div class="col-12 col-lg-4">
          <div class="d-flex align-items-center gap-2 mb-3">
            <img src="<?= asset('img/logo.svg') ?>" alt="Bachat Logo" width="30" height="30" class="b-logo">
            <span class="b-brand-text fs-4 mb-0">bachat<span>.</span></span>
          </div>
          <p class="b-footer-about mb-3">
            <?= e(t('app.tagline')) ?>. Save big every month on daily groceries and essentials delivered directly within your gated community.
          </p>
          <?php if ($society): ?>
            <div class="b-footer-society-chip">
              <i data-lucide="map-pin"></i>
              <span>Delivering to <strong><?= e($society['name']) ?></strong><?php if (!empty($user['flat_no'])): ?> · Flat <?= e($user['flat_no']) ?><?php endif; ?></span>
            </div>
          <?php else: ?>
            <div class="b-footer-society-chip">
              <i data-lucide="building-2"></i>
              <span>Active in 50+ Top Gated Societies</span>
            </div>
          <?php endif; ?>
        </div>

        <!-- Categories Column -->
        <div class="col-6 col-md-4 col-lg-2">
          <h6 class="b-footer-heading">Categories</h6>
          <ul class="b-footer-links">
            <li><a href="<?= url('/shop') ?>?cat=1">Atta, Rice & Dal</a></li>
            <li><a href="<?= url('/shop') ?>?cat=2">Oil & Ghee</a></li>
            <li><a href="<?= url('/shop') ?>?cat=3">Cleaning & Home</a></li>
            <li><a href="<?= url('/shop') ?>?cat=4">Tea, Sugar & Spices</a></li>
            <li><a href="<?= url('/shop') ?>?cat=5">Soaps & Personal Care</a></li>
          </ul>
        </div>

        <!-- Quick Links Column -->
        <div class="col-6 col-md-4 col-lg-2">
          <h6 class="b-footer-heading">Quick Links</h6>
          <ul class="b-footer-links">
            <?php if ($user): ?>
              <?php if ($role === 'resident'): ?>
                <li><a href="<?= url('/dashboard') ?>"><?= e(t('nav.dashboard')) ?></a></li>
                <li><a href="<?= url('/shop') ?>"><?= e(t('nav.shop')) ?></a></li>
                <li><a href="<?= url('/campaigns') ?>">Bulk Deals</a></li>
                <li><a href="<?= url('/orders') ?>"><?= e(t('nav.orders')) ?></a></li>
                <li><a href="<?= url('/lists') ?>">Monthly Lists</a></li>
                <li><a href="<?= url('/profile') ?>"><?= e(t('nav.profile')) ?></a></li>
              <?php elseif (in_array($role, ['super_admin','society_admin'], true)): ?>
                <li><a href="<?= url($role==='super_admin'?'/super/dashboard':'/admin/dashboard') ?>"><?= e(t('nav.admin')) ?></a></li>
                <li><a href="<?= url('/shop') ?>"><?= e(t('nav.shop')) ?></a></li>
                <li><a href="<?= url('/profile') ?>"><?= e(t('nav.profile')) ?></a></li>
              <?php else: ?>
                <li><a href="<?= url('/dashboard') ?>"><?= e(t('nav.dashboard')) ?></a></li>
                <li><a href="<?= url('/profile') ?>"><?= e(t('nav.profile')) ?></a></li>
              <?php endif; ?>
            <?php else: ?>
              <li><a href="<?= url('/') ?>"><?= e(t('nav.home')) ?></a></li>
              <li><a href="<?= url('/login') ?>"><?= e(t('nav.login')) ?></a></li>
              <li><a href="<?= url('/join') ?>"><?= e(t('nav.join')) ?></a></li>
              <li><a href="<?= url('/vendor/register') ?>">Vendor Partner</a></li>
            <?php endif; ?>
          </ul>
        </div>

        <!-- Help & Contact Column -->
        <div class="col-12 col-md-4 col-lg-3">
          <h6 class="b-footer-heading">Help & Support</h6>
          <ul class="b-footer-links">
            <li><a href="<?= url(auth_check() ? '/tickets' : '/login') ?>"><i data-lucide="life-buoy" class="b-footer-ico"></i> <?= e(t('footer.support')) ?></a></li>
            <li><a href="<?= url('/demand') ?>"><i data-lucide="vote" class="b-footer-ico"></i> Demand New Product</a></li>
            <li><a href="<?= url('/services') ?>"><i data-lucide="wrench" class="b-footer-ico"></i> Society Home Services</a></li>
            <li><a href="mailto:support@bachat.in"><i data-lucide="mail" class="b-footer-ico"></i> support@bachat.in</a></li>
          </ul>
          <div class="mt-3">
            <span class="badge b-badge-green" style="font-size:11px;">
              <i data-lucide="clock" style="width:13px;height:13px;"></i> Daily 7 AM – 10 PM
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Bottom Legal & Notice Bar -->
  <div class="b-footer-bottom">
    <div class="container py-3">
      <div class="row gy-2 align-items-center">
        <div class="col-md-6 small text-muted">
          © <?= date('Y') ?> <strong><?= e(t('app.name')) ?> Technologies</strong>. All rights reserved.
        </div>
        <div class="col-md-6 text-md-end small text-muted d-flex align-items-center justify-content-md-end gap-1">
          <i data-lucide="info" style="width:14px;height:14px;color:#7C3AED;"></i>
          <span><?= e(t('app.sample_prices')) ?></span>
        </div>
      </div>
    </div>
  </div>
</footer>
