<?php
/**
 * Универсальная форма заявки.
 * @var string $type @var string $formId @var string $button
 * Опционально: $product (array), $withMessage (bool), $city (string)
 */
$product = $product ?? null;
$withMessage = $withMessage ?? false;
$fid = e($formId);
$page = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
?>
<form class="lead-form" action="/api/lead/" method="post" novalidate data-lead-form data-goal="lead_<?= e($type) ?>">
  <input type="hidden" name="type" value="<?= e($type) ?>">
  <input type="hidden" name="_ft" value="<?= e(Security::formToken()) ?>">
  <input type="hidden" name="page" value="<?= e($page) ?>">
  <input type="hidden" name="utm" value="">
  <input type="hidden" name="product_id" value="<?= $product ? (int)$product['id'] : '' ?>">
  <?php if (!empty($city)): ?><input type="hidden" name="city" value="<?= e($city) ?>"><?php endif; ?>
  <!-- Honeypot: поле скрыто от людей, боты его заполняют -->
  <div class="hp" aria-hidden="true"><label>Ваш сайт <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>

  <div class="field">
    <label for="<?= $fid ?>-name">Имя</label>
    <input id="<?= $fid ?>-name" name="name" type="text" maxlength="100" autocomplete="name" placeholder="Как к вам обращаться">
  </div>
  <div class="field">
    <label for="<?= $fid ?>-phone">Телефон <span class="req" aria-hidden="true">*</span></label>
    <input id="<?= $fid ?>-phone" name="phone" type="tel" inputmode="tel" autocomplete="tel" required placeholder="+7 (7__) ___-__-__" data-phone-mask>
    <div class="field__err" data-err="phone"></div>
  </div>
  <?php if ($withMessage): ?>
  <div class="field">
    <label for="<?= $fid ?>-msg">Комментарий</label>
    <textarea id="<?= $fid ?>-msg" name="message" rows="3" maxlength="1000" placeholder="Например: нужен чемодан для ручной клади, бюджет до 40 000 ₸"></textarea>
    <div class="field__err" data-err="message"></div>
  </div>
  <?php endif; ?>
  <label class="check">
    <input type="checkbox" name="consent" value="1" required>
    <span>Я даю <a href="/soglasie/" target="_blank">согласие на сбор и обработку персональных данных</a> и ознакомлен(а) с <a href="/politika-konfidencialnosti/" target="_blank">политикой конфиденциальности</a></span>
  </label>
  <div class="field__err" data-err="consent"></div>
  <div class="field__err" data-err="form"></div>
  <button class="btn btn--block" type="submit"><?= e($button) ?></button>
</form>
