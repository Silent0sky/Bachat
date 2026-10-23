<?php
/** Flats. Vars: $rows, $edit (null|array) */
$blocks = [];
foreach ($rows as $r) {
    $blocks[$r['block'] !== '' ? $r['block'] : '—'][] = $r;
}
ksort($blocks);
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
  <div>
    <h1 class="section-title mb-1">Flats</h1>
    <p class="text-muted small mb-0"><?= count($rows) ?> flats across <?= count($blocks) ?> block(s).</p>
  </div>
  <?php if ($edit === null): ?>
    <a class="btn btn-brand b-btn-pill" href="<?= url('/admin/flats?edit=new') ?>"><i data-lucide="plus" class="me-1"></i> Add flat</a>
  <?php else: ?>
    <a class="btn btn-soft b-btn-pill" href="<?= url('/admin/flats') ?>"><i data-lucide="x" class="me-1"></i> Close form</a>
  <?php endif; ?>
</div>

<?php if ($edit !== null || (string) ($_GET['edit'] ?? '') === 'new'): ?>
<div class="b-card mb-4 fade-up">
  <h2 class="h6 fw-bold text-brand mb-3"><?= is_array($edit) ? 'Edit flat — ' . e($edit['block'] . '-' . $edit['flat_number']) : 'Add a flat' ?></h2>
  <form method="post" action="<?= url('/admin/flats') ?>" class="row g-3">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold">Block / Wing</label>
      <input class="form-control" name="block" maxlength="20" placeholder="e.g. A" value="<?= e(old('block', $edit['block'] ?? '')) ?>">
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label fw-semibold">Flat number *</label>
      <input class="form-control" name="flat_number" required maxlength="30" placeholder="e.g. 101" value="<?= e(old('flat_number', $edit['flat_number'] ?? '')) ?>">
    </div>
    <div class="col-md-3">
      <label class="form-label fw-semibold">Flat type</label>
      <input class="form-control" name="flat_type" maxlength="40" placeholder="e.g. 3 BHK" value="<?= e(old('flat_type', $edit['flat_type'] ?? '')) ?>">
    </div>
    <div class="col-md-3 d-flex align-items-end gap-2">
      <button class="btn btn-brand b-btn-pill"><?= is_array($edit) ? 'Save changes' : 'Add flat' ?></button>
      <?php if (is_array($edit)): ?><a class="btn btn-soft b-btn-pill" href="<?= url('/admin/flats') ?>">Cancel</a><?php endif; ?>
    </div>
  </form>
</div>
<?php endif; ?>

<?php if ($rows === []): ?>
  <div class="b-card"><div class="empty-state"><i data-lucide="door-open"></i><p>No flats yet — add your blocks and flat numbers so residents can map their membership.</p></div></div>
<?php else: ?>
  <?php foreach ($blocks as $block => $flats): ?>
    <h2 class="h6 fw-bold text-brand mb-2">Block <?= e($block) ?> <span class="text-muted small fw-normal">(<?= count($flats) ?>)</span></h2>
    <div class="row g-2 mb-4">
      <?php foreach ($flats as $f): ?>
        <div class="col-6 col-md-4 col-xl-3">
          <div class="list-tile">
            <div class="flex-grow-1">
              <div class="fw-semibold"><?= e(($f['block'] !== '' ? $f['block'] . '-' : '') . $f['flat_number']) ?></div>
              <div class="small text-muted"><?= e($f['flat_type'] ?? '—') ?> · <?= (int) $f['members_count'] ?> member(s)</div>
            </div>
            <div class="d-flex gap-1">
              <a class="btn btn-sm btn-soft" href="<?= url('/admin/flats?edit=' . $f['id']) ?>">Edit</a>
              <form method="post" action="<?= url('/admin/flats/' . $f['id'] . '/delete') ?>" data-confirm="Delete flat <?= e(($f['block'] !== '' ? $f['block'] . '-' : '') . $f['flat_number']) ?>? Flats with memberships cannot be deleted.">
                <?= csrf_field() ?>
                <button class="btn btn-sm btn-soft text-danger"><i data-lucide="trash-2" width="14" height="14"></i></button>
              </form>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
