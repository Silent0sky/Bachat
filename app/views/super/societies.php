<?php /** Societies manager. Vars: $societies, $edit (null|array — array when adding/editing) */ ?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="section-title mb-1">Societies</h1>
    <p class="text-muted small mb-0">Every society gets its own branding, members, prices and admin.</p>
  </div>
  <?php if ($edit === null): ?>
    <a class="btn btn-brand b-btn-pill" href="<?= url('/super/societies?edit=new') ?>"><i data-lucide="plus" class="me-1"></i> Add society</a>
  <?php else: ?>
    <a class="btn btn-soft b-btn-pill" href="<?= url('/super/societies') ?>"><i data-lucide="x" class="me-1"></i> Close form</a>
  <?php endif; ?>
</div>

<?php if ($edit !== null): ?>
<div class="b-card mb-4 fade-up" id="society-form">
  <h2 class="h6 fw-bold text-brand mb-3"><?= $edit['id'] === null ? 'Add a new society' : 'Edit society #' . (int) $edit['id'] ?></h2>
  <form method="post" action="<?= url('/super/societies') ?>" enctype="multipart/form-data" class="row g-3">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <div class="col-md-6">
      <label class="form-label fw-semibold">Society name *</label>
      <input class="form-control" name="name" required maxlength="150" value="<?= e(old('name', $edit['name'])) ?>">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold">Code * <span class="text-muted small">(unique)</span></label>
      <input class="form-control text-uppercase" name="code" required maxlength="30" value="<?= e(old('code', $edit['code'])) ?>">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold">Primary color *</label>
      <div class="d-flex gap-2">
        <input type="color" class="form-control form-control-color" value="<?= e(old('primary_color', $edit['primary_color'])) ?>"
               oninput="this.form.primary_color_hex.value = this.value">
        <input class="form-control" name="primary_color" id="primary_color_hex" required pattern="#[0-9A-Fa-f]{6}" maxlength="9"
               value="<?= e(old('primary_color', $edit['primary_color'])) ?>">
      </div>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold">City</label>
      <input class="form-control" name="city" maxlength="80" value="<?= e(old('city', $edit['city'])) ?>">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold">State</label>
      <input class="form-control" name="state" maxlength="80" value="<?= e(old('state', $edit['state'])) ?>">
    </div>
    <div class="col-md-6">
      <label class="form-label fw-semibold">Address</label>
      <input class="form-control" name="address" maxlength="255" value="<?= e(old('address', $edit['address'])) ?>">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold">Default language</label>
      <select class="form-select" name="default_language">
        <?php foreach (['en' => 'English', 'hi' => 'हिन्दी', 'mr' => 'मराठी'] as $k => $lbl): ?>
          <option value="<?= $k ?>" <?= (string) old('default_language', $edit['default_language']) === $k ? 'selected' : '' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold">Status</label>
      <select class="form-select" name="status">
        <option value="active" <?= (string) old('status', $edit['status']) === 'active' ? 'selected' : '' ?>>Active</option>
        <option value="suspended" <?= (string) old('status', $edit['status']) === 'suspended' ? 'selected' : '' ?>>Suspended</option>
      </select>
    </div>
    <div class="col-md-6">
      <label class="form-label fw-semibold">Landing text <span class="text-muted small">(shown on the society page)</span></label>
      <textarea class="form-control" name="landing_text" rows="2" maxlength="400"><?= e(old('landing_text', $edit['landing_text'])) ?></textarea>
    </div>
    <div class="col-md-6">
      <label class="form-label fw-semibold">Contact</label>
      <div class="d-flex gap-2">
        <input class="form-control" type="email" name="contact_email" placeholder="office@…" maxlength="150" value="<?= e(old('contact_email', $edit['contact_email'])) ?>">
        <input class="form-control" name="contact_phone" placeholder="+91 …" maxlength="20" value="<?= e(old('contact_phone', $edit['contact_phone'])) ?>">
      </div>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold">UPI ID</label>
      <input class="form-control" name="upi_id" maxlength="80" placeholder="society@upi" value="<?= e(old('upi_id', $edit['upi_id'])) ?>">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold">Delivery charge (₹)</label>
      <input class="form-control" type="number" step="0.01" min="0" name="delivery_charge" value="<?= e(old('delivery_charge', $edit['delivery_charge'])) ?>">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold">Commission type</label>
      <select class="form-select" name="commission_type">
        <option value="percent" <?= (string) old('commission_type', $edit['commission_type']) === 'percent' ? 'selected' : '' ?>>Percent</option>
        <option value="fixed" <?= (string) old('commission_type', $edit['commission_type']) === 'fixed' ? 'selected' : '' ?>>Fixed</option>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold">Commission rate</label>
      <input class="form-control" type="number" step="0.01" min="0" name="commission_rate" value="<?= e(old('commission_rate', $edit['commission_rate'])) ?>">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold">Subscription plan</label>
      <select class="form-select" name="subscription_plan">
        <?php foreach (['none' => 'None', 'monthly' => 'Monthly', 'yearly' => 'Yearly'] as $k => $lbl): ?>
          <option value="<?= $k ?>" <?= (string) old('subscription_plan', $edit['subscription_plan']) === $k ? 'selected' : '' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold">Subscription amount (₹)</label>
      <input class="form-control" type="number" step="0.01" min="0" name="subscription_amount" value="<?= e(old('subscription_amount', $edit['subscription_amount'])) ?>">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold">Payment verified by</label>
      <select class="form-select" name="payment_verifier">
        <option value="society_admin" <?= (string) old('payment_verifier', $edit['payment_verifier']) === 'society_admin' ? 'selected' : '' ?>>Society admin</option>
        <option value="super_admin" <?= (string) old('payment_verifier', $edit['payment_verifier']) === 'super_admin' ? 'selected' : '' ?>>Platform (super) admin</option>
      </select>
    </div>
    <div class="col-6 col-md-3 d-flex align-items-end gap-4">
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" id="pickup_enabled" name="pickup_enabled" value="1" <?= (int) old('pickup_enabled', $edit['pickup_enabled']) === 1 ? 'checked' : '' ?>>
        <label class="form-check-label small" for="pickup_enabled">Pickup enabled</label>
      </div>
      <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" id="delivery_enabled" name="delivery_enabled" value="1" <?= (int) old('delivery_enabled', $edit['delivery_enabled']) === 1 ? 'checked' : '' ?>>
        <label class="form-check-label small" for="delivery_enabled">Delivery enabled</label>
      </div>
    </div>
    <div class="col-md-4">
      <label class="form-label fw-semibold">Logo <?= $edit['id'] === null ? '' : '<span class="text-muted small">(leave empty to keep)</span>' ?></label>
      <input type="file" class="form-control" name="logo" accept="image/*">
    </div>
    <div class="col-md-4">
      <label class="form-label fw-semibold">Banner <?= $edit['id'] === null ? '' : '<span class="text-muted small">(leave empty to keep)</span>' ?></label>
      <input type="file" class="form-control" name="banner" accept="image/*">
    </div>
    <div class="col-md-4">
      <label class="form-label fw-semibold">Payment QR image <?= $edit['id'] === null ? '' : '<span class="text-muted small">(leave empty to keep)</span>' ?></label>
      <input type="file" class="form-control" name="qr_image" accept="image/*">
    </div>
    <div class="col-12 d-flex gap-2">
      <button class="btn btn-brand b-btn-pill"><?= $edit['id'] === null ? 'Create society' : 'Save changes' ?></button>
      <a class="btn btn-soft b-btn-pill" href="<?= url('/super/societies') ?>">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>

<?php if ($societies === []): ?>
  <div class="b-card"><div class="empty-state"><i data-lucide="building-2"></i><p>No societies yet — add your first one.</p></div></div>
<?php else: ?>
<div class="b-table fade-up">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead>
        <tr><th>Society</th><th>Location</th><th>Users</th><th>Members</th><th>Plan</th><th>Status</th><th class="text-end">Actions</th></tr>
      </thead>
      <tbody>
        <?php foreach ($societies as $s): ?>
          <tr>
            <td>
              <div class="d-flex align-items-center gap-2">
                <?php if (!empty($s['logo'])): ?>
                  <img src="<?= e(upload_url($s['logo'])) ?>" alt="" width="40" height="40" class="rounded-3 object-fit-cover">
                <?php else: ?>
                  <span class="d-inline-flex align-items-center justify-content-center rounded-3" style="width:40px;height:40px;background:<?= e($s['primary_color']) ?>1A;color:<?= e($s['primary_color']) ?>">
                    <i data-lucide="building-2" width="20" height="20"></i>
                  </span>
                <?php endif; ?>
                <div>
                  <div class="fw-semibold"><?= e($s['name']) ?></div>
                  <div class="small text-muted"><span class="chip p-1 px-2" style="padding:2px 10px"><?= e($s['code']) ?></span> · <?= e($s['slug']) ?></div>
                </div>
              </div>
            </td>
            <td class="small"><?= e($s['city'] ?? '—') ?><div class="text-muted"><?= e($s['state'] ?? '') ?></div></td>
            <td><?= (int) $s['users_count'] ?></td>
            <td><?= (int) $s['members_count'] ?></td>
            <td class="small"><?= e(ucfirst($s['subscription_plan'])) ?><?php if ($s['subscription_plan'] !== 'none'): ?> · <?= money($s['subscription_amount']) ?><?php endif; ?></td>
            <td><span class="<?= e(status_badge($s['status'])) ?>"><?= e($s['status']) ?></span></td>
            <td class="text-end">
              <div class="d-inline-flex gap-2">
                <a class="btn btn-sm btn-soft" href="<?= url('/super/societies?edit=' . $s['id']) ?>">Edit</a>
                <form method="post" action="<?= url('/super/societies/' . $s['id'] . '/status') ?>"
                      data-confirm="<?= $s['status'] === 'active' ? 'Suspend this society? Its residents will lose access until re-activated.' : 'Re-activate this society?' ?>">
                  <?= csrf_field() ?>
                  <input type="hidden" name="status" value="<?= $s['status'] === 'active' ? 'suspended' : 'active' ?>">
                  <button class="btn btn-sm <?= $s['status'] === 'active' ? 'btn-outline-brand' : 'btn-brand' ?>">
                    <?= $s['status'] === 'active' ? 'Suspend' : 'Activate' ?>
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
