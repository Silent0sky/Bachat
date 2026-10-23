<?php
/**
 * Bulk campaigns — resident listing.
 * Vars: $campaigns, $myInterest (campaign_id => status), $myOrders
 */
?>
<h1 class="section-title mt-4"><?= e(t('campaigns.title')) ?></h1>
<p class="text-muted"><?= e(t('campaigns.sub')) ?></p>

<?php if (!empty($myOrders)): ?>
<div class="b-card mb-4">
  <div class="d-flex justify-content-between align-items-center mb-2">
    <h6 class="fw-bold mb-0"><i data-lucide="package" class="me-1 text-brand"></i><?= e(t('campaigns.my_orders')) ?></h6>
    <a href="<?= url('/orders') ?>" class="btn btn-sm btn-soft b-btn-pill"><?= e(t('orders.my_orders')) ?></a>
  </div>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th><?= e(t('orders.order_no')) ?></th><th><?= e(t('common.total')) ?></th><th><?= e(t('common.status')) ?></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($myOrders as $o): ?>
        <tr>
          <td class="fw-semibold"><?= e($o['order_no']) ?></td>
          <td class="price"><?= money($o['total']) ?></td>
          <td><span class="<?= status_badge($o['status']) ?>"><?= e(ucwords(str_replace('_', ' ', $o['status']))) ?></span></td>
          <td class="text-end"><a class="btn btn-sm btn-outline-brand b-btn-pill" href="<?= url('/orders/' . $o['id']) ?>"><?= e(t('common.view')) ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if (!$campaigns): ?>
<div class="empty-state b-card my-4">
  <i data-lucide="megaphone"></i>
  <p class="mt-2"><?= e(t('campaigns.no_campaigns')) ?></p>
  <a href="<?= url('/demand') ?>" class="btn btn-outline-brand b-btn-pill mt-2"><?= e(t('nav.demand')) ?></a>
</div>
<?php else: ?>
<div class="row g-3 mb-5">
  <?php foreach ($campaigns as $c):
      $target = (float) $c['target_qty'];
      $pct = $target > 0 ? min(100, round(((float) $c['agg_qty'] / $target) * 100)) : 0;
      $mine = $myInterest[(int) $c['id']] ?? null;
  ?>
  <div class="col-12 col-md-6 col-xl-4">
    <div class="b-card b-card-hover h-100 d-flex flex-column fade-up">
      <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
        <span class="chip <?= $c['type'] === 'grocery' ? 'chip-active' : '' ?>">
          <i data-lucide="<?= $c['type'] === 'grocery' ? 'shopping-basket' : 'package' ?>" width="14" height="14"></i>
          <?= e(t('campaigns.type_' . $c['type'])) ?>
        </span>
        <?php if ($c['society_id'] === null): ?><span class="b-badge-blue"><?= e(t('campaigns.platform_wide')) ?></span><?php endif; ?>
      </div>
      <h5 class="fw-bold mb-1">
        <a class="text-decoration-none text-reset" href="<?= url('/campaigns/' . $c['id']) ?>"><?= e($c['title']) ?></a>
      </h5>
      <p class="text-muted small mb-2">
        <i data-lucide="clock" width="14" height="14" class="me-1"></i><?= e(t('campaigns.deadline_left', ['when' => human_dt($c['deadline'])])) ?>
        · <span class="text-brand fw-semibold"><?= e($c['countdown']) ?></span>
      </p>
      <div class="b-progress progress-thin mb-1" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
        <div class="b-progress-bar" style="width:<?= $pct ?>%"></div>
      </div>
      <div class="d-flex justify-content-between small text-muted mb-3">
        <span><?= e(t('campaigns.target')) ?>: <?= e(qty($c['agg_qty'])) ?> / <?= e(qty($c['target_qty'])) ?></span>
        <span><i data-lucide="users" width="14" height="14" class="me-1"></i><?= e(t('campaigns.interested_count', ['n' => (string) $c['interested_count']])) ?></span>
      </div>
      <div class="mt-auto">
        <?php if ($mine): ?>
          <span class="<?= $mine === 'confirmed' ? 'b-badge-green' : 'b-badge-amber' ?> mb-2">
            <i data-lucide="<?= $mine === 'confirmed' ? 'check-circle-2' : 'hand' ?>" width="13" height="13"></i>
            <?= e($mine === 'confirmed' ? t('campaigns.interest_confirmed') : t('campaigns.interested_by_you')) ?>
          </span>
        <?php endif; ?>
        <a href="<?= url('/campaigns/' . $c['id']) ?>" class="btn btn-brand w-100 b-btn-pill mt-1">
          <?= e(t('common.details')) ?> <i data-lucide="arrow-right" width="15" height="15" class="ms-1"></i>
        </a>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
