<?php
/**
 * Mobile Admin Bar: Rendered only on screens < 992px for super_admin, society_admin, vendor.
 * Provides instant 1-tap navigation and quick menu drawer toggle.
 */
$role = $user['role'] ?? null;
$active = $active ?? '';

$quickPills = [];
if ($role === 'super_admin') {
    $quickPills = [
        ['super-dashboard', '/super/dashboard', 'layout-dashboard', 'Dashboard'],
        ['super-societies', '/super/societies', 'building-2', 'Societies'],
        ['super-users', '/super/users', 'users', 'Users'],
        ['super-catalog', '/super/catalog', 'shopping-basket', 'Catalog'],
        ['super-vendors', '/super/vendors', 'store', 'Vendors'],
        ['super-campaigns', '/super/campaigns', 'megaphone', 'Campaigns'],
        ['super-payments', '/super/payments', 'badge-indian-rupee', 'Payments'],
        ['super-reports', '/super/reports', 'bar-chart-3', 'Reports'],
        ['super-settings', '/super/settings', 'settings', 'Settings'],
    ];
} elseif ($role === 'society_admin') {
    $quickPills = [
        ['admin-dashboard', '/admin/dashboard', 'layout-dashboard', 'Dashboard'],
        ['admin-members', '/admin/members', 'users', 'Members'],
        ['admin-orders', '/admin/orders', 'package', 'Orders'],
        ['admin-payments', '/admin/payments', 'badge-indian-rupee', 'Payments'],
        ['admin-campaigns', '/admin/campaigns', 'megaphone', 'Campaigns'],
        ['admin-deliveries', '/admin/deliveries', 'bike', 'Deliveries'],
        ['admin-bookings', '/admin/bookings', 'calendar-check', 'Bookings'],
        ['admin-reports', '/admin/reports', 'bar-chart-3', 'Reports'],
    ];
} elseif ($role === 'vendor') {
    $quickPills = [
        ['vendor-dashboard', '/vendor/dashboard', 'layout-dashboard', 'Dashboard'],
        ['vendor-quotations', '/vendor/quotations', 'file-text', 'Quotations'],
        ['vendor-products', '/vendor/products', 'boxes', 'Products'],
        ['vendor-purchase-orders', '/vendor/purchase-orders', 'truck', 'Purchase Orders'],
        ['vendor-bookings', '/vendor/bookings', 'calendar-check', 'Bookings'],
    ];
}

$roleLabel = match($role) {
    'super_admin' => 'Super Admin',
    'society_admin' => 'Society Admin',
    'vendor' => 'Vendor Portal',
    default => 'Admin'
};
?>
<div class="b-mobile-admin-bar d-lg-none mb-3">
  <div class="d-flex align-items-center justify-content-between p-2 px-3 b-card-bar">
    <div class="d-flex align-items-center gap-2 min-w-0">
      <span class="badge b-badge-green text-uppercase" style="font-size:10px; font-weight:700; letter-spacing:0.5px;"><?= $roleLabel ?></span>
      <span class="text-white fw-bold small text-truncate" style="letter-spacing:-0.2px;"><?= e($pageTitle ?? 'Console') ?></span>
    </div>
    <button class="btn btn-sm btn-outline-brand b-btn-pill d-inline-flex align-items-center gap-1 py-1 px-2.5" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminMobileDrawer" aria-label="Open full panel menu">
      <i data-lucide="layout-grid" width="14" height="14"></i>
      <span style="font-size:12px;">Menu</span>
    </button>
  </div>

  <?php if (!empty($quickPills)): ?>
  <div class="b-mobile-pills-wrap mt-2">
    <div class="b-mobile-pills">
      <?php foreach ($quickPills as [$k, $href, $icon, $label]): ?>
        <a href="<?= url($href) ?>" class="b-pill-tab <?= $active === $k ? 'active' : '' ?>">
          <i data-lucide="<?= e($icon) ?>" width="14" height="14"></i>
          <span><?= e($label) ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
