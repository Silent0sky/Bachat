<?php
/** Profile page. Vars: $membership (?array — memberships row + society_name) */
$u = auth_user();
?>
<div class="container py-4" style="max-width:920px;">
  <h1 class="section-title mb-1"><?= e(t('profile.title')) ?></h1>
  <p class="text-muted small mb-4"><?= e(t('profile.member_since')) ?>: <?= e(human_date($u['created_at'] ?? null)) ?></p>

  <div class="row g-4">
    <!-- ============ Account + preferences + password ============ -->
    <div class="col-lg-7">
      <div class="b-card mb-3">
        <h2 class="h6 fw-bold text-brand text-uppercase mb-3"><?= e(t('profile.account')) ?></h2>
        <form method="post" action="<?= url('/profile') ?>">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="name"><?= e(t('auth.full_name')) ?> *</label>
            <input type="text" class="form-control" id="name" name="name" maxlength="120"
                   value="<?= e(old('name', $u['name'] ?? '')) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="email"><?= e(t('auth.email')) ?></label>
            <input type="email" class="form-control" id="email" value="<?= e($u['email'] ?? '') ?>" readonly>
            <div class="form-text"><?= e(t('profile.email_note')) ?></div>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="phone"><?= e(t('auth.phone')) ?></label>
            <input type="tel" class="form-control" id="phone" name="phone" maxlength="20"
                   value="<?= e(old('phone', $u['phone'] ?? '')) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="language"><?= e(t('profile.language')) ?> *</label>
            <select class="form-select" id="language" name="language">
              <?php foreach (['en' => 'English', 'hi' => 'हिन्दी', 'mr' => 'मराठी'] as $code => $label): ?>
                <option value="<?= e($code) ?>" <?= (old('language', $u['language'] ?? 'en')) === $code ? 'selected' : '' ?>><?= e($label) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="form-text"><?= e(t('profile.language_note')) ?></div>
          </div>
          <button type="submit" class="btn btn-brand b-btn-pill">
            <i data-lucide="save" width="15" height="15"></i> <?= e(t('common.save')) ?>
          </button>
        </form>
      </div>

      <div class="b-card">
        <h2 class="h6 fw-bold text-brand text-uppercase mb-1"><?= e(t('profile.security')) ?></h2>
        <p class="small text-muted mb-3"><?= e(t('profile.change_password')) ?></p>
        <form method="post" action="<?= url('/profile') ?>">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="current_password"><?= e(t('profile.current_password')) ?></label>
            <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password">
          </div>
          <div class="row g-2 mb-3">
            <div class="col-sm-6">
              <label class="form-label small fw-semibold" for="password"><?= e(t('profile.new_password')) ?></label>
              <input type="password" class="form-control" id="password" name="password" autocomplete="new-password" minlength="6">
            </div>
            <div class="col-sm-6">
              <label class="form-label small fw-semibold" for="password_confirmation"><?= e(t('profile.confirm_password')) ?></label>
              <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" autocomplete="new-password" minlength="6">
            </div>
          </div>
          <button type="submit" class="btn btn-outline-brand b-btn-pill">
            <i data-lucide="key-round" width="15" height="15"></i> <?= e(t('profile.change_password')) ?>
          </button>
        </form>
      </div>
    </div>

    <!-- ============ Membership + shortcuts ============ -->
    <div class="col-lg-5">
      <div class="b-card mb-3">
        <h2 class="h6 fw-bold text-brand text-uppercase mb-3"><?= e(t('profile.my_membership')) ?></h2>
        <?php if ($membership): ?>
          <div class="list-tile mb-2">
            <div class="b-icon-tile mb-0"><i data-lucide="building-2"></i></div>
            <div class="flex-grow-1">
              <div class="fw-semibold"><?= e($membership['society_name'] ?? '—') ?></div>
              <div class="small text-muted">
                <?= e(ucfirst((string) $membership['member_role'])) ?>
                · <?= e(t('profile.member_since')) ?> <?= e(human_date($membership['requested_at'] ?? null)) ?>
              </div>
            </div>
            <span class="<?= status_badge((string) $membership['status']) ?>"><?= e(ucfirst((string) $membership['status'])) ?></span>
          </div>
        <?php else: ?>
          <div class="b-note mb-0"><i data-lucide="info"></i><span><?= e(t('auth.membership_pending')) ?></span></div>
        <?php endif; ?>
      </div>

      <div class="b-card mb-3">
        <h2 class="h6 fw-bold text-brand text-uppercase mb-3"><?= e(t('common.actions')) ?></h2>
        <a class="list-tile mb-2 text-decoration-none text-dark" href="<?= url('/profile/addresses') ?>">
          <div class="b-icon-tile mb-0"><i data-lucide="map-pin"></i></div>
          <div class="flex-grow-1">
            <div class="fw-semibold small"><?= e(t('profile.addresses')) ?></div>
            <div class="small text-muted"><?= e(t('profile.addresses_sub')) ?></div>
          </div>
          <i data-lucide="chevron-right" width="16" height="16" class="text-muted"></i>
        </a>
        <a class="list-tile mb-2 text-decoration-none text-dark" href="<?= url('/orders') ?>">
          <div class="b-icon-tile mb-0"><i data-lucide="package"></i></div>
          <div class="flex-grow-1"><div class="fw-semibold small"><?= e(t('orders.my_orders')) ?></div></div>
          <i data-lucide="chevron-right" width="16" height="16" class="text-muted"></i>
        </a>
        <a class="list-tile text-decoration-none text-dark" href="<?= url('/bookings') ?>">
          <div class="b-icon-tile mb-0"><i data-lucide="calendar-check"></i></div>
          <div class="flex-grow-1"><div class="fw-semibold small"><?= e(t('services.my_bookings')) ?></div></div>
          <i data-lucide="chevron-right" width="16" height="16" class="text-muted"></i>
        </a>
      </div>
    </div>
  </div>
</div>
