<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Lang;

class HomeController extends Controller
{
    public function index(): void
    {
        $societies = [];
        $stats = ['societies' => 0, 'products' => 0, 'services' => 0];
        try {
            $societies = Database::fetchAll(
                'SELECT id, name, slug, city, logo, banner FROM societies WHERE status = "active" ORDER BY name ASC LIMIT 12'
            );
            $stats['societies'] = (int) Database::scalar('SELECT COUNT(*) FROM societies WHERE status = "active"');
            $stats['products']  = (int) Database::scalar('SELECT COUNT(*) FROM products WHERE is_active = 1');
            $stats['services']  = (int) Database::scalar('SELECT COUNT(*) FROM services WHERE is_active = 1');
        } catch (\Throwable $e) {
            error_log('[Bachat Home] ' . $e->getMessage());
        }
        $this->view('home', [
            'pageTitle' => t('app.name') . ' — ' . t('app.tagline'),
            'active'    => 'home',
            'societies' => $societies,
            'stats'     => $stats,
        ]);
    }

    /** Public society landing page with society-specific branding. */
    public function society(string $slug): void
    {
        $soc = Database::fetch('SELECT * FROM societies WHERE slug = ? AND status = "active" LIMIT 1', [$slug]);
        if (!$soc) {
            http_response_code(404);
            include APP . '/views/errors/404.php';
            exit;
        }
        $notices = Database::fetchAll(
            'SELECT id, title, body, publish_at FROM announcements
             WHERE society_id = ? AND (publish_at IS NULL OR publish_at <= NOW())
               AND (expires_at IS NULL OR expires_at >= NOW())
             ORDER BY is_pinned DESC, publish_at DESC LIMIT 5',
            [$soc['id']]
        );
        $this->view('society_landing', [
            'pageTitle' => $soc['name'],
            'active'    => 'home',
            'soc'       => $soc,
            'notices'   => $notices,
        ]);
    }

    /** Switch language: /lang/{code} — persists in session. */
    public function lang(string $code): void
    {
        Lang::setLocale($code);
        $this->back();
    }
}
