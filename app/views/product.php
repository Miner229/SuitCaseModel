<?php /** @var array $p @var array $images @var array $related */
$discount = $p['old_price'] && $p['old_price'] > $p['price'] ? (int)round(100 - $p['price'] * 100 / $p['old_price']) : 0;
$s = Settings::all();
?>
<section class="wrap section section--top product" data-ecommerce='<?= e(json_encode(['id' => (string)$p['id'], 'name' => $p['name'], 'price' => (int)$p['price'], 'brand' => $p['brand'], 'category' => $p['category_name']], JSON_UNESCAPED_UNICODE)) ?>'>
  <div class="gallery" data-gallery>
    <div class="gallery__main">
      <?php if ($images): $im = $images[0]; ?>
        <img src="<?= e(Images::url($im['file'], 800)) ?>" srcset="<?= e(Images::srcset($im['file'])) ?>" sizes="(max-width: 900px) 100vw, 560px"
             width="<?= (int)($im['width'] ?: 1200) ?>" height="<?= (int)($im['height'] ?: 1200) ?>" alt="<?= e($im['alt'] ?: $p['name']) ?>" fetchpriority="high" data-gallery-main>
      <?php else: ?>
        <img src="/assets/img/no-photo.svg" width="600" height="600" alt="Фото скоро появится">
      <?php endif; ?>
      <?php if ($discount): ?><span class="badge badge--sale badge--abs">−<?= $discount ?>%</span><?php endif; ?>
    </div>
    <?php if (count($images) > 1): ?>
    <div class="gallery__thumbs">
      <?php foreach ($images as $i => $im): ?>
        <button type="button" class="gallery__thumb<?= $i === 0 ? ' is-active' : '' ?>" data-src="<?= e(Images::url($im['file'], 800)) ?>" data-srcset="<?= e(Images::srcset($im['file'])) ?>" aria-label="Фото <?= $i + 1 ?>">
          <img src="<?= e(Images::url($im['file'], 400)) ?>" width="80" height="80" alt="" loading="lazy">
        </button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <div class="product__info">
    <h1><?= e($p['name']) ?></h1>
    <div class="product__meta">
      <?php if ($p['sku']): ?><span>Артикул: <?= e($p['sku']) ?></span><?php endif; ?>
      <span class="<?= $p['in_stock'] ? 'stock' : 'stock stock--no' ?>"><?= $p['in_stock'] ? 'В наличии' : 'Под заказ, 5–10 дней' ?></span>
    </div>
    <?php if ($p['short_desc']): ?><p class="lead"><?= e($p['short_desc']) ?></p><?php endif; ?>

    <div class="key-specs">
      <?php if ($p['width_cm']): ?><div><span>Габариты</span><b><?= e(num_fmt($p['height_cm'])) ?>×<?= e(num_fmt($p['width_cm'])) ?>×<?= e(num_fmt($p['depth_cm'])) ?> см</b></div><?php endif; ?>
      <?php if ($p['volume_l']): ?><div><span>Объём</span><b><?= e(num_fmt($p['volume_l'])) ?> л</b></div><?php endif; ?>
      <?php if ($p['weight_kg']): ?><div><span>Вес</span><b><?= e(num_fmt($p['weight_kg'])) ?> кг</b></div><?php endif; ?>
      <div><span>Размер</span><b><?= e($p['size_label']) ?></b></div>
    </div>

    <div class="buy">
      <div class="price price--lg">
        <span class="price__now"><?= e(price_fmt($p['price'])) ?></span>
        <?php if ($discount): ?><s class="price__old"><?= e(price_fmt($p['old_price'])) ?></s><?php endif; ?>
      </div>
      <div class="buy__btns">
        <button class="btn btn--lg" type="button" data-modal="order" data-product-id="<?= (int)$p['id'] ?>" data-product-name="<?= e($p['name'] . ' — ' . price_fmt($p['price'])) ?>" data-goal="open_order">Заказать</button>
        <a class="btn btn--lg btn--wa" href="https://wa.me/<?= e(preg_replace('/\D/', '', $s['whatsapp'])) ?>?text=<?= rawurlencode('Здравствуйте! Интересует ' . $p['name'] . ' ' . base_url('/product/' . $p['slug'] . '/')) ?>" target="_blank" rel="noopener" data-goal="click_whatsapp">Спросить в WhatsApp</a>
      </div>
      <ul class="buy__notes">
        <li>Доставка по Алматы и Астане — 1 день, по Казахстану — 2–5 дней</li>
        <li>Оплата Kaspi, картой или наличными при получении</li>
        <li>Гарантия <?= (int)$p['warranty_months'] ?> <?= plural((int)$p['warranty_months'], 'месяц', 'месяца', 'месяцев') ?>, обмен 14 дней</li>
      </ul>
    </div>
  </div>
</section>

<section class="wrap section product__details">
  <div>
    <h2>Характеристики</h2>
    <table class="specs">
      <?php foreach (Catalog::specs($p) as [$k, $v]): ?><tr><th scope="row"><?= e($k) ?></th><td><?= e($v) ?></td></tr><?php endforeach; ?>
    </table>
  </div>
  <div>
    <h2>Описание</h2>
    <div class="text"><?php foreach (preg_split('/\R{2,}/', trim((string)$p['description'])) as $para) if ($para !== '') echo '<p>' . nl2br(e($para)) . '</p>'; ?></div>
    <?php if ($p['size_label'] === 'S'): ?>
      <div class="notice">✈️ Подходит под нормы ручной клади большинства авиакомпаний (55×40×20 см). Перед полётом уточните правила своего тарифа.</div>
    <?php endif; ?>
  </div>
</section>

<?php if ($related): ?>
<section class="wrap section">
  <h2>Похожие модели</h2>
  <div class="grid"><?php foreach ($related as $r) echo View::partial('partials/card', ['p' => $r]); ?></div>
</section>
<?php endif; ?>
