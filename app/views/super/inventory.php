<?php
/**
 * Super admin — central inventory + stock adjustment. Vars: $rows, $variants, $types, $low
 */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mt-4 mb-3 gap-2">
  <div>
    <h1 class="section-title mb-1"><?= e(t('proc.inventory_platform')) ?></h1>
    <p class="text-muted mb-0"><?= e(t('proc.inv_sub')) ?></p>
  </div>
  <div class="d-flex gap-2">
    <a class="chip <?= $low ? 'chip-active' : '' ?>" href="<?= url('/super/inventory') . ($low ? '' : '?low=1') ?>">
      <i data-lucide="alert-triangle" width="14" height="14"></i> <?= e(t('proc.low_stock')) ?>
    </a>
    <button class="btn btn-brand b-btn-pill" type="button" data-bs-toggle="collapse" data-bs-target="#adjustStock">
      <i data-lucide="sliders-horizontal" width="16" height="16" class="me-1"></i><?= e(t('proc.adjust')) ?>
    </button>
  </div>
</div>

<?php /* ---------- ADJUST FORM ---------- */ ?>
<div class="collapse mb-4" id="adjustStock">
  <div class="b-card">
    <h5 class="fw-bold mb-3"><?= e(t('proc.adjust')) ?></h5>
    <form method="post" action="<?= url('/super/inventory/adjust') ?>">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-12 col-md-4">
          <label class="form-label small fw-semibold"><?= e(t('proc.variant')) ?></label>
          <select class="form-select" name="variant_id" required>
            <option value="">—</option>
            <?php foreach ($variants as $v): ?>
              <option value="<?= (int) $v['id'] ?>"><?= e(product_name($v)) ?> · <?= e($v['brand_name'] ?? '') ?> · <?= e(pack_label($v['pack_size'], $v['unit'])) ?> (#<?= (int) $v['id'] ?>)</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small fw-semibold"><?= e(t('proc.adj_type')) ?></label>
          <select class="form-select" name="type">
            <?php foreach ($types as $ty): ?><option value="<?= e($ty) ?>"><?= e($ty) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div class="col-6 col-md-2">
          <label class="form-label small fw-semibold"><?= e(t('proc.adj_qty')) ?></label>
          <input type="number" class="form-control" name="qty" step="0.5" required>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label small fw-semibold"><?= e(t('proc.adj_note')) ?></label>
          <input type="text" class="form-control" name="note" maxlength="255">
        </div>
      </div>
      <div class="b-note mt-3"><i data-lucide="info"></i><span><?= e(t('proc.adj_note_info')) ?></span></div>
      <button type="submit" class="btn btn-brand b-btn-pill mt-3"><?= e(t('common.save')) ?></button>
    </form>
  </div>
</div>

<?php if (!$rows): ?>
<div class="empty-state b-card my-4"><i data-lucide="warehouse"></i><p class="mt-2"><?= e(t('proc.inv_empty')) ?></p></div>
<?php else: ?>
<div class="table-responsive b-table mb-5">
  <table class="table align-middle mb-0">
    <thead>
      <tr>
        <th><?= e(t('common.name')) ?></th>
        <th>SKU</th>
        <th><?= e(t('proc.available')) ?></th>
        <th><?= e(t('proc.received')) ?></th>
        <th><?= e(t('proc.reserved')) ?></th>
        <th><?= e(t('proc.dispatched')) ?></th>
        <th><?= e(t('proc.damaged')) ?></th>
        <th><?= e(t('proc.sellable_stock')) ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): $avail = (float) $r['available']; ?>
      <tr>
        <td>
          <span class="fw-semibold"><?= e(product_name($r)) ?></span>
          <span class="small text-muted d-block"><?= e($r['brand_name'] ?? '') ?> <?= e(pack_label($r['pack_size'], $r['unit'])) ?></span>
        </td>
        <td class="small text-muted"><?= e($r['sku'] ?? '—') ?></td>
        <td class="fw-bold <?= $avail <= 10 ? 'text-danger' : '' ?>"><?= e(qty($r['available'])) ?>
          <?php if ($avail <= 10): ?><span class="b-badge-red ms-1"><?= e(t('proc.low_stock')) ?></span><?php endif; ?>
        </td>
        <td><?= e(qty($r['received'])) ?></td>
        <td><?= e(qty($r['reserved'])) ?></td>
        <td><?= e(qty($r['dispatched'])) ?></td>
        <td><?= e(qty($r['damaged'])) ?></td>
        <td><?= e(qty($r['stock_qty'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
