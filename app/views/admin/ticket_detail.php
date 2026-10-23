<?php
/**
 * Admin — ticket detail: thread, reply, status, assignment.
 * Vars: $t (ticket), $messages, $assignees, $requester, $societyName, $assigneeName, $isSuper, $statuses.
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
<a class="btn btn-soft btn-sm b-btn-pill mb-3 fade-up" href="<?= url($isSuper ? '/super/tickets' : '/admin/tickets') ?>">
  <i data-lucide="arrow-left" style="width:15px;height:15px"></i> <?= e(t('common.back')) ?>
</a>

<div class="row g-4">
  <!-- Thread + reply -->
  <div class="col-12 col-lg-8">
    <div class="b-card fade-up d1 mb-4">
      <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-3">
        <div>
          <span class="b-badge-blue"><?= e(t('tickets.ticket_no')) ?> <?= e($t['ticket_no']) ?></span>
          <h1 class="h5 fw-bold mt-2 mb-0" style="color:var(--b-forest)"><?= e($t['subject']) ?></h1>
        </div>
        <span class="<?= status_badge((string) $t['status']) ?>"><?= e(t('tickets.status_' . $t['status'])) ?></span>
      </div>
      <div class="d-flex flex-wrap gap-2 small text-muted">
        <span class="b-badge-grey"><?= e($t['category']) ?></span>
        <span class="<?= $prioBadge[$t['priority']] ?? 'b-badge-grey' ?>"><?= e(t('tickets.prio_' . $t['priority'])) ?></span>
        <?php if ($t['sla_due_at'] && $isActive): ?>
          <span class="<?= $overdue ? 'b-badge-red' : 'b-badge-grey' ?>">
            <i data-lucide="timer" style="width:13px;height:13px"></i>
            <?= e(t('tickets.sla_due')) ?>: <?= e(human_dt($t['sla_due_at'])) ?><?= $overdue ? ' · ' . e(t('tickets.overdue')) : '' ?>
          </span>
        <?php endif; ?>
      </div>
    </div>

    <div class="mb-4 fade-up d2">
      <?php foreach ($messages as $m): $staff = ((int) $m['is_staff']) === 1; ?>
        <div class="d-flex <?= $staff ? 'justify-content-end' : 'justify-content-start' ?>">
          <div class="bubble <?= $staff ? 'bubble-me' : 'bubble-them' ?>">
            <strong class="d-block small mb-1">
              <?= $staff ? '<i data-lucide="headset" style="width:13px;height:13px"></i> ' . e(t('tickets.support')) : e($m['author']) ?>
              · <?= e($m['author']) ?>
            </strong>
            <div style="white-space:pre-line"><?= e($m['message']) ?></div>
            <?php if (!empty($m['attachment'])): ?>
              <a class="d-inline-flex align-items-center gap-1 mt-2 <?= $staff ? 'text-white-50' : 'text-muted' ?>"
                 href="<?= e(upload_url($m['attachment'])) ?>" download style="font-size:12px">
                <i data-lucide="paperclip" style="width:12px;height:12px"></i> <?= e(t('common.download')) ?>
              </a>
            <?php endif; ?>
            <small><?= e(human_dt($m['created_at'])) ?></small>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <form class="b-card fade-up d3" method="post" action="<?= url('/admin/tickets/' . (int) $t['id'] . '/reply') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <label class="form-label small fw-semibold" for="st_reply"><?= e(t('tickets.reply_first')) ?></label>
      <textarea class="form-control mb-2" id="st_reply" name="message" rows="4" required placeholder="<?= e(t('tickets.reply_placeholder')) ?>"></textarea>
      <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
        <input type="file" class="form-control form-control-sm w-auto" name="attachment" accept="image/*,.pdf,.txt">
        <button class="btn btn-brand b-btn-pill" type="submit">
          <i data-lucide="send" style="width:15px;height:15px"></i> <?= e(t('tickets.reply')) ?>
        </button>
      </div>
    </form>
  </div>

  <!-- Side controls -->
  <div class="col-12 col-lg-4">
    <div class="b-card fade-up d1 mb-3">
      <h2 class="h6 fw-bold mb-3" style="color:var(--b-forest)"><?= e(t('tickets.details')) ?></h2>
      <dl class="mb-0 small">
        <div class="d-flex justify-content-between py-1 border-bottom">
          <dt class="fw-normal text-muted"><?= e(t('tickets.requester')) ?></dt>
          <dd class="mb-0 fw-semibold"><?= e($requester['name'] ?? '—') ?></dd>
        </div>
        <div class="d-flex justify-content-between py-1 border-bottom">
          <dt class="fw-normal text-muted"><?= e(t('common.email')) ?></dt>
          <dd class="mb-0"><?= e($requester['email'] ?? '—') ?></dd>
        </div>
        <div class="d-flex justify-content-between py-1 border-bottom">
          <dt class="fw-normal text-muted"><?= e(t('notices.society')) ?></dt>
          <dd class="mb-0"><?= $t['society_id'] ? e($societyName ?? '') : '<span class="text-muted">—</span>' ?></dd>
        </div>
        <div class="d-flex justify-content-between py-1 border-bottom">
          <dt class="fw-normal text-muted"><?= e(t('tickets.assigned_to')) ?></dt>
          <dd class="mb-0"><?= $t['assigned_to'] ? e($assigneeName ?? '—') : '<span class="text-muted">' . e(t('tickets.unassigned')) . '</span>' ?></dd>
        </div>
        <div class="d-flex justify-content-between py-1 border-bottom">
          <dt class="fw-normal text-muted"><?= e(t('tickets.created_on')) ?></dt>
          <dd class="mb-0"><?= e(human_dt($t['created_at'])) ?></dd>
        </div>
        <?php if ($t['feedback_rating']): ?>
          <div class="d-flex justify-content-between py-1">
            <dt class="fw-normal text-muted"><?= e(t('tickets.rate_us')) ?></dt>
            <dd class="mb-0 rating-stars"><?= str_repeat('★', (int) $t['feedback_rating']) ?></dd>
          </div>
        <?php endif; ?>
      </dl>
    </div>

    <div class="b-card fade-up d2 mb-3">
      <h2 class="h6 fw-bold mb-3" style="color:var(--b-forest)"><?= e(t('common.status')) ?></h2>
      <form method="post" action="<?= url('/admin/tickets/' . (int) $t['id'] . '/status') ?>">
        <?= csrf_field() ?>
        <div class="d-flex flex-wrap gap-2">
          <?php foreach ($statuses as $s): ?>
            <button class="btn btn-sm <?= $t['status'] === $s ? 'btn-forest' : 'btn-soft' ?> b-btn-pill" type="submit" name="status" value="<?= e($s) ?>">
              <?= e(t('tickets.status_' . $s)) ?>
            </button>
          <?php endforeach; ?>
        </div>
      </form>
    </div>

    <div class="b-card fade-up d3">
      <h2 class="h6 fw-bold mb-3" style="color:var(--b-forest)"><?= e(t('tickets.assign_to')) ?></h2>
      <form method="post" action="<?= url('/admin/tickets/' . (int) $t['id'] . '/assign') ?>">
        <?= csrf_field() ?>
        <select class="form-select mb-2" name="assigned_to" required>
          <option value=""><?= e(t('tickets.assign_to')) ?>…</option>
          <?php foreach ($assignees as $a): ?>
            <option value="<?= (int) $a['id'] ?>" <?= (int) $t['assigned_to'] === (int) $a['id'] ? 'selected' : '' ?>>
              <?= e($a['name']) ?> (<?= e(t('role.' . $a['role'])) ?>)
            </option>
          <?php endforeach; ?>
        </select>
        <button class="btn btn-outline-brand w-100" type="submit">
          <i data-lucide="user-check" style="width:15px;height:15px"></i> <?= e(t('tickets.assign_to')) ?>
        </button>
      </form>
    </div>
  </div>
</div>
