<?php
/**
 * Single notice (resident) with read receipt recorded by the controller.
 * Vars: $notice (announcement row + author).
 */
?>
<div class="row justify-content-center">
  <div class="col-12 col-lg-9 col-xl-8">
    <a class="btn btn-soft btn-sm b-btn-pill mb-3 fade-up" href="<?= url('/notices') ?>">
      <i data-lucide="arrow-left" style="width:15px;height:15px"></i> <?= e(t('notices.back_to_notices')) ?>
    </a>

    <article class="b-card fade-up d1">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <?php if ((int) $notice['is_pinned'] === 1): ?>
            <span class="b-badge-amber"><i data-lucide="pin" style="width:13px;height:13px"></i> <?= e(t('notices.pinned')) ?></span>
          <?php endif; ?>
          <span class="b-badge-grey"><i data-lucide="calendar" style="width:13px;height:13px"></i> <?= e(human_dt($notice['publish_at'] ?: $notice['created_at'])) ?></span>
          <?php if (!empty($notice['author'])): ?>
            <span class="b-badge-blue"><i data-lucide="user" style="width:13px;height:13px"></i> <?= e(t('notices.author', ['name' => $notice['author']])) ?></span>
          <?php endif; ?>
        </div>
      </div>

      <h1 class="h3 fw-bold mb-3" style="color:var(--b-forest)"><?= e($notice['title']) ?></h1>

      <?php if (!empty($notice['image'])): ?>
        <img class="img-fluid rounded-3 mb-3 w-100" style="max-height:420px;object-fit:cover"
             src="<?= e(upload_url($notice['image'])) ?>" alt="<?= e($notice['title']) ?>">
      <?php endif; ?>

      <div class="text-body" style="line-height:1.75;white-space:pre-line"><?= e($notice['body']) ?></div>

      <?php if (!empty($notice['attachment'])): ?>
        <div class="b-note mt-4">
          <i data-lucide="paperclip"></i>
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 w-100">
            <span><?= e(t('notices.attachment')) ?></span>
            <a class="btn btn-soft btn-sm b-btn-pill" href="<?= e(upload_url($notice['attachment'])) ?>" download>
              <i data-lucide="download" style="width:14px;height:14px"></i> <?= e(t('notices.attachment_download')) ?>
            </a>
          </div>
        </div>
      <?php endif; ?>
    </article>
  </div>
</div>
