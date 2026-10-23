<?php
/**
 * Super admin — procurement console.
 * Tab "campaigns": platform-wide campaign list + filters + manage panel (?id=).
 * Tab "quotations": all vendor quotations with select/reject.
 * Vars (campaigns tab): $campaigns, $societies, $statuses, $selCamp, $selItems, $selQuotes
 * Vars (quotations tab): $quotations, $statuses, $status
 */
$tab = $tab ?? 'campaigns';
?>
<h1 class="section-title mt-4"><?= e($tab === 'quotations' ? t('proc.quotations') : t('proc.platform_campaigns')) ?></h1>
<p class="text-muted mb-3"><?= e(t('proc.platform_note')) ?></p>

<ul class="nav b-nav-pills gap-2 mb-4">
  <li class="nav-item">
    <a class="chip <?= $tab === 'campaigns' ? 'chip-active' : '' ?>" href="<?= url('/super/campaigns') ?>">
      <i data-lucide="megaphone" width="14" height="14"></i> <?= e(t('proc.campaigns')) ?>
    </a>
  </li>
  <li class="nav-item">
    <a class="chip <?= $tab === 'quotations' ? 'chip-active' : '' ?>" href="<?= url('/super/quotations') ?>">
      <i data-lucide="file-text" width="14" height="14"></i> <?= e(t('proc.quotations')) ?>
    </a>
  </li>
</ul>

<?php if ($tab === 'campaigns'): ?>

  <?php /* ---------- FILTERS ---------- */ ?>
  <form method="get" action="<?= url('/super/campaigns') ?>" class="b-card row g-2 align-items-end mb-3 py-3">
    <div class="col-6 col-md-4">
      <label class="form-label small fw-semibold"><?= e(t('proc.filter_society')) ?></label>
      <select class="form-select form-select-sm" name="society">
        <option value=""><?= e(t('common.all')) ?></option>
        <?php foreach ($societies as $s): ?>
          <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label small fw-semibold"><?= e(t('proc.filter_status')) ?></label>
      <select class="form-select form-select-sm" name="status">
        <option value=""><?= e(t('common.all')) ?></option>
        <?php foreach ($statuses as $st): ?><option value="<?= e($st) ?>"><?= e(ucfirst($st)) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <button class="btn btn-sm btn-brand b-btn-pill w-100"><?= e(t('common.filter')) ?></button>
    </div>
  </form>

  <?php /* ---------- CAMPAIGN LIST ---------- */ ?>
  <?php if (!$campaigns): ?>
  <div class="empty-state b-card my-4"><i data-lucide="megaphone"></i><p class="mt-2"><?= e(t('proc.no_campaigns')) ?></p></div>
  <?php else: ?>
  <div class="table-responsive b-table">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th><?= e(t('proc.campaign_title')) ?></th>
          <th><?= e(t('proc.filter_society')) ?></th>
          <th><?= e(t('common.status')) ?></th>
          <th><?= e(t('proc.progress')) ?></th>
          <th><?= e(t('common.deadline')) ?></th>
          <th><?= e(t('proc.quotations')) ?></th>
          <th class="text-end"><?= e(t('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($campaigns as $c):
          $target = (float) $c['target_qty'];
          $pct = $target > 0 ? min(100, round(((float) $c['agg_qty'] / $target) * 100)) : 0;
      ?>
        <tr>
          <td class="fw-semibold"><?= e($c['title']) ?></td>
          <td class="small"><?= $c['society_name'] !== null ? e($c['society_name']) : '<span class="b-badge-blue">' . e(t('campaigns.platform_wide')) . '</span>' ?></td>
          <td><span class="<?= status_badge($c['status']) ?>"><?= e(ucfirst($c['status'])) ?></span></td>
          <td style="min-width:120px;">
            <div class="b-progress progress-thin"><div class="b-progress-bar" style="width:<?= $pct ?>%"></div></div>
            <span class="small text-muted"><?= e(qty($c['agg_qty'])) ?>/<?= e(qty($c['target_qty'])) ?></span>
          </td>
          <td class="small"><?= e(human_dt($c['deadline'])) ?></td>
          <td><?= (int) $c['quotation_count'] ?></td>
          <td class="text-end">
            <div class="d-inline-flex gap-1">
              <a class="btn btn-sm btn-outline-brand b-btn-pill" href="<?= url('/super/campaigns?id=' . $c['id']) ?>"><?= e(t('proc.manage')) ?></a>
              <?php if ($c['status'] !== 'cancelled'): ?>
              <form method="post" action="<?= url('/super/campaigns/' . (int) $c['id'] . '/cancel') ?>" data-confirm="<?= e(t('proc.cancel_confirm')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger b-btn-pill" title="<?= e(t('proc.cancel')) ?>"><i data-lucide="x-circle" width="14" height="14"></i></button>
              </form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

  <?php /* ---------- MANAGE PANEL (?id=) ---------- */ ?>
  <?php if ($selCamp):
      $allPriced = $selItems !== [] && !in_array(null, array_map(static fn ($i) => $i['confirmed_price'], $selItems), true);
  ?>
  <div class="b-card mt-4" style="border:2px solid var(--b-green);">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
      <div>
        <h2 class="h5 fw-bold mb-1"><?= e($selCamp['title']) ?></h2>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <span class="<?= status_badge($selCamp['status']) ?>"><?= e(ucfirst($selCamp['status'])) ?></span>
          <span class="small text-muted"><?= $selCamp['society_name'] !== null ? e($selCamp['society_name']) : e(t('campaigns.platform_wide')) ?>
            · <?= e(t('common.deadline')) ?>: <?= e(human_dt($selCamp['deadline'])) ?></span>
        </div>
      </div>
      <a class="btn btn-sm btn-soft b-btn-pill" href="<?= url('/super/campaigns') ?>"><?= e(t('common.close')) ?></a>
    </div>

    <div class="table-responsive mt-3">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th><?= e(t('common.name')) ?></th>
            <th><?= e(t('proc.target_qty')) ?></th>
            <th><?= e(t('proc.aggregated')) ?></th>
            <th><?= e(t('campaigns.quoted_price')) ?></th>
            <th><?= e(t('campaigns.final_price')) ?></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($selItems as $it): ?>
          <tr>
            <td>
              <span class="fw-semibold"><?= e(product_name($it)) ?></span>
              <span class="small text-muted d-block"><?= e($it['brand_name'] ?? '') ?> <?= e(pack_label($it['pack_size'], $it['unit'])) ?></span>
            </td>
            <td><?= e(qty($it['target_qty'])) ?></td>
            <td class="fw-semibold"><?= e(qty($it['aggregated_qty'])) ?></td>
            <td><?= $it['quoted_price'] !== null ? money($it['quoted_price']) : '—' ?></td>
            <td><?= $it['confirmed_price'] !== null ? '<span class="price">' . money($it['confirmed_price']) . '</span>' : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <?php /* set final prices */ ?>
    <?php if ($selItems): ?>
    <form method="post" action="<?= url('/super/campaigns/' . (int) $selCamp['id'] . '/prices') ?>" class="border-top pt-3 mt-3">
      <?= csrf_field() ?>
      <h6 class="fw-bold mb-2"><?= e(t('proc.set_prices')) ?></h6>
      <div class="row g-2 align-items-end">
        <?php foreach ($selItems as $it): ?>
          <div class="col-6 col-md-3">
            <label class="form-label small fw-semibold mb-0"><?= e(product_name($it)) ?></label>
            <span class="small text-muted d-block mb-1"><?= e(pack_label($it['pack_size'], $it['unit'])) ?></span>
            <div class="input-group input-group-sm">
              <span class="input-group-text">₹</span>
              <input type="number" class="form-control" name="confirmed_price_<?= (int) $it['id'] ?>" min="0" step="0.01"
                     value="<?= $it['confirmed_price'] !== null ? e(qty($it['confirmed_price'])) : ($it['quoted_price'] !== null ? e(qty($it['quoted_price'])) : '') ?>">
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <button type="submit" class="btn btn-brand btn-sm b-btn-pill mt-3"><?= e(t('proc.save_prices')) ?></button>
    </form>
    <?php endif; ?>

    <?php /* quotations of this campaign */ ?>
    <h6 class="fw-bold mt-4 mb-2"><?= e(t('proc.quotations')) ?></h6>
    <?php if (!$selQuotes): ?>
      <p class="small text-muted"><?= e(t('proc.no_quotations')) ?></p>
    <?php else: ?>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr><th><?= e(t('proc.vendor')) ?></th><th><?= e(t('vendor.unit_price')) ?></th><th><?= e(t('vendor.min_qty')) ?></th><th><?= e(t('vendor.delivery_cost')) ?></th><th><?= e(t('vendor.lead_time')) ?></th><th><?= e(t('common.status')) ?></th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($selQuotes as $q): ?>
          <tr>
            <td class="fw-semibold"><?= e($q['vendor_name']) ?></td>
            <td><?= money($q['unit_price']) ?></td>
            <td><?= e(qty($q['min_qty'])) ?></td>
            <td><?= money($q['delivery_cost']) ?></td>
            <td><?= (int) $q['lead_time_days'] ?>d</td>
            <td><span class="<?= status_badge($q['status']) ?>"><?= e(t('vendor.quotation_' . $q['status'])) ?></span></td>
            <td class="text-end">
              <?php if ($q['status'] === 'submitted'): ?>
              <form method="post" action="<?= url('/super/quotations/' . (int) $q['id'] . '/select') ?>" class="d-inline">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-brand b-btn-pill"><?= e(t('proc.select')) ?></button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

<?php else: ?>

  <?php /* ---------- QUOTATIONS TAB ---------- */ ?>
  <form method="get" action="<?= url('/super/quotations') ?>" class="b-card row g-2 align-items-end mb-3 py-3">
    <div class="col-6 col-md-3">
      <label class="form-label small fw-semibold"><?= e(t('proc.filter_status')) ?></label>
      <select class="form-select form-select-sm" name="status">
        <option value=""><?= e(t('common.all')) ?></option>
        <?php foreach ($statuses as $st): ?>
          <option value="<?= e($st) ?>" <?= ($status ?? '') === $st ? 'selected' : '' ?>><?= e(ucfirst($st)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2"><button class="btn btn-sm btn-brand b-btn-pill w-100"><?= e(t('common.filter')) ?></button></div>
  </form>

  <?php if (empty($quotations)): ?>
  <div class="empty-state b-card my-4"><i data-lucide="file-text"></i><p class="mt-2"><?= e(t('proc.no_quotations')) ?></p></div>
  <?php else: ?>
  <div class="table-responsive b-table">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th><?= e(t('proc.vendor')) ?></th>
          <th><?= e(t('proc.campaign_title')) ?></th>
          <th><?= e(t('proc.filter_society')) ?></th>
          <th><?= e(t('vendor.unit_price')) ?></th>
          <th><?= e(t('vendor.min_qty')) ?></th>
          <th><?= e(t('common.status')) ?></th>
          <th class="text-end"><?= e(t('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($quotations as $q): ?>
        <tr>
          <td class="fw-semibold"><?= e($q['vendor_name']) ?></td>
          <td class="small"><a class="text-decoration-none" href="<?= url('/super/campaigns?id=' . (int) $q['campaign_id']) ?>"><?= e($q['campaign_title']) ?></a></td>
          <td class="small"><?= $q['society_name'] !== null ? e($q['society_name']) : e(t('campaigns.platform_wide')) ?></td>
          <td><?= money($q['unit_price']) ?></td>
          <td><?= e(qty($q['min_qty'])) ?></td>
          <td><span class="<?= status_badge($q['status']) ?>"><?= e(t('vendor.quotation_' . $q['status'])) ?></span></td>
          <td class="text-end">
            <?php if ($q['status'] === 'submitted'): ?>
            <div class="d-inline-flex gap-1">
              <form method="post" action="<?= url('/super/quotations/' . (int) $q['id'] . '/select') ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-brand b-btn-pill"><i data-lucide="check" width="13" height="13" class="me-1"></i><?= e(t('proc.select')) ?></button>
              </form>
              <form method="post" action="<?= url('/super/quotations/' . (int) $q['id'] . '/reject') ?>" data-confirm="<?= e(t('proc.reject_confirm')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-outline-danger b-btn-pill"><i data-lucide="x" width="13" height="13" class="me-1"></i><?= e(t('common.reject')) ?></button>
              </form>
            </div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>

<?php endif; ?>
