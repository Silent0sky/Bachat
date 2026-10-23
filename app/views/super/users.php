<?php
/** Users & roles. Vars: $users, $societies, $fRole, $fSociety, $fQ, $edit (null|array) */
$roles = ['super_admin' => 'Super admin', 'society_admin' => 'Society admin', 'resident' => 'Resident', 'vendor' => 'Vendor', 'delivery_staff' => 'Delivery staff'];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="section-title mb-1">Users &amp; Roles</h1>
    <p class="text-muted small mb-0">All accounts on the platform — admins, residents, vendors and delivery staff.</p>
  </div>
  <?php if ($edit === null): ?>
    <a class="btn btn-brand b-btn-pill" href="<?= url('/super/users?edit=new') ?>"><i data-lucide="plus" class="me-1"></i> Add user</a>
  <?php else: ?>
    <a class="btn btn-soft b-btn-pill" href="<?= url('/super/users') ?>"><i data-lucide="x" class="me-1"></i> Close form</a>
  <?php endif; ?>
</div>

<?php if ($edit !== null): ?>
<div class="b-card mb-4 fade-up" id="user-form">
  <h2 class="h6 fw-bold text-brand mb-3"><?= $edit['id'] === null ? 'Create a user' : 'Edit user #' . (int) $edit['id'] . ' — ' . e($edit['name']) ?></h2>
  <form method="post" action="<?= url('/super/users') ?>" class="row g-3">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <div class="col-md-4">
      <label class="form-label fw-semibold">Full name *</label>
      <input class="form-control" name="name" required maxlength="120" value="<?= e(old('name', $edit['name'])) ?>">
    </div>
    <?php if ($edit['id'] === null): ?>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Email * <span class="text-muted small">(login id)</span></label>
        <input class="form-control" type="email" name="email" required maxlength="150" value="<?= e(old('email')) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Password * <span class="text-muted small">(min 6 chars)</span></label>
        <input class="form-control" type="text" name="password" required minlength="6" autocomplete="new-password">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Role *</label>
        <select class="form-select" name="role" id="roleSel" onchange="document.getElementById('socWrap').style.display = ['society_admin','resident','delivery_staff'].includes(this.value) ? '' : 'none'">
          <?php foreach ($roles as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= (string) old('role', $edit['role']) === $k ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php else: ?>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Email</label>
        <input class="form-control" value="<?= e($edit['email']) ?>" disabled>
        <div class="form-text">Email and role can't be changed here — create a new user if needed.</div>
      </div>
    <?php endif; ?>
    <div class="col-md-4" id="socWrap" <?= $edit['id'] !== null || in_array((string) old('role', $edit['role']), ['society_admin', 'resident', 'delivery_staff'], true) ? '' : 'style="display:none"' ?>>
      <label class="form-label fw-semibold">Society <span class="text-muted small">(required for most roles)</span></label>
      <select class="form-select" name="society_id">
        <option value="">— none / platform —</option>
        <?php foreach ($societies as $s): ?>
          <option value="<?= (int) $s['id'] ?>" <?= (string) (old('society_id', $edit['society_id']) ?? '') === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label fw-semibold">Phone</label>
      <input class="form-control" name="phone" maxlength="20" value="<?= e(old('phone', $edit['phone'])) ?>">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold">Status</label>
      <select class="form-select" name="status">
        <?php foreach (['active' => 'Active', 'pending' => 'Pending', 'suspended' => 'Suspended'] as $k => $lbl): ?>
          <option value="<?= $k ?>" <?= (string) old('status', $edit['status']) === $k ? 'selected' : '' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold">Language</label>
      <select class="form-select" name="language">
        <option value="en" <?= (string) old('language', $edit['language']) === 'en' ? 'selected' : '' ?>>English</option>
        <option value="hi" <?= (string) old('language', $edit['language']) === 'hi' ? 'selected' : '' ?>>हिन्दी</option>
        <option value="mr" <?= (string) old('language', $edit['language']) === 'mr' ? 'selected' : '' ?>>मराठी</option>
      </select>
    </div>
    <div class="col-12 d-flex gap-2">
      <button class="btn btn-brand b-btn-pill"><?= $edit['id'] === null ? 'Create user' : 'Save changes' ?></button>
      <a class="btn btn-soft b-btn-pill" href="<?= url('/super/users') ?>">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>

<div class="b-card mb-3 fade-up">
  <form method="get" action="<?= url('/super/users') ?>" class="row g-2 align-items-end">
    <div class="col-12 col-md-3">
      <div class="input-icon">
        <i data-lucide="search"></i>
        <input class="form-control" name="q" placeholder="Name, email or phone…" value="<?= e($fQ) ?>">
      </div>
    </div>
    <div class="col-6 col-md-3">
      <select class="form-select" name="role">
        <option value="">All roles</option>
        <?php foreach ($roles as $k => $lbl): ?>
          <option value="<?= $k ?>" <?= $fRole === $k ? 'selected' : '' ?>><?= $lbl ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <select class="form-select" name="society">
        <option value="">All societies</option>
        <?php foreach ($societies as $s): ?>
          <option value="<?= (int) $s['id'] ?>" <?= (string) $fSociety === (string) $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-3 d-flex gap-2">
      <button class="btn btn-forest flex-fill">Filter</button>
      <a class="btn btn-soft" href="<?= url('/super/users') ?>">Reset</a>
    </div>
  </form>
</div>

<?php if ($users === []): ?>
  <div class="b-card"><div class="empty-state"><i data-lucide="users"></i><p>No users match these filters.</p></div></div>
<?php else: ?>
<div class="b-table fade-up">
  <div class="table-responsive">
    <table class="table table-hover align-middle mb-0">
      <thead><tr><th>User</th><th>Role</th><th>Society</th><th>Status</th><th>Last login</th><th class="text-end">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= e($u['name']) ?></div>
              <div class="small text-muted"><?= e($u['email']) ?><?= $u['phone'] ? ' · ' . e($u['phone']) : '' ?></div>
            </td>
            <td><span class="<?= $u['role'] === 'super_admin' ? 'b-badge-red' : ($u['role'] === 'society_admin' ? 'b-badge-blue' : 'b-badge-grey') ?>"><?= e($roles[$u['role']] ?? $u['role']) ?></span></td>
            <td class="small"><?= e($u['society_name'] ?? '—') ?></td>
            <td><span class="<?= e(status_badge($u['status'])) ?>"><?= e($u['status']) ?></span></td>
            <td class="small text-muted"><?= e(human_dt($u['last_login_at'])) ?></td>
            <td class="text-end">
              <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                <a class="btn btn-sm btn-soft" href="<?= url('/super/users?edit=' . $u['id']) ?>">Edit</a>
                <?php if ((int) $u['id'] !== (int) auth_user()['id']): ?>
                  <form method="post" action="<?= url('/super/users/' . $u['id'] . '/status') ?>"
                        data-confirm="<?= $u['status'] === 'active' ? 'Suspend this user?' : 'Re-activate this user?' ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="status" value="<?= $u['status'] === 'active' ? 'suspended' : 'active' ?>">
                    <button class="btn btn-sm <?= $u['status'] === 'active' ? 'btn-outline-brand' : 'btn-brand' ?>">
                      <?= $u['status'] === 'active' ? 'Suspend' : 'Activate' ?>
                    </button>
                  </form>
                  <form method="post" action="<?= url('/super/users/' . $u['id'] . '/reset-password') ?>"
                        data-confirm="Generate a temporary password for <?= e($u['email']) ?>?">
                    <?= csrf_field() ?>
                    <button class="btn btn-sm btn-amber" title="Generate a temporary password">Reset pwd</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
