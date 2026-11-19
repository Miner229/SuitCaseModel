<?php /** @var array $categories @var array $cities */ ?>
<p class="a-muted">Разделы каталога и региональные посадочные страницы: адреса, тексты, метатеги. Пустые поля заполняются шаблоном автоматически.
Главная, «Доставка», «Контакты» и другие страницы — в разделе <a href="/admin/pages/">Страницы</a>.</p>
<div class="a-grid">
<section class="a-card"><div class="a-actions" style="justify-content:space-between"><h2 style="margin:0">Категории</h2><a class="a-btn a-btn--sm" href="/admin/seo/new/category/">+ Добавить</a></div>
  <table class="a-table"><?php foreach ($categories as $c): ?><tr><td><a href="/admin/seo/<?= (int)$c['id'] ?>/category/"><?= e($c['name']) ?></a></td><td class="a-muted">/catalog/<?= e($c['slug']) ?>/</td><td class="a-muted"><?= (int)$c['cnt'] ?> тов.</td><td><?= $c['meta_title'] ? '✓' : '<span class="a-muted">шаблон</span>' ?><?= trim((string)$c['seo_text']) !== '' ? ' · текст' : '' ?></td></tr><?php endforeach; ?></table>
</section>
<section class="a-card"><div class="a-actions" style="justify-content:space-between"><h2 style="margin:0">Города</h2><a class="a-btn a-btn--sm" href="/admin/seo/new/city/">+ Добавить</a></div>
  <table class="a-table"><?php foreach ($cities as $c): ?><tr<?= $c['is_active'] ? '' : ' class="a-off"' ?>><td><a href="/admin/seo/<?= (int)$c['id'] ?>/city/"><?= e($c['name']) ?></a></td><td class="a-muted">/<?= e($c['slug']) ?>/</td><td><?= $c['is_active'] ? 'вкл' : 'выкл' ?><?= trim((string)$c['seo_text']) !== '' ? ' · текст' : '' ?></td></tr><?php endforeach; ?></table>
</section>
</div>
