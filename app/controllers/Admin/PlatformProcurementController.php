<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\Notify;

/**
 * Super-admin procurement console: platform-wide campaigns, quotations,
 * purchase orders (receive → inventory) and central stock adjustments.
 */
final class PlatformProcurementController extends Controller
{
    /* ============================== CAMPAIGNS (platform-wide) ============================== */

    public function campaigns(): void
    {
        [$where, $params] = $this->campaignFilter();

        $campaigns = Database::fetchAll(
            "SELECT c.*, s.name AS society_name,
                    COALESCE(a.aggregated, 0) AS agg_qty,
                    COALESCE(a.target, 0)     AS target_qty,
                    COALESCE(q.n, 0)          AS quotation_count,
                    COALESCE(i.n, 0)          AS interested_count
               FROM campaigns c
               LEFT JOIN societies s ON s.id = c.society_id
               LEFT JOIN (SELECT campaign_id, SUM(aggregated_qty) AS aggregated, SUM(target_qty) AS target
                            FROM campaign_items GROUP BY campaign_id) a ON a.campaign_id = c.id
               LEFT JOIN (SELECT campaign_id, COUNT(*) AS n FROM quotations GROUP BY campaign_id) q ON q.campaign_id = c.id
               LEFT JOIN (SELECT campaign_id, COUNT(*) AS n FROM campaign_interests
                           WHERE status <> 'cancelled' GROUP BY campaign_id) i ON i.campaign_id = c.id
              $where
              ORDER BY c.id DESC
              LIMIT 200",
            $params
        );

        $societies = Database::fetchAll('SELECT id, name FROM societies ORDER BY name');
        $statuses  = ['draft', 'active', 'extended', 'closed', 'failed', 'cancelled'];

        // Optional detail panel (?id=) — same data the society admin sees.
        $selCamp = null;
        $selItems = [];
        $selQuotes = [];
        $selId = (int) ($this->query('id', 0) ?? 0);
        if ($selId > 0) {
            $selCamp = Database::fetch(
                'SELECT c.*, s.name AS society_name FROM campaigns c
                  LEFT JOIN societies s ON s.id = c.society_id WHERE c.id = ?',
                [$selId]
            );
            if ($selCamp) {
                $selItems = Database::fetchAll(
                    'SELECT ci.*, v.pack_size, v.unit, v.sku, p.name_en, p.name_hi, p.name_mr, b.name AS brand_name
                       FROM campaign_items ci
                       JOIN product_variants v ON v.id = ci.variant_id
                       JOIN products p         ON p.id = v.product_id
                       LEFT JOIN brands b      ON b.id = v.brand_id
                      WHERE ci.campaign_id = ? ORDER BY ci.id',
                    [$selCamp['id']]
                );
                $selQuotes = Database::fetchAll(
                    'SELECT q.*, u.name AS vendor_name FROM quotations q
                      JOIN users u ON u.id = q.vendor_id
                     WHERE q.campaign_id = ? ORDER BY (q.status = "selected") DESC, q.id DESC',
                    [$selCamp['id']]
                );
            }
        }

        $this->view('super/procurement', [
            'active'    => 'super-campaigns',
            'pageTitle' => t('proc.platform_campaigns'),
            'tab'       => 'campaigns',
            'campaigns' => $campaigns,
            'societies' => $societies,
            'statuses'  => $statuses,
            'selCamp'   => $selCamp,
            'selItems'  => $selItems,
            'selQuotes' => $selQuotes,
        ]);
    }

    public function setPrices(string $id): void
    {
        $camp = Database::fetch('SELECT * FROM campaigns WHERE id = ?', [(int) $id]);
        if (!$camp) {
            flash('error', t('common.not_found'));
            $this->redirect('/super/campaigns');
        }

        $items = Database::fetchAll('SELECT id FROM campaign_items WHERE campaign_id = ?', [$camp['id']]);
        $changed = 0;
        foreach ($items as $it) {
            $val = $this->post('confirmed_price_' . $it['id']);
            if ($val === null || $val === '') {
                continue;
            }
            if (!is_numeric($val) || (float) $val < 0) {
                flash('error', t('proc.price_invalid'));
                $this->redirect('/super/campaigns?id=' . $camp['id']);
            }
            Database::run('UPDATE campaign_items SET confirmed_price = ? WHERE id = ?', [round((float) $val, 2), (int) $it['id']]);
            $changed++;
        }
        if ($changed === 0) {
            flash('info', t('proc.no_price_change'));
            $this->redirect('/super/campaigns?id=' . $camp['id']);
        }

        $this->notifyInterested((int) $camp['id'], 'Final prices ready — confirm your bulk order', 'Final prices for "' . $camp['title'] . '" are ready. Confirm your bulk order now.');
        Audit::log('campaign.set_prices', 'campaign', (int) $camp['id'], ['items' => $changed, 'by' => 'super_admin']);
        flash('success', t('proc.prices_saved'));
        $this->redirect('/super/campaigns?id=' . $camp['id']);
    }

    public function cancelCampaign(string $id): void
    {
        $camp = Database::fetch('SELECT * FROM campaigns WHERE id = ?', [(int) $id]);
        if (!$camp) {
            flash('error', t('common.not_found'));
            $this->redirect('/super/campaigns');
        }
        if (in_array($camp['status'], ['cancelled'], true)) {
            flash('info', t('proc.already_cancelled'));
            $this->redirect('/super/campaigns?id=' . $camp['id']);
        }

        Database::run("UPDATE campaigns SET status = 'cancelled' WHERE id = ?", [$camp['id']]);

        $this->notifyInterested((int) $camp['id'], 'Bulk campaign cancelled', '"' . $camp['title'] . '" was cancelled by the platform. No payment is due.');
        if ($camp['society_id'] !== null) {
            Notify::societyAdmins((int) $camp['society_id'], 'campaign', 'Bulk campaign cancelled', '"' . $camp['title'] . '" was cancelled platform-wide.', '/admin/campaigns/' . $camp['id']);
        }
        Audit::log('campaign.cancel', 'campaign', (int) $camp['id'], ['by' => 'super_admin']);

        flash('success', t('proc.cancelled'));
        $this->redirect('/super/campaigns?id=' . $camp['id']);
    }

    /* ============================== QUOTATIONS (platform-wide) ============================== */

    public function quotations(): void
    {
        $status = (string) ($this->query('status', '') ?? '');
        $where  = '';
        $params = [];
        if (in_array($status, ['submitted', 'selected', 'rejected'], true)) {
            $where = ' WHERE q.status = ?';
            $params[] = $status;
        }

        $quotations = Database::fetchAll(
            "SELECT q.*, c.title AS campaign_title, c.society_id AS campaign_society_id, s.name AS society_name, u.name AS vendor_name
               FROM quotations q
               JOIN campaigns c ON c.id = q.campaign_id
               JOIN users u     ON u.id = q.vendor_id
               LEFT JOIN societies s ON s.id = c.society_id
              $where
              ORDER BY (q.status = 'submitted') DESC, q.id DESC
              LIMIT 200",
            $params
        );

        $statuses = ['submitted', 'selected', 'rejected'];

        $this->view('super/procurement', [
            'active'     => 'super-quotations',
            'pageTitle'  => t('proc.quotations'),
            'tab'        => 'quotations',
            'quotations' => $quotations,
            'statuses'   => $statuses,
            'status'     => $status,
        ]);
    }

    public function selectQuotation(string $id): void
    {
        $q = Database::fetch(
            'SELECT q.*, c.title AS campaign_title FROM quotations q
              JOIN campaigns c ON c.id = q.campaign_id WHERE q.id = ?',
            [(int) $id]
        );
        if (!$q || $q['status'] !== 'submitted') {
            flash('error', t('proc.quotation_not_open'));
            $this->redirect('/super/quotations');
        }

        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            Database::run("UPDATE quotations SET status = 'selected' WHERE id = ?", [$q['id']]);
            Database::run(
                "UPDATE quotations SET status = 'rejected' WHERE campaign_id = ? AND id <> ? AND status = 'submitted'",
                [$q['campaign_id'], $q['id']]
            );
            Database::run('UPDATE campaign_items SET quoted_price = ? WHERE campaign_id = ?', [(float) $q['unit_price'], (int) $q['campaign_id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[Bachat Procurement] selectQuotation: ' . $e->getMessage());
            flash('error', t('common.error'));
            $this->redirect('/super/quotations');
        }

        Notify::user((int) $q['vendor_id'], 'quotation', 'Your quotation was selected', 'Your quotation for "' . $q['campaign_title'] . '" was selected. A purchase order may follow.', '/vendor/quotations');
        $this->notifyInterested((int) $q['campaign_id'], 'Quotation selected', 'A vendor quotation for "' . $q['campaign_title'] . '" was selected. Final prices will be announced shortly.');
        Audit::log('campaign.quotation_select', 'quotation', (int) $q['id'], ['campaign_id' => (int) $q['campaign_id'], 'by' => 'super_admin']);

        flash('success', t('proc.quotation_selected'));
        $this->redirect('/super/campaigns?id=' . $q['campaign_id']);
    }

    public function rejectQuotation(string $id): void
    {
        $q = Database::fetch('SELECT * FROM quotations WHERE id = ?', [(int) $id]);
        if (!$q || $q['status'] !== 'submitted') {
            flash('error', t('proc.quotation_not_open'));
            $this->redirect('/super/quotations');
        }

        Database::run("UPDATE quotations SET status = 'rejected' WHERE id = ?", [$q['id']]);
        Notify::user((int) $q['vendor_id'], 'quotation', 'Your quotation was not selected', 'Your quotation was not selected this time. Watch for new RFQs.', '/vendor/quotations');
        Audit::log('campaign.quotation_reject', 'quotation', (int) $q['id'], ['by' => 'super_admin']);

        flash('success', t('proc.quotation_rejected'));
        $this->redirect('/super/quotations');
    }

    /* ============================== PURCHASE ORDERS ============================== */

    public function purchaseOrders(): void
    {
        $pos = Database::fetchAll(
            'SELECT po.*, u.name AS vendor_name, s.name AS society_name, cu.name AS creator_name,
                    (SELECT COUNT(*) FROM purchase_order_items x WHERE x.po_id = po.id) AS item_count,
                    (SELECT COALESCE(SUM(x.qty),0)          FROM purchase_order_items x WHERE x.po_id = po.id) AS total_qty,
                    (SELECT COALESCE(SUM(x.received_qty),0) FROM purchase_order_items x WHERE x.po_id = po.id) AS received_qty
               FROM purchase_orders po
               JOIN users u ON u.id = po.vendor_id
               LEFT JOIN societies s ON s.id = po.society_id
               LEFT JOIN users cu ON cu.id = po.created_by
              ORDER BY po.id DESC LIMIT 200'
        );

        $this->view('super/pos', [
            'active'    => 'super-purchase-orders',
            'pageTitle' => t('proc.purchase_orders'),
            'pos'       => $pos,
        ]);
    }

    public function poDetail(string $id): void
    {
        $po = Database::fetch(
            'SELECT po.*, u.name AS vendor_name, s.name AS society_name, cu.name AS creator_name
               FROM purchase_orders po
               JOIN users u ON u.id = po.vendor_id
               LEFT JOIN societies s ON s.id = po.society_id
               LEFT JOIN users cu ON cu.id = po.created_by
              WHERE po.id = ?',
            [(int) $id]
        );
        if (!$po) {
            flash('error', t('common.not_found'));
            $this->redirect('/super/purchase-orders');
        }

        $items = Database::fetchAll(
            'SELECT poi.*, p.name_en, p.name_hi, p.name_mr, b.name AS brand_name, v.pack_size, v.unit
               FROM purchase_order_items poi
               JOIN product_variants v ON v.id = poi.variant_id
               JOIN products p         ON p.id = v.product_id
               LEFT JOIN brands b      ON b.id = v.brand_id
              WHERE poi.po_id = ? ORDER BY poi.id',
            [$po['id']]
        );

        $this->view('super/po_detail', [
            'active'    => 'super-purchase-orders',
            'pageTitle' => t('proc.po_detail', ['no' => $po['po_no']]),
            'po'        => $po,
            'items'     => $items,
        ]);
    }

    public function poStatus(string $id): void
    {
        $po = Database::fetch('SELECT * FROM purchase_orders WHERE id = ?', [(int) $id]);
        if (!$po) {
            flash('error', t('common.not_found'));
            $this->redirect('/super/purchase-orders');
        }

        $new = (string) $this->post('status');
        $allowed = ['draft' => ['sent'], 'sent' => ['cancelled']];
        if (!isset($allowed[$po['status']]) || !in_array($new, $allowed[$po['status']], true)) {
            flash('error', t('proc.po_status_invalid'));
            $this->redirect('/super/purchase-orders/' . $po['id']);
        }

        Database::run('UPDATE purchase_orders SET status = ? WHERE id = ?', [$new, $po['id']]);

        if ($new === 'sent') {
            Notify::user((int) $po['vendor_id'], 'po', 'New purchase order ' . $po['po_no'], 'You received purchase order ' . $po['po_no'] . '.', '/vendor/purchase-orders?id=' . $po['id']);
            flash('success', t('proc.po_sent'));
        } else {
            Notify::user((int) $po['vendor_id'], 'po', 'Purchase order cancelled', 'PO ' . $po['po_no'] . ' was cancelled by the platform.', '/vendor/purchase-orders?id=' . $po['id']);
            flash('info', t('proc.po_cancelled_admin'));
        }
        Audit::log('po.' . $new, 'purchase_order', (int) $po['id'], ['note' => $this->post('note') ?: null]);

        $this->redirect('/super/purchase-orders/' . $po['id']);
    }

    public function poReceive(string $id): void
    {
        $po = Database::fetch('SELECT * FROM purchase_orders WHERE id = ?', [(int) $id]);
        if (!$po) {
            flash('error', t('common.not_found'));
            $this->redirect('/super/purchase-orders');
        }
        if (!in_array($po['status'], ['sent', 'accepted', 'partially_received'], true)) {
            flash('error', t('proc.po_not_receivable'));
            $this->redirect('/super/purchase-orders/' . $po['id']);
        }

        $items = Database::fetchAll('SELECT * FROM purchase_order_items WHERE po_id = ? ORDER BY id', [$po['id']]);
        if ($items === []) {
            flash('error', t('proc.po_no_lines'));
            $this->redirect('/super/purchase-orders/' . $po['id']);
        }

        // Validate ALL received quantities before touching the DB.
        $recv = [];
        foreach ($items as $it) {
            $val       = (float) ($this->post('received_' . $it['id'], 0) ?? 0);
            $remaining = (float) $it['qty'] - (float) $it['received_qty'];
            if ($val < 0 || $val > $remaining + 0.0001) {
                flash('error', t('proc.recv_too_much'));
                $this->redirect('/super/purchase-orders/' . $po['id']);
            }
            $recv[(int) $it['id']] = ['variant_id' => (int) $it['variant_id'], 'qty' => round($val, 3)];
        }

        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();

            foreach ($items as $it) {
                $q = $recv[(int) $it['id']]['qty'];
                if ($q <= 0) {
                    continue;
                }
                Database::run('UPDATE purchase_order_items SET received_qty = received_qty + ? WHERE id = ?', [$q, (int) $it['id']]);

                // Inventory upsert (central stock).
                $inv = Database::fetch('SELECT id FROM inventory WHERE variant_id = ? FOR UPDATE', [$it['variant_id']]);
                if ($inv) {
                    Database::run('UPDATE inventory SET received = received + ? WHERE variant_id = ?', [$q, $it['variant_id']]);
                } else {
                    Database::run('INSERT INTO inventory (variant_id, received) VALUES (?,?)', [$it['variant_id'], $q]);
                }

                // Sellable stock + movement ledger.
                Database::run('UPDATE product_variants SET stock_qty = stock_qty + ? WHERE id = ?', [$q, $it['variant_id']]);
                Database::run(
                    "INSERT INTO stock_movements (variant_id, type, qty, ref_type, ref_id, note, created_by, created_at)
                     VALUES (?, 'in', ?, 'po', ?, ?, ?, NOW())",
                    [$it['variant_id'], $q, (int) $po['id'], 'PO ' . $po['po_no'] . ' receipt', (int) Auth::id()]
                );
            }

            // All lines fully received?
            $sum = Database::fetch(
                'SELECT COALESCE(SUM(qty),0) AS q, COALESCE(SUM(received_qty),0) AS r FROM purchase_order_items WHERE po_id = ?',
                [$po['id']]
            );
            $newStatus = (float) $sum['r'] >= (float) $sum['q'] - 0.0001 ? 'received' : 'partially_received';
            Database::run('UPDATE purchase_orders SET status = ? WHERE id = ?', [$newStatus, $po['id']]);

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[Bachat Procurement] poReceive: ' . $e->getMessage());
            flash('error', t('common.error'));
            $this->redirect('/super/purchase-orders/' . $po['id']);
        }

        Notify::user((int) $po['vendor_id'], 'po', 'Goods received for PO ' . $po['po_no'], 'Receipt recorded — status: ' . $newStatus . '.', '/vendor/purchase-orders?id=' . $po['id']);
        if ((int) ($po['created_by'] ?? 0) > 0) {
            Notify::user((int) $po['created_by'], 'po', 'Goods received for PO ' . $po['po_no'], 'Receipt recorded — status: ' . $newStatus . '.', '/super/purchase-orders/' . $po['id']);
        }
        if ($po['society_id'] !== null) {
            Notify::societyAdmins((int) $po['society_id'], 'po', 'Goods received for PO ' . $po['po_no'], 'Receipt recorded — status: ' . $newStatus . '.', $po['campaign_id'] !== null ? '/admin/campaigns/' . $po['campaign_id'] : null);
        }
        Audit::log('po.receive', 'purchase_order', (int) $po['id'], ['status' => $newStatus]);

        flash('success', t('proc.po_received'));
        $this->redirect('/super/purchase-orders/' . $po['id']);
    }

    /* ============================== CENTRAL INVENTORY ============================== */

    public function inventory(): void
    {
        $low = $this->query('low') === '1';

        $rows = Database::fetchAll(
            'SELECT v.id, v.pack_size, v.unit, v.sku, v.stock_qty, v.is_active,
                    p.name_en, p.name_hi, p.name_mr, b.name AS brand_name,
                    COALESCE(i.received, 0)   AS received,
                    COALESCE(i.reserved, 0)   AS reserved,
                    COALESCE(i.dispatched, 0) AS dispatched,
                    COALESCE(i.returned, 0)   AS returned,
                    COALESCE(i.damaged, 0)    AS damaged,
                    (COALESCE(i.received,0) - COALESCE(i.reserved,0) - COALESCE(i.dispatched,0)) AS available
               FROM product_variants v
               JOIN products p    ON p.id = v.product_id
               LEFT JOIN brands b ON b.id = v.brand_id
               LEFT JOIN inventory i ON i.variant_id = v.id
              ' . ($low ? 'HAVING available <= 10' : '') . '
              ORDER BY p.name_en, v.pack_size
              LIMIT 400'
        );

        $variants = Database::fetchAll(
            'SELECT v.id, v.pack_size, v.unit, p.name_en, p.name_hi, p.name_mr, b.name AS brand_name
               FROM product_variants v
               JOIN products p    ON p.id = v.product_id
               LEFT JOIN brands b ON b.id = v.brand_id
              WHERE v.is_active = 1
              ORDER BY p.name_en, v.pack_size'
        );
        $types = ['in', 'out', 'damage', 'adjust'];

        $this->view('super/inventory', [
            'active'    => 'super-inventory',
            'pageTitle' => t('proc.inventory_platform'),
            'rows'      => $rows,
            'variants'  => $variants,
            'types'     => $types,
            'low'       => $low,
        ]);
    }

    public function inventoryAdjust(): void
    {
        $variantId = (int) ($this->post('variant_id', 0) ?? 0);
        $variant   = Database::fetch('SELECT * FROM product_variants WHERE id = ?', [$variantId]);
        if (!$variant) {
            flash('error', t('common.not_found'));
            $this->redirect('/super/inventory');
        }

        $type = (string) $this->post('type');
        if (!in_array($type, ['in', 'out', 'damage', 'adjust'], true)) {
            flash('error', t('validation.in', ['field' => 'Type']));
            $this->redirect('/super/inventory');
        }

        $qty = round((float) ($this->post('qty', 0) ?? 0), 3);
        if ($type === 'adjust') {
            if ($qty === 0.0) {
                flash('error', t('proc.adj_qty_invalid'));
                $this->redirect('/super/inventory');
            }
        } elseif ($qty <= 0) {
            flash('error', t('validation.min_num', ['field' => 'Qty', 'value' => '0.001']));
            $this->redirect('/super/inventory');
        }

        $stock = (float) $variant['stock_qty'];
        if (in_array($type, ['out', 'damage'], true) && $qty > $stock + 0.0001) {
            flash('error', t('proc.adj_insufficient'));
            $this->redirect('/super/inventory');
        }

        $note = mb_substr((string) ($this->post('note', '') ?? ''), 0, 255) ?: null;

        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();

            $inv = Database::fetch('SELECT id FROM inventory WHERE variant_id = ? FOR UPDATE', [$variantId]);
            if (!$inv) {
                Database::run(
                    'INSERT INTO inventory (variant_id, received, reserved, dispatched, returned, damaged) VALUES (?,0,0,0,0,0)',
                    [$variantId]
                );
            }

            switch ($type) {
                case 'in':
                    Database::run('UPDATE inventory SET received = received + ? WHERE variant_id = ?', [$qty, $variantId]);
                    Database::run('UPDATE product_variants SET stock_qty = stock_qty + ? WHERE id = ?', [$qty, $variantId]);
                    break;
                case 'out':
                    Database::run('UPDATE inventory SET dispatched = dispatched + ? WHERE variant_id = ?', [$qty, $variantId]);
                    Database::run('UPDATE product_variants SET stock_qty = stock_qty - ? WHERE id = ?', [$qty, $variantId]);
                    break;
                case 'damage':
                    Database::run('UPDATE inventory SET damaged = damaged + ? WHERE variant_id = ?', [$qty, $variantId]);
                    Database::run('UPDATE product_variants SET stock_qty = stock_qty - ? WHERE id = ?', [$qty, $variantId]);
                    break;
                case 'adjust':
                    // Signed correction of the sellable stock (movement records the signed qty).
                    Database::run('UPDATE product_variants SET stock_qty = stock_qty + ? WHERE id = ?', [$qty, $variantId]);
                    break;
            }

            Database::run(
                "INSERT INTO stock_movements (variant_id, type, qty, ref_type, ref_id, note, created_by, created_at)
                 VALUES (?, ?, ?, 'manual', NULL, ?, ?, NOW())",
                [$variantId, $type, $qty, $note, (int) Auth::id()]
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[Bachat Procurement] inventoryAdjust: ' . $e->getMessage());
            flash('error', t('common.error'));
            $this->redirect('/super/inventory');
        }

        Audit::log('inventory.adjust', 'variant', $variantId, ['type' => $type, 'qty' => $qty, 'note' => $note]);
        flash('success', t('proc.adjust_done'));
        $this->redirect('/super/inventory');
    }

    /* ============================== HELPERS ============================== */

    /** Build the campaign filter (society / status) from GET params. @return array{0:string,1:array} */
    private function campaignFilter(): array
    {
        $where  = ' WHERE 1=1';
        $params = [];

        $soc = (int) ($this->query('society', 0) ?? 0);
        if ($soc > 0) {
            $where .= ' AND c.society_id = ?';
            $params[] = $soc;
        }
        $status = (string) ($this->query('status', '') ?? '');
        if (in_array($status, ['draft', 'active', 'extended', 'closed', 'failed', 'cancelled'], true)) {
            $where .= ' AND c.status = ?';
            $params[] = $status;
        }
        return [$where, $params];
    }

    private function notifyInterested(int $campaignId, string $title, string $body): void
    {
        $ids = array_map('intval', array_column(
            Database::fetchAll("SELECT user_id FROM campaign_interests WHERE campaign_id = ? AND status = 'interested'", [$campaignId]),
            'user_id'
        ));
        Notify::users($ids, 'campaign', $title, $body, '/campaigns/' . $campaignId);
    }
}
