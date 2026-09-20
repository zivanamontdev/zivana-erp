/**
 * Interaksi halaman RBAC: toggle accordion role + tri-state checkbox
 * (logika tri-state-nya sendiri ada di checkbox-tree.js, dipakai
 * bersama pattern pohon bertingkat lain).
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-rbac-form]').forEach(function (form) {
      CheckboxTree.init(form);
    });

    document.querySelectorAll('[data-role-toggle]').forEach(function (toggle) {
      toggle.addEventListener('click', function () {
        var role = toggle.closest('.rbac-role');
        if (role) {
          role.classList.toggle('is-open');
        }
      });
    });
  });
})();
