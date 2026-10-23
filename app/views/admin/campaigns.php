<?php
/**
 * Society admin — bulk campaigns list + create form.
 * Vars: $campaigns, $variantOptions
 */
$campaigns = $campaigns ?? [];
$variantOptions = $variantOptions ?? [];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mt-4 mb-3 gap-2">
  <div>
    <h1 class="section-title mb-1"><?= e(t('proc.campaigns')) ?></h1>
    <p class="text-muted mb-0"><?= e(t('proc.campaigns_sub')) ?></p>
  </div>
  <button class="btn btn-brand b-btn-pill" type="button" data-bs-toggle="collapse" data-bs-target="#newCampaign">
    <i data-lucide="plus" width="16" height="16" class="me-1"></i><?= e(t('proc.create_campaign')) ?>
  </button>
</div>

<?php /* ---------- CREATE CAMPAIGN ---------- */ ?>
<div class="collapse mb-4" id="newCampaign">
  <div class="b-card">
    <h5 class="fw-bold mb-3"><?= e(t('proc.new_campaign')) ?></h5>
    <form method="post" action="<?= url('/admin/campaigns') ?>">
      <?= csrf_field() ?>
      <div class="row g-3">
        <div class="col-12 col-md-6">
          <label class="form-label small fw-semibold"><?= e(t('proc.campaign_title')) ?> *</label>
          <input type="text" class="form-control" name="title" required maxlength="180" value="<?= e(old('title')) ?>">
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label small fw-semibold"><?= e(t('proc.type')) ?></label>
          <select class="form-select" name="type">
            <option value="grocery"><?= e(t('campaigns.type_grocery')) ?></option>
            <option value="product"><?= e(t('campaigns.type_product')) ?></option>
          </select>
        </div>
        <div class="col-6 col-md-3">
          <label class="form-label small fw-semibold"><?= e(t('common.deadline')) ?> *</label>
          <input type="datetime-local" class="form-control" name="deadline" required value="<?= e(date('Y-m-d\TH:i', strtotime('+7 days'))) ?>">
        </div>
        <div class="col-12">
          <label class="form-label small fw-semibold"><?= e(t('proc.description')) ?></label>
          <textarea class="form-control" name="description" rows="2" maxlength="1000"><?= e(old('description')) ?></textarea>
        </div>
        <div class="col-4 col-md-3">
          <label class="form-label small fw-semibold"><?= e(t('proc.delivery_cost')) ?></label>
          <input type="number" class="form-control" name="delivery_cost" min="0" step="0.01" value="0">
        </div>
        <div class="col-4 col-md-3">
          <label class="form-label small fw-semibold"><?= e(t('proc.tax_percent')) ?></label>
          <input type="number" class="form-control" name="tax_percent" min="0" max="99" step="0.01" value="0">
        </div>
        <div class="col-4 col-md-3">
          <label class="form-label small fw-semibold"><?= e(t('proc.lead_time_days')) ?></label>
          <input type="number" class="form-control" name="lead_time_days" min="0" max="90" value="7">
        </div>
      </div>

      <hr class="my-4">
      <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="fw-bold mb-0"><?= e(t('proc.items')) ?></h6>
        <button type="button" class="btn btn-sm btn-soft b-btn-pill" id="addItemRow"><i data-lucide="plus" width="14" height="14" class="me-1"></i><?= e(t('proc.add_item')) ?></button>
      </div>

      <div id="itemRows">
        <?php for ($i = 0; $i < 3; $i++): ?>
        <div class="row g-2 align-items-end item-row mb-2">
          <div class="col-12 col-md-6">
            <label class="form-label small fw-semibold"><?= e(t('proc.variant')) ?></label>
            <select class="form-select" name="item_variant[]">
              <option value="">—</option>
              <?php foreach ($variantOptions as $v): ?>
                <option value="<?= (int) $v['id'] ?>"><?= e(product_name($v)) ?> · <?= e($v['brand_name'] ?? '') ?> · <?= e(pack_label($v['pack_size'], $v['unit'])) ?> (#<?= (int) $v['id'] ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6 col-md-3">
            <label class="form-label small fw-semibold"><?= e(t('proc.target_qty')) ?></label>
            <input type="number" class="form-control" name="item_target[]" min="0" step="0.5" value="50">
          </div>
          <div class="col-6 col-md-2">
            <label class="form-label small fw-semibold"><?= e(t('proc.min_qty')) ?></label>
            <input type="number" class="form-control" name="item_min[]" min="0" step="0.5" value="10">
          </div>
          <div class="col-12 col-md-1 text-md-end">
            <button type="button" class="btn btn-sm btn-outline-danger" data-remove-row title="Remove"><i data-lucide="trash-2" width="14" height="14"></i></button>
          </div>
        </div>
        <?php endfor; ?>

        <template id="itemRowTemplate">
          <div class="row g-2 align-items-end item-row mb-2">
            <div class="col-12 col-md-6">
              <label class="form-label small fw-semibold"><?= e(t('proc.variant')) ?></label>
              <select class="form-select" name="item_variant[]">
                <option value="">—</option>
                <?php foreach ($variantOptions as $v): ?>
                  <option value="<?= (int) $v['id'] ?>"><?= e(product_name($v)) ?> · <?= e($v['brand_name'] ?? '') ?> · <?= e(pack_label($v['pack_size'], $v['unit'])) ?> (#<?= (int) $v['id'] ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label small fw-semibold"><?= e(t('proc.target_qty')) ?></label>
              <input type="number" class="form-control" name="item_target[]" min="0" step="0.5" value="50">
            </div>
            <div class="col-6 col-md-2">
              <label class="form-label small fw-semibold"><?= e(t('proc.min_qty')) ?></label>
              <input type="number" class="form-control" name="item_min[]" min="0" step="0.5" value="10">
            </div>
            <div class="col-12 col-md-1 text-md-end">
              <button type="button" class="btn btn-sm btn-outline-danger" data-remove-row title="Remove"><i data-lucide="trash-2" width="14" height="14"></i></button>
            </div>
          </div>
        </template>
      </div>

      <button type="submit" class="btn btn-brand b-btn-pill mt-3"><i data-lucide="megaphone" width="16" height="16" class="me-1"></i><?= e(t('proc.create_campaign')) ?></button>
    </form>
  </div>
</div>

<?php /* ---------- CAMPAIGN LIST ---------- */ ?>
<?php if (!$campaigns): ?>
<div class="empty-state b-card my-4"><i data-lucide="megaphone"></i><p class="mt-2"><?= e(t('proc.no_campaigns')) ?></p></div>
<?php else: ?>
<div class="table-responsive b-table">
  <table class="table align-middle mb-0" style="min-width: 820px;">
    <thead>
      <tr>
        <th style="min-width: 240px;"><?= e(t('proc.campaign_title')) ?></th>
        <th style="white-space: nowrap; min-width: 100px;"><?= e(t('common.status')) ?></th>
        <th style="min-width: 150px;"><?= e(t('proc.progress')) ?></th>
        <th style="white-space: nowrap; min-width: 140px;"><?= e(t('common.deadline')) ?></th>
        <th style="white-space: nowrap; text-align: center; min-width: 90px;"><?= e(t('proc.interested')) ?></th>
        <th style="white-space: nowrap; text-align: center; min-width: 90px;"><?= e(t('proc.quotations')) ?></th>
        <th style="white-space: nowrap; min-width: 100px;"></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($campaigns as $c):
        $target = (float) $c['target_qty'];
        $pct = $target > 0 ? min(100, round(((float) $c['agg_qty'] / $target) * 100)) : 0;
    ?>
      <tr>
        <td style="min-width: 240px;">
          <a class="fw-semibold text-decoration-none text-reset d-block" href="<?= url('/admin/campaigns/' . $c['id']) ?>"><?= e($c['title']) ?></a>
          <span class="small text-muted d-block mt-1"><?= e(t('campaigns.type_' . $c['type'])) ?></span>
        </td>
        <td style="white-space: nowrap;"><span class="<?= status_badge($c['status']) ?>"><?= e(ucfirst($c['status'])) ?></span></td>
        <td style="min-width: 150px;">
          <div class="b-progress progress-thin"><div class="b-progress-bar" style="width:<?= $pct ?>%"></div></div>
          <span class="small text-muted d-block mt-1"><?= e(qty($c['agg_qty'])) ?> / <?= e(qty($c['target_qty'])) ?></span>
        </td>
        <td class="small" style="white-space: nowrap;"><?= e(human_dt($c['deadline'])) ?></td>
        <td style="text-align: center; font-weight: 600;"><?= (int) $c['interested_count'] ?></td>
        <td style="text-align: center; font-weight: 600;"><?= (int) $c['quotation_count'] ?></td>
        <td class="text-end" style="white-space: nowrap;">
          <a class="btn btn-sm btn-outline-brand b-btn-pill" href="<?= url('/admin/campaigns/' . $c['id']) ?>"><?= e(t('proc.manage')) ?></a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<script>
document.addEventListener('click', function (e) {
  var add = e.target.closest('#addItemRow');
  if (add) {
    e.preventDefault();
    var tpl = document.getElementById('itemRowTemplate');
    document.getElementById('itemRows').appendChild(tpl.content.cloneNode(true));
    if (window.bachInitIcons) window.bachInitIcons();
  }
  var rm = e.target.closest('[data-remove-row]');
  if (rm) {
    e.preventDefault();
    var row = rm.closest('.item-row');
    if (row) row.remove();
  }
});
</script>
