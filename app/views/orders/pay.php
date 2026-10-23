<?php
/** UPI payment page. Vars: $order, $payment (?array), $society (?array) */
$upiId = $society['upi_id'] ?? '';
$qr    = $society['qr_image'] ?? null;
$amount = (float) ($payment['amount'] ?? $order['total']);
$upiLink = $upiId !== ''
    ? 'upi://pay?pa=' . rawurlencode($upiId) . '&pn=' . rawurlencode($society['name'] ?? 'Society')
      . '&am=' . number_format($amount, 2, '.', '') . '&cu=INR&tn=' . rawurlencode('Order ' . $order['order_no'])
    : '';
?>
<div class="container py-4" style="max-width:720px;">
  <a class="btn btn-soft btn-sm b-btn-pill mb-3" href="<?= url('/orders/' . (int) $order['id']) ?>"><i data-lucide="arrow-left" width="14" height="14"></i> <?= e(t('common.back')) ?></a>

  <div class="b-card mb-3">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
      <div>
        <h1 class="section-title mb-1"><?= e(t('orders.upi_title')) ?></h1>
        <p class="small text-muted mb-0">
          <?= e(t('orders.order_no')) ?> <strong><?= e($order['order_no']) ?></strong>
          · <?= e(human_dt($order['placed_at'] ?: $order['created_at'])) ?>
        </p>
      </div>
      <div class="text-end">
        <div class="stat-label"><?= e(t('orders.amount_due')) ?></div>
        <div class="price fs-4"><?= e(money($amount)) ?></div>
        <?php if ($payment && $payment['status'] === 'failed'): ?>
          <span class="b-badge-red mt-1"><?= e(ucfirst((string) $payment['status'])) ?> — <?= e(t('orders.pay_now')) ?></span>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="b-card mb-3">
    <h2 class="h6 fw-bold text-brand text-uppercase mb-3"><?= e(t('orders.upi_id')) ?></h2>
    <div class="row g-3 align-items-center">
      <?php if ($qr): ?>
        <div class="col-5 col-md-4 text-center">
          <img src="<?= e(upload_url($qr)) ?>" alt="UPI QR" class="rounded-3 border" style="width:100%; max-width:170px;">
        </div>
      <?php endif; ?>
      <div class="<?= $qr ? 'col-7 col-md-8' : 'col-12' ?>">
        <?php if ($upiId !== ''): ?>
          <div class="input-group mb-2">
            <input type="text" class="form-control bg-brand-soft fw-bold" value="<?= e($upiId) ?>" readonly aria-label="<?= e(t('orders.upi_id')) ?>">
            <button class="btn btn-soft" type="button"
                    onclick="navigator.clipboard && navigator.clipboard.writeText('<?= e($upiId) ?>');bachToast('UPI ID copied ✓','success');">
              <i data-lucide="copy" width="15" height="15"></i>
            </button>
          </div>
          <?php if ($upiLink !== ''): ?>
            <a class="btn btn-brand btn-sm b-btn-pill" href="<?= e($upiLink) ?>">
              <i data-lucide="smartphone" width="14" height="14"></i> <?= e(t('shop.upi')) ?>
            </a>
          <?php endif; ?>
        <?php else: ?>
          <div class="b-note mb-0"><i data-lucide="alert-triangle"></i><span><?= e(t('orders.upi_submit_note')) ?></span></div>
        <?php endif; ?>
      </div>
    </div>
    <p class="small text-muted mb-0 mt-3"><i data-lucide="info" width="13" height="13"></i> <?= e(t('orders.scan_note')) ?></p>
  </div>

  <div class="b-card">
    <h2 class="h6 fw-bold text-brand text-uppercase mb-3"><?= e(t('orders.payment')) ?> — <?= e(t('common.submit')) ?></h2>
    <form method="post" action="<?= url('/orders/' . (int) $order['id'] . '/pay') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label small fw-semibold" for="utr"><?= e(t('orders.utr')) ?> *</label>
        <input type="text" class="form-control" id="utr" name="utr" maxlength="80"
               value="<?= e(old('utr')) ?>" required>
        <div class="form-text"><?= e(t('orders.upi_submit_note')) ?></div>
      </div>
      <div class="mb-3">
        <label class="form-label small fw-semibold" for="proof"><?= e(t('orders.screenshot')) ?></label>
        <input type="file" class="form-control" id="proof" name="proof" accept="image/*">
      </div>
      <button type="submit" class="btn btn-brand w-100 b-btn-pill">
        <i data-lucide="send" width="15" height="15"></i> <?= e(t('common.submit')) ?> · <?= e(money($amount)) ?>
      </button>
    </form>
  </div>
</div>
