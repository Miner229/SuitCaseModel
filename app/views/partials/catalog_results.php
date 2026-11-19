<?php /** @var array $products @var array $f @var string $basePath */ ?>
<div class="toolbar">
  <span class="toolbar__count">Найдено: <b data-count><?= count($products) ?></b> <?= plural(count($products), 'товар', 'товара', 'товаров') ?></span>
  <label class="toolbar__sort">Сортировка
    <select name="sort" form="filters" data-sort>
      <?php foreach (Catalog::SORTS as $k => $label): ?>
        <option value="<?= e($k) ?>"<?= $f['sort'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>
</div>
<?php
$chips = [];
foreach (['size', 'material', 'color', 'brand'] as $k) {
    foreach ($f[$k] as $v) {
        $chips[] = [$k === 'size' ? 'Размер ' . $v : $v, Catalog::filterUrl($basePath, $f, [$k => array_values(array_diff($f[$k], [$v]))])];
    }
}
if ($f['price_min'] !== null) $chips[] = ['от ' . price_fmt($f['price_min']), Catalog::filterUrl($basePath, $f, ['price_min' => null])];
if ($f['price_max'] !== null) $chips[] = ['до ' . price_fmt($f['price_max']), Catalog::filterUrl($basePath, $f, ['price_max' => null])];
if ($f['wheels'] !== null) $chips[] = [$f['wheels'] . ' колеса', Catalog::filterUrl($basePath, $f, ['wheels' => null])];
if ($f['expandable']) $chips[] = ['С расширением', Catalog::filterUrl($basePath, $f, ['expandable' => false])];
if ($f['in_stock']) $chips[] = ['В наличии', Catalog::filterUrl($basePath, $f, ['in_stock' => false])];
?>
<?php if ($chips): ?>
<div class="chips">
  <?php foreach ($chips as [$label, $url]): ?><a class="chip" href="<?= e($url) ?>" data-chip><?= e($label) ?> <span aria-hidden="true">×</span><span class="sr">убрать фильтр</span></a><?php endforeach; ?>
</div>
<?php endif; ?>
<?php if ($products): ?>
  <div class="grid grid--catalog">
    <?php foreach ($products as $i => $p) echo View::partial('partials/card', ['p' => $p, 'eager' => $i < 2]); ?>
  </div>
<?php else: ?>
  <div class="empty">
    <p><b>По выбранным параметрам ничего не найдено.</b></p>
    <p>Попробуйте <a href="<?= e($basePath) ?>">сбросить фильтры</a> или <button class="link" type="button" data-modal="callback">оставьте заявку</button> — подберём похожую модель или привезём под заказ.</p>
  </div>
<?php endif; ?>
