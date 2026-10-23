<?php
/** Order detail (society admin). Vars: $order, $items, $events, $payments, $task */
$flow = [
    'pending'   => ['confirmed' => 'Confirm order'],
    'confirmed' => ['packed' => 'Mark packed'],
    'packed'    => ['ready' => 'Mark ready'],
    'ready'     => ['delivered' => 'Mark delivered'],
];
$canCancel = in_array($order['status'], ['pending', 'confirmed', 'packed', 'ready'], true);
$isPickup  = $order['fulfilment'] === 'pickup';
?>
<a class="small text-decoration-none text-brand fw-semibold d-inline-block mb-2" href="<?= url('/admin/orders') ?>"><i data-lucide="arrow-left" width="14" height="14"></i> All orders</a>

<div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-4">
  <div>
    <h1 class="section-title mb-1"><?= e($order['order_no']) ?></h1>
    <p class="small text-muted mb-0">
      Placed <?= e(human_dt($order['created_at'])) ?>
      · <?= $order['fulfilment'] === 'delivery' ? 'Home delivery' : 'Society pickup' ?>
      <?php if ($order['slot_label']): ?> · <?= e($order['slot_label']) ?><?= $order['slot_date'] ? ' (' . e(human_date($order['slot_date'])) . ')' : '' ?><?php endif; ?>
    </p>
  </div>
  <span class="<?= e(status_badge($order['status'])) ?>" style="font-size:14px"><?= e(str_replace('_', ' ', $order['status'])) ?></span>
</div>

<div class="row g-4">
  <div class="col-lg-7">
    <div class="b-card mb-4 fade-up">
      <h2 class="h6 fw-bold text-brand mb-3">Items (<?= count($items) ?>)</h2>
      <div class="b-table">
        <div class="table-responsive">
          <table class="table table-sm align-middle mb-0">
            <thead><tr><th>Item</th><th>Qty</th><th>Unit price</th><th class="text-end">Total</th></tr></thead>
            <tbody>
              <?php foreach ($items as $it): ?>
                <tr>
                  <td>
                    <div class="fw-semibold small"><?= e($it['product_name']) ?></div>
                    <div class="small text-muted"><?= e($it['brand_name'] ?? '') ?> <?= $it['pack_size'] ? '· ' . e(pack_label($it['pack_size'], $it['unit'])) : '' ?></div>
                  </td>
                  <td class="small"><?= e(qty($it['qty'])) ?></td>
                  <td class="small"><?= money($it['unit_price']) ?></td>
                  <td class="small fw-semibold text-end"><?= money($it['line_total']) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <hr>
      <div class="row g-2 small">
        <div class="col-6 text-muted">Subtotal</div><div class="col-6 text-end"><?= money($order['subtotal']) ?></div>
        <div class="col-6 text-muted">Discount</div><div class="col-6 text-end text-brand">− <?= money($order['discount']) ?></div>
        <div class="col-6 text-muted">Delivery fee</div><div class="col-6 text-end"><?= money($order['delivery_fee']) ?></div>
        <div class="col-6 text-muted">Tax</div><div class="col-6 text-end"><?= money($order['tax']) ?></div>
        <div class="col-6 fw-bold">Total</div><div class="col-6 text-end fw-bold text-brand" style="font-size:16px"><?= money($order['total']) ?></div>
      </div>
    </div>

    <div class="b-card fade-up d1">
      <h2 class="h6 fw-bold text-brand mb-3">Status actions</h2>
      <?php if ($order['status'] === 'delivered' || $order['status'] === 'cancelled' || $order['status'] === 'failed'): ?>
        <p class="small text-muted mb-0">This order is <?= e($order['status']) ?> — no further transitions.</p>
      <?php else: ?>
        <div class="d-flex flex-wrap gap-2 align-items-center">
          <?php foreach ($flow[$order['status']] ?? [] as $to => $lbl): ?>
            <?php if ($to === 'delivered' && !$isPickup) continue; // delivery orders are completed by staff via OTP ?>
            <?php if ($to === 'confirmed'): ?>
              <form method="post" action="<?= url('/admin/orders/' . $order['id'] . '/status') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="status" value="confirmed">
                <button class="btn btn-brand b-btn-pill"><?= e($lbl) ?></button>
              </form>
            <?php else: ?>
              <form method="post" action="<?= url('/admin/orders/' . $order['id'] . '/status') ?>" data-confirm="<?= e($lbl) ?>?">
                <?= csrf_field() ?>
                <input type="hidden" name="status" value="<?= e($to) ?>">
                <button class="btn btn-brand b-btn-pill"><?= e($lbl) ?></button>
              </form>
            <?php endif; ?>
          <?php endforeach; ?>
          <?php if (!$isPickup && in_array($order['status'], ['confirmed', 'packed', 'ready'], true)): ?>
            <a class="btn btn-soft b-btn-pill" href="<?= url('/admin/deliveries') ?>"><i data-lucide="bike" class="me-1"></i> Assign delivery</a>
          <?php endif; ?>
          <?php if ($canCancel): ?>
            <form method="post" action="<?= url('/admin/orders/' . $order['id'] . '/status') ?>" class="d-flex gap-1 ms-auto" data-confirm="Cancel this order? The resident will be notified with your reason.">
              <?= csrf_field() ?>
              <input type="hidden" name="status" value="cancelled">
              <input class="form-control form-control-sm" name="reason" placeholder="cancellation reason" maxlength="200" required style="width:190px">
              <button class="btn btn-outline-brand text-danger">Cancel order</button>
            </form>
          <?php endif; ?>
        </div>
        <?php if (!$isPickup && ($order['status'] ?? '') === 'ready'): ?>
          <div class="b-note mt-3"><i data-lucide="info"></i><span>Delivery orders are marked delivered by the delivery staff using the OTP — assign a task in Deliveries.</span></div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="b-card mb-4 fade-up">
      <h2 class="h6 fw-bold text-brand mb-3">Resident &amp; delivery details</h2>
      <div class="small mb-1"><strong><?= e($order['user_name']) ?></strong> · <?= e($order['user_email']) ?></div>
      <div class="small text-muted mb-1">Contact: <?= e($order['contact_name'] ?? '—') ?> <?= $order['contact_phone'] ? '· ' . e($order['contact_phone']) : '' ?></div>
      <div class="small text-muted mb-3">Address: <?= e($order['address_text'] ?? '—') ?></div>
      <?php if ($task): ?>
        <hr>
        <div class="d-flex align-items-center gap-2">
          <i data-lucide="bike" class="text-brand"></i>
          <div class="flex-grow-1 small">
            <div class="fw-semibold">Delivery task — <?= e($task['staff_name']) ?></div>
            <div class="text-muted">Status: <?= e(str_replace('_', ' ', $task['status'])) ?><?= $task['otp'] ? ' · OTP ' . e($task['otp']) : '' ?></div>
          </div>
          <a class="btn btn-sm btn-soft" href="<?= url('/admin/deliveries') ?>">Manage</a>
        </div>
      <?php endif; ?>
    </div>

    <div class="b-card mb-4 fade-up d1">
      <h2 class="h6 fw-bold text-brand mb-3">Payments</h2>
      <?php if ($payments === []): ?>
        <div class="empty-state p-3"><i data-lucide="wallet"></i><p>No payment records yet.</p></div>
      <?php else: ?>
        <div class="d-flex flex-column gap-2">
          <?php foreach ($payments as $p): ?>
            <div class="list-tile">
              <div class="flex-grow-1 small">
                <div class="fw-semibold"><?= money($p['amount']) ?> · <span class="text-uppercase"><?= e($p['method']) ?></span></div>
                <div class="text-muted"><?= $p['utr'] ? 'UTR ' . e($p['utr']) : ($p['txn_ref'] ? 'Ref ' . e($p['txn_ref']) : 'no reference') ?> · <?= e(human_dt($p['created_at'])) ?></div>
              </div>
              <span class="<?= e(status_badge($p['status'])) ?>"><?= e($p['status']) ?></span>
            </div>
          <?php endforeach; ?>
        </div>
        <a class="btn btn-sm btn-soft mt-2" href="<?= url('/admin/payments') ?>">Go to payment verification</a>
      <?php endif; ?>
    </div>

    <div class="b-card fade-up d2">
      <h2 class="h6 fw-bold text-brand mb-3">Timeline</h2>
      <?php if ($events === []): ?>
        <div class="empty-state p-3"><i data-lucide="history"></i><p>No events recorded.</p></div>
      <?php else: ?>
        <div class="b-timeline">
          <?php foreach ($events as $ev): ?>
            <div class="mb-3">
              <div class="d-flex align-items-center gap-2">
                <span class="b-badge-blue" style="font-size:11px"><?= e(str_replace('_', ' ', $ev['status'])) ?></span>
                <span class="small text-muted"><?= e(human_dt($ev['created_at'])) ?></span>
              </div>
              <?php if ($ev['note']): ?><div class="small text-muted mt-1"><?= e($ev['note']) ?></div><?php endif; ?>
              <?php if ($ev['actor_name']): ?><div class="small text-muted">by <?= e($ev['actor_name']) ?></div><?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</div>
