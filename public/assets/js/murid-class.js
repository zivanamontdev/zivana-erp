(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var group = document.querySelector('[data-student-class]');
    if (!group) return;
    var levels = JSON.parse(group.dataset.classLevels);
    var level = group.querySelector('[name="level_kelas"]');
    var classroom = group.querySelector('[name="kelas_id"]');
    function filter() {
      var selectedLevel = levels[classroom.value] || '';
      Array.from(level.options).forEach(function (option) {
        option.disabled = option.value !== '' && option.value !== selectedLevel;
        option.hidden = option.disabled;
      });
      level.value = selectedLevel;
      level.dispatchEvent(new Event('change', {bubbles: true}));
    }
    classroom.addEventListener('change', filter);
    if (classroom.form) classroom.form.addEventListener('reset', function () { window.setTimeout(filter, 0); });
    filter();
  });
})();
