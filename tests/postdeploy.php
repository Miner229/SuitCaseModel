<?php
declare(strict_types=1);
/**
 * Финальная проверка ПОСЛЕ размещения на домене (только чтение, ничего не меняет).
 *   php tests/postdeploy.php https://example.kz
 * Проверяет: SSL, редиректы http→https и www→без www, заголовки безопасности, robots/sitemap для продакшена,
 * счётчик Метрики и коды верификации, закрытость служебных файлов, сжатие, битые внутренние ссылки.
 */
$base = rtrim($argv[1] ?? '', '/');
if (!preg_match('~^https?://[^/]+$~', $base)) {
    fwrite(STDERR, "Укажите адрес сайта: php tests/postdeploy.php https://example.kz\n");
    exit(2);
}
$host = parse_url($base, PHP_URL_HOST);
$isHttps = str_starts_with($base, 'https://');
$pass = 0; $fail = 0; $warn = 0; $fails = [];
function t(string $n, bool $ok, string $i = '', bool $soft = false): void
{
    global $pass, $fail, $warn, $fails;
    if ($ok) { $pass++; echo "  \033[32m✓\033[0m $n\n"; return; }
    if ($soft) { $warn++; echo "  \033[33m! $n\033[0m $i\n"; return; }
    $fail++; $fails[] = "$n $i"; echo "  \033[31m✗ $n\033[0m $i\n";
}
function get(string $url, bool $follow = false, array $hdr = []): array
{
    $h = [];
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => $follow, CURLOPT_TIMEOUT => 20, CURLOPT_USERAGENT => 'Mozilla/5.0 (PostDeployCheck)',
        CURLOPT_HTTPHEADER => $hdr, CURLOPT_HEADERFUNCTION => function ($c, $l) use (&$h) { if (str_contains($l, ':')) { [$k, $v] = explode(':', $l, 2); $h[strtolower(trim($k))] = trim($v); } return strlen($l); }]);
    $b = (string)curl_exec($ch);
    $r = ['code' => (int)curl_getinfo($ch, CURLINFO_HTTP_CODE), 'body' => $b, 'h' => $h, 'err' => curl_error($ch), 'time' => curl_getinfo($ch, CURLINFO_TOTAL_TIME)];
    curl_close($ch);
    return $r;
}

echo "\n\033[1mSSL и зеркала\033[0m\n";
if ($isHttps) {
    $ctx = stream_context_create(['ssl' => ['capture_peer_cert' => true, 'verify_peer' => true, 'SNI_enabled' => true, 'peer_name' => $host]]);
    $s = @stream_socket_client("ssl://$host:443", $en, $es, 10, STREAM_CLIENT_CONNECT, $ctx);
    t('SSL-сертификат валиден', (bool)$s, $es);
    if ($s) {
        $cert = openssl_x509_parse(stream_context_get_params($s)['options']['ssl']['peer_certificate']);
        $days = (int)(($cert['validTo_time_t'] - time()) / 86400);
        t("Сертификат действует ещё $days дн. (выдан: " . ($cert['issuer']['O'] ?? $cert['issuer']['CN'] ?? '?') . ')', $days > 14);
    }
    $r = get('http://' . $host . '/');
    t('http:// → 301 на https://', $r['code'] === 301 && str_starts_with($r['h']['location'] ?? '', 'https://'), $r['code'] . ' ' . ($r['h']['location'] ?? ''));
    $r = get($base . '/');
    t('HSTS включён', isset($r['h']['strict-transport-security']));
} else {
    t('Сайт открывается по HTTPS', false, 'укажите https:// адрес');
}
$www = str_starts_with($host, 'www.') ? substr($host, 4) : 'www.' . $host;
$r = get(($isHttps ? 'https://' : 'http://') . $www . '/');
t("Зеркало $www → 301 на $host", $r['code'] === 301 && str_contains($r['h']['location'] ?? '', "//$host/"), $r['err'] ?: ($r['code'] . ' ' . ($r['h']['location'] ?? '')), true);

echo "\n\033[1mГлавная, заголовки, счётчики\033[0m\n";
$home = get($base . '/', false, ['Accept-Encoding: gzip']);
t('Главная отвечает 200', $home['code'] === 200);
t('Время ответа главной < 1 с (' . round($home['time'], 2) . ' с)', $home['time'] < 1);
t('Сжатие gzip/brotli включено', in_array($home['h']['content-encoding'] ?? '', ['gzip', 'br'], true), '', true);
t('Нет X-Powered-By (версия PHP скрыта)', !isset($home['h']['x-powered-by']), $home['h']['x-powered-by'] ?? '', true);
t('Content-Security-Policy и X-Content-Type-Options', isset($home['h']['content-security-policy'], $home['h']['x-content-type-options']));
$html = get($base . '/')['body'];
t('Счётчик Яндекс Метрики установлен', (bool)preg_match('/ym\((\d+),"init"/', $html), 'укажите номер в Админке → Настройки');
t('Мета-тег Яндекс Вебмастера', str_contains($html, 'name="yandex-verification"'), 'или подтвердите права DNS/файлом', true);
t('Мета-тег Google Search Console', str_contains($html, 'name="google-site-verification"'), 'или подтвердите права через DNS', true);
t('canonical указывает на этот домен', str_contains($html, '<link rel="canonical" href="' . $base . '/"'), 'проверьте APP_URL в .env');
t('Страница открыта для индексации', str_contains($html, 'content="index, follow"'));
t('В HTML нет ссылок на localhost / тестовый домен', !preg_match('~https?://(localhost|127\.0\.0\.1)~', $html));

echo "\n\033[1mrobots.txt и sitemap.xml\033[0m\n";
$rb = get($base . '/robots.txt')['body'];
t('robots.txt: сайт НЕ закрыт целиком (APP_ENV=production)', !preg_match('~^Disallow: /\s*$~m', $rb));
t('robots.txt: закрыты /admin/ и /api/, указан sitemap этого домена', str_contains($rb, 'Disallow: /admin/') && str_contains($rb, "Sitemap: $base/sitemap.xml"));
$sm = get($base . '/sitemap.xml');
libxml_use_internal_errors(true);
$x = simplexml_load_string($sm['body']);
$locs = [];
if ($x) foreach ($x->url as $u) $locs[] = (string)$u->loc;
t('sitemap.xml валиден, ' . count($locs) . ' URL', $x !== false && count($locs) > 20);
t('Все URL в sitemap на этом домене', $locs && !array_filter($locs, fn($l) => !str_starts_with($l, $base . '/')));

echo "\n\033[1mЗакрытые разделы\033[0m\n";
foreach (['/.env', '/app/bootstrap.php', '/database/schema.sql', '/bin/install.php', '/tests/smoke.php', '/storage/logs/php-error.log', '/router.php', '/uploads/test.php', '/.git/config'] as $p) {
    $c = get($base . $p)['code'];
    t("$p недоступен ($c)", in_array($c, [403, 404], true));
}
$r = get($base . '/admin/');
t('/admin/ требует входа', $r['code'] === 302 && str_contains($r['h']['location'] ?? '', '/admin/login/'));

echo "\n\033[1mБитые ссылки (обход всех страниц из sitemap)\033[0m\n";
$checked = []; $broken = [];
foreach ($locs as $page) {
    $r = get($page);
    if ($r['code'] !== 200) { $broken[] = "$page → {$r['code']}"; continue; }
    preg_match_all('~(?:href|src)="([^"#]+)"~', $r['body'], $m);
    foreach (array_unique($m[1]) as $link) {
        $link = html_entity_decode($link);
        if (preg_match('~^(tel:|mailto:|https?://(?!' . preg_quote($host, '~') . '))~', $link) || str_starts_with($link, 'data:')) continue;
        $abs = str_starts_with($link, 'http') ? $link : $base . $link;
        if (str_contains($abs, '/api/') || isset($checked[$abs])) continue;
        $checked[$abs] = get($abs)['code'];
        if ($checked[$abs] !== 200) $broken[] = "$abs → {$checked[$abs]} (на $page)";
    }
}
t('Проверено ' . count($locs) . ' страниц и ' . count($checked) . ' уникальных ссылок/картинок — битых нет', !$broken, implode("\n      ", array_slice($broken, 0, 10)));

echo "\n\033[1mИтого: $pass ок, $warn предупреждений, $fail ошибок\033[0m\n";
foreach ($fails as $f) echo "  - $f\n";
echo "\nВручную: отправить заявку с телефона → проверить Telegram и Админку → Заявки; в Метрике → «Посещения» и цели; в Вебмастере → «Индексирование».\n";
exit($fail ? 1 : 0);
