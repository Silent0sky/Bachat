<?php
/**
 * Notification center (shared by all roles).
 * Vars: $rows (latest 50), $unread.
 */
$typeIcon = [
    'order'    => 'package',
    'ticket'   => 'life-buoy',
    'notice'   => 'megaphone',
    'review'   => 'star',
    'campaign' => 'megaphone',
    'booking'  => 'calendar-check',
    'payment'  => 'badge-indian-rupee',
    'general'  => 'bell',
];
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4 fade-up">
  <div>
    <h1 class="section-title mb-1"><?= e(t('notifications.title')) ?></h1>
    <p class="text-muted mb-0">
      <?= $unread > 0 ? e(t('notifications.unread', ['n' => (string) $unread])) : e(t('notifications.all_caught')) ?>
    </p>
  </div>
  <?php if ($unread > 0): ?>
    <form method="post" action="<?= url('/notifications/read-all') ?>">
      <?= csrf_field() ?>
      <button class="btn btn-soft b-btn-pill" type="submit">
        <i data-lucide="check-check" style="width:15px;height:15px"></i> <?= e(t('notifications.mark_all')) ?>
      </button>
    </form>
  <?php endif; ?>
</div>

<?php if ($rows === []): ?>
  <div class="b-card empty-state fade-up">
    <i data-lucide="bell-off"></i>
    <p class="mt-2 fw-semibold"><?= e(t('notifications.empty')) ?></p>
    <p><?= e(t('notifications.empty_sub')) ?></p>
  </div>
<?php else: ?>
  <div class="d-flex flex-column gap-2">
    <?php foreach ($rows as $i => $n): $isUnread = ((int) $n['is_read']) === 0; ?>
      <div class="list-tile fade-up<?= $i < 4 ? ' d' . ($i + 1) : '' ?> <?= $isUnread ? 'border-0' : '' ?>"
           style="<?= $isUnread ? 'border-color:var(--b-green);background:var(--b-mint,#EAF6EF)' : '' ?>">
        <span class="b-avatar" style="flex:0 0 auto">
          <i data-lucide="<?= e($typeIcon[$n['type']] ?? 'bell') ?>" style="width:15px;height:15px"></i>
        </span>

        <div class="flex-grow-1" style="min-width:0">
          <div class="d-flex align-items-center gap-2">
            <?php if ($isUnread): ?><span class="badge rounded-circle p-1" style="background:var(--b-green);width:8px;height:8px"></span><?php endif; ?>
            <span class="<?= $isUnread ? 'fw-bold' : '' ?> text-truncate"><?= e($n['title']) ?></span>
            <span class="b-badge-grey d-none d-sm-inline-flex" style="font-size:10.5px;padding:2px 8px">
              <?= e(t('notifications.type_' . (in_array($n['type'], ['order','ticket','notice','review','campaign','booking','general'], true) ? $n['type'] : 'general'))) ?>
            </span>
          </div>
          <?php if (!empty($n['body'])): ?>
            <div class="text-muted small text-truncate"><?= e($n['body']) ?></div>
          <?php endif; ?>
          <div class="text-muted" style="font-size:11.5px"><?= e(human_dt($n['created_at'])) ?></div>
        </div>

        <div class="d-flex align-items-center gap-1" style="flex:0 0 auto">
          <?php if ($isUnread): ?>
            <form method="post" action="<?= url('/notifications/' . (int) $n['id'] . '/read') ?>">
              <?= csrf_field() ?>
              <button class="btn btn-soft btn-sm b-btn-pill" type="submit" title="<?= e(t('notifications.mark_read')) ?>">
                <i data-lucide="check" style="width:14px;height:14px"></i>
              </button>
            </form>
          <?php endif; ?>
          <?php if (!empty($n['link'])): ?>
            <a class="btn btn-soft btn-sm b-btn-pill" href="<?= url((string) $n['link']) ?>"
               data-notif-read="<?= (int) $n['id'] ?>">
              <?= e(t('notifications.open')) ?> <i data-lucide="chevron-right" style="width:14px;height:14px"></i>
            </a>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

  <script>
    /* Clicking "Open" marks the notification read via AJAX, then navigates. */
    document.querySelectorAll('[data-notif-read]').forEach(function (a) {
      a.addEventListener('click', function (ev) {
        ev.preventDefault();
        var id = a.getAttribute('data-notif-read');
        var go = function () { window.location.href = a.href; };
        if (window.bachAjax) {
          window.bachAjax(window.BACHAT_URL.replace(/\/$/, '') + '/notifications/' + id + '/read').then(go).catch(go);
        } else {
          go();
        }
      });
    });
  </script>
<?php endif; ?>
