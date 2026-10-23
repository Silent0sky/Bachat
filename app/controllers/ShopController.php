<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

/**
 * Shop catalogue, favourites, saved lists, reminders (Task 3 / Agent A).
 * Routes: GET /shop[/category/{id}|/product/{id}], GET|POST /favourites[/toggle],
 *         GET|POST /lists..., GET|POST /reminders...
 */
final class ShopController extends Controller
{
    private const PER_PAGE = 24;

    /* ==================================================================
     *  Price resolution — society override first, variant base as fallback.
     *  MUST be used for every displayed/charged price.
     * ================================================================== */
    private function effectivePrice(int $variantId, int $societyId): float
    {
        $p = Database::scalar(
            'SELECT selling_price FROM society_prices WHERE variant_id = ? AND society_id = ?',
            [$variantId, $societyId]
        );
        if ($p === false || $p === null) {
            $p = Database::scalar('SELECT selling_price FROM product_variants WHERE id = ?', [$variantId]);
        }
        return round((float) $p, 2);
    }

    private function wantsJson(): bool
    {
        $xrw = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        return $xrw === 'xmlhttprequest'
            || str_contains(strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? '')), 'application/json');
    }

    /* ==================================================================
     *  GET /shop  +  GET /shop/category/{id}  (shared listing)
     * ================================================================== */
    public function index(): void
    {
        $this->renderCatalog((int) ($this->query('category', 0) ?? 0), null);
    }

    public function category(string $id): void
    {
        $catId = (int) $id;
        $cat = $catId > 0
            ? Database::fetch('SELECT * FROM categories WHERE id = ? AND is_active = 1 LIMIT 1', [$catId])
            : null;
        if (!$cat) {
            flash('info', t('common.not_found'));
            $this->redirect('/shop');
        }
        $this->renderCatalog($catId, $cat);
    }

    private function renderCatalog(int $catId, ?array $cat): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();
        $sid    = (int) Auth::societyId();
        $q      = trim((string) ($this->query('q', '') ?? ''));
        $sort   = (string) ($this->query('sort', '') ?? '');
        $page   = max(1, (int) (($this->query('page', 1) ?? 1)));

        [$rows, $total] = $this->listing($sid, $catId, $q, $sort, $page);
        $children = $cat
            ? Database::fetchAll('SELECT * FROM categories WHERE parent_id = ? AND is_active = 1 ORDER BY sort_order, id', [$catId])
            : [];

        $this->view('shop/catalog', [
            'pageTitle' => $cat ? product_name($cat) : ($q !== '' ? t('shop.search_results', ['q' => $q]) : t('shop.all_products')),
            'active'    => 'shop',
            'products'  => $rows,
            'total'     => $total,
            'page'      => $page,
            'pages'     => (int) max(1, (int) ceil($total / self::PER_PAGE)),
            'q'         => $q,
            'sort'      => $sort,
            'catId'     => $catId,
            'cat'       => $cat,
            'children'  => $children,
            'categories'=> $this->categoryTree(),
        ]);
    }

    /** @return array{0: array<int,array>, 1: int} [rows, totalProducts] */
    private function listing(int $sid, int $catId, string $q, string $sort, int $page): array
    {
        $where  = ['p.is_active = 1', 'v.is_active = 1'];
        $params = [$sid]; // first placeholder = society_prices join

        if ($catId > 0) {
            $ids = [$catId];
            foreach (Database::fetchAll('SELECT id FROM categories WHERE parent_id = ?', [$catId]) as $c) {
                $ids[] = (int) $c['id'];
            }
            $where[] = 'p.category_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            array_push($params, ...$ids);
        }
        if ($q !== '') {
            $like = '%' . $q . '%';
            $where[] = '(p.name_en LIKE ? OR p.name_hi LIKE ? OR p.name_mr LIKE ? OR EXISTS (
                            SELECT 1 FROM brands b2 JOIN product_variants v2 ON v2.brand_id = b2.id
                            WHERE v2.product_id = p.id AND b2.name LIKE ?))';
            array_push($params, $like, $like, $like, $like);
        }

        $orderBy = match ($sort) {
            'price_asc'  => 'min_price ASC, p.id ASC',
            'price_desc' => 'min_price DESC, p.id ASC',
            'newest'     => 'p.created_at DESC, p.id DESC',
            default      => 'p.id ASC',
        };

        $base = 'FROM products p
                 JOIN product_variants v ON v.product_id = p.id AND v.is_active = 1
                 LEFT JOIN society_prices sp ON sp.variant_id = v.id AND sp.society_id = ?
                 WHERE ' . implode(' AND ', $where);

        $total = (int) Database::scalar('SELECT COUNT(DISTINCT p.id) ' . $base, $params);

        $offset = ($page - 1) * self::PER_PAGE;
        $rows = Database::fetchAll(
            'SELECT p.id, p.name_en, p.name_hi, p.name_mr, p.image, p.unit, p.description, p.created_at,
                    MIN(COALESCE(sp.selling_price, v.selling_price)) AS min_price,
                    COUNT(v.id) AS variant_count
             ' . $base . '
             GROUP BY p.id, p.name_en, p.name_hi, p.name_mr, p.image, p.unit, p.description, p.created_at
             ORDER BY ' . $orderBy . '
             LIMIT ' . self::PER_PAGE . ' OFFSET ' . $offset,
            $params
        );

        return [$rows, $total];
    }

    /** @return array<int, array> top-level categories with `children` key */
    private function categoryTree(): array
    {
        $tops = Database::fetchAll(
            'SELECT id, parent_id, name_en, name_hi, name_mr, icon FROM categories
             WHERE parent_id IS NULL AND is_active = 1 ORDER BY sort_order, id'
        );
        $kids = Database::fetchAll(
            'SELECT id, parent_id, name_en, name_hi, name_mr, icon FROM categories
             WHERE parent_id IS NOT NULL AND is_active = 1 ORDER BY sort_order, id'
        );
        $map = [];
        foreach ($kids as $k) {
            $map[(int) $k['parent_id']][] = $k;
        }
        foreach ($tops as &$t) {
            $t['children'] = $map[(int) $t['id']] ?? [];
        }
        return $tops;
    }

    /* ==================================================================
     *  GET /shop/product/{id}
     * ================================================================== */
    public function show(string $id): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();
        $pid = (int) $id;
        $sid = (int) Auth::societyId();

        $product = Database::fetch(
            'SELECT p.*, c.name_en AS cat_name_en, c.name_hi AS cat_name_hi, c.name_mr AS cat_name_mr, c.id AS cat_id
             FROM products p JOIN categories c ON c.id = p.category_id
             WHERE p.id = ? AND p.is_active = 1 LIMIT 1',
            [$pid]
        );
        if (!$product) {
            flash('error', t('common.not_found'));
            $this->redirect('/shop');
        }

        $variants = Database::fetchAll(
            'SELECT v.*, b.name AS brand_name,
                    COALESCE(NULLIF(v.variant_name, ""), b.name, "Standard") AS variant_label,
                    COALESCE(sp.selling_price, v.selling_price) AS price
             FROM product_variants v
             LEFT JOIN brands b ON b.id = v.brand_id
             LEFT JOIN society_prices sp ON sp.variant_id = v.id AND sp.society_id = ?
             WHERE v.product_id = ? AND v.is_active = 1
             ORDER BY v.id ASC',
            [$sid, $pid]
        );

        $favIds = [];
        foreach (Database::fetchAll('SELECT variant_id FROM favourites WHERE user_id = ?', [Auth::id()]) as $f) {
            $favIds[] = (int) $f['variant_id'];
        }

        $reviews = Database::fetchAll(
            'SELECT r.rating, r.title, r.body, r.photo, r.created_at, u.name AS user_name
             FROM reviews r JOIN users u ON u.id = r.user_id
             WHERE r.target_type = "product" AND r.target_id = ? AND r.status = "approved"
             ORDER BY r.id DESC
             LIMIT 10',
            [$pid]
        );

        $lists = Database::fetchAll(
            'SELECT id, name FROM saved_lists WHERE user_id = ? ORDER BY id DESC',
            [Auth::id()]
        );

        $this->view('shop/product', [
            'pageTitle' => product_name($product),
            'active'    => 'shop',
            'product'   => $product,
            'variants'  => $variants,
            'favIds'    => $favIds,
            'reviews'   => $reviews,
            'lists'     => $lists,
        ]);
    }

    /* ==================================================================
     *  GET /favourites
     * ================================================================== */
    public function favourites(): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();
        $sid = (int) Auth::societyId();

        $rows = Database::fetchAll(
            'SELECT f.variant_id, v.pack_size, v.unit, v.stock_qty, v.min_qty, v.max_qty, v.reference_price,
                    p.id AS product_id, p.name_en, p.name_hi, p.name_mr, p.image,
                    b.name AS brand_name,
                    COALESCE(sp.selling_price, v.selling_price) AS price
             FROM favourites f
             JOIN product_variants v ON v.id = f.variant_id AND v.is_active = 1
             JOIN products p ON p.id = v.product_id AND p.is_active = 1
             LEFT JOIN brands b ON b.id = v.brand_id
             LEFT JOIN society_prices sp ON sp.variant_id = v.id AND sp.society_id = ?
             WHERE f.user_id = ?
             ORDER BY f.variant_id DESC',
            [$sid, Auth::id()]
        );

        $this->view('shop/favourites', [
            'pageTitle' => t('shop.favourites_title'),
            'active'    => 'favourites',
            'rows'      => $rows,
        ]);
    }

    /* ==================================================================
     *  POST /favourites/toggle — AJAX (JSON) or PRG fallback
     * ================================================================== */
    public function toggleFavourite(): void
    {
        $this->requireRole(['resident']);
        $vid = (int) $this->post('variant_id', 0);

        $variant = Database::fetch(
            'SELECT v.id FROM product_variants v JOIN products p ON p.id = v.product_id
             WHERE v.id = ? AND v.is_active = 1 AND p.is_active = 1 LIMIT 1',
            [$vid]
        );
        if (!$variant) {
            if ($this->wantsJson()) {
                $this->json(['ok' => false, 'faved' => false, 'message' => t('shop.unavailable')]);
            }
            flash('error', t('shop.unavailable'));
            $this->back();
        }

        $uid = (int) Auth::id();
        $exists = (int) Database::scalar(
            'SELECT COUNT(*) FROM favourites WHERE user_id = ? AND variant_id = ?',
            [$uid, $vid]
        );

        if ($exists > 0) {
            Database::run('DELETE FROM favourites WHERE user_id = ? AND variant_id = ?', [$uid, $vid]);
            $faved = false;
            $msg   = t('shop.fav_removed');
        } else {
            Database::run('INSERT INTO favourites (user_id, variant_id) VALUES (?,?)', [$uid, $vid]);
            $faved = true;
            $msg   = t('shop.fav_added');
        }

        if ($this->wantsJson()) {
            $this->json(['ok' => true, 'faved' => $faved, 'message' => $msg]);
        }
        flash('success', $msg);
        $this->back();
    }

    /* ==================================================================
     *  Saved lists
     * ================================================================== */
    public function lists(): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();
        $uid = (int) Auth::id();
        $sid = (int) Auth::societyId();

        $lists = Database::fetchAll(
            'SELECT l.*, (SELECT COUNT(*) FROM saved_list_items li WHERE li.list_id = l.id) AS item_count
             FROM saved_lists l WHERE l.user_id = ? ORDER BY l.id DESC',
            [$uid]
        );
        $items = Database::fetchAll(
            'SELECT li.list_id, li.id AS item_id, li.variant_id, li.qty,
                    v.pack_size, v.unit, v.stock_qty, v.min_qty, v.max_qty,
                    p.id AS product_id, p.name_en, p.name_hi, p.name_mr, p.image,
                    b.name AS brand_name,
                    COALESCE(sp.selling_price, v.selling_price) AS price
             FROM saved_list_items li
             JOIN saved_lists l ON l.id = li.list_id AND l.user_id = ?
             JOIN product_variants v ON v.id = li.variant_id
             JOIN products p ON p.id = v.product_id
             LEFT JOIN brands b ON b.id = v.brand_id
             LEFT JOIN society_prices sp ON sp.variant_id = v.id AND sp.society_id = ?
             ORDER BY li.id DESC',
            [$uid, $sid]
        );

        $this->view('shop/lists', [
            'pageTitle' => t('shop.lists_title'),
            'active'    => 'lists',
            'lists'     => $lists,
            'items'     => $items,
        ]);
    }

    /** POST /lists — create a list. */
    public function saveList(): void
    {
        $this->requireRole(['resident']);
        $errors = $this->validate(['name' => 'required|min:2|max:100']);
        if ($errors) {
            flash('error', (string) reset($errors));
            $this->keepOld();
            $this->back();
        }
        Database::run(
            'INSERT INTO saved_lists (user_id, name, created_at) VALUES (?, ?, NOW())',
            [Auth::id(), mb_substr((string) $this->post('name', ''), 0, 100)]
        );
        flash('success', t('shop.list_created'));
        $this->redirect('/lists');
    }

    /** POST /lists/{id}/delete */
    public function deleteList(string $id): void
    {
        $this->requireRole(['resident']);
        Database::run('DELETE FROM saved_lists WHERE id = ? AND user_id = ?', [(int) $id, Auth::id()]);
        flash('success', t('shop.list_deleted'));
        $this->redirect('/lists');
    }

    /** POST /lists/{id}/items — add an item (also accepts list_id in the body). */
    public function addListItem(string $id): void
    {
        $this->requireRole(['resident']);
        $uid    = (int) Auth::id();
        $listId = (int) $id ?: (int) $this->post('list_id', 0);

        $list = Database::fetch(
            'SELECT id FROM saved_lists WHERE id = ? AND user_id = ? LIMIT 1',
            [$listId, $uid]
        );
        if (!$list) {
            flash('error', t('shop.list_not_owner'));
            $this->redirect('/lists');
        }

        $errors = $this->validate([
            'variant_id' => 'required|int',
            'qty'        => 'required|numeric|min:0.5',
        ]);
        if ($errors) {
            flash('error', (string) reset($errors));
            $this->back();
        }

        $vid = (int) $this->post('variant_id', 0);
        $qty = round((float) $this->post('qty', 1), 3);
        $variant = Database::fetch(
            'SELECT v.id, v.stock_qty, v.max_qty FROM product_variants v JOIN products p ON p.id = v.product_id
             WHERE v.id = ? AND v.is_active = 1 AND p.is_active = 1 LIMIT 1',
            [$vid]
        );
        if (!$variant) {
            flash('error', t('shop.variant_unavailable'));
            $this->back();
        }
        if ($variant['max_qty'] !== null && $qty > (float) $variant['max_qty']) {
            $qty = round((float) $variant['max_qty'], 3);
        }
        if ((float) $variant['stock_qty'] > 0) {
            $qty = min($qty, (float) $variant['stock_qty']);
        }

        $existing = Database::fetch(
            'SELECT id, qty FROM saved_list_items WHERE list_id = ? AND variant_id = ? LIMIT 1',
            [$listId, $vid]
        );
        if ($existing) {
            Database::run('UPDATE saved_list_items SET qty = ? WHERE id = ?', [$qty, (int) $existing['id']]);
        } else {
            Database::run(
                'INSERT INTO saved_list_items (list_id, variant_id, qty) VALUES (?,?,?)',
                [$listId, $vid, $qty]
            );
        }
        flash('success', t('shop.item_added'));
        $this->back();
    }

    /** POST /lists/{id}/to-cart — push the whole list into the cart. */
    public function listToCart(string $id): void
    {
        $this->requireRole(['resident']);
        $this->requireMembership();
        $uid = (int) Auth::id();
        $sid = (int) Auth::societyId();

        $list = Database::fetch(
            'SELECT id FROM saved_lists WHERE id = ? AND user_id = ? LIMIT 1',
            [(int) $id, $uid]
        );
        if (!$list) {
            flash('error', t('shop.list_not_owner'));
            $this->redirect('/lists');
        }

        $items = Database::fetchAll(
            'SELECT li.variant_id, li.qty, v.stock_qty, v.min_qty, v.max_qty
             FROM saved_list_items li
             JOIN product_variants v ON v.id = li.variant_id AND v.is_active = 1
             JOIN products p ON p.id = v.product_id AND p.is_active = 1
             WHERE li.list_id = ?',
            [(int) $id]
        );
        if (!$items) {
            flash('info', t('shop.list_empty'));
            $this->redirect('/lists');
        }

        $cart = Database::fetch('SELECT id FROM carts WHERE user_id = ? LIMIT 1', [$uid]);
        if (!$cart) {
            Database::run('INSERT INTO carts (user_id, society_id, created_at) VALUES (?,?,NOW())', [$uid, $sid]);
            $cart = ['id' => (int) Database::pdo()->lastInsertId()];
        }
        $cartId = (int) $cart['id'];

        $added = 0;
        foreach ($items as $it) {
            if ((float) $it['stock_qty'] <= 0) {
                continue; // out of stock — skip
            }
            $qty = (float) $it['qty'];
            if ($qty < (float) $it['min_qty']) {
                $qty = (float) $it['min_qty'];
            }
            if ($it['max_qty'] !== null && $qty > (float) $it['max_qty']) {
                $qty = (float) $it['max_qty'];
            }
            $qty = round(min($qty, (float) $it['stock_qty']), 3);
            if ($qty <= 0) {
                continue;
            }
            $existing = Database::fetch(
                'SELECT id, qty FROM cart_items WHERE cart_id = ? AND variant_id = ? LIMIT 1',
                [$cartId, (int) $it['variant_id']]
            );
            if ($existing) {
                $newQty = min((float) $existing['qty'] + $qty, (float) $it['stock_qty']);
                if ($it['max_qty'] !== null) {
                    $newQty = min($newQty, (float) $it['max_qty']);
                }
                Database::run('UPDATE cart_items SET qty = ? WHERE id = ?', [round($newQty, 3), (int) $existing['id']]);
            } else {
                Database::run(
                    'INSERT INTO cart_items (cart_id, variant_id, qty) VALUES (?,?,?)',
                    [$cartId, (int) $it['variant_id'], $qty]
                );
            }
            $added++;
        }

        flash('success', t('shop.list_to_cart_done', ['n' => $added]));
        $this->redirect('/cart');
    }

    /* ==================================================================
     *  Reminders (fired by platform cron)
     * ================================================================== */
    public function reminders(): void
    {
        $this->requireRole(['resident']);
        $uid = (int) Auth::id();

        $rems = Database::fetchAll(
            'SELECT r.*, l.name AS list_name
             FROM reminders r
             LEFT JOIN saved_lists l ON l.id = r.list_id
             WHERE r.user_id = ?
             ORDER BY r.next_run ASC, r.id DESC',
            [$uid]
        );
        $lists = Database::fetchAll(
            'SELECT id, name FROM saved_lists WHERE user_id = ? ORDER BY id DESC',
            [$uid]
        );

        $this->view('shop/reminders', [
            'pageTitle' => t('shop.reminders_title'),
            'active'    => 'reminders',
            'rems'      => $rems,
            'lists'     => $lists,
        ]);
    }

    /** POST /reminders */
    public function saveReminder(): void
    {
        $this->requireRole(['resident']);
        $errors = $this->validate([
            'title'     => 'required|min:3|max:150',
            'frequency' => 'required|in:weekly,monthly',
            'next_run'  => 'required|date',
        ]);
        if ($errors) {
            flash('error', (string) reset($errors));
            $this->keepOld();
            $this->back();
        }

        $listId = (int) $this->post('list_id', 0);
        if ($listId > 0) {
            $ok = Database::scalar(
                'SELECT id FROM saved_lists WHERE id = ? AND user_id = ? LIMIT 1',
                [$listId, Auth::id()]
            );
            if (!$ok) {
                $listId = 0;
            }
        }

        Database::run(
            'INSERT INTO reminders (user_id, list_id, title, frequency, next_run, is_active, created_at)
             VALUES (?,?,?,?,?,1,NOW())',
            [
                Auth::id(),
                $listId > 0 ? $listId : null,
                mb_substr((string) $this->post('title', ''), 0, 150),
                (string) $this->post('frequency', 'weekly'),
                date('Y-m-d', (int) strtotime((string) $this->post('next_run', ''))),
            ]
        );
        flash('success', t('shop.reminder_created'));
        $this->redirect('/reminders');
    }

    /** POST /reminders/{id}/delete */
    public function deleteReminder(string $id): void
    {
        $this->requireRole(['resident']);
        Database::run('DELETE FROM reminders WHERE id = ? AND user_id = ?', [(int) $id, Auth::id()]);
        flash('success', t('shop.reminder_deleted'));
        $this->redirect('/reminders');
    }
}
