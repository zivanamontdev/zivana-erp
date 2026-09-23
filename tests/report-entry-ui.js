// Minimal DOM contract test for report progress; browser layout is tested separately.
const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
const events = {};
const globalEvents = {};
const fields = [{value: ''}, {value: ''}];
const count = {};
const progress = {style: {}};
const buttons = [0, 1].map(() => ({disabled: false, classList: {toggle() {}}}));
const form = {
  querySelectorAll(selector) { return selector === 'select[data-report-value]' ? fields : buttons; },
  querySelector(selector) { return selector === '[data-report-count]' ? count : selector === '[data-report-progress]' ? progress : null; },
  addEventListener(name, callback) { events[name] = callback; }
};
vm.runInNewContext(fs.readFileSync('public/assets/js/report-entry.js', 'utf8'), {
  document: {querySelector() {return form;}, addEventListener(name, callback) {callback();}},
  window: {addEventListener(name, callback) {globalEvents[name] = callback;}}
});
assert(buttons.every(button => button.disabled));
assert.strictEqual(count.textContent, '0 dari 2');
fields[0].value = '1'; fields[1].value = '2';
events.change({target: {matches() {return true;}}});
assert(buttons.every(button => !button.disabled));
assert.strictEqual(progress.style.width, '100%');
let prevented = false;
globalEvents.beforeunload({preventDefault() {prevented = true;}});
assert(prevented);
fields[1].value = '';
events.change({target: {matches() {return true;}}});
assert(buttons.every(button => button.disabled));
events.submit(); prevented = false;
globalEvents.beforeunload({preventDefault() {prevented = true;}});
assert(!prevented);
console.log('PASS: live progress, desktop/mobile submit gating, clearing selections and unsaved-change warning.');
