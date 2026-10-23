<?php
/** Resident bookings. Vars: $bookings, $upiId */
$bname = fn (array $b): string => (string) ($b['name_' . lang()] ?? '') !== '' ? (string) $b['name_' . lang()] : (string) $b['name_en'];
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4">
    <div>
      <h1 class="section-title mb-1"><?= e(t('bookings.title')) ?></h1>
      <p class="text-muted small mb-0"><?= e(t('bookings.sub')) ?></p>
    </div>
    <a class="btn btn-brand btn-sm b-btn-pill" href="<?= url('/services') ?>">
      <i data-lucide="plus" width="14" height="14"></i> <?= e(t('bookings.browse_services')) ?>
    </a>
  </div>

  <?php if (empty($bookings)): ?>
    <div class="empty-state b-card">
      <i data-lucide="calendar-check"></i>
      <p><?= e(t('bookings.none')) ?></p>
      <a class="btn btn-brand b-btn-pill mt-3" href="<?= url('/services') ?>"><?= e(t('bookings.browse_services')) ?></a>
    </div>
  <?php else: ?>
  <div class="row g-3">
    <?php foreach ($bookings as $b):
        $id = (int) $b['id'];
        $cancellable  = in_array($b['status'], ['pending', 'confirmed'], true);
        $reschedulable = in_array($b['status'], ['pending', 'confirmed', 'assigned', 'rescheduled'], true);
        $showOtp      = in_array($b['status'], ['pending', 'confirmed', 'assigned', 'in_progress'], true) && !empty($b['otp_code']);
        $payStatus    = $b['pay_status'] ?? null;
        $showPayForm  = $b['status'] === 'confirmed' && !in_array($payStatus, ['submitted', 'verified'], true);
    ?>
    <div class="col-12">
      <div class="b-card">
        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
          <div>
            <div class="small text-muted mb-1">
              <i data-lucide="hash" width="13" height="13"></i> <?= e(t('bookings.booking_no')) ?> <strong><?= e($b['booking_no']) ?></strong>
              · <?= e(human_dt($b['created_at'])) ?>
            </div>
            <h5 class="fw-bold mb-1"><?= e($bname($b)) ?></h5>
            <div class="small text-muted">
              <i data-lucide="calendar-clock" width="13" height="13"></i> <?= e(t('bookings.slot')) ?>: <?= e(human_dt($b['slot_at'])) ?>
              <?php if (!empty($b['vendor_name'])): ?>
                · <i data-lucide="store" width="13" height="13"></i> <?= e(t('bookings.provider')) ?>: <?= e($b['vendor_name']) ?>
              <?php endif; ?>
              <?php if (!empty($b['campaign_title'])): ?>
                · <span class="b-badge-amber"><?= e(t('bookings.group_deal')) ?>: <?= e($b['campaign_title']) ?></span>
              <?php endif; ?>
            </div>
          </div>
          <div class="text-end">
            <div class="price"><?= e(money($b['price'])) ?></div>
            <span class="<?= status_badge($b['status']) ?>"><?= e(ucfirst(str_replace('_', ' ', (string) $b['status']))) ?></span>
            <?php if ($payStatus === 'verified'): ?>
              <div class="mt-1"><span class="b-badge-green"><?= e(t('bookings.payment_verified')) ?></span></div>
            <?php elseif ($payStatus === 'submitted'): ?>
              <div class="mt-1"><span class="b-badge-amber"><?= e(t('bookings.payment_awaiting')) ?></span></div>
            <?php endif; ?>
          </div>
        </div>

        <div class="d-flex flex-wrap gap-2 mt-3">
          <button class="btn btn-soft btn-sm b-btn-pill" type="button" data-bs-toggle="collapse" data-bs-target="#bd-<?= $id ?>" aria-expanded="false">
            <i data-lucide="info" width="14" height="14"></i> <?= e(t('common.details')) ?>
          </button>
          <?php if ($reschedulable): ?>
            <button class="btn btn-outline-brand btn-sm b-btn-pill" type="button" data-bs-toggle="collapse" data-bs-target="#rs-<?= $id ?>">
              <i data-lucide="calendar-sync" width="14" height="14"></i> <?= e(t('bookings.reschedule')) ?>
            </button>
          <?php endif; ?>
          <?php if ($showPayForm): ?>
            <button class="btn btn-amber btn-sm b-btn-pill" type="button" data-bs-toggle="collapse" data-bs-target="#pay-<?= $id ?>">
              <i data-lucide="badge-indian-rupee" width="14" height="14"></i> <?= e(t('bookings.pay_upi')) ?>
            </button>
          <?php endif; ?>
          <?php if ($cancellable): ?>
            <form method="post" action="<?= url('/bookings/' . $id . '/cancel') ?>" data-confirm="<?= e(t('services.cancel_confirm')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="cancel_reason" value="Cancelled by resident">
              <button type="submit" class="btn btn-outline-danger btn-sm b-btn-pill">
                <i data-lucide="x-circle" width="14" height="14"></i> <?= e(t('bookings.cancel')) ?>
              </button>
            </form>
          <?php endif; ?>
        </div>

        <!-- ============ Details ============ -->
        <div class="collapse mt-3" id="bd-<?= $id ?>">
          <div class="border-top pt-3">
            <div class="row g-3 small">
              <div class="col-md-6">
                <div class="fw-semibold text-muted"><?= e(t('bookings.address')) ?></div>
                <div><?= e($b['address_text'] ?: '—') ?></div>
                <div class="mt-2 fw-semibold text-muted"><?= e(t('bookings.phone')) ?></div>
                <div><?= e($b['contact_phone'] ?: '—') ?></div>
                <?php if (!empty($b['notes'])): ?>
                  <div class="mt-2 fw-semibold text-muted"><?= e(t('common.notes')) ?></div>
                  <div><?= e($b['notes']) ?></div>
                <?php endif; ?>
              </div>
              <div class="col-md-6">
                <?php if ($showOtp): ?>
                  <div class="b-note mb-2">
                    <i data-lucide="key-round"></i>
                    <span>
                      <strong><?= e(t('bookings.completion_code')) ?>:</strong>
                      <span class="fw-bold fs-5 ms-1" style="letter-spacing:3px;"><?= e($b['otp_code']) ?></span>
                    </span>
                  </div>
                <?php endif; ?>
                <?php if (!empty($b['cancel_reason'])): ?>
                  <div class="fw-semibold text-muted"><?= e(t('bookings.cancel_reason')) ?></div>
                  <div><?= e($b['cancel_reason']) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>

        <!-- ============ Reschedule ============ -->
        <?php if ($reschedulable): ?>
        <div class="collapse mt-3" id="rs-<?= $id ?>">
          <div class="border-top pt-3 bg-brand-soft rounded-3 p-3">
            <form method="post" action="<?= url('/bookings/' . $id . '/reschedule') ?>" class="row g-2 align-items-end">
              <?= csrf_field() ?>
              <div class="col-6 col-md-4">
                <label class="form-label small fw-semibold mb-1"><?= e(t('bookings.new_slot')) ?></label>
                <input type="date" class="form-control form-control-sm" name="slot_date" min="<?= date('Y-m-d') ?>" required>
              </div>
              <div class="col-6 col-md-3">
                <label class="form-label small fw-semibold mb-1">&nbsp;</label>
                <input type="time" class="form-control form-control-sm" name="slot_time" value="10:00" required>
              </div>
              <div class="col-12 col-md-5">
                <button type="submit" class="btn btn-brand btn-sm w-100 b-btn-pill">
                  <i data-lucide="check" width="14" height="14"></i> <?= e(t('bookings.reschedule')) ?>
                </button>
              </div>
            </form>
          </div>
        </div>
        <?php endif; ?>

        <!-- ============ UPI payment ============ -->
        <?php if ($showPayForm): ?>
        <div class="collapse mt-3" id="pay-<?= $id ?>">
          <div class="border-top pt-3">
            <div class="b-note mb-3"><i data-lucide="wallet"></i><span><?= e(t('bookings.pay_hint')) ?></span></div>
            <form method="post" action="<?= url('/bookings/' . $id . '/pay') ?>" enctype="multipart/form-data" class="row g-2">
              <?= csrf_field() ?>
              <div class="col-12">
                <label class="form-label small fw-semibold mb-1"><?= e(t('bookings.upi_id')) ?></label>
                <div class="input-group">
                  <input type="text" class="form-control bg-brand-soft fw-bold" value="<?= e($upiId ?: '—') ?>" readonly aria-label="<?= e(t('bookings.upi_id')) ?>">
                  <button class="btn btn-soft" type="button" onclick="navigator.clipboard && navigator.clipboard.writeText('<?= e($upiId) ?>');bachToast('UPI ID copied ✓','success');">
                    <i data-lucide="copy" width="14" height="14"></i>
                  </button>
                </div>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold mb-1"><?= e(t('bookings.utr')) ?> *</label>
                <input type="text" class="form-control form-control-sm" name="utr" maxlength="80" required>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold mb-1"><?= e(t('bookings.screenshot')) ?></label>
                <input type="file" class="form-control form-control-sm" name="proof_image" accept="image/*">
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-amber btn-sm b-btn-pill">
                  <i data-lucide="send" width="14" height="14"></i> <?= e(t('bookings.pay_submit')) ?> · <?= e(money($b['price'])) ?>
                </button>
              </div>
            </form>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
