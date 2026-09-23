(function () {
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-period-form]').forEach(function (form) {
      var semester = form.querySelector('[name="semester"]');
      var type = form.querySelector('[name="tipe"]');
      function sync() {
        type.disabled = !semester.value;
        type.dispatchEvent(new Event('change', {bubbles:true}));
      }
      semester.addEventListener('change', sync);
      sync();
    });
  });
})();
