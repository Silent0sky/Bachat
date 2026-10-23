<?php
/**
 * Resident — ticket thread.
 * Vars: $t (support_tickets row), $messages (ticket_messages + author).
 */
$prioBadge = [
    'urgent' => 'b-badge-red',
    'high'   => 'b-badge-amber',
    'medium' => 'b-badge-blue',
    'low'    => 'b-badge-grey',
];
$isActive = !in_array($t['status'], ['resolved', 'closed'], true);
$overdue  = $isActive && $t['sla_due_at'] && $t['sla_due_at'] < date('Y-m-d H:i:s');
?>
<div class="row justify-content-center">
  <div class="col-12 col-lg-10 col-xl-9">
    <a class="btn btn-soft btn-sm b-btn-pill mb-3 fade-up" href="<?= url('/tickets') ?>">
      <i data-lucide="arrow-left" style="width:15px;height:15px"></i> <?= e(t('common.back')) ?>
    </a>

    <!-- Header -->
    <div class="b-card fade-up d1 mb-4">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
        <div>
          <span class="b-badge-blue"><?= e(t('tickets.ticket_no')) ?> <?= e($t['ticket_no']) ?></span>
          <h1 class="h5 fw-bold mt-2 mb-0" style="color:var(--b-forest)"><?= e($t['subject']) ?></h1>
        </div>
        <span class="<?= status_badge((string) $t['status']) ?>"><?= e(t('tickets.status_' . $t['status'])) ?></span>
      </div>
      <div class="d-flex flex-wrap gap-2 small text-muted">
        <span class="b-badge-grey"><?= e($t['category']) ?></span>
        <span class="<?= $prioBadge[$t['priority']] ?? 'b-badge-grey' ?>"><?= e(t('tickets.prio_' . $t['priority'])) ?></span>
        <span><i data-lucide="calendar" style="width:13px;height:13px"></i> <?= e(t('tickets.created_on')) ?>: <?= e(human_dt($t['created_at'])) ?></span>
        <?php if ($t['sla_due_at'] && $isActive): ?>
          <span class="<?= $overdue ? 'b-badge-red' : 'b-badge-grey' ?>">
            <i data-lucide="timer" style="width:13px;height:13px"></i>
            <?= e(t('tickets.sla_due')) ?>: <?= e(human_dt($t['sla_due_at'])) ?><?= $overdue ? ' · ' . e(t('tickets.overdue')) : '' ?>
          </span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Thread -->
    <div class="mb-4 fade-up d2">
      <?php foreach ($messages as $m): $mine = ((int) $m['is_staff']) === 0; ?>
        <div class="d-flex <?= $mine ? 'justify-content-end' : 'justify-content-start' ?>">
          <div class="bubble <?= $mine ? 'bubble-me' : 'bubble-them' ?>">
            <?php if (!$mine): ?><strong class="d-block small mb-1"><i data-lucide="headset" style="width:13px;height:13px"></i> <?= e(t('tickets.support')) ?> · <?= e($m['author']) ?></strong><?php endif; ?>
            <div style="white-space:pre-line"><?= e($m['message']) ?></div>
            <?php if (!empty($m['attachment'])): ?>
              <a class="d-inline-flex align-items-center gap-1 mt-2 <?= $mine ? 'text-white-50' : 'text-muted' ?>"
                 href="<?= e(upload_url($m['attachment'])) ?>" download style="font-size:12px">
                <i data-lucide="paperclip" style="width:12px;height:12px"></i> <?= e(t('common.download')) ?>
              </a>
            <?php endif; ?>
            <small><?= e(human_dt($m['created_at'])) ?></small>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <?php if ($t['status'] !== 'closed'): ?>
      <!-- Reply -->
      <form class="b-card fade-up d3 mb-4" method="post" action="<?= url('/tickets/' . (int) $t['id'] . '/reply') ?>" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <label class="form-label small fw-semibold" for="tk_reply"><?= e(t('tickets.reply_first')) ?></label>
        <textarea class="form-control mb-2" id="tk_reply" name="message" rows="4" required placeholder="<?= e(t('tickets.reply_placeholder')) ?>"></textarea>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
          <input type="file" class="form-control form-control-sm w-auto" name="attachment" accept="image/*,.pdf,.txt">
          <button class="btn btn-brand b-btn-pill" type="submit">
            <i data-lucide="send" style="width:15px;height:15px"></i> <?= e(t('tickets.reply')) ?>
          </button>
        </div>
      </form>
    <?php endif; ?>

    <?php if ($t['status'] === 'closed'): ?>
      <div class="b-card fade-up d3 mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
          <div class="b-note mb-0"><i data-lucide="lock"></i><span><?= e(t('tickets.closed_locked')) ?></span></div>
          <form method="post" action="<?= url('/tickets/' . (int) $t['id'] . '/reopen') ?>" data-confirm="<?= e(t('tickets.reopen_confirm')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-amber b-btn-pill" type="submit">
              <i data-lucide="rotate-ccw" style="width:15px;height:15px"></i> <?= e(t('tickets.reopen')) ?>
            </button>
          </form>
        </div>
      </div>
    <?php endif; ?>

    <?php if (in_array($t['status'], ['resolved', 'closed'], true)): ?>
      <?php if ($t['feedback_rating']): ?>
        <div class="b-card fade-up d3 text-center">
          <p class="mb-1 fw-semibold"><?= e(t('tickets.rate_us')) ?></p>
          <div class="rating-stars" style="font-size:22px">
            <?= str_repeat('★', (int) $t['feedback_rating']) . str_repeat('☆', 5 - (int) $t['feedback_rating']) ?>
          </div>
          <p class="text-muted small mb-0"><?= e(t('tickets.rated', ['n' => (string) (int) $t['feedback_rating']])) ?></p>
        </div>
      <?php else: ?>
        <form class="b-card fade-up d3 text-center" method="post" action="<?= url('/tickets/' . (int) $t['id'] . '/feedback') ?>">
          <?= csrf_field() ?>
          <p class="fw-semibold mb-3"><?= e(t('tickets.rate_us')) ?></p>
          <div class="d-flex justify-content-center gap-2 flex-wrap mb-3">
            <?php for ($i = 1; $i <= 5; $i++): ?>
              <input type="radio" class="btn-check" name="rating" id="fb<?= $i ?>" value="<?= $i ?>">
              <label class="btn btn-soft b-btn-pill rating-stars" for="fb<?= $i ?>">★ <?= $i ?></label>
            <?php endfor; ?>
          </div>
          <button class="btn btn-brand b-btn-pill" type="submit"><?= e(t('common.submit')) ?></button>
        </form>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
