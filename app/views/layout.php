<?php
/** @var Seo $seo @var string $content */
$s = Settings::all();
$nonce = Security::nonce();
$navCats = [];
try { $navCats = Catalog::categories(); } catch (Throwable) {}
$curPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
?><!doctype html>
<html lang="ru">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($seo->title) ?></title>
<meta name="description" content="<?= e($seo->description) ?>">
<link rel="canonical" href="<?= e($seo->canonical) ?>">
<meta name="robots" content="<?= e($seo->robots) ?>">
<meta name="geo.region" content="KZ">
<meta name="theme-color" content="#0f3d3e">
<?php if ($s['yandex_verification']): ?><meta name="yandex-verification" content="<?= e($s['yandex_verification']) ?>">
<?php endif; if ($s['google_verification']): ?><meta name="google-site-verification" content="<?= e($s['google_verification']) ?>">
<?php endif; ?>
<meta property="og:type" content="<?= e($seo->ogType) ?>">
<meta property="og:locale" content="ru_KZ">
<meta property="og:site_name" content="<?= e($s['site_name']) ?>">
<meta property="og:title" content="<?= e($seo->title) ?>">
<meta property="og:description" content="<?= e($seo->description) ?>">
<meta property="og:url" content="<?= e($seo->canonical) ?>">
<meta property="og:image" content="<?= e($seo->ogImage ?: base_url('/assets/img/og.jpg')) ?>">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/assets/img/apple-touch-icon.png">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="stylesheet" href="/assets/css/style.css?v=<?= filemtime(PUB . '/assets/css/style.css') ?>">
<?php foreach ($seo->jsonLd as $ld) echo Seo::ldJson($ld), "\n"; ?>
<?php if ($s['metrika_id'] && ctype_digit($s['metrika_id'])): ?>
<script nonce="<?= e($nonce) ?>">
(function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)};m[i].l=1*new Date();
for(var j=0;j<document.scripts.length;j++){if(document.scripts[j].src===r){return;}}
k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)})
(window,document,"script","https://mc.yandex.ru/metrika/tag.js","ym");
ym(<?= (int)$s['metrika_id'] ?>,"init",{clickmap:true,trackLinks:true,accurateTrackBounce:true,webvisor:true,ecommerce:"dataLayer"});
</script>
<?php endif; ?>
<?php if (preg_match('/^G-[A-Z0-9]{4,15}$/', $s['ga4_id'])): ?>
<script async src="https://www.googletagmanager.com/gtag/js?id=<?= e($s['ga4_id']) ?>" nonce="<?= e($nonce) ?>"></script>
<script nonce="<?= e($nonce) ?>">window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','<?= e($s['ga4_id']) ?>');</script>
<?php endif; if (preg_match('/^GTM-[A-Z0-9]{4,12}$/', $s['gtm_id'])): ?>
<script nonce="<?= e($nonce) ?>">(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;var n=d.querySelector('[nonce]');n&&j.setAttribute('nonce',n.nonce||n.getAttribute('nonce'));f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?= e($s['gtm_id']) ?>');</script>
<?php endif; ?>
</head>
<body data-metrika="<?= e(ctype_digit($s['metrika_id']) ? $s['metrika_id'] : '') ?>" data-popup-delay="<?= (int)$s['popup_delay'] ?>">
<?php if ($s['metrika_id'] && ctype_digit($s['metrika_id'])): ?><noscript><div><img src="https://mc.yandex.ru/watch/<?= (int)$s['metrika_id'] ?>" style="position:absolute;left:-9999px" alt=""></div></noscript><?php endif; ?>
<?php if (preg_match('/^GTM-[A-Z0-9]{4,12}$/', $s['gtm_id'])): ?><noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?= e($s['gtm_id']) ?>" height="0" width="0" style="display:none;visibility:hidden" title="GTM"></iframe></noscript><?php endif; ?>
<a class="skip" href="#main">Перейти к содержимому</a>

<div class="topbar">
  <div class="wrap topbar__in">
    <span>🚚 Доставка по всему Казахстану</span>
    <span class="hide-sm"><?= e($s['work_hours']) ?></span>
    <a href="/goroda/" class="hide-sm">Города доставки</a>
  </div>
</div>
<header class="header">
  <div class="wrap header__in">
    <a class="logo" href="/" aria-label="<?= e($s['site_name']) ?> — на главную">
      <img src="/assets/img/logo.svg" width="36" height="36" alt=""><span><?= e($s['site_name']) ?></span>
    </a>
    <nav class="nav" id="nav" aria-label="Основное меню">
      <a href="/catalog/"<?= $curPath === '/catalog/' ? ' aria-current="page"' : '' ?>>Каталог</a>
      <?php foreach ($navCats as $c): ?>
        <a href="/catalog/<?= e($c['slug']) ?>/"<?= $curPath === '/catalog/' . $c['slug'] . '/' ? ' aria-current="page"' : '' ?>><?= e($c['name']) ?></a>
      <?php endforeach; ?>
      <a href="/dostavka-i-oplata/">Доставка</a>
      <a href="/blog/"<?= str_starts_with((string)$curPath, '/blog/') ? ' aria-current="page"' : '' ?>>Блог</a>
      <a href="/kontakty/">Контакты</a>
    </nav>
    <div class="header__actions">
      <a class="header__phone" href="tel:<?= e($s['phone']) ?>" data-goal="click_phone"><?= e(phone_display($s['phone'])) ?></a>
      <button class="btn btn--sm" type="button" data-modal="callback" data-goal="open_callback">Перезвоните мне</button>
      <button class="burger" type="button" aria-controls="nav" aria-expanded="false" aria-label="Меню"><span></span></button>
    </div>
  </div>
</header>

<main id="main">
<?php if ($seo->breadcrumbs && count($seo->breadcrumbs) > 1): ?>
  <nav class="wrap crumbs" aria-label="Хлебные крошки">
    <?php foreach ($seo->breadcrumbs as $i => [$name, $url]): ?>
      <?php if ($i < count($seo->breadcrumbs) - 1): ?><a href="<?= e($url) ?>"><?= e($name) ?></a><span aria-hidden="true">›</span><?php else: ?><span aria-current="page"><?= e($name) ?></span><?php endif; ?>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>
<?php if (isset($_GET['sent'])): ?>
  <div class="wrap"><div class="notice <?= $_GET['sent'] === '1' ? 'notice--ok' : 'notice--err' ?>" role="status"><?= $_GET['sent'] === '1' ? 'Спасибо! Заявка отправлена, мы скоро перезвоним.' : 'Не удалось отправить заявку. Проверьте телефон и согласие, или позвоните нам.' ?></div></div>
<?php endif; ?>
<?= $content ?>
</main>

<footer class="footer">
  <div class="wrap footer__grid">
    <div>
      <div class="logo logo--light"><img src="/assets/img/logo.svg" width="32" height="32" alt="" loading="lazy"><span><?= e($s['site_name']) ?></span></div>
      <p><?= e($s['site_tagline']) ?></p>
      <p class="muted"><?= e($s['company_legal']) ?></p>
    </div>
    <div>
      <div class="footer__h">Каталог</div>
      <?php foreach ($navCats as $c): ?><a href="/catalog/<?= e($c['slug']) ?>/"><?= e($c['name']) ?></a><?php endforeach; ?>
      <a href="/kak-vybrat-chemodan/">Как выбрать чемодан</a>
      <a href="/blog/">Блог</a>
    </div>
    <div>
      <div class="footer__h">Покупателям</div>
      <a href="/dostavka-i-oplata/">Доставка и оплата</a>
      <a href="/garantiya-i-vozvrat/">Гарантия и возврат</a>
      <a href="/o-kompanii/">О компании</a>
      <a href="/goroda/">Города доставки</a>
      <a href="/politika-konfidencialnosti/">Политика конфиденциальности</a>
    </div>
    <div>
      <div class="footer__h">Контакты</div>
      <a href="tel:<?= e($s['phone']) ?>" data-goal="click_phone"><?= e(phone_display($s['phone'])) ?></a>
      <a href="mailto:<?= e($s['email']) ?>" data-goal="click_email"><?= e($s['email']) ?></a>
      <span><?= e($s['address']) ?></span>
      <span><?= e($s['work_hours']) ?></span>
    </div>
  </div>
  <div class="wrap footer__bottom">© <?= date('Y') ?> <?= e($s['site_name']) ?>. Цены указаны в тенге и не являются публичной офертой.</div>
</footer>

<!-- Кнопки быстрой связи -->
<div class="fab" id="fab">
  <div class="fab__list" id="fab-list" hidden>
    <a class="fab__item fab__item--wa" href="https://wa.me/<?= e(preg_replace('/\D/', '', $s['whatsapp'])) ?>?text=<?= rawurlencode('Здравствуйте! Интересует чемодан') ?>" target="_blank" rel="noopener" data-goal="click_whatsapp">WhatsApp</a>
    <?php if ($s['telegram']): ?><a class="fab__item fab__item--tg" href="https://t.me/<?= e(ltrim($s['telegram'], '@')) ?>" target="_blank" rel="noopener" data-goal="click_telegram">Telegram</a><?php endif; ?>
    <a class="fab__item fab__item--ph" href="tel:<?= e($s['phone']) ?>" data-goal="click_phone">Позвонить</a>
    <button class="fab__item fab__item--cb" type="button" data-modal="callback" data-goal="open_callback">Обратный звонок</button>
  </div>
  <button class="fab__toggle" type="button" aria-expanded="false" aria-controls="fab-list" aria-label="Связаться с нами">
    <svg width="26" height="26" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2C6.5 2 2 6.1 2 11.2c0 2.6 1.2 5 3.1 6.6L4.5 22l4.3-2.3c1 .3 2.1.4 3.2.4 5.5 0 10-4.1 10-9.2S17.5 2 12 2Zm-4 10.5a1.3 1.3 0 1 1 0-2.6 1.3 1.3 0 0 1 0 2.6Zm4 0a1.3 1.3 0 1 1 0-2.6 1.3 1.3 0 0 1 0 2.6Zm4 0a1.3 1.3 0 1 1 0-2.6 1.3 1.3 0 0 1 0 2.6Z"/></svg>
  </button>
</div>

<?= View::partial('partials/modals', ['s' => $s]) ?>
<script src="/assets/js/app.js?v=<?= filemtime(PUB . '/assets/js/app.js') ?>" defer></script>
</body>
</html>
