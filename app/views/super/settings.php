<?php
/** Platform settings. Vars: $settings (key → row), $knownKeys, $gateway */
$knownRows = [];
foreach ($knownKeys as $k) {
    $knownRows[$k] = $settings[$k]['setting_value'] ?? '';
}
$isBool = static fn(string $v): bool => in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);
?>
<div class="mb-4">
  <h1 class="section-title mb-1">Platform Settings</h1>
  <p class="text-muted small mb-0">Key/value configuration used across the platform.</p>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="b-card mb-4 fade-up">
      <h2 class="h6 fw-bold text-brand mb-3">Core settings</h2>
      <form method="post" action="<?= url('/super/settings') ?>" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Support email</label>
          <input class="form-control" type="email" name="support_email" value="<?= e($knownRows['support_email']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Support phone</label>
          <input class="form-control" name="support_phone" value="<?= e($knownRows['support_phone']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Default commission % <span class="text-muted small">(new societies)</span></label>
          <input class="form-control" type="number" step="0.01" min="0" name="default_commission_percent" value="<?= e($knownRows['default_commission_percent']) ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label fw-semibold">Sample prices note</label>
          <input class="form-control" name="sample_prices_note" value="<?= e($knownRows['sample_prices_note']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold d-block">WhatsApp integration</label>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="sw_wa" name="whatsapp_enabled" value="1" <?= $isBool($knownRows['whatsapp_enabled']) ? 'checked' : '' ?>>
            <label class="form-check-label small" for="sw_wa"><?= $isBool($knownRows['whatsapp_enabled']) ? 'Enabled' : 'Disabled' ?></label>
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold d-block">SMS integration</label>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="sw_sms" name="sms_enabled" value="1" <?= $isBool($knownRows['sms_enabled']) ? 'checked' : '' ?>>
            <label class="form-check-label small" for="sw_sms"><?= $isBool($knownRows['sms_enabled']) ? 'Enabled' : 'Disabled' ?></label>
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label fw-semibold d-block">Browser push</label>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" role="switch" id="sw_push" name="browser_push_enabled" value="1" <?= $isBool($knownRows['browser_push_enabled']) ? 'checked' : '' ?>>
            <label class="form-check-label small" for="sw_push"><?= $isBool($knownRows['browser_push_enabled']) ? 'Enabled' : 'Disabled' ?></label>
          </div>
        </div>
        <div class="col-12">
          <button class="btn btn-brand b-btn-pill">Save settings</button>
        </div>
      </form>
    </div>

    <div class="b-card fade-up d1">
      <h2 class="h6 fw-bold text-brand mb-3">Add a new setting key</h2>
      <form method="post" action="<?= url('/super/settings') ?>" class="row g-2 align-items-end">
        <?= csrf_field() ?>
        <div class="col-md-5">
          <label class="form-label fw-semibold">Key <span class="text-muted small">(lowercase, dots/underscores)</span></label>
          <input class="form-control" name="new_key" maxlength="80" pattern="[a-z0-9_\.]+" placeholder="e.g. delivery_slot_note" required>
        </div>
        <div class="col-md-5">
          <label class="form-label fw-semibold">Value</label>
          <input class="form-control" name="new_value">
        </div>
        <div class="col-md-2 d-grid">
          <button class="btn btn-forest">Add</button>
        </div>
      </form>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="b-card mb-4 fade-up d2">
      <h2 class="h6 fw-bold text-brand mb-3">Payment gateway status <span class="text-muted small fw-normal">(read-only — set in app/config/config.php)</span></h2>
      <div class="d-flex flex-column gap-2">
        <div class="list-tile">
          <i data-lucide="smartphone" class="text-brand"></i>
          <div class="flex-grow-1">
            <div class="fw-semibold small">UPI manual payments</div>
            <div class="small text-muted">Per-society UPI ID / QR · admin verification</div>
          </div>
          <span class="<?= $gateway['upi_enabled'] ? 'b-badge-green' : 'b-badge-grey' ?>"><?= $gateway['upi_enabled'] ? 'enabled' : 'off' ?></span>
        </div>
        <div class="list-tile">
          <i data-lucide="banknote" class="text-brand"></i>
          <div class="flex-grow-1">
            <div class="fw-semibold small">Cash on delivery / collection</div>
            <div class="small text-muted">Collected at handover, verified by admin</div>
          </div>
          <span class="<?= $gateway['cod_enabled'] ? 'b-badge-green' : 'b-badge-grey' ?>"><?= $gateway['cod_enabled'] ? 'enabled' : 'off' ?></span>
        </div>
        <div class="list-tile">
          <i data-lucide="credit-card" class="text-brand"></i>
          <div class="flex-grow-1">
            <div class="fw-semibold small">Online gateway</div>
            <div class="small text-muted"><?= $gateway['gateway_provider'] !== '' ? e($gateway['gateway_provider']) : 'no provider configured' ?></div>
          </div>
          <span class="<?= $gateway['gateway_enabled'] ? 'b-badge-green' : 'b-badge-amber' ?>"><?= $gateway['gateway_enabled'] ? 'enabled' : 'not configured' ?></span>
        </div>
        <div class="list-tile">
          <i data-lucide="mail" class="text-brand"></i>
          <div class="flex-grow-1">
            <div class="fw-semibold small">Email dispatch</div>
            <div class="small text-muted">PHP mail() — in-app notifications always work</div>
          </div>
          <span class="<?= $gateway['mail_enabled'] ? 'b-badge-green' : 'b-badge-grey' ?>"><?= $gateway['mail_enabled'] ? 'enabled' : 'off' ?></span>
        </div>
      </div>
      <div class="small text-muted mt-2"><i data-lucide="clock" width="13" height="13"></i> App timezone: <strong><?= e($gateway['timezone']) ?></strong></div>
    </div>

    <div class="b-card fade-up d3">
      <h2 class="h6 fw-bold text-brand mb-3">Integration placeholders</h2>
      <div class="b-note mb-2">
        <i data-lucide="info"></i>
        <span>WhatsApp / SMS / push are stubs by design. Add API credentials in <code>app/config/config.php → integrations</code> and wire them in a mailer/queue service — no code changes are needed in these panels.</span>
      </div>
      <div class="small text-muted">Current values: whatsapp_api_url <?= config('integrations.whatsapp_api_url') ? 'set' : 'empty' ?> · sms_api_url <?= config('integrations.sms_api_url') ? 'set' : 'empty' ?> · browser_push <?= config('integrations.browser_push') ? 'on' : 'off' ?></div>
    </div>
  </div>
</div>
