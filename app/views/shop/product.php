<?php
/**
 * Product Detail — Guided Selection Flow:
 * Category → Product → Brand/Variant → Pack Size → Quantity → Add to Cart
 *
 * @var array $product
 * @var array $variants
 * @var array $favIds
 * @var array $reviews
 * @var array $lists
 */
$catLabel = (string) ($product['cat_name_' . lang()] ?? '') ?: (string) ($product['cat_name_en'] ?? '');
$favIds   = $favIds ?? [];

// Organize variants by Brand / Variant
$groups = [];
foreach ($variants as $v) {
    $label = trim((string) ($v['variant_label'] ?: ($v['brand_name'] ?: 'Standard')));
    if (!isset($groups[$label])) {
        $groups[$label] = [
            'name'     => $label,
            'brand'    => (string) ($v['brand_name'] ?? ''),
            'minPrice' => (float) $v['price'],
            'unit'     => (string) $v['unit'],
            'variants' => [],
        ];
    }
    $groups[$label]['variants'][] = $v;
    if ((float) $v['price'] < $groups[$label]['minPrice']) {
        $groups[$label]['minPrice'] = (float) $v['price'];
    }
}

// Prepare JSON payload for dynamic client-side flow
$variantsPayload = [];
foreach ($variants as $v) {
    $variantsPayload[] = [
        'id'              => (int) $v['id'],
        'group'           => trim((string) ($v['variant_label'] ?: ($v['brand_name'] ?: 'Standard'))),
        'brand_name'      => (string) ($v['brand_name'] ?? ''),
        'variant_name'    => (string) ($v['variant_name'] ?? ''),
        'pack_size'       => (float) $v['pack_size'],
        'unit'            => (string) $v['unit'],
        'pack_label'      => pack_label($v['pack_size'], $v['unit']),
        'price'           => (float) $v['price'],
        'reference_price' => (float) $v['reference_price'],
        'stock_qty'       => (float) $v['stock_qty'],
        'min_qty'         => (float) max(1, (float) $v['min_qty']),
        'max_qty'         => $v['max_qty'] !== null ? (float) $v['max_qty'] : max((float) $v['stock_qty'], 10),
        'sku'             => (string) ($v['sku'] ?? ''),
    ];
}
?>

<style>
  .flow-step-bar {
    display: flex;
    align-items: center;
    gap: .5rem;
    overflow-x: auto;
    padding: .5rem 0;
    margin-bottom: 1.25rem;
    scrollbar-width: none;
  }
  .flow-step-bar::-webkit-scrollbar { display: none; }
  .flow-step-item {
    display: flex;
    align-items: center;
    gap: .35rem;
    font-size: .82rem;
    font-weight: 600;
    white-space: nowrap;
    color: #475569;
    padding: .25rem .75rem;
    border-radius: 999px;
    background: #FFFFFF;
    border: 1px solid #E2E8F0;
  }
  .flow-step-item.active {
    color: #7C3AED;
    background: #F3E8FF;
    border-color: #7C3AED;
    font-weight: 700;
  }
  .flow-step-item.done {
    color: #7C3AED;
    background: #FFFFFF;
    border-color: #DDD6FE;
  }
  .flow-step-sep {
    color: #DDD6FE;
    font-size: .75rem;
    font-weight: 700;
  }

  /* Selection Chips & Cards */
  .selection-chip {
    cursor: pointer;
    border: 1.5px solid #ECE4F9;
    border-radius: 12px;
    padding: .75rem 1.1rem;
    background: #FFFFFF;
    transition: all .18s cubic-bezier(.2,.8,.3,1);
    display: inline-flex;
    flex-direction: column;
    min-width: 130px;
    text-align: left;
    user-select: none;
  }
  .selection-chip:hover {
    border-color: #7C3AED;
    transform: translateY(-2px);
    box-shadow: 0 4px 14px rgba(124,58,237,.14);
  }
  .selection-chip.active {
    border-color: #7C3AED;
    background: #FAF5FF;
    box-shadow: 0 4px 14px rgba(124,58,237,.18);
  }
  .selection-chip.active .chip-title {
    color: #7C3AED;
  }

  .pack-card {
    cursor: pointer;
    border: 1.5px solid #ECE4F9;
    border-radius: 12px;
    padding: .85rem 1.15rem;
    background: #FFFFFF;
    transition: all .18s cubic-bezier(.2,.8,.3,1);
    min-width: 140px;
    text-align: left;
    position: relative;
    user-select: none;
  }
  .pack-card:hover {
    border-color: #7C3AED;
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(124,58,237,.15);
  }
  .pack-card.active {
    border-color: #7C3AED;
    background: #FAF5FF;
    box-shadow: 0 0 0 1px #7C3AED, 0 6px 16px rgba(124,58,237,.2);
  }
  .pack-card.out-of-stock {
    opacity: .55;
    cursor: not-allowed;
    background: #F8FAFC;
  }

  /* Quantity Preset Chips */
  .qty-chip {
    cursor: pointer;
    border: 1.5px solid #ECE4F9;
    border-radius: 8px;
    padding: .45rem 1rem;
    font-weight: 700;
    font-size: .92rem;
    background: #FFFFFF;
    color: #18181B;
    transition: all .15s ease;
  }
  .qty-chip:hover {
    border-color: #7C3AED;
    color: #7C3AED;
  }
  .qty-chip.active {
    background: #7C3AED;
    border-color: #7C3AED;
    color: #FFFFFF;
  }

  /* Price summary calculation card */
  .calc-summary-card {
    background: #FAF5FF;
    border: 1.5px solid #DDD6FE;
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
  }

  .b-fav.active { color: #E11D48; border-color: #FECDD3; background: #FFF1F2; }
  .b-fav.active svg { fill: currentColor; }
</style>

<!-- Top Breadcrumb -->
<nav aria-label="breadcrumb" class="mb-2">
  <ol class="breadcrumb small mb-0">
    <li class="breadcrumb-item"><a href="<?= url('/shop') ?>" class="text-decoration-none text-brand"><?= e(t('nav.shop')) ?></a></li>
    <li class="breadcrumb-item"><a href="<?= url('/shop/category/' . (int) $product['category_id']) ?>" class="text-decoration-none text-brand"><?= e($catLabel) ?></a></li>
    <li class="breadcrumb-item active" aria-current="page"><?= e(product_name($product)) ?></li>
  </ol>
</nav>

<!-- Visual Selection Flow Chain -->
<div class="flow-step-bar">
  <div class="flow-step-item done">
    <i data-lucide="check-circle" style="width:14px;height:14px;"></i>
    <span>Category: <?= e($catLabel) ?></span>
  </div>
  <span class="flow-step-sep">→</span>
  <div class="flow-step-item done">
    <i data-lucide="check-circle" style="width:14px;height:14px;"></i>
    <span>Product: <?= e(product_name($product)) ?></span>
  </div>
  <span class="flow-step-sep">→</span>
  <div class="flow-step-item active" id="flow-ind-variant">
    <span class="badge bg-brand text-white rounded-pill px-1" style="font-size:10px;">3</span>
    <span id="flow-label-variant">Brand / Variant</span>
  </div>
  <span class="flow-step-sep">→</span>
  <div class="flow-step-item" id="flow-ind-pack">
    <span class="badge bg-secondary text-white rounded-pill px-1" style="font-size:10px;">4</span>
    <span id="flow-label-pack">Pack Size</span>
  </div>
  <span class="flow-step-sep">→</span>
  <div class="flow-step-item" id="flow-ind-qty">
    <span class="badge bg-secondary text-white rounded-pill px-1" style="font-size:10px;">5</span>
    <span id="flow-label-qty">Quantity</span>
  </div>
  <span class="flow-step-sep">→</span>
  <div class="flow-step-item" id="flow-ind-cart">
    <i data-lucide="shopping-cart" style="width:14px;height:14px;"></i>
    <span>Add to Cart</span>
  </div>
</div>

<!-- Product Hero Details -->
<div class="row g-4 mb-4">
  <div class="col-12 col-md-4 col-lg-3">
    <?php if (!empty($product['image'])): ?>
      <img src="<?= e(upload_url($product['image'])) ?>" alt="<?= e(product_name($product)) ?>"
           class="w-100 rounded-4 fade-up shadow-sm border bg-white" style="aspect-ratio:1/.9;object-fit:contain;padding:12px;">
    <?php else: ?>
      <div class="w-100 rounded-4 d-flex align-items-center justify-content-center fade-up shadow-sm border"
           style="aspect-ratio:1/.9;background:#FFFFFF;">
        <i data-lucide="package" style="width:72px;height:72px;color:#0C831F;opacity:.35;"></i>
      </div>
    <?php endif; ?>
  </div>
  <div class="col-12 col-md-8 col-lg-9">
    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
      <span class="b-badge-purple"><i data-lucide="map-pin" style="width:11px;height:11px;"></i> Society Delivery</span>
      <span class="b-badge-green"><i data-lucide="shield-check" class="me-1" style="width:12px;height:12px;"></i>Wholesale Society Savings</span>
      <span class="b-badge-grey"><?= e($catLabel) ?></span>
    </div>
    <h1 class="section-title mb-2 text-dark"><?= e(product_name($product)) ?></h1>
    <p class="text-muted small mb-2"><?= e($product['description'] ?: 'Pure household essential directly fulfilled with wholesale cooperative society discount.') ?></p>
    <div class="d-flex align-items-center gap-3 text-muted small mt-2">
      <div><i data-lucide="layers" class="me-1" style="width:14px;height:14px;color:#7C3AED;"></i><strong class="text-dark"><?= count($groups) ?></strong> Brand/Variants</div>
      <div><i data-lucide="boxes" class="me-1" style="width:14px;height:14px;color:#7C3AED;"></i><strong class="text-dark"><?= count($variants) ?></strong> Pack Sizes</div>
      <div><i data-lucide="truck" class="me-1" style="width:14px;height:14px;color:#7C3AED;"></i><strong class="text-dark">Doorstep Delivery</strong></div>
    </div>
  </div>
</div>

<!-- Guided Selection Card: Category → Product → Brand/Variant → Pack Size → Quantity → Add to Cart -->
<div class="b-card p-3 p-md-4 mb-4 fade-up product-buy shadow-sm border-0" id="selection-wizard" style="background:#FFFFFF;border-radius:18px;">
  
  <!-- Step 1: Brand / Variant Selection -->
  <div class="mb-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div class="fw-bold fs-6 text-dark d-flex align-items-center gap-2">
        <span class="d-inline-flex align-items-center justify-content-center bg-brand text-white rounded-circle" style="width:24px;height:24px;font-size:12px;">1</span>
        <span>Select Brand / Variant</span>
      </div>
      <span class="small text-muted" id="selected-variant-display">Choose an option below</span>
    </div>
    
    <div class="d-flex flex-wrap gap-2 pt-1" id="variant-chips-container">
      <?php foreach ($groups as $gName => $g): ?>
        <div class="selection-chip" data-variant-group="<?= e($gName) ?>">
          <div class="d-flex align-items-center justify-content-between w-100 gap-2">
            <span class="fw-bold text-dark chip-title"><?= e($gName) ?></span>
            <i data-lucide="check" class="chip-check text-brand d-none" style="width:16px;height:16px;"></i>
          </div>
          <span class="small text-muted mt-1">From <?= e(money($g['minPrice'])) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <hr class="my-3 text-muted opacity-25">

  <!-- Step 2: Pack Size Selection -->
  <div class="mb-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div class="fw-bold fs-6 text-dark d-flex align-items-center gap-2">
        <span class="d-inline-flex align-items-center justify-content-center bg-brand text-white rounded-circle" style="width:24px;height:24px;font-size:12px;">2</span>
        <span>Choose Pack Size</span>
      </div>
      <span class="small text-muted" id="selected-pack-display">Select size</span>
    </div>
    
    <div class="d-flex flex-wrap gap-2 pt-1" id="pack-cards-container">
      <!-- Dynamically filled by JavaScript based on active Brand/Variant -->
    </div>
  </div>

  <hr class="my-3 text-muted opacity-25">

  <!-- Step 3: Quantity Selection -->
  <div class="mb-4">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <div class="fw-bold fs-6 text-dark d-flex align-items-center gap-2">
        <span class="d-inline-flex align-items-center justify-content-center bg-brand text-white rounded-circle" style="width:24px;height:24px;font-size:12px;">3</span>
        <span>Choose Quantity</span>
      </div>
      <span class="small text-muted" id="qty-helper">Packs to purchase</span>
    </div>

    <div class="d-flex flex-wrap align-items-center gap-3 pt-1">
      <!-- Quick chips: 1, 2, 3, 5 -->
      <div class="d-flex gap-2" id="qty-preset-chips">
        <button type="button" class="qty-chip active" data-preset-qty="1">1</button>
        <button type="button" class="qty-chip" data-preset-qty="2">2</button>
        <button type="button" class="qty-chip" data-preset-qty="3">3</button>
        <button type="button" class="qty-chip" data-preset-qty="5">5</button>
      </div>

      <!-- Custom Stepper -->
      <div class="d-flex align-items-center gap-2">
        <span class="small text-muted">or custom:</span>
        <div class="qty-stepper">
          <button type="button" class="qty-minus" aria-label="minus">−</button>
          <input type="number" class="qty-input" id="wizard-qty-input" value="1" min="1" max="50" step="1"
                 data-qty-for="" aria-label="Quantity">
          <button type="button" class="qty-plus" aria-label="plus">+</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Step 4: Live Price Calculation & Add to Cart -->
  <div class="calc-summary-card mt-3">
    <div class="row align-items-center g-3">
      <div class="col-12 col-lg-7">
        <div class="d-flex align-items-baseline gap-2 flex-wrap">
          <span class="text-muted small">Total Calculation:</span>
          <span class="fw-bold fs-4 text-dark" id="calc-total-money">₹0.00</span>
          <span class="text-decoration-line-through text-muted small" id="calc-mrp-total"></span>
          <span class="b-badge-green" id="calc-savings-badge" style="font-size:11px;"></span>
        </div>
        <div class="small text-muted mt-1" id="calc-breakdown-text">
          Select variant and pack size to calculate price
        </div>
      </div>
      
      <div class="col-12 col-lg-5 text-lg-end d-flex align-items-center justify-content-lg-end gap-2 flex-wrap">
        <button type="button" class="btn btn-brand b-btn-pill px-4 py-2 flex-grow-1 flex-lg-grow-0 fw-bold"
                id="wizard-add-to-cart-btn" data-add-to-cart="">
          <i data-lucide="shopping-cart" class="me-1"></i>
          <span id="wizard-btn-label"><?= e(t('shop.add_to_cart')) ?></span>
        </button>
        
        <button type="button" class="btn btn-soft b-fav b-btn-pill p-2" id="wizard-fav-btn"
                data-fav-toggle="" aria-label="<?= e(t('nav.favourites')) ?>">
          <i data-lucide="heart" style="width:20px;height:20px;"></i>
        </button>
      </div>
    </div>
  </div>

</div>

<!-- Save to Monthly List Form -->
<?php if ($variants && $lists): ?>
<div class="b-card mb-4 fade-up">
  <form method="post" action="<?= url('/lists/0/items') ?>" class="row g-2 align-items-end">
    <?= csrf_field() ?>
    <div class="col-12 col-md-5">
      <label class="form-label small fw-semibold mb-1" for="sl-variant">Add Variant to Monthly Grocery List</label>
      <select class="form-select form-select-sm" id="sl-variant" name="variant_id">
        <?php foreach ($variants as $v): ?>
          <option value="<?= (int) $v['id'] ?>">
            <?= e($v['variant_label']) ?> — <?= e(pack_label($v['pack_size'], $v['unit'])) ?> (<?= e(money($v['price'])) ?>)
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3">
      <label class="form-label small fw-semibold mb-1" for="sl-list"><?= e(t('shop.save_to_list')) ?></label>
      <select class="form-select form-select-sm" id="sl-list" name="list_id">
        <?php foreach ($lists as $l): ?>
          <option value="<?= (int) $l['id'] ?>"><?= e($l['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-2">
      <label class="form-label small fw-semibold mb-1" for="sl-qty"><?= e(t('shop.qty')) ?></label>
      <input type="number" class="form-control form-control-sm" id="sl-qty" name="qty" value="1" min="1" step="1">
    </div>
    <div class="col-12 col-md-2">
      <button type="submit" class="btn btn-outline-brand btn-sm b-btn-pill w-100">
        <i data-lucide="list-plus" class="me-1"></i>Save
      </button>
    </div>
  </form>
</div>
<?php endif; ?>

<!-- All Configured Pack Sizes Reference Table -->
<div class="card border-0 shadow-sm rounded-4 mb-4 fade-up" style="background:#FFFFFF;">
  <div class="card-body p-3 p-md-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h3 class="fs-6 fw-bold mb-0 text-dark"><i data-lucide="list" class="me-1 text-brand" style="width:16px;height:16px;"></i>All Configured Variants & Prices</h3>
      <span class="small text-muted"><?= count($variants) ?> pack options available</span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm table-hover align-middle mb-0" style="font-size:.88rem;">
        <thead class="table-light text-muted small">
          <tr>
            <th>Brand / Variant</th>
            <th>Pack Size</th>
            <th>Reference MRP</th>
            <th>Bachat Society Price</th>
            <th>Stock Status</th>
            <th class="text-end">Quick Action</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($variants as $v):
              $pDiff = (float) $v['reference_price'] - (float) $v['price'];
          ?>
            <tr>
              <td class="fw-semibold text-dark"><?= e($v['variant_label']) ?></td>
              <td><?= e(pack_label($v['pack_size'], $v['unit'])) ?></td>
              <td class="text-muted text-decoration-line-through"><?= $v['reference_price'] > 0 ? e(money($v['reference_price'])) : '—' ?></td>
              <td class="fw-bold text-brand"><?= e(money($v['price'])) ?></td>
              <td>
                <span class="<?= (float) $v['stock_qty'] > 0 ? 'badge bg-success-subtle text-success' : 'badge bg-danger-subtle text-danger' ?> rounded-pill">
                  <?= (float) $v['stock_qty'] > 0 ? 'In Stock' : 'Out of Stock' ?>
                </span>
              </td>
              <td class="text-end">
                <button type="button" class="btn btn-outline-brand btn-sm rounded-pill py-0 px-2 select-variant-row-btn"
                        data-row-group="<?= e(trim((string) ($v['variant_label'] ?: ($v['brand_name'] ?: 'Standard')))) ?>"
                        data-row-vid="<?= (int) $v['id'] ?>">
                  Select
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Customer Reviews -->
<h2 class="fs-5 fw-bold mt-4 mb-2"><?= e(t('shop.customer_reviews')) ?></h2>
<?php if (!$reviews): ?>
  <div class="b-card empty-state">
    <i data-lucide="star"></i>
    <p><?= e(t('shop.no_reviews')) ?></p>
  </div>
<?php else: ?>
  <?php foreach ($reviews as $r): ?>
    <div class="b-card mb-2 fade-up">
      <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
        <div>
          <span class="rating-stars"><?= str_repeat('★', (int) $r['rating']) ?><?= str_repeat('☆', max(0, 5 - (int) $r['rating'])) ?></span>
          <?php if (!empty($r['title'])): ?><span class="fw-semibold ms-2"><?= e($r['title']) ?></span><?php endif; ?>
        </div>
        <span class="small text-muted"><?= e(human_date($r['created_at'])) ?></span>
      </div>
      <?php if (!empty($r['body'])): ?>
        <p class="small mt-2 mb-2"><?= e($r['body']) ?></p>
      <?php endif; ?>
      <div class="small text-muted">
        <i data-lucide="badge-check" class="b-verified" style="width:14px;height:14px;vertical-align:-2px;"></i>
        <?= e(explode(' ', (string) $r['user_name'])[0]) ?> · <?= e(t('shop.verified_buyer')) ?>
      </div>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<!-- Interactive Selection Flow Engine (Vanilla JS) -->
<script>
(function() {
  var variantsData = <?= json_encode($variantsPayload, JSON_UNESCAPED_UNICODE) ?>;
  var favIds = <?= json_encode($favIds) ?>;

  var currentGroup = null;
  var currentVariant = null;
  var currentQty = 1;

  var variantChipsContainer = document.getElementById('variant-chips-container');
  var packCardsContainer = document.getElementById('pack-cards-container');
  var qtyPresetChips = document.getElementById('qty-preset-chips');
  var qtyInput = document.getElementById('wizard-qty-input');
  var addToCartBtn = document.getElementById('wizard-add-to-cart-btn');
  var favBtn = document.getElementById('wizard-fav-btn');

  var selVariantDisp = document.getElementById('selected-variant-display');
  var selPackDisp = document.getElementById('selected-pack-display');
  var calcTotalMoney = document.getElementById('calc-total-money');
  var calcMrpTotal = document.getElementById('calc-mrp-total');
  var calcSavingsBadge = document.getElementById('calc-savings-badge');
  var calcBreakdownText = document.getElementById('calc-breakdown-text');
  var wizardBtnLabel = document.getElementById('wizard-btn-label');

  var flowLabelVariant = document.getElementById('flow-label-variant');
  var flowLabelPack = document.getElementById('flow-label-pack');
  var flowLabelQty = document.getElementById('flow-label-qty');

  function init() {
    if (!variantsData || variantsData.length === 0) return;

    // Pick first group by default
    currentGroup = variantsData[0].group;
    selectGroup(currentGroup, variantsData[0].id);

    // Bind Group click events
    if (variantChipsContainer) {
      variantChipsContainer.addEventListener('click', function(e) {
        var chip = e.target.closest('.selection-chip');
        if (!chip) return;
        var groupName = chip.getAttribute('data-variant-group');
        selectGroup(groupName);
      });
    }

    // Bind Pack click events
    if (packCardsContainer) {
      packCardsContainer.addEventListener('click', function(e) {
        var card = e.target.closest('.pack-card');
        if (!card || card.classList.contains('out-of-stock')) return;
        var vid = parseInt(card.getAttribute('data-variant-id'), 10);
        selectVariantById(vid);
      });
    }

    // Bind Quantity Preset Chips
    if (qtyPresetChips) {
      qtyPresetChips.addEventListener('click', function(e) {
        var btn = e.target.closest('.qty-chip');
        if (!btn) return;
        var q = parseInt(btn.getAttribute('data-preset-qty'), 10) || 1;
        setQuantity(q);
      });
    }

    // Bind Quantity Input Change
    if (qtyInput) {
      qtyInput.addEventListener('input', function() {
        var val = parseInt(qtyInput.value, 10);
        if (isNaN(val) || val < 1) val = 1;
        setQuantity(val, false);
      });
      qtyInput.addEventListener('change', function() {
        var val = parseInt(qtyInput.value, 10);
        if (isNaN(val) || val < 1) val = 1;
        setQuantity(val, true);
      });
    }

    // Quick Select from Table rows
    document.addEventListener('click', function(e) {
      var rowBtn = e.target.closest('.select-variant-row-btn');
      if (rowBtn) {
        var g = rowBtn.getAttribute('data-row-group');
        var vid = parseInt(rowBtn.getAttribute('data-row-vid'), 10);
        selectGroup(g, vid);
        var wiz = document.getElementById('selection-wizard');
        if (wiz) {
          wiz.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
      }
    });
  }

  function selectGroup(groupName, targetVariantId) {
    currentGroup = groupName;

    // Update Step 1 active chip
    var allChips = variantChipsContainer.querySelectorAll('.selection-chip');
    allChips.forEach(function(c) {
      var match = c.getAttribute('data-variant-group') === groupName;
      c.classList.toggle('active', match);
      var check = c.querySelector('.chip-check');
      if (check) check.classList.toggle('d-none', !match);
    });

    if (selVariantDisp) {
      selVariantDisp.innerHTML = '<span class="text-brand fw-bold">' + escapeHtml(groupName) + '</span> selected';
    }
    if (flowLabelVariant) {
      flowLabelVariant.innerText = groupName;
    }

    // Filter variants for this group
    var groupVariants = variantsData.filter(function(v) { return v.group === groupName; });

    // Render Step 2 Pack Sizes
    renderPackCards(groupVariants);

    // Auto-select variant
    var toSelect = null;
    if (targetVariantId) {
      toSelect = groupVariants.find(function(v) { return v.id === targetVariantId; });
    }
    if (!toSelect) {
      toSelect = groupVariants.find(function(v) { return v.stock_qty > 0; }) || groupVariants[0];
    }

    if (toSelect) {
      selectVariant(toSelect);
    }
  }

  function renderPackCards(list) {
    packCardsContainer.innerHTML = '';
    list.forEach(function(v) {
      var inStock = v.stock_qty > 0;
      var hasDisc = v.reference_price > v.price;
      var savePct = hasDisc ? Math.round(((v.reference_price - v.price) / v.reference_price) * 100) : 0;

      var div = document.createElement('div');
      div.className = 'pack-card ' + (inStock ? '' : 'out-of-stock');
      div.setAttribute('data-variant-id', v.id);

      var html = '<div class="fw-bold text-dark fs-6">' + escapeHtml(v.pack_label) + '</div>' +
        '<div class="d-flex align-items-baseline gap-1 mt-1">' +
          '<span class="fw-bold text-brand">' + formatMoney(v.price) + '</span>';
      if (hasDisc) {
        html += '<span class="small text-muted text-decoration-line-through" style="font-size:.78rem;">' + formatMoney(v.reference_price) + '</span>';
      }
      html += '</div>';

      if (hasDisc) {
        html += '<span class="badge bg-success-subtle text-success rounded-pill mt-1" style="font-size:10px;">Save ' + savePct + '%</span>';
      } else if (!inStock) {
        html += '<span class="badge bg-danger-subtle text-danger rounded-pill mt-1" style="font-size:10px;">Out of Stock</span>';
      } else {
        html += '<span class="badge bg-light text-muted border rounded-pill mt-1" style="font-size:10px;">In Stock</span>';
      }

      div.innerHTML = html;
      packCardsContainer.appendChild(div);
    });
    if (window.lucide && window.lucide.createIcons) {
      window.lucide.createIcons();
    }
  }

  function selectVariantById(vid) {
    var v = variantsData.find(function(item) { return item.id === vid; });
    if (v) selectVariant(v);
  }

  function selectVariant(v) {
    currentVariant = v;

    // Highlight pack card
    var allCards = packCardsContainer.querySelectorAll('.pack-card');
    allCards.forEach(function(card) {
      var id = parseInt(card.getAttribute('data-variant-id'), 10);
      card.classList.toggle('active', id === v.id);
    });

    if (selPackDisp) {
      selPackDisp.innerHTML = '<span class="text-brand fw-bold">' + escapeHtml(v.pack_label) + '</span> (' + formatMoney(v.price) + ')';
    }
    if (flowLabelPack) {
      flowLabelPack.innerText = v.pack_label;
    }

    // Set buttons & attributes
    if (addToCartBtn) {
      addToCartBtn.setAttribute('data-add-to-cart', v.id);
      addToCartBtn.disabled = v.stock_qty <= 0;
    }
    if (qtyInput) {
      qtyInput.setAttribute('data-qty-for', v.id);
      qtyInput.setAttribute('data-max', v.max_qty || 50);
      qtyInput.setAttribute('data-min', v.min_qty || 1);
    }
    if (favBtn) {
      favBtn.setAttribute('data-fav-toggle', v.id);
      var isFaved = favIds.indexOf(v.id) !== -1;
      favBtn.classList.toggle('active', isFaved);
      var icon = favBtn.querySelector('svg');
      if (icon) {
        if (isFaved) icon.setAttribute('fill', 'currentColor');
        else icon.removeAttribute('fill');
      }
    }

    // Update Dropdown in Monthly List form if present
    var slVariant = document.getElementById('sl-variant');
    if (slVariant) {
      slVariant.value = v.id;
    }

    recalcPrice();
  }

  function setQuantity(q, updatePresets) {
    currentQty = Math.max(1, q);
    if (qtyInput) {
      qtyInput.value = currentQty;
    }
    if (flowLabelQty) {
      flowLabelQty.innerText = currentQty + (currentQty === 1 ? ' pack' : ' packs');
    }

    // Highlight preset chips
    if (updatePresets !== false && qtyPresetChips) {
      var chips = qtyPresetChips.querySelectorAll('.qty-chip');
      chips.forEach(function(c) {
        var pq = parseInt(c.getAttribute('data-preset-qty'), 10);
        c.classList.toggle('active', pq === currentQty);
      });
    }

    recalcPrice();
  }

  function recalcPrice() {
    if (!currentVariant) return;

    var unitPrice = currentVariant.price;
    var refPrice = currentVariant.reference_price;
    var totalPrice = unitPrice * currentQty;
    var totalRef = refPrice * currentQty;
    var totalSavings = totalRef > totalPrice ? totalRef - totalPrice : 0;
    var savePercent = totalRef > 0 ? Math.round((totalSavings / totalRef) * 100) : 0;

    if (calcTotalMoney) {
      calcTotalMoney.innerText = formatMoney(totalPrice);
    }

    if (calcMrpTotal) {
      if (totalSavings > 0) {
        calcMrpTotal.innerText = formatMoney(totalRef);
        calcMrpTotal.style.display = 'inline';
      } else {
        calcMrpTotal.style.display = 'none';
      }
    }

    if (calcSavingsBadge) {
      if (totalSavings > 0) {
        calcSavingsBadge.innerText = 'Save ' + formatMoney(totalSavings) + ' (' + savePercent + '%)';
        calcSavingsBadge.style.display = 'inline-block';
      } else {
        calcSavingsBadge.style.display = 'none';
      }
    }

    if (calcBreakdownText) {
      var label = currentVariant.group + ' (' + currentVariant.pack_label + ')';
      calcBreakdownText.innerHTML = '<strong>' + escapeHtml(label) + '</strong> × <strong>' + currentQty + ' ' + (currentQty === 1 ? 'pack' : 'packs') + '</strong> at ' + formatMoney(unitPrice) + ' each';
    }

    if (wizardBtnLabel) {
      if (currentVariant.stock_qty <= 0) {
        wizardBtnLabel.innerText = 'Out of Stock';
      } else {
        wizardBtnLabel.innerText = 'Add ' + currentQty + ' to Cart — ' + formatMoney(totalPrice);
      }
    }
  }

  function formatMoney(num) {
    return '₹' + parseFloat(num).toFixed(2);
  }

  function escapeHtml(str) {
    var div = document.createElement('div');
    div.innerText = str || '';
    return div.innerHTML;
  }

  // Launch on DOM ready
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})();
</script>
