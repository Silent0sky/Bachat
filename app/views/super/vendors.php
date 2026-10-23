<?php
/** Vendors. Vars: $vendors, $edit (null|array) */
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="section-title mb-1">Vendors</h1>
    <p class="text-muted small mb-0">Approve vendor partners who quote on bulk campaigns and serve societies directly.</p>
  </div>
  <?php if ($edit === null): ?>
    <a class="btn btn-brand b-btn-pill" href="<?= url('/super/vendors?edit=new') ?>"><i data-lucide="plus" class="me-1"></i> Add vendor</a>
  <?php else: ?>
    <a class="btn btn-soft b-btn-pill" href="<?= url('/super/vendors') ?>"><i data-lucide="x" class="me-1"></i> Close form</a>
  <?php endif; ?>
</div>

<?php $showForm = ($edit === null && (string) ($_GET['edit'] ?? '') === 'new'); ?>
<?php if ($showForm || is_array($edit)): ?>
<div class="b-card mb-4 fade-up" id="vendor-form">
  <h2 class="h6 fw-bold text-brand mb-3"><?= is_array($edit) ? 'Edit vendor — ' . e($edit['name']) : 'Add a vendor' ?></h2>
  <form method="post" action="<?= url('/super/vendors') ?>" class="row g-3">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <div class="col-md-4">
      <label class="form-label fw-semibold">Business name *</label>
      <input class="form-control" name="name" required maxlength="120" value="<?= e(old('name', $edit['name'] ?? '')) ?>">
    </div>
    <?php if (is_array($edit)): ?>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Email</label>
        <input class="form-control" value="<?= e($edit['email']) ?>" disabled>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label fw-semibold">Status</label>
        <select class="form-select" name="status">
          <option value="active" <?= (string) old('status', $edit['status']) === 'active' ? 'selected' : '' ?>>Active</option>
          <option value="pending" <?= (string) old('status', $edit['status']) === 'pending' ? 'selected' : '' ?>>Pending</option>
          <option value="suspended" <?= (string) old('status', $edit['status']) === 'suspended' ? 'selected' : '' ?>>Suspended</option>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label fw-semibold">Phone</label>
        <input class="form-control" name="phone" maxlength="20" value="<?= e(old('phone', $edit['phone'])) ?>">
      </div>
    <?php else: ?>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Email * <span class="text-muted small">(login id)</span></label>
        <input class="form-control" type="email" name="email" required maxlength="150" value="<?= e(old('email')) ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label fw-semibold">Password * <span class="text-muted small">(min 6)</span></label>
        <input class="form-control" type="text" name="password" required minlength="6" autocomplete="new-password">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label fw-semibold">Phone</label>
        <input class="form-control" name="phone" maxlength="20" value="<?= e(old('phone')) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label fw-semibold">Status</label>
        <select class="form-select" name="status">
          <option value="pending">Pending approval</option>
          <option value="active">Active now</option>
        </select>
      </div>
    <?php endif; ?>
    <div class="col-12 d-flex gap-2">
      <button class="btn btn-brand b-btn-pill"><?= is_array($edit) ? 'Save changes' : 'Create vendor' ?></button>
      <a class="btn btn-soft b-btn-pill" href="<?= url('/super/vendors') ?>">Cancel</a>
    </div>
  </form>
</div>
<?php endif; ?>

<?php if ($vendors === []): ?>
  <div class="b-card"><div class="empty-state"><i data-lucide="store"></i><p>No vendors yet.</p></div></div>
<?php else: ?>
<div class="row g-3">
  <?php foreach ($vendors as $v): ?>
    <div class="col-md-6 col-xl-4">
      <div class="b-card b-card-hover h-100 fade-up">
        <div class="d-flex justify-content-between align-items-start mb-2">
          <div class="d-flex align-items-center gap-2">
            <div class="b-icon-tile mb-0"><i data-lucide="store"></i></div>
            <div>
              <div class="fw-bold"><?= e($v['name']) ?></div>
              <div class="small text-muted"><?= e($v['email']) ?></div>
            </div>
          </div>
          <span class="<?= e(status_badge($v['status'])) ?>"><?= e($v['status'] === 'pending' ? 'awaiting approval' : $v['status']) ?></span>
        </div>
        <div class="d-flex gap-3 small text-muted mb-3">
          <span><i data-lucide="package" width="13" height="13"></i> <?= (int) $v['product_count'] ?> products</span>
          <span><i data-lucide="file-text" width="13" height="13"></i> <?= (int) $v['quote_count'] ?> quotes</span>
          <?php if ($v['phone']): ?><span><i data-lucide="phone" width="13" height="13"></i> <?= e($v['phone']) ?></span><?php endif; ?>
        </div>
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-sm btn-soft" href="<?= url('/super/vendors?edit=' . $v['id']) ?>">Edit</a>
          <form method="post" action="<?= url('/super/vendors/' . $v['id'] . '/status') ?>"
                data-confirm="<?= $v['status'] === 'active' ? 'Suspend this vendor?' : ($v['status'] === 'pending' ? 'Approve this vendor? They will be notified.' : 'Re-activate this vendor?') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="status" value="<?= $v['status'] === 'active' ? 'suspended' : 'active' ?>">
            <button class="btn btn-sm <?= $v['status'] === 'active' ? 'btn-outline-brand' : 'btn-brand' ?>">
              <?= $v['status'] === 'active' ? 'Suspend' : ($v['status'] === 'pending' ? 'Approve' : 'Re-activate') ?>
            </button>
          </form>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
