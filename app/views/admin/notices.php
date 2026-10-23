<?php
/**
 * Admin notice board — create + manage.
 * Vars: $rows, $societies (super admin only), $isSuper.
 */
$now = date('Y-m-d H:i:s');
$stateOf = function (array $a) use ($now): array {
    if ($a['publish_at'] && $a['publish_at'] > $now) {
        return ['label' => t('notices.scheduled'), 'class' => 'b-badge-blue'];
    }
    if ($a['expires_at'] && $a['expires_at'] < $now) {
        return ['label' => t('notices.expired'), 'class' => 'b-badge-grey'];
    }
    return ['label' => t('notices.live'), 'class' => 'b-badge-green'];
};
?>
<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4 fade-up">
  <div>
    <h1 class="section-title mb-1"><?= e(t('notices.manage_title')) ?></h1>
    <p class="text-muted mb-0"><?= e(t('notices.manage_sub')) ?></p>
  </div>
</div>

<div class="row g-4">
  <!-- Create form -->
  <div class="col-12 col-xl-4">
    <form class="b-card fade-up" method="post" action="<?= url('/admin/notices') ?>" enctype="multipart/form-data">
      <?= csrf_field() ?>
      <h2 class="h6 fw-bold mb-3" style="color:var(--b-forest)">
        <i data-lucide="megaphone" style="width:16px;height:16px"></i> <?= e(t('notices.create_notice')) ?>
      </h2>

      <?php if ($isSuper): ?>
        <div class="mb-3">
          <label class="form-label small fw-semibold"><?= e(t('notices.select_society')) ?></label>
          <select class="form-select" name="society_id">
            <option value=""><?= e(t('notices.platform_wide')) ?></option>
            <?php foreach ($societies as $s): ?>
              <option value="<?= (int) $s['id'] ?>" <?= old('society_id') == $s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>

      <div class="mb-3">
        <label class="form-label small fw-semibold" for="n_title"><?= e(t('tickets.subject')) ?> *</label>
        <input class="form-control" id="n_title" name="title" maxlength="200" required value="<?= e(old('title')) ?>">
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold" for="n_body"><?= e(t('notices.body')) ?> *</label>
        <textarea class="form-control" id="n_body" name="body" rows="6" required><?= e(old('body')) ?></textarea>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-6">
          <label class="form-label small fw-semibold"><?= e(t('notices.audience')) ?></label>
          <select class="form-select" name="audience">
            <option value="all"><?= e(t('notices.audience_all')) ?></option>
            <option value="residents" <?= old('audience') === 'residents' ? 'selected' : '' ?>><?= e(t('notices.audience_residents')) ?></option>
            <option value="admins" <?= old('audience') === 'admins' ? 'selected' : '' ?>><?= e(t('notices.audience_admins')) ?></option>
          </select>
        </div>
        <div class="col-6 d-flex align-items-end">
          <div class="form-check">
            <input class="form-check-input" type="checkbox" id="n_pin" name="is_pinned" value="1" <?= old('is_pinned') ? 'checked' : '' ?>>
            <label class="form-check-label small" for="n_pin"><?= e(t('notices.pin_top')) ?></label>
          </div>
        </div>
      </div>

      <div class="row g-2 mb-3">
        <div class="col-6">
          <label class="form-label small fw-semibold"><?= e(t('notices.publish_at')) ?></label>
          <input type="datetime-local" class="form-control" name="publish_at" value="<?= e(old('publish_at')) ?>">
        </div>
        <div class="col-6">
          <label class="form-label small fw-semibold"><?= e(t('notices.expires_at')) ?></label>
          <input type="datetime-local" class="form-control" name="expires_at" value="<?= e(old('expires_at')) ?>">
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label small fw-semibold"><?= e(t('notices.image')) ?></label>
        <input type="file" class="form-control" name="image" accept="image/*">
      </div>
      <div class="mb-3">
        <label class="form-label small fw-semibold"><?= e(t('notices.attachment')) ?></label>
        <input type="file" class="form-control" name="attachment" accept=".pdf,.txt,application/pdf,text/plain">
      </div>

      <div class="b-note mb-3"><i data-lucide="info"></i><span><?= e(t('notices.no_expiry_note')) ?></span></div>

      <button class="btn btn-brand w-100" type="submit">
        <i data-lucide="send" style="width:15px;height:15px"></i> <?= e(t('notices.create_notice')) ?>
      </button>
    </form>
  </div>

  <!-- List -->
  <div class="col-12 col-xl-8">
    <?php if ($rows === []): ?>
      <div class="b-card empty-state fade-up">
        <i data-lucide="megaphone"></i>
        <p class="mt-2"><?= e(t('notices.no_notices')) ?></p>
      </div>
    <?php else: ?>
      <div class="b-table fade-up d1">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead>
              <tr>
                <th><?= e(t('tickets.subject')) ?></th>
                <?php if ($isSuper): ?><th><?= e(t('notices.society')) ?></th><?php endif; ?>
                <th><?= e(t('notices.audience')) ?></th>
                <th><?= e(t('common.status')) ?></th>
                <th><?= e(t('common.date')) ?></th>
                <th class="text-end"><?= e(t('common.actions')) ?></th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($rows as $a): $st = $stateOf($a); ?>
                <tr>
                  <td>
                    <div class="d-flex align-items-center gap-2">
                      <?php if ((int) $a['is_pinned'] === 1): ?>
                        <i data-lucide="pin" style="width:14px;height:14px;color:var(--b-amber)"></i>
                      <?php endif; ?>
                      <div>
                        <div class="fw-semibold"><?= e(mb_substr((string) $a['title'], 0, 60)) ?></div>
                        <div class="text-muted small"><?= e(mb_substr((string) preg_replace('/\s+/u', ' ', strip_tags((string) $a['body'])), 0, 70)) ?>…</div>
                      </div>
                    </div>
                  </td>
                  <?php if ($isSuper): ?>
                    <td><?= $a['society_id'] ? '<span class="b-badge-blue">' . e((string) $a['society_name']) . '</span>' : '<span class="b-badge-grey">' . e(t('notices.platform_wide')) . '</span>' ?></td>
                  <?php endif; ?>
                  <td><span class="b-badge-grey"><?= e($a['audience'] === 'residents' ? t('notices.audience_residents') : ($a['audience'] === 'admins' ? t('notices.audience_admins') : t('notices.audience_all'))) ?></span></td>
                  <td>
                    <span class="<?= $st['class'] ?>"><?= e($st['label']) ?></span>
                    <div class="text-muted small mt-1">
                      <i data-lucide="eye" style="width:12px;height:12px"></i>
                      <?= e(t('notices.reads', ['n' => (string) (int) $a['read_count']])) ?>
                    </div>
                  </td>
                  <td class="small text-muted"><?= e(human_dt($a['created_at'])) ?></td>
                  <td class="text-end">
                    <div class="d-inline-flex gap-1">
                      <form method="post" action="<?= url('/admin/notices/' . (int) $a['id'] . '/pin') ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-soft btn-sm b-btn-pill" title="<?= e((int) $a['is_pinned'] === 1 ? t('notices.unpin') : t('notices.pin_top')) ?>">
                          <i data-lucide="<?= (int) $a['is_pinned'] === 1 ? 'pin-off' : 'pin' ?>" style="width:14px;height:14px"></i>
                        </button>
                      </form>
                      <form method="post" action="<?= url('/admin/notices/' . (int) $a['id'] . '/delete') ?>" data-confirm="<?= e(t('notices.delete_confirm')) ?>">
                        <?= csrf_field() ?>
                        <button class="btn btn-soft btn-sm b-btn-pill text-danger" title="<?= e(t('common.delete')) ?>">
                          <i data-lucide="trash-2" style="width:14px;height:14px"></i>
                        </button>
                      </form>
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
</div>
