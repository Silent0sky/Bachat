<?php
/**
 * Resident — my support tickets.
 * Vars: $tickets, $openCount, $resolvedCount.
 */
$prioBadge = [
    'urgent' => 'b-badge-red',
    'high'   => 'b-badge-amber',
    'medium' => 'b-badge-blue',
    'low'    => 'b-badge-grey',
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4 fade-up">
  <div>
    <h1 class="section-title mb-1"><?= e(t('tickets.my_tickets')) ?></h1>
    <p class="text-muted mb-0"><?= e(t('tickets.my_sub')) ?></p>
  </div>
  <a class="btn btn-brand b-btn-pill" href="<?= url('/tickets/new') ?>">
    <i data-lucide="plus" style="width:15px;height:15px"></i> <?= e(t('tickets.new_ticket')) ?>
  </a>
</div>

<div class="row g-3 mb-4">
  <div class="col-6 col-lg-4">
    <div class="b-card stat-card fade-up">
      <div class="stat-value"><?= (int) $openCount ?></div>
      <div class="stat-label"><?= e(t('tickets.open_count')) ?></div>
      <div class="stat-icon bg-brand-soft"><i data-lucide="life-buoy" style="color:var(--b-green-d)"></i></div>
    </div>
  </div>
  <div class="col-6 col-lg-4">
    <div class="b-card stat-card fade-up d2">
      <div class="stat-value"><?= count($tickets) ?></div>
      <div class="stat-label"><?= e(t('tickets.total')) ?></div>
      <div class="stat-icon bg-brand-soft"><i data-lucide="inbox" style="color:var(--b-green-d)"></i></div>
    </div>
  </div>
  <div class="col-6 col-lg-4 d-none d-lg-block">
    <div class="b-card stat-card fade-up d3">
      <div class="stat-value"><?= (int) $resolvedCount ?></div>
      <div class="stat-label"><?= e(t('tickets.resolved_count')) ?></div>
      <div class="stat-icon bg-brand-soft"><i data-lucide="check-circle-2" style="color:var(--b-green-d)"></i></div>
    </div>
  </div>
</div>

<?php if ($tickets === []): ?>
  <div class="b-card empty-state fade-up">
    <i data-lucide="life-buoy"></i>
    <p class="mt-2 fw-semibold"><?= e(t('tickets.no_tickets')) ?></p>
    <p><?= e(t('tickets.no_tickets_sub')) ?></p>
    <a class="btn btn-brand b-btn-pill mt-2" href="<?= url('/tickets/new') ?>"><?= e(t('tickets.new_ticket')) ?></a>
  </div>
<?php else: ?>
  <div class="b-table fade-up d1">
    <div class="table-responsive">
      <table class="table table-hover align-middle mb-0">
        <thead>
          <tr>
            <th><?= e(t('tickets.ticket_no')) ?></th>
            <th><?= e(t('tickets.subject')) ?></th>
            <th><?= e(t('tickets.category')) ?></th>
            <th><?= e(t('tickets.priority')) ?></th>
            <th><?= e(t('common.status')) ?></th>
            <th><?= e(t('tickets.last_activity')) ?></th>
            <th class="text-end"></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tickets as $tk): ?>
            <tr>
              <td class="fw-semibold"><?= e($tk['ticket_no']) ?></td>
              <td><?= e(mb_substr((string) $tk['subject'], 0, 48)) ?></td>
              <td class="text-muted small"><?= e($tk['category']) ?></td>
              <td><span class="<?= $prioBadge[$tk['priority']] ?? 'b-badge-grey' ?>"><?= e(t('tickets.prio_' . $tk['priority'])) ?></span></td>
              <td><span class="<?= status_badge((string) $tk['status']) ?>"><?= e(t('tickets.status_' . $tk['status'])) ?></span></td>
              <td class="small text-muted"><?= e(human_dt($tk['last_activity'])) ?></td>
              <td class="text-end">
                <a class="btn btn-soft btn-sm b-btn-pill" href="<?= url('/tickets/' . (int) $tk['id']) ?>">
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
