/**
 * Pattern Tabs generik. Dipakai di Data Sekolah dan Manajemen Murid.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-tabs]').forEach(function (tabs) {
      var firstInvalid = null;
      // Required fields in another tab must be visible before browser focus.
      tabs.addEventListener('invalid', function (event) {
        if (firstInvalid && firstInvalid !== event.target) {
          event.preventDefault();
          return;
        }
        firstInvalid = event.target;
        window.setTimeout(function () { firstInvalid = null; }, 0);
        var panel = event.target.closest('[data-tab-panel]');
        if (!panel || panel.classList.contains('is-active')) return;
        var trigger = tabs.querySelector('[data-tab-target="' + panel.getAttribute('data-tab-panel') + '"]');
        if (trigger) trigger.click();
      }, true);
      tabs.querySelectorAll('[data-tab-target]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var target = btn.getAttribute('data-tab-target');

          tabs.querySelectorAll('[data-tab-target]').forEach(function (b) {
            b.classList.remove('is-active');
            if (b.classList.contains('ui-button')) {
              b.classList.remove('ui-button--tabular-active');
              b.classList.add('ui-button--tabular-inactive');
              b.setAttribute('aria-pressed', 'false');
            }
          });
          tabs.querySelectorAll('[data-tab-panel]').forEach(function (p) {
            p.classList.remove('is-active');
          });

          btn.classList.add('is-active');
          if (btn.classList.contains('ui-button')) {
            btn.classList.remove('ui-button--tabular-inactive');
            btn.classList.add('ui-button--tabular-active');
            btn.setAttribute('aria-pressed', 'true');
          }
          var panel = tabs.querySelector('[data-tab-panel="' + target + '"]');
          if (panel) {
            panel.classList.add('is-active');
          }
        });
      });
    });
  });
})();
