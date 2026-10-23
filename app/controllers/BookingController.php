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
 * Resident bookings (Task 4-b / Agent C).
 * Routes: POST /services/{id}/book, GET /bookings, POST /bookings/{id}/cancel|reschedule|pay
 */
final class BookingController extends Controller
{
    /* ==================================================================
     *  POST /services/{id}/book — create a pending booking from the service page
     * ================================================================== */
    public function book(string $id): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();
        $sid = (int) Auth::societyId();
        $uid = (int) Auth::id();

        $service = Database::fetch(
            'SELECT * FROM services WHERE id = ? AND is_active = 1 LIMIT 1',
            [(int) $id]
        );
        if (!$service) {
            flash('error', t('services.not_found'));
            $this->redirect('/services');
        }

        $date   = (string) $this->post('slot_date', '');
        $time   = (string) $this->post('slot_time', '');
        $addrId = (int) $this->post('address_id', 0);
        $custom = (string) $this->post('address_text', '');
        $phone  = (string) $this->post('contact_phone', '');
        $notes  = (string) $this->post('notes', '');

        $this->keepOld();

        $errors = $this->validate([
            'slot_date'     => 'required|date',
            'slot_time'     => 'required',
            'contact_phone' => 'required|min:6|max:20',
        ]);
        if ($errors) {
            flash('error', implode(' ', $errors));
            $this->redirect('/services/' . $service['id']);
        }

        $slotTs = strtotime($date . ' ' . $time);
        if ($slotTs === false || $slotTs <= time()) {
            flash('error', t('bookings.invalid_slot'));
            $this->redirect('/services/' . $service['id']);
        }

        // Address: saved address wins, otherwise custom text. Never trust the client label alone.
        $addressText = '';
        if ($addrId > 0) {
            $a = Database::fetch(
                'SELECT * FROM addresses WHERE id = ? AND user_id = ? LIMIT 1',
                [$addrId, $uid]
            );
            if ($a) {
                $addressText = trim(($a['label'] ? $a['label'] . ': ' : '') . $a['line1']
                    . ($a['city'] ? ', ' . $a['city'] : '')
                    . ($a['pincode'] ? ' - ' . $a['pincode'] : ''));
            }
        }
        if ($addressText === '' && $custom !== '') {
            $addressText = mb_substr($custom, 0, 400);
        }
        if ($addressText === '') {
            flash('error', t('bookings.address_required'));
            $this->redirect('/services/' . $service['id']);
        }

        $slotAt = date('Y-m-d H:i:s', $slotTs);
        $vendorId = $service['vendor_id'] !== null ? (int) $service['vendor_id'] : null;

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $bookingNo = $this->uniqueBookingNo();
            Database::run(
                'INSERT INTO bookings
                    (booking_no, user_id, society_id, service_id, vendor_id, slot_at, duration_mins,
                     status, price, address_text, contact_phone, notes, otp_code, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())',
                [
                    $bookingNo, $uid, $sid, (int) $service['id'], $vendorId, $slotAt,
                    (int) $service['duration_mins'], 'pending', $service['base_price'],
                    $addressText, $phone, $notes !== '' ? mb_substr($notes, 0, 400) : null,
                    $this->otp(),
                ]
            );
            $bookingId = (int) $pdo->lastInsertId();
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[Bachat Bookings] book: ' . $e->getMessage());
            flash('error', t('common.error'));
            $this->redirect('/services/' . $service['id']);
        }

        Audit::log('booking.create', 'booking', $bookingId, ['service_id' => (int) $service['id'], 'slot' => $slotAt]);
        Session::forget('_old');

        $title = 'New service booking';
        $body  = Auth::user()['name'] . ' booked "' . $service['name_en'] . '" for '
               . human_dt($slotAt) . ' (' . money($service['base_price']) . '). Booking ' . $bookingNo . '.';
        Notify::societyAdmins($sid, 'booking', $title, $body, '/admin/bookings');
        if ($vendorId) {
            Notify::user($vendorId, 'booking', $title, $body, '/vendor/bookings');
        }

        flash('success', t('services.booked'));
        $this->redirect('/bookings');
    }

    /* ==================================================================
     *  GET /bookings — my bookings
     * ================================================================== */
    public function index(): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();

        $bookings = Database::fetchAll(
            'SELECT b.*, s.name_en, s.name_hi, s.name_mr, s.category,
                    u.name AS vendor_name,
                    p.status AS pay_status, p.id AS pay_id, p.utr AS pay_utr, p.proof_image AS pay_proof,
                    sc.title AS campaign_title
             FROM bookings b
             JOIN services s ON s.id = b.service_id
             LEFT JOIN users u ON u.id = b.vendor_id
             LEFT JOIN payments p ON p.id = (SELECT MAX(p2.id) FROM payments p2 WHERE p2.booking_id = b.id)
             LEFT JOIN service_campaigns sc ON sc.id = b.service_campaign_id
             WHERE b.user_id = ?
             ORDER BY b.created_at DESC',
            [Auth::id()]
        );

        $society = current_society();

        $this->view('bookings/index', [
            'active'    => 'bookings',
            'pageTitle' => t('bookings.title'),
            'bookings'  => $bookings,
            'upiId'     => $society['upi_id'] ?? '',
        ]);
    }

    /* ==================================================================
     *  POST /bookings/{id}/cancel
     * ================================================================== */
    public function cancel(string $id): void
    {
        $this->requireRole(['resident']);
        $uid = (int) Auth::id();

        $booking = Database::fetch(
            'SELECT b.*, s.name_en FROM bookings b JOIN services s ON s.id = b.service_id
             WHERE b.id = ? AND b.user_id = ? LIMIT 1',
            [(int) $id, $uid]
        );

        if (!$booking) {
            flash('error', t('bookings.not_yours'));
            $this->redirect('/bookings');
        }
        if (!in_array($booking['status'], ['pending', 'confirmed'], true)) {
            flash('error', t('bookings.cannot_cancel'));
            $this->redirect('/bookings');
        }

        $reason = mb_substr((string) $this->post('cancel_reason', 'Cancelled by resident'), 0, 255);

        Database::run(
            'UPDATE bookings SET status = "cancelled", cancel_reason = ?, updated_at = NOW() WHERE id = ?',
            [$reason, $booking['id']]
        );
        Audit::log('booking.cancel', 'booking', (int) $booking['id'], ['by' => 'resident']);

        $body = 'Booking ' . $booking['booking_no'] . ' (' . $booking['name_en'] . ') was cancelled by the resident.'
            . ($this->post('cancel_reason') ? ' Reason: ' . $reason : '');
        Notify::societyAdmins((int) $booking['society_id'], 'booking', 'Booking cancelled', $body, '/admin/bookings');
        if ($booking['vendor_id']) {
            Notify::user((int) $booking['vendor_id'], 'booking', 'Booking cancelled', $body, '/vendor/bookings');
        }

        flash('success', t('bookings.cancelled_done'));
        $this->redirect('/bookings');
    }

    /* ==================================================================
     *  POST /bookings/{id}/reschedule
     * ================================================================== */
    public function reschedule(string $id): void
    {
        $this->requireRole(['resident']);
        $uid = (int) Auth::id();

        $booking = Database::fetch(
            'SELECT * FROM bookings WHERE id = ? AND user_id = ? LIMIT 1',
            [(int) $id, $uid]
        );

        if (!$booking) {
            flash('error', t('bookings.not_yours'));
            $this->redirect('/bookings');
        }
        if (!in_array($booking['status'], ['pending', 'confirmed', 'assigned', 'rescheduled'], true)) {
            flash('error', t('bookings.cannot_reschedule'));
            $this->redirect('/bookings');
        }

        $date = (string) $this->post('slot_date', '');
        $time = (string) $this->post('slot_time', '');
        $ts   = strtotime($date . ' ' . $time);
        if ($date === '' || $time === '' || $ts === false || $ts <= time()) {
            flash('error', t('bookings.invalid_slot'));
            $this->redirect('/bookings');
        }

        Database::run(
            'UPDATE bookings SET slot_at = ?, status = "rescheduled", updated_at = NOW() WHERE id = ?',
            [date('Y-m-d H:i:s', $ts), $booking['id']]
        );
        Audit::log('booking.reschedule', 'booking', (int) $booking['id'], ['new_slot' => date('Y-m-d H:i', $ts)]);

        $body = 'Booking ' . $booking['booking_no'] . ' was rescheduled to ' . human_dt(date('Y-m-d H:i:s', $ts)) . '.';
        Notify::societyAdmins((int) $booking['society_id'], 'booking', 'Booking rescheduled', $body, '/admin/bookings');
        if ($booking['vendor_id']) {
            Notify::user((int) $booking['vendor_id'], 'booking', 'Booking rescheduled', $body, '/vendor/bookings');
        }

        flash('success', t('bookings.rescheduled_done'));
        $this->redirect('/bookings');
    }

    /* ==================================================================
     *  POST /bookings/{id}/pay — submit UPI payment for verification
     * ================================================================== */
    public function submitPayment(string $id): void
    {
        $this->requireRole(['resident']);
        $uid = (int) Auth::id();

        $booking = Database::fetch(
            'SELECT * FROM bookings WHERE id = ? AND user_id = ? LIMIT 1',
            [(int) $id, $uid]
        );

        if (!$booking) {
            flash('error', t('bookings.not_yours'));
            $this->redirect('/bookings');
        }
        if ($booking['status'] !== 'confirmed') {
            flash('error', t('bookings.pay_only_confirmed'));
            $this->redirect('/bookings');
        }

        $existing = Database::fetch(
            'SELECT * FROM payments WHERE booking_id = ? ORDER BY id DESC LIMIT 1',
            [$booking['id']]
        );
        if ($existing && in_array($existing['status'], ['submitted', 'verified'], true)) {
            flash('info', t('bookings.payment_awaiting'));
            $this->redirect('/bookings');
        }

        $utr = (string) $this->post('utr', '');
        if (mb_strlen($utr) < 4) {
            flash('error', t('bookings.utr_required'));
            $this->redirect('/bookings');
        }

        // Screenshot is optional but recommended
        $proof = null;
        $file  = $this->file('proof_image');
        if ($file) {
            try {
                $proof = Upload::image($file, 'payments');
            } catch (RuntimeException $e) {
                flash('error', $e->getMessage());
                $this->redirect('/bookings');
            }
        }

        if ($existing) { // a failed/pending attempt — resubmit on the same row
            Database::run(
                'UPDATE payments SET amount = ?, method = "upi", txn_ref = NULL, utr = ?, proof_image = ?,
                        status = "submitted", verified_by = NULL, verified_at = NULL WHERE id = ?',
                [$booking['price'], $utr, $proof, $existing['id']]
            );
            $paymentId = (int) $existing['id'];
        } else {
            Database::run(
                'INSERT INTO payments (order_id, booking_id, society_id, user_id, amount, method, txn_ref, utr,
                                       proof_image, status, created_at)
                 VALUES (NULL, ?, ?, ?, ?, "upi", NULL, ?, ?, "submitted", NOW())',
                [$booking['id'], $booking['society_id'], $uid, $booking['price'], $utr, $proof]
            );
            $paymentId = (int) Database::pdo()->lastInsertId();
        }

        Audit::log('booking.payment_submit', 'payment', $paymentId, ['booking_id' => (int) $booking['id']]);

        // Route to the society's configured payment verifier
        $verifier = Database::scalar('SELECT payment_verifier FROM societies WHERE id = ?', [$booking['society_id']]);
        $title = 'Service booking payment submitted';
        $body  = 'Booking ' . $booking['booking_no'] . ' — ' . money($booking['price'])
               . ' (UTR: ' . $utr . ') is awaiting your verification.';
        if ($verifier === 'super_admin') {
            Notify::superAdmins('payment', $title, $body, '/super/payments');
        } else {
            Notify::societyAdmins((int) $booking['society_id'], 'payment', $title, $body, '/admin/payments');
        }

        flash('success', t('bookings.payment_submitted'));
        $this->redirect('/bookings');
    }

    /* ============================ helpers ============================ */

    private function uniqueBookingNo(): string
    {
        do {
            $no = 'SB' . date('ymd') . strtoupper(bin2hex(random_bytes(2)));
            $exists = Database::scalar('SELECT COUNT(*) FROM bookings WHERE booking_no = ?', [$no]);
        } while ((int) $exists > 0);
        return $no;
    }

    private function otp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
