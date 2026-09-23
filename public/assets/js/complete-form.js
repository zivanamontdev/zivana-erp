/** Enable a submit button only when required fields and passwords are valid. */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-complete-form]').forEach(function (form) {
      var submit = form.querySelector('[data-complete-submit]');
      var password = form.querySelector('[name="password"]');
      var confirmation = form.querySelector('[name="password_confirmation"]');
      var employeePolicy = form.hasAttribute('data-employee-password-policy');
      var feedback = new Map();
      if (employeePolicy) [password, confirmation].filter(Boolean).forEach(function (field) {
        var message = document.createElement('span');
        message.id = field.id + '-validation';
        message.className = 'field-error';
        message.setAttribute('aria-live', 'polite');
        field.closest('.ui-field').appendChild(message);
        field.setAttribute('aria-describedby', ((field.getAttribute('aria-describedby') || '') + ' ' + message.id).trim());
        feedback.set(field, message);
      });
      function meetsPolicy(value) {
        return Array.from(value).length >= 8 && /[A-Z]/.test(value) && /[0-9]/.test(value) && /[\p{P}\p{S}]/u.test(value);
      }
      function showError(field, message) {
        field.setCustomValidity(message);
        field.classList.toggle('is-negative', Boolean(message));
        field.setAttribute('aria-invalid', String(Boolean(message)));
        feedback.get(field).textContent = message;
        feedback.get(field).hidden = !message;
      }
      function sync() {
        if (employeePolicy && password && confirmation) {
          var requirement = 'Minimal 8 karakter, 1 huruf besar, 1 angka, dan 1 simbol (contoh: Password123$).';
          showError(password, meetsPolicy(password.value) ? '' : requirement);
          showError(confirmation, !meetsPolicy(confirmation.value) ? requirement : confirmation.value !== password.value ? 'Kata sandi tidak sama.' : '');
        } else if (confirmation && password) {
          confirmation.setCustomValidity(confirmation.value !== password.value ? 'Kata sandi tidak sama.' : '');
        }
        var valid = Array.from(form.querySelectorAll('input, select, textarea')).every(function (field) {
          return field.disabled || ((!field.required || field.value.trim() !== '') && field.validity.valid
            && (!field.minLength || field.minLength < 0 || field.value.length >= field.minLength));
        });
        submit.disabled = !valid;
        submit.classList.toggle('ui-button--disabled', !valid);
        submit.classList.toggle('ui-button--' + submit.dataset.enabledVariant, valid);
        return valid;
      }
      form.addEventListener('input', sync);
      form.addEventListener('change', sync);
      form.addEventListener('focusin', sync);
      form.addEventListener('submit', function (event) { if (!sync()) event.preventDefault(); });
      form.addEventListener('reset', function () { window.setTimeout(sync, 0); });
      sync();
    });
  });
})();
