<?php
/**
 * Resident notice board.
 * Vars: $notices (announcements visible to my society), $active.
 */

/** Local excerpt helper (plain text, trimmed). */
if (!function_exists('bach_notice_excerpt')) {
    function bach_notice_excerpt(string $text, int $len = 150): string
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', strip_tags($text)));
        return mb_strlen($text) > $len ? mb_substr($text, 0, $len) . '…' : $text;
    }
}
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4 fade-up">
  <div>
    <h1 class="section-title mb-1"><?= e(t('notices.title')) ?></h1>
    <p class="text-muted mb-0"><?= e(t('notices.no_expiry_note')) ?></p>
  </div>
</div>

<?php if ($notices === []): ?>
  <div class="b-card empty-state fade-up">
    <i data-lucide="megaphone"></i>
    <p class="mt-2"><?= e(t('notices.no_notices')) ?></p>
  </div>
<?php else: ?>
  <div class="row g-3">
    <?php foreach ($notices as $i => $n): ?>
      <?php $when = $n['publish_at'] ?: $n['created_at']; ?>
      <div class="col-12 col-md-6 col-xl-4">
        <article class="b-card b-card-hover h-100 d-flex flex-column fade-up<?= $i < 4 ? ' d' . min($i + 1, 4) : '' ?>">
          <div class="d-flex justify-content-between align-items-start gap-2 mb-2">
            <span class="b-badge-grey"><i data-lucide="calendar" style="width:13px;height:13px"></i> <?= e(human_date($when)) ?></span>
            <?php if ((int) $n['is_pinned'] === 1): ?>
              <span class="b-badge-amber"><i data-lucide="pin" style="width:13px;height:13px"></i> <?= e(t('notices.pinned')) ?></span>
            <?php endif; ?>
          </div>
          <h2 class="h6 fw-bold text-forest mb-2" style="color:var(--b-forest)">
            <a class="text-decoration-none" style="color:inherit" href="<?= url('/notices/' . (int) $n['id']) ?>"><?= e($n['title']) ?></a>
          </h2>
          <p class="text-muted small mb-3 flex-grow-1"><?= e(bach_notice_excerpt((string) $n['body'])) ?></p>
          <div class="d-flex justify-content-between align-items-center pt-2 border-top">
            <?php if (!empty($n['attachment']) || !empty($n['image'])): ?>
              <span class="b-badge-blue"><i data-lucide="paperclip" style="width:13px;height:13px"></i> <?= e(t('common.download')) ?></span>
            <?php else: ?>
              <span class="text-muted small"><?= e($n['author'] ? t('notices.author', ['name' => $n['author']]) : '') ?></span>
            <?php endif; ?>
            <a class="btn btn-outline-brand btn-sm b-btn-pill" href="<?= url('/notices/' . (int) $n['id']) ?>">
              <?= e(t('notices.read_more')) ?> <i data-lucide="arrow-right" style="width:14px;height:14px"></i>
            </a>
          </div>
        </article>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
