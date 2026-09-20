/**
 * Pattern "Accordion Bertingkat" generik. Dipakai di Rapor Murid
 * (daftar) dan Pengisian Rapor. Lihat cookbook/design-system.md
 * bagian 4.6.
 *
 * Markup: <div class="accordion-item">
 *   <button class="accordion-header" data-accordion-toggle>...</button>
 *   <div class="accordion-body">...</div>
 * </div>
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-accordion-toggle]').forEach(function (toggle) {
      toggle.addEventListener('click', function () {
        var item = toggle.closest('.accordion-item');
        if (item) {
          item.classList.toggle('is-open');
        }
      });
    });
  });
})();
