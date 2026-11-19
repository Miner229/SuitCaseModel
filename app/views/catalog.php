<?php /** @var ?array $cat @var array $f @var array $options @var array $categories @var string $basePath */ ?>
<section class="wrap section section--top">
  <h1><?= e($cat ? ($cat['h1'] ?: $cat['name']) : 'Каталог чемоданов') ?></h1>
  <?php if ($cat && $cat['intro']): ?><p class="lead"><?= e($cat['intro']) ?></p><?php endif; ?>

  <div class="cat-tabs" role="list">
    <a role="listitem" href="/catalog/"<?= !$cat ? ' aria-current="page"' : '' ?>>Все</a>
    <?php foreach ($categories as $c): ?>
      <a role="listitem" href="/catalog/<?= e($c['slug']) ?>/"<?= $cat && $cat['id'] == $c['id'] ? ' aria-current="page"' : '' ?>><?= e($c['name']) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="catalog">
    <button class="btn btn--ghost filters-toggle" type="button" aria-controls="filters" aria-expanded="false">Фильтры</button>
    <form class="filters" id="filters" method="get" action="<?= e($basePath) ?>" data-filters>
      <div class="filters__head"><b>Фильтры</b><button type="button" class="filters__close" aria-label="Закрыть фильтры">×</button></div>
      <?php if (!$cat || count($options['size']) > 1): ?>
      <fieldset>
        <legend>Размер</legend>
        <?php foreach ($options['size'] as $v): ?>
          <label class="check"><input type="checkbox" name="size[]" value="<?= e($v) ?>"<?= in_array($v, $f['size'], true) ? ' checked' : '' ?>><span><?= e(Catalog::SIZES[$v] ?? $v) ?></span></label>
        <?php endforeach; ?>
      </fieldset>
      <?php endif; ?>
      <fieldset>
        <legend>Цена, ₸</legend>
        <div class="range">
          <input type="number" name="price_min" min="0" step="1000" inputmode="numeric" placeholder="от <?= (int)$options['price_min'] ?>" value="<?= e($f['price_min'] ?? '') ?>" aria-label="Цена от">
          <input type="number" name="price_max" min="0" step="1000" inputmode="numeric" placeholder="до <?= (int)$options['price_max'] ?>" value="<?= e($f['price_max'] ?? '') ?>" aria-label="Цена до">
        </div>
      </fieldset>
      <fieldset>
        <legend>Материал</legend>
        <?php foreach ($options['material'] as $v): ?>
          <label class="check"><input type="checkbox" name="material[]" value="<?= e($v) ?>"<?= in_array($v, $f['material'], true) ? ' checked' : '' ?>><span><?= e($v) ?></span></label>
        <?php endforeach; ?>
      </fieldset>
      <fieldset>
        <legend>Цвет</legend>
        <?php foreach ($options['color'] as $v): ?>
          <label class="check"><input type="checkbox" name="color[]" value="<?= e($v) ?>"<?= in_array($v, $f['color'], true) ? ' checked' : '' ?>><span><?= e($v) ?></span></label>
        <?php endforeach; ?>
      </fieldset>
      <fieldset>
        <legend>Бренд</legend>
        <?php foreach ($options['brand'] as $v): ?>
          <label class="check"><input type="checkbox" name="brand[]" value="<?= e($v) ?>"<?= in_array($v, $f['brand'], true) ? ' checked' : '' ?>><span><?= e($v) ?></span></label>
        <?php endforeach; ?>
      </fieldset>
      <fieldset>
        <legend>Колёса</legend>
        <label class="check"><input type="radio" name="wheels" value=""<?= $f['wheels'] === null ? ' checked' : '' ?>><span>Любые</span></label>
        <label class="check"><input type="radio" name="wheels" value="4"<?= $f['wheels'] === 4 ? ' checked' : '' ?>><span>4 колеса (360°)</span></label>
        <label class="check"><input type="radio" name="wheels" value="2"<?= $f['wheels'] === 2 ? ' checked' : '' ?>><span>2 колеса</span></label>
      </fieldset>
      <fieldset>
        <legend>Дополнительно</legend>
        <label class="check"><input type="checkbox" name="expandable" value="1"<?= $f['expandable'] ? ' checked' : '' ?>><span>С расширением объёма</span></label>
        <label class="check"><input type="checkbox" name="in_stock" value="1"<?= $f['in_stock'] ? ' checked' : '' ?>><span>Только в наличии</span></label>
      </fieldset>
      <div class="filters__btns">
        <button class="btn btn--block" type="submit">Показать</button>
        <a class="btn btn--ghost btn--block" href="<?= e($basePath) ?>">Сбросить</a>
      </div>
    </form>

    <div class="catalog__main" id="catalog-results" aria-live="polite">
      <?= View::partial('partials/catalog_results', get_defined_vars()) ?>
    </div>
  </div>

  <?php if ($cat): ?>
  <div class="seo-text">
    <h2><?= e($cat['name']) ?>: как выбрать</h2>
    <p>Все модели раздела «<?= e($cat['name']) ?>» мы измеряем вместе с колёсами и ручками, поэтому габариты в карточках соответствуют тому, что измерят на стойке регистрации. Если сомневаетесь в выборе — оставьте заявку, и менеджер подберёт модель под вашу авиакомпанию и бюджет.</p>
  </div>
  <?php endif; ?>
</section>
