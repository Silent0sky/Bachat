<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\Notify;

/**
 * Society-admin bulk campaign management + demand board + distribution.
 * Scope: ALWAYS Auth::societyId() — a society admin can never touch another society's rows.
 */
final class SocietyCampaignController extends Controller
{
    /* ============================== INDEX + CREATE ============================== */

    public function index(): void
    {
        $sid = $this->societyGuard();

        $campaigns = Database::fetchAll(
            "SELECT c.*,
                    COALESCE(a.aggregated, 0) AS agg_qty,
                    COALESCE(a.target, 0)     AS target_qty,
                    COALESCE(q.n, 0)          AS quotation_count,
                    COALESCE(i.n, 0)          AS interested_count
               FROM campaigns c
               LEFT JOIN (SELECT campaign_id, SUM(aggregated_qty) AS aggregated, SUM(target_qty) AS target
                            FROM campaign_items GROUP BY campaign_id) a ON a.campaign_id = c.id
               LEFT JOIN (SELECT campaign_id, COUNT(*) AS n FROM quotations GROUP BY campaign_id) q ON q.campaign_id = c.id
               LEFT JOIN (SELECT campaign_id, COUNT(*) AS n FROM campaign_interests
                           WHERE status <> 'cancelled' GROUP BY campaign_id) i ON i.campaign_id = c.id
              WHERE c.society_id = ?
              ORDER BY c.id DESC",
            [$sid]
        );

        $variantOptions = $this->variantOptions();

        $this->view('admin/campaigns', [
            'active'         => 'admin-campaigns',
            'pageTitle'      => t('proc.campaigns'),
            'campaigns'      => $campaigns,
            'variantOptions' => $variantOptions,
        ]);
    }

    public function create(): void
    {
        $sid = $this->societyGuard();

        $errors = $this->validate([
            'title'          => 'required|max:180',
            'type'           => 'in:grocery,product',
            'deadline'       => 'required|date',
            'delivery_cost'  => 'numeric|min:0',
            'tax_percent'    => 'numeric|min:0|max:99',
            'lead_time_days' => 'int|min:0',
        ]);
        if ($errors) {
            flash('error', implode(' ', $errors));
            $this->keepOld();
            $this->back();
        }

        // Item rows come as parallel arrays from the repeater form.
        $variantIds = (array) ($this->post('item_variant', []) ?? []);
        $targets    = (array) ($this->post('item_target', []) ?? []);
        $mins       = (array) ($this->post('item_min', []) ?? []);

        $rows = [];
        foreach ($variantIds as $i => $vid) {
            $vid = (int) $vid;
            if ($vid <= 0) {
                continue;
            }
            $target = round((float) ($targets[$i] ?? 0), 3);
            $min    = round((float) ($mins[$i] ?? 0), 3);
            if ($target <= 0) {
                continue;
            }
            $rows[] = ['variant_id' => $vid, 'target_qty' => $target, 'min_qty' => max(0, $min)];
        }
        if ($rows === []) {
            flash('error', t('proc.items_required'));
            $this->keepOld();
            $this->back();
        }

        // Verify every variant exists + is active (never trust posted ids).
        foreach ($rows as $k => $r) {
            $v = Database::fetch('SELECT id FROM product_variants WHERE id = ? AND is_active = 1', [$r['variant_id']]);
            if (!$v) {
                unset($rows[$k]);
            }
        }
        if ($rows === []) {
            flash('error', t('proc.items_required'));
            $this->keepOld();
            $this->back();
        }

        $deadline = date('Y-m-d H:i:s', strtotime((string) $this->post('deadline')));

        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            Database::run(
                "INSERT INTO campaigns (society_id, title, description, type, status, starts_at, deadline, extension_count,
                                        delivery_cost, tax_percent, lead_time_days, created_by, created_at)
                 VALUES (?,?,?,?, 'active', NOW(), ?, 0, ?, ?, ?, ?, NOW())",
                [
                    $sid,
                    (string) $this->post('title'),
                    (string) ($this->post('description', '') ?? '') ?: null,
                    $this->post('type', 'grocery') === 'product' ? 'product' : 'grocery',
                    $deadline,
                    round((float) ($this->post('delivery_cost', 0) ?? 0), 2),
                    round((float) ($this->post('tax_percent', 0) ?? 0), 2),
                    max(0, (int) ($this->post('lead_time_days', 7) ?? 7)),
                    (int) Auth::id(),
                ]
            );
            $cid = (int) $pdo->lastInsertId();
            foreach ($rows as $r) {
                Database::run(
                    'INSERT INTO campaign_items (campaign_id, variant_id, target_qty, min_qty, aggregated_qty) VALUES (?,?,?,0,0)',
                    [$cid, $r['variant_id'], $r['target_qty'], $r['min_qty']]
                );
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[Bachat SocCampaign] create: ' . $e->getMessage());
            flash('error', t('common.error'));
            $this->back();
        }

        Notify::societyResidents(
            $sid, 'campaign', 'New bulk campaign: ' . $this->post('title'),
            'Register your interest before ' . human_dt($deadline) . '.',
            '/campaigns/' . $cid
        );
        Audit::log('campaign.create', 'campaign', $cid, ['title' => $this->post('title'), 'items' => count($rows)]);

        flash('success', t('proc.created'));
        $this->redirect('/admin/campaigns/' . $cid);
    }

    /* ============================== DETAIL ============================== */

    public function detail(string $id): void
    {
        $sid  = $this->societyGuard();
        $camp = $this->scopedCampaign((int) $id, $sid);
        if (!$camp) {
            flash('error', t('common.not_found'));
            $this->redirect('/admin/campaigns');
        }

        $items = Database::fetchAll(
            'SELECT ci.*, v.pack_size, v.unit, v.sku, p.name_en, p.name_hi, p.name_mr, b.name AS brand_name
               FROM campaign_items ci
               JOIN product_variants v ON v.id = ci.variant_id
               JOIN products p         ON p.id = v.product_id
               LEFT JOIN brands b      ON b.id = v.brand_id
              WHERE ci.campaign_id = ? ORDER BY ci.id',
            [$camp['id']]
        );

        // Interests with resident name + flat label.
        $interests = Database::fetchAll(
            "SELECT i.*, u.name AS user_name, u.phone AS user_phone,
                    (SELECT CONCAT(f.block, '-', f.flat_number) FROM memberships m
                      JOIN flats f ON f.id = m.flat_id
                     WHERE m.user_id = i.user_id AND m.society_id = ? AND m.status = 'active'
                     ORDER BY m.id DESC LIMIT 1) AS flat
               FROM campaign_interests i
               JOIN users u ON u.id = i.user_id
              WHERE i.campaign_id = ?
              ORDER BY FIELD(i.status, 'interested', 'confirmed', 'cancelled'), i.id DESC",
            [$sid, $camp['id']]
        );
        $interestQty = [];
        if ($interests) {
            $iids = implode(',', array_map('intval', array_column($interests, 'id')));
            foreach (
                Database::fetchAll(
                    "SELECT ii.*, ci.variant_id FROM campaign_interest_items ii
                      JOIN campaign_items ci ON ci.id = ii.campaign_item_id
                     WHERE ii.interest_id IN ($iids)"
                ) as $r
            ) {
                $interestQty[(int) $r['interest_id']][] = $r;
            }
        }

        $quotations = Database::fetchAll(
            'SELECT q.*, u.name AS vendor_name FROM quotations q
              JOIN users u ON u.id = q.vendor_id
             WHERE q.campaign_id = ? ORDER BY (q.status = "selected") DESC, q.id DESC',
            [$camp['id']]
        );

        $this->view('admin/campaign_detail', [
            'active'      => 'admin-campaigns',
            'pageTitle'   => $camp['title'],
            'camp'        => $camp,
            'items'       => $items,
            'interests'   => $interests,
            'interestQty' => $interestQty,
            'quotations'  => $quotations,
        ]);
    }

    /* ============================== LIFECYCLE ACTIONS ============================== */

    public function extend(string $id): void
    {
        $sid  = $this->societyGuard();
        $camp = $this->scopedCampaign((int) $id, $sid);
        if (!$camp) {
            flash('error', t('common.not_found'));
            $this->redirect('/admin/campaigns');
        }
        if (!in_array($camp['status'], ['active', 'extended'], true)) {
            flash('error', t('proc.not_extendable'));
            $this->redirect('/admin/campaigns/' . $camp['id']);
        }

        $base = strtotime((string) ($camp['deadline'] ?? 'now'));
        if ($base === false || $base < time()) {
            $base = time();
        }
        $newDeadline = date('Y-m-d H:i:s', strtotime('+7 days', $base));

        Database::run(
            "UPDATE campaigns SET status = 'extended', deadline = ?, extension_count = extension_count + 1 WHERE id = ?",
            [$newDeadline, $camp['id']]
        );

        $this->notifyInterested((int) $camp['id'], 'Campaign extended', '"' . $camp['title'] . '" now closes on ' . human_dt($newDeadline) . '.');
        Audit::log('campaign.extend', 'campaign', (int) $camp['id'], ['deadline' => $newDeadline]);
        flash('success', t('proc.extended'));
        $this->redirect('/admin/campaigns/' . $camp['id']);
    }

    public function close(string $id): void
    {
        $sid  = $this->societyGuard();
        $camp = $this->scopedCampaign((int) $id, $sid);
        if (!$camp) {
            flash('error', t('common.not_found'));
            $this->redirect('/admin/campaigns');
        }
        if (!in_array($camp['status'], ['active', 'extended'], true)) {
            flash('error', t('proc.not_closable'));
            $this->redirect('/admin/campaigns/' . $camp['id']);
        }

        Database::run("UPDATE campaigns SET status = 'closed' WHERE id = ?", [$camp['id']]);

        $this->notifyInterested((int) $camp['id'], 'Bulk campaign closed — review final prices', '"' . $camp['title'] . '" closed. Final prices are being prepared; you will be able to confirm your bulk order soon.');
        Audit::log('campaign.close', 'campaign', (int) $camp['id']);
        flash('success', t('proc.closed'));
        $this->redirect('/admin/campaigns/' . $camp['id']);
    }

    public function cancel(string $id): void
    {
        $sid  = $this->societyGuard();
        $camp = $this->scopedCampaign((int) $id, $sid);
        if (!$camp) {
            flash('error', t('common.not_found'));
            $this->redirect('/admin/campaigns');
        }
        if ($camp['status'] === 'cancelled') {
            flash('info', t('proc.already_cancelled'));
            $this->redirect('/admin/campaigns/' . $camp['id']);
        }

        Database::run("UPDATE campaigns SET status = 'cancelled' WHERE id = ?", [$camp['id']]);

        $this->notifyInterested((int) $camp['id'], 'Bulk campaign cancelled', '"' . $camp['title'] . '" was cancelled. No payment is due.');
        Audit::log('campaign.cancel', 'campaign', (int) $camp['id']);
        flash('success', t('proc.cancelled'));
        $this->redirect('/admin/campaigns/' . $camp['id']);
    }

    /* ============================== QUOTATIONS + PRICES ============================== */

    public function selectQuotation(string $id): void
    {
        $sid  = $this->societyGuard();
        $camp = $this->scopedCampaign((int) $id, $sid);
        if (!$camp) {
            flash('error', t('common.not_found'));
            $this->redirect('/admin/campaigns');
        }

        $q = Database::fetch(
            'SELECT * FROM quotations WHERE id = ? AND campaign_id = ?',
            [(int) ($this->post('quotation_id', 0) ?? 0), $camp['id']]
        );
        if (!$q || $q['status'] !== 'submitted') {
            flash('error', t('proc.quotation_not_open'));
            $this->redirect('/admin/campaigns/' . $camp['id']);
        }

        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            Database::run("UPDATE quotations SET status = 'selected' WHERE id = ?", [$q['id']]);
            Database::run(
                "UPDATE quotations SET status = 'rejected' WHERE campaign_id = ? AND id <> ? AND status = 'submitted'",
                [$camp['id'], $q['id']]
            );
            // Quoted price becomes the reference price on every item.
            Database::run('UPDATE campaign_items SET quoted_price = ? WHERE campaign_id = ?', [(float) $q['unit_price'], $camp['id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[Bachat SocCampaign] selectQuotation: ' . $e->getMessage());
            flash('error', t('common.error'));
            $this->redirect('/admin/campaigns/' . $camp['id']);
        }

        Notify::user((int) $q['vendor_id'], 'quotation', 'Your quotation was selected', 'Your quotation for "' . $camp['title'] . '" was selected. A purchase order may follow.', '/vendor/quotations');
        $this->notifyInterested((int) $camp['id'], 'Quotation selected', 'A vendor quotation for "' . $camp['title'] . '" was selected. Final prices will be announced shortly.');
        Audit::log('campaign.quotation_select', 'quotation', (int) $q['id'], ['campaign_id' => (int) $camp['id']]);

        flash('success', t('proc.quotation_selected'));
        $this->redirect('/admin/campaigns/' . $camp['id']);
    }

    public function setPrices(string $id): void
    {
        $sid  = $this->societyGuard();
        $camp = $this->scopedCampaign((int) $id, $sid);
        if (!$camp) {
            flash('error', t('common.not_found'));
            $this->redirect('/admin/campaigns');
        }

        $items = Database::fetchAll('SELECT id FROM campaign_items WHERE campaign_id = ?', [$camp['id']]);
        $changed = 0;
        foreach ($items as $it) {
            $val = $this->post('confirmed_price_' . $it['id']);
            if ($val === null || $val === '') {
                continue; // leave blank = skip this item
            }
            if (!is_numeric($val) || (float) $val < 0) {
                flash('error', t('proc.price_invalid'));
                $this->redirect('/admin/campaigns/' . $camp['id']);
            }
            Database::run(
                'UPDATE campaign_items SET confirmed_price = ? WHERE id = ?',
                [round((float) $val, 2), (int) $it['id']]
            );
            $changed++;
        }
        if ($changed === 0) {
            flash('info', t('proc.no_price_change'));
            $this->redirect('/admin/campaigns/' . $camp['id']);
        }

        $this->notifyInterested((int) $camp['id'], 'Final prices ready — confirm your bulk order', 'Final prices for "' . $camp['title'] . '" are ready. Confirm your bulk order now.');
        Audit::log('campaign.set_prices', 'campaign', (int) $camp['id'], ['items' => $changed]);
        flash('success', t('proc.prices_saved'));
        $this->redirect('/admin/campaigns/' . $camp['id']);
    }

    /* ============================== PURCHASE ORDER ============================== */

    public function createPO(string $id): void
    {
        $sid  = $this->societyGuard();
        $camp = $this->scopedCampaign((int) $id, $sid);
        if (!$camp) {
            flash('error', t('common.not_found'));
            $this->redirect('/admin/campaigns');
        }

        $q = Database::fetch(
            "SELECT * FROM quotations WHERE campaign_id = ? AND status = 'selected' ORDER BY id DESC LIMIT 1",
            [$camp['id']]
        );
        if (!$q) {
            flash('error', t('proc.select_quotation_first'));
            $this->redirect('/admin/campaigns/' . $camp['id']);
        }

        $errors = $this->validate(['expected_at' => 'date']);
        if ($errors) {
            flash('error', implode(' ', $errors));
            $this->back();
        }
        $expected = (string) ($this->post('expected_at', '') ?? '');
        $expectedAt = $expected !== '' ? date('Y-m-d', strtotime($expected)) : null;

        // Total is recomputed server-side: confirmed_price (fallback quoted_price) × aggregated qty.
        $items = Database::fetchAll('SELECT * FROM campaign_items WHERE campaign_id = ?', [$camp['id']]);
        $lines = [];
        $total = 0.0;
        foreach ($items as $it) {
            $qty   = (float) $it['aggregated_qty'];
            $price = $it['confirmed_price'] !== null ? (float) $it['confirmed_price'] : ($it['quoted_price'] !== null ? (float) $it['quoted_price'] : null);
            if ($qty <= 0 || $price === null) {
                continue;
            }
            $lines[] = ['variant_id' => (int) $it['variant_id'], 'qty' => $qty, 'unit_price' => round($price, 2)];
            $total += round($price * $qty, 2);
        }
        if ($lines === []) {
            flash('error', t('proc.po_no_lines'));
            $this->redirect('/admin/campaigns/' . $camp['id']);
        }
        $total = round($total, 2);

        do {
            $poNo   = 'PO' . date('ymd') . strtoupper(bin2hex(random_bytes(2)));
            $exists = (int) Database::scalar('SELECT COUNT(*) FROM purchase_orders WHERE po_no = ?', [$poNo]);
        } while ($exists > 0);

        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            Database::run(
                "INSERT INTO purchase_orders (po_no, campaign_id, vendor_id, society_id, total, status, expected_at, created_by, created_at)
                 VALUES (?,?,?,?,?, 'sent', ?, ?, NOW())",
                [$poNo, (int) $camp['id'], (int) $q['vendor_id'], $sid, $total, $expectedAt, (int) Auth::id()]
            );
            $poId = (int) $pdo->lastInsertId();
            foreach ($lines as $l) {
                Database::run(
                    'INSERT INTO purchase_order_items (po_id, variant_id, qty, received_qty, unit_price) VALUES (?,?,?,0,?)',
                    [$poId, $l['variant_id'], $l['qty'], $l['unit_price']]
                );
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[Bachat SocCampaign] createPO: ' . $e->getMessage());
            flash('error', t('common.error'));
            $this->redirect('/admin/campaigns/' . $camp['id']);
        }

        Notify::user((int) $q['vendor_id'], 'po', 'New purchase order ' . $poNo, 'You received purchase order ' . $poNo . ' for "' . $camp['title'] . '".', '/vendor/purchase-orders?id=' . $poId);
        Audit::log('po.create', 'purchase_order', $poId, ['campaign_id' => (int) $camp['id'], 'total' => $total, 'vendor_id' => (int) $q['vendor_id']]);

        flash('success', t('proc.po_created', ['no' => $poNo]));
        $this->redirect('/admin/campaigns/' . $camp['id']);
    }

    /* ============================== REFUND ============================== */

    public function refund(string $id): void
    {
        $sid  = $this->societyGuard();
        $camp = $this->scopedCampaign((int) $id, $sid);
        if (!$camp) {
            flash('error', t('common.not_found'));
            $this->redirect('/admin/campaigns');
        }

        // Users whose interest reached "confirmed" (i.e. they hold a bulk order).
        $users = array_map('intval', array_column(
            Database::fetchAll("SELECT user_id FROM campaign_interests WHERE campaign_id = ? AND status = 'confirmed'", [$camp['id']]),
            'user_id'
        ));
        if ($users === []) {
            flash('info', t('proc.refund_none'));
            $this->redirect('/admin/campaigns/' . $camp['id']);
        }

        $variantIds = array_map('intval', array_column(
            Database::fetchAll('SELECT variant_id FROM campaign_items WHERE campaign_id = ?', [$camp['id']]),
            'variant_id'
        ));

        // Campaign orders of those users that contain this campaign's variants.
        $orders = [];
        if ($variantIds !== []) {
            $orders = Database::fetchAll(
                'SELECT o.id, o.total,
                        (SELECT p2.id FROM payments p2 WHERE p2.order_id = o.id ORDER BY p2.id DESC LIMIT 1) AS payment_id
                   FROM orders o
                   JOIN order_items oi ON oi.order_id = o.id
                  WHERE o.type = \'campaign\' AND o.society_id = ? AND o.status NOT IN (\'cancelled\',\'failed\')
                    AND o.user_id IN (' . implode(',', $users) . ')
                    AND oi.variant_id IN (' . implode(',', $variantIds) . ')
                  GROUP BY o.id, o.total',
                [$sid]
            );
        }
        $orders = array_values(array_filter($orders, static fn ($o) => $o['payment_id'] !== null));
        if ($orders === []) {
            flash('info', t('proc.refund_none'));
            $this->redirect('/admin/campaigns/' . $camp['id']);
        }

        $reason = mb_substr((string) ($this->post('reason', '') ?? ''), 0, 255) ?: ('Bulk campaign #' . $camp['id'] . ' refund');

        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            foreach ($orders as $o) {
                Database::run(
                    "INSERT INTO refunds (order_id, payment_id, amount, reason, status, processed_by, created_at)
                     VALUES (?,?,?,?,'requested',?,NOW())",
                    [(int) $o['id'], (int) $o['payment_id'], (float) $o['total'], $reason, (int) Auth::id()]
                );
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[Bachat SocCampaign] refund: ' . $e->getMessage());
            flash('error', t('common.error'));
            $this->redirect('/admin/campaigns/' . $camp['id']);
        }

        Notify::superAdmins('refund', 'Refund requests created', count($orders) . ' refund(s) requested for "' . $camp['title'] . '".', '/super/refunds');
        Audit::log('campaign.refund', 'campaign', (int) $camp['id'], ['orders' => count($orders)]);

        flash('success', t('proc.refund_done', ['n' => (string) count($orders)]));
        $this->redirect('/admin/campaigns/' . $camp['id']);
    }

    /* ============================== DEMAND BOARD ============================== */

    public function demand(): void
    {
        $sid = $this->societyGuard();

        $votes = Database::fetchAll(
            "SELECT dv.*, u.name AS creator_name, cat.name_en AS category_name,
                    (SELECT COUNT(*) FROM demand_vote_interests x WHERE x.vote_id = dv.id) AS vote_count
               FROM demand_votes dv
               JOIN users u ON u.id = dv.user_id
               LEFT JOIN categories cat ON cat.id = dv.category_id
              WHERE dv.society_id = ?
              ORDER BY FIELD(dv.status, 'open', 'validated', 'converted', 'rejected'), dv.created_at DESC
              LIMIT 100",
            [$sid]
        );

        $this->view('admin/demand', [
            'active'    => 'admin-demand',
            'pageTitle' => t('proc.demand_board'),
            'votes'     => $votes,
        ]);
    }

    public function validateDemand(string $id): void
    {
        $sid  = $this->societyGuard();
        $vote = Database::fetch('SELECT * FROM demand_votes WHERE id = ? AND society_id = ?', [(int) $id, $sid]);
        if (!$vote) {
            flash('error', t('common.not_found'));
            $this->redirect('/admin/demand');
        }
        if ($vote['status'] !== 'open') {
            flash('info', t('proc.demand_already_done'));
            $this->redirect('/admin/demand');
        }

        Database::run("UPDATE demand_votes SET status = 'validated' WHERE id = ?", [$vote['id']]);
        Notify::societyResidents($sid, 'demand', 'Demand validated', '"' . $vote['title'] . '" was validated by your society admin.', '/demand');
        Audit::log('demand.validate', 'demand_vote', (int) $vote['id']);

        flash('success', t('proc.validated'));
        $this->redirect('/admin/demand');
    }

    public function convertDemand(string $id): void
    {
        $sid  = $this->societyGuard();
        $vote = Database::fetch('SELECT * FROM demand_votes WHERE id = ? AND society_id = ?', [(int) $id, $sid]);
        if (!$vote) {
            flash('error', t('common.not_found'));
            $this->redirect('/admin/demand');
        }
        if (!in_array($vote['status'], ['open', 'validated'], true)) {
            flash('info', t('proc.demand_already_done'));
            $this->redirect('/admin/demand');
        }

        Database::run("UPDATE demand_votes SET status = 'converted' WHERE id = ?", [$vote['id']]);

        Notify::superAdmins('demand', 'Validated demand ready for listing', '"' . $vote['title'] . '" was converted by ' . ($vote['society_id'] ? 'society #' . $vote['society_id'] : 'a society') . '.', '/super/catalog');
        $voters = array_map('intval', array_column(
            Database::fetchAll('SELECT user_id FROM demand_vote_interests WHERE vote_id = ?', [$vote['id']]),
            'user_id'
        ));
        Notify::users($voters, 'demand', 'Your request was picked up', '"' . $vote['title'] . '" was converted for listing on the platform.', '/demand');
        Audit::log('demand.convert', 'demand_vote', (int) $vote['id']);

        flash('success', t('proc.converted'));
        $this->redirect('/admin/demand');
    }

    /* ============================== DISTRIBUTION ============================== */

    public function inventory(): void
    {
        $sid = $this->societyGuard();

        $items = Database::fetchAll(
            'SELECT ci.*, c.title AS campaign_title, c.status AS campaign_status,
                    p.name_en, p.name_hi, p.name_mr, b.name AS brand_name, v.pack_size, v.unit
               FROM campaign_items ci
               JOIN campaigns c         ON c.id = ci.campaign_id AND c.society_id = ?
               JOIN product_variants v  ON v.id = ci.variant_id
               JOIN products p          ON p.id = v.product_id
               LEFT JOIN brands b       ON b.id = v.brand_id
              WHERE ci.aggregated_qty > 0
              ORDER BY ci.id DESC',
            [$sid]
        );

        $this->view('admin/inventory', [
            'active'    => 'admin-inventory',
            'pageTitle' => t('proc.inventory'),
            'items'     => $items,
        ]);
    }

    public function distribute(): void
    {
        $sid = $this->societyGuard();

        $item = Database::fetch(
            'SELECT ci.*, c.society_id FROM campaign_items ci
              JOIN campaigns c ON c.id = ci.campaign_id
             WHERE ci.id = ?',
            [(int) ($this->post('campaign_item_id', 0) ?? 0)]
        );
        if (!$item || (int) $item['society_id'] !== $sid) {
            flash('error', t('common.not_found'));
            $this->redirect('/admin/inventory');
        }

        $qty = round((float) ($this->post('qty', 0) ?? 0), 3);
        if ($qty <= 0) {
            flash('error', t('validation.min_num', ['field' => 'Qty', 'value' => '0.001']));
            $this->redirect('/admin/inventory');
        }
        $remaining = (float) $item['aggregated_qty'] - (float) $item['distributed_qty'];
        if ($qty > $remaining + 0.0001) {
            flash('error', t('proc.dist_too_much'));
            $this->redirect('/admin/inventory');
        }

        $note = mb_substr((string) ($this->post('note', '') ?? ''), 0, 255) ?: null;

        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            Database::run('UPDATE campaign_items SET distributed_qty = distributed_qty + ? WHERE id = ?', [$qty, (int) $item['id']]);
            Database::run(
                "INSERT INTO stock_movements (variant_id, type, qty, ref_type, ref_id, note, created_by, created_at)
                 VALUES (?, 'dispatch', ?, 'campaign_item', ?, ?, ?, NOW())",
                [(int) $item['variant_id'], $qty, (int) $item['id'], $note, (int) Auth::id()]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[Bachat SocCampaign] distribute: ' . $e->getMessage());
            flash('error', t('common.error'));
            $this->redirect('/admin/inventory');
        }

        Audit::log('inventory.distribute', 'campaign_item', (int) $item['id'], ['qty' => $qty, 'note' => $note]);
        flash('success', t('proc.dist_done'));
        $this->redirect('/admin/inventory');
    }

    /* ============================== HELPERS ============================== */

    /** Society admins operate strictly inside their own society. */
    private function societyGuard(): int
    {
        $sid = Auth::societyId();
        if ($sid === null) {
            flash('error', t('common.unauthorized'));
            $this->redirect('/admin/dashboard');
        }
        return (int) $sid;
    }

    private function scopedCampaign(int $id, int $sid): ?array
    {
        return Database::fetch('SELECT * FROM campaigns WHERE id = ? AND society_id = ?', [$id, $sid]);
    }

    private function variantOptions(): array
    {
        return Database::fetchAll(
            'SELECT v.id, v.pack_size, v.unit, v.sku, p.name_en, p.name_hi, p.name_mr, b.name AS brand_name
               FROM product_variants v
               JOIN products p    ON p.id = v.product_id
               LEFT JOIN brands b ON b.id = v.brand_id
              WHERE v.is_active = 1 AND p.is_active = 1
              ORDER BY p.name_en, v.pack_size'
        );
    }

    /** Notify every resident whose interest in this campaign is still open. */
    private function notifyInterested(int $campaignId, string $title, string $body): void
    {
        $ids = array_map('intval', array_column(
            Database::fetchAll("SELECT user_id FROM campaign_interests WHERE campaign_id = ? AND status = 'interested'", [$campaignId]),
            'user_id'
        ));
        Notify::users($ids, 'campaign', $title, $body, '/campaigns/' . $campaignId);
    }
}
