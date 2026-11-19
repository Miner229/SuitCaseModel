<?php /** @var array $row @var string $type */ $v = fn($k) => e($row[$k] ?? ''); ?>
<form method="post" class="a-form a-card">
  <?= Security::csrfField() ?>
  <p class="a-muted">Страница: <a href="<?= $type === 'city' ? '/' . $v('slug') . '/' : '/catalog/' . $v('slug') . '/' ?>" target="_blank" rel="noopener"><?= $type === 'city' ? '/' . $v('slug') . '/' : '/catalog/' . $v('slug') . '/' ?> ↗</a></p>
  <label>Название<input name="name" value="<?= $v('name') ?>"></label>
  <?php if ($type === 'city'): ?>
    <label>Название с предлогом <small>«в Алматы», «в Астане» — используется в заголовках</small><input name="name_in" value="<?= $v('name_in') ?>"></label>
    <label>Срок доставки<input name="delivery_days" value="<?= $v('delivery_days') ?>"></label>
    <label>Текст о доставке<textarea name="delivery_text" rows="4"><?= $v('delivery_text') ?></textarea></label>
    <label><input type="checkbox" name="is_active" value="1"<?= $row['is_active'] ? ' checked' : '' ?>> Страница включена</label>
  <?php else: ?>
    <label>Заголовок H1<input name="h1" value="<?= $v('h1') ?>"></label>
  <?php endif; ?>
  <label>Вводный текст<textarea name="intro" rows="3"><?= $v('intro') ?></textarea></label>
  <label>Meta title <small data-counter="meta_title">(до 70 символов)</small><input name="meta_title" value="<?= $v('meta_title') ?>" maxlength="200"></label>
  <label>Meta description <small data-counter="meta_description">(120–160 символов)</small><textarea name="meta_description" rows="2" maxlength="320"><?= $v('meta_description') ?></textarea></label>
  <label>Порядок<input name="sort" type="number" value="<?= (int)$row['sort'] ?>"></label>
  <div class="a-actions"><button class="a-btn">Сохранить</button><a class="a-btn a-btn--ghost" href="/admin/seo/">Назад</a></div>
</form>
