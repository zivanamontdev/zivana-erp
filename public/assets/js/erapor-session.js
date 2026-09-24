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

    function testRowValue(row) {
      var read = function (key) {
        var input = row.querySelector('[data-ummi-test-field="' + key + '"]');
        return input ? input.value : '';
      };
      var orderText = read('urutan');
      var date = read('tanggal_tes');
      var volume = read('jilid').trim();
      var grade = read('nilai');
      if (!/^\d+$/.test(orderText) || !date || !volume || !grade) return null;
      var order = Number(orderText);
      if (!Number.isSafeInteger(order) || order < 1 || order > 2147483647) return null;
      return {urutan: order, tanggal_tes: date, jilid: volume, nilai: grade};
    }

    function hasIncompleteTestRows() {
      return Array.from(root.querySelectorAll('[data-erapor-entry="UMMI_TEST"]')).some(function (row) {
        return row.dataset.eraporIncomplete === 'true' && row.dataset.eraporDeleteTest !== 'true';
      });
    }

    function updateConfirmState() {
      setConfirmDisabled(!serverCanConfirm || pending.size > 0 || saving || hasIncompleteTestRows());
    }

    function updateUmmiReadingCount(card) {
      var count = card.querySelector('[data-ummi-reading-count]');
      if (!count) return;
      var total = Number(count.dataset.total || 0);
      var filled = Array.from(card.querySelectorAll('[data-ummi-reading]')).filter(function (field) { return field.value !== ''; }).length;
      count.dataset.filled = String(filled);
      count.textContent = 'Terisi ' + filled + ' dari ' + total + ' materi';
    }

    function valueOf(field) {
      if (field.dataset.eraporEntry === 'UMMI_TEST') {
        if (field.dataset.eraporDeleteTest === 'true') return null;
        return testRowValue(field);
      }
      if (field.type === 'checkbox') return Boolean(field.checked);
      if (field.tagName === 'SELECT') {
        if (field.value === '') return null;
        return field.dataset.eraporEntry === 'RTS' ? Number(field.value) : field.value;
      }
      return /^[\s\p{Z}]*$/u.test(field.value) ? null : field.value;
    }

    function expectedOf(field) {
      var value = field.dataset.savedValue;
      if (value === undefined || value === '') return null;
      if (field.dataset.eraporEntry === 'UMMI_TEST') {
        try { return JSON.parse(value); } catch (error) { return null; }
      }
      if (field.dataset.eraporKey === 'mulai_pra_tk') return value === 'true';
      return field.dataset.eraporEntry === 'RTS' ? Number(value) : value;
    }

    function setStatus(message, state) {
      status.textContent = message;
      status.dataset.state = state || 'neutral';
    }

    function setConfirmDisabled(disabled) {
      disabled = disabled || hasIncompleteTestRows();
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
      var value = valueOf(field);
      var expected = expectedOf(field);
      if (field.dataset.eraporEntry === 'RTS') {
        return {
          indikator_id: Number(field.dataset.eraporIndicatorId),
          nilai: value,
          expected: expected
        };
      }
      return {key: field.dataset.eraporKey, value: value, expected: expected};
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
      updateConfirmState();
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
      var changes = fields.map(payloadFor);
      var url = apiUrl + '/dokumen/' + encodeURIComponent(documentId) + '/simpan';
      saving = true;
      setConfirmDisabled(true);
      setStatus('Menyimpan perubahan…', 'saving');

      try {
        var data = await request(url, {changes: changes});
        fields.forEach(function (field, index) {
          var saved = changes[index].value;
          if (field.dataset.eraporEntry === 'UMMI_TEST') {
            if (saved === null && field.dataset.eraporDeleteTest === 'true') {
              field.remove();
              return;
            }
            field.dataset.savedValue = saved === null ? '' : JSON.stringify(saved);
            field.dataset.eraporIncomplete = 'false';
            field.dataset.eraporDeleteTest = 'false';
            var testStatus = field.querySelector('[data-ummi-test-status]');
            if (testStatus) testStatus.textContent = 'Tersimpan.';
          } else {
            field.dataset.savedValue = saved === null ? '' : String(saved);
          }
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
        if (!blocked) updateConfirmState();
      }
    }

    function handleTestRowChange(row) {
      if (blocked || row.dataset.eraporDeleteTest === 'true') return;
      var value = testRowValue(row);
      var statusLine = row.querySelector('[data-ummi-test-status]');
      if (!value) {
        row.dataset.eraporIncomplete = 'true';
        pending.delete(row);
        if (statusLine) statusLine.textContent = 'Lengkapi urutan, tanggal, jilid, dan nilai untuk menyimpan.';
        updateConfirmState();
        if (pending.size === 0 && timer) { window.clearTimeout(timer); timer = null; }
        setStatus('Lengkapi atau hapus baris tes Ummi yang belum lengkap sebelum menyelesaikan rapor.', 'pending');
        return;
      }
      row.dataset.eraporIncomplete = 'false';
      if (statusLine) statusLine.textContent = 'Perubahan akan disimpan otomatis.';
      noteChange(row);
    }

    function newTestToken() {
      var bytes = new Uint8Array(16);
      if (window.crypto && window.crypto.getRandomValues) window.crypto.getRandomValues(bytes);
      else for (var i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
      return Array.from(bytes).map(function (byte) { return byte.toString(16).padStart(2, '0'); }).join('');
    }

    root.addEventListener('input', function (event) {
      var testRow = event.target.closest('[data-erapor-entry="UMMI_TEST"]');
      if (testRow) { handleTestRowChange(testRow); return; }
      var field = event.target.closest('[data-erapor-entry]');
      if (field && field.tagName !== 'SELECT' && field.type !== 'checkbox') noteChange(field);
    });

    root.addEventListener('change', function (event) {
      var testRow = event.target.closest('[data-erapor-entry="UMMI_TEST"]');
      if (testRow) { handleTestRowChange(testRow); return; }
      var field = event.target.closest('[data-erapor-entry]');
      if (!field) return;
      if (field.dataset.eraporPraToggle === 'true') {
        var documentCard = field.closest('[data-erapor-document]');
        if (documentCard) documentCard.querySelectorAll('[data-ummi-pra-tk="true"]').forEach(function (volume) { volume.hidden = !field.checked; });
      }
      if (field.dataset.ummiReading === 'true') {
        var ummiCard = field.closest('[data-erapor-document]');
        if (ummiCard) updateUmmiReadingCount(ummiCard);
      }
      if (field.tagName === 'SELECT' || field.type === 'checkbox') noteChange(field);
    });

    root.addEventListener('click', async function (event) {
      var addTest = event.target.closest('[data-erapor-add-test]');
      if (addTest) {
        if (blocked || saving) return;
        if (pending.size) {
          setStatus('Tunggu sampai perubahan lain tersimpan sebelum menambah baris tes.', 'pending');
          return;
        }
        var documentCard = addTest.closest('[data-erapor-document]');
        var template = documentCard && documentCard.querySelector('template[data-ummi-test-template]');
        var list = documentCard && documentCard.querySelector('[data-ummi-test-list]');
        if (!template || !list) return;
        var holder = document.createElement('div');
        holder.innerHTML = template.innerHTML.replaceAll('__TOKEN__', newTestToken());
        var row = holder.firstElementChild;
        if (!row) return;
        row.dataset.eraporKey = 'tes:' + row.querySelector('[data-ummi-test-field="urutan"]').name.slice(-32);
        list.appendChild(row);
        updateConfirmState();
        var orderInput = row.querySelector('[data-ummi-test-field="urutan"]');
        if (orderInput) orderInput.focus();
        setStatus('Baris tes ditambahkan. Lengkapi seluruh kolom untuk menyimpannya.', 'pending');
        return;
      }

      var removeTest = event.target.closest('[data-erapor-remove-test]');
      if (removeTest) {
        var testRow = removeTest.closest('[data-erapor-entry="UMMI_TEST"]');
        if (!testRow || blocked || saving) return;
        if (!testRow.dataset.savedValue) {
          pending.delete(testRow);
          testRow.remove();
          updateConfirmState();
          return;
        }
        if (!window.confirm('Hapus catatan tes ini dari Rapor Ummi?')) return;
        testRow.dataset.eraporDeleteTest = 'true';
        testRow.dataset.eraporIncomplete = 'false';
        var testStatus = testRow.querySelector('[data-ummi-test-status]');
        if (testStatus) testStatus.textContent = 'Penghapusan akan disimpan…';
        noteChange(testRow);
        return;
      }

      var initUmmi = event.target.closest('[data-erapor-ummi-init]');
      if (initUmmi) {
        if (blocked || saving || pending.size) {
          setStatus('Simpan perubahan yang tertunda terlebih dahulu, lalu mulai pengisian Ummi.', 'pending');
          return;
        }
        saving = true;
        initUmmi.disabled = true;
        setConfirmDisabled(true);
        setStatus('Menyiapkan setelan periode Ummi…', 'saving');
        try {
          await request(apiUrl + '/dokumen/' + encodeURIComponent(initUmmi.dataset.eraporDocumentId) + '/simpan', {changes: []});
          window.location.reload();
          return;
        } catch (error) {
          if (error.status === 401) { window.location.assign(root.dataset.loginUrl); return; }
          blocked = error.status === 409 || error.status === 419 || error.status === 422 || error.status === 403;
          setStatus(error.message || 'Setelan periode Ummi belum dapat dibuat.', 'error');
          if (blocked) recovery.hidden = false;
        } finally {
          saving = false;
          initUmmi.disabled = blocked;
          if (!blocked) updateConfirmState();
        }
        return;
      }

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
      if (hasIncompleteTestRows()) {
        setStatus('Lengkapi atau hapus baris tes Ummi yang belum lengkap sebelum menyelesaikan rapor.', 'error');
        return;
      }
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
      root.querySelectorAll('[data-erapor-document]').forEach(function (card) { updateUmmiReadingCount(card); });
    } catch (error) {
      setStatus('Ringkasan sesi tidak dapat dibaca. Muat ulang halaman sebelum mengubah nilai.', 'error');
      blocked = true;
    }
  });
})();
