<?php /** @var array $a @var ?int $id @var array $errors */
$v = fn($k) => e($a[$k] ?? '');
$err = fn($k) => isset($errors[$k]) ? '<div class="a-err">' . e($errors[$k]) . '</div>' : '';
?>
<?php if ($errors): ?><div class="a-flash a-flash--err">Исправьте ошибки — статья не сохранена.</div><?php endif; ?>
<form method="post" action="/admin/blog/save/" enctype="multipart/form-data" class="a-form">
  <?= Security::csrfField() ?>
  <input type="hidden" name="id" value="<?= (int)$id ?>">
  <div class="a-grid">
    <section class="a-card a-span2">
      <label>Заголовок (H1) *<input name="title" value="<?= $v('title') ?>" maxlength="200" required></label><?= $err('title') ?>
      <label>Адрес статьи <small>/blog/<b>…</b>/ — пусто = из заголовка</small><input name="slug" value="<?= $v('slug') ?>" pattern="[a-z0-9\-]*" maxlength="160"></label><?= $err('slug') ?>
      <label>Анонс <small>(1–2 предложения, показывается в списке статей и под заголовком)</small><textarea name="excerpt" rows="2" maxlength="400"><?= $v('excerpt') ?></textarea></label>
      <label>Текст статьи<textarea name="body" rows="24"><?= $v('body') ?></textarea></label>
      <?= View::partial('admin/_md_help') ?>
    </section>
    <section class="a-card">
      <h2>Публикация</h2>
      <label><input type="checkbox" name="is_published" value="1"<?= !empty($a['is_published']) ? ' checked' : '' ?>> Опубликована</label>
      <label>Дата публикации <small>(будущая дата — статья появится автоматически)</small><input type="datetime-local" name="published_at" value="<?= e(date('Y-m-d\TH:i', strtotime((string)($a['published_at'] ?? 'now')))) ?>"></label>
      <label><input type="checkbox" name="noindex" value="1"<?= !empty($a['noindex']) ? ' checked' : '' ?>> Закрыть от индексации (noindex)</label>
      <h2>Обложка</h2>
      <?php if (!empty($a['cover'])): ?>
        <img src="<?= e(Images::url($a['cover'], 400)) ?>" width="240" height="135" alt="" class="a-cover">
        <label><input type="checkbox" name="remove_cover" value="1"> Удалить обложку</label>
      <?php endif; ?>
      <label>Загрузить обложку <small>JPG/PNG/WebP, лучше 1600×900</small><input type="file" name="cover" accept="image/jpeg,image/png,image/webp"></label>
      <label>Alt обложки<input name="cover_alt" value="<?= $v('cover_alt') ?>" maxlength="200"></label>
    </section>
    <section class="a-card">
      <h2>SEO</h2>
      <p class="a-muted">Пустые поля — заголовок и анонс статьи.</p>
      <label>Meta title <small data-counter="meta_title">(до 70 символов)</small><input name="meta_title" value="<?= $v('meta_title') ?>" maxlength="200"></label>
      <label>Meta description <small data-counter="meta_description">(120–160 символов)</small><textarea name="meta_description" rows="3" maxlength="320"><?= $v('meta_description') ?></textarea></label>
    </section>
  </div>
  <div class="a-sticky">
    <button class="a-btn">Сохранить</button>
    <a class="a-btn a-btn--ghost" href="/admin/blog/">К списку</a>
    <?php if ($id && !empty($a['is_published'])): ?><a class="a-btn a-btn--ghost" href="/blog/<?= $v('slug') ?>/" target="_blank" rel="noopener">Открыть на сайте ↗</a><?php endif; ?>
    <?php if ($id): ?><button class="a-btn a-btn--danger" type="submit" form="del-art" data-confirm="Удалить статью?">Удалить</button><?php endif; ?>
  </div>
</form>
<?php if ($id): ?><form id="del-art" method="post" action="/admin/blog/<?= (int)$id ?>/delete/"><?= Security::csrfField() ?></form><?php endif; ?>
