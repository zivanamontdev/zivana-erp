/**
 * Desktop collapse preference and compact-screen navigation.
 * Mobile expansion never overwrites the saved desktop preference.
 */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var sidebar = document.querySelector('.sidebar');
    var toggle = document.querySelector('[data-sidebar-toggle]');
    if (!sidebar || !toggle) return;
    var narrow = window.matchMedia('(max-width: 56rem)');
    var key = 'zivana-erp-sidebar-collapsed';
    sidebar.classList.toggle('is-collapsed', document.documentElement.classList.contains('sidebar-collapsed'));

    function sync() {
      document.documentElement.classList.toggle('sidebar-collapsed', sidebar.classList.contains('is-collapsed'));
      // CSS owns logo visibility from the first paint, including mobile.
      var expanded = narrow.matches
        ? sidebar.classList.contains('is-mobile-open')
        : !sidebar.classList.contains('is-collapsed');
      toggle.setAttribute('aria-expanded', String(expanded));
      toggle.setAttribute('aria-label', narrow.matches
        ? (expanded ? 'Tutup menu navigasi' : 'Buka menu navigasi')
        : (expanded ? 'Ciutkan sidebar' : 'Perluas sidebar'));
    }
    toggle.setAttribute('aria-controls', 'main-navigation');
    toggle.addEventListener('click', function () {
      if (narrow.matches) {
        sidebar.classList.toggle('is-mobile-open');
      } else {
        var collapsed = sidebar.classList.toggle('is-collapsed');
        try { localStorage.setItem(key, collapsed ? '1' : '0'); } catch (_) {}
      }
      sync();
    });
    narrow.addEventListener('change', function () {
      sidebar.classList.remove('is-mobile-open');
      // Do not leave focus inside navigation that just became hidden.
      if (narrow.matches && sidebar.contains(document.activeElement)) toggle.focus();
      sync();
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && sidebar.classList.contains('is-mobile-open')) {
        sidebar.classList.remove('is-mobile-open');
        toggle.focus();
        sync();
      }
    });
    document.querySelectorAll('[data-nav-toggle]').forEach(function (button) {
      var group = button.closest('.nav-group');
      if (!group) return;
      button.setAttribute('aria-expanded', String(group.classList.contains('is-open')));
      button.addEventListener('click', function () {
        if (!narrow.matches && sidebar.classList.contains('is-collapsed')) {
          sidebar.classList.remove('is-collapsed');
          try { localStorage.setItem(key, '0'); } catch (_) {}
          sync();
        }
        var open = group.classList.toggle('is-open');
        button.setAttribute('aria-expanded', String(open));
      });
    });
    sync();
    document.documentElement.classList.add('sidebar-ready');
  });
})();
