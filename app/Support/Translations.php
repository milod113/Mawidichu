<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

/**
 * Charge les chaînes d'interface (lang/ui/{locale}.json) envoyées au front.
 */
class Translations
{
    /** @var array<string, array<string, string>> */
    private static array $cache = [];

    /**
     * @return array<string, string>
     */
    public static function for(string $locale): array
    {
        if (isset(self::$cache[$locale])) {
            return self::$cache[$locale];
        }

        $path = lang_path('ui/'.$locale.'.json');

        return self::$cache[$locale] = File::exists($path)
            ? (json_decode(File::get($path), true) ?? [])
            : [];
    }
}
