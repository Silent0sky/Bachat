<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\Notify;

/**
 * Society admin: service campaigns + bookings (Task 4-b / Agent C).
 * Scope: everything filtered by Auth::societyId().
 * Routes: /admin/service-campaigns[...], /admin/bookings[...]
 */
final class SocietyServiceController extends Controller
{
    /* ==================================================================
     *  GET /admin/service-campaigns — list + create form
     * ================================================================== */
    public function campaigns(): void
    {
        $this->requireRole(['society_admin']);
        $sid = (int) Auth::societyId();

        $campaigns = Database::fetchAll(
            'SELECT sc.*, s.name_en, s.name_hi, s.name_mr, s.base_price,
                    u.name AS vendor_name,
                    (SELECT COUNT(*) FROM service_interests si
                      WHERE si.campaign_id = sc.id AND si.status <> "cancelled") AS participants,
                    (SELECT COUNT(*) FROM service_interests si2
                      WHERE si2.campaign_id = sc.id AND si2.status = "confirmed") AS confirmed_count
             FROM service_campaigns sc
             JOIN services s ON s.id = sc.service_id
             LEFT JOIN users u ON u.id = sc.vendor_id
             WHERE sc.society_id = ?
             ORDER BY sc.created_at DESC',
            [$sid]
        );

        $services = Database::fetchAll(
            'SELECT id, category, name_en, name_hi, name_mr, base_price, duration_mins, vendor_id
             FROM services WHERE is_active = 1 ORDER BY category, name_en'
        );

        $vendors = Database::fetchAll(
            'SELECT id, name FROM users WHERE role = "vendor" AND status = "active" ORDER BY name'
        );

        $this->view('admin/service_campaigns', [
            'active'    => 'admin-service-campaigns',
            'pageTitle' => t('bookings.campaign_admin_title'),
            'campaigns' => $campaigns,
            'services'  => $services,
            'vendors'   => $vendors,
        ]);
    }

    /* ==================================================================
     *  POST /admin/service-campaigns — create an open group deal
     * ================================================================== */
    public function create(): void
    {
        $this->requireRole(['society_admin']);
        $sid = (int) Auth::societyId();

        $serviceId   = (int) $this->post('service_id', 0);
        $title       = (string) $this->post('title', '');
        $description = (string) $this->post('description', '');
        $deadline    = str_replace('T', ' ', (string) $this->post('deadline', ''));
        $minPax      = (int) $this->post('min_participants', 0);
        $discount    = (float) $this->post('group_discount_percent', 0);
        $estimated   = (string) $this->post('estimated_price', '');
        $slotDate    = (string) $this->post('slot_date', '');
        $slotTime    = (string) $this->post('slot_time', '');
        $vendorId    = (int) $this->post('vendor_id', 0);

        $errors = $this->validate([
            'service_id'   => 'required|int',
            'title'        => 'required|max:180',
            'min_participants' => 'required|int|min:2',
            'group_discount_percent' => 'required|numeric|min:0|max:90',
        ]);
        if ($errors) {
            flash('error', implode(' ', $errors));
            $this->keepOld();
            $this->redirect('/admin/service-campaigns');
        }

        $service = Database::fetch(
            'SELECT * FROM services WHERE id = ? AND is_active = 1 LIMIT 1',
            [$serviceId]
        );
        if (!$service) {
            flash('error', t('services.not_found'));
            $this->redirect('/admin/service-campaigns');
        }

        // Optional vendor must be an active vendor
        if ($vendorId > 0) {
            $ok = Database::scalar('SELECT COUNT(*) FROM users WHERE id = ? AND role = "vendor" AND status = "active"', [$vendorId]);
            if ((int) $ok === 0) {
                $vendorId = 0;
            }
        }

        $estimatedPrice = $estimated !== '' && is_numeric($estimated)
            ? round((float) $estimated, 2)
            : round(((float) $service['base_price']) * (1 - $discount / 100), 2);

        $deadlineAt = $deadline !== '' && strtotime($deadline) !== false ? date('Y-m-d H:i:s', strtotime($deadline)) : null;
        $slotDateSql = $slotDate !== '' && strtotime($slotDate) !== false ? date('Y-m-d', strtotime($slotDate)) : null;
        $slotTimeSql = $slotTime !== '' ? $this->normalizeTime($slotTime) : null;

        Database::run(
            'INSERT INTO service_campaigns
                (society_id, service_id, title, description, deadline, min_participants,
                 group_discount_percent, estimated_price, slot_date, slot_time, vendor_id, status, created_by, created_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,"open",?,NOW())',
            [
                $sid, $serviceId, $title, $description !== '' ? mb_substr($description, 0, 1000) : null,
                $deadlineAt, max(2, $minPax), $discount, $estimatedPrice,
                $slotDateSql, $slotTimeSql, $vendorId ?: null, Auth::id(),
            ]
        );
        $campaignId = (int) Database::pdo()->lastInsertId();

        Audit::log('service_campaign.create', 'service_campaign', $campaignId, ['title' => $title, 'discount' => $discount]);

        Notify::societyResidents(
            $sid,
            'service_campaign',
            'New group service deal: ' . $title,
            $service['name_en'] . ' at ~' . money($estimatedPrice) . ' per flat (' . (float) $discount
            . '% group discount). Show your interest before ' . ($deadlineAt ? human_dt($deadlineAt) : 'the deadline') . '.',
            '/service-campaigns/' . $campaignId
        );

        flash('success', t('bookings.campaign_created'));
        $this->redirect('/admin/service-campaigns');
    }

    /* ==================================================================
     *  POST /admin/service-campaigns/{id}/finalize — announce final price
     * ================================================================== */
    public function finalize(string $id): void
    {
        $this->requireRole(['society_admin']);
        $sid = (int) Auth::societyId();

        $campaign = $this->ownCampaign((int) $id, $sid);
        if (!$campaign) {
            flash('error', t('common.not_found'));
            $this->redirect('/admin/service-campaigns');
        }
        if (!in_array($campaign['status'], ['open', 'pricing'], true)) {
            flash('error', t('bookings.campaign_bad_state'));
            $this->redirect('/admin/service-campaigns');
        }

        $finalPrice = (float) $this->post('final_price', 0);
        if ($finalPrice <= 0) {
            flash('error', t('validation.numeric', ['field' => 'Final price']));
            $this->redirect('/admin/service-campaigns');
        }

        Database::run(
            'UPDATE service_campaigns SET final_price = ?, status = "confirm_open" WHERE id = ?',
            [round($finalPrice, 2), $campaign['id']]
        );

        Audit::log('service_campaign.finalize', 'service_campaign', (int) $campaign['id'], ['final_price' => $finalPrice]);

        Notify::users(
            $this->interestedUserIds((int) $campaign['id']),
            'service_campaign',
            'Final group price ready — confirm your booking',
            '"' . $campaign['title'] . '": final price ' . money($finalPrice) . ' per participant. Confirm before slots fill up.',
            '/service-campaigns/' . $campaign['id']
        );

        flash('success', t('bookings.campaign_finalized'));
        $this->redirect('/admin/service-campaigns');
    }

    /* ==================================================================
     *  POST /admin/service-campaigns/{id}/close — confirm_open → closed
     * ================================================================== */
    public function close(string $id): void
    {
        $this->requireRole(['society_admin']);
        $sid = (int) Auth::societyId();

        $campaign = $this->ownCampaign((int) $id, $sid);
        if (!$campaign || $campaign['status'] !== 'confirm_open') {
            flash('error', t('bookings.campaign_bad_state'));
            $this->redirect('/admin/service-campaigns');
        }

        Database::run('UPDATE service_campaigns SET status = "closed" WHERE id = ?', [$campaign['id']]);
        Audit::log('service_campaign.close', 'service_campaign', (int) $campaign['id']);

        flash('success', t('bookings.campaign_closed'));
        $this->redirect('/admin/service-campaigns');
    }

    /* ==================================================================
     *  POST /admin/service-campaigns/{id}/cancel — open/pricing → cancelled
     * ================================================================== */
    public function cancel(string $id): void
    {
        $this->requireRole(['society_admin']);
        $sid = (int) Auth::societyId();

        $campaign = $this->ownCampaign((int) $id, $sid);
        if (!$campaign || !in_array($campaign['status'], ['open', 'pricing'], true)) {
            flash('error', t('bookings.campaign_bad_state'));
            $this->redirect('/admin/service-campaigns');
        }

        Database::run('UPDATE service_campaigns SET status = "cancelled" WHERE id = ?', [$campaign['id']]);
        Audit::log('service_campaign.cancel', 'service_campaign', (int) $campaign['id']);

        Notify::users(
            $this->interestedUserIds((int) $campaign['id']),
            'service_campaign',
            'Group deal cancelled',
            'The group deal "' . $campaign['title'] . '" was cancelled by your society admin. No payment is due.',
            '/service-campaigns'
        );

        flash('success', t('bookings.campaign_cancelled'));
        $this->redirect('/admin/service-campaigns');
    }

    /* ==================================================================
     *  GET /admin/bookings — society bookings (+ status filter)
     * ================================================================== */
    public function bookings(): void
    {
        $this->requireRole(['society_admin']);
        $sid = (int) Auth::societyId();

        $filter = (string) $this->query('status', '');
        $allowed = ['pending', 'confirmed', 'assigned', 'in_progress', 'completed', 'cancelled', 'rescheduled'];

        $sql = 'SELECT b.*, s.name_en, s.name_hi, s.name_mr,
                       ru.name AS resident_name, ru.phone AS resident_phone,
                       v.name AS vendor_name,
                       sc.title AS campaign_title
                FROM bookings b
                JOIN services s ON s.id = b.service_id
                LEFT JOIN users ru ON ru.id = b.user_id
                LEFT JOIN users v ON v.id = b.vendor_id
                LEFT JOIN service_campaigns sc ON sc.id = b.service_campaign_id
                WHERE b.society_id = ?';
        $params = [$sid];
        if (in_array($filter, $allowed, true)) {
            $sql .= ' AND b.status = ?';
            $params[] = $filter;
        }
        $sql .= ' ORDER BY FIELD(b.status, "pending", "confirmed", "assigned", "in_progress", "rescheduled", "completed", "cancelled"), b.slot_at ASC';
        $bookings = Database::fetchAll($sql, $params);

        $vendors = Database::fetchAll('SELECT id, name FROM users WHERE role = "vendor" AND status = "active" ORDER BY name');
        $staff   = Database::fetchAll(
            'SELECT id, name FROM users WHERE role = "delivery_staff" AND society_id = ? AND status = "active" ORDER BY name',
            [$sid]
        );

        $counts = [];
        foreach (Database::fetchAll(
            'SELECT status, COUNT(*) AS n FROM bookings WHERE society_id = ? GROUP BY status',
            [$sid]
        ) as $row) {
            $counts[$row['status']] = (int) $row['n'];
        }

        $this->view('admin/bookings', [
            'active'    => 'admin-bookings',
            'pageTitle' => t('bookings.admin_title'),
            'bookings'  => $bookings,
            'vendors'   => $vendors,
            'staff'     => $staff,
            'filter'    => $filter,
            'counts'    => $counts,
        ]);
    }

    /* ==================================================================
     *  POST /admin/bookings/{id}/assign — vendor OR delivery staff
     * ================================================================== */
    public function assign(string $id): void
    {
        $this->requireRole(['society_admin']);
        $sid = (int) Auth::societyId();

        $booking = Database::fetch(
            'SELECT b.*, s.name_en FROM bookings b JOIN services s ON s.id = b.service_id
             WHERE b.id = ? AND b.society_id = ? LIMIT 1',
            [(int) $id, $sid]
        );
        if (!$booking) {
            flash('error', t('common.not_found'));
            $this->redirect('/admin/bookings');
        }
        if (!in_array($booking['status'], ['pending', 'confirmed', 'assigned'], true)) {
            flash('error', t('bookings.admin_invalid_transition'));
            $this->redirect('/admin/bookings');
        }

        $type  = (string) $this->post('assignee_type', '');
        $aid   = (int) $this->post('assignee_id', 0);

        if ($type === 'vendor') {
            $vendor = Database::fetch(
                'SELECT id, name FROM users WHERE id = ? AND role = "vendor" AND status = "active" LIMIT 1',
                [$aid]
            );
            if (!$vendor) {
                flash('error', t('bookings.assignee_invalid'));
                $this->redirect('/admin/bookings');
            }
            Database::run(
                'UPDATE bookings SET vendor_id = ?, status = "assigned", updated_at = NOW() WHERE id = ?',
                [$vendor['id'], $booking['id']]
            );
            Audit::log('booking.assign', 'booking', (int) $booking['id'], ['to' => 'vendor:' . $vendor['name']]);
            Notify::user(
                (int) $vendor['id'], 'booking', 'New booking assigned — ' . $booking['booking_no'],
                $booking['name_en'] . ' · ' . human_dt($booking['slot_at']) . ' · ' . $booking['address_text'],
                '/vendor/bookings'
            );
            Notify::user(
                (int) $booking['user_id'], 'booking', 'Booking assigned — ' . $booking['booking_no'],
                'Your booking was assigned to ' . $vendor['name'] . '. They will arrive at the scheduled slot.',
                '/bookings'
            );
            flash('success', t('bookings.admin_assigned'));
            $this->redirect('/admin/bookings');
        }

        if ($type === 'staff') {
            $staff = Database::fetch(
                'SELECT id, name FROM users WHERE id = ? AND role = "delivery_staff" AND society_id = ? AND status = "active" LIMIT 1',
                [$aid, $sid]
            );
            if (!$staff) {
                flash('error', t('bookings.assignee_invalid'));
                $this->redirect('/admin/bookings');
            }

            $pdo = Database::pdo();
            $pdo->beginTransaction();
            try {
                // retire any previous active task for this booking (reassign case)
                Database::run(
                    'UPDATE delivery_tasks SET status = "failed", fail_reason = "Reassigned by society admin"
                     WHERE booking_id = ? AND status IN ("assigned","picked","out_for_delivery")',
                    [$booking['id']]
                );
                Database::run(
                    'INSERT INTO delivery_tasks (order_id, booking_id, society_id, assigned_to, status, otp, attempts, created_at)
                     VALUES (NULL, ?, ?, ?, "assigned", ?, 0, NOW())',
                    [$booking['id'], $sid, $staff['id'], $this->otp()]
                );
                Database::run(
                    'UPDATE bookings SET status = "assigned", updated_at = NOW() WHERE id = ?',
                    [$booking['id']]
                );
                $pdo->commit();
            } catch (\Throwable $e) {
                $pdo->rollBack();
                error_log('[Bachat AdminServices] assign: ' . $e->getMessage());
                flash('error', t('common.error'));
                $this->redirect('/admin/bookings');
            }

            Audit::log('booking.assign', 'booking', (int) $booking['id'], ['to' => 'staff:' . $staff['name']]);
            Notify::user(
                (int) $staff['id'], 'booking', 'New service task — ' . $booking['booking_no'],
                $booking['name_en'] . ' · ' . human_dt($booking['slot_at']) . ' · ' . $booking['address_text'],
                '/delivery/dashboard'
            );
            Notify::user(
                (int) $booking['user_id'], 'booking', 'Booking assigned — ' . $booking['booking_no'],
                'Your booking was assigned to our society staff (' . $staff['name'] . ').',
                '/bookings'
            );
            flash('success', t('bookings.admin_assigned'));
            $this->redirect('/admin/bookings');
        }

        flash('error', t('bookings.assignee_invalid'));
        $this->redirect('/admin/bookings');
    }

    /* ==================================================================
     *  POST /admin/bookings/{id}/status — confirm | in_progress | completed | cancelled
     * ================================================================== */
    public function updateStatus(string $id): void
    {
        $this->requireRole(['society_admin']);
        $sid = (int) Auth::societyId();

        $booking = Database::fetch(
            'SELECT b.*, s.name_en FROM bookings b JOIN services s ON s.id = b.service_id
             WHERE b.id = ? AND b.society_id = ? LIMIT 1',
            [(int) $id, $sid]
        );
        if (!$booking) {
            flash('error', t('common.not_found'));
            $this->redirect('/admin/bookings');
        }

        $status = (string) $this->post('status', '');
        $from   = $booking['status'];

        $allowedFrom = [
            'confirmed'   => ['pending'],
            'in_progress' => ['confirmed', 'assigned'],
            'completed'   => ['in_progress', 'assigned', 'confirmed'],
            'cancelled'   => ['pending', 'confirmed', 'assigned', 'in_progress'],
        ];

        if (!isset($allowedFrom[$status]) || !in_array($from, $allowedFrom[$status], true)) {
            flash('error', t('bookings.admin_invalid_transition'));
            $this->redirect('/admin/bookings');
        }

        if ($status === 'cancelled') {
            $reason = mb_substr((string) $this->post('reason', 'Cancelled by society admin'), 0, 255);
            Database::run(
                'UPDATE bookings SET status = "cancelled", cancel_reason = ?, updated_at = NOW() WHERE id = ?',
                [$reason, $booking['id']]
            );
            Notify::user(
                (int) $booking['user_id'], 'booking', 'Booking cancelled — ' . $booking['booking_no'],
                'Your booking was cancelled by the society admin. Reason: ' . $reason,
                '/bookings'
            );
        } else {
            Database::run('UPDATE bookings SET status = ?, updated_at = NOW() WHERE id = ?', [$status, $booking['id']]);

            if ($status === 'completed') {
                $this->recordCommission($booking);
            }

            $lines = [
                'confirmed'   => 'Your booking was confirmed.',
                'in_progress' => 'Service is now in progress.',
                'completed'   => 'Your booking was marked completed. Thanks for using Bachat!',
            ];
            Notify::user(
                (int) $booking['user_id'], 'booking', 'Booking update — ' . $booking['booking_no'],
                $lines[$status] ?? 'Status updated to ' . $status . '.',
                '/bookings'
            );
        }

        Audit::log('booking.admin_status', 'booking', (int) $booking['id'], ['from' => $from, 'to' => $status]);

        flash('success', t('bookings.admin_status_updated')
            . ($status === 'completed' ? ' ' . t('bookings.commission_note') : ''));
        $this->redirect('/admin/bookings');
    }

    /* ============================ helpers ============================ */

    private function ownCampaign(int $id, int $sid): ?array
    {
        return Database::fetch(
            'SELECT * FROM service_campaigns WHERE id = ? AND society_id = ? LIMIT 1',
            [$id, $sid]
        );
    }

    /** @return int[] user ids with active interest in a campaign */
    private function interestedUserIds(int $campaignId): array
    {
        $rows = Database::fetchAll(
            'SELECT user_id FROM service_interests WHERE campaign_id = ? AND status = "interested"',
            [$campaignId]
        );
        return array_map('intval', array_column($rows, 'user_id'));
    }

    /** H:i[:s] → H:i:s */
    private function normalizeTime(string $time): ?string
    {
        $t = strtotime($time);
        return $t === false ? null : date('H:i:s', $t);
    }

    private function otp(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
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
