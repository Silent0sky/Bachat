<?php
/** Catalog manager — 5 tabs. Vars depend on tab:
 * categories: $parents, $byParent, $allCats, $editCat
 * brands:     $brands, $editBrand
 * products:   $products, $categories, $vendors, $editProduct
 * variants:   $variants, $products, $brands, $editVariant
 * prices:     $societies, $variants, $overrides
 */
$tab = $tab ?? 'categories';
$editCat = $editCat ?? null;
$editBrand = $editBrand ?? null;
$editProduct = $editProduct ?? null;
$editVariant = $editVariant ?? null;
$parents = $parents ?? [];
$byParent = $byParent ?? [];
$allCats = $allCats ?? [];
$brands = $brands ?? [];
$products = $products ?? [];
$categories = $categories ?? [];
$vendors = $vendors ?? [];
$variants = $variants ?? [];
$societies = $societies ?? [];
$overrides = $overrides ?? [];

$tabs = [
  'categories' => 'Categories',
  'brands'     => 'Brands',
  'products'   => 'Products',
  'variants'   => 'Pack Sizes & Prices',
  'prices'     => 'Society Prices',
  'bulk'       => '📥 Bulk Grocery Upload',
];
?>
<div class="mb-4">
  <h1 class="section-title mb-2">Catalog</h1>
  <div class="d-flex flex-wrap gap-2">
    <?php foreach ($tabs as $key => $lbl): ?>
      <a class="chip <?= $tab === $key ? 'chip-active' : '' ?>" href="<?= url('/super/catalog?tab=' . $key) ?>"><?= e($lbl) ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if ($tab === 'categories'): ?>
  <?php $c = $editCat; ?>
  <div class="b-card mb-4 fade-up">
    <h2 class="h6 fw-bold text-brand mb-3"><?= $c ? 'Edit category #' . (int) $c['id'] : 'Add a category' ?></h2>
    <form method="post" action="<?= url('/super/categories') ?>" class="row g-3">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) ($c['id'] ?? 0) ?>">
      <div class="col-md-4">
        <label class="form-label fw-semibold">Name (English) *</label>
        <input class="form-control" name="name_en" required maxlength="100" value="<?= e(old('name_en', $c['name_en'] ?? '')) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">नाम (हिन्दी)</label>
        <input class="form-control" name="name_hi" maxlength="100" value="<?= e(old('name_hi', $c['name_hi'] ?? '')) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">नाव (मराठी)</label>
        <input class="form-control" name="name_mr" maxlength="100" value="<?= e(old('name_mr', $c['name_mr'] ?? '')) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Parent category</label>
        <select class="form-select" name="parent_id">
          <option value="">— top level —</option>
          <?php foreach ($allCats as $ac): if ($ac['parent_id'] !== null) continue; ?>
            <option value="<?= (int) $ac['id'] ?>" <?= (string) (old('parent_id', $c['parent_id'] ?? '') ?? '') === (string) $ac['id'] ? 'selected' : '' ?>><?= e($ac['name_en']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-4">
        <label class="form-label fw-semibold">Icon <span class="text-muted small">(lucide name, e.g. wheat)</span></label>
        <input class="form-control" name="icon" maxlength="40" value="<?= e(old('icon', $c['icon'] ?? '')) ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label fw-semibold">Sort order</label>
        <input class="form-control" type="number" name="sort_order" value="<?= e(old('sort_order', $c['sort_order'] ?? 0)) ?>">
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" role="switch" id="cat_active" name="is_active" value="1" <?= (int) old('is_active', $c['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
          <label class="form-check-label small" for="cat_active">Active</label>
        </div>
      </div>
      <div class="col-12 d-flex gap-2">
        <button class="btn btn-brand b-btn-pill"><?= $c ? 'Save changes' : 'Create category' ?></button>
        <?php if ($c): ?><a class="btn btn-soft b-btn-pill" href="<?= url('/super/catalog?tab=categories') ?>">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <?php if ($allCats === []): ?>
    <div class="b-card"><div class="empty-state"><i data-lucide="shopping-basket"></i><p>No categories yet.</p></div></div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($parents as $p): ?>
        <div class="col-md-6 col-xl-4">
          <div class="b-card h-100 fade-up">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-bold">
                  <i data-lucide="<?= e($p['icon'] ?: 'folder') ?>" width="16" height="16" class="text-brand me-1"></i><?= e($p['name_en']) ?>
                </div>
                <div class="small text-muted"><?= e($p['name_hi'] ?? '') ?> <?= $p['is_active'] ? '' : '· <span class=\"b-badge-grey\">hidden</span>' ?></div>
              </div>
              <a class="btn btn-sm btn-soft" href="<?= url('/super/catalog?tab=categories&edit=' . $p['id']) ?>">Edit</a>
            </div>
            <?php $kids = $byParent[(int) $p['id']] ?? []; ?>
            <?php if ($kids !== []): ?>
              <hr class="my-2">
              <div class="d-flex flex-column gap-1">
                <?php foreach ($kids as $k): ?>
                  <div class="d-flex justify-content-between align-items-center small">
                    <span><i data-lucide="corner-down-right" width="13" height="13" class="text-muted me-1"></i><?= e($k['name_en']) ?>
                      <?php if (!$k['is_active']): ?><span class="b-badge-grey">hidden</span><?php endif; ?>
                    </span>
                    <span class="d-inline-flex gap-1">
                      <a class="text-decoration-none text-brand fw-semibold" href="<?= url('/super/catalog?tab=categories&edit=' . $k['id']) ?>">Edit</a>
                      <form method="post" action="<?= url('/super/categories/' . $k['id'] . '/delete') ?>" data-confirm="Delete category '<?= e($k['name_en']) ?>'?">
                        <?= csrf_field() ?>
                        <button class="btn btn-sm btn-link text-danger p-0 text-decoration-none">Delete</button>
                      </form>
                    </span>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
            <hr class="my-2">
            <form method="post" action="<?= url('/super/categories/' . $p['id'] . '/delete') ?>" data-confirm="Delete category '<?= e($p['name_en']) ?>'? (must have no products/subcategories)">
              <?= csrf_field() ?>
              <button class="btn btn-sm btn-soft text-danger">Delete</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

<?php elseif ($tab === 'brands'): ?>
  <?php $b = $editBrand; ?>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="b-card fade-up">
        <h2 class="h6 fw-bold text-brand mb-3"><?= $b ? 'Edit brand #' . (int) $b['id'] : 'Add a brand' ?></h2>
        <form method="post" action="<?= url('/super/brands') ?>" enctype="multipart/form-data" class="row g-3">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int) ($b['id'] ?? 0) ?>">
          <div class="col-12">
            <label class="form-label fw-semibold">Brand name *</label>
            <input class="form-control" name="name" required maxlength="100" value="<?= e(old('name', $b['name'] ?? '')) ?>">
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Logo <?= $b ? '<span class="text-muted small">(empty = keep)</span>' : '' ?></label>
            <input type="file" class="form-control" name="logo" accept="image/*">
          </div>
          <div class="col-12 d-flex gap-2">
            <button class="btn btn-brand b-btn-pill"><?= $b ? 'Save' : 'Add brand' ?></button>
            <?php if ($b): ?><a class="btn btn-soft b-btn-pill" href="<?= url('/super/catalog?tab=brands') ?>">Cancel</a><?php endif; ?>
          </div>
        </form>
      </div>
    </div>
    <div class="col-lg-8">
      <div class="b-table fade-up d1">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0">
            <thead><tr><th>Brand</th><th>Logo</th><th class="text-end">Actions</th></tr></thead>
            <tbody>
              <?php foreach ($brands as $br): ?>
                <tr>
                  <td class="fw-semibold"><?= e($br['name']) ?></td>
                  <td><?php if (!empty($br['logo'])): ?><img src="<?= e(upload_url($br['logo'])) ?>" alt="" width="34" height="34" class="rounded-3 object-fit-cover"><?php else: ?>—<?php endif; ?></td>
                  <td class="text-end"><a class="btn btn-sm btn-soft" href="<?= url('/super/catalog?tab=brands&edit=' . $br['id']) ?>">Edit</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

<?php elseif ($tab === 'products'): ?>
  <?php $p = $editProduct; ?>
  <div class="b-card mb-4 fade-up">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="h6 fw-bold text-brand mb-0"><?= $p ? 'Edit product #' . (int) $p['id'] : 'Add a product' ?></h2>
      <a class="btn btn-sm btn-outline-brand b-btn-pill" href="<?= url('/super/catalog?tab=bulk') ?>">
        <i data-lucide="upload-cloud" width="14" height="14" class="me-1"></i>Bulk Upload Groceries
      </a>
    </div>
    <form method="post" action="<?= url('/super/products') ?>" enctype="multipart/form-data" class="row g-3">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) ($p['id'] ?? 0) ?>">
      <div class="col-md-4">
        <label class="form-label fw-semibold">Name (English) *</label>
        <input class="form-control" name="name_en" required maxlength="150" value="<?= e(old('name_en', $p['name_en'] ?? '')) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">नाम (हिन्दी)</label>
        <input class="form-control" name="name_hi" maxlength="150" value="<?= e(old('name_hi', $p['name_hi'] ?? '')) ?>">
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">नाव (मराठी)</label>
        <input class="form-control" name="name_mr" maxlength="150" value="<?= e(old('name_mr', $p['name_mr'] ?? '')) ?>">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label fw-semibold">Category *</label>
        <select class="form-select" name="category_id" required>
          <option value="">— select —</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int) $c['id'] ?>" <?= (string) (old('category_id', $p['category_id'] ?? '') ?? '') === (string) $c['id'] ? 'selected' : '' ?>>
              <?= $c['parent_id'] !== null ? '— ' : '' ?><?= e($c['name_en']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label fw-semibold">Unit</label>
        <select class="form-select" name="unit">
          <?php foreach (['pc', 'kg', 'g', 'l', 'ml', 'pack', 'bottle', 'bag', 'box'] as $u): ?>
            <option value="<?= $u ?>" <?= (string) old('unit', $p['unit'] ?? 'pc') === $u ? 'selected' : '' ?>><?= e(unit_label($u)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label fw-semibold">Fulfilment</label>
        <select class="form-select" name="fulfillment">
          <option value="central" <?= (string) old('fulfillment', $p['fulfillment'] ?? 'central') === 'central' ? 'selected' : '' ?>>Central (platform stock)</option>
          <option value="vendor_direct" <?= (string) old('fulfillment', $p['fulfillment'] ?? 'central') === 'vendor_direct' ? 'selected' : '' ?>>Vendor direct</option>
        </select>
      </div>
      <div class="col-6 col-md-4">
        <label class="form-label fw-semibold">Vendor <span class="text-muted small">(for vendor_direct)</span></label>
        <select class="form-select" name="vendor_id">
          <option value="">— none —</option>
          <?php foreach ($vendors as $v): ?>
            <option value="<?= (int) $v['id'] ?>" <?= (string) (old('vendor_id', $p['vendor_id'] ?? '') ?? '') === (string) $v['id'] ? 'selected' : '' ?>><?= e($v['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label fw-semibold">SKU</label>
        <input class="form-control" name="sku" maxlength="40" value="<?= e(old('sku', $p['sku'] ?? '')) ?>">
      </div>
      <div class="col-6 col-md-3">
        <label class="form-label fw-semibold">Image <?= $p ? '<span class="text-muted small">(empty = keep)</span>' : '' ?></label>
        <input type="file" class="form-control" name="image" accept="image/*">
      </div>
      <div class="col-md-3 d-flex align-items-end">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" role="switch" id="prod_active" name="is_active" value="1" <?= (int) old('is_active', $p['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
          <label class="form-check-label small" for="prod_active">Active</label>
        </div>
      </div>
      <div class="col-md-9">
        <label class="form-label fw-semibold">Description</label>
        <textarea class="form-control" name="description" rows="2"><?= e(old('description', $p['description'] ?? '')) ?></textarea>
      </div>
      <div class="col-12 d-flex gap-2">
        <button class="btn btn-brand b-btn-pill"><?= $p ? 'Save changes' : 'Create product' ?></button>
        <?php if ($p): ?><a class="btn btn-soft b-btn-pill" href="<?= url('/super/catalog?tab=products') ?>">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <?php if ($products === []): ?>
    <div class="b-card"><div class="empty-state"><i data-lucide="shopping-basket"></i><p>No products yet.</p></div></div>
  <?php else: ?>
    <div class="b-table fade-up">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Product</th><th>Category</th><th>Unit</th><th>Fulfilment</th><th>Vendor</th><th>Variants</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
            <?php foreach ($products as $pr): ?>
              <tr>
                <td>
                  <div class="d-flex align-items-center gap-2">
                    <?php if (!empty($pr['image'])): ?>
                      <img src="<?= e(upload_url($pr['image'])) ?>" alt="" width="36" height="36" class="rounded-3 object-fit-cover">
                    <?php else: ?>
                      <span class="d-inline-flex align-items-center justify-content-center rounded-3 bg-brand-soft text-brand" style="width:36px;height:36px"><i data-lucide="package" width="16" height="16"></i></span>
                    <?php endif; ?>
                    <div class="fw-semibold"><?= e(product_name($pr)) ?><div class="small text-muted"><?= e($pr['sku'] ?? '') ?></div></div>
                  </div>
                </td>
                <td class="small"><?= e($pr['category_name']) ?></td>
                <td class="small"><?= e(unit_label($pr['unit'])) ?></td>
                <td class="small"><?= $pr['fulfillment'] === 'vendor_direct' ? '<span class="b-badge-blue">vendor direct</span>' : '<span class="b-badge-grey">central</span>' ?></td>
                <td class="small"><?= e($pr['vendor_name'] ?? '—') ?></td>
                <td><?= (int) $pr['variant_count'] ?></td>
                <td><?= $pr['is_active'] ? '<span class="b-badge-green">active</span>' : '<span class="b-badge-grey">inactive</span>' ?></td>
                <td class="text-end">
                  <div class="d-inline-flex gap-1">
                    <a class="btn btn-sm btn-soft" href="<?= url('/super/catalog?tab=products&edit=' . $pr['id']) ?>">Edit</a>
                    <form method="post" action="<?= url('/super/products/' . $pr['id'] . '/delete') ?>" data-confirm="Delete '<?= e(product_name($pr)) ?>'? Products used in orders are deactivated instead.">
                      <?= csrf_field() ?>
                      <button class="btn btn-sm btn-outline-brand text-danger">Delete</button>
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

<?php elseif ($tab === 'variants'): ?>
  <?php $v = $editVariant; ?>
  <div class="b-card mb-4 fade-up">
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="h6 fw-bold text-brand mb-0"><?= $v ? 'Edit variant #' . (int) $v['id'] : 'Add a pack size / variant' ?></h2>
      <a class="btn btn-sm btn-outline-brand b-btn-pill" href="<?= url('/super/catalog?tab=bulk') ?>">
        <i data-lucide="upload-cloud" width="14" height="14" class="me-1"></i>Bulk Upload Pack Sizes
      </a>
    </div>
    <form method="post" action="<?= url('/super/variants') ?>" class="row g-3">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int) ($v['id'] ?? 0) ?>">
      <div class="col-md-4">
        <label class="form-label fw-semibold">Product *</label>
        <select class="form-select" name="product_id" required>
          <option value="">— select —</option>
          <?php foreach ($products as $pr): ?>
            <option value="<?= (int) $pr['id'] ?>" <?= (string) (old('product_id', $v['product_id'] ?? '') ?? '') === (string) $pr['id'] ? 'selected' : '' ?>><?= e($pr['name_en']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4">
        <label class="form-label fw-semibold">Brand</label>
        <select class="form-select" name="brand_id">
          <option value="">— none —</option>
          <?php foreach ($brands as $br): ?>
            <option value="<?= (int) $br['id'] ?>" <?= (string) (old('brand_id', $v['brand_id'] ?? '') ?? '') === (string) $br['id'] ? 'selected' : '' ?>><?= e($br['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label fw-semibold">Pack size *</label>
        <input class="form-control" type="number" step="0.001" min="0.001" name="pack_size" required value="<?= e(old('pack_size', $v['pack_size'] ?? 1)) ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label fw-semibold">Unit *</label>
        <select class="form-select" name="unit">
          <?php foreach (['g', 'kg', 'ml', 'l', 'pc', 'pack', 'bottle', 'bag', 'box'] as $u): ?>
            <option value="<?= $u ?>" <?= (string) old('unit', $v['unit'] ?? 'pc') === $u ? 'selected' : '' ?>><?= e(unit_label($u)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label fw-semibold">SKU</label>
        <input class="form-control" name="sku" maxlength="40" value="<?= e(old('sku', $v['sku'] ?? '')) ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label fw-semibold">Reference price * <span class="text-muted small">(MRP)</span></label>
        <input class="form-control" type="number" step="0.01" min="0" name="reference_price" required value="<?= e(old('reference_price', $v['reference_price'] ?? '')) ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label fw-semibold">Selling price *</label>
        <input class="form-control" type="number" step="0.01" min="0" name="selling_price" required value="<?= e(old('selling_price', $v['selling_price'] ?? '')) ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label fw-semibold">Stock qty</label>
        <input class="form-control" type="number" step="0.001" name="stock_qty" value="<?= e(old('stock_qty', $v['stock_qty'] ?? 0)) ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label fw-semibold">Min qty</label>
        <input class="form-control" type="number" step="0.001" min="0.001" name="min_qty" value="<?= e(old('min_qty', $v['min_qty'] ?? 1)) ?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label fw-semibold">Max qty <span class="text-muted small">(empty = ∞)</span></label>
        <input class="form-control" type="number" step="0.001" min="0" name="max_qty" value="<?= e(old('max_qty', $v['max_qty'] ?? '')) ?>">
      </div>
      <div class="col-md-2 d-flex align-items-end">
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" role="switch" id="var_active" name="is_active" value="1" <?= (int) old('is_active', $v['is_active'] ?? 1) === 1 ? 'checked' : '' ?>>
          <label class="form-check-label small" for="var_active">Active</label>
        </div>
      </div>
      <div class="col-12 d-flex gap-2">
        <button class="btn btn-brand b-btn-pill"><?= $v ? 'Save changes' : 'Create variant' ?></button>
        <?php if ($v): ?><a class="btn btn-soft b-btn-pill" href="<?= url('/super/catalog?tab=variants') ?>">Cancel</a><?php endif; ?>
      </div>
    </form>
  </div>

  <?php if ($variants === []): ?>
    <div class="b-card"><div class="empty-state"><i data-lucide="boxes"></i><p>No variants yet.</p></div></div>
  <?php else: ?>
    <div class="b-table fade-up">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead><tr><th>Product</th><th>Brand</th><th>Pack</th><th>SKU</th><th>Ref ₹</th><th>Sell ₹</th><th>Stock</th><th>Status</th><th class="text-end">Actions</th></tr></thead>
          <tbody>
            <?php foreach ($variants as $vr): ?>
              <tr>
                <td class="fw-semibold small"><?= e($vr['product_name']) ?></td>
                <td class="small"><?= e($vr['brand_name'] ?? '—') ?></td>
                <td class="small fw-semibold"><?= e(pack_label($vr['pack_size'], $vr['unit'])) ?></td>
                <td class="small text-muted"><?= e($vr['sku'] ?? '—') ?></td>
                <td class="small"><?= money($vr['reference_price']) ?></td>
                <td class="fw-semibold"><?= money($vr['selling_price']) ?></td>
                <td class="small"><?= e(qty($vr['stock_qty'])) ?></td>
                <td><?= $vr['is_active'] ? '<span class="b-badge-green">active</span>' : '<span class="b-badge-grey">inactive</span>' ?></td>
                <td class="text-end">
                  <div class="d-inline-flex gap-1">
                    <a class="btn btn-sm btn-soft" href="<?= url('/super/catalog?tab=variants&edit=' . $vr['id']) ?>">Edit</a>
                    <form method="post" action="<?= url('/super/variants/' . $vr['id'] . '/delete') ?>" data-confirm="Delete this variant? Variants used in orders are deactivated instead.">
                      <?= csrf_field() ?>
                      <button class="btn btn-sm btn-outline-brand text-danger">Delete</button>
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

<?php elseif ($tab === 'prices'): ?>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="b-card fade-up">
        <h2 class="h6 fw-bold text-brand mb-1">Set a society price</h2>
        <p class="small text-muted">Overrides the base selling price for one society (e.g. negotiated bulk rates).</p>
        <form method="post" action="<?= url('/super/prices') ?>" class="row g-3">
          <?= csrf_field() ?>
          <div class="col-12">
            <label class="form-label fw-semibold">Society *</label>
            <select class="form-select" name="society_id" required>
              <option value="">— select —</option>
              <?php foreach ($societies as $s): ?>
                <option value="<?= (int) $s['id'] ?>"><?= e($s['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Variant *</label>
            <select class="form-select" name="variant_id" required>
              <option value="">— select —</option>
              <?php foreach ($variants as $vr): ?>
                <option value="<?= (int) $vr['id'] ?>"><?= e($vr['product_name']) ?> · <?= e($vr['brand_name'] ?? 'Generic') ?> · <?= e(pack_label($vr['pack_size'], $vr['unit'])) ?> (base <?= money($vr['selling_price']) ?>)</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label fw-semibold">Selling price for this society (₹) *</label>
            <input class="form-control" type="number" step="0.01" min="0" name="selling_price" required>
          </div>
          <div class="col-12">
            <button class="btn btn-brand b-btn-pill w-100">Save override</button>
          </div>
        </form>
      </div>
    </div>
    <div class="col-lg-8">
      <?php if ($overrides === []): ?>
        <div class="b-card"><div class="empty-state"><i data-lucide="tag"></i><p>No society price overrides yet.</p></div></div>
      <?php else: ?>
        <div class="b-table fade-up d1">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <thead><tr><th>Society</th><th>Variant</th><th>Base ₹</th><th>Override ₹</th><th class="text-end">Actions</th></tr></thead>
              <tbody>
                <?php foreach ($overrides as $o): ?>
                  <tr>
                    <td class="fw-semibold"><?= e($o['society_name']) ?></td>
                    <td class="small"><?= e($o['product_name']) ?> · <?= e($o['brand_name'] ?? 'Generic') ?> · <?= e(pack_label($o['pack_size'], $o['unit'])) ?></td>
                    <td class="small"><?= money($o['base_price']) ?></td>
                    <td class="fw-semibold text-brand"><?= money($o['selling_price']) ?></td>
                    <td class="text-end">
                      <form method="post" action="<?= url('/super/prices') ?>" data-confirm="Remove this override? The variant falls back to its base price.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
                        <button class="btn btn-sm btn-outline-brand text-danger">Remove</button>
                      </form>
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

<?php elseif ($tab === 'bulk'): ?>
  <!-- Bulk Grocery Upload Tab -->
  <div class="row g-4 mb-4">
    <!-- Current Stats Cards -->
    <div class="col-6 col-md-3">
      <div class="b-card text-center p-3 h-100 fade-up">
        <div class="small text-muted fw-semibold text-uppercase">Total Products</div>
        <div class="h3 fw-bold text-brand my-1"><?= (int) ($totalProducts ?? 0) ?></div>
        <div class="small text-muted">Across all categories</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="b-card text-center p-3 h-100 fade-up d1">
        <div class="small text-muted fw-semibold text-uppercase">Pack Sizes / SKUs</div>
        <div class="h3 fw-bold text-brand my-1"><?= (int) ($totalVariants ?? 0) ?></div>
        <div class="small text-muted">Active variants</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="b-card text-center p-3 h-100 fade-up d2">
        <div class="small text-muted fw-semibold text-uppercase">Categories</div>
        <div class="h3 fw-bold text-brand my-1"><?= (int) ($totalCategories ?? 0) ?></div>
        <div class="small text-muted">Auto-created if new</div>
      </div>
    </div>
    <div class="col-6 col-md-3">
      <div class="b-card text-center p-3 h-100 fade-up d3">
        <div class="small text-muted fw-semibold text-uppercase">Brands</div>
        <div class="h3 fw-bold text-brand my-1"><?= (int) ($totalBrands ?? 0) ?></div>
        <div class="small text-muted">Auto-linked</div>
      </div>
    </div>
  </div>

  <?php if (!empty($uploadStats)): ?>
    <!-- Upload Results Banner -->
    <div class="b-card mb-4 border border-brand border-2 fade-up">
      <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="h6 fw-bold text-brand mb-0"><i data-lucide="check-circle" class="me-1"></i>Last Upload Processing Summary</h3>
        <span class="badge b-badge-green"><?= (int) $uploadStats['total_rows'] ?> row(s) processed</span>
      </div>
      <div class="row g-2 mb-3">
        <div class="col-6 col-md-2">
          <div class="p-2 bg-light rounded text-center">
            <span class="d-block small text-muted">Products Added</span>
            <span class="fw-bold text-success">+<?= (int) $uploadStats['products_created'] ?></span>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="p-2 bg-light rounded text-center">
            <span class="d-block small text-muted">Products Updated</span>
            <span class="fw-bold text-primary"><?= (int) $uploadStats['products_updated'] ?></span>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="p-2 bg-light rounded text-center">
            <span class="d-block small text-muted">Pack Sizes Added</span>
            <span class="fw-bold text-success">+<?= (int) $uploadStats['variants_created'] ?></span>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="p-2 bg-light rounded text-center">
            <span class="d-block small text-muted">Pack Sizes Updated</span>
            <span class="fw-bold text-primary"><?= (int) $uploadStats['variants_updated'] ?></span>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="p-2 bg-light rounded text-center">
            <span class="d-block small text-muted">Categories Created</span>
            <span class="fw-bold text-secondary">+<?= (int) $uploadStats['categories_created'] ?></span>
          </div>
        </div>
        <div class="col-6 col-md-2">
          <div class="p-2 bg-light rounded text-center">
            <span class="d-block small text-muted">Brands Created</span>
            <span class="fw-bold text-secondary">+<?= (int) $uploadStats['brands_created'] ?></span>
          </div>
        </div>
      </div>

      <?php if (!empty($uploadStats['errors'])): ?>
        <div class="alert alert-warning mb-0">
          <div class="fw-bold mb-2"><i data-lucide="alert-triangle" class="me-1"></i>Issues found in <?= count($uploadStats['errors']) ?> row(s):</div>
          <div class="table-responsive" style="max-height: 220px; overflow-y: auto;">
            <table class="table table-sm table-bordered mb-0 bg-white small">
              <thead class="table-light">
                <tr><th>Row #</th><th>Item</th><th>Reason</th></tr>
              </thead>
              <tbody>
                <?php foreach ($uploadStats['errors'] as $err): ?>
                  <tr>
                    <td class="fw-semibold"><?= (int) $err['row'] ?></td>
                    <td><?= e($err['product']) ?></td>
                    <td class="text-danger"><?= e($err['error']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- Left: Upload Form -->
    <div class="col-lg-6">
      <div class="b-card h-100 fade-up">
        <h2 class="h6 fw-bold text-brand mb-2"><i data-lucide="upload-cloud" class="me-1"></i>Upload Groceries CSV</h2>
        <p class="text-muted small mb-3">Upload your product catalog spreadsheet in CSV format. Missing categories and brands will be automatically created.</p>

        <form method="post" action="<?= url('/super/catalog/bulk-upload') ?>" enctype="multipart/form-data">
          <?= csrf_field() ?>

          <div class="mb-3">
            <label class="form-label fw-semibold">Select CSV / Excel File *</label>
            <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv,text/plain" required>
            <div class="form-text">Must be a comma or semicolon delimited CSV file. Supports Unicode & Hindi/Marathi.</div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Society Price Scope</label>
            <select name="society_id" class="form-select">
              <option value="">Apply as Platform Base Price (All Societies)</option>
              <?php foreach ($societies as $soc): ?>
                <option value="<?= (int) $soc['id'] ?>">Override Price for <?= e($soc['name']) ?> (<?= e($soc['code']) ?>)</option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">You can also leave this as Base Price and specify individual society codes inside the CSV.</div>
          </div>

          <div class="mb-4">
            <div class="form-check form-switch">
              <input class="form-check-input" type="checkbox" name="update_existing" id="update_existing" value="1" checked>
              <label class="form-check-label fw-semibold" for="update_existing">
                Update existing products & pack sizes
              </label>
              <div class="text-muted small">If matching product name/SKU is found, updates its selling price, stock, and descriptions.</div>
            </div>
          </div>

          <div class="d-flex align-items-center gap-2">
            <button type="submit" class="btn btn-brand b-btn-pill px-4">
              <i data-lucide="upload" width="16" height="16" class="me-1"></i>Upload & Process Groceries
            </button>
            <a class="btn btn-soft b-btn-pill" href="<?= url('/super/catalog?tab=products') ?>">View Products</a>
          </div>
        </form>
      </div>
    </div>

    <!-- Right: Download Template & Instructions -->
    <div class="col-lg-6">
      <div class="b-card h-100 fade-up d1">
        <h2 class="h6 fw-bold text-brand mb-2"><i data-lucide="file-spreadsheet" class="me-1"></i>Sample Template & Format</h2>
        <p class="text-muted small mb-3">Download our pre-formatted grocery catalog template. Fill it in Microsoft Excel, Google Sheets, or LibreOffice, then upload.</p>

        <div class="p-3 bg-light rounded-3 mb-3 border">
          <div class="d-flex justify-content-between align-items-center">
            <div>
              <div class="fw-bold"><i data-lucide="file-text" width="16" height="16" class="text-brand me-1"></i>bachat_bulk_grocery_template.csv</div>
              <div class="small text-muted">Pre-filled with Atta, Rice, Oil, Dal, Milk, Sugar & Tea examples</div>
            </div>
            <a class="btn btn-brand btn-sm b-btn-pill text-nowrap" href="<?= url('/super/catalog/template') ?>">
              <i data-lucide="download" width="14" height="14" class="me-1"></i>Download Template
            </a>
          </div>
        </div>

        <h3 class="fw-semibold small text-uppercase text-muted mb-2">Required & Supported Columns</h3>
        <ul class="list-unstyled small mb-0 d-flex flex-column gap-2">
          <li><span class="badge b-badge-green me-1">Required</span> <strong>Category</strong> — e.g. <em>Atta & Flours</em>, <em>Rice</em>, <em>Dal & Pulses</em>. Auto-created if new.</li>
          <li><span class="badge b-badge-green me-1">Required</span> <strong>Product Name (EN)</strong> — e.g. <em>Aashirvaad Shudh Chakki Atta</em>.</li>
          <li><span class="badge b-badge-grey me-1">Optional</span> <strong>Product Name (HI / MR)</strong> — e.g. <em>आशीर्वाद शुद्ध चक्की आटा</em>.</li>
          <li><span class="badge b-badge-grey me-1">Optional</span> <strong>Brand</strong> — e.g. <em>Aashirvaad</em>, <em>Fortune</em>, <em>Amul</em>. Auto-created if new.</li>
          <li><span class="badge b-badge-green me-1">Required</span> <strong>Pack Size & Unit</strong> — e.g. <em>5</em> in size, <em>kg</em> in unit. (Supports: <code>kg, g, l, ml, pc, pack, bottle, bag, box</code>).</li>
          <li><span class="badge b-badge-green me-1">Required</span> <strong>Selling Price (Bachat)</strong> — Discounted price resident pays (e.g. <em>215.00</em>).</li>
          <li><span class="badge b-badge-grey me-1">Optional</span> <strong>Reference Price (MRP)</strong> — Printed MRP price for savings calculation (e.g. <em>260.00</em>).</li>
          <li><span class="badge b-badge-grey me-1">Optional</span> <strong>Stock Qty & SKU</strong> — Inventory level (defaults to 100) and product SKU code.</li>
          <li><span class="badge b-badge-grey me-1">Optional</span> <strong>Society Code</strong> — Set price override for a specific society code.</li>
        </ul>
      </div>
    </div>
  </div>
<?php endif; ?>

