<?php /** @var array $categories @var array $cities */ ?>
<p class="a-muted">Здесь редактируются тексты и метатеги разделов каталога и региональных посадочных страниц. Пустые поля заполняются шаблоном автоматически.</p>
<div class="a-grid">
<section class="a-card"><h2>Категории</h2>
  <table class="a-table"><?php foreach ($categories as $c): ?><tr><td><a href="/admin/seo/<?= (int)$c['id'] ?>/category/"><?= e($c['name']) ?></a></td><td class="a-muted">/catalog/<?= e($c['slug']) ?>/</td><td><?= $c['meta_title'] ? '✓' : '<span class="a-muted">шаблон</span>' ?></td></tr><?php endforeach; ?></table>
</section>
<section class="a-card"><h2>Города</h2>
  <table class="a-table"><?php foreach ($cities as $c): ?><tr<?= $c['is_active'] ? '' : ' class="a-off"' ?>><td><a href="/admin/seo/<?= (int)$c['id'] ?>/city/"><?= e($c['name']) ?></a></td><td class="a-muted">/<?= e($c['slug']) ?>/</td><td><?= $c['is_active'] ? 'вкл' : 'выкл' ?></td></tr><?php endforeach; ?></table>
</section>
</div>
