<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Upload;
use App\Services\BulkGroceryService;
use App\Services\Notify;
use RuntimeException;

/**
 * Vendor panel + public vendor registration.
 * Scoping: vendors only ever see rows tied to Auth::id().
 */
final class VendorController extends Controller
{
    /* ============================== PUBLIC REGISTER ============================== */

    public function showRegister(): void
    {
        if (Auth::check() && Auth::isRole('vendor')) {
            $this->redirect('/vendor/dashboard');
        }
        $this->view('vendor/register', [
            'active'    => 'join',
            'pageTitle' => t('vendor.register_title'),
        ]);
    }

    public function register(): void
    {
        $errors = $this->validate([
            'name'                 => 'required|max:120',
            'email'                => 'required|email|max:150',
            'phone'                => 'max:20',
            'password'             => 'required|min:6|max:64',
            'password_confirmation' => 'required|same:password',
            'city'                 => 'required|max:80',
            'category_note'        => 'max:600',
        ]);
        if ($errors) {
            flash('error', implode(' ', $errors));
            $this->keepOld();
            $this->back();
        }

        $email = strtolower((string) $this->post('email'));
        if ((int) Database::scalar('SELECT COUNT(*) FROM users WHERE email = ?', [$email]) > 0) {
            flash('error', t('vendor.email_taken'));
            $this->keepOld();
            $this->back();
        }

        Database::run(
            "INSERT INTO users (name, email, phone, password_hash, role, society_id, status, language, created_at, updated_at)
             VALUES (?,?,?,?, 'vendor', NULL, 'pending', ?, NOW(), NOW())",
            [
                (string) $this->post('name'),
                $email,
                (string) ($this->post('phone', '') ?? '') ?: null,
                password_hash((string) $this->post('password'), PASSWORD_DEFAULT),
                in_array(lang(), ['en', 'hi', 'mr'], true) ? lang() : 'en',
            ]
        );
        $newId = (int) Database::pdo()->lastInsertId();

        Audit::log('vendor.register', 'user', $newId, [
            'email'    => $email,
            'city'     => $this->post('city'),
            'supplies' => $this->post('category_note'),
        ]);

        flash('success', t('vendor.register_done'));
        $this->redirect('/login');
    }

    /* ============================== DASHBOARD ============================== */

    public function dashboard(): void
    {
        $this->requireRole(['vendor']);
        $uid = (int) Auth::id();

        $openRfqs = Database::fetchAll(
            "SELECT c.* FROM campaigns c
              WHERE c.status IN ('active','extended')
                AND NOT EXISTS (SELECT 1 FROM quotations q WHERE q.campaign_id = c.id AND q.vendor_id = ?)
              ORDER BY (c.deadline IS NULL), c.deadline ASC LIMIT 6",
            [$uid]
        );
        $openRfqCount = (int) Database::scalar(
            "SELECT COUNT(*) FROM campaigns c
              WHERE c.status IN ('active','extended')
                AND NOT EXISTS (SELECT 1 FROM quotations q WHERE q.campaign_id = c.id AND q.vendor_id = ?)",
            [$uid]
        );

        $qSubmitted = (int) Database::scalar("SELECT COUNT(*) FROM quotations WHERE vendor_id = ? AND status = 'submitted'", [$uid]);
        $qSelected  = (int) Database::scalar("SELECT COUNT(*) FROM quotations WHERE vendor_id = ? AND status = 'selected'", [$uid]);

        $posInProgress = (int) Database::scalar(
            "SELECT COUNT(*) FROM purchase_orders WHERE vendor_id = ? AND status IN ('sent','accepted','partially_received')",
            [$uid]
        );
        $latestPos = Database::fetchAll(
            'SELECT po.*, s.name AS society_name,
                    (SELECT COUNT(*) FROM purchase_order_items x WHERE x.po_id = po.id) AS item_count
               FROM purchase_orders po
               LEFT JOIN societies s ON s.id = po.society_id
              WHERE po.vendor_id = ? ORDER BY po.id DESC LIMIT 5',
            [$uid]
        );

        $listings       = (int) Database::scalar('SELECT COUNT(*) FROM products WHERE vendor_id = ?', [$uid]);
        $listingsActive = (int) Database::scalar('SELECT COUNT(*) FROM products WHERE vendor_id = ? AND is_active = 1', [$uid]);

        $this->view('vendor/dashboard', [
            'active'        => 'vendor-dashboard',
            'pageTitle'     => t('vendor.dashboard_title'),
            'openRfqs'      => $openRfqs,
            'openRfqCount'  => $openRfqCount,
            'qSubmitted'    => $qSubmitted,
            'qSelected'     => $qSelected,
            'posInProgress' => $posInProgress,
            'latestPos'     => $latestPos,
            'listings'      => $listings,
            'listingsActive' => $listingsActive,
        ]);
    }

    /* ============================== QUOTATIONS / RFQs ============================== */

    public function quotations(): void
    {
        $this->requireRole(['vendor']);
        $uid = (int) Auth::id();

        $campaigns = Database::fetchAll(
            "SELECT c.* FROM campaigns c WHERE c.status IN ('active','extended')
              ORDER BY (c.deadline IS NULL), c.deadline ASC, c.id DESC LIMIT 50"
        );

        $items = [];
        $ids   = array_map('intval', array_column($campaigns, 'id'));
        if ($ids !== []) {
            foreach (
                Database::fetchAll(
                    'SELECT ci.*, p.name_en, p.name_hi, p.name_mr, b.name AS brand_name, v.pack_size, v.unit
                       FROM campaign_items ci
                       JOIN product_variants v ON v.id = ci.variant_id
                       JOIN products p         ON p.id = v.product_id
                       LEFT JOIN brands b      ON b.id = v.brand_id
                      WHERE ci.campaign_id IN (' . implode(',', $ids) . ')
                      ORDER BY ci.id'
                ) as $r
            ) {
                $items[(int) $r['campaign_id']][] = $r;
            }
        }

        $myQuotes = [];
        foreach (Database::fetchAll('SELECT * FROM quotations WHERE vendor_id = ?', [$uid]) as $q) {
            $myQuotes[(int) $q['campaign_id']] = $q;
        }

        $this->view('vendor/quotations', [
            'active'    => 'vendor-quotations',
            'pageTitle' => t('vendor.quotations_title'),
            'campaigns' => $campaigns,
            'items'     => $items,
            'myQuotes'  => $myQuotes,
        ]);
    }

    public function submitQuotation(): void
    {
        $this->requireRole(['vendor']);
        $uid = (int) Auth::id();

        $camp = Database::fetch('SELECT * FROM campaigns WHERE id = ?', [(int) ($this->post('campaign_id', 0) ?? 0)]);
        if (!$camp || !in_array($camp['status'], ['active', 'extended'], true)) {
            flash('error', t('vendor.campaign_not_open'));
            $this->redirect('/vendor/quotations');
        }

        if (Database::fetch('SELECT id FROM quotations WHERE campaign_id = ? AND vendor_id = ? LIMIT 1', [$camp['id'], $uid])) {
            flash('info', t('vendor.quoted_already'));
            $this->redirect('/vendor/quotations');
        }

        $errors = $this->validate([
            'unit_price'     => 'required|numeric|min:0',
            'min_qty'        => 'numeric|min:0',
            'delivery_cost'  => 'numeric|min:0',
            'tax_percent'    => 'numeric|min:0|max:99',
            'lead_time_days' => 'int|min:0',
            'notes'          => 'max:400',
        ]);
        if ($errors) {
            flash('error', implode(' ', $errors));
            $this->keepOld();
            $this->back();
        }

        Database::run(
            "INSERT INTO quotations (campaign_id, vendor_id, unit_price, min_qty, delivery_cost, tax_percent, lead_time_days, notes, status, created_at)
             VALUES (?,?,?,?,?,?,?,?, 'submitted', NOW())",
            [
                (int) $camp['id'], $uid,
                round((float) $this->post('unit_price'), 2),
                round((float) ($this->post('min_qty', 0) ?? 0), 3),
                round((float) ($this->post('delivery_cost', 0) ?? 0), 2),
                round((float) ($this->post('tax_percent', 0) ?? 0), 2),
                max(0, (int) ($this->post('lead_time_days', 7) ?? 7)),
                mb_substr((string) ($this->post('notes', '') ?? ''), 0, 400) ?: null,
            ]
        );

        $vendorName = Auth::user()['name'] ?? 'A vendor';
        $body       = $vendorName . ' submitted a quotation for "' . $camp['title'] . '"';
        if ($camp['society_id'] !== null) {
            Notify::societyAdmins((int) $camp['society_id'], 'quotation', 'New quotation received', $body, '/admin/campaigns/' . $camp['id']);
        }
        Notify::superAdmins('quotation', 'New quotation received', $body, '/super/quotations');
        Audit::log('quotation.submit', 'campaign', (int) $camp['id'], ['vendor_id' => $uid, 'unit_price' => $this->post('unit_price')]);

        flash('success', t('vendor.quotation_done'));
        $this->redirect('/vendor/quotations');
    }

    /* ============================== PRODUCTS ============================== */

    public function products(): void
    {
        $this->requireRole(['vendor']);
        $uid = (int) Auth::id();

        $products = Database::fetchAll('SELECT * FROM products WHERE vendor_id = ? ORDER BY id DESC', [$uid]);

        $variants = [];
        $ids      = array_map('intval', array_column($products, 'id'));
        if ($ids !== []) {
            foreach (
                Database::fetchAll(
                    'SELECT pv.*, b.name AS brand_name FROM product_variants pv
                      LEFT JOIN brands b ON b.id = pv.brand_id
                     WHERE pv.product_id IN (' . implode(',', $ids) . ') ORDER BY pv.id'
                ) as $v
            ) {
                $variants[(int) $v['product_id']][] = $v;
            }
        }

        $categories = Database::fetchAll('SELECT id, name_en, name_hi, name_mr FROM categories WHERE is_active = 1 ORDER BY sort_order, name_en');
        $brands     = Database::fetchAll('SELECT id, name FROM brands ORDER BY name');
        $units      = ['g', 'kg', 'ml', 'l', 'pc', 'pack', 'bottle', 'bag', 'box'];

        $this->view('vendor/products', [
            'active'     => 'vendor-products',
            'pageTitle'  => t('vendor.products_title'),
            'products'   => $products,
            'variants'   => $variants,
            'categories' => $categories,
            'brands'     => $brands,
            'units'      => $units,
        ]);
    }

    public function saveProduct(): void
    {
        $this->requireRole(['vendor']);
        $uid = (int) Auth::id();

        $errors = $this->validate([
            'name_en'     => 'required|max:150',
            'name_hi'     => 'max:150',
            'name_mr'     => 'max:150',
            'category_id' => 'required|int',
            'unit'        => 'in:g,kg,ml,l,pc,pack,bottle,bag,box',
            'description' => 'max:2000',
        ]);
        if ($errors) {
            flash('error', implode(' ', $errors));
            $this->keepOld();
            $this->back();
        }

        $catId = (int) $this->post('category_id');
        if (!Database::fetch('SELECT id FROM categories WHERE id = ? AND is_active = 1', [$catId])) {
            flash('error', t('validation.in', ['field' => 'Category']));
            $this->keepOld();
            $this->back();
        }

        $image = null;
        $file  = $this->file('image');
        if ($file) {
            try {
                $image = Upload::image($file, 'products');
            } catch (RuntimeException $e) {
                flash('error', $e->getMessage());
                $this->keepOld();
                $this->back();
            }
        }

        $nameEn = (string) $this->post('name_en');
        $slug   = strtolower((string) preg_replace('/[^a-z0-9]+/', '-', $nameEn));
        $slug   = trim($slug, '-') . '-' . time();

        Database::run(
            "INSERT INTO products (category_id, name_en, name_hi, name_mr, slug, description, image, unit, fulfillment, vendor_id, is_active, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?, 'vendor_direct', ?, 0, NOW(), NOW())",
            [
                $catId, $nameEn,
                (string) ($this->post('name_hi', '') ?? '') ?: null,
                (string) ($this->post('name_mr', '') ?? '') ?: null,
                $slug,
                (string) ($this->post('description', '') ?? '') ?: null,
                $image,
                (string) ($this->post('unit', 'pc') ?? 'pc'),
                $uid,
            ]
        );
        $pid = (int) Database::pdo()->lastInsertId();

        Audit::log('vendor.product.create', 'product', $pid, ['name' => $nameEn, 'vendor_id' => $uid]);
        flash('success', t('vendor.product_saved'));
        $this->redirect('/vendor/products');
    }

    public function saveVariant(): void
    {
        $this->requireRole(['vendor']);
        $uid = (int) Auth::id();

        $pid = (int) ($this->post('product_id', 0) ?? 0);
        if (!Database::fetch('SELECT id FROM products WHERE id = ? AND vendor_id = ?', [$pid, $uid])) {
            flash('error', t('common.unauthorized'));
            $this->redirect('/vendor/products');
        }

        $errors = $this->validate([
            'pack_size'      => 'required|numeric|min:0.001',
            'unit'           => 'in:g,kg,ml,l,pc,pack,bottle,bag,box',
            'sku'            => 'max:40',
            'reference_price' => 'numeric|min:0',
            'selling_price'  => 'required|numeric|min:0',
            'min_qty'        => 'numeric|min:0',
        ]);
        if ($errors) {
            flash('error', implode(' ', $errors));
            $this->keepOld();
            $this->back();
        }

        $brandId = (int) ($this->post('brand_id', 0) ?? 0);

        Database::run(
            'INSERT INTO product_variants (product_id, brand_id, pack_size, unit, sku, reference_price, selling_price, stock_qty, min_qty, is_active)
             VALUES (?,?,?,?,?,?,?,?,?,1)',
            [
                $pid, $brandId > 0 ? $brandId : null,
                round((float) $this->post('pack_size'), 3),
                (string) ($this->post('unit', 'pc') ?? 'pc'),
                (string) ($this->post('sku', '') ?? '') ?: null,
                round((float) ($this->post('reference_price', 0) ?? 0), 2),
                round((float) $this->post('selling_price'), 2),
                0,
                round((float) ($this->post('min_qty', 1) ?? 1), 3),
            ]
        );

        Audit::log('vendor.variant.create', 'product', $pid, ['sku' => $this->post('sku'), 'vendor_id' => $uid]);
        flash('success', t('vendor.variant_saved'));
        $this->redirect('/vendor/products');
    }

    public function catalogTemplate(): void
    {
        BulkGroceryService::downloadSampleTemplate();
    }

    public function bulkUploadGroceries(): void
    {
        $this->requireRole(['vendor']);
        $uid = (int) Auth::id();

        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Please select a valid CSV file to upload.');
            $this->redirect('/vendor/products');
        }

        $file = (string) $_FILES['csv_file']['tmp_name'];
        $stats = BulkGroceryService::processUpload($file, null, true, $uid);

        if ($stats['total_rows'] > 0 && empty($stats['errors'])) {
            flash('success', "Bulk grocery upload completed! Added {$stats['products_created']} products, created {$stats['variants_created']} pack sizes.");
        } elseif ($stats['total_rows'] > 0) {
            flash('info', "Processed: {$stats['products_created']} created, {$stats['variants_created']} pack sizes. " . count($stats['errors']) . " row(s) had errors.");
        } else {
            flash('error', 'No grocery rows could be processed. Please check the CSV column format.');
        }

        $this->redirect('/vendor/products');
    }

    public function bulkUploadImages(): void
    {
        $this->requireRole(['vendor']);
        $uid = (int) Auth::id();

        if (!isset($_FILES['images']) || !is_array($_FILES['images']['name']) || count(array_filter($_FILES['images']['name'])) === 0) {
            flash('error', 'Please select image files to upload.');
            $this->redirect('/vendor/products');
        }

        $stats = BulkGroceryService::processBulkImages($_FILES['images'], $uid);

        if ($stats['matched'] > 0 && empty($stats['errors'])) {
            flash('success', "Bulk image upload completed! Updated images for {$stats['matched']} product(s).");
        } elseif ($stats['matched'] > 0) {
            flash('info', "Matched {$stats['matched']} image(s), skipped {$stats['skipped']}. " . count($stats['errors']) . " error(s).");
        } else {
            $msg = 'No images could be matched to your products. Name files by product name, slug, or ID.';
            if (!empty($stats['errors'])) {
                $msg .= ' First error: ' . $stats['errors'][0];
            }
            flash('error', $msg);
        }

        $this->redirect('/vendor/products');
    }

    /* ============================== PURCHASE ORDERS ============================== */

    public function purchaseOrders(): void
    {
        $this->requireRole(['vendor']);
        $uid = (int) Auth::id();

        $pos = Database::fetchAll(
            'SELECT po.*, s.name AS society_name,
                    (SELECT COUNT(*) FROM purchase_order_items x WHERE x.po_id = po.id) AS item_count,
                    (SELECT COALESCE(SUM(x.qty),0)          FROM purchase_order_items x WHERE x.po_id = po.id) AS total_qty,
                    (SELECT COALESCE(SUM(x.received_qty),0) FROM purchase_order_items x WHERE x.po_id = po.id) AS received_qty
               FROM purchase_orders po
               LEFT JOIN societies s ON s.id = po.society_id
              WHERE po.vendor_id = ?
              ORDER BY po.id DESC',
            [$uid]
        );

        $detail = null;
        $detailItems = [];
        $reqId = (int) ($this->query('id', 0) ?? 0);
        if ($reqId > 0) {
            $detail = Database::fetch(
                'SELECT po.*, s.name AS society_name FROM purchase_orders po
                  LEFT JOIN societies s ON s.id = po.society_id
                 WHERE po.id = ? AND po.vendor_id = ?',
                [$reqId, $uid]
            );
            if ($detail) {
                $detailItems = Database::fetchAll(
                    'SELECT poi.*, p.name_en, p.name_hi, p.name_mr, b.name AS brand_name, v.pack_size, v.unit
                       FROM purchase_order_items poi
                       JOIN product_variants v ON v.id = poi.variant_id
                       JOIN products p         ON p.id = v.product_id
                       LEFT JOIN brands b      ON b.id = v.brand_id
                      WHERE poi.po_id = ? ORDER BY poi.id',
                    [$detail['id']]
                );
            }
        }

        $this->view('vendor/pos', [
            'active'      => 'vendor-purchase-orders',
            'pageTitle'   => t('vendor.pos_title'),
            'pos'         => $pos,
            'detail'      => $detail,
            'detailItems' => $detailItems,
        ]);
    }

    public function poStatus(string $id): void
    {
        $this->requireRole(['vendor']);
        $uid = (int) Auth::id();

        $po = Database::fetch('SELECT * FROM purchase_orders WHERE id = ? AND vendor_id = ?', [(int) $id, $uid]);
        if (!$po) {
            flash('error', t('common.not_found'));
            $this->redirect('/vendor/purchase-orders');
        }
        if ($po['status'] !== 'sent') {
            flash('error', t('vendor.po_not_open'));
            $this->redirect('/vendor/purchase-orders?id=' . $po['id']);
        }

        $new = (string) $this->post('status');
        if (!in_array($new, ['accepted', 'cancelled'], true)) {
            flash('error', t('validation.in', ['field' => 'Status']));
            $this->redirect('/vendor/purchase-orders?id=' . $po['id']);
        }

        Database::run('UPDATE purchase_orders SET status = ? WHERE id = ?', [$new, $po['id']]);

        $note = mb_substr((string) ($this->post('note', '') ?? ''), 0, 255);
        if ($new === 'accepted') {
            if ((int) ($po['created_by'] ?? 0) > 0) {
                Notify::user((int) $po['created_by'], 'po', 'Purchase order accepted', $po['po_no'] . ' was accepted by the vendor.', '/super/purchase-orders/' . $po['id']);
            }
            if ($po['society_id'] !== null) {
                Notify::societyAdmins(
                    (int) $po['society_id'], 'po', 'Purchase order accepted',
                    'PO ' . $po['po_no'] . ' was accepted by the vendor.',
                    $po['campaign_id'] !== null ? '/admin/campaigns/' . $po['campaign_id'] : null
                );
            }
            flash('success', t('vendor.po_accepted'));
        } else {
            flash('info', t('vendor.po_cancelled'));
        }

        Audit::log('po.' . $new, 'purchase_order', (int) $po['id'], ['vendor_id' => $uid, 'note' => $note ?: null]);
        $this->redirect('/vendor/purchase-orders?id=' . $po['id']);
    }
}
