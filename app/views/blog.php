<?php /** @var array $articles @var array $page */ ?>
<section class="wrap section section--top">
  <h1><?= e($page['h1'] ?: 'Блог') ?></h1>
  <p class="lead">Советы по выбору чемодана, нормы багажа авиакомпаний и лайфхаки для путешествий.</p>
  <?php if ($articles): ?>
  <div class="posts">
    <?php foreach ($articles as $a) echo View::partial('partials/post_card', ['a' => $a]); ?>
  </div>
  <?php else: ?><p class="muted">Статьи скоро появятся.</p><?php endif; ?>
  <?= View::partial('partials/seo_text', ['text' => $page['body']]) ?>
</section>
