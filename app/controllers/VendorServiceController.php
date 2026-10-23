<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\Notify;

/**
 * Vendor service operations (Task 4-b / Agent C).
 * Scope: bookings.vendor_id = Auth::id().
 * Routes: GET /vendor/bookings|reviews, POST /vendor/bookings/{id}/status
 */
final class VendorServiceController extends Controller
{
    /* ==================================================================
     *  GET /vendor/bookings — jobs for my team
     * ================================================================== */
    public function bookings(): void
    {
        $this->requireRole(['vendor']);
        $uid = (int) Auth::id();

        $bookings = Database::fetchAll(
            'SELECT b.*, s.name_en, s.name_hi, s.name_mr, s.category,
                    u.name AS resident_name, u.phone AS resident_phone,
                    soc.name AS society_name, sc.title AS campaign_title
             FROM bookings b
             JOIN services s ON s.id = b.service_id
             LEFT JOIN users u ON u.id = b.user_id
             LEFT JOIN societies soc ON soc.id = b.society_id
             LEFT JOIN service_campaigns sc ON sc.id = b.service_campaign_id
             WHERE b.vendor_id = ?
             ORDER BY FIELD(b.status, "pending", "confirmed", "assigned", "in_progress", "rescheduled", "completed", "cancelled"),
                      b.slot_at ASC',
            [$uid]
        );

        $stats = [
            'pending'   => count(array_filter($bookings, fn ($b) => $b['status'] === 'pending')),
            'active'    => count(array_filter($bookings, fn ($b) => in_array($b['status'], ['confirmed', 'assigned', 'in_progress', 'rescheduled'], true))),
            'completed' => count(array_filter($bookings, fn ($b) => $b['status'] === 'completed')),
        ];

        $this->view('vendor/bookings', [
            'active'    => 'vendor-bookings',
            'pageTitle' => t('bookings.vendor_title'),
            'bookings'  => $bookings,
            'stats'     => $stats,
        ]);
    }

    /* ==================================================================
     *  POST /vendor/bookings/{id}/status — accept | start | complete (OTP) | cancel
     * ================================================================== */
    public function bookingStatus(string $id): void
    {
        $this->requireRole(['vendor']);
        $uid = (int) Auth::id();

        $booking = Database::fetch(
            'SELECT b.*, s.name_en FROM bookings b JOIN services s ON s.id = b.service_id
             WHERE b.id = ? AND b.vendor_id = ? LIMIT 1',
            [(int) $id, $uid]
        );
        if (!$booking) {
            flash('error', t('bookings.vendor_not_yours'));
            $this->redirect('/vendor/bookings');
        }

        $action = (string) $this->post('action', '');
        $status = $booking['status'];
        $notifyBody = '';

        switch ($action) {
            case 'accept':
                if ($status !== 'pending') {
                    flash('error', t('bookings.vendor_invalid_action'));
                    $this->redirect('/vendor/bookings');
                }
                Database::run('UPDATE bookings SET status = "confirmed", updated_at = NOW() WHERE id = ?', [$booking['id']]);
                $notifyBody = 'Booking ' . $booking['booking_no'] . ' (' . $booking['name_en'] . ') was accepted by the vendor.';
                Audit::log('booking.accept', 'booking', (int) $booking['id'], ['by' => 'vendor']);
                break;

            case 'start':
                if (!in_array($status, ['confirmed', 'assigned'], true)) {
                    flash('error', t('bookings.vendor_invalid_action'));
                    $this->redirect('/vendor/bookings');
                }
                Database::run('UPDATE bookings SET status = "in_progress", updated_at = NOW() WHERE id = ?', [$booking['id']]);
                $notifyBody = 'Service started for booking ' . $booking['booking_no'] . ' (' . $booking['name_en'] . ').';
                Audit::log('booking.start', 'booking', (int) $booking['id'], ['by' => 'vendor']);
                break;

            case 'complete':
                if ($status !== 'in_progress') {
                    flash('error', t('bookings.vendor_invalid_action'));
                    $this->redirect('/vendor/bookings');
                }
                $otp = (string) $this->post('otp', '');
                if ($otp === '' || $booking['otp_code'] === null || !hash_equals((string) $booking['otp_code'], $otp)) {
                    flash('error', t('bookings.vendor_otp_wrong'));
                    $this->redirect('/vendor/bookings');
                }
                Database::run('UPDATE bookings SET status = "completed", updated_at = NOW() WHERE id = ?', [$booking['id']]);
                $this->recordCommission($booking);
                $notifyBody = 'Booking ' . $booking['booking_no'] . ' (' . $booking['name_en'] . ') was completed (code verified).';
                Audit::log('booking.complete', 'booking', (int) $booking['id'], ['by' => 'vendor', 'otp_verified' => true]);
                break;

            case 'cancel':
                if (!in_array($status, ['pending', 'confirmed', 'assigned'], true)) {
                    flash('error', t('bookings.vendor_invalid_action'));
                    $this->redirect('/vendor/bookings');
                }
                $reason = mb_substr((string) $this->post('reason', 'Cancelled by vendor'), 0, 255);
                Database::run(
                    'UPDATE bookings SET status = "cancelled", cancel_reason = ?, updated_at = NOW() WHERE id = ?',
                    [$reason, $booking['id']]
                );
                $notifyBody = 'Booking ' . $booking['booking_no'] . ' (' . $booking['name_en'] . ') was cancelled by the vendor. Reason: ' . $reason;
                Audit::log('booking.vendor_cancel', 'booking', (int) $booking['id'], ['reason' => $reason]);
                break;

            default:
                flash('error', t('bookings.vendor_invalid_action'));
                $this->redirect('/vendor/bookings');
        }

        Notify::user((int) $booking['user_id'], 'booking', 'Booking update — ' . $booking['booking_no'], $notifyBody, '/bookings');
        Notify::societyAdmins((int) $booking['society_id'], 'booking', 'Booking update — ' . $booking['booking_no'], $notifyBody, '/admin/bookings');

        flash('success', $action === 'cancel' ? t('bookings.vendor_cancelled') : t('bookings.vendor_updated'));
        $this->redirect('/vendor/bookings');
    }

    /* ==================================================================
     *  GET /vendor/reviews — read-only reviews on my services / my vendor profile
     * ================================================================== */
    public function reviews(): void
    {
        $this->requireRole(['vendor']);
        $uid = (int) Auth::id();

        $reviews = Database::fetchAll(
            'SELECT r.*, u.name AS user_name,
                    s.name_en AS service_name
             FROM reviews r
             JOIN users u ON u.id = r.user_id
             LEFT JOIN services s ON r.target_type = "service" AND s.id = r.target_id
             WHERE r.status IN ("pending","approved")
               AND (
                    (r.target_type = "service" AND r.target_id IN (SELECT id FROM services WHERE vendor_id = ?))
                 OR (r.target_type = "vendor" AND r.target_id = ?)
               )
             ORDER BY r.created_at DESC',
            [$uid, $uid]
        );

        $avg = 0.0;
        $approved = array_values(array_filter($reviews, fn ($r) => $r['status'] === 'approved'));
        if ($approved) {
            $avg = array_sum(array_map(fn ($r) => (int) $r['rating'], $approved)) / count($approved);
        }

        $this->view('vendor/reviews', [
            'active'    => 'vendor-reviews',
            'pageTitle' => t('bookings.vendor_reviews_title'),
            'reviews'   => $reviews,
            'avg'       => $avg,
        ]);
    }

    /* ============================ helpers ============================ */

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
