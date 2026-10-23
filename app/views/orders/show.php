<?php
/** Order detail. Vars: $order, $items (order_items), $events (order_events), $payment (?array), $society (?array) */
$cancellable = in_array($order['status'], ['pending', 'confirmed'], true);
$payable     = $payment && $payment['method'] === 'upi' && in_array($payment['status'], ['pending', 'failed'], true);
$payWait     = $payment && $payment['status'] === 'submitted';
$payVerified = $payment && $payment['status'] === 'verified';
?>
<div class="container py-4">
  <a class="btn btn-soft btn-sm b-btn-pill mb-3" href="<?= url('/orders') ?>"><i data-lucide="arrow-left" width="14" height="14"></i> <?= e(t('common.back')) ?></a>

  <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-4">
    <div>
      <h1 class="section-title mb-1"><?= e(t('orders.order_page')) ?> <?= e($order['order_no']) ?></h1>
      <p class="text-muted small mb-0">
        <i data-lucide="calendar" width="13" height="13"></i> <?= e(t('orders.placed_on')) ?>: <?= e(human_dt($order['placed_at'] ?: $order['created_at'])) ?>
        <?php if ($order['type'] !== 'regular'): ?>
          · <span class="b-badge-amber"><?= e(t('orders.campaign_note')) ?></span>
        <?php endif; ?>
      </p>
    </div>
    <div class="text-end">
      <span class="<?= status_badge((string) $order['status']) ?>" style="font-size:13px;"><?= e(ucfirst(str_replace('_', ' ', (string) $order['status']))) ?></span>
      <div class="price fs-4 mt-1"><?= e(money($order['total'])) ?></div>
    </div>
  </div>

  <?php if ($order['status'] === 'cancelled' && !empty($order['cancel_reason'])): ?>
    <div class="b-note mb-3"><i data-lucide="x-circle"></i><span><strong><?= e(t('orders.cancel_order')) ?>:</strong> <?= e($order['cancel_reason']) ?></span></div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- ============ Items + bill ============ -->
    <div class="col-lg-7">
      <div class="b-card mb-3">
        <h2 class="h6 fw-bold text-brand text-uppercase mb-3"><?= e(t('orders.items')) ?> (<?= count($items) ?>)</h2>
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead>
              <tr>
                <th><?= e(t('orders.item')) ?></th>
                <th class="text-center"><?= e(t('common.qty')) ?></th>
                <th class="text-end"><?= e(t('orders.rate')) ?></th>
                <th class="text-end"><?= e(t('orders.amount')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $it): ?>
                <tr>
                  <td>
                    <div class="small fw-semibold"><?= e($it['product_name']) ?></div>
                    <div class="small text-muted">
                      <?= e($it['brand_name'] ?? '') ?>
                      <?= $it['pack_size'] !== null ? '· ' . e(pack_label($it['pack_size'], $it['unit'])) : '' ?>
                    </div>
                  </td>
                  <td class="text-center small"><?= e(qty($it['qty'])) ?></td>
                  <td class="text-end small"><?= e(money($it['unit_price'])) ?></td>
                  <td class="text-end small fw-semibold"><?= e(money($it['line_total'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="b-card">
        <h2 class="h6 fw-bold text-brand text-uppercase mb-3"><?= e(t('orders.bill')) ?></h2>
        <div class="d-flex justify-content-between mb-1"><span class="text-muted"><?= e(t('shop.subtotal')) ?></span><span><?= e(money($order['subtotal'])) ?></span></div>
        <?php if ((float) $order['discount'] > 0): ?>
          <div class="d-flex justify-content-between mb-1 text-success"><span><?= e(t('shop.discount')) ?></span><span>− <?= e(money($order['discount'])) ?></span></div>
        <?php endif; ?>
        <?php if ((float) $order['delivery_fee'] > 0): ?>
          <div class="d-flex justify-content-between mb-1"><span class="text-muted"><?= e(t('shop.delivery_fee')) ?></span><span><?= e(money($order['delivery_fee'])) ?></span></div>
        <?php endif; ?>
        <?php if ((float) $order['tax'] > 0): ?>
          <div class="d-flex justify-content-between mb-1"><span class="text-muted">Tax</span><span><?= e(money($order['tax'])) ?></span></div>
        <?php endif; ?>
        <hr>
        <div class="d-flex justify-content-between align-items-center">
          <span class="fw-semibold"><?= e(t('shop.grand_total')) ?></span>
          <span class="price fs-5"><?= e(money($order['total'])) ?></span>
        </div>
      </div>
    </div>

    <!-- ============ Side: fulfilment, payment, actions ============ -->
    <div class="col-lg-5">
      <div class="b-card mb-3">
        <h2 class="h6 fw-bold text-brand text-uppercase mb-3">
          <i data-lucide="<?= $order['fulfilment'] === 'delivery' ? 'bike' : 'warehouse' ?>" width="15" height="15"></i>
          <?= e($order['fulfilment'] === 'delivery' ? t('orders.delivery_to') : t('orders.pickup_at')) ?>
        </h2>
        <div class="small mb-1"><?= e($order['address_text'] ?: '—') ?></div>
        <?php if (!empty($order['slot_date'])): ?>
          <div class="small text-muted"><i data-lucide="calendar-clock" width="13" height="13"></i>
            <?= e(human_date($order['slot_date'])) ?><?= $order['slot_label'] ? ' · ' . e($order['slot_label']) : '' ?>
          </div>
        <?php endif; ?>
        <hr class="my-2">
        <div class="small">
          <span class="text-muted"><?= e(t('orders.placed_by')) ?>:</span>
          <strong><?= e($order['contact_name'] ?: (auth_user()['name'] ?? '')) ?></strong>
          <?php if (!empty($order['contact_phone'])): ?>
            · <a class="text-decoration-none" href="tel:<?= e($order['contact_phone']) ?>"><?= e($order['contact_phone']) ?></a>
          <?php endif; ?>
        </div>
        <?php if ($society): ?>
          <div class="small text-muted mt-1"><i data-lucide="building-2" width="13" height="13"></i> <?= e($society['name']) ?></div>
        <?php endif; ?>
      </div>

      <!-- Payment -->
      <div class="b-card mb-3">
        <h2 class="h6 fw-bold text-brand text-uppercase mb-3"><?= e(t('orders.payment')) ?></h2>
        <?php if (!$payment): ?>
          <p class="small text-muted mb-0">—</p>
        <?php else: ?>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small text-muted"><?= e(t('orders.payment_method_label')) ?></span>
            <span class="b-badge-<?= $payment['method'] === 'cod' ? 'grey' : 'blue' ?>"><?= e(strtoupper((string) $payment['method'])) ?></span>
          </div>
          <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small text-muted"><?= e(t('common.status')) ?></span>
            <span class="<?= status_badge((string) $payment['status']) ?>"><?= e(ucfirst((string) $payment['status'])) ?></span>
          </div>
          <?php if ($payVerified): ?>
            <div class="small text-muted mb-0">
              <i data-lucide="check-circle-2" width="13" height="13"></i>
              <?= e(t('orders.verified_on')) ?>: <?= e(human_dt($payment['verified_at'])) ?>
            </div>
          <?php elseif ($payWait): ?>
            <div class="b-note mb-0"><i data-lucide="clock"></i><span><?= e(t('orders.waiting_verification')) ?></span></div>
          <?php elseif ($payable): ?>
            <a class="btn btn-amber w-100 b-btn-pill" href="<?= url('/orders/' . (int) $order['id'] . '/pay') ?>">
              <i data-lucide="badge-indian-rupee" width="15" height="15"></i> <?= e(t('orders.pay_now')) ?> · <?= e(money($order['total'])) ?>
            </a>
            <p class="small text-muted mb-0 mt-2"><?= e(t('orders.upi_submit_note')) ?></p>
          <?php elseif ($payment['method'] === 'cod'): ?>
            <p class="small text-muted mb-0"><?= e(t('orders.cod_note')) ?></p>
          <?php endif; ?>
        <?php endif; ?>
      </div>

      <!-- Actions -->
      <div class="b-card mb-3">
        <div class="d-flex flex-wrap gap-2">
          <a class="btn btn-outline-brand btn-sm b-btn-pill" href="<?= url('/orders/' . (int) $order['id'] . '/invoice') ?>" target="_blank" rel="noopener">
            <i data-lucide="file-text" width="14" height="14"></i> <?= e(t('orders.invoice')) ?>
          </a>
          <form method="post" action="<?= url('/orders/' . (int) $order['id'] . '/repeat') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-soft btn-sm b-btn-pill">
              <i data-lucide="repeat" width="14" height="14"></i> <?= e(t('shop.repeat_order')) ?>
            </button>
          </form>
          <?php if ($cancellable): ?>
            <form method="post" action="<?= url('/orders/' . (int) $order['id'] . '/cancel') ?>"
                  data-confirm="<?= e(t('orders.cancel_confirm')) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="cancel_reason" value="">
              <button type="submit" class="btn btn-outline-danger btn-sm b-btn-pill">
                <i data-lucide="x-circle" width="14" height="14"></i> <?= e(t('orders.cancel_order')) ?>
              </button>
            </form>
          <?php endif; ?>
        </div>
        <?php if ($payVerified && $order['status'] === 'cancelled'): ?>
          <div class="b-note mt-3 mb-0"><i data-lucide="undo-2"></i><span><?= e(t('orders.refund_requested')) ?></span></div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ============ Timeline ============ -->
  <?php if (!empty($events)): ?>
  <div class="b-card mt-4">
    <h2 class="h6 fw-bold text-brand text-uppercase mb-3"><?= e(t('orders.timeline')) ?></h2>
    <div class="b-timeline">
      <?php foreach ($events as $ev): ?>
        <div class="mb-3">
          <div class="small fw-semibold text-uppercase" style="letter-spacing:.5px;"><?= e(str_replace('_', ' ', (string) $ev['status'])) ?></div>
          <?php if (!empty($ev['note'])): ?><div class="small text-muted"><?= e($ev['note']) ?></div><?php endif; ?>
          <div class="small text-muted"><i data-lucide="clock" width="12" height="12"></i> <?= e(human_dt($ev['created_at'])) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
</div>
