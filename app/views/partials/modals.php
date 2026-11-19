<?php /** @var array $s */ ?>
<div class="modal" id="modal-callback" role="dialog" aria-modal="true" aria-labelledby="mcb-title" hidden>
  <div class="modal__box">
    <button class="modal__close" type="button" data-close aria-label="Закрыть">×</button>
    <div class="modal__title" id="mcb-title">Перезвоним за 15 минут</div>
    <p class="muted">Подберём чемодан под ваш маршрут и авиакомпанию, расскажем о доставке.</p>
    <?= View::partial('partials/lead_form', ['type' => 'callback', 'formId' => 'mcb', 'button' => 'Жду звонка']) ?>
  </div>
</div>

<div class="modal" id="modal-order" role="dialog" aria-modal="true" aria-labelledby="mor-title" hidden>
  <div class="modal__box">
    <button class="modal__close" type="button" data-close aria-label="Закрыть">×</button>
    <div class="modal__title" id="mor-title">Оформить заказ</div>
    <p class="muted" data-order-product></p>
    <?= View::partial('partials/lead_form', ['type' => 'order', 'formId' => 'mor', 'button' => 'Отправить заказ', 'withMessage' => true]) ?>
  </div>
</div>

<div class="modal" id="modal-popup" role="dialog" aria-modal="true" aria-labelledby="mpp-title" hidden>
  <div class="modal__box modal__box--promo">
    <button class="modal__close" type="button" data-close aria-label="Закрыть">×</button>
    <div class="modal__badge">−5%</div>
    <div class="modal__title" id="mpp-title">Скидка 5% на первый заказ</div>
    <p class="muted">Оставьте телефон — менеджер закрепит скидку за вами и поможет с выбором.</p>
    <?= View::partial('partials/lead_form', ['type' => 'popup', 'formId' => 'mpp', 'button' => 'Получить скидку']) ?>
  </div>
</div>

<div class="modal" id="modal-success" role="dialog" aria-modal="true" aria-labelledby="msc-title" hidden>
  <div class="modal__box modal__box--center">
    <button class="modal__close" type="button" data-close aria-label="Закрыть">×</button>
    <div class="ok-icon" aria-hidden="true">✓</div>
    <div class="modal__title" id="msc-title">Заявка отправлена</div>
    <p data-success-text>Спасибо! Менеджер свяжется с вами в ближайшее время.</p>
    <button class="btn" type="button" data-close>Хорошо</button>
  </div>
</div>
