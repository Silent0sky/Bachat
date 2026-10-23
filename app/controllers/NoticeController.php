<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Upload;
use App\Services\Notify;
use RuntimeException;

/**
 * Community — society notices (announcements).
 * Resident side: read-only board + read receipts.
 * Admin side: create/pin/delete for own society (super admin: platform-wide).
 */
class NoticeController extends Controller
{
    /* ---------------- Resident ---------------- */

    /** GET /notices — live notices for my society (pinned first). */
    public function index(): void
    {
        $this->requireRole(['resident']);
        $sid = Auth::societyId();
        if ($sid === null) {
            $this->redirect('/join/pending');
        }

        // Platform-wide notices (society_id NULL) are treated as visible to every society.
        $notices = Database::fetchAll(
            "SELECT a.id, a.title, a.body, a.image, a.attachment, a.is_pinned, a.audience,
                    a.publish_at, a.expires_at, a.created_at, u.name AS author
             FROM announcements a
             LEFT JOIN users u ON u.id = a.created_by
             WHERE (a.society_id = ? OR a.society_id IS NULL)
               AND a.audience IN ('all','residents')
               AND (a.publish_at IS NULL OR a.publish_at <= NOW())
               AND (a.expires_at IS NULL OR a.expires_at >= NOW())
             ORDER BY a.is_pinned DESC, COALESCE(a.publish_at, a.created_at) DESC
             LIMIT 60",
            [$sid]
        );

        $this->view('community/notices', [
            'pageTitle' => t('notices.title'),
            'active'    => 'notices',
            'notices'   => $notices,
        ]);
    }

    /** GET /notices/{id} — full notice + read receipt. */
    public function show(string $id): void
    {
        $this->requireRole(['resident']);
        $sid = Auth::societyId();
        $aid = (int) $id;
        if ($aid <= 0 || $sid === null) {
            $this->notFound();
        }

        $notice = Database::fetch(
            "SELECT a.*, u.name AS author
             FROM announcements a
             LEFT JOIN users u ON u.id = a.created_by
             WHERE a.id = ?
               AND (a.society_id = ? OR a.society_id IS NULL)
               AND a.audience IN ('all','residents')
               AND (a.publish_at IS NULL OR a.publish_at <= NOW())
               AND (a.expires_at IS NULL OR a.expires_at >= NOW())
             LIMIT 1",
            [$aid, $sid]
        );
        if (!$notice) {
            $this->notFound();
        }

        try {
            Database::run(
                'INSERT IGNORE INTO announcement_reads (announcement_id, user_id, read_at) VALUES (?,?,NOW())',
                [$aid, Auth::id()]
            );
        } catch (\Throwable $e) {
            error_log('[Bachat Notice] read receipt: ' . $e->getMessage());
        }

        $this->view('community/notice_show', [
            'pageTitle' => $notice['title'],
            'active'    => 'notices',
            'notice'    => $notice,
        ]);
    }

    /* ---------------- Admin ---------------- */

    /** GET /admin/notices — manage list + create form. */
    public function manage(): void
    {
        $this->requireRole(['society_admin', 'super_admin']);
        $isSuper = Auth::isRole('super_admin');
        $sid     = Auth::societyId();

        $readCount = '(SELECT COUNT(*) FROM announcement_reads r WHERE r.announcement_id = a.id)';
        if ($isSuper) {
            $rows = Database::fetchAll(
                "SELECT a.*, s.name AS society_name, {$readCount} AS read_count
                 FROM announcements a
                 LEFT JOIN societies s ON s.id = a.society_id
                 ORDER BY a.is_pinned DESC, a.created_at DESC
                 LIMIT 200"
            );
            $societies = Database::fetchAll(
                'SELECT id, name FROM societies WHERE status = "active" ORDER BY name ASC'
            );
        } else {
            $rows = Database::fetchAll(
                "SELECT a.*, s.name AS society_name, {$readCount} AS read_count
                 FROM announcements a
                 LEFT JOIN societies s ON s.id = a.society_id
                 WHERE a.society_id = ?
                 ORDER BY a.is_pinned DESC, a.created_at DESC
                 LIMIT 200",
                [$sid]
            );
            $societies = [];
        }

        $this->view('admin/notices', [
            'pageTitle' => t('notices.manage_title'),
            'active'    => 'admin-notices',
            'rows'      => $rows,
            'societies' => $societies,
            'isSuper'   => $isSuper,
        ]);
    }

    /** POST /admin/notices — create a notice. */
    public function store(): void
    {
        $this->requireRole(['society_admin', 'super_admin']);
        $isSuper = Auth::isRole('super_admin');

        $errors = $this->validate([
            'title'      => 'required|max:200',
            'body'       => 'required|max:10000',
            'audience'   => 'in:all,residents,admins',
            'publish_at' => 'date',
            'expires_at' => 'date',
        ]);
        if ($errors !== []) {
            flash('error', (string) reset($errors));
            $this->keepOld();
            $this->back();
        }

        // Society scope: society_admin is locked to own society; super admin may choose or go platform-wide.
        if ($isSuper) {
            $chosen = (int) ($this->post('society_id') ?: 0);
            $sid    = $chosen > 0 ? $chosen : null;
        } else {
            $sid = Auth::societyId();
        }

        $image = null;
        $attachment = null;
        try {
            if ($f = $this->file('image')) {
                $image = Upload::image($f, 'notices');
            }
            if ($f = $this->file('attachment')) {
                $attachment = Upload::file($f, 'notices', false);
            }
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            $this->keepOld();
            $this->back();
        }

        $title      = (string) $this->post('title');
        $body       = (string) $this->post('body');
        $audience   = (string) ($this->post('audience') ?: 'all');
        $isPinned   = $this->post('is_pinned') ? 1 : 0;
        $publishAt  = $this->normalizeDt((string) $this->post('publish_at'));
        $expiresAt  = $this->normalizeDt((string) $this->post('expires_at'));

        try {
            Database::run(
                'INSERT INTO announcements (society_id, title, body, image, attachment, audience, is_pinned, publish_at, expires_at, created_by, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?,NOW())',
                [$sid, $title, $body, $image, $attachment, $audience, $isPinned, $publishAt, $expiresAt, Auth::id()]
            );
        } catch (\Throwable $e) {
            error_log('[Bachat Notice] store: ' . $e->getMessage());
            flash('error', t('common.error'));
            $this->keepOld();
            $this->back();
        }

        $noticeId = (int) Database::pdo()->lastInsertId();
        Audit::log('notice.create', 'announcement', $noticeId, [
            'title' => $title, 'audience' => $audience, 'society_id' => $sid, 'pinned' => $isPinned,
        ]);

        // Announce immediately if already live (scheduled notices are announced by cron).
        if ($publishAt === null || $publishAt <= date('Y-m-d H:i:s')) {
            $excerpt = mb_substr(trim((string) preg_replace('/\s+/u', ' ', strip_tags($body))), 0, 140);
            if ($audience === 'admins') {
                if ($sid !== null) {
                    Notify::societyAdmins($sid, 'notice', 'New notice: ' . $title, $excerpt, '/admin/notices');
                }
            } else {
                $this->notifyResidents($sid, 'notice', 'New notice: ' . $title, $excerpt, '/notices/' . $noticeId);
            }
        }

        flash('success', t('notices.published'));
        $this->redirect('/admin/notices');
    }

    /** POST /admin/notices/{id}/pin — toggle pin. */
    public function togglePin(string $id): void
    {
        $this->requireRole(['society_admin', 'super_admin']);
        $notice = $this->findScoped((int) $id);
        if (!$notice) {
            flash('error', t('common.not_found'));
            $this->redirect('/admin/notices');
        }

        Database::run('UPDATE announcements SET is_pinned = 1 - is_pinned WHERE id = ?', [$notice['id']]);
        Audit::log('notice.pin', 'announcement', (int) $notice['id'], [
            'pinned' => ((int) $notice['is_pinned']) === 0,
        ]);

        flash('success', t('common.saved'));
        $this->redirect('/admin/notices');
    }

    /** POST /admin/notices/{id}/delete */
    public function delete(string $id): void
    {
        $this->requireRole(['society_admin', 'super_admin']);
        $notice = $this->findScoped((int) $id);
        if (!$notice) {
            flash('error', t('common.not_found'));
            $this->redirect('/admin/notices');
        }

        Database::run('DELETE FROM announcements WHERE id = ?', [$notice['id']]);
        Audit::log('notice.delete', 'announcement', (int) $notice['id'], ['title' => $notice['title']]);

        flash('success', t('notices.deleted'));
        $this->redirect('/admin/notices');
    }

    /* ---------------- internals ---------------- */

    /** Fetch one announcement within the admin's scope (super admin: any). */
    private function findScoped(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        if (Auth::isRole('super_admin')) {
            return Database::fetch('SELECT * FROM announcements WHERE id = ? LIMIT 1', [$id]);
        }
        $sid = Auth::societyId();
        if ($sid === null) {
            return null;
        }
        return Database::fetch(
            'SELECT * FROM announcements WHERE id = ? AND society_id = ? LIMIT 1',
            [$id, $sid]
        );
    }

    /** Notify residents of one society — or of every active society for platform-wide notices. */
    private function notifyResidents(?int $sid, string $type, string $title, string $body, string $link): void
    {
        if ($sid !== null) {
            Notify::societyResidents($sid, $type, $title, $body, $link);
            return;
        }
        try {
            $societies = Database::fetchAll('SELECT id FROM societies WHERE status = "active"');
            foreach ($societies as $s) {
                Notify::societyResidents((int) $s['id'], $type, $title, $body, $link);
            }
        } catch (\Throwable $e) {
            error_log('[Bachat Notice] platform notify: ' . $e->getMessage());
        }
    }

    /** Accepts 'YYYY-MM-DDTHH:MM' (datetime-local) or 'YYYY-MM-DD HH:MM[:SS]'. */
    private function normalizeDt(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $ts = strtotime($value);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    private function notFound(): never
    {
        http_response_code(404);
        include APP . '/views/errors/404.php';
        exit;
    }
}
