<?php /** @var array $s */
$f = function ($k, $label, $hint = '', $type = 'text') use ($s) {
    return '<label>' . e($label) . ($hint ? ' <small>' . e($hint) . '</small>' : '') . '<input type="' . $type . '" name="' . $k . '" value="' . e($s[$k] ?? '') . '"></label>';
};
?>
<form method="post" action="/admin/settings/" class="a-form">
  <?= Security::csrfField() ?>
  <div class="a-grid">
    <section class="a-card">
      <h2>Магазин и контакты</h2>
      <?= $f('site_name', 'Название сайта') ?>
      <?= $f('site_tagline', 'Слоган / описание') ?>
      <?= $f('phone', 'Телефон', 'любой формат, +7…') ?>
      <?= $f('whatsapp', 'WhatsApp', 'номер в формате 77001234567') ?>
      <?= $f('telegram', 'Telegram для клиентов', 'username без @') ?>
      <?= $f('email', 'Email', '', 'email') ?>
      <?= $f('address', 'Адрес') ?>
      <?= $f('work_hours', 'Режим работы') ?>
      <?= $f('city_default', 'Город магазина') ?>
      <?= $f('company_legal', 'Юр. лицо и БИН/ИИН', 'показывается в подвале и в политике') ?>
    </section>
    <section class="a-card">
      <h2>Аналитика и поисковики</h2>
      <?= $f('metrika_id', 'Номер счётчика Яндекс Метрики', 'только цифры, например 98765432') ?>
      <?= $f('yandex_verification', 'Код Яндекс Вебмастера', 'можно вставить весь мета-тег') ?>
      <?= $f('google_verification', 'Код Google Search Console', 'можно вставить весь мета-тег') ?>
      <h2>Формы и попап</h2>
      <?= $f('popup_delay', 'Показ попапа со скидкой через, сек', '0 — отключить', 'number') ?>
      <?= $f('consent_version', 'Редакция текста согласия', 'меняйте при изменении текста — сохраняется в каждой заявке') ?>
      <h2>Telegram-уведомления</h2>
      <p class="a-muted">Статус: <b><?= Telegram::configured() ? 'настроено' : 'не настроено' ?></b>. Токен бота и chat_id хранятся в файле <code>.env</code> на сервере (не в базе) — так безопаснее.</p>
      <button class="a-btn a-btn--ghost" type="submit" form="tg-test">Отправить тестовое сообщение</button>
    </section>
  </div>
  <div class="a-sticky"><button class="a-btn">Сохранить настройки</button></div>
</form>
<form id="tg-test" method="post" action="/admin/telegram-test/"><?= Security::csrfField() ?></form>
