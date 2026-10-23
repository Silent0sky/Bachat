<?php
/**
 * Demand board — resident. Vars: $votes, $categories
 */
?>
<h1 class="section-title mt-4"><?= e(t('demand.title')) ?></h1>
<p class="text-muted"><?= e(t('demand.sub')) ?></p>

<div class="b-note mb-4"><i data-lucide="info"></i><span><?= e(t('demand.note')) ?></span></div>

<?php /* ---------- NEW REQUEST ---------- */ ?>
<div class="b-card mb-4">
  <h5 class="fw-bold mb-3"><i data-lucide="megaphone" class="me-1 text-brand"></i><?= e(t('demand.request_something')) ?></h5>
  <form method="post" action="<?= url('/demand') ?>">
    <?= csrf_field() ?>
    <div class="row g-3">
      <div class="col-12 col-md-3">
        <label class="form-label small fw-semibold"><?= e(t('demand.item_type')) ?></label>
        <select class="form-select" name="item_type">
          <option value="product"><?= e(t('demand.type_product')) ?></option>
          <option value="service"><?= e(t('demand.type_service')) ?></option>
        </select>
      </div>
      <div class="col-12 col-md-5">
        <label class="form-label small fw-semibold"><?= e(t('demand.title_label')) ?></label>
        <input type="text" class="form-control" name="title" maxlength="180" required value="<?= e(old('title')) ?>" placeholder="<?= e(t('demand.title_ph')) ?>">
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label small fw-semibold"><?= e(t('demand.category')) ?></label>
        <select class="form-select" name="category_id">
          <option value=""><?= e(t('common.none')) ?></option>
          <?php foreach ($categories as $cat): ?>
            <option value="<?= (int) $cat['id'] ?>"><?= e(product_name($cat)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12">
        <label class="form-label small fw-semibold"><?= e(t('demand.details_label')) ?></label>
        <textarea class="form-control" name="details" rows="2" maxlength="600" placeholder="<?= e(t('demand.details_ph')) ?>"><?= e(old('details')) ?></textarea>
      </div>
    </div>
    <button type="submit" class="btn btn-brand b-btn-pill mt-3"><i data-lucide="send" width="16" height="16" class="me-1"></i><?= e(t('demand.post')) ?></button>
  </form>
</div>

<?php /* ---------- REQUEST LIST ---------- */ ?>
<?php if (!$votes): ?>
<div class="empty-state b-card my-4"><i data-lucide="vote"></i><p class="mt-2"><?= e(t('demand.empty')) ?></p></div>
<?php else: ?>
<h2 class="h6 fw-bold text-muted text-uppercase mt-4 mb-2"><?= e(t('demand.list_title')) ?></h2>
<?php foreach ($votes as $v): ?>
  <div class="b-card mb-2 py-3 fade-up">
    <div class="d-flex align-items-start gap-3">
      <div class="text-center flex-shrink-0" style="min-width:64px;">
        <?php if ($v['my_vote']): ?>
          <span class="chip chip-active d-block mb-1"><i data-lucide="check" width="14" height="14"></i> <?= (int) $v['vote_count'] ?></span>
          <span class="small text-muted"><?= e(t('demand.voted_short')) ?></span>
        <?php elseif (in_array($v['status'], ['open', 'validated'], true)): ?>
          <form method="post" action="<?= url('/demand/' . $v['id'] . '/vote') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-sm btn-outline-brand b-btn-pill d-block mb-1 w-100">
              <i data-lucide="chevron-up" width="14" height="14"></i> <?= (int) $v['vote_count'] ?>
            </button>
            <span class="small text-muted"><?= e(t('demand.vote')) ?></span>
          </form>
        <?php else: ?>
          <span class="chip d-block mb-1"><?= (int) $v['vote_count'] ?></span>
          <span class="small text-muted"><?= e(t('demand.votes')) ?></span>
        <?php endif; ?>
      </div>
      <div class="flex-grow-1">
        <div class="d-flex flex-wrap align-items-center gap-2">
          <span class="fw-semibold"><?= e($v['title']) ?></span>
          <span class="<?= status_badge($v['status']) ?>"><?= e(ucfirst($v['status'])) ?></span>
          <span class="chip" style="padding:2px 10px;font-size:11px;">
            <i data-lucide="<?= $v['item_type'] === 'service' ? 'wrench' : 'package' ?>" width="12" height="12"></i>
            <?= e($v['item_type'] === 'service' ? t('demand.type_service') : t('demand.type_product')) ?>
          </span>
          <?php if ($v['society_id'] === null): ?><span class="b-badge-blue"><?= e(t('campaigns.platform_wide')) ?></span><?php endif; ?>
        </div>
        <?php if ($v['details']): ?><p class="small text-muted mb-1 mt-1"><?= nl2br(e($v['details'])) ?></p><?php endif; ?>
        <div class="small text-muted">
          <?= e(t('demand.by', ['name' => $v['creator_name']])) ?> · <?= e(human_dt($v['created_at'])) ?>
          <?php if (!empty($v['category_name'])): ?> · <?= e($v['category_name']) ?><?php endif; ?>
        </div>
      </div>
    </div>
  </div>
<?php endforeach; ?>
<?php endif; ?>

<div class="mb-5"></div>
