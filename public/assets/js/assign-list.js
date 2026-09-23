/**
 * Pattern "Assign Many-to-Many" — dropdown dinamis + tombol tambah/
 * hapus baris. Dipakai di modal "Atur Anak Murid" (Manajemen Guru
 * dan Detail Kelas). Lihat cookbook/design-system.md bagian 4.5.
 *
 * Markup yang diharapkan:
 * <div data-assign-list>
 *   <button type="button" data-assign-add>+ Tambah Murid</button>
 *   <div data-assign-rows>
 *     <div data-assign-row>...<button data-assign-remove>...</button></div>
 *   </div>
 *   <template data-assign-template>
 *     <div data-assign-row>...<button data-assign-remove>...</button></div>
 *   </template>
 * </div>
 */
(function () {
  'use strict';
  var cloneSequence = 0;

  function bindRemove(row) {
    var removeBtn = row.querySelector('[data-assign-remove]');
    if (removeBtn) {
      removeBtn.addEventListener('click', function () {
        row.remove();
      });
    }
  }

  function initAssignList(container) {
    var rowsContainer = container.querySelector('[data-assign-rows]');
    var addBtn = container.querySelector('[data-assign-add]');
    var template = container.querySelector('template[data-assign-template]');

    if (!rowsContainer || !addBtn || !template) {
      return;
    }

    rowsContainer.querySelectorAll('[data-assign-row]').forEach(bindRemove);

    addBtn.addEventListener('click', function () {
      var fragment = template.content.cloneNode(true);
      // Repeated field components need unique IDs and matching labels.
      var suffix = '-clone-' + (++cloneSequence);
      var ids = {};
      fragment.querySelectorAll('[id]').forEach(function (element) {
        ids[element.id] = element.id + suffix;
        element.id += suffix;
      });
      fragment.querySelectorAll('[for], [aria-controls], [aria-describedby]').forEach(function (element) {
        ['for', 'aria-controls', 'aria-describedby'].forEach(function (attribute) {
          if (!element.hasAttribute(attribute)) return;
          element.setAttribute(attribute, element.getAttribute(attribute).split(' ').map(function (id) { return ids[id] || id; }).join(' '));
        });
      });
      rowsContainer.appendChild(fragment);
      bindRemove(rowsContainer.lastElementChild);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-assign-list]').forEach(initAssignList);
  });
})();
