<?php
// Роутер ТОЛЬКО для локальной разработки: php -S localhost:8000 -t public public/router.php
// На хостинге его роль выполняют .htaccess (Apache) или nginx.conf.
$p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($p !== '/' && is_file(__DIR__ . $p) && !str_ends_with($p, '.php')) {
    if (str_starts_with($p, '/assets/') || str_starts_with($p, '/uploads/')) {
        header('Cache-Control: public, max-age=31536000, immutable');
    }
    return false;
}
require __DIR__ . '/index.php';
