<?php
/** Members & approvals. Vars: $tab, $rows, $counts, $flats */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
  <div>
    <h1 class="section-title mb-1">Members &amp; Approvals</h1>
    <p class="text-muted small mb-0">Approve join requests and manage your resident directory.</p>
  </div>
  <button class="btn btn-brand b-btn-pill" data-bs-toggle="collapse" data-bs-target="#addResident" aria-expanded="false">
    <i data-lucide="user-plus" class="me-1"></i> Add resident directly
  </button>
</div>

<div class="collapse mb-3" id="addResident">
  <div class="b-card fade-up">
    <h2 class="h6 fw-bold text-brand mb-1">Add a resident directly</h2>
    <p class="small text-muted">Creates the user account (with a temporary password) and an <strong>active</strong> membership in one step.</p>
    <form method="post" action="<?= url('/admin/residents') ?>" class="row g-3">
      <?= csrf_field() ?>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Full name *</label>
        <input class="form-control" name="name" required maxlength="120" value="<?= e(old('name')) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Email *</label>
        <input class="form-control" type="email" name="email" required maxlength="150" value="<?= e(old('email')) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Phone</label>
        <input class="form-control" name="phone" maxlength="20" value="<?= e(old('phone')) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Flat</label>
        <select class="form-select" name="flat_id">
          <option value="">— none —</option>
          <?php foreach ($flats as $f): ?>
            <option value="<?= (int) $f['id'] ?>" <?= (string) old('flat_id') === (string) $f['id'] ? 'selected' : '' ?>><?= e(($f['block'] !== '' ? $f['block'] . '-' : '') . $f['flat_number']) ?><?= $f['flat_type'] ? ' · ' . e($f['flat_type']) : '' ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-4">
        <label class="form-label fw-semibold">Member as</label>
        <select class="form-select" name="member_role">
          <option value="owner">Owner</option>
          <option value="tenant">Tenant</option>
        </select>
      </div>
      <div class="col-6 col-md-4">
        <label class="form-label fw-semibold">Temp password <span class="text-muted small">(auto-shown after save)</span></label>
        <input class="form-control" type="text" name="password" required minlength="6" autocomplete="new-password" value="Bch<?= random_int(100000, 999999) ?>">
      </div>
      <div class="col-12">
        <button class="btn btn-brand b-btn-pill">Create resident</button>
      </div>
    </form>
  </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-3">
  <a class="chip <?= $tab === 'pending' ? 'chip-active' : '' ?>" href="<?= url('/admin/members?tab=pending') ?>">
    Pending <span class="b-badge-amber ms-1"><?= (int) $counts['pending'] ?></span>
  </a>
  <a class="chip <?= $tab === 'active' ? 'chip-active' : '' ?>" href="<?= url('/admin/members?tab=active') ?>">
    Active <span class="b-badge-green ms-1"><?= (int) $counts['active'] ?></span>
  </a>
</div>

<?php if ($rows === []): ?>
  <div class="b-card"><div class="empty-state"><i data-lucide="users"></i>
    <p><?= $tab === 'pending' ? 'No pending requests right now.' : 'No active members yet — approve requests or add residents directly.' ?></p>
  </div></div>
<?php else: ?>
<div class="b-table fade-up">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>Resident</th><th>Flat</th><th>Member as</th><th><?= $tab === 'pending' ? 'Requested' : 'Since' ?></th><th>Status</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($rows as $m): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= e($m['user_name']) ?></div>
              <div class="small text-muted"><?= e($m['user_email']) ?><?= $m['user_phone'] ? ' · ' . e($m['user_phone']) : '' ?></div>
            </td>
            <td class="small"><?= $m['flat_number'] !== null ? e(($m['block'] !== null && $m['block'] !== '' ? $m['block'] . '-' : '') . $m['flat_number']) . ($m['flat_type'] ? ' <div class="text-muted">' . e($m['flat_type']) . '</div>' : '') : '<span class="text-muted">—</span>' ?></td>
            <td class="small"><?= e(ucfirst($m['member_role'])) ?></td>
            <td class="small text-muted"><?= e(human_dt($m['decided_at'] ?? $m['requested_at'])) ?></td>
            <td><span class="<?= e(status_badge($m['status'])) ?>"><?= e($m['status']) ?></span></td>
            <td class="text-end">
              <?php if ($m['status'] === 'pending'): ?>
                <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                  <form method="post" action="<?= url('/admin/memberships/' . $m['id'] . '/approve') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-brand">Approve</button>
                  </form>
                  <form method="post" action="<?= url('/admin/memberships/' . $m['id'] . '/reject') ?>" class="d-flex gap-1" data-confirm="Reject this membership request?">
                    <?= csrf_field() ?>
                    <input class="form-control form-control-sm" name="note" placeholder="note (optional)" maxlength="120" style="width:130px">
                    <button class="btn btn-sm btn-outline-brand text-danger">Reject</button>
                  </form>
                </div>
              <?php else: ?>
                <form method="post" action="<?= url('/admin/memberships/' . $m['id'] . '/remove') ?>" data-confirm="Remove <?= e($m['user_name']) ?> from the society? They will lose access immediately.">
                  <?= csrf_field() ?>
                  <button class="btn btn-sm btn-outline-brand text-danger">Remove</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
