<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

/**
 * Shared notification center (any logged-in role).
 * Residents, admins, vendors and delivery staff all read their own bell feed here.
 */
class NotificationController extends Controller
{
    /** GET /notifications — my latest 50, unread highlighted. */
    public function index(): void
    {
        $this->requireAuth();
        $uid = (int) Auth::id();

        $rows   = Database::fetchAll(
            'SELECT id, type, title, body, link, is_read, created_at
             FROM notifications
             WHERE user_id = ?
             ORDER BY id DESC
             LIMIT 50',
            [$uid]
        );
        $unread = (int) Database::scalar(
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0',
            [$uid]
        );

        $this->view('notifications/index', [
            'pageTitle' => t('notifications.title'),
            'active'    => 'notifications',
            'rows'      => $rows,
            'unread'    => $unread,
        ]);
    }

    /** POST /notifications/{id}/read — mark one as read (owner only). */
    public function read(string $id): void
    {
        $this->requireAuth();
        $nid = (int) $id;
        if ($nid > 0) {
            Database::run(
                'UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?',
                [$nid, Auth::id()]
            );
        }

        if ($this->wantsJson()) {
            $this->json(['ok' => true]);
        }
        $this->back();
    }

    /** POST /notifications/read-all — mark everything as read. */
    public function readAll(): void
    {
        $this->requireAuth();
        Database::run(
            'UPDATE notifications SET is_read = 1 WHERE user_id = ?',
            [Auth::id()]
        );

        if ($this->wantsJson()) {
            $this->json(['ok' => true]);
        }
        flash('success', t('notifications.all_marked'));
        $this->redirect('/notifications');
    }

    /* ---------------- internals ---------------- */

    private function wantsJson(): bool
    {
        return (string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }
}
