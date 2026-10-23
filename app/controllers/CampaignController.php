<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\Notify;

/**
 * Resident-side bulk campaigns: browse, register interest, confirm the bulk
 * order once final prices are announced, cancel interest.
 * Scope: resident of an ACTIVE society membership (routes enforce role+society).
 */
final class CampaignController extends Controller
{
    /* ============================== INDEX ============================== */

    public function index(): void
    {
        $this->requireMembership();
        $uid = (int) Auth::id();
        $sid = (int) (Auth::societyId() ?? 0);

        $campaigns = Database::fetchAll(
            "SELECT c.*,
                    COALESCE(a.aggregated, 0) AS agg_qty,
                    COALESCE(a.target, 0)     AS target_qty,
                    COALESCE(i.n, 0)          AS interested_count
               FROM campaigns c
               LEFT JOIN (SELECT campaign_id, SUM(aggregated_qty) AS aggregated, SUM(target_qty) AS target
                            FROM campaign_items GROUP BY campaign_id) a ON a.campaign_id = c.id
               LEFT JOIN (SELECT campaign_id, COUNT(*) AS n FROM campaign_interests
                           WHERE status <> 'cancelled' GROUP BY campaign_id) i ON i.campaign_id = c.id
              WHERE c.status IN ('active','extended')
                AND (c.society_id = ? OR c.society_id IS NULL)
              ORDER BY (c.deadline IS NULL) ASC, c.deadline ASC, c.id DESC",
            [$sid]
        );
        foreach ($campaigns as &$c) {
            $c['countdown'] = self::countdown($c['deadline']);
        }
        unset($c);

        $myInterest = [];
        foreach (
            Database::fetchAll(
                "SELECT campaign_id, status FROM campaign_interests
                  WHERE user_id = ? AND status <> 'cancelled'",
                [$uid]
            ) as $r
        ) {
            $myInterest[(int) $r['campaign_id']] = $r['status'];
        }

        $myOrders = Database::fetchAll(
            "SELECT id, order_no, total, status, created_at FROM orders
              WHERE user_id = ? AND type = 'campaign' ORDER BY id DESC LIMIT 3",
            [$uid]
        );

        $this->view('campaigns/index', [
            'active'     => 'campaigns',
            'pageTitle'  => t('campaigns.title'),
            'campaigns'  => $campaigns,
            'myInterest' => $myInterest,
            'myOrders'   => $myOrders,
        ]);
    }

    /* ============================== SHOW ============================== */

    public function show(string $id): void
    {
        $this->requireMembership();
        $uid = (int) Auth::id();
        $sid = (int) (Auth::societyId() ?? 0);

        $camp = Database::fetch('SELECT * FROM campaigns WHERE id = ?', [(int) $id]);
        if (!$camp || ($camp['society_id'] !== null && (int) $camp['society_id'] !== $sid)) {
            flash('error', t('common.not_found'));
            $this->redirect('/campaigns');
        }

        $items = $this->itemsWithProducts((int) $camp['id']);

        $interestedCount = (int) Database::scalar(
            "SELECT COUNT(*) FROM campaign_interests WHERE campaign_id = ? AND status <> 'cancelled'",
            [$camp['id']]
        );

        $my = Database::fetch(
            'SELECT * FROM campaign_interests WHERE campaign_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1',
            [$camp['id'], $uid]
        );
        $myItems = [];
        if ($my) {
            foreach (
                Database::fetchAll(
                    'SELECT ii.*, ci.quoted_price, ci.confirmed_price
                       FROM campaign_interest_items ii
                       JOIN campaign_items ci ON ci.id = ii.campaign_item_id
                      WHERE ii.interest_id = ?',
                    [$my['id']]
                ) as $r
            ) {
                $myItems[(int) $r['campaign_item_id']] = $r;
            }
        }

        // Confirmation panel: closed + every item has a confirmed price + I am still "interested".
        $allPriced = $items !== [] && !in_array(null, array_map(static fn ($i) => $i['confirmed_price'], $items), true);
        $canConfirm = $camp['status'] === 'closed' && $allPriced && $my !== null && $my['status'] === 'interested';

        $this->view('campaigns/show', [
            'active'          => 'campaigns',
            'pageTitle'       => $camp['title'],
            'camp'            => $camp,
            'items'           => $items,
            'interestedCount' => $interestedCount,
            'my'              => $my,
            'myItems'         => $myItems,
            'canConfirm'      => $canConfirm,
            'countdown'       => self::countdown($camp['deadline']),
        ]);
    }

    /* ============================== INTEREST ============================== */

    public function interest(string $id): void
    {
        $this->requireMembership();
        $uid = (int) Auth::id();
        $sid = (int) (Auth::societyId() ?? 0);

        $camp = Database::fetch('SELECT * FROM campaigns WHERE id = ?', [(int) $id]);
        if (!$camp || ($camp['society_id'] !== null && (int) $camp['society_id'] !== $sid)) {
            flash('error', t('common.not_found'));
            $this->redirect('/campaigns');
        }
        if (!in_array($camp['status'], ['active', 'extended'], true)) {
            flash('error', t('campaigns.not_live'));
            $this->redirect('/campaigns/' . $camp['id']);
        }

        $existing = Database::fetch(
            'SELECT * FROM campaign_interests WHERE campaign_id = ? AND user_id = ? LIMIT 1',
            [$camp['id'], $uid]
        );
        if ($existing && $existing['status'] !== 'cancelled') {
            flash('info', t('campaigns.already_interested'));
            $this->redirect('/campaigns/' . $camp['id']);
        }

        $items = Database::fetchAll(
            'SELECT ci.*, v.selling_price FROM campaign_items ci
              JOIN product_variants v ON v.id = ci.variant_id
             WHERE ci.campaign_id = ? ORDER BY ci.id',
            [$camp['id']]
        );
        if ($items === []) {
            flash('error', t('common.error'));
            $this->redirect('/campaigns/' . $camp['id']);
        }

        // Quantities come from the client — validate, never trust.
        $rows = [];
        foreach ($items as $it) {
            $qty = (float) ($this->post('qty_' . $it['id'], 0) ?? 0);
            if ($qty < 1 || $qty > 100000) {
                flash('error', t('campaigns.qty_invalid'));
                $this->redirect('/campaigns/' . $camp['id']);
            }
            $rows[] = ['item' => $it, 'qty' => round($qty, 3)];
        }

        $note = mb_substr((string) ($this->post('note', '') ?? ''), 0, 255);

        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();

            if ($existing) { // re-activating a previously cancelled interest (unique key campaign+user)
                Database::run(
                    "UPDATE campaign_interests SET status = 'interested', note = ?, created_at = NOW() WHERE id = ?",
                    [$note !== '' ? $note : null, $existing['id']]
                );
                Database::run('DELETE FROM campaign_interest_items WHERE interest_id = ?', [$existing['id']]);
                $interestId = (int) $existing['id'];
            } else {
                Database::run(
                    "INSERT INTO campaign_interests (campaign_id, user_id, status, note, created_at)
                     VALUES (?,?,'interested',?,NOW())",
                    [$camp['id'], $uid, $note !== '' ? $note : null]
                );
                $interestId = (int) $pdo->lastInsertId();
            }

            foreach ($rows as $r) {
                $eff = $r['item']['quoted_price'] !== null
                    ? (float) $r['item']['quoted_price']
                    : (float) $r['item']['selling_price'];
                Database::run(
                    'INSERT INTO campaign_interest_items (interest_id, campaign_item_id, qty, est_price) VALUES (?,?,?,?)',
                    [$interestId, (int) $r['item']['id'], $r['qty'], round($eff, 2)]
                );
                Database::run(
                    'UPDATE campaign_items SET aggregated_qty = aggregated_qty + ? WHERE id = ?',
                    [$r['qty'], (int) $r['item']['id']]
                );
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[Bachat Campaign] interest: ' . $e->getMessage());
            flash('error', t('common.error'));
            $this->redirect('/campaigns/' . $camp['id']);
        }

        $u      = Auth::user() ?? [];
        $body   = ($u['name'] ?? 'A resident') . ' registered interest in "' . $camp['title'] . '"';
        if ($camp['society_id'] !== null) {
            Notify::societyAdmins((int) $camp['society_id'], 'campaign', 'New bulk interest', $body, '/admin/campaigns/' . $camp['id']);
        }
        Notify::superAdmins('campaign', 'New bulk interest', $body, '/super/campaigns?id=' . $camp['id']);
        Audit::log('campaign.interest', 'campaign', (int) $camp['id'], ['user_id' => $uid, 'items' => count($rows)]);

        flash('success', t('campaigns.interest_done'));
        $this->redirect('/campaigns/' . $camp['id']);
    }

    /* ============================== CONFIRM ============================== */

    public function confirm(string $id): void
    {
        $this->requireMembership();
        $uid = (int) Auth::id();
        $sid = (int) (Auth::societyId() ?? 0);

        $camp = Database::fetch('SELECT * FROM campaigns WHERE id = ?', [(int) $id]);
        if (!$camp || ($camp['society_id'] !== null && (int) $camp['society_id'] !== $sid)) {
            flash('error', t('common.not_found'));
            $this->redirect('/campaigns');
        }
        if ($camp['status'] !== 'closed') {
            flash('error', t('campaigns.confirm_error'));
            $this->redirect('/campaigns/' . $camp['id']);
        }

        $my = Database::fetch(
            "SELECT * FROM campaign_interests WHERE campaign_id = ? AND user_id = ? AND status = 'interested' LIMIT 1",
            [$camp['id'], $uid]
        );
        if (!$my) {
            flash('error', t('campaigns.confirm_error'));
            $this->redirect('/campaigns/' . $camp['id']);
        }

        $lines = Database::fetchAll(
            "SELECT ii.qty, ci.variant_id, ci.confirmed_price,
                    v.pack_size, v.unit, p.name_en, p.name_hi, p.name_mr, b.name AS brand_name
               FROM campaign_interest_items ii
               JOIN campaign_items ci  ON ci.id = ii.campaign_item_id
               JOIN product_variants v ON v.id  = ci.variant_id
               JOIN products p         ON p.id  = v.product_id
               LEFT JOIN brands b      ON b.id  = v.brand_id
              WHERE ii.interest_id = ?",
            [$my['id']]
        );
        if ($lines === [] || in_array(null, array_map(static fn ($l) => $l['confirmed_price'], $lines), true)) {
            flash('error', t('campaigns.confirm_error'));
            $this->redirect('/campaigns/' . $camp['id']);
        }
        if (!$this->post('confirm_check')) { // explicit consent checkbox is mandatory
            flash('error', t('checkout.must_confirm'));
            $this->redirect('/campaigns/' . $camp['id']);
        }

        // Recompute ALL money server-side from DB prices — never trust the client.
        $subtotal = 0.0;
        foreach ($lines as $l) {
            $subtotal += round((float) $l['confirmed_price'] * (float) $l['qty'], 2);
        }
        $subtotal = round($subtotal, 2);
        $delivery = round((float) $camp['delivery_cost'], 2);
        $tax      = round($subtotal * (float) $camp['tax_percent'] / 100, 2);
        $total    = round($subtotal + $delivery + $tax, 2);

        $method = in_array((string) ($this->post('payment_method', 'cod') ?? ''), ['cod', 'upi'], true)
            ? (string) $this->post('payment_method')
            : 'cod';

        $society = Database::fetch(
            'SELECT id, name, commission_type, commission_rate FROM societies WHERE id = ? LIMIT 1',
            [$sid]
        );
        $address = trim((string) ($this->post('address_text', '') ?? ''));
        if ($address === '') {
            $address = 'Society bulk distribution — ' . ($society['name'] ?? 'Society');
        }
        $u   = Auth::user() ?? [];
        $ven = Database::fetch(
            "SELECT vendor_id FROM quotations WHERE campaign_id = ? AND status = 'selected' ORDER BY id DESC LIMIT 1",
            [$camp['id']]
        );
        $vendorId = $ven ? (int) $ven['vendor_id'] : null;

        // Loop until the order number is unique (2 hex bytes = 65k/day, collision is unlikely).
        do {
            $orderNo = 'BC' . date('ymd') . strtoupper(bin2hex(random_bytes(2)));
            $exists  = (int) Database::scalar('SELECT COUNT(*) FROM orders WHERE order_no = ?', [$orderNo]);
        } while ($exists > 0);

        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();

            Database::run(
                "INSERT INTO orders (order_no, user_id, society_id, type, status, subtotal, discount, delivery_fee, tax, total,
                                     address_text, contact_name, contact_phone, fulfilment, placed_at, created_at, updated_at)
                 VALUES (?,?,?,'campaign','pending',?,0,?,?,?,?,?,?,'pickup',NOW(),NOW(),NOW())",
                [
                    $orderNo, $uid, $sid, $subtotal, $delivery, $tax, $total,
                    mb_substr($address, 0, 400), mb_substr((string) ($u['name'] ?? ''), 0, 120),
                    mb_substr((string) ($u['phone'] ?? ''), 0, 20),
                ]
            );
            $orderId = (int) $pdo->lastInsertId();

            foreach ($lines as $l) {
                $price = round((float) $l['confirmed_price'], 2);
                Database::run(
                    'INSERT INTO order_items (order_id, variant_id, product_name, brand_name, pack_size, unit, qty, unit_price, line_total)
                     VALUES (?,?,?,?,?,?,?,?,?)',
                    [
                        $orderId, (int) $l['variant_id'], product_name($l),
                        $l['brand_name'] ?: null, (float) $l['pack_size'], (string) $l['unit'],
                        (float) $l['qty'], $price, round((float) $l['qty'] * $price, 2),
                    ]
                );
            }

            Database::run(
                "INSERT INTO order_events (order_id, status, note, created_by, created_at)
                 VALUES (?,'pending',?,?,NOW())",
                [$orderId, 'Bulk campaign order (campaign #' . $camp['id'] . ') confirmed by resident', $uid]
            );

            Database::run(
                "INSERT INTO payments (order_id, society_id, user_id, amount, method, status, created_at)
                 VALUES (?,?,?,?,?,'pending',NOW())",
                [$orderId, $sid, $uid, $total, $method]
            );

            Database::run("UPDATE campaign_interests SET status = 'confirmed' WHERE id = ?", [$my['id']]);
            Database::run(
                'UPDATE campaign_interest_items ii
                   JOIN campaign_items ci ON ci.id = ii.campaign_item_id
                    SET ii.confirmed_price = ci.confirmed_price
                  WHERE ii.interest_id = ?',
                [$my['id']]
            );

            if ($society && (float) $society['commission_rate'] > 0) {
                $type   = $society['commission_type'] === 'fixed' ? 'fixed' : 'percent';
                $amount = $type === 'percent'
                    ? round($subtotal * (float) $society['commission_rate'] / 100, 2)
                    : round((float) $society['commission_rate'], 2);
                Database::run(
                    "INSERT INTO commissions (order_id, society_id, vendor_id, type, rate, amount, status)
                     VALUES (?,?,?,?,?,?,'due')",
                    [$orderId, $sid, $vendorId, $type, (float) $society['commission_rate'], $amount]
                );
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[Bachat Campaign] confirm: ' . $e->getMessage());
            flash('error', t('common.error'));
            $this->redirect('/campaigns/' . $camp['id']);
        }

        if ($camp['society_id'] !== null) {
            Notify::societyAdmins(
                (int) $camp['society_id'], 'order', 'Bulk order confirmed',
                ($u['name'] ?? 'A resident') . ' confirmed bulk order ' . $orderNo . ' for "' . $camp['title'] . '"',
                '/admin/orders'
            );
        }
        Audit::log('campaign.confirm', 'campaign', (int) $camp['id'], ['order_no' => $orderNo, 'total' => $total]);

        flash('success', t('campaigns.confirm_done'));
        $this->redirect('/orders');
    }

    /* ============================== CANCEL INTEREST ============================== */

    public function cancelInterest(string $id): void
    {
        $this->requireMembership();
        $uid = (int) Auth::id();
        $sid = (int) (Auth::societyId() ?? 0);

        $camp = Database::fetch('SELECT * FROM campaigns WHERE id = ?', [(int) $id]);
        if (!$camp || ($camp['society_id'] !== null && (int) $camp['society_id'] !== $sid)) {
            flash('error', t('common.not_found'));
            $this->redirect('/campaigns');
        }

        $my = Database::fetch(
            "SELECT * FROM campaign_interests WHERE campaign_id = ? AND user_id = ? AND status = 'interested' LIMIT 1",
            [$camp['id'], $uid]
        );
        if (!$my) {
            flash('error', t('common.error'));
            $this->redirect('/campaigns/' . $camp['id']);
        }

        $myItems = Database::fetchAll(
            'SELECT campaign_item_id, qty FROM campaign_interest_items WHERE interest_id = ?',
            [$my['id']]
        );

        $pdo = Database::pdo();
        try {
            $pdo->beginTransaction();
            Database::run("UPDATE campaign_interests SET status = 'cancelled' WHERE id = ?", [$my['id']]);
            foreach ($myItems as $mi) {
                Database::run(
                    'UPDATE campaign_items SET aggregated_qty = GREATEST(aggregated_qty - ?, 0) WHERE id = ?',
                    [(float) $mi['qty'], (int) $mi['campaign_item_id']]
                );
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[Bachat Campaign] cancelInterest: ' . $e->getMessage());
            flash('error', t('common.error'));
            $this->redirect('/campaigns/' . $camp['id']);
        }

        Audit::log('campaign.interest_cancel', 'campaign', (int) $camp['id'], ['user_id' => $uid]);
        flash('success', t('campaigns.cancel_done'));
        $this->redirect('/campaigns/' . $camp['id']);
    }

    /* ============================== HELPERS ============================== */

    /** Campaign items joined with product/brand/pack info for display. */
    private function itemsWithProducts(int $campaignId): array
    {
        return Database::fetchAll(
            'SELECT ci.*, v.pack_size, v.unit, v.sku, v.selling_price,
                    p.name_en, p.name_hi, p.name_mr, p.image, b.name AS brand_name
               FROM campaign_items ci
               JOIN product_variants v ON v.id = ci.variant_id
               JOIN products p         ON p.id = v.product_id
               LEFT JOIN brands b      ON b.id = v.brand_id
              WHERE ci.campaign_id = ?
              ORDER BY ci.id',
            [$campaignId]
        );
    }

    /** Human "time left" string for a deadline. */
    private static function countdown(?string $deadline): string
    {
        if (!$deadline) {
            return t('campaigns.no_deadline');
        }
        $diff = strtotime($deadline) - time();
        if ($diff <= 0) {
            return t('campaigns.ended');
        }
        $days = (int) floor($diff / 86400);
        if ($days >= 1) {
            return t('campaigns.days_left', ['n' => (string) $days]);
        }
        $hours = max(1, (int) floor($diff / 3600));
        return t('campaigns.hours_left', ['n' => (string) $hours]);
    }
}
