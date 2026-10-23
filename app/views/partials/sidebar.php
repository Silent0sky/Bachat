<?php
/**
 * Sidebar for super_admin / society_admin / vendor.
 * Desktop: sleek fixed sidebar (d-none d-lg-flex).
 * Mobile: slide-out drawer (#adminMobileDrawer) accessible via mobile bar or nav toggle.
 * Vars: $user, $active
 */
$role   = $user['role'] ?? null;
$active = $active ?? '';
$groups = [];

if ($role === 'super_admin') {
    $groups = [
        'Platform' => [
            ['super-dashboard', '/super/dashboard', 'layout-dashboard', 'Dashboard'],
            ['super-societies', '/super/societies', 'building-2', 'Societies'],
            ['super-users', '/super/users', 'users', 'Users & Roles'],
        ],
        'Catalog & Commerce' => [
            ['super-catalog', '/super/catalog', 'shopping-basket', 'Catalog & Groceries'],
            ['super-vendors', '/super/vendors', 'store', 'Vendors'],
            ['super-campaigns', '/super/campaigns', 'megaphone', 'Bulk Campaigns'],
            ['super-quotations', '/super/quotations', 'file-text', 'Quotations / RFQs'],
            ['super-purchase-orders', '/super/purchase-orders', 'truck', 'Purchase Orders'],
            ['super-inventory', '/super/inventory', 'warehouse', 'Inventory'],
        ],
        'Finance & Billing' => [
            ['super-payments', '/super/payments', 'badge-indian-rupee', 'Payments'],
            ['super-refunds', '/super/refunds', 'undo-2', 'Refunds'],
            ['super-coupons', '/super/coupons', 'ticket-percent', 'Coupons'],
            ['super-subscriptions', '/super/subscriptions', 'credit-card', 'Subscriptions'],
            ['super-commissions', '/super/commissions', 'percent', 'Commissions'],
        ],
        'Support & Insights' => [
            ['super-tickets', '/super/tickets', 'life-buoy', 'Support Tickets'],
            ['super-reviews', '/super/reviews', 'star', 'Reviews'],
            ['super-reports', '/super/reports', 'bar-chart-3', 'Analytics & Reports'],
            ['super-audit', '/super/audit', 'scroll-text', 'Audit Logs'],
            ['super-settings', '/super/settings', 'settings', 'Platform Settings'],
        ],
    ];
} elseif ($role === 'society_admin') {
    $groups = [
        'Overview' => [
            ['admin-dashboard', '/admin/dashboard', 'layout-dashboard', 'Dashboard'],
            ['admin-members', '/admin/members', 'users', 'Members & Approvals'],
            ['admin-flats', '/admin/flats', 'door-open', 'Flats & Residents'],
            ['admin-notices', '/admin/notices', 'megaphone', 'Notice Board'],
        ],
        'Bulk Supply & Orders' => [
            ['admin-orders', '/admin/orders', 'package', 'Orders'],
            ['admin-combined-orders', '/admin/orders?view=combined', 'boxes', 'Combined Products'],
            ['admin-payments', '/admin/payments', 'badge-indian-rupee', 'Payment Verification'],
            ['admin-deliveries', '/admin/deliveries', 'bike', 'Deliveries'],
            ['admin-campaigns', '/admin/campaigns', 'megaphone', 'Bulk Campaigns'],
            ['admin-inventory', '/admin/inventory', 'warehouse', 'Distribution'],
            ['admin-demand', '/admin/demand', 'vote', 'Demand Board'],
        ],
        'Services & Facilities' => [
            ['admin-service-campaigns', '/admin/service-campaigns', 'wrench', 'Service Deals'],
            ['admin-bookings', '/admin/bookings', 'calendar-check', 'Bookings'],
        ],
        'Settings & Reports' => [
            ['admin-tickets', '/admin/tickets', 'life-buoy', 'Support Desk'],
            ['admin-reviews', '/admin/reviews', 'star', 'Reviews'],
            ['admin-coupons', '/admin/coupons', 'ticket-percent', 'Coupons'],
            ['admin-reports', '/admin/reports', 'bar-chart-3', 'Reports'],
            ['admin-settings', '/admin/settings', 'settings', 'Society Settings'],
        ],
    ];
} elseif ($role === 'vendor') {
    $groups = [
        'Console' => [
            ['vendor-dashboard', '/vendor/dashboard', 'layout-dashboard', 'Dashboard'],
        ],
        'RFQs & Products' => [
            ['vendor-quotations', '/vendor/quotations', 'file-text', 'Quotations / RFQs'],
            ['vendor-products', '/vendor/products', 'boxes', 'My Products (Bulk CSV)'],
        ],
        'Orders & Services' => [
            ['vendor-purchase-orders', '/vendor/purchase-orders', 'truck', 'Purchase Orders'],
            ['vendor-bookings', '/vendor/bookings', 'calendar-check', 'Service Bookings'],
            ['vendor-reviews', '/vendor/reviews', 'star', 'Reviews & Ratings'],
        ],
    ];
}

$society = current_society();
?>

<!-- Desktop Fixed Sidebar -->
<aside class="b-sidebar d-none d-lg-flex flex-column">
  <div class="b-side-head">
    <div class="d-flex align-items-center gap-2 mb-1">
      <span class="b-side-badge"><?= $role === 'super_admin' ? 'PLATFORM' : ($role === 'vendor' ? 'VENDOR' : 'SOCIETY') ?></span>
      <span class="b-side-live-dot" title="System online"></span>
    </div>
    <span class="b-side-title"><?= e(t('nav.admin')) ?></span>
    <span class="b-side-sub"><?= e($society['name'] ?? ($role === 'super_admin' ? 'Central Console' : 'Vendor Portal')) ?></span>
  </div>

  <nav class="b-side-nav flex-grow-1" aria-label="Panel navigation">
    <?php foreach ($groups as $groupTitle => $items): ?>
      <div class="b-side-section-label"><?= e($groupTitle) ?></div>
      <?php foreach ($items as [$key, $href, $icon, $label]): ?>
        <a href="<?= url($href) ?>" class="b-side-link <?= $active === $key ? 'active' : '' ?>">
          <i data-lucide="<?= e($icon) ?>"></i>
          <span><?= e($label) ?></span>
        </a>
      <?php endforeach; ?>
    <?php endforeach; ?>
  </nav>

  <div class="b-side-footer">
    <div class="d-flex align-items-center gap-2">
      <span class="b-avatar b-avatar-sm"><?= e(mb_strtoupper(mb_substr($user['name'] ?? 'U', 0, 1))) ?></span>
      <div class="min-w-0 flex-grow-1">
        <div class="b-side-user-name text-truncate"><?= e($user['name'] ?? '') ?></div>
        <div class="b-side-user-role"><?= e(t('role.' . $role)) ?></div>
      </div>
      <form method="post" action="<?= url('/logout') ?>" class="m-0"><?= csrf_field() ?>
        <button type="submit" class="btn btn-sm btn-icon text-muted" title="Logout" style="padding:4px 8px;">
          <i data-lucide="log-out" width="16" height="16"></i>
        </button>
      </form>
    </div>
  </div>
</aside>

<!-- Mobile Slide-out Drawer for Admin & Vendor -->
<div class="offcanvas offcanvas-start b-offcanvas b-admin-offcanvas d-lg-none" tabindex="-1" id="adminMobileDrawer" aria-labelledby="adminMobileDrawerLabel">
  <div class="offcanvas-header border-bottom">
    <div class="d-flex align-items-center gap-2">
      <img src="<?= asset('img/logo.svg') ?>" alt="Logo" width="28" height="28" class="b-logo">
      <div>
        <h5 class="offcanvas-title b-brand-text mb-0" id="adminMobileDrawerLabel" style="font-size:17px;"><?= e(t('nav.admin')) ?></h5>
        <small class="text-muted" style="font-size:11px;"><?= e($society['name'] ?? ($role === 'super_admin' ? 'Central Console' : 'Vendor Portal')) ?></small>
      </div>
    </div>
    <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
  </div>
  <div class="offcanvas-body p-3">
    <nav aria-label="Mobile panel navigation">
      <?php foreach ($groups as $groupTitle => $items): ?>
        <div class="b-side-section-label mt-3 first:mt-0"><?= e($groupTitle) ?></div>
        <?php foreach ($items as [$key, $href, $icon, $label]): ?>
          <a href="<?= url($href) ?>" class="b-side-link <?= $active === $key ? 'active' : '' ?>">
            <i data-lucide="<?= e($icon) ?>"></i>
            <span><?= e($label) ?></span>
          </a>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
  </div>
</div>
