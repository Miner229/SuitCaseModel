<?php
declare(strict_types=1);
/**
 * Восстанавливает потерянные файлы фото (например, после редеплоя без постоянного диска).
 * Для каждой записи в product_images без файла рисует демо-картинку в цвете товара.
 *   php bin/repair_images.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/bootstrap.php';
require __DIR__ . '/demo_images.php';

$rows = Db::all('SELECT i.id, i.file, i.sort, p.color_hex, p.size_label, p.material FROM product_images i JOIN products p ON p.id = i.product_id');
$tmp = sys_get_temp_dir() . '/repair-' . bin2hex(random_bytes(4)) . '.png';
$fixed = 0;
foreach ($rows as $r) {
    if (is_file(UPLOADS . '/' . $r['file'] . '-800.webp')) continue;
    $view = ['front', 'side', 'open'][((int)$r['sort']) % 3];
    demo_suitcase_png($tmp, $r['color_hex'], $r['size_label'], $view, str_starts_with($r['material'], 'Ткань'));
    $img = Images::storeFile($tmp);
    Db::run('UPDATE product_images SET file = ?, width = ?, height = ? WHERE id = ?', [$img['file'], $img['width'], $img['height'], $r['id']]);
    $fixed++;
}
@unlink($tmp);
echo $fixed ? "Восстановлено фото: $fixed (демо-заглушки). Подключите постоянный диск к public/uploads.\n" : "Все фото на месте.\n";
