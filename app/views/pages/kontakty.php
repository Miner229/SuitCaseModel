<?php require __DIR__ . '/_wrap_start.php'; ?>
  <div class="two-col">
    <div>
      <p><b>Телефон:</b> <a href="tel:<?= e($s['phone']) ?>" data-goal="click_phone"><?= e(phone_display($s['phone'])) ?></a></p>
      <p><b>WhatsApp:</b> <a href="https://wa.me/<?= e(preg_replace('/\D/', '', $s['whatsapp'])) ?>" target="_blank" rel="noopener" data-goal="click_whatsapp">написать</a></p>
      <?php if ($s['telegram']): ?><p><b>Telegram:</b> <a href="https://t.me/<?= e(ltrim($s['telegram'], '@')) ?>" target="_blank" rel="noopener" data-goal="click_telegram">@<?= e(ltrim($s['telegram'], '@')) ?></a></p><?php endif; ?>
      <p><b>Email:</b> <a href="mailto:<?= e($s['email']) ?>" data-goal="click_email"><?= e($s['email']) ?></a></p>
      <p><b>Адрес:</b> <?= e($s['address']) ?></p>
      <p><b>Режим работы:</b> <?= e($s['work_hours']) ?></p>
      <p class="muted"><?= e($s['company_legal']) ?></p>
    </div>
    <div class="consult__form">
      <h2>Напишите нам</h2>
      <?= View::partial('partials/lead_form', ['type' => 'consult', 'formId' => 'contacts', 'button' => 'Отправить', 'withMessage' => true]) ?>
    </div>
  </div>
  <?= View::partial('partials/seo_text', ['text' => $pageBody ?? '']) ?>
</section>
