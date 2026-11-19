(function () {
  'use strict';
  // Подтверждение опасных действий
  document.addEventListener('click', function (e) {
    var b = e.target.closest('[data-confirm]');
    if (b && !confirm(b.getAttribute('data-confirm'))) e.preventDefault();
  });
  // Предпросмотр выбранных фото
  document.querySelectorAll('[data-preview]').forEach(function (inp) {
    var box = document.querySelector('[data-preview-box]');
    inp.addEventListener('change', function () {
      box.innerHTML = '';
      Array.prototype.forEach.call(inp.files, function (f) {
        if (!/^image\//.test(f.type)) return;
        var img = document.createElement('img');
        img.src = URL.createObjectURL(f);
        img.alt = f.name;
        box.appendChild(img);
      });
    });
  });
  // Счётчики длины SEO-полей
  document.querySelectorAll('[data-counter]').forEach(function (el) {
    var f = el.closest('label').querySelector('input,textarea');
    var base = el.textContent;
    function upd() { el.textContent = base + ' — ' + f.value.length + ' симв.'; }
    f.addEventListener('input', upd); upd();
  });
  // Предупреждение о несохранённых изменениях
  var form = document.querySelector('form.a-form'), dirty = false;
  if (form) {
    form.addEventListener('input', function () { dirty = true; });
    form.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (e) { if (dirty) { e.preventDefault(); e.returnValue = ''; } });
  }
})();
