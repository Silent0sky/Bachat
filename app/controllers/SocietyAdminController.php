<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Upload;
use App\Services\Notify;
use PDOException;
use RuntimeException;

/**
 * Society admin — society-scoped console.
 * EVERY query in this controller is filtered by the admin's own society id.
 */
final class SocietyAdminController extends Controller
{
    /** Allowed order status transitions (admin side). */
    private const ORDER_FLOW = [
        'pending'   => ['confirmed', 'cancelled'],
        'confirmed' => ['packed', 'cancelled'],
        'packed'    => ['ready', 'cancelled'],
        'ready'     => ['delivered', 'cancelled'],
    ];

    /* ================================================================
       HELPERS
       ================================================================ */

    /** Society scope guard — society admins ALWAYS work inside their own society. */
    private function sid(): int
    {
        $sid = Auth::societyId();
        if ($sid === null) {
            flash('error', 'No society is linked to your account. Contact the platform admin.');
            $this->redirect('/login');
        }
        return $sid;
    }

    private function flashErrors(array $errors): void
    {
        foreach ($errors as $msg) {
            flash('error', (string) $msg);
        }
    }

    private function intOrNull(mixed $v): ?int
    {
        if (is_string($v)) {
            $v = trim($v);
        }
        return ($v === null || $v === '' || !is_numeric($v)) ? null : (int) $v;
    }

    private function strOrNull(mixed $v): ?string
    {
        if ($v === null) {
            return null;
        }
        $v = trim((string) $v);
        return $v === '' ? null : $v;
    }

    private function num(mixed $v, float $def = 0.0): float
    {
        return is_numeric($v) ? (float) $v : $def;
    }

    private function flag(mixed $v): int
    {
        return (!empty($v) && $v !== '0') ? 1 : 0;
    }

    /** @return array{0:string,1:string,2:string} from, to, toSql */
    private function reportRange(): array
    {
        $from = $this->query('from');
        $to   = $this->query('to');
        $from = (is_string($from) && strtotime($from)) ? date('Y-m-d', strtotime($from)) : date('Y-m-d', strtotime('-30 days'));
        $to   = (is_string($to) && strtotime($to)) ? date('Y-m-d', strtotime($to)) : date('Y-m-d');
        return [$from, $to, $to . ' 23:59:59'];
    }

    private function fillDays(array $rows, string $from, string $to, string $valKey): array
    {
        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['d']] = (float) ($r[$valKey] ?? 0);
        }
        $out = [];
        $cur = strtotime($from);
        $end = strtotime($to);
        while ($cur !== false && $end !== false && $cur <= $end) {
            $d     = date('Y-m-d', $cur);
            $out[] = ['d' => $d, 'v' => $map[$d] ?? 0.0];
            $cur   = strtotime('+1 day', $cur);
        }
        return $out;
    }

    private function tryUploadImage(?array $file, string $dir, array &$errors, string $field): ?string
    {
        if ($file === null) {
            return null;
        }
        try {
            return Upload::image($file, $dir);
        } catch (RuntimeException $e) {
            $errors[$field] = $e->getMessage();
            return null;
        }
    }

    private function sendCsv(string $type, array $header, array $rows): never
    {
        $name = 'bachat-' . $type . '-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Pragma: no-cache');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, $header);
        foreach ($rows as $r) {
            fputcsv($out, $r);
        }
        fclose($out);
        exit;
    }

    /* ================================================================
       DASHBOARD
       ================================================================ */

    public function dashboard(): void
    {
        $sid = $this->sid();
        $monthStart = date('Y-m-01 00:00:00');

        $kpis = [
            'members_active'  => (int) Database::scalar('SELECT COUNT(*) FROM memberships WHERE society_id = ? AND status = "active"', [$sid]),
            'pending'         => (int) Database::scalar('SELECT COUNT(*) FROM memberships WHERE society_id = ? AND status = "pending"', [$sid]),
            'orders_today'    => (int) Database::scalar('SELECT COUNT(*) FROM orders WHERE society_id = ? AND DATE(created_at) = CURDATE()', [$sid]),
            'month_revenue'   => (float) Database::scalar('SELECT COALESCE(SUM(total),0) FROM orders WHERE society_id = ? AND status <> "cancelled" AND created_at >= ?', [$sid, $monthStart]),
            'pay_pending'     => (int) Database::scalar('SELECT COUNT(*) FROM payments WHERE society_id = ? AND status = "submitted"', [$sid]),
            'tickets_open'    => (int) Database::scalar('SELECT COUNT(*) FROM support_tickets WHERE society_id = ? AND status IN ("open","in_progress","waiting","reopened")', [$sid]),
            'campaigns_live'  => (int) Database::scalar('SELECT COUNT(*) FROM campaigns WHERE status = "active" AND (society_id = ? OR society_id IS NULL) AND (deadline IS NULL OR deadline >= NOW())', [$sid]),
        ];

        // Sales — last 14 days
        $revRows = Database::fetchAll(
            'SELECT DATE(created_at) d, SUM(total) v FROM orders
             WHERE society_id = ? AND status <> "cancelled" AND created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             GROUP BY DATE(created_at)',
            [$sid]
        );
        $rev14 = $this->fillDays($revRows, date('Y-m-d', strtotime('-13 days')), date('Y-m-d'), 'v');

        // Orders by status
        $statusRows = Database::fetchAll(
            'SELECT status, COUNT(*) c FROM orders WHERE society_id = ? GROUP BY status',
            [$sid]
        );

        $latestOrders = Database::fetchAll(
            'SELECT o.id, o.order_no, o.status, o.total, o.created_at, o.fulfilment, u.name user_name
             FROM orders o JOIN users u ON u.id = o.user_id
             WHERE o.society_id = ? ORDER BY o.id DESC LIMIT 6',
            [$sid]
        );

        $pendingMembers = Database::fetchAll(
            'SELECT m.id, m.requested_at, m.member_role, u.name user_name, f.block, f.flat_number
             FROM memberships m
             JOIN users u ON u.id = m.user_id
             LEFT JOIN flats f ON f.id = m.flat_id
             WHERE m.society_id = ? AND m.status = "pending"
             ORDER BY m.requested_at ASC LIMIT 6',
            [$sid]
        );

        $this->view('dashboards/society_admin', [
            'active'         => 'admin-dashboard',
            'pageTitle'      => 'Society Dashboard',
            'useCharts'      => true,
            'kpis'           => $kpis,
            'rev14'          => $rev14,
            'statusRows'     => $statusRows,
            'latestOrders'   => $latestOrders,
            'pendingMembers' => $pendingMembers,
        ]);
    }

    /* ================================================================
       MEMBERS
       ================================================================ */

    public function members(): void
    {
        $sid = $this->sid();
        $tab = (string) $this->query('tab', 'pending');
        if (!in_array($tab, ['pending', 'active'], true)) {
            $tab = 'pending';
        }

        $rows = Database::fetchAll(
            'SELECT m.*, u.name user_name, u.email user_email, u.phone user_phone,
                    f.block, f.flat_number, f.flat_type
             FROM memberships m
             JOIN users u ON u.id = m.user_id
             LEFT JOIN flats f ON f.id = m.flat_id
             WHERE m.society_id = ? AND m.status = ?
             ORDER BY m.requested_at DESC LIMIT 200',
            [$sid, $tab]
        );

        $counts = [
            'pending' => (int) Database::scalar('SELECT COUNT(*) FROM memberships WHERE society_id = ? AND status = "pending"', [$sid]),
            'active'  => (int) Database::scalar('SELECT COUNT(*) FROM memberships WHERE society_id = ? AND status = "active"', [$sid]),
        ];

        $flats = Database::fetchAll(
            'SELECT id, block, flat_number, flat_type FROM flats WHERE society_id = ? ORDER BY block ASC, flat_number ASC',
            [$sid]
        );

        $this->view('admin/members', [
            'active'    => 'admin-members',
            'pageTitle' => 'Members & Approvals',
            'tab'       => $tab,
            'rows'      => $rows,
            'counts'    => $counts,
            'flats'     => $flats,
        ]);
    }

    private function scopedMembership(string $id, array $statuses): ?array
    {
        $sid = $this->sid();
        $mid = $this->intOrNull($id);
        if ($mid === null) {
            return null;
        }
        $ph = implode(',', array_fill(0, count($statuses), '?'));
        return Database::fetch(
            "SELECT m.*, u.name user_name, u.email user_email FROM memberships m JOIN users u ON u.id = m.user_id
             WHERE m.id = ? AND m.society_id = ? AND m.status IN ($ph) LIMIT 1",
            [$mid, $sid, ...$statuses]
        );
    }

    public function approveMember(string $id): void
    {
        $m = $this->scopedMembership($id, ['pending']);
        if (!$m) {
            flash('error', 'Pending membership request not found in your society.');
            $this->redirect('/admin/members');
        }
        Database::run(
            'UPDATE memberships SET status = "active", decided_by = ?, decided_at = NOW(), note = NULL WHERE id = ?',
            [Auth::id(), $m['id']]
        );
        Audit::log('membership.approved', 'membership', (int) $m['id'], ['user_id' => $m['user_id'], 'society_id' => $m['society_id']]);
        Notify::user((int) $m['user_id'], 'membership', 'Membership approved',
            'Welcome! Your society membership is now active — start shopping and saving.', '/dashboard');
        flash('success', 'Approved ' . $m['user_name'] . '.');
        $this->redirect('/admin/members?tab=pending');
    }

    public function rejectMember(string $id): void
    {
        $m = $this->scopedMembership($id, ['pending']);
        if (!$m) {
            flash('error', 'Pending membership request not found in your society.');
            $this->redirect('/admin/members');
        }
        $note = $this->strOrNull($this->post('note'));
        Database::run(
            'UPDATE memberships SET status = "rejected", decided_by = ?, decided_at = NOW(), note = ? WHERE id = ?',
            [Auth::id(), $note, $m['id']]
        );
        Audit::log('membership.rejected', 'membership', (int) $m['id'], ['user_id' => $m['user_id'], 'note' => $note !== null ? $note : '']);
        Notify::user((int) $m['user_id'], 'membership', 'Membership request rejected',
            'Your membership request was rejected by the society admin.' . ($note ? ' Note: ' . $note : ''), null);
        flash('success', 'Request from ' . $m['user_name'] . ' rejected.');
        $this->redirect('/admin/members?tab=pending');
    }

    public function removeMember(string $id): void
    {
        $m = $this->scopedMembership($id, ['active']);
        if (!$m) {
            flash('error', 'Active membership not found in your society.');
            $this->redirect('/admin/members?tab=active');
        }
        Database::run(
            'UPDATE memberships SET status = "removed", decided_by = ?, decided_at = NOW() WHERE id = ?',
            [Auth::id(), $m['id']]
        );
        Audit::log('membership.removed', 'membership', (int) $m['id'], ['user_id' => $m['user_id']]);
        Notify::user((int) $m['user_id'], 'membership', 'Membership removed',
            'Your society membership was removed by the society admin. Contact the society office if this is a mistake.', null);
        flash('success', 'Removed ' . $m['user_name'] . ' from the society.');
        $this->redirect('/admin/members?tab=active');
    }

    public function addResident(): void
    {
        $sid    = $this->sid();
        $errors = $this->validate([
            'name'        => 'required|max:120',
            'email'       => 'required|email|max:150',
            'password'    => 'required|min:6',
            'phone'       => 'max:20',
            'member_role' => 'in:owner,tenant',
        ]);
        $email = strtolower((string) $this->post('email'));
        if ($errors === [] && Database::fetch('SELECT id FROM users WHERE email = ? LIMIT 1', [$email])) {
            $errors['email'] = 'This email is already registered.';
        }
        $flatId = $this->intOrNull($this->post('flat_id'));
        if ($errors === [] && $flatId !== null) {
            $flat = Database::fetch('SELECT id FROM flats WHERE id = ? AND society_id = ?', [$flatId, $sid]);
            if (!$flat) {
                $errors['flat_id'] = 'Flat not found in your society.';
            }
        }
        if ($errors !== []) {
            $this->keepOld();
            $this->flashErrors($errors);
            $this->back();
        }

        $temp = 'Bch' . random_int(100000, 999999);
        Database::run(
            'INSERT INTO users (name, email, phone, password_hash, role, society_id, status, language, created_at, updated_at)
             VALUES (?,?,?,?, "resident", ?, "active", "en", NOW(), NOW())',
            [(string) $this->post('name'), $email, $this->strOrNull($this->post('phone')), password_hash($temp, PASSWORD_DEFAULT), $sid]
        );
        $uid = (int) Database::pdo()->lastInsertId();
        Database::run(
            'INSERT INTO memberships (user_id, society_id, flat_id, member_role, status, requested_at, decided_by, decided_at)
             VALUES (?,?,?,?, "active", NOW(), ?, NOW())',
            [$uid, $sid, $flatId, (string) $this->post('member_role', 'owner'), Auth::id()]
        );
        Audit::log('resident.added', 'user', $uid, ['email' => $email, 'flat_id' => $flatId, 'society_id' => $sid]);
        Notify::user($uid, 'account', 'Welcome to your society on Bachat',
            'An account was created for you. Temporary password: ' . $temp . ' — please change it after first login.', '/login');
        flash('info', 'Resident added. Temporary password: ' . $temp . ' — share it securely; ask them to change it after login.');
        $this->redirect('/admin/members?tab=active');
    }

    /* ================================================================
       FLATS
       ================================================================ */

    public function flats(): void
    {
        $sid = $this->sid();
        $rows = Database::fetchAll(
            'SELECT f.*,
                    (SELECT COUNT(*) FROM memberships m WHERE m.flat_id = f.id AND m.status = "active") members_count
             FROM flats f WHERE f.society_id = ?
             ORDER BY f.block ASC, f.flat_number ASC LIMIT 500',
            [$sid]
        );
        $editId = $this->intOrNull($this->query('edit'));
        $edit   = $editId ? Database::fetch('SELECT * FROM flats WHERE id = ? AND society_id = ?', [$editId, $sid]) : null;
        $this->view('admin/flats', [
            'active'    => 'admin-flats',
            'pageTitle' => 'Flats',
            'rows'      => $rows,
            'edit'      => $edit,
        ]);
    }

    public function saveFlat(): void
    {
        $sid    = $this->sid();
        $id     = $this->intOrNull($this->post('id'));
        $errors = $this->validate([
            'block'       => 'max:20',
            'flat_number' => 'required|max:30',
            'flat_type'   => 'max:40',
        ]);
        if ($errors !== []) {
            $this->keepOld();
            $this->flashErrors($errors);
            $this->back();
        }
        $block = (string) $this->post('block', '');
        $flatNo = (string) $this->post('flat_number');
        $type  = $this->strOrNull($this->post('flat_type'));

        $dup = Database::fetch(
            'SELECT id FROM flats WHERE society_id = ? AND block = ? AND flat_number = ? AND id != ? LIMIT 1',
            [$sid, $block, $flatNo, $id ?? 0]
        );
        if ($dup) {
            flash('error', 'Flat "' . ($block !== '' ? $block . '-' : '') . $flatNo . '" already exists in your society.');
            $this->back();
        }

        if ($id !== null) {
            $exists = Database::fetch('SELECT id FROM flats WHERE id = ? AND society_id = ?', [$id, $sid]);
            if (!$exists) {
                flash('error', 'Flat not found.');
                $this->redirect('/admin/flats');
            }
            Database::run('UPDATE flats SET block = ?, flat_number = ?, flat_type = ? WHERE id = ?', [$block, $flatNo, $type, $id]);
            Audit::log('flat.updated', 'flat', $id, ['block' => $block, 'flat_number' => $flatNo]);
            flash('success', 'Flat updated.');
        } else {
            Database::run('INSERT INTO flats (society_id, block, flat_number, flat_type) VALUES (?,?,?,?)', [$sid, $block, $flatNo, $type]);
            Audit::log('flat.created', 'flat', (int) Database::pdo()->lastInsertId(), ['block' => $block, 'flat_number' => $flatNo]);
            flash('success', 'Flat added.');
        }
        $this->redirect('/admin/flats');
    }

    public function deleteFlat(string $id): void
    {
        $sid = $this->sid();
        $fid = $this->intOrNull($id);
        $flat = $fid ? Database::fetch('SELECT id, block, flat_number FROM flats WHERE id = ? AND society_id = ?', [$fid, $sid]) : null;
        if (!$flat) {
            flash('error', 'Flat not found.');
            $this->redirect('/admin/flats');
        }
        $members = (int) Database::scalar('SELECT COUNT(*) FROM memberships WHERE flat_id = ?', [$fid]);
        if ($members > 0) {
            flash('error', 'Cannot delete Flat "' . $flat['block'] . '-' . $flat['flat_number'] . '" — ' . $members . ' membership record(s) reference it.');
            $this->redirect('/admin/flats');
        }
        Database::run('DELETE FROM flats WHERE id = ?', [$fid]);
        Audit::log('flat.deleted', 'flat', (int) $flat['id'], ['block' => $flat['block'], 'flat_number' => $flat['flat_number']]);
        flash('success', 'Flat deleted.');
        $this->redirect('/admin/flats');
    }

    /* ================================================================
       ORDERS
       ================================================================ */

    public function orders(): void
    {
        $sid    = $this->sid();
        $view   = (string) $this->query('view', 'orders');
        if (!in_array($view, ['orders', 'combined'], true)) {
            $view = 'orders';
        }
        $status = (string) $this->query('status', '');
        $type   = (string) $this->query('type', '');
        $q      = (string) $this->query('q', '');

        // 1. Regular individual orders list
        $sql = 'SELECT o.*, u.name user_name,
                    (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) item_count
                FROM orders o JOIN users u ON u.id = o.user_id
                WHERE o.society_id = ?';
        $par = [$sid];
        if (in_array($status, ['pending', 'confirmed', 'packed', 'ready', 'out_for_delivery', 'delivered', 'cancelled', 'failed'], true)) {
            $sql .= ' AND o.status = ?';
            $par[] = $status;
        }
        if (in_array($type, ['regular', 'campaign', 'repeat'], true)) {
            $sql .= ' AND o.type = ?';
            $par[] = $type;
        }
        if ($q !== '') {
            $sql .= ' AND (o.order_no LIKE ? OR u.name LIKE ? OR o.contact_phone LIKE ?)';
            $like = "%$q%";
            array_push($par, $like, $like, $like);
        }
        $sql .= ' ORDER BY o.id DESC LIMIT 100';
        $rows = Database::fetchAll($sql, $par);

        // 2. Combined Products aggregation across all customer orders
        $combSql = "
            SELECT 
                COALESCE(oi.variant_id, 0) AS variant_id,
                oi.product_name,
                oi.brand_name,
                oi.pack_size,
                oi.unit,
                oi.unit_price,
                SUM(oi.qty) AS total_qty,
                SUM(oi.line_total) AS total_amount,
                COUNT(DISTINCT o.id) AS order_count,
                COUNT(DISTINCT o.user_id) AS customer_count,
                pv.sku,
                p.image AS product_img,
                c.name_en AS category_name
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            LEFT JOIN product_variants pv ON pv.id = oi.variant_id
            LEFT JOIN products p ON p.id = pv.product_id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE o.society_id = ?
        ";
        $combPar = [$sid];

        if (in_array($status, ['pending', 'confirmed', 'packed', 'ready', 'out_for_delivery', 'delivered', 'cancelled', 'failed'], true)) {
            $combSql .= ' AND o.status = ?';
            $combPar[] = $status;
        } else {
            // Default: include active orders, exclude cancelled/failed
            $combSql .= " AND o.status NOT IN ('cancelled', 'failed')";
        }

        if (in_array($type, ['regular', 'campaign', 'repeat'], true)) {
            $combSql .= ' AND o.type = ?';
            $combPar[] = $type;
        }

        if ($q !== '') {
            $combSql .= ' AND (oi.product_name LIKE ? OR oi.brand_name LIKE ?)';
            $like = "%$q%";
            array_push($combPar, $like, $like);
        }

        $combSql .= ' GROUP BY COALESCE(oi.variant_id, 0), oi.product_name, oi.brand_name, oi.pack_size, oi.unit, oi.unit_price, pv.sku, p.image, c.name_en ORDER BY total_qty DESC';
        $combinedProducts = Database::fetchAll($combSql, $combPar);

        // 3. Customer breakdown per product (who ordered how much)
        $cbSql = "
            SELECT 
                COALESCE(oi.variant_id, 0) AS variant_id,
                oi.product_name,
                o.id AS order_id,
                o.order_no,
                o.status AS order_status,
                o.fulfilment,
                u.id AS user_id,
                u.name AS customer_name,
                u.phone AS customer_phone,
                u.email AS customer_email,
                CONCAT(COALESCE(f.block, ''), ' ', COALESCE(f.flat_number, '')) AS flat_label,
                oi.qty,
                oi.line_total
            FROM order_items oi
            JOIN orders o ON o.id = oi.order_id
            JOIN users u ON u.id = o.user_id
            LEFT JOIN (
                SELECT user_id, MAX(flat_id) AS flat_id 
                FROM memberships 
                WHERE society_id = ? AND status = 'active' 
                GROUP BY user_id
            ) m ON m.user_id = u.id
            LEFT JOIN flats f ON f.id = m.flat_id
            WHERE o.society_id = ?
        ";
        $cbPar = [$sid, $sid];
        if (in_array($status, ['pending', 'confirmed', 'packed', 'ready', 'out_for_delivery', 'delivered', 'cancelled', 'failed'], true)) {
            $cbSql .= ' AND o.status = ?';
            $cbPar[] = $status;
        } else {
            $cbSql .= " AND o.status NOT IN ('cancelled', 'failed')";
        }
        $cbSql .= ' ORDER BY oi.id ASC';
        $rawBreakdowns = Database::fetchAll($cbSql, $cbPar);

        $breakdownByProduct = [];
        foreach ($rawBreakdowns as $cb) {
            $key = ((int) $cb['variant_id']) . '_' . trim($cb['product_name']);
            $breakdownByProduct[$key][] = $cb;
        }

        // Summary KPIs for Combined view
        $kpis = [
            'total_products'   => count($combinedProducts),
            'total_units'      => (float) array_sum(array_column($combinedProducts, 'total_qty')),
            'total_amount'     => (float) array_sum(array_column($combinedProducts, 'total_amount')),
            'total_customers'  => count(array_unique(array_column($rawBreakdowns, 'user_id'))),
        ];

        $this->view('admin/orders', [
            'active'             => $view === 'combined' ? 'admin-combined-orders' : 'admin-orders',
            'pageTitle'          => $view === 'combined' ? 'Combined Customer Products' : 'Orders',
            'view'               => $view,
            'rows'               => $rows,
            'combinedProducts'   => $combinedProducts,
            'breakdownByProduct' => $breakdownByProduct,
            'kpis'               => $kpis,
            'fStatus'            => $status,
            'fType'              => $type,
            'fQ'                 => $q,
        ]);
    }

    public function orderDetail(string $id): void
    {
        $sid = $this->sid();
        $oid = $this->intOrNull($id);
        $order = $oid
            ? Database::fetch(
                'SELECT o.*, u.name user_name, u.email user_email, s.name society_name, s.upi_id
                 FROM orders o JOIN users u ON u.id = o.user_id JOIN societies s ON s.id = o.society_id
                 WHERE o.id = ? AND o.society_id = ? LIMIT 1',
                [$oid, $sid]
            )
            : null;
        if (!$order) {
            flash('error', 'Order not found in your society.');
            $this->redirect('/admin/orders');
        }

        $items = Database::fetchAll(
            'SELECT oi.*, p.name_en product_name_en, b.name brand_name_db
             FROM order_items oi
             LEFT JOIN product_variants v ON v.id = oi.variant_id
             LEFT JOIN products p ON p.id = v.product_id
             LEFT JOIN brands b ON b.id = v.brand_id
             WHERE oi.order_id = ?',
            [$oid]
        );
        $events = Database::fetchAll(
            'SELECT e.*, u.name actor_name FROM order_events e LEFT JOIN users u ON u.id = e.created_by
             WHERE e.order_id = ? ORDER BY e.id DESC',
            [$oid]
        );
        $paymentsRows = Database::fetchAll('SELECT * FROM payments WHERE order_id = ? ORDER BY id DESC', [$oid]);
        $task = Database::fetch(
            'SELECT dt.*, st.name staff_name FROM delivery_tasks dt JOIN users st ON st.id = dt.assigned_to
             WHERE dt.order_id = ? ORDER BY dt.id DESC LIMIT 1',
            [$oid]
        );

        $this->view('admin/order_detail', [
            'active'    => 'admin-orders',
            'pageTitle' => 'Order ' . $order['order_no'],
            'order'     => $order,
            'items'     => $items,
            'events'    => $events,
            'payments'  => $paymentsRows,
            'task'      => $task,
        ]);
    }

    public function updateOrderStatus(string $id): void
    {
        $sid = $this->sid();
        $oid = $this->intOrNull($id);
        $order = $oid
            ? Database::fetch('SELECT * FROM orders WHERE id = ? AND society_id = ? LIMIT 1', [$oid, $sid])
            : null;
        if (!$order) {
            flash('error', 'Order not found in your society.');
            $this->redirect('/admin/orders');
        }

        $to = (string) $this->post('status');
        $allowed = self::ORDER_FLOW[$order['status']] ?? [];
        if (!in_array($to, $allowed, true)) {
            flash('error', 'Cannot move order from "' . $order['status'] . '" to "' . $to . '".');
            $this->redirect('/admin/orders/' . $order['id']);
        }
        if ($to === 'cancelled') {
            $reason = $this->strOrNull($this->post('reason'));
            if ($reason === null) {
                flash('error', 'A cancellation reason is required.');
                $this->redirect('/admin/orders/' . $order['id']);
            }
        }
        if ($to === 'delivered' && $order['fulfilment'] !== 'pickup') {
            flash('error', 'Delivery orders are marked delivered by the delivery staff (OTP flow). Assign a delivery task instead.');
            $this->redirect('/admin/orders/' . $order['id']);
        }

        $note = $to === 'cancelled'
            ? 'Cancelled: ' . $reason
            : ($this->strOrNull($this->post('note')) ?? 'Status updated by society admin');

        $sql = 'UPDATE orders SET status = ?, updated_at = NOW()';
        $par = [$to];
        if ($to === 'delivered') {
            $sql .= ', delivered_at = NOW()';
        }
        if ($to === 'cancelled') {
            $sql .= ', cancel_reason = ?';
            $par[] = $reason;
        }
        $sql .= ' WHERE id = ?';
        $par[] = $order['id'];
        Database::run($sql, $par);

        Database::run(
            'INSERT INTO order_events (order_id, status, note, created_by, created_at) VALUES (?,?,?,?,NOW())',
            [$order['id'], $to, $note, Auth::id()]
        );
        Audit::log('order.status', 'order', (int) $order['id'], ['from' => $order['status'], 'to' => $to]);

        $nice = str_replace('_', ' ', $to);
        Notify::user(
            (int) $order['user_id'], 'order', 'Order ' . $order['order_no'] . ' ' . ucfirst($nice),
            'Your order status is now: ' . $nice . '.', '/orders/' . $order['id']
        );
        flash('success', 'Order ' . $order['order_no'] . ' → ' . $nice . '.');
        $this->redirect('/admin/orders/' . $order['id']);
    }

    /* ================================================================
       DELIVERIES
       ================================================================ */

    public function deliveries(): void
    {
        $sid = $this->sid();
        $queue = Database::fetchAll(
            "SELECT o.id, o.order_no, o.status, o.slot_date, o.slot_label, o.address_text, o.contact_phone,
                    u.name user_name,
                    dt.id task_id, dt.status task_status, dt.otp, dt.assigned_to, st.name staff_name
             FROM orders o
             JOIN users u ON u.id = o.user_id
             LEFT JOIN delivery_tasks dt ON dt.order_id = o.id AND dt.status IN ('assigned','picked','out_for_delivery')
             LEFT JOIN users st ON st.id = dt.assigned_to
             WHERE o.society_id = ? AND o.fulfilment = 'delivery' AND o.status IN ('confirmed','packed','ready')
             ORDER BY o.id DESC LIMIT 100",
            [$sid]
        );
        $tasks = Database::fetchAll(
            "SELECT dt.*, o.order_no, o.status order_status, st.name staff_name
             FROM delivery_tasks dt
             JOIN orders o ON o.id = dt.order_id
             JOIN users st ON st.id = dt.assigned_to
             WHERE dt.society_id = ?
             ORDER BY dt.id DESC LIMIT 60",
            [$sid]
        );
        $staff = Database::fetchAll(
            'SELECT id, name, phone FROM users WHERE role = "delivery_staff" AND society_id = ? AND status = "active" ORDER BY name ASC',
            [$sid]
        );
        $this->view('admin/deliveries', [
            'active'    => 'admin-deliveries',
            'pageTitle' => 'Deliveries',
            'queue'     => $queue,
            'tasks'     => $tasks,
            'staff'     => $staff,
        ]);
    }

    public function assignDelivery(): void
    {
        $sid      = $this->sid();
        $orderId  = $this->intOrNull($this->post('order_id'));
        $staffId  = $this->intOrNull($this->post('staff_id'));

        $order = $orderId
            ? Database::fetch(
                "SELECT id, order_no, user_id, status FROM orders
                 WHERE id = ? AND society_id = ? AND fulfilment = 'delivery' AND status IN ('confirmed','packed','ready') LIMIT 1",
                [$orderId, $sid]
            )
            : null;
        if (!$order) {
            flash('error', 'Delivery order not found (or not in an assignable status).');
            $this->redirect('/admin/deliveries');
        }
        $staff = $staffId
            ? Database::fetch(
                'SELECT id, name FROM users WHERE id = ? AND role = "delivery_staff" AND society_id = ? AND status = "active" LIMIT 1',
                [$staffId, $sid]
            )
            : null;
        if (!$staff) {
            flash('error', 'Please choose a delivery staff member from your society.');
            $this->redirect('/admin/deliveries');
        }

        $reassigned = false;
        $active = Database::fetch(
            "SELECT id FROM delivery_tasks WHERE order_id = ? AND status IN ('assigned','picked','out_for_delivery') LIMIT 1",
            [$orderId]
        );
        if ($active) {
            Database::run(
                "UPDATE delivery_tasks SET status = 'failed', fail_reason = 'Reassigned by society admin' WHERE id = ?",
                [$active['id']]
            );
            $reassigned = true;
        }

        $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Database::run(
            "INSERT INTO delivery_tasks (order_id, society_id, assigned_to, status, otp, attempts, created_at)
             VALUES (?, ?, ?, 'assigned', ?, 0, NOW())",
            [$orderId, $sid, $staffId, $otp]
        );
        Audit::log('delivery.assigned', 'delivery_task', (int) Database::pdo()->lastInsertId(), [
            'order_id' => $orderId, 'staff_id' => $staffId, 'reassigned' => $reassigned,
        ]);
        Notify::user((int) $staffId, 'delivery', 'Delivery task assigned',
            'Order ' . $order['order_no'] . ' was assigned to you. Open your delivery panel to start.', '/delivery/dashboard');
        flash('success', 'Task assigned to ' . $staff['name'] . ' — OTP ' . $otp . ' (share with the resident at handover).');
        $this->redirect('/admin/deliveries');
    }

    /* ================================================================
       PAYMENTS
       ================================================================ */

    public function payments(): void
    {
        $sid    = $this->sid();
        $status = (string) $this->query('status', 'submitted');
        if (!in_array($status, ['pending', 'submitted', 'verified', 'failed', 'refunded', 'all'], true)) {
            $status = 'submitted';
        }
        $sql = 'SELECT p.*, o.order_no, b.booking_no, u.name payer_name, u.email payer_email
                FROM payments p
                LEFT JOIN orders o ON o.id = p.order_id
                LEFT JOIN bookings b ON b.id = p.booking_id
                JOIN users u ON u.id = p.user_id
                WHERE COALESCE(p.society_id, o.society_id, b.society_id) = ?';
        $par = [$sid];
        if ($status !== 'all') {
            $sql .= ' AND p.status = ?';
            $par[] = $status;
        }
        $sql .= ' ORDER BY p.id DESC LIMIT 200';
        $rows = Database::fetchAll($sql, $par);

        $society = Database::fetch('SELECT payment_verifier FROM societies WHERE id = ?', [$sid]);
        $this->view('admin/payments', [
            'active'      => 'admin-payments',
            'pageTitle'   => 'Payment Verification',
            'rows'        => $rows,
            'fStatus'     => $status,
            'verifier'    => $society['payment_verifier'] ?? 'society_admin',
        ]);
    }

    private function verifierAllowed(int $sid): bool
    {
        $row = Database::fetch('SELECT payment_verifier FROM societies WHERE id = ?', [$sid]);
        if ($row && $row['payment_verifier'] === 'super_admin' && Auth::role() === 'society_admin') {
            flash('error', 'Verification is reserved to the platform admin for this society.');
            return false;
        }
        return true;
    }

    public function verifyPayment(string $id): void
    {
        $sid = $this->sid();
        if (!$this->verifierAllowed($sid)) {
            $this->redirect('/admin/payments');
        }
        $pid = $this->intOrNull($id);
        $pay = $pid
            ? Database::fetch(
                'SELECT p.*, o.order_no FROM payments p LEFT JOIN orders o ON o.id = p.order_id
                 WHERE p.id = ? AND COALESCE(p.society_id, o.society_id) = ? LIMIT 1',
                [$pid, $sid]
            )
            : null;
        if (!$pay) {
            flash('error', 'Payment not found in your society.');
            $this->redirect('/admin/payments');
        }
        if ($pay['status'] !== 'submitted') {
            flash('error', 'Only submitted payments can be verified.');
            $this->redirect('/admin/payments');
        }
        Database::run(
            'UPDATE payments SET status = "verified", verified_by = ?, verified_at = NOW() WHERE id = ?',
            [Auth::id(), $pay['id']]
        );
        Audit::log('payment.verified', 'payment', (int) $pay['id'], [
            'amount' => $pay['amount'], 'method' => $pay['method'], 'order_id' => $pay['order_id'], 'booking_id' => $pay['booking_id'],
        ]);
        Notify::user(
            (int) $pay['user_id'], 'payment', 'Payment verified',
            'Your payment of ' . money($pay['amount']) . ' was verified.' . ($pay['order_no'] ? ' Order ' . $pay['order_no'] . '.' : ''),
            $pay['order_id'] ? '/orders/' . $pay['order_id'] : ($pay['booking_id'] ? '/bookings' : null)
        );
        flash('success', 'Payment #' . $pay['id'] . ' verified.');
        $this->redirect('/admin/payments');
    }

    public function rejectPayment(string $id): void
    {
        $sid = $this->sid();
        if (!$this->verifierAllowed($sid)) {
            $this->redirect('/admin/payments');
        }
        $pid = $this->intOrNull($id);
        $pay = $pid
            ? Database::fetch(
                'SELECT p.* FROM payments p LEFT JOIN orders o ON o.id = p.order_id
                 WHERE p.id = ? AND COALESCE(p.society_id, o.society_id) = ? LIMIT 1',
                [$pid, $sid]
            )
            : null;
        if (!$pay) {
            flash('error', 'Payment not found in your society.');
            $this->redirect('/admin/payments');
        }
        if ($pay['status'] !== 'submitted') {
            flash('error', 'Only submitted payments can be rejected.');
            $this->redirect('/admin/payments');
        }
        $reason = $this->strOrNull($this->post('reason')) ?? 'Payment could not be verified.';
        Database::run('UPDATE payments SET status = "failed" WHERE id = ?', [$pid]);
        Audit::log('payment.rejected', 'payment', (int) $pay['id'], [
            'amount' => $pay['amount'], 'method' => $pay['method'], 'reason' => $reason,
        ]);
        Notify::user(
            (int) $pay['user_id'], 'payment', 'Payment rejected',
            'Your payment of ' . money($pay['amount']) . ' could not be verified: ' . $reason,
            $pay['order_id'] ? '/orders/' . $pay['order_id'] . '/pay' : null
        );
        flash('success', 'Payment #' . $pay['id'] . ' marked as failed.');
        $this->redirect('/admin/payments');
    }

    /* ================================================================
       COUPONS (society-scoped)
       ================================================================ */

    public function coupons(): void
    {
        $sid = $this->sid();
        $rows = Database::fetchAll(
            'SELECT c.*, s.name society_name FROM coupons c LEFT JOIN societies s ON s.id = c.society_id
             WHERE c.society_id = ? ORDER BY c.id DESC LIMIT 200',
            [$sid]
        );
        $editId = $this->intOrNull($this->query('edit'));
        $edit   = $editId ? Database::fetch('SELECT * FROM coupons WHERE id = ? AND society_id = ?', [$editId, $sid]) : null;
        $this->view('admin/coupons', [
            'active'    => 'admin-coupons',
            'pageTitle' => 'Society Coupons',
            'rows'      => $rows,
            'edit'      => $edit,
        ]);
    }

    public function saveCoupon(): void
    {
        $sid    = $this->sid();
        $id     = $this->intOrNull($this->post('id'));
        $errors = $this->validate([
            'code'           => 'required|max:40',
            'title'          => 'max:150',
            'type'           => 'in:fixed,percent',
            'value'          => 'required|numeric',
            'min_cart_value' => 'numeric',
            'max_discount'   => 'numeric',
            'per_user_limit' => 'int',
            'valid_from'     => 'date',
            'valid_to'       => 'date',
            'funding_source' => 'in:platform,society,vendor',
        ]);
        $code = strtoupper((string) $this->post('code'));
        if ($errors === [] && Database::fetch('SELECT id FROM coupons WHERE code = ? AND id != ? LIMIT 1', [$code, $id ?? 0])) {
            $errors['code'] = 'This coupon code already exists (codes are platform-wide unique).';
        }
        if ($errors !== []) {
            $this->keepOld();
            $this->flashErrors($errors);
            $this->back();
        }
        $funding = (string) $this->post('funding_source', 'society');
        if ($funding === 'platform') {
            $funding = 'society'; // society admins can only fund from society/vendor budgets
        }
        $dt = static function (mixed $v): ?string {
            if (!is_string($v) || $v === '') {
                return null;
            }
            $v = str_replace('T', ' ', $v);
            if (strlen($v) === 10) $v .= ' 00:00:00';
            if (strlen($v) === 16) $v .= ':00';
            return strtotime($v) === false ? null : $v;
        };
        $data = [
            'code'           => $code,
            'title'          => $this->strOrNull($this->post('title')),
            'type'           => (string) $this->post('type', 'fixed'),
            'value'          => $this->num($this->post('value')),
            'min_cart_value' => $this->num($this->post('min_cart_value')),
            'max_discount'   => $this->strOrNull($this->post('max_discount')) === null ? null : $this->num($this->post('max_discount')),
            'usage_limit'    => $this->intOrNull($this->post('usage_limit')),
            'per_user_limit' => max(1, (int) $this->num($this->post('per_user_limit'), 1)),
            'valid_from'     => $dt($this->post('valid_from')),
            'valid_to'       => $dt($this->post('valid_to')),
            'funding_source' => $funding,
            'stacking'       => $this->flag($this->post('stacking')),
            'is_active'      => $this->flag($this->post('is_active')),
        ];
        if ($id !== null) {
            $exists = Database::fetch('SELECT id FROM coupons WHERE id = ? AND society_id = ?', [$id, $sid]);
            if (!$exists) {
                flash('error', 'Coupon not found in your society.');
                $this->redirect('/admin/coupons');
            }
            Database::run(
                'UPDATE coupons SET code=?, title=?, type=?, value=?, min_cart_value=?, max_discount=?, usage_limit=?, per_user_limit=?, valid_from=?, valid_to=?, funding_source=?, stacking=?, is_active=? WHERE id=?',
                [...array_values($data), $id]
            );
            Audit::log('coupon.updated', 'coupon', $id, ['code' => $code, 'society_id' => $sid, 'value' => $data['value']]);
            flash('success', 'Coupon updated.');
        } else {
            Database::run(
                'INSERT INTO coupons (code, title, type, value, min_cart_value, max_discount, usage_limit, per_user_limit, valid_from, valid_to, society_id, funding_source, stacking, is_active)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                [...array_values($data), $sid]
            );
            Audit::log('coupon.created', 'coupon', (int) Database::pdo()->lastInsertId(), ['code' => $code, 'society_id' => $sid, 'value' => $data['value']]);
            flash('success', 'Coupon "' . $code . '" created for your society.');
        }
        $this->redirect('/admin/coupons');
    }

    public function toggleCoupon(string $id): void
    {
        $sid = $this->sid();
        $cid = $this->intOrNull($id);
        $c   = $cid ? Database::fetch('SELECT id, code, is_active FROM coupons WHERE id = ? AND society_id = ?', [$cid, $sid]) : null;
        if (!$c) {
            flash('error', 'Coupon not found in your society.');
            $this->redirect('/admin/coupons');
        }
        $new = (int) $c['is_active'] === 1 ? 0 : 1;
        Database::run('UPDATE coupons SET is_active = ? WHERE id = ?', [$new, $cid]);
        Audit::log('coupon.' . ($new === 1 ? 'enabled' : 'disabled'), 'coupon', (int) $c['id'], ['code' => $c['code'], 'society_id' => $sid]);
        flash('success', 'Coupon "' . $c['code'] . '" ' . ($new === 1 ? 'enabled' : 'disabled') . '.');
        $this->redirect('/admin/coupons');
    }

    /* ================================================================
       REPORTS (society-scoped)
       ================================================================ */

    public function reports(): void
    {
        $sid = $this->sid();
        [$from, $to, $toSql] = $this->reportRange();
        $fromSql = $from . ' 00:00:00';

        $revRows = Database::fetchAll(
            "SELECT DATE(created_at) d, SUM(total) v FROM orders
             WHERE society_id = ? AND status <> 'cancelled' AND created_at >= ? AND created_at <= ?
             GROUP BY DATE(created_at)",
            [$sid, $fromSql, $toSql]
        );
        $revSeries = $this->fillDays($revRows, $from, $to, 'v');

        $topProducts = Database::fetchAll(
            "SELECT oi.product_name, SUM(oi.qty) qty, SUM(oi.line_total) revenue
             FROM order_items oi JOIN orders o ON o.id = oi.order_id
             WHERE o.society_id = ? AND o.status <> 'cancelled' AND o.created_at >= ? AND o.created_at <= ?
             GROUP BY oi.product_name ORDER BY qty DESC LIMIT 8",
            [$sid, $fromSql, $toSql]
        );

        $byMethod = Database::fetchAll(
            "SELECT p.method, COUNT(*) c, COALESCE(SUM(CASE WHEN p.status = 'verified' THEN p.amount ELSE 0 END),0) verified_amt
             FROM payments p LEFT JOIN orders o ON o.id = p.order_id
             WHERE COALESCE(p.society_id, o.society_id) = ? AND p.status <> 'failed' AND p.created_at >= ? AND p.created_at <= ?
             GROUP BY p.method ORDER BY verified_amt DESC",
            [$sid, $fromSql, $toSql]
        );

        $bookingsByStatus = Database::fetchAll(
            'SELECT status, COUNT(*) c FROM bookings WHERE society_id = ? AND created_at >= ? AND created_at <= ? GROUP BY status',
            [$sid, $fromSql, $toSql]
        );

        $savings = (float) Database::scalar(
            "SELECT COALESCE(SUM(oi.qty * GREATEST(pv.reference_price - oi.unit_price, 0)), 0)
             FROM order_items oi
             JOIN orders o ON o.id = oi.order_id
             JOIN product_variants pv ON pv.id = oi.variant_id
             WHERE o.society_id = ? AND o.type = 'campaign' AND o.status <> 'cancelled'",
            [$sid]
        );

        $grossSales = array_sum(array_map(static fn($r) => (float) $r['v'], $revSeries));

        $this->view('admin/reports', [
            'active'    => 'admin-reports',
            'pageTitle' => 'Society Reports',
            'useCharts' => true,
            'from'      => $from,
            'to'        => $to,
            'revSeries' => $revSeries,
            'topProducts' => $topProducts,
            'byMethod'  => $byMethod,
            'bookingsByStatus' => $bookingsByStatus,
            'savings'   => $savings,
            'grossSales' => $grossSales,
        ]);
    }

    public function exportCsv(): void
    {
        $sid  = $this->sid();
        $type = (string) $this->query('type', 'orders');
        [$from, $to, $toSql] = $this->reportRange();
        $fromSql = $from . ' 00:00:00';

        switch ($type) {
            case 'members':
                $rows = Database::fetchAll(
                    'SELECT m.id, u.name, u.email, u.phone, m.member_role, m.status, m.requested_at,
                            COALESCE(CONCAT_WS("-", NULLIF(f.block,""), f.flat_number), "—") flat
                     FROM memberships m
                     JOIN users u ON u.id = m.user_id
                     LEFT JOIN flats f ON f.id = m.flat_id
                     WHERE m.society_id = ? ORDER BY m.id ASC',
                    [$sid]
                );
                $out = array_map(static fn($r) => [$r['id'], $r['name'], $r['email'], $r['phone'], $r['member_role'], $r['status'], $r['requested_at'], $r['flat']], $rows);
                $this->sendCsv('members', ['ID', 'Name', 'Email', 'Phone', 'Role', 'Status', 'Requested At', 'Flat'], $out);

            case 'bookings':
                $rows = Database::fetchAll(
                    "SELECT b.id, b.booking_no, b.created_at, u.name resident, b.slot_at, b.price, b.status
                     FROM bookings b JOIN users u ON u.id = b.user_id
                     WHERE b.society_id = ? AND b.created_at >= ? AND b.created_at <= ?
                     ORDER BY b.id ASC",
                    [$sid, $fromSql, $toSql]
                );
                $out = array_map(static fn($r) => [$r['id'], $r['booking_no'], $r['created_at'], $r['resident'], $r['slot_at'], $r['price'], $r['status']], $rows);
                $this->sendCsv('bookings', ['ID', 'Booking No', 'Created', 'Resident', 'Slot', 'Price', 'Status'], $out);

            case 'payments':
                $rows = Database::fetchAll(
                    "SELECT p.id, p.created_at, u.name payer, p.method, p.amount, p.status,
                            COALESCE(o.order_no, b.booking_no, '—') ref, COALESCE(p.utr,'') utr
                     FROM payments p
                     LEFT JOIN orders o ON o.id = p.order_id
                     LEFT JOIN bookings b ON b.id = p.booking_id
                     JOIN users u ON u.id = p.user_id
                     WHERE COALESCE(p.society_id, o.society_id, b.society_id) = ? AND p.created_at >= ? AND p.created_at <= ?
                     ORDER BY p.id ASC",
                    [$sid, $fromSql, $toSql]
                );
                $out = array_map(static fn($r) => [$r['id'], $r['created_at'], $r['payer'], $r['method'], $r['amount'], $r['status'], $r['ref'], $r['utr']], $rows);
                $this->sendCsv('payments', ['ID', 'Date', 'Paid By', 'Method', 'Amount', 'Status', 'Ref', 'UTR'], $out);

            default: // orders
                $rows = Database::fetchAll(
                    "SELECT o.id, o.order_no, o.created_at, u.name resident, o.type, o.status,
                            o.subtotal, o.discount, o.delivery_fee, o.tax, o.total, o.fulfilment
                     FROM orders o JOIN users u ON u.id = o.user_id
                     WHERE o.society_id = ? AND o.created_at >= ? AND o.created_at <= ?
                     ORDER BY o.id ASC",
                    [$sid, $fromSql, $toSql]
                );
                $out = array_map(static fn($r) => [$r['id'], $r['order_no'], $r['created_at'], $r['resident'], $r['type'], $r['status'], $r['subtotal'], $r['discount'], $r['delivery_fee'], $r['tax'], $r['total'], $r['fulfilment']], $rows);
                $this->sendCsv('orders', ['ID', 'Order No', 'Placed At', 'Resident', 'Type', 'Status', 'Subtotal', 'Discount', 'Delivery Fee', 'Tax', 'Total', 'Fulfilment'], $out);
        }
    }

    /* ================================================================
       SOCIETY SETTINGS
       ================================================================ */

    public function settings(): void
    {
        $sid = $this->sid();
        $society = Database::fetch('SELECT * FROM societies WHERE id = ? LIMIT 1', [$sid]);
        if (!$society) {
            flash('error', 'Society not found.');
            $this->redirect('/admin/dashboard');
        }
        $subscription = Database::fetch(
            'SELECT * FROM subscriptions WHERE society_id = ? ORDER BY id DESC LIMIT 1',
            [$sid]
        );
        $this->view('admin/settings', [
            'active'       => 'admin-settings',
            'pageTitle'    => 'Society Settings',
            'society'      => $society,
            'subscription' => $subscription,
        ]);
    }

    public function saveSettings(): void
    {
        $sid    = $this->sid();
        $society = Database::fetch('SELECT * FROM societies WHERE id = ? LIMIT 1', [$sid]);
        if (!$society) {
            flash('error', 'Society not found.');
            $this->redirect('/admin/dashboard');
        }

        $errors = $this->validate([
            'name'             => 'required|max:150',
            'city'             => 'max:80',
            'state'            => 'max:80',
            'address'          => 'max:255',
            'primary_color'    => 'required|max:9',
            'default_language' => 'in:en,hi,mr',
            'landing_text'     => 'max:400',
            'contact_email'    => 'email',
            'contact_phone'    => 'max:20',
            'upi_id'           => 'max:80',
            'delivery_charge'  => 'numeric',
            'payment_verifier' => 'in:society_admin,super_admin',
        ]);
        $color = (string) $this->post('primary_color', '#28A66A');
        if ($errors === [] && !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $errors['primary_color'] = 'Primary color must be a hex value like #28A66A.';
        }
        $errors2 = $errors;
        $logo    = $this->tryUploadImage($this->file('logo'), 'logos', $errors2, 'logo');
        $banner  = $this->tryUploadImage($this->file('banner'), 'banners', $errors2, 'banner');
        $qr      = $this->tryUploadImage($this->file('qr_image'), 'qr', $errors2, 'qr_image');
        if ($errors2 !== []) {
            $this->keepOld();
            $this->flashErrors($errors2);
            $this->back();
        }

        $data = [
            'name'             => (string) $this->post('name'),
            'city'             => $this->strOrNull($this->post('city')),
            'state'            => $this->strOrNull($this->post('state')),
            'address'          => $this->strOrNull($this->post('address')),
            'primary_color'    => $color,
            'default_language' => (string) $this->post('default_language', 'en'),
            'landing_text'     => $this->strOrNull($this->post('landing_text')),
            'contact_email'    => $this->strOrNull($this->post('contact_email')),
            'contact_phone'    => $this->strOrNull($this->post('contact_phone')),
            'upi_id'           => $this->strOrNull($this->post('upi_id')),
            'delivery_charge'  => $this->num($this->post('delivery_charge')),
            'pickup_enabled'   => $this->flag($this->post('pickup_enabled')),
            'delivery_enabled' => $this->flag($this->post('delivery_enabled')),
            'payment_verifier' => (string) $this->post('payment_verifier', 'society_admin'),
        ];
        if ($logo !== null) {
            $data['logo'] = $logo;
        }
        if ($banner !== null) {
            $data['banner'] = $banner;
        }
        if ($qr !== null) {
            $data['qr_image'] = $qr;
        }

        $set = implode(', ', array_map(static fn($k) => "$k = ?", array_keys($data)));
        Database::run("UPDATE societies SET $set, updated_at = NOW() WHERE id = ?", [...array_values($data), $sid]);
        Audit::log('society.settings', 'society', $sid, ['name' => $data['name'], 'payment_verifier' => $data['payment_verifier']]);
        flash('success', 'Society settings saved.');
        $this->redirect('/admin/settings');
    }
}
