<?php
/**
 * Bulk campaign detail — resident.
 * Vars: $camp, $items, $interestedCount, $my (?array), $myItems (ci_id => row), $canConfirm, $countdown
 */
$live   = in_array($camp['status'], ['active', 'extended'], true);
$effSum = 0.0;
?>
<a href="<?= url('/campaigns') ?>" class="btn btn-sm btn-soft b-btn-pill mt-4">
  <i data-lucide="arrow-left" width="15" height="15" class="me-1"></i><?= e(t('common.back')) ?>
</a>

<div class="b-card mt-3">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
    <div>
      <h1 class="h4 fw-bold mb-1"><?= e($camp['title']) ?></h1>
      <div class="d-flex flex-wrap gap-2 align-items-center">
        <span class="<?= status_badge($camp['status']) ?>"><?= e(ucfirst($camp['status'])) ?></span>
        <span class="chip <?= $camp['type'] === 'grocery' ? 'chip-active' : '' ?>"><?= e(t('campaigns.type_' . $camp['type'])) ?></span>
        <?php if ($camp['society_id'] === null): ?><span class="b-badge-blue"><?= e(t('campaigns.platform_wide')) ?></span><?php endif; ?>
      </div>
    </div>
    <div class="text-md-end">
      <div class="small text-muted"><?= e(t('common.deadline')) ?>: <strong><?= e(human_dt($camp['deadline'])) ?></strong></div>
      <div class="small text-brand fw-semibold"><?= e($countdown) ?></div>
    </div>
  </div>

  <?php if ($camp['description']): ?>
    <p class="text-muted mt-3 mb-0"><?= nl2br(e($camp['description'])) ?></p>
  <?php endif; ?>

  <div class="row g-3 mt-1">
    <div class="col-6 col-md-3"><div class="small text-muted"><?= e(t('proc.delivery_cost')) ?></div><div class="fw-bold"><?= money($camp['delivery_cost']) ?></div></div>
    <div class="col-6 col-md-3"><div class="small text-muted"><?= e(t('proc.tax_percent')) ?></div><div class="fw-bold"><?= e(qty($camp['tax_percent'])) ?>%</div></div>
    <div class="col-6 col-md-3"><div class="small text-muted"><?= e(t('campaigns.interested_people', ['n' => (string) $interestedCount])) ?></div><div class="fw-bold"><i data-lucide="users" width="16" height="16" class="me-1"></i><?= (int) $interestedCount ?></div></div>
    <div class="col-6 col-md-3"><div class="small text-muted"><?= e(t('campaigns.lead_time_note')) ?></div><div class="fw-bold"><?= e(t('campaigns.lead_time_days', ['n' => (string) $camp['lead_time_days']])) ?></div></div>
  </div>
</div>

<?php if ($camp['status'] === 'failed'): ?>
  <div class="b-note mt-3"><i data-lucide="alert-triangle"></i><span><?= e(t('campaigns.failed')) ?></span></div>
<?php endif; ?>

<?php foreach ($items as $it):
    $target = (float) $it['target_qty'];
    $pct = $target > 0 ? min(100, round(((float) $it['aggregated_qty'] / $target) * 100)) : 0;
    $eff = $it['quoted_price'] !== null ? (float) $it['quoted_price'] : (float) $it['selling_price'];
    if ($it['confirmed_price'] !== null) {
        $myQty = isset($myItems[(int) $it['id']]) ? (float) $myItems[(int) $it['id']]['qty'] : 0.0;
        $effSum += round((float) $it['confirmed_price'] * $myQty, 2);
    }
?>
<div class="b-card mt-3">
  <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
    <div>
      <div class="fw-bold"><?= e(product_name($it)) ?></div>
      <div class="small text-muted">
        <?= e($it['brand_name'] ?: t('shop.brand')) ?> · <?= e(pack_label($it['pack_size'], $it['unit'])) ?>
        <?php if ($it['sku']): ?> · SKU <?= e($it['sku']) ?><?php endif; ?>
      </div>
    </div>
    <div class="text-end">
      <?php if ($it['confirmed_price'] !== null): ?>
        <div class="price"><?= money($it['confirmed_price']) ?></div>
        <div class="small text-muted"><?= e(t('campaigns.final_price')) ?></div>
      <?php elseif ($it['quoted_price'] !== null): ?>
        <div class="price"><?= money($it['quoted_price']) ?></div>
        <div class="small text-muted"><?= e(t('campaigns.quoted_price')) ?></div>
      <?php else: ?>
        <div class="price"><?= money($eff) ?></div>
        <div class="small text-muted"><?= e(t('shop.sample_price_note')) ?></div>
      <?php endif; ?>
    </div>
  </div>
  <div class="b-progress progress-thin mt-3" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
    <div class="b-progress-bar" style="width:<?= $pct ?>%"></div>
  </div>
  <div class="d-flex justify-content-between small text-muted mt-1">
    <span><?= e(t('campaigns.target')) ?>: <?= e(qty($it['aggregated_qty'])) ?> / <?= e(qty($it['target_qty'])) ?></span>
    <span><?= e(t('campaigns.min_qty')) ?>: <?= e(qty($it['min_qty'])) ?></span>
  </div>
</div>
<?php endforeach; ?>

<?php /* ---------- MY INTEREST ---------- */ ?>
<?php if ($my && $my['status'] !== 'cancelled'): ?>
<div class="b-card mt-3">
  <h6 class="fw-bold mb-3"><i data-lucide="hand" class="me-1 text-brand"></i><?= e(t('campaigns.my_interest')) ?>
    <span class="<?= status_badge($my['status']) ?> ms-2"><?= e(ucfirst($my['status'])) ?></span>
  </h6>
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-2">
      <thead><tr><th><?= e(t('common.name')) ?></th><th><?= e(t('campaigns.your_qty')) ?></th>
        <?php if ($canConfirm): ?><th><?= e(t('campaigns.final_price')) ?></th><th class="text-end"><?= e(t('common.total')) ?></th><?php endif; ?>
      </tr></thead>
      <tbody>
      <?php foreach ($items as $it): if (!isset($myItems[(int) $it['id']])) continue; $mi = $myItems[(int) $it['id']]; ?>
        <tr>
          <td><?= e(product_name($it)) ?> <span class="text-muted small">(<?= e(pack_label($it['pack_size'], $it['unit'])) ?>)</span></td>
          <td><?= e(qty($mi['qty'])) ?></td>
          <?php if ($canConfirm): ?>
            <td><?= money($it['confirmed_price']) ?></td>
            <td class="text-end"><?= money((float) $it['confirmed_price'] * (float) $mi['qty']) ?></td>
          <?php endif; ?>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php if ($my['note']): ?><p class="small text-muted mb-2"><i data-lucide="message-square" width="13" height="13" class="me-1"></i><?= e($my['note']) ?></p><?php endif; ?>
  <?php if ($my['status'] === 'interested' && $live): ?>
    <form method="post" action="<?= url('/campaigns/' . $camp['id'] . '/cancel-interest') ?>" data-confirm="<?= e(t('campaigns.cancel_confirm')) ?>">
      <?= csrf_field() ?>
      <button type="submit" class="btn btn-sm btn-outline-danger b-btn-pill"><?= e(t('campaigns.cancel_interest')) ?></button>
    </form>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php /* ---------- INTEREST FORM ---------- */ ?>
<?php if ($live && (!$my || $my['status'] === 'cancelled')): ?>
<div class="b-card mt-3 border-brand">
  <h5 class="fw-bold mb-1"><i data-lucide="hand-metal" class="me-1 text-brand"></i><?= e(t('campaigns.show_interest')) ?></h5>
  <p class="text-muted small"><?= e(t('campaigns.prices_note')) ?></p>
  <form method="post" action="<?= url('/campaigns/' . $camp['id'] . '/interest') ?>">
    <?= csrf_field() ?>
    <?php foreach ($items as $it): $eff = $it['quoted_price'] !== null ? (float) $it['quoted_price'] : (float) $it['selling_price']; ?>
    <div class="list-tile mb-2 flex-wrap">
      <div class="flex-grow-1">
        <div class="fw-semibold"><?= e(product_name($it)) ?> <span class="text-muted small">(<?= e(pack_label($it['pack_size'], $it['unit'])) ?>)</span></div>
        <div class="small text-muted"><?= e(t('shop.sample_price_note')) ?>: <?= money($eff) ?></div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <span class="small text-muted"><?= e(t('common.qty')) ?></span>
        <div class="qty-stepper">
          <button type="button" class="qty-minus">−</button>
          <input class="qty-input" type="number" name="qty_<?= (int) $it['id'] ?>" value="1" min="1" step="1" data-min="1" data-step="1" aria-label="<?= e(t('common.qty')) ?>">
          <button type="button" class="qty-plus">+</button>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <label class="form-label small fw-semibold mt-2"><?= e(t('campaigns.note_optional')) ?></label>
    <textarea class="form-control" name="note" rows="2" maxlength="255" placeholder="<?= e(t('campaigns.note_ph')) ?>"></textarea>
    <button type="submit" class="btn btn-brand b-btn-pill mt-3">
      <i data-lucide="hand" width="16" height="16" class="me-1"></i><?= e(t('campaigns.submit_interest')) ?>
    </button>
  </form>
</div>
<?php endif; ?>

<?php /* ---------- CONFIRMATION PANEL (closed + final prices) ---------- */ ?>
<?php if ($canConfirm):
    $delivery = (float) $camp['delivery_cost'];
    $tax      = round($effSum * (float) $camp['tax_percent'] / 100, 2);
    $grand    = round($effSum + $delivery + $tax, 2);
?>
<div class="b-card mt-3" style="border:2px solid var(--b-green);">
  <h5 class="fw-bold text-brand mb-1"><i data-lucide="badge-check" class="me-1"></i><?= e(t('campaigns.confirm_panel_title')) ?></h5>
  <div class="b-note mb-3"><i data-lucide="info"></i><span><?= e(t('campaigns.prices_note')) ?></span></div>
  <div class="table-responsive">
    <table class="table table-sm align-middle">
      <thead><tr><th><?= e(t('common.name')) ?></th><th><?= e(t('common.qty')) ?></th><th><?= e(t('campaigns.final_price')) ?></th><th class="text-end"><?= e(t('common.total')) ?></th></tr></thead>
      <tbody>
      <?php foreach ($items as $it): if (!isset($myItems[(int) $it['id']])) continue; $mi = $myItems[(int) $it['id']]; ?>
        <tr>
          <td><?= e(product_name($it)) ?> <span class="text-muted small">(<?= e(pack_label($it['pack_size'], $it['unit'])) ?>)</span></td>
          <td><?= e(qty($mi['qty'])) ?></td>
          <td><?= money($it['confirmed_price']) ?></td>
          <td class="text-end"><?= money((float) $it['confirmed_price'] * (float) $mi['qty']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><td colspan="3" class="text-end text-muted"><?= e(t('shop.subtotal')) ?></td><td class="text-end fw-semibold"><?= money($effSum) ?></td></tr>
        <tr><td colspan="3" class="text-end text-muted"><?= e(t('shop.delivery_fee')) ?></td><td class="text-end fw-semibold"><?= money($delivery) ?></td></tr>
        <tr><td colspan="3" class="text-end text-muted"><?= e(t('proc.tax_percent')) ?> (<?= e(qty($camp['tax_percent'])) ?>%)</td><td class="text-end fw-semibold"><?= money($tax) ?></td></tr>
        <tr><td colspan="3" class="text-end fw-bold"><?= e(t('shop.grand_total')) ?></td><td class="text-end price fs-5"><?= money($grand) ?></td></tr>
      </tfoot>
    </table>
  </div>

  <form method="post" action="<?= url('/campaigns/' . $camp['id'] . '/confirm') ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
      <div class="col-12 col-md-6">
        <label class="form-label small fw-semibold"><?= e(t('shop.address')) ?> — <span class="text-muted"><?= e(t('common.optional')) ?></span></label>
        <input type="text" class="form-control" name="address_text" maxlength="400" placeholder="<?= e(t('campaigns.address_ph')) ?>">
      </div>
      <div class="col-12 col-md-6">
        <label class="form-label small fw-semibold"><?= e(t('shop.payment_method')) ?></label>
        <select class="form-select" name="payment_method">
          <option value="cod"><?= e(t('shop.cod')) ?></option>
          <option value="upi"><?= e(t('shop.upi')) ?></option>
        </select>
      </div>
      <div class="col-12">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="confirm_check" value="1" id="confirmCheck" required>
          <label class="form-check-label small" for="confirmCheck"><?= e(t('campaigns.agree_note')) ?></label>
        </div>
      </div>
    </div>
    <button type="submit" class="btn btn-brand b-btn-pill mt-3">
      <i data-lucide="check-circle-2" width="17" height="17" class="me-1"></i><?= e(t('campaigns.confirm_order')) ?>
    </button>
  </form>
</div>
<?php elseif ($camp['status'] === 'closed'): ?>
<div class="b-note mt-3"><i data-lucide="clock"></i><span><?= e(t('campaigns.closed_note')) ?></span></div>
<?php endif; ?>

<div class="mb-5"></div>
