<?php

declare(strict_types=1);

namespace App\Core;

final class Lang
{
    private const LOCALES   = ['en', 'hi', 'mr'];
    private static string $locale = 'en';
    private static array $lines   = [];

    public static function boot(): void
    {
        $sessionLang = Session::get('lang');
        $user        = Auth::user();
        $locale      = $sessionLang ?: ($user['language'] ?? null) ?: config('app.locale', 'en');
        self::setLocale(is_string($locale) ? $locale : 'en');
    }

    public static function setLocale(string $locale): void
    {
        self::$locale = in_array($locale, self::LOCALES, true) ? $locale : 'en';
        Session::set('lang', self::$locale);
        self::load();
    }

    public static function locale(): string
    {
        return self::$locale;
    }

    private static function load(): void
    {
        $lines = [];

        // 1) English base + English extras (fallback layer)
        foreach (self::filesFor('en') as $f) {
            $lines = array_merge($lines, self::safeRequire($f));
        }
        // 2) Selected locale base + extras (override layer)
        if (self::$locale !== 'en') {
            foreach (self::filesFor(self::$locale) as $f) {
                $lines = array_merge($lines, self::safeRequire($f));
            }
        }
        self::$lines = $lines;
    }

    /** @return string[] */
    private static function filesFor(string $locale): array
    {
        $files = [APP . '/lang/' . $locale . '.php'];
        $extra = glob(APP . '/lang/extra/*_' . $locale . '.php') ?: [];
        sort($extra);
        return array_merge($files, $extra);
    }

    private static function safeRequire(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        $data = require $file;
        return is_array($data) ? $data : [];
    }

    public static function get(string $key, array $replace = []): string
    {
        $line = self::$lines[$key] ?? $key;
        foreach ($replace as $k => $v) {
            $line = str_replace(':' . $k, (string) $v, $line);
        }
        return $line;
    }
}
