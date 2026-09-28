// Toast bersama: tutup otomatis (sukses/info 5 dtk, error 8 dtk), tombol tutup, dan window.uiToast(pesan, variant).
(function () {
  'use strict';
  var region = document.querySelector('[data-toast-region]');
  if (!region) return;

  function dismiss(toast) {
    if (!toast || toast.classList.contains('is-leaving')) return;
    toast.classList.add('is-leaving');
    window.setTimeout(function () { toast.remove(); }, 200);
  }

  // Garis waktu di bawah toast (animasi CSS) menentukan kapan toast tertutup; berhenti saat disorot/difokus.
  function arm(toast) {
    var delay = toast.classList.contains('ui-toast--error') ? 8000 : 5000;
    toast.style.setProperty('--toast-duration', delay + 'ms');
    var timer = toast.querySelector('.ui-toast-timer');
    var reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (timer && !reduced) timer.addEventListener('animationend', function () { dismiss(toast); });
    else window.setTimeout(function () { dismiss(toast); }, delay);
    toast.addEventListener('mouseenter', function () { toast.classList.add('is-paused'); });
    toast.addEventListener('mouseleave', function () { toast.classList.remove('is-paused'); });
    toast.addEventListener('focusin', function () { toast.classList.add('is-paused'); });
    toast.addEventListener('focusout', function () { toast.classList.remove('is-paused'); });
  }

  region.addEventListener('click', function (event) {
    var close = event.target.closest('[data-toast-close]');
    if (close) dismiss(close.closest('[data-toast]'));
  });
  region.querySelectorAll('[data-toast]').forEach(arm);

  // Form ber-atribut data-toast-validate (dengan novalidate): kesalahan isian ditampilkan sebagai toast,
  // bukan balon bawaan browser. Validasi server tetap berlaku.
  document.addEventListener('submit', function (event) {
    var form = event.target;
    if (!form.matches || !form.matches('[data-toast-validate]') || form.checkValidity()) return;
    event.preventDefault();
    var field = form.querySelector(':invalid');
    var label = field.id ? form.querySelector('label[for="' + CSS.escape(field.id) + '"]') : null;
    var name = label ? label.textContent.trim() : 'Isian';
    var message = field.type === 'checkbox' ? 'Centang persetujuan terlebih dahulu.'
      : field.validity.valueMissing ? (field.type === 'file' ? 'Pilih ' + name.toLowerCase() + ' terlebih dahulu.' : name + ' wajib diisi.')
      : field.validity.patternMismatch && field.name === 'nuptk' ? 'NUPTK harus 16 digit atau kosong.'
      : name + ' tidak sesuai format.';
    window.uiToast(message, 'error');
    field.focus();
  }, true);

  window.uiToast = function (message, variant) {
    var type = ['success', 'error', 'info'].indexOf(variant) >= 0 ? variant : 'info';
    var template = document.querySelector('template[data-toast-template="' + type + '"]');
    if (!template) return;
    var holder = document.createElement('div');
    holder.innerHTML = template.innerHTML;
    var toast = holder.firstElementChild;
    toast.querySelector('.ui-toast-message').textContent = message;
    region.appendChild(toast);
    arm(toast);
    return toast;
  };
})();
