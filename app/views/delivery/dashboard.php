<?php
/** Delivery staff dashboard. Vars: $tasks, $stats */
$failReasons = [
    'Customer unavailable' => 'delivery.fail_unavailable',
    'Address issue'        => 'delivery.fail_address',
    'Refused'              => 'delivery.fail_refused',
    'Other'                => 'delivery.fail_other',
];
$steps = ['assigned', 'picked', 'out_for_delivery', 'delivered'];
$stepLabels = [
    'assigned'         => 'delivery.status_assigned',
    'picked'           => 'delivery.status_picked',
    'out_for_delivery' => 'delivery.status_ofd',
    'delivered'        => null, // rendered as ucfirst fallback
];
$stats = $stats ?? ['active' => 0, 'delivered' => 0, 'failed' => 0];
?>
<div class="container-fluid px-3 px-md-4 py-4">
  <div class="mb-4">
    <h1 class="section-title mb-1"><?= e(t('delivery.title')) ?></h1>
    <p class="text-muted small mb-0"><?= e(t('delivery.sub')) ?></p>
  </div>

  <!-- ============ Stats ============ -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-4">
      <a class="text-decoration-none d-block h-100" href="<?= url('/delivery/dashboard') ?>">
        <div class="b-card stat-card h-100">
          <div class="stat-value"><?= (int) $stats['active'] ?></div>
          <div class="stat-label"><?= e(t('delivery.stat_active')) ?></div>
          <div class="stat-icon bg-brand-soft"><i data-lucide="bike"></i></div>
        </div>
      </a>
    </div>
    <div class="col-6 col-md-4">
      <a class="text-decoration-none d-block h-100" href="<?= url('/delivery/history?status=delivered') ?>">
        <div class="b-card stat-card h-100">
          <div class="stat-value"><?= (int) $stats['delivered'] ?></div>
          <div class="stat-label"><?= e(t('delivery.stat_delivered')) ?></div>
          <div class="stat-icon bg-brand-soft"><i data-lucide="check-circle-2"></i></div>
        </div>
      </a>
    </div>
    <div class="col-6 col-md-4">
      <a class="text-decoration-none d-block h-100" href="<?= url('/delivery/history?status=failed') ?>">
        <div class="b-card stat-card h-100">
          <div class="stat-value"><?= (int) $stats['failed'] ?></div>
          <div class="stat-label"><?= e(t('delivery.stat_failed')) ?></div>
          <div class="stat-icon b-icon-tile-amber"><i data-lucide="alert-triangle"></i></div>
        </div>
      </a>
    </div>
  </div>

  <?php if (empty($tasks)): ?>
    <div class="empty-state b-card">
      <i data-lucide="package-check"></i>
      <p><?= e(t('delivery.none')) ?></p>
    </div>
  <?php else: ?>
  <div class="row g-3 g-xl-4">
    <?php foreach ($tasks as $t):
        $id = (int) $t['id'];
        $isOrder   = !empty($t['order_id']);
        $refNo     = $isOrder ? $t['order_no'] : ($t['booking_no'] ?? null);
        $address   = $isOrder ? $t['order_address'] : $t['booking_address'];
        $phone     = $isOrder ? $t['order_phone'] : $t['booking_phone'];
        $amount    = $isOrder ? $t['order_total'] : $t['booking_price'];
        $codDue    = ($t['pay_method'] === 'cod') && ($t['pay_status'] === 'pending');
        $stepIdx   = array_search($t['status'], $steps, true);
        $next      = match ($t['status']) {
            'assigned' => 'picked',
            'picked' => 'out_for_delivery',
            'out_for_delivery' => 'delivered',
            default => null,
        };
    ?>
    <div class="col-lg-6">
      <div class="b-card h-100">
        <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
          <div>
            <span class="b-badge-<?= $isOrder ? 'blue' : 'amber' ?>"><?= e($isOrder ? t('delivery.type_order') : t('delivery.type_booking')) ?></span>
            <strong class="ms-1"><?= e($refNo ?? ('#' . $id)) ?></strong>
            <div class="small text-muted"><i data-lucide="building-2" width="13" height="13"></i> <?= e($t['society_name']) ?></div>
          </div>
          <span class="<?= status_badge($t['status']) ?>"><?= e(ucfirst(str_replace('_', ' ', (string) $t['status']))) ?></span>
        </div>

        <div class="list-tile mb-2">
          <div class="b-icon-tile mb-0"><i data-lucide="map-pin"></i></div>
          <div class="flex-grow-1 small">
            <div class="fw-semibold"><?= e(t('delivery.address')) ?></div>
            <div class="text-muted"><?= e($address ?: '—') ?></div>
            <div class="mt-1"><i data-lucide="phone" width="12" height="12"></i>
              <?php if (!empty($phone)): ?>
                <a class="text-decoration-none text-brand fw-semibold" href="tel:<?= e($phone) ?>"><?= e($phone) ?></a>
              <?php else: ?>—<?php endif; ?>
            </div>
          </div>
          <?php if ($amount !== null): ?>
            <div class="text-end">
              <div class="price"><?= e(money($amount)) ?></div>
              <?php if ($codDue): ?>
                <span class="b-badge-amber"><?= e(t('delivery.collect', ['amount' => money($amount)])) ?></span>
              <?php elseif (!empty($t['pay_method'])): ?>
                <span class="b-badge-green"><?= e(t('delivery.paid_online')) ?></span>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- ============ Stepper ============ -->
        <div class="d-flex align-items-center gap-1 mb-3 small text-muted">
          <?php foreach ($steps as $i => $step): ?>
            <?php if ($i > 0): ?><i data-lucide="chevron-right" width="12" height="12" class="flex-shrink-0"></i><?php endif; ?>
            <span class="chip <?= $step === $t['status'] ? 'chip-active' : '' ?>" style="padding:3px 10px; font-size:11px;">
              <?= e($stepLabels[$step] ? t($stepLabels[$step]) : ucfirst(str_replace('_', ' ', $step))) ?>
            </span>
          <?php endforeach; ?>
        </div>

        <div class="d-flex flex-wrap gap-2">
          <?php if ($next && $next !== 'delivered'): ?>
            <form method="post" action="<?= url('/delivery/tasks/' . $id . '/status') ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="status" value="<?= e($next) ?>">
              <button type="submit" class="btn btn-brand btn-sm b-btn-pill">
                <i data-lucide="<?= $next === 'picked' ? 'package-check' : 'bike' ?>" width="14" height="14"></i>
                <?= e($next === 'picked' ? t('delivery.next_picked') : t('delivery.next_ofd')) ?>
              </button>
            </form>
          <?php elseif ($next === 'delivered'): ?>
            <button class="btn btn-forest btn-sm b-btn-pill" type="button" data-bs-toggle="collapse" data-bs-target="#dlv-<?= $id ?>">
              <i data-lucide="badge-check" width="14" height="14"></i> <?= e(t('delivery.next_delivered')) ?>
            </button>
          <?php endif; ?>

          <button class="btn btn-soft btn-sm b-btn-pill" type="button" data-bs-toggle="collapse" data-bs-target="#proof-<?= $id ?>">
            <i data-lucide="camera" width="14" height="14"></i> <?= e(t('delivery.upload_proof')) ?>
          </button>

          <button class="btn btn-outline-danger btn-sm b-btn-pill" type="button" data-bs-toggle="collapse" data-bs-target="#fail-<?= $id ?>">
            <i data-lucide="alert-triangle" width="14" height="14"></i> <?= e(t('delivery.fail')) ?>
          </button>
        </div>

        <!-- ============ OTP completion ============ -->
        <?php if ($next === 'delivered'): ?>
        <div class="collapse mt-3" id="dlv-<?= $id ?>">
          <div class="bg-brand-soft rounded-3 p-3">
            <form method="post" action="<?= url('/delivery/tasks/' . $id . '/status') ?>" class="row g-2 align-items-end">
              <?= csrf_field() ?>
              <input type="hidden" name="status" value="delivered">
              <div class="col-12">
                <label class="form-label small fw-semibold mb-1"><?= e(t('delivery.otp_label')) ?></label>
                <input type="text" class="form-control" name="otp" inputmode="numeric" pattern="\d{6}" maxlength="6"
                       placeholder="••••••" style="letter-spacing:6px; font-weight:700;" required>
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-forest w-100 b-btn-pill">
                  <i data-lucide="check-circle-2" width="14" height="14"></i> <?= e(t('delivery.otp_verify')) ?>
                  <?= $codDue && $amount !== null ? ' · ' . e(money($amount)) : '' ?>
                </button>
              </div>
            </form>
          </div>
        </div>
        <?php endif; ?>

        <!-- ============ Proof upload ============ -->
        <div class="collapse mt-3" id="proof-<?= $id ?>">
          <div class="border-top pt-3">
            <?php if (!empty($t['proof_image'])): ?>
              <div class="mb-2">
                <div class="small fw-semibold mb-1"><?= e(t('delivery.proof')) ?></div>
                <a href="<?= e(upload_url($t['proof_image'])) ?>" target="_blank" rel="noopener">
                  <img src="<?= e(upload_url($t['proof_image'])) ?>" alt="<?= e(t('delivery.proof')) ?>" class="rounded-3" style="max-height:120px;">
                </a>
              </div>
            <?php endif; ?>
            <form method="post" action="<?= url('/delivery/tasks/' . $id . '/proof') ?>" enctype="multipart/form-data" class="d-flex gap-2 align-items-end">
              <?= csrf_field() ?>
              <div class="flex-grow-1">
                <label class="form-label small fw-semibold mb-1"><?= e(t('delivery.upload_proof')) ?></label>
                <input type="file" class="form-control form-control-sm" name="proof_image" accept="image/*" required>
              </div>
              <button type="submit" class="btn btn-soft btn-sm"><i data-lucide="upload" width="14" height="14"></i></button>
            </form>
          </div>
        </div>

        <!-- ============ Fail ============ -->
        <div class="collapse mt-3" id="fail-<?= $id ?>">
          <div class="border-top pt-3">
            <form method="post" action="<?= url('/delivery/tasks/' . $id . '/status') ?>" class="row g-2">
              <?= csrf_field() ?>
              <input type="hidden" name="status" value="failed">
              <div class="col-md-6">
                <label class="form-label small fw-semibold mb-1"><?= e(t('delivery.fail_reason')) ?></label>
                <select class="form-select form-select-sm" name="fail_reason" required>
                  <?php foreach ($failReasons as $val => $key): ?>
                    <option value="<?= e($val) ?>"><?= e(t($key)) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label small fw-semibold mb-1"><?= e(t('delivery.fail_note')) ?></label>
                <input type="text" class="form-control form-control-sm" name="fail_note" maxlength="180">
              </div>
              <div class="col-12">
                <button type="submit" class="btn btn-outline-danger btn-sm b-btn-pill"><?= e(t('delivery.fail')) ?></button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <div class="text-center mt-4">
    <a class="btn btn-soft btn-sm b-btn-pill" href="<?= url('/delivery/history') ?>">
      <i data-lucide="history" width="14" height="14"></i> <?= e(t('delivery.history')) ?>
    </a>
  </div>
</div>
