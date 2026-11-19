<?php
declare(strict_types=1);

final class Env
{
    private static array $vars = [];

    public static function load(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            $v = trim($v);
            // убираем инлайн-комментарий " # ..." для незакавыченных значений
            if ($v !== '' && $v[0] !== '"' && $v[0] !== "'") {
                $v = trim(preg_replace('/\s+#.*$/', '', $v));
            }
            self::$vars[trim($k)] = trim($v, "\"'");
        }
    }

    public static function get(string $key, string $default = ''): string
    {
        $env = getenv($key);
        if ($env !== false && $env !== '') {
            return $env;
        }
        return self::$vars[$key] ?? $default;
    }
}
