<?php
declare(strict_types=1);

define('ROOT', dirname(__DIR__));
define('APP', ROOT . '/app');
define('PUB', ROOT . '/public');
define('UPLOADS', PUB . '/uploads');

require APP . '/lib/Env.php';
Env::load(ROOT . '/.env');

$debug = Env::get('APP_DEBUG') === '1';
error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT . '/storage/logs/php-error.log');
if (PHP_SAPI !== 'cli') {
    header_remove('X-Powered-By');
}
date_default_timezone_set('Asia/Almaty');
mb_internal_encoding('UTF-8');

foreach (['Db', 'Helpers', 'Security', 'Settings', 'Seo', 'Catalog', 'Leads', 'Telegram', 'Images', 'View'] as $lib) {
    require APP . '/lib/' . $lib . '.php';
}

set_exception_handler(function (Throwable $e) use ($debug) {
    error_log((string)$e);
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }
    echo $debug ? '<pre>' . e((string)$e) . '</pre>' : '<h1>Временная ошибка</h1><p>Пожалуйста, обновите страницу через минуту.</p>';
});
