<?php
declare(strict_types=1);

final class Security
{
    private static string $nonce = '';

    public static function nonce(): string
    {
        if (self::$nonce === '') {
            self::$nonce = base64_encode(random_bytes(16));
        }
        return self::$nonce;
    }

    /** HTTP-заголовки безопасности. CSP разрешает только свой домен + Яндекс Метрику + Google (верификация). */
    public static function sendHeaders(bool $admin = false): void
    {
        $n = self::nonce();
        $metrika = 'https://mc.yandex.ru https://mc.yandex.com https://yastatic.net';
        $csp = [
            "default-src 'self'",
            "script-src 'self' 'nonce-$n' $metrika",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: blob: $metrika",
            "connect-src 'self' $metrika wss://mc.yandex.ru wss://mc.yandex.com",
            "frame-src 'self' https://mc.yandex.ru https://mc.yandex.com https://yandex.ru https://www.google.com",
            "font-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            $admin ? "frame-ancestors 'none'" : "frame-ancestors 'self' https://webvisor.com https://*.webvisor.com https://metrika.yandex.ru https://metrika.yandex.kz",
        ];
        header('Content-Security-Policy: ' . implode('; ', $csp));
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
        if ($admin) {
            header('X-Frame-Options: DENY');
            header('X-Robots-Tag: noindex, nofollow');
            header('Cache-Control: no-store');
        }
        if (is_https()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('sid');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
    }

    // ---------- CSRF для админки (сессионный токен) ----------
    public static function csrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf'];
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(self::csrfToken()) . '">';
    }

    public static function checkCsrf(): void
    {
        self::startSession();
        $t = (string)($_POST['_csrf'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $t)) {
            http_response_code(419);
            exit('Сессия устарела. Обновите страницу и повторите действие.');
        }
    }

    // ---------- Токен публичных форм (без сессии, чтобы страницы кэшировались) ----------
    // Подписанная метка времени: защищает от CSRF с чужих сайтов и от ботов, отправляющих форму мгновенно.
    public static function formToken(): string
    {
        $ts = (string)time();
        return $ts . '.' . hash_hmac('sha256', $ts, self::secret());
    }

    /** @return string|null текст ошибки или null, если токен валиден */
    public static function verifyFormToken(string $token, int $minSeconds = 3, int $maxSeconds = 86400): ?string
    {
        if (!preg_match('/^(\d{10})\.([a-f0-9]{64})$/', $token, $m)) {
            return 'bad_token';
        }
        if (!hash_equals(hash_hmac('sha256', $m[1], self::secret()), $m[2])) {
            return 'bad_token';
        }
        $age = time() - (int)$m[1];
        if ($age < $minSeconds) {
            return 'too_fast';
        }
        if ($age > $maxSeconds) {
            return 'expired';
        }
        return null;
    }

    private static function secret(): string
    {
        $s = Env::get('APP_SECRET');
        if (strlen($s) < 32 || str_starts_with($s, 'change_me')) {
            if (Env::get('APP_ENV') === 'production') {
                throw new RuntimeException('APP_SECRET не задан в .env (минимум 32 символа)');
            }
            $s = 'dev-only-secret-' . str_repeat('x', 32);
        }
        return $s;
    }

    // ---------- Ограничение частоты запросов (хранится в БД) ----------
    /** true — лимит не превышен. */
    public static function rateLimit(string $key, int $max, int $windowSec): bool
    {
        $now = time();
        $key = substr($key, 0, 120);
        $row = Db::one('SELECT hits, window_start FROM rate_limits WHERE rkey = ?', [$key]);
        if (!$row || $now - (int)$row['window_start'] >= $windowSec) {
            Db::run('REPLACE INTO rate_limits (rkey, hits, window_start) VALUES (?, 1, ?)', [$key, $now]);
            if (random_int(1, 50) === 1) {
                Db::run('DELETE FROM rate_limits WHERE window_start < ?', [$now - 86400]);
            }
            return true;
        }
        if ((int)$row['hits'] >= $max) {
            return false;
        }
        Db::run('UPDATE rate_limits SET hits = hits + 1 WHERE rkey = ?', [$key]);
        return true;
    }

    public static function resetRateLimit(string $key): void
    {
        Db::run('DELETE FROM rate_limits WHERE rkey = ?', [$key]);
    }

    // ---------- Авторизация администратора ----------
    public static function admin(): ?array
    {
        self::startSession();
        if (empty($_SESSION['admin_id'])) {
            return null;
        }
        // Таймаут неактивности 2 часа
        if (time() - (int)($_SESSION['admin_seen'] ?? 0) > 7200) {
            self::logout();
            return null;
        }
        // Привязка сессии к User-Agent (простая защита от угона cookie)
        if (($_SESSION['admin_ua'] ?? '') !== hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '')) {
            self::logout();
            return null;
        }
        $_SESSION['admin_seen'] = time();
        return Db::one('SELECT id, login FROM admins WHERE id = ?', [(int)$_SESSION['admin_id']]);
    }

    public static function login(string $login, string $password): bool
    {
        $row = Db::one('SELECT * FROM admins WHERE login = ?', [$login]);
        // password_verify выполняем всегда, чтобы время ответа не выдавало существование логина
        $hash = $row['password_hash'] ?? password_hash(random_bytes(8), PASSWORD_DEFAULT);
        $ok = password_verify($password, $hash) && $row !== null;
        if (!$ok) {
            return false;
        }
        if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
            Db::run('UPDATE admins SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $row['id']]);
        }
        self::startSession();
        session_regenerate_id(true);
        $_SESSION['admin_id'] = (int)$row['id'];
        $_SESSION['admin_seen'] = time();
        $_SESSION['admin_ua'] = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        Db::run('UPDATE admins SET last_login_at = NOW() WHERE id = ?', [$row['id']]);
        return true;
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        session_regenerate_id(true);
    }
}
