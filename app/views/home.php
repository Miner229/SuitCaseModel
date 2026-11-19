<?php /** @var array $featured @var array $categories @var array $cities @var array $faq */ ?>
<section class="hero">
  <div class="wrap hero__in">
    <div class="hero__text">
      <h1>Чемоданы для путешествий с доставкой по Казахстану</h1>
      <p class="hero__lead">Ручная кладь по нормам Air Astana и FlyArystan, вместительные чемоданы для отпуска и семейные наборы. Гарантия до 5 лет, оплата Kaspi, доставка от 1 дня.</p>
      <div class="hero__cta">
        <a class="btn btn--lg" href="/catalog/">Смотреть каталог</a>
        <button class="btn btn--lg btn--ghost" type="button" data-modal="callback" data-goal="open_callback">Помочь с выбором</button>
      </div>
      <ul class="hero__facts">
        <li><b>20+</b> моделей в наличии</li>
        <li><b>1 день</b> доставка по Алматы и Астане</li>
        <li><b>до 5 лет</b> гарантия</li>
      </ul>
    </div>
    <div class="hero__img">
      <?php if (!empty($featured[0]['image'])): ?>
      <img src="<?= e(Images::url($featured[0]['image'], 800)) ?>" srcset="<?= e(Images::srcset($featured[0]['image'])) ?>" sizes="(max-width: 800px) 80vw, 460px" width="800" height="800" alt="<?= e($featured[0]['name']) ?>" fetchpriority="high">
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="wrap section">
  <h2>Выберите размер</h2>
  <div class="cats">
    <?php foreach ($categories as $c): ?>
      <a class="cat" href="/catalog/<?= e($c['slug']) ?>/">
        <span class="cat__name"><?= e($c['name']) ?></span>
        <span class="cat__meta"><?= (int)$c['cnt'] ?> <?= plural((int)$c['cnt'], 'модель', 'модели', 'моделей') ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

<section class="wrap section">
  <div class="section__head">
    <h2>Популярные модели</h2>
    <a href="/catalog/">Весь каталог →</a>
  </div>
  <div class="grid">
    <?php foreach ($featured as $i => $p) echo View::partial('partials/card', ['p' => $p, 'eager' => false]); ?>
  </div>
</section>

<section class="section section--tint">
  <div class="wrap">
    <h2>Почему покупают у нас</h2>
    <div class="benefits">
      <div class="benefit"><b>Честные размеры</b><p>Меряем каждую модель с колёсами и ручками — указываем реальные габариты для стоек регистрации.</p></div>
      <div class="benefit"><b>Оплата Kaspi</b><p>Перевод, QR или рассрочка Kaspi Red. Для юрлиц — безналичный расчёт с документами.</p></div>
      <div class="benefit"><b>Доставка по РК</b><p>Курьер по Алматы и Астане за 1 день, в регионы — за 2–5 дней транспортной компанией.</p></div>
      <div class="benefit"><b>Гарантия</b><p>До 5 лет на корпус, колёса и замки. Обмен и возврат в течение 14 дней.</p></div>
    </div>
  </div>
</section>

<section class="wrap section consult">
  <div class="consult__text">
    <h2>Не знаете, какой чемодан выбрать?</h2>
    <p>Расскажите, куда и на сколько летите — подберём размер под нормы авиакомпании и бюджет. Перезвоним в течение 15 минут.</p>
  </div>
  <div class="consult__form">
    <?= View::partial('partials/lead_form', ['type' => 'consult', 'formId' => 'home', 'button' => 'Подобрать чемодан', 'withMessage' => true]) ?>
  </div>
</section>

<section class="wrap section">
  <h2>Частые вопросы</h2>
  <div class="faq">
    <?php foreach ($faq as [$q, $a]): ?>
      <details><summary><?= e($q) ?></summary><p><?= e($a) ?></p></details>
    <?php endforeach; ?>
  </div>
</section>

<section class="wrap section">
  <h2>Доставляем чемоданы по городам Казахстана</h2>
  <div class="city-links">
    <?php foreach ($cities as $c): ?><a href="/<?= e($c['slug']) ?>/">Чемоданы <?= e($c['name_in']) ?></a><?php endforeach; ?>
  </div>
</section>
