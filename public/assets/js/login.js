/**
 * Toggle show/hide password di halaman Login.
 */
(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-password-toggle]').forEach(function (toggleBtn) {
      var passwordInput = document.getElementById(toggleBtn.getAttribute('aria-controls') || 'password');

      if (!passwordInput) {
        return;
      }

      var showIcon = toggleBtn.querySelector('[data-password-show]');
      var hideIcon = toggleBtn.querySelector('[data-password-hide]');
      function syncVisibility() {
        var isVisible = passwordInput.type === 'text';
        toggleBtn.setAttribute('aria-pressed', String(isVisible));
        toggleBtn.setAttribute('aria-label', isVisible ? 'Sembunyikan kata sandi' : 'Tampilkan kata sandi');
        if (showIcon) showIcon.hidden = isVisible;
        if (hideIcon) hideIcon.hidden = !isVisible;
      }
      syncVisibility();

      toggleBtn.addEventListener('click', function () {
        var isHidden = passwordInput.type === 'password';
        passwordInput.type = isHidden ? 'text' : 'password';
        syncVisibility();
      });
    });
  });
})();
