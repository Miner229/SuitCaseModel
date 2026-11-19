<?php /** @var array $city @var array $products @var array $categories @var array $cities */ ?>
<section class="hero hero--city">
  <div class="wrap">
    <h1>Чемоданы <?= e($city['name_in']) ?></h1>
    <p class="hero__lead"><?= e($city['intro'] ?: 'Купить чемодан ' . $city['name_in'] . ' с доставкой: ручная кладь, средние и большие модели, наборы. Оплата Kaspi, гарантия до 5 лет.') ?></p>
    <div class="hero__cta">
      <a class="btn btn--lg" href="/catalog/">Каталог чемоданов</a>
      <button class="btn btn--lg btn--ghost" type="button" data-modal="callback" data-goal="open_callback">Консультация</button>
    </div>
    <ul class="hero__facts">
      <li><b><?= e($city['delivery_days']) ?></b> доставка <?= e($city['name_in']) ?></li>
      <li><b>Kaspi</b> оплата и рассрочка</li>
      <li><b>14 дней</b> на обмен и возврат</li>
    </ul>
  </div>
</section>

<section class="wrap section">
  <h2>Популярные чемоданы <?= e($city['name_in']) ?></h2>
  <div class="grid"><?php foreach ($products as $p) echo View::partial('partials/card', ['p' => $p]); ?></div>
  <p class="center"><a class="btn btn--ghost" href="/catalog/">Смотреть все модели</a></p>
</section>

<section class="section section--tint">
  <div class="wrap two-col">
    <div>
      <h2>Доставка <?= e($city['name_in']) ?></h2>
      <div class="text">
        <?php $dt = $city['delivery_text'] ?: "Доставляем чемоданы {$city['name_in']} за {$city['delivery_days']}. Перед отправкой менеджер подтвердит заказ по телефону или в WhatsApp. Оплатить можно через Kaspi, картой или при получении.";
        foreach (preg_split('/\R{2,}/', $dt) as $para) echo '<p>' . nl2br(e($para)) . '</p>'; ?>
      </div>
      <h3>Разделы каталога</h3>
      <ul class="list">
        <?php foreach ($categories as $c): ?><li><a href="/catalog/<?= e($c['slug']) ?>/"><?= e($c['name']) ?></a></li><?php endforeach; ?>
      </ul>
    </div>
    <div class="consult__form">
      <h3>Заказать чемодан <?= e($city['name_in']) ?></h3>
      <?= View::partial('partials/lead_form', ['type' => 'consult', 'formId' => 'city', 'button' => 'Отправить заявку', 'withMessage' => true, 'city' => $city['name']]) ?>
    </div>
  </div>
</section>

<section class="wrap section">
  <h2>Другие города</h2>
  <div class="city-links">
    <?php foreach ($cities as $c) if ($c['id'] !== $city['id']): ?><a href="/<?= e($c['slug']) ?>/"><?= e($c['name']) ?></a><?php endif; ?>
  </div>
</section>
