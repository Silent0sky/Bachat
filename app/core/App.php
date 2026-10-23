<?php

declare(strict_types=1);

namespace App\Core;

final class App
{
    public static function boot(): void
    {
        $cfg = $GLOBALS['__bachat_config'] ?? [];
        $env = $cfg['app']['env'] ?? 'production';

        ini_set('display_errors', $env === 'development' ? '1' : '0');
        error_reporting($env === 'development' ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);
        date_default_timezone_set($cfg['app']['timezone'] ?? 'Asia/Kolkata');

        Session::start();

        set_exception_handler(static function (\Throwable $e) use ($env): void {
            if ($env === 'development') {
                http_response_code(500);
                echo '<pre style="padding:20px;white-space:pre-wrap;font-family:monospace">'
                    . e($e->getMessage() . "\n\n" . $e->getTraceAsString()) . '</pre>';
                exit;
            }
            error_log('[Bachat] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            http_response_code(500);
            include APP . '/views/errors/500.php';
        });

        Lang::boot();
        Router::load(APP . '/routes.php');
        Router::dispatch();
    }
}
