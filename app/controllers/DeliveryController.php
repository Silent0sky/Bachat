<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Upload;
use App\Services\Notify;
use RuntimeException;

/**
 * Delivery staff console (Task 4-b / Agent C).
 * Scope: delivery_tasks.assigned_to = Auth::id().
 * Routes: GET /delivery/dashboard|history, POST /delivery/tasks/{id}/status|proof
 */
final class DeliveryController extends Controller
{
    private const FAIL_REASONS = ['Customer unavailable', 'Address issue', 'Refused', 'Other'];

    /* ==================================================================
     *  GET /delivery/dashboard — active tasks with stepper + OTP completion
     * ================================================================== */
    public function dashboard(): void
    {
        $this->requireRole(['delivery_staff']);
        $uid = (int) Auth::id();

        $tasks = $this->taskQuery(
            'AND t.assigned_to = ? AND t.status IN ("assigned","picked","out_for_delivery")',
            [$uid]
        );

        $stats = [
            'active'    => count($tasks),
            'delivered' => (int) Database::scalar('SELECT COUNT(*) FROM delivery_tasks WHERE assigned_to = ? AND status = "delivered"', [$uid]),
            'failed'    => (int) Database::scalar('SELECT COUNT(*) FROM delivery_tasks WHERE assigned_to = ? AND status = "failed"', [$uid]),
        ];

        $this->view('delivery/dashboard', [
            'active'    => 'delivery',
            'pageTitle' => t('delivery.title'),
            'tasks'     => $tasks,
            'stats'     => $stats,
        ]);
    }

    /* ==================================================================
     *  GET /delivery/history — delivered + failed tasks
     * ================================================================== */
    public function history(): void
    {
        $this->requireRole(['delivery_staff']);
        $uid = (int) Auth::id();

        $tasks = $this->taskQuery(
            'AND t.assigned_to = ? AND t.status IN ("delivered","failed") ORDER BY COALESCE(t.delivered_at, t.created_at) DESC LIMIT 200',
            [$uid]
        );

        $this->view('delivery/history', [
            'active'    => 'delivery',
            'pageTitle' => t('delivery.history'),
            'tasks'     => $tasks,
        ]);
    }

    /* ==================================================================
     *  POST /delivery/tasks/{id}/status — stepper + OTP-verified completion / fail
     * ================================================================== */
    public function updateStatus(string $id): void
    {
        $this->requireRole(['delivery_staff']);
        $uid = (int) Auth::id();

        $task = Database::fetch('SELECT * FROM delivery_tasks WHERE id = ? AND assigned_to = ? LIMIT 1', [(int) $id, $uid]);
        if (!$task) {
            flash('error', t('delivery.not_yours'));
            $this->redirect('/delivery/dashboard');
        }

        $status = (string) $this->post('status', '');

        if ($status === 'failed') {
            $this->failTask($task);
        } elseif (in_array($status, ['picked', 'out_for_delivery', 'delivered'], true)) {
            $this->advanceTask($task, $status);
        } else {
            flash('error', t('delivery.bad_state'));
            $this->redirect('/delivery/dashboard');
        }
    }

    /* ==================================================================
     *  POST /delivery/tasks/{id}/proof — photo proof of delivery
     * ================================================================== */
    public function uploadProof(string $id): void
    {
        $this->requireRole(['delivery_staff']);
        $uid = (int) Auth::id();

        $task = Database::fetch('SELECT * FROM delivery_tasks WHERE id = ? AND assigned_to = ? LIMIT 1', [(int) $id, $uid]);
        if (!$task) {
            flash('error', t('delivery.not_yours'));
            $this->redirect('/delivery/dashboard');
        }

        $file = $this->file('proof_image');
        if (!$file) {
            flash('error', t('delivery.photo_required'));
            $this->redirect('/delivery/dashboard');
        }

        try {
            $path = Upload::image($file, 'payments');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            $this->redirect('/delivery/dashboard');
        }

        Database::run('UPDATE delivery_tasks SET proof_image = ? WHERE id = ?', [$path, $task['id']]);
        Audit::log('delivery.proof_upload', 'delivery_task', (int) $task['id']);

        flash('success', t('delivery.proof_uploaded'));
        $this->redirect('/delivery/dashboard');
    }

    /* ============================ core transitions ============================ */

    private function advanceTask(array $task, string $next): void
    {
        // Strict stepper: assigned → picked → out_for_delivery → delivered
        $allowed = [
            'picked'           => ['assigned'],
            'out_for_delivery' => ['picked'],
            'delivered'        => ['out_for_delivery'],
        ];
        if (!in_array($task['status'], $allowed[$next], true)) {
            flash('error', t('delivery.bad_state'));
            $this->redirect('/delivery/dashboard');
        }

        $order   = $task['order_id'] ? Database::fetch('SELECT * FROM orders WHERE id = ?', [$task['order_id']]) : null;
        $booking = $task['booking_id'] ? Database::fetch('SELECT * FROM bookings WHERE id = ?', [$task['booking_id']]) : null;
        $label   = $order['order_no'] ?? ($booking['booking_no'] ?? ('Task #' . $task['id']));

        if ($next !== 'delivered') {
            Database::run('UPDATE delivery_tasks SET status = ? WHERE id = ?', [$next, $task['id']]);
            Audit::log('delivery.status', 'delivery_task', (int) $task['id'], ['status' => $next]);
            flash('success', $next === 'picked' ? t('delivery.status_picked') : t('delivery.status_ofd'));
            $this->redirect('/delivery/dashboard');
        }

        // --- delivered: OTP-gated, side effects on order/booking + COD payment ---
        $otp = (string) $this->post('otp', '');
        if ($otp === '') {
            flash('error', t('delivery.otp_required'));
            $this->redirect('/delivery/dashboard');
        }
        if ($task['otp'] === null || !hash_equals((string) $task['otp'], $otp)) {
            flash('error', t('delivery.otp_wrong'));
            $this->redirect('/delivery/dashboard');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            Database::run(
                'UPDATE delivery_tasks SET status = "delivered", delivered_at = NOW(), attempts = attempts + 1 WHERE id = ?',
                [$task['id']]
            );

            $userId = null;
            $socId  = (int) $task['society_id'];

            if ($order) {
                $userId = (int) $order['user_id'];
                Database::run(
                    'UPDATE orders SET status = "delivered", delivered_at = NOW(), updated_at = NOW() WHERE id = ?',
                    [$order['id']]
                );
                Database::run(
                    'INSERT INTO order_events (order_id, status, note, created_by, created_at) VALUES (?, "delivered", ?, NULL, NOW())',
                    [$order['id'], 'Delivered by delivery staff (OTP verified)']
                );
                // COD collection: mark the pending cash payment as verified by me
                Database::run(
                    'UPDATE payments SET status = "verified", verified_by = ?, verified_at = NOW()
                     WHERE order_id = ? AND method = "cod" AND status = "pending"',
                    [$uid, $order['id']]
                );
            }

            if ($booking) {
                $userId = (int) $booking['user_id'];
                Database::run(
                    'UPDATE bookings SET status = "completed", updated_at = NOW() WHERE id = ?',
                    [$booking['id']]
                );
                $this->recordCommission($booking);
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[Bachat Delivery] deliver: ' . $e->getMessage());
            flash('error', t('common.error'));
            $this->redirect('/delivery/dashboard');
        }

        Audit::log('delivery.delivered', 'delivery_task', (int) $task['id'], ['ref' => $label]);

        if ($userId) {
            Notify::user($userId, 'delivery', 'Delivered — ' . $label, 'Your ' . ($order ? 'order' : 'service booking') . ' ' . $label . ' has been delivered. OTP verified.', $order ? '/orders/' . $order['id'] : '/bookings');
        }
        Notify::societyAdmins((int) $task['society_id'], 'delivery', 'Delivered — ' . $label, 'Task for ' . $label . ' completed by ' . Auth::user()['name'] . ' (OTP verified).', '/admin/deliveries');

        flash('success', t('delivery.delivered_done'));
        $this->redirect('/delivery/dashboard');
    }

    private function failTask(array $task): void
    {
        $reason = (string) $this->post('fail_reason', 'Other');
        if (!in_array($reason, self::FAIL_REASONS, true)) {
            $reason = 'Other';
        }
        $note = (string) $this->post('fail_note', '');
        $full = $reason . ($note !== '' ? ' — ' . mb_substr($note, 0, 180) : '');

        Database::run(
            'UPDATE delivery_tasks SET status = "failed", attempts = attempts + 1, fail_reason = ? WHERE id = ?',
            [mb_substr($full, 0, 255), $task['id']]
        );
        Audit::log('delivery.failed', 'delivery_task', (int) $task['id'], ['reason' => $reason]);

        Notify::societyAdmins(
            (int) $task['society_id'],
            'delivery',
            'Delivery attempt failed',
            'Task for ' . ($task['order_id'] ? 'order #' . $task['order_id'] : 'booking #' . $task['booking_id'])
            . ' failed: ' . $full . ' (attempt ' . ((int) $task['attempts'] + 1) . ').',
            '/admin/deliveries'
        );

        flash('info', t('delivery.fail_done'));
        $this->redirect('/delivery/dashboard');
    }

    /* ============================ helpers ============================ */

    /** Shared join for task lists (orders + bookings + society + latest payment). */
    private function taskQuery(string $where, array $params = []): array
    {
        return Database::fetchAll(
            'SELECT t.*,
                    o.order_no, o.address_text AS order_address, o.contact_phone AS order_phone,
                    o.total AS order_total, o.user_id AS order_user_id,
                    b.booking_no, b.address_text AS booking_address, b.contact_phone AS booking_phone,
                    b.price AS booking_price, b.user_id AS booking_user_id,
                    soc.name AS society_name,
                    p.method AS pay_method, p.status AS pay_status
             FROM delivery_tasks t
             LEFT JOIN orders o    ON o.id = t.order_id
             LEFT JOIN bookings b  ON b.id = t.booking_id
             JOIN societies soc    ON soc.id = t.society_id
             LEFT JOIN payments p  ON p.id = COALESCE(
                    (SELECT MAX(p2.id) FROM payments p2 WHERE p2.order_id = t.order_id),
                    (SELECT MAX(p3.id) FROM payments p3 WHERE p3.booking_id = t.booking_id))
             WHERE 1 = 1 ' . $where,
            $params
        );
    }

    /** Society commission on a completed booking (guard: rate > 0, not already recorded). */
    private function recordCommission(array $booking): void
    {
        $society = Database::fetch(
            'SELECT id, commission_type, commission_rate FROM societies WHERE id = ? LIMIT 1',
            [$booking['society_id']]
        );
        if (!$society || (float) $society['commission_rate'] <= 0) {
            return;
        }
        $exists = Database::scalar('SELECT COUNT(*) FROM commissions WHERE booking_id = ?', [$booking['id']]);
        if ((int) $exists > 0) {
            return;
        }
        $rate   = (float) $society['commission_rate'];
        $amount = $society['commission_type'] === 'fixed' ? $rate : round(((float) $booking['price']) * $rate / 100, 2);
        Database::run(
            'INSERT INTO commissions (order_id, booking_id, society_id, vendor_id, type, rate, amount, status, created_at)
             VALUES (NULL, ?, ?, ?, ?, ?, ?, "due", NOW())',
            [$booking['id'], $society['id'], $booking['vendor_id'], $society['commission_type'], $rate, $amount]
        );
    }
}
