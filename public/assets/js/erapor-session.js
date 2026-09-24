(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-erapor-editor]');
    if (!root) return;

    var apiUrl = root.dataset.apiUrl;
    var token = root.dataset.csrfToken;
    var status = root.querySelector('[data-erapor-status]');
    var recovery = root.querySelector('[data-erapor-recovery]');
    var pending = new Map();
    var timer = null;
    var saving = false;
    var blocked = false;
    var serverCanConfirm = false;

    function valueOf(field) {
      if (field.tagName === 'SELECT') {
        if (field.value === '') return null;
        return field.dataset.eraporEntry === 'RTS' ? Number(field.value) : field.value;
      }
      return /^[\s\p{Z}]*$/u.test(field.value) ? null : field.value;
    }

    function expectedOf(field) {
      var value = field.dataset.savedValue;
      if (value === undefined || value === '') return null;
      return field.dataset.eraporEntry === 'RTS' ? Number(value) : value;
    }

    function setStatus(message, state) {
      status.textContent = message;
      status.dataset.state = state || 'neutral';
    }

    function setConfirmDisabled(disabled) {
      root.querySelectorAll('[data-erapor-confirm]').forEach(function (button) {
        button.disabled = disabled;
        button.classList.toggle('ui-button--disabled', disabled);
        button.classList.toggle('ui-button--primary', !disabled);
      });
    }

    function schedule() {
      if (timer) window.clearTimeout(timer);
      timer = window.setTimeout(flush, 550);
    }

    function noteChange(field) {
      if (blocked || field.disabled) return;
      pending.set(field, true);
      setConfirmDisabled(true);
      setStatus('Ada perubahan yang belum disimpan…', 'pending');
      schedule();
    }

    function payloadFor(field) {
      var type = field.dataset.eraporEntry;
      var key = field.dataset.eraporKey;
      var value = valueOf(field);
      var expected = expectedOf(field);
      if (type === 'RTS') {
        return {
          indikator_id: Number(field.dataset.eraporIndicatorId),
          nilai: value,
          expected: expected
        };
      }
      return {key: key, value: value, expected: expected};
    }

    function updateProgress(completion) {
      if (!completion || !Array.isArray(completion.documents)) return;
      var totalRequired = 0;
      var totalFilled = 0;
      completion.documents.forEach(function (item) {
        totalRequired += Number(item.required || 0);
        totalFilled += Number(item.filled || 0);
        var card = root.querySelector('[data-erapor-type="' + CSS.escape(item.jenis) + '"]');
        if (!card) return;
        card.dataset.eraporRequired = item.required;
        card.dataset.eraporFilled = item.filled;
        var label = card.querySelector('[data-erapor-document-progress]');
        if (label) label.textContent = item.filled + ' / ' + item.required + ' terisi';
      });
      var count = root.querySelector('[data-erapor-overall-count]');
      var bar = root.querySelector('[data-erapor-overall-bar]');
      if (count) count.textContent = totalFilled + ' dari ' + totalRequired;
      if (bar) bar.style.width = (totalRequired ? Math.min(100, Math.round(totalFilled / totalRequired * 100)) : 0) + '%';
    }

    function applyState(data) {
      if (!data) return;
      updateProgress(data.completion);
      if (data.session && data.session.status) {
        var statusLine = root.querySelector('.pengisian-rapor-warning');
        if (statusLine && !statusLine.textContent.includes('Status ')) {
          statusLine.textContent += ' · Status ' + data.session.status.replaceAll('_', ' ');
        }
      }
      serverCanConfirm = root.dataset.canSubmit === 'true'
        && data.capabilities && data.capabilities.can_confirm_filled === true;
      setConfirmDisabled(!serverCanConfirm || pending.size > 0 || saving);
    }

    async function request(url, body) {
      var headers = {'Accept': 'application/json'};
      var options = {method: 'GET', credentials: 'same-origin', cache: 'no-store', headers: headers};
      if (body !== undefined) {
        headers['Content-Type'] = 'application/json';
        headers['X-CSRF-Token'] = token;
        options.method = 'POST';
        options.body = JSON.stringify(body);
      }
      var response = await fetch(url, options);
      var json = await response.json();
      if (typeof json.csrf_token === 'string') token = json.csrf_token;
      root.dataset.csrfToken = token;
      if (!response.ok || json.ok !== true) {
        var error = new Error((json.error && json.error.message) || 'Permintaan tidak berhasil.');
        error.status = response.status;
        error.code = json.error && json.error.code;
        throw error;
      }
      return json.data;
    }

    async function flush() {
      if (saving || blocked || pending.size === 0) return;
      if (timer) window.clearTimeout(timer);
      timer = null;

      var first = pending.keys().next().value;
      var documentId = first.dataset.eraporDocumentId;
      var fields = Array.from(pending.keys()).filter(function (field) {
        return field.dataset.eraporDocumentId === documentId;
      });
      fields.forEach(function (field) { pending.delete(field); });
      var type = first.dataset.eraporEntry;
      var changes = fields.map(payloadFor);
      var url = apiUrl + '/dokumen/' + encodeURIComponent(documentId) + '/simpan';
      saving = true;
      setConfirmDisabled(true);
      setStatus('Menyimpan perubahan…', 'saving');

      try {
        var data = await request(url, {changes: changes});
        fields.forEach(function (field, index) {
          var saved = changes[index].value;
          field.dataset.savedValue = saved === null ? '' : String(saved);
          field.removeAttribute('aria-invalid');
        });
        applyState(data);
        if (pending.size === 0) setStatus('Semua perubahan tersimpan.', 'success');
        else setStatus('Perubahan tersimpan. Menyimpan perubahan berikutnya…', 'pending');
      } catch (error) {
        if (error.status === 401) {
          window.location.assign(root.dataset.loginUrl);
          return;
        }
        fields.forEach(function (field) { if (!pending.has(field)) pending.set(field, true); });
        blocked = error.status === 409 || error.status === 419 || error.status === 422 || error.status === 403;
        if (error.status === 409) {
          setStatus('Data rapor berubah di sisi server. Isian yang tampak di layar belum tersimpan; salin perubahan yang diperlukan sebelum memuat ulang.', 'error');
          recovery.hidden = false;
        } else {
          setStatus(error.message || 'Gagal menyimpan. Periksa koneksi lalu coba lagi.', 'error');
          recovery.hidden = false;
        }
      } finally {
        saving = false;
        if (!blocked && pending.size) schedule();
        if (!blocked) setConfirmDisabled(!serverCanConfirm || pending.size > 0);
      }
    }

    root.addEventListener('input', function (event) {
      var field = event.target.closest('[data-erapor-entry]');
      if (field && field.tagName !== 'SELECT') noteChange(field);
    });
    root.addEventListener('change', function (event) {
      var field = event.target.closest('[data-erapor-entry]');
      if (field && field.tagName === 'SELECT') noteChange(field);
    });

    root.addEventListener('click', async function (event) {
      var retry = event.target.closest('[data-erapor-retry]');
      if (retry) {
        blocked = false;
        recovery.hidden = true;
        schedule();
        return;
      }
      var reload = event.target.closest('[data-erapor-reload]');
      if (reload) {
        if (window.confirm('Muat ulang sesi? Perubahan yang belum tersimpan di halaman ini akan hilang.')) window.location.reload();
        return;
      }
      var confirmButton = event.target.closest('[data-erapor-confirm]');
      if (!confirmButton || confirmButton.disabled || pending.size || saving || blocked) return;
      if (!window.confirm('Kirim rapor ini untuk menandai bahwa pengisian guru telah selesai?')) return;
      setConfirmDisabled(true);
      try {
        var result = await request(apiUrl + '/konfirmasi-isi', {});
        if (result.result === 'confirmed' || result.result === 'already_confirmed') {
          window.location.reload();
          return;
        }
        applyState(result);
        setStatus('Masih ada isian wajib yang belum lengkap. Periksa kembali progress tiap dokumen.', 'error');
      } catch (error) {
        setStatus(error.message || 'Konfirmasi belum dapat dilakukan.', 'error');
        setConfirmDisabled(!serverCanConfirm);
      }
    });

    try {
      var initial = JSON.parse(root.querySelector('[data-erapor-initial-state]').textContent);
      applyState(initial);
    } catch (error) {
      setStatus('Ringkasan sesi tidak dapat dibaca. Muat ulang halaman sebelum mengubah nilai.', 'error');
      blocked = true;
    }
  });
})();
