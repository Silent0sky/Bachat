<?php
/** Society admin: service campaigns + create form. Vars: $campaigns, $services, $vendors */
$sname = fn (array $s): string => (string) ($s['name_' . lang()] ?? '') !== '' ? (string) $s['name_' . lang()] : (string) $s['name_en'];
?>
<div class="d-flex justify-content-between align-items-end flex-wrap gap-2 mb-4">
  <div>
    <h1 class="section-title mb-1"><?= e(t('bookings.campaign_admin_title')) ?></h1>
    <p class="text-muted small mb-0"><?= e(t('bookings.campaign_admin_sub')) ?></p>
  </div>
  <a class="btn btn-soft btn-sm b-btn-pill" href="<?= url('/admin/bookings') ?>">
    <i data-lucide="calendar-check" width="14" height="14"></i> <?= e(t('bookings.admin_title')) ?>
  </a>
</div>

<!-- ============ Create form ============ -->
<div class="b-card mb-4">
  <button class="btn btn-brand b-btn-pill w-100 d-lg-none" type="button" data-bs-toggle="collapse" data-bs-target="#newDeal" aria-expanded="false">
    <i data-lucide="plus" width="14" height="14"></i> <?= e(t('bookings.campaign_create')) ?>
  </button>
  <h5 class="d-none d-lg-block fw-bold text-brand mb-3"><i data-lucide="megaphone" class="me-1"></i><?= e(t('bookings.campaign_create')) ?></h5>

  <div class="collapse d-lg-block" id="newDeal">
    <form method="post" action="<?= url('/admin/service-campaigns') ?>" class="row g-3">
      <?= csrf_field() ?>

      <div class="col-md-6">
        <label class="form-label small fw-semibold"><?= e(t('bookings.campaign_service')) ?> *</label>
        <select class="form-select" name="service_id" id="svcSelect" required>
          <option value="">—</option>
          <?php foreach ($services as $s): ?>
            <option value="<?= (int) $s['id'] ?>" data-price="<?= e((string) (float) $s['base_price']) ?>">
              <?= e($sname($s)) ?> (<?= e(money($s['base_price'])) ?>)
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-md-6">
        <label class="form-label small fw-semibold"><?= e(t('bookings.campaign_title_field')) ?> *</label>
        <input type="text" class="form-control" name="title" maxlength="180" required>
      </div>

      <div class="col-12">
        <label class="form-label small fw-semibold"><?= e(t('bookings.campaign_description')) ?></label>
        <textarea class="form-control" name="description" rows="2" maxlength="1000"></textarea>
      </div>

      <div class="col-6 col-md-4 col-lg-3">
        <label class="form-label small fw-semibold"><?= e(t('bookings.campaign_deadline')) ?></label>
        <input type="datetime-local" class="form-control" name="deadline">
      </div>

      <div class="col-6 col-md-4 col-lg-3">
        <label class="form-label small fw-semibold"><?= e(t('bookings.campaign_min_participants')) ?> *</label>
        <input type="number" class="form-control" name="min_participants" min="2" max="500" value="5" required>
      </div>

      <div class="col-6 col-md-4 col-lg-3">
        <label class="form-label small fw-semibold"><?= e(t('bookings.campaign_discount')) ?> *</label>
        <div class="input-group">
          <input type="number" class="form-control" name="group_discount_percent" min="0" max="90" step="0.5" value="10" required>
          <span class="input-group-text">%</span>
        </div>
      </div>

      <div class="col-6 col-md-4 col-lg-3">
        <label class="form-label small fw-semibold"><?= e(t('bookings.campaign_estimated')) ?></label>
        <input type="number" class="form-control" name="estimated_price" min="0" step="0.01">
      </div>

      <div class="col-6 col-md-4 col-lg-3">
        <label class="form-label small fw-semibold"><?= e(t('bookings.campaign_slot_date')) ?></label>
        <input type="date" class="form-control" name="slot_date" min="<?= date('Y-m-d') ?>">
      </div>

      <div class="col-6 col-md-4 col-lg-3">
        <label class="form-label small fw-semibold"><?= e(t('bookings.campaign_slot_time')) ?></label>
        <input type="time" class="form-control" name="slot_time" value="10:00">
      </div>

      <div class="col-6 col-md-4 col-lg-3">
        <label class="form-label small fw-semibold"><?= e(t('bookings.campaign_vendor')) ?></label>
        <select class="form-select" name="vendor_id">
          <option value=""><?= e(t('common.none')) ?></option>
          <?php foreach ($vendors as $v): ?>
            <option value="<?= (int) $v['id'] ?>"><?= e($v['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-12">
        <button type="submit" class="btn btn-brand b-btn-pill">
          <i data-lucide="megaphone" width="15" height="15"></i> <?= e(t('bookings.campaign_create_btn')) ?>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ============ Campaign list ============ -->
<?php if (empty($campaigns)): ?>
  <div class="empty-state b-card">
    <i data-lucide="wrench"></i>
    <p>No service campaigns yet — create the first group deal above.</p>
  </div>
<?php else: ?>
<div class="b-table">
  <div class="table-responsive">
    <table class="table align-middle mb-0">
      <thead>
        <tr>
          <th>Deal</th>
          <th>Service</th>
          <th><?= e(t('bookings.campaign_participants')) ?></th>
          <th>Pricing</th>
          <th><?= e(t('common.deadline')) ?></th>
          <th><?= e(t('common.status')) ?></th>
          <th class="text-end"><?= e(t('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($campaigns as $c):
            $id = (int) $c['id'];
            $canFinalize = in_array($c['status'], ['open', 'pricing'], true);
            $canClose    = $c['status'] === 'confirm_open';
            $canCancel   = in_array($c['status'], ['open', 'pricing'], true);
            $suggest     = round(((float) $c['base_price']) * (1 - ((float) $c['group_discount_percent']) / 100), 2);
            $needAction  = $canFinalize || $canClose || $canCancel;
        ?>
        <tr>
          <td>
            <div class="fw-semibold"><?= e($c['title']) ?></div>
            <div class="small text-muted"><?= e(t('bookings.campaign_participants')) ?> min: <?= (int) $c['min_participants'] ?>
              · confirmed: <?= (int) $c['confirmed_count'] ?></div>
          </td>
          <td class="small">
            <?= e($sname($c)) ?>
            <div class="text-muted"><?= e(money($c['base_price'])) ?> base</div>
          </td>
          <td>
            <strong><?= (int) $c['participants'] ?></strong>
            <div class="progress b-progress mt-1" style="height:6px; min-width:70px;" role="progressbar"
                 aria-valuenow="<?= (int) $c['participants'] ?>" aria-valuemin="0" aria-valuemax="<?= (int) max(1, $c['min_participants']) ?>">
              <div class="progress-bar b-progress-bar" style="width:<?= min(100, (int) round($c['participants'] / max(1, (int) $c['min_participants']) * 100)) ?>%"></div>
            </div>
          </td>
          <td class="small">
            <?= e(qty($c['group_discount_percent'])) ?>% off
            <div class="text-muted">
              <?= e($c['final_price'] !== null
                    ? t('bookings.campaign_final_price_set') . ': ' . money($c['final_price'])
                    : ($c['estimated_price'] !== null ? '~' . money($c['estimated_price']) : '—')) ?>
            </div>
          </td>
          <td class="small"><?= $c['deadline'] ? e(human_dt($c['deadline'])) : '—' ?></td>
          <td><span class="<?= status_badge($c['status']) ?>"><?= e(ucfirst((string) $c['status'])) ?></span></td>
          <td class="text-end">
            <?php if ($canFinalize): ?>
              <button class="btn btn-amber btn-sm b-btn-pill" type="button" data-bs-toggle="collapse" data-bs-target="#fin-<?= $id ?>">
                <i data-lucide="badge-percent" width="14" height="14"></i> <?= e(t('bookings.campaign_finalize')) ?>
              </button>
            <?php endif; ?>
            <?php if ($canClose): ?>
              <form method="post" action="<?= url('/admin/service-campaigns/' . $id . '/close') ?>" class="d-inline"
                    data-confirm="Close this deal? Residents can no longer confirm.">
                <?= csrf_field() ?>
                <button class="btn btn-soft btn-sm b-btn-pill"><?= e(t('bookings.campaign_close')) ?></button>
              </form>
            <?php endif; ?>
            <?php if ($needAction): ?>
              <button class="btn btn-soft btn-sm b-btn-pill" type="button" data-bs-toggle="collapse" data-bs-target="#sc-<?= $id ?>">
                <i data-lucide="more-horizontal" width="14" height="14"></i>
              </button>
            <?php endif; ?>
          </td>
        </tr>

        <?php if ($canFinalize): ?>
        <tr class="collapse" id="fin-<?= $id ?>">
          <td colspan="7" class="bg-brand-soft">
            <form method="post" action="<?= url('/admin/service-campaigns/' . $id . '/finalize') ?>" class="row g-2 align-items-end">
              <?= csrf_field() ?>
              <div class="col-md-5">
                <label class="form-label small fw-semibold mb-1"><?= e(t('bookings.campaign_final_price')) ?> *</label>
                <div class="input-group">
                  <span class="input-group-text">₹</span>
                  <input type="number" class="form-control" name="final_price" min="1" step="0.01"
                         value="<?= e((string) ($c['estimated_price'] !== null ? $c['estimated_price'] : $suggest)) ?>" required>
                </div>
                <div class="small text-muted mt-1"><?= e(t('bookings.campaign_finalize_hint', ['price' => money($suggest)])) ?></div>
              </div>
              <div class="col-md-4">
                <button type="submit" class="btn btn-amber b-btn-pill">
                  <i data-lucide="megaphone" width="14" height="14"></i> <?= e(t('bookings.campaign_finalize')) ?>
                </button>
              </div>
            </form>
          </td>
        </tr>
        <?php endif; ?>

        <?php if ($canCancel): ?>
        <tr class="collapse" id="sc-<?= $id ?>">
          <td colspan="7" class="bg-brand-soft">
            <form method="post" action="<?= url('/admin/service-campaigns/' . $id . '/cancel') ?>"
                  data-confirm="Cancel this deal? Interested residents will be notified." class="d-flex gap-2 align-items-center">
              <?= csrf_field() ?>
              <button type="submit" class="btn btn-outline-danger btn-sm b-btn-pill">
                <i data-lucide="x-circle" width="14" height="14"></i> <?= e(t('bookings.campaign_cancel')) ?>
              </button>
              <span class="small text-muted">Interested residents will be notified — no payment is due.</span>
            </form>
          </td>
        </tr>
        <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
