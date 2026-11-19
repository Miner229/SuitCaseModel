<?php
declare(strict_types=1);

/** Сбор метаданных страницы: title, description, canonical, robots, Open Graph, JSON-LD, хлебные крошки. */
final class Seo
{
    public string $title = '';
    public string $description = '';
    public string $canonical = '';
    public string $robots = 'index, follow';
    public string $ogImage = '';
    public string $ogType = 'website';
    /** @var array<int,array{0:string,1:string}> [название, url] */
    public array $breadcrumbs = [];
    /** @var array<int,array> */
    public array $jsonLd = [];
    public ?array $prevNext = null;

    public function __construct(string $title, string $description, string $path)
    {
        $this->title = $title;
        $this->description = str_limit($description, 300);
        $this->canonical = base_url($path);
    }

    public function crumbs(array $items): self
    {
        $this->breadcrumbs = array_merge([['Главная', '/']], $items);
        $list = [];
        foreach ($this->breadcrumbs as $i => [$name, $url]) {
            $list[] = ['@type' => 'ListItem', 'position' => $i + 1, 'name' => $name, 'item' => base_url($url)];
        }
        $this->jsonLd[] = ['@context' => 'https://schema.org', '@type' => 'BreadcrumbList', 'itemListElement' => $list];
        return $this;
    }

    public static function organization(): array
    {
        $s = Settings::all();
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Store',
            '@id' => base_url('/#store'),
            'name' => $s['site_name'],
            'description' => $s['site_tagline'],
            'url' => base_url('/'),
            'logo' => base_url('/assets/img/logo.svg'),
            'image' => base_url('/assets/img/og.jpg'),
            'telephone' => $s['phone'],
            'email' => $s['email'],
            'priceRange' => '₸₸',
            'currenciesAccepted' => 'KZT',
            'address' => [
                '@type' => 'PostalAddress',
                'streetAddress' => $s['address'],
                'addressLocality' => $s['city_default'],
                'addressCountry' => 'KZ',
            ],
            'areaServed' => ['@type' => 'Country', 'name' => 'Казахстан'],
            'openingHours' => 'Mo-Su 10:00-20:00',
        ];
    }

    public static function product(array $p, array $images): array
    {
        $imgs = array_map(fn($i) => base_url(Images::url($i['file'], 1200)), $images);
        $props = [];
        foreach (Catalog::specs($p) as [$label, $val]) {
            $props[] = ['@type' => 'PropertyValue', 'name' => $label, 'value' => $val];
        }
        $data = [
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => $p['name'],
            'sku' => $p['sku'] ?: ('ID' . $p['id']),
            'description' => str_limit($p['description'] ?: $p['short_desc'], 500),
            'image' => $imgs ?: [base_url('/assets/img/og.jpg')],
            'brand' => ['@type' => 'Brand', 'name' => $p['brand'] ?: Settings::get('site_name')],
            'color' => $p['color'],
            'material' => $p['material'],
            'additionalProperty' => $props,
            'offers' => [
                '@type' => 'Offer',
                'url' => base_url('/product/' . $p['slug'] . '/'),
                'priceCurrency' => 'KZT',
                'price' => (string)(int)$p['price'],
                'priceValidUntil' => date('Y-12-31'),
                'availability' => $p['in_stock'] ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                'itemCondition' => 'https://schema.org/NewCondition',
                'seller' => ['@id' => base_url('/#store')],
                'areaServed' => 'KZ',
            ],
        ];
        if ($p['weight_kg']) {
            $data['weight'] = ['@type' => 'QuantitativeValue', 'value' => (float)$p['weight_kg'], 'unitCode' => 'KGM'];
        }
        if ($p['width_cm']) {
            $data['width'] = ['@type' => 'QuantitativeValue', 'value' => (float)$p['width_cm'], 'unitCode' => 'CMT'];
            $data['height'] = ['@type' => 'QuantitativeValue', 'value' => (float)$p['height_cm'], 'unitCode' => 'CMT'];
            $data['depth'] = ['@type' => 'QuantitativeValue', 'value' => (float)$p['depth_cm'], 'unitCode' => 'CMT'];
        }
        return $data;
    }

    public static function faq(array $qa): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn($x) => [
                '@type' => 'Question',
                'name' => $x[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $x[1]],
            ], $qa),
        ];
    }

    public static function itemList(array $products): array
    {
        $items = [];
        foreach (array_values($products) as $i => $p) {
            $items[] = ['@type' => 'ListItem', 'position' => $i + 1, 'url' => base_url('/product/' . $p['slug'] . '/'), 'name' => $p['name']];
        }
        return ['@context' => 'https://schema.org', '@type' => 'ItemList', 'itemListElement' => $items];
    }

    public static function ldJson(array $data): string
    {
        // JSON_HEX_TAG не даёт закрыть </script> из данных
        return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
    }
}
