<?php /** @var array $a */ $url = '/blog/' . $a['slug'] . '/'; ?>
<article class="post-card">
  <?php if ($a['cover']): ?><a class="post-card__img" href="<?= e($url) ?>" tabindex="-1" aria-hidden="true"><img src="<?= e(Images::url($a['cover'], 800)) ?>" width="800" height="450" alt="" loading="lazy" decoding="async"></a><?php endif; ?>
  <div class="post-card__body">
    <time class="muted" datetime="<?= e(date('Y-m-d', strtotime($a['published_at']))) ?>"><?= e(date('d.m.Y', strtotime($a['published_at']))) ?></time>
    <h2 class="post-card__title"><a href="<?= e($url) ?>"><?= e($a['title']) ?></a></h2>
    <p><?= e(str_limit($a['excerpt'] ?: Markdown::plain($a['body']), 180)) ?></p>
    <a class="post-card__more" href="<?= e($url) ?>">Читать →</a>
  </div>
</article>
