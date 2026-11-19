<?php /** SEO-текст из админки (разметка Markdown). @var ?string $text */ $html = Markdown::render($text ?? ''); ?>
<?php if ($html !== ''): ?><div class="seo-text text"><?= $html ?></div><?php endif; ?>
