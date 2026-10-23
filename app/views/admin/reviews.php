<?php
/**
 * Admin — review moderation.
 * Vars: $reviews, $tab, $isSuper, $pendingCount, $reportedCount.
 */
$contextOf = function (array $r): array {
    return match ($r['target_type']) {
        'product'  => ['icon' => 'shopping-basket', 'type' => t('reviews.context_product'),
                       'name' => product_name(['name_en' => $r['p_name_en'] ?? null, 'name_hi' => $r['p_name_hi'] ?? null, 'name_mr' => $r['p_name_mr'] ?? null]) ?: ('#' . $r['target_id'])],
        'service'  => ['icon' => 'wrench', 'type' => t('reviews.context_service'),
                       'name' => product_name(['name_en' => $r['s_name_en'] ?? null, 'name_hi' => $r['s_name_hi'] ?? null, 'name_mr' => $r['s_name_mr'] ?? null]) ?: ('#' . $r['target_id'])],
        'vendor'   => ['icon' => 'store', 'type' => t('reviews.context_vendor'), 'name' => (string) ($r['vendor_name'] ?? ('#' . $r['target_id']))],
        default    => ['icon' => 'bike', 'type' => t('reviews.context_delivery'), 'name' => (string) ($r['delivery_name'] ?? ('#' . $r['target_id']))],
    };
};
$tabs = [
    'pending'  => [t('reviews.tab_pending'), $pendingCount],
    'reported' => [t('reviews.tab_reported'), $reportedCount],
    'approved' => [t('reviews.tab_approved'), null],
    'rejected' => [t('reviews.tab_rejected'), null],
    'all'      => [t('reviews.tab_all'), null],
];
$base = url($isSuper ? '/super/reviews' : '/admin/reviews');
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4 fade-up">
  <div>
    <h1 class="section-title mb-1"><?= e(t('reviews.moderation_title')) ?></h1>
    <p class="text-muted mb-0"><?= e(t('reviews.moderation_sub')) ?></p>
  </div>
</div>

<div class="d-flex flex-wrap gap-2 mb-4 fade-up d1">
  <?php foreach ($tabs as $key => [$label, $count]): ?>
    <a class="chip <?= $tab === $key ? 'chip-active' : '' ?>" href="<?= $base ?>?tab=<?= e($key) ?>">
      <?= e($label) ?><?= $count !== null && $count > 0 ? ' <span class="badge rounded-pill text-bg-light">' . (int) $count . '</span>' : '' ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($reviews === []): ?>
  <div class="b-card empty-state fade-up">
    <i data-lucide="star"></i>
    <p class="mt-2"><?= e(t('reviews.no_reviews')) ?></p>
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($reviews as $i => $r): $c = $contextOf($r); ?>
      <div class="col-12 col-xl-6">
        <div class="b-card h-100 fade-up<?= $i < 4 ? ' d' . ($i % 4 + 1) : '' ?>">
          <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <div class="d-flex align-items-center gap-2">
              <span class="b-avatar"><i data-lucide="<?= e($c['icon']) ?>" style="width:15px;height:15px"></i></span>
              <div>
                <div class="fw-semibold small" style="color:var(--b-forest)"><?= e($c['name']) ?></div>
                <div class="text-muted" style="font-size:12px"><?= e($c['type']) ?></div>
              </div>
            </div>
            <div class="text-end">
              <span class="<?= status_badge((string) $r['status']) ?>"><?= e(t('reviews.tab_' . $r['status'])) ?></span>
              <?php if ((int) $r['report_count'] > 0): ?>
                <div class="mt-1"><span class="b-badge-red"><i data-lucide="flag" style="width:12px;height:12px"></i> <?= e(t('reviews.report_count', ['n' => (string) (int) $r['report_count']])) ?></span></div>
              <?php endif; ?>
            </div>
          </div>

          <div class="rating-stars mb-1"><?= str_repeat('★', (int) $r['rating']) . str_repeat('☆', 5 - (int) $r['rating']) ?></div>
          <?php if (!empty($r['title'])): ?><div class="fw-semibold"><?= e($r['title']) ?></div><?php endif; ?>
          <?php if (!empty($r['body'])): ?><p class="text-muted small mb-2" style="white-space:pre-line"><?= e($r['body']) ?></p><?php endif; ?>
          <?php if (!empty($r['photo'])): ?>
            <img class="rounded-3 mb-2" style="max-height:130px" src="<?= e(upload_url($r['photo'])) ?>" alt="">
          <?php endif; ?>

          <div class="text-muted small mb-2">
            <?= e(t('reviews.by', ['name' => $r['author']])) ?> · <?= e(human_dt($r['created_at'])) ?>
            <?php if ($r['order_id']): ?> · <?= e(t('reviews.order_ref')) ?> #<?= (int) $r['order_id'] ?><?php endif; ?>
            <?php if ($r['booking_id']): ?> · <?= e(t('reviews.booking_ref')) ?> #<?= (int) $r['booking_id'] ?><?php endif; ?>
          </div>

          <?php if (!empty($r['reply'])): ?>
            <div class="b-note mb-3">
              <i data-lucide="message-square"></i>
              <span><strong><?= e(t('reviews.reply_label')) ?>:</strong> <?= e($r['reply']) ?></span>
            </div>
          <?php endif; ?>

          <div class="d-flex gap-2 mb-2">
            <form method="post" action="<?= url('/admin/reviews/' . (int) $r['id'] . '/status') ?>">
              <?= csrf_field() ?>
              <button class="btn btn-forest btn-sm b-btn-pill" name="status" value="approved" <?= $r['status'] === 'approved' ? 'disabled' : '' ?>>
                <i data-lucide="check" style="width:14px;height:14px"></i> <?= e(t('reviews.approve')) ?>
              </button>
            </form>
            <form method="post" action="<?= url('/admin/reviews/' . (int) $r['id'] . '/status') ?>">
              <?= csrf_field() ?>
              <button class="btn btn-soft btn-sm b-btn-pill text-danger" name="status" value="rejected" <?= $r['status'] === 'rejected' ? 'disabled' : '' ?>>
                <i data-lucide="x" style="width:14px;height:14px"></i> <?= e(t('reviews.reject')) ?>
              </button>
            </form>
          </div>

          <form method="post" action="<?= url('/admin/reviews/' . (int) $r['id'] . '/reply') ?>">
            <?= csrf_field() ?>
            <div class="input-group input-group-sm">
              <input class="form-control" name="reply" maxlength="1000" placeholder="<?= e(t('reviews.reply_label')) ?>…" required>
              <button class="btn btn-outline-brand" type="submit"><?= e(t('reviews.reply_btn')) ?></button>
            </div>
          </form>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
