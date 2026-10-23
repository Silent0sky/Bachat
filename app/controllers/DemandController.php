<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\Database;

/**
 * Resident demand board: post "we want X" requests and up-vote neighbours' requests.
 * Voting NEVER creates orders or payments — it only signals demand.
 */
final class DemandController extends Controller
{
    public function index(): void
    {
        $this->requireMembership();
        $uid = (int) Auth::id();
        $sid = (int) (Auth::societyId() ?? 0);

        $votes = Database::fetchAll(
            "SELECT dv.*, u.name AS creator_name,
                    (SELECT COUNT(*) FROM demand_vote_interests x WHERE x.vote_id = dv.id) AS vote_count,
                    EXISTS(SELECT 1 FROM demand_vote_interests y WHERE y.vote_id = dv.id AND y.user_id = ?) AS my_vote
               FROM demand_votes dv
               JOIN users u ON u.id = dv.user_id
              WHERE dv.society_id = ? OR dv.society_id IS NULL
              ORDER BY dv.created_at DESC
              LIMIT 80",
            [$uid, $sid]
        );

        $categories = Database::fetchAll(
            'SELECT id, name_en, name_hi, name_mr FROM categories WHERE is_active = 1 ORDER BY sort_order, name_en'
        );

        $this->view('demand/index', [
            'active'     => 'demand',
            'pageTitle'  => t('demand.title'),
            'votes'      => $votes,
            'categories' => $categories,
        ]);
    }

    public function create(): void
    {
        $this->requireMembership();
        $uid = (int) Auth::id();
        $sid = (int) (Auth::societyId() ?? 0);

        $errors = $this->validate([
            'title'     => 'required|max:180',
            'item_type' => 'in:product,service',
            'details'   => 'max:600',
        ]);
        if ($errors) {
            flash('error', implode(' ', $errors));
            $this->keepOld();
            $this->back();
        }

        $itemType = $this->post('item_type', 'product') === 'service' ? 'service' : 'product';
        $catId    = (int) ($this->post('category_id', 0) ?? 0);
        if ($itemType !== 'product' || $catId <= 0) {
            $catId = null;
        } elseif (!Database::fetch('SELECT id FROM categories WHERE id = ? AND is_active = 1', [$catId])) {
            $catId = null;
        }

        $details = mb_substr((string) ($this->post('details', '') ?? ''), 0, 600);

        Database::run(
            "INSERT INTO demand_votes (society_id, user_id, item_type, title, details, category_id, status, created_at)
             VALUES (?,?,?,?,?,?,'open',NOW())",
            [$sid, $uid, $itemType, mb_substr((string) $this->post('title'), 0, 180), $details !== '' ? $details : null, $catId]
        );
        Audit::log('demand.create', 'demand_vote', (int) Database::pdo()->lastInsertId(), ['title' => $this->post('title')]);

        flash('success', t('demand.created'));
        $this->redirect('/demand');
    }

    public function vote(string $id): void
    {
        $this->requireMembership();
        $uid = (int) Auth::id();
        $sid = (int) (Auth::societyId() ?? 0);

        $vote = Database::fetch('SELECT * FROM demand_votes WHERE id = ?', [(int) $id]);
        if (!$vote || ($vote['society_id'] !== null && (int) $vote['society_id'] !== $sid)) {
            flash('error', t('common.not_found'));
            $this->redirect('/demand');
        }
        if (!in_array($vote['status'], ['open', 'validated'], true)) {
            flash('info', t('demand.closed_for_votes'));
            $this->redirect('/demand');
        }
        if ((int) Database::scalar('SELECT COUNT(*) FROM demand_vote_interests WHERE vote_id = ? AND user_id = ?', [$vote['id'], $uid]) > 0) {
            flash('info', t('demand.already_voted'));
            $this->redirect('/demand');
        }

        Database::run('INSERT INTO demand_vote_interests (vote_id, user_id) VALUES (?,?)', [$vote['id'], $uid]);
        Audit::log('demand.vote', 'demand_vote', (int) $vote['id'], ['user_id' => $uid]);

        flash('success', t('demand.voted'));
        $this->redirect('/demand');
    }
}
