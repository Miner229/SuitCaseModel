<?php /** @var array $p @var ?int $id @var array $images @var array $categories @var array $errors */
$v = fn($k) => e($p[$k] ?? '');
$err = fn($k) => isset($errors[$k]) ? '<div class="a-err">' . e($errors[$k]) . '</div>' : '';
?>
<?php if ($errors): ?><div class="a-flash a-flash--err">Исправьте ошибки в форме — изменения не сохранены.</div><?php endif; ?>
<form method="post" action="/admin/products/save/" enctype="multipart/form-data" class="a-form">
  <?= Security::csrfField() ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="a-grid">
    <section class="a-card">
      <h2>Основное</h2>
      <label>Название *<input name="name" value="<?= $v('name') ?>" required maxlength="200"></label><?= $err('name') ?>
      <label>Адрес страницы (ЧПУ) <small>/product/<b>…</b>/ — пусто = из названия. При смене старый адрес автоматически перенаправит на новый</small><input name="slug" value="<?= $v('slug') ?>" pattern="[a-z0-9\-]*" maxlength="160"></label><?= $err('slug') ?>
      <div class="a-row">
        <label>Категория<select name="category_id"><option value="">—</option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"<?= (int)($p['category_id'] ?? 0) === (int)$c['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label>
        <label>Размер<select name="size_label"><?php foreach (Catalog::SIZES as $k => $l): ?><option value="<?= $k ?>"<?= ($p['size_label'] ?? '') === $k ? ' selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?></select></label>
      </div>
      <div class="a-row">
        <label>Бренд<input name="brand" value="<?= $v('brand') ?>" maxlength="80"></label>
        <label>Артикул<input name="sku" value="<?= $v('sku') ?>" maxlength="60"></label>
      </div>
      <div class="a-row">
        <label>Цена, ₸ *<input name="price" type="number" min="1" step="1" value="<?= $v('price') ?>" required></label><?= $err('price') ?>
        <label>Старая цена, ₸ <small>(для скидки)</small><input name="old_price" type="number" min="0" step="1" value="<?= $v('old_price') ?>"></label><?= $err('old_price') ?>
      </div>
      <div class="a-checks">
        <label><input type="checkbox" name="is_active" value="1"<?= !empty($p['is_active']) ? ' checked' : '' ?>> Показывать на сайте</label>
        <label><input type="checkbox" name="in_stock" value="1"<?= !empty($p['in_stock']) ? ' checked' : '' ?>> В наличии</label>
        <label><input type="checkbox" name="is_featured" value="1"<?= !empty($p['is_featured']) ? ' checked' : '' ?>> Популярный (выше в списке, на главной)</label>
        <label><input type="checkbox" name="noindex" value="1"<?= !empty($p['noindex']) ? ' checked' : '' ?>> Закрыть от индексации (noindex) — товар виден на сайте, но не попадает в поиск и sitemap</label>
      </div>
      <label>Порядок сортировки <small>(меньше — выше)</small><input name="sort" type="number" value="<?= $v('sort') ?: 0 ?>"></label>
    </section>

    <section class="a-card">
      <h2>Характеристики</h2>
      <div class="a-row a-row--3">
        <label>Высота, см<input name="height_cm" inputmode="decimal" value="<?= $v('height_cm') ?>"></label>
        <label>Ширина, см<input name="width_cm" inputmode="decimal" value="<?= $v('width_cm') ?>"></label>
        <label>Глубина, см<input name="depth_cm" inputmode="decimal" value="<?= $v('depth_cm') ?>"></label>
      </div>
      <?= $err('height_cm') . $err('width_cm') . $err('depth_cm') ?>
      <div class="a-row">
        <label>Объём, л<input name="volume_l" inputmode="decimal" value="<?= $v('volume_l') ?>"></label>
        <label>Вес, кг<input name="weight_kg" inputmode="decimal" value="<?= $v('weight_kg') ?>"></label>
      </div>
      <?= $err('volume_l') . $err('weight_kg') ?>
      <div class="a-row">
        <label>Материал<input name="material" value="<?= $v('material') ?>" list="materials" maxlength="60"></label>
        <label>Колёса<select name="wheels"><?php foreach ([4, 2, 8, 0] as $w): ?><option value="<?= $w ?>"<?= (int)($p['wheels'] ?? 4) === $w ? ' selected' : '' ?>><?= $w ?: 'без колёс' ?></option><?php endforeach; ?></select></label>
      </div>
      <datalist id="materials"><option>Поликарбонат</option><option>Полипропилен</option><option>ABS-пластик</option><option>Ткань (нейлон)</option><option>Ткань (полиэстер)</option><option>Алюминий</option></datalist>
      <div class="a-row">
        <label>Цвет<input name="color" value="<?= $v('color') ?>" maxlength="60"></label>
        <label>Цвет (образец)<input name="color_hex" type="color" value="<?= $v('color_hex') ?: '#555555' ?>"></label>
      </div>
      <div class="a-row">
        <label>Замок<input name="lock_type" value="<?= $v('lock_type') ?>" maxlength="60" placeholder="Кодовый TSA"></label>
        <label>Гарантия, мес.<input name="warranty_months" type="number" min="0" max="120" value="<?= $v('warranty_months') ?>"></label>
      </div>
      <label><input type="checkbox" name="expandable" value="1"<?= !empty($p['expandable']) ? ' checked' : '' ?>> Есть расширение объёма</label>
    </section>

    <section class="a-card a-span2">
      <h2>Описание</h2>
      <label>Краткое описание <small>(до 300 символов, показывается под названием)</small><textarea name="short_desc" rows="2" maxlength="300"><?= $v('short_desc') ?></textarea></label>
      <label>Полное описание <small>(пустая строка — новый абзац)</small><textarea name="description" rows="8"><?= $v('description') ?></textarea></label>
    </section>

    <section class="a-card a-span2">
      <h2>Фотографии</h2>
      <?php if ($images): ?>
      <div class="a-images">
        <?php foreach ($images as $im): ?>
          <div class="a-image">
            <img src="<?= e(Images::url($im['file'], 400)) ?>" width="140" height="140" alt="">
            <label>Порядок<input type="number" name="img_sort[<?= (int)$im['id'] ?>]" value="<?= (int)$im['sort'] ?>"></label>
            <label>Alt-текст<input name="img_alt[<?= (int)$im['id'] ?>]" value="<?= e($im['alt']) ?>" maxlength="200"></label>
            <button class="a-btn a-btn--sm a-btn--danger" type="submit" form="del-img-<?= (int)$im['id'] ?>" data-confirm="Удалить фото?">Удалить</button>
          </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <label>Добавить фото <small>JPG, PNG или WebP, до 10 МБ, от 200×200 px. Можно выбрать несколько. Фото автоматически сжимаются и конвертируются в WebP.</small>
        <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple data-preview></label>
      <div class="a-preview" data-preview-box></div>
    </section>

    <section class="a-card a-span2">
      <h2>SEO</h2>
      <p class="a-muted">Если оставить пустыми — заполнятся автоматически из названия, цены и характеристик.</p>
      <label>Title <small data-counter="meta_title">(рекомендуется до 70 символов)</small><input name="meta_title" value="<?= $v('meta_title') ?>" maxlength="200"></label>
      <label>Description <small data-counter="meta_description">(рекомендуется 120–160 символов)</small><textarea name="meta_description" rows="2" maxlength="320"><?= $v('meta_description') ?></textarea></label>
    </section>
  </div>
  <div class="a-sticky">
    <button class="a-btn">Сохранить</button>
    <a class="a-btn a-btn--ghost" href="/admin/products/">Отмена</a>
    <?php if ($id): ?>
      <a class="a-btn a-btn--ghost" href="/product/<?= $v('slug') ?>/" target="_blank" rel="noopener">Смотреть на сайте ↗</a>
      <button class="a-btn a-btn--ghost" type="submit" form="dup-form">Дублировать</button>
      <button class="a-btn a-btn--danger" type="submit" form="del-form" data-confirm="Удалить товар безвозвратно вместе с фото?">Удалить товар</button>
    <?php endif; ?>
  </div>
</form>
<?php if ($id): ?>
  <form id="del-form" method="post" action="/admin/products/<?= (int)$id ?>/delete/"><?= Security::csrfField() ?></form>
  <form id="dup-form" method="post" action="/admin/products/<?= (int)$id ?>/duplicate/"><?= Security::csrfField() ?></form>
  <?php foreach ($images as $im): ?><form id="del-img-<?= (int)$im['id'] ?>" method="post" action="/admin/images/<?= (int)$im['id'] ?>/delete/"><?= Security::csrfField() ?></form><?php endforeach; ?>
<?php endif; ?>
