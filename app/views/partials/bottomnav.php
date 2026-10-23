<?php
/** Resident sticky bottom navigation (mobile-first). Vars: $active, cartCount passed as $cartCount */
$active    = $active ?? '';
$cartCount = isset($cartCount) ? (int) $cartCount : 0;
try {
    if (!isset($cartCount) || $cartCount === 0) {
        $u = auth_user();
        if ($u) {
            $cartCount = (int) \App\Core\Database::scalar(
                'SELECT COALESCE(SUM(ci.qty),0) FROM cart_items ci JOIN carts c ON c.id = ci.cart_id WHERE c.user_id = ?',
                [$u['id']]
            );
        }
    }
} catch (\Throwable $e) { $cartCount = 0; }
?>
<nav class="b-bottomnav" aria-label="Primary mobile navigation">
  <a href="<?= url('/dashboard') ?>" class="bn-item <?= $active === 'dashboard' ? 'active' : '' ?>">
    <i data-lucide="home"></i><span><?= e(t('nav.home')) ?></span>
  </a>
  <a href="<?= url('/shop') ?>" class="bn-item <?= $active === 'shop' ? 'active' : '' ?>">
    <i data-lucide="shopping-basket"></i><span><?= e(t('nav.shop')) ?></span>
  </a>
  <a href="<?= url('/cart') ?>" class="bn-item bn-cart <?= $active === 'cart' ? 'active' : '' ?>">
    <span class="bn-cart-wrap">
      <i data-lucide="shopping-cart"></i>
      <span class="badge b-badge-dot" id="cartBadge" <?= $cartCount ? '' : 'hidden' ?>><?= $cartCount ?></span>
    </span>
    <span><?= e(t('nav.cart')) ?></span>
  </a>
  <a href="<?= url('/campaigns') ?>" class="bn-item <?= $active === 'campaigns' ? 'active' : '' ?>">
    <i data-lucide="users"></i><span><?= e(t('nav.campaigns')) ?></span>
  </a>
  <button type="button" class="bn-item" data-bs-toggle="offcanvas" data-bs-target="#mobileNav" aria-label="<?= e(t('nav.more')) ?>">
    <i data-lucide="menu"></i><span><?= e(t('nav.more')) ?></span>
  </button>
</nav>
