<?php /** @var array $featured */ ?>
<section class="wrap section section--top center">
  <h1>Страница не найдена</h1>
  <p class="lead">Возможно, товар снят с продажи или ссылка устарела.</p>
  <p><a class="btn" href="/catalog/">Перейти в каталог</a> <a class="btn btn--ghost" href="/">На главную</a></p>
</section>
<?php if ($featured): ?>
<section class="wrap section">
  <h2>Популярные модели</h2>
  <div class="grid"><?php foreach ($featured as $p) echo View::partial('partials/card', ['p' => $p]); ?></div>
</section>
<?php endif; ?>
