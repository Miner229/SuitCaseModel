<?php
declare(strict_types=1);
/**
 * Запускается при каждом старте контейнера (deploy/entrypoint.sh). Безопасно запускать повторно.
 * 1) Однократно заменяет старые демо-фото на студийные рендеры из bin/demo-photos/.
 *    Фото, загруженные заказчиком через админку, НЕ трогаются (у демо-фото особый alt).
 * 2) Восстанавливает потерянные файлы фото (редеплой без постоянного диска).
 *   php bin/repair_images.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/demo_images.php';

const DEMO_PHOTOS_VERSION = '4';

$products = Db::all('SELECT id, name, sku, color_hex AS hex, size_label AS size, material FROM products');

// 1. Обновление демо-фото до новой версии
if (Settings::get('demo_photos_version') !== DEMO_PHOTOS_VERSION) {
    $replaced = 0;
    foreach ($products as $p) {
        if (!demo_photo_file($p['sku'], 'front')) continue;
        $demo = array_filter(Catalog::images((int)$p['id']), fn($i) => preg_match(DEMO_ALT_RE, $i['alt']));
        if (!$demo) continue;
        foreach ($demo as $im) {
            Images::delete($im['file']);
            Db::run('DELETE FROM product_images WHERE id = ?', [$im['id']]);
        }
        foreach (DEMO_VIEWS as $k => [, $label]) {
            $img = demo_store_photo($p, $k);
            Db::run('INSERT INTO product_images (product_id, file, width, height, alt, sort) VALUES (?, ?, ?, ?, ?, ?)',
                [$p['id'], $img['file'], $img['width'], $img['height'], $p['name'] . ' — ' . $label, $k - 10]);
        }
        $replaced++;
    }
    Settings::set('demo_photos_version', DEMO_PHOTOS_VERSION);
    echo "Демо-фото обновлены у товаров: $replaced\n";
}

// 2. Восстановление потерянных файлов
$byId = array_column($products, null, 'id');
$fixed = 0;
foreach (Db::all('SELECT id, product_id, file, sort FROM product_images') as $r) {
    if (is_file(UPLOADS . '/' . $r['file'] . '-800.webp')) continue;
    $img = demo_store_photo($byId[$r['product_id']], ((int)$r['sort'] % 3 + 3) % 3);
    Db::run('UPDATE product_images SET file = ?, width = ?, height = ? WHERE id = ?', [$img['file'], $img['width'], $img['height'], $r['id']]);
    $fixed++;
}
echo $fixed ? "Восстановлено фото: $fixed (демо-заглушки). Подключите постоянный диск к public/uploads.\n" : "Все фото на месте.\n";
