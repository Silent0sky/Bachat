<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $cfg = config('app.session', []);
        session_name(is_string($cfg['name'] ?? null) ? $cfg['name'] : 'bachat_sid');
        session_set_cookie_params([
            'lifetime' => (int) ($cfg['lifetime'] ?? 7200),
            'path'     => '/',
            'domain'   => '',
            'secure'   => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();

        // Configurable session expiry (idle timeout).
        $lifetime = (int) ($cfg['lifetime'] ?? 7200);
        $last     = $_SESSION['_last_activity'] ?? time();
        if (time() - (int) $last > $lifetime) {
            self::destroy();
            self::start();
        }
        $_SESSION['_last_activity'] = time();

        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        } elseif (time() - (int) $_SESSION['_created'] > 86400) {
            session_regenerate_id(true);
            $_SESSION['_created'] = time();
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        session_regenerate_id(true);
    }

    public static function destroy(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }
}
