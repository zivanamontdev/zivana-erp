/**
 * Interaksi sidebar: toggle collapse + toggle submenu.
 * Vanilla JS, tanpa dependency, sesuai arsitektur native PHP (lihat
 * cookbook/architecture.md bagian 6).
 */
(function () {
  'use strict';

  var STORAGE_KEY = 'zivana-erp-sidebar-collapsed';

  function initCollapse() {
    var sidebar = document.querySelector('.sidebar');
    var toggleBtn = document.querySelector('[data-sidebar-toggle]');
    if (!sidebar || !toggleBtn) {
      return;
    }

    var isCollapsed = false;
    try {
      isCollapsed = localStorage.getItem(STORAGE_KEY) === '1';
    } catch (e) {
      /* localStorage bisa gagal di private mode — abaikan, pakai default false */
    }

    if (isCollapsed) {
      sidebar.classList.add('is-collapsed');
    }

    toggleBtn.addEventListener('click', function () {
      var collapsed = sidebar.classList.toggle('is-collapsed');
      try {
        localStorage.setItem(STORAGE_KEY, collapsed ? '1' : '0');
      } catch (e) {
        /* abaikan kalau localStorage tidak tersedia */
      }
    });
  }

  function initSubmenuToggle() {
    var toggles = document.querySelectorAll('[data-nav-toggle]');
    toggles.forEach(function (toggle) {
      toggle.addEventListener('click', function () {
        var group = toggle.closest('.nav-group');
        if (group) {
          group.classList.toggle('is-open');
        }
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initCollapse();
    initSubmenuToggle();
  });
})();
