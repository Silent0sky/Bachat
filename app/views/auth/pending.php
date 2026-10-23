<?php
/**
 * Membership pending/rejected status (Agent A).
 * Vars: $m — latest membership row with society fields.
 */
$status     = (string) $m['status'];
$badgeClass = status_badge($status);
?>
<div class="row justify-content-center py-4">
  <div class="col-12 col-md-8 col-lg-6">
    <div class="b-card text-center fade-up">
      <?php if (!empty($m['society_logo'])): ?>
        <img src="<?= e(upload_url($m['society_logo'])) ?>" alt="" width="64" height="64" class="rounded-3 mb-2" style="object-fit:cover;">
      <?php else: ?>
        <i data-lucide="building-2" style="width:48px;height:48px;color:var(--b-green);" class="mb-2"></i>
      <?php endif; ?>

      <h1 class="section-title d-block"><?= e(t('auth.pending_title')) ?></h1>
      <p class="fw-bold mb-1"><?= e($m['society_name']) ?><?= $m['society_city'] ? ' · ' . e($m['society_city']) : '' ?></p>
      <span class="<?= e($badgeClass) ?> mb-3"><?= e(ucfirst($status)) ?></span>

      <?php if ($status === 'pending'): ?>
        <p class="text-muted small mt-3 mb-1"><?= e(t('auth.pending_review')) ?></p>
        <p class="text-muted small mb-0"><?= e(t('auth.pending_approved_note')) ?></p>
      <?php elseif ($status === 'rejected'): ?>
        <p class="text-muted small mt-3 mb-0"><?= e(t('auth.pending_rejected')) ?></p>
      <?php elseif ($status === 'removed'): ?>
        <p class="text-muted small mt-3 mb-0"><?= e(t('auth.pending_removed')) ?></p>
      <?php endif; ?>

      <div class="b-note mt-3 text-start justify-content-center">
        <i data-lucide="phone"></i>
        <div>
          <strong><?= e(t('auth.pending_contact')) ?></strong>
          <div class="small">
            <?= $m['contact_phone'] ? e($m['contact_phone']) : '—' ?>
            <?= $m['contact_email'] ? ' · ' . e($m['contact_email']) : '' ?>
          </div>
        </div>
      </div>

      <p class="small text-muted mt-3 mb-0"><?= e(t('auth.requested_on')) ?>: <?= e(human_dt($m['requested_at'])) ?></p>

      <div class="d-flex gap-2 justify-content-center mt-3">
        <a href="<?= url('/join/pending') ?>" class="btn btn-soft b-btn-pill">
          <i data-lucide="refresh-cw" class="me-1"></i><?= e(t('auth.refresh')) ?>
        </a>
        <form method="post" action="<?= url('/logout') ?>">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-outline-brand b-btn-pill">
            <i data-lucide="log-out" class="me-1"></i><?= e(t('nav.logout')) ?>
          </button>
        </form>
      </div>
    </div>
  </div>
</div>
