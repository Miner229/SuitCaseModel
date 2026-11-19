<?php
declare(strict_types=1);

/** Админ-панель: товары, фото, заявки, настройки, SEO категорий и городов. */

function admin_dispatch(string $method, string $path): void
{
    Security::sendHeaders(true);
    Security::startSession();
    $seg = array_values(array_filter(explode('/', $path), 'strlen'));
    array_shift($seg); // 'admin'
    $route = implode('/', $seg);

    if ($route === 'login') {
        $method === 'POST' ? admin_login_post() : admin_view('login', ['title' => 'Вход'], false);
        return;
    }
    $admin = Security::admin();
    if (!$admin) {
        redirect('/admin/login/');
    }
    if ($method === 'POST') {
        Security::checkCsrf();
    }
    $id = isset($seg[1]) && ctype_digit($seg[1]) ? (int)$seg[1] : 0;

    match (true) {
        $route === '' => admin_dashboard(),
        $route === 'logout' && $method === 'POST' => (function () { Security::logout(); redirect('/admin/login/'); })(),
        $route === 'products' => admin_products(),
        $route === 'products/new' => admin_product_form(null),
        $route === 'products/save' && $method === 'POST' => admin_product_save(),
        $id && ($seg[0] ?? '') === 'products' && count($seg) === 2 => admin_product_form($id),
        $id && ($seg[0] ?? '') === 'products' && ($seg[2] ?? '') === 'delete' && $method === 'POST' => admin_product_delete($id),
        $id && ($seg[0] ?? '') === 'products' && ($seg[2] ?? '') === 'quick' && $method === 'POST' => admin_product_quick($id),
        $id && ($seg[0] ?? '') === 'products' && ($seg[2] ?? '') === 'duplicate' && $method === 'POST' => admin_product_duplicate($id),
        $id && ($seg[0] ?? '') === 'images' && ($seg[2] ?? '') === 'delete' && $method === 'POST' => admin_image_delete($id),
        $route === 'leads' => admin_leads(),
        $route === 'leads/export' => admin_leads_export(),
        $id && ($seg[0] ?? '') === 'leads' && $method === 'POST' => admin_lead_update($id),
        $route === 'settings' => $method === 'POST' ? admin_settings_save() : admin_view('settings', ['title' => 'Настройки', 's' => Settings::all()]),
        $route === 'telegram-test' && $method === 'POST' => admin_telegram_test(),
        $route === 'seo' => admin_view('seo', ['title' => 'SEO: категории и города', 'categories' => Catalog::categories(), 'cities' => Catalog::cities(false)]),
        ($seg[0] ?? '') === 'seo' && ($seg[1] ?? '') === 'new' && in_array($seg[2] ?? '', ['category', 'city'], true) => admin_seo_edit($seg[2], 0, $method),
        $id && ($seg[0] ?? '') === 'seo' && in_array($seg[2] ?? '', ['category', 'city'], true) && ($seg[3] ?? '') === 'delete' && $method === 'POST' => admin_seo_delete($seg[2], $id),
        $id && ($seg[0] ?? '') === 'seo' && in_array($seg[2] ?? '', ['category', 'city'], true) => admin_seo_edit($seg[2], $id, $method),
        $route === 'pages' => admin_view('pages', ['title' => 'Страницы сайта']),
        ($seg[0] ?? '') === 'pages' && isset(Pages::EDITABLE[$seg[1] ?? '']) => admin_page_edit($seg[1], $method),
        $route === 'blog' => admin_view('blog', ['title' => 'Блог', 'articles' => Db::all('SELECT * FROM articles ORDER BY COALESCE(published_at, created_at) DESC, id DESC')]),
        $route === 'blog/new' => admin_article_form(null),
        $route === 'blog/save' && $method === 'POST' => admin_article_save(),
        $id && ($seg[0] ?? '') === 'blog' && count($seg) === 2 => admin_article_form($id),
        $id && ($seg[0] ?? '') === 'blog' && ($seg[2] ?? '') === 'delete' && $method === 'POST' => admin_article_delete($id),
        $route === 'redirects' => $method === 'POST' ? admin_redirect_add() : admin_view('redirects', ['title' => 'Редиректы (301)', 'redirects' => Db::all('SELECT * FROM redirects ORDER BY id DESC')]),
        $id && ($seg[0] ?? '') === 'redirects' && ($seg[2] ?? '') === 'delete' && $method === 'POST' => admin_redirect_delete($id),
        $route === 'password' => $method === 'POST' ? admin_password_save($admin) : admin_view('password', ['title' => 'Смена пароля']),
        default => (function () { http_response_code(404); admin_view('message', ['title' => 'Не найдено', 'text' => 'Раздел не найден']); })(),
    };
}

function flash(?string $msg = null, string $type = 'ok'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = [$type, $msg];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function admin_view(string $tpl, array $vars = [], bool $layout = true): void
{
    header('Content-Type: text/html; charset=utf-8');
    echo View::render('admin/' . $tpl, $vars + ['flash' => flash()], $layout ? 'admin/layout' : null);
}

function admin_login_post(): never
{
    Security::checkCsrf();
    $ip = client_ip();
    $login = trim((string)($_POST['login'] ?? ''));
    // Защита от подбора пароля: 5 попыток на IP за 15 минут
    // + не более 20 попыток в час на один логин с любых IP (против распределённого перебора)
    if (!Security::rateLimit('login:' . $ip, 5, 900) || !Security::rateLimit('loginuser:' . mb_strtolower($login), 20, 3600)) {
        http_response_code(429);
        admin_view('login', ['title' => 'Вход', 'error' => 'Слишком много попыток. Подождите 15 минут.'], false);
        exit;
    }
    if (Security::login($login, (string)($_POST['password'] ?? ''))) {
        Security::resetRateLimit('login:' . $ip);
        Security::resetRateLimit('loginuser:' . mb_strtolower($login));
        redirect('/admin/');
    }
    usleep(400000);
    admin_view('login', ['title' => 'Вход', 'error' => 'Неверный логин или пароль'], false);
    exit;
}

function admin_dashboard(): void
{
    admin_view('dashboard', [
        'title' => 'Обзор',
        'stats' => [
            'products' => (int)Db::value('SELECT COUNT(*) FROM products'),
            'active' => (int)Db::value('SELECT COUNT(*) FROM products WHERE is_active = 1'),
            'new_leads' => (int)Db::value("SELECT COUNT(*) FROM leads WHERE status = 'new'"),
            'leads_7d' => (int)Db::value('SELECT COUNT(*) FROM leads WHERE created_at >= NOW() - INTERVAL 7 DAY'),
            'no_photo' => (int)Db::value('SELECT COUNT(*) FROM products p WHERE NOT EXISTS (SELECT 1 FROM product_images i WHERE i.product_id = p.id)'),
        ],
        'leads' => Db::all('SELECT l.*, p.name product_name FROM leads l LEFT JOIN products p ON p.id = l.product_id ORDER BY l.id DESC LIMIT 8'),
        'tg' => Telegram::configured(),
    ]);
}

function admin_products(): void
{
    $q = trim((string)($_GET['q'] ?? ''));
    $params = [];
    $where = '';
    if ($q !== '') {
        $where = 'WHERE p.name LIKE ? OR p.sku LIKE ?';
        $params = ['%' . $q . '%', '%' . $q . '%'];
    }
    $products = Db::all("SELECT p.*, c.name category_name,
        (SELECT file FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort, i.id LIMIT 1) image,
        (SELECT COUNT(*) FROM product_images i WHERE i.product_id = p.id) images_cnt
        FROM products p LEFT JOIN categories c ON c.id = p.category_id $where ORDER BY p.sort, p.id", $params);
    admin_view('products', ['title' => 'Товары', 'products' => $products, 'q' => $q]);
}

function admin_product_form(?int $id, ?array $data = null, array $errors = []): void
{
    $p = $data;
    if ($p === null && $id) {
        $p = Db::one('SELECT * FROM products WHERE id = ?', [$id]);
        if (!$p) {
            http_response_code(404);
            admin_view('message', ['title' => 'Не найдено', 'text' => 'Товар не найден']);
            return;
        }
    }
    admin_view('product_form', [
        'title' => $id ? 'Редактирование товара' : 'Новый товар',
        'p' => $p ?? ['size_label' => 'M', 'wheels' => 4, 'warranty_months' => 12, 'in_stock' => 1, 'is_active' => 1, 'color_hex' => '#555555'],
        'id' => $id,
        'images' => $id ? Catalog::images($id) : [],
        'categories' => Catalog::categories(),
        'errors' => $errors,
    ]);
}

const PRODUCT_FIELDS = [
    'name', 'slug', 'category_id', 'brand', 'sku', 'price', 'old_price', 'size_label', 'material', 'color', 'color_hex',
    'width_cm', 'height_cm', 'depth_cm', 'volume_l', 'weight_kg', 'wheels', 'lock_type', 'warranty_months',
    'short_desc', 'description', 'meta_title', 'meta_description', 'sort',
];
const PRODUCT_FLAGS = ['expandable', 'in_stock', 'is_active', 'is_featured', 'noindex'];

function admin_product_save(): never
{
    $id = ctype_digit((string)($_POST['id'] ?? '')) ? (int)$_POST['id'] : 0;
    $d = [];
    foreach (PRODUCT_FIELDS as $f) {
        $d[$f] = trim((string)($_POST[$f] ?? ''));
    }
    foreach (PRODUCT_FLAGS as $f) {
        $d[$f] = empty($_POST[$f]) ? 0 : 1;
    }
    $errors = [];
    if (mb_strlen($d['name']) < 3 || mb_strlen($d['name']) > 200) $errors['name'] = 'Название от 3 до 200 символов';
    $d['slug'] = slugify($d['slug'] !== '' ? $d['slug'] : $d['name']);
    if ($d['slug'] === '') $errors['slug'] = 'Укажите адрес страницы (латиницей)';
    elseif (Db::value('SELECT id FROM products WHERE slug = ? AND id <> ?', [$d['slug'], $id])) $errors['slug'] = 'Такой адрес уже занят другим товаром';
    if (!ctype_digit($d['price']) || (int)$d['price'] <= 0) $errors['price'] = 'Цена — целое число больше 0';
    if ($d['old_price'] !== '' && !ctype_digit($d['old_price'])) $errors['old_price'] = 'Старая цена — целое число';
    if (!isset(Catalog::SIZES[$d['size_label']])) $errors['size_label'] = 'Выберите размер';
    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $d['color_hex'])) $d['color_hex'] = '#555555';
    foreach (['width_cm', 'height_cm', 'depth_cm', 'volume_l', 'weight_kg'] as $f) {
        $d[$f] = str_replace(',', '.', $d[$f]);
        if ($d[$f] !== '' && (!is_numeric($d[$f]) || (float)$d[$f] < 0 || (float)$d[$f] > 999)) $errors[$f] = 'Число от 0 до 999';
    }
    if (!in_array($d['wheels'], ['0', '2', '4', '8'], true)) $d['wheels'] = '4';
    $d['warranty_months'] = ctype_digit($d['warranty_months']) ? min(120, (int)$d['warranty_months']) : 12;
    $d['sort'] = is_numeric($d['sort']) ? (int)$d['sort'] : 0;
    $d['category_id'] = ctype_digit($d['category_id']) && Db::value('SELECT id FROM categories WHERE id = ?', [(int)$d['category_id']]) ? (int)$d['category_id'] : null;
    $d['short_desc'] = mb_substr($d['short_desc'], 0, 300);
    $d['meta_title'] = mb_substr($d['meta_title'], 0, 200);
    $d['meta_description'] = mb_substr($d['meta_description'], 0, 320);

    if ($errors) {
        admin_product_form($id ?: null, $d + ['id' => $id], $errors);
        exit;
    }
    foreach (['old_price', 'width_cm', 'height_cm', 'depth_cm', 'volume_l', 'weight_kg'] as $f) {
        $d[$f] = $d[$f] === '' ? null : $d[$f];
    }
    $cols = array_merge(PRODUCT_FIELDS, PRODUCT_FLAGS);
    $vals = array_map(fn($c) => $d[$c], $cols);
    $oldSlug = $id ? (string)Db::value('SELECT slug FROM products WHERE id = ?', [$id]) : '';
    if ($id) {
        Db::run('UPDATE products SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?', [...$vals, $id]);
    } else {
        Db::run('INSERT INTO products (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', $vals);
        $id = Db::lastId();
    }

    // Сменился адрес товара — старая ссылка автоматически ведёт на новую (301), позиции в поиске сохраняются
    if ($oldSlug !== '' && $oldSlug !== $d['slug']) {
        Redirects::add('/product/' . $oldSlug . '/', '/product/' . $d['slug'] . '/', 'Смена адреса товара');
    }

    // Порядок и alt существующих фото
    foreach ((array)($_POST['img_sort'] ?? []) as $imgId => $sort) {
        Db::run('UPDATE product_images SET sort = ?, alt = ? WHERE id = ? AND product_id = ?', [
            (int)$sort, mb_substr(trim((string)($_POST['img_alt'][$imgId] ?? '')), 0, 200), (int)$imgId, $id,
        ]);
    }
    // Новые фото
    $msgs = [];
    if (!empty($_FILES['images']['name'][0])) {
        $maxSort = (int)Db::value('SELECT COALESCE(MAX(sort), 0) FROM product_images WHERE product_id = ?', [$id]);
        foreach ($_FILES['images']['name'] as $i => $n) {
            $file = ['name' => $n, 'tmp_name' => $_FILES['images']['tmp_name'][$i], 'error' => $_FILES['images']['error'][$i], 'size' => $_FILES['images']['size'][$i]];
            try {
                $img = Images::storeUpload($file);
                Db::run('INSERT INTO product_images (product_id, file, width, height, alt, sort) VALUES (?, ?, ?, ?, ?, ?)',
                    [$id, $img['file'], $img['width'], $img['height'], $d['name'], ++$maxSort]);
            } catch (RuntimeException $e) {
                $msgs[] = mb_substr((string)$n, 0, 60) . ': ' . $e->getMessage();
            }
        }
    }
    flash($msgs ? 'Товар сохранён, но часть фото не загружена: ' . implode('; ', $msgs) : 'Товар сохранён', $msgs ? 'err' : 'ok');
    redirect('/admin/products/' . $id . '/');
}

function admin_product_quick(int $id): never
{
    $price = (string)($_POST['price'] ?? '');
    if (ctype_digit($price) && (int)$price > 0) {
        Db::run('UPDATE products SET price = ?, in_stock = ?, is_active = ? WHERE id = ?', [(int)$price, empty($_POST['in_stock']) ? 0 : 1, empty($_POST['is_active']) ? 0 : 1, $id]);
        flash('Изменения сохранены');
    } else {
        flash('Цена должна быть целым числом больше 0', 'err');
    }
    redirect('/admin/products/');
}

function admin_product_duplicate(int $id): never
{
    $p = Db::one('SELECT * FROM products WHERE id = ?', [$id]);
    if ($p) {
        $cols = array_merge(PRODUCT_FIELDS, PRODUCT_FLAGS);
        $p['name'] .= ' (копия)';
        $p['slug'] = $p['slug'] . '-copy-' . substr(bin2hex(random_bytes(3)), 0, 4);
        $p['is_active'] = 0;
        Db::run('INSERT INTO products (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', array_map(fn($c) => $p[$c], $cols));
        $new = Db::lastId();
        flash('Создана копия (скрыта с сайта). Добавьте фото и включите показ.');
        redirect('/admin/products/' . $new . '/');
    }
    redirect('/admin/products/');
}

function admin_product_delete(int $id): never
{
    $p = Db::one('SELECT p.slug, p.is_active, c.slug AS cat FROM products p LEFT JOIN categories c ON c.id = p.category_id WHERE p.id = ?', [$id]);
    foreach (Catalog::images($id) as $im) {
        Images::delete($im['file']);
    }
    Db::run('DELETE FROM products WHERE id = ?', [$id]);
    // Удалённый товар не превращается в ошибку 404: ссылка ведёт в его категорию
    if ($p && $p['is_active']) {
        Redirects::add('/product/' . $p['slug'] . '/', $p['cat'] ? '/catalog/' . $p['cat'] . '/' : '/catalog/', 'Товар удалён');
    }
    flash($p && $p['is_active'] ? 'Товар удалён. Его адрес теперь перенаправляет в категорию (см. «Редиректы»).' : 'Товар удалён');
    redirect('/admin/products/');
}

function admin_image_delete(int $imgId): never
{
    $im = Db::one('SELECT * FROM product_images WHERE id = ?', [$imgId]);
    if ($im) {
        Images::delete($im['file']);
        Db::run('DELETE FROM product_images WHERE id = ?', [$imgId]);
        flash('Фото удалено');
        redirect('/admin/products/' . $im['product_id'] . '/');
    }
    redirect('/admin/products/');
}

function admin_leads(): void
{
    $status = (string)($_GET['status'] ?? '');
    $where = isset(Leads::STATUSES[$status]) ? 'WHERE l.status = ?' : '';
    $leads = Db::all("SELECT l.*, p.name product_name, p.slug product_slug FROM leads l LEFT JOIN products p ON p.id = l.product_id $where ORDER BY l.id DESC LIMIT 500", $where ? [$status] : []);
    admin_view('leads', ['title' => 'Заявки', 'leads' => $leads, 'status' => $status]);
}

function admin_lead_update(int $id): never
{
    $st = (string)($_POST['status'] ?? '');
    if (isset(Leads::STATUSES[$st])) {
        Db::run('UPDATE leads SET status = ?, admin_note = ? WHERE id = ?', [$st, mb_substr(trim((string)($_POST['admin_note'] ?? '')), 0, 1000), $id]);
        flash('Заявка #' . $id . ' обновлена');
    }
    redirect('/admin/leads/' . (isset($_POST['back']) && isset(Leads::STATUSES[$_POST['back']]) ? '?status=' . $_POST['back'] : ''));
}

function admin_leads_export(): never
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="leads-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM для корректного открытия в Excel
    fputcsv($out, ['ID', 'Дата', 'Тип', 'Имя', 'Телефон', 'Товар', 'Город', 'Сообщение', 'Статус', 'Страница', 'UTM', 'Согласие'], ';');
    foreach (Db::all('SELECT l.*, p.name product_name FROM leads l LEFT JOIN products p ON p.id = l.product_id ORDER BY l.id DESC') as $l) {
        // Префикс ' защищает от CSV-инъекций формул в Excel
        $safe = fn($v) => preg_match('/^[=+\-@\t\r]/', (string)$v) ? "'" . $v : (string)$v;
        fputcsv($out, array_map($safe, [$l['id'], $l['created_at'], Leads::TYPES[$l['type']] ?? $l['type'], $l['name'], $l['phone'], $l['product_name'],
            $l['city'], $l['message'], Leads::STATUSES[$l['status']] ?? $l['status'], $l['page_url'], $l['utm'], $l['consent_at'] . ' v' . $l['consent_version']]), ';');
    }
    exit;
}

const SETTINGS_EDITABLE = [
    'site_name', 'site_tagline', 'phone', 'whatsapp', 'telegram', 'email', 'address', 'work_hours', 'city_default', 'instagram',
    'metrika_id', 'ga4_id', 'gtm_id', 'yandex_verification', 'google_verification', 'consent_version', 'company_legal', 'popup_delay',
];

function admin_settings_save(): never
{
    $errors = [];
    foreach (SETTINGS_EDITABLE as $k) {
        $v = trim(strip_tags((string)($_POST[$k] ?? '')));
        $v = mb_substr($v, 0, 300);
        if ($k === 'phone') {
            $n = normalize_phone($v);
            if (!$n) { $errors[] = 'Телефон указан неверно'; continue; }
            $v = $n;
        }
        if ($k === 'whatsapp') $v = preg_replace('/\D/', '', $v);
        if ($k === 'metrika_id' && $v !== '' && !ctype_digit($v)) { $errors[] = 'Номер счётчика Метрики — только цифры'; continue; }
        if ($k === 'ga4_id') { $v = strtoupper($v); if ($v !== '' && !preg_match('/^G-[A-Z0-9]{4,15}$/', $v)) { $errors[] = 'ID Google Analytics 4 — в формате G-XXXXXXXXXX'; continue; } }
        if ($k === 'gtm_id') { $v = strtoupper($v); if ($v !== '' && !preg_match('/^GTM-[A-Z0-9]{4,12}$/', $v)) { $errors[] = 'ID Google Tag Manager — в формате GTM-XXXXXXX'; continue; } }
        if (in_array($k, ['yandex_verification', 'google_verification'], true)) {
            // Можно вставить весь мета-тег целиком — вытащим только content
            if (preg_match('/content=["\']?([^"\'\s>]+)/i', (string)($_POST[$k] ?? ''), $m)) $v = $m[1];
            $v = preg_replace('/[^A-Za-z0-9_\-]/', '', $v);
        }
        if ($k === 'popup_delay') $v = (string)max(0, min(600, (int)$v));
        if ($k === 'email' && $v !== '' && !filter_var($v, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Email указан неверно'; continue; }
        Settings::set($k, $v);
    }
    flash($errors ? 'Сохранено, кроме: ' . implode('; ', $errors) : 'Настройки сохранены', $errors ? 'err' : 'ok');
    redirect('/admin/settings/');
}

function admin_telegram_test(): never
{
    if (!Telegram::configured()) {
        flash('Telegram не настроен: заполните TELEGRAM_BOT_TOKEN и TELEGRAM_CHAT_ID в файле .env на сервере', 'err');
    } else {
        $ok = Telegram::send('✅ Тестовое сообщение с сайта ' . htmlspecialchars(Settings::get('site_name')) . '. Уведомления о заявках работают.');
        flash($ok ? 'Тестовое сообщение отправлено в Telegram' : 'Ошибка отправки. Проверьте токен, chat_id и что бот добавлен в чат (подробности в логе)', $ok ? 'ok' : 'err');
    }
    redirect('/admin/settings/');
}

function admin_seo_edit(string $type, int $id, string $method): void
{
    $table = $type === 'city' ? 'cities' : 'categories';
    $row = $id ? Db::one("SELECT * FROM $table WHERE id = ?", [$id]) : null;
    if ($id && !$row) {
        redirect('/admin/seo/');
    }
    $errors = [];
    if ($method === 'POST') {
        $d = fn($k, $len) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $len);
        $name = $d('name', $type === 'city' ? 80 : 120);
        $slug = slugify($d('slug', 80) ?: $name);
        if (mb_strlen($name) < 2) $errors[] = 'Укажите название';
        if ($slug === '') $errors[] = 'Укажите адрес (латиницей)';
        elseif (Db::value("SELECT id FROM $table WHERE slug = ? AND id <> ?", [$slug, $id])) $errors[] = 'Такой адрес уже занят';
        elseif ($type === 'city' && in_array($slug, reserved_slugs(), true)) $errors[] = 'Адрес /' . $slug . '/ зарезервирован сайтом, выберите другой';
        $nameIn = $d('name_in', 100) ?: 'в ' . $name;
        if (!$errors) {
            if ($type === 'city') {
                $vals = [$slug, $name, $nameIn, $d('delivery_days', 40) ?: '2–4 дня', $d('delivery_text', 3000), $d('meta_title', 200), $d('meta_description', 320), $d('intro', 2000), $d('seo_text', 20000), empty($_POST['is_active']) ? 0 : 1, (int)($_POST['sort'] ?? 0)];
                $id ? Db::run('UPDATE cities SET slug = ?, name = ?, name_in = ?, delivery_days = ?, delivery_text = ?, meta_title = ?, meta_description = ?, intro = ?, seo_text = ?, is_active = ?, sort = ? WHERE id = ?', [...$vals, $id])
                    : Db::run('INSERT INTO cities (slug, name, name_in, delivery_days, delivery_text, meta_title, meta_description, intro, seo_text, is_active, sort) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', $vals);
                $prefix = '/';
            } else {
                $vals = [$slug, $name, $d('h1', 200), $d('meta_title', 200), $d('meta_description', 320), $d('intro', 2000), $d('seo_text', 20000), (int)($_POST['sort'] ?? 0)];
                $id ? Db::run('UPDATE categories SET slug = ?, name = ?, h1 = ?, meta_title = ?, meta_description = ?, intro = ?, seo_text = ?, sort = ? WHERE id = ?', [...$vals, $id])
                    : Db::run('INSERT INTO categories (slug, name, h1, meta_title, meta_description, intro, seo_text, sort) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', $vals);
                $prefix = '/catalog/';
            }
            $msg = $id ? 'Сохранено' : ($type === 'city' ? 'Город добавлен' : 'Категория добавлена');
            if ($row && $row['slug'] !== $slug) {
                Redirects::add($prefix . $row['slug'] . '/', $prefix . $slug . '/', 'Смена адреса');
                $msg .= '. Старый адрес ' . $prefix . $row['slug'] . '/ перенаправляет на новый.';
            }
            $id = $id ?: Db::lastId();
            flash($msg);
            redirect("/admin/seo/$id/$type/");
        }
        $row = array_merge($row ?? [], $_POST, ['slug' => $slug]);
    }
    admin_view('seo_edit', [
        'title' => $id ? ($type === 'city' ? 'Город: ' : 'Категория: ') . $row['name'] : ($type === 'city' ? 'Новый город' : 'Новая категория'),
        'row' => $row ?? ['is_active' => 1, 'sort' => 100], 'type' => $type, 'id' => $id, 'errors' => $errors,
    ]);
}

function admin_seo_delete(string $type, int $id): never
{
    $table = $type === 'city' ? 'cities' : 'categories';
    $row = Db::one("SELECT * FROM $table WHERE id = ?", [$id]);
    if ($row) {
        Db::run("DELETE FROM $table WHERE id = ?", [$id]);
        if ($type === 'city') {
            Redirects::add('/' . $row['slug'] . '/', '/goroda/', 'Город удалён');
        } else {
            Redirects::add('/catalog/' . $row['slug'] . '/', '/catalog/', 'Категория удалена');
        }
        flash(($type === 'city' ? 'Город' : 'Категория') . ' «' . $row['name'] . '» удалён(а). Старый адрес перенаправляет ' . ($type === 'city' ? 'на список городов.' : 'в общий каталог; товары остались без категории.'));
    }
    redirect('/admin/seo/');
}

function admin_page_edit(string $slug, string $method): void
{
    if ($method === 'POST') {
        $d = fn($k, $len) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $len);
        Pages::save($slug, ['meta_title' => $d('meta_title', 200), 'meta_description' => $d('meta_description', 320), 'h1' => $d('h1', 200), 'body' => $d('body', 50000)]);
        flash('Страница сохранена');
        redirect('/admin/pages/' . $slug . '/');
    }
    admin_view('page_edit', ['title' => 'Страница: ' . Pages::EDITABLE[$slug][0], 'slug' => $slug, 'page' => Pages::get($slug), 'mode' => Pages::EDITABLE[$slug][1]]);
}

function admin_article_form(?int $id, ?array $data = null, array $errors = []): void
{
    $a = $data ?? ($id ? Db::one('SELECT * FROM articles WHERE id = ?', [$id]) : null);
    if ($id && !$a) {
        redirect('/admin/blog/');
    }
    admin_view('article_form', ['title' => $id ? 'Статья' : 'Новая статья', 'a' => $a ?? ['is_published' => 0, 'published_at' => date('Y-m-d H:i:s')], 'id' => $id, 'errors' => $errors]);
}

function admin_article_save(): never
{
    $id = ctype_digit((string)($_POST['id'] ?? '')) ? (int)$_POST['id'] : 0;
    $old = $id ? Db::one('SELECT * FROM articles WHERE id = ?', [$id]) : null;
    $d = fn($k, $len) => mb_substr(trim((string)($_POST[$k] ?? '')), 0, $len);
    $a = [
        'title' => $d('title', 200), 'slug' => slugify($d('slug', 160) ?: $d('title', 200)),
        'meta_title' => $d('meta_title', 200), 'meta_description' => $d('meta_description', 320),
        'excerpt' => $d('excerpt', 400), 'body' => $d('body', 100000), 'cover_alt' => $d('cover_alt', 200),
        'is_published' => empty($_POST['is_published']) ? 0 : 1, 'noindex' => empty($_POST['noindex']) ? 0 : 1,
    ];
    $ts = strtotime(str_replace('T', ' ', $d('published_at', 20)));
    $a['published_at'] = date('Y-m-d H:i:s', $ts ?: time());
    $errors = [];
    if (mb_strlen($a['title']) < 5) $errors['title'] = 'Заголовок от 5 символов';
    if ($a['slug'] === '') $errors['slug'] = 'Укажите адрес';
    elseif (Db::value('SELECT id FROM articles WHERE slug = ? AND id <> ?', [$a['slug'], $id])) $errors['slug'] = 'Такой адрес уже занят';
    if ($errors) {
        admin_article_form($id ?: null, $a + ['id' => $id, 'cover' => $old['cover'] ?? ''], $errors);
        exit;
    }
    $cols = array_keys($a);
    if ($id) {
        Db::run('UPDATE articles SET ' . implode(', ', array_map(fn($c) => "$c = ?", $cols)) . ' WHERE id = ?', [...array_values($a), $id]);
    } else {
        Db::run('INSERT INTO articles (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')', array_values($a));
        $id = Db::lastId();
    }
    $msg = 'Статья сохранена';
    if ($old && $old['slug'] !== $a['slug']) {
        Redirects::add('/blog/' . $old['slug'] . '/', '/blog/' . $a['slug'] . '/', 'Смена адреса статьи');
    }
    if (!empty($_POST['remove_cover']) && $old && $old['cover']) {
        Images::delete($old['cover']);
        Db::run("UPDATE articles SET cover = '' WHERE id = ?", [$id]);
    }
    if (!empty($_FILES['cover']['name'])) {
        try {
            $img = Images::storeUpload($_FILES['cover']);
            if ($old && $old['cover']) Images::delete($old['cover']);
            Db::run('UPDATE articles SET cover = ? WHERE id = ?', [$img['file'], $id]);
        } catch (RuntimeException $e) {
            flash('Статья сохранена, но обложка не загружена: ' . $e->getMessage(), 'err');
            redirect('/admin/blog/' . $id . '/');
        }
    }
    flash($msg);
    redirect('/admin/blog/' . $id . '/');
}

function admin_article_delete(int $id): never
{
    $a = Db::one('SELECT * FROM articles WHERE id = ?', [$id]);
    if ($a) {
        if ($a['cover']) Images::delete($a['cover']);
        Db::run('DELETE FROM articles WHERE id = ?', [$id]);
        if ($a['is_published']) Redirects::add('/blog/' . $a['slug'] . '/', '/blog/', 'Статья удалена');
        flash('Статья удалена');
    }
    redirect('/admin/blog/');
}

function admin_redirect_add(): never
{
    $err = Redirects::add((string)($_POST['from_path'] ?? ''), (string)($_POST['to_path'] ?? ''), trim((string)($_POST['note'] ?? '')));
    flash($err ?? 'Редирект добавлен', $err ? 'err' : 'ok');
    redirect('/admin/redirects/');
}

function admin_redirect_delete(int $id): never
{
    Db::run('DELETE FROM redirects WHERE id = ?', [$id]);
    flash('Редирект удалён');
    redirect('/admin/redirects/');
}

function admin_password_save(array $admin): never
{
    $cur = (string)($_POST['current'] ?? '');
    $new = (string)($_POST['new'] ?? '');
    $row = Db::one('SELECT password_hash FROM admins WHERE id = ?', [$admin['id']]);
    if (!password_verify($cur, $row['password_hash'])) {
        flash('Текущий пароль неверный', 'err');
    } elseif (strlen($new) < 10 || !preg_match('/\d/', $new) || !preg_match('/\p{L}/u', $new)) {
        flash('Новый пароль: минимум 10 символов, буквы и цифры', 'err');
    } elseif ($new !== (string)($_POST['new2'] ?? '')) {
        flash('Пароли не совпадают', 'err');
    } else {
        Db::run('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($new, PASSWORD_DEFAULT), $admin['id']]);
        session_regenerate_id(true);
        flash('Пароль изменён');
    }
    redirect('/admin/password/');
}
