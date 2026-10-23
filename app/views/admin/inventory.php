<?php
/**
 * Society admin — distribution view of campaign stock. Vars: $items
 */
?>
<h1 class="section-title mt-4"><?= e(t('proc.inventory')) ?></h1>
<p class="text-muted mb-4"><?= e(t('proc.dist_sub')) ?></p>

<?php if (!$items): ?>
<div class="empty-state b-card my-4"><i data-lucide="warehouse"></i><p class="mt-2"><?= e(t('proc.dist_empty')) ?></p></div>
<?php else: ?>
<div class="table-responsive b-table">
  <table class="table align-middle mb-0">
    <thead>
      <tr>
        <th><?= e(t('common.name')) ?></th>
        <th><?= e(t('proc.campaign_title')) ?></th>
        <th><?= e(t('proc.aggregated')) ?></th>
        <th><?= e(t('proc.distributed')) ?></th>
        <th><?= e(t('proc.remaining')) ?></th>
        <th style="min-width:260px;"><?= e(t('proc.distribute')) ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $it):
        $remaining = (float) $it['aggregated_qty'] - (float) $it['distributed_qty'];
    ?>
      <tr>
        <td>
          <span class="fw-semibold"><?= e(product_name($it)) ?></span>
          <span class="small text-muted d-block"><?= e($it['brand_name'] ?? '') ?> <?= e(pack_label($it['pack_size'], $it['unit'])) ?></span>
        </td>
        <td class="small">
          <a class="text-decoration-none" href="<?= url('/admin/campaigns/' . (int) $it['campaign_id']) ?>"><?= e($it['campaign_title']) ?></a>
          <span class="<?= status_badge($it['campaign_status']) ?> d-inline-block ms-1"><?= e(ucfirst($it['campaign_status'])) ?></span>
        </td>
        <td><?= e(qty($it['aggregated_qty'])) ?></td>
        <td><?= e(qty($it['distributed_qty'])) ?></td>
        <td class="fw-semibold"><?= e(qty($remaining)) ?></td>
        <td>
          <?php if ($remaining > 0): ?>
          <form method="post" action="<?= url('/admin/inventory/distribute') ?>" class="row g-1 align-items-center">
            <?= csrf_field() ?>
            <input type="hidden" name="campaign_item_id" value="<?= (int) $it['id'] ?>">
            <div class="col-4">
              <input type="number" class="form-control form-control-sm" name="qty" min="0.001" max="<?= e(qty($remaining)) ?>" step="0.5" value="<?= e(qty($remaining)) ?>" aria-label="<?= e(t('proc.dist_qty')) ?>">
            </div>
            <div class="col-5">
              <input type="text" class="form-control form-control-sm" name="note" maxlength="255" placeholder="<?= e(t('proc.dist_note')) ?>">
            </div>
            <div class="col-3">
              <button type="submit" class="btn btn-sm btn-brand b-btn-pill w-100"><?= e(t('proc.distribute')) ?></button>
            </div>
          </form>
          <?php else: ?>
            <span class="b-badge-green"><?= e(t('proc.dist_complete')) ?></span>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="b-note mt-3"><i data-lucide="info"></i><span><?= e(t('proc.dist_note_info')) ?></span></div>
