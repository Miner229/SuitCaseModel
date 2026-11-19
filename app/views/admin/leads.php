<?php /** @var array $leads @var string $status */ ?>
<div class="a-actions">
  <a class="a-btn a-btn--sm<?= $status === '' ? '' : ' a-btn--ghost' ?>" href="/admin/leads/">Все</a>
  <?php foreach (Leads::STATUSES as $k => $v): ?><a class="a-btn a-btn--sm<?= $status === $k ? '' : ' a-btn--ghost' ?>" href="/admin/leads/?status=<?= $k ?>"><?= $v ?></a><?php endforeach; ?>
  <a class="a-btn a-btn--sm a-btn--ghost" href="/admin/leads/export/">CSV</a>
</div>
<?= View::partial('admin/_leads_table', ['leads' => $leads]) ?>
