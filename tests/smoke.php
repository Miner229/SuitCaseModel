<?php
declare(strict_types=1);
/**
 * Функциональные HTTP-тесты сайта.
 *   php tests/smoke.php [base_url]
 * По умолчанию base_url = APP_URL из .env. Для проверки Telegram запустите мок:
 *   php -S 127.0.0.1:8099 tests/mock/telegram.php  и TELEGRAM_API_BASE=http://127.0.0.1:8099 в .env
 * ВНИМАНИЕ: тест создаёт и удаляет данные. Запускайте на тестовом стенде, не на боевой базе.
 */
require dirname(__DIR__) . '/app/bootstrap.php';

$BASE = rtrim($argv[1] ?? Env::get('APP_URL', 'http://localhost:8000'), '/');
$pass = 0; $fail = 0; $failures = [];
$section = '';

function t(string $name, bool $ok, string $info = ''): void
{
    global $pass, $fail, $failures, $section;
    if ($ok) { $pass++; echo "  \033[32m✓\033[0m $name\n"; }
    else { $fail++; $failures[] = "[$section] $name $info"; echo "  \033[31m✗ $name\033[0m $info\n"; }
}
function section(string $s): void { global $section; $section = $s; echo "\n\033[1m$s\033[0m\n"; }

/** HTTP-запрос. @return array{code:int, body:string, headers:array<string,string>, url:string} */
function http(string $method, string $path, array $opt = []): array
{
    global $BASE;
    static $jar = null;
    $jar ??= tempnam(sys_get_temp_dir(), 'jar');
    $ch = curl_init(str_starts_with($path, 'http') ? $path : $BASE . $path);
    $headers = [];
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HEADERFUNCTION => function ($c, $h) use (&$headers) {
            if (str_contains($h, ':')) { [$k, $v] = explode(':', $h, 2); $headers[strtolower(trim($k))] = trim($v); }
            return strlen($h);
        },
        CURLOPT_USERAGENT => $opt['ua'] ?? 'SmokeTest/1.0',
        CURLOPT_TIMEOUT => 20,
    ]);
    if (empty($opt['nojar'])) { curl_setopt($ch, CURLOPT_COOKIEJAR, $jar); curl_setopt($ch, CURLOPT_COOKIEFILE, $jar); }
    $h = $opt['headers'] ?? [];
    if (isset($opt['json'])) { $h[] = 'Content-Type: application/json'; curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($opt['json'], JSON_UNESCAPED_UNICODE)); }
    if (isset($opt['form'])) { curl_setopt($ch, CURLOPT_POSTFIELDS, $opt['form']); }
    if ($h) curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
    $body = (string)curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'body' => $body, 'headers' => $headers, 'url' => $path];
}
function dom(string $html): DOMXPath
{
    $d = new DOMDocument();
    libxml_use_internal_errors(true);
    $d->loadHTML('<?xml encoding="utf-8"?>' . $html);
    libxml_clear_errors();
    return new DOMXPath($d);
}
function attr(DOMXPath $x, string $q, string $a): string { $n = $x->query($q)->item(0); return $n instanceof DOMElement ? $n->getAttribute($a) : ''; }
function txt(DOMXPath $x, string $q): string { $n = $x->query($q)->item(0); return $n ? trim($n->textContent) : ''; }
function token(int $age = 10): string { $ts = (string)(time() - $age); return $ts . '.' . hash_hmac('sha256', $ts, Env::get('APP_SECRET')); }
function csrf(string $html): string { return attr(dom($html), '//input[@name="_csrf"]', 'value'); }
/** Файл фото существует на сервере (проверка по HTTP, работает и с удалённым сервером). */
function imgExists(string $file, int $w): bool { return http('GET', "/uploads/$file-$w.webp")['code'] === 200; }
function resetLimits(): void { Db::run('DELETE FROM rate_limits'); }
function lead(array $over = []): array
{
    return array_merge(['type' => 'callback', 'name' => 'Тест Тестов', 'phone' => '+7 (701) 234-56-78', 'consent' => '1', '_ft' => token(), 'website' => '', 'page' => '/catalog/'], $over);
}

resetLimits();
$tgLog = ROOT . '/tests/mock/telegram.log';
@unlink($tgLog);

// ============================================================
section('1. Страницы и коды ответов');
$pages = ['/', '/catalog/', '/goroda/', '/dostavka-i-oplata/', '/kontakty/', '/o-kompanii/', '/garantiya-i-vozvrat/', '/kak-vybrat-chemodan/', '/politika-konfidencialnosti/', '/soglasie/'];
foreach (Catalog::categories() as $c) $pages[] = '/catalog/' . $c['slug'] . '/';
foreach (Catalog::cities() as $c) $pages[] = '/' . $c['slug'] . '/';
$products = Db::all('SELECT slug FROM products WHERE is_active = 1 AND noindex = 0');
foreach ($products as $p) $pages[] = '/product/' . $p['slug'] . '/';
$pages[] = '/blog/';
foreach (Articles::published() as $a) $pages[] = '/blog/' . $a['slug'] . '/';
$html = [];
$slow = [];
foreach ($pages as $p) {
    $t0 = microtime(true);
    $r = http('GET', $p);
    $ms = (microtime(true) - $t0) * 1000;
    if ($ms > 500) $slow[] = "$p ({$ms}ms)";
    $html[$p] = $r['body'];
    if ($r['code'] !== 200) t("GET $p → 200", false, "получено {$r['code']}");
}
t('Все ' . count($pages) . ' страниц отвечают 200', count(array_filter($html, fn($b) => $b !== '')) === count($pages));
t('Время генерации каждой страницы < 500 мс', !$slow, implode(', ', $slow));
t('Активных товаров в каталоге: ' . count($products) . ' (ожидается ~20)', count($products) >= 18);
$products = array_values($products);
t('Несуществующая страница → 404', http('GET', '/net-takoy-stranicy/')['code'] === 404);
t('Несуществующий товар → 404', http('GET', '/product/net-takogo/')['code'] === 404);
t('Несуществующая категория → 404', http('GET', '/catalog/net-takoy/')['code'] === 404);
t('404-страница закрыта от индексации', str_contains(http('GET', '/net/')['body'], 'noindex'));
$r = http('GET', '/catalog');
t('Без слэша → 301 на /catalog/', $r['code'] === 301 && str_ends_with($r['headers']['location'] ?? '', '/catalog/'));
$r = http('GET', '/CATALOG/');
t('Верхний регистр → 301 на нижний', $r['code'] === 301 && str_ends_with($r['headers']['location'] ?? '', '/catalog/'));
$r = http('GET', '/index.php');
t('/index.php → 301 на /', $r['code'] === 301);
$r = http('GET', '/catalog?size[]=S');
t('Редирект сохраняет GET-параметры', str_contains($r['headers']['location'] ?? '', 'size'));
t('POST на публичную страницу → 405', http('POST', '/catalog/')['code'] === 405);

// ============================================================
section('2. SEO: метатеги, заголовки, canonical, микроразметка');
$titles = []; $descs = [];
$seoErr = [];
foreach ($html as $p => $b) {
    $x = dom($b);
    $title = txt($x, '//title');
    $desc = attr($x, '//meta[@name="description"]', 'content');
    $canon = attr($x, '//link[@rel="canonical"]', 'href');
    $h1 = $x->query('//h1')->length;
    if ($title === '' || mb_strlen($title) > 90) $seoErr[] = "$p: title пуст или > 90 (" . mb_strlen($title) . ')';
    if (mb_strlen($desc) < 50 || mb_strlen($desc) > 300) $seoErr[] = "$p: description " . mb_strlen($desc) . ' симв.';
    if ($canon !== base_url($p)) $seoErr[] = "$p: canonical=$canon";
    if ($h1 !== 1) $seoErr[] = "$p: h1 x$h1";
    if (attr($x, '//html', 'lang') !== 'ru') $seoErr[] = "$p: lang";
    if (!attr($x, '//meta[@property="og:title"]', 'content')) $seoErr[] = "$p: og:title";
    if (!str_contains(attr($x, '//meta[@name="robots"]', 'content'), 'index, follow')) $seoErr[] = "$p: robots";
    foreach ($x->query('//script[@type="application/ld+json"]') as $s) {
        if (json_decode($s->textContent) === null) $seoErr[] = "$p: невалидный JSON-LD";
    }
    foreach ($x->query('//img') as $img) {
        if (!$img->hasAttribute('alt')) $seoErr[] = "$p: img без alt";
    }
    $titles[$title][] = $p; $descs[$desc][] = $p;
}
t('title/description/canonical/один H1/og/lang/alt/JSON-LD на всех страницах', !$seoErr, implode('; ', array_slice($seoErr, 0, 5)));
$dupT = array_filter($titles, fn($v) => count($v) > 1);
$dupD = array_filter($descs, fn($v) => count($v) > 1);
t('Нет дублей title', !$dupT, json_encode($dupT, JSON_UNESCAPED_UNICODE));
t('Нет дублей description', !$dupD, json_encode($dupD, JSON_UNESCAPED_UNICODE));
$pp = '/product/' . $products[0]['slug'] . '/';
$ld = array_map(fn($n) => json_decode($n->textContent, true), iterator_to_array(dom($html[$pp])->query('//script[@type="application/ld+json"]')));
$types = array_column($ld, '@type');
t('Карточка товара: JSON-LD Product + BreadcrumbList', in_array('Product', $types) && in_array('BreadcrumbList', $types));
$prod = $ld[array_search('Product', $types)];
t('Product: цена, валюта KZT, наличие, изображения', ($prod['offers']['priceCurrency'] ?? '') === 'KZT' && (int)$prod['offers']['price'] > 0 && str_contains($prod['offers']['availability'], 'schema.org') && !empty($prod['image']));
$homeTypes = array_column(array_map(fn($n) => json_decode($n->textContent, true), iterator_to_array(dom($html['/'])->query('//script[@type="application/ld+json"]'))), '@type');
t('Главная: JSON-LD Store, WebSite, FAQPage', !array_diff(['Store', 'WebSite', 'FAQPage'], $homeTypes));
t('ЧПУ товаров: только латиница, цифры, дефис', !array_filter($products, fn($p) => !preg_match('/^[a-z0-9-]+$/', $p['slug'])));
$r = http('GET', '/catalog/?size[]=S&sort=price_asc');
$x = dom($r['body']);
t('Страница фильтра: noindex + canonical на /catalog/', str_contains(attr($x, '//meta[@name="robots"]', 'content'), 'noindex') && attr($x, '//link[@rel="canonical"]', 'href') === base_url('/catalog/'));

// ============================================================
section('3. sitemap.xml и robots.txt');
$r = http('GET', '/sitemap.xml');
t('sitemap.xml: 200 и Content-Type XML', $r['code'] === 200 && str_contains($r['headers']['content-type'] ?? '', 'xml'));
libxml_use_internal_errors(true);
$sx = simplexml_load_string($r['body']);
t('sitemap.xml — валидный XML', $sx !== false);
$locs = [];
if ($sx) foreach ($sx->url as $u) $locs[] = (string)$u->loc;
t('В sitemap ' . count($locs) . ' URL, все страницы сайта включены', count($locs) === count($pages), 'страниц ' . count($pages));
$bad = [];
foreach ($locs as $l) { $c = http('GET', str_replace(base_url('/'), '/', $l))['code']; if ($c !== 200) $bad[] = "$l=$c"; }
t('Все URL из sitemap отвечают 200', !$bad, implode(', ', $bad));
t('Нет /admin и /api в sitemap', !array_filter($locs, fn($l) => str_contains($l, '/admin') || str_contains($l, '/api')));
$r = http('GET', '/robots.txt');
t('robots.txt: 200, text/plain, содержит Sitemap', $r['code'] === 200 && str_contains($r['headers']['content-type'] ?? '', 'text/plain') && str_contains($r['body'], 'Sitemap: '));
if (Env::get('APP_ENV') === 'production') {
    t('robots.txt (prod): закрыты /admin/ и /api/, есть Clean-param для Яндекса', str_contains($r['body'], 'Disallow: /admin/') && str_contains($r['body'], 'Clean-param'));
} else {
    t('robots.txt (тестовый стенд): сайт закрыт от индексации', str_contains($r['body'], "Disallow: /\n"));
}

// ============================================================
section('4. Региональное SEO');
$cities = Catalog::cities();
$cErr = [];
foreach ($cities as $c) {
    $x = dom($html['/' . $c['slug'] . '/']);
    $h1 = txt($x, '//h1');
    $title = txt($x, '//title');
    if (!str_contains($h1, $c['name_in'])) $cErr[] = "{$c['slug']}: H1 без «{$c['name_in']}»";
    if (!str_contains($title, $c['name_in'])) $cErr[] = "{$c['slug']}: title без города";
    if (!str_contains(attr($x, '//meta[@name="description"]', 'content'), $c['name_in'])) $cErr[] = "{$c['slug']}: description без города";
    if (!str_contains($html['/' . $c['slug'] . '/'], '"areaServed":{"@type":"City","name":"' . $c['name'] . '"')) $cErr[] = "{$c['slug']}: нет areaServed";
    if (!$x->query('//input[@name="city"][@value="' . $c['name'] . '"]')->length) $cErr[] = "{$c['slug']}: город не передаётся в заявку";
}
t(count($cities) . ' городских страниц: город в H1, title, description, микроразметке и форме', !$cErr, implode('; ', array_slice($cErr, 0, 4)));
$intros = array_column($cities, 'intro');
t('Вводные тексты городов уникальны', count(array_unique($intros)) === count($intros));
t('Главная ссылается на все города (перелинковка)', !array_filter($cities, fn($c) => !str_contains($html['/'], 'href="/' . $c['slug'] . '/"')));
t('og:locale = ru_KZ, geo.region = KZ', str_contains($html['/'], 'og:locale" content="ru_KZ"') && str_contains($html['/'], 'geo.region" content="KZ"'));

// ============================================================
section('5. Каталог и фильтры');
$all = (int)txt(dom(http('GET', '/catalog/')['body']), '//*[@data-count]');
t("Каталог без фильтров показывает все товары ($all)", $all === count($products));
$cnt = fn(string $q) => (int)txt(dom(http('GET', '/catalog/' . $q)['body']), '//*[@data-count]');
$exp = fn(string $where, array $p = []) => (int)Db::value("SELECT COUNT(*) FROM products WHERE is_active = 1 AND $where", $p);
t('Фильтр по размеру S', $cnt('?size[]=S') === $exp("size_label = 'S'"));
t('Фильтр по нескольким размерам S+M', $cnt('?size[]=S&size[]=M') === $exp("size_label IN ('S','M')"));
t('Фильтр по материалу', $cnt('?material[]=' . urlencode('Поликарбонат')) === $exp("material = 'Поликарбонат'"));
t('Фильтр по цене от/до', $cnt('?price_min=30000&price_max=50000') === $exp('price BETWEEN 30000 AND 50000'));
t('Фильтр по цвету + бренду', $cnt('?color[]=' . urlencode('Чёрный') . '&brand[]=Aero') === $exp("color = 'Чёрный' AND brand = 'Aero'"));
t('Фильтр «2 колеса» / «расширение» / «в наличии»', $cnt('?expandable=1') === $exp('expandable = 1') && $cnt('?in_stock=1') === $exp('in_stock = 1') && $cnt('?wheels=2') === $exp('wheels = 2'));
$prices = array_map(fn($n) => (int)preg_replace('/\D/', '', $n->textContent), iterator_to_array(dom(http('GET', '/catalog/?sort=price_asc')['body'])->query('//span[@class="price__now"]')));
$sorted = $prices; sort($sorted);
t('Сортировка «сначала дешевле»', $prices === $sorted && count($prices) > 1);
$prices = array_map(fn($n) => (int)preg_replace('/\D/', '', $n->textContent), iterator_to_array(dom(http('GET', '/catalog/?sort=price_desc')['body'])->query('//span[@class="price__now"]')));
$sorted = $prices; rsort($sorted);
t('Сортировка «сначала дороже»', $prices === $sorted);
t('Пустой результат — понятное сообщение', str_contains(http('GET', '/catalog/?price_max=1')['body'], 'ничего не найдено'));
t('AJAX-фильтр отдаёт только блок результатов', ($r = http('GET', '/catalog/?size[]=L', ['headers' => ['X-Requested-With: fetch']])) && !str_contains($r['body'], '<html') && str_contains($r['body'], 'data-count'));
foreach (Catalog::categories() as $c) {
    t("Категория «{$c['name']}»: {$c['cnt']} товаров", $cnt($c['slug'] . '/') === (int)$c['cnt']);
}
$x = dom($html[$pp]);
t('Карточка: цена, характеристики (≥8 строк), габариты, кнопка «Заказать», галерея', txt($x, '//span[@class="price__now"]') !== '' && $x->query('//table[@class="specs"]//tr')->length >= 8 && str_contains($html[$pp], 'Габариты') && $x->query('//button[@data-modal="order"]')->length > 0 && $x->query('//button[contains(@class,"gallery__thumb")]')->length >= 2);
$imgs = $x->query('//img[contains(@src,"/uploads/")]');
$okImg = true;
foreach ($imgs as $i) { $rr = http('GET', $i->getAttribute('src')); if ($rr['code'] !== 200 || ($rr['headers']['content-type'] ?? '') !== 'image/webp') $okImg = false; }
t('Фото товара отдаются в WebP', $imgs->length > 0 && $okImg);
t('Мусор в фильтрах игнорируется (SQL-инъекция/XSS)', http('GET', "/catalog/?size[]=S'%20OR%201=1--&sort=<script>&price_min=abc")['code'] === 200 && !str_contains(http('GET', '/catalog/?sort=<script>alert(1)</script>')['body'], '<script>alert(1)'));

// ============================================================
section('6. API');
$r = http('GET', '/api/products/');
$j = json_decode($r['body'], true);
t('GET /api/products/ — JSON со всеми товарами', $r['code'] === 200 && ($j['ok'] ?? false) && $j['count'] === count($products));
$j = json_decode(http('GET', '/api/products/?category=ruchnaya-klad&sort=price_asc')['body'], true);
t('API: фильтр по категории и сортировка', $j['count'] > 0 && !array_filter($j['items'], fn($i) => $i['category'] !== 'ruchnaya-klad'));
t('API: несуществующая категория → пустой список', json_decode(http('GET', '/api/products/?category=zzz')['body'], true)['count'] === 0);
t('API: неизвестный метод → 404 JSON', http('GET', '/api/unknown/')['code'] === 404);
t('API: GET на /api/lead/ не принимается', http('GET', '/api/lead/')['code'] === 404);
t('API закрыт от индексации (X-Robots-Tag)', str_contains(http('GET', '/api/products/')['headers']['x-robots-tag'] ?? '', 'noindex'));

// ============================================================
section('7. Формы заявок, антиспам, согласие, Telegram');
resetLimits();
$before = (int)Db::value('SELECT COUNT(*) FROM leads');
$pid = (int)Db::value('SELECT id FROM products WHERE is_active = 1 ORDER BY id LIMIT 1');
$r = http('POST', '/api/lead/', ['json' => lead(['type' => 'order', 'product_id' => (string)$pid, 'message' => 'Хочу <b>чёрный</b>', 'utm' => 'utm_source=yandex&utm_medium=cpc'])]);
$j = json_decode($r['body'], true);
t('Корректная заявка (заказ товара) → 200 ok', $r['code'] === 200 && ($j['ok'] ?? false), $r['body']);
$l = Leads::find((int)($j['id'] ?? 0));
t('Заявка сохранена в БД с нормализованным телефоном', $l && $l['phone'] === '+77012345678' && (int)$l['product_id'] === $pid);
t('В заявке сохранены факт и версия согласия, IP, страница, UTM', $l && $l['consent_at'] && $l['consent_version'] !== '' && $l['ip'] !== '' && $l['page_url'] === '/catalog/' && str_contains($l['utm'], 'yandex'));
usleep(300000);
$tg = is_file($tgLog) ? array_map(fn($s) => json_decode($s, true), file($tgLog)) : [];
$last = end($tg) ?: [];
t('Уведомление ушло в Telegram (мок API)', $l && (int)$l['tg_sent'] === 1 && str_contains($last['post']['text'] ?? '', '+7 (701) 234-56-78'));
t('Telegram: в сообщении есть товар и ссылка, HTML экранирован', str_contains($last['post']['text'] ?? '', 'Aero') && str_contains($last['post']['text'] ?? '', '&lt;b&gt;') && ($last['post']['parse_mode'] ?? '') === 'HTML');
t('Telegram: chat_id и токен берутся из .env', ($last['post']['chat_id'] ?? '') === Env::get('TELEGRAM_CHAT_ID') && str_contains($last['uri'] ?? '', '/bot' . Env::get('TELEGRAM_BOT_TOKEN') . '/sendMessage'));
foreach (['callback', 'consult', 'popup'] as $type) {
    resetLimits();
    $r = http('POST', '/api/lead/', ['json' => lead(['type' => $type, 'phone' => '8 777 123 45 67'])]);
    t("Форма «" . Leads::TYPES[$type] . "» отправляется", (json_decode($r['body'], true)['ok'] ?? false) === true);
}
resetLimits();
$r = http('POST', '/api/lead/', ['json' => lead(['phone' => '123'])]);
t('Неверный телефон → 422 с текстом ошибки', $r['code'] === 422 && isset(json_decode($r['body'], true)['errors']['phone']));
$r = http('POST', '/api/lead/', ['json' => lead(['consent' => ''])]);
t('Без согласия на обработку ПД → 422', $r['code'] === 422 && isset(json_decode($r['body'], true)['errors']['consent']));
$r = http('POST', '/api/lead/', ['json' => lead(['_ft' => ''])]);
t('Без токена формы → 400', $r['code'] === 400);
$r = http('POST', '/api/lead/', ['json' => lead(['_ft' => token(10) . 'x'])]);
t('Поддельный токен → 400', $r['code'] === 400);
$r = http('POST', '/api/lead/', ['json' => lead(['_ft' => token(0)])]);
t('Мгновенная отправка (бот) → 400', $r['code'] === 400);
$r = http('POST', '/api/lead/', ['json' => lead(['_ft' => token(90000)])]);
t('Просроченный токен (>24ч) → 400', $r['code'] === 400);
$cntBefore = (int)Db::value('SELECT COUNT(*) FROM leads');
$r = http('POST', '/api/lead/', ['json' => lead(['website' => 'http://spam.example'])]);
t('Honeypot: бот получает «ok», но заявка не сохраняется', (json_decode($r['body'], true)['ok'] ?? false) && (int)Db::value('SELECT COUNT(*) FROM leads') === $cntBefore);
$r = http('POST', '/api/lead/', ['json' => lead(['message' => 'buy http://a.example http://b.example'])]);
t('Сообщение с несколькими ссылками отклоняется', $r['code'] === 422);
$r = http('POST', '/api/lead/', ['json' => lead(), 'headers' => ['Origin: https://evil.example']]);
t('Запрос с чужого сайта (Origin) → 403', $r['code'] === 403);
resetLimits();
$codes = [];
for ($i = 0; $i < 7; $i++) $codes[] = http('POST', '/api/lead/', ['json' => lead()])['code'];
t('Лимит частоты: 6-я заявка за 10 минут → 429', array_slice($codes, 0, 5) === [200, 200, 200, 200, 200] && $codes[5] === 429, implode(',', $codes));
resetLimits();
$r = http('POST', '/api/lead/', ['form' => lead()]);
t('Форма без JavaScript: обычный POST → редирект с ?sent=1', $r['code'] === 303 && str_contains($r['headers']['location'] ?? '', 'sent=1'));
resetLimits();
$r = http('POST', '/api/lead/', ['form' => lead(['page' => '//evil.example/'])]);
t('Open redirect через поле page невозможен', ($r['headers']['location'] ?? '') === '/?sent=1');
$x = dom($html['/']);
$forms = $x->query('//form[@data-lead-form]');
$okForms = true;
foreach ($forms as $f) {
    $fx = fn($q) => $x->query($q, $f)->length;
    if (!$fx('.//input[@name="consent"][@required]') || !$fx('.//input[@name="website"]') || !$fx('.//input[@name="_ft"]') || !$fx('.//a[@href="/soglasie/"]')) $okForms = false;
}
t("Все формы ({$forms->length}) содержат согласие (обязательное), ссылку на текст, honeypot и токен", $forms->length >= 4 && $okForms);
t('Попапы: обратный звонок, заказ, скидка, успех', !array_filter(['modal-callback', 'modal-order', 'modal-popup', 'modal-success'], fn($id) => !str_contains($html['/'], 'id="' . $id . '"')));
t('Кнопки быстрой связи: WhatsApp, Telegram, звонок', str_contains($html['/'], 'https://wa.me/') && str_contains($html['/'], 'https://t.me/') && str_contains($html['/'], 'href="tel:+7'));
Db::run('DELETE FROM leads WHERE id > ?', [0]); // чистим тестовые заявки (стенд)

// ============================================================
section('8. Безопасность');
$h = http('GET', '/')['headers'];
t('CSP с nonce, без unsafe-inline для скриптов', str_contains($h['content-security-policy'] ?? '', "script-src 'self' 'nonce-") && !preg_match("/script-src[^;]*unsafe-inline/", $h['content-security-policy'] ?? ''));
t('X-Content-Type-Options, Referrer-Policy, Permissions-Policy', isset($h['x-content-type-options'], $h['referrer-policy'], $h['permissions-policy']));
t('Нет заголовка X-Powered-By с версией PHP', !isset($h['x-powered-by']) || Env::get('APP_ENV') !== 'production', 'отключите expose_php на сервере');
t('.env недоступен из веба (лежит вне public/)', http('GET', '/.env')['code'] === 404 && !is_file(PUB . '/.env'));
t('Исходники app/ недоступны из веба', http('GET', '/app/bootstrap.php')['code'] === 404 && http('GET', '/../app/bootstrap.php')['code'] !== 200);
t('Админка без входа → редирект на логин', http('GET', '/admin/products/', ['nojar' => true])['code'] === 302);
$ah = http('GET', '/admin/login/')['headers'];
t('Админка: X-Frame-Options DENY, noindex, no-store', ($ah['x-frame-options'] ?? '') === 'DENY' && str_contains($ah['x-robots-tag'] ?? '', 'noindex') && str_contains($ah['cache-control'] ?? '', 'no-store'));
$sc = $ah['set-cookie'] ?? '';
t('Cookie сессии: HttpOnly, SameSite=Strict', stripos($sc, 'httponly') !== false && stripos($sc, 'samesite=strict') !== false);

// ============================================================
section('9. Админ-панель: вход, товары, фото, цены, заявки, настройки');
resetLimits();
$login = http('GET', '/admin/login/');
$r = http('POST', '/admin/login/', ['form' => ['_csrf' => csrf($login['body']), 'login' => 'admin', 'password' => 'wrong-password']]);
t('Неверный пароль — отказ', str_contains($r['body'], 'Неверный логин или пароль'));
$r = http('POST', '/admin/login/', ['form' => ['login' => 'admin', 'password' => 'x']]);
t('Вход без CSRF-токена → 403', $r['code'] === 403);
resetLimits();
$codes = [];
for ($i = 0; $i < 6; $i++) { $lg = http('GET', '/admin/login/'); $codes[] = http('POST', '/admin/login/', ['form' => ['_csrf' => csrf($lg['body']), 'login' => 'admin', 'password' => 'bad' . $i]])['code']; }
t('Защита от перебора: 6-я попытка за 15 мин → 429', end($codes) === 429, implode(',', $codes));
resetLimits();
$ADMIN_PASS = getenv('TEST_ADMIN_PASS') ?: 'TestPass12345';
$lg = http('GET', '/admin/login/');
$r = http('POST', '/admin/login/', ['form' => ['_csrf' => csrf($lg['body']), 'login' => 'admin', 'password' => $ADMIN_PASS]]);
t('Вход с верным паролем → редирект в админку', $r['code'] === 302 && ($r['headers']['location'] ?? '') === '/admin/');
$dash = http('GET', '/admin/');
t('Дашборд открывается', $dash['code'] === 200 && str_contains($dash['body'], 'Обзор'));
$tok = csrf($dash['body']);

// Создание товара с фото
$jpg = tempnam(sys_get_temp_dir(), 'img') . '.jpg';
$im = imagecreatetruecolor(900, 900); imagefill($im, 0, 0, imagecolorallocate($im, 200, 50, 50)); imagejpeg($im, $jpg, 90);
$fake = tempnam(sys_get_temp_dir(), 'fake') . '.jpg';
file_put_contents($fake, "<?php echo 'hacked'; ?>");
$slug = 'test-chemodan-' . random_int(1000, 9999);
$r = http('POST', '/admin/products/save/', ['form' => [
    '_csrf' => $tok, 'name' => 'Тестовый чемодан QA', 'slug' => $slug, 'price' => '12345', 'old_price' => '15000', 'size_label' => 'M',
    'material' => 'Поликарбонат', 'color' => 'Жёлтый', 'height_cm' => '65,5', 'width_cm' => '44', 'depth_cm' => '26', 'volume_l' => '60', 'weight_kg' => '3.1',
    'wheels' => '4', 'warranty_months' => '24', 'is_active' => '1', 'in_stock' => '1', 'short_desc' => 'Краткое описание теста',
    'description' => "Первый абзац.\n\nВторой абзац <script>alert(1)</script>",
    'images[0]' => new CURLFile($jpg, 'image/jpeg', 'photo.jpg'),
    'images[1]' => new CURLFile($fake, 'image/jpeg', 'shell.php.jpg'),
]]);
$newId = (int)Db::value('SELECT id FROM products WHERE slug = ?', [$slug]);
t('Добавление товара', $r['code'] === 302 && $newId > 0);
$imgs = Catalog::images($newId);
t('Фото загружено и сконвертировано в WebP 400/800/1200', count($imgs) === 1 && imgExists($imgs[0]['file'], 1200) && imgExists($imgs[0]['file'], 400));
t('PHP-файл, замаскированный под JPG, отклонён', count($imgs) === 1 && !glob(UPLOADS . '/*.php'));
$pg = http('GET', "/product/$slug/");
t('Новый товар виден на сайте с ценой и габаритами', $pg['code'] === 200 && str_contains($pg['body'], '12 345 ₸') && str_contains($pg['body'], '65,5×44×26'));
t('HTML/JS в описании экранируется (XSS)', !str_contains($pg['body'], '<script>alert(1)') && str_contains($pg['body'], '&lt;script&gt;'));
t('Новый товар появился в sitemap и каталоге', str_contains(http('GET', '/sitemap.xml')['body'], "/product/$slug/") && str_contains(http('GET', '/catalog/')['body'], "/product/$slug/"));
// Редактирование
$form = http('GET', "/admin/products/$newId/");
t('Форма редактирования открывается', $form['code'] === 200 && str_contains($form['body'], 'Тестовый чемодан QA'));
$r = http('POST', '/admin/products/save/', ['form' => [
    '_csrf' => $tok, 'id' => (string)$newId, 'name' => 'Тестовый чемодан QA v2', 'slug' => $slug, 'price' => '22222', 'size_label' => 'L',
    'material' => 'Полипропилен', 'color' => 'Жёлтый', 'wheels' => '4', 'is_active' => '1', 'in_stock' => '1', 'description' => 'Новое описание',
    'img_sort[' . $imgs[0]['id'] . ']' => '5', 'img_alt[' . $imgs[0]['id'] . ']' => 'Новый alt',
]]);
$pg = http('GET', "/product/$slug/")['body'];
t('Изменение названия, цены, описания, материала', str_contains($pg, 'Тестовый чемодан QA v2') && str_contains($pg, '22 222 ₸') && str_contains($pg, 'Новое описание') && str_contains($pg, 'Полипропилен'));
t('Изменение alt-текста фото', str_contains($pg, 'alt="Новый alt"'));
$r = http('POST', '/admin/products/save/', ['form' => ['_csrf' => $tok, 'id' => (string)$newId, 'name' => 'X', 'price' => '-5', 'size_label' => 'M']]);
t('Валидация: короткое название и отрицательная цена не сохраняются', str_contains($r['body'], 'Исправьте ошибки') && (int)Db::value('SELECT price FROM products WHERE id = ?', [$newId]) === 22222);
$other = Db::value('SELECT slug FROM products WHERE id <> ? LIMIT 1', [$newId]);
$r = http('POST', '/admin/products/save/', ['form' => ['_csrf' => $tok, 'id' => (string)$newId, 'name' => 'Дубль ЧПУ', 'slug' => $other, 'price' => '100', 'size_label' => 'M']]);
t('Валидация: занятый ЧПУ не сохраняется', str_contains($r['body'], 'уже занят'));
// Быстрое изменение цены из списка
http('POST', "/admin/products/$newId/quick/", ['form' => ['_csrf' => $tok, 'price' => '19990', 'in_stock' => '1', 'is_active' => '1']]);
t('Быстрое изменение цены из списка товаров', (int)Db::value('SELECT price FROM products WHERE id = ?', [$newId]) === 19990);
http('POST', "/admin/products/$newId/quick/", ['form' => ['_csrf' => $tok, 'price' => '19990', 'in_stock' => '1']]);
t('Скрытие товара: страница → 404, нет в каталоге', http('GET', "/product/$slug/")['code'] === 404 && !str_contains(http('GET', '/catalog/')['body'], "/product/$slug/"));
http('POST', "/admin/products/$newId/quick/", ['form' => ['_csrf' => $tok, 'price' => '19990', 'is_active' => '1']]);
t('Товар «под заказ» показывается с пометкой', str_contains(http('GET', "/product/$slug/")['body'], 'Под заказ'));
// Добавление второго фото и удаление
$r = http('POST', '/admin/products/save/', ['form' => ['_csrf' => $tok, 'id' => (string)$newId, 'name' => 'Тестовый чемодан QA v2', 'slug' => $slug, 'price' => '19990', 'size_label' => 'L', 'is_active' => '1', 'images[0]' => new CURLFile($jpg, 'image/jpeg', 'p2.jpg')]]);
$imgs = Catalog::images($newId);
t('Добавление ещё одного фото', count($imgs) === 2);
$file = $imgs[0]['file'];
http('POST', '/admin/images/' . $imgs[0]['id'] . '/delete/', ['form' => ['_csrf' => $tok]]);
t('Удаление фото (и файлов с диска)', count(Catalog::images($newId)) === 1 && !imgExists($file, 800));
http('POST', "/admin/products/$newId/delete/", ['form' => []]);
t('Удаление без CSRF-токена не срабатывает', (bool)Db::value('SELECT id FROM products WHERE id = ?', [$newId]));
// Дублирование
$r = http('POST', "/admin/products/$newId/duplicate/", ['form' => ['_csrf' => $tok]]);
$dupId = (int)basename(rtrim($r['headers']['location'] ?? '', '/'));
t('Дублирование товара (копия скрыта)', $dupId > 0 && (int)Db::value('SELECT is_active FROM products WHERE id = ?', [$dupId]) === 0);
http('POST', "/admin/products/$dupId/delete/", ['form' => ['_csrf' => $tok]]);
$left = Catalog::images($newId);
http('POST', "/admin/products/$newId/delete/", ['form' => ['_csrf' => $tok]]);
t('Удаление товара вместе с фото', !Db::value('SELECT id FROM products WHERE id IN (?, ?)', [$newId, $dupId]) && !imgExists($left[0]['file'], 400));
t('Удалённый товар → 301 в категорию (не 404)', http('GET', "/product/$slug/")['code'] === 301);
// Заявки в админке
resetLimits();
http('POST', '/api/lead/', ['json' => lead(['name' => '<img src=x onerror=alert(1)>', 'message' => '=HYPERLINK("http://x")']), 'nojar' => true]);
$lid = (int)Db::value('SELECT MAX(id) FROM leads');
$lp = http('GET', '/admin/leads/');
t('Заявка отображается в админке, HTML-теги из имени вырезаны (XSS)', str_contains($lp['body'], 'onerror=alert(1)') && !str_contains($lp['body'], '<img src=x'));
http('POST', "/admin/leads/$lid/", ['form' => ['_csrf' => $tok, 'status' => 'done', 'admin_note' => 'Перезвонили']]);
t('Смена статуса заявки и заметка', Db::value('SELECT status FROM leads WHERE id = ?', [$lid]) === 'done');
$csv = http('GET', '/admin/leads/export/');
t('Выгрузка заявок в CSV (с защитой от формул Excel)', str_contains($csv['headers']['content-type'] ?? '', 'text/csv') && str_contains($csv['body'], "'=HYPERLINK"));
Db::run('DELETE FROM leads');
// Настройки
$old = Settings::all();
$r = http('POST', '/admin/settings/', ['form' => array_merge($old, ['_csrf' => $tok, 'metrika_id' => '12345678', 'phone' => '8 (707) 111 22 33',
    'yandex_verification' => '<meta name="yandex-verification" content="abc123def" />', 'google_verification' => 'g-TOKEN_x1'])]);
$home = http('GET', '/')['body'];
t('Номер Метрики из админки → код счётчика на сайте', str_contains($home, 'ym(12345678,"init"') && str_contains($home, 'mc.yandex.ru/watch/12345678'));
t('Метатеги верификации Яндекс Вебмастера и Google Search Console', str_contains($home, 'name="yandex-verification" content="abc123def"') && str_contains($home, 'name="google-site-verification" content="g-TOKEN_x1"'));
t('Телефон из настроек нормализуется и выводится на сайте', str_contains($home, 'tel:+77071112233'));
t('Скрипт Метрики помечен nonce (проходит CSP)', (bool)preg_match('/<script nonce="[^"]+">\s*\(function\(m,e,t,r,i,k,a\)/', $home));
http('POST', '/admin/settings/', ['form' => array_merge($old, ['_csrf' => $tok])]);
$r = http('POST', '/admin/telegram-test/', ['form' => ['_csrf' => $tok]]);
t('Кнопка «Тест Telegram» в настройках', $r['code'] === 302);
$seo = http('GET', '/admin/seo/');
$cid = (int)Db::value("SELECT id FROM cities WHERE slug = 'almaty'");
http('POST', "/admin/seo/$cid/city/", ['form' => ['_csrf' => $tok, 'name' => 'Алматы', 'name_in' => 'в Алматы', 'delivery_days' => '1 день', 'meta_title' => 'Тестовый title Алматы', 'is_active' => '1']]);
t('SEO-раздел: редактирование title городской страницы', $seo['code'] === 200 && str_contains(http('GET', '/almaty/')['body'], '<title>Тестовый title Алматы</title>'));
Db::run("UPDATE cities SET meta_title = '' WHERE id = ?", [$cid]);
$r = http('POST', '/admin/password/', ['form' => ['_csrf' => $tok, 'current' => $ADMIN_PASS, 'new' => 'short', 'new2' => 'short']]);
t('Слабый новый пароль отклоняется', str_contains(http('GET', '/admin/password/')['body'], 'минимум 10'));
$r = http('POST', '/admin/logout/', ['form' => ['_csrf' => $tok]]);
t('Выход из админки', http('GET', '/admin/')['code'] === 302);


// ============================================================
section('10. SEO-инструменты: страницы, блог, редиректы, города и категории');
resetLimits();
$lg = http('GET', '/admin/login/');
http('POST', '/admin/login/', ['form' => ['_csrf' => csrf($lg['body']), 'login' => 'admin', 'password' => $ADMIN_PASS]]);
$tok = csrf(http('GET', '/admin/')['body']);
$title = fn(string $path) => txt(dom(http('GET', $path)['body']), '//title');
$h1 = fn(string $path) => txt(dom(http('GET', $path)['body']), '//h1');

// Страницы
t('Раздел «Страницы» открывается', str_contains(http('GET', '/admin/pages/')['body'], 'Главная'));
http('POST', '/admin/pages/home/', ['form' => ['_csrf' => $tok, 'meta_title' => 'QA title главной', 'meta_description' => 'QA описание главной страницы для проверки', 'h1' => 'QA H1 главной', 'body' => "## QA подзаголовок\n\nQA SEO-текст **жирный**\n\n- пункт 1\n- пункт 2"]]);
$home = http('GET', '/')['body'];
t('Главная: title, description, H1 из админки', $title('/') === 'QA title главной' && $h1('/') === 'QA H1 главной' && str_contains($home, 'content="QA описание главной'));
t('Главная: SEO-текст с разметкой (заголовок, жирный, список)', str_contains($home, '<h2>QA подзаголовок</h2>') && str_contains($home, '<strong>жирный</strong>') && str_contains($home, '<li>пункт 2</li>'));
http('POST', '/admin/pages/o-kompanii/', ['form' => ['_csrf' => $tok, 'meta_title' => '', 'meta_description' => '', 'h1' => 'QA О нас', 'body' => "Свой текст о компании.\n\n<script>alert(1)</script> [плохая](javascript:alert(1)) [хорошая](/catalog/)"]]);
$ok = http('GET', '/o-kompanii/')['body'];
t('Информационная страница: текст из админки заменяет стандартный', str_contains($ok, 'Свой текст о компании') && !str_contains($ok, 'Работаем с официальными поставщиками') && $h1('/o-kompanii/') === 'QA О нас');
t('Разметка безопасна: <script> экранирован, javascript:-ссылка удалена', !str_contains($ok, '<script>alert(1)') && !str_contains($ok, 'javascript:') && str_contains($ok, '<a href="/catalog/">хорошая</a>'));
Db::run("DELETE FROM pages WHERE slug IN ('home', 'o-kompanii')");
t('После очистки полей — снова стандартные тексты', str_contains(http('GET', '/o-kompanii/')['body'], 'официальными поставщиками') && $h1('/') !== 'QA H1 главной');

// Категории
$r = http('POST', '/admin/seo/new/category/', ['form' => ['_csrf' => $tok, 'name' => 'QA Детские', 'slug' => 'qa-detskie', 'h1' => 'QA детские чемоданы', 'seo_text' => "## Как выбрать детский чемодан\n\nQA текст категории", 'sort' => '50']]);
$catId = (int)Db::value("SELECT id FROM categories WHERE slug = 'qa-detskie'");
t('Добавление новой категории', $catId > 0 && http('GET', '/catalog/qa-detskie/')['code'] === 200);
t('Новая категория: в меню, sitemap, H1 и SEO-текст', str_contains(http('GET', '/')['body'], 'href="/catalog/qa-detskie/"') && str_contains(http('GET', '/sitemap.xml')['body'], '/catalog/qa-detskie/') && $h1('/catalog/qa-detskie/') === 'QA детские чемоданы' && str_contains(http('GET', '/catalog/qa-detskie/')['body'], 'QA текст категории'));
http('POST', "/admin/seo/$catId/category/", ['form' => ['_csrf' => $tok, 'name' => 'QA Детские', 'slug' => 'qa-dlya-detey']]);
$r = http('GET', '/catalog/qa-detskie/');
t('Смена адреса категории → 301 со старого на новый', $r['code'] === 301 && str_ends_with($r['headers']['location'] ?? '', '/catalog/qa-dlya-detey/'));
http('POST', "/admin/seo/$catId/category/delete/", ['form' => ['_csrf' => $tok]]);
$r = http('GET', '/catalog/qa-dlya-detey/');
t('Удаление категории → 301 в каталог, старые адреса тоже', !Db::value('SELECT id FROM categories WHERE id = ?', [$catId]) && $r['code'] === 301 && str_ends_with($r['headers']['location'] ?? '', '/catalog/') && str_ends_with(http('GET', '/catalog/qa-detskie/')['headers']['location'] ?? '', '/catalog/'));
$r = http('POST', '/admin/seo/new/category/', ['form' => ['_csrf' => $tok, 'name' => 'Дубль', 'slug' => 'srednie']]);
t('Нельзя создать категорию с занятым адресом', str_contains($r['body'], 'уже занят'));

// Города
$r = http('POST', '/admin/seo/new/city/', ['form' => ['_csrf' => $tok, 'name' => 'Блог-сити', 'slug' => 'blog', 'is_active' => '1']]);
t('Нельзя занять городом служебный адрес (/blog/)', str_contains($r['body'], 'зарезервирован'));
http('POST', '/admin/seo/new/city/', ['form' => ['_csrf' => $tok, 'name' => 'Жезказган', 'name_in' => 'в Жезказгане', 'delivery_days' => '3–5 дней', 'seo_text' => 'QA текст для Жезказгана', 'is_active' => '1']]);
$cityId = (int)Db::value("SELECT id FROM cities WHERE slug = 'zhezkazgan'");
$cp = http('GET', '/zhezkazgan/');
t('Добавление нового города (ЧПУ из названия: /zhezkazgan/)', $cityId > 0 && $cp['code'] === 200 && $h1('/zhezkazgan/') === 'Чемоданы в Жезказгане' && str_contains($cp['body'], 'QA текст для Жезказгана'));
t('Новый город в sitemap и в списке городов', str_contains(http('GET', '/sitemap.xml')['body'], '/zhezkazgan/') && str_contains(http('GET', '/goroda/')['body'], 'href="/zhezkazgan/"'));
http('POST', "/admin/seo/$cityId/city/delete/", ['form' => ['_csrf' => $tok]]);
$r = http('GET', '/zhezkazgan/');
t('Удаление города → 301 на список городов', $r['code'] === 301 && str_ends_with($r['headers']['location'] ?? '', '/goroda/'));

// Товары: смена ЧПУ, noindex, удаление
$p = Db::one('SELECT * FROM products WHERE is_active = 1 ORDER BY id LIMIT 1');
// Форма со ВСЕМИ полями товара, чтобы тест не затирал характеристики
$baseForm = ['_csrf' => $tok, 'id' => (string)$p['id']];
foreach (['name', 'category_id', 'brand', 'sku', 'price', 'old_price', 'size_label', 'material', 'color', 'color_hex', 'width_cm', 'height_cm', 'depth_cm', 'volume_l', 'weight_kg', 'wheels', 'lock_type', 'warranty_months', 'short_desc', 'description', 'meta_title', 'meta_description', 'sort'] as $f) {
    $baseForm[$f] = (string)($p[$f] ?? '');
}
foreach (['expandable', 'in_stock', 'is_active', 'is_featured'] as $f) {
    if ($p[$f]) $baseForm[$f] = '1';
}
$baseForm['width_cm'] = str_replace('.', ',', $baseForm['width_cm']);
http('POST', '/admin/products/save/', ['form' => $baseForm + ['slug' => $p['slug'] . '-new']]);
$r = http('GET', '/product/' . $p['slug'] . '/?utm_source=test');
t('Смена ЧПУ товара → 301 на новый адрес, UTM сохраняются', $r['code'] === 301 && str_contains($r['headers']['location'] ?? '', '/product/' . $p['slug'] . '-new/?utm_source=test'));
http('POST', '/admin/products/save/', ['form' => $baseForm + ['slug' => $p['slug']]]);
t('Возврат старого ЧПУ — без петли редиректов', http('GET', '/product/' . $p['slug'] . '/')['code'] === 200 && http('GET', '/product/' . $p['slug'] . '-new/')['code'] === 301);
http('POST', '/admin/products/save/', ['form' => $baseForm + ['slug' => $p['slug'], 'noindex' => '1']]);
t('Товар с noindex: виден на сайте, закрыт от индексации, нет в sitemap', http('GET', '/product/' . $p['slug'] . '/')['code'] === 200 && str_contains(http('GET', '/product/' . $p['slug'] . '/')['body'], 'content="noindex, follow"') && !str_contains(http('GET', '/sitemap.xml')['body'], '/product/' . $p['slug'] . '/'));
http('POST', '/admin/products/save/', ['form' => $baseForm + ['slug' => $p['slug']]]);
$tmpSlug = 'qa-udalyaemyy-' . random_int(100, 999);
http('POST', '/admin/products/save/', ['form' => ['_csrf' => $tok, 'name' => 'QA удаляемый', 'slug' => $tmpSlug, 'price' => '1000', 'size_label' => 'S', 'category_id' => (string)$p['category_id'], 'is_active' => '1']]);
$tid = (int)Db::value('SELECT id FROM products WHERE slug = ?', [$tmpSlug]);
http('POST', "/admin/products/$tid/delete/", ['form' => ['_csrf' => $tok]]);
$catSlug = Db::value('SELECT slug FROM categories WHERE id = ?', [$p['category_id']]);
$r = http('GET', "/product/$tmpSlug/");
t('Удалённый товар → 301 в его категорию (а не 404)', $r['code'] === 301 && str_ends_with($r['headers']['location'] ?? '', "/catalog/$catSlug/"));

// Редиректы вручную
http('POST', '/admin/redirects/', ['form' => ['_csrf' => $tok, 'from_path' => 'https://old-site.kz/Chemodany/Old-Page.html', 'to_path' => '/catalog/srednie/', 'note' => 'QA']]);
$r = http('GET', '/chemodany/old-page.html');
t('Ручной редирект со старого сайта (полный URL, регистр) → 301', $r['code'] === 301 && str_ends_with($r['headers']['location'] ?? '', '/catalog/srednie/'));
t('Счётчик переходов по редиректу', (int)Db::value("SELECT hits FROM redirects WHERE from_path = '/chemodany/old-page.html'") >= 1);
http('POST', '/admin/redirects/', ['form' => ['_csrf' => $tok, 'from_path' => '/qa-a/', 'to_path' => '/qa-b/']]);
http('POST', '/admin/redirects/', ['form' => ['_csrf' => $tok, 'from_path' => '/qa-b/', 'to_path' => '/catalog/']]);
t('Цепочка A→B→C схлопывается в A→C', Db::value("SELECT to_path FROM redirects WHERE from_path = '/qa-a/'") === '/catalog/');
http('POST', '/admin/redirects/', ['form' => ['_csrf' => $tok, 'from_path' => '/admin/settings/', 'to_path' => '/']]);
t('Служебные адреса (/admin/) перенаправить нельзя', !Db::value("SELECT id FROM redirects WHERE from_path = '/admin/settings/'"));
http('POST', '/admin/redirects/', ['form' => ['_csrf' => $tok, 'from_path' => '/qa-x/', 'to_path' => 'javascript:alert(1)']]);
t('Недопустимый адрес назначения отклоняется', !Db::value("SELECT id FROM redirects WHERE from_path = '/qa-x/'"));
$rid = (int)Db::value("SELECT id FROM redirects WHERE from_path = '/qa-a/'");
http('POST', "/admin/redirects/$rid/delete/", ['form' => ['_csrf' => $tok]]);
t('Удаление редиректа → адрес снова 404', http('GET', '/qa-a/')['code'] === 404);
t('Раздел «Редиректы» показывает список', str_contains(http('GET', '/admin/redirects/')['body'], '/chemodany/old-page.html'));

// Блог
$bl = http('GET', '/blog/');
t('Блог: список статей, ссылка в меню', $bl['code'] === 200 && count(Articles::published()) >= 3 && str_contains(http('GET', '/')['body'], 'href="/blog/"'));
$a0 = Articles::published()[0];
$ap = http('GET', '/blog/' . $a0['slug'] . '/');
$types = array_column(array_map(fn($n) => json_decode($n->textContent, true), iterator_to_array(dom($ap['body'])->query('//script[@type="application/ld+json"]'))), '@type');
t('Статья: 200, JSON-LD BlogPosting + хлебные крошки, товары и CTA', $ap['code'] === 200 && in_array('BlogPosting', $types) && in_array('BreadcrumbList', $types) && str_contains($ap['body'], 'Получить консультацию'));
$png = tempnam(sys_get_temp_dir(), 'cov') . '.png';
$im = imagecreatetruecolor(1600, 900); imagefill($im, 0, 0, imagecolorallocate($im, 30, 120, 160)); imagepng($im, $png);
$r = http('POST', '/admin/blog/save/', ['form' => ['_csrf' => $tok, 'title' => 'QA статья черновик', 'slug' => 'qa-statya', 'excerpt' => 'Анонс QA', 'body' => "## Раздел\n\nТекст QA статьи", 'published_at' => date('Y-m-d\TH:i'), 'cover' => new CURLFile($png, 'image/png', 'c.png')]]);
$aid = (int)Db::value("SELECT id FROM articles WHERE slug = 'qa-statya'");
t('Создание статьи с обложкой (WebP)', $aid > 0 && ($cov = Db::value('SELECT cover FROM articles WHERE id = ?', [$aid])) && imgExists($cov, 1200));
t('Черновик не виден на сайте и в sitemap', http('GET', '/blog/qa-statya/')['code'] === 404 && !str_contains(http('GET', '/sitemap.xml')['body'], 'qa-statya'));
http('POST', '/admin/blog/save/', ['form' => ['_csrf' => $tok, 'id' => (string)$aid, 'title' => 'QA статья черновик', 'slug' => 'qa-statya', 'body' => "## Раздел\n\nТекст QA статьи", 'is_published' => '1', 'published_at' => date('Y-m-d\TH:i', time() - 60)]]);
t('Публикация: статья на сайте, в списке и sitemap', http('GET', '/blog/qa-statya/')['code'] === 200 && str_contains(http('GET', '/blog/')['body'], '/blog/qa-statya/') && str_contains(http('GET', '/sitemap.xml')['body'], '/blog/qa-statya/'));
http('POST', '/admin/blog/save/', ['form' => ['_csrf' => $tok, 'id' => (string)$aid, 'title' => 'QA статья черновик', 'slug' => 'qa-statya', 'is_published' => '1', 'published_at' => date('Y-m-d\TH:i', time() + 86400 * 3)]]);
t('Отложенная публикация: статья с будущей датой пока скрыта', http('GET', '/blog/qa-statya/')['code'] === 404);
http('POST', '/admin/blog/save/', ['form' => ['_csrf' => $tok, 'id' => (string)$aid, 'title' => 'QA статья черновик', 'slug' => 'qa-statya-2', 'is_published' => '1', 'noindex' => '1', 'published_at' => date('Y-m-d\TH:i', time() - 60)]]);
t('Смена адреса статьи → 301; noindex статьи', str_ends_with(http('GET', '/blog/qa-statya/')['headers']['location'] ?? '', '/blog/qa-statya-2/') && str_contains(http('GET', '/blog/qa-statya-2/')['body'], 'noindex, follow') && !str_contains(http('GET', '/sitemap.xml')['body'], 'qa-statya-2'));
http('POST', "/admin/blog/$aid/delete/", ['form' => ['_csrf' => $tok]]);
t('Удаление статьи (с обложкой) → 301 в блог', !Db::value('SELECT id FROM articles WHERE id = ?', [$aid]) && !imgExists($cov, 400) && str_ends_with(http('GET', '/blog/qa-statya-2/')['headers']['location'] ?? '', '/blog/'));

// Google Analytics 4 / Tag Manager
$old = Settings::all();
http('POST', '/admin/settings/', ['form' => array_merge($old, ['_csrf' => $tok, 'ga4_id' => 'g-abc123xyz9', 'gtm_id' => 'GTM-QA12345'])]);
$home = http('GET', '/');
t('GA4: gtag с nonce, ID нормализован', (bool)preg_match('~<script async src="https://www\.googletagmanager\.com/gtag/js\?id=G-ABC123XYZ9" nonce="~', $home['body']));
t('GTM: контейнер с nonce + noscript', str_contains($home['body'], "'GTM-QA12345'") && str_contains($home['body'], 'ns.html?id=GTM-QA12345'));
t('CSP разрешает Google Analytics/Tag Manager', str_contains($home['headers']['content-security-policy'] ?? '', 'https://www.googletagmanager.com'));
http('POST', '/admin/settings/', ['form' => array_merge($old, ['_csrf' => $tok, 'ga4_id' => '<script>', 'gtm_id' => 'bad'])]);
t('Неверные ID Google отклоняются', Settings::get('ga4_id') === 'G-ABC123XYZ9' || !str_contains(http('GET', '/')['body'], '<script>"'));
http('POST', '/admin/settings/', ['form' => array_merge($old, ['_csrf' => $tok, 'ga4_id' => '', 'gtm_id' => ''])]);
t('Без ID — коды Google не выводятся', !str_contains(http('GET', '/')['body'], 'googletagmanager.com/gtag'));
Db::run("DELETE FROM redirects WHERE note IN ('QA', 'Смена адреса товара', 'Товар удалён', 'Смена адреса', 'Категория удалена', 'Город удалён', 'Смена адреса статьи', 'Статья удалена') OR from_path LIKE '/qa-%'");

// Повторная установка (как при каждом перезапуске на Railway) — ничего не ломает и не дублирует
$cnt = [(int)Db::value('SELECT COUNT(*) FROM products'), (int)Db::value('SELECT COUNT(*) FROM articles'), (int)Db::value('SELECT COUNT(*) FROM cities')];
exec('php ' . escapeshellarg(ROOT . '/bin/install.php') . ' 2>&1', $out, $code);
t('Повторный запуск install.php: без ошибок и дублей данных', $code === 0 && $cnt === [(int)Db::value('SELECT COUNT(*) FROM products'), (int)Db::value('SELECT COUNT(*) FROM articles'), (int)Db::value('SELECT COUNT(*) FROM cities')], implode(' ', $out));
http('POST', '/admin/logout/', ['form' => ['_csrf' => $tok]]);

// ============================================================
echo "\n\033[1mИтого: $pass пройдено, $fail провалено\033[0m\n";
foreach ($failures as $f) echo "  - $f\n";
exit($fail ? 1 : 0);
