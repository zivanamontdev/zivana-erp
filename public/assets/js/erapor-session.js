(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {
    var root = document.querySelector('[data-erapor-editor]');
    if (!root) return;

    var apiUrl = root.dataset.apiUrl;
    var token = root.dataset.csrfToken;
    var status = root.querySelector('[data-erapor-status]');
    var recovery = root.querySelector('[data-erapor-recovery]');
    // Sisakan ruang setinggi bar navigasi mobile (fixed) supaya isian terakhir tidak tertutup.
    var actionBar = root.querySelector('[data-erapor-pager]');
    if (actionBar && window.ResizeObserver) {
      new window.ResizeObserver(function () {
        root.style.setProperty('--report-actions-height', actionBar.getBoundingClientRect().height + 'px');
      }).observe(actionBar);
    }
    var pending = new Map();
    var timer = null;
    var saving = false;
    var blocked = false;
    var serverStatus = null;
    var lastCompletion = null;
    var pages = Array.from(root.querySelectorAll('[data-erapor-page]'));
    var steps = Array.from(root.querySelectorAll('[data-erapor-step]'));
    var currentPage = 0;

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

    // No. baris tes = posisi baris (1, 2, 3, ...), diperbarui saat baris ditambah/dihapus.
    function renumberTests(list) {
      if (!list) return;
      Array.from(list.querySelectorAll('[data-erapor-entry="UMMI_TEST"]')).forEach(function (row, index) {
        var no = row.querySelector('[data-ummi-test-no]');
        if (no) no.textContent = String(index + 1);
      });
    }

    function hasIncompleteTestRows() {
      return Array.from(root.querySelectorAll('[data-erapor-entry="UMMI_TEST"]')).some(function (row) {
        return row.dataset.eraporIncomplete === 'true' && row.dataset.eraporDeleteTest !== 'true';
      });
    }

    // Rapor wajib yang belum lengkap menurut server (rapor opsional seperti Ummi punya required 0).
    function incompleteDocuments() {
      return ((lastCompletion && lastCompletion.documents) || []).filter(function (item) {
        return Number(item.required || 0) > 0 && Number(item.filled || 0) < Number(item.required);
      });
    }

    function updateConfirmState() {
      var busy = pending.size > 0 || saving || hasIncompleteTestRows();
      var incomplete = incompleteDocuments();
      setConfirmDisabled(serverStatus !== 'BELUM_DIISI' || busy || incomplete.length > 0);
      setReceptionDisabled(serverStatus !== 'TELAH_DIISI' || busy || incomplete.length > 0);
      updatePagerHint(incomplete);
    }

    // Di halaman terakhir, jelaskan mengapa Selesaikan Rapor belum aktif dan tawarkan penanda isian kosong.
    function updatePagerHint(incomplete) {
      var hint = root.querySelector('[data-erapor-pager-hint]');
      if (!hint) return;
      var submit = root.querySelector('[data-erapor-pager-submit]');
      var show = Boolean(submit) && currentPage === pages.length - 1 && incomplete.length > 0;
      hint.hidden = !show;
      if (!show) return;
      var names = incomplete.map(function (item) {
        var page = root.querySelector('[data-erapor-page][data-erapor-type="' + CSS.escape(item.jenis) + '"]');
        var heading = page && page.querySelector('h2');
        return (heading ? heading.textContent.trim() : item.jenis) + ' (' + item.filled + '/' + item.required + ')';
      });
      hint.textContent = 'Lengkapi dulu: ' + names.join(', ') + '. ';
      var showButton = document.createElement('button');
      showButton.type = 'button';
      showButton.className = 'erapor-pager-hint-link';
      showButton.dataset.eraporShowMissing = 'true';
      showButton.textContent = 'Tunjukkan yang belum diisi';
      hint.appendChild(showButton);
    }

    function setReceptionDisabled(disabled) {
      root.querySelectorAll('[data-erapor-confirm-reception]').forEach(function (button) {
        button.disabled = disabled;
        button.classList.toggle('ui-button--disabled', disabled);
        button.classList.toggle('ui-button--primary', !disabled);
      });
    }

    // Centang "mulai dari PRA TK" menampilkan Jilid PRA TK; hapus centang menyembunyikannya dan mengosongkan
    // nilainya (server menghapus nilai PRA TK saat setelan disimpan), sehingga harus diisi ulang bila dicentang lagi.
    async function handlePraToggle(field) {
      var card = field.closest('[data-erapor-document]');
      if (!card) return;
      var volumes = Array.from(card.querySelectorAll('[data-ummi-pra-tk="true"]'));
      var praSelects = [];
      volumes.forEach(function (volume) { praSelects = praSelects.concat(Array.from(volume.querySelectorAll('select[data-ummi-reading]'))); });
      if (!field.checked && praSelects.some(function (select) { return select.value !== ''; })) {
        if (!(await askConfirm('Hapus Nilai PRA TK?', 'Nilai Jilid PRA TK yang sudah diisi akan dihapus. Centang lagi bila perlu mengisinya ulang.', 'Hapus'))) {
          field.checked = true;
          return;
        }
      }
      volumes.forEach(function (volume) { volume.hidden = !field.checked; });
      if (!field.checked) {
        praSelects.forEach(function (select) {
          pending.delete(select);
          select.value = '';
          select.dataset.savedValue = '';
          select.removeAttribute('aria-invalid');
          select.dispatchEvent(new CustomEvent('change', {bubbles: false}));
        });
        updateUmmiReadingCount(card);
      }
      noteChange(field);
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
      if (field.dataset.eraporRadio === 'true') {
        var checked = field.querySelector('input[type="radio"]:checked');
        return checked ? Number(checked.value) : null;
      }
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
      refreshQuestion(field);
      updateSectionCounts();
      pending.set(field, true);
      setConfirmDisabled(true);
      setReceptionDisabled(true);
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
        var optional = Number(item.required || 0) === 0;
        var stepLabel = root.querySelector('[data-erapor-step-progress="' + CSS.escape(item.jenis) + '"]');
        if (stepLabel) stepLabel.textContent = optional ? 'Opsional' : item.filled + ' / ' + item.required;
        var card = root.querySelector('[data-erapor-page][data-erapor-type="' + CSS.escape(item.jenis) + '"]');
        if (!card) return;
        card.dataset.eraporRequired = item.required;
        card.dataset.eraporFilled = item.filled;
        var label = card.querySelector('[data-erapor-document-progress]');
        if (label) label.textContent = optional ? 'Opsional' : item.filled + ' / ' + item.required + ' terisi';
      });
      var count = root.querySelector('[data-erapor-overall-count]');
      var bar = root.querySelector('[data-erapor-overall-bar]');
      if (count) count.textContent = totalFilled + ' dari ' + totalRequired;
      if (bar) {
        bar.style.width = (totalRequired ? Math.min(100, Math.round(totalFilled / totalRequired * 100)) : 0) + '%';
        if (bar.parentElement) bar.parentElement.setAttribute('aria-valuenow', Math.round(parseFloat(bar.style.width)));
      }
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
      if (data.completion) lastCompletion = data.completion;
      var currentStatus = data.session && data.session.status;
      serverStatus = root.dataset.canSubmit === 'true' ? currentStatus : null;
      updateConfirmState();
    }

    // Token CSRF sekali pakai per sesi login: tab lain atau jeda >1 jam membuat token halaman ini basi.
    // Respons 419 selalu membawa token baru, jadi setiap permintaan dicoba ulang sekali sebelum dianggap gagal.
    async function request(url, body, retried) {
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
      if (response.status === 419 && body !== undefined && !retried && typeof json.csrf_token === 'string') {
        return request(url, body, true);
      }
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
      setReceptionDisabled(true);
      setStatus('Menyimpan perubahan…', 'saving');

      try {
        var data = await request(url, {changes: changes});
        fields.forEach(function (field, index) {
          var saved = changes[index].value;
          if (field.dataset.eraporEntry === 'UMMI_TEST') {
            if (saved === null && field.dataset.eraporDeleteTest === 'true') {
              var parentList = field.parentElement;
              field.remove();
              renumberTests(parentList);
              return;
            }
            field.dataset.savedValue = saved === null ? '' : JSON.stringify(saved);
            field.dataset.eraporIncomplete = 'false';
            field.dataset.eraporDeleteTest = 'false';
            var testStatus = field.querySelector('[data-ummi-test-status]');
            if (testStatus) testStatus.textContent = '';
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
        if (statusLine) statusLine.textContent = '';
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

    // ---- Halaman per jenis rapor (seperti Google Form) ----
    var pager = root.querySelector('[data-erapor-pager]');
    function showPage(index, scrollToTop) {
      if (!pages.length || index < 0 || index >= pages.length) return;
      currentPage = index;
      pages.forEach(function (page, i) { page.hidden = i !== index; });
      steps.forEach(function (step, i) {
        if (i === index) step.setAttribute('aria-current', 'step');
        else step.removeAttribute('aria-current');
      });
      if (pager) {
        var last = index === pages.length - 1;
        pager.querySelector('[data-erapor-pager-label]').textContent = 'Bagian ' + (index + 1) + ' dari ' + pages.length;
        var prev = pager.querySelector('[data-erapor-prev]');
        prev.disabled = index === 0;
        prev.classList.toggle('ui-button--disabled', index === 0);
        prev.classList.toggle('ui-button--outline', index !== 0);
        var submits = pager.querySelectorAll('[data-erapor-pager-submit]');
        var next = pager.querySelector('[data-erapor-next]');
        // Di halaman terakhir Selanjutnya berganti menjadi Selesaikan Rapor (bila status mengizinkan).
        next.hidden = last && submits.length > 0;
        next.disabled = last;
        next.classList.toggle('ui-button--disabled', last);
        next.classList.toggle('ui-button--primary', !last);
        submits.forEach(function (button) { button.hidden = !last; });
        updatePagerHint(incompleteDocuments());
      }
      try { window.history.replaceState(null, '', '#bagian-' + (index + 1)); } catch (error) { /* abaikan */ }
      if (steps[index] && steps[index].parentElement) {
        var nav = steps[index].parentElement;
        nav.scrollLeft = Math.max(0, steps[index].offsetLeft - (nav.clientWidth - steps[index].offsetWidth) / 2);
      }
      if (filterMode !== 'all') applyFilter();
      if (scrollToTop) {
        var anchor = root.querySelector('[data-erapor-steps]') || pages[index];
        window.scrollTo({top: Math.max(0, anchor.getBoundingClientRect().top + window.scrollY - 16), behavior: 'smooth'});
      }
    }

    function openSections(element) {
      for (var node = element.parentElement; node && node !== root; node = node.parentElement) {
        if (node.tagName === 'DETAILS') node.open = true;
      }
    }

    function goTo(element) {
      var page = element.closest('[data-erapor-page]');
      if (page) showPage(pages.indexOf(page), false);
      openSections(element);
      element.scrollIntoView({block: 'center', behavior: 'smooth'});
      var target = element.querySelector('input:not([type="hidden"]):not(:disabled), textarea:not(:disabled), .ui-select-trigger, select:not(:disabled)');
      if (target && target.focus) target.focus({preventScroll: true});
    }

    // ---- Hitungan terisi/total per bagian (judul merah) dan sub bagian (judul oranye) ----
    // Isian wajib; pada bagian tanpa isian wajib (rapor opsional seperti Ummi) semua isian dihitung.
    function sectionEntries(scope) {
      var required = Array.from(scope.querySelectorAll('[data-erapor-entry][aria-required="true"]'));
      if (required.length) return required;
      return Array.from(scope.querySelectorAll('select[data-erapor-entry], textarea[data-erapor-entry], [data-erapor-radio]'));
    }

    function updateSectionCounts() {
      root.querySelectorAll('details.erapor-section').forEach(function (section) {
        var summary = section.querySelector(':scope > summary');
        if (!summary) return;
        var badge = summary.querySelector('[data-erapor-section-count]');
        if (!badge) {
          badge = document.createElement('span');
          badge.className = 'erapor-section-count';
          badge.setAttribute('data-erapor-section-count', '');
          summary.insertBefore(badge, summary.querySelector('.ui-disclosure-chevron'));
        }
        var entries = sectionEntries(section);
        var filled = entries.filter(function (entry) { return valueOf(entry) !== null; }).length;
        badge.textContent = filled + '/' + entries.length;
        badge.hidden = entries.length === 0;
        badge.classList.toggle('is-complete', entries.length > 0 && filled === entries.length);
      });
    }

    // ---- Filter "Semua" / "Belum Diisi" ----
    // Diterapkan saat filter diklik atau pindah halaman (bukan setiap klik jawaban) agar kartu tidak melompat saat diisi.
    var filterMode = 'all';
    function applyFilter() {
      var only = filterMode === 'empty';
      root.querySelectorAll('[data-erapor-filter]').forEach(function (button) {
        var on = button.dataset.eraporFilter === filterMode;
        button.classList.toggle('ui-button--tabular-active', on);
        button.classList.toggle('ui-button--tabular-inactive', !on);
        button.setAttribute('aria-pressed', on ? 'true' : 'false');
      });
      pages.forEach(function (page) {
        page.classList.toggle('is-filtering', only);
        var visible = 0;
        page.querySelectorAll('[data-erapor-question]').forEach(function (card) {
          var entries = sectionEntries(card);
          var hide = only && entries.every(function (entry) { return valueOf(entry) !== null; });
          card.classList.toggle('is-filtered', hide);
          if (!hide) visible++;
        });
        page.querySelectorAll('details.erapor-section').forEach(function (section) {
          section.classList.toggle('is-filtered', only && !section.querySelector('[data-erapor-question]:not(.is-filtered)'));
          if (only && !section.classList.contains('is-filtered')) section.open = true;
        });
        var empty = page.querySelector('[data-erapor-filter-empty]');
        if (!empty) {
          empty = document.createElement('p');
          empty.className = 'erapor-filter-empty';
          empty.setAttribute('data-erapor-filter-empty', '');
          empty.textContent = 'Tidak ada isian yang belum diisi di bagian ini.';
          page.appendChild(empty);
        }
        empty.hidden = !(only && visible === 0);
      });
    }

    // ---- Penanda isian wajib yang belum diisi ----
    function requiredEntries(scope) {
      return Array.from(scope.querySelectorAll('[data-erapor-entry][aria-required="true"]')).filter(function (field) {
        var page = field.closest('[data-erapor-page]');
        return !page || page.dataset.eraporOptional !== 'true';
      });
    }

    function setQuestionInvalid(card, invalid) {
      card.classList.toggle('is-invalid', invalid);
      var message = card.querySelector('[data-erapor-question-error]');
      if (message) message.hidden = !invalid;
    }

    function updateStepFlags() {
      // Judul yang dilipat ikut ditandai agar isian merah di dalamnya tetap terlihat.
      root.querySelectorAll('details.erapor-section').forEach(function (section) {
        section.classList.toggle('has-invalid', Boolean(section.querySelector('[data-erapor-question].is-invalid')));
      });
      pages.forEach(function (page, i) {
        if (steps[i]) steps[i].classList.toggle('has-invalid', Boolean(page.querySelector('[data-erapor-question].is-invalid')));
      });
    }

    function refreshQuestion(field) {
      var card = field.closest('[data-erapor-question]');
      if (!card || !card.classList.contains('is-invalid')) return;
      if (requiredEntries(card).every(function (entry) { return valueOf(entry) !== null; })) {
        setQuestionInvalid(card, false);
        updateStepFlags();
      }
    }

    function markMissing(fields) {
      root.querySelectorAll('[data-erapor-question].is-invalid').forEach(function (card) { setQuestionInvalid(card, false); });
      var first = null;
      fields.forEach(function (field) {
        var card = field.closest('[data-erapor-question]') || field;
        if (card.hasAttribute('data-erapor-question')) setQuestionInvalid(card, true);
        if (!first) first = card;
      });
      updateStepFlags();
      if (first) goTo(first);
      return fields.length;
    }

    // Pemeriksaan di browser hanya untuk menunjukkan lokasi; server tetap penentu kelengkapan.
    function validateRequired() {
      var missing = requiredEntries(root).filter(function (field) { return valueOf(field) === null; });
      if (!markMissing(missing)) return true;
      setStatus(missing.length + ' isian wajib belum diisi. Lengkapi isian bertanda merah sebelum melanjutkan.', 'error');
      return false;
    }

    function markServerMissing(completion) {
      var fields = [];
      ((completion && completion.documents) || []).forEach(function (item) {
        var page = root.querySelector('[data-erapor-page][data-erapor-type="' + CSS.escape(item.jenis) + '"]');
        (item.missing || []).forEach(function (entry) {
          var field = page && page.querySelector('[data-erapor-key="' + CSS.escape(entry.key) + '"]');
          if (field) fields.push(field);
        });
      });
      markMissing(fields);
    }

    root.addEventListener('input', function (event) {
      var testRow = event.target.closest('[data-erapor-entry="UMMI_TEST"]');
      if (testRow) { handleTestRowChange(testRow); return; }
      var field = event.target.closest('[data-erapor-entry]');
      if (field && field.tagName !== 'SELECT' && field.type !== 'checkbox' && field.dataset.eraporRadio !== 'true') noteChange(field);
    });

    root.addEventListener('change', function (event) {
      var testRow = event.target.closest('[data-erapor-entry="UMMI_TEST"]');
      if (testRow) { handleTestRowChange(testRow); return; }
      var field = event.target.closest('[data-erapor-entry]');
      if (!field) return;
      if (field.dataset.eraporPraToggle === 'true') { handlePraToggle(field); return; }
      if (field.dataset.ummiReading === 'true') {
        var ummiCard = field.closest('[data-erapor-document]');
        if (ummiCard) updateUmmiReadingCount(ummiCard);
      }
      if (field.tagName === 'SELECT' || field.type === 'checkbox' || field.dataset.eraporRadio === 'true') noteChange(field);
    });

    // Bagikan ke Orang Tua: tautan wa.me terbuka seperti biasa; pembagian dicatat di server (keepalive).
    document.addEventListener('click', function (event) {
      var link = event.target.closest('[data-erapor-share]');
      if (!link) return;
      var body = new FormData();
      body.append('csrf_token', root.dataset.csrfToken);
      body.append('penerima', link.dataset.eraporShare);
      fetch(window.location.pathname.replace(/\/$/, '') + '/bagikan', {method: 'POST', body: body, credentials: 'same-origin', keepalive: true})
        .then(function (response) { return response.ok ? response.json() : null; })
        .then(function (data) {
          if (!data || !data.ok) { if (window.uiToast) window.uiToast('Pembagian belum tercatat. Coba lagi.', 'error'); return; }
          document.querySelectorAll('[data-erapor-share-status]').forEach(function (status) {
            status.innerHTML = '<span class="badge badge-positif">Dibagikan ke Orang Tua</span>';
          });
          if (window.uiToast) window.uiToast('Rapor dibagikan ke orang tua.', 'success');
        })
        .catch(function () { if (window.uiToast) window.uiToast('Pembagian belum tercatat. Coba lagi.', 'error'); });
    });

    // Konfirmasi memakai modal aplikasi (#erapor-confirm-modal), bukan window.confirm() bawaan browser.
    var confirmModal = root.querySelector('#erapor-confirm-modal');
    // Pindahkan ke <body> agar overlay menutupi action bar fixed (root membentuk stacking context sendiri).
    if (confirmModal) document.body.appendChild(confirmModal);
    function askConfirm(title, message, acceptLabel) {
      if (!confirmModal) return Promise.resolve(window.confirm(message));
      var accept = confirmModal.querySelector('[data-erapor-confirm-accept]');
      var cancel = confirmModal.querySelector('[data-erapor-confirm-cancel]');
      var opener = document.activeElement;
      confirmModal.querySelector('.modal-title').textContent = title;
      confirmModal.querySelector('[data-erapor-confirm-message]').textContent = message;
      accept.textContent = acceptLabel;
      confirmModal.classList.add('is-open');
      document.body.classList.add('modal-open');
      cancel.focus();
      return new Promise(function (resolve) {
        function close(result) {
          confirmModal.classList.remove('is-open');
          document.body.classList.remove('modal-open');
          confirmModal.removeEventListener('click', onClick);
          document.removeEventListener('keydown', onKey);
          if (opener && opener.focus) opener.focus();
          resolve(result);
        }
        function onClick(event) {
          event.stopPropagation();
          if (event.target.closest('[data-erapor-confirm-accept]')) close(true);
          else if (event.target === confirmModal || event.target.closest('[data-erapor-confirm-cancel]')) close(false);
        }
        function onKey(event) {
          if (event.key === 'Escape') close(false);
        }
        confirmModal.addEventListener('click', onClick);
        document.addEventListener('keydown', onKey);
      });
    }

    root.addEventListener('click', async function (event) {
      var step = event.target.closest('[data-erapor-step]');
      if (step) { showPage(Number(step.dataset.eraporStep), true); return; }
      if (event.target.closest('[data-erapor-prev]')) { showPage(currentPage - 1, true); return; }
      if (event.target.closest('[data-erapor-next]')) { showPage(currentPage + 1, true); return; }
      var filterButton = event.target.closest('[data-erapor-filter]');
      if (filterButton) { filterMode = filterButton.dataset.eraporFilter; applyFilter(); return; }
      var bingStart = event.target.closest('[data-erapor-bing-start]');
      if (bingStart) {
        var bingPage = bingStart.closest('[data-erapor-page]');
        var bingFields = bingPage && bingPage.querySelector('[data-erapor-bing-fields]');
        if (bingFields) bingFields.hidden = false;
        var intro = bingStart.closest('[data-erapor-bing-intro]');
        if (intro) intro.remove();
        updateSectionCounts();
        if (bingFields) { var first = bingFields.querySelector('.ui-select-trigger, select, textarea'); if (first) first.focus(); }
        return;
      }
      var openUrl = event.target.closest('[data-erapor-open-url]');
      if (openUrl) { window.open(openUrl.dataset.eraporOpenUrl, '_blank', 'noopener'); return; }
      if (event.target.closest('[data-erapor-show-missing]')) {
        if (!validateRequired()) return;
        markServerMissing(lastCompletion);
        return;
      }

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
        var orderInput = row.querySelector('[data-ummi-test-field="urutan"]');
        row.dataset.eraporKey = 'tes:' + orderInput.name.slice(-32);
        // Urutan otomatis: satu di atas urutan terbesar yang sudah ada.
        var maxOrder = Array.from(list.querySelectorAll('[data-ummi-test-field="urutan"]')).reduce(function (max, input) {
          return Math.max(max, Number(input.value) || 0);
        }, 0);
        orderInput.value = String(maxOrder + 1);
        list.appendChild(row);
        renumberTests(list);
        updateConfirmState();
        var dateInput = row.querySelector('[data-ummi-test-field="tanggal_tes"]');
        if (dateInput) dateInput.focus();
        setStatus('Baris tes ditambahkan. Lengkapi seluruh kolom, atau hapus baris ini, sebelum Selesaikan Rapor.', 'pending');
        return;
      }

      var tooltip = event.target.closest('[data-ui-tooltip]');
      if (tooltip && tooltip.closest('summary')) { event.preventDefault(); tooltip.focus(); return; }

      var removeTest = event.target.closest('[data-erapor-remove-test]');
      if (removeTest) {
        var testRow = removeTest.closest('[data-erapor-entry="UMMI_TEST"]');
        if (!testRow || blocked || saving) return;
        if (!testRow.dataset.savedValue) {
          pending.delete(testRow);
          var testList = testRow.parentElement;
          testRow.remove();
          renumberTests(testList);
          updateConfirmState();
          if (!pending.size && !saving) setStatus(hasIncompleteTestRows() ? 'Lengkapi atau hapus baris tes Ummi yang belum lengkap sebelum Selesaikan Rapor.' : 'Baris tes kosong dihapus. Semua perubahan tersimpan.', hasIncompleteTestRows() ? 'pending' : 'success');
          return;
        }
        if (!(await askConfirm('Hapus Catatan Tes?', 'Catatan tes ini akan dihapus dari Rapor Ummi.', 'Hapus'))) return;
        if (blocked || saving) return;
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
        setReceptionDisabled(true);
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
        if (await askConfirm('Muat Ulang Sesi?', 'Perubahan yang belum tersimpan di halaman ini akan hilang.', 'Muat Ulang')) window.location.reload();
        return;
      }
      var confirmButton = event.target.closest('[data-erapor-confirm]');
      var receiveButton = event.target.closest('[data-erapor-confirm-reception]');
      if ((!confirmButton && !receiveButton) || (confirmButton && confirmButton.disabled)
        || (receiveButton && receiveButton.disabled) || pending.size || saving || blocked) return;
      if (hasIncompleteTestRows()) {
        setStatus('Lengkapi atau hapus baris tes Ummi yang belum lengkap sebelum melanjutkan.', 'error');
        var incompleteRow = Array.from(root.querySelectorAll('[data-erapor-entry="UMMI_TEST"]')).find(function (row) {
          return row.dataset.eraporIncomplete === 'true' && row.dataset.eraporDeleteTest !== 'true';
        });
        if (incompleteRow) goTo(incompleteRow);
        return;
      }
      if (!validateRequired()) return;
      var receiving = Boolean(receiveButton);
      var confirmation = receiving
        ? 'Konfirmasi penerimaan akan mengunci seluruh isian rapor dan mengirimkannya ke antrean persetujuan. Setelah dilanjutkan, nilai tidak dapat diubah.'
        : 'Konfirmasi ini menandai pengisian guru telah selesai. Anda masih dapat meninjau dan mengubah isian sebelum konfirmasi penerimaan.';
      if (!(await askConfirm(receiving ? 'Konfirmasi Penerimaan?' : 'Selesaikan Rapor?', confirmation,
        receiving ? 'Konfirmasi Penerimaan' : 'Selesaikan Rapor'))) return;
      if (pending.size || saving || blocked) return;
      setConfirmDisabled(true);
      setReceptionDisabled(true);
      try {
        var result = await request(apiUrl + (receiving ? '/konfirmasi-penerimaan' : '/konfirmasi-isi'), {});
        if (result.result === 'confirmed' || result.result === 'already_confirmed') {
          window.location.reload();
          return;
        }
        applyState(result);
        markServerMissing(result.completion);
        setStatus('Masih ada isian wajib yang belum lengkap. Lengkapi isian bertanda merah.', 'error');
      } catch (error) {
        if (error.status === 401) { window.location.assign(root.dataset.loginUrl); return; }
        blocked = error.status === 403 || error.status === 409 || error.status === 419 || error.status === 422;
        if (blocked) recovery.hidden = false;
        setStatus(error.message || 'Konfirmasi belum dapat dilakukan.', 'error');
        setConfirmDisabled(blocked || serverStatus !== 'BELUM_DIISI');
        setReceptionDisabled(blocked || serverStatus !== 'TELAH_DIISI');
      }
    });

    try {
      var initial = JSON.parse(root.querySelector('[data-erapor-initial-state]').textContent);
      applyState(initial);
      root.querySelectorAll('[data-erapor-document]').forEach(function (card) { updateUmmiReadingCount(card); });
      updateSectionCounts();
      var hashPage = /^#bagian-(\d+)$/.exec(window.location.hash);
      showPage(hashPage ? Math.min(pages.length, Number(hashPage[1])) - 1 : 0, false);
    } catch (error) {
      setStatus('Ringkasan sesi tidak dapat dibaca. Muat ulang halaman sebelum mengubah nilai.', 'error');
      blocked = true;
    }
  });
})();
