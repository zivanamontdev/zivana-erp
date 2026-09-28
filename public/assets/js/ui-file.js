// uiField type file: tampilkan nama file terpilih di samping tombol "Pilih File".
(function () {
  'use strict';
  document.addEventListener('change', function (event) {
    var input = event.target;
    if (!input.classList || !input.classList.contains('ui-file-input')) return;
    var picker = input.closest('[data-file-picker]');
    var label = picker && picker.querySelector('[data-file-name]');
    if (!label) return;
    var names = Array.from(input.files || []).map(function (file) { return file.name; });
    label.textContent = names.length ? names.join(', ') : label.dataset.empty;
    picker.classList.toggle('has-file', names.length > 0);
  });
})();
