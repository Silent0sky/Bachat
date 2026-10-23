<?php
/**
 * Printable standalone invoice (rendered via Controller::bare — full HTML document).
 * Vars: $order, $items (order_items), $payment (?array), $society (?array)
 */
$logo = $society && !empty($society['logo']) ? upload_url($society['logo']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e(t('orders.invoice_title')) ?> · <?= e($order['order_no']) ?></title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: 'Segoe UI', system-ui, -apple-system, Arial, sans-serif; color: #1F2A24; background: #F4F6F5; padding: 24px 12px; }
  .sheet { max-width: 780px; margin: 0 auto; background: #fff; border-radius: 16px; box-shadow: 0 8px 30px rgba(22,69,47,.12); padding: 34px 38px; }
  .head { display: flex; justify-content: space-between; align-items: flex-start; gap: 18px; border-bottom: 3px solid #16452F; padding-bottom: 16px; }
  .brand { display: flex; align-items: center; gap: 12px; }
  .brand img { width: 48px; height: 48px; border-radius: 10px; object-fit: cover; }
  .brand-name { font-size: 20px; font-weight: 800; color: #16452F; }
  .brand-sub { font-size: 12px; color: #5C6B62; line-height: 1.5; }
  .doc-type { font-size: 13px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; color: #28A66A; background: #EAF7F0; padding: 6px 14px; border-radius: 999px; white-space: nowrap; }
  .meta { display: flex; flex-wrap: wrap; gap: 26px; margin: 20px 0; font-size: 13px; }
  .meta div span { display: block; color: #5C6B62; font-size: 11px; text-transform: uppercase; letter-spacing: .8px; margin-bottom: 2px; }
  .meta div strong { font-size: 13.5px; }
  table { width: 100%; border-collapse: collapse; margin-top: 6px; font-size: 13px; }
  thead th { background: #F1F5F3; color: #41544A; text-align: left; padding: 10px 12px; font-size: 11px; text-transform: uppercase; letter-spacing: .8px; border-bottom: 1px solid #E2EAE5; }
  tbody td { padding: 10px 12px; border-bottom: 1px solid #EDF2EF; vertical-align: top; }
  .num { text-align: right; white-space: nowrap; }
  .ctr { text-align: center; }
  .tot { margin-top: 16px; margin-left: auto; width: 270px; font-size: 13.5px; }
  .tot div { display: flex; justify-content: space-between; padding: 5px 0; }
  .tot .grand { border-top: 2px solid #16452F; margin-top: 6px; padding-top: 10px; font-weight: 800; font-size: 17px; color: #16452F; }
  .status { display: inline-block; padding: 3px 12px; border-radius: 999px; font-size: 11.5px; font-weight: 700; }
  .st-pending { background: #FDF3DC; color: #8A6410; } .st-submitted { background: #FDF3DC; color: #8A6410; }
  .st-verified { background: #E4F5EB; color: #1D7A4C; } .st-failed { background: #FCE9E8; color: #A83A36; }
  .st-refunded { background: #EEF1F0; color: #5C6B62; } .st-other { background: #EEF1F0; color: #5C6B62; }
  .pay-box { margin-top: 22px; border: 1px dashed #C7D6CD; border-radius: 12px; padding: 14px 16px; font-size: 13px; background: #FAFCFB; }
  .foot { margin-top: 26px; padding-top: 14px; border-top: 1px solid #E2EAE5; font-size: 11.5px; color: #5C6B62; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
  .printbar { max-width: 780px; margin: 0 auto 14px; text-align: right; }
  .printbar button { background: #16452F; color: #fff; border: none; border-radius: 999px; padding: 9px 22px; font-size: 13.5px; font-weight: 600; cursor: pointer; }
  .printbar button:hover { background: #0F3624; }
  @media print {
    body { background: #fff; padding: 0; }
    .sheet { box-shadow: none; border-radius: 0; padding: 10mm 8mm; max-width: none; }
    .printbar { display: none; }
  }
  @media (max-width: 560px) {
    .sheet { padding: 22px 16px; }
    .head { flex-direction: column; }
    .meta { gap: 14px; }
    .tot { width: 100%; }
  }
</style>
</head>
<body>
<div class="printbar"><button type="button" onclick="window.print()">🖨 <?= e(t('common.print')) ?></button></div>

<div class="sheet">
  <div class="head">
    <div class="brand">
      <?php if ($logo): ?>
        <img src="<?= e($logo) ?>" alt="">
      <?php endif; ?>
      <div>
        <div class="brand-name"><?= e($society['name'] ?? t('app.name')) ?></div>
        <div class="brand-sub">
          <?= e(trim((string) (($society['address'] ?? '') . (($society['city'] ?? '') ? ', ' . $society['city'] : '')))) ?>
          <?php if (!empty($society['contact_phone'])): ?><br><?= e($society['contact_phone']) ?><?php endif; ?>
          <?php if (!empty($society['contact_email'])): ?><br><?= e($society['contact_email']) ?><?php endif; ?>
        </div>
      </div>
    </div>
    <div style="text-align:right;">
      <div class="doc-type"><?= e(t('orders.invoice_title')) ?></div>
    </div>
  </div>

  <div class="meta">
    <div><span><?= e(t('orders.order_no')) ?></span><strong><?= e($order['order_no']) ?></strong></div>
    <div><span><?= e(t('orders.placed_on')) ?></span><strong><?= e(human_dt($order['placed_at'] ?: $order['created_at'])) ?></strong></div>
    <div><span><?= e(t('common.status')) ?></span><strong><?= e(ucfirst(str_replace('_', ' ', (string) $order['status']))) ?></strong></div>
    <div><span><?= e(t('shop.fulfilment')) ?></span><strong><?= e($order['fulfilment'] === 'delivery' ? t('shop.delivery') : t('shop.pickup')) ?></strong></div>
  </div>

  <div class="meta">
    <div style="max-width:340px;">
      <span><?= e($order['fulfilment'] === 'delivery' ? t('orders.delivery_to') : t('orders.pickup_at')) ?></span>
      <strong><?= e($order['address_text'] ?: '—') ?></strong>
    </div>
    <div>
      <span><?= e(t('orders.placed_by')) ?></span>
      <strong><?= e($order['contact_name'] ?: '—') ?></strong>
      <?php if (!empty($order['contact_phone'])): ?><div><?= e($order['contact_phone']) ?></div><?php endif; ?>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>#</th>
        <th><?= e(t('orders.item')) ?></th>
        <th class="ctr"><?= e(t('common.qty')) ?></th>
        <th class="num"><?= e(t('orders.rate')) ?></th>
        <th class="num"><?= e(t('orders.amount')) ?></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $i => $it): ?>
        <tr>
          <td><?= $i + 1 ?></td>
          <td>
            <strong><?= e($it['product_name']) ?></strong>
            <div style="color:#5C6B62; font-size:11.5px;">
              <?= e($it['brand_name'] ?? '') ?>
              <?= $it['pack_size'] !== null ? ($it['brand_name'] ? ' · ' : '') . e(pack_label($it['pack_size'], $it['unit'])) : '' ?>
            </div>
          </td>
          <td class="ctr"><?= e(qty($it['qty'])) ?></td>
          <td class="num"><?= e(money($it['unit_price'])) ?></td>
          <td class="num"><strong><?= e(money($it['line_total'])) ?></strong></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <div class="tot">
    <div><span><?= e(t('shop.subtotal')) ?></span><span><?= e(money($order['subtotal'])) ?></span></div>
    <?php if ((float) $order['discount'] > 0): ?>
      <div style="color:#1D7A4C;"><span><?= e(t('shop.discount')) ?></span><span>− <?= e(money($order['discount'])) ?></span></div>
    <?php endif; ?>
    <?php if ((float) $order['delivery_fee'] > 0): ?>
      <div><span><?= e(t('shop.delivery_fee')) ?></span><span><?= e(money($order['delivery_fee'])) ?></span></div>
    <?php endif; ?>
    <?php if ((float) $order['tax'] > 0): ?>
      <div><span>Tax</span><span><?= e(money($order['tax'])) ?></span></div>
    <?php endif; ?>
    <div class="grand"><span><?= e(t('shop.grand_total')) ?></span><span><?= e(money($order['total'])) ?></span></div>
  </div>

  <div class="pay-box">
    <strong><?= e(t('orders.payment')) ?>:</strong>
    <?php if ($payment): ?>
      <?= e(strtoupper((string) $payment['method'])) ?>
      <span class="status st-<?= e((string) $payment['status']) ?>"><?= e(ucfirst((string) $payment['status'])) ?></span>
      <?php if (!empty($payment['utr'])): ?> · UTR <?= e($payment['utr']) ?><?php endif; ?>
      <?php if (!empty($payment['verified_at'])): ?> · <?= e(t('orders.verified_on')) ?> <?= e(human_dt($payment['verified_at'])) ?><?php endif; ?>
    <?php else: ?>
      <span class="status st-other">—</span>
    <?php endif; ?>
    <?php if ($society && !empty($society['upi_id'])): ?>
      <div style="margin-top:6px; color:#5C6B62;">UPI: <strong><?= e($society['upi_id']) ?></strong></div>
    <?php endif; ?>
  </div>

  <div class="foot">
    <span><?= e(t('app.powered_by')) ?> · <?= e(t('app.sample_prices')) ?></span>
    <span><?= e(t('orders.society_order_note')) ?></span>
  </div>
</div>
</body>
</html>
