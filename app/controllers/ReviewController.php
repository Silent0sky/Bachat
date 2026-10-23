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
 * Community — verified-purchase reviews + moderation.
 *
 * target_id convention (important for cross-module rendering):
 *  - product  → reviews.target_id = products.id (resolved via order_item → variant → product)
 *  - service  → reviews.target_id = services.id (from a COMPLETED booking)
 *  - vendor   → reviews.target_id = users.id of the vendor (booking vendor or product's vendor)
 *  - delivery → reviews.target_id = users.id of the delivery staff (assigned_to of delivered task)
 *
 * Eligibility is always re-verified server-side on POST. One review per order/booking per target.
 */
class ReviewController extends Controller
{
    /* ---------------- Resident ---------------- */

    /** GET /reviews/new?type=product&target={orderItemId}&order={orderId} | type=service&booking={id} | type=vendor… | type=delivery&order={id} */
    public function create(): void
    {
        $this->requireRole(['resident']);

        $type      = (string) $this->query('type');
        $target    = (int) $this->query('target', 0);
        $orderId   = (int) $this->query('order', 0);
        $bookingId = (int) $this->query('booking', 0);

        $ctx = $this->resolveTarget($type, $target, $orderId, $bookingId);
        if ($ctx === null) {
            flash('error', t('reviews.not_eligible'));
            $this->back();
        }
        if ($ctx['dup']) {
            flash('info', t('reviews.already_reviewed'));
            $this->redirect($ctx['back']);
        }

        $this->view('community/review_new', [
            'pageTitle' => t('reviews.write_title'),
            'active'    => 'orders',
            'ctx'       => $ctx,
        ]);
    }

    /** POST /reviews */
    public function store(): void
    {
        $this->requireRole(['resident']);

        $type      = (string) $this->post('target_type');
        $target    = (int) $this->post('target_id', 0);
        $orderId   = (int) $this->post('order_id', 0);
        $bookingId = (int) $this->post('booking_id', 0);

        // Never trust the client — re-run the full eligibility check.
        $ctx = $this->resolveTarget($type, $target, $orderId, $bookingId);
        if ($ctx === null) {
            flash('error', t('reviews.not_eligible'));
            $this->back();
        }
        if ($ctx['dup']) {
            flash('error', t('reviews.already_reviewed'));
            $this->redirect($ctx['back']);
        }

        $errors = $this->validate([
            'rating' => 'required|int|min:1|max:5',
            'title'  => 'max:150',
            'body'   => 'max:2000',
        ]);
        $rating = (int) $this->post('rating', 0);
        if ($rating < 1 || $rating > 5) {
            $errors['rating'] = t('reviews.rating') . ' (1–5)';
        }
        if ($errors !== []) {
            flash('error', (string) reset($errors));
            $this->keepOld();
            $this->back();
        }

        $photo = null;
        try {
            if ($f = $this->file('photo')) {
                $photo = Upload::image($f, 'reviews');
            }
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            $this->keepOld();
            $this->back();
        }

        try {
            Database::run(
                'INSERT INTO reviews (user_id, society_id, target_type, target_id, order_id, booking_id, rating, title, body, photo, status, created_at)
                 VALUES (?,?,?,?,?,?,?,?,?,?, "pending", NOW())',
                [
                    Auth::id(), Auth::societyId(), $ctx['target_type'], $ctx['target_id'],
                    $ctx['order_id'], $ctx['booking_id'], $rating,
                    (string) $this->post('title'), (string) $this->post('body'), $photo,
                ]
            );
        } catch (\Throwable $e) {
            error_log('[Bachat Review] store: ' . $e->getMessage());
            flash('error', t('common.error'));
            $this->keepOld();
            $this->back();
        }

        $reviewId = (int) Database::pdo()->lastInsertId();
        Audit::log('review.create', 'review', $reviewId, [
            'target_type' => $ctx['target_type'], 'target_id' => $ctx['target_id'], 'rating' => $rating,
        ]);
        Notify::superAdmins('review', 'New review awaiting moderation', $ctx['label'] . ' · ' . $rating . '★', '/admin/reviews');

        flash('success', t('shop.review_saved'));
        $this->redirect($ctx['back']);
    }

    /** POST /reviews/{id}/report — flag a review for moderation. */
    public function report(string $id): void
    {
        $this->requireRole(['resident']);
        $rid = (int) $id;

        $review = $rid > 0
            ? Database::fetch('SELECT id, status FROM reviews WHERE id = ? LIMIT 1', [$rid])
            : null;
        if (!$review || !in_array($review['status'], ['pending', 'approved'], true)) {
            flash('error', t('common.not_found'));
            $this->back();
        }

        Database::run('UPDATE reviews SET report_count = report_count + 1 WHERE id = ?', [$rid]);
        Audit::log('review.report', 'review', $rid);

        flash('success', t('reviews.reported'));
        $this->back();
    }

    /* ---------------- Moderation (society_admin + super_admin) ---------------- */

    /** GET /admin/reviews (and /super/reviews) — moderation queue. */
    public function moderate(): void
    {
        $this->requireRole(['society_admin', 'super_admin']);
        $isSuper = Auth::isRole('super_admin');
        $sid     = Auth::societyId();

        $tab = (string) ($this->query('tab') ?: 'pending');
        if (!in_array($tab, ['pending', 'reported', 'approved', 'rejected', 'all'], true)) {
            $tab = 'pending';
        }

        $where  = [];
        $params = [];
        if (!$isSuper) {
            $where[]  = 'r.society_id = ?';
            $params[] = $sid;
        }
        if ($tab === 'pending') {
            $where[] = 'r.status = "pending"';
        } elseif ($tab === 'reported') {
            $where[] = 'r.report_count > 0';
        } elseif ($tab === 'approved') {
            $where[] = 'r.status = "approved"';
        } elseif ($tab === 'rejected') {
            $where[] = 'r.status = "rejected"';
        }

        $sql = 'SELECT r.*, u.name AS author,
                       p.name_en AS p_name_en, p.name_hi AS p_name_hi, p.name_mr AS p_name_mr,
                       s.name_en AS s_name_en, s.name_hi AS s_name_hi, s.name_mr AS s_name_mr,
                       vu.name AS vendor_name, du.name AS delivery_name
                FROM reviews r
                JOIN users u ON u.id = r.user_id
                LEFT JOIN products p ON r.target_type = "product" AND p.id = r.target_id
                LEFT JOIN services s ON r.target_type = "service" AND s.id = r.target_id
                LEFT JOIN users vu  ON r.target_type = "vendor"  AND vu.id = r.target_id
                LEFT JOIN users du  ON r.target_type = "delivery" AND du.id = r.target_id'
            . ($where !== [] ? ' WHERE ' . implode(' AND ', $where) : '')
            . ' ORDER BY r.report_count DESC, r.created_at DESC
                LIMIT 200';

        $reviews = Database::fetchAll($sql, $params);

        $pendingWhere  = (!$isSuper && $sid !== null) ? ' AND r.society_id = ?' : '';
        $pendingParams = (!$isSuper && $sid !== null) ? [$sid] : [];
        $pendingCount  = (int) Database::scalar('SELECT COUNT(*) FROM reviews r WHERE r.status = "pending"' . $pendingWhere, $pendingParams);
        $reportedCount = (int) Database::scalar('SELECT COUNT(*) FROM reviews r WHERE r.report_count > 0' . $pendingWhere, $pendingParams);

        $this->view('admin/reviews', [
            'pageTitle'     => t('reviews.moderation_title'),
            'active'        => $isSuper ? 'super-reviews' : 'admin-reviews',
            'reviews'       => $reviews,
            'tab'           => $tab,
            'isSuper'       => $isSuper,
            'pendingCount'  => $pendingCount,
            'reportedCount' => $reportedCount,
        ]);
    }

    /** POST /admin/reviews/{id}/status — approve / reject. */
    public function setStatus(string $id): void
    {
        $this->requireRole(['society_admin', 'super_admin']);
        $review = $this->findScopedReview((int) $id);
        if (!$review) {
            flash('error', t('common.not_found'));
            $this->redirect($this->reviewsUrl());
        }

        $status = (string) $this->post('status');
        if (!in_array($status, ['approved', 'rejected', 'pending'], true)) {
            flash('error', t('common.error'));
            $this->redirect($this->reviewsUrl());
        }

        Database::run('UPDATE reviews SET status = ? WHERE id = ?', [$status, $review['id']]);

        if ($status === 'approved') {
            Notify::user(
                (int) $review['user_id'],
                'review',
                'Your review was published',
                'Thanks for sharing your experience on Bachat.',
                '/orders'
            );
        }
        Audit::log('review.status', 'review', (int) $review['id'], [
            'from' => $review['status'], 'to' => $status,
        ]);

        flash('success', t('reviews.status_updated'));
        $this->redirect($this->reviewsUrl());
    }

    /** POST /admin/reviews/{id}/reply — public reply attached to the review. */
    public function reply(string $id): void
    {
        $this->requireRole(['society_admin', 'super_admin']);
        $review = $this->findScopedReview((int) $id);
        if (!$review) {
            flash('error', t('common.not_found'));
            $this->redirect($this->reviewsUrl());
        }

        $errors = $this->validate(['reply' => 'required|min:2|max:1000']);
        if ($errors !== []) {
            flash('error', (string) reset($errors));
            $this->back();
        }

        Database::run(
            'UPDATE reviews SET reply = ?, replied_at = NOW() WHERE id = ?',
            [(string) $this->post('reply'), $review['id']]
        );
        Audit::log('review.reply', 'review', (int) $review['id']);

        Notify::user(
            (int) $review['user_id'],
            'review',
            'Reply to your review',
            mb_substr((string) $this->post('reply'), 0, 140),
            null
        );

        flash('success', t('reviews.reply_saved'));
        $this->redirect($this->reviewsUrl());
    }

    /* ---------------- internals ---------------- */

    /**
     * Resolve + verify review eligibility from GET/POST params.
     * Returns ['target_type','target_id','order_id','booking_id','label','sub','back','dup'] or null when ineligible.
     *
     * @return array<string,mixed>|null
     */
    private function resolveTarget(string $type, int $target, int $orderId, int $bookingId): ?array
    {
        $uid = (int) Auth::id();

        if ($type === 'product') {
            if ($orderId <= 0 || $target <= 0) {
                return null;
            }
            $order = Database::fetch('SELECT id, order_no, user_id, status FROM orders WHERE id = ? LIMIT 1', [$orderId]);
            if (!$order || (int) $order['user_id'] !== $uid || $order['status'] !== 'delivered') {
                return null;
            }
            // target = order_items.id → product-level reviews (target_id = products.id)
            $item = Database::fetch(
                'SELECT oi.id, oi.product_name, v.product_id
                 FROM order_items oi
                 LEFT JOIN product_variants v ON v.id = oi.variant_id
                 WHERE oi.id = ? AND oi.order_id = ? LIMIT 1',
                [$target, $orderId]
            );
            if (!$item || empty($item['product_id'])) {
                return null;
            }
            $productId = (int) $item['product_id'];
            return [
                'target_type' => 'product',
                'target_id'   => $productId,
                'order_id'    => $orderId,
                'booking_id'  => null,
                'label'       => (string) $item['product_name'],
                'sub'         => t('reviews.order_ref') . ': ' . $order['order_no'],
                'back'        => '/orders/' . $orderId,
                'dup'         => $this->hasDuplicate('product', $productId, $orderId, null),
            ];
        }

        if ($type === 'service') {
            if ($bookingId <= 0) {
                return null;
            }
            $b = Database::fetch(
                'SELECT b.id, b.booking_no, b.user_id, b.status, b.service_id,
                        s.name_en, s.name_hi, s.name_mr
                 FROM bookings b JOIN services s ON s.id = b.service_id
                 WHERE b.id = ? LIMIT 1',
                [$bookingId]
            );
            if (!$b || (int) $b['user_id'] !== $uid || $b['status'] !== 'completed') {
                return null;
            }
            return [
                'target_type' => 'service',
                'target_id'   => (int) $b['service_id'],
                'order_id'    => null,
                'booking_id'  => $bookingId,
                'label'       => product_name($b),
                'sub'         => t('reviews.booking_ref') . ': ' . $b['booking_no'],
                'back'        => '/bookings',
                'dup'         => $this->hasDuplicate('service', (int) $b['service_id'], null, $bookingId),
            ];
        }

        if ($type === 'vendor') {
            // From a completed service booking with a vendor…
            if ($bookingId > 0) {
                $b = Database::fetch(
                    'SELECT b.id, b.booking_no, b.user_id, b.status, b.vendor_id,
                            s.name_en, s.name_hi, s.name_mr
                     FROM bookings b JOIN services s ON s.id = b.service_id
                     WHERE b.id = ? LIMIT 1',
                    [$bookingId]
                );
                if (!$b || (int) $b['user_id'] !== $uid || $b['status'] !== 'completed' || empty($b['vendor_id'])) {
                    return null;
                }
                $vendor = Database::fetch('SELECT id, name FROM users WHERE id = ? LIMIT 1', [(int) $b['vendor_id']]);
                if (!$vendor) {
                    return null;
                }
                return [
                    'target_type' => 'vendor',
                    'target_id'   => (int) $b['vendor_id'],
                    'order_id'    => null,
                    'booking_id'  => $bookingId,
                    'label'       => t('reviews.context_vendor') . ': ' . $vendor['name'],
                    'sub'         => t('reviews.booking_ref') . ': ' . $b['booking_no'],
                    'back'        => '/bookings',
                    'dup'         => $this->hasDuplicate('vendor', (int) $b['vendor_id'], null, $bookingId),
                ];
            }
            // …or from a delivered order whose items map to a vendor's products.
            if ($orderId > 0) {
                $order = Database::fetch('SELECT id, order_no, user_id, status FROM orders WHERE id = ? LIMIT 1', [$orderId]);
                if (!$order || (int) $order['user_id'] !== $uid || $order['status'] !== 'delivered') {
                    return null;
                }
                $vid = Database::scalar(
                    'SELECT p.vendor_id
                     FROM order_items oi
                     JOIN product_variants v ON v.id = oi.variant_id
                     JOIN products p ON p.id = v.product_id
                     WHERE oi.order_id = ? AND p.vendor_id IS NOT NULL
                     LIMIT 1',
                    [$orderId]
                );
                if ($vid === false) {
                    return null;
                }
                $vendor = Database::fetch('SELECT id, name FROM users WHERE id = ? LIMIT 1', [(int) $vid]);
                if (!$vendor) {
                    return null;
                }
                return [
                    'target_type' => 'vendor',
                    'target_id'   => (int) $vid,
                    'order_id'    => $orderId,
                    'booking_id'  => null,
                    'label'       => t('reviews.context_vendor') . ': ' . $vendor['name'],
                    'sub'         => t('reviews.order_ref') . ': ' . $order['order_no'],
                    'back'        => '/orders/' . $orderId,
                    'dup'         => $this->hasDuplicate('vendor', (int) $vid, $orderId, null),
                ];
            }
            return null;
        }

        if ($type === 'delivery') {
            if ($orderId <= 0) {
                return null;
            }
            $order = Database::fetch('SELECT id, order_no, user_id, status FROM orders WHERE id = ? LIMIT 1', [$orderId]);
            if (!$order || (int) $order['user_id'] !== $uid || $order['status'] !== 'delivered') {
                return null;
            }
            $task = Database::fetch(
                'SELECT id, assigned_to FROM delivery_tasks
                 WHERE order_id = ? AND status = "delivered" AND assigned_to IS NOT NULL
                 ORDER BY id DESC LIMIT 1',
                [$orderId]
            );
            if (!$task) {
                return null;
            }
            $staff = Database::fetch('SELECT id, name FROM users WHERE id = ? LIMIT 1', [(int) $task['assigned_to']]);
            return [
                'target_type' => 'delivery',
                'target_id'   => (int) $task['assigned_to'],
                'order_id'    => $orderId,
                'booking_id'  => null,
                'label'       => t('reviews.context_delivery') . ': ' . ($staff['name'] ?? '—'),
                'sub'         => t('reviews.order_ref') . ': ' . $order['order_no'],
                'back'        => '/orders/' . $orderId,
                'dup'         => $this->hasDuplicate('delivery', (int) $task['assigned_to'], $orderId, null),
            ];
        }

        return null;
    }

    /** One review per user per order/booking per target. */
    private function hasDuplicate(string $type, int $targetId, ?int $orderId, ?int $bookingId): bool
    {
        $n = (int) Database::scalar(
            'SELECT COUNT(*) FROM reviews
             WHERE user_id = ? AND target_type = ? AND target_id = ? AND order_id <=> ? AND booking_id <=> ?',
            [Auth::id(), $type, $targetId, $orderId, $bookingId]
        );
        return $n > 0;
    }

    /** Review list URL for the current moderator role. */
    private function reviewsUrl(): string
    {
        return Auth::isRole('super_admin') ? '/super/reviews' : '/admin/reviews';
    }

    /** Review within the moderator's scope (super admin: all; society admin: reviews of own residents). */
    private function findScopedReview(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        if (Auth::isRole('super_admin')) {
            return Database::fetch('SELECT * FROM reviews WHERE id = ? LIMIT 1', [$id]);
        }
        $sid = Auth::societyId();
        if ($sid === null) {
            return null;
        }
        return Database::fetch('SELECT * FROM reviews WHERE id = ? AND society_id = ? LIMIT 1', [$id, $sid]);
    }
}
