<?php /** @var array $a @var array $others @var array $products */ ?>
<article class="wrap section section--top article">
  <header>
    <h1><?= e($a['title']) ?></h1>
    <p class="muted"><time datetime="<?= e(date('c', strtotime($a['published_at']))) ?>"><?= e(date('d.m.Y', strtotime($a['published_at']))) ?></time></p>
    <?php if ($a['excerpt']): ?><p class="lead"><?= e($a['excerpt']) ?></p><?php endif; ?>
  </header>
  <?php if ($a['cover']): ?>
    <img class="article__cover" src="<?= e(Images::url($a['cover'], 1200)) ?>" srcset="<?= e(Images::srcset($a['cover'])) ?>" sizes="(max-width: 900px) 100vw, 860px" width="1200" height="675" alt="<?= e($a['cover_alt'] ?: $a['title']) ?>" fetchpriority="high">
  <?php endif; ?>
  <div class="text article__body"><?= Markdown::render($a['body']) ?></div>
  <aside class="article__cta">
    <b>Нужна помощь с выбором?</b>
    <p>Подберём чемодан под ваш маршрут, авиакомпанию и бюджет.</p>
    <button class="btn" type="button" data-modal="callback" data-goal="open_callback">Получить консультацию</button>
  </aside>
</article>
<?php if ($products): ?>
<section class="wrap section">
  <h2>Популярные чемоданы</h2>
  <div class="grid"><?php foreach ($products as $p) echo View::partial('partials/card', ['p' => $p]); ?></div>
</section>
<?php endif; ?>
<?php if ($others): ?>
<section class="wrap section">
  <h2>Читайте также</h2>
  <div class="posts"><?php foreach ($others as $o) echo View::partial('partials/post_card', ['a' => $o]); ?></div>
</section>
<?php endif; ?>
