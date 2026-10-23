<?php
/** Toast stack — renders server flash messages; app.js auto-initializes Bootstrap toasts. */
$flashes = \App\Core\Flash::all();
if (!empty($flashes)): ?>
<div class="toast-container position-fixed top-0 start-50 translate-middle-x p-3 b-toasts" aria-live="polite" aria-atomic="true">
  <?php foreach ($flashes as $f): [$type, $msg] = [$f['type'], $f['message']]; ?>
    <div class="toast b-toast align-items-center border-0 show" role="alert" data-bs-delay="3800"
         data-flash-type="<?= e($type) ?>">
      <div class="d-flex">
        <div class="toast-body">
          <i data-lucide="<?= $type === 'success' ? 'check-circle-2' : ($type === 'error' ? 'alert-triangle' : 'info') ?>" class="me-2"></i>
          <?= e($msg) ?>
        </div>
        <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
