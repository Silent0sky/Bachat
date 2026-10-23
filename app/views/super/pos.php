<?php
/**
 * Super admin — all purchase orders. Vars: $pos
 */
?>
<h1 class="section-title mt-4"><?= e(t('proc.purchase_orders')) ?></h1>
<p class="text-muted mb-4"><?= e(t('proc.pos_sub')) ?></p>

<?php if (!$pos): ?>
<div class="empty-state b-card my-4"><i data-lucide="truck"></i><p class="mt-2"><?= e(t('proc.no_pos')) ?></p></div>
<?php else: ?>
<div class="table-responsive b-table">
  <table class="table align-middle mb-0">
    <thead>
      <tr>
        <th><?= e(t('vendor.po_no')) ?></th>
        <th><?= e(t('proc.vendor')) ?></th>
        <th><?= e(t('proc.filter_society')) ?></th>
        <th><?= e(t('proc.created_by')) ?></th>
        <th><?= e(t('vendor.items')) ?></th>
        <th><?= e(t('common.total')) ?></th>
        <th><?= e(t('proc.received')) ?></th>
        <th><?= e(t('vendor.expected')) ?></th>
        <th><?= e(t('common.status')) ?></th>
        <th></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($pos as $po): ?>
      <tr>
        <td class="fw-semibold"><?= e($po['po_no']) ?></td>
        <td class="small"><?= e($po['vendor_name']) ?></td>
        <td class="small"><?= e($po['society_name'] ?? t('common.na')) ?></td>
        <td class="small"><?= e($po['creator_name'] ?? t('common.na')) ?></td>
        <td><?= (int) $po['item_count'] ?></td>
        <td class="price"><?= money($po['total']) ?></td>
        <td class="small"><?= e(qty($po['received_qty'])) ?> / <?= e(qty($po['total_qty'])) ?></td>
        <td class="small"><?= e(human_date($po['expected_at'])) ?></td>
        <td><span class="<?= status_badge($po['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $po['status']))) ?></span></td>
        <td class="text-end">
          <a class="btn btn-sm btn-outline-brand b-btn-pill" href="<?= url('/super/purchase-orders/' . (int) $po['id']) ?>"><?= e(t('common.view')) ?></a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>
