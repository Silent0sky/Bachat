<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Mailer;

/**
 * In-app + email notification dispatcher.
 * Primary channel: notifications table (bell icon). Email is best-effort.
 */
final class Notify
{
    public static function user(int $userId, string $type, string $title, string $body = '', ?string $link = null): void
    {
        try {
            Database::run(
                'INSERT INTO notifications (user_id, type, title, body, link, is_read, created_at)
                 VALUES (?,?,?,?,?,0,NOW())',
                [$userId, $type, $title, $body, $link]
            );
            $email = Database::scalar('SELECT email FROM users WHERE id = ?', [$userId]);
            if (is_string($email) && $email !== '') {
                Mailer::send($email, $title, '<p>' . e($body) . '</p>' . ($link ? '<p><a href="' . e(url($link)) . '">Open Bachat</a></p>' : ''));
            }
        } catch (\Throwable $e) {
            error_log('[Bachat Notify] ' . $e->getMessage());
        }
    }

    /** @param int[] $userIds */
    public static function users(array $userIds, string $type, string $title, string $body = '', ?string $link = null): void
    {
        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            if ($uid > 0) {
                self::user($uid, $type, $title, $body, $link);
            }
        }
    }

    public static function societyAdmins(int $societyId, string $type, string $title, string $body = '', ?string $link = null): void
    {
        $ids = Database::fetchAll('SELECT id FROM users WHERE role = "society_admin" AND society_id = ? AND status = "active"', [$societyId]);
        self::users(array_column($ids, 'id'), $type, $title, $body, $link);
    }

    public static function superAdmins(string $type, string $title, string $body = '', ?string $link = null): void
    {
        $ids = Database::fetchAll('SELECT id FROM users WHERE role = "super_admin" AND status = "active"');
        self::users(array_column($ids, 'id'), $type, $title, $body, $link);
    }

    /** All active residents of a society. */
    public static function societyResidents(int $societyId, string $type, string $title, string $body = '', ?string $link = null): void
    {
        $ids = Database::fetchAll(
            'SELECT u.id FROM users u
             JOIN memberships m ON m.user_id = u.id AND m.status = "active"
             WHERE m.society_id = ? AND u.status = "active" AND u.role = "resident"',
            [$societyId]
        );
        self::users(array_column($ids, 'id'), $type, $title, $body, $link);
    }
}
