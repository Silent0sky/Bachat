<?php
/** Society coupons. Vars: $rows, $edit (null|array) */
$c = $edit;
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="section-title mb-1">Society Coupons</h1>
    <p class="text-muted small mb-0">Discount codes for your residents — funded from your society (or a vendor partner).</p>
  </div>
  <?php if ($c === null): ?>
    <a class="btn btn-brand b-btn-pill" href="<?= url('/admin/coupons?edit=new') ?>"><i data-lucide="plus" class="me-1"></i> Add coupon</a>
  <?php else: ?>
    <a class="btn btn-soft b-btn-pill" href="<?= url('/admin/coupons') ?>"><i data-lucide="x" class="me-1"></i> Close form</a>
  <?php endif; ?>
</div>

<?php if ($c !== null || (string) ($_GET['edit'] ?? '') === 'new'): ?>
<div class="b-card mb-4 fade-up">
  <h2 class="h6 fw-bold text-brand mb-3"><?= is_array($c) ? 'Edit coupon — ' . e($c['code']) : 'Add a coupon for your society' ?></h2>
  <form method="post" action="<?= url('/admin/coupons') ?>" class="row g-3">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) ($c['id'] ?? 0) ?>">
    <div class="col-6 col-md-2">
      <label class="form-label fw-semibold">Code *</label>
      <input class="form-control text-uppercase" name="code" required maxlength="40" value="<?= e(old('code', $c['code'] ?? '')) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label fw-semibold">Title <span class="text-muted small">(shown to residents)</span></label>
      <input class="form-control" name="title" maxlength="150" value="<?= e(old('title', $c['title'] ?? '')) ?>">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label fw-semibold">Type</label>
      <select class="form-select" name="type">
        <option value="fixed" <?= (string) old('type', $c['type'] ?? 'fixed') === 'fixed' ? 'selected' : '' ?>>₹ flat off</option>
        <option value="percent" <?= (string) old('type', $c['type'] ?? 'fixed') === 'percent' ? 'selected' : '' ?>>% off</option>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label fw-semibold">Value *</label>
      <input class="form-control" type="number" step="0.01" min="0" name="value" required value="<?= e(old('value', $c['value'] ?? '')) ?>">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold">Min cart value (₹)</label>
      <input class="form-control" type="number" step="0.01" min="0" name="min_cart_value" value="<?= e(old('min_cart_value', $c['min_cart_value'] ?? 0)) ?>">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold">Max discount (₹)</label>
      <input class="form-control" type="number" step="0.01" min="0" name="max_discount" value="<?= e(old('max_discount', $c['max_discount'] ?? '')) ?>">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label fw-semibold">Total usage limit</label>
      <input class="form-control" type="number" min="1" name="usage_limit" value="<?= e(old('usage_limit', $c['usage_limit'] ?? '')) ?>">
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label fw-semibold">Per-user limit</label>
      <input class="form-control" type="number" min="1" name="per_user_limit" value="<?= e(old('per_user_limit', $c['per_user_limit'] ?? 1)) ?>">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold">Funded by</label>
      <select class="form-select" name="funding_source">
        <option value="society" <?= (string) old('funding_source', $c['funding_source'] ?? 'society') === 'society' ? 'selected' : '' ?>>Society funds</option>
        <option value="vendor" <?= (string) old('funding_source', $c['funding_source'] ?? 'society') === 'vendor' ? 'selected' : '' ?>>Vendor partner</option>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold">Valid from</label>
      <input class="form-control" type="datetime-local" name="valid_from" value="<?= e(old('valid_from', !empty($c['valid_from']) ? date('Y-m-d\TH:i', strtotime($c['valid_from'])) : date('Y-m-d\TH:i'))) ?>">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold">Valid to</label>
      <input class="form-control" type="datetime-local" name="valid_to" value="<?= e(old('valid_to', $c['valid_to'] ? date('Y-m-d\TH:i', strtotime($c['valid_to'])) : '')) ?>">
    </div>
    <div class="col-md-6 d-flex align-items-end gap-4">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" id="cp_stacking" name="stacking" value="1" <?= (int) old('stacking', $c['stacking'] ?? 0) === 1 ? 'checked' : '' ?>>
        <label class="form-check-label small" for="cp_stacking">Stackable with offers</label>
      </div>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" id="cp_active" name="is_active" value="1" <?= (int) old('is_active', $c['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
        <label class="form-check-label small" for="cp_active">Active</label>
      </div>
    </div>
    <div class="col-12 d-flex gap-2">
      <button class="btn btn-brand b-btn-pill"><?= is_array($c) ? 'Save changes' : 'Create coupon' ?></button>
      <a class="btn btn-soft b-btn-pill" href="<?= url('/admin/coupons') ?>">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>

<?php if ($rows === []): ?>
  <div class="b-card"><div class="empty-state"><i data-lucide="ticket-percent"></i><p>No society coupons yet — create one to reward your residents.</p></div></div>
<?php else: ?>
<div class="b-table fade-up">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Code</th><th>Discount</th><th>Min cart</th><th>Funded by</th><th>Validity</th><th>Used</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $cp): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= e($cp['code']) ?></div>
              <div class="small text-muted"><?= e($cp['title'] ?? '') ?></div>
            </td>
            <td class="fw-semibold"><?= $cp['type'] === 'percent' ? qty($cp['value']) . '%' : money($cp['value']) ?>
              <?php if ($cp['max_discount']): ?><div class="small text-muted">cap <?= money($cp['max_discount']) ?></div><?php endif; ?>
            </td>
            <td class="small"><?= (float) $cp['min_cart_value'] > 0 ? money($cp['min_cart_value']) : '—' ?></td>
            <td><span class="b-badge-grey"><?= e(ucfirst($cp['funding_source'])) ?></span></td>
            <td class="small text-muted"><?= e(human_date($cp['valid_from'])) ?> → <?= e(human_date($cp['valid_to'])) ?></td>
            <td class="small"><?= (int) $cp['used_count'] ?><?= $cp['usage_limit'] ? '/' . (int) $cp['usage_limit'] : '' ?></td>
            <td><?= (int) $cp['is_active'] === 1 ? '<span class="b-badge-green">active</span>' : '<span class="b-badge-grey">off</span>' ?></td>
            <td class="text-end">
              <div class="d-inline-flex gap-1">
                <a class="btn btn-sm btn-soft" href="<?= url('/admin/coupons?edit=' . $cp['id']) ?>">Edit</a>
                <form method="post" action="<?= url('/admin/coupons/' . $cp['id'] . '/toggle') ?>">
                  <?= csrf_field() ?>
                  <button class="btn btn-sm <?= (int) $cp['is_active'] === 1 ? 'btn-outline-brand' : 'btn-brand' ?>">
                    <?= (int) $cp['is_active'] === 1 ? 'Disable' : 'Enable' ?>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
