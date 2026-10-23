<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;

/**
 * Profile + saved addresses (Task 3 / Agent A).
 * Routes: GET|POST /profile, GET|POST /profile/addresses, POST /addresses/{id}/delete
 */
final class ProfileController extends Controller
{
    /* ==================================================================
     *  GET /profile
     * ================================================================== */
    public function show(): void
    {
        $this->requireAuth();
        $user = Auth::user();

        $membership = Database::fetch(
            'SELECT m.*, s.name AS society_name FROM memberships m
             JOIN societies s ON s.id = m.society_id
             WHERE m.user_id = ? ORDER BY m.id DESC LIMIT 1',
            [Auth::id()]
        );

        $this->view('profile/show', [
            'pageTitle'  => t('profile.title'),
            'active'     => 'profile',
            'membership' => $membership,
        ]);
    }

    /* ==================================================================
     *  POST /profile
     * ================================================================== */
    public function update(): void
    {
        $this->requireAuth();
        $uid = (int) Auth::id();

        $rules = [
            'name'     => 'required|min:2|max:120',
            'phone'    => 'max:20',
            'language' => 'required|in:en,hi,mr',
        ];
        $newPw = (string) ($this->post('password', '') ?? '');
        if ($newPw !== '') {
            $rules['password']          = 'required|min:6|same:password_confirmation';
            $rules['current_password']  = 'required';
        }
        $errors = $this->validate($rules);
        if ($errors) {
            flash('error', (string) reset($errors));
            $this->keepOld();
            $this->back();
        }

        if ($newPw !== '') {
            $hash = Database::scalar('SELECT password_hash FROM users WHERE id = ?', [$uid]);
            if (!is_string($hash) || !password_verify((string) $this->post('current_password', ''), $hash)) {
                flash('error', t('profile.password_wrong'));
                $this->keepOld();
                $this->back();
            }
        }

        $lang = (string) $this->post('language', 'en');
        Database::run(
            'UPDATE users SET name = ?, phone = ?, language = ?, updated_at = NOW() WHERE id = ?',
            [
                (string) $this->post('name', ''),
                (string) ($this->post('phone', '') ?? '') ?: null,
                in_array($lang, ['en', 'hi', 'mr'], true) ? $lang : 'en',
                $uid,
            ]
        );
        Session::set('lang', $lang);

        if ($newPw !== '') {
            Database::run(
                'UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?',
                [password_hash($newPw, PASSWORD_DEFAULT), $uid]
            );
            Audit::log('password.changed', 'user', $uid);
            flash('success', t('profile.password_changed'));
        } else {
            flash('success', t('profile.saved'));
        }

        Audit::log('profile.updated', 'user', $uid);
        $this->redirect('/profile');
    }

    /* ==================================================================
     *  GET /profile/addresses
     * ================================================================== */
    public function addresses(): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();

        $addrs = Database::fetchAll(
            'SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id ASC',
            [Auth::id()]
        );

        $this->view('profile/addresses', [
            'pageTitle' => t('profile.addresses'),
            'active'    => 'profile',
            'addrs'     => $addrs,
        ]);
    }

    /* ==================================================================
     *  POST /profile/addresses
     * ================================================================== */
    public function saveAddress(): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();
        $uid = (int) Auth::id();

        $errors = $this->validate([
            'label'   => 'required|max:60',
            'line1'   => 'required|max:255',
            'city'    => 'max:80',
            'pincode' => 'max:10',
        ]);
        if ($errors) {
            flash('error', (string) reset($errors));
            $this->keepOld();
            $this->back();
        }

        $isDefault = (bool) $this->post('is_default');
        $count = (int) Database::scalar('SELECT COUNT(*) FROM addresses WHERE user_id = ?', [$uid]);
        if ($count === 0) {
            $isDefault = true; // first address is always the default
        }

        Database::run(
            'INSERT INTO addresses (user_id, label, line1, city, pincode, is_default, created_at)
             VALUES (?,?,?,?,?,?,NOW())',
            [
                $uid,
                mb_substr((string) $this->post('label', ''), 0, 60),
                (string) $this->post('line1', ''),
                mb_substr((string) ($this->post('city', '') ?? ''), 0, 80) ?: null,
                mb_substr((string) ($this->post('pincode', '') ?? ''), 0, 10) ?: null,
                $isDefault ? 1 : 0,
            ]
        );
        $id = (int) Database::pdo()->lastInsertId();

        if ($isDefault) {
            Database::run('UPDATE addresses SET is_default = 0 WHERE user_id = ? AND id <> ?', [$uid, $id]);
        }

        flash('success', t('profile.address_saved'));
        $this->redirect('/profile/addresses');
    }

    /* ==================================================================
     *  POST /addresses/{id}/delete
     * ================================================================== */
    public function deleteAddress(string $id): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();
        $uid = (int) Auth::id();

        $addr = Database::fetch(
            'SELECT id, is_default FROM addresses WHERE id = ? AND user_id = ? LIMIT 1',
            [(int) $id, $uid]
        );
        if ($addr) {
            Database::run('DELETE FROM addresses WHERE id = ? AND user_id = ?', [(int) $id, $uid]);
            if ((int) $addr['is_default'] === 1) {
                // promote the oldest remaining address to default
                Database::run(
                    'UPDATE addresses SET is_default = 1 WHERE id = (
                        SELECT id FROM (SELECT id FROM addresses WHERE user_id = ? ORDER BY id ASC LIMIT 1) t
                    )',
                    [$uid]
                );
            }
        }

        flash('success', t('profile.address_deleted'));
        $this->redirect('/profile/addresses');
    }
}
