<?php
/** Deliveries. Vars: $queue, $tasks, $staff */
$staffAvailable = $staff !== [];
?>
<div class="mb-4">
  <h1 class="section-title mb-1">Deliveries</h1>
  <p class="text-muted small mb-0">Assign confirmed orders to your delivery staff. The OTP is shown here — share it with the resident; staff confirm handover with it.</p>
</div>

<div class="b-card mb-4 fade-up">
  <h2 class="h6 fw-bold text-brand mb-3">Delivery queue <span class="text-muted small fw-normal">(confirmed / packed / ready · home delivery)</span></h2>
  <?php if ($queue === []): ?>
    <div class="empty-state"><i data-lucide="bike"></i><p>No delivery orders waiting right now.</p></div>
  <?php elseif (!$staffAvailable): ?>
    <div class="b-note mb-3"><i data-lucide="alert-triangle"></i><span>You have no active delivery staff in your society. Add staff accounts (role: delivery staff) from the platform admin or ask them to join.</span></div>
    <div class="b-table">
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead><tr><th>Order</th><th>Resident</th><th>Address</th><th>Status</th></tr></thead>
          <tbody>
            <?php foreach ($queue as $q): ?>
              <tr>
                <td class="small fw-semibold"><?= e($q['order_no']) ?></td>
                <td class="small"><?= e($q['user_name']) ?><div class="text-muted"><?= e($q['contact_phone'] ?? '') ?></div></td>
                <td class="small text-muted"><?= e($q['address_text'] ?? '—') ?></td>
                <td><span class="<?= e(status_badge($q['status'])) ?>"><?= e(str_replace('_', ' ', $q['status'])) ?></span></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php else: ?>
    <div class="b-table">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead><tr><th>Order</th><th>Resident</th><th>Address</th><th>Slot</th><th>Assigned to</th><th class="text-end">Assign / Reassign</th></tr></thead>
          <tbody>
            <?php foreach ($queue as $q): ?>
              <tr>
                <td>
                  <div class="fw-semibold small"><?= e($q['order_no']) ?></div>
                  <span class="<?= e(status_badge($q['status'])) ?>" style="font-size:10.5px"><?= e(str_replace('_', ' ', $q['status'])) ?></span>
                </td>
                <td class="small"><?= e($q['user_name']) ?><div class="text-muted"><?= e($q['contact_phone'] ?? '') ?></div></td>
                <td class="small text-muted" style="max-width:220px"><?= e($q['address_text'] ?? '—') ?></td>
                <td class="small text-muted"><?= $q['slot_date'] ? e(human_date($q['slot_date'])) : '—' ?><?= $q['slot_label'] ? '<div>' . e($q['slot_label']) . '</div>' : '' ?></td>
                <td class="small">
                  <?php if ($q['task_id']): ?>
                    <span class="fw-semibold text-brand"><?= e($q['staff_name']) ?></span>
                    <div class="text-muted">task: <?= e(str_replace('_', ' ', $q['task_status'])) ?> · OTP <?= e($q['otp']) ?></div>
                  <?php else: ?>
                    <span class="text-muted">unassigned</span>
                  <?php endif; ?>
                </td>
                <td class="text-end">
                  <form method="post" action="<?= url('/admin/deliveries/assign') ?>" class="d-flex gap-1 justify-content-end">
                    <?= csrf_field() ?>
                    <input type="hidden" name="order_id" value="<?= (int) $q['id'] ?>">
                    <select class="form-select form-select-sm" name="staff_id" style="width:150px" required>
                      <option value="">staff…</option>
                      <?php foreach ($staff as $st): ?>
                        <option value="<?= (int) $st['id'] ?>" <?= (int) ($q['assigned_to'] ?? 0) === (int) $st['id'] ? 'selected' : '' ?>><?= e($st['name']) ?></option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn btn-sm btn-brand"><?= $q['task_id'] ? 'Reassign' : 'Assign' ?></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>

<div class="b-card fade-up d1">
  <h2 class="h6 fw-bold text-brand mb-3">Recent delivery tasks</h2>
  <?php if ($tasks === []): ?>
    <div class="empty-state"><i data-lucide="clipboard-list"></i><p>No delivery tasks yet.</p></div>
  <?php else: ?>
    <div class="b-table">
      <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
          <thead><tr><th>Order</th><th>Staff</th><th>OTP</th><th>Status</th><th>Created</th><th>Delivered</th></tr></thead>
          <tbody>
            <?php foreach ($tasks as $t): ?>
              <tr>
                <td class="small fw-semibold"><?= e($t['order_no']) ?><div class="text-muted">order: <?= e(str_replace('_', ' ', $t['order_status'])) ?></div></td>
                <td class="small"><?= e($t['staff_name']) ?></td>
                <td class="small fw-bold text-brand"><?= e($t['otp'] ?? '—') ?></td>
                <td><span class="<?= e(status_badge($t['status'])) ?>"><?= e(str_replace('_', ' ', $t['status'])) ?></span>
                  <?php if ($t['status'] === 'failed' && $t['fail_reason']): ?><div class="small text-muted"><?= e($t['fail_reason']) ?></div><?php endif; ?>
                </td>
                <td class="small text-muted"><?= e(human_dt($t['created_at'])) ?></td>
                <td class="small text-muted"><?= e(human_dt($t['delivered_at'])) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>
</div>
