<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Services\Notify;

/**
 * Resident-facing home services + group service campaigns (Task 4-b / Agent C).
 * Routes: GET /services, GET /services/{id}, GET|POST /service-campaigns[...]
 */
final class ServiceController extends Controller
{
    /* ==================================================================
     *  GET /services — catalogue + open group deals for my society
     * ================================================================== */
    public function index(): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();
        $sid = (int) Auth::societyId();

        $cat = (string) $this->query('cat', '');

        $categories = Database::fetchAll(
            'SELECT DISTINCT category FROM services WHERE is_active = 1 ORDER BY category'
        );

        $sql = 'SELECT s.*, u.name AS vendor_name
                FROM services s
                LEFT JOIN users u ON u.id = s.vendor_id AND u.status = "active"
                WHERE s.is_active = 1';
        $params = [];
        if ($cat !== '') {
            $sql .= ' AND s.category = ?';
            $params[] = $cat;
        }
        $sql .= ' ORDER BY s.category ASC, s.base_price ASC';
        $services = Database::fetchAll($sql, $params);

        // Open group campaigns for my society (interest / pricing / confirm stages)
        $campaigns = $this->openCampaigns($sid);

        $this->view('services/index', [
            'active'      => 'services',
            'pageTitle'   => t('services.title'),
            'services'    => $services,
            'categories'  => array_column($categories, 'category'),
            'currentCat'  => $cat,
            'campaigns'   => $campaigns,
        ]);
    }

    /* ==================================================================
     *  GET /services/{id} — detail + booking form (posts to BookingController)
     * ================================================================== */
    public function show(string $id): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();

        $service = Database::fetch(
            'SELECT s.*, u.name AS vendor_name, u.phone AS vendor_phone
             FROM services s
             LEFT JOIN users u ON u.id = s.vendor_id AND u.status = "active"
             WHERE s.id = ? AND s.is_active = 1 LIMIT 1',
            [(int) $id]
        );

        if (!$service) {
            flash('error', t('services.not_found'));
            $this->redirect('/services');
        }

        $addresses = Database::fetchAll(
            'SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC',
            [Auth::id()]
        );

        $this->view('services/show', [
            'active'    => 'services',
            'pageTitle' => t('services.book_title', ['service' => $this->serviceName($service)]),
            'service'   => $service,
            'addresses' => $addresses,
        ]);
    }

    /* ==================================================================
     *  GET /service-campaigns — all group deals of my society
     * ================================================================== */
    public function campaigns(): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();
        $sid = (int) Auth::societyId();

        $campaigns = Database::fetchAll(
            'SELECT sc.*, s.name_en, s.name_hi, s.name_mr, s.base_price, s.duration_mins,
                    u.name AS vendor_name,
                    (SELECT COUNT(*) FROM service_interests si
                      WHERE si.campaign_id = sc.id AND si.status <> "cancelled") AS participants
             FROM service_campaigns sc
             JOIN services s ON s.id = sc.service_id
             LEFT JOIN users u ON u.id = sc.vendor_id
             WHERE sc.society_id = ?
             ORDER BY FIELD(sc.status, "confirm_open", "open", "pricing", "closed", "cancelled"), sc.deadline IS NULL, sc.deadline ASC',
            [$sid]
        );

        $myInterests = $this->myInterestMap($sid);

        $this->view('services/campaigns', [
            'active'      => 'services',
            'pageTitle'   => t('services.campaigns_title'),
            'campaigns'   => $campaigns,
            'myInterests' => $myInterests,
        ]);
    }

    /* ==================================================================
     *  GET /service-campaigns/{id} — detail + interest / confirmation panel
     * ================================================================== */
    public function campaignShow(string $id): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();
        $sid = (int) Auth::societyId();
        $uid = (int) Auth::id();

        $campaign = Database::fetch(
            'SELECT sc.*, s.name_en, s.name_hi, s.name_mr, s.description AS service_description,
                    s.base_price, s.duration_mins, s.cancellation_policy, s.category,
                    u.name AS vendor_name
             FROM service_campaigns sc
             JOIN services s ON s.id = sc.service_id
             LEFT JOIN users u ON u.id = sc.vendor_id
             WHERE sc.id = ? LIMIT 1',
            [(int) $id]
        );

        if (!$campaign) {
            flash('error', t('common.not_found'));
            $this->redirect('/service-campaigns');
        }
        if ((int) $campaign['society_id'] !== $sid) {
            flash('error', t('services.society_mismatch'));
            $this->redirect('/service-campaigns');
        }

        $participants = (int) Database::scalar(
            'SELECT COUNT(*) FROM service_interests WHERE campaign_id = ? AND status <> "cancelled"',
            [$campaign['id']]
        );

        $myInterest = Database::fetch(
            'SELECT * FROM service_interests WHERE campaign_id = ? AND user_id = ? LIMIT 1',
            [$campaign['id'], $uid]
        );

        $canInterest = $campaign['status'] === 'open' && (!$myInterest || $myInterest['status'] === 'cancelled');
        $canConfirm  = $campaign['status'] === 'confirm_open'
            && $campaign['final_price'] !== null
            && $myInterest && $myInterest['status'] === 'interested';

        $this->view('services/campaign_show', [
            'active'       => 'services',
            'pageTitle'    => $campaign['title'],
            'campaign'     => $campaign,
            'participants' => $participants,
            'myInterest'   => $myInterest,
            'canInterest'  => $canInterest,
            'canConfirm'   => $canConfirm,
        ]);
    }

    /* ==================================================================
     *  POST /service-campaigns/{id}/interested — register interest
     * ================================================================== */
    public function interested(string $id): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();
        $sid = (int) Auth::societyId();
        $uid = (int) Auth::id();

        $campaign = Database::fetch(
            'SELECT * FROM service_campaigns WHERE id = ? LIMIT 1',
            [(int) $id]
        );

        if (!$campaign || (int) $campaign['society_id'] !== $sid) {
            flash('error', t('services.society_mismatch'));
            $this->redirect('/service-campaigns');
        }
        if ($campaign['status'] !== 'open') {
            flash('error', t('services.not_open'));
            $this->redirect('/service-campaigns/' . $campaign['id']);
        }

        $existing = Database::fetch(
            'SELECT * FROM service_interests WHERE campaign_id = ? AND user_id = ? LIMIT 1',
            [$campaign['id'], $uid]
        );

        if ($existing && $existing['status'] === 'interested') {
            flash('info', t('services.already_interested'));
            $this->redirect('/service-campaigns/' . $campaign['id']);
        }

        if ($existing) { // previously cancelled — flip back
            Database::run(
                'UPDATE service_interests SET status = "interested", confirmed_price = NULL WHERE id = ?',
                [$existing['id']]
            );
        } else {
            Database::run(
                'INSERT INTO service_interests (campaign_id, user_id, status, created_at) VALUES (?,?,"interested",NOW())',
                [$campaign['id'], $uid]
            );
        }

        $count = (int) Database::scalar(
            'SELECT COUNT(*) FROM service_interests WHERE campaign_id = ? AND status = "interested"',
            [$campaign['id']]
        );

        Notify::societyAdmins(
            $sid,
            'service_campaign',
            'New group service interest',
            Auth::user()['name'] . ' is interested in "' . $campaign['title'] . '" — ' . $count
            . ' interested so far (minimum ' . (int) $campaign['min_participants'] . ').',
            '/admin/service-campaigns'
        );

        flash('success', t('services.interest_registered'));
        $this->redirect('/service-campaigns/' . $campaign['id']);
    }

    /* ==================================================================
     *  POST /service-campaigns/{id}/confirm — create the confirmed booking
     * ================================================================== */
    public function confirmBooking(string $id): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();
        $sid = (int) Auth::societyId();
        $uid = (int) Auth::id();

        $campaign = Database::fetch(
            'SELECT sc.*, s.duration_mins, s.vendor_id AS service_vendor_id
             FROM service_campaigns sc
             JOIN services s ON s.id = sc.service_id
             WHERE sc.id = ? LIMIT 1',
            [(int) $id]
        );

        if (!$campaign || (int) $campaign['society_id'] !== $sid) {
            flash('error', t('services.society_mismatch'));
            $this->redirect('/service-campaigns');
        }

        $myInterest = Database::fetch(
            'SELECT * FROM service_interests WHERE campaign_id = ? AND user_id = ? LIMIT 1',
            [$campaign['id'], $uid]
        );

        $ok = $campaign['status'] === 'confirm_open'
            && $campaign['final_price'] !== null
            && $myInterest && $myInterest['status'] === 'interested'
            && $this->post('confirm') !== null;

        if (!$ok) {
            flash('error', t('services.cannot_confirm'));
            $this->redirect('/service-campaigns/' . $campaign['id']);
        }

        // Slot: campaign slot, or +7 days default
        if (!empty($campaign['slot_date'])) {
            $slotAt = $campaign['slot_date'] . ' ' . ($campaign['slot_time'] ?: '10:00:00');
        } else {
            $slotAt = date('Y-m-d H:i:s', strtotime('+7 days'));
        }

        $vendorId = $campaign['vendor_id'] !== null
            ? (int) $campaign['vendor_id']
            : ($campaign['service_vendor_id'] !== null ? (int) $campaign['service_vendor_id'] : null);

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $bookingNo = $this->uniqueBookingNo();
            Database::run(
                'INSERT INTO bookings
                    (booking_no, user_id, society_id, service_id, service_campaign_id, vendor_id,
                     slot_at, duration_mins, status, price, address_text, contact_phone, notes,
                     otp_code, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW(),NOW())',
                [
                    $bookingNo, $uid, $sid, (int) $campaign['service_id'], (int) $campaign['id'], $vendorId,
                    $slotAt, (int) $campaign['duration_mins'], 'confirmed', $campaign['final_price'],
                    $this->defaultAddressText($uid), Auth::user()['phone'],
                    'Group deal: ' . $campaign['title'],
                    $this->otp(),
                ]
            );
            $bookingId = (int) $pdo->lastInsertId();

            Database::run(
                'UPDATE service_interests SET status = "confirmed", confirmed_price = ? WHERE id = ?',
                [$campaign['final_price'], $myInterest['id']]
            );

            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            error_log('[Bachat Services] confirmBooking: ' . $e->getMessage());
            flash('error', t('common.error'));
            $this->redirect('/service-campaigns/' . $campaign['id']);
        }

        Audit::log('booking.group_confirm', 'booking', $bookingId, ['campaign_id' => (int) $campaign['id'], 'price' => (float) $campaign['final_price']]);

        $title = 'Group booking confirmed';
        $body  = Auth::user()['name'] . ' confirmed the group deal "' . $campaign['title'] . '" ('
               . money($campaign['final_price']) . ', ' . human_dt($slotAt) . ').';
        Notify::societyAdmins($sid, 'booking', $title, $body, '/admin/bookings');
        if ($vendorId) {
            Notify::user($vendorId, 'booking', $title, $body, '/vendor/bookings');
        }

        flash('success', t('services.confirmed'));
        $this->redirect('/bookings');
    }

    /* ============================ helpers ============================ */

    /** Localised service name with en fallback. */
    private function serviceName(array $row): string
    {
        $l = lang();
        return (string) ($row['name_' . $l] ?? '') !== '' ? (string) $row['name_' . $l] : (string) $row['name_en'];
    }

    private function openCampaigns(int $sid): array
    {
        return Database::fetchAll(
            'SELECT sc.*, s.name_en, s.name_hi, s.name_mr, s.base_price,
                    (SELECT COUNT(*) FROM service_interests si
                      WHERE si.campaign_id = sc.id AND si.status <> "cancelled") AS participants
             FROM service_campaigns sc
             JOIN services s ON s.id = sc.service_id
             WHERE sc.society_id = ? AND sc.status IN ("open","pricing","confirm_open")
             ORDER BY sc.deadline IS NULL, sc.deadline ASC',
            [$sid]
        );
    }

    /** campaign_id => my interest status, for the current society's campaigns. */
    private function myInterestMap(int $sid): array
    {
        $rows = Database::fetchAll(
            'SELECT si.campaign_id, si.status FROM service_interests si
             JOIN service_campaigns sc ON sc.id = si.campaign_id
             WHERE sc.society_id = ? AND si.user_id = ?',
            [$sid, Auth::id()]
        );
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r['campaign_id']] = $r['status'];
        }
        return $map;
    }

    /** Collision-safe booking number: SB + ymmdd + 4 hex chars. */
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

    /** Default address text of a user (label, line, city — pincode). */
    private function defaultAddressText(int $userId): string
    {
        $a = Database::fetch(
            'SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id DESC LIMIT 1',
            [$userId]
        );
        if (!$a) {
            return '';
        }
        return trim(($a['label'] ? $a['label'] . ': ' : '') . $a['line1']
            . ($a['city'] ? ', ' . $a['city'] : '')
            . ($a['pincode'] ? ' - ' . $a['pincode'] : ''));
    }
}
