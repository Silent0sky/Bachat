<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Lang;

/**
 * Scheduled maintenance jobs (driven by cron.php):
 *  - Close/extend/failed bulk campaigns past their deadline
 *  - Move service campaigns to "pricing" after deadline
 *  - Expire subscriptions
 *  - Expire notices
 *  - Fire shopping reminders (weekly/monthly)
 */
final class CronService
{
    public static function runAll(): array
    {
        Lang::boot();
        return [
            'campaigns'      => self::processCampaigns(),
            'serviceCamps'   => self::processServiceCampaigns(),
            'subscriptions'  => self::expireSubscriptions(),
            'notices'        => self::expiredNotices(),
            'reminders'      => self::fireReminders(),
        ];
    }

    private static function processCampaigns(): int
    {
        $past = Database::fetchAll(
            "SELECT c.* FROM campaigns c
             WHERE c.status IN ('active','extended') AND c.deadline IS NOT NULL AND c.deadline <= NOW()"
        );
        $changed = 0;
        foreach ($past as $c) {
            $aggr = (float) Database::scalar(
                'SELECT COALESCE(SUM(aggregated_qty),0) FROM campaign_items WHERE campaign_id = ?',
                [$c['id']]
            );
            $minAny = (float) Database::scalar(
                'SELECT COALESCE(SUM(min_qty),0) FROM campaign_items WHERE campaign_id = ?',
                [$c['id']]
            );
            $target = (float) Database::scalar(
                'SELECT COALESCE(SUM(target_qty),0) FROM campaign_items WHERE campaign_id = ?',
                [$c['id']]
            );
            $status = $aggr >= $target && $target > 0 ? 'closed' : ($aggr >= $minAny && $minAny > 0 ? 'closed' : 'failed');
            Database::run('UPDATE campaigns SET status = ? WHERE id = ?', [$status, $c['id']]);
            $changed++;

            $title = $c['title'];
            if ($status === 'closed') {
                $ids = Database::fetchAll(
                    'SELECT user_id FROM campaign_interests WHERE campaign_id = ? AND status = "interested"',
                    [$c['id']]
                );
                foreach ($ids as $r) {
                    Notify::user((int) $r['user_id'], 'campaign', 'Bulk campaign ready for confirmation',
                        'Final prices are being confirmed for "' . $title . '". Review and confirm your quantities.', '/campaigns/' . $c['id']);
                }
            } else {
                $ids = Database::fetchAll(
                    'SELECT user_id FROM campaign_interests WHERE campaign_id = ? AND status = "interested"',
                    [$c['id']]
                );
                foreach ($ids as $r) {
                    Notify::user((int) $r['user_id'], 'campaign', 'Bulk campaign did not meet its target',
                        'The campaign "' . $title . '" closed without reaching the minimum quantity. No payment is due — nothing was booked.', '/campaigns');
                }
                Notify::superAdmins('campaign', 'Campaign failed target', '"' . $title . '" closed as failed.', '/super/campaigns');
            }
        }
        return $changed;
    }

    private static function processServiceCampaigns(): int
    {
        $past = Database::fetchAll(
            "SELECT * FROM service_campaigns WHERE status = 'open' AND deadline IS NOT NULL AND deadline <= NOW()"
        );
        foreach ($past as $sc) {
            Database::run('UPDATE service_campaigns SET status = "pricing" WHERE id = ?', [$sc['id']]);
            Notify::societyAdmins((int) $sc['society_id'], 'service_campaign', 'Service campaign ready for pricing',
                '"' . $sc['title'] . '" reached its deadline. Confirm the final price with the vendor so residents can confirm bookings.',
                '/admin/service-campaigns');
        }
        return count($past);
    }

    private static function expireSubscriptions(): int
    {
        $rows = Database::fetchAll(
            "SELECT s.*, soc.name AS society_name FROM subscriptions s JOIN societies soc ON soc.id = s.society_id
             WHERE s.status = 'active' AND s.ends_at IS NOT NULL AND s.ends_at < CURDATE()"
        );
        foreach ($rows as $s) {
            Database::run("UPDATE subscriptions SET status = 'expired' WHERE id = ?", [$s['id']]);
            Notify::societyAdmins((int) $s['society_id'], 'subscription', 'Bachat subscription expired',
                'Your society subscription has expired. Renew to keep bulk campaigns and premium features active.', '/admin/settings');
            Notify::superAdmins('subscription', 'Subscription expired', $s['society_name'] . "'s subscription expired.", '/super/subscriptions');
        }
        return count($rows);
    }

    private static function expiredNotices(): int
    {
        Database::run('UPDATE announcements SET is_pinned = 0 WHERE expires_at IS NOT NULL AND expires_at < NOW()');
        return 0;
    }

    private static function fireReminders(): int
    {
        $due = Database::fetchAll(
            'SELECT r.*, u.name AS user_name FROM reminders r JOIN users u ON u.id = r.user_id
             WHERE r.is_active = 1 AND r.next_run <= CURDATE()'
        );
        foreach ($due as $r) {
            $link = $r['list_id'] ? '/lists' : '/shop';
            Notify::user((int) $r['user_id'], 'reminder', 'Shopping reminder: ' . $r['title'],
                'This is your ' . $r['frequency'] . ' reminder from Bachat.', $link);
            $nextDate = date('Y-m-d', strtotime($r['next_run'] . ' ' . ($r['frequency'] === 'weekly' ? '+7 days' : '+1 month')));
            Database::run('UPDATE reminders SET next_run = ? WHERE id = ?', [$nextDate, $r['id']]);
        }
        return count($due);
    }
}
