<?php

namespace App\Support;

use RuntimeException;

class ComuniItalianiCatalog
{
    private static ?array $catalog = null;

    public static function names(): array
    {
        return array_keys(self::all());
    }

    public static function all(): array
    {
        if (self::$catalog !== null) {
            return self::$catalog;
        }

        $path = resource_path('data/comuni-italiani.json');
        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read comuni catalog at [{$path}].");
        }

        $decoded = json_decode($contents, true);

        if (!is_array($decoded)) {
            throw new RuntimeException("Invalid comuni catalog JSON at [{$path}].");
        }

        return self::$catalog = $decoded;
    }

    public static function codeFor(string $name): ?string
    {
        return self::all()[$name] ?? null;
    }
}
