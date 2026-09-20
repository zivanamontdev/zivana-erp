/**
 * Buka/tutup modal generik. Dipakai di semua modul (modal tambah/ubah/
 * hapus). Lihat cookbook/design-system.md bagian 4.3.
 *
 * Markup yang diharapkan:
 * <button data-modal-open="modal-tambah-x">Buka</button>
 * <div class="modal-overlay" id="modal-tambah-x">
 *   <div class="modal-box modal-sm">
 *     ...
 *     <button data-modal-close>Batal</button>
 *   </div>
 * </div>
 */
(function () {
  'use strict';

  function openModal(id) {
    var modal = document.getElementById(id);
    if (!modal) {
      return;
    }
    modal.classList.add('is-open');
    document.body.classList.add('modal-open');
  }

  function closeModal(overlay) {
    overlay.classList.remove('is-open');
    if (!document.querySelector('.modal-overlay.is-open')) {
      document.body.classList.remove('modal-open');
    }
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-modal-open]').forEach(function (trigger) {
      trigger.addEventListener('click', function () {
        openModal(trigger.getAttribute('data-modal-open'));
      });
    });

    document.querySelectorAll('[data-modal-close]').forEach(function (closer) {
      closer.addEventListener('click', function () {
        var overlay = closer.closest('.modal-overlay');
        if (overlay) {
          closeModal(overlay);
        }
      });
    });

    document.querySelectorAll('.modal-overlay').forEach(function (overlay) {
      overlay.addEventListener('click', function (event) {
        if (event.target === overlay) {
          closeModal(overlay);
        }
      });
    });

    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.is-open').forEach(closeModal);
      }
    });
  });
})();
