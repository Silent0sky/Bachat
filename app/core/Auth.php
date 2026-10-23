<?php

declare(strict_types=1);

namespace App\Core;

final class Auth
{
    private static ?array $user = null;

    /** Attempt login. Returns true on success; sets flash errors otherwise. */
    public static function attempt(string $email, string $password): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (RateLimiter::tooManyAttempts($email, $ip)) {
            Flash::add('error', t('auth.locked'));
            return false;
        }

        $user = Database::fetch('SELECT * FROM users WHERE email = ? LIMIT 1', [$email]);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            RateLimiter::hit($email, $ip);
            Flash::add('error', t('auth.invalid_credentials'));
            return false;
        }
        if ($user['status'] === 'suspended') {
            Flash::add('error', t('auth.suspended'));
            return false;
        }

        RateLimiter::clear($email, $ip);
        Session::regenerate();
        Session::set('user_id', (int) $user['id']);
        Session::forget('_old');
        self::$user = null; // reload

        Database::run(
            'UPDATE users SET last_login_at = NOW(), failed_logins = 0, locked_until = NULL WHERE id = ?',
            [$user['id']]
        );
        Audit::log('login', 'user', (int) $user['id']);
        return true;
    }

    public static function id(): ?int
    {
        $uid = Session::get('user_id');
        return $uid === null ? null : (int) $uid;
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        if (self::$user === null) {
            self::$user = Database::fetch(
                'SELECT id, name, email, phone, role, society_id, status, language, avatar, created_at
                 FROM users WHERE id = ? LIMIT 1',
                [self::id()]
            );
            if (self::$user && self::$user['status'] === 'suspended') {
                self::logout();
                return null;
            }
        }
        return self::$user;
    }

    public static function role(): ?string
    {
        return self::user()['role'] ?? null;
    }

    public static function isRole(string|array $roles): bool
    {
        $r = self::role();
        return $r !== null && in_array($r, (array) $roles, true);
    }

    /**
     * Society scope for the logged-in user.
     *  - super_admin: null (platform-wide)
     *  - society_admin / vendor / delivery_staff: users.society_id
     *  - resident: society of their ACTIVE membership
     */
    public static function societyId(): ?int
    {
        $u = self::user();
        if (!$u) {
            return null;
        }
        if ($u['role'] === 'super_admin') {
            return null;
        }
        if ($u['role'] === 'resident') {
            $mid = Database::scalar(
                'SELECT society_id FROM memberships WHERE user_id = ? AND status = "active" ORDER BY id DESC LIMIT 1',
                [$u['id']]
            );
            return $mid === false ? null : (int) $mid;
        }
        return $u['society_id'] !== null ? (int) $u['society_id'] : null;
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$user = null;
    }
}
