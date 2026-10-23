<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    /** Render a view inside the app layout. Pass 'active' for nav highlighting. */
    protected function view(string $tpl, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        ob_start();
        include APP . '/views/' . $tpl . '.php';
        $content = ob_get_clean();

        $user    = Auth::user();
        $society = current_society();
        $lang    = lang();
        include APP . '/views/layout.php';
    }

    /** Render a standalone (layout-less) view — invoices, print pages. */
    protected function bare(string $tpl, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        include APP . '/views/' . $tpl . '.php';
    }

    protected function json(mixed $data, int $code = 200): never
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    protected function redirect(string $to): never
    {
        header('Location: ' . url($to));
        exit;
    }

    protected function back(): never
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? null;
        if ($ref && str_starts_with($ref, url('/'))) {
            header('Location: ' . $ref);
        } else {
            header('Location: ' . url('/'));
        }
        exit;
    }

    /* ---------- Input helpers (never trust raw input) ---------- */

    protected function post(string $key, mixed $default = null): mixed
    {
        $v = $_POST[$key] ?? $default;
        if (is_string($v)) {
            return trim($v);
        }
        return $v;
    }

    protected function query(string $key, mixed $default = null): mixed
    {
        $v = $_GET[$key] ?? $default;
        if (is_string($v)) {
            return trim($v);
        }
        return $v;
    }

    protected function file(string $key): ?array
    {
        $f = $_FILES[$key] ?? null;
        return (is_array($f) && ($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) ? $f : null;
    }

    /** Keep submitted input for re-filling the form after a validation error. */
    protected function keepOld(array $exclude = ['password', 'password_confirmation', '_token']): void
    {
        $old = array_diff_key($_POST, array_flip($exclude));
        Session::set('_old', $old);
    }

    /* ---------- Access guards (also enforced by Router middlewares) ---------- */

    protected function requireAuth(): void
    {
        if (!Auth::check()) {
            Flash::add('error', t('auth.login_required'));
            $this->redirect('/login');
        }
    }

    /** @param string[] $roles */
    protected function requireRole(array $roles): void
    {
        $this->requireAuth();
        if (!in_array(Auth::role() ?? '', $roles, true)) {
            http_response_code(403);
            include APP . '/views/errors/403.php';
            exit;
        }
    }

    /** Ensure the resident has an active society membership. */
    protected function requireMembership(): void
    {
        if (Auth::role() === 'resident' && Auth::societyId() === null) {
            Flash::add('info', t('auth.membership_pending'));
            $this->redirect('/join/pending');
        }
    }

    /* ---------- Validation shortcut ---------- */

    /** @return string[] list of error messages (empty = OK) */
    protected function validate(array $rules): array
    {
        $v = Validator::make($_POST, $rules);
        return $v->errors();
    }
}
