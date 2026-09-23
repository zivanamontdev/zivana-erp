/**
 * Position action menus against the viewport so scrollable tables do not clip them.
 */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    function closeAll() {
      document.querySelectorAll('[data-action-menu].is-open').forEach(function (menu) {
        menu.classList.remove('is-open');
        var toggle = menu.querySelector('[data-action-menu-toggle]');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
      });
    }
    document.querySelectorAll('[data-action-menu-toggle]').forEach(function (toggle) {
      toggle.setAttribute('aria-expanded', 'false');
      toggle.addEventListener('click', function (event) {
        event.stopPropagation();
        var menu = toggle.closest('[data-action-menu]');
        if (!menu) return;
        var wasOpen = menu.classList.contains('is-open');
        closeAll();
        if (wasOpen) return;
        menu.classList.add('is-open');
        toggle.setAttribute('aria-expanded', 'true');
        var dropdown = menu.querySelector('.action-menu-dropdown');
        if (!dropdown) return;
        dropdown.style.position = 'fixed';
        dropdown.style.right = 'auto';
        dropdown.style.maxHeight = 'calc(100svh - 2rem)';
        dropdown.style.overflowY = 'auto';
        var anchor = toggle.getBoundingClientRect();
        if (menu.hasAttribute('data-dropdown-match-trigger')) {
          dropdown.style.boxSizing = 'border-box';
          dropdown.style.minWidth = '0';
          dropdown.style.width = anchor.width + 'px';
        }
        var rect = dropdown.getBoundingClientRect();
        var viewport = document.documentElement;
        var left = Math.max(8, Math.min(anchor.right - rect.width, viewport.clientWidth - rect.width - 8));
        var top = anchor.bottom + 4;
        if (top + rect.height > viewport.clientHeight - 8) top = Math.max(8, anchor.top - rect.height - 4);
        dropdown.style.left = left + 'px';
        dropdown.style.top = top + 'px';
      });
    });
    document.addEventListener('click', closeAll);
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        var toggle = document.querySelector('[data-action-menu].is-open [data-action-menu-toggle]');
        closeAll();
        if (toggle) toggle.focus();
      }
    });
    window.addEventListener('resize', closeAll);
    document.addEventListener('scroll', function (event) {
      if (event.target.closest && event.target.closest('.action-menu-dropdown')) return;
      closeAll();
    }, true);
  });
})();
