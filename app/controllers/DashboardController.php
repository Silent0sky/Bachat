<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

/**
 * Resident dashboard (Task 3 / Agent A).
 * Route: GET /dashboard — society-scoped stats, campaigns, notices, bookings.
 */
final class DashboardController extends Controller
{
    public function resident(): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();

        $uid = (int) Auth::id();
        $sid = (int) Auth::societyId();

        /* ---------- Stats ---------- */
        $stats = [
            'orders'    => (int) Database::scalar('SELECT COUNT(*) FROM orders WHERE user_id = ?', [$uid]),
            'delivered' => (int) Database::scalar('SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = "delivered"', [$uid]),
            'saved'     => round((float) Database::scalar(
                'SELECT COALESCE(SUM(discount), 0) FROM orders WHERE user_id = ? AND status <> "cancelled"',
                [$uid]
            ), 2),
            'bookings'  => 0,
        ];
        try {
            $stats['bookings'] = (int) Database::scalar(
                'SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status NOT IN ("cancelled", "completed")',
                [$uid]
            );
        } catch (\Throwable $e) {
            $stats['bookings'] = 0;
        }

        /* ---------- Recent orders (5) ---------- */
        $recentOrders = Database::fetchAll(
            'SELECT o.*,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count,
                    (SELECT p.status FROM payments p WHERE p.order_id = o.id ORDER BY p.id DESC LIMIT 1) AS pay_status
             FROM orders o WHERE o.user_id = ?
             ORDER BY o.id DESC LIMIT 5',
            [$uid]
        );

        /* ---------- Active bulk deals I participate in (3) ---------- */
        $campaigns = [];
        try {
            $campaigns = Database::fetchAll(
                'SELECT ci.campaign_id, c.id, c.title, c.deadline, ci.status AS my_status
                 FROM campaign_interests ci
                 JOIN campaigns c ON c.id = ci.campaign_id
                 WHERE ci.user_id = ? AND ci.status <> "cancelled" AND c.status IN ("active", "extended")
                 ORDER BY ci.id DESC LIMIT 3',
                [$uid]
            );
            if ($campaigns) {
                $ids = array_map(static fn (array $r): int => (int) $r['campaign_id'], $campaigns);
                $ph  = implode(',', array_fill(0, count($ids), '?'));
                $agg = Database::fetchAll(
                    "SELECT campaign_id, SUM(target_qty) AS target, SUM(aggregated_qty) AS achieved
                     FROM campaign_items WHERE campaign_id IN ($ph) GROUP BY campaign_id",
                    $ids
                );
                $byId = [];
                foreach ($agg as $a) {
                    $byId[(int) $a['campaign_id']] = $a;
                }
                foreach ($campaigns as &$c) {
                    $a = $byId[(int) $c['campaign_id']] ?? null;
                    $target   = $a ? (float) $a['target'] : 0.0;
                    $achieved = $a ? (float) $a['achieved'] : 0.0;
                    $c['target']   = $target;
                    $c['achieved'] = $achieved;
                    $c['pct']      = $target > 0 ? (int) min(100, round($achieved / $target * 100)) : 0;
                }
            }
        } catch (\Throwable $e) {
            $campaigns = [];
        }

        /* ---------- Pinned notices (3) ---------- */
        $notices = [];
        try {
            $notices = Database::fetchAll(
                'SELECT id, title, body, is_pinned, publish_at FROM announcements
                 WHERE society_id = ? AND (publish_at IS NULL OR publish_at <= NOW())
                   AND (expires_at IS NULL OR expires_at >= NOW())
                 ORDER BY is_pinned DESC, id DESC LIMIT 3',
                [$sid]
            );
        } catch (\Throwable $e) {
            $notices = [];
        }

        /* ---------- Upcoming bookings (3) ---------- */
        $bookings = [];
        try {
            $bookings = Database::fetchAll(
                'SELECT b.id, b.booking_no, b.slot_at, b.status, b.price, s.name_en AS service_name
                 FROM bookings b
                 JOIN services s ON s.id = b.service_id
                 WHERE b.user_id = ? AND b.slot_at >= NOW() AND b.status NOT IN ("cancelled", "completed")
                 ORDER BY b.slot_at ASC LIMIT 3',
                [$uid]
            );
        } catch (\Throwable $e) {
            $bookings = [];
        }

        $this->view('dashboards/resident', [
            'pageTitle'    => t('nav.dashboard'),
            'active'       => 'dashboard',
            'stats'        => $stats,
            'recentOrders' => $recentOrders,
            'campaigns'    => $campaigns,
            'notices'      => $notices,
            'bookings'     => $bookings,
        ]);
    }
}
