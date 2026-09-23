/** Debounced GET search; submit the entire form to preserve active filters. */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form.list-filter[method="GET"] input[type="search"][name="q"]').forEach(function (input) {
      var form = input.form;
      var initialValue = input.value;
      var timer;
      var composing = false;

      function cancel() { window.clearTimeout(timer); }
      function schedule() {
        cancel();
        if (composing || input.value === initialValue) return;
        timer = window.setTimeout(function () {
          form.requestSubmit();
        }, 400);
      }

      input.addEventListener('input', schedule);
      input.addEventListener('search', schedule);
      input.addEventListener('compositionstart', function () { composing = true; cancel(); });
      input.addEventListener('compositionend', function () { composing = false; schedule(); });
      form.addEventListener('submit', cancel);
      // Select filters already submit immediately through their change handler.
      form.addEventListener('change', function (event) {
        if (event.target !== input) cancel();
      });
      window.addEventListener('pagehide', cancel);
    });
  });
})();
