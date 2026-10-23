<?php
/** Saved addresses. Vars: $addrs (addresses rows) */
?>
<div class="container py-4" style="max-width:880px;">
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4">
    <div>
      <h1 class="section-title mb-1"><?= e(t('profile.addresses')) ?></h1>
      <p class="text-muted small mb-0"><?= e(t('profile.addresses_sub')) ?></p>
    </div>
    <a class="btn btn-soft btn-sm b-btn-pill" href="<?= url('/profile') ?>">
      <i data-lucide="user-cog" width="14" height="14"></i> <?= e(t('profile.title')) ?>
    </a>
  </div>

  <div class="row g-4">
    <!-- ============ Address list ============ -->
    <div class="col-lg-7">
      <?php if (empty($addrs)): ?>
        <div class="empty-state b-card">
          <i data-lucide="map-pin"></i>
          <p><?= e(t('profile.no_addresses')) ?></p>
        </div>
      <?php else: ?>
        <?php foreach ($addrs as $a): ?>
          <div class="b-card mb-3">
            <div class="d-flex justify-content-between align-items-start gap-2">
              <div class="d-flex gap-3 align-items-start">
                <div class="b-icon-tile mb-0"><i data-lucide="<?= (int) $a['is_default'] === 1 ? 'house' : 'map-pin' ?>"></i></div>
                <div>
                  <div class="fw-semibold">
                    <?= e($a['label']) ?>
                    <?php if ((int) $a['is_default'] === 1): ?>
                      <span class="b-badge-green ms-1"><?= e(t('profile.default_addr')) ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="small text-muted">
                    <?= e($a['line1']) ?>
                    <?= $a['city'] ? ', ' . e($a['city']) : '' ?>
                    <?= $a['pincode'] ? ' - ' . e($a['pincode']) : '' ?>
                  </div>
                </div>
              </div>
              <form method="post" action="<?= url('/addresses/' . (int) $a['id'] . '/delete') ?>"
                    data-confirm="<?= e(t('profile.delete_confirm')) ?>">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-outline-danger btn-sm" aria-label="<?= e(t('common.delete')) ?>">
                  <i data-lucide="trash-2" width="14" height="14"></i>
                </button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- ============ Add address ============ -->
    <div class="col-lg-5">
      <div class="b-card">
        <h2 class="h6 fw-bold text-brand text-uppercase mb-3"><i data-lucide="plus" width="15" height="15"></i> <?= e(t('profile.add_new')) ?></h2>
        <form method="post" action="<?= url('/profile/addresses') ?>">
          <?= csrf_field() ?>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="label"><?= e(t('profile.label')) ?> *</label>
            <input type="text" class="form-control" id="label" name="label" maxlength="60"
                   value="<?= e(old('label')) ?>" placeholder="Home" required>
          </div>
          <div class="mb-3">
            <label class="form-label small fw-semibold" for="line1"><?= e(t('profile.line1')) ?> *</label>
            <textarea class="form-control" id="line1" name="line1" rows="2" maxlength="255" required><?= e(old('line1')) ?></textarea>
          </div>
          <div class="row g-2 mb-3">
            <div class="col-6">
              <label class="form-label small fw-semibold" for="city"><?= e(t('profile.city')) ?></label>
              <input type="text" class="form-control" id="city" name="city" maxlength="80" value="<?= e(old('city')) ?>">
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold" for="pincode"><?= e(t('profile.pincode')) ?></label>
              <input type="text" class="form-control" id="pincode" name="pincode" maxlength="10" inputmode="numeric" value="<?= e(old('pincode')) ?>">
            </div>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" name="is_default" id="is_default">
            <label class="form-check-label small" for="is_default"><?= e(t('profile.default')) ?></label>
          </div>
          <button type="submit" class="btn btn-brand w-100 b-btn-pill">
            <i data-lucide="save" width="15" height="15"></i> <?= e(t('profile.add_address')) ?>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
