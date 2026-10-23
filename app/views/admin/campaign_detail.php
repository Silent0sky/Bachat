<?php
/**
 * Society admin — campaign detail with lifecycle actions.
 * Vars: $camp, $items, $interests, $interestQty (interest_id => rows), $quotations
 */
$camp        = $camp ?? [];
$items       = $items ?? [];
$interests   = $interests ?? [];
$interestQty = $interestQty ?? [];
$quotations  = $quotations ?? [];
$live        = in_array($camp['status'] ?? '', ['active', 'extended'], true);
$selQ    = null;
foreach ($quotations as $q) { if ($q['status'] === 'selected') { $selQ = $q; break; } }
$allPriced = $items !== [] && !in_array(null, array_map(static fn ($i) => $i['confirmed_price'], $items), true);
$itemNames = [];
foreach ($items as $it) {
    $itemNames[(int) $it['variant_id']] = product_name($it) . ' (' . pack_label($it['pack_size'], $it['unit']) . ')';
}
?>
<a href="<?= url('/admin/campaigns') ?>" class="btn btn-sm btn-soft b-btn-pill mt-4">
  <i data-lucide="arrow-left" width="15" height="15" class="me-1"></i><?= e(t('common.back')) ?>
</a>

<div class="b-card mt-3">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
    <div>
      <h1 class="h4 fw-bold mb-1"><?= e($camp['title']) ?></h1>
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <span class="<?= status_badge($camp['status']) ?>"><?= e(ucfirst($camp['status'])) ?></span>
        <span class="chip"><?= e(t('campaigns.type_' . $camp['type'])) ?></span>
        <span class="small text-muted">
          <i data-lucide="clock" width="13" height="13" class="me-1"></i><?= e(t('common.deadline')) ?>: <?= e(human_dt($camp['deadline'])) ?>
          · <?= e(t('proc.extensions')) ?>: <?= (int) $camp['extension_count'] ?>
        </span>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2">
      <?php if ($live): ?>
        <form method="post" action="<?= url('/admin/campaigns/' . $camp['id'] . '/extend') ?>">
          <?= csrf_field() ?><button class="btn btn-sm btn-amber b-btn-pill"><i data-lucide="calendar-plus" width="14" height="14" class="me-1"></i><?= e(t('proc.extend')) ?></button>
        </form>
        <form method="post" action="<?= url('/admin/campaigns/' . $camp['id'] . '/close') ?>" data-confirm="<?= e(t('proc.close_confirm')) ?>">
          <?= csrf_field() ?><button class="btn btn-sm btn-forest b-btn-pill"><i data-lucide="lock" width="14" height="14" class="me-1"></i><?= e(t('proc.close')) ?></button>
        </form>
      <?php endif; ?>
      <?php if ($camp['status'] !== 'cancelled'): ?>
        <form method="post" action="<?= url('/admin/campaigns/' . $camp['id'] . '/cancel') ?>" data-confirm="<?= e(t('proc.cancel_confirm')) ?>">
          <?= csrf_field() ?><button class="btn btn-sm btn-outline-danger b-btn-pill"><i data-lucide="x-circle" width="14" height="14" class="me-1"></i><?= e(t('proc.cancel')) ?></button>
        </form>
      <?php endif; ?>
    </div>
  </div>
  <div class="row g-3 mt-1 small">
    <div class="col-6 col-md-3"><span class="text-muted d-block"><?= e(t('proc.delivery_cost')) ?></span><strong><?= money($camp['delivery_cost']) ?></strong></div>
    <div class="col-6 col-md-3"><span class="text-muted d-block"><?= e(t('proc.tax_percent')) ?></span><strong><?= e(qty($camp['tax_percent'])) ?>%</strong></div>
    <div class="col-6 col-md-3"><span class="text-muted d-block"><?= e(t('proc.lead_time_days')) ?></span><strong><?= (int) $camp['lead_time_days'] ?></strong></div>
    <div class="col-6 col-md-3"><span class="text-muted d-block"><?= e(t('proc.created_by')) ?></span><strong><?= e(auth_user()['name'] ?? '—') ?></strong></div>
  </div>
</div>

<?php /* ---------- ITEMS ---------- */ ?>
<div class="b-table table-responsive mt-3">
  <table class="table align-middle mb-0">
    <thead>
      <tr>
        <th><?= e(t('common.name')) ?></th>
        <th><?= e(t('proc.target_qty')) ?></th>
        <th><?= e(t('proc.aggregated')) ?></th>
        <th><?= e(t('proc.progress')) ?></th>
        <th><?= e(t('campaigns.quoted_price')) ?></th>
        <th><?= e(t('campaigns.final_price')) ?></th>
        <th><?= e(t('proc.distributed')) ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($items as $it):
        $target = (float) $it['target_qty'];
        $pct = $target > 0 ? min(100, round(((float) $it['aggregated_qty'] / $target) * 100)) : 0;
    ?>
      <tr>
        <td>
          <span class="fw-semibold"><?= e(product_name($it)) ?></span>
          <span class="small text-muted d-block"><?= e($it['brand_name'] ?? '') ?> <?= e(pack_label($it['pack_size'], $it['unit'])) ?></span>
        </td>
        <td><?= e(qty($it['target_qty'])) ?></td>
        <td class="fw-semibold"><?= e(qty($it['aggregated_qty'])) ?></td>
        <td style="min-width:120px;">
          <div class="b-progress progress-thin"><div class="b-progress-bar" style="width:<?= $pct ?>%"></div></div>
          <span class="small text-muted"><?= $pct ?>%</span>
        </td>
        <td><?= $it['quoted_price'] !== null ? money($it['quoted_price']) : '—' ?></td>
        <td><?= $it['confirmed_price'] !== null ? '<span class="price">' . money($it['confirmed_price']) . '</span>' : '—' ?></td>
        <td><?= e(qty($it['distributed_qty'])) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php /* ---------- QUOTATIONS ---------- */ ?>
<div class="b-card mt-3">
  <h5 class="fw-bold mb-3"><i data-lucide="file-text" class="me-1 text-brand"></i><?= e(t('proc.quotations')) ?> (<?= count($quotations) ?>)</h5>
  <?php if (!$quotations): ?>
    <p class="text-muted small mb-0"><?= e(t('proc.no_quotations')) ?></p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr>
          <th><?= e(t('proc.vendor')) ?></th>
          <th><?= e(t('vendor.unit_price')) ?></th>
          <th><?= e(t('vendor.min_qty')) ?></th>
          <th><?= e(t('vendor.delivery_cost')) ?></th>
          <th><?= e(t('vendor.tax_percent')) ?></th>
          <th><?= e(t('vendor.lead_time')) ?></th>
          <th><?= e(t('common.notes')) ?></th>
          <th><?= e(t('common.status')) ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($quotations as $q): ?>
        <tr>
          <td class="fw-semibold"><?= e($q['vendor_name']) ?></td>
          <td><?= money($q['unit_price']) ?></td>
          <td><?= e(qty($q['min_qty'])) ?></td>
          <td><?= money($q['delivery_cost']) ?></td>
          <td><?= e(qty($q['tax_percent'])) ?>%</td>
          <td><?= (int) $q['lead_time_days'] ?>d</td>
          <td class="small text-muted" style="max-width:220px;"><?= nl2br(e($q['notes'] ?? '')) ?></td>
          <td><span class="<?= status_badge($q['status']) ?>"><?= e(t('vendor.quotation_' . $q['status'])) ?></span></td>
          <td class="text-end">
            <?php if ($q['status'] === 'submitted'): ?>
            <form method="post" action="<?= url('/admin/campaigns/' . $camp['id'] . '/select-quotation') ?>" class="d-inline">
              <?= csrf_field() ?><input type="hidden" name="quotation_id" value="<?= (int) $q['id'] ?>">
              <button class="btn btn-sm btn-brand b-btn-pill"><i data-lucide="check" width="13" height="13" class="me-1"></i><?= e(t('proc.select')) ?></button>
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

<?php /* ---------- FINAL PRICES ---------- */ ?>
<?php if ($items): ?>
<div class="b-card mt-3">
  <h5 class="fw-bold mb-1"><i data-lucide="tag" class="me-1 text-brand"></i><?= e(t('proc.set_prices')) ?></h5>
  <p class="text-muted small mb-3"><?= e(t('proc.set_prices_note')) ?></p>
  <form method="post" action="<?= url('/admin/campaigns/' . $camp['id'] . '/prices') ?>">
    <?= csrf_field() ?>
    <div class="row g-3 align-items-end">
      <?php foreach ($items as $it): ?>
        <div class="col-12 col-md-6 col-lg-4">
          <label class="form-label small fw-semibold mb-0"><?= e(product_name($it)) ?></label>
          <span class="small text-muted d-block mb-1"><?= e(pack_label($it['pack_size'], $it['unit'])) ?></span>
          <div class="input-group">
            <span class="input-group-text">₹</span>
            <input type="number" class="form-control" name="confirmed_price_<?= (int) $it['id'] ?>" min="0" step="0.01"
                   value="<?= $it['confirmed_price'] !== null ? e(qty($it['confirmed_price'])) : ($it['quoted_price'] !== null ? e(qty($it['quoted_price'])) : '') ?>">
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    <button type="submit" class="btn btn-brand b-btn-pill mt-3"><i data-lucide="save" width="15" height="15" class="me-1"></i><?= e(t('proc.save_prices')) ?></button>
  </form>
</div>
<?php endif; ?>

<?php /* ---------- PURCHASE ORDER ---------- */ ?>
<div class="b-card mt-3">
  <h5 class="fw-bold mb-1"><i data-lucide="truck" class="me-1 text-brand"></i><?= e(t('proc.create_po')) ?></h5>
  <?php if ($selQ): ?>
    <p class="small text-muted mb-3">
      <?= e(t('proc.selected_quotation')) ?>: <strong><?= e($selQ['vendor_name']) ?></strong>
      · <?= money($selQ['unit_price']) ?>
      <?php if ($allPriced): ?><span class="b-badge-green ms-1"><?= e(t('proc.prices_ready')) ?></span>
      <?php else: ?><span class="b-badge-amber ms-1"><?= e(t('proc.prices_pending_note')) ?></span><?php endif; ?>
    </p>
    <form method="post" action="<?= url('/admin/campaigns/' . $camp['id'] . '/po') ?>" class="row g-2 align-items-end">
      <?= csrf_field() ?>
      <div class="col-12 col-md-4">
        <label class="form-label small fw-semibold"><?= e(t('proc.expected_at')) ?></label>
        <input type="date" class="form-control" name="expected_at" value="<?= e(date('Y-m-d', strtotime('+' . max(1, (int) $camp['lead_time_days']) . ' days'))) ?>">
      </div>
      <div class="col-12 col-md-3">
        <button type="submit" class="btn btn-brand b-btn-pill"><i data-lucide="send" width="15" height="15" class="me-1"></i><?= e(t('proc.send_po')) ?></button>
      </div>
    </form>
  <?php else: ?>
    <div class="b-note"><i data-lucide="info"></i><span><?= e(t('proc.select_quotation_first')) ?></span></div>
  <?php endif; ?>
</div>

<?php /* ---------- REFUND ---------- */ ?>
<?php if (in_array($camp['status'], ['closed', 'cancelled', 'failed'], true)): ?>
<div class="b-card mt-3">
  <h5 class="fw-bold mb-1"><i data-lucide="undo-2" class="me-1 text-brand"></i><?= e(t('proc.refund')) ?></h5>
  <p class="text-muted small mb-3"><?= e(t('proc.refund_note')) ?></p>
  <form method="post" action="<?= url('/admin/campaigns/' . $camp['id'] . '/refund') ?>" class="row g-2 align-items-end" data-confirm="<?= e(t('proc.refund_confirm')) ?>">
    <?= csrf_field() ?>
    <div class="col-12 col-md-6">
      <label class="form-label small fw-semibold"><?= e(t('common.notes')) ?></label>
      <input type="text" class="form-control" name="reason" maxlength="255" placeholder="<?= e(t('proc.refund_reason_ph')) ?>">
    </div>
    <div class="col-12 col-md-3">
      <button type="submit" class="btn btn-amber b-btn-pill"><i data-lucide="undo-2" width="15" height="15" class="me-1"></i><?= e(t('proc.refund')) ?></button>
    </div>
  </form>
</div>
<?php endif; ?>

<?php /* ---------- INTERESTS ---------- */ ?>
<div class="b-card mt-3 mb-5">
  <h5 class="fw-bold mb-3"><i data-lucide="users" class="me-1 text-brand"></i><?= e(t('proc.interests')) ?> (<?= count($interests) ?>)</h5>
  <?php if (!$interests): ?>
    <p class="text-muted small mb-0"><?= e(t('campaigns.no_interest')) ?></p>
  <?php else: ?>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead><tr><th><?= e(t('common.name')) ?></th><th><?= e(t('auth.flat')) ?></th><th><?= e(t('common.qty')) ?></th><th><?= e(t('common.notes')) ?></th><th><?= e(t('common.status')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($interests as $in): ?>
        <tr>
          <td class="fw-semibold"><?= e($in['user_name']) ?><span class="small text-muted d-block"><?= e($in['user_phone'] ?? '') ?></span></td>
          <td class="small"><?= e($in['flat'] ?? t('common.na')) ?></td>
          <td class="small">
            <?php foreach ($interestQty[(int) $in['id']] ?? [] as $iq): ?>
              <div><?= e(qty($iq['qty'])) ?> × <?= e($itemNames[(int) $iq['variant_id']] ?? ('#' . (int) $iq['variant_id'])) ?></div>
            <?php endforeach; ?>
          </td>
          <td class="small text-muted"><?= nl2br(e($in['note'] ?? '')) ?></td>
          <td><span class="<?= status_badge($in['status']) ?>"><?= e(ucfirst($in['status'])) ?></span></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
