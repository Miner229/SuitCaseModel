<?php
declare(strict_types=1);

final class Catalog
{
    public const SIZES = [
        'S' => 'S — ручная кладь (до 55 см)',
        'M' => 'M — средний (60–68 см)',
        'L' => 'L — большой (70–77 см)',
        'XL' => 'XL — очень большой (от 78 см)',
        'SET' => 'Набор',
    ];

    public const SORTS = [
        'popular' => 'По популярности',
        'price_asc' => 'Сначала дешевле',
        'price_desc' => 'Сначала дороже',
        'new' => 'Новинки',
    ];

    public static function categories(): array
    {
        return Db::all('SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.is_active = 1) AS cnt FROM categories c ORDER BY sort, id');
    }

    public static function category(string $slug): ?array
    {
        return Db::one('SELECT * FROM categories WHERE slug = ?', [$slug]);
    }

    /** Разбор и валидация GET-фильтров. Любые значения проверяются по белому списку. */
    public static function parseFilters(array $q): array
    {
        $f = [
            'size' => [], 'material' => [], 'color' => [], 'brand' => [],
            'price_min' => null, 'price_max' => null, 'wheels' => null, 'expandable' => false, 'in_stock' => false,
            'sort' => 'popular',
        ];
        $opts = self::filterOptions();
        foreach (['size', 'material', 'color', 'brand'] as $k) {
            $vals = $q[$k] ?? [];
            $vals = is_array($vals) ? $vals : [$vals];
            $f[$k] = array_values(array_intersect(array_map('strval', $vals), $opts[$k]));
        }
        foreach (['price_min', 'price_max'] as $k) {
            if (isset($q[$k]) && is_scalar($q[$k]) && ctype_digit((string)$q[$k])) {
                $f[$k] = (int)$q[$k];
            }
        }
        if (isset($q['wheels']) && in_array((string)$q['wheels'], ['2', '4'], true)) {
            $f['wheels'] = (int)$q['wheels'];
        }
        $f['expandable'] = !empty($q['expandable']);
        $f['in_stock'] = !empty($q['in_stock']);
        if (isset($q['sort']) && is_string($q['sort']) && isset(self::SORTS[$q['sort']])) {
            $f['sort'] = $q['sort'];
        }
        return $f;
    }

    public static function hasFilters(array $f): bool
    {
        return $f['size'] || $f['material'] || $f['color'] || $f['brand'] || $f['price_min'] !== null
            || $f['price_max'] !== null || $f['wheels'] !== null || $f['expandable'] || $f['in_stock'] || $f['sort'] !== 'popular';
    }

    public static function filterOptions(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $col = fn(string $c) => array_map('strval', array_column(
            Db::all("SELECT DISTINCT $c AS v FROM products WHERE is_active = 1 AND $c <> '' ORDER BY $c"), 'v'
        ));
        $range = Db::one('SELECT MIN(price) mn, MAX(price) mx FROM products WHERE is_active = 1') ?: ['mn' => 0, 'mx' => 0];
        $sizes = $col('size_label');
        $order = array_keys(self::SIZES);
        usort($sizes, fn($a, $b) => array_search($a, $order, true) <=> array_search($b, $order, true));
        return $cache = [
            'size' => $sizes,
            'material' => $col('material'),
            'color' => $col('color'),
            'brand' => $col('brand'),
            'price_min' => (int)$range['mn'],
            'price_max' => (int)$range['mx'],
        ];
    }

    public static function products(array $f, ?int $categoryId = null, int $limit = 200): array
    {
        $where = ['p.is_active = 1'];
        $params = [];
        if ($categoryId) {
            $where[] = 'p.category_id = ?';
            $params[] = $categoryId;
        }
        $map = ['size' => 'size_label', 'material' => 'material', 'color' => 'color', 'brand' => 'brand'];
        foreach ($map as $k => $col) {
            if (!empty($f[$k])) {
                $where[] = "p.$col IN (" . implode(',', array_fill(0, count($f[$k]), '?')) . ')';
                array_push($params, ...$f[$k]);
            }
        }
        if (($f['price_min'] ?? null) !== null) {
            $where[] = 'p.price >= ?';
            $params[] = $f['price_min'];
        }
        if (($f['price_max'] ?? null) !== null) {
            $where[] = 'p.price <= ?';
            $params[] = $f['price_max'];
        }
        if (($f['wheels'] ?? null) !== null) {
            $where[] = 'p.wheels = ?';
            $params[] = $f['wheels'];
        }
        if (!empty($f['expandable'])) {
            $where[] = 'p.expandable = 1';
        }
        if (!empty($f['in_stock'])) {
            $where[] = 'p.in_stock = 1';
        }
        $order = match ($f['sort'] ?? 'popular') {
            'price_asc' => 'p.price ASC',
            'price_desc' => 'p.price DESC',
            'new' => 'p.created_at DESC, p.id DESC',
            default => 'p.in_stock DESC, p.is_featured DESC, p.sort ASC, p.id ASC',
        };
        $sql = 'SELECT p.*, c.slug AS category_slug, c.name AS category_name,
                (SELECT file FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort, i.id LIMIT 1) AS image
                FROM products p LEFT JOIN categories c ON c.id = p.category_id
                WHERE ' . implode(' AND ', $where) . " ORDER BY $order LIMIT " . (int)$limit;
        return Db::all($sql, $params);
    }

    public static function featured(int $limit = 8): array
    {
        return self::products(['sort' => 'popular'], null, $limit);
    }

    public static function product(string $slug): ?array
    {
        return Db::one('SELECT p.*, c.slug AS category_slug, c.name AS category_name FROM products p
            LEFT JOIN categories c ON c.id = p.category_id WHERE p.slug = ? AND p.is_active = 1', [$slug]);
    }

    public static function images(int $productId): array
    {
        return Db::all('SELECT * FROM product_images WHERE product_id = ? ORDER BY sort, id', [$productId]);
    }

    public static function related(array $p, int $limit = 4): array
    {
        return Db::all('SELECT p.*, (SELECT file FROM product_images i WHERE i.product_id = p.id ORDER BY i.sort, i.id LIMIT 1) AS image
            FROM products p WHERE p.is_active = 1 AND p.id <> ? ORDER BY (p.category_id <=> ?) DESC, ABS(CAST(p.price AS SIGNED) - ?) ASC LIMIT ' . (int)$limit,
            [$p['id'], $p['category_id'], $p['price']]);
    }

    /** Характеристики для таблицы на карточке и для микроразметки. */
    public static function specs(array $p): array
    {
        $s = [];
        if ($p['brand']) $s[] = ['Бренд', $p['brand']];
        if ($p['sku']) $s[] = ['Артикул', $p['sku']];
        $s[] = ['Размер', self::SIZES[$p['size_label']] ?? $p['size_label']];
        if ($p['width_cm']) {
            $s[] = ['Габариты (В × Ш × Г)', num_fmt($p['height_cm']) . ' × ' . num_fmt($p['width_cm']) . ' × ' . num_fmt($p['depth_cm']) . ' см'];
        }
        if ($p['volume_l']) $s[] = ['Объём', num_fmt($p['volume_l']) . ' л'];
        if ($p['weight_kg']) $s[] = ['Вес', num_fmt($p['weight_kg']) . ' кг'];
        if ($p['material']) $s[] = ['Материал корпуса', $p['material']];
        if ($p['color']) $s[] = ['Цвет', $p['color']];
        $s[] = ['Колёса', $p['wheels'] == 4 ? '4 (вращение 360°)' : (string)$p['wheels']];
        if ($p['lock_type']) $s[] = ['Замок', $p['lock_type']];
        $s[] = ['Расширение объёма', $p['expandable'] ? 'Да' : 'Нет'];
        $s[] = ['Гарантия', $p['warranty_months'] . ' ' . plural((int)$p['warranty_months'], 'месяц', 'месяца', 'месяцев')];
        return $s;
    }

    public static function cities(bool $onlyActive = true): array
    {
        return Db::all('SELECT * FROM cities' . ($onlyActive ? ' WHERE is_active = 1' : '') . ' ORDER BY sort, name');
    }

    public static function city(string $slug): ?array
    {
        return Db::one('SELECT * FROM cities WHERE slug = ? AND is_active = 1', [$slug]);
    }

    /** Ссылка на каталог с изменённым набором фильтров (для чипсов «сбросить»). */
    public static function filterUrl(string $base, array $f, array $override = []): string
    {
        $q = array_merge($f, $override);
        $out = [];
        foreach (['size', 'material', 'color', 'brand'] as $k) {
            if (!empty($q[$k])) $out[$k] = array_values($q[$k]);
        }
        foreach (['price_min', 'price_max', 'wheels'] as $k) {
            if (($q[$k] ?? null) !== null) $out[$k] = $q[$k];
        }
        if (!empty($q['expandable'])) $out['expandable'] = 1;
        if (!empty($q['in_stock'])) $out['in_stock'] = 1;
        if (($q['sort'] ?? 'popular') !== 'popular') $out['sort'] = $q['sort'];
        $qs = http_build_query($out);
        return $base . ($qs ? '?' . $qs : '');
    }
}
