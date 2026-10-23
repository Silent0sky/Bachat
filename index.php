<?php
/**
 * Bachat (बचत) — Har Society Ki Smart Bachat
 * Front controller. All requests (except real files) are routed through here.
 * PHP 8.1+ compatible, no Composer required.
 */

declare(strict_types=1);

define('BACHAT_ROOT', __DIR__);
define('APP', __DIR__ . '/app');

require APP . '/helpers/functions.php';

/* ---------- Load configuration ---------- */
$configFile = APP . '/config/config.php';
if (!is_file($configFile)) {
    $configFile = APP . '/config/config.example.php';
}
$GLOBALS['__bachat_config'] = require $configFile;

/* ---------- Autoloader (no Composer needed) ---------- */
spl_autoload_register(function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $rel = substr($class, strlen($prefix));
    $map = ['Core' => 'core', 'Controllers' => 'controllers', 'Models' => 'models', 'Services' => 'services'];
    $parts = explode('\\', $rel);
    if (isset($map[$parts[0]])) {
        $parts[0] = $map[$parts[0]];
    }
    $file = APP . '/' . implode('/', $parts) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

use App\Core\App;

App::boot();
