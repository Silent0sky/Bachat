<?php
/**
 * Checkout page. Vars: $items, $subtotal, $coupon (?array), $discount (float),
 * $addresses, $pickupEnabled, $deliveryEnabled (bool), $codEnabled, $upiEnabled (bool), $deliveryCharge (float)
 */
$feeOn   = (float) $deliveryCharge > 0;
$estTotal = max(0.0, (float) $subtotal - (float) $discount + ($feeOn ? (float) $deliveryCharge : 0.0));
?>
<div class="container py-4">
  <h1 class="section-title mb-1"><?= e(t('checkout.order_summary')) ?></h1>
  <p class="text-muted small mb-4"><?= e(t('shop.in_cart_note')) ?></p>

  <form method="post" action="<?= url('/checkout') ?>" id="checkoutForm">
  <?= csrf_field() ?>

  <div class="row g-4">
    <!-- ============ Left: fulfilment + address + contact ============ -->
    <div class="col-lg-7">

      <!-- Fulfilment -->
      <div class="b-card mb-3">
        <h2 class="h6 fw-bold text-brand text-uppercase mb-3"><?= e(t('shop.fulfilment')) ?></h2>
        <div class="row g-2">
          <?php if ($pickupEnabled): ?>
          <div class="col-sm-6">
            <label class="list-tile w-100 h-100 m-0" style="cursor:pointer;">
              <input type="radio" class="form-check-input mt-0" name="fulfilment" value="pickup"
                     <?= !$deliveryEnabled ? 'checked' : '' ?> data-fulfilment>
              <span class="flex-grow-1">
                <span class="fw-semibold d-block"><i data-lucide="warehouse" width="15" height="15" class="d-inline"></i> <?= e(t('shop.pickup')) ?></span>
                <span class="small text-muted"><?= e(t('checkout.pickup_note')) ?></span>
              </span>
            </label>
          </div>
          <?php endif; ?>
          <?php if ($deliveryEnabled): ?>
          <div class="col-sm-6">
            <label class="list-tile w-100 h-100 m-0" style="cursor:pointer;">
              <input type="radio" class="form-check-input mt-0" name="fulfilment" value="delivery"
                     <?= !$pickupEnabled ? 'checked' : '' ?> data-fulfilment>
              <span class="flex-grow-1">
                <span class="fw-semibold d-block"><i data-lucide="bike" width="15" height="15" class="d-inline"></i> <?= e(t('shop.delivery')) ?></span>
                <span class="small text-muted"><?= e($feeOn ? t('shop.delivery_fee') . ': ' . money($deliveryCharge) : t('shop.delivery_fee') . ': ' . money(0)) ?></span>
              </span>
            </label>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Delivery address -->
      <?php if ($deliveryEnabled): ?>
      <div class="b-card mb-3" id="deliveryPanel">
        <h2 class="h6 fw-bold text-brand text-uppercase mb-3"><?= e(t('checkout.delivery_address')) ?></h2>

        <?php if (empty($addresses)): ?>
          <div class="b-note mb-3"><i data-lucide="info"></i><span><?= e(t('checkout.no_addresses')) ?></span></div>
        <?php else: ?>
          <label class="form-label small fw-semibold" for="address_id"><?= e(t('shop.address')) ?></label>
        <?php endif; ?>
        <select class="form-select mb-2" name="address_id" id="address_id" <?= empty($addresses) ? 'hidden' : '' ?>>
          <?php foreach ($addresses as $a): ?>
            <option value="<?= (int) $a['id'] ?>" <?= (int) $a['is_default'] === 1 ? 'selected' : '' ?>>
              <?= e($a['label']) ?> — <?= e($a['line1']) ?><?= $a['city'] ? ', ' . e($a['city']) : '' ?><?= $a['pincode'] ? ' - ' . e($a['pincode']) : '' ?>
            </option>
          <?php endforeach; ?>
          <option value="new" <?= empty($addresses) ? 'selected' : '' ?>><?= e(t('checkout.new_address')) ?></option>
        </select>

        <div class="collapse <?= empty($addresses) ? 'show' : '' ?>" id="newAddressPanel">
          <div class="bg-brand-soft rounded-3 p-3">
            <div class="row g-2">
              <div class="col-sm-4">
                <label class="form-label small fw-semibold mb-1"><?= e(t('profile.label')) ?> *</label>
                <input type="text" class="form-control form-control-sm" name="new_label" maxlength="60" placeholder="Home">
              </div>
              <div class="col-sm-8">
                <label class="form-label small fw-semibold mb-1"><?= e(t('profile.line1')) ?> *</label>
                <input type="text" class="form-control form-control-sm" name="new_line1" maxlength="255">
              </div>
              <div class="col-sm-6">
                <label class="form-label small fw-semibold mb-1"><?= e(t('profile.city')) ?></label>
                <input type="text" class="form-control form-control-sm" name="new_city" maxlength="80">
              </div>
              <div class="col-sm-6">
                <label class="form-label small fw-semibold mb-1"><?= e(t('profile.pincode')) ?></label>
                <input type="text" class="form-control form-control-sm" name="new_pincode" maxlength="10" inputmode="numeric">
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <!-- Contact -->
      <div class="b-card mb-3">
        <h2 class="h6 fw-bold text-brand text-uppercase mb-3"><?= e(t('checkout.contact')) ?></h2>
        <div class="row g-2">
          <div class="col-sm-6">
            <label class="form-label small fw-semibold mb-1"><?= e(t('checkout.contact_name')) ?> *</label>
            <input type="text" class="form-control" name="contact_name" maxlength="120"
                   value="<?= e(old('contact_name', auth_user()['name'] ?? '')) ?>" required>
          </div>
          <div class="col-sm-6">
            <label class="form-label small fw-semibold mb-1"><?= e(t('checkout.contact_phone')) ?> *</label>
            <input type="tel" class="form-control" name="contact_phone" maxlength="20"
                   value="<?= e(old('contact_phone', auth_user()['phone'] ?? '')) ?>" required>
          </div>
        </div>
      </div>

      <!-- Payment method -->
      <div class="b-card mb-3">
        <h2 class="h6 fw-bold text-brand text-uppercase mb-3"><?= e(t('shop.payment_method')) ?></h2>
        <?php if ($upiEnabled): ?>
        <label class="list-tile w-100 mb-2" style="cursor:pointer;">
          <input type="radio" class="form-check-input mt-0" name="payment_method" value="upi" <?= $upiEnabled && !$codEnabled ? 'checked' : '' ?>>
          <span class="flex-grow-1">
            <span class="fw-semibold d-block"><i data-lucide="smartphone" width="15" height="15" class="d-inline"></i> <?= e(t('shop.upi')) ?></span>
            <span class="small text-muted"><?= e(t('checkout.pay_upi_note')) ?></span>
          </span>
        </label>
        <?php endif; ?>
        <?php if ($codEnabled): ?>
        <label class="list-tile w-100 mb-0" style="cursor:pointer;">
          <input type="radio" class="form-check-input mt-0" name="payment_method" value="cod" <?= $codEnabled && !$upiEnabled ? 'checked' : '' ?>>
          <span class="flex-grow-1">
            <span class="fw-semibold d-block"><i data-lucide="banknote" width="15" height="15" class="d-inline"></i> <?= e(t('shop.cod')) ?></span>
            <span class="small text-muted"><?= e(t('orders.cod_note')) ?></span>
          </span>
        </label>
        <?php endif; ?>
        <?php if (!$upiEnabled && !$codEnabled): ?>
          <div class="b-note mb-0"><i data-lucide="alert-triangle"></i><span><?= e(t('checkout.no_payment_method')) ?></span></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- ============ Right: items + totals + coupon + confirm ============ -->
    <div class="col-lg-5">
      <div class="b-card mb-3">
        <h2 class="h6 fw-bold text-brand text-uppercase mb-3"><?= e(t('checkout.items')) ?></h2>
        <?php foreach ($items as $it): ?>
          <div class="d-flex justify-content-between align-items-center gap-2 py-2 border-bottom">
            <div class="min-w-0" style="min-width:0;">
              <div class="small fw-semibold text-truncate"><?= e(product_name($it)) ?></div>
              <div class="small text-muted">
                <?= e(pack_label($it['pack_size'], $it['unit'])) ?> × <?= e(qty($it['qty'])) ?>
              </div>
            </div>
            <div class="small fw-semibold flex-shrink-0"><?= e(money((float) $it['unit_price'] * (float) $it['qty'])) ?></div>
          </div>
        <?php endforeach; ?>

        <!-- Coupon -->
        <div class="mt-3">
          <?php if ($coupon): ?>
            <div class="d-flex justify-content-between align-items-center bg-brand-soft rounded-3 p-2 px-3">
              <div class="small">
                <i data-lucide="ticket-percent" width="14" height="14" class="text-brand"></i>
                <strong class="ms-1"><?= e($coupon['code']) ?></strong>
                <span class="text-muted">— <?= e(t('shop.discount')) ?> <?= e(money($discount)) ?></span>
              </div>
              <a class="btn btn-outline-danger btn-sm" href="<?= url('/checkout?coupon=remove') ?>"><?= e(t('checkout.coupon_remove')) ?></a>
            </div>
          <?php else: ?>
            <div class="d-flex gap-2">
              <input type="text" class="form-control form-control-sm" id="couponCodeInput" placeholder="<?= e(t('shop.coupon_code')) ?>" maxlength="30">
              <button type="button" class="btn btn-soft btn-sm flex-shrink-0" onclick="applyCoupon()"><?= e(t('shop.coupon_apply')) ?></button>
            </div>
            <script>
            function applyCoupon() {
              var code = document.getElementById('couponCodeInput').value.trim();
              if (code) {
                location.href = '<?= url('/checkout') ?>?coupon=apply&code=' + encodeURIComponent(code);
              }
            }
            </script>
          <?php endif; ?>
        </div>
      </div>

      <div class="b-card sticky-top" style="top:84px;">
        <h2 class="h6 fw-bold text-brand text-uppercase mb-3"><?= e(t('checkout.totals')) ?></h2>
        <div class="d-flex justify-content-between mb-1">
          <span class="text-muted"><?= e(t('shop.subtotal')) ?></span>
          <strong><?= e(money($subtotal)) ?></strong>
        </div>
        <?php if ($coupon && $discount > 0): ?>
          <div class="d-flex justify-content-between mb-1 text-success">
            <span><?= e(t('shop.discount')) ?> (<?= e($coupon['code']) ?>)</span>
            <span>− <?= e(money($discount)) ?></span>
          </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between mb-1" id="feeRow" <?= $feeOn ? '' : 'hidden' ?>>
          <span class="text-muted"><?= e(t('shop.delivery_fee')) ?></span>
          <span id="feeAmount"><?= e(money($deliveryCharge)) ?></span>
        </div>
        <hr>
        <div class="d-flex justify-content-between align-items-center mb-3">
          <span class="fw-semibold"><?= e(t('shop.grand_total')) ?></span>
          <span class="price fs-5" id="grandTotal"><?= e(money($estTotal)) ?></span>
        </div>

        <div class="form-check mb-2">
          <input class="form-check-input" type="checkbox" value="1" name="confirm" id="confirmBox" required>
          <label class="form-check-label small" for="confirmBox"><?= e(t('checkout.confirm_order')) ?></label>
        </div>
        <p class="small text-muted mb-2"><?= e(t('checkout.current_prices_note')) ?></p>

        <button type="submit" class="btn btn-brand w-100 b-btn-pill" <?= (!$upiEnabled && !$codEnabled) ? 'disabled' : '' ?>>
          <i data-lucide="check-circle-2" width="16" height="16"></i> <?= e(t('shop.place_order')) ?>
        </button>
      </div>
    </div>
  </div>
  </form>
</div>

<script>
(function () {
  var fee = <?= json_encode((float) $deliveryCharge) ?>;
  var base = <?= json_encode(round((float) $subtotal - (float) $discount, 2)) ?>;
  var fmt = function (n) { return '₹' + Number(n).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };

  function sync() {
    var del = document.querySelector('input[name="fulfilment"][value="delivery"]');
    var isDelivery = del && del.checked;
    var panel = document.getElementById('deliveryPanel');
    if (panel) panel.classList.toggle('d-none', !isDelivery);
    var feeRow = document.getElementById('feeRow');
    if (feeRow) feeRow.hidden = !(isDelivery && fee > 0);
    var gt = document.getElementById('grandTotal');
    if (gt) gt.textContent = fmt(base + (isDelivery ? fee : 0));
  }
  document.querySelectorAll('[data-fulfilment]').forEach(function (r) {
    r.addEventListener('change', sync);
  });
  var sel = document.getElementById('address_id');
  if (sel) {
    var newPanel = document.getElementById('newAddressPanel');
    var syncAddr = function () { if (newPanel) newPanel.classList.toggle('show', sel.value === 'new'); };
    sel.addEventListener('change', syncAddr);
  }
  sync();
})();
</script>
