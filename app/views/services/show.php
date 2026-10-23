<?php
/** Service detail + booking form. Vars: $service, $addresses */
$sname = fn (array $s): string => (string) ($s['name_' . lang()] ?? '') !== '' ? (string) $s['name_' . lang()] : (string) $s['name_en'];
?>
<div class="container py-4">
  <a class="btn btn-soft btn-sm b-btn-pill mb-3" href="<?= url('/services') ?>"><i data-lucide="arrow-left" width="14" height="14"></i> <?= e(t('common.back')) ?></a>

  <div class="row g-4">
    <!-- ============ Detail column ============ -->
    <div class="col-lg-7">
      <div class="b-card h-100">
        <div class="d-flex align-items-start gap-3 mb-3">
          <?php if (!empty($service['image'])): ?>
            <img src="<?= e(upload_url($service['image'])) ?>" alt="<?= e($sname($service)) ?>" class="rounded-3" style="width:88px;height:88px;object-fit:cover;">
          <?php else: ?>
            <div class="b-icon-tile b-icon-tile-lg"><i data-lucide="wrench"></i></div>
          <?php endif; ?>
          <div>
            <span class="b-badge-grey"><?= e($service['category']) ?></span>
            <h1 class="section-title mt-1 mb-0" style="font-size: clamp(19px, 3vw, 24px);"><?= e($sname($service)) ?></h1>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-3 mb-3">
          <div><div class="stat-value" style="font-size:22px;"><?= e(money($service['base_price'])) ?></div><div class="stat-label"><?= e(t('services.base_price')) ?></div></div>
          <div><div class="stat-value" style="font-size:22px;"><?= e(t('services.duration_mins', ['n' => (string) (int) $service['duration_mins']])) ?></div><div class="stat-label"><?= e(t('services.duration')) ?></div></div>
          <?php if (!empty($service['area'])): ?>
            <div><div class="stat-value" style="font-size:22px;"><?= e($service['area']) ?></div><div class="stat-label"><?= e(t('services.area')) ?></div></div>
          <?php endif; ?>
        </div>

        <?php if (!empty($service['description'])): ?>
          <h6 class="fw-bold text-brand mb-1"><?= e(t('services.what_included')) ?></h6>
          <p class="text-muted small"><?= nl2br(e($service['description'])) ?></p>
        <?php endif; ?>

        <div class="list-tile mb-2">
          <div class="b-icon-tile mb-0"><i data-lucide="store"></i></div>
          <div class="flex-grow-1">
            <div class="fw-semibold small"><?= e(t('services.provider')) ?></div>
            <div class="small text-muted">
              <?= e($service['vendor_name'] ?? '—') ?>
              <?php if (!empty($service['vendor_phone'])): ?> · <?= e($service['vendor_phone']) ?><?php endif; ?>
            </div>
          </div>
        </div>

        <?php if (!empty($service['cancellation_policy'])): ?>
          <div class="b-note mt-2">
            <i data-lucide="shield-check"></i>
            <span><strong><?= e(t('services.cancellation')) ?>:</strong> <?= e($service['cancellation_policy']) ?></span>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ============ Booking form column ============ -->
    <div class="col-lg-5">
      <div class="b-card">
        <h2 class="h5 fw-bold text-brand mb-1"><?= e(t('services.book_this')) ?></h2>
        <p class="text-muted small"><?= e(t('services.choose_slot')) ?></p>

        <form method="post" action="<?= url('/services/' . $service['id'] . '/book') ?>">
          <?= csrf_field() ?>

          <div class="row g-2 mb-2">
            <div class="col-6">
              <label class="form-label small fw-semibold" for="slot_date"><?= e(t('services.slot_date')) ?></label>
              <input type="date" class="form-control" id="slot_date" name="slot_date"
                     min="<?= date('Y-m-d') ?>" value="<?= e(old('slot_date')) ?>" required>
            </div>
            <div class="col-6">
              <label class="form-label small fw-semibold" for="slot_time"><?= e(t('services.slot_time')) ?></label>
              <input type="time" class="form-control" id="slot_time" name="slot_time" value="<?= e(old('slot_time') ?: '10:00') ?>" required>
            </div>
          </div>

          <h6 class="small fw-bold text-uppercase text-muted mt-3 mb-2"><?= e(t('services.pick_address')) ?></h6>

          <?php if (!empty($addresses)): ?>
            <label class="form-label small fw-semibold" for="address_id"><?= e(t('services.select_saved_address')) ?></label>
            <select class="form-select mb-2" id="address_id" name="address_id">
              <option value=""><?= e(t('common.none')) ?></option>
              <?php foreach ($addresses as $a): ?>
                <option value="<?= (int) $a['id'] ?>" <?= (string) old('address_id') === (string) $a['id'] ? 'selected' : '' ?>>
                  <?= e($a['label']) ?> — <?= e($a['line1']) ?><?= $a['city'] ? ', ' . e($a['city']) : '' ?>
                </option>
              <?php endforeach; ?>
            </select>
          <?php endif; ?>

          <label class="form-label small fw-semibold" for="address_text"><?= e(t('services.or_custom_address')) ?></label>
          <textarea class="form-control" id="address_text" name="address_text" rows="2"
                    maxlength="400" placeholder="<?= e(t('services.service_address')) ?>"><?= e(old('address_text')) ?></textarea>

          <label class="form-label small fw-semibold mt-3" for="contact_phone"><?= e(t('services.contact_phone')) ?></label>
          <input type="tel" class="form-control" id="contact_phone" name="contact_phone"
                 value="<?= e(old('contact_phone', auth_user()['phone'] ?? '')) ?>" required>

          <label class="form-label small fw-semibold mt-3" for="notes"><?= e(t('services.notes_for_provider')) ?></label>
          <textarea class="form-control" id="notes" name="notes" rows="2" maxlength="400"><?= e(old('notes')) ?></textarea>

          <button type="submit" class="btn btn-brand w-100 mt-3 b-btn-pill">
            <i data-lucide="calendar-check" class="me-1"></i><?= e(t('services.book_now')) ?> · <?= e(money($service['base_price'])) ?>
          </button>
          <p class="small text-muted mb-0 mt-2">
            <i data-lucide="info" width="13" height="13"></i> <?= e(t('services.campaign_note')) ?>
          </p>
        </form>
      </div>
    </div>
  </div>
</div>
