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
 * Community — support tickets.
 * Resident side: create, converse, reopen, rate. Works for any logged-in role.
 * Admin side: scoped queue (society_admin: own society + unlinked tickets; super_admin: platform-wide).
 */
class TicketController extends Controller
{
    private const CATEGORIES = ['Order issue', 'Payment', 'Service booking', 'Delivery', 'Membership', 'Suggestion', 'Other'];
    private const PRIORITIES = ['low', 'medium', 'high', 'urgent'];
    private const STATUSES   = ['open', 'in_progress', 'waiting', 'resolved', 'closed'];

    /* ---------------- Public ---------------- */

    /** GET /help/contact — public support contact info (auth optional). */
    public function contactInfo(): void
    {
        $email = '';
        $phone = '';
        try {
            $email = (string) (Database::scalar('SELECT setting_value FROM settings WHERE setting_key = ?', ['support_email']) ?: '');
            $phone = (string) (Database::scalar('SELECT setting_value FROM settings WHERE setting_key = ?', ['support_phone']) ?: '');
        } catch (\Throwable $e) {
            error_log('[Bachat Ticket] settings: ' . $e->getMessage());
        }

        $this->view('community/contact', [
            'pageTitle'    => t('tickets.contact_title'),
            'active'       => 'tickets',
            'supportEmail' => $email,
            'supportPhone' => $phone,
        ]);
    }

    /* ---------------- Resident / any user ---------------- */

    /** GET /tickets — my tickets. */
    public function index(): void
    {
        $this->requireAuth();
        $uid = (int) Auth::id();

        $tickets = Database::fetchAll(
            'SELECT t.*, COALESCE(m.last_at, t.created_at) AS last_activity
             FROM support_tickets t
             LEFT JOIN (SELECT ticket_id, MAX(created_at) AS last_at FROM ticket_messages GROUP BY ticket_id) m
                    ON m.ticket_id = t.id
             WHERE t.user_id = ?
             ORDER BY last_activity DESC
             LIMIT 100',
            [$uid]
        );

        $openCount     = 0;
        $resolvedCount = 0;
        foreach ($tickets as $tk) {
            if (in_array($tk['status'], ['resolved', 'closed'], true)) {
                $resolvedCount++;
            } else {
                $openCount++;
            }
        }

        $this->view('community/tickets', [
            'pageTitle'     => t('tickets.my_tickets'),
            'active'        => 'tickets',
            'tickets'       => $tickets,
            'openCount'     => $openCount,
            'resolvedCount' => $resolvedCount,
        ]);
    }

    /** GET /tickets/new — create form. */
    public function create(): void
    {
        $this->requireAuth();
        $this->view('community/ticket_new', [
            'pageTitle'  => t('tickets.new_ticket'),
            'active'     => 'tickets',
            'categories' => self::CATEGORIES,
            'priorities' => self::PRIORITIES,
        ]);
    }

    /** POST /tickets — persist ticket + first message atomically. */
    public function store(): void
    {
        $this->requireAuth();

        $errors = $this->validate([
            'subject'  => 'required|max:200',
            'category' => 'in:' . implode(',', self::CATEGORIES),
            'priority' => 'in:' . implode(',', self::PRIORITIES),
            'message'  => 'required|min:5|max:5000',
        ]);

        // Optional order / booking reference — must exist AND belong to the user.
        $orderNo   = (string) $this->post('order_no');
        $bookingNo = (string) $this->post('booking_no');
        $orderId   = null;
        $bookingId = null;

        if ($orderNo !== '' && $bookingNo !== '') {
            $errors['order_no'] = t('tickets.both_refs');
        }
        if ($errors === [] && $orderNo !== '') {
            $found = Database::scalar(
                'SELECT id FROM orders WHERE order_no = ? AND user_id = ? LIMIT 1',
                [$orderNo, Auth::id()]
            );
            if ($found === false) {
                $errors['order_no'] = t('tickets.invalid_ref');
            } else {
                $orderId = (int) $found;
            }
        }
        if ($errors === [] && $bookingNo !== '') {
            $found = Database::scalar(
                'SELECT id FROM bookings WHERE booking_no = ? AND user_id = ? LIMIT 1',
                [$bookingNo, Auth::id()]
            );
            if ($found === false) {
                $errors['booking_no'] = t('tickets.invalid_ref');
            } else {
                $bookingId = (int) $found;
            }
        }

        if ($errors !== []) {
            flash('error', (string) reset($errors));
            $this->keepOld();
            $this->back();
        }

        $attachment = null;
        try {
            if ($f = $this->file('attachment')) {
                $attachment = Upload::file($f, 'tickets', false); // images + pdf + txt
            }
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            $this->keepOld();
            $this->back();
        }

        $subject  = (string) $this->post('subject');
        $category = (string) $this->post('category');
        $priority = (string) $this->post('priority');
        $message  = (string) $this->post('message');
        $sid      = Auth::societyId();

        $slaHours = match ($priority) {
            'urgent' => 8,
            'high'   => 24,
            default  => 48,
        };
        $slaDueAt = date('Y-m-d H:i:s', time() + $slaHours * 3600);

        $pdo = Database::pdo();
        $ticketId = 0;
        $ticketNo = '';
        for ($attempt = 0; $attempt < 3 && $ticketId === 0; $attempt++) {
            $ticketNo = 'TK' . date('ymd') . strtoupper(bin2hex(random_bytes(2)));
            try {
                $pdo->beginTransaction();
                Database::run(
                    'INSERT INTO support_tickets
                        (ticket_no, user_id, society_id, subject, category, priority, status, order_id, booking_id, sla_due_at, created_at)
                     VALUES (?,?,?,?,?,?, "open", ?,?,?, NOW())',
                    [$ticketNo, Auth::id(), $sid, $subject, $category, $priority, $orderId, $bookingId, $slaDueAt]
                );
                $ticketId = (int) $pdo->lastInsertId();
                Database::run(
                    'INSERT INTO ticket_messages (ticket_id, user_id, message, attachment, is_staff, created_at)
                     VALUES (?,?,?,?,0, NOW())',
                    [$ticketId, Auth::id(), $message, $attachment]
                );
                $pdo->commit();
            } catch (\Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                if ($attempt === 2) {
                    error_log('[Bachat Ticket] store: ' . $e->getMessage());
                    flash('error', t('common.error'));
                    $this->keepOld();
                    $this->back();
                }
                $ticketId = 0; // retry with a fresh ticket number
            }
        }

        Audit::log('ticket.create', 'support_ticket', $ticketId, [
            'subject' => $subject, 'priority' => $priority, 'category' => $category,
        ]);

        if ($sid !== null) {
            Notify::societyAdmins($sid, 'ticket', 'New support ticket ' . $ticketNo, $subject, '/admin/tickets/' . $ticketId);
        }
        Notify::superAdmins('ticket', 'New support ticket ' . $ticketNo, $subject, '/admin/tickets/' . $ticketId);

        flash('success', t('tickets.created'));
        $this->redirect('/tickets/' . $ticketId);
    }

    /** GET /tickets/{id} — thread (owner only). */
    public function show(string $id): void
    {
        $this->requireAuth();
        $ticket = $this->findOwned((int) $id);
        if (!$ticket) {
            flash('error', t('tickets.not_found'));
            $this->redirect('/tickets');
        }

        $messages = Database::fetchAll(
            'SELECT m.*, u.name AS author
             FROM ticket_messages m
             JOIN users u ON u.id = m.user_id
             WHERE m.ticket_id = ?
             ORDER BY m.id ASC',
            [$ticket['id']]
        );

        $this->view('community/ticket_show', [
            'pageTitle' => $ticket['ticket_no'] . ' · ' . $ticket['subject'],
            'active'    => 'tickets',
            't'         => $ticket,
            'messages'  => $messages,
        ]);
    }

    /** POST /tickets/{id}/reply — resident reply. */
    public function userReply(string $id): void
    {
        $this->requireAuth();
        $ticket = $this->findOwned((int) $id);
        if (!$ticket) {
            flash('error', t('tickets.not_found'));
            $this->redirect('/tickets');
        }
        if ($ticket['status'] === 'closed') {
            flash('error', t('tickets.closed_locked'));
            $this->redirect('/tickets/' . $ticket['id']);
        }

        $errors = $this->validate(['message' => 'required|min:2|max:5000']);
        if ($errors !== []) {
            flash('error', (string) reset($errors));
            $this->back();
        }

        $attachment = null;
        try {
            if ($f = $this->file('attachment')) {
                $attachment = Upload::file($f, 'tickets', false);
            }
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            $this->back();
        }

        Database::run(
            'INSERT INTO ticket_messages (ticket_id, user_id, message, attachment, is_staff, created_at)
             VALUES (?,?,?,?,0, NOW())',
            [$ticket['id'], Auth::id(), (string) $this->post('message'), $attachment]
        );

        // A reply from the user re-activates waiting/resolved/reopened tickets.
        if (in_array($ticket['status'], ['waiting', 'resolved', 'reopened'], true)) {
            Database::run('UPDATE support_tickets SET status = "open" WHERE id = ?', [$ticket['id']]);
        }

        if (!empty($ticket['assigned_to'])) {
            Notify::user(
                (int) $ticket['assigned_to'],
                'ticket',
                'New reply on ' . $ticket['ticket_no'],
                mb_substr((string) $this->post('message'), 0, 140),
                '/admin/tickets/' . $ticket['id']
            );
        }

        flash('success', t('tickets.reply_sent'));
        $this->redirect('/tickets/' . $ticket['id']);
    }

    /** POST /tickets/{id}/reopen — resident reopens a closed ticket. */
    public function reopen(string $id): void
    {
        $this->requireAuth();
        $ticket = $this->findOwned((int) $id);
        if (!$ticket) {
            flash('error', t('tickets.not_found'));
            $this->redirect('/tickets');
        }
        if ($ticket['status'] !== 'closed') {
            flash('info', t('common.error'));
            $this->redirect('/tickets/' . $ticket['id']);
        }

        Database::run(
            'UPDATE support_tickets SET status = "reopened", closed_at = NULL WHERE id = ?',
            [$ticket['id']]
        );
        Audit::log('ticket.reopen', 'support_ticket', (int) $ticket['id']);

        $excerpt = 'Ticket reopened by resident.';
        if ($ticket['society_id']) {
            Notify::societyAdmins((int) $ticket['society_id'], 'ticket', 'Ticket reopened: ' . $ticket['ticket_no'], $excerpt, '/admin/tickets/' . $ticket['id']);
        }
        Notify::superAdmins('ticket', 'Ticket reopened: ' . $ticket['ticket_no'], $excerpt, '/admin/tickets/' . $ticket['id']);

        flash('success', t('tickets.reopened'));
        $this->redirect('/tickets/' . $ticket['id']);
    }

    /** POST /tickets/{id}/feedback — 1–5 rating after resolve/close. */
    public function feedback(string $id): void
    {
        $this->requireAuth();
        $ticket = $this->findOwned((int) $id);
        if (!$ticket) {
            flash('error', t('tickets.not_found'));
            $this->redirect('/tickets');
        }
        if (!in_array($ticket['status'], ['resolved', 'closed'], true)) {
            flash('error', t('common.error'));
            $this->redirect('/tickets/' . $ticket['id']);
        }

        $rating = (int) $this->post('rating', 0);
        if ($rating < 1 || $rating > 5) {
            flash('error', t('reviews.rating') . ' (1–5)');
            $this->redirect('/tickets/' . $ticket['id']);
        }

        Database::run('UPDATE support_tickets SET feedback_rating = ? WHERE id = ?', [$rating, $ticket['id']]);
        Audit::log('ticket.feedback', 'support_ticket', (int) $ticket['id'], ['rating' => $rating]);

        flash('success', t('tickets.feedback_thanks'));
        $this->redirect('/tickets/' . $ticket['id']);
    }

    /* ---------------- Admin ---------------- */

    /** GET /admin/tickets (also /super/tickets) — scoped queue. */
    public function adminIndex(): void
    {
        $this->requireRole(['society_admin', 'super_admin']);
        $isSuper = Auth::isRole('super_admin');
        $sid     = Auth::societyId();

        $status   = (string) $this->query('status');
        $priority = (string) $this->query('priority');

        $where  = [];
        $params = [];
        if (!$isSuper) {
            if ($sid !== null) {
                $where[] = '(t.society_id = ? OR t.society_id IS NULL)';
                $params[] = $sid;
            } else {
                $where[] = 't.society_id IS NULL';
            }
        }
        if (in_array($status, self::STATUSES, true)) {
            $where[]  = 't.status = ?';
            $params[] = $status;
        }
        if (in_array($priority, self::PRIORITIES, true)) {
            $where[]  = 't.priority = ?';
            $params[] = $priority;
        }

        $sql = 'SELECT t.*, u.name AS requester, s.name AS society_name, a.name AS assignee,
                       COALESCE(m.last_at, t.created_at) AS last_activity
                FROM support_tickets t
                JOIN users u ON u.id = t.user_id
                LEFT JOIN societies s ON s.id = t.society_id
                LEFT JOIN users a ON a.id = t.assigned_to
                LEFT JOIN (SELECT ticket_id, MAX(created_at) AS last_at FROM ticket_messages GROUP BY ticket_id) m
                       ON m.ticket_id = t.id'
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
            . " ORDER BY FIELD(t.status,'open','reopened','in_progress','waiting','resolved','closed'), t.created_at DESC
                LIMIT 200";

        $tickets = Database::fetchAll($sql, $params);

        $openCount = 0;
        $overdue   = 0;
        $now       = date('Y-m-d H:i:s');
        foreach ($tickets as $tk) {
            if (!in_array($tk['status'], ['resolved', 'closed'], true)) {
                $openCount++;
                if ($tk['sla_due_at'] && $tk['sla_due_at'] < $now) {
                    $overdue++;
                }
            }
        }

        $this->view('admin/tickets', [
            'pageTitle'  => t('tickets.staff_console'),
            'active'     => $isSuper ? 'super-tickets' : 'admin-tickets',
            'tickets'    => $tickets,
            'isSuper'    => $isSuper,
            'status'     => $status,
            'priority'   => $priority,
            'openCount'  => $openCount,
            'overdue'    => $overdue,
            'statuses'   => self::STATUSES,
            'priorities' => self::PRIORITIES,
        ]);
    }

    /** GET /admin/tickets/{id} — staff thread + controls. */
    public function adminShow(string $id): void
    {
        $this->requireRole(['society_admin', 'super_admin']);
        $ticket = $this->findScopedTicket((int) $id);
        if (!$ticket) {
            flash('error', t('tickets.not_found'));
            $this->redirect('/admin/tickets');
        }

        $messages = Database::fetchAll(
            'SELECT m.*, u.name AS author, u.role AS author_role
             FROM ticket_messages m
             JOIN users u ON u.id = m.user_id
             WHERE m.ticket_id = ?
             ORDER BY m.id ASC',
            [$ticket['id']]
        );

        // Assignees: society admins of the ticket's society + all super admins.
        if ($ticket['society_id']) {
            $assignees = Database::fetchAll(
                'SELECT id, name, role FROM users
                 WHERE status = "active" AND (role = "super_admin" OR (role = "society_admin" AND society_id = ?))
                 ORDER BY role DESC, name ASC',
                [$ticket['society_id']]
            );
        } else {
            $assignees = Database::fetchAll(
                'SELECT id, name, role FROM users WHERE status = "active" AND role = "super_admin" ORDER BY name ASC'
            );
        }

        $requester = Database::fetch(
            'SELECT id, name, email, phone, role FROM users WHERE id = ? LIMIT 1',
            [$ticket['user_id']]
        );

        $societyName = null;
        if ($ticket['society_id']) {
            $societyName = (string) (Database::scalar(
                'SELECT name FROM societies WHERE id = ? LIMIT 1',
                [$ticket['society_id']]
            ) ?: '');
        }

        $assigneeName = null;
        if (!empty($ticket['assigned_to'])) {
            $assigneeName = (string) (Database::scalar(
                'SELECT name FROM users WHERE id = ? LIMIT 1',
                [$ticket['assigned_to']]
            ) ?: '');
        }

        $this->view('admin/ticket_detail', [
            'pageTitle'    => $ticket['ticket_no'] . ' · ' . $ticket['subject'],
            'active'       => Auth::isRole('super_admin') ? 'super-tickets' : 'admin-tickets',
            't'            => $ticket,
            'messages'     => $messages,
            'assignees'    => $assignees,
            'requester'    => $requester,
            'societyName'  => $societyName,
            'assigneeName' => $assigneeName,
            'isSuper'      => Auth::isRole('super_admin'),
            'statuses'     => self::STATUSES,
        ]);
    }

    /** POST /admin/tickets/{id}/reply — staff reply (is_staff = 1). */
    public function reply(string $id): void
    {
        $this->requireRole(['society_admin', 'super_admin']);
        $ticket = $this->findScopedTicket((int) $id);
        if (!$ticket) {
            flash('error', t('tickets.not_found'));
            $this->redirect('/admin/tickets');
        }

        $errors = $this->validate(['message' => 'required|min:2|max:5000']);
        if ($errors !== []) {
            flash('error', (string) reset($errors));
            $this->back();
        }

        $attachment = null;
        try {
            if ($f = $this->file('attachment')) {
                $attachment = Upload::file($f, 'tickets', false);
            }
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            $this->back();
        }

        $message = (string) $this->post('message');
        Database::run(
            'INSERT INTO ticket_messages (ticket_id, user_id, message, attachment, is_staff, created_at)
             VALUES (?,?,?,?,1, NOW())',
            [$ticket['id'], Auth::id(), $message, $attachment]
        );

        if (in_array($ticket['status'], ['open', 'waiting', 'reopened'], true)) {
            Database::run('UPDATE support_tickets SET status = "in_progress" WHERE id = ?', [$ticket['id']]);
        }

        Notify::user(
            (int) $ticket['user_id'],
            'ticket',
            'Support replied on ' . $ticket['ticket_no'],
            mb_substr($message, 0, 140),
            '/tickets/' . $ticket['id']
        );
        Audit::log('ticket.staff_reply', 'support_ticket', (int) $ticket['id']);

        flash('success', t('tickets.reply_sent'));
        $this->redirect('/admin/tickets/' . $ticket['id']);
    }

    /** POST /admin/tickets/{id}/status */
    public function setStatus(string $id): void
    {
        $this->requireRole(['society_admin', 'super_admin']);
        $ticket = $this->findScopedTicket((int) $id);
        if (!$ticket) {
            flash('error', t('tickets.not_found'));
            $this->redirect('/admin/tickets');
        }

        $status = (string) $this->post('status');
        if (!in_array($status, self::STATUSES, true)) {
            flash('error', t('common.error'));
            $this->redirect('/admin/tickets/' . $ticket['id']);
        }

        $closedAt = $status === 'closed' ? date('Y-m-d H:i:s') : null;
        Database::run(
            'UPDATE support_tickets SET status = ?, closed_at = ? WHERE id = ?',
            [$status, $closedAt, $ticket['id']]
        );

        if (in_array($status, ['resolved', 'closed'], true)) {
            Notify::user(
                (int) $ticket['user_id'],
                'ticket',
                'Ticket ' . $ticket['ticket_no'] . ' — ' . t('tickets.status_' . $status),
                $ticket['subject'],
                '/tickets/' . $ticket['id']
            );
        }
        Audit::log('ticket.status', 'support_ticket', (int) $ticket['id'], [
            'from' => $ticket['status'], 'to' => $status,
        ]);

        flash('success', t('tickets.status_changed'));
        $this->redirect('/admin/tickets/' . $ticket['id']);
    }

    /** POST /admin/tickets/{id}/assign */
    public function assign(string $id): void
    {
        $this->requireRole(['society_admin', 'super_admin']);
        $ticket = $this->findScopedTicket((int) $id);
        if (!$ticket) {
            flash('error', t('tickets.not_found'));
            $this->redirect('/admin/tickets');
        }

        $assigneeId = (int) $this->post('assigned_to', 0);
        if ($ticket['society_id']) {
            $assignee = Database::fetch(
                'SELECT id, name FROM users
                 WHERE id = ? AND status = "active"
                   AND (role = "super_admin" OR (role = "society_admin" AND society_id = ?))
                 LIMIT 1',
                [$assigneeId, $ticket['society_id']]
            );
        } else {
            $assignee = Database::fetch(
                'SELECT id, name FROM users WHERE id = ? AND status = "active" AND role = "super_admin" LIMIT 1',
                [$assigneeId]
            );
        }
        if (!$assignee) {
            flash('error', t('common.unauthorized'));
            $this->redirect('/admin/tickets/' . $ticket['id']);
        }

        Database::run(
            'UPDATE support_tickets SET assigned_to = ?, status = "in_progress" WHERE id = ?',
            [$assignee['id'], $ticket['id']]
        );

        Notify::user(
            (int) $assignee['id'],
            'ticket',
            'Ticket assigned to you: ' . $ticket['ticket_no'],
            $ticket['subject'],
            '/admin/tickets/' . $ticket['id']
        );
        Audit::log('ticket.assign', 'support_ticket', (int) $ticket['id'], [
            'assignee' => $assignee['name'],
        ]);

        flash('success', t('tickets.assigned_done'));
        $this->redirect('/admin/tickets/' . $ticket['id']);
    }

    /* ---------------- internals ---------------- */

    /** Ticket owned by the current user. */
    private function findOwned(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        return Database::fetch(
            'SELECT * FROM support_tickets WHERE id = ? AND user_id = ? LIMIT 1',
            [$id, Auth::id()]
        );
    }

    /** Ticket within the staff scope (super admin: all; society admin: own society + unlinked). */
    private function findScopedTicket(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        if (Auth::isRole('super_admin')) {
            return Database::fetch('SELECT * FROM support_tickets WHERE id = ? LIMIT 1', [$id]);
        }
        $sid = Auth::societyId();
        if ($sid === null) {
            return Database::fetch('SELECT * FROM support_tickets WHERE id = ? AND society_id IS NULL LIMIT 1', [$id]);
        }
        return Database::fetch(
            'SELECT * FROM support_tickets WHERE id = ? AND (society_id = ? OR society_id IS NULL) LIMIT 1',
            [$id, $sid]
        );
    }
}
