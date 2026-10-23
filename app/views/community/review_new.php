<?php
/**
 * Resident — write a verified review.
 * Vars: $ctx = ['target_type','target_id','order_id','booking_id','label','sub','back','dup'].
 */
$typeIcon = [
    'product'  => 'shopping-basket',
    'service'  => 'wrench',
    'vendor'   => 'store',
    'delivery' => 'bike',
];
?>
<div class="row justify-content-center">
  <div class="col-12 col-lg-8 col-xl-7">
    <a class="btn btn-soft btn-sm b-btn-pill mb-3 fade-up" href="<?= url((string) $ctx['back']) ?>">
      <i data-lucide="arrow-left" style="width:15px;height:15px"></i> <?= e(t('common.back')) ?>
    </a>

    <div class="mb-4 fade-up">
      <h1 class="section-title mb-1"><?= e(t('reviews.write_title')) ?></h1>
      <p class="text-muted mb-0"><?= e(t('reviews.write_sub')) ?></p>
    </div>

    <div class="b-card mb-4 fade-up d1">
      <div class="d-flex align-items-center gap-3">
        <span class="b-avatar"><i data-lucide="<?= e($typeIcon[$ctx['target_type']] ?? 'star') ?>" style="width:16px;height:16px"></i></span>
        <div>
          <div class="text-muted small"><?= e(t('reviews.target_label')) ?> · <?= e(t('reviews.context_' . $ctx['target_type'])) ?></div>
          <div class="fw-bold" style="color:var(--b-forest)"><?= e($ctx['label']) ?></div>
          <div class="text-muted small"><?= e($ctx['sub']) ?></div>
        </div>
      </div>
    </div>

    <form class="b-card fade-up d2" method="post" action="<?= url('/reviews') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="target_type" value="<?= e($ctx['target_type']) ?>">
      <input type="hidden" name="target_id" value="<?= (int) $ctx['target_id'] ?>">
      <input type="hidden" name="order_id" value="<?= (int) ($ctx['order_id'] ?? 0) ?>">
      <input type="hidden" name="booking_id" value="<?= (int) ($ctx['booking_id'] ?? 0) ?>">

      <div class="mb-3">
        <span class="form-label small fw-semibold d-block mb-2"><?= e(t('reviews.rating')) ?> *</span>
        <div class="d-flex gap-2 flex-wrap">
          <?php for ($i = 1; $i <= 5; $i++): ?>
            <input type="radio" class="btn-check" name="rating" id="rate<?= $i ?>" value="<?= $i ?>" <?= $i === 5 ? 'checked' : '' ?>>
            <label class="btn btn-soft b-btn-pill rating-stars" for="rate<?= $i ?>">★ <?= $i ?></label>
          <?php endfor; ?>
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold" for="rv_title"><?= e(t('reviews.title_label')) ?></label>
        <input class="form-control" id="rv_title" name="title" maxlength="150" value="<?= e(old('title')) ?>">
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold" for="rv_body"><?= e(t('reviews.body_label')) ?></label>
        <textarea class="form-control" id="rv_body" name="body" rows="5" maxlength="2000"><?= e(old('body')) ?></textarea>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold"><?= e(t('reviews.photo')) ?></label>
        <input type="file" class="form-control" name="photo" accept="image/*">
      </div>

      <div class="b-note mb-3">
        <i data-lucide="shield-check"></i>
        <span><?= e(t('reviews.verified_note')) ?></span>
      </div>

      <button class="btn btn-brand w-100" type="submit">
        <i data-lucide="star" style="width:15px;height:15px"></i> <?= e(t('reviews.submit')) ?>
      </button>
    </form>
  </div>
</div>
