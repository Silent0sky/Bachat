<?php
/** Cart page. Vars: $items (cart rows), $subtotal (float) */
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4">
    <div>
      <h1 class="section-title mb-1"><?= e(t('shop.cart_title')) ?></h1>
      <p class="text-muted small mb-0"><?= e(t('shop.in_cart_note')) ?></p>
    </div>
    <?php if (!empty($items)): ?>
      <a class="btn btn-brand b-btn-pill" href="<?= url('/checkout') ?>">
        <i data-lucide="arrow-right-circle" width="16" height="16"></i> <?= e(t('shop.proceed_checkout')) ?>
      </a>
    <?php endif; ?>
  </div>

  <?php if (empty($items)): ?>
    <div class="empty-state b-card">
      <i data-lucide="shopping-cart"></i>
      <p><?= e(t('shop.cart_empty')) ?></p>
      <a class="btn btn-brand b-btn-pill mt-3" href="<?= url('/shop') ?>">
        <i data-lucide="shopping-basket" width="15" height="15"></i> <?= e(t('shop.empty_cart_cta')) ?>
      </a>
    </div>
  <?php else: ?>
  <div class="row g-3">
    <div class="col-lg-8">
      <?php foreach ($items as $it):
          $dead = !(int) $it['variant_active'] || !(int) $it['product_active'] || (float) $it['stock_qty'] <= 0;
          $max  = $it['max_qty'] !== null ? min((float) $it['max_qty'], (float) $it['stock_qty']) : (float) $it['stock_qty'];
          $min  = max((float) $it['min_qty'], 0.001);
          $step = (float) $it['min_qty'] < 1 ? (float) $it['min_qty'] : 1;
      ?>
      <div class="b-card mb-3 <?= $dead ? 'opacity-75' : '' ?>">
        <div class="d-flex gap-3">
          <?php if (!empty($it['image'])): ?>
            <img src="<?= e(upload_url($it['image'])) ?>" alt="<?= e(product_name($it)) ?>" class="rounded-3 flex-shrink-0" style="width:64px;height:64px;object-fit:cover;">
          <?php else: ?>
            <div class="b-icon-tile flex-shrink-0 mb-0"><i data-lucide="package"></i></div>
          <?php endif; ?>

          <div class="flex-grow-1" style="min-width:0;">
            <div class="d-flex justify-content-between gap-2">
              <div style="min-width:0;">
                <a class="fw-bold text-dark text-decoration-none d-block text-truncate" href="<?= url('/shop/product/' . (int) $it['product_id']) ?>">
                  <?= e(product_name($it)) ?>
                </a>
                <div class="small text-muted">
                  <?= e($it['brand_name'] ?? '') ?>
                  · <?= e(pack_label($it['pack_size'], $it['unit'])) ?>
                  · <span class="text-muted"><?= e($it['sku']) ?></span>
                </div>
              </div>
              <div class="text-end flex-shrink-0">
                <div class="price"><?= e(money($it['unit_price'])) ?></div>
                <?php if ((float) $it['reference_price'] > (float) $it['unit_price']): ?>
                  <div class="price-old"><?= e(money($it['reference_price'])) ?></div>
                <?php endif; ?>
              </div>
            </div>

            <?php if ($dead): ?>
              <div class="b-note mt-2"><i data-lucide="alert-triangle"></i><span><?= e(t('shop.out_of_stock_note')) ?></span></div>
            <?php endif; ?>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
              <form method="post" action="<?= url('/cart/update') ?>" class="d-flex align-items-center gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="variant_id" value="<?= (int) $it['variant_id'] ?>">
                <div class="qty-stepper">
                  <button type="button" class="qty-btn qty-minus" aria-label="decrease">−</button>
                  <input type="number" class="qty-input" name="qty" value="<?= e(qty($it['qty'])) ?>"
                         data-min="<?= e((string) $min) ?>" data-max="<?= e((string) max($min, $max)) ?>" data-step="<?= e((string) $step) ?>"
                         inputmode="decimal" <?= $dead ? 'disabled' : '' ?>>
                  <button type="button" class="qty-btn qty-plus" aria-label="increase">+</button>
                </div>
                <button type="submit" class="btn btn-soft btn-sm" <?= $dead ? 'disabled' : '' ?>>
                  <i data-lucide="refresh-cw" width="14" height="14"></i> <?= e(t('common.update')) ?>
                </button>
              </form>

              <div class="d-flex align-items-center gap-3">
                <div class="small text-muted"><?= e(t('shop.line_total')) ?></div>
                <div class="price"><?= e(money((float) $it['unit_price'] * (float) $it['qty'])) ?></div>
                <form method="post" action="<?= url('/cart/remove') ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="variant_id" value="<?= (int) $it['variant_id'] ?>">
                  <button type="submit" class="btn btn-outline-danger btn-sm" aria-label="<?= e(t('common.delete')) ?>">
                    <i data-lucide="trash-2" width="14" height="14"></i>
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="col-lg-4">
      <div class="b-card sticky-top" style="top:84px;">
        <h2 class="h6 fw-bold text-brand text-uppercase mb-3"><?= e(t('checkout.totals')) ?></h2>
        <div class="d-flex justify-content-between mb-1">
          <span class="text-muted"><?= e(t('shop.subtotal')) ?></span>
          <strong><?= e(money($subtotal)) ?></strong>
        </div>
        <div class="d-flex justify-content-between mb-1 small text-muted">
          <span><?= e(t('checkout.items')) ?></span>
          <span><?= count($items) ?></span>
        </div>
        <hr>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <span class="fw-semibold"><?= e(t('shop.grand_total')) ?></span>
          <span class="price fs-5"><?= e(money($subtotal)) ?></span>
        </div>
        <a class="btn btn-brand w-100 b-btn-pill" href="<?= url('/checkout') ?>">
          <i data-lucide="arrow-right-circle" width="16" height="16"></i> <?= e(t('shop.proceed_checkout')) ?>
        </a>
        <a class="btn btn-soft w-100 b-btn-pill mt-2" href="<?= url('/shop') ?>"><?= e(t('shop.continue_shopping')) ?></a>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
