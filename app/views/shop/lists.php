<?php
/**
 * Saved shopping lists (Agent A).
 * Vars: $lists (with item_count), $items (flat rows joined to lists).
 */
$byList = [];
foreach ($items as $it) {
    $byList[(int) $it['list_id']][] = $it;
}
?>
<div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h1 class="section-title"><?= e(t('shop.lists_title')) ?></h1>
    <p class="text-muted small mb-0"><?= e(t('shop.lists_sub')) ?></p>
  </div>
  <a href="<?= url('/shop') ?>" class="btn btn-soft b-btn-pill btn-sm">
    <i data-lucide="shopping-basket" class="me-1"></i><?= e(t('nav.shop')) ?>
  </a>
</div>

<div class="row g-4">
  <div class="col-12 col-lg-4">
    <div class="b-card fade-up">
      <h2 class="fs-6 fw-bold mb-3"><?= e(t('shop.new_list')) ?></h2>
      <form method="post" action="<?= url('/lists') ?>">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label fw-semibold small" for="nl-name"><?= e(t('shop.list_name')) ?></label>
          <input type="text" class="form-control" id="nl-name" name="name" value="<?= e(old('name')) ?>"
                 placeholder="Monthly staples" maxlength="100" required>
        </div>
        <button type="submit" class="btn btn-brand w-100 b-btn-pill">
          <i data-lucide="plus" class="me-1"></i><?= e(t('common.create')) ?>
        </button>
      </form>
    </div>
  </div>

  <div class="col-12 col-lg-8">
    <?php if (!$lists): ?>
      <div class="b-card empty-state">
        <i data-lucide="list-checks"></i>
        <p><?= e(t('shop.no_lists')) ?></p>
      </div>
    <?php else: ?>
      <?php foreach ($lists as $l): $li = $byList[(int) $l['id']] ?? []; ?>
      <div class="b-card mb-3 fade-up">
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
          <div>
            <span class="fw-bold"><?= e($l['name']) ?></span>
            <span class="b-badge-grey ms-2"><?= e((int) $l['item_count'] === 1 ? t('shop.list_items_one') : t('shop.list_items_many', ['n' => (int) $l['item_count']])) ?></span>
          </div>
          <div class="d-flex gap-2">
            <form method="post" action="<?= url('/lists/' . (int) $l['id'] . '/to-cart') ?>">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-brand btn-sm b-btn-pill" <?= $li ? '' : 'disabled' ?>>
                <i data-lucide="shopping-cart" class="me-1"></i><?= e(t('shop.to_cart')) ?>
              </button>
            </form>
            <form method="post" action="<?= url('/lists/' . (int) $l['id'] . '/delete') ?>" data-confirm="<?= e(t('shop.confirm_remove') . ' (' . e($l['name']) . ')') ?>">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-soft btn-sm" title="<?= e(t('common.delete')) ?>">
                <i data-lucide="trash-2" style="width:15px;height:15px;"></i>
              </button>
            </form>
          </div>
        </div>

        <?php if (!$li): ?>
          <p class="small text-muted mb-0"><?= e(t('shop.list_empty')) ?></p>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table table-sm align-middle mb-2">
              <tbody>
              <?php foreach ($li as $it): ?>
                <tr>
                  <td class="ps-0">
                    <a href="<?= url('/shop/product/' . (int) $it['product_id']) ?>" class="text-decoration-none text-dark fw-semibold small">
                      <?= e(product_name($it)) ?>
                    </a>
                    <div class="text-muted" style="font-size:12px;">
                      <?= e($it['brand_name'] ?: '—') ?> · <?= e(pack_label($it['pack_size'], $it['unit'])) ?>
                      <?php if ((float) $it['stock_qty'] <= 0): ?>
                        <span class="b-badge-red ms-1"><?= e(t('shop.out_of_stock')) ?></span>
                      <?php endif; ?>
                    </div>
                  </td>
                  <td class="text-nowrap">× <?= e(qty($it['qty'])) ?></td>
                  <td class="text-end text-nowrap fw-semibold"><?= e(money(round((float) $it['price'] * (float) $it['qty'], 2))) ?></td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>

        <details class="mt-1">
          <summary class="small text-brand fw-semibold" style="cursor:pointer;"><?= e(t('shop.list_add_item')) ?></summary>
          <form method="post" action="<?= url('/lists/' . (int) $l['id'] . '/items') ?>" class="row g-2 mt-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-7 col-md-5">
              <label class="form-label small text-muted mb-1" for="vi-<?= (int) $l['id'] ?>"><?= e(t('shop.variant_id')) ?></label>
              <input type="number" class="form-control form-control-sm" id="vi-<?= (int) $l['id'] ?>" name="variant_id" min="1" required>
            </div>
            <div class="col-5 col-md-3">
              <label class="form-label small text-muted mb-1" for="vq-<?= (int) $l['id'] ?>"><?= e(t('shop.qty')) ?></label>
              <input type="number" class="form-control form-control-sm" id="vq-<?= (int) $l['id'] ?>" name="qty" value="1" min="0.5" step="1" required>
            </div>
            <div class="col-12 col-md-4">
              <button type="submit" class="btn btn-outline-brand btn-sm b-btn-pill w-100"><?= e(t('common.add')) ?></button>
            </div>
          </form>
        </details>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
