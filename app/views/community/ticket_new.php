<?php
/**
 * Resident — create support ticket.
 * Vars: $categories, $priorities.
 */
$prioHint = [
    'low'    => t('tickets.prio_low'),
    'medium' => t('tickets.prio_medium'),
    'high'   => t('tickets.prio_high'),
    'urgent' => t('tickets.prio_urgent'),
];
$catLabel = [
    'Order issue'     => t('tickets.cat_order'),
    'Payment'         => t('tickets.cat_payment'),
    'Service booking' => t('tickets.cat_service'),
    'Delivery'        => t('tickets.cat_delivery'),
    'Membership'      => t('tickets.cat_membership'),
    'Suggestion'      => t('tickets.cat_suggestion'),
    'Other'           => t('tickets.cat_other'),
];
?>
<div class="row justify-content-center">
  <div class="col-12 col-lg-9 col-xl-8">
    <a class="btn btn-soft btn-sm b-btn-pill mb-3 fade-up" href="<?= url('/tickets') ?>">
      <i data-lucide="arrow-left" style="width:15px;height:15px"></i> <?= e(t('common.back')) ?>
    </a>

    <div class="mb-4 fade-up">
      <h1 class="section-title mb-1"><?= e(t('tickets.new_ticket')) ?></h1>
      <p class="text-muted mb-0"><?= e(t('tickets.new_sub')) ?></p>
    </div>

    <form class="b-card fade-up d1" method="post" action="<?= url('/tickets') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>

      <div class="mb-3">
        <label class="form-label small fw-semibold" for="tk_subject"><?= e(t('tickets.subject')) ?> *</label>
        <input class="form-control" id="tk_subject" name="subject" maxlength="200" required value="<?= e(old('subject')) ?>">
      </div>

      <div class="row g-2 mb-3">
        <div class="col-12 col-sm-7">
          <label class="form-label small fw-semibold"><?= e(t('tickets.category')) ?></label>
          <select class="form-select" name="category">
            <?php foreach ($categories as $c): ?>
              <option value="<?= e($c) ?>" <?= old('category') === $c ? 'selected' : '' ?>><?= e($catLabel[$c] ?? $c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-sm-5">
          <label class="form-label small fw-semibold"><?= e(t('tickets.priority')) ?></label>
          <select class="form-select" name="priority">
            <?php foreach ($priorities as $p): ?>
              <option value="<?= e($p) ?>" <?= (old('priority') ?: 'medium') === $p ? 'selected' : '' ?>><?= e($prioHint[$p] ?? $p) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-12 col-sm-6">
          <label class="form-label small fw-semibold"><?= e(t('tickets.order_ref')) ?></label>
          <input class="form-control" name="order_no" placeholder="ORD…" value="<?= e(old('order_no')) ?>">
        </div>
        <div class="col-12 col-sm-6">
          <label class="form-label small fw-semibold"><?= e(t('tickets.booking_ref')) ?></label>
          <input class="form-control" name="booking_no" placeholder="BK…" value="<?= e(old('booking_no')) ?>">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold" for="tk_msg"><?= e(t('tickets.message')) ?> *</label>
        <textarea class="form-control" id="tk_msg" name="message" rows="6" required><?= e(old('message')) ?></textarea>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold"><?= e(t('tickets.attachment')) ?></label>
        <input type="file" class="form-control" name="attachment" accept="image/*,.pdf,.txt">
      </div>

      <div class="b-note mb-3">
        <i data-lucide="shield-check"></i>
        <span><?= e(t('tickets.contact_note')) ?></span>
      </div>

      <button class="btn btn-brand w-100" type="submit">
        <i data-lucide="send" style="width:15px;height:15px"></i> <?= e(t('common.submit')) ?>
      </button>
    </form>
  </div>
</div>
