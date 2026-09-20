/**
 * Pattern Tabs generik. Dipakai di Data Sekolah dan Manajemen Murid.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-tabs]').forEach(function (tabs) {
      tabs.querySelectorAll('[data-tab-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var target = btn.getAttribute('data-tab-target');

          tabs.querySelectorAll('[data-tab-target]').forEach(function (b) {
            b.classList.remove('is-active');
          });
          tabs.querySelectorAll('[data-tab-panel]').forEach(function (p) {
            p.classList.remove('is-active');
          });

          btn.classList.add('is-active');
          var panel = tabs.querySelector('[data-tab-panel="' + target + '"]');
          if (panel) {
            panel.classList.add('is-active');
          }
        });
      });
    });
  });
})();
