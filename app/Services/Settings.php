<?php

namespace App\Services;

use App\Models\Setting;

class Settings
{
    public static function get(string $key, ?string $fallback = null): ?string
    {
        $row = Setting::find($key);

        return $row ? $row->value : $fallback;
    }

    public static function set(string $key, mixed $value): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
    }

    /** @return array<string, string> */
    public static function all(): array
    {
        return Setting::pluck('value', 'key')->all();
    }
}
