<?php /** @var array $redirects */ ?>
<p class="a-muted">301-редирект отправляет посетителей и поисковых роботов со старого адреса на новый и сохраняет позиции в поиске.
Редиректы создаются <b>автоматически</b>, когда вы меняете адрес или удаляете товар, категорию, город или статью. Здесь можно добавить свои — например, при переезде со старого сайта.</p>
<form method="post" class="a-card a-form a-redirect-form">
  <?= Security::csrfField() ?>
  <div class="a-row a-row--3">
    <label>Старый адрес<input name="from_path" placeholder="/old-page/ или https://site.kz/old-page/" required></label>
    <label>Новый адрес<input name="to_path" placeholder="/catalog/ или https://…" required></label>
    <label>Заметка<input name="note" maxlength="200" placeholder="необязательно"></label>
  </div>
  <button class="a-btn">Добавить редирект</button>
</form>
<?php if ($redirects): ?>
<div class="a-table-wrap" style="margin-top:16px"><table class="a-table">
  <thead><tr><th>Старый адрес</th><th>→ Новый адрес</th><th>Переходов</th><th>Заметка</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($redirects as $r): ?>
    <tr>
      <td><?= e($r['from_path']) ?></td>
      <td><a href="<?= e($r['to_path']) ?>" target="_blank" rel="noopener"><?= e($r['to_path']) ?></a></td>
      <td><?= (int)$r['hits'] ?></td>
      <td class="a-muted"><?= e($r['note']) ?><br><?= e(date('d.m.Y', strtotime($r['created_at']))) ?></td>
      <td><form method="post" action="/admin/redirects/<?= (int)$r['id'] ?>/delete/"><?= Security::csrfField() ?><button class="a-btn a-btn--sm a-btn--danger" data-confirm="Удалить редирект? Старый адрес начнёт отдавать ошибку 404.">Удалить</button></form></td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php else: ?><p class="a-muted" style="margin-top:16px">Редиректов пока нет.</p><?php endif; ?>
