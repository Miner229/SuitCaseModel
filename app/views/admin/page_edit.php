<?php /** @var string $slug @var array $page @var string $mode */ $v = fn($k) => e($page[$k] ?? ''); $url = $slug === 'home' ? '/' : '/' . $slug . '/'; ?>
<form method="post" class="a-form a-card">
  <?= Security::csrfField() ?>
  <p class="a-muted">Страница: <a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e($url) ?> ↗</a>. Пустые поля — используется стандартный вариант.</p>
  <label>Meta title <small data-counter="meta_title">(до 70 символов)</small><input name="meta_title" value="<?= $v('meta_title') ?>" maxlength="200"></label>
  <label>Meta description <small data-counter="meta_description">(120–160 символов)</small><textarea name="meta_description" rows="2" maxlength="320"><?= $v('meta_description') ?></textarea></label>
  <label>Заголовок H1<input name="h1" value="<?= $v('h1') ?>" maxlength="200"></label>
  <label><?= $mode === 'replace' ? 'Текст страницы' : 'SEO-текст внизу страницы' ?>
    <small><?= $mode === 'replace' ? 'если заполнить — полностью заменит стандартный текст страницы' : 'выводится под основным содержимым страницы' ?></small>
    <textarea name="body" rows="18"><?= $v('body') ?></textarea></label>
  <?= View::partial('admin/_md_help') ?>
  <div class="a-actions"><button class="a-btn">Сохранить</button><a class="a-btn a-btn--ghost" href="/admin/pages/">Назад</a></div>
</form>
