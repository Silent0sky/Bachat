<?php

declare(strict_types=1);

namespace App\Core;

final class Flash
{
    private const KEY = '_flash';

    public static function add(string $type, string $message): void
    {
        $msgs          = Session::get(self::KEY, []);
        $msgs[]        = ['type' => $type, 'message' => $message];
        Session::set(self::KEY, $msgs);
    }

    /** @return array<int, array{type:string,message:string}> */
    public static function all(): array
    {
        $msgs = Session::get(self::KEY, []);
        Session::forget(self::KEY);
        return is_array($msgs) ? $msgs : [];
    }
}
