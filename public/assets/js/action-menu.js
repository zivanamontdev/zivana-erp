/**
 * Dropdown menu aksi (tombol "⋮" di kolom aksi tabel). Dipakai di
 * hampir semua halaman daftar mulai Fase 4.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-action-menu-toggle]').forEach(function (toggle) {
      toggle.addEventListener('click', function (event) {
        event.stopPropagation();
        var menu = toggle.closest('[data-action-menu]');
        if (!menu) {
          return;
        }
        var wasOpen = menu.classList.contains('is-open');
        document.querySelectorAll('[data-action-menu].is-open').forEach(function (m) {
          m.classList.remove('is-open');
        });
        if (!wasOpen) {
          menu.classList.add('is-open');
        }
      });
    });

    document.addEventListener('click', function () {
      document.querySelectorAll('[data-action-menu].is-open').forEach(function (m) {
        m.classList.remove('is-open');
      });
    });
  });
})();
