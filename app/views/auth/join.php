<?php
/**
 * Join society page (Agent A). Public.
 * Vars: $societies — active societies.
 */
$socSel = (int) old('society_id', 0);
?>
<div class="py-3">
  <div class="text-center mb-4">
    <h1 class="section-title"><?= e(t('auth.join_title')) ?></h1>
    <p class="text-muted mb-0"><?= e(t('auth.join_sub')) ?></p>
  </div>

  <?php if ($societies): ?>
  <h2 class="fs-6 fw-bold text-uppercase text-muted mb-2"><?= e(t('auth.join_societies')) ?></h2>
  <div class="row g-3 mb-4">
    <?php foreach ($societies as $i => $s): ?>
    <div class="col-12 col-md-6 col-lg-4">
      <div class="b-card b-card-hover h-100 fade-up<?= $i < 4 ? ' d' . ($i + 1) : '' ?>">
        <div class="d-flex align-items-center gap-3">
          <?php if (!empty($s['logo'])): ?>
            <img src="<?= e(upload_url($s['logo'])) ?>" alt="" width="48" height="48" class="rounded-3" style="object-fit:cover;">
          <?php else: ?>
            <span class="b-avatar" style="width:48px;height:48px;font-size:20px;"><?= e(mb_strtoupper(mb_substr($s['name'], 0, 1))) ?></span>
          <?php endif; ?>
          <div class="min-w-0">
            <div class="fw-bold text-truncate"><?= e($s['name']) ?></div>
            <div class="small text-muted"><?= e($s['city'] ?: '') ?></div>
          </div>
        </div>
        <?php if (!empty($s['landing_text'])): ?>
          <p class="small text-muted mt-2 mb-0" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;">
            <?= e($s['landing_text']) ?>
          </p>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="row justify-content-center">
    <div class="col-12 col-md-9 col-lg-7">
      <div class="b-card fade-up d2">
        <h2 class="fs-5 fw-bold mb-3"><?= e(t('auth.join_form')) ?></h2>
        <form method="post" action="<?= url('/join') ?>">
          <?= csrf_field() ?>
          <?php if (!auth_check()): ?>
          <div class="row g-3">
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold" for="j-name"><?= e(t('auth.full_name')) ?> *</label>
              <input type="text" class="form-control" id="j-name" name="name" value="<?= e(old('name')) ?>" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold" for="j-phone"><?= e(t('auth.phone')) ?> *</label>
              <input type="tel" class="form-control" id="j-phone" name="phone" value="<?= e(old('phone')) ?>" required>
            </div>
            <div class="col-12">
              <label class="form-label fw-semibold" for="j-email"><?= e(t('auth.email')) ?> *</label>
              <input type="email" class="form-control" id="j-email" name="email" value="<?= e(old('email')) ?>" required>
            </div>
          </div>
          <?php endif; ?>

          <div class="row g-3 mt-0">
            <div class="col-12">
              <label class="form-label fw-semibold" for="j-society"><?= e(t('auth.society')) ?> *</label>
              <select class="form-select" id="j-society" name="society_id" required>
                <option value=""><?= e(t('auth.select_society')) ?></option>
                <?php foreach ($societies as $s): ?>
                  <option value="<?= (int) $s['id'] ?>" <?= $socSel === (int) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?><?= $s['city'] ? ' — ' . e($s['city']) : '' ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label fw-semibold" for="j-block"><?= e(t('auth.block')) ?></label>
              <input type="text" class="form-control" id="j-block" name="block" value="<?= e(old('block')) ?>" maxlength="20" placeholder="A">
            </div>
            <div class="col-6 col-md-3">
              <label class="form-label fw-semibold" for="j-flat"><?= e(t('auth.flat_no')) ?> *</label>
              <input type="text" class="form-control" id="j-flat" name="flat_no" value="<?= e(old('flat_no')) ?>" maxlength="30" placeholder="101" required>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold d-block"><?= e(t('auth.member_as')) ?> *</label>
              <div class="d-flex gap-3 mt-1">
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="member_as" id="j-owner" value="owner" <?= old('member_as', 'owner') === 'owner' ? 'checked' : '' ?>>
                  <label class="form-check-label" for="j-owner"><?= e(t('auth.owner')) ?></label>
                </div>
                <div class="form-check">
                  <input class="form-check-input" type="radio" name="member_as" id="j-tenant" value="tenant" <?= old('member_as') === 'tenant' ? 'checked' : '' ?>>
                  <label class="form-check-label" for="j-tenant"><?= e(t('auth.tenant')) ?></label>
                </div>
              </div>
            </div>
          </div>

          <?php if (!auth_check()): ?>
          <div class="row g-3 mt-0">
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold" for="j-pw"><?= e(t('auth.password')) ?> *</label>
              <input type="password" class="form-control" id="j-pw" name="password" minlength="6" required autocomplete="new-password">
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold" for="j-pw2"><?= e(t('auth.password_confirm')) ?> *</label>
              <input type="password" class="form-control" id="j-pw2" name="password_confirmation" minlength="6" required autocomplete="new-password">
            </div>
          </div>
          <?php endif; ?>

          <div class="row g-3 mt-0">
            <div class="col-12 col-md-6">
              <label class="form-label fw-semibold" for="j-invite"><?= e(t('auth.invite_code')) ?></label>
              <input type="text" class="form-control" id="j-invite" name="invite_code" value="<?= e(old('invite_code')) ?>">
            </div>
          </div>

          <p class="small text-muted mt-3 mb-0"><i data-lucide="info" class="me-1" style="width:14px;height:14px;vertical-align:-2px;"></i><?= e(t('auth.flat_note')) ?></p>
          <button type="submit" class="btn btn-brand b-btn-pill mt-3 px-4">
            <i data-lucide="building-2" class="me-1"></i><?= e(t('auth.join_btn')) ?>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
