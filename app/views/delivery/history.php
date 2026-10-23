<?php
/** Delivery history (delivered + failed). Vars: $tasks */
?>
<div class="container-fluid px-3 px-md-4 py-4">
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4">
    <div>
      <h1 class="section-title mb-1"><?= e(t('delivery.history')) ?></h1>
      <p class="text-muted small mb-0"><?= e(t('delivery.history_sub')) ?></p>
    </div>
    <a class="btn btn-brand btn-sm b-btn-pill" href="<?= url('/delivery/dashboard') ?>">
      <i data-lucide="bike" width="14" height="14"></i> <?= e(t('delivery.title')) ?>
    </a>
  </div>

  <?php if (empty($tasks)): ?>
    <div class="empty-state b-card">
      <i data-lucide="history"></i>
      <p><?= e(t('delivery.history_empty')) ?></p>
    </div>
  <?php else: ?>
  <div class="b-table">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th><?= e(t('delivery.task')) ?></th>
            <th><?= e(t('delivery.address')) ?></th>
            <th><?= e(t('delivery.amount')) ?></th>
            <th><?= e(t('common.status')) ?></th>
            <th><?= e(t('delivery.delivered_at')) ?></th>
            <th><?= e(t('delivery.proof')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($tasks as $t):
              $isOrder = !empty($t['order_id']);
              $refNo   = $isOrder ? $t['order_no'] : ($t['booking_no'] ?? null);
              $address = $isOrder ? $t['order_address'] : $t['booking_address'];
              $amount  = $isOrder ? $t['order_total'] : $t['booking_price'];
          ?>
          <tr>
            <td>
              <div class="fw-semibold"><?= e($refNo ?? ('#' . $t['id'])) ?></div>
              <div class="small text-muted">
                <span class="b-badge-<?= $isOrder ? 'blue' : 'amber' ?>" style="font-size:10.5px;"><?= e($isOrder ? t('delivery.type_order') : t('delivery.type_booking')) ?></span>
                · <?= e($t['society_name']) ?>
                · <?= e(t('delivery.attempts')) ?>: <?= (int) $t['attempts'] ?>
              </div>
            </td>
            <td class="small"><?= e(mb_substr((string) ($address ?: '—'), 0, 60)) ?></td>
            <td class="small fw-semibold"><?= $amount !== null ? e(money($amount)) : '—' ?></td>
            <td>
              <span class="<?= status_badge($t['status']) ?>"><?= e(ucfirst((string) $t['status'])) ?></span>
              <?php if (!empty($t['fail_reason'])): ?>
                <div class="small text-muted mt-1"><?= e(t('delivery.failed_reason')) ?>: <?= e($t['fail_reason']) ?></div>
              <?php endif; ?>
            </td>
            <td class="small"><?= $t['delivered_at'] ? e(human_dt($t['delivered_at'])) : '—' ?></td>
            <td>
              <?php if (!empty($t['proof_image'])): ?>
                <a href="<?= e(upload_url($t['proof_image'])) ?>" target="_blank" rel="noopener">
                  <img src="<?= e(upload_url($t['proof_image'])) ?>" alt="<?= e(t('delivery.proof')) ?>" class="rounded-2" style="height:38px;">
                </a>
              <?php else: ?>
                <span class="text-muted small"><?= e(t('delivery.proof_none')) ?></span>
              <?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
