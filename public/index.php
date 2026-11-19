<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = (string)($_SERVER['REQUEST_URI'] ?? '/');
$path = rawurldecode((string)parse_url($uri, PHP_URL_PATH));
$query = (string)parse_url($uri, PHP_URL_QUERY);

// Скрытые файлы и папки (.git, .env и т.п.) — всегда 404
if (preg_match('~/\.~', $path)) {
    http_response_code(404);
    exit('Not Found');
}

// ---- Нормализация URL (одна страница = один адрес, важно для SEO) ----
if (Env::get('APP_FORCE_HTTPS') === '1' && !is_https() && PHP_SAPI !== 'cli-server') {
    redirect('https://' . ($_SERVER['HTTP_HOST'] ?? '') . $uri, 301);
}
if ($method === 'GET') {
    $norm = $path;
    if ($norm === '/index.php') {
        $norm = '/';
    }
    $norm = preg_replace('~/{2,}~', '/', $norm);
    if (preg_match('~[A-Z]~', $norm) && !str_starts_with($norm, '/uploads/')) {
        $norm = strtolower($norm);
    }
    if (!str_ends_with($norm, '/') && !preg_match('~\.[a-z0-9]{2,4}$~', $norm)) {
        $norm .= '/';
    }
    if ($norm !== $path) {
        redirect($norm . ($query !== '' ? '?' . $query : ''), 301);
    }
}

require APP . '/routes.php';
dispatch($method, $path);
