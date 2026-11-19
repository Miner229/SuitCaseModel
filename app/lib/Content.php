<?php
declare(strict_types=1);

/**
 * Простая разметка для SEO-текстов и статей (заказчику не нужен HTML):
 *   ## Заголовок, ### Подзаголовок, - список, 1. нумерованный список, **жирный**, [текст](ссылка),
 *   | таблица | с колонками |, пустая строка — новый абзац.
 * Всё экранируется до разбора, поэтому вставить скрипт или произвольный HTML невозможно.
 */
final class Markdown
{
    public static function render(?string $text): string
    {
        $text = trim(str_replace("\r\n", "\n", (string)$text));
        if ($text === '') {
            return '';
        }
        $out = [];
        foreach (preg_split('/\n{2,}/', $text) as $block) {
            $lines = explode("\n", trim($block));
            if (preg_match('/^(#{2,3})\s+(.+)$/', $lines[0], $m) && count($lines) === 1) {
                $tag = strlen($m[1]) === 2 ? 'h2' : 'h3';
                $out[] = "<$tag>" . self::inline($m[2]) . "</$tag>";
            } elseif (self::all($lines, '/^[-*]\s+/')) {
                $out[] = '<ul>' . implode('', array_map(fn($l) => '<li>' . self::inline(preg_replace('/^[-*]\s+/', '', $l)) . '</li>', $lines)) . '</ul>';
            } elseif (self::all($lines, '/^\d+[.)]\s+/')) {
                $out[] = '<ol>' . implode('', array_map(fn($l) => '<li>' . self::inline(preg_replace('/^\d+[.)]\s+/', '', $l)) . '</li>', $lines)) . '</ol>';
            } elseif (self::all($lines, '/^\|/')) {
                $out[] = self::table($lines);
            } else {
                // заголовок, за которым сразу идёт текст без пустой строки
                if (preg_match('/^(#{2,3})\s+(.+)$/', $lines[0], $m)) {
                    $tag = strlen($m[1]) === 2 ? 'h2' : 'h3';
                    $out[] = "<$tag>" . self::inline($m[2]) . "</$tag>";
                    array_shift($lines);
                }
                if ($lines) {
                    $out[] = '<p>' . implode('<br>', array_map([self::class, 'inline'], $lines)) . '</p>';
                }
            }
        }
        return implode("\n", $out);
    }

    private static function all(array $lines, string $re): bool
    {
        foreach ($lines as $l) {
            if (!preg_match($re, $l)) return false;
        }
        return true;
    }

    private static function table(array $lines): string
    {
        $rows = [];
        foreach ($lines as $l) {
            if (preg_match('/^\|[\s:|-]+\|?$/', $l)) continue; // строка-разделитель |---|---|
            $rows[] = array_map('trim', explode('|', trim(trim($l), '|')));
        }
        if (!$rows) return '';
        $head = array_shift($rows);
        $h = '<table class="specs"><thead><tr>' . implode('', array_map(fn($c) => '<th>' . self::inline($c) . '</th>', $head)) . '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $h .= '<tr>' . implode('', array_map(fn($c) => '<td>' . self::inline($c) . '</td>', $r)) . '</tr>';
        }
        return $h . '</tbody></table>';
    }

    public static function inline(string $s): string
    {
        $s = e(trim($s));
        $s = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s);
        return preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)\)/u', function ($m) {
            $url = html_entity_decode($m[2], ENT_QUOTES);
            if (!preg_match('~^(https?://|/)[^\s"<>]*$~i', $url) || str_starts_with($url, '//')) {
                return $m[1]; // недопустимая ссылка (javascript: и т.п.) — оставляем только текст
            }
            $ext = preg_match('~^https?://~i', $url) && parse_url($url, PHP_URL_HOST) !== parse_url(base_url('/'), PHP_URL_HOST);
            return '<a href="' . e($url) . '"' . ($ext ? ' target="_blank" rel="noopener"' : '') . '>' . $m[1] . '</a>';
        }, $s);
    }

    /** Простой текст из разметки — для description и анонсов. */
    public static function plain(?string $text): string
    {
        $t = preg_replace(['/^#{2,3}\s+/m', '/\*\*/', '/\[([^\]]+)\]\([^)]+\)/', '/^[-*]\s+/m', '/^\|.*$/m'], ['', '', '$1', '', ''], (string)$text);
        return trim(preg_replace('/\s+/u', ' ', (string)$t));
    }
}

/** 301-редиректы: старые адреса → новые. Сохраняют позиции в поиске при смене ЧПУ или удалении страниц. */
final class Redirects
{
    /** Адреса, которые нельзя перенаправлять (служебные разделы). */
    private const PROTECTED = ['/admin', '/api/', '/assets/', '/uploads/'];

    /** Приводит путь к каноническому виду: /путь/ в нижнем регистре, без домена и параметров. */
    public static function normalize(string $path): string
    {
        $path = trim($path);
        if (preg_match('~^https?://~i', $path)) {
            $path = (string)parse_url($path, PHP_URL_PATH);
        }
        $path = '/' . ltrim(rawurldecode((string)parse_url($path, PHP_URL_PATH)), '/');
        $path = preg_replace('~/{2,}~', '/', mb_strtolower($path));
        if (!str_ends_with($path, '/') && !preg_match('~\.[a-z0-9]{2,4}$~', $path)) {
            $path .= '/';
        }
        return $path;
    }

    public static function find(string $path): ?array
    {
        try {
            $r = Db::one('SELECT * FROM redirects WHERE from_path = ?', [self::normalize($path)]);
        } catch (PDOException) {
            return null;
        }
        if ($r) {
            Db::run('UPDATE redirects SET hits = hits + 1 WHERE id = ?', [$r['id']]);
        }
        return $r;
    }

    /** @return string|null текст ошибки или null при успехе */
    public static function add(string $from, string $to, string $note = ''): ?string
    {
        $from = self::normalize($from);
        $to = trim($to);
        if ($from === '/') return 'Нельзя перенаправить главную страницу';
        foreach (self::PROTECTED as $p) {
            if (str_starts_with($from, $p)) return 'Служебные адреса перенаправлять нельзя';
        }
        if (preg_match('~^https?://~i', $to)) {
            if (!filter_var($to, FILTER_VALIDATE_URL)) return 'Неверный адрес назначения';
            if (parse_url($to, PHP_URL_HOST) === parse_url(base_url('/'), PHP_URL_HOST)) $to = self::normalize($to);
        } elseif (str_starts_with($to, '/') && !str_starts_with($to, '//')) {
            $q = parse_url($to, PHP_URL_QUERY);
            $to = self::normalize($to) . ($q ? '?' . $q : '');
        } else {
            return 'Адрес назначения должен начинаться с / или https://';
        }
        if ($to === $from) return 'Адрес не может перенаправлять сам на себя';
        // Если на новый адрес уже вёл редирект — удаляем его (иначе получится петля A → B → A)
        Db::run('DELETE FROM redirects WHERE from_path = ?', [$to]);
        // Цепочки A → B, B → C схлопываем в A → C (поисковики не любят цепочки)
        Db::run('UPDATE redirects SET to_path = ? WHERE to_path = ?', [$to, $from]);
        Db::run('INSERT INTO redirects (from_path, to_path, note) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE to_path = VALUES(to_path), note = VALUES(note)', [$from, $to, mb_substr($note, 0, 200)]);
        return null;
    }
}

/** Метатеги и тексты главной и информационных страниц, редактируемые в админке. */
final class Pages
{
    /** slug => [название в админке, режим поля «текст»: replace — заменяет текст страницы, append — SEO-текст внизу] */
    public const EDITABLE = [
        'home' => ['Главная', 'append'],
        'dostavka-i-oplata' => ['Доставка и оплата', 'append'],
        'kontakty' => ['Контакты', 'append'],
        'o-kompanii' => ['О компании', 'replace'],
        'garantiya-i-vozvrat' => ['Гарантия и возврат', 'replace'],
        'kak-vybrat-chemodan' => ['Как выбрать чемодан', 'replace'],
        'politika-konfidencialnosti' => ['Политика конфиденциальности', 'replace'],
        'soglasie' => ['Согласие на обработку ПД', 'replace'],
        'goroda' => ['Города доставки (список)', 'append'],
        'blog' => ['Блог (список статей)', 'append'],
        'catalog' => ['Каталог (все товары)', 'append'],
    ];

    private static array $cache = [];

    public static function get(string $slug): array
    {
        if (!array_key_exists($slug, self::$cache)) {
            try {
                self::$cache[$slug] = Db::one('SELECT * FROM pages WHERE slug = ?', [$slug]) ?? [];
            } catch (PDOException) {
                self::$cache[$slug] = [];
            }
        }
        return self::$cache[$slug] + ['meta_title' => '', 'meta_description' => '', 'h1' => '', 'body' => ''];
    }

    public static function save(string $slug, array $d): void
    {
        Db::run('INSERT INTO pages (slug, meta_title, meta_description, h1, body) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE meta_title = VALUES(meta_title), meta_description = VALUES(meta_description), h1 = VALUES(h1), body = VALUES(body)',
            [$slug, $d['meta_title'], $d['meta_description'], $d['h1'], $d['body']]);
        unset(self::$cache[$slug]);
    }

    /** Применяет переопределения из админки к SEO-объекту страницы. */
    public static function apply(string $slug, Seo $seo): array
    {
        $p = self::get($slug);
        if ($p['meta_title'] !== '') $seo->title = $p['meta_title'];
        if ($p['meta_description'] !== '') $seo->description = str_limit($p['meta_description'], 300);
        return $p;
    }
}

/** Статьи блога. */
final class Articles
{
    public static function published(int $limit = 100): array
    {
        return Db::all('SELECT * FROM articles WHERE is_published = 1 AND published_at <= NOW() ORDER BY published_at DESC, id DESC LIMIT ' . (int)$limit);
    }

    public static function find(string $slug): ?array
    {
        return Db::one('SELECT * FROM articles WHERE slug = ? AND is_published = 1 AND published_at <= NOW()', [$slug]);
    }
}

/** Адреса первого уровня, которые нельзя занять городом или страницей. */
function reserved_slugs(): array
{
    return array_merge(['catalog', 'product', 'goroda', 'blog', 'admin', 'api', 'assets', 'uploads', 'index', '404'], array_keys(STATIC_PAGES));
}
