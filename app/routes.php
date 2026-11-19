<?php
declare(strict_types=1);

/** Информационные страницы: slug => [title, description, h1] */
const STATIC_PAGES = [
    'dostavka-i-oplata' => ['Доставка и оплата чемоданов по Казахстану', 'Условия доставки чемоданов по Казахстану: курьером по Алматы и Астане, в регионы — транспортной компанией и Казпочтой. Оплата Kaspi, картой, наличными.', 'Доставка и оплата'],
    'garantiya-i-vozvrat' => ['Гарантия и возврат', 'Гарантия на чемоданы до 5 лет, обмен и возврат в течение 14 дней по Закону РК «О защите прав потребителей».', 'Гарантия и возврат'],
    'kak-vybrat-chemodan' => ['Как выбрать чемодан: размеры, материалы, колёса', 'Гид по выбору чемодана: какие размеры разрешены для ручной клади Air Astana и SCAT, поликарбонат или полипропилен, 2 или 4 колеса.', 'Как выбрать чемодан'],
    'o-kompanii' => ['О компании', 'Магазин чемоданов и дорожных сумок с доставкой по всему Казахстану. Официальная гарантия, честные цены.', 'О компании'],
    'kontakty' => ['Контакты', 'Телефон, WhatsApp, Telegram и адрес магазина чемоданов. Ежедневно с 10:00 до 20:00.', 'Контакты'],
    'politika-konfidencialnosti' => ['Политика конфиденциальности', 'Политика в отношении сбора, обработки и защиты персональных данных в соответствии с Законом РК «О персональных данных и их защите».', 'Политика конфиденциальности'],
    'soglasie' => ['Согласие на сбор и обработку персональных данных', 'Текст согласия на сбор и обработку персональных данных при отправке заявки на сайте.', 'Согласие на сбор и обработку персональных данных'],
];

function dispatch(string $method, string $path): void
{
    if (str_starts_with($path, '/admin')) {
        require APP . '/admin.php';
        admin_dispatch($method, $path);
        return;
    }
    if (str_starts_with($path, '/api/')) {
        api_dispatch($method, $path);
        return;
    }
    if ($method !== 'GET' && $method !== 'HEAD') {
        http_response_code(405);
        header('Allow: GET, HEAD');
        exit('Method Not Allowed');
    }

    $seg = array_values(array_filter(explode('/', $path), 'strlen'));
    match (true) {
        $path === '/' => page_home(),
        $path === '/robots.txt' => page_robots(),
        $path === '/sitemap.xml' => page_sitemap(),
        $seg === ['catalog'] => page_catalog(null),
        count($seg) === 2 && $seg[0] === 'catalog' => page_catalog($seg[1]),
        count($seg) === 2 && $seg[0] === 'product' => page_product($seg[1]),
        $seg === ['goroda'] => page_cities(),
        count($seg) === 1 && isset(STATIC_PAGES[$seg[0]]) => page_static($seg[0]),
        count($seg) === 1 && preg_match('~^[a-z\-]+$~', $seg[0]) === 1 => page_city($seg[0]),
        default => not_found(),
    };
}

function not_found(): never
{
    $seo = new Seo('Страница не найдена', 'Запрошенная страница не существует.', '/404/');
    $seo->robots = 'noindex, follow';
    View::page('404', ['seo' => $seo, 'featured' => safe_featured()], 404);
}

function safe_featured(): array
{
    try {
        return Catalog::featured(4);
    } catch (Throwable) {
        return [];
    }
}

function page_home(): never
{
    $s = Settings::all();
    $seo = new Seo(
        'Чемоданы купить в Казахстане — цены от ' . price_fmt(Catalog::filterOptions()['price_min']) . ' | ' . $s['site_name'],
        'Интернет-магазин чемоданов: ручная кладь, средние и большие чемоданы, наборы. Доставка по Алматы, Астане и всему Казахстану. Гарантия до 5 лет, оплата Kaspi.',
        '/'
    );
    $faq = home_faq();
    $seo->jsonLd[] = Seo::organization();
    $seo->jsonLd[] = ['@context' => 'https://schema.org', '@type' => 'WebSite', 'name' => $s['site_name'], 'url' => base_url('/'), 'inLanguage' => 'ru-KZ'];
    $seo->jsonLd[] = Seo::faq($faq);
    View::page('home', [
        'seo' => $seo,
        'featured' => Catalog::featured(8),
        'categories' => Catalog::categories(),
        'cities' => Catalog::cities(),
        'faq' => $faq,
    ]);
}

function home_faq(): array
{
    return [
        ['Какой размер чемодана подходит для ручной клади?', 'Большинство авиакомпаний (Air Astana, FlyArystan, SCAT) принимают ручную кладь до 55×40×20 см. Выбирайте чемоданы размера S — все они укладываются в эти габариты.'],
        ['Есть ли доставка по Казахстану?', 'Да. По Алматы и Астане — курьером за 1 день, в другие города — транспортной компанией за 2–5 дней. Стоимость доставки зависит от города.'],
        ['Какие способы оплаты?', 'Kaspi (перевод, QR, Kaspi Red/рассрочка), банковская карта, наличные курьеру, безналичный расчёт для юрлиц.'],
        ['Что делать, если чемодан повредили при перелёте?', 'На корпус и колёса действует гарантия производителя. Сохраните акт о повреждении багажа от авиакомпании и свяжитесь с нами — поможем с ремонтом или заменой.'],
        ['Можно ли вернуть чемодан?', 'Да, в течение 14 дней, если товар не был в использовании и сохранил товарный вид.'],
    ];
}

function page_catalog(?string $catSlug): never
{
    $cat = null;
    if ($catSlug !== null) {
        $cat = Catalog::category($catSlug);
        if (!$cat) {
            not_found();
        }
    }
    $f = Catalog::parseFilters($_GET);
    $products = Catalog::products($f, $cat ? (int)$cat['id'] : null);
    $path = $cat ? '/catalog/' . $cat['slug'] . '/' : '/catalog/';
    $s = Settings::all();
    if ($cat) {
        $seo = new Seo($cat['meta_title'] ?: $cat['name'] . ' — купить в Казахстане | ' . $s['site_name'], $cat['meta_description'] ?: $cat['intro'] ?? '', $path);
        $seo->crumbs([['Каталог', '/catalog/'], [$cat['name'], $path]]);
    } else {
        $seo = new Seo('Каталог чемоданов — цены, размеры, характеристики | ' . $s['site_name'], 'Каталог чемоданов на колёсах: размеры S, M, L, XL и наборы. Поликарбонат, полипропилен, ABS. Фильтр по цене, размеру, материалу и цвету. Доставка по Казахстану.', $path);
        $seo->crumbs([['Каталог', '/catalog/']]);
    }
    // Страницы с фильтрами не индексируем — избегаем дублей, canonical указывает на основную страницу
    if (Catalog::hasFilters($f)) {
        $seo->robots = 'noindex, follow';
    }
    $seo->jsonLd[] = Seo::itemList($products);
    $vars = [
        'seo' => $seo, 'cat' => $cat, 'f' => $f, 'products' => $products, 'basePath' => $path,
        'options' => Catalog::filterOptions(), 'categories' => Catalog::categories(),
    ];
    // Частичный ответ для AJAX-фильтрации (без перезагрузки страницы)
    if (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
        Security::sendHeaders();
        header('Content-Type: text/html; charset=utf-8');
        header('Vary: X-Requested-With');
        echo View::partial('partials/catalog_results', $vars);
        exit;
    }
    View::page('catalog', $vars);
}

function page_product(string $slug): never
{
    $p = Catalog::product($slug);
    if (!$p) {
        not_found();
    }
    $images = Catalog::images((int)$p['id']);
    $s = Settings::all();
    $path = '/product/' . $p['slug'] . '/';
    $dims = $p['width_cm'] ? ', ' . num_fmt($p['height_cm']) . '×' . num_fmt($p['width_cm']) . '×' . num_fmt($p['depth_cm']) . ' см' : '';
    $seo = new Seo(
        $p['meta_title'] ?: $p['name'] . ' — купить за ' . price_fmt($p['price']) . ' | ' . $s['site_name'],
        $p['meta_description'] ?: $p['name'] . $dims . ($p['volume_l'] ? ', ' . num_fmt($p['volume_l']) . ' л' : '') . '. ' . $p['short_desc'] . ' Доставка по Казахстану, гарантия.',
        $path
    );
    $seo->ogType = 'product';
    if ($images) {
        $seo->ogImage = base_url(Images::url($images[0]['file'], 1200));
    }
    $crumbs = [['Каталог', '/catalog/']];
    if ($p['category_slug']) {
        $crumbs[] = [$p['category_name'], '/catalog/' . $p['category_slug'] . '/'];
    }
    $crumbs[] = [$p['name'], $path];
    $seo->crumbs($crumbs);
    $seo->jsonLd[] = Seo::product($p, $images);
    View::page('product', ['seo' => $seo, 'p' => $p, 'images' => $images, 'related' => Catalog::related($p)]);
}

function page_cities(): never
{
    $seo = new Seo('Доставка чемоданов по городам Казахстана | ' . Settings::get('site_name'), 'Доставка чемоданов в Алматы, Астану, Шымкент, Караганду и другие города Казахстана. Сроки и стоимость по каждому городу.', '/goroda/');
    $seo->crumbs([['Города доставки', '/goroda/']]);
    View::page('cities', ['seo' => $seo, 'cities' => Catalog::cities()]);
}

function page_city(string $slug): never
{
    $city = Catalog::city($slug);
    if (!$city) {
        not_found();
    }
    $s = Settings::all();
    $path = '/' . $city['slug'] . '/';
    $seo = new Seo(
        $city['meta_title'] ?: 'Чемоданы ' . $city['name_in'] . ' — купить с доставкой, цены | ' . $s['site_name'],
        $city['meta_description'] ?: 'Купить чемодан ' . $city['name_in'] . ': ручная кладь, средние и большие, наборы. Доставка ' . $city['delivery_days'] . ', оплата Kaspi. Цены от ' . price_fmt(Catalog::filterOptions()['price_min']) . '.',
        $path
    );
    $seo->crumbs([['Города доставки', '/goroda/'], [$city['name'], $path]]);
    $org = Seo::organization();
    $org['areaServed'] = ['@type' => 'City', 'name' => $city['name']];
    $seo->jsonLd[] = $org;
    $products = Catalog::featured(8);
    $seo->jsonLd[] = Seo::itemList($products);
    View::page('city', ['seo' => $seo, 'city' => $city, 'products' => $products, 'categories' => Catalog::categories(), 'cities' => Catalog::cities()]);
}

function page_static(string $slug): never
{
    [$title, $desc, $h1] = STATIC_PAGES[$slug];
    $seo = new Seo($title . ' | ' . Settings::get('site_name'), $desc, '/' . $slug . '/');
    $seo->crumbs([[$h1, '/' . $slug . '/']]);
    if ($slug === 'kontakty') {
        $seo->jsonLd[] = Seo::organization();
    }
    View::page('pages/' . $slug, ['seo' => $seo, 'h1' => $h1, 'cities' => Catalog::cities()]);
}

function page_robots(): never
{
    header('Content-Type: text/plain; charset=utf-8');
    $prod = Env::get('APP_ENV', 'production') === 'production';
    $lines = ['User-agent: *'];
    if (!$prod) {
        // Тестовый стенд закрыт от индексации полностью
        $lines[] = 'Disallow: /';
    } else {
        $lines = array_merge($lines, [
            'Disallow: /admin/',
            'Disallow: /api/',
            // Страницы фильтров не закрываем здесь: на них стоит meta noindex + canonical,
            // а закрытие в robots.txt помешало бы роботу увидеть этот noindex.
            'Allow: /assets/',
            'Allow: /uploads/',
            '',
            'User-agent: Yandex',
            'Disallow: /admin/',
            'Disallow: /api/',
            'Clean-param: utm_source&utm_medium&utm_campaign&utm_content&utm_term&yclid&gclid&fbclid',
        ]);
    }
    $lines[] = '';
    $lines[] = 'Sitemap: ' . base_url('/sitemap.xml');
    echo implode("\n", $lines) . "\n";
    exit;
}

function page_sitemap(): never
{
    header('Content-Type: application/xml; charset=utf-8');
    $urls = [];
    $add = function (string $path, string $lastmod, string $freq, string $prio, ?string $img = null) use (&$urls) {
        $urls[] = [base_url($path), $lastmod, $freq, $prio, $img];
    };
    $today = date('Y-m-d');
    $latest = (string)(Db::value('SELECT DATE(MAX(updated_at)) FROM products WHERE is_active = 1') ?: $today);
    $add('/', $latest, 'daily', '1.0');
    $add('/catalog/', $latest, 'daily', '0.9');
    foreach (Catalog::categories() as $c) {
        $add('/catalog/' . $c['slug'] . '/', $latest, 'weekly', '0.8');
    }
    foreach (Db::all('SELECT p.slug, DATE(p.updated_at) d, (SELECT file FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort, i.id LIMIT 1) img FROM products p WHERE p.is_active = 1 ORDER BY p.id') as $p) {
        $add('/product/' . $p['slug'] . '/', $p['d'], 'weekly', '0.7', $p['img'] ? base_url(Images::url($p['img'], 1200)) : null);
    }
    $add('/goroda/', $latest, 'monthly', '0.6');
    foreach (Catalog::cities() as $c) {
        $add('/' . $c['slug'] . '/', $latest, 'weekly', '0.7');
    }
    foreach (array_keys(STATIC_PAGES) as $slug) {
        $add('/' . $slug . '/', $today, 'monthly', in_array($slug, ['politika-konfidencialnosti', 'soglasie'], true) ? '0.2' : '0.5');
    }
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
    foreach ($urls as [$loc, $mod, $freq, $prio, $img]) {
        echo '  <url><loc>' . e($loc) . '</loc><lastmod>' . e($mod) . '</lastmod><changefreq>' . $freq . '</changefreq><priority>' . $prio . '</priority>';
        if ($img) {
            echo '<image:image><image:loc>' . e($img) . '</image:loc></image:image>';
        }
        echo "</url>\n";
    }
    echo '</urlset>' . "\n";
    exit;
}

// ======================= API =======================
function api_dispatch(string $method, string $path): void
{
    Security::sendHeaders();
    header('X-Robots-Tag: noindex');
    match (true) {
        $path === '/api/lead/' && $method === 'POST' => api_lead(),
        $path === '/api/products/' && $method === 'GET' => api_products(),
        $path === '/api/form-token/' && $method === 'GET' => json_response(['token' => Security::formToken()]),
        default => json_response(['ok' => false, 'error' => 'not_found'], 404),
    };
}

function api_lead(): never
{
    // Принимаем JSON (fetch) или обычную форму (если JS отключён)
    $isJson = str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
    $raw = $isJson ? file_get_contents('php://input', false, null, 0, 20000) : '';
    $in = $isJson ? (json_decode((string)$raw, true) ?: []) : $_POST;
    // Защита от отправки с чужих сайтов: Origin (если есть) должен совпадать с нашим хостом
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    if ($origin !== '' && parse_url($origin, PHP_URL_HOST) !== parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST)) {
        json_response(['ok' => false, 'errors' => ['form' => 'Недопустимый источник запроса']], 403);
    }
    $res = Leads::submit(is_array($in) ? $in : []);
    if (!$isJson) {
        // Без JS — возвращаем пользователя на страницу с сообщением
        $back = (string)($in['page'] ?? '/');
        if (!preg_match('~^/[^\s]*$~', $back) || str_starts_with($back, '//')) {
            $back = '/';
        }
        redirect($back . (str_contains($back, '?') ? '&' : '?') . ($res['ok'] ? 'sent=1' : 'sent=0'), 303);
    }
    if ($res['ok']) {
        json_response(['ok' => true, 'id' => $res['id'], 'message' => 'Спасибо! Менеджер свяжется с вами в течение 15 минут в рабочее время.']);
    }
    json_response(['ok' => false, 'errors' => $res['errors']], $res['code'] ?? 422);
}

/** Публичный JSON API каталога (для интеграций, мобильного приложения, маркетплейс-фидов). */
function api_products(): never
{
    if (!Security::rateLimit('api:' . client_ip(), 120, 60)) {
        json_response(['ok' => false, 'error' => 'rate_limited'], 429);
    }
    $f = Catalog::parseFilters($_GET);
    $catId = null;
    if (!empty($_GET['category']) && is_string($_GET['category'])) {
        $c = Catalog::category($_GET['category']);
        $catId = $c ? (int)$c['id'] : -1;
    }
    $items = array_map(fn($p) => [
        'id' => (int)$p['id'],
        'name' => $p['name'],
        'url' => base_url('/product/' . $p['slug'] . '/'),
        'category' => $p['category_slug'],
        'brand' => $p['brand'],
        'price' => (int)$p['price'],
        'old_price' => $p['old_price'] ? (int)$p['old_price'] : null,
        'currency' => 'KZT',
        'size' => $p['size_label'],
        'dimensions_cm' => $p['width_cm'] ? ['h' => (float)$p['height_cm'], 'w' => (float)$p['width_cm'], 'd' => (float)$p['depth_cm']] : null,
        'volume_l' => $p['volume_l'] !== null ? (float)$p['volume_l'] : null,
        'weight_kg' => $p['weight_kg'] !== null ? (float)$p['weight_kg'] : null,
        'material' => $p['material'],
        'color' => $p['color'],
        'wheels' => (int)$p['wheels'],
        'in_stock' => (bool)$p['in_stock'],
        'image' => $p['image'] ? base_url(Images::url($p['image'], 800)) : null,
    ], Catalog::products($f, $catId));
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: public, max-age=300');
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'count' => count($items), 'items' => $items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}
