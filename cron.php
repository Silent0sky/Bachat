<?php
/**
 * Bachat scheduled tasks (cron entry point).
 *
 * InfinityFree does not offer server-side cron. Use a free external cron such as
 * cron-job.org to GET:  https://your-site/cron.php?key=YOUR_CRON_SECRET
 * (set app.cron_secret in app/config/config.php). Recommended: every 15 minutes.
 */

declare(strict_types=1);

define('BACHAT_ROOT', __DIR__);
define('APP', __DIR__ . '/app');

require APP . '/helpers/functions.php';

$GLOBALS['__bachat_config'] = require APP . '/config/config.php';

spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) return;
    $rel = substr($class, strlen($prefix));
    $map = ['Core' => 'core', 'Controllers' => 'controllers', 'Models' => 'models', 'Services' => 'services'];
    $parts = explode('\\', $rel);
    if (isset($map[$parts[0]])) $parts[0] = $map[$parts[0]];
    $file = APP . '/' . implode('/', $parts) . '.php';
    if (is_file($file)) require $file;
});

$secret = $_GET['key'] ?? '';
if (!hash_equals((string) config('app.cron_secret', ''), (string) $secret)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Invalid cron key']);
    exit;
}

date_default_timezone_set(config('app.timezone', 'Asia/Kolkata'));

header('Content-Type: application/json; charset=utf-8');

try {
    $results = \App\Services\CronService::runAll();
    echo json_encode(['ok' => true, 'ran_at' => date('c'), 'results' => $results], JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    error_log('[Bachat Cron] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Cron failed — see server logs']);
}
