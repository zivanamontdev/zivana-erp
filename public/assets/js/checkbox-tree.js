/**
 * Utilitas tri-state checkbox untuk pattern parent/child bertingkat.
 * Dipakai di RBAC dan pattern pohon lain yang butuh "pilih semua"
 * dengan indikator sebagian tercentang (indeterminate).
 * Lihat cookbook/design-system.md bagian 3.3.
 *
 * Markup yang diharapkan:
 * <div data-tree-group>
 *   <label><input type="checkbox" class="checkbox" data-tree-parent> Label <span data-tree-count></span></label>
 *   <div data-tree-group> (nested, opsional)
 *     <label><input type="checkbox" class="checkbox" data-tree-parent> ... </label>
 *     <input type="checkbox" class="checkbox" data-tree-leaf>
 *   </div>
 *   <input type="checkbox" class="checkbox" data-tree-leaf>
 * </div>
 */
(function (global) {
  'use strict';

  function allLeaves(group) {
    return Array.prototype.slice.call(group.querySelectorAll('[data-tree-leaf]'));
  }

  function findOwnParent(group) {
    return group.querySelector(':scope > label [data-tree-parent], :scope > [data-tree-parent]');
  }

  function findOwnCount(group) {
    return group.querySelector(':scope > label [data-tree-count], :scope > [data-tree-count]');
  }

  function updateGroupState(group) {
    var parent = findOwnParent(group);
    var leaves = allLeaves(group);
    var checkedCount = leaves.filter(function (leaf) { return leaf.checked; }).length;

    if (parent) {
      if (checkedCount === 0) {
        parent.checked = false;
        parent.indeterminate = false;
      } else if (checkedCount === leaves.length) {
        parent.checked = true;
        parent.indeterminate = false;
      } else {
        parent.checked = false;
        parent.indeterminate = true;
      }
    }

    var countEl = findOwnCount(group);
    if (countEl) {
      countEl.textContent = checkedCount + '/' + leaves.length;
    }
  }

  function propagateUp(group) {
    var current = group;
    while (current) {
      updateGroupState(current);
      var parentElement = current.parentElement;
      current = parentElement ? parentElement.closest('[data-tree-group]') : null;
    }
  }

  function init(root) {
    root = root || document;

    root.querySelectorAll('[data-tree-group]').forEach(function (group) {
      updateGroupState(group);
    });

    root.querySelectorAll('[data-tree-parent]').forEach(function (parent) {
      parent.addEventListener('change', function () {
        var group = parent.closest('[data-tree-group]');
        if (!group) {
          return;
        }
        allLeaves(group).forEach(function (leaf) {
          leaf.checked = parent.checked;
        });
        parent.indeterminate = false;
        propagateUp(group);
      });
    });

    root.querySelectorAll('[data-tree-leaf]').forEach(function (leaf) {
      leaf.addEventListener('change', function () {
        var group = leaf.closest('[data-tree-group]');
        if (group) {
          propagateUp(group);
        }
      });
    });
  }

  global.CheckboxTree = { init: init };
})(window);
