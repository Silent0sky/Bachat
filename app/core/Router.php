<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Simple, dependency-free router.
 * Patterns: /shop/product/{id}  — {param} matches any segment except "/".
 * Options:  'auth' => true                 → any logged-in user
 *           'role' => ['resident', ...]    → logged-in + one of these roles
 *           'society' => true              → resident must have an ACTIVE membership
 * CSRF is validated automatically for every POST request.
 */
final class Router
{
    /** @var array<int, array{method:string, regex:string, params:array<int,string>, handler:string, opts:array}> */
    private static array $routes = [];

    public static function get(string $pattern, string $handler, array $opts = []): void
    {
        self::add('GET', $pattern, $handler, $opts);
    }

    public static function post(string $pattern, string $handler, array $opts = []): void
    {
        self::add('POST', $pattern, $handler, $opts);
    }

    public static function add(string $method, string $pattern, string $handler, array $opts = []): void
    {
        $params = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_]+)\}/', static function ($m) use (&$params) {
            $params[] = $m[1];
            return '([^/]+)';
        }, $pattern);
        self::$routes[] = [
            'method'  => $method,
            'regex'   => '#^' . $regex . '$#',
            'params'  => $params,
            'handler' => $handler,
            'opts'    => $opts,
        ];
    }

    public static function load(string $file): void
    {
        require $file;
    }

    public static function currentPath(): string
    {
        // Fallback routing for hosts without mod_rewrite: /index.php?r=/shop
        if (isset($_GET['r']) && is_string($_GET['r']) && $_GET['r'] !== '') {
            $path = '/' . trim($_GET['r'], '/');
            return $path === '/' ? '/' : rtrim($path, '/');
        }
        $uri  = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        $base = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $base = rtrim($base, '/');
        if ($base !== '' && str_starts_with($path, $base)) {
            $path = substr($path, strlen($base));
        }
        $path = '/' . trim($path, '/');
        return $path === '/' ? '/' : rtrim($path, '/');
    }

    public static function dispatch(): void
    {
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if ($method === 'HEAD') {
            $method = 'GET';
        }
        $path = self::currentPath();

        if ($method === 'POST') {
            $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
            if (!CSRF::validate(is_string($token) ? $token : null)) {
                http_response_code(419);
                include APP . '/views/errors/419.php';
                exit;
            }
        }

        foreach (self::$routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }

            array_shift($m);
            $args = [];
            foreach ($route['params'] as $i => $name) {
                $args[$name] = isset($m[$i]) ? trim(urldecode($m[$i])) : null;
            }

            self::runMiddlewares($route['opts'], $path);
            self::invoke($route['handler'], $args);
            return;
        }

        http_response_code(404);
        include APP . '/views/errors/404.php';
    }

    private static function runMiddlewares(array $opts, string $path): void
    {
        $isLoggedIn = Auth::check();

        if (!empty($opts['auth']) && !$isLoggedIn) {
            Flash::add('error', t('auth.login_required'));
            Session::set('intended_url', $path);
            header('Location: ' . url('/login'));
            exit;
        }

        if (!empty($opts['role'])) {
            if (!$isLoggedIn) {
                Flash::add('error', t('auth.login_required'));
                Session::set('intended_url', $path);
                header('Location: ' . url('/login'));
                exit;
            }
            $roles = (array) $opts['role'];
            if (!in_array(Auth::role(), $roles, true)) {
                http_response_code(403);
                include APP . '/views/errors/403.php';
                exit;
            }
        }

        if (!empty($opts['society'])) {
            $role = Auth::role();
            if ($role === 'resident' && Auth::societyId() === null) {
                Flash::add('info', t('auth.membership_pending'));
                header('Location: ' . url('/join/pending'));
                exit;
            }
        }
    }

    private static function invoke(string $handler, array $args): void
    {
        [$classShort, $action] = array_pad(explode('@', $handler, 2), 2, 'index');
        $class = 'App\\Controllers\\' . str_replace('/', '\\', $classShort);

        if (!class_exists($class)) {
            if (config('app.env') === 'development') {
                throw new \RuntimeException('Controller not found: ' . $class);
            }
            error_log('[Bachat] Missing controller: ' . $class);
            http_response_code(500);
            include APP . '/views/errors/500.php';
            exit;
        }
        $controller = new $class();
        if (!method_exists($controller, $action)) {
            if (config('app.env') === 'development') {
                throw new \RuntimeException('Method not found: ' . $class . '::' . $action);
            }
            error_log('[Bachat] Missing method: ' . $class . '::' . $action);
            http_response_code(500);
            include APP . '/views/errors/500.php';
            exit;
        }
        $controller->{$action}(...array_values($args));
    }
}
