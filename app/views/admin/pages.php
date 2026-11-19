<p class="a-muted">Метатеги, заголовок H1 и тексты страниц сайта. Пустое поле — используется стандартный текст.</p>
<div class="a-table-wrap"><table class="a-table">
  <thead><tr><th>Страница</th><th>Адрес</th><th>Title</th><th>Текст</th></tr></thead>
  <tbody>
  <?php foreach (Pages::EDITABLE as $slug => [$name, $mode]): $p = Pages::get($slug); $url = $slug === 'home' ? '/' : '/' . $slug . '/'; ?>
    <tr>
      <td><a href="/admin/pages/<?= e($slug) ?>/"><b><?= e($name) ?></b></a></td>
      <td><a href="<?= e($url) ?>" target="_blank" rel="noopener"><?= e($url) ?> ↗</a></td>
      <td><?= $p['meta_title'] !== '' ? '✓ свой' : '<span class="a-muted">стандартный</span>' ?></td>
      <td><?= trim((string)$p['body']) !== '' ? '✓ ' . ($mode === 'replace' ? 'свой текст' : 'SEO-текст') : '<span class="a-muted">—</span>' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
