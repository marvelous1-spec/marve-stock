<?php
declare(strict_types=1);

namespace App\Config;

final class Env
{
    /** @return array<string, string> */
    public static function load(string $path): array
    {
        $values = [];
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
                [$key, $value] = explode('=', $line, 2);
                $values[trim($key)] = trim($value, " \t\n\r\0\x0B\"");
            }
        }
        return $values;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        static $env = null;
        $env ??= self::load(dirname(__DIR__, 2) . '/.env');
        return $_ENV[$key] ?? getenv($key) ?: $env[$key] ?? $default;
    }
}
