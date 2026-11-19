/**
 * Браузерные тесты (Playwright): адаптивность, меню, фильтры, попапы, формы, цели Метрики.
 *   node tests/e2e.js [base_url]
 * Требуется: npm i -D playwright (или глобальный playwright), доступ к БД через `mariadb`/`mysql` CLI для проверки заявок.
 * Скриншоты сохраняются в tests/screenshots/.
 */
const { chromium, devices } = require('playwright');
const { execSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const BASE = (process.argv[2] || 'http://localhost:8000').replace(/\/$/, '');
const SHOTS = path.join(__dirname, 'screenshots');
fs.mkdirSync(SHOTS, { recursive: true });

const env = Object.fromEntries(fs.readFileSync(path.join(__dirname, '..', '.env'), 'utf8').split('\n')
  .filter(l => l.includes('=') && !l.startsWith('#')).map(l => { const i = l.indexOf('='); return [l.slice(0, i).trim(), l.slice(i + 1).trim()]; }));
const sql = q => execSync(`mariadb --default-character-set=utf8mb4 -N -u${env.DB_USER} -p${env.DB_PASS} -h${env.DB_HOST} ${env.DB_NAME} -e ${JSON.stringify(q)}`).toString().trim();

let pass = 0, fail = 0; const failures = [];
const t = (name, ok, info = '') => { if (ok) { pass++; console.log('  \x1b[32m✓\x1b[0m ' + name); } else { fail++; failures.push(name + ' ' + info); console.log('  \x1b[31m✗ ' + name + '\x1b[0m ' + info); } };
const section = s => console.log('\n\x1b[1m' + s + '\x1b[0m');

const VIEWPORTS = [
  ['phone-320', { viewport: { width: 320, height: 640 }, isMobile: true, hasTouch: true, deviceScaleFactor: 2 }],
  ['iphone-13', devices['iPhone 13']],
  ['galaxy-s9', devices['Galaxy S9+']],
  ['ipad-mini', devices['iPad Mini']],
  ['laptop-1280', { viewport: { width: 1280, height: 800 } }],
  ['desktop-1920', { viewport: { width: 1920, height: 1080 } }],
];
const PAGES = ['/', '/catalog/', '/catalog/ruchnaya-klad/', '/product/chemodan-aero-lite-s-chernyy/', '/almaty/', '/kontakty/', '/dostavka-i-oplata/'];

(async () => {
  sql('DELETE FROM rate_limits');
  const savedMetrika = sql("SELECT v FROM settings WHERE k='metrika_id'");
  const savedDelay = sql("SELECT v FROM settings WHERE k='popup_delay'");
  sql("UPDATE settings SET v='12345678' WHERE k='metrika_id'");
  sql("UPDATE settings SET v='0' WHERE k='popup_delay'");
  const browser = await chromium.launch();

  // Заглушка Метрики: перехватываем вызовы ym(), внешний скрипт не грузим
  const withYm = async ctx => {
    await ctx.addInitScript(() => { window.__goals = []; window.ym = function () { if (arguments[1] === 'reachGoal') window.__goals.push(arguments[2]); }; });
    await ctx.route('https://mc.yandex.ru/**', r => r.fulfill({ status: 200, body: '' }));
  };

  try {
    section('1. Адаптивность: 6 разрешений × ' + PAGES.length + ' страниц');
    for (const [name, opts] of VIEWPORTS) {
      const ctx = await browser.newContext({ ...opts, locale: 'ru-RU' });
      await withYm(ctx);
      const page = await ctx.newPage();
      const errors = [];
      page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
      page.on('pageerror', e => errors.push(e.message));
      const overflow = [], broken = [];
      for (const p of PAGES) {
        await page.goto(BASE + p, { waitUntil: 'load' });
        const r = await page.evaluate(() => ({
          // overflow-x:hidden на body маскирует вылезающие блоки, поэтому ищем элементы за правым краем экрана
          sw: Math.max(document.documentElement.scrollWidth, ...[...document.querySelectorAll('main *, header *, footer *')]
            .filter(el => !el.closest('.cat-tabs, .gallery__thumbs, .a-table-wrap, .hp, .sr, .skip') && el.getClientRects().length)
            .map(el => Math.round(el.getBoundingClientRect().right))),
          w: window.innerWidth,
          brokenImgs: [...document.images].filter(i => i.complete && i.naturalWidth === 0 && i.loading !== 'lazy').map(i => i.src),
          smallTap: [...document.querySelectorAll('.btn, .burger, .fab__toggle')].filter(b => { const r = b.getBoundingClientRect(); return r.width > 0 && r.height < 36; }).length,
          fontPx: parseFloat(getComputedStyle(document.body).fontSize),
        }));
        if (r.sw > r.w + 1) overflow.push(`${p} (${r.sw}>${r.w})`);
        if (r.brokenImgs.length) broken.push(p + ': ' + r.brokenImgs[0]);
        if (r.smallTap || r.fontPx < 14) broken.push(`${p}: мелкие кнопки/шрифт`);
      }
      await page.goto(BASE + '/');
      await page.screenshot({ path: `${SHOTS}/${name}-home.png` });
      await page.goto(BASE + '/catalog/');
      await page.screenshot({ path: `${SHOTS}/${name}-catalog.png` });
      t(`${name}: нет горизонтальной прокрутки`, !overflow.length, overflow.join(', '));
      t(`${name}: нет битых картинок, кнопки ≥36px, шрифт ≥14px`, !broken.length, broken.join(', '));
      t(`${name}: нет ошибок JS и нарушений CSP в консоли`, !errors.length, errors.slice(0, 3).join(' | '));
      await ctx.close();
    }

    section('2. Мобильная версия: меню, фильтры, кнопки связи');
    {
      const ctx = await browser.newContext({ ...devices['iPhone 13'], locale: 'ru-RU' });
      await withYm(ctx);
      const page = await ctx.newPage();
      await page.goto(BASE + '/');
      t('Бургер-меню скрыто по умолчанию', !(await page.locator('#nav').isVisible()));
      await page.click('.burger');
      t('Бургер-меню открывается', await page.locator('#nav a[href="/catalog/"]').isVisible());
      await page.click('.burger');
      t('Бургер-меню закрывается', !(await page.locator('#nav').isVisible()));
      await page.click('.fab__toggle');
      t('Кнопка связи раскрывает WhatsApp / Telegram / звонок', await page.locator('.fab__item--wa').isVisible() && await page.locator('.fab__item--tg').isVisible() && await page.locator('.fab__item--ph').isVisible());
      t('WhatsApp-ссылка корректна', /^https:\/\/wa\.me\/\d{11}\?text=/.test(await page.getAttribute('.fab__item--wa', 'href')));
      await page.click('.fab__item--wa').catch(() => {});
      await page.goto(BASE + '/catalog/');
      const total = +(await page.textContent('[data-count]'));
      t('Фильтры на мобильном скрыты за кнопкой', !(await page.locator('#filters').isVisible()));
      await page.click('.filters-toggle');
      t('Панель фильтров открывается на весь экран', await page.locator('#filters').isVisible());
      await page.check('#filters input[name="size[]"][value="S"]');
      await page.waitForFunction(n => +document.querySelector('[data-count]').textContent !== n, total);
      const sCount = +(await page.textContent('[data-count]'));
      t(`Фильтр применяется без перезагрузки (${total} → ${sCount})`, sCount > 0 && sCount < total);
      t('Кнопка показывает число найденных', (await page.textContent('.filters__btns .btn')).includes(String(sCount)));
      t('URL обновился (можно поделиться ссылкой)', page.url().includes('size%5B%5D=S') || page.url().includes('size[]=S'));
      await page.click('.filters__btns button[type=submit]');
      t('Панель фильтров закрывается', !(await page.locator('#filters').isVisible()));
      t('Цель Метрики «catalog_filter»', (await page.evaluate(() => window.__goals)).includes('catalog_filter'));
      await page.reload();
      t('После перезагрузки фильтр сохраняется', +(await page.textContent('[data-count]')) === sCount && await page.isChecked('#filters input[value="S"]'));
      await ctx.close();
    }

    section('3. Десктоп: сортировка, карточка, галерея');
    {
      const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 }, locale: 'ru-RU' });
      await withYm(ctx);
      const page = await ctx.newPage();
      await page.goto(BASE + '/catalog/');
      t('Фильтры видны сбоку без кнопки', await page.locator('#filters').isVisible() && !(await page.locator('.filters-toggle').isVisible()));
      await page.selectOption('[data-sort]', 'price_asc');
      await page.waitForURL(/sort=price_asc/);
      await page.waitForTimeout(300);
      const prices = await page.$$eval('.price__now', els => els.map(e => +e.textContent.replace(/\D/g, '')));
      t('Сортировка по цене работает через интерфейс', prices.length > 5 && prices.every((p, i) => i === 0 || p >= prices[i - 1]));
      await page.fill('input[name=price_max]', '30000');
      await page.press('input[name=price_max]', 'Tab');
      await page.waitForTimeout(800);
      const p2 = await page.$$eval('.price__now', els => els.map(e => +e.textContent.replace(/\D/g, '')));
      t('Фильтр по цене «до 30 000»', p2.length > 0 && p2.every(p => p <= 30000));
      await page.click('.chip');
      await page.waitForLoadState('load');
      t('Чип фильтра сбрасывает его', !page.url().includes('price_max'));
      await page.goto(BASE + '/catalog/');
      await page.click('.card__title a >> nth=0');
      await page.waitForLoadState('load');
      t('Переход в карточку товара из каталога', page.url().includes('/product/'));
      const src1 = await page.getAttribute('[data-gallery-main]', 'src');
      await page.click('.gallery__thumb >> nth=1');
      t('Галерея: переключение фото', (await page.getAttribute('[data-gallery-main]', 'src')) !== src1);
      t('Таблица характеристик заполнена', (await page.locator('.specs tr').count()) >= 8);
      await page.screenshot({ path: `${SHOTS}/desktop-product.png`, fullPage: true });
      await ctx.close();
    }

    section('4. Формы и попапы через интерфейс');
    {
      const ctx = await browser.newContext({ viewport: { width: 1280, height: 800 }, locale: 'ru-RU' });
      await withYm(ctx);
      const page = await ctx.newPage();
      sql('DELETE FROM leads');
      await page.goto(BASE + '/product/chemodan-aero-lite-m-chernyy/?utm_source=yandex&utm_medium=cpc');
      await page.click('.buy__btns button[data-modal=order]');
      t('Кнопка «Заказать» открывает попап', await page.locator('#modal-order').isVisible());
      t('В попапе указан выбранный товар', (await page.textContent('[data-order-product]')).includes('Aero Lite M'));
      await page.click('#modal-order button[type=submit]');
      t('Пустая форма: подсказки об ошибках, отправки нет', (await page.textContent('#modal-order [data-err=phone]')).length > 5 && (await page.textContent('#modal-order [data-err=consent]')).length > 5);
      await page.click('#mor-phone');
      await page.keyboard.type('7012345678');
      t('Маска телефона', (await page.inputValue('#mor-phone')) === '+7 (701) 234-56-78');
      await page.fill('#mor-name', 'Айгерим');
      await page.fill('#mor-msg', 'Нужна доставка в Шымкент');
      await page.check('#modal-order input[name=consent]');
      await page.waitForTimeout(3000); // защита от ботов: не раньше 3 сек после загрузки
      await page.click('#modal-order button[type=submit]');
      await page.waitForSelector('#modal-success:not([hidden])', { timeout: 8000 });
      t('Успешная отправка → окно «Заявка отправлена»', await page.locator('#modal-success').isVisible());
      const row = sql("SELECT CONCAT_WS('|', name, phone, type, IFNULL(product_id,''), utm, tg_sent) FROM leads ORDER BY id DESC LIMIT 1").split('|');
      t('Заявка в БД: имя, телефон, тип, товар, UTM, Telegram', row[0] === 'Айгерим' && row[1] === '+77012345678' && row[2] === 'order' && row[3] !== '' && row[4].includes('utm_source=yandex') && row[5] === '1', row.join('|'));
      const goals = await page.evaluate(() => window.__goals);
      t('Цели Метрики: open_order, lead_order, lead_any', ['open_order', 'lead_order', 'lead_any'].every(g => goals.includes(g)), goals.join(','));
      await page.keyboard.press('Escape');
      t('Escape закрывает попап', !(await page.locator('#modal-success').isVisible()));
      await page.click('.header__actions button[data-modal=callback]');
      t('«Перезвоните мне» открывает попап обратного звонка', await page.locator('#modal-callback').isVisible());
      await page.click('#modal-callback .modal__close');
      t('Крестик закрывает попап', !(await page.locator('#modal-callback').isVisible()));
      await page.click('a[href^="tel:"] >> nth=0').catch(() => {});
      t('Цель «click_phone»', (await page.evaluate(() => window.__goals)).includes('click_phone'));
      await ctx.close();

      // Попап со скидкой по таймеру + повторно не показывается
      sql("UPDATE settings SET v='2' WHERE k='popup_delay'");
      const ctx2 = await browser.newContext({ viewport: { width: 1280, height: 800 } });
      await withYm(ctx2);
      const p2 = await ctx2.newPage();
      await p2.goto(BASE + '/catalog/');
      t('Попап со скидкой не появляется сразу', !(await p2.locator('#modal-popup').isVisible()));
      await p2.waitForSelector('#modal-popup:not([hidden])', { timeout: 5000 }).catch(() => {});
      t('Попап со скидкой появляется через заданное время', await p2.locator('#modal-popup').isVisible());
      await p2.click('#modal-popup .modal__close');
      await p2.goto(BASE + '/');
      await p2.waitForTimeout(3000);
      t('Попап не показывается повторно (раз в 7 дней)', !(await p2.locator('#modal-popup').isVisible()));
      await ctx2.close();

      // Форма без JavaScript
      const ctx3 = await browser.newContext({ javaScriptEnabled: false });
      const p3 = await ctx3.newPage();
      await p3.goto(BASE + '/kontakty/');
      await p3.fill('#contacts-phone', '+77019998877');
      await p3.check('.consult__form input[name=consent]');
      await p3.waitForTimeout(3100);
      await p3.click('.consult__form button[type=submit]');
      await p3.waitForLoadState('load');
      t('Форма работает и без JavaScript', (await p3.textContent('.notice')).includes('Спасибо'));
      await ctx3.close();
      sql('DELETE FROM leads');
    }

    section('5. Админка в браузере (мобильный)');
    {
      const ctx = await browser.newContext({ ...devices['iPhone 13'] });
      const page = await ctx.newPage();
      await page.goto(BASE + '/admin/');
      await page.fill('input[name=login]', 'admin');
      await page.fill('input[name=password]', process.env.TEST_ADMIN_PASS || 'TestPass12345');
      await page.click('button');
      await page.waitForURL(BASE + '/admin/');
      t('Вход в админку с телефона', (await page.textContent('h1')) === 'Обзор');
      await page.goto(BASE + '/admin/products/');
      t('Список товаров: ~20 строк', (await page.locator('.a-table tbody tr').count()) >= 18);
      await page.screenshot({ path: `${SHOTS}/admin-products-mobile.png` });
      await page.goto(BASE + '/admin/products/new/');
      await page.setInputFiles('input[type=file]', path.join(SHOTS, 'iphone-13-home.png'));
      t('Предпросмотр фото перед загрузкой', (await page.locator('[data-preview-box] img').count()) === 1);
      await ctx.close();
    }

    section('6. Производительность (эмуляция: мобильный, CPU ×4 медленнее, сеть 4G)');
    {
      const ctx = await browser.newContext({ ...devices['Moto G4'] });
      await withYm(ctx);
      for (const p of ['/', '/catalog/', '/product/chemodan-aero-lite-s-chernyy/', '/almaty/']) {
        const page = await ctx.newPage();
        const cdp = await ctx.newCDPSession(page);
        await cdp.send('Network.enable');
        await cdp.send('Network.setCacheDisabled', { cacheDisabled: true });
        await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 150, downloadThroughput: 1.6 * 1024 * 1024 / 8 * 4, uploadThroughput: 750 * 1024 / 8 });
        await cdp.send('Emulation.setCPUThrottlingRate', { rate: 4 });
        await page.addInitScript(() => {
          window.__lcp = 0; window.__cls = 0;
          new PerformanceObserver(l => l.getEntries().forEach(e => { window.__lcp = e.startTime; })).observe({ type: 'largest-contentful-paint', buffered: true });
          new PerformanceObserver(l => l.getEntries().forEach(e => { if (!e.hadRecentInput) window.__cls += e.value; })).observe({ type: 'layout-shift', buffered: true });
        });
        await page.goto(BASE + p, { waitUntil: 'load' });
        await page.mouse.wheel(0, 2000); await page.waitForTimeout(1500);
        const m = await page.evaluate(() => {
          const nav = performance.getEntriesByType('navigation')[0];
          const res = performance.getEntriesByType('resource');
          return { lcp: Math.round(window.__lcp), cls: +window.__cls.toFixed(3), ttfb: Math.round(nav.responseStart), load: Math.round(nav.loadEventEnd),
            kb: Math.round((nav.transferSize + res.reduce((a, r) => a + (r.transferSize || 0), 0)) / 1024), req: res.length + 1,
            blocking: res.filter(r => r.renderBlockingStatus === 'blocking').length };
        });
        t(`${p}: LCP ${m.lcp} мс (<2500), CLS ${m.cls} (<0.1), ${m.kb} КБ, ${m.req} запросов, TTFB ${m.ttfb} мс`, m.lcp < 2500 && m.cls < 0.1 && m.kb < 1500 && m.ttfb < 600, JSON.stringify(m));
        await page.close();
      }
      await ctx.close();
    }
  } finally {
    sql(`UPDATE settings SET v='${savedMetrika.replace(/'/g, '')}' WHERE k='metrika_id'`);
    sql(`UPDATE settings SET v='${savedDelay.replace(/'/g, '')}' WHERE k='popup_delay'`);
    sql('DELETE FROM rate_limits');
    await browser.close();
  }
  console.log(`\n\x1b[1mИтого: ${pass} пройдено, ${fail} провалено\x1b[0m`);
  failures.forEach(f => console.log('  - ' + f));
  process.exit(fail ? 1 : 0);
})();
