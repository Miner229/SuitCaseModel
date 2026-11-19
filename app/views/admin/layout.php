<?php /** @var string $title @var string $content @var ?array $flash */
$cur = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$newLeads = (int)Db::value("SELECT COUNT(*) FROM leads WHERE status = 'new'");
$nav = ['/admin/' => 'Обзор', '/admin/products/' => 'Товары', '/admin/leads/' => 'Заявки', '/admin/blog/' => 'Блог', '/admin/pages/' => 'Страницы', '/admin/seo/' => 'Категории и города', '/admin/redirects/' => 'Редиректы', '/admin/settings/' => 'Настройки', '/admin/password/' => 'Пароль'];
?><!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow"><title><?= e($title) ?> — Админ-панель</title>
<link rel="stylesheet" href="/assets/css/admin.css?v=<?= filemtime(PUB . '/assets/css/admin.css') ?>">
</head><body>
<header class="a-head">
  <a class="a-logo" href="/admin/">⚙ <?= e(Settings::get('site_name')) ?></a>
  <nav class="a-nav">
    <?php foreach ($nav as $href => $label): ?>
      <a href="<?= $href ?>"<?= ($href === '/admin/' ? $cur === $href : str_starts_with((string)$cur, $href)) ? ' class="on"' : '' ?>><?= e($label) ?><?= $href === '/admin/leads/' && $newLeads ? ' <span class="a-count">' . $newLeads . '</span>' : '' ?></a>
    <?php endforeach; ?>
  </nav>
  <div class="a-right">
    <a href="/" target="_blank" rel="noopener">Открыть сайт ↗</a>
    <form method="post" action="/admin/logout/"><?= Security::csrfField() ?><button class="a-btn a-btn--ghost a-btn--sm">Выйти</button></form>
  </div>
</header>
<main class="a-main">
  <h1><?= e($title) ?></h1>
  <?php if ($flash): ?><div class="a-flash a-flash--<?= e($flash[0]) ?>" role="status"><?= e($flash[1]) ?></div><?php endif; ?>
  <?= $content ?>
</main>
<script src="/assets/js/admin.js?v=<?= filemtime(PUB . '/assets/js/admin.js') ?>" defer></script>
</body></html>
