<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Services\Notify;
use PDOException;

/**
 * Auth + society join (Task 3 / Agent A).
 * Routes: GET|POST /login, GET|POST /join, GET /join/pending, POST /logout
 */
final class AuthController extends Controller
{
    /** Role → home route map (used on login + after guards). */
    public static function roleHome(?string $role = null): string
    {
        return match ($role ?? Auth::role()) {
            'super_admin'    => '/super/dashboard',
            'society_admin'  => '/admin/dashboard',
            'vendor'         => '/vendor/dashboard',
            'delivery_staff' => '/delivery/dashboard',
            default          => '/dashboard',
        };
    }

    /* ==================================================================
     *  GET /login
     * ================================================================== */
    public function showLogin(): void
    {
        if (Auth::check()) {
            $this->redirect(self::roleHome());
        }
        $this->view('auth/login', [
            'pageTitle' => t('auth.login_title'),
            'active'    => 'login',
        ]);
    }

    /* ==================================================================
     *  POST /login
     * ================================================================== */
    public function login(): void
    {
        $errors = $this->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);
        if ($errors) {
            flash('error', (string) reset($errors));
            $this->keepOld();
            $this->back();
        }

        $email    = strtolower((string) $this->post('email', ''));
        $password = (string) $this->post('password', '');

        if (!Auth::attempt($email, $password)) {
            // Auth::attempt() flashed its own error (invalid credentials / lock / suspended).
            $this->keepOld();
            $this->back();
        }

        $intended = Session::get('intended_url');
        Session::forget('intended_url');
        if (is_string($intended) && $intended !== '' && str_starts_with($intended, '/')) {
            $this->redirect($intended);
        }
        $this->redirect(self::roleHome());
    }

    /* ==================================================================
     *  GET /join
     * ================================================================== */
    public function showJoin(): void
    {
        if (Auth::check() && Auth::role() !== 'resident') {
            $this->redirect(self::roleHome());
        }
        $societies = Database::fetchAll(
            'SELECT id, name, slug, city, logo, banner, landing_text, contact_email, contact_phone
             FROM societies WHERE status = "active" ORDER BY name ASC'
        );
        $this->view('auth/join', [
            'pageTitle' => t('auth.join_title'),
            'active'    => 'join',
            'societies' => $societies,
        ]);
    }

    /* ==================================================================
     *  POST /join — create resident + PENDING membership (or add a new
     *  pending membership for a logged-in resident).
     * ================================================================== */
    public function join(): void
    {
        $loggedIn = Auth::check();
        if ($loggedIn && Auth::role() !== 'resident') {
            $this->redirect(self::roleHome());
        }

        $rules = [
            'society_id' => 'required|int',
            'member_as'  => 'required|in:owner,tenant',
        ];
        if (!$loggedIn) {
            $rules += [
                'name'     => 'required|min:2|max:120',
                'email'    => 'required|email|max:150',
                'phone'    => 'required|min:6|max:20',
                'password' => 'required|min:6|same:password_confirmation',
            ];
        }
        $errors = $this->validate($rules);
        if ($errors) {
            flash('error', (string) reset($errors));
            $this->keepOld();
            $this->back();
        }

        $sid = (int) $this->post('society_id', 0);
        $soc = Database::fetch(
            'SELECT id, name, default_language FROM societies WHERE id = ? AND status = "active" LIMIT 1',
            [$sid]
        );
        if (!$soc) {
            flash('error', t('auth.select_society'));
            $this->keepOld();
            $this->back();
        }

        $memberAs = (string) $this->post('member_as', 'owner');
        $block    = mb_substr((string) ($this->post('block', '') ?? ''), 0, 20);
        $flatNo   = mb_substr((string) ($this->post('flat_no', '') ?? ''), 0, 30);
        $invite   = mb_substr((string) ($this->post('invite_code', '') ?? ''), 0, 40);

        /* ---- Find-or-create the flat (by society + block + flat number) ---- */
        $flatId = null;
        if ($flatNo !== '') {
            $flat = Database::fetch(
                'SELECT id FROM flats WHERE society_id = ? AND block = ? AND flat_number = ? LIMIT 1',
                [$sid, $block, $flatNo]
            );
            if ($flat) {
                $flatId = (int) $flat['id'];
            } else {
                try {
                    Database::run(
                        'INSERT INTO flats (society_id, block, flat_number) VALUES (?,?,?)',
                        [$sid, $block, $flatNo]
                    );
                    $flatId = (int) Database::pdo()->lastInsertId();
                } catch (PDOException) {
                    // Unique race — re-read.
                    $flat = Database::fetch(
                        'SELECT id FROM flats WHERE society_id = ? AND block = ? AND flat_number = ? LIMIT 1',
                        [$sid, $block, $flatNo]
                    );
                    $flatId = $flat ? (int) $flat['id'] : null;
                }
            }
        }

        /* ---- Create user (guest) or reuse current resident ---- */
        try {
            if ($loggedIn) {
                $uid = (int) Auth::id();
            } else {
                $email  = strtolower((string) $this->post('email', ''));
                $exists = Database::scalar('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
                if ($exists) {
                    flash('error', t('auth.email_exists'));
                    $this->keepOld();
                    $this->back();
                }
                Database::run(
                    'INSERT INTO users (name, email, phone, password_hash, role, society_id, status, language, created_at, updated_at)
                     VALUES (?, ?, ?, ?, "resident", NULL, "active", ?, NOW(), NOW())',
                    [
                        (string) $this->post('name', ''),
                        $email,
                        (string) $this->post('phone', ''),
                        password_hash((string) $this->post('password', ''), PASSWORD_DEFAULT),
                        $soc['default_language'],
                    ]
                );
                $uid = (int) Database::pdo()->lastInsertId();
            }

            $note = $invite !== '' ? 'Invite code: ' . $invite : null;
            Database::run(
                'INSERT INTO memberships (user_id, society_id, flat_id, member_role, status, requested_at, note)
                 VALUES (?, ?, ?, ?, "pending", NOW(), ?)',
                [$uid, $sid, $flatId, $memberAs, $note]
            );
        } catch (PDOException $e) {
            error_log('[Bachat Join] ' . $e->getMessage());
            flash('error', t('common.error'));
            $this->keepOld();
            $this->back();
        }

        Audit::log('join.request', 'society', $sid, ['user_id' => $uid]);
        Notify::societyAdmins(
            $sid,
            'membership',
            'New membership request',
            ($this->post('name', '') ?: (string) (auth_user()['name'] ?? 'Resident')) . ' · ' . t('auth.' . $memberAs),
            '/admin/members'
        );

        if ($loggedIn) {
            flash('success', t('auth.join_logged_in'));
            $this->redirect('/join/pending');
        }
        flash('success', t('auth.join_success'));
        $this->redirect('/login');
    }

    /* ==================================================================
     *  GET /join/pending — membership review status screen
     * ================================================================== */
    public function pending(): void
    {
        if (!Auth::check()) {
            flash('error', t('auth.login_required'));
            $this->redirect('/login');
        }
        if (Auth::role() !== 'resident') {
            $this->redirect(self::roleHome());
        }

        $m = Database::fetch(
            'SELECT m.*, s.name AS society_name, s.city AS society_city, s.address AS society_address,
                    s.contact_email, s.contact_phone, s.logo AS society_logo
             FROM memberships m
             JOIN societies s ON s.id = m.society_id
             WHERE m.user_id = ?
             ORDER BY m.id DESC
             LIMIT 1',
            [Auth::id()]
        );
        if (!$m) {
            $this->redirect('/join');
        }
        if ($m['status'] === 'active') {
            $this->redirect('/dashboard');
        }

        $this->view('auth/pending', [
            'pageTitle' => t('auth.pending_title'),
            'active'    => 'join',
            'm'         => $m,
        ]);
    }

    /* ==================================================================
     *  POST /logout
     * ================================================================== */
    public function logout(): void
    {
        Auth::logout();
        // Session::destroy() wiped the session — start a fresh one so the
        // logout flash message survives the redirect.
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
            $_SESSION = [];
        }
        flash('success', t('auth.logout_done'));
        $this->redirect('/login');
    }
}
