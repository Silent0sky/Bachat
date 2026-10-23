<?php

declare(strict_types=1);

namespace App\Core;

final class Audit
{
    private const SENSITIVE_KEYS = ['password', 'password_confirmation', '_token', 'token', 'utr', 'card_number', 'otp', 'key_secret'];

    public static function log(string $action, ?string $entityType = null, ?int $entityId = null, array $details = []): void
    {
        try {
            Database::run(
                'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip, created_at)
                 VALUES (?,?,?,?,?,?,NOW())',
                [
                    Auth::id(),
                    $action,
                    $entityType,
                    $entityId,
                    json_encode(self::scrub($details), JSON_UNESCAPED_UNICODE),
                    $_SERVER['REMOTE_ADDR'] ?? null,
                ]
            );
        } catch (\Throwable $e) {
            error_log('[Bachat Audit] ' . $e->getMessage()); // never break the app because of audit failures
        }
    }

    private static function scrub(array $data): array
    {
        foreach ($data as $k => $v) {
            if (in_array(strtolower((string) $k), self::SENSITIVE_KEYS, true)) {
                $data[$k] = '[redacted]';
            } elseif (is_array($v)) {
                $data[$k] = self::scrub($v);
            }
        }
        return $data;
    }
}
