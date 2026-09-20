/**
 * Toggle show/hide password di halaman Login.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var toggleBtn = document.querySelector('[data-password-toggle]');
    var passwordInput = document.getElementById('password');

    if (!toggleBtn || !passwordInput) {
      return;
    }

    toggleBtn.addEventListener('click', function () {
      var isHidden = passwordInput.type === 'password';
      passwordInput.type = isHidden ? 'text' : 'password';
      toggleBtn.setAttribute('aria-label', isHidden ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
    });
  });
})();
