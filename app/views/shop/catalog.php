<?php
/**
 * Shop catalogue — used by /shop and /shop/category/{id}.
 *
 * @var array $products
 * @var int $total
 * @var int $page
 * @var int $pages
 * @var string $q
 * @var string $sort
 * @var int $catId
 * @var ?array $cat
 * @var array $children
 * @var array $categories
 */
$catName = static function (array $c): string {
    $l = lang();
    return (string) ($c['name_' . $l] ?? '') ?: (string) ($c['name_en'] ?? '');
};

/** Build a listing URL preserving filters. */
$link = static function (array $over = []) use ($q, $sort, $catId): string {
    $params = array_filter([
        'q'      => $over['q'] ?? $q,
        'sort'   => $over['sort'] ?? $sort,
        'page'   => $over['page'] ?? null,
    ], static fn ($v) => $v !== null && $v !== '');
    $path = isset($over['catId']) ? ($over['catId'] ? '/shop/category/' . (int) $over['catId'] : '/shop') : ($catId ? '/shop/category/' . $catId : '/shop');
    return url($path . ($params ? '?' . http_build_query($params) : ''));
};
?>
<div class="d-flex align-items-end justify-content-between flex-wrap gap-2 mb-3">
  <div>
    <h1 class="section-title"><?= e($cat ? $catName($cat) : ($q !== '' ? t('shop.search_results', ['q' => $q]) : t('shop.all_products'))) ?></h1>
    <p class="text-muted small mb-0"><?= (int) $total ?> <?= (int) $total === 1 ? 'product' : 'products' ?></p>
  </div>

  <!-- Search + sort (GET form works without JS) -->
  <form method="get" action="<?= e(url($catId ? '/shop/category/' . $catId : '/shop')) ?>" class="d-flex gap-2 flex-grow-1 flex-md-grow-0">
    <div class="input-icon flex-grow-1">
      <i data-lucide="search"></i>
      <input type="search" class="form-control" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('shop.search_placeholder')) ?>" aria-label="<?= e(t('common.search')) ?>">
    </div>
    <select class="form-select w-auto" name="sort" aria-label="<?= e(t('shop.sort')) ?>">
      <option value=""><?= e(t('shop.sort_default')) ?></option>
      <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>><?= e(t('shop.sort_price_asc')) ?></option>
      <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>><?= e(t('shop.sort_price_desc')) ?></option>
      <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>><?= e(t('shop.sort_newest')) ?></option>
    </select>
    <button type="submit" class="btn btn-brand px-3" aria-label="<?= e(t('common.search')) ?>"><i data-lucide="filter"></i></button>
  </form>
</div>

<!-- Category chips -->
<div class="d-flex flex-wrap gap-2 mb-2">
  <a href="<?= e($link(['catId' => 0])) ?>" class="chip <?= $catId === 0 ? 'chip-active' : '' ?>">
    <i data-lucide="layout-grid" style="width:14px;height:14px;"></i><?= e(t('common.all')) ?>
  </a>
  <?php foreach ($categories as $c): ?>
    <a href="<?= e($link(['catId' => (int) $c['id']])) ?>" class="chip <?= $catId === (int) $c['id'] ? 'chip-active' : '' ?>">
      <?php if (!empty($c['icon'])): ?><i data-lucide="<?= e($c['icon']) ?>" style="width:14px;height:14px;"></i><?php endif; ?>
      <?= e($catName($c)) ?>
    </a>
  <?php endforeach; ?>
</div>

<?php if ($cat && $children): ?>
<div class="d-flex flex-wrap gap-2 mb-3 ps-1">
  <?php foreach ($children as $ch): ?>
    <a href="<?= e($link(['catId' => (int) $ch['id']])) ?>" class="chip chip-light bg-brand-soft text-dark" style="border-color:transparent;">
      <?= e($catName($ch)) ?>
    </a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$products): ?>
  <div class="b-card empty-state my-5">
    <i data-lucide="package-search"></i>
    <p class="fw-semibold"><?= e(t('shop.no_products')) ?></p>
    <p class="small"><?= e(t('shop.try_other_search')) ?></p>
    <a href="<?= url('/shop') ?>" class="btn btn-soft b-btn-pill mt-1"><?= e(t('shop.continue_shopping')) ?></a>
  </div>
<?php else: ?>
  <div class="row g-3 g-md-4">
    <?php foreach ($products as $p): ?>
    <div class="col-6 col-md-4 col-lg-3">
      <div class="b-card b-card-hover product-card h-100 p-2 fade-up">
        <span class="product-badge-saving"><i data-lucide="sparkles" style="width:10px;height:10px;"></i> Wholesale</span>
        <a href="<?= url('/shop/product/' . (int) $p['id']) ?>" class="text-decoration-none">
          <?php if (!empty($p['image'])): ?>
            <img src="<?= e(upload_url($p['image'])) ?>" alt="<?= e(product_name($p)) ?>" class="product-img" loading="lazy">
          <?php else: ?>
            <div class="product-img d-flex align-items-center justify-content-center"
                 style="background:#FAF7FE;">
              <i data-lucide="package" style="width:48px;height:48px;color:#7C3AED;opacity:.4;"></i>
            </div>
          <?php endif; ?>
          <div class="product-name"><?= e(product_name($p)) ?></div>
          <div class="small text-muted mb-1"><?= (int) $p['variant_count'] ?> <?= (int) $p['variant_count'] === 1 ? 'pack option' : 'pack options' ?></div>
        </a>
        <div class="d-flex align-items-center justify-content-between mt-auto pt-2 border-top">
          <div>
            <div class="small text-muted" style="font-size:11px;"><?= e(t('shop.from')) ?></div>
            <div class="price"><?= e(money($p['min_price'])) ?></div>
          </div>
          <a href="<?= url('/shop/product/' . (int) $p['id']) ?>" class="btn btn-blinkit-add">
            <i data-lucide="plus" style="width:13px;height:13px;"></i> ADD
          </a>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <?php if ($pages > 1): ?>
  <nav class="d-flex justify-content-center align-items-center gap-3 mt-4" aria-label="Pagination">
    <?php if ($page > 1): ?>
      <a href="<?= e($link(['page' => $page - 1])) ?>" class="btn btn-soft btn-sm b-btn-pill">← <?= e(t('common.back')) ?></a>
    <?php endif; ?>
    <span class="small text-muted"><?= (int) $page ?> / <?= (int) $pages ?></span>
    <?php if ($page < $pages): ?>
      <a href="<?= e($link(['page' => $page + 1])) ?>" class="btn btn-soft btn-sm b-btn-pill"><?= e(t('common.view')) ?> →</a>
    <?php endif; ?>
  </nav>
  <?php endif; ?>

  <p class="small text-muted text-center mt-4 mb-0">
    <i data-lucide="shield-check" style="width:14px;height:14px;vertical-align:-2px;color:#28A66A;"></i> Verified cooperative wholesale rates curated for your society
  </p>
<?php endif; ?>
