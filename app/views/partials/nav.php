<?php
/** Top navigation — adapts to role. Vars: $user, $society, $active */
$user      = $user ?? null;
$society   = $society ?? null;
$role      = $user['role'] ?? null;
$active    = $active ?? '';
$unreadN   = 0;
if ($user) {
    try {
        $unreadN = (int) \App\Core\Database::scalar('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [$user['id']]);
    } catch (\Throwable $e) { $unreadN = 0; }
}
$appName = $society['name'] ?? t('app.name');
?>
<header>
<nav class="navbar navbar-expand-lg b-nav sticky-top">
  <div class="<?= (in_array($role, ['super_admin','society_admin','vendor'], true)) ? 'container-fluid' : 'container' ?>">
    <a class="navbar-brand b-brand" href="<?= url(auth_check() ? (match($role){ 'super_admin'=>'/super/dashboard','society_admin'=>'/admin/dashboard','vendor'=>'/vendor/dashboard','delivery_staff'=>'/delivery/dashboard', default=>'/dashboard'}) : '/') ?>">
      <img src="<?= asset('img/logo.svg') ?>" alt="Bachat logo" width="34" height="34" class="b-logo">
      <span class="b-brand-text">bachat<span>.</span></span>
    </a>

    <!-- Society Delivery Tag (No 10-mins, authentic Image 2 style) -->
    <div class="d-none d-sm-flex align-items-center ms-2 ms-lg-3">
      <div class="blinkit-delivery-pill">
        <span class="delivery-badge-society"><i data-lucide="map-pin" style="width:12px;height:12px;"></i> Deliver to</span>
        <div class="delivery-loc-name" title="<?= e($society['name'] ?? 'Har Society Ki Smart Bachat') ?>">
          <?= e($society['name'] ?? 'Society Express') ?>
        </div>
      </div>
    </div>

    <!-- Blinkit Central Search Bar -->
    <form action="<?= url('/shop') ?>" method="get" class="blinkit-search-box d-none d-md-flex mx-3 flex-grow-1">
      <i data-lucide="search"></i>
      <input type="search" name="q" placeholder='Search "rice", "atta", "dal", "milk", "soap"...' autocomplete="off" value="<?= e($_GET['q'] ?? '') ?>">
    </form>

    <button class="navbar-toggler b-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-controls="mobileNav" aria-label="Menu">
      <i data-lucide="menu"></i>
    </button>

    <div class="collapse navbar-collapse d-none d-lg-flex">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
        <?php if (!$user): ?>
          <li class="nav-item"><a class="nav-link <?= $active==='home'?'active':'' ?>" href="<?= url('/') ?>"><?= e(t('nav.home')) ?></a></li>
          <li class="nav-item"><a class="nav-link <?= $active==='join'?'active':'' ?>" href="<?= url('/join') ?>"><?= e(t('nav.join')) ?></a></li>
          <li class="nav-item"><a class="nav-link b-btn-nav" href="<?= url('/login') ?>"><?= e(t('nav.login')) ?></a></li>
        <?php else: ?>
          <?php if ($role === 'resident'): ?>
            <li class="nav-item"><a class="nav-link <?= $active==='shop'?'active':'' ?>" href="<?= url('/shop') ?>"><i data-lucide="shopping-basket" class="me-1"></i><?= e(t('nav.shop')) ?></a></li>
            <li class="nav-item"><a class="nav-link <?= $active==='campaigns'?'active':'' ?>" href="<?= url('/campaigns') ?>"><i data-lucide="users" class="me-1"></i>Bulk Deals</a></li>
            <li class="nav-item dropdown">
              <a class="nav-link dropdown-toggle <?= in_array($active, ['orders','bookings','notices','tickets','demand','favourites','lists','reminders','profile'], true)?'active':'' ?>" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false"><?= e(t('nav.more')) ?></a>
              <ul class="dropdown-menu dropdown-menu-end b-dropdown">
                <li><a class="dropdown-item" href="<?= url('/orders') ?>"><i data-lucide="package"></i> <?= e(t('nav.orders')) ?></a></li>
                <li><a class="dropdown-item" href="<?= url('/services') ?>"><i data-lucide="wrench"></i> <?= e(t('nav.services')) ?></a></li>
                <li><a class="dropdown-item" href="<?= url('/favourites') ?>"><i data-lucide="heart"></i> <?= e(t('nav.favourites')) ?></a></li>
                <li><a class="dropdown-item" href="<?= url('/lists') ?>"><i data-lucide="list-checks"></i> Monthly List</a></li>
                <li><a class="dropdown-item" href="<?= url('/notices') ?>"><i data-lucide="megaphone"></i> Notice Board</a></li>
                <li><a class="dropdown-item" href="<?= url('/profile') ?>"><i data-lucide="user-cog"></i> <?= e(t('nav.profile')) ?></a></li>
              </ul>
            </li>
            <li class="nav-item">
              <a href="<?= url('/cart') ?>" class="blinkit-cart-btn ms-1" aria-label="Cart">
                <i data-lucide="shopping-cart"></i>
                <span>My Cart</span>
              </a>
            </li>
          <?php elseif (in_array($role, ['super_admin','society_admin'], true)): ?>
            <li class="nav-item"><a class="nav-link <?= str_starts_with($active,'admin-')||str_starts_with($active,'super-')?'active':'' ?>" href="<?= url($role==='super_admin'?'/super/dashboard':'/admin/dashboard') ?>"><i data-lucide="shield" class="me-1"></i><?= e(t('nav.admin')) ?></a></li>
          <?php elseif ($role === 'vendor'): ?>
            <li class="nav-item"><a class="nav-link <?= $active==='vendor-quotations'?'active':'' ?>" href="<?= url('/vendor/quotations') ?>"><?= e(t('nav.reports')) ?></a></li>
          <?php endif; ?>
          <?php if ($role !== 'delivery_staff'): ?>
          <li class="nav-item">
            <a class="nav-link position-relative <?= $active==='notifications'?'active':'' ?>" href="<?= url('/notifications') ?>" aria-label="<?= e(t('nav.notifications')) ?>">
              <i data-lucide="bell"></i>
              <?php if ($unreadN > 0): ?><span class="badge b-badge-dot"><?= $unreadN > 99 ? '99+' : $unreadN ?></span><?php endif; ?>
            </a>
          </li>
          <?php endif; ?>
          <li class="nav-item dropdown ms-1">
            <a class="nav-link dropdown-toggle b-user-chip" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <span class="b-avatar"><?= e(mb_strtoupper(mb_substr($user['name'], 0, 1))) ?></span>
              <span class="d-none d-xl-inline ms-1 fw-bold"><?= e(explode(' ', $user['name'])[0]) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end b-dropdown">
              <li><h6 class="dropdown-header"><?= e($user['email']) ?> · <?= e(t('role.' . $role)) ?></h6></li>
              <li><a class="dropdown-item" href="<?= url('/profile') ?>"><i data-lucide="user-cog"></i> <?= e(t('nav.profile')) ?></a></li>
              <?php if ($role === 'society_admin' && $society): ?><li><a class="dropdown-item" href="<?= url('/society/' . $society['slug']) ?>"><i data-lucide="building-2"></i> <?= e(t('footer.society')) ?></a></li><?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li>
                <form method="post" action="<?= url('/logout') ?>"><?= csrf_field() ?>
                  <button type="submit" class="dropdown-item text-danger"><i data-lucide="log-out"></i> <?= e(t('nav.logout')) ?></button>
                </form>
              </li>
            </ul>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </div>
</nav>

<!-- Mobile offcanvas menu (Blinkit Style) -->
<div class="offcanvas offcanvas-end b-offcanvas" tabindex="-1" id="mobileNav" aria-labelledby="mobileNavLabel">
  <div class="offcanvas-header">
    <div class="d-flex align-items-center gap-2">
      <img src="<?= asset('img/logo.svg') ?>" alt="logo" width="28" height="28" class="b-logo">
      <h5 class="offcanvas-title b-brand-text mb-0 fs-5" id="mobileNavLabel">bachat<span>.</span></h5>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body">
    <?php if (!$user): ?>
      <a class="b-mnav-link" href="<?= url('/') ?>"><i data-lucide="home"></i><span><?= e(t('nav.home')) ?></span></a>
      <a class="b-mnav-link" href="<?= url('/join') ?>"><i data-lucide="building-2"></i><span><?= e(t('nav.join')) ?></span></a>
      <a class="btn btn-brand w-100 mt-2" href="<?= url('/login') ?>"><?= e(t('nav.login')) ?></a>
    <?php else: ?>
      <!-- User profile card in drawer -->
      <div class="b-mnav-user-card">
        <div class="b-mnav-user-avatar">
          <?= strtoupper(substr($user['name'] ?? 'U', 0, 1)) ?>
        </div>
        <div class="overflow-hidden">
          <div class="fw-bold text-dark text-truncate" style="font-size:14.5px;"><?= e($user['name']) ?></div>
          <div class="small text-muted text-truncate" style="font-size:12px;">
            <?= e($society['name'] ?? 'Har Society Ki Smart Bachat') ?>
            <?php if (!empty($user['flat_no'])): ?> · Flat <?= e($user['flat_no']) ?><?php endif; ?>
          </div>
        </div>
      </div>

      <?php if ($role === 'resident'): ?>
        <div class="b-mnav-section-label">Shopping & Services</div>
        <a class="b-mnav-link <?= $active==='dashboard'?'active':'' ?>" href="<?= url('/dashboard') ?>"><i data-lucide="layout-dashboard"></i><span><?= e(t('nav.dashboard')) ?></span></a>
        <a class="b-mnav-link <?= $active==='shop'?'active':'' ?>" href="<?= url('/shop') ?>"><i data-lucide="shopping-basket"></i><span><?= e(t('nav.shop')) ?></span></a>
        <a class="b-mnav-link <?= $active==='campaigns'?'active':'' ?>" href="<?= url('/campaigns') ?>"><i data-lucide="users"></i><span>Bulk Deals</span></a>
        <a class="b-mnav-link <?= $active==='services'?'active':'' ?>" href="<?= url('/services') ?>"><i data-lucide="wrench"></i><span><?= e(t('nav.services')) ?></span></a>

        <div class="b-mnav-section-label">My Activity</div>
        <a class="b-mnav-link <?= $active==='orders'?'active':'' ?>" href="<?= url('/orders') ?>"><i data-lucide="package"></i><span><?= e(t('nav.orders')) ?></span></a>
        <a class="b-mnav-link <?= $active==='bookings'?'active':'' ?>" href="<?= url('/bookings') ?>"><i data-lucide="calendar-check"></i><span><?= e(t('nav.bookings')) ?></span></a>
        <a class="b-mnav-link <?= $active==='notices'?'active':'' ?>" href="<?= url('/notices') ?>"><i data-lucide="megaphone"></i><span><?= e(t('nav.notices')) ?></span></a>
        <a class="b-mnav-link <?= $active==='demand'?'active':'' ?>" href="<?= url('/demand') ?>"><i data-lucide="vote"></i><span><?= e(t('nav.demand')) ?></span></a>
        <a class="b-mnav-link <?= $active==='favourites'?'active':'' ?>" href="<?= url('/favourites') ?>"><i data-lucide="heart"></i><span><?= e(t('nav.favourites')) ?></span></a>
        <a class="b-mnav-link <?= $active==='lists'?'active':'' ?>" href="<?= url('/lists') ?>"><i data-lucide="list-checks"></i><span>Monthly Lists</span></a>
        <a class="b-mnav-link <?= $active==='reminders'?'active':'' ?>" href="<?= url('/reminders') ?>"><i data-lucide="alarm-clock"></i><span>Order Reminders</span></a>

        <div class="b-mnav-section-label">Account & Support</div>
        <a class="b-mnav-link <?= $active==='tickets'?'active':'' ?>" href="<?= url('/tickets') ?>"><i data-lucide="life-buoy"></i><span>Help & Support</span></a>
        <a class="b-mnav-link <?= $active==='notifications'?'active':'' ?>" href="<?= url('/notifications') ?>">
          <i data-lucide="bell"></i>
          <span><?= e(t('nav.notifications')) ?></span>
          <?php if ($unreadN): ?><span class="badge b-badge-purple ms-auto" style="font-size:10px;"><?= $unreadN ?></span><?php endif; ?>
        </a>
        <a class="b-mnav-link <?= $active==='profile'?'active':'' ?>" href="<?= url('/profile') ?>"><i data-lucide="user-cog"></i><span><?= e(t('nav.profile')) ?></span></a>

      <?php elseif (in_array($role, ['super_admin','society_admin'], true)): ?>
        <button class="btn btn-brand w-100 mb-3 text-start d-flex align-items-center justify-content-between b-btn-pill" type="button" data-bs-dismiss="offcanvas" data-bs-toggle="offcanvas" data-bs-target="#adminMobileDrawer">
          <span><i data-lucide="layout-grid" class="me-2"></i>Open Full Admin Panel</span>
          <i data-lucide="chevron-right"></i>
        </button>
        <a class="b-mnav-link" href="<?= url($role==='super_admin'?'/super/dashboard':'/admin/dashboard') ?>"><i data-lucide="layout-dashboard"></i><span><?= e(t('nav.dashboard')) ?></span></a>
        <?php if ($role === 'super_admin'): ?>
          <a class="b-mnav-link" href="<?= url('/super/societies') ?>"><i data-lucide="building-2"></i><span>Societies</span></a>
          <a class="b-mnav-link" href="<?= url('/super/users') ?>"><i data-lucide="users"></i><span>Users & Roles</span></a>
          <a class="b-mnav-link" href="<?= url('/super/catalog') ?>"><i data-lucide="shopping-basket"></i><span>Catalog & Groceries</span></a>
          <a class="b-mnav-link" href="<?= url('/super/vendors') ?>"><i data-lucide="store"></i><span>Vendors</span></a>
          <a class="b-mnav-link" href="<?= url('/super/campaigns') ?>"><i data-lucide="megaphone"></i><span>Bulk Campaigns</span></a>
          <a class="b-mnav-link" href="<?= url('/super/payments') ?>"><i data-lucide="badge-indian-rupee"></i><span>Payments</span></a>
          <a class="b-mnav-link" href="<?= url('/super/reports') ?>"><i data-lucide="bar-chart-3"></i><span>Reports & Analytics</span></a>
          <a class="b-mnav-link" href="<?= url('/super/settings') ?>"><i data-lucide="settings"></i><span>Settings</span></a>
        <?php else: ?>
          <a class="b-mnav-link" href="<?= url('/admin/members') ?>"><i data-lucide="users"></i><span>Members</span></a>
          <a class="b-mnav-link" href="<?= url('/admin/orders') ?>"><i data-lucide="package"></i><span>Orders</span></a>
          <a class="b-mnav-link" href="<?= url('/admin/payments') ?>"><i data-lucide="badge-indian-rupee"></i><span>Payments</span></a>
          <a class="b-mnav-link" href="<?= url('/admin/campaigns') ?>"><i data-lucide="megaphone"></i><span>Campaigns</span></a>
          <a class="b-mnav-link" href="<?= url('/admin/deliveries') ?>"><i data-lucide="bike"></i><span>Deliveries</span></a>
          <a class="b-mnav-link" href="<?= url('/admin/reports') ?>"><i data-lucide="bar-chart-3"></i><span>Reports</span></a>
        <?php endif; ?>
        <a class="b-mnav-link" href="<?= url('/profile') ?>"><i data-lucide="user-cog"></i><span><?= e(t('nav.profile')) ?></span></a>
      <?php elseif ($role === 'vendor'): ?>
        <a class="b-mnav-link" href="<?= url('/vendor/dashboard') ?>"><i data-lucide="store"></i><span><?= e(t('nav.vendor_panel')) ?></span></a>
        <a class="b-mnav-link" href="<?= url('/vendor/quotations') ?>"><i data-lucide="file-text"></i><span>Quotations</span></a>
        <a class="b-mnav-link" href="<?= url('/vendor/products') ?>"><i data-lucide="boxes"></i><span>Products</span></a>
        <a class="b-mnav-link" href="<?= url('/vendor/purchase-orders') ?>"><i data-lucide="truck"></i><span>Purchase orders</span></a>
        <a class="b-mnav-link" href="<?= url('/profile') ?>"><i data-lucide="user-cog"></i><span><?= e(t('nav.profile')) ?></span></a>
      <?php elseif ($role === 'delivery_staff'): ?>
        <a class="b-mnav-link" href="<?= url('/delivery/dashboard') ?>"><i data-lucide="map-pin"></i><span><?= e(t('nav.delivery_panel')) ?></span></a>
        <a class="b-mnav-link" href="<?= url('/delivery/history') ?>"><i data-lucide="history"></i><span>History</span></a>
        <a class="b-mnav-link" href="<?= url('/profile') ?>"><i data-lucide="user-cog"></i><span><?= e(t('nav.profile')) ?></span></a>
      <?php endif; ?>

      <div class="b-lang-wrap">
        <?= include_lang_switch($lang ?? 'en') ?>
      </div>

      <form method="post" action="<?= url('/logout') ?>" class="mt-3">
        <?= csrf_field() ?>
        <button class="b-btn-logout w-100" type="submit">
          <i data-lucide="log-out"></i>
          <span><?= e(t('nav.logout')) ?></span>
        </button>
      </form>
    <?php endif; ?>
  </div>
</div>
</header>

