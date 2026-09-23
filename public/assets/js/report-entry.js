(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.querySelector('[data-report-entry]');
    if (!form) return;
    var dirty = false;
    var fields = Array.from(form.querySelectorAll('select[data-report-value]'));
    function updateProgress() {
      var filled = fields.filter(function (field) { return field.value !== ''; }).length;
      var count = form.querySelector('[data-report-count]');
      var bar = form.querySelector('[data-report-progress]');
      if (count) count.textContent = filled + ' dari ' + fields.length;
      if (bar) bar.style.width = (fields.length ? filled / fields.length * 100 : 0) + '%';
      form.querySelectorAll('[data-report-submit]').forEach(function (button) {
        button.disabled = !fields.length || filled !== fields.length;
        button.classList.toggle('ui-button--disabled', button.disabled);
        button.classList.toggle('ui-button--primary', !button.disabled);
      });
    }
    form.addEventListener('input', function (event) {
      if (!event.target.closest('.ui-select-search')) dirty = true;
    });
    form.addEventListener('change', function (event) {
      if (event.target.matches('[data-report-value]')) { dirty = true; updateProgress(); }
    });
    var switcher = form.querySelector('select[data-report-switcher]');
    if (switcher) {
      var original = switcher.value;
      switcher.addEventListener('change', function () {
        if (switcher.value === original) return;
        if (dirty && !window.confirm('Perubahan belum disimpan. Pindah murid dan abaikan perubahan?')) {
          switcher.value = original;
          switcher.dispatchEvent(new Event('change', {bubbles: true}));
          return;
        }
        dirty = false; window.location.href = switcher.value;
      });
    }
    form.addEventListener('submit', function () { dirty = false; });
    window.addEventListener('beforeunload', function (event) {
      if (dirty) { event.preventDefault(); event.returnValue = ''; }
    });
    updateProgress();
  });
})();
