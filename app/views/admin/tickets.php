<?php
/**
 * Admin — support ticket queue.
 * Vars: $tickets, $isSuper, $status, $priority, $openCount, $overdue, $statuses, $priorities.
 */
$prioBadge = [
    'urgent' => 'b-badge-red',
    'high'   => 'b-badge-amber',
    'medium' => 'b-badge-blue',
    'low'    => 'b-badge-grey',
];
$now = date('Y-m-d H:i:s');
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4 fade-up">
  <div>
    <h1 class="section-title mb-1"><?= e(t('tickets.staff_console')) ?></h1>
    <p class="text-muted mb-0"><?= e(t('tickets.my_sub')) ?></p>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-3">
    <div class="b-card stat-card fade-up">
      <div class="stat-value"><?= (int) $openCount ?></div>
      <div class="stat-label"><?= e(t('tickets.open_count')) ?></div>
      <div class="stat-icon bg-brand-soft"><i data-lucide="life-buoy" style="color:var(--b-green-d)"></i></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="b-card stat-card fade-up d2 <?= $overdue > 0 ? 'border-danger-subtle' : '' ?>">
      <div class="stat-value" style="<?= $overdue > 0 ? 'color:var(--b-red,#C0392B)' : '' ?>"><?= (int) $overdue ?></div>
      <div class="stat-label"><?= e(t('tickets.overdue_count')) ?></div>
      <div class="stat-icon bg-brand-soft"><i data-lucide="alarm-clock" style="color:var(--b-green-d)"></i></div>
    </div>
  </div>
  <div class="col-6 col-lg-3">
    <div class="b-card stat-card fade-up d3">
      <div class="stat-value"><?= count($tickets) ?></div>
      <div class="stat-label"><?= e(t('tickets.total')) ?></div>
      <div class="stat-icon bg-brand-soft"><i data-lucide="inbox" style="color:var(--b-green-d)"></i></div>
    </div>
  </div>
</div>

<form class="b-card mb-4 fade-up d1" method="get" action="<?= url($isSuper ? '/super/tickets' : '/admin/tickets') ?>">
  <div class="row g-2 align-items-end">
    <div class="col-6 col-md-3">
      <label class="form-label small fw-semibold"><?= e(t('tickets.filter_status')) ?></label>
      <select class="form-select" name="status">
        <option value=""><?= e(t('common.all')) ?></option>
        <?php foreach ($statuses as $s): ?>
          <option value="<?= e($s) ?>" <?= $status === $s ? 'selected' : '' ?>><?= e(t('tickets.status_' . $s)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label small fw-semibold"><?= e(t('tickets.filter_priority')) ?></label>
      <select class="form-select" name="priority">
        <option value=""><?= e(t('common.all')) ?></option>
        <?php foreach ($priorities as $p): ?>
          <option value="<?= e($p) ?>" <?= $priority === $p ? 'selected' : '' ?>><?= e(t('tickets.prio_' . $p)) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-3">
      <button class="btn btn-soft w-100" type="submit"><i data-lucide="filter" style="width:14px;height:14px"></i> <?= e(t('common.filter')) ?></button>
    </div>
  </div>
</form>

<?php if ($tickets === []): ?>
  <div class="b-card empty-state fade-up">
    <i data-lucide="life-buoy"></i>
    <p class="mt-2"><?= e(t('tickets.no_tickets')) ?></p>
  </div>
<?php else: ?>
  <div class="b-table fade-up d2">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th><?= e(t('tickets.ticket_no')) ?></th>
            <th><?= e(t('tickets.subject')) ?></th>
            <th><?= e(t('tickets.requester')) ?></th>
            <?php if ($isSuper): ?><th><?= e(t('notices.society')) ?></th><?php endif; ?>
            <th><?= e(t('tickets.priority')) ?></th>
            <th><?= e(t('common.status')) ?></th>
            <th><?= e(t('tickets.sla_due')) ?></th>
            <th><?= e(t('tickets.assigned_to')) ?></th>
            <th class="text-end"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tickets as $tk):
              $active = !in_array($tk['status'], ['resolved', 'closed'], true);
              $late   = $active && $tk['sla_due_at'] && $tk['sla_due_at'] < $now;
          ?>
            <tr>
              <td class="fw-semibold"><?= e($tk['ticket_no']) ?></td>
              <td>
                <div class="fw-semibold"><?= e(mb_substr((string) $tk['subject'], 0, 40)) ?></div>
                <div class="text-muted small"><?= e($tk['category']) ?> · <?= e(t('tickets.last_activity')) ?> <?= e(human_dt($tk['last_activity'])) ?></div>
              </td>
              <td class="small"><?= e($tk['requester']) ?></td>
              <?php if ($isSuper): ?>
                <td class="small"><?= $tk['society_id'] ? e((string) $tk['society_name']) : '<span class="text-muted">—</span>' ?></td>
              <?php endif; ?>
              <td><span class="<?= $prioBadge[$tk['priority']] ?? 'b-badge-grey' ?>"><?= e(t('tickets.prio_' . $tk['priority'])) ?></span></td>
              <td><span class="<?= status_badge((string) $tk['status']) ?>"><?= e(t('tickets.status_' . $tk['status'])) ?></span></td>
              <td class="small <?= $late ? 'text-danger fw-semibold' : 'text-muted' ?>">
                <?= $tk['sla_due_at'] ? e(human_dt($tk['sla_due_at'])) : '—' ?><?= $late ? ' · ' . e(t('tickets.overdue')) : '' ?>
              </td>
              <td class="small"><?= $tk['assignee'] ? e((string) $tk['assignee']) : '<span class="text-muted">' . e(t('tickets.unassigned')) . '</span>' ?></td>
              <td class="text-end">
                <a class="btn btn-soft btn-sm b-btn-pill" href="<?= url('/admin/tickets/' . (int) $tk['id']) ?>">
                  <?= e(t('common.view')) ?> <i data-lucide="chevron-right" style="width:14px;height:14px"></i>
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
<?php endif; ?>
