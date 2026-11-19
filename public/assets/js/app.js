/* Чемодан.kz — клиентский скрипт. Без зависимостей, ~6 КБ. */
(function () {
  'use strict';
  var d = document, body = d.body;
  var metrikaId = body.getAttribute('data-metrika');

  function $(s, root) { return (root || d).querySelector(s); }
  function $$(s, root) { return Array.prototype.slice.call((root || d).querySelectorAll(s)); }
  function store(k, v) { try { if (v === undefined) return localStorage.getItem(k); localStorage.setItem(k, v); } catch (e) { return null; } }

  // ---------- Цели Яндекс Метрики ----------
  function goal(name, params) {
    if (metrikaId && typeof window.ym === 'function') { window.ym(+metrikaId, 'reachGoal', name, params || {}); }
  }
  d.addEventListener('click', function (e) {
    var el = e.target.closest('[data-goal]');
    if (el && el.tagName !== 'FORM') goal(el.getAttribute('data-goal'));
  });

  // ---------- UTM-метки: сохраняем при первом заходе, передаём с заявкой ----------
  (function () {
    var q = location.search.slice(1).split('&').filter(function (p) { return /^(utm_[a-z]+|yclid|gclid)=/.test(p); });
    if (q.length) { try { sessionStorage.setItem('utm', q.join('&')); } catch (e) {} }
  })();
  function utm() { try { return sessionStorage.getItem('utm') || ''; } catch (e) { return ''; } }

  // ---------- Меню ----------
  var burger = $('.burger');
  if (burger) burger.addEventListener('click', function () {
    var open = body.classList.toggle('nav-open');
    burger.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  // ---------- Кнопки быстрой связи ----------
  var fabT = $('.fab__toggle'), fabL = $('#fab-list');
  if (fabT) fabT.addEventListener('click', function () {
    var open = fabL.hidden;
    fabL.hidden = !open;
    fabT.setAttribute('aria-expanded', open ? 'true' : 'false');
  });

  // ---------- Модальные окна ----------
  var lastFocus = null;
  function openModal(id) {
    var m = $('#modal-' + id);
    if (!m) return;
    $$('.modal:not([hidden])').forEach(function (x) { x.hidden = true; });
    lastFocus = d.activeElement;
    m.hidden = false;
    body.classList.add('modal-open');
    var f = $('input:not([type=hidden]):not([tabindex="-1"]), button', $('.modal__box', m));
    if (f) setTimeout(function () { f.focus(); }, 30);
  }
  function closeModal(m) {
    m.hidden = true;
    if (!$('.modal:not([hidden])')) body.classList.remove('modal-open');
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }
  d.addEventListener('click', function (e) {
    var t = e.target.closest('[data-modal]');
    if (t) {
      var id = t.getAttribute('data-modal');
      if (id === 'order') {
        var m = $('#modal-order');
        $('[name=product_id]', m).value = t.getAttribute('data-product-id') || '';
        $('[data-order-product]', m).textContent = t.getAttribute('data-product-name') || '';
      }
      if (fabL && !fabL.hidden) { fabL.hidden = true; }
      openModal(id);
      return;
    }
    if (e.target.closest('[data-close]') || e.target.classList.contains('modal')) {
      var mm = e.target.closest('.modal');
      if (mm) closeModal(mm);
    }
  });
  d.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
      var m = $('.modal:not([hidden])');
      if (m) closeModal(m);
      body.classList.remove('filters-open');
    }
  });

  // ---------- Маска телефона +7 (7xx) xxx-xx-xx ----------
  function formatPhone(v) {
    var x = v.replace(/\D/g, '');
    if (x[0] === '8') x = '7' + x.slice(1);
    if (x[0] !== '7') x = '7' + x;
    x = x.slice(0, 11);
    var r = '+7';
    if (x.length > 1) r += ' (' + x.slice(1, 4);
    if (x.length >= 4) r += ') ' + x.slice(4, 7);
    if (x.length >= 7) r += '-' + x.slice(7, 9);
    if (x.length >= 9) r += '-' + x.slice(9, 11);
    return r;
  }
  $$('[data-phone-mask]').forEach(function (inp) {
    inp.addEventListener('input', function () { inp.value = inp.value ? formatPhone(inp.value) : ''; });
    inp.addEventListener('focus', function () { if (!inp.value) inp.value = '+7 ('; });
    inp.addEventListener('blur', function () { if (inp.value.replace(/\D/g, '').length <= 1) inp.value = ''; });
  });

  // ---------- Отправка заявок ----------
  function showErrors(form, errs) {
    $$('[data-err]', form).forEach(function (el) { el.textContent = ''; });
    $$('.field', form).forEach(function (el) { el.classList.remove('has-err'); });
    Object.keys(errs || {}).forEach(function (k) {
      var el = $('[data-err="' + k + '"]', form);
      if (el) el.textContent = errs[k];
      var inp = $('[name="' + k + '"]', form);
      if (inp && inp.closest('.field')) inp.closest('.field').classList.add('has-err');
    });
  }
  function validate(form) {
    var errs = {};
    var digits = ($('[name=phone]', form).value || '').replace(/\D/g, '');
    if (digits.length !== 11) errs.phone = 'Введите номер полностью: +7 (7xx) xxx-xx-xx';
    if (!$('[name=consent]', form).checked) errs.consent = 'Необходимо согласие на обработку персональных данных';
    return errs;
  }
  $$('[data-lead-form]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var errs = validate(form);
      if (Object.keys(errs).length) { showErrors(form, errs); return; }
      showErrors(form, {});
      $('[name=utm]', form).value = utm();
      var data = {};
      new FormData(form).forEach(function (v, k) { data[k] = v; });
      var btn = $('button[type=submit]', form);
      btn.disabled = true;
      fetch(form.action, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(data),
        credentials: 'same-origin'
      }).then(function (r) { return r.json().catch(function () { return { ok: false, errors: { form: 'Ошибка сервера' } }; }); })
        .then(function (res) {
          btn.disabled = false;
          if (res.ok) {
            goal(form.getAttribute('data-goal') || 'lead');
            goal('lead_any');
            form.reset();
            store('lead_sent', String(Date.now()));
            var sc = $('#modal-success [data-success-text]');
            if (sc && res.message) sc.textContent = res.message;
            openModal('success');
          } else {
            showErrors(form, res.errors || { form: 'Не удалось отправить. Позвоните нам.' });
          }
        })
        .catch(function () {
          btn.disabled = false;
          showErrors(form, { form: 'Нет соединения. Проверьте интернет или позвоните нам.' });
        });
    });
  });

  // ---------- Всплывающее окно со скидкой: по таймеру или при попытке уйти (1 раз в 7 дней) ----------
  (function () {
    var delay = parseInt(body.getAttribute('data-popup-delay'), 10);
    if (!$('#modal-popup') || !(delay > 0) || /\/(kontakty|soglasie|politika)/.test(location.pathname)) return;
    var shown = parseInt(store('popup_shown') || '0', 10);
    var sent = parseInt(store('lead_sent') || '0', 10);
    var week = 7 * 864e5;
    if (Date.now() - shown < week || Date.now() - sent < week) return;
    var done = false;
    function show() {
      if (done || $('.modal:not([hidden])')) return;
      done = true;
      store('popup_shown', String(Date.now()));
      openModal('popup');
      goal('popup_shown');
    }
    setTimeout(show, delay * 1000);
    d.addEventListener('mouseout', function (e) { if (!e.relatedTarget && e.clientY < 5) show(); });
  })();

  // ---------- Фильтры каталога без перезагрузки ----------
  var filters = $('[data-filters]');
  if (filters) {
    var results = $('#catalog-results');
    var ft = $('.filters-toggle');
    if (ft) ft.addEventListener('click', function () { body.classList.add('filters-open'); ft.setAttribute('aria-expanded', 'true'); });
    $('.filters__close') && $('.filters__close').addEventListener('click', function () { body.classList.remove('filters-open'); });
    var timer, ctrl;
    function load(url, push) {
      if (ctrl) ctrl.abort();
      ctrl = window.AbortController ? new AbortController() : null;
      results.classList.add('is-loading');
      fetch(url, { headers: { 'X-Requested-With': 'fetch' }, signal: ctrl ? ctrl.signal : undefined })
        .then(function (r) { return r.text(); })
        .then(function (html) {
          results.innerHTML = html;
          results.classList.remove('is-loading');
          if (push) history.replaceState(null, '', url);
          var c = $('[data-count]', results);
          var sb = $('.filters__btns .btn', filters);
          if (c && sb) sb.textContent = 'Показать (' + c.textContent + ')';
        })
        .catch(function (e) { if (e.name !== 'AbortError') { results.classList.remove('is-loading'); location.href = url; } });
    }
    function currentUrl() {
      var p = new URLSearchParams();
      new FormData(filters).forEach(function (v, k) { if (v !== '') p.append(k, v); });
      var s = $('[data-sort]');
      if (s && s.value !== 'popular') p.set('sort', s.value); else p.delete('sort');
      var q = p.toString();
      return filters.getAttribute('action') + (q ? '?' + q : '');
    }
    filters.addEventListener('change', function () { clearTimeout(timer); timer = setTimeout(function () { load(currentUrl(), true); }, 250); });
    filters.addEventListener('submit', function (e) { e.preventDefault(); body.classList.remove('filters-open'); load(currentUrl(), true); goal('catalog_filter'); });
    results.addEventListener('change', function (e) { if (e.target.matches('[data-sort]')) load(currentUrl(), true); });
    results.addEventListener('click', function (e) {
      var chip = e.target.closest('[data-chip]');
      if (chip) { e.preventDefault(); location.href = chip.href; }
    });
  }

  // ---------- Галерея ----------
  var main = $('[data-gallery-main]');
  $$('.gallery__thumb').forEach(function (b) {
    b.addEventListener('click', function () {
      main.src = b.getAttribute('data-src');
      main.srcset = b.getAttribute('data-srcset');
      $$('.gallery__thumb').forEach(function (x) { x.classList.remove('is-active'); });
      b.classList.add('is-active');
    });
  });

  // ---------- Электронная коммерция Метрики: просмотр товара ----------
  var prod = $('[data-ecommerce]');
  if (prod) {
    try {
      window.dataLayer = window.dataLayer || [];
      window.dataLayer.push({ ecommerce: { currencyCode: 'KZT', detail: { products: [JSON.parse(prod.getAttribute('data-ecommerce'))] } } });
    } catch (e) {}
  }
})();
