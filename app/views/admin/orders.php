<?php
/**
 * Society orders & Combined Customer Products manifest.
 * Vars: $rows, $combinedProducts, $breakdownByProduct, $kpis, $view, $fStatus, $fType, $fQ
 */
$view               = $view ?? 'orders';
$isCombined         = $view === 'combined';
$rows               = $rows ?? [];
$combinedProducts   = $combinedProducts ?? [];
$breakdownByProduct = $breakdownByProduct ?? [];
$kpis               = $kpis ?? ['total_products' => count($combinedProducts), 'total_units' => 0, 'total_amount' => 0, 'total_customers' => 0];
$fStatus            = $fStatus ?? '';
$fType              = $fType ?? '';
$fQ                 = $fQ ?? '';

$statuses = [
    'pending'          => 'Pending',
    'confirmed'        => 'Confirmed',
    'packed'           => 'Packed',
    'ready'            => 'Ready',
    'out_for_delivery' => 'Out for delivery',
    'delivered'        => 'Delivered',
    'cancelled'        => 'Cancelled',
    'failed'           => 'Failed',
];
$types = [
    'regular'  => 'Regular',
    'campaign' => 'Campaign',
    'repeat'   => 'Repeat',
];
?>

<!-- Header -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
  <div>
    <h1 class="section-title mb-1">
      <?= $isCombined ? 'Combined Customer Products' : 'Orders' ?>
    </h1>
    <p class="text-muted mb-0">
      <?= $isCombined
        ? 'Consolidated view of all products ordered across residents (Cust 1, Cust 2, etc.) for batch procurement & tower packing.'
        : 'Manage individual resident orders, track fulfillment status, and view customer invoices.' ?>
    </p>
  </div>
  <div class="d-flex align-items-center gap-2">
    <?php if ($isCombined): ?>
      <button type="button" onclick="window.print()" class="btn btn-outline-brand b-btn-pill">
        <i data-lucide="printer" width="16" height="16" class="me-1"></i> Print Packing List
      </button>
    <?php endif; ?>
  </div>
</div>

<!-- Switch Tabs (Orders vs Combined Products) -->
<div class="d-flex flex-wrap gap-2 mb-4 p-1 bg-white rounded-pill border shadow-xs" style="width: fit-content; max-width: 100%;">
  <a href="<?= url('/admin/orders') ?>" class="btn b-btn-pill <?= !$isCombined ? 'btn-brand' : 'btn-ghost text-muted' ?> px-3 py-1-5 fw-semibold" style="font-size: 13px;">
    <i data-lucide="package" width="15" height="15" class="me-1"></i> Individual Orders (<?= count($rows) ?>)
  </a>
  <a href="<?= url('/admin/orders?view=combined' . ($fStatus ? '&status=' . urlencode($fStatus) : '')) ?>" class="btn b-btn-pill <?= $isCombined ? 'btn-brand' : 'btn-ghost text-muted' ?> px-3 py-1-5 fw-semibold" style="font-size: 13px;">
    <i data-lucide="boxes" width="15" height="15" class="me-1"></i> Combined Products (<?= count($combinedProducts) ?>)
  </a>
</div>

<?php if ($isCombined): ?>
  <!-- ==================== COMBINED PRODUCTS VIEW ==================== -->

  <!-- KPI Summary Cards -->
  <div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
      <div class="b-card stat-card h-100">
        <div class="stat-label">Unique Products</div>
        <div class="stat-value"><?= (int) $kpis['total_products'] ?></div>
        <div class="stat-icon icon-brand"><i data-lucide="shopping-bag"></i></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="b-card stat-card h-100">
        <div class="stat-label">Total Units to Pack</div>
        <div class="stat-value"><?= qty($kpis['total_units']) ?></div>
        <div class="stat-icon icon-emerald"><i data-lucide="package-check"></i></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="b-card stat-card h-100">
        <div class="stat-label">Combined Order Value</div>
        <div class="stat-value"><?= money($kpis['total_amount']) ?></div>
        <div class="stat-icon icon-amber"><i data-lucide="badge-indian-rupee"></i></div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="b-card stat-card h-100">
        <div class="stat-label">Participating Residents</div>
        <div class="stat-value"><?= (int) $kpis['total_customers'] ?></div>
        <div class="stat-icon icon-sky"><i data-lucide="users"></i></div>
      </div>
    </div>
  </div>

  <!-- Filter & Search Bar -->
  <form method="get" action="<?= url('/admin/orders') ?>" class="b-card mb-4">
    <input type="hidden" name="view" value="combined">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-5">
        <label class="form-label small fw-semibold">Search Item or Brand</label>
        <div class="input-icon">
          <i data-lucide="search"></i>
          <input class="form-control" name="q" placeholder="Product name or brand…" value="<?= e($fQ) ?>">
        </div>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small fw-semibold">Order Status</label>
        <select class="form-select" name="status">
          <option value="">Active Orders (Pending + Confirmed + Packed + Ready)</option>
          <?php foreach ($statuses as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= $fStatus === $k ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label small fw-semibold">Order Type</label>
        <select class="form-select" name="type">
          <option value="">All types</option>
          <?php foreach ($types as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= $fType === $k ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-2 d-flex gap-2">
        <button class="btn btn-brand flex-fill b-btn-pill">Filter</button>
        <a class="btn btn-soft b-btn-pill" href="<?= url('/admin/orders?view=combined') ?>">Reset</a>
      </div>
    </div>
  </form>

  <!-- Combined Products Table -->
  <?php if ($combinedProducts === []): ?>
    <div class="b-card empty-state text-center py-5">
      <i data-lucide="boxes" style="width:48px;height:48px;color:#A78BFA;"></i>
      <h5 class="fw-bold mt-3">No Ordered Products Found</h5>
      <p class="text-muted small">No customer orders match the current filter selection.</p>
    </div>
  <?php else: ?>
    <div class="table-responsive b-table fade-up mb-4">
      <table class="table align-middle mb-0" style="min-width: 860px;">
        <thead>
          <tr>
            <th style="min-width: 220px;">Product & Details</th>
            <th style="white-space: nowrap; min-width: 110px;">Category</th>
            <th style="white-space: nowrap; min-width: 130px; text-align: center;">Total Combined Qty</th>
            <th style="white-space: nowrap; min-width: 120px;">Price & Value</th>
            <th style="white-space: nowrap; min-width: 110px; text-align: center;">Orders</th>
            <th style="min-width: 270px;">Customer Breakdown (किस-किस ने कितना मंगाया)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($combinedProducts as $p):
              $key = ((int) $p['variant_id']) . '_' . trim($p['product_name']);
              $custs = $breakdownByProduct[$key] ?? [];
          ?>
            <tr>
              <!-- Product info -->
              <td>
                <div class="d-flex align-items-center gap-2">
                  <div class="rounded-3 d-flex align-items-center justify-content-center bg-light border flex-shrink-0" style="width: 44px; height: 44px; overflow: hidden;">
                    <?php if (!empty($p['product_img'])): ?>
                      <img src="<?= asset($p['product_img']) ?>" alt="<?= e($p['product_name']) ?>" style="width: 100%; height: 100%; object-fit: contain;">
                    <?php else: ?>
                      <i data-lucide="shopping-bag" style="width: 20px; height: 20px; color: #7C3AED;"></i>
                    <?php endif; ?>
                  </div>
                  <div>
                    <div class="fw-bold text-dark"><?= e($p['product_name']) ?></div>
                    <div class="small text-muted">
                      <?= e($p['brand_name'] ?? 'Generic') ?>
                      <?php if (!empty($p['pack_size'])): ?>
                        · <?= qty($p['pack_size']) ?> <?= e($p['unit']) ?>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </td>

              <!-- Category -->
              <td style="white-space: nowrap;">
                <span class="b-badge-purple" style="font-size: 11px;">
                  <?= e($p['category_name'] ?? 'Grocery') ?>
                </span>
              </td>

              <!-- Combined Quantity -->
              <td style="text-align: center; white-space: nowrap;">
                <span class="badge" style="background: #F3E8FF; color: #6D28D9; font-size: 14.5px; font-weight: 800; padding: 7px 14px; border-radius: 12px; border: 1.5px solid #DDD6FE;">
                  <?= qty($p['total_qty']) ?> <?= e($p['unit'] ?? 'units') ?>
                </span>
              </td>

              <!-- Price & Total Amount -->
              <td style="white-space: nowrap;">
                <div class="fw-bold text-dark"><?= money($p['total_amount']) ?></div>
                <div class="small text-muted"><?= money($p['unit_price']) ?> / <?= e($p['unit'] ?? 'unit') ?></div>
              </td>

              <!-- Orders & Customers count -->
              <td style="text-align: center; white-space: nowrap;">
                <span class="badge bg-white text-dark border px-2 py-1 fw-semibold">
                  <i data-lucide="users" width="12" height="12" class="me-1 text-muted"></i>
                  <?= (int) $p['customer_count'] ?> <?= (int) $p['customer_count'] === 1 ? 'Customer' : 'Customers' ?>
                </span>
                <div class="small text-muted mt-1"><?= (int) $p['order_count'] ?> <?= (int) $p['order_count'] === 1 ? 'order' : 'orders' ?></div>
              </td>

              <!-- Customer Breakdown -->
              <td>
                <div class="d-flex flex-column gap-1">
                  <?php foreach ($custs as $c): ?>
                    <div class="p-1 px-2 rounded-2 border bg-white d-flex align-items-center justify-content-between gap-2 shadow-xs" style="font-size: 12px;">
                      <div class="d-flex align-items-center gap-1 text-truncate">
                        <i data-lucide="user" width="12" height="12" style="color: #7C3AED; flex-shrink: 0;"></i>
                        <span class="fw-semibold text-truncate"><?= e($c['customer_name']) ?></span>
                        <?php if (!empty(trim((string)$c['flat_label']))): ?>
                          <span class="badge bg-light text-muted border py-0 px-1" style="font-size: 10px;">
                            Flat <?= e(trim($c['flat_label'])) ?>
                          </span>
                        <?php endif; ?>
                      </div>
                      <div class="d-flex align-items-center gap-2 flex-shrink-0">
                        <span class="fw-bold" style="color: #047857;">
                          <?= qty($c['qty']) ?> <?= e($p['unit'] ?? 'units') ?>
                        </span>
                        <a href="<?= url('/admin/orders/' . $c['order_id']) ?>" class="badge text-decoration-none b-badge-purple py-0 px-1" title="Open Order Receipt">
                          #<?= e($c['order_no']) ?>
                        </a>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

<?php else: ?>
  <!-- ==================== INDIVIDUAL ORDERS VIEW ==================== -->

  <!-- Filter Bar -->
  <form method="get" action="<?= url('/admin/orders') ?>" class="b-card mb-4">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-4">
        <label class="form-label small fw-semibold">Search Resident or Order #</label>
        <div class="input-icon">
          <i data-lucide="search"></i>
          <input class="form-control" name="q" placeholder="Order no, resident or phone…" value="<?= e($fQ) ?>">
        </div>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small fw-semibold">Status</label>
        <select class="form-select" name="status">
          <option value="">All statuses</option>
          <?php foreach ($statuses as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= $fStatus === $k ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label small fw-semibold">Type</label>
        <select class="form-select" name="type">
          <option value="">All types</option>
          <?php foreach ($types as $k => $lbl): ?>
            <option value="<?= $k ?>" <?= $fType === $k ? 'selected' : '' ?>><?= $lbl ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-2 d-flex gap-2">
        <button class="btn btn-brand flex-fill b-btn-pill">Filter</button>
        <a class="btn btn-soft b-btn-pill" href="<?= url('/admin/orders') ?>">Reset</a>
      </div>
    </div>
  </form>

  <?php if ($rows === []): ?>
    <div class="b-card empty-state text-center py-5">
      <i data-lucide="package-open" style="width:48px;height:48px;color:#A78BFA;"></i>
      <h5 class="fw-bold mt-3">No Orders Found</h5>
      <p class="text-muted small">No individual orders match these filters.</p>
    </div>
  <?php else: ?>
    <div class="b-table fade-up">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" style="min-width: 780px;">
          <thead>
            <tr>
              <th style="min-width: 160px;">Order</th>
              <th style="min-width: 170px;">Resident</th>
              <th style="white-space: nowrap; min-width: 90px;">Type</th>
              <th style="white-space: nowrap; min-width: 110px;">Fulfilment</th>
              <th style="white-space: nowrap; min-width: 80px; text-align: center;">Items</th>
              <th style="white-space: nowrap; min-width: 110px;">Total</th>
              <th style="white-space: nowrap; min-width: 110px;">Status</th>
              <th class="text-end" style="white-space: nowrap; min-width: 90px;">Action</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($rows as $o): ?>
              <tr>
                <td>
                  <div class="fw-semibold text-dark"><?= e($o['order_no']) ?></div>
                  <div class="small text-muted"><?= e(human_dt($o['created_at'])) ?></div>
                </td>
                <td class="small">
                  <div class="fw-bold"><?= e($o['user_name']) ?></div>
                  <div class="text-muted"><?= e($o['contact_name'] ?? '') ?> <?= $o['contact_phone'] ? '· ' . e($o['contact_phone']) : '' ?></div>
                </td>
                <td><span class="b-badge-grey"><?= e($types[$o['type']] ?? $o['type']) ?></span></td>
                <td class="small" style="white-space: nowrap;">
                  <?= $o['fulfilment'] === 'delivery'
                    ? '<span class="badge bg-light text-dark border"><i data-lucide="bike" width="13" height="13" class="me-1 text-brand"></i> Tower Delivery</span>'
                    : '<span class="badge bg-light text-dark border"><i data-lucide="store" width="13" height="13" class="me-1 text-brand"></i> Pickup</span>' ?>
                </td>
                <td class="small" style="text-align: center; font-weight: 700;">
                  <?= (int) $o['item_count'] ?>
                </td>
                <td class="fw-bold text-dark"><?= money($o['total']) ?></td>
                <td><span class="<?= e(status_badge($o['status'])) ?>"><?= e(str_replace('_', ' ', $o['status'])) ?></span></td>
                <td class="text-end">
                  <a class="btn btn-sm btn-outline-brand b-btn-pill" href="<?= url('/admin/orders/' . $o['id']) ?>">Open</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

<?php endif; ?>
