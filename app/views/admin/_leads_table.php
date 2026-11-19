<?php /** @var array $leads @var bool $compact */ $compact = $compact ?? false; ?>
<?php if (!$leads): ?><p class="a-muted">Заявок пока нет.</p><?php else: ?>
<div class="a-table-wrap"><table class="a-table a-table--leads">
  <thead><tr><th>#</th><th>Дата</th><th>Тип</th><th>Клиент</th><th>Товар / сообщение</th><th>Статус</th></tr></thead>
  <tbody>
  <?php foreach ($leads as $l): ?>
    <tr class="st-<?= e($l['status']) ?>">
      <td><?= (int)$l['id'] ?></td>
      <td class="nowrap"><?= e(date('d.m.Y H:i', strtotime($l['created_at']))) ?></td>
      <td><?= e(Leads::TYPES[$l['type']] ?? $l['type']) ?><?= $l['tg_sent'] ? ' <span title="Отправлено в Telegram">✈</span>' : '' ?></td>
      <td><?= e($l['name'] ?: '—') ?><br><a class="nowrap" href="tel:<?= e($l['phone']) ?>"><?= e(phone_display($l['phone'])) ?></a>
        <a href="https://wa.me/<?= e(ltrim($l['phone'], '+')) ?>" target="_blank" rel="noopener">WA</a></td>
      <td><?php if ($l['product_name']): ?><b><?= e($l['product_name']) ?></b><br><?php endif; ?>
        <?= e($l['message']) ?><?php if ($l['city']): ?><br><span class="a-muted">Город: <?= e($l['city']) ?></span><?php endif; ?>
        <?php if (!$compact): ?><br><span class="a-muted"><?= e($l['page_url']) ?><?= $l['utm'] ? ' · ' . e($l['utm']) : '' ?> · согласие <?= e($l['consent_at']) ?> (v<?= e($l['consent_version']) ?>)</span><?php endif; ?></td>
      <td>
        <?php if ($compact): ?><?= e(Leads::STATUSES[$l['status']] ?? $l['status']) ?>
        <?php else: ?>
        <form method="post" action="/admin/leads/<?= (int)$l['id'] ?>/" class="a-lead-form">
          <?= Security::csrfField() ?><input type="hidden" name="back" value="<?= e($_GET['status'] ?? '') ?>">
          <select name="status"><?php foreach (Leads::STATUSES as $k => $v): ?><option value="<?= $k ?>"<?= $l['status'] === $k ? ' selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select>
          <input name="admin_note" value="<?= e($l['admin_note']) ?>" placeholder="Заметка">
          <button class="a-btn a-btn--sm">OK</button>
        </form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody></table></div>
<?php endif; ?>
