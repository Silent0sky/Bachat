<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Core\Upload;
use App\Services\Notify;
use RuntimeException;

/**
 * Resident orders: list, detail, cancel, invoice, repeat, UPI payment (Task 3 / Agent A).
 * Routes: GET /orders[/{id}], POST /orders/{id}/cancel|repeat|pay,
 *         GET /orders/{id}/invoice|pay
 */
final class OrderController extends Controller
{
    /* ==================================================================
     *  Helpers
     * ================================================================== */

    /** Fetch an order owned by the current user or die with 404. */
    private function ownOrder(int $id): array
    {
        $o = Database::fetch(
            'SELECT * FROM orders WHERE id = ? AND user_id = ? LIMIT 1',
            [$id, Auth::id()]
        );
        if (!$o) {
            http_response_code(404);
            include APP . '/views/errors/404.php';
            exit;
        }
        return $o;
    }

    private function latestPayment(int $orderId): ?array
    {
        return Database::fetch(
            'SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC LIMIT 1',
            [$orderId]
        );
    }

    private function orderQuery(string $extra = ''): string
    {
        return 'SELECT o.*,
                (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
                (SELECT p.status FROM payments p WHERE p.order_id = o.id ORDER BY p.id DESC LIMIT 1) AS pay_status,
                (SELECT p.method  FROM payments p WHERE p.order_id = o.id ORDER BY p.id DESC LIMIT 1) AS pay_method
                FROM orders o ' . $extra;
    }

    /* ==================================================================
     *  GET /orders
     * ================================================================== */
    public function index(): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();

        $orders = Database::fetchAll(
            $this->orderQuery('WHERE o.user_id = ? ORDER BY o.id DESC LIMIT 100'),
            [Auth::id()]
        );

        $this->view('orders/index', [
            'pageTitle' => t('orders.my_orders'),
            'active'    => 'orders',
            'orders'    => $orders,
        ]);
    }

    /* ==================================================================
     *  GET /orders/{id}
     * ================================================================== */
    public function show(string $id): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();

        $order   = $this->ownOrder((int) $id);
        $items   = Database::fetchAll('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC', [$order['id']]);
        $events  = Database::fetchAll('SELECT * FROM order_events WHERE order_id = ? ORDER BY id ASC', [$order['id']]);
        $payment = $this->latestPayment((int) $order['id']);
        $society = Database::fetch('SELECT * FROM societies WHERE id = ? LIMIT 1', [$order['society_id']]);

        $this->view('orders/show', [
            'pageTitle' => t('orders.order_page') . ' ' . $order['order_no'],
            'active'    => 'orders',
            'order'     => $order,
            'items'     => $items,
            'events'    => $events,
            'payment'   => $payment,
            'society'   => $society,
        ]);
    }

    /* ==================================================================
     *  POST /orders/{id}/cancel
     * ================================================================== */
    public function cancel(string $id): void
    {
        $this->requireRole(['resident']);
        $order = $this->ownOrder((int) $id);

        if (!in_array($order['status'], ['pending', 'confirmed'], true)) {
            flash('error', t('orders.cannot_cancel'));
            $this->redirect('/orders/' . (int) $order['id']);
        }

        $reason = mb_substr((string) ($this->post('cancel_reason', '') ?? ''), 0, 255);
        $uid    = (int) Auth::id();

        $pdo = Database::pdo();
        $refunded = false;
        $pdo->beginTransaction();
        try {
            Database::run(
                'UPDATE orders SET status = "cancelled", cancel_reason = ?, updated_at = NOW() WHERE id = ?',
                [$reason !== '' ? $reason : null, (int) $order['id']]
            );
            Database::run(
                'INSERT INTO order_events (order_id, status, note, created_by, created_at) VALUES (?, "cancelled", ?, ?, NOW())',
                [(int) $order['id'], 'Cancelled by customer' . ($reason !== '' ? ' — ' . $reason : ''), $uid]
            );

            /* Release reserved stock (mirror of the placement reservation). */
            $items = Database::fetchAll(
                'SELECT variant_id, qty FROM order_items WHERE order_id = ? AND variant_id IS NOT NULL',
                [(int) $order['id']]
            );
            foreach ($items as $it) {
                $inv = Database::fetch('SELECT id FROM inventory WHERE variant_id = ? LIMIT 1', [(int) $it['variant_id']]);
                if ($inv) {
                    Database::run(
                        'UPDATE inventory SET reserved = GREATEST(0, reserved - ?) WHERE id = ?',
                        [(float) $it['qty'], (int) $inv['id']]
                    );
                } else {
                    Database::run(
                        'UPDATE product_variants SET stock_qty = stock_qty + ? WHERE id = ?',
                        [(float) $it['qty'], (int) $it['variant_id']]
                    );
                }
                Database::run(
                    'INSERT INTO stock_movements (variant_id, type, qty, ref_type, ref_id, note, created_by, created_at)
                     VALUES (?, "release", ?, "order", ?, ?, ?, NOW())',
                    [(int) $it['variant_id'], (float) $it['qty'], (int) $order['id'], 'Order ' . $order['order_no'] . ' cancelled', $uid]
                );
            }

            /* Verified payment → refund request for super admins. */
            $verified = Database::fetch(
                'SELECT * FROM payments WHERE order_id = ? AND status = "verified" ORDER BY id DESC LIMIT 1',
                [(int) $order['id']]
            );
            if ($verified) {
                Database::run(
                    'INSERT INTO refunds (order_id, payment_id, amount, reason, status, created_at)
                     VALUES (?, ?, ?, "Order cancelled", "requested", NOW())',
                    [(int) $order['id'], (int) $verified['id'], (float) $verified['amount']]
                );
                $refunded = true;
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[Bachat OrderCancel] ' . $e->getMessage());
            flash('error', t('common.error'));
            $this->redirect('/orders/' . (int) $order['id']);
        }

        Notify::societyAdmins(
            (int) $order['society_id'],
            'order',
            'Order ' . $order['order_no'] . ' cancelled',
            (string) (auth_user()['name'] ?? 'Resident') . ($reason !== '' ? ' · ' . $reason : ''),
            '/admin/orders'
        );
        Notify::user($uid, 'order', 'Order ' . $order['order_no'] . ' cancelled', t('orders.cancelled'), '/orders/' . (int) $order['id']);
        if ($refunded) {
            Notify::superAdmins('refund', 'Refund requested — order ' . $order['order_no'], money((float) $verified['amount']), '/super/refunds');
        }
        Audit::log('order.cancelled', 'order', (int) $order['id'], ['reason' => $reason]);

        flash('success', t('orders.cancelled'));
        $this->redirect('/orders/' . (int) $order['id']);
    }

    /* ==================================================================
     *  GET /orders/{id}/invoice — printable, layout-less
     * ================================================================== */
    public function invoice(string $id): void
    {
        $this->requireRole(['resident']);
        $order = $this->ownOrder((int) $id);
        $items = Database::fetchAll('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC', [$order['id']]);
        $payment = $this->latestPayment((int) $order['id']);
        $society = Database::fetch('SELECT * FROM societies WHERE id = ? LIMIT 1', [$order['society_id']]);

        $this->bare('orders/invoice', [
            'order'   => $order,
            'items'   => $items,
            'payment' => $payment,
            'society' => $society ?: null,
        ]);
    }

    /* ==================================================================
     *  POST /orders/{id}/repeat — copy items into the cart
     * ================================================================== */
    public function repeat(string $id): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();
        $uid = (int) Auth::id();
        $sid = (int) Auth::societyId();

        $order = $this->ownOrder((int) $id);
        $items = Database::fetchAll(
            'SELECT oi.variant_id, oi.qty FROM order_items oi
             WHERE oi.order_id = ? AND oi.variant_id IS NOT NULL',
            [(int) $order['id']]
        );
        if (!$items) {
            flash('info', t('shop.list_empty'));
            $this->redirect('/orders/' . (int) $order['id']);
        }

        $cart = Database::fetch('SELECT id FROM carts WHERE user_id = ? LIMIT 1', [$uid]);
        if (!$cart) {
            Database::run('INSERT INTO carts (user_id, society_id, created_at) VALUES (?,?,NOW())', [$uid, $sid]);
            $cart = ['id' => (int) Database::pdo()->lastInsertId()];
        }
        $cartId = (int) $cart['id'];

        $added = 0;
        $skipped = 0;
        foreach ($items as $it) {
            $v = Database::fetch(
                'SELECT v.id, v.stock_qty, v.min_qty, v.max_qty
                 FROM product_variants v JOIN products p ON p.id = v.product_id
                 WHERE v.id = ? AND v.is_active = 1 AND p.is_active = 1 LIMIT 1',
                [(int) $it['variant_id']]
            );
            if (!$v || (float) $v['stock_qty'] <= 0) {
                $skipped++;
                continue;
            }
            $qty = (float) $it['qty'];
            if ($qty < (float) $v['min_qty']) {
                $qty = (float) $v['min_qty'];
            }
            if ($v['max_qty'] !== null && $qty > (float) $v['max_qty']) {
                $qty = (float) $v['max_qty'];
            }
            $qty = round(min($qty, (float) $v['stock_qty']), 3);
            if ($qty <= 0) {
                $skipped++;
                continue;
            }

            $existing = Database::fetch(
                'SELECT id, qty FROM cart_items WHERE cart_id = ? AND variant_id = ? LIMIT 1',
                [$cartId, (int) $v['id']]
            );
            if ($existing) {
                $newQty = (float) $existing['qty'] + $qty;
                if ($v['max_qty'] !== null) {
                    $newQty = min($newQty, (float) $v['max_qty']);
                }
                Database::run(
                    'UPDATE cart_items SET qty = ? WHERE id = ?',
                    [round(min($newQty, (float) $v['stock_qty']), 3), (int) $existing['id']]
                );
            } else {
                Database::run(
                    'INSERT INTO cart_items (cart_id, variant_id, qty) VALUES (?,?,?)',
                    [$cartId, (int) $v['id'], $qty]
                );
            }
            $added++;
        }

        if ($added > 0) {
            flash('success', t('orders.repeat_added', ['n' => $added]));
        }
        if ($skipped > 0) {
            flash('info', t('orders.repeat_skipped', ['n' => $skipped]));
        }
        if ($added === 0 && $skipped === 0) {
            flash('info', t('shop.cart_empty'));
        }
        $this->redirect('/cart');
    }

    /* ==================================================================
     *  GET /orders/{id}/pay — UPI payment submission page
     * ================================================================== */
    public function payPage(string $id): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();

        $order   = $this->ownOrder((int) $id);
        $payment = $this->latestPayment((int) $order['id']);

        if (!$payment || $payment['method'] !== 'upi' || !in_array($payment['status'], ['pending', 'failed'], true)) {
            flash('info', t('orders.pay_not_allowed'));
            $this->redirect('/orders/' . (int) $order['id']);
        }

        $society = Database::fetch('SELECT * FROM societies WHERE id = ? LIMIT 1', [$order['society_id']]);

        $this->view('orders/pay', [
            'pageTitle' => t('orders.upi_title') . ' · ' . $order['order_no'],
            'active'    => 'orders',
            'order'     => $order,
            'payment'   => $payment,
            'society'   => $society ?: null,
        ]);
    }

    /* ==================================================================
     *  POST /orders/{id}/pay — submit UTR + screenshot for verification
     * ================================================================== */
    public function submitPayment(string $id): void
    {
        $this->requireRole(['resident']);
        $uid    = (int) Auth::id();
        $order  = $this->ownOrder((int) $id);
        $payment = $this->latestPayment((int) $order['id']);

        if (!$payment || $payment['method'] !== 'upi' || !in_array($payment['status'], ['pending', 'failed', 'submitted'], true)) {
            flash('info', t('orders.pay_not_allowed'));
            $this->redirect('/orders/' . (int) $order['id']);
        }

        $errors = $this->validate(['utr' => 'required|min:6|max:80']);
        if ($errors) {
            flash('error', (string) reset($errors));
            $this->back();
        }

        $proofRel = null;
        if ($file = $this->file('proof')) {
            try {
                $proofRel = Upload::image($file, 'payments');
            } catch (RuntimeException $e) {
                flash('error', $e->getMessage());
                $this->back();
            }
        }

        $utr = mb_substr((string) $this->post('utr', ''), 0, 80);

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'UPDATE payments SET utr = ?, txn_ref = ?, proof_image = COALESCE(?, proof_image), status = "submitted" WHERE id = ?',
                [$utr, $utr, $proofRel, (int) $payment['id']]
            );
            Database::run(
                'INSERT INTO order_events (order_id, status, note, created_by, created_at) VALUES (?, ?, ?, ?, NOW())',
                [(int) $order['id'], $order['status'], 'UPI payment submitted for verification', $uid]
            );
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('[Bachat PaySubmit] ' . $e->getMessage());
            flash('error', t('common.error'));
            $this->back();
        }

        $verifier = (string) (current_society()['payment_verifier'] ?? 'society_admin');
        $title = 'UPI payment submitted for verification';
        $body  = 'Order ' . $order['order_no'] . ' · ' . money((float) $order['total']);
        if ($verifier === 'super_admin') {
            Notify::superAdmins('payment', $title, $body, '/super/payments');
        } else {
            Notify::societyAdmins((int) $order['society_id'], 'payment', $title, $body, '/admin/payments');
        }
        Audit::log('payment.submitted', 'order', (int) $order['id'], ['amount' => (float) $order['total']]);

        Session::forget('_old');
        flash('success', t('orders.payment_submitted'));
        $this->redirect('/orders/' . (int) $order['id']);
    }
}
