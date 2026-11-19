<?php /** @var array $articles */ ?>
<div class="a-actions"><a class="a-btn" href="/admin/blog/new/">+ Новая статья</a><a class="a-btn a-btn--ghost" href="/blog/" target="_blank" rel="noopener">Блог на сайте ↗</a></div>
<?php if (!$articles): ?><p class="a-muted">Статей пока нет.</p><?php else: ?>
<div class="a-table-wrap"><table class="a-table">
  <thead><tr><th>Статья</th><th>Дата</th><th>Статус</th></tr></thead>
  <tbody>
  <?php foreach ($articles as $a): ?>
    <tr<?= $a['is_published'] ? '' : ' class="a-off"' ?>>
      <td><a href="/admin/blog/<?= (int)$a['id'] ?>/"><b><?= e($a['title']) ?></b></a><br><span class="a-muted">/blog/<?= e($a['slug']) ?>/</span></td>
      <td class="nowrap"><?= e($a['published_at'] ? date('d.m.Y', strtotime($a['published_at'])) : '—') ?></td>
      <td><?= $a['is_published'] ? (strtotime((string)$a['published_at']) > time() ? 'запланирована' : 'опубликована') : 'черновик' ?><?= $a['noindex'] ? ' · noindex' : '' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
