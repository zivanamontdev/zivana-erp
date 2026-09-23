/** Progressive custom select. Native select remains the submitted value/fallback. */
(function () {
  'use strict';
  document.addEventListener('DOMContentLoaded', function () {
    var open = null;
    var sequence = 0;
    function close(restoreFocus) {
      if (!open) return;
      var current = open;
      open = null;
      current.list.hidden = true;
      current.trigger.setAttribute('aria-expanded', 'false');
      if (restoreFocus) current.trigger.focus();
    }
    function init(root) {
      root.querySelectorAll('[data-ui-select]').forEach(function (wrapper) {
        if (wrapper.dataset.selectReady) return;
        wrapper.dataset.selectReady = 'true';
        var select = wrapper.querySelector('select');
        var label = wrapper.querySelector('label');
        var id = 'custom-select-' + (++sequence);
        var trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.id = id;
        trigger.className = wrapper.hasAttribute('data-ui-filter')
          ? 'ui-button ui-button--outline ui-select-trigger ui-filter-trigger'
          : 'field-input ui-select-trigger font-geist';
        trigger.disabled = select.disabled;
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.setAttribute('aria-controls', id + '-options');
        ['aria-invalid', 'aria-describedby'].forEach(function (attr) {
          if (select.hasAttribute(attr)) trigger.setAttribute(attr, select.getAttribute(attr));
        });
        var text = document.createElement('span');
        trigger.appendChild(text);
        var chevron = wrapper.querySelector('[data-select-chevron]');
        if (chevron) trigger.appendChild(chevron.content.cloneNode(true));
        var list = document.createElement('div');
        list.id = id + '-options';
        list.className = 'ui-select-options';
        list.hidden = true;
        list.setAttribute('role', 'listbox');
        list.setAttribute('aria-label', label.textContent);
        label.id = id + '-label';
        label.htmlFor = id;
        trigger.setAttribute('aria-labelledby', label.id + ' ' + id + '-value');
        text.id = id + '-value';
        var buttons = [];
        Array.from(select.options).forEach(function (option, index) {
          var button = document.createElement('button');
          button.type = 'button';
          button.className = 'ui-select-option';
          button.setAttribute('role', 'option');
          button.tabIndex = -1;
          button.disabled = option.disabled;
          renderOption(button, option);
          button.addEventListener('click', function () {
            select.selectedIndex = index;
            select.dispatchEvent(new Event('change', {bubbles: true}));
            close(true);
          });
          list.appendChild(button);
          buttons.push(button);
        });
        function renderOption(target, option) {
          target.textContent = '';
          target.classList.toggle('ui-select-value--image', !!(option && option.dataset.optionImage));
          if (!option) return;
          if (option.dataset.optionImage) {
            var image = document.createElement('img');
            image.src = option.dataset.optionImage;
            image.alt = '';
            image.width = 24;
            image.height = 24;
            image.className = 'ui-select-option-image';
            target.appendChild(image);
          }
          var caption = document.createElement('span');
          caption.textContent = option.textContent;
          target.appendChild(caption);
        }
        function sync() {
          trigger.disabled = select.disabled;
          renderOption(text, select.selectedOptions[0]);
          buttons.forEach(function (button, index) {
            button.disabled = select.options[index].disabled;
            button.hidden = select.options[index].hidden;
            button.setAttribute('aria-selected', String(index === select.selectedIndex));
          });
        }
        function show() {
          if (trigger.disabled) return;
          close(false);
          sync();
          list.hidden = false;
          trigger.setAttribute('aria-expanded', 'true');
          var rect = trigger.getBoundingClientRect();
          list.style.width = rect.width + 'px';
          list.style.left = rect.left + 'px';
          var below = window.innerHeight - rect.bottom - 8;
          var above = rect.top - 8;
          var upwards = below < Math.min(240, list.scrollHeight) && above > below;
          list.style.maxHeight = Math.max(40, Math.min(240, upwards ? above : below)) + 'px';
          list.style.top = (upwards ? rect.top - list.getBoundingClientRect().height - 4 : rect.bottom + 4) + 'px';
          open = {list: list, trigger: trigger, wrapper: wrapper};
          var selected = buttons[select.selectedIndex];
          var focusOption = selected && !selected.disabled ? selected : buttons.find(function (button) { return !button.disabled; });
          if (focusOption) focusOption.focus();
        }
        trigger.addEventListener('click', function () { open && open.trigger === trigger ? close(false) : show(); });
        trigger.addEventListener('keydown', function (event) {
          if (event.key === 'ArrowDown' || event.key === 'ArrowUp') { event.preventDefault(); show(); }
        });
        list.addEventListener('keydown', function (event) {
          var enabled = buttons.filter(function (button) { return !button.disabled; });
          var index = enabled.indexOf(document.activeElement);
          var target = null;
          if (event.key === 'ArrowDown') target = enabled[(index + 1) % enabled.length];
          if (event.key === 'ArrowUp') target = enabled[(index - 1 + enabled.length) % enabled.length];
          if (event.key === 'Home') target = enabled[0];
          if (event.key === 'End') target = enabled[enabled.length - 1];
          if (event.key.length === 1 && event.key !== ' ') target = enabled.find(function (button) { return button.textContent.toLowerCase().startsWith(event.key.toLowerCase()); });
          if (target) { event.preventDefault(); target.focus(); }
          if (event.key === 'Tab') close(true);
        });
        select.addEventListener('change', sync);
        select.addEventListener('invalid', function (event) { event.preventDefault(); trigger.focus(); });
        if (select.form) select.form.addEventListener('reset', function () { window.setTimeout(sync, 0); });
        select.classList.add('ui-visually-hidden');
        select.tabIndex = -1;
        select.setAttribute('aria-hidden', 'true');
        select.after(trigger, list);
        sync();
      });
    }
    init(document);
    new MutationObserver(function () { init(document); }).observe(document.body, {childList: true, subtree: true});
    document.addEventListener('click', function (event) { if (open && !open.wrapper.contains(event.target)) close(false); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && open) { event.preventDefault(); close(true); } });
    window.addEventListener('resize', function () { close(false); });
    document.addEventListener('scroll', function (event) { if (open && !open.list.contains(event.target)) close(false); }, true);
  });
})();
