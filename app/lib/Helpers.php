<?php
declare(strict_types=1);

/** Экранирование вывода в HTML (защита от XSS). Используется во всех шаблонах. */
function e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function base_url(string $path = ''): string
{
    $base = rtrim(Env::get('APP_URL', ''), '/');
    if ($base === '' && Env::get('RAILWAY_PUBLIC_DOMAIN') !== '') {
        $base = 'https://' . Env::get('RAILWAY_PUBLIC_DOMAIN');
    }
    if ($base === '') {
        $scheme = is_https() ? 'https' : 'http';
        $base = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    return $base . '/' . ltrim($path, '/');
}

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
        || (int)($_SERVER['SERVER_PORT'] ?? 0) === 443;
}

function price_fmt(int|string|null $v): string
{
    return number_format((int)$v, 0, '', ' ') . ' ₸';
}

function num_fmt(mixed $v): string
{
    if ($v === null || $v === '') {
        return '';
    }
    $s = rtrim(rtrim(number_format((float)$v, 2, ',', ''), '0'), ',');
    return $s;
}

function redirect(string $url, int $code = 302): never
{
    header('Location: ' . $url, true, $code);
    exit;
}

function json_response(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Транслитерация для ЧПУ: «Чемодан Travel Pro L» → chemodan-travel-pro-l */
function slugify(string $s): string
{
    $map = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'zh','з'=>'z','и'=>'i','й'=>'y',
        'к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f',
        'х'=>'h','ц'=>'ts','ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
        'ә'=>'a','ғ'=>'g','қ'=>'k','ң'=>'n','ө'=>'o','ұ'=>'u','ү'=>'u','һ'=>'h','і'=>'i',
    ];
    $s = mb_strtolower(trim($s));
    $s = strtr($s, $map);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim((string)$s, '-');
}

function client_ip(): string
{
    // За Cloudflare/прокси хостинга доверяем только REMOTE_ADDR, иначе IP легко подделать.
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

function str_limit(string $s, int $len): string
{
    $s = trim(preg_replace('/\s+/u', ' ', strip_tags($s)));
    return mb_strlen($s) > $len ? rtrim(mb_substr($s, 0, $len - 1)) . '…' : $s;
}

/** Нормализация казахстанского номера в формат +77XXXXXXXXX. Возвращает null, если номер некорректен. */
function normalize_phone(string $raw): ?string
{
    $d = preg_replace('/\D+/', '', $raw);
    if (strlen($d) === 11 && ($d[0] === '8' || $d[0] === '7')) {
        $d = '7' . substr($d, 1);
    } elseif (strlen($d) === 10) {
        $d = '7' . $d;
    } else {
        return null;
    }
    // Казахстан: +7 7xx (мобильные) и +7 7xx стационарные коды; РФ (+7 9xx, 4xx, 8xx) тоже допускаем
    if (!preg_match('/^7[3-9]\d{9}$/', $d)) {
        return null;
    }
    return '+' . $d;
}

function phone_display(string $p): string
{
    $d = preg_replace('/\D+/', '', $p);
    if (strlen($d) !== 11) {
        return $p;
    }
    return sprintf('+7 (%s) %s-%s-%s', substr($d, 1, 3), substr($d, 4, 3), substr($d, 7, 2), substr($d, 9, 2));
}

function plural(int $n, string $one, string $few, string $many): string
{
    $n10 = $n % 10;
    $n100 = $n % 100;
    if ($n10 === 1 && $n100 !== 11) return $one;
    if ($n10 >= 2 && $n10 <= 4 && ($n100 < 10 || $n100 >= 20)) return $few;
    return $many;
}
