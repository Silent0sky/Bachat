<?php

declare(strict_types=1);

namespace App\Core;

final class RateLimiter
{
    public static function tooManyAttempts(string $email, string $ip, ?int $max = null, ?int $minutes = null): bool
    {
        // Toggleable via config. Defaults to false for development/testing ease
        if (!config('security.rate_limit_enabled', false)) {
            return false;
        }

        $max = $max ?? (int) config('security.max_login_tries', 10);
        $minutes = $minutes ?? (int) config('security.lock_minutes', 15);

        $row = Database::fetch(
            'SELECT COUNT(*) AS c FROM login_attempts 
             WHERE (email = ? OR ip = ?) AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [$email, $ip, $minutes]
        );

        return ((int) ($row['c'] ?? 0)) >= $max;
    }

    public static function hit(string $email, string $ip): void
    {
        Database::run(
            'INSERT INTO login_attempts (email, ip, success, created_at) VALUES (?,?,0,NOW())',
            [$email, $ip]
        );
    }

    public static function clear(string $email, string $ip): void
    {
        Database::run('DELETE FROM login_attempts WHERE email = ? OR ip = ?', [$email, $ip]);
    }
}
