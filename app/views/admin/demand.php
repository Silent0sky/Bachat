<?php
/**
 * Society admin — demand board moderation. Vars: $votes
 */
?>
<h1 class="section-title mt-4"><?= e(t('proc.demand_board')) ?></h1>
<p class="text-muted mb-4"><?= e(t('proc.demand_sub')) ?></p>

<?php if (!$votes): ?>
<div class="empty-state b-card my-4"><i data-lucide="vote"></i><p class="mt-2"><?= e(t('proc.demand_empty')) ?></p></div>
<?php else: ?>
<div class="table-responsive b-table">
  <table class="table align-middle mb-0">
    <thead>
      <tr>
        <th><?= e(t('demand.title_label')) ?></th>
        <th><?= e(t('demand.item_type')) ?></th>
        <th><?= e(t('demand.by')) ?></th>
        <th><?= e(t('demand.votes')) ?></th>
        <th><?= e(t('common.status')) ?></th>
        <th class="text-end"><?= e(t('common.actions')) ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($votes as $v): ?>
      <tr>
        <td>
          <span class="fw-semibold"><?= e($v['title']) ?></span>
          <?php if ($v['details']): ?><span class="small text-muted d-block"><?= e(mb_strimwidth((string) $v['details'], 0, 90, '…')) ?></span><?php endif; ?>
        </td>
        <td class="small"><?= e($v['item_type'] === 'service' ? t('demand.type_service') : t('demand.type_product')) ?></td>
        <td class="small"><?= e($v['creator_name']) ?><span class="text-muted d-block"><?= e(human_dt($v['created_at'])) ?></span></td>
        <td><span class="chip"><?= (int) $v['vote_count'] ?></span></td>
        <td><span class="<?= status_badge($v['status']) ?>"><?= e(ucfirst($v['status'])) ?></span></td>
        <td class="text-end">
          <?php if ($v['status'] === 'open'): ?>
            <form method="post" action="<?= url('/admin/demand/' . (int) $v['id'] . '/validate') ?>" class="d-inline">
              <?= csrf_field() ?>
              <button class="btn btn-sm btn-outline-brand b-btn-pill"><i data-lucide="check" width="13" height="13" class="me-1"></i><?= e(t('proc.validate')) ?></button>
            </form>
          <?php endif; ?>
          <?php if (in_array($v['status'], ['open', 'validated'], true)): ?>
            <form method="post" action="<?= url('/admin/demand/' . (int) $v['id'] . '/convert') ?>" class="d-inline" data-confirm="<?= e(t('proc.convert_confirm')) ?>">
              <?= csrf_field() ?>
              <button class="btn btn-sm btn-brand b-btn-pill"><i data-lucide="repeat" width="13" height="13" class="me-1"></i><?= e(t('proc.convert')) ?></button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<div class="b-note mt-3"><i data-lucide="info"></i><span><?= e(t('proc.demand_note')) ?></span></div>
