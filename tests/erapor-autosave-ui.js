// Real editor JS with a small DOM/network fixture. No browser dependencies or application DB writes.
const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync('public/assets/js/erapor-session.js', 'utf8');
const sleep = ms => new Promise(resolve => setTimeout(resolve, ms));
async function until(predicate) {
  for (let i = 0; i < 200; i++) { if (predicate()) return; await sleep(2); }
  throw new Error('Timed out waiting for editor fixture');
}
function fixture(mode = 'normal', kind = 'RTS') {
  const initial = kind === 'RTS' ? 1 : 'A';
  const rootEvents = {}, docEvents = {}, windowEvents = {}, timers = new Set();
  const fields = [1, 2].map((id, index) => ({
    dataset: {eraporEntry: kind, eraporRadio: kind === 'RTS' ? 'true' : 'false', eraporDocumentId: '1', eraporIndicatorId: String(id), eraporKey: 'nilai:' + id, savedValue: index ? '' : String(initial)},
    tagName: kind === 'RTS' ? 'DIV' : 'SELECT',
    value: index ? (kind === 'RTS' ? null : '') : initial,
    querySelector() { return this.value === null ? null : {value: String(this.value)}; },
    closest(selector) { return selector === '[data-erapor-entry]' ? this : null; },
    removeAttribute() {}
  }));
  const status = {dataset: {}}, recovery = {hidden: true}, buttons = [{disabled: true, classList: {toggle() {}}}];
  const ppiClasses = new Set(['ui-button--disabled']);
  const ppiButton = {disabled: true, classList: {toggle(name, on) { on ? ppiClasses.add(name) : ppiClasses.delete(name); }, contains(name) { return ppiClasses.has(name); }}};
  const sessionStatus = kind === 'PPI' ? 'SELESAI' : 'BELUM_DIISI';
  const ppiState = values => ({included: false, separate: true, status: 'BELUM_DIAJUKAN',
    can_submit: values[1] !== null && values[2] !== null, progress: {required: 2, filled: Object.values(values).filter(v => v !== null).length}});
  const root = {
    dataset: {apiUrl: '/fixture', csrfToken: 'synthetic', canSubmit: 'true'},
    querySelector(selector) {
      if (selector === '[data-erapor-status]') return status;
      if (selector === '[data-erapor-recovery]') return recovery;
      if (selector === '[data-erapor-initial-state]') return {textContent: JSON.stringify({session: {status: sessionStatus}, completion: {documents: []}, ...(kind === 'PPI' ? {ppi: ppiState({1: initial, 2: null})} : {})})};
      return null;
    },
    querySelectorAll(selector) {
      if (selector === '[data-erapor-entry]:not([type="radio"])') return fields;
      if (selector === '[data-erapor-confirm]') return buttons;
      if (selector === '[data-erapor-submit-ppi]' && kind === 'PPI') return [ppiButton];
      return [];
    },
    addEventListener(name, callback) { rootEvents[name] = callback; }
  };
  const state = {values: {1: initial, 2: null}, calls: [], failures: 0, mode, draftCount: 0, navigator: {onLine: true}};
  const window = {
    setTimeout(fn, ms) {
      // Accelerate debounce/backoff; retain a long request timeout for deliberate network delays.
      const id = setTimeout(() => { timers.delete(id); fn(); }, ms === 20000 ? 200 : Math.max(1, Math.round(ms / 100)));
      timers.add(id); return id;
    },
    clearTimeout(id) { clearTimeout(id); timers.delete(id); },
    addEventListener(name, callback) { windowEvents[name] = callback; },
    location: {hash: '', assign() { throw new Error('Unsaved changes must not auto-navigate away'); }, reload() { state.reloaded = true; state.reloadWarning = state.dirtyWarning(); }}
  };
  const fetch = async (url, options) => {
    if (url.endsWith('/draft')) {
      state.draftCount++;
      return {ok: true, status: 200, json: async () => ({ok: true, data: {draft_at: '2026-10-18 12:00:00'}})};
    }
    const changes = JSON.parse(options.body).changes;
    state.calls.push(changes);
    if (state.mode === 'unavailable') return {ok: false, status: 503, json: async () => ({ok: false, error: {message: 'Busy'}})};
    if (state.mode === 'conflict') state.values[1] = 4;
    const idOf = c => c.indikator_id || Number(c.key.split(':').pop());
    const valueOf = c => 'nilai' in c ? c.nilai : c.value;
    if (changes.some(c => state.values[idOf(c)] !== c.expected && state.values[idOf(c)] !== valueOf(c))) {
      return {ok: false, status: 409, json: async () => ({ok: false, error: {message: 'Conflict'}})};
    }
    changes.forEach(c => { state.values[idOf(c)] = valueOf(c); });
    if (state.mode === 'lost-ack' && state.failures++ === 0) throw new TypeError('Connection lost AFTER commit');
    if (state.mode === 'slow') await sleep(25);
    return {ok: true, status: 200, json: async () => ({ok: true, data: {session: {status: sessionStatus}, completion: {documents: []}, ...(kind === 'PPI' ? {ppi: ppiState(state.values)} : {})}})};
  };
  vm.runInNewContext(source, {
    document: {querySelector() { return root; }, querySelectorAll() { return []; }, addEventListener(name, callback) { (docEvents[name] ||= []).push(callback); }},
    window, navigator: state.navigator, fetch, AbortController, CSS: {escape: String}, Map, Set, Promise, console
  });
  docEvents.DOMContentLoaded.forEach(callback => callback());
  state.change = (id, value) => { const field = fields[id - 1]; field.value = value; rootEvents.change({target: field}); };
  state.click = selector => docEvents.click.forEach(callback => callback({target: {closest: query => query === selector ? {} : null}}));
  state.initUmmi = () => rootEvents.click({target: {closest: query => query === '[data-erapor-ummi-init]' ? {dataset: {eraporDocumentId: '99'}, disabled: false} : null}});
  state.online = () => { state.navigator.onLine = true; windowEvents.online(); };
  state.dirtyWarning = () => { let warned = false; windowEvents.beforeunload({preventDefault() { warned = true; }}); return warned; };
  state.status = status; state.recovery = recovery; state.ppiButton = ppiButton;
  state.done = () => status.dataset.state === 'success';
  state.dispose = () => timers.forEach(clearTimeout);
  return state;
}
(async () => {
  let f = fixture();
  try {
    f.change(1, 2); await until(f.done);
    f.change(1, 3); f.change(2, 2); await until(() => f.done() && f.values[1] === 3);
    assert.equal(f.values[2], 2); assert.equal(f.dirtyWarning(), false);
  } finally { f.dispose(); }
  f = fixture('slow');
  try {
    f.change(1, 2); await until(() => f.calls.length === 1);
    f.change(1, 3); f.change(2, 2); await until(() => f.done() && f.values[1] === 3);
    assert.equal(f.values[2], 2); assert.equal(f.calls[1][0].expected, 2);
  } finally { f.dispose(); }
  f = fixture('lost-ack');
  try {
    f.change(1, 2); await until(() => f.calls.length === 1);
    f.change(1, 3); f.change(2, 2); assert(f.dirtyWarning());
    await until(() => f.done() && f.values[1] === 3);
    assert.deepEqual(f.calls[1], f.calls[0], 'Retry must replay the committed batch, not newer input');
    assert.equal(f.values[2], 2); assert.equal(f.recovery.hidden, true); assert.equal(f.dirtyWarning(), false);
  } finally { f.dispose(); }
  f = fixture('unavailable');
  try {
    f.change(1, 2); await until(() => f.calls.length === 4); await sleep(30);
    assert.equal(f.calls.length, 4, 'Do not endlessly hammer an unavailable server');
    f.change(1, 3); f.change(2, 2); await sleep(20); assert.equal(f.calls.length, 4);
    assert(f.dirtyWarning()); f.mode = 'normal';
    f.click('[data-erapor-draft]'); await until(() => f.draftCount === 1);
    assert.equal(f.values[1], 3); assert.equal(f.values[2], 2);
  } finally { f.dispose(); }
  f = fixture('conflict');
  try {
    f.change(1, 2); await until(() => f.recovery.hidden === false);
    f.change(2, 2); await sleep(25);
    assert.equal(f.calls.length, 1); assert.equal(f.values[1], 4, 'Do not silently overwrite another tab');
    assert.equal(f.values[2], null); assert(f.dirtyWarning()); assert.equal(f.status.dataset.state, 'error');
  } finally { f.dispose(); }
  f = fixture();
  try {
    f.navigator.onLine = false; f.change(1, 2); await sleep(15); assert.equal(f.calls.length, 0);
    f.online(); await until(f.done); assert.equal(f.values[1], 2);
  } finally { f.dispose(); }
  f = fixture();
  try {
    await f.initUmmi();
    assert.equal(f.reloaded, true);
    assert.equal(f.reloadWarning, false, 'Acknowledged Ummi initialization must not trigger an unsaved-change unload prompt');
  } finally { f.dispose(); }
  for (const kind of ['AGAMA', 'BING', 'UMMI', 'PPI']) {
    f = fixture('lost-ack', kind);
    try {
      f.change(1, 'B'); await until(() => f.calls.length === 1);
      f.change(1, 'C'); f.change(2, 'B'); await until(() => f.done() && f.values[1] === 'C');
      assert.deepEqual(f.calls[1], f.calls[0], kind + ' also retries its original value/expected payload');
      assert.equal(f.values[2], 'B');
    } finally { f.dispose(); }
  }
  f = fixture('slow', 'PPI');
  try {
    assert.equal(f.ppiButton.disabled, true);
    f.change(2, 'B');
    assert.equal(f.ppiButton.disabled, true, 'Pending PPI edits cannot be submitted before acknowledgement');
    await until(() => !f.ppiButton.disabled);
    assert(f.ppiButton.classList.contains('ui-button--primary'), 'Completed PPI must look enabled, not stay visually grey');
    assert.equal(f.ppiButton.classList.contains('ui-button--disabled'), false);
    f.change(1, '');
    assert.equal(f.ppiButton.disabled, true, 'A new edit immediately disables the previously enabled PPI submission');
    assert(f.ppiButton.classList.contains('ui-button--disabled'));
    await until(f.done);
    assert.equal(f.ppiButton.disabled, true, 'Incomplete PPI remains disabled after server acknowledgement');
    assert.equal(f.ppiButton.classList.contains('ui-button--primary'), false);
  } finally { f.dispose(); }
  console.log('PASS: eRapor autosave (repeat edits, lost ACK, bounded retry, draft, conflict, reconnect, unload warning, PPI submission state and styling).');
})().catch(error => { console.error(error); process.exitCode = 1; });
