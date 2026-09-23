/* Derived display only. Disabled age is never submitted or stored. */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('form-murid');
    if (!form) return;
    var birth = form.querySelector('[name="tanggal_lahir"]');
    var age = form.querySelector('[name="umur"]');
    if (!birth || !age) return;
    function updateAge() {
      age.value = '';
      var parts = /^(\d{4})-(\d{2})-(\d{2})$/.exec(birth.value);
      if (!parts) return;
      var year = Number(parts[1]), month = Number(parts[2]) - 1, day = Number(parts[3]);
      var date = new Date(0);
      date.setHours(0, 0, 0, 0);
      date.setFullYear(year, month, day);
      var today = new Date();
      today.setHours(0, 0, 0, 0);
      if (date.getFullYear() !== year || date.getMonth() !== month || date.getDate() !== day || date > today) return;
      var years = today.getFullYear() - year;
      if (today.getMonth() < month || (today.getMonth() === month && today.getDate() < day)) years--;
      age.value = String(years);
    }
    birth.addEventListener('input', updateAge);
    birth.addEventListener('change', updateAge);
    form.addEventListener('reset', function () { window.setTimeout(updateAge, 0); });
    updateAge();
  });
})();
