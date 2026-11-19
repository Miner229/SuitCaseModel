<?php /** @var ?string $error */ ?><!doctype html>
<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow"><title>Вход в админ-панель</title>
<link rel="stylesheet" href="/assets/css/admin.css?v=<?= filemtime(PUB . '/assets/css/admin.css') ?>"></head>
<body class="a-login">
<form class="a-card a-login__box" method="post" action="/admin/login/">
  <h1>Вход в админ-панель</h1>
  <?php if (!empty($error)): ?><div class="a-flash a-flash--err"><?= e($error) ?></div><?php endif; ?>
  <?= Security::csrfField() ?>
  <label>Логин<input name="login" autocomplete="username" required autofocus></label>
  <label>Пароль<input name="password" type="password" autocomplete="current-password" required></label>
  <button class="a-btn">Войти</button>
</form>
</body></html>
