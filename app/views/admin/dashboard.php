<?php /** @var array $stats @var array $leads @var bool $tg */ ?>
<div class="a-stats">
  <a class="a-card a-stat" href="/admin/leads/?status=new"><b><?= $stats['new_leads'] ?></b><span>новых заявок</span></a>
  <div class="a-card a-stat"><b><?= $stats['leads_7d'] ?></b><span>заявок за 7 дней</span></div>
  <a class="a-card a-stat" href="/admin/products/"><b><?= $stats['active'] ?> / <?= $stats['products'] ?></b><span>товаров на сайте</span></a>
  <div class="a-card a-stat<?= $stats['no_photo'] ? ' a-stat--warn' : '' ?>"><b><?= $stats['no_photo'] ?></b><span>товаров без фото</span></div>
</div>
<?php if (!$tg): ?><div class="a-flash a-flash--err">Telegram-уведомления не настроены — заявки сохраняются только здесь. См. «Настройки».</div><?php endif; ?>
<?php if (!Settings::get('metrika_id')): ?><div class="a-flash a-flash--err">Не указан номер счётчика Яндекс Метрики — статистика не собирается. См. «Настройки».</div><?php endif; ?>
<div class="a-actions"><a class="a-btn" href="/admin/products/new/">+ Добавить товар</a><a class="a-btn a-btn--ghost" href="/admin/leads/export/">Выгрузить заявки (CSV)</a></div>
<h2>Последние заявки</h2>
<?= View::partial('admin/_leads_table', ['leads' => $leads, 'compact' => true]) ?>
