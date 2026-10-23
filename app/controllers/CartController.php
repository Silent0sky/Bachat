<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Services\Notify;

/**
 * Cart + checkout + order placement (Task 3 / Agent A).
 * Routes: POST /cart/add|update|remove, GET /cart, GET /checkout, POST /checkout (place).
 *
 * SECURITY: client prices are never trusted — every total is recomputed
 * server-side from product_variants + society_prices.
 */
final class CartController extends Controller
{
    /* ==================================================================
     *  Helpers
     * ================================================================== */

    private function wantsJson(): bool
    {
        $xrw = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return $xrw === 'xmlhttprequest'
            || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
    }

    /** Effective price for one variant: society override → variant base price. */
    private function effectivePrice(int $variantId, int $societyId): float
    {
        $p = Database::scalar(
            'SELECT selling_price FROM society_prices WHERE variant_id = ? AND society_id = ?',
            [$variantId, $societyId]
        );
        if ($p === false || $p === null) {
            $p = Database::scalar('SELECT selling_price FROM product_variants WHERE id = ?', [$variantId]);
        }
        return round((float) $p, 2);
    }

    /** Get-or-create the cart row for the logged-in user. */
    private function getCart(): array
    {
        $uid  = (int) Auth::id();
        $cart = Database::fetch('SELECT * FROM carts WHERE user_id = ? LIMIT 1', [$uid]);
        if (!$cart) {
            Database::run(
                'INSERT INTO carts (user_id, society_id, created_at) VALUES (?,?,NOW())',
                [$uid, Auth::societyId()]
            );
            $cart = [
                'id'         => (int) Database::pdo()->lastInsertId(),
                'user_id'    => $uid,
                'society_id' => Auth::societyId(),
            ];
        }
        return $cart;
    }

    /** Cart rows joined with live variant/product data + effective price. */
    private function cartItems(int $cartId, int $sid): array
    {
        return Database::fetchAll(
            'SELECT ci.id AS cart_item_id, ci.variant_id, v.id AS variant_id, v.id, ci.qty,
                    v.pack_size, v.unit, v.sku, v.stock_qty, v.min_qty, v.max_qty,
                    v.is_active AS variant_active, v.reference_price,
                    p.id AS product_id, p.name_en, p.name_hi, p.name_mr, p.image,
                    p.is_active AS product_active, p.fulfillment,
                    b.name AS brand_name,
                    COALESCE(sp.selling_price, v.selling_price) AS unit_price
             FROM cart_items ci
             JOIN product_variants v ON v.id = ci.variant_id
             JOIN products p ON p.id = v.product_id
             LEFT JOIN brands b ON b.id = v.brand_id
             LEFT JOIN society_prices sp ON sp.variant_id = v.id AND sp.society_id = ?
             WHERE ci.cart_id = ?
             ORDER BY ci.id ASC',
            [$sid, $cartId]
        );
    }

    private function cartSubtotal(array $items): float
    {
        $sum = 0.0;
        foreach ($items as $it) {
            $sum += round(((float) $it['unit_price']) * ((float) $it['qty']), 2);
        }
        return round($sum, 2);
    }

    /**
     * Validate a variant for cart math (server-side truth).
     * @return array{error:?string, variant:?array, qty:float}
     */
    private function checkVariant(int $vid, float $qty): array
    {
        $v = Database::fetch(
            'SELECT v.*, p.is_active AS product_active, p.fulfillment
             FROM product_variants v JOIN products p ON p.id = v.product_id
             WHERE v.id = ? LIMIT 1',
            [$vid]
        );
        if (!$v || !(int) $v['is_active'] || !(int) $v['product_active']) {
            return ['error' => t('shop.unavailable'), 'variant' => null, 'qty' => $qty];
        }
        if ((float) $v['stock_qty'] <= 0) {
            return ['error' => t('shop.out_of_stock'), 'variant' => null, 'qty' => $qty];
        }
        if ($qty < (float) $v['min_qty']) {
            return ['error' => t('shop.min_qty_note', ['qty' => qty($v['min_qty'])]), 'variant' => null, 'qty' => $qty];
        }
        if ($v['max_qty'] !== null && $qty > (float) $v['max_qty']) {
            return ['error' => t('shop.max_qty_note', ['max' => qty($v['max_qty'])]), 'variant' => null, 'qty' => $qty];
        }
        $qty = round(min($qty, (float) $v['stock_qty']), 3); // clamp to available stock
        return ['error' => null, 'variant' => $v, 'qty' => $qty];
    }

    /**
     * Coupon re-validation. Returns null when usable, else a translated error.
     */
    private function couponError(?array $c, float $subtotal, int $sid, int $uid): ?string
    {
        if (!$c || !(int) $c['is_active']) {
            return t('shop.coupon_invalid');
        }
        $now = time();
        if (!empty($c['valid_from']) && strtotime((string) $c['valid_from']) > $now) {
            return t('shop.coupon_invalid');
        }
        if (!empty($c['valid_to']) && strtotime((string) $c['valid_to']) < $now) {
            return t('shop.coupon_invalid');
        }
        if ((float) $c['min_cart_value'] > 0 && $subtotal < (float) $c['min_cart_value']) {
            return t('checkout.coupon_min_cart', ['amount' => money($c['min_cart_value'])]);
        }
        if ($c['society_id'] !== null && (int) $c['society_id'] !== $sid) {
            return t('shop.coupon_invalid');
        }
        if ($c['usage_limit'] !== null && (int) $c['used_count'] >= (int) $c['usage_limit']) {
            return t('shop.coupon_invalid');
        }
        $used = (int) Database::scalar(
            'SELECT COUNT(*) FROM coupon_redemptions WHERE coupon_id = ? AND user_id = ?',
            [(int) $c['id'], $uid]
        );
        if ($used >= (int) $c['per_user_limit']) {
            return t('checkout.coupon_user_limit');
        }
        return null;
    }

    private function couponDiscount(array $c, float $subtotal): float
    {
        $d = $c['type'] === 'percent'
            ? round($subtotal * (float) $c['value'] / 100, 2)
            : (float) $c['value'];
        if ($c['max_discount'] !== null) {
            $d = min($d, (float) $c['max_discount']);
        }
        return round(min($d, $subtotal), 2);
    }

    /* ==================================================================
     *  POST /cart/add (AJAX from data-add-to-cart buttons)
     * ================================================================== */
    public function add(): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();
        $sid = (int) Auth::societyId();

        $vid = (int) $this->post('variant_id', 0);
        $qty = (float) ($this->post('qty', 1) ?: 1);

        $c = $this->checkVariant($vid, $qty);
        if ($c['error']) {
            if ($this->wantsJson()) {
                $this->json(['ok' => false, 'message' => $c['error']]);
            }
            flash('error', $c['error']);
            $this->back();
        }

        $cart   = $this->getCart();
        $existing = Database::fetch(
            'SELECT id, qty FROM cart_items WHERE cart_id = ? AND variant_id = ? LIMIT 1',
            [(int) $cart['id'], $vid]
        );

        if ($existing) {
            $newQty = (float) $existing['qty'] + $c['qty'];
            if ($c['variant']['max_qty'] !== null) {
                $newQty = min($newQty, (float) $c['variant']['max_qty']);
            }
            $newQty = round(min($newQty, (float) $c['variant']['stock_qty']), 3);
            Database::run('UPDATE cart_items SET qty = ? WHERE id = ?', [$newQty, (int) $existing['id']]);
            $delta = max(0, $newQty - (float) $existing['qty']);
        } else {
            Database::run(
                'INSERT INTO cart_items (cart_id, variant_id, qty) VALUES (?,?,?)',
                [(int) $cart['id'], $vid, $c['qty']]
            );
            $delta = $c['qty'];
        }

        $items    = $this->cartItems((int) $cart['id'], $sid);
        $subtotal = $this->cartSubtotal($items);

        if ($this->wantsJson()) {
            $this->json([
                'ok'                 => true,
                'message'            => t('shop.added_to_cart'),
                'delta'              => (int) round($delta),
                'cart_subtotal_html' => money($subtotal),
            ]);
        }
        flash('success', t('shop.added_to_cart'));
        $this->redirect('/cart');
    }

    /* ==================================================================
     *  POST /cart/update — AJAX (JSON) or regular PRG
     * ================================================================== */
    public function update(): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();
        $sid = (int) Auth::societyId();

        $vid = (int) $this->post('variant_id', 0);
        $qty = (float) ($this->post('qty', 0) ?: 0);
        $cart = $this->getCart();

        if ($qty <= 0) {
            Database::run('DELETE FROM cart_items WHERE cart_id = ? AND variant_id = ?', [(int) $cart['id'], $vid]);
            if ($this->wantsJson()) {
                $this->json(['ok' => true, 'message' => t('shop.removed_from_cart')]);
            }
            flash('success', t('shop.removed_from_cart'));
            $this->redirect('/cart');
        }

        $c = $this->checkVariant($vid, $qty);
        if ($c['error']) {
            if ($this->wantsJson()) {
                $this->json(['ok' => false, 'message' => $c['error']]);
            }
            flash('error', $c['error']);
            $this->back();
        }

        $existing = Database::fetch(
            'SELECT id FROM cart_items WHERE cart_id = ? AND variant_id = ? LIMIT 1',
            [(int) $cart['id'], $vid]
        );
        if ($existing) {
            Database::run('UPDATE cart_items SET qty = ? WHERE id = ?', [$c['qty'], (int) $existing['id']]);
        } else {
            Database::run(
                'INSERT INTO cart_items (cart_id, variant_id, qty) VALUES (?,?,?)',
                [(int) $cart['id'], $vid, $c['qty']]
            );
        }

        if ($this->wantsJson()) {
            $items = $this->cartItems((int) $cart['id'], $sid);
            $this->json([
                'ok'                 => true,
                'message'            => t('shop.cart_updated'),
                'cart_subtotal_html' => money($this->cartSubtotal($items)),
            ]);
        }
        flash('success', t('shop.cart_updated'));
        $this->redirect('/cart');
    }

    /* ==================================================================
     *  POST /cart/remove — AJAX (JSON) or regular PRG
     * ================================================================== */
    public function remove(): void
    {
        $this->requireRole(['resident']);
        $cart = $this->getCart();
        $vid  = (int) $this->post('variant_id', 0);
        Database::run('DELETE FROM cart_items WHERE cart_id = ? AND variant_id = ?', [(int) $cart['id'], $vid]);

        if ($this->wantsJson()) {
            $this->json(['ok' => true, 'message' => t('shop.removed_from_cart')]);
        }
        flash('success', t('shop.removed_from_cart'));
        $this->redirect('/cart');
    }

    /* ==================================================================
     *  GET /cart
     * ================================================================== */
    public function index(): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();
        $sid = (int) Auth::societyId();

        $cart  = $this->getCart();
        $items = $this->cartItems((int) $cart['id'], $sid);

        $this->view('cart/index', [
            'pageTitle' => t('shop.cart_title'),
            'active'    => 'cart',
            'items'     => $items,
            'subtotal'  => $this->cartSubtotal($items),
        ]);
    }

    /* ==================================================================
     *  GET /checkout — also handles ?coupon=apply|remove (GET form)
     * ================================================================== */
    public function checkout(): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();
        $sid = (int) Auth::societyId();
        $uid = (int) Auth::id();
        $soc = current_society();

        $cart  = $this->getCart();
        $items = $this->cartItems((int) $cart['id'], $sid);
        if (!$items) {
            flash('info', t('shop.cart_empty'));
            $this->redirect('/cart');
        }
        $subtotal = $this->cartSubtotal($items);

        /* ---- Coupon apply / remove via GET (?coupon=apply&code=X) ---- */
        $couponParam = (string) ($this->query('coupon', '') ?? '');
        if ($couponParam === 'apply') {
            $code = strtoupper(trim((string) ($this->query('code', '') ?? '')));
            $row  = $code !== ''
                ? Database::fetch('SELECT * FROM coupons WHERE code = ? LIMIT 1', [$code])
                : null;
            $err = $this->couponError($row, $subtotal, $sid, $uid);
            if ($row && !$err) {
                Session::set('applied_coupon', ['id' => (int) $row['id'], 'code' => (string) $row['code']]);
                flash('success', t('shop.coupon_applied', ['code' => (string) $row['code']]));
            } else {
                flash('error', $err ?? t('shop.coupon_invalid'));
            }
            $this->redirect('/checkout');
        } elseif ($couponParam === 'remove') {
            Session::forget('applied_coupon');
            flash('info', t('checkout.coupon_removed'));
            $this->redirect('/checkout');
        }

        /* ---- Applied coupon (re-validated every render) ---- */
        $couponRow = null;
        $discount  = 0.0;
        $applied   = Session::get('applied_coupon');
        if (is_array($applied) && !empty($applied['id'])) {
            $row = Database::fetch('SELECT * FROM coupons WHERE id = ? LIMIT 1', [(int) $applied['id']]);
            $err = $this->couponError($row, $subtotal, $sid, $uid);
            if ($row && !$err) {
                $couponRow = $row;
                $discount  = $this->couponDiscount($row, $subtotal);
            } else {
                Session::forget('applied_coupon');
                if ($err) {
                    flash('error', $err);
                }
            }
        }

        $addresses = Database::fetchAll(
            'SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id ASC',
            [$uid]
        );

        $pickupEnabled   = $soc ? (int) $soc['pickup_enabled'] === 1 : true;
        $deliveryEnabled = $soc ? (int) $soc['delivery_enabled'] === 1 : true;
        if (!$pickupEnabled && !$deliveryEnabled) {
            $pickupEnabled = true; // sane fallback
        }

        $codEnabled = (bool) config('payments.cod.enabled', false);
        $upiEnabled = (bool) config('payments.upi.enabled', false);

        $this->view('cart/checkout', [
            'pageTitle'       => t('checkout.order_summary'),
            'active'          => 'cart',
            'items'           => $items,
            'subtotal'        => $subtotal,
            'coupon'          => $couponRow,
            'discount'        => $discount,
            'addresses'       => $addresses,
            'pickupEnabled'   => $pickupEnabled,
            'deliveryEnabled' => $deliveryEnabled,
            'codEnabled'      => $codEnabled,
            'upiEnabled'      => $upiEnabled,
            'deliveryCharge'  => $soc ? (float) $soc['delivery_charge'] : 0.0,
        ]);
    }

    /* ==================================================================
     *  POST /checkout — PLACE ORDER (full server-side recompute)
     * ================================================================== */
    public function placeOrder(): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();
        $uid = (int) Auth::id();
        $sid = (int) Auth::societyId();
        $soc = Database::fetch('SELECT * FROM societies WHERE id = ? LIMIT 1', [$sid]);
        if (!$soc) {
            flash('error', t('common.error'));
            $this->redirect('/cart');
        }

        /* ---- Mandatory confirmation checkbox ---- */
        if (!$this->post('confirm')) {
            flash('error', t('checkout.must_confirm'));
            $this->redirect('/checkout');
        }

        /* ---- Fulfilment (server-side clamp to society capabilities) ---- */
        $fulfilment = $this->post('fulfilment') === 'delivery' ? 'delivery' : 'pickup';
        if ($fulfilment === 'delivery' && !(int) $soc['delivery_enabled']) {
            $fulfilment = 'pickup';
        }
        if ($fulfilment === 'pickup' && !(int) $soc['pickup_enabled']) {
            $fulfilment = 'delivery';
        }

        /* ---- Payment method (server-side clamp to enabled config) ---- */
        $method = $this->post('payment_method') === 'upi' ? 'upi' : 'cod';
        if ($method === 'upi' && !config('payments.upi.enabled', false)) {
            $method = 'cod';
        }
        if ($method === 'cod' && !config('payments.cod.enabled', false)) {
            $method = 'upi';
        }
        if ($method === 'upi' && !config('payments.upi.enabled', false)) {
            flash('error', t('checkout.no_payment_method'));
            $this->redirect('/checkout');
        }

        /* ---- Contact + address validation ---- */
        $rules = [
            'contact_name'  => 'required|max:120',
            'contact_phone' => 'required|min:6|max:20',
        ];
        $isDelivery = $fulfilment === 'delivery';
        if ($isDelivery && $this->post('address_id') === 'new') {
            $rules += [
                'new_label' => 'required|max:60',
                'new_line1' => 'required|max:255',
                'new_pincode' => 'max:10',
            ];
        }
        $errors = $this->validate($rules);
        if ($errors) {
            flash('error', (string) reset($errors));
            $this->redirect('/checkout');
        }

        $contactName  = (string) $this->post('contact_name', '');
        $contactPhone = (string) $this->post('contact_phone', '');

        /* ---- Resolve address text ---- */
        if ($isDelivery) {
            $addrId = (string) ($this->post('address_id', '') ?? '');
            if ($addrId === 'new') {
                $label   = mb_substr((string) $this->post('new_label', ''), 0, 60);
                $line1   = mb_substr((string) $this->post('new_line1', ''), 0, 255);
                $city    = mb_substr((string) ($this->post('new_city', '') ?? ''), 0, 80);
                $pincode = mb_substr((string) ($this->post('new_pincode', '') ?? ''), 0, 10);
                Database::run(
                    'INSERT INTO addresses (user_id, label, line1, city, pincode, is_default, created_at)
                     VALUES (?,?,?,?,?,0,NOW())',
                    [$uid, $label, $line1, $city, $pincode]
                );
                $addressText = $label . ': ' . $line1 . ($city !== '' ? ', ' . $city : '') . ($pincode !== '' ? ' - ' . $pincode : '');
            } else {
                $addr = Database::fetch(
                    'SELECT * FROM addresses WHERE id = ? AND user_id = ? LIMIT 1',
                    [(int) $addrId, $uid]
                );
                if (!$addr) {
                    flash('error', t('checkout.pick_address'));
                    $this->redirect('/checkout');
                }
                $addressText = $addr['label'] . ': ' . $addr['line1']
                    . ($addr['city'] ? ', ' . $addr['city'] : '')
                    . ($addr['pincode'] ? ' - ' . $addr['pincode'] : '');
            }
        } else {
            $addressText = t('shop.pickup') . ': ' . $soc['name']
                . ($soc['address'] ? ', ' . $soc['address'] : '')
                . ($soc['city'] ? ', ' . $soc['city'] : '');
        }

        /* ==================================================================
         *  TRANSACTION — order + items + events + payment + coupon +
         *  stock reservation + commission, then empty the cart.
         * ================================================================== */
        $pdo = Database::pdo();
        $orderId = 0;
        $orderNo = '';
        $skipped = [];
        $total   = 0.0;

        $pdo->beginTransaction();
        try {
            $cart = Database::fetch('SELECT id FROM carts WHERE user_id = ? LIMIT 1', [$uid]);
            if (!$cart) {
                throw new \RuntimeException('cart missing');
            }
            $rows = $this->cartItems((int) $cart['id'], $sid);
            if (!$rows) {
                throw new \RuntimeException('cart empty');
            }

            $valid = [];
            foreach ($rows as $r) {
                if (!(int) $r['variant_active'] || !(int) $r['product_active'] || (float) $r['stock_qty'] <= 0) {
                    $skipped[] = product_name($r);
                    continue;
                }
                $q = (float) $r['qty'];
                if ($q < (float) $r['min_qty']) {
                    $q = (float) $r['min_qty'];
                }
                if ($r['max_qty'] !== null && $q > (float) $r['max_qty']) {
                    $q = (float) $r['max_qty'];
                }
                $q = round(min($q, (float) $r['stock_qty']), 3);
                if ($q <= 0) {
                    $skipped[] = product_name($r);
                    continue;
                }
                $valid[] = [
                    'variant'    => $r,
                    'qty'        => $q,
                    'unit_price' => round((float) $r['unit_price'], 2),
                ];
            }
            if (!$valid) {
                throw new \RuntimeException('no valid items');
            }

            $subtotal = 0.0;
            foreach ($valid as $line) {
                $subtotal += round($line['unit_price'] * $line['qty'], 2);
            }
            $subtotal = round($subtotal, 2);

            /* ---- Coupon re-validation (never trust the session blindly) ---- */
            $couponId = null;
            $discount = 0.0;
            $applied  = Session::get('applied_coupon');
            if (is_array($applied) && !empty($applied['id'])) {
                $cRow = Database::fetch('SELECT * FROM coupons WHERE id = ? LIMIT 1', [(int) $applied['id']]);
                $err  = $this->couponError($cRow, $subtotal, $sid, $uid);
                if ($cRow && !$err) {
                    $discount = $this->couponDiscount($cRow, $subtotal);
                    $couponId = (int) $cRow['id'];
                } else {
                    Session::forget('applied_coupon'); // silently drop invalid coupon
                }
            }

            $deliveryFee = $isDelivery ? round((float) $soc['delivery_charge'], 2) : 0.0;
            $total = round(max(0.0, $subtotal - $discount + $deliveryFee), 2);

            /* ---- Unique order number ---- */
            do {
                $orderNo = 'BC' . date('ymd') . strtoupper(bin2hex(random_bytes(2)));
                $dup = Database::scalar('SELECT id FROM orders WHERE order_no = ? LIMIT 1', [$orderNo]);
            } while ($dup);

            Database::run(
                'INSERT INTO orders (order_no, user_id, society_id, type, status, subtotal, discount, delivery_fee, tax, total,
                                     coupon_id, address_text, contact_name, contact_phone, fulfilment, placed_at, created_at, updated_at)
                 VALUES (?,?,?,"regular","pending",?,?,?,?,?,?,?,?,?,?,NOW(),NOW(),NOW())',
                [$orderNo, $uid, $sid, $subtotal, $discount, $deliveryFee, 0, $total, $couponId,
                 $addressText, $contactName, $contactPhone, $fulfilment]
            );
            $orderId = (int) $pdo->lastInsertId();

            /* ---- Immutable line snapshots ---- */
            foreach ($valid as $line) {
                $v = $line['variant'];
                Database::run(
                    'INSERT INTO order_items (order_id, variant_id, product_name, brand_name, pack_size, unit, qty, unit_price, line_total)
                     VALUES (?,?,?,?,?,?,?,?,?)',
                    [
                        $orderId,
                        (int) ($v['variant_id'] ?? $v['id'] ?? 0),
                        product_name($v),
                        $v['brand_name'] ?? null,
                        $v['pack_size'],
                        $v['unit'],
                        $line['qty'],
                        $line['unit_price'],
                        round($line['unit_price'] * $line['qty'], 2),
                    ]
                );
            }

            Database::run(
                'INSERT INTO order_events (order_id, status, note, created_by, created_at) VALUES (?, "pending", ?, ?, NOW())',
                [$orderId, 'Order placed', $uid]
            );

            Database::run(
                'INSERT INTO payments (order_id, society_id, user_id, amount, method, status, created_at)
                 VALUES (?,?,?,?,?, "pending", NOW())',
                [$orderId, $sid, $uid, $total, $method]
            );

            /* ---- Coupon redemption bookkeeping ---- */
            if ($couponId !== null) {
                Database::run(
                    'INSERT INTO coupon_redemptions (coupon_id, order_id, user_id, amount) VALUES (?,?,?,?)',
                    [$couponId, $orderId, $uid, $discount]
                );
                Database::run('UPDATE coupons SET used_count = used_count + 1 WHERE id = ?', [$couponId]);
            }

            /* ---- Stock reservation (central fulfillment only) ---- */
            foreach ($valid as $line) {
                $v = $line['variant'];
                if (($v['fulfillment'] ?? 'central') !== 'central') {
                    continue;
                }
                $inv = Database::fetch('SELECT id FROM inventory WHERE variant_id = ? LIMIT 1', [(int) $v['id']]);
                if ($inv) {
                    Database::run('UPDATE inventory SET reserved = reserved + ? WHERE id = ?', [$line['qty'], (int) $inv['id']]);
                } else {
                    Database::run(
                        'UPDATE product_variants SET stock_qty = GREATEST(0, stock_qty - ?) WHERE id = ?',
                        [$line['qty'], (int) $v['id']]
                    );
                }
                Database::run(
                    'INSERT INTO stock_movements (variant_id, type, qty, ref_type, ref_id, note, created_by, created_at)
                     VALUES (?, "reserve", ?, "order", ?, ?, ?, NOW())',
                    [(int) $v['id'], $line['qty'], $orderId, 'Order ' . $orderNo, $uid]
                );
            }

            /* ---- Society commission ---- */
            if ((float) $soc['commission_rate'] > 0) {
                $ctype  = $soc['commission_type'] === 'fixed' ? 'fixed' : 'percent';
                $cAmount = $ctype === 'percent'
                    ? round($total * (float) $soc['commission_rate'] / 100, 2)
                    : round((float) $soc['commission_rate'], 2);
                Database::run(
                    'INSERT INTO commissions (order_id, society_id, type, rate, amount, status) VALUES (?,?,?,?,?, "due")',
                    [$orderId, $sid, $ctype, $soc['commission_rate'], $cAmount]
                );
            }

            Database::run('DELETE FROM cart_items WHERE cart_id = ?', [(int) $cart['id']]);
            Session::forget('applied_coupon');

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[Bachat Order] ' . $e->getMessage());
            flash('error', $e->getMessage() === 'cart empty' || $e->getMessage() === 'no valid items'
                ? t('shop.cart_empty') : t('common.error'));
            $this->redirect('/cart');
        }

        Audit::log('order.placed', 'order', $orderId, ['order_no' => $orderNo, 'total' => $total]);
        Notify::societyAdmins(
            $sid,
            'order',
            'New order ' . $orderNo,
            (string) (auth_user()['name'] ?? 'Resident') . ' · ' . money($total),
            '/admin/orders'
        );

        flash('success', t('shop.order_placed'));
        if ($skipped) {
            flash('info', t('orders.some_skipped', ['items' => implode(', ', array_slice($skipped, 0, 3)) . (count($skipped) > 3 ? '…' : '')]));
        }
        $this->redirect('/orders/' . $orderId);
    }
}
