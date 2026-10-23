<?php
/** Society settings. Vars: $society (full societies row), $subscription (?array latest subscription) */
$langs  = ['en' => 'English', 'hi' => 'हिन्दी', 'mr' => 'मराठी'];
$sel = fn (string $v, string $cur): string => $v === $cur ? 'selected' : '';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="section-title mb-1">Society Settings</h1>
    <p class="text-muted small mb-0">Branding, payments and fulfilment for <strong><?= e($society['name']) ?></strong></p>
  </div>
  <a class="btn btn-soft b-btn-pill" href="<?= url('/society/' . $society['slug']) ?>" target="_blank" rel="noopener">
    <i data-lucide="external-link" class="me-1"></i> View public page
  </a>
</div>

<?php if ($subscription): ?>
<div class="b-card mb-4 d-flex flex-wrap align-items-center gap-3">
  <div class="b-icon-tile mb-0"><i data-lucide="credit-card"></i></div>
  <div class="flex-grow-1">
    <div class="fw-semibold">
      Subscription: <span class="text-capitalize"><?= e((string) $subscription['plan']) ?></span>
      · <?= money($subscription['amount']) ?>
      <span class="<?= status_badge((string) $subscription['status']) ?> ms-1"><?= e(ucfirst(str_replace('_', ' ', (string) $subscription['status']))) ?></span>
    </div>
    <div class="small text-muted">
      <?php if (!empty($subscription['starts_at'])): ?><?= e(human_date($subscription['starts_at'])) ?> → <?= e(human_date($subscription['ends_at'])) ?> ·<?php endif; ?>
      Managed by the platform admin
    </div>
  </div>
</div>
<?php endif; ?>

<form method="post" action="<?= url('/admin/settings') ?>" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <div class="row g-4">

    <!-- ============ Branding ============ -->
    <div class="col-lg-6">
      <div class="b-card h-100">
        <h2 class="h6 fw-bold text-brand mb-3"><i data-lucide="building-2" class="me-1"></i>Branding &amp; profile</h2>

        <label class="form-label small fw-semibold">Society name *</label>
        <input type="text" class="form-control mb-3" name="name" maxlength="150" value="<?= e($society['name']) ?>" required>

        <div class="row g-2 mb-3">
          <div class="col-md-6">
            <label class="form-label small fw-semibold">City</label>
            <input type="text" class="form-control" name="city" maxlength="80" value="<?= e($society['city'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">State</label>
            <input type="text" class="form-control" name="state" maxlength="80" value="<?= e($society['state'] ?? '') ?>">
          </div>
        </div>

        <label class="form-label small fw-semibold">Address</label>
        <input type="text" class="form-control mb-3" name="address" maxlength="255" value="<?= e($society['address'] ?? '') ?>">

        <div class="row g-2 mb-3">
          <div class="col-7">
            <label class="form-label small fw-semibold" for="pc">Primary color *</label>
            <div class="input-group">
              <span class="input-group-text p-1">
                <input type="color" id="pc" class="form-control form-control-color p-0 border-0" style="width:34px;height:34px;"
                       value="<?= e($society['primary_color']) ?>" oninput="document.getElementById('pcHex').value=this.value">
              </span>
              <input type="text" class="form-control" id="pcHex" name="primary_color" maxlength="9"
                     pattern="#[0-9A-Fa-f]{6}" value="<?= e($society['primary_color']) ?>" required>
            </div>
          </div>
          <div class="col-5">
            <label class="form-label small fw-semibold">Default language</label>
            <select class="form-select" name="default_language">
              <?php foreach ($langs as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= $sel($code, (string) $society['default_language']) ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>

        <label class="form-label small fw-semibold">Landing text (shown on your public page)</label>
        <textarea class="form-control" name="landing_text" rows="2" maxlength="400"><?= e($society['landing_text'] ?? '') ?></textarea>

        <div class="row g-3 mt-1">
          <div class="col-md-4">
            <label class="form-label small fw-semibold">Logo</label>
            <div class="d-flex align-items-center gap-2 mb-1">
              <?php if (!empty($society['logo'])): ?>
                <img src="<?= e(upload_url($society['logo'])) ?>" alt="logo" class="rounded-3" style="height:34px;">
              <?php else: ?>
                <span class="b-icon-tile" style="width:34px;height:34px;"><i data-lucide="image" width="16" height="16"></i></span>
              <?php endif; ?>
              <span class="small text-muted"><?= !empty($society['logo']) ? 'Replace below' : 'No logo yet' ?></span>
            </div>
            <input type="file" class="form-control form-control-sm" name="logo" accept="image/*">
          </div>
          <div class="col-md-8">
            <label class="form-label small fw-semibold">Banner</label>
            <?php if (!empty($society['banner'])): ?>
              <img src="<?= e(upload_url($society['banner'])) ?>" alt="banner" class="rounded-3 d-block mb-1" style="max-height:52px;">
            <?php else: ?>
              <div class="small text-muted mb-1">No banner yet</div>
            <?php endif; ?>
            <input type="file" class="form-control form-control-sm" name="banner" accept="image/*">
          </div>
        </div>
      </div>
    </div>

    <!-- ============ Payments + fulfilment ============ -->
    <div class="col-lg-6">
      <div class="b-card h-100">
        <h2 class="h6 fw-bold text-brand mb-3"><i data-lucide="wallet" class="me-1"></i>Payments &amp; fulfilment</h2>

        <label class="form-label small fw-semibold">Society UPI ID</label>
        <input type="text" class="form-control mb-3" name="upi_id" maxlength="80" placeholder="society@upi" value="<?= e($society['upi_id'] ?? '') ?>">

        <label class="form-label small fw-semibold">UPI QR image</label>
        <?php if (!empty($society['qr_image'])): ?>
          <img src="<?= e(upload_url($society['qr_image'])) ?>" alt="UPI QR" class="rounded-3 d-block mb-1" style="max-height:70px;">
        <?php else: ?>
          <div class="small text-muted mb-1">No QR uploaded — residents pay using the UPI ID text.</div>
        <?php endif; ?>
        <input type="file" class="form-control form-control-sm mb-3" name="qr_image" accept="image/*">

        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label small fw-semibold">Payment verification by</label>
            <select class="form-select" name="payment_verifier">
              <option value="society_admin" <?= $sel('society_admin', (string) $society['payment_verifier']) ?>>Society admin</option>
              <option value="super_admin" <?= $sel('super_admin', (string) $society['payment_verifier']) ?>>Platform (super admin)</option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label small fw-semibold">Delivery charge (₹)</label>
            <input type="number" class="form-control" name="delivery_charge" min="0" step="0.01" value="<?= e((string) $society['delivery_charge']) ?>">
          </div>
        </div>

        <div class="form-check form-switch mb-2">
          <input class="form-check-input" type="checkbox" role="switch" id="sw_pickup" name="pickup_enabled" value="1"
                 <?= (int) $society['pickup_enabled'] === 1 ? 'checked' : '' ?>>
          <label class="form-check-label small fw-semibold" for="sw_pickup">Society pickup point enabled</label>
        </div>
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" role="switch" id="sw_delivery" name="delivery_enabled" value="1"
                 <?= (int) $society['delivery_enabled'] === 1 ? 'checked' : '' ?>>
          <label class="form-check-label small fw-semibold" for="sw_delivery">Home delivery enabled</label>
        </div>

        <hr>
        <h2 class="h6 fw-bold text-brand mb-3"><i data-lucide="phone" class="me-1"></i>Contact</h2>
        <div class="row g-2">
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Contact email</label>
            <input type="email" class="form-control" name="contact_email" maxlength="150" value="<?= e($society['contact_email'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-semibold">Contact phone</label>
            <input type="text" class="form-control" name="contact_phone" maxlength="20" value="<?= e($society['contact_phone'] ?? '') ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- ============ Read-only platform-managed ============ -->
    <div class="col-12">
      <div class="b-card">
        <h2 class="h6 fw-bold text-brand mb-3"><i data-lucide="lock" class="me-1"></i>Managed by the platform admin</h2>
        <div class="row g-3 small">
          <div class="col-6 col-md-3"><div class="text-muted">Society code</div><div class="fw-semibold"><?= e($society['code']) ?></div></div>
          <div class="col-6 col-md-3"><div class="text-muted">Status</div><div><span class="<?= status_badge((string) $society['status']) ?>"><?= e(ucfirst((string) $society['status'])) ?></span></div></div>
          <div class="col-6 col-md-3"><div class="text-muted">Commission</div><div class="fw-semibold text-capitalize"><?= e((string) $society['commission_type']) ?> · <?= e(qty($society['commission_rate'])) ?><?= $society['commission_type'] === 'percent' ? '%' : ' ₹' ?></div></div>
          <div class="col-6 col-md-3"><div class="text-muted">Members</div><div class="fw-semibold"><?= (int) \App\Core\Database::scalar('SELECT COUNT(*) FROM memberships WHERE society_id = ? AND status = "active"', [$society['id']]) ?> active</div></div>
        </div>
      </div>
    </div>

    <div class="col-12 d-flex gap-2">
      <button type="submit" class="btn btn-brand b-btn-pill px-4">
        <i data-lucide="save" width="15" height="15"></i> Save settings
      </button>
      <a class="btn btn-soft b-btn-pill" href="<?= url('/admin/settings') ?>">Reset</a>
    </div>
  </div>
</form>
