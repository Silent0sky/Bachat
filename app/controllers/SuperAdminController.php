<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Core\Upload;
use App\Services\BulkGroceryService;
use App\Services\Notify;
use PDOException;
use RuntimeException;

/**
 * Super admin — platform console.
 * Owns: societies, users, catalog, vendors, coupons, subscriptions,
 * commissions, payments, refunds, reports, audit, settings.
 */
final class SuperAdminController extends Controller
{
    private const UNITS = ['g', 'kg', 'ml', 'l', 'pc', 'pack', 'bottle', 'bag', 'box'];
    private const ROLES = ['super_admin', 'society_admin', 'resident', 'vendor', 'delivery_staff'];

    /* ================================================================
       HELPERS
       ================================================================ */

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

    /** datetime-local / date input → MySQL datetime (null when empty/invalid). */
    private function dtLocal(?string $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $v = str_replace('T', ' ', $v);
        if (strlen($v) === 10) {
            $v .= ' 00:00:00';
        }
        if (strlen($v) === 16) {
            $v .= ':00';
        }
        return strtotime($v) === false ? null : $v;
    }

    private function uniqueSlug(string $table, string $base, int $ignoreId = 0): string
    {
        $base = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($base)), '-');
        if ($base === '') {
            $base = 'item';
        }
        $slug = $base;
        $i    = 2;
        while (true) {
            $row = Database::fetch("SELECT id FROM {$table} WHERE slug = ? AND id != ? LIMIT 1", [$slug, $ignoreId]);
            if (!$row) {
                return $slug;
            }
            $slug = $base . '-' . $i;
            $i++;
        }
    }

    /** @return array{0:string,1:string,2:string} from (Y-m-d), to (Y-m-d), toSql (Y-m-d 23:59:59) */
    private function reportRange(): array
    {
        $from = $this->query('from');
        $to   = $this->query('to');
        $from = (is_string($from) && strtotime($from)) ? date('Y-m-d', strtotime($from)) : date('Y-m-d', strtotime('-30 days'));
        $to   = (is_string($to) && strtotime($to)) ? date('Y-m-d', strtotime($to)) : date('Y-m-d');
        return [$from, $to, $to . ' 23:59:59'];
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

    /** Fill a per-day series so charts have continuous dates. */
    private function fillDays(array $rows, string $from, string $to, string $valKey): array
    {
        $map = [];
        foreach ($rows as $r) {
            $map[(string) $r['d']] = (float) ($r[$valKey] ?? 0);
        }
        $out  = [];
        $cur  = strtotime($from);
        $end  = strtotime($to);
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

    /* ================================================================
       DASHBOARD
       ================================================================ */

    public function dashboard(): void
    {
        $kpis = [
            'societies'       => (int) Database::scalar('SELECT COUNT(*) FROM societies WHERE status = "active"'),
            'users'           => (int) Database::scalar('SELECT COUNT(*) FROM users'),
            'orders_30d'      => (int) Database::scalar('SELECT COUNT(*) FROM orders WHERE status <> "cancelled" AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'),
            'revenue_30d'     => (float) Database::scalar('SELECT COALESCE(SUM(total),0) FROM orders WHERE status <> "cancelled" AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)'),
            'pay_pending'     => (int) Database::scalar('SELECT COUNT(*) FROM payments WHERE status = "submitted"'),
            'vendors_pending' => (int) Database::scalar('SELECT COUNT(*) FROM users WHERE role = "vendor" AND status = "pending"'),
            'tickets_open'    => (int) Database::scalar('SELECT COUNT(*) FROM support_tickets WHERE status IN ("open","in_progress","waiting","reopened")'),
        ];

        // Revenue by day — last 14 days
        $revRows = Database::fetchAll(
            'SELECT DATE(created_at) d, SUM(total) v FROM orders
             WHERE status <> "cancelled" AND created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             GROUP BY DATE(created_at)'
        );
        $rev14 = $this->fillDays($revRows, date('Y-m-d', strtotime('-13 days')), date('Y-m-d'), 'v');

        // Orders count by society
        $ordersBySociety = Database::fetchAll(
            'SELECT s.name, COUNT(o.id) c FROM societies s
             LEFT JOIN orders o ON o.society_id = s.id AND o.status <> "cancelled"
             GROUP BY s.id, s.name ORDER BY c DESC, s.name ASC'
        );

        // Top 6 products by qty sold (delivered orders)
        $topProducts = Database::fetchAll(
            'SELECT oi.product_name, SUM(oi.qty) q
             FROM order_items oi JOIN orders o ON o.id = oi.order_id
             WHERE o.status = "delivered"
             GROUP BY oi.product_name ORDER BY q DESC LIMIT 6'
        );

        $latestOrders = Database::fetchAll(
            'SELECT o.id, o.order_no, o.status, o.total, o.created_at, o.fulfilment, s.name society_name, u.name user_name
             FROM orders o
             JOIN societies s ON s.id = o.society_id
             JOIN users u ON u.id = o.user_id
             ORDER BY o.id DESC LIMIT 8'
        );

        $this->view('dashboards/super', [
            'active'         => 'super-dashboard',
            'pageTitle'      => 'Platform Dashboard',
            'kpis'           => $kpis,
            'rev14'          => $rev14,
            'ordersBySociety' => $ordersBySociety,
            'topProducts'    => $topProducts,
            'latestOrders'   => $latestOrders,
            'useCharts'      => true,
        ]);
    }

    /* ================================================================
       SOCIETIES
       ================================================================ */

    public function societies(): void
    {
        $editId = $this->query('edit');
        $edit   = null;
        if (is_string($editId) && $editId !== '') {
            $edit = $editId === 'new'
                ? ['id' => null, 'name' => '', 'code' => '', 'city' => '', 'state' => '', 'address' => '',
                   'logo' => null, 'banner' => null, 'primary_color' => '#28A66A', 'default_language' => 'en',
                   'status' => 'active', 'upi_id' => '', 'qr_image' => null, 'delivery_charge' => '0.00',
                   'pickup_enabled' => 1, 'delivery_enabled' => 1, 'payment_verifier' => 'society_admin',
                   'commission_type' => 'percent', 'commission_rate' => '2.50',
                   'subscription_plan' => 'none', 'subscription_amount' => '0.00',
                   'landing_text' => '', 'contact_email' => '', 'contact_phone' => '']
                : Database::fetch('SELECT * FROM societies WHERE id = ?', [$this->intOrNull($editId) ?? 0]);
            if ($edit === null) {
                flash('error', 'Society not found.');
                $this->redirect('/super/societies');
            }
        }

        $societies = Database::fetchAll(
            'SELECT s.*,
                (SELECT COUNT(*) FROM users u WHERE u.society_id = s.id) users_count,
                (SELECT COUNT(*) FROM memberships m WHERE m.society_id = s.id AND m.status = "active") members_count
             FROM societies s ORDER BY s.name ASC'
        );

        $this->view('super/societies', [
            'active'    => 'super-societies',
            'pageTitle' => 'Societies',
            'societies' => $societies,
            'edit'      => $edit,
        ]);
    }

    public function saveSociety(): void
    {
        $id     = $this->intOrNull($this->post('id'));
        $errors = $this->validate([
            'name'             => 'required|max:150',
            'code'             => 'required|max:30',
            'city'             => 'max:80',
            'state'            => 'max:80',
            'address'          => 'max:255',
            'primary_color'    => 'required|max:9',
            'default_language' => 'in:en,hi,mr',
            'status'           => 'in:active,suspended',
            'upi_id'           => 'max:80',
            'delivery_charge'  => 'numeric',
            'payment_verifier' => 'in:society_admin,super_admin',
            'commission_type'  => 'in:fixed,percent',
            'commission_rate'  => 'numeric',
            'subscription_plan'   => 'in:none,monthly,yearly',
            'subscription_amount' => 'numeric',
            'landing_text'     => 'max:400',
            'contact_email'    => 'email',
            'contact_phone'    => 'max:20',
        ]);

        $color = (string) $this->post('primary_color', '#28A66A');
        if ($errors === [] && !preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $errors['primary_color'] = 'Primary color must be a hex value like #28A66A.';
        }

        $code = strtoupper((string) $this->post('code'));
        $dup  = Database::fetch('SELECT id FROM societies WHERE code = ? AND id != ? LIMIT 1', [$code, $id ?? 0]);
        if ($errors === [] && $dup) {
            $errors['code'] = 'This society code is already used.';
        }

        $logoPath   = $this->tryUploadImage($this->file('logo'), 'logos', $errors, 'logo');
        $bannerPath = $this->tryUploadImage($this->file('banner'), 'banners', $errors, 'banner');

        if ($errors !== []) {
            $this->keepOld();
            $this->flashErrors($errors);
            $this->back();
        }

        $data = [
            'name'                => (string) $this->post('name'),
            'code'                => $code,
            'city'                => $this->strOrNull($this->post('city')),
            'state'               => $this->strOrNull($this->post('state')),
            'address'             => $this->strOrNull($this->post('address')),
            'primary_color'       => $color,
            'default_language'    => (string) $this->post('default_language', 'en'),
            'status'              => (string) $this->post('status', 'active'),
            'upi_id'              => $this->strOrNull($this->post('upi_id')),
            'delivery_charge'     => $this->num($this->post('delivery_charge')),
            'pickup_enabled'      => $this->flag($this->post('pickup_enabled')),
            'delivery_enabled'    => $this->flag($this->post('delivery_enabled')),
            'payment_verifier'    => (string) $this->post('payment_verifier', 'society_admin'),
            'commission_type'     => (string) $this->post('commission_type', 'percent'),
            'commission_rate'     => $this->num($this->post('commission_rate')),
            'subscription_plan'   => (string) $this->post('subscription_plan', 'none'),
            'subscription_amount' => $this->num($this->post('subscription_amount')),
            'landing_text'        => $this->strOrNull($this->post('landing_text')),
            'contact_email'       => $this->strOrNull($this->post('contact_email')),
            'contact_phone'       => $this->strOrNull($this->post('contact_phone')),
        ];

        if ($id !== null) {
            $current = Database::fetch('SELECT * FROM societies WHERE id = ? LIMIT 1', [$id]);
            if (!$current) {
                flash('error', 'Society not found.');
                $this->redirect('/super/societies');
            }
            if ($logoPath !== null) {
                $data['logo'] = $logoPath;
            }
            if ($bannerPath !== null) {
                $data['banner'] = $bannerPath;
            }
            $set = implode(', ', array_map(static fn($k) => "$k = ?", array_keys($data)));
            Database::run("UPDATE societies SET $set, updated_at = NOW() WHERE id = ?", [...array_values($data), $id]);
            Audit::log('society.updated', 'society', $id, ['name' => $data['name'], 'status' => $data['status']]);
            flash('success', 'Society updated.');
        } else {
            $data['slug'] = $this->uniqueSlug('societies', $data['name']);
            if ($logoPath !== null) {
                $data['logo'] = $logoPath;
            }
            if ($bannerPath !== null) {
                $data['banner'] = $bannerPath;
            }
            $cols = implode(', ', array_keys($data));
            $qs   = implode(', ', array_fill(0, count($data), '?'));
            Database::run("INSERT INTO societies ($cols) VALUES ($qs)", array_values($data));
            $newId = (int) Database::pdo()->lastInsertId();
            Audit::log('society.created', 'society', $newId, ['name' => $data['name'], 'code' => $data['code'], 'slug' => $data['slug']]);
            flash('success', 'Society "' . $data['name'] . '" created.');
            $id = $newId;
        }

        $this->redirect('/super/societies');
    }

    public function societyStatus(string $id): void
    {
        $sid  = $this->intOrNull($id);
        $soc  = $sid ? Database::fetch('SELECT id, name, status FROM societies WHERE id = ?', [$sid]) : null;
        if (!$soc) {
            flash('error', 'Society not found.');
            $this->redirect('/super/societies');
        }
        $status = (string) $this->post('status');
        if (!in_array($status, ['active', 'suspended'], true)) {
            $status = $soc['status'] === 'active' ? 'suspended' : 'active';
        }
        Database::run('UPDATE societies SET status = ?, updated_at = NOW() WHERE id = ?', [$status, $soc['id']]);
        Audit::log('society.' . ($status === 'active' ? 'activated' : 'suspended'), 'society', (int) $soc['id'], ['name' => $soc['name']]);
        if ($status === 'suspended') {
            Notify::societyAdmins((int) $soc['id'], 'society', 'Society suspended', 'Your society has been suspended by the platform. Contact support for details.', '/login');
        }
        flash('success', 'Society "' . $soc['name'] . '" is now ' . $status . '.');
        $this->redirect('/super/societies');
    }

    /* ================================================================
       USERS
       ================================================================ */

    public function users(): void
    {
        $role    = (string) $this->query('role', '');
        $soc     = $this->intOrNull($this->query('society'));
        $q       = (string) $this->query('q', '');

        $sql  = 'SELECT u.*, s.name society_name FROM users u LEFT JOIN societies s ON s.id = u.society_id WHERE 1=1';
        $par  = [];
        if (in_array($role, self::ROLES, true)) {
            $sql .= ' AND u.role = ?';
            $par[] = $role;
        }
        if ($soc !== null) {
            $sql .= ' AND u.society_id = ?';
            $par[] = $soc;
        }
        if ($q !== '') {
            $sql .= ' AND (u.name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)';
            $like = "%$q%";
            array_push($par, $like, $like, $like);
        }
        $sql .= ' ORDER BY u.created_at DESC LIMIT 200';
        $users = Database::fetchAll($sql, $par);

        $societies = Database::fetchAll('SELECT id, name FROM societies ORDER BY name ASC');

        $this->view('super/users', [
            'active'    => 'super-users',
            'pageTitle' => 'Users & Roles',
            'users'     => $users,
            'societies' => $societies,
            'fRole'     => $role,
            'fSociety'  => $soc,
            'fQ'        => $q,
            'edit'      => $this->editUser(),
        ]);
    }

    /** Row being edited (from ?edit=) or blank form for ?edit=new. */
    private function editUser(): ?array
    {
        $e = $this->query('edit');
        if (!is_string($e) || $e === '') {
            return null;
        }
        if ($e === 'new') {
            return ['id' => null, 'name' => '', 'email' => '', 'phone' => '', 'role' => 'resident',
                    'society_id' => null, 'status' => 'active', 'language' => 'en'];
        }
        return Database::fetch('SELECT id, name, email, phone, role, society_id, status, language FROM users WHERE id = ?', [$this->intOrNull($e) ?? 0]);
    }

    public function saveUser(): void
    {
        $id     = $this->intOrNull($this->post('id'));
        $errors = [];

        if ($id === null) {
            $errors = $this->validate([
                'name'     => 'required|max:120',
                'email'    => 'required|email|max:150',
                'role'     => 'in:' . implode(',', self::ROLES),
                'status'   => 'in:active,pending,suspended',
                'language' => 'in:en,hi,mr',
                'password' => 'required|min:6',
                'phone'    => 'max:20',
            ]);
            $email = strtolower((string) $this->post('email'));
            if ($errors === [] && Database::fetch('SELECT id FROM users WHERE email = ? LIMIT 1', [$email])) {
                $errors['email'] = 'This email is already registered.';
            }
            $role = (string) $this->post('role', 'resident');
            $sid  = $this->intOrNull($this->post('society_id'));
            if ($errors === [] && in_array($role, ['society_admin', 'resident', 'delivery_staff'], true) && $sid === null) {
                $errors['society_id'] = 'A society is required for this role.';
            }
            if ($errors === [] && $sid !== null && !Database::fetch('SELECT id FROM societies WHERE id = ?', [$sid])) {
                $errors['society_id'] = 'Invalid society.';
            }
            if ($errors !== []) {
                $this->keepOld();
                $this->flashErrors($errors);
                $this->back();
            }
            Database::run(
                'INSERT INTO users (name, email, phone, password_hash, role, society_id, status, language, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,?,?,NOW(),NOW())',
                [
                    (string) $this->post('name'),
                    $email,
                    $this->strOrNull($this->post('phone')),
                    password_hash((string) $this->post('password'), PASSWORD_DEFAULT),
                    $role,
                    $sid,
                    (string) $this->post('status', 'active'),
                    (string) $this->post('language', 'en'),
                ]
            );
            $newId = (int) Database::pdo()->lastInsertId();
            Audit::log('user.created', 'user', $newId, ['email' => $email, 'role' => $role, 'society_id' => $sid]);
            flash('success', 'User created.');
        } else {
            $errors = $this->validate([
                'name'     => 'required|max:120',
                'status'   => 'in:active,pending,suspended',
                'language' => 'in:en,hi,mr',
                'phone'    => 'max:20',
            ]);
            $target = Database::fetch('SELECT id, role FROM users WHERE id = ?', [$id]);
            if ($errors === [] && !$target) {
                $errors['name'] = 'User not found.';
            }
            $sid = $this->intOrNull($this->post('society_id'));
            if ($errors === [] && $sid !== null && !Database::fetch('SELECT id FROM societies WHERE id = ?', [$sid])) {
                $errors['society_id'] = 'Invalid society.';
            }
            if ($errors !== []) {
                $this->keepOld();
                $this->flashErrors($errors);
                $this->back();
            }
            Database::run(
                'UPDATE users SET name = ?, phone = ?, society_id = ?, status = ?, language = ?, updated_at = NOW() WHERE id = ?',
                [
                    (string) $this->post('name'),
                    $this->strOrNull($this->post('phone')),
                    $sid,
                    (string) $this->post('status', 'active'),
                    (string) $this->post('language', 'en'),
                    $id,
                ]
            );
            Audit::log('user.updated', 'user', $id, ['status' => (string) $this->post('status'), 'society_id' => $sid]);
            flash('success', 'User updated.');
        }

        $this->redirect('/super/users');
    }

    public function userStatus(string $id): void
    {
        $uid = $this->intOrNull($id);
        $u   = $uid ? Database::fetch('SELECT id, name, status FROM users WHERE id = ?', [$uid]) : null;
        if (!$u) {
            flash('error', 'User not found.');
            $this->redirect('/super/users');
        }
        if ((int) $u['id'] === Auth::id()) {
            flash('error', 'You cannot change your own status here.');
            $this->redirect('/super/users');
        }
        $status = (string) $this->post('status');
        if (!in_array($status, ['active', 'suspended'], true)) {
            $status = $u['status'] === 'active' ? 'suspended' : 'active';
        }
        Database::run('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?', [$status, $u['id']]);
        Audit::log('user.' . ($status === 'active' ? 'activated' : 'suspended'), 'user', (int) $u['id'], ['name' => $u['name']]);
        Notify::user((int) $u['id'], 'account', $status === 'suspended' ? 'Account suspended' : 'Account re-activated',
            $status === 'suspended' ? 'Your account was suspended by the platform admin.' : 'Your account is active again.', null);
        flash('success', 'User "' . $u['name'] . '" is now ' . $status . '.');
        $this->redirect('/super/users');
    }

    public function resetPassword(string $id): void
    {
        $uid = $this->intOrNull($id);
        $u   = $uid ? Database::fetch('SELECT id, name, email FROM users WHERE id = ?', [$uid]) : null;
        if (!$u) {
            flash('error', 'User not found.');
            $this->redirect('/super/users');
        }
        $temp = 'Bch' . random_int(100000, 999999);
        Database::run('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?', [password_hash($temp, PASSWORD_DEFAULT), $u['id']]);
        // NOTE: password value is intentionally NOT written to the audit log.
        Audit::log('user.password_reset', 'user', (int) $u['id'], ['email' => $u['email']]);
        Notify::user((int) $u['id'], 'account', 'Your password was reset',
            'A temporary password was set for your account. Please login and change it immediately from Profile.', '/profile');
        flash('info', 'Temporary password for ' . $u['email'] . ': ' . $temp . ' — share it securely and ask the user to change it after login.');
        $this->redirect('/super/users');
    }

    /* ================================================================
       CATALOG (categories | brands | products | variants | prices)
       ================================================================ */

    public function catalog(): void
    {
        $tab = (string) $this->query('tab', 'categories');
        if (!in_array($tab, ['categories', 'brands', 'products', 'variants', 'prices', 'bulk'], true)) {
            $tab = 'categories';
        }

        $data = [
            'active'    => 'super-catalog',
            'pageTitle' => 'Catalog',
            'tab'       => $tab,
        ];

        if ($tab === 'categories') {
            $cats       = Database::fetchAll('SELECT * FROM categories ORDER BY parent_id IS NULL DESC, sort_order ASC, name_en ASC');
            $parents    = array_values(array_filter($cats, static fn($c) => $c['parent_id'] === null));
            $byParent   = [];
            foreach ($cats as $c) {
                if ($c['parent_id'] !== null) {
                    $byParent[(int) $c['parent_id']][] = $c;
                }
            }
            $editId = $this->intOrNull($this->query('edit'));
            $data['parents']  = $parents;
            $data['byParent'] = $byParent;
            $data['allCats']  = $cats;
            $data['editCat']  = $editId ? Database::fetch('SELECT * FROM categories WHERE id = ?', [$editId]) : null;
        } elseif ($tab === 'brands') {
            $data['brands'] = Database::fetchAll('SELECT * FROM brands ORDER BY name ASC LIMIT 300');
            $editId = $this->intOrNull($this->query('edit'));
            $data['editBrand'] = $editId ? Database::fetch('SELECT * FROM brands WHERE id = ?', [$editId]) : null;
        } elseif ($tab === 'products') {
            $data['products'] = Database::fetchAll(
                'SELECT p.*, c.name_en category_name, u.name vendor_name,
                        (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) variant_count
                 FROM products p
                 JOIN categories c ON c.id = p.category_id
                 LEFT JOIN users u ON u.id = p.vendor_id
                 ORDER BY p.name_en ASC LIMIT 300'
            );
            $data['categories'] = Database::fetchAll('SELECT id, name_en, parent_id FROM categories ORDER BY parent_id IS NULL DESC, sort_order ASC, name_en ASC');
            $data['vendors']    = Database::fetchAll('SELECT id, name FROM users WHERE role = "vendor" ORDER BY name ASC');
            $editId = $this->intOrNull($this->query('edit'));
            $data['editProduct'] = $editId ? Database::fetch('SELECT * FROM products WHERE id = ?', [$editId]) : null;
        } elseif ($tab === 'variants') {
            $data['variants'] = Database::fetchAll(
                'SELECT v.*, p.name_en product_name, b.name brand_name
                 FROM product_variants v
                 JOIN products p ON p.id = v.product_id
                 LEFT JOIN brands b ON b.id = v.brand_id
                 ORDER BY p.name_en ASC, v.pack_size ASC LIMIT 300'
            );
            $data['products'] = Database::fetchAll('SELECT id, name_en FROM products ORDER BY name_en ASC');
            $data['brands']   = Database::fetchAll('SELECT id, name FROM brands ORDER BY name ASC');
            $editId = $this->intOrNull($this->query('edit'));
            $data['editVariant'] = $editId ? Database::fetch('SELECT * FROM product_variants WHERE id = ?', [$editId]) : null;
        } elseif ($tab === 'bulk') {
            $data['societies']       = Database::fetchAll('SELECT id, name, code FROM societies ORDER BY name ASC');
            $data['totalProducts']   = (int) Database::scalar('SELECT COUNT(*) FROM products');
            $data['totalVariants']   = (int) Database::scalar('SELECT COUNT(*) FROM product_variants');
            $data['totalCategories'] = (int) Database::scalar('SELECT COUNT(*) FROM categories');
            $data['totalBrands']     = (int) Database::scalar('SELECT COUNT(*) FROM brands');
            $data['uploadStats']     = Session::get('bulk_upload_stats');
            Session::forget('bulk_upload_stats');
        } else { // prices
            $data['societies'] = Database::fetchAll('SELECT id, name FROM societies ORDER BY name ASC');
            $data['variants']  = Database::fetchAll(
                'SELECT v.id, v.pack_size, v.unit, v.selling_price, p.name_en product_name, b.name brand_name
                 FROM product_variants v
                 JOIN products p ON p.id = v.product_id
                 LEFT JOIN brands b ON b.id = v.brand_id
                 WHERE v.is_active = 1 ORDER BY p.name_en ASC, v.pack_size ASC LIMIT 500'
            );
            $data['overrides'] = Database::fetchAll(
                'SELECT sp.*, s.name society_name, p.name_en product_name, v.pack_size, v.unit, v.selling_price base_price, b.name brand_name
                 FROM society_prices sp
                 JOIN societies s ON s.id = sp.society_id
                 JOIN product_variants v ON v.id = sp.variant_id
                 JOIN products p ON p.id = v.product_id
                 LEFT JOIN brands b ON b.id = v.brand_id
                 ORDER BY s.name ASC, p.name_en ASC LIMIT 300'
            );
        }

        $this->view('super/catalog', $data);
    }

    public function catalogTemplate(): void
    {
        BulkGroceryService::downloadSampleTemplate();
    }

    public function bulkUploadGroceries(): void
    {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            flash('error', 'Please select a valid CSV file to upload.');
            $this->redirect('/super/catalog?tab=bulk');
        }

        $file = (string) $_FILES['csv_file']['tmp_name'];
        $societyId = $this->intOrNull($this->post('society_id'));
        $updateExisting = (bool) $this->post('update_existing', 1);

        $stats = BulkGroceryService::processUpload($file, $societyId, $updateExisting);
        Session::set('bulk_upload_stats', $stats);

        if ($stats['total_rows'] > 0 && empty($stats['errors'])) {
            flash('success', "Bulk grocery upload completed! Added {$stats['products_created']} products, created {$stats['variants_created']} pack sizes, updated {$stats['products_updated']} products.");
        } elseif ($stats['total_rows'] > 0) {
            flash('info', "Bulk grocery upload completed with warnings: {$stats['products_created']} products created, {$stats['variants_created']} pack sizes created. " . count($stats['errors']) . " row(s) had errors.");
        } else {
            flash('error', 'No grocery rows could be processed. Please check the CSV column format.');
        }

        $this->redirect('/super/catalog?tab=bulk');
    }

    public function saveCategory(): void
    {
        $id     = $this->intOrNull($this->post('id'));
        $errors = $this->validate([
            'name_en'    => 'required|max:100',
            'name_hi'    => 'max:100',
            'name_mr'    => 'max:100',
            'icon'       => 'max:40',
            'sort_order' => 'int',
        ]);
        $parentId = $this->intOrNull($this->post('parent_id'));
        if ($errors === [] && $parentId !== null) {
            if ($parentId === $id) {
                $errors['parent_id'] = 'A category cannot be its own parent.';
            } elseif (!Database::fetch('SELECT id FROM categories WHERE id = ?', [$parentId])) {
                $errors['parent_id'] = 'Parent category not found.';
            }
        }
        if ($errors !== []) {
            $this->keepOld();
            $this->flashErrors($errors);
            $this->back();
        }
        $data = [
            'parent_id'  => $parentId,
            'name_en'    => (string) $this->post('name_en'),
            'name_hi'    => $this->strOrNull($this->post('name_hi')),
            'name_mr'    => $this->strOrNull($this->post('name_mr')),
            'icon'       => $this->strOrNull($this->post('icon')),
            'sort_order' => (int) $this->num($this->post('sort_order')),
            'is_active'  => $this->flag($this->post('is_active')),
        ];
        if ($id !== null) {
            Database::run('UPDATE categories SET parent_id = ?, name_en = ?, name_hi = ?, name_mr = ?, icon = ?, sort_order = ?, is_active = ? WHERE id = ?',
                [...array_values($data), $id]);
            Audit::log('category.updated', 'category', $id, ['name' => $data['name_en']]);
            flash('success', 'Category updated.');
        } else {
            Database::run('INSERT INTO categories (parent_id, name_en, name_hi, name_mr, icon, sort_order, is_active) VALUES (?,?,?,?,?,?,?)', array_values($data));
            Audit::log('category.created', 'category', (int) Database::pdo()->lastInsertId(), ['name' => $data['name_en']]);
            flash('success', 'Category created.');
        }
        $this->redirect('/super/catalog?tab=categories');
    }

    public function deleteCategory(string $id): void
    {
        $cid = $this->intOrNull($id);
        $cat = $cid ? Database::fetch('SELECT id, name_en FROM categories WHERE id = ?', [$cid]) : null;
        if (!$cat) {
            flash('error', 'Category not found.');
            $this->redirect('/super/catalog?tab=categories');
        }
        $products = (int) Database::scalar('SELECT COUNT(*) FROM products WHERE category_id = ?', [$cid]);
        if ($products > 0) {
            flash('error', 'Cannot delete "' . $cat['name_en'] . '" — ' . $products . ' product(s) are assigned to it.');
            $this->redirect('/super/catalog?tab=categories');
        }
        $children = (int) Database::scalar('SELECT COUNT(*) FROM categories WHERE parent_id = ?', [$cid]);
        if ($children > 0) {
            flash('error', 'Cannot delete "' . $cat['name_en'] . '" — it has ' . $children . ' subcategory(ies). Delete them first.');
            $this->redirect('/super/catalog?tab=categories');
        }
        try {
            Database::run('DELETE FROM categories WHERE id = ?', [$cid]);
            Audit::log('category.deleted', 'category', (int) $cat['id'], ['name' => $cat['name_en']]);
            flash('success', 'Category deleted.');
        } catch (PDOException) {
            flash('error', 'Category could not be deleted (referenced by other data).');
        }
        $this->redirect('/super/catalog?tab=categories');
    }

    public function saveBrand(): void
    {
        $id     = $this->intOrNull($this->post('id'));
        $errors = $this->validate(['name' => 'required|max:100']);
        $name   = (string) $this->post('name');
        if ($errors === [] && Database::fetch('SELECT id FROM brands WHERE name = ? AND id != ? LIMIT 1', [$name, $id ?? 0])) {
            $errors['name'] = 'A brand with this name already exists.';
        }
        $errors2 = $errors;
        $logo    = $this->tryUploadImage($this->file('logo'), 'brands', $errors2, 'logo');
        if ($errors2 !== []) {
            $this->keepOld();
            $this->flashErrors($errors2);
            $this->back();
        }
        if ($id !== null) {
            if ($logo !== null) {
                Database::run('UPDATE brands SET name = ?, logo = ? WHERE id = ?', [$name, $logo, $id]);
            } else {
                Database::run('UPDATE brands SET name = ? WHERE id = ?', [$name, $id]);
            }
            Audit::log('brand.updated', 'brand', $id, ['name' => $name]);
            flash('success', 'Brand updated.');
        } else {
            Database::run('INSERT INTO brands (name, logo) VALUES (?,?)', [$name, $logo]);
            Audit::log('brand.created', 'brand', (int) Database::pdo()->lastInsertId(), ['name' => $name]);
            flash('success', 'Brand created.');
        }
        $this->redirect('/super/catalog?tab=brands');
    }

    public function saveProduct(): void
    {
        $id     = $this->intOrNull($this->post('id'));
        $errors = $this->validate([
            'name_en'     => 'required|max:150',
            'name_hi'     => 'max:150',
            'name_mr'     => 'max:150',
            'category_id' => 'required|int',
            'unit'        => 'required|max:10',
            'fulfillment' => 'in:central,vendor_direct',
            'sku'         => 'max:40',
        ]);
        $catId = $this->intOrNull($this->post('category_id'));
        if ($errors === [] && !Database::fetch('SELECT id FROM categories WHERE id = ?', [$catId ?? 0])) {
            $errors['category_id'] = 'Category not found.';
        }
        $vendorId = $this->intOrNull($this->post('vendor_id'));
        if ($errors === [] && $vendorId !== null) {
            $v = Database::fetch('SELECT id FROM users WHERE id = ? AND role = "vendor"', [$vendorId]);
            if (!$v) {
                $errors['vendor_id'] = 'Vendor not found.';
            }
        }
        $errors2 = $errors;
        $image   = $this->tryUploadImage($this->file('image'), 'products', $errors2, 'image');
        if ($errors2 !== []) {
            $this->keepOld();
            $this->flashErrors($errors2);
            $this->back();
        }

        $nameEn = (string) $this->post('name_en');
        if ($id !== null) {
            $current = Database::fetch('SELECT * FROM products WHERE id = ?', [$id]);
            if (!$current) {
                flash('error', 'Product not found.');
                $this->redirect('/super/catalog?tab=products');
            }
            $data = [
                'category_id' => $catId,
                'name_en'     => $nameEn,
                'name_hi'     => $this->strOrNull($this->post('name_hi')),
                'name_mr'     => $this->strOrNull($this->post('name_mr')),
                'sku'         => $this->strOrNull($this->post('sku')),
                'description' => $this->strOrNull($this->post('description')),
                'unit'        => (string) $this->post('unit', 'pc'),
                'fulfillment' => (string) $this->post('fulfillment', 'central'),
                'vendor_id'   => $vendorId,
                'is_active'   => $this->flag($this->post('is_active')),
            ];
            if ($image !== null) {
                $data['image'] = $image;
            }
            $set = implode(', ', array_map(static fn($k) => "$k = ?", array_keys($data)));
            Database::run("UPDATE products SET $set, updated_at = NOW() WHERE id = ?", [...array_values($data), $id]);
            Audit::log('product.updated', 'product', $id, ['name' => $nameEn]);
            flash('success', 'Product updated.');
        } else {
            $slug = $this->uniqueSlug('products', $nameEn);
            $data = [
                'category_id' => $catId,
                'name_en'     => $nameEn,
                'name_hi'     => $this->strOrNull($this->post('name_hi')),
                'name_mr'     => $this->strOrNull($this->post('name_mr')),
                'slug'        => $slug,
                'sku'         => $this->strOrNull($this->post('sku')),
                'description' => $this->strOrNull($this->post('description')),
                'image'       => $image,
                'unit'        => (string) $this->post('unit', 'pc'),
                'fulfillment' => (string) $this->post('fulfillment', 'central'),
                'vendor_id'   => $vendorId,
                'is_active'   => $this->flag($this->post('is_active')),
            ];
            $cols = implode(', ', array_keys($data));
            $qs   = implode(', ', array_fill(0, count($data), '?'));
            Database::run("INSERT INTO products ($cols) VALUES ($qs)", array_values($data));
            Audit::log('product.created', 'product', (int) Database::pdo()->lastInsertId(), ['name' => $nameEn, 'slug' => $slug]);
            flash('success', 'Product created.');
        }
        $this->redirect('/super/catalog?tab=products');
    }

    public function deleteProduct(string $id): void
    {
        $pid = $this->intOrNull($id);
        $p   = $pid ? Database::fetch('SELECT id, name_en FROM products WHERE id = ?', [$pid]) : null;
        if (!$p) {
            flash('error', 'Product not found.');
            $this->redirect('/super/catalog?tab=products');
        }
        $variantCount = (int) Database::scalar('SELECT COUNT(*) FROM product_variants WHERE product_id = ?', [$pid]);
        $orderCount   = (int) Database::scalar(
            'SELECT COUNT(*) FROM order_items oi JOIN product_variants v ON v.id = oi.variant_id WHERE v.product_id = ?',
            [$pid]
        );
        if ($variantCount > 0 || $orderCount > 0) {
            Database::run('UPDATE products SET is_active = 0, updated_at = NOW() WHERE id = ?', [$pid]);
            Audit::log('product.deactivated', 'product', (int) $p['id'], ['name' => $p['name_en'], 'variants' => $variantCount, 'orders' => $orderCount]);
            flash('info', 'Product is referenced by variants/orders — deactivated instead of deleted.');
            $this->redirect('/super/catalog?tab=products');
        }
        try {
            Database::run('DELETE FROM products WHERE id = ?', [$pid]);
            Audit::log('product.deleted', 'product', (int) $p['id'], ['name' => $p['name_en']]);
            flash('success', 'Product deleted.');
        } catch (PDOException) {
            Database::run('UPDATE products SET is_active = 0, updated_at = NOW() WHERE id = ?', [$pid]);
            Audit::log('product.deactivated', 'product', (int) $p['id'], ['name' => $p['name_en']]);
            flash('info', 'Product could not be hard-deleted — deactivated instead.');
        }
        $this->redirect('/super/catalog?tab=products');
    }

    public function saveVariant(): void
    {
        $id     = $this->intOrNull($this->post('id'));
        $errors = $this->validate([
            'product_id'      => 'required|int',
            'pack_size'       => 'required|numeric',
            'unit'            => 'required|in:' . implode(',', self::UNITS),
            'sku'             => 'max:40',
            'reference_price' => 'required|numeric',
            'selling_price'   => 'required|numeric',
            'stock_qty'       => 'required|numeric',
            'min_qty'         => 'required|numeric',
            'max_qty'         => 'numeric',
        ]);
        $productId = $this->intOrNull($this->post('product_id'));
        if ($errors === [] && !Database::fetch('SELECT id FROM products WHERE id = ?', [$productId ?? 0])) {
            $errors['product_id'] = 'Product not found.';
        }
        $brandId = $this->intOrNull($this->post('brand_id'));
        if ($errors === [] && $brandId !== null && !Database::fetch('SELECT id FROM brands WHERE id = ?', [$brandId])) {
            $errors['brand_id'] = 'Brand not found.';
        }
        if ($errors !== []) {
            $this->keepOld();
            $this->flashErrors($errors);
            $this->back();
        }
        $data = [
            'product_id'      => $productId,
            'brand_id'        => $brandId,
            'pack_size'       => $this->num($this->post('pack_size'), 1),
            'unit'            => (string) $this->post('unit', 'pc'),
            'sku'             => $this->strOrNull($this->post('sku')),
            'reference_price' => $this->num($this->post('reference_price')),
            'selling_price'   => $this->num($this->post('selling_price')),
            'stock_qty'       => $this->num($this->post('stock_qty')),
            'min_qty'         => $this->num($this->post('min_qty'), 1),
            'max_qty'         => $this->strOrNull($this->post('max_qty')) === null ? null : $this->num($this->post('max_qty')),
            'is_active'       => $this->flag($this->post('is_active')),
        ];
        if ($id !== null) {
            Database::run(
                'UPDATE product_variants SET product_id=?, brand_id=?, pack_size=?, unit=?, sku=?, reference_price=?, selling_price=?, stock_qty=?, min_qty=?, max_qty=?, is_active=? WHERE id=?',
                [...array_values($data), $id]
            );
            Audit::log('variant.updated', 'variant', $id, ['product_id' => $productId, 'selling_price' => $data['selling_price']]);
            flash('success', 'Variant updated.');
        } else {
            Database::run(
                'INSERT INTO product_variants (product_id, brand_id, pack_size, unit, sku, reference_price, selling_price, stock_qty, min_qty, max_qty, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,?)',
                array_values($data)
            );
            Audit::log('variant.created', 'variant', (int) Database::pdo()->lastInsertId(), ['product_id' => $productId, 'selling_price' => $data['selling_price']]);
            flash('success', 'Variant created.');
        }
        $this->redirect('/super/catalog?tab=variants');
    }

    public function deleteVariant(string $id): void
    {
        $vid = $this->intOrNull($id);
        $v   = $vid ? Database::fetch('SELECT id, product_id FROM product_variants WHERE id = ?', [$vid]) : null;
        if (!$v) {
            flash('error', 'Variant not found.');
            $this->redirect('/super/catalog?tab=variants');
        }
        try {
            Database::run('DELETE FROM product_variants WHERE id = ?', [$vid]);
            Audit::log('variant.deleted', 'variant', (int) $v['id'], ['product_id' => $v['product_id']]);
            flash('success', 'Variant deleted.');
        } catch (PDOException) {
            Database::run('UPDATE product_variants SET is_active = 0 WHERE id = ?', [$vid]);
            Audit::log('variant.deactivated', 'variant', (int) $v['id'], ['product_id' => $v['product_id']]);
            flash('info', 'Variant is referenced by orders — deactivated instead of deleted.');
        }
        $this->redirect('/super/catalog?tab=variants');
    }

    public function saveSocietyPrice(): void
    {
        $action = (string) $this->post('action', 'save');

        if ($action === 'delete') {
            $pid  = $this->intOrNull($this->post('id'));
            $rule = $pid ? Database::fetch('SELECT * FROM society_prices WHERE id = ?', [$pid]) : null;
            if (!$rule) {
                flash('error', 'Price override not found.');
                $this->redirect('/super/catalog?tab=prices');
            }
            Database::run('DELETE FROM society_prices WHERE id = ?', [$pid]);
            Audit::log('price.override_removed', 'society_price', (int) $rule['id'], [
                'variant_id' => $rule['variant_id'], 'society_id' => $rule['society_id'],
            ]);
            flash('success', 'Price override removed — the variant falls back to its base selling price.');
            $this->redirect('/super/catalog?tab=prices');
        }

        $errors = $this->validate([
            'variant_id'    => 'required|int',
            'society_id'    => 'required|int',
            'selling_price' => 'required|numeric',
        ]);
        $variantId = $this->intOrNull($this->post('variant_id'));
        $societyId = $this->intOrNull($this->post('society_id'));
        if ($errors === [] && !Database::fetch('SELECT id FROM product_variants WHERE id = ?', [$variantId ?? 0])) {
            $errors['variant_id'] = 'Variant not found.';
        }
        if ($errors === [] && !Database::fetch('SELECT id FROM societies WHERE id = ?', [$societyId ?? 0])) {
            $errors['society_id'] = 'Society not found.';
        }
        if ($errors !== []) {
            $this->keepOld();
            $this->flashErrors($errors);
            $this->back();
        }
        $price = $this->num($this->post('selling_price'));
        Database::run(
            'INSERT INTO society_prices (variant_id, society_id, selling_price) VALUES (?,?,?)
             ON DUPLICATE KEY UPDATE selling_price = VALUES(selling_price)',
            [$variantId, $societyId, $price]
        );
        Audit::log('price.override', 'society_price', null, ['variant_id' => $variantId, 'society_id' => $societyId, 'selling_price' => $price]);
        Notify::societyAdmins((int) $societyId, 'pricing', 'Price override updated', 'A platform admin set a special price for one of your society\'s catalog items.', '/shop');
        flash('success', 'Society price saved.');
        $this->redirect('/super/catalog?tab=prices');
    }

    /* ================================================================
       VENDORS
       ================================================================ */

    public function vendors(): void
    {
        $vendors = Database::fetchAll(
            'SELECT u.*,
                (SELECT COUNT(*) FROM products p WHERE p.vendor_id = u.id) product_count,
                (SELECT COUNT(*) FROM quotations q WHERE q.vendor_id = u.id) quote_count
             FROM users u WHERE u.role = "vendor"
             ORDER BY u.status = "pending" DESC, u.name ASC'
        );
        $editId = $this->intOrNull($this->query('edit'));
        $this->view('super/vendors', [
            'active'    => 'super-vendors',
            'pageTitle' => 'Vendors',
            'vendors'   => $vendors,
            'edit'      => $editId ? Database::fetch('SELECT id, name, email, phone, status FROM users WHERE id = ? AND role = "vendor"', [$editId]) : null,
        ]);
    }

    public function saveVendor(): void
    {
        $id     = $this->intOrNull($this->post('id'));
        if ($id !== null) {
            $errors = $this->validate(['name' => 'required|max:120', 'phone' => 'max:20', 'status' => 'in:active,pending,suspended']);
            if ($errors !== []) {
                $this->keepOld();
                $this->flashErrors($errors);
                $this->back();
            }
            Database::run(
                'UPDATE users SET name = ?, phone = ?, status = ?, updated_at = NOW() WHERE id = ? AND role = "vendor"',
                [(string) $this->post('name'), $this->strOrNull($this->post('phone')), (string) $this->post('status', 'pending'), $id]
            );
            Audit::log('vendor.updated', 'user', $id, ['name' => (string) $this->post('name')]);
            flash('success', 'Vendor updated.');
            $this->redirect('/super/vendors');
        }

        $errors = $this->validate([
            'name'     => 'required|max:120',
            'email'    => 'required|email|max:150',
            'password' => 'required|min:6',
            'phone'    => 'max:20',
            'status'   => 'in:active,pending',
        ]);
        $email = strtolower((string) $this->post('email'));
        if ($errors === [] && Database::fetch('SELECT id FROM users WHERE email = ? LIMIT 1', [$email])) {
            $errors['email'] = 'This email is already registered.';
        }
        if ($errors !== []) {
            $this->keepOld();
            $this->flashErrors($errors);
            $this->back();
        }
        Database::run(
            'INSERT INTO users (name, email, phone, password_hash, role, society_id, status, language, created_at, updated_at)
             VALUES (?,?,?,?,"vendor",NULL,?,"en",NOW(),NOW())',
            [
                (string) $this->post('name'),
                $email,
                $this->strOrNull($this->post('phone')),
                password_hash((string) $this->post('password'), PASSWORD_DEFAULT),
                (string) $this->post('status', 'pending'),
            ]
        );
        Audit::log('vendor.created', 'user', (int) Database::pdo()->lastInsertId(), ['email' => $email]);
        flash('success', 'Vendor account created.');
        $this->redirect('/super/vendors');
    }

    public function vendorStatus(string $id): void
    {
        $vid = $this->intOrNull($id);
        $v   = $vid ? Database::fetch('SELECT id, name, email, status FROM users WHERE id = ? AND role = "vendor"', [$vid]) : null;
        if (!$v) {
            flash('error', 'Vendor not found.');
            $this->redirect('/super/vendors');
        }
        $status = (string) $this->post('status');
        if (!in_array($status, ['active', 'suspended'], true)) {
            $status = $v['status'] === 'active' ? 'suspended' : 'active';
        }
        Database::run('UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?', [$status, $v['id']]);
        Audit::log('vendor.' . ($status === 'active' ? 'approved' : 'suspended'), 'user', (int) $v['id'], ['name' => $v['name'], 'from' => $v['status']]);
        if ($status === 'active' && $v['status'] === 'pending') {
            Notify::user((int) $v['id'], 'vendor', 'Vendor account approved', 'Welcome to Bachat! Your vendor account is approved — you can now quote on campaigns.', '/vendor/dashboard');
        } elseif ($status === 'suspended') {
            Notify::user((int) $v['id'], 'vendor', 'Vendor account suspended', 'Your vendor account was suspended by the platform admin.', '/login');
        }
        flash('success', 'Vendor "' . $v['name'] . '" is now ' . $status . '.');
        $this->redirect('/super/vendors');
    }

    /* ================================================================
       COUPONS
       ================================================================ */

    public function coupons(): void
    {
        $coupons   = Database::fetchAll(
            'SELECT c.*, s.name society_name FROM coupons c LEFT JOIN societies s ON s.id = c.society_id ORDER BY c.id DESC LIMIT 200'
        );
        $societies = Database::fetchAll('SELECT id, name FROM societies ORDER BY name ASC');
        $editId    = $this->intOrNull($this->query('edit'));
        $this->view('super/coupons', [
            'active'    => 'super-coupons',
            'pageTitle' => 'Coupons',
            'coupons'   => $coupons,
            'societies' => $societies,
            'edit'      => $editId ? Database::fetch('SELECT * FROM coupons WHERE id = ?', [$editId]) : null,
        ]);
    }

    public function saveCoupon(): void
    {
        $id     = $this->intOrNull($this->post('id'));
        $errors = $this->validate([
            'code'          => 'required|max:40',
            'title'         => 'max:150',
            'type'          => 'in:fixed,percent',
            'value'         => 'required|numeric',
            'min_cart_value' => 'numeric',
            'max_discount'  => 'numeric',
            'per_user_limit' => 'int',
            'valid_from'    => 'date',
            'valid_to'      => 'date',
            'society_id'    => 'int',
            'funding_source' => 'in:platform,society,vendor',
        ]);
        $code = strtoupper((string) $this->post('code'));
        if ($errors === [] && Database::fetch('SELECT id FROM coupons WHERE code = ? AND id != ? LIMIT 1', [$code, $id ?? 0])) {
            $errors['code'] = 'This coupon code already exists.';
        }
        $societyId = $this->intOrNull($this->post('society_id'));
        if ($errors === [] && $societyId !== null && !Database::fetch('SELECT id FROM societies WHERE id = ?', [$societyId])) {
            $errors['society_id'] = 'Society not found.';
        }
        if ($errors !== []) {
            $this->keepOld();
            $this->flashErrors($errors);
            $this->back();
        }
        $data = [
            'code'           => $code,
            'title'          => $this->strOrNull($this->post('title')),
            'type'           => (string) $this->post('type', 'fixed'),
            'value'          => $this->num($this->post('value')),
            'min_cart_value' => $this->num($this->post('min_cart_value')),
            'max_discount'   => $this->strOrNull($this->post('max_discount')) === null ? null : $this->num($this->post('max_discount')),
            'usage_limit'    => $this->intOrNull($this->post('usage_limit')),
            'per_user_limit' => max(1, (int) $this->num($this->post('per_user_limit'), 1)),
            'valid_from'     => $this->dtLocal($this->post('valid_from')),
            'valid_to'       => $this->dtLocal($this->post('valid_to')),
            'society_id'     => $societyId,
            'funding_source' => (string) $this->post('funding_source', 'platform'),
            'stacking'       => $this->flag($this->post('stacking')),
            'is_active'      => $this->flag($this->post('is_active')),
        ];
        if ($id !== null) {
            Database::run(
                'UPDATE coupons SET code=?, title=?, type=?, value=?, min_cart_value=?, max_discount=?, usage_limit=?, per_user_limit=?, valid_from=?, valid_to=?, society_id=?, funding_source=?, stacking=?, is_active=? WHERE id=?',
                [...array_values($data), $id]
            );
            Audit::log('coupon.updated', 'coupon', $id, ['code' => $code, 'value' => $data['value']]);
            flash('success', 'Coupon updated.');
        } else {
            Database::run(
                'INSERT INTO coupons (code, title, type, value, min_cart_value, max_discount, usage_limit, per_user_limit, valid_from, valid_to, society_id, funding_source, stacking, is_active)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
                array_values($data)
            );
            Audit::log('coupon.created', 'coupon', (int) Database::pdo()->lastInsertId(), ['code' => $code, 'value' => $data['value']]);
            flash('success', 'Coupon "' . $code . '" created.');
        }
        $this->redirect('/super/coupons');
    }

    public function toggleCoupon(string $id): void
    {
        $cid = $this->intOrNull($id);
        $c   = $cid ? Database::fetch('SELECT id, code, is_active FROM coupons WHERE id = ?', [$cid]) : null;
        if (!$c) {
            flash('error', 'Coupon not found.');
            $this->redirect('/super/coupons');
        }
        $new = (int) $c['is_active'] === 1 ? 0 : 1;
        Database::run('UPDATE coupons SET is_active = ? WHERE id = ?', [$new, $cid]);
        Audit::log('coupon.' . ($new === 1 ? 'enabled' : 'disabled'), 'coupon', (int) $c['id'], ['code' => $c['code']]);
        flash('success', 'Coupon "' . $c['code'] . '" ' . ($new === 1 ? 'enabled' : 'disabled') . '.');
        $this->redirect(auth_role() === 'society_admin' ? '/admin/coupons' : '/super/coupons');
    }

    /* ================================================================
       SUBSCRIPTIONS
       ================================================================ */

    public function subscriptions(): void
    {
        $subs = Database::fetchAll(
            'SELECT sb.*, s.name society_name, s.code society_code
             FROM subscriptions sb JOIN societies s ON s.id = sb.society_id
             ORDER BY sb.id DESC LIMIT 200'
        );
        $expired = Database::fetchAll(
            'SELECT sb.*, s.name society_name FROM subscriptions sb JOIN societies s ON s.id = sb.society_id
             WHERE sb.status = "expired" ORDER BY sb.ends_at DESC LIMIT 100'
        );
        $this->view('super/subscriptions', [
            'active'    => 'super-subscriptions',
            'pageTitle' => 'Society Subscriptions',
            'subs'      => $subs,
            'expired'   => $expired,
            'societies' => Database::fetchAll('SELECT id, name, subscription_plan, subscription_amount FROM societies ORDER BY name ASC'),
        ]);
    }

    public function saveSubscription(): void
    {
        $errors = $this->validate([
            'society_id' => 'required|int',
            'plan'       => 'in:monthly,yearly',
            'amount'     => 'required|numeric',
            'starts_at'  => 'date',
            'ends_at'    => 'date',
            'note'       => 'max:255',
        ]);
        $societyId = $this->intOrNull($this->post('society_id'));
        if ($errors === [] && !Database::fetch('SELECT id FROM societies WHERE id = ?', [$societyId ?? 0])) {
            $errors['society_id'] = 'Society not found.';
        }
        if ($errors !== []) {
            $this->keepOld();
            $this->flashErrors($errors);
            $this->back();
        }
        $plan   = (string) $this->post('plan', 'monthly');
        $starts = $this->strOrNull($this->post('starts_at')) ?? date('Y-m-d');
        $ends   = $this->strOrNull($this->post('ends_at')) ?? date('Y-m-d', strtotime($starts . ' ' . ($plan === 'yearly' ? '+1 year' : '+1 month')));
        $amount = $this->num($this->post('amount'));
        $note   = $this->strOrNull($this->post('note'));

        Database::run(
            'INSERT INTO subscriptions (society_id, plan, amount, status, starts_at, ends_at, paid_at, note)
             VALUES (?,?,?,"active",?,?,NOW(),?)',
            [$societyId, $plan, $amount, $starts, $ends, $note]
        );
        $subId = (int) Database::pdo()->lastInsertId();
        Database::run('UPDATE societies SET subscription_plan = ?, subscription_amount = ?, updated_at = NOW() WHERE id = ?', [$plan, $amount, $societyId]);
        Audit::log('subscription.created', 'subscription', $subId, ['society_id' => $societyId, 'plan' => $plan, 'amount' => $amount, 'ends_at' => $ends]);
        Notify::societyAdmins((int) $societyId, 'subscription', 'Subscription activated',
            'Your ' . $plan . ' Bachat subscription is active until ' . $ends . '.', '/admin/settings');
        flash('success', 'Subscription activated.');
        $this->redirect('/super/subscriptions');
    }

    /* ================================================================
       COMMISSIONS
       ================================================================ */

    public function commissions(): void
    {
        $rows = Database::fetchAll(
            'SELECT cm.*, s.name society_name, o.order_no, b.booking_no
             FROM commissions cm
             JOIN societies s ON s.id = cm.society_id
             LEFT JOIN orders o ON o.id = cm.order_id
             LEFT JOIN bookings b ON b.id = cm.booking_id
             ORDER BY cm.id DESC LIMIT 200'
        );
        $totals = [
            'due'      => (float) Database::scalar('SELECT COALESCE(SUM(amount),0) FROM commissions WHERE status = "due"'),
            'recorded' => (float) Database::scalar('SELECT COALESCE(SUM(amount),0) FROM commissions WHERE status = "recorded"'),
        ];
        $this->view('super/commissions', [
            'active'    => 'super-commissions',
            'pageTitle' => 'Commissions',
            'rows'      => $rows,
            'totals'    => $totals,
            'societies' => Database::fetchAll('SELECT id, name FROM societies ORDER BY name ASC'),
        ]);
    }

    public function saveCommission(): void
    {
        $errors = $this->validate([
            'society_id' => 'required|int',
            'order_id'   => 'int',
            'booking_id' => 'int',
            'type'       => 'in:fixed,percent',
            'rate'       => 'numeric',
            'amount'     => 'required|numeric',
            'status'     => 'in:due,recorded',
        ]);
        $societyId = $this->intOrNull($this->post('society_id'));
        $orderId   = $this->intOrNull($this->post('order_id'));
        $bookingId = $this->intOrNull($this->post('booking_id'));
        if ($errors === [] && !Database::fetch('SELECT id FROM societies WHERE id = ?', [$societyId ?? 0])) {
            $errors['society_id'] = 'Society not found.';
        }
        if ($errors === [] && $orderId !== null && !Database::fetch('SELECT id FROM orders WHERE id = ?', [$orderId])) {
            $errors['order_id'] = 'Order not found.';
        }
        if ($errors === [] && $bookingId !== null && !Database::fetch('SELECT id FROM bookings WHERE id = ?', [$bookingId])) {
            $errors['booking_id'] = 'Booking not found.';
        }
        if ($errors !== []) {
            $this->keepOld();
            $this->flashErrors($errors);
            $this->back();
        }
        $data = [
            'order_id'   => $orderId,
            'booking_id' => $bookingId,
            'society_id' => $societyId,
            'vendor_id'  => $this->intOrNull($this->post('vendor_id')),
            'type'       => (string) $this->post('type', 'percent'),
            'rate'       => $this->num($this->post('rate')),
            'amount'     => $this->num($this->post('amount')),
            'status'     => (string) $this->post('status', 'due'),
        ];
        Database::run(
            'INSERT INTO commissions (order_id, booking_id, society_id, vendor_id, type, rate, amount, status) VALUES (?,?,?,?,?,?,?,?)',
            array_values($data)
        );
        Audit::log('commission.created', 'commission', (int) Database::pdo()->lastInsertId(), [
            'society_id' => $societyId, 'order_id' => $orderId, 'booking_id' => $bookingId, 'amount' => $data['amount'],
        ]);
        flash('success', 'Commission entry recorded.');
        $this->redirect('/super/commissions');
    }

    /* ================================================================
       PAYMENTS
       ================================================================ */

    public function payments(): void
    {
        $status = (string) $this->query('status', '');
        $soc    = $this->intOrNull($this->query('society'));

        $sql = 'SELECT p.*, o.order_no, b.booking_no, u.name payer_name, u.email payer_email, s.name society_name
                FROM payments p
                LEFT JOIN orders o ON o.id = p.order_id
                LEFT JOIN bookings b ON b.id = p.booking_id
                JOIN users u ON u.id = p.user_id
                LEFT JOIN societies s ON s.id = p.society_id
                WHERE 1=1';
        $par = [];
        if (in_array($status, ['pending', 'submitted', 'verified', 'failed', 'refunded'], true)) {
            $sql .= ' AND p.status = ?';
            $par[] = $status;
        }
        if ($soc !== null) {
            $sql .= ' AND p.society_id = ?';
            $par[] = $soc;
        }
        $sql .= ' ORDER BY p.id DESC LIMIT 200';
        $rows = Database::fetchAll($sql, $par);

        $this->view('super/payments', [
            'active'    => 'super-payments',
            'pageTitle' => 'Payment Verification',
            'rows'      => $rows,
            'fStatus'   => $status,
            'fSociety'  => $soc,
            'societies' => Database::fetchAll('SELECT id, name FROM societies ORDER BY name ASC'),
        ]);
    }

    public function verifyPayment(string $id): void
    {
        $pay = $this->intOrNull($id)
            ? Database::fetch('SELECT p.*, o.order_no, b.booking_no FROM payments p LEFT JOIN orders o ON o.id = p.order_id LEFT JOIN bookings b ON b.id = p.booking_id WHERE p.id = ?', [$this->intOrNull($id)])
            : null;
        if (!$pay) {
            flash('error', 'Payment not found.');
            $this->redirect('/super/payments');
        }
        if ($pay['status'] !== 'submitted') {
            flash('error', 'Only submitted payments can be verified.');
            $this->redirect('/super/payments');
        }
        Database::run(
            'UPDATE payments SET status = "verified", verified_by = ?, verified_at = NOW() WHERE id = ?',
            [Auth::id(), $pay['id']]
        );
        Audit::log('payment.verified', 'payment', (int) $pay['id'], [
            'amount' => $pay['amount'], 'method' => $pay['method'],
            'order_id' => $pay['order_id'], 'booking_id' => $pay['booking_id'],
        ]);
        Notify::user(
            (int) $pay['user_id'], 'payment', 'Payment verified',
            'Your payment of ' . money($pay['amount']) . ' was verified.' . ($pay['order_no'] ? ' Order ' . $pay['order_no'] . '.' : ''),
            $pay['order_id'] ? '/orders/' . $pay['order_id'] : ($pay['booking_id'] ? '/bookings' : null)
        );
        flash('success', 'Payment #' . $pay['id'] . ' verified.');
        $this->redirect('/super/payments');
    }

    public function rejectPayment(string $id): void
    {
        $pid = $this->intOrNull($id);
        $pay = $pid ? Database::fetch('SELECT * FROM payments WHERE id = ?', [$pid]) : null;
        if (!$pay) {
            flash('error', 'Payment not found.');
            $this->redirect('/super/payments');
        }
        if ($pay['status'] !== 'submitted') {
            flash('error', 'Only submitted payments can be rejected.');
            $this->redirect('/super/payments');
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
        $this->redirect('/super/payments');
    }

    /* ================================================================
       REFUNDS
       ================================================================ */

    public function refunds(): void
    {
        $rows = Database::fetchAll(
            'SELECT r.*, o.order_no, u.name processed_by_name, p.amount paid_amount
             FROM refunds r
             LEFT JOIN orders o ON o.id = r.order_id
             LEFT JOIN payments p ON p.id = r.payment_id
             LEFT JOIN users u ON u.id = r.processed_by
             ORDER BY r.id DESC LIMIT 200'
        );
        $orders = Database::fetchAll('SELECT id, order_no, total, user_id FROM orders ORDER BY id DESC LIMIT 200');
        $paymentsList = Database::fetchAll(
            'SELECT p.id, p.order_id, p.amount, o.order_no FROM payments p LEFT JOIN orders o ON o.id = p.order_id
             WHERE p.status = "verified" ORDER BY p.id DESC LIMIT 200'
        );
        $this->view('super/refunds', [
            'active'    => 'super-refunds',
            'pageTitle' => 'Refunds',
            'rows'      => $rows,
            'orders'    => $orders,
            'paymentsList' => $paymentsList,
        ]);
    }

    public function saveRefund(): void
    {
        $errors = $this->validate([
            'order_id'   => 'int',
            'payment_id' => 'int',
            'amount'     => 'required|numeric',
            'reason'     => 'max:255',
        ]);
        $orderId   = $this->intOrNull($this->post('order_id'));
        $paymentId = $this->intOrNull($this->post('payment_id'));

        if ($errors === [] && $paymentId !== null) {
            $pay = Database::fetch('SELECT id, order_id, amount FROM payments WHERE id = ?', [$paymentId]);
            if (!$pay) {
                $errors['payment_id'] = 'Payment not found.';
            } else {
                $orderId = $orderId ?? (int) $pay['order_id'];
            }
        }
        if ($errors === [] && $orderId === null) {
            $errors['order_id'] = 'Select an order or a payment for the refund.';
        }
        if ($errors === [] && $orderId !== null && !Database::fetch('SELECT id FROM orders WHERE id = ?', [$orderId])) {
            $errors['order_id'] = 'Order not found.';
        }
        if ($errors !== []) {
            $this->keepOld();
            $this->flashErrors($errors);
            $this->back();
        }

        $amount = $this->num($this->post('amount'));
        $reason = $this->strOrNull($this->post('reason'));

        Database::run(
            'INSERT INTO refunds (order_id, payment_id, booking_id, amount, reason, status, processed_by)
             VALUES (?,?,NULL,?,?,"processed",?)',
            [$orderId, $paymentId, $amount, $reason, Auth::id()]
        );
        $refundId = (int) Database::pdo()->lastInsertId();
        if ($paymentId !== null) {
            Database::run('UPDATE payments SET status = "refunded" WHERE id = ?', [$paymentId]);
        }
        Audit::log('refund.processed', 'refund', $refundId, [
            'order_id' => $orderId, 'payment_id' => $paymentId, 'amount' => $amount, 'reason' => $reason,
        ]);
        $uid = Database::scalar('SELECT user_id FROM orders WHERE id = ?', [$orderId ?? 0]);
        if ($uid) {
            Notify::user((int) $uid, 'refund', 'Refund processed', 'A refund of ' . money($amount) . ' was processed for your order.', '/orders');
        }
        flash('success', 'Refund of ' . money($amount) . ' recorded.');
        $this->redirect('/super/refunds');
    }

    /* ================================================================
       REPORTS
       ================================================================ */

    public function reports(): void
    {
        [$from, $to, $toSql] = $this->reportRange();
        $sid   = $this->intOrNull($this->query('society'));
        $scope = $sid !== null ? ' AND o.society_id = ' . $sid : '';
        // scope is built from an int-cast value only — safe for the queries below.
        $fromSql = $from . ' 00:00:00';

        $revRows = Database::fetchAll(
            "SELECT DATE(o.created_at) d, SUM(o.total) v, COUNT(*) c FROM orders o
             WHERE o.status <> 'cancelled' AND o.created_at >= ? AND o.created_at <= ?$scope
             GROUP BY DATE(o.created_at)",
            [$fromSql, $toSql]
        );
        $revSeries = $this->fillDays($revRows, $from, $to, 'v');

        $bySociety = Database::fetchAll(
            "SELECT s.name, COUNT(o.id) c, COALESCE(SUM(o.total),0) rev
             FROM societies s LEFT JOIN orders o ON o.society_id = s.id AND o.status <> 'cancelled'
               AND o.created_at >= ? AND o.created_at <= ?
             GROUP BY s.id, s.name ORDER BY rev DESC",
            [$fromSql, $toSql]
        );

        $topProducts = Database::fetchAll(
            "SELECT oi.product_name, SUM(oi.qty) qty, SUM(oi.line_total) revenue
             FROM order_items oi JOIN orders o ON o.id = oi.order_id
             WHERE o.status <> 'cancelled' AND o.created_at >= ? AND o.created_at <= ?$scope
             GROUP BY oi.product_name ORDER BY qty DESC LIMIT 8",
            [$fromSql, $toSql]
        );

        $byMethod = Database::fetchAll(
            "SELECT p.method, COUNT(*) c, COALESCE(SUM(CASE WHEN p.status = 'verified' THEN p.amount ELSE 0 END),0) verified_amt
             FROM payments p JOIN orders o ON o.id = p.order_id
             WHERE p.status <> 'failed' AND o.status <> 'cancelled' AND p.created_at >= ? AND p.created_at <= ?$scope
             GROUP BY p.method ORDER BY verified_amt DESC",
            [$fromSql, $toSql]
        );

        $commissionTotals = Database::fetchAll(
            'SELECT s.name, COALESCE(SUM(cm.amount),0) total,
                    COALESCE(SUM(CASE WHEN cm.status = "recorded" THEN cm.amount ELSE 0 END),0) recorded
             FROM commissions cm JOIN societies s ON s.id = cm.society_id
             WHERE cm.created_at >= ? AND cm.created_at <= ?' . ($sid !== null ? ' AND cm.society_id = ?' : '') . '
             GROUP BY s.id, s.name ORDER BY total DESC',
            $sid !== null ? [$fromSql, $toSql, $sid] : [$fromSql, $toSql]
        );

        $subRevenue = Database::fetchAll(
            'SELECT plan, COUNT(*) c, COALESCE(SUM(amount),0) total FROM subscriptions
             WHERE paid_at IS NOT NULL AND paid_at >= ? AND paid_at <= ?' . ($sid !== null ? ' AND society_id = ?' : '') . '
             GROUP BY plan',
            $sid !== null ? [$fromSql, $toSql, $sid] : [$fromSql, $toSql]
        );

        $grossSales = array_sum(array_map(static fn($r) => (float) $r['v'], $revSeries));

        $this->view('super/reports', [
            'active'    => 'super-reports',
            'pageTitle' => 'Platform Reports',
            'useCharts' => true,
            'from'      => $from,
            'to'        => $to,
            'fSociety'  => $sid,
            'societies' => Database::fetchAll('SELECT id, name FROM societies ORDER BY name ASC'),
            'revSeries' => $revSeries,
            'bySociety' => $bySociety,
            'topProducts' => $topProducts,
            'byMethod'  => $byMethod,
            'commissionTotals' => $commissionTotals,
            'subRevenue' => $subRevenue,
            'grossSales' => $grossSales,
        ]);
    }

    public function exportCsv(): void
    {
        $type = (string) $this->query('type', 'orders');
        [$from, $to, $toSql] = $this->reportRange();
        $fromSql = $from . ' 00:00:00';
        $sid     = $this->intOrNull($this->query('society'));
        // alias-aware society filter (value is int-cast above — safe to inline)
        $socEq   = static fn (string $expr): string => $sid !== null ? " AND {$expr} = {$sid}" : '';

        switch ($type) {
            case 'users':
                $rows = Database::fetchAll(
                    'SELECT u.id, u.name, u.email, u.phone, u.role, COALESCE(s.name,"—") society, u.status, u.language, u.created_at
                     FROM users u LEFT JOIN societies s ON s.id = u.society_id WHERE 1 = 1' . $socEq('u.society_id') . ' ORDER BY u.id ASC'
                );
                $out = array_map(static fn($r) => [$r['id'], $r['name'], $r['email'], $r['phone'], $r['role'], $r['society'], $r['status'], $r['language'], $r['created_at']], $rows);
                $this->sendCsv('users', ['ID', 'Name', 'Email', 'Phone', 'Role', 'Society', 'Status', 'Language', 'Created'], $out);

            case 'products':
                $rows = Database::fetchAll(
                    'SELECT p.id, p.name_en, c.name_en category, p.unit, p.fulfillment, COALESCE(u.name,"—") vendor,
                            (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) variants, p.is_active
                     FROM products p JOIN categories c ON c.id = p.category_id LEFT JOIN users u ON u.id = p.vendor_id
                     ORDER BY p.id ASC'
                );
                $out = array_map(static fn($r) => [$r['id'], $r['name_en'], $r['category'], $r['unit'], $r['fulfillment'], $r['vendor'], $r['variants'], $r['is_active'] ? 'active' : 'inactive'], $rows);
                $this->sendCsv('products', ['ID', 'Product', 'Category', 'Unit', 'Fulfilment', 'Vendor', 'Variants', 'Status'], $out);

            case 'commissions':
                $rows = Database::fetchAll(
                    'SELECT cm.id, s.name society, COALESCE(o.order_no,"—") order_no, COALESCE(b.booking_no,"—") booking_no,
                            cm.type, cm.rate, cm.amount, cm.status, cm.created_at
                     FROM commissions cm
                     JOIN societies s ON s.id = cm.society_id
                     LEFT JOIN orders o ON o.id = cm.order_id
                     LEFT JOIN bookings b ON b.id = cm.booking_id
                     WHERE cm.created_at >= ? AND cm.created_at <= ?' . $socEq('cm.society_id') . '
                     ORDER BY cm.id ASC',
                    [$fromSql, $toSql]
                );
                $out = array_map(static fn($r) => [$r['id'], $r['society'], $r['order_no'], $r['booking_no'], $r['type'], $r['rate'], $r['amount'], $r['status'], $r['created_at']], $rows);
                $this->sendCsv('commissions', ['ID', 'Society', 'Order', 'Booking', 'Type', 'Rate', 'Amount', 'Status', 'Created'], $out);

            case 'payments':
                $rows = Database::fetchAll(
                    'SELECT p.id, p.created_at, COALESCE(s.name,"—") society, u.name payer, p.method, p.amount, p.status,
                            COALESCE(o.order_no,"—") order_no, COALESCE(p.utr,"") utr
                     FROM payments p
                     LEFT JOIN societies s ON s.id = p.society_id
                     JOIN users u ON u.id = p.user_id
                     LEFT JOIN orders o ON o.id = p.order_id
                     WHERE p.created_at >= ? AND p.created_at <= ?' . $socEq('COALESCE(p.society_id, o.society_id)') . '
                     ORDER BY p.id ASC',
                    [$fromSql, $toSql]
                );
                $out = array_map(static fn($r) => [$r['id'], $r['created_at'], $r['society'], $r['payer'], $r['method'], $r['amount'], $r['status'], $r['order_no'], $r['utr']], $rows);
                $this->sendCsv('payments', ['ID', 'Date', 'Society', 'Paid by', 'Method', 'Amount', 'Status', 'Order', 'UTR'], $out);

            default: // orders
                $rows = Database::fetchAll(
                    'SELECT o.id, o.order_no, o.created_at, s.name society, u.name resident, o.type, o.status,
                            o.subtotal, o.discount, o.delivery_fee, o.tax, o.total, o.fulfilment
                     FROM orders o JOIN societies s ON s.id = o.society_id JOIN users u ON u.id = o.user_id
                     WHERE o.created_at >= ? AND o.created_at <= ?' . $socEq('o.society_id') . '
                     ORDER BY o.id ASC',
                    [$fromSql, $toSql]
                );
                $out = array_map(static fn($r) => [$r['id'], $r['order_no'], $r['created_at'], $r['society'], $r['resident'], $r['type'], $r['status'], $r['subtotal'], $r['discount'], $r['delivery_fee'], $r['tax'], $r['total'], $r['fulfilment']], $rows);
                $this->sendCsv('orders', ['ID', 'Order No', 'Placed At', 'Society', 'Resident', 'Type', 'Status', 'Subtotal', 'Discount', 'Delivery Fee', 'Tax', 'Total', 'Fulfilment'], $out);
        }
    }

    /* ================================================================
       AUDIT LOG
       ================================================================ */

    public function audit(): void
    {
        $q    = (string) $this->query('q', '');
        $sql  = 'SELECT a.*, u.name user_name, u.email user_email FROM audit_logs a LEFT JOIN users u ON u.id = a.user_id';
        $par  = [];
        if ($q !== '') {
            $sql .= ' WHERE a.action LIKE ? OR a.entity_type LIKE ?';
            $like = "%$q%";
            array_push($par, $like, $like);
        }
        $sql .= ' ORDER BY a.id DESC LIMIT 200';
        $rows = Database::fetchAll($sql, $par);

        $this->view('super/audit', [
            'active'    => 'super-audit',
            'pageTitle' => 'Audit Logs',
            'rows'      => $rows,
            'q'         => $q,
        ]);
    }

    /* ================================================================
       PLATFORM SETTINGS
       ================================================================ */

    private const SETTING_KEYS = [
        'support_email', 'support_phone', 'sample_prices_note', 'default_commission_percent',
        'whatsapp_enabled', 'sms_enabled', 'browser_push_enabled',
    ];

    public function settings(): void
    {
        $settings = [];
        foreach (Database::fetchAll('SELECT setting_key, setting_value, updated_at FROM settings') as $row) {
            $settings[$row['setting_key']] = $row;
        }
        $gateway = [
            'gateway_enabled' => (bool) config('payments.gateway.enabled', false),
            'gateway_provider' => (string) config('payments.gateway.provider', ''),
            'upi_enabled'     => (bool) config('payments.upi.enabled', false),
            'cod_enabled'     => (bool) config('payments.cod.enabled', false),
            'mail_enabled'    => (bool) config('app.mail.enabled', false),
            'timezone'        => (string) config('app.timezone', 'UTC'),
        ];
        $this->view('super/settings', [
            'active'    => 'super-settings',
            'pageTitle' => 'Platform Settings',
            'settings'  => $settings,
            'knownKeys' => self::SETTING_KEYS,
            'gateway'   => $gateway,
        ]);
    }

    public function saveSettings(): void
    {
        $changed = [];
        foreach ($_POST as $key => $value) {
            if ($key === '_token' || $key === 'new_key' || $key === 'new_value') {
                continue;
            }
            if (!is_string($key) || $key === '' || strlen($key) > 80) {
                continue;
            }
            $val = is_string($value) ? trim($value) : '';
            Database::run(
                'INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (?,?,NOW())
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()',
                [$key, $val]
            );
            $changed[] = $key;
        }
        $newKey   = trim((string) $this->post('new_key'));
        $newVal   = (string) $this->post('new_value');
        if ($newKey !== '' && strlen($newKey) <= 80 && preg_match('/^[a-z0-9_\.]+$/', $newKey)) {
            Database::run(
                'INSERT INTO settings (setting_key, setting_value, updated_at) VALUES (?,?,NOW())
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()',
                [$newKey, trim($newVal)]
            );
            $changed[] = $newKey;
            flash('success', 'Setting "' . $newKey . '" added.');
        }
        Audit::log('settings.updated', 'settings', null, ['keys' => $changed]);
        if ($changed !== [] && $newKey === '') {
            flash('success', 'Settings saved (' . count($changed) . ' key(s) updated).');
        }
        $this->redirect('/super/settings');
    }
}
