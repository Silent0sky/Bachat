<?php

declare(strict_types=1);

namespace App\Core;

final class Mailer
{
    /**
     * Best-effort email sender. InfinityFree and most free hosts restrict mail(),
     * so in-app notifications (notifications table) are always the primary channel.
     * Configure app.mail.enabled = true together with a working mail host.
     */
    public static function send(string $to, string $subject, string $htmlBody): bool
    {
        if (!config('app.mail.enabled')) {
            return false;
        }
        $from    = config('app.mail.from_email', 'no-reply@example.com');
        $fromName = config('app.mail.from_name', 'Bachat');
        $headers = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $fromName . ' <' . $from . '>',
            'X-Mailer: Bachat',
        ]);
        try {
            return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $htmlBody, $headers);
        } catch (\Throwable $e) {
            error_log('[Bachat Mail] ' . $e->getMessage());
            return false;
        }
    }
}
