<?php /** @var array $products @var string $q */ ?>
<div class="a-actions">
  <a class="a-btn" href="/admin/products/new/">+ Добавить товар</a>
  <form method="get" class="a-search"><input type="search" name="q" value="<?= e($q) ?>" placeholder="Поиск по названию или артикулу"><button class="a-btn a-btn--ghost">Найти</button></form>
</div>
<p class="a-muted">Цену, наличие и видимость можно изменить прямо в списке — нажмите «Сохранить» в строке.</p>
<div class="a-table-wrap"><table class="a-table a-table--cards">
  <thead><tr><th></th><th>Товар</th><th>Категория</th><th>Цена, ₸</th><th>Наличие</th><th>На сайте</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($products as $p): ?>
    <tr<?= $p['is_active'] ? '' : ' class="a-off"' ?>>
      <td><?php if ($p['image']): ?><img src="<?= e(Images::url($p['image'], 400)) ?>" width="56" height="56" alt="" loading="lazy" class="a-thumb"><?php else: ?><span class="a-nophoto">нет фото</span><?php endif; ?></td>
      <td><a href="/admin/products/<?= (int)$p['id'] ?>/"><b><?= e($p['name']) ?></b></a><br><span class="a-muted"><?= e($p['sku']) ?> · <?= e($p['size_label']) ?> · фото: <?= (int)$p['images_cnt'] ?></span></td>
      <td><?= e($p['category_name'] ?? '—') ?></td>
      <td colspan="3">
        <form method="post" action="/admin/products/<?= (int)$p['id'] ?>/quick/" class="a-quick">
          <?= Security::csrfField() ?>
          <input name="price" type="number" min="1" step="1" value="<?= (int)$p['price'] ?>" aria-label="Цена" required>
          <label><input type="checkbox" name="in_stock" value="1"<?= $p['in_stock'] ? ' checked' : '' ?>> в наличии</label>
          <label><input type="checkbox" name="is_active" value="1"<?= $p['is_active'] ? ' checked' : '' ?>> показывать</label>
          <button class="a-btn a-btn--sm">Сохранить</button>
        </form>
      </td>
      <td class="nowrap">
        <a class="a-btn a-btn--sm a-btn--ghost" href="/admin/products/<?= (int)$p['id'] ?>/">Изменить</a>
        <?php if ($p['is_active']): ?><a class="a-btn a-btn--sm a-btn--ghost" href="/product/<?= e($p['slug']) ?>/" target="_blank" rel="noopener">↗</a><?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
