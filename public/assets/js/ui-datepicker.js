/** Small themed date popup; submitted values remain native ISO dates. */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var active = null;
    var months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    function iso(date) {
      return date.getFullYear() + '-' + String(date.getMonth() + 1).padStart(2, '0') + '-' + String(date.getDate()).padStart(2, '0');
    }
    function close(focus) {
      if (!active) return;
      var previous = active; active = null;
      previous.popup.hidden = true;
      previous.trigger.setAttribute('aria-expanded', 'false');
      if (focus) previous.trigger.focus();
    }
    document.querySelectorAll('[data-datepicker]').forEach(function (wrapper, index) {
      var input = wrapper.querySelector('input');
      var trigger = wrapper.querySelector('[data-datepicker-toggle]');
      var popup = document.createElement('div');
      popup.id = 'date-popup-' + index;
      popup.className = 'ui-date-popup';
      popup.hidden = true;
      popup.setAttribute('role', 'dialog');
      popup.setAttribute('aria-label', trigger.getAttribute('aria-label'));
      trigger.setAttribute('aria-controls', popup.id);
      wrapper.appendChild(popup);
      wrapper.classList.add('datepicker-ready');
      var month;
      var view = 'days';
      var yearStart;
      function focusChoice() {
        var choice = popup.querySelector('[data-choice]:not(:disabled)') || popup.querySelector('[data-date]:not(:disabled)');
        if (choice) choice.focus({preventScroll: true});
      }
      function button(text, label, action) {
        var element = document.createElement('button');
        element.type = 'button'; element.textContent = text;
        element.setAttribute('aria-label', label);
        element.addEventListener('click', action);
        return element;
      }
      function render() {
        popup.replaceChildren();
        var header = document.createElement('div'); header.className = 'ui-date-heading';
        function move(amount) {
          if (view === 'years') yearStart += amount * 12;
          else if (view === 'months') month.setFullYear(month.getFullYear() + amount);
          else month.setMonth(month.getMonth() + amount);
          render();
          popup.querySelector(amount < 0 ? '[data-prev]' : '[data-next]').focus({preventScroll: true});
        }
        var prev = button('‹', 'Bulan sebelumnya', function () { move(-1); }); prev.dataset.prev = '';
        var next = button('›', 'Bulan berikutnya', function () { move(1); }); next.dataset.next = '';
        var heading = view === 'years' ? yearStart + ' – ' + (yearStart + 11) : view === 'months' ? String(month.getFullYear()) : months[month.getMonth()] + ' ' + month.getFullYear();
        var title = button(heading, 'Pilih tahun', function () {
          view = 'years'; yearStart = Math.floor(month.getFullYear() / 12) * 12;
          render(); focusChoice();
        });
        title.dataset.yearPicker = '';
        title.setAttribute('aria-live', 'polite');
        var navigationLabel = view === 'years' ? 'Rentang tahun' : view === 'months' ? 'Tahun' : 'Bulan';
        prev.setAttribute('aria-label', navigationLabel + ' sebelumnya');
        next.setAttribute('aria-label', navigationLabel + ' berikutnya');
        header.append(prev, title, next); popup.appendChild(header);
        if (view !== 'days') {
          var choices = document.createElement('div'); choices.className = 'ui-date-choices';
          for (var index = 0; index < 12; index++) {
            (function (number) {
              var isYear = view === 'years';
              var year = isYear ? yearStart + number : month.getFullYear();
              var label = isYear ? String(year) : months[number];
              var choice = button(label, isYear ? 'Pilih tahun ' + year : label + ' ' + year, function () {
                if (isYear) { month.setFullYear(year); view = 'months'; }
                else { month.setMonth(number); view = 'days'; }
                render(); focusChoice();
              });
              choice.dataset.choice = isYear ? String(year) : String(number);
              choice.setAttribute('aria-pressed', String(isYear ? year === month.getFullYear() : number === month.getMonth()));
              var first = new Date(year, isYear ? 0 : number, 1);
              var last = new Date(year, isYear ? 12 : number + 1, 0);
              choice.disabled = year < 100 || year > 9999 || Boolean((input.min && iso(last) < input.min) || (input.max && iso(first) > input.max));
              choices.appendChild(choice);
            })(index);
          }
          popup.appendChild(choices);
          return;
        }
        var grid = document.createElement('div'); grid.className = 'ui-date-grid';
        ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'].forEach(function (day) { var cell = document.createElement('span'); cell.textContent = day; grid.appendChild(cell); });
        var offset = (month.getDay() + 6) % 7;
        for (var blank = 0; blank < offset; blank++) grid.appendChild(document.createElement('span'));
        var days = new Date(month.getFullYear(), month.getMonth() + 1, 0).getDate();
        for (var day = 1; day <= days; day++) {
          (function (number) {
            var value = iso(new Date(month.getFullYear(), month.getMonth(), number));
            var cell = button(String(number), number + ' ' + title.textContent, function () {
              input.value = value;
              input.dispatchEvent(new Event('input', {bubbles: true}));
              input.dispatchEvent(new Event('change', {bubbles: true}));
              close(true);
            });
            cell.dataset.date = value;
            cell.setAttribute('aria-pressed', String(value === input.value));
            cell.disabled = Boolean((input.min && value < input.min) || (input.max && value > input.max));
            if (value === iso(new Date())) cell.setAttribute('aria-current', 'date');
            grid.appendChild(cell);
          })(day);
        }
        popup.appendChild(grid);
      }
      trigger.addEventListener('click', function () {
        if (active && active.trigger === trigger) { close(false); return; }
        close(false);
        var date = input.value ? new Date(input.value + 'T12:00:00') : new Date();
        month = new Date(date.getFullYear(), date.getMonth(), 1);
        view = 'days';
        render(); popup.hidden = false;
        var rect = input.getBoundingClientRect();
        popup.style.width = Math.min(288, rect.width, window.innerWidth - 16) + 'px';
        popup.style.left = Math.max(8, Math.min(rect.left, window.innerWidth - popup.offsetWidth - 8)) + 'px';
        var below = window.innerHeight - rect.bottom - 8;
        var upwards = below < popup.offsetHeight && rect.top > below;
        popup.style.maxHeight = Math.max(80, upwards ? rect.top - 12 : below - 4) + 'px';
        popup.style.top = (upwards ? Math.max(8, rect.top - popup.offsetHeight - 4) : rect.bottom + 4) + 'px';
        trigger.setAttribute('aria-expanded', 'true');
        active = {popup: popup, trigger: trigger, wrapper: wrapper};
        var selected = popup.querySelector('[aria-pressed="true"]:not(:disabled)') || popup.querySelector('[data-date]:not(:disabled)');
        if (selected) selected.focus();
      });
      popup.addEventListener('keydown', function (event) {
        var cells = Array.from(popup.querySelectorAll(view === 'days' ? '[data-date]' : '[data-choice]'));
        var current = cells.indexOf(document.activeElement);
        var columns = view === 'days' ? 7 : 3;
        var steps = {ArrowLeft: -1, ArrowRight: 1, ArrowUp: -columns, ArrowDown: columns};
        if (current >= 0 && steps[event.key]) {
          event.preventDefault(); var target = cells[current + steps[event.key]];
          if (target && !target.disabled) target.focus();
        }
      });
      popup.addEventListener('focusout', function () {
        window.setTimeout(function () { if (active && active.popup === popup && !wrapper.contains(document.activeElement)) close(false); }, 0);
      });
    });
    document.addEventListener('click', function (event) {
      // A month change replaces the clicked button. Use the original event
      // path, not contains(target), which sees that old button as detached.
      if (active && !event.composedPath().includes(active.wrapper)) close(false);
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && active) { event.preventDefault(); event.stopImmediatePropagation(); close(true); }
    }, true);
    window.addEventListener('resize', function () { close(false); });
    document.addEventListener('scroll', function (event) { if (active && !active.popup.contains(event.target)) close(false); }, true);
  });
})();
