<?php /** @var array $cities */ ?>
<section class="wrap section section--top">
  <h1>Доставка чемоданов по Казахстану</h1>
  <p class="lead">Отправляем заказы во все областные центры и крупные города. Выберите свой город, чтобы узнать сроки доставки.</p>
  <div class="cities">
    <?php foreach ($cities as $c): ?>
      <a class="city-card" href="/<?= e($c['slug']) ?>/"><b><?= e($c['name']) ?></b><span>Доставка <?= e($c['delivery_days']) ?></span></a>
    <?php endforeach; ?>
  </div>
</section>
