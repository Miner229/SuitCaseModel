<?php /** Информационная страница с текстом из админки. @var string $h1 @var string $pageBody */ ?>
<section class="wrap section section--top page-text">
  <h1><?= e($h1) ?></h1>
  <div class="text"><?= Markdown::render($pageBody) ?></div>
</section>
