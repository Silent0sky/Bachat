<?php
/**
 * Super admin — PO detail with receive + status actions. Vars: $po, $items
 */
$canReceive = in_array($po['status'], ['sent', 'accepted', 'partially_received'], true);
?>
<a href="<?= url('/super/purchase-orders') ?>" class="btn btn-sm btn-soft b-btn-pill mt-4">
  <i data-lucide="arrow-left" width="15" height="15" class="me-1"></i><?= e(t('common.back')) ?>
</a>

<div class="b-card mt-3">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
      <div class="small text-muted"><?= e(t('vendor.po_no')) ?></div>
      <h1 class="h4 fw-bold mb-1"><?= e($po['po_no']) ?></h1>
      <span class="<?= status_badge($po['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $po['status']))) ?></span>
    </div>
    <div class="text-md-end small text-muted">
      <div><i data-lucide="store" width="13" height="13" class="me-1"></i><?= e(t('proc.vendor')) ?>: <strong><?= e($po['vendor_name']) ?></strong></div>
      <div><i data-lucide="building-2" width="13" height="13" class="me-1"></i><?= e(t('proc.filter_society')) ?>: <?= e($po['society_name'] ?? t('common.na')) ?></div>
      <div><i data-lucide="user" width="13" height="13" class="me-1"></i><?= e(t('proc.created_by')) ?>: <?= e($po['creator_name'] ?? t('common.na')) ?></div>
      <div><i data-lucide="calendar" width="13" height="13" class="me-1"></i><?= e(t('vendor.expected')) ?>: <strong><?= e(human_date($po['expected_at'])) ?></strong></div>
    </div>
  </div>

  <?php if ($po['campaign_id'] !== null): ?>
    <a class="small text-brand d-inline-block mt-2" href="<?= url('/super/campaigns?id=' . (int) $po['campaign_id']) ?>">
      <i data-lucide="megaphone" width="13" height="13" class="me-1"></i><?= e(t('proc.campaign_title')) ?> #<?= (int) $po['campaign_id'] ?>
    </a>
  <?php endif; ?>

  <div class="table-responsive mt-3">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr>
          <th><?= e(t('common.name')) ?></th>
          <th><?= e(t('proc.ordered')) ?></th>
          <th><?= e(t('proc.received')) ?></th>
          <th><?= e(t('common.price')) ?></th>
          <th class="text-end"><?= e(t('common.total')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php $sum = 0.0; foreach ($items as $it): $line = round((float) $it['unit_price'] * (float) $it['qty'], 2); $sum += $line; ?>
        <tr>
          <td>
            <span class="fw-semibold"><?= e(product_name($it)) ?></span>
            <span class="small text-muted d-block"><?= e($it['brand_name'] ?? '') ?> <?= e(pack_label($it['pack_size'], $it['unit'])) ?></span>
          </td>
          <td><?= e(qty($it['qty'])) ?></td>
          <td><?= e(qty($it['received_qty'])) ?></td>
          <td><?= money($it['unit_price']) ?></td>
          <td class="text-end"><?= money($line) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td colspan="4" class="text-end fw-bold"><?= e(t('shop.grand_total')) ?></td><td class="text-end price fs-5"><?= money($po['total'] ?: $sum) ?></td></tr>
      </tfoot>
    </table>
  </div>

  <?php /* ---------- STATUS ACTIONS ---------- */ ?>
  <div class="d-flex flex-wrap gap-2 border-top pt-3 mt-3">
    <?php if ($po['status'] === 'draft'): ?>
      <form method="post" action="<?= url('/super/purchase-orders/' . (int) $po['id'] . '/status') ?>">
        <?= csrf_field() ?><input type="hidden" name="status" value="sent">
        <button class="btn btn-brand b-btn-pill"><i data-lucide="send" width="15" height="15" class="me-1"></i><?= e(t('proc.mark_sent')) ?></button>
      </form>
    <?php elseif ($po['status'] === 'sent'): ?>
      <form method="post" action="<?= url('/super/purchase-orders/' . (int) $po['id'] . '/status') ?>" data-confirm="<?= e(t('proc.po_cancel_confirm')) ?>">
        <?= csrf_field() ?><input type="hidden" name="status" value="cancelled">
        <button class="btn btn-outline-danger b-btn-pill"><i data-lucide="x-circle" width="15" height="15" class="me-1"></i><?= e(t('proc.cancel')) ?></button>
      </form>
    <?php endif; ?>
  </div>
</div>

<?php /* ---------- RECEIVE STOCK ---------- */ ?>
<?php if ($canReceive): ?>
<div class="b-card mt-3 mb-5" style="border:2px solid var(--b-green);">
  <h5 class="fw-bold mb-1"><i data-lucide="package-check" class="me-1 text-brand"></i><?= e(t('proc.receive')) ?></h5>
  <p class="text-muted small mb-3"><?= e(t('proc.receive_note')) ?></p>
  <form method="post" action="<?= url('/super/purchase-orders/' . (int) $po['id'] . '/receive') ?>" data-confirm="<?= e(t('proc.receive_confirm')) ?>">
    <?= csrf_field() ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-3">
        <thead><tr><th><?= e(t('common.name')) ?></th><th><?= e(t('proc.ordered')) ?></th><th><?= e(t('proc.received')) ?></th><th style="max-width:140px;"><?= e(t('proc.recv_qty')) ?></th></tr></thead>
        <tbody>
        <?php foreach ($items as $it):
            $remaining = (float) $it['qty'] - (float) $it['received_qty'];
        ?>
          <tr>
            <td>
              <span class="fw-semibold"><?= e(product_name($it)) ?></span>
              <span class="small text-muted d-block"><?= e(pack_label($it['pack_size'], $it['unit'])) ?></span>
            </td>
            <td><?= e(qty($it['qty'])) ?></td>
            <td><?= e(qty($it['received_qty'])) ?></td>
            <td>
              <?php if ($remaining > 0): ?>
                <input type="number" class="form-control form-control-sm" name="received_<?= (int) $it['id'] ?>"
                       min="0" max="<?= e(qty($remaining)) ?>" step="0.5" value="0" aria-label="<?= e(t('proc.recv_qty')) ?>">
              <?php else: ?>
                <span class="b-badge-green"><?= e(t('proc.dist_complete')) ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <button type="submit" class="btn btn-brand b-btn-pill"><i data-lucide="package-check" width="16" height="16" class="me-1"></i><?= e(t('proc.receive')) ?></button>
  </form>
</div>
<?php endif; ?>
