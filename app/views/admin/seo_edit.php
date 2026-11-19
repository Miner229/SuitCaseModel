<?php /** @var array $row @var string $type @var int $id @var array $errors */
$v = fn($k) => e($row[$k] ?? '');
$prefix = $type === 'city' ? '/' : '/catalog/';
?>
<?php foreach ($errors as $er): ?><div class="a-flash a-flash--err"><?= e($er) ?></div><?php endforeach; ?>
<form method="post" class="a-form a-card">
  <?= Security::csrfField() ?>
  <?php if ($id): ?><p class="a-muted">Страница: <a href="<?= e($prefix . ($row['slug'] ?? '') . '/') ?>" target="_blank" rel="noopener"><?= e($prefix . ($row['slug'] ?? '') . '/') ?> ↗</a></p><?php endif; ?>
  <div class="a-row">
    <label>Название *<input name="name" value="<?= $v('name') ?>" required></label>
    <label>Адрес <small><?= e($prefix) ?><b>…</b>/ — латиницей; пусто = из названия. При смене старый адрес автоматически перенаправит на новый.</small><input name="slug" value="<?= $v('slug') ?>" pattern="[a-z0-9\-]*" maxlength="80"></label>
  </div>
  <?php if ($type === 'city'): ?>
    <div class="a-row">
      <label>Название с предлогом <small>«в Алматы», «в Астане» — для заголовков</small><input name="name_in" value="<?= $v('name_in') ?>"></label>
      <label>Срок доставки<input name="delivery_days" value="<?= $v('delivery_days') ?>" placeholder="2–4 дня"></label>
    </div>
    <label>Текст о доставке<textarea name="delivery_text" rows="3"><?= $v('delivery_text') ?></textarea></label>
    <label><input type="checkbox" name="is_active" value="1"<?= !empty($row['is_active']) ? ' checked' : '' ?>> Страница включена</label>
  <?php else: ?>
    <label>Заголовок H1<input name="h1" value="<?= $v('h1') ?>"></label>
  <?php endif; ?>
  <label>Вводный текст <small>(под заголовком)</small><textarea name="intro" rows="3"><?= $v('intro') ?></textarea></label>
  <label>Meta title <small data-counter="meta_title">(до 70 символов)</small><input name="meta_title" value="<?= $v('meta_title') ?>" maxlength="200"></label>
  <label>Meta description <small data-counter="meta_description">(120–160 символов)</small><textarea name="meta_description" rows="2" maxlength="320"><?= $v('meta_description') ?></textarea></label>
  <label>SEO-текст внизу страницы<textarea name="seo_text" rows="12"><?= $v('seo_text') ?></textarea></label>
  <?= View::partial('admin/_md_help') ?>
  <label>Порядок <small>(меньше — выше)</small><input name="sort" type="number" value="<?= (int)($row['sort'] ?? 0) ?>"></label>
  <div class="a-actions">
    <button class="a-btn"><?= $id ? 'Сохранить' : 'Добавить' ?></button><a class="a-btn a-btn--ghost" href="/admin/seo/">Назад</a>
    <?php if ($id): ?><button class="a-btn a-btn--danger" type="submit" form="del-seo" data-confirm="Удалить? Старый адрес будет перенаправлять <?= $type === 'city' ? 'на список городов' : 'в общий каталог' ?>.">Удалить</button><?php endif; ?>
  </div>
</form>
<?php if ($id): ?><form id="del-seo" method="post" action="/admin/seo/<?= (int)$id ?>/<?= e($type) ?>/delete/"><?= Security::csrfField() ?></form><?php endif; ?>
