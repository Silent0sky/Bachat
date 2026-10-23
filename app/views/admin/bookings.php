<?php
/** Society admin: bookings + assign/status. Vars: $bookings, $vendors, $staff, $filter, $counts */
$sname = fn (array $b): string => (string) ($b['name_' . lang()] ?? '') !== '' ? (string) $b['name_' . lang()] : (string) $b['name_en'];
$statuses = ['pending', 'confirmed', 'assigned', 'in_progress', 'completed', 'cancelled', 'rescheduled'];
?>
<div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4">
  <div>
    <h1 class="section-title mb-1"><?= e(t('bookings.admin_title')) ?></h1>
    <p class="text-muted small mb-0"><?= e(t('bookings.admin_sub')) ?></p>
  </div>
  <a class="btn btn-soft btn-sm b-btn-pill" href="<?= url('/admin/service-campaigns') ?>">
    <i data-lucide="megaphone" width="14" height="14"></i> <?= e(t('bookings.campaign_admin_title')) ?>
  </a>
</div>

<!-- ============ Status filter chips ============ -->
<div class="d-flex flex-wrap gap-2 mb-4">
  <a class="chip <?= $filter === '' ? 'chip-active' : '' ?>" href="<?= url('/admin/bookings') ?>">
    <?= e(t('common.all')) ?> (<?= (int) array_sum($counts) ?>)
  </a>
  <?php foreach ($statuses as $st): ?>
    <a class="chip <?= $filter === $st ? 'chip-active' : '' ?>" href="<?= url('/admin/bookings?status=' . $st) ?>">
      <?= e(ucfirst(str_replace('_', ' ', $st))) ?> (<?= (int) ($counts[$st] ?? 0) ?>)
    </a>
  <?php endforeach; ?>
</div>

<?php if (empty($bookings)): ?>
  <div class="empty-state b-card">
    <i data-lucide="calendar-check"></i>
    <p>No bookings<?= $filter !== '' ? ' with status "' . e($filter) . '"' : '' ?>.</p>
  </div>
<?php else: ?>
<div class="b-table">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th><?= e(t('bookings.booking_no')) ?></th>
          <th><?= e(t('bookings.admin_resident')) ?></th>
          <th><?= e(t('bookings.service')) ?></th>
          <th><?= e(t('bookings.slot')) ?></th>
          <th><?= e(t('common.price')) ?></th>
          <th>Assigned</th>
          <th><?= e(t('common.status')) ?></th>
          <th class="text-end"><?= e(t('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bookings as $b):
            $id = (int) $b['id'];
            $canConfirm = $b['status'] === 'pending';
            $canStart   = in_array($b['status'], ['confirmed', 'assigned'], true);
            $canDone    = in_array($b['status'], ['in_progress', 'assigned', 'confirmed'], true);
            $canCancel  = in_array($b['status'], ['pending', 'confirmed', 'assigned', 'in_progress'], true);
            $canAssign  = in_array($b['status'], ['pending', 'confirmed', 'assigned'], true);
            $needAction = $canConfirm || $canStart || $canDone || $canCancel || $canAssign;
            $assignee   = $b['vendor_name'] ?? null;
        ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= e($b['booking_no']) ?></div>
            <div class="small text-muted"><?= e(human_date($b['created_at'])) ?></div>
          </td>
          <td class="small">
            <?= e($b['resident_name'] ?? '—') ?>
            <div class="text-muted"><?= e($b['resident_phone'] ?? '') ?></div>
          </td>
          <td class="small">
            <?= e($sname($b)) ?>
            <?php if (!empty($b['campaign_title'])): ?>
              <div class="text-muted"><?= e(t('bookings.group_deal')) ?>: <?= e($b['campaign_title']) ?></div>
            <?php endif; ?>
          </td>
          <td class="small"><?= e(human_dt($b['slot_at'])) ?></td>
          <td class="fw-semibold"><?= e(money($b['price'])) ?></td>
          <td class="small"><?= $assignee ? e($assignee) : '—' ?></td>
          <td><span class="<?= status_badge($b['status']) ?>"><?= e(ucfirst(str_replace('_', ' ', (string) $b['status']))) ?></span></td>
          <td class="text-end">
            <?php if ($canConfirm): ?>
              <form method="post" action="<?= url('/admin/bookings/' . $id . '/status') ?>" class="d-inline">
                <?= csrf_field() ?><input type="hidden" name="status" value="confirmed">
                <button class="btn btn-brand btn-sm b-btn-pill"><?= e(t('bookings.admin_confirm')) ?></button>
              </form>
            <?php endif; ?>
            <?php if ($canStart): ?>
              <form method="post" action="<?= url('/admin/bookings/' . $id . '/status') ?>" class="d-inline">
                <?= csrf_field() ?><input type="hidden" name="status" value="in_progress">
                <button class="btn btn-forest btn-sm b-btn-pill"><?= e(t('bookings.admin_start')) ?></button>
              </form>
            <?php endif; ?>
            <?php if ($needAction): ?>
              <button class="btn btn-soft btn-sm b-btn-pill" type="button" data-bs-toggle="collapse" data-bs-target="#ab-<?= $id ?>">
                <i data-lucide="more-horizontal" width="14" height="14"></i>
              </button>
            <?php endif; ?>
          </td>
        </tr>

        <?php if ($needAction): ?>
        <tr class="collapse" id="ab-<?= $id ?>">
          <td colspan="8" class="bg-brand-soft">
            <div class="row g-3">
              <div class="col-md-4 small">
                <div class="fw-semibold text-muted"><?= e(t('bookings.address')) ?></div>
                <div><?= e($b['address_text'] ?: '—') ?></div>
                <?php if (!empty($b['notes'])): ?>
                  <div class="mt-1 text-muted"><?= e(t('common.notes')) ?>: <?= e($b['notes']) ?></div>
                <?php endif; ?>
              </div>

              <?php if ($canAssign): ?>
              <div class="col-md-4">
                <div class="small fw-bold text-uppercase text-muted mb-2"><?= e(t('bookings.admin_assign_to')) ?></div>
                <form method="post" action="<?= url('/admin/bookings/' . $id . '/assign') ?>" class="row g-2 align-items-end">
                  <?= csrf_field() ?>
                  <div class="col-6">
                    <select class="form-select form-select-sm" name="assignee_type" required>
                      <option value="vendor"><?= e(t('bookings.admin_assign_vendor')) ?></option>
                      <option value="staff"><?= e(t('bookings.admin_assign_staff')) ?></option>
                    </select>
                  </div>
                  <div class="col-6">
                    <select class="form-select form-select-sm" name="assignee_id" required>
                      <?php foreach ($vendors as $v): ?>
                        <option value="<?= (int) $v['id'] ?>">V: <?= e($v['name']) ?></option>
                      <?php endforeach; ?>
                      <?php foreach ($staff as $sf): ?>
                        <option value="<?= (int) $sf['id'] ?>">S: <?= e($sf['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-12">
                    <button type="submit" class="btn btn-brand btn-sm b-btn-pill">
                      <i data-lucide="user-check" width="14" height="14"></i> <?= e(t('bookings.admin_assign_btn')) ?>
                    </button>
                    <?php if (empty($vendors) && empty($staff)): ?>
                      <span class="small text-danger ms-2"><?= e(t('bookings.admin_no_staff')) ?></span>
                    <?php endif; ?>
                  </div>
                </form>
              </div>
              <?php endif; ?>

              <?php if ($canDone || $canCancel): ?>
              <div class="col-md-<?= $canAssign ? '4' : '8' ?>">
                <div class="small fw-bold text-uppercase text-muted mb-2"><?= e(t('common.actions')) ?></div>
                <?php if ($canDone): ?>
                  <form method="post" action="<?= url('/admin/bookings/' . $id . '/status') ?>" class="d-inline me-2"
                        data-confirm="Mark this booking as completed?">
                    <?= csrf_field() ?><input type="hidden" name="status" value="completed">
                    <button class="btn btn-forest btn-sm b-btn-pill">
                      <i data-lucide="check-circle-2" width="14" height="14"></i> <?= e(t('bookings.admin_complete')) ?>
                    </button>
                  </form>
                <?php endif; ?>
                <?php if ($canCancel): ?>
                  <form method="post" action="<?= url('/admin/bookings/' . $id . '/status') ?>" class="d-inline-flex gap-2 align-items-center"
                        data-confirm="Cancel this booking? The resident will be notified.">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="cancelled">
                    <input type="text" class="form-control form-control-sm" name="reason" maxlength="255"
                           placeholder="<?= e(t('bookings.cancel_reason')) ?>" style="max-width:180px;">
                    <button type="submit" class="btn btn-outline-danger btn-sm b-btn-pill">
                      <i data-lucide="x-circle" width="14" height="14"></i> <?= e(t('bookings.cancel')) ?>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
