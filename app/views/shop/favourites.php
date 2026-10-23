<?php
/**
 * Favourites (Agent A).
 * Vars: $rows — favourited variants with product + effective price.
 */
?>
<div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-3">
  <h1 class="section-title"><?= e(t('shop.favourites_title')) ?></h1>
  <a href="<?= url('/shop') ?>" class="btn btn-soft b-btn-pill btn-sm">
    <i data-lucide="shopping-basket" class="me-1"></i><?= e(t('shop.continue_shopping')) ?>
  </a>
</div>

<?php if (!$rows): ?>
  <div class="b-card empty-state my-5">
    <i data-lucide="heart"></i>
    <p class="fw-semibold"><?= e(t('shop.fav_empty')) ?></p>
    <a href="<?= url('/shop') ?>" class="btn btn-brand b-btn-pill mt-2"><?= e(t('dash.shop_tile')) ?></a>
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($rows as $r):
        $stock  = (float) $r['stock_qty'];
        $maxQty = $r['max_qty'] !== null ? min((float) $r['max_qty'], max($stock, (float) $r['min_qty'])) : max($stock, (float) $r['min_qty']);
    ?>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="b-card product-buy h-100 fade-up">
        <div class="d-flex gap-3">
          <?php if (!empty($r['image'])): ?>
            <img src="<?= e(upload_url($r['image'])) ?>" alt="" width="72" height="72" class="rounded-3 flex-shrink-0" style="object-fit:cover;">
          <?php else: ?>
            <div class="rounded-3 d-flex align-items-center justify-content-center flex-shrink-0"
                 style="width:72px;height:72px;background:linear-gradient(135deg,#EAF6EF,#FDF6E7);">
              <i data-lucide="package" style="width:26px;height:26px;color:#9BC4AC;"></i>
            </div>
          <?php endif; ?>
          <div class="min-w-0 flex-grow-1">
            <a href="<?= url('/shop/product/' . (int) $r['product_id']) ?>" class="product-name text-decoration-none d-block text-truncate">
              <?= e(product_name($r)) ?>
            </a>
            <div class="small text-muted"><?= e($r['brand_name'] ?: '—') ?> · <?= e(pack_label($r['pack_size'], $r['unit'])) ?></div>
            <div class="mt-1"><span class="price"><?= e(money($r['price'])) ?></span>
              <?php if ((float) $r['reference_price'] > (float) $r['price']): ?>
                <span class="price-old ms-1"><?= e(money($r['reference_price'])) ?></span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <?php if ($stock > 0): ?>
        <div class="d-flex align-items-center gap-2 mt-3 flex-wrap">
          <div class="qty-stepper">
            <button type="button" class="qty-minus" aria-label="minus">−</button>
            <input type="number" class="qty-input" value="<?= e(qty(max((float) $r['min_qty'], 1))) ?>"
                   data-min="<?= e(qty($r['min_qty'])) ?>" data-max="<?= e(qty($maxQty)) ?>" data-step="1"
                   data-qty-for="<?= (int) $r['variant_id'] ?>" aria-label="<?= e(t('common.qty')) ?>">
            <button type="button" class="qty-plus" aria-label="plus">+</button>
          </div>
          <button type="button" class="btn btn-brand btn-sm b-btn-pill px-3" data-add-to-cart="<?= (int) $r['variant_id'] ?>">
            <i data-lucide="shopping-cart" class="me-1"></i><?= e(t('shop.add_to_cart')) ?>
          </button>
          <form method="post" action="<?= url('/favourites/toggle') ?>" class="ms-auto">
            <?= csrf_field() ?>
            <input type="hidden" name="variant_id" value="<?= (int) $r['variant_id'] ?>">
            <button type="submit" class="btn btn-soft btn-sm" title="<?= e(t('shop.remove_fav')) ?>">
              <i data-lucide="heart-off" style="width:15px;height:15px;"></i>
            </button>
          </form>
        </div>
        <?php else: ?>
        <div class="mt-3 d-flex justify-content-between align-items-center">
          <span class="b-badge-red"><?= e(t('shop.out_of_stock')) ?></span>
          <form method="post" action="<?= url('/favourites/toggle') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="variant_id" value="<?= (int) $r['variant_id'] ?>">
            <button type="submit" class="btn btn-soft btn-sm"><?= e(t('shop.remove_fav')) ?></button>
          </form>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
