<?php
/**
 * Shopping reminders (Agent A) — fired by platform cron.
 * Vars: $rems, $lists.
 */
?>
<div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h1 class="section-title"><?= e(t('shop.reminders_title')) ?></h1>
    <p class="text-muted small mb-0"><?= e(t('shop.reminders_sub')) ?></p>
  </div>
  <a href="<?= url('/lists') ?>" class="btn btn-soft b-btn-pill btn-sm">
    <i data-lucide="list-checks" class="me-1"></i><?= e(t('nav.lists')) ?>
  </a>
</div>

<div class="row g-4">
  <div class="col-12 col-lg-4">
    <div class="b-card fade-up">
      <h2 class="fs-6 fw-bold mb-3"><?= e(t('common.add')) ?></h2>
      <form method="post" action="<?= url('/reminders') ?>">
        <?= csrf_field() ?>
        <div class="mb-3">
          <label class="form-label fw-semibold small" for="rm-title"><?= e(t('shop.reminder_title_field')) ?></label>
          <input type="text" class="form-control" id="rm-title" name="title" value="<?= e(old('title')) ?>"
                 placeholder="Rice + atta refill" maxlength="150" required>
        </div>
        <div class="row g-2 mb-3">
          <div class="col-6">
            <label class="form-label fw-semibold small" for="rm-freq"><?= e(t('shop.frequency')) ?></label>
            <select class="form-select" id="rm-freq" name="frequency">
              <option value="weekly" <?= old('frequency', 'weekly') === 'weekly' ? 'selected' : '' ?>><?= e(t('shop.freq_weekly')) ?></option>
              <option value="monthly" <?= old('frequency') === 'monthly' ? 'selected' : '' ?>><?= e(t('shop.freq_monthly')) ?></option>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label fw-semibold small" for="rm-date"><?= e(t('shop.next_run')) ?></label>
            <input type="date" class="form-control" id="rm-date" name="next_run"
                   value="<?= e(old('next_run', date('Y-m-d', strtotime('+7 days')))) ?>" required>
          </div>
        </div>
        <?php if ($lists): ?>
        <div class="mb-3">
          <label class="form-label fw-semibold small" for="rm-list"><?= e(t('shop.link_list')) ?></label>
          <select class="form-select" id="rm-list" name="list_id">
            <option value="">—</option>
            <?php foreach ($lists as $l): ?>
              <option value="<?= (int) $l['id'] ?>" <?= (int) old('list_id', 0) === (int) $l['id'] ? 'selected' : '' ?>><?= e($l['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
        <button type="submit" class="btn btn-brand w-100 b-btn-pill">
          <i data-lucide="alarm-clock" class="me-1"></i><?= e(t('common.save')) ?>
        </button>
      </form>
    </div>
  </div>

  <div class="col-12 col-lg-8">
    <div class="b-note mb-3">
      <i data-lucide="info"></i>
      <span><?= e(t('shop.reminder_note')) ?></span>
    </div>

    <?php if (!$rems): ?>
      <div class="b-card empty-state">
        <i data-lucide="alarm-clock"></i>
        <p><?= e(t('shop.no_reminders')) ?></p>
      </div>
    <?php else: ?>
      <?php foreach ($rems as $r): ?>
      <div class="b-card d-flex align-items-center gap-3 mb-2 fade-up">
        <span class="b-avatar flex-shrink-0"><i data-lucide="alarm-clock"></i></span>
        <div class="flex-grow-1 min-w-0">
          <div class="fw-semibold text-truncate"><?= e($r['title']) ?></div>
          <div class="small text-muted">
            <?= e(t('shop.next_run')) ?>: <strong><?= e(human_date($r['next_run'])) ?></strong>
            · <?= e($r['frequency'] === 'weekly' ? t('shop.freq_weekly') : t('shop.freq_monthly')) ?>
            <?= !empty($r['list_name']) ? ' · ' . e($r['list_name']) : '' ?>
          </div>
        </div>
        <?php if ((int) $r['is_active'] === 1): ?>
          <span class="b-badge-green flex-shrink-0">Active</span>
        <?php else: ?>
          <span class="b-badge-grey flex-shrink-0">Off</span>
        <?php endif; ?>
        <form method="post" action="<?= url('/reminders/' . (int) $r['id'] . '/delete') ?>" data-confirm="<?= e(t('shop.reminder_deleted') . '?') ?>">
          <?= csrf_field() ?>
          <button type="submit" class="btn btn-soft btn-sm" title="<?= e(t('common.delete')) ?>">
            <i data-lucide="trash-2" style="width:15px;height:15px;"></i>
          </button>
        </form>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
