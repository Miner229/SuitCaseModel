<?php
declare(strict_types=1);
/**
 * Установка: создаёт таблицы, наполняет демо-данными, создаёт администратора.
 *   php bin/install.php                     — таблицы + демо-данные + админ (пароль сгенерируется)
 *   php bin/install.php --admin=login --password=Secret12345
 *   php bin/install.php --no-demo           — без демо-товаров
 *   php bin/install.php --reset             — УДАЛИТЬ все данные и установить заново (только для тестового стенда)
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/demo_data.php';
require __DIR__ . '/demo_images.php';

$opt = getopt('', ['admin::', 'password::', 'no-demo', 'reset']);
$pdo = Db::pdo();

if (isset($opt['reset'])) {
    echo "Удаление таблиц...\n";
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['leads', 'product_images', 'products', 'categories', 'cities', 'settings', 'admins', 'rate_limits'] as $t) {
        $pdo->exec("DROP TABLE IF EXISTS $t");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    foreach (glob(UPLOADS . '/*.webp') as $f) {
        unlink($f);
    }
}

echo "Создание таблиц...\n";
foreach (array_filter(array_map('trim', explode(';', (string)file_get_contents(ROOT . '/database/schema.sql')))) as $sql) {
    if (!str_starts_with($sql, '--') || str_contains($sql, 'CREATE')) {
        $pdo->exec($sql);
    }
}
@mkdir(ROOT . '/storage/logs', 0750, true);
@mkdir(UPLOADS, 0755, true);

if (!Db::value('SELECT COUNT(*) FROM categories')) {
    foreach (DEMO_CATEGORIES as $i => $c) {
        Db::run('INSERT INTO categories (slug, name, h1, meta_title, meta_description, intro, sort) VALUES (?, ?, ?, ?, ?, ?, ?)', [...$c, $i]);
    }
    echo "Категории: " . count(DEMO_CATEGORIES) . "\n";
}
if (!Db::value('SELECT COUNT(*) FROM cities')) {
    foreach (DEMO_CITIES as $i => [$slug, $name, $in, $days, $intro]) {
        Db::run('INSERT INTO cities (slug, name, name_in, delivery_days, intro, sort) VALUES (?, ?, ?, ?, ?, ?)', [$slug, $name, $in, $days, $intro, $i]);
    }
    echo "Города: " . count(DEMO_CITIES) . "\n";
}

if (!isset($opt['no-demo']) && !Db::value('SELECT COUNT(*) FROM products')) {
    $catIds = array_column(Db::all('SELECT id, slug FROM categories'), 'id', 'slug');
    $tmp = sys_get_temp_dir() . '/demo-' . bin2hex(random_bytes(4)) . '.png';
    foreach (DEMO_PRODUCTS as $i => $p) {
        Db::run('INSERT INTO products (slug, name, category_id, brand, sku, price, old_price, size_label, material, color, color_hex,
            height_cm, width_cm, depth_cm, volume_l, weight_kg, wheels, lock_type, expandable, warranty_months, short_desc, description,
            in_stock, is_featured, sort) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', [
            slugify($p['name']), $p['name'], $catIds[$p['cat']], $p['brand'], $p['sku'], $p['price'], $p['old'] ?? null, $p['size'],
            $p['material'], $p['color'], $p['hex'], $p['dims'][0] ?? null, $p['dims'][1] ?? null, $p['dims'][2] ?? null,
            $p['vol'], $p['kg'], $p['wheels'] ?? 4, $p['lock'] ?? 'Кодовый TSA', (int)($p['exp'] ?? 0), $p['warranty'] ?? 36,
            $p['short'], $p['desc'], (int)($p['stock'] ?? 1), (int)($p['top'] ?? 0), $i * 10,
        ]);
        $pid = Db::lastId();
        foreach (['front', 'side', 'open'] as $k => $view) {
            demo_suitcase_png($tmp, $p['hex'], $p['size'], $view, str_starts_with($p['material'], 'Ткань'));
            $img = Images::storeFile($tmp);
            Db::run('INSERT INTO product_images (product_id, file, width, height, alt, sort) VALUES (?, ?, ?, ?, ?, ?)',
                [$pid, $img['file'], $img['width'], $img['height'], $p['name'] . ' — ' . ['вид спереди', 'вид сбоку', 'внутри'][$k], $k]);
        }
        echo "  товар: {$p['name']}\n";
    }
    @unlink($tmp);
}

foreach (Settings::DEFAULTS as $k => $v) {
    Db::run('INSERT IGNORE INTO settings (k, v) VALUES (?, ?)', [$k, $v]);
}

$login = (string)($opt['admin'] ?? 'admin');
if (!Db::value('SELECT id FROM admins WHERE login = ?', [$login])) {
    $pass = (string)($opt['password'] ?? '');
    if ($pass === '') {
        $pass = substr(strtr(base64_encode(random_bytes(12)), '+/=', 'xyz'), 0, 14) . random_int(10, 99);
    }
    Db::run('INSERT INTO admins (login, password_hash) VALUES (?, ?)', [$login, password_hash($pass, PASSWORD_DEFAULT)]);
    echo "\nАдминистратор создан:\n  логин:  $login\n  пароль: $pass\n  вход:   " . base_url('/admin/') . "\nСохраните пароль и смените его после первого входа.\n";
}
echo "\nГотово.\n";

