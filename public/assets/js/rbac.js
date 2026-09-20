/**
 * Interaksi halaman RBAC: toggle accordion role, checkbox "pilih semua"
 * per modul/section, dan penghitung "x/y" otomatis.
 *
 * Catatan: ini bukan tri-state/indeterminate checkbox sungguhan —
 * design-system.md tidak punya contoh visual state indeterminate,
 * jadi dipakai pola sederhana "centang semua turunan" saja.
 * Lihat cookbook/design-system.md bagian 3.3.
 */
(function () {
  'use strict';

  function updateCounts(scope) {
    scope.querySelectorAll('[data-rbac-group]').forEach(function (group) {
      var countEl = group.querySelector(':scope > [data-rbac-parent] [data-rbac-count], :scope > label [data-rbac-count]');
      if (!countEl) {
        return;
      }
      var boxes = group.querySelectorAll('[data-rbac-checkbox]');
      var checked = group.querySelectorAll('[data-rbac-checkbox]:checked');
      countEl.textContent = checked.length + '/' + boxes.length;

      var parent = group.querySelector(':scope > label [data-rbac-parent]');
      if (parent) {
        parent.checked = boxes.length > 0 && checked.length === boxes.length;
      }
    });
  }

  function initRoleForm(form) {
    form.querySelectorAll('[data-rbac-parent]').forEach(function (parent) {
      parent.addEventListener('change', function () {
        var group = parent.closest('[data-rbac-group]');
        if (!group) {
          return;
        }
        group.querySelectorAll('[data-rbac-checkbox]').forEach(function (cb) {
          cb.checked = parent.checked;
        });
        updateCounts(form);
      });
    });

    form.querySelectorAll('[data-rbac-checkbox]').forEach(function (cb) {
      cb.addEventListener('change', function () {
        updateCounts(form);
      });
    });

    updateCounts(form);
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-rbac-form]').forEach(initRoleForm);

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
