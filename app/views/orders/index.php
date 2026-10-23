<?php
/** Orders list. Vars: $orders (orders.* + item_count, pay_status, pay_method) */
?>
<div class="container py-4">
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4">
    <div>
      <h1 class="section-title mb-1"><?= e(t('orders.my_orders')) ?></h1>
      <p class="text-muted small mb-0"><?= e(t('orders.society_order_note')) ?></p>
    </div>
    <a class="btn btn-brand btn-sm b-btn-pill" href="<?= url('/shop') ?>">
      <i data-lucide="shopping-basket" width="14" height="14"></i> <?= e(t('nav.shop')) ?>
    </a>
  </div>

  <?php if (empty($orders)): ?>
    <div class="empty-state b-card">
      <i data-lucide="package"></i>
      <p><?= e(t('orders.no_orders')) ?></p>
      <a class="btn btn-brand b-btn-pill mt-3" href="<?= url('/shop') ?>"><?= e(t('shop.empty_cart_cta')) ?></a>
    </div>
  <?php else: ?>
  <div class="b-table">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead>
          <tr>
            <th><?= e(t('orders.order_no')) ?></th>
            <th><?= e(t('orders.items')) ?></th>
            <th><?= e(t('common.total')) ?></th>
            <th><?= e(t('common.status')) ?></th>
            <th><?= e(t('orders.payment')) ?></th>
            <th class="text-end"><?= e(t('common.actions')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($orders as $o):
              $id          = (int) $o['id'];
              $cancellable = in_array($o['status'], ['pending', 'confirmed'], true);
              $payable     = ($o['pay_method'] ?? null) === 'upi' && in_array($o['pay_status'] ?? null, ['pending', 'failed'], true);
              $payWait     = ($o['pay_status'] ?? null) === 'submitted';
          ?>
          <tr>
            <td>
              <a class="fw-bold text-decoration-none text-dark" href="<?= url('/orders/' . $id) ?>"><?= e($o['order_no']) ?></a>
              <div class="small text-muted"><?= e(human_dt($o['placed_at'] ?: $o['created_at'])) ?></div>
            </td>
            <td class="small">
              <?= e(t('orders.n_items', ['n' => (string) (int) $o['item_count']])) ?>
              <?php if ($o['type'] !== 'regular'): ?>
                <span class="b-badge-amber" style="font-size:10.5px;"><?= e(ucfirst((string) $o['type'])) ?></span>
              <?php endif; ?>
              <div class="text-muted"><?= e($o['fulfilment'] === 'delivery' ? t('shop.delivery') : t('shop.pickup')) ?></div>
            </td>
            <td class="fw-semibold"><?= e(money($o['total'])) ?></td>
            <td><span class="<?= status_badge((string) $o['status']) ?>"><?= e(ucfirst(str_replace('_', ' ', (string) $o['status']))) ?></span></td>
            <td class="small">
              <?php if ($o['pay_status'] !== null): ?>
                <span class="<?= status_badge((string) $o['pay_status']) ?>"><?= e(ucfirst((string) $o['pay_status'])) ?></span>
                <div class="text-muted text-uppercase" style="font-size:10.5px;"><?= e((string) $o['pay_method']) ?></div>
              <?php else: ?>
                <span class="text-muted">—</span>
              <?php endif; ?>
            </td>
            <td class="text-end">
              <div class="d-flex justify-content-end flex-wrap gap-1">
                <?php if ($payable): ?>
                  <a class="btn btn-amber btn-sm b-btn-pill" href="<?= url('/orders/' . $id . '/pay') ?>">
                    <i data-lucide="badge-indian-rupee" width="14" height="14"></i> <?= e(t('orders.pay_now')) ?>
                  </a>
                <?php elseif ($payWait): ?>
                  <span class="b-badge-amber align-self-center"><?= e(t('orders.waiting_verification')) ?></span>
                <?php endif; ?>
                <a class="btn btn-soft btn-sm b-btn-pill" href="<?= url('/orders/' . $id) ?>">
                  <i data-lucide="eye" width="14" height="14"></i> <?= e(t('common.view')) ?>
                </a>
                <button class="btn btn-soft btn-sm b-btn-pill" type="button" data-bs-toggle="collapse" data-bs-target="#mo-<?= $id ?>" aria-expanded="false" aria-label="<?= e(t('common.actions')) ?>">
                  <i data-lucide="more-horizontal" width="14" height="14"></i>
                </button>
              </div>
            </td>
          </tr>
          <tr class="collapse" id="mo-<?= $id ?>">
            <td colspan="6" class="bg-brand-soft">
              <div class="d-flex flex-wrap gap-2 align-items-center">
                <a class="btn btn-outline-brand btn-sm b-btn-pill" href="<?= url('/orders/' . $id . '/invoice') ?>" target="_blank" rel="noopener">
                  <i data-lucide="file-text" width="14" height="14"></i> <?= e(t('orders.invoice')) ?>
                </a>
                <form method="post" action="<?= url('/orders/' . $id . '/repeat') ?>">
                  <?= csrf_field() ?>
                  <button type="submit" class="btn btn-soft btn-sm b-btn-pill">
                    <i data-lucide="repeat" width="14" height="14"></i> <?= e(t('shop.repeat_order')) ?>
                  </button>
                </form>
                <?php if ($cancellable): ?>
                  <form method="post" action="<?= url('/orders/' . $id . '/cancel') ?>" class="d-inline-flex gap-2 align-items-center"
                        data-confirm="<?= e(t('orders.cancel_confirm')) ?>">
                    <?= csrf_field() ?>
                    <input type="text" class="form-control form-control-sm" name="cancel_reason" maxlength="255"
                           placeholder="<?= e(t('orders.cancel_reason')) ?>" style="max-width:180px;">
                    <button type="submit" class="btn btn-outline-danger btn-sm b-btn-pill">
                      <i data-lucide="x-circle" width="14" height="14"></i> <?= e(t('orders.cancel_order')) ?>
                    </button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>
