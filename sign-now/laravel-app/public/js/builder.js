
/* docsign builder — drag/resize fields with selection, snap-to-grid,
   alignment guides, keyboard nudging and a bound properties panel. */
(function ($) {
    'use strict';

    var RENDER_SCALE = 1.5;
    var DOC_ID = $('#pdfStack').data('doc-id');
    var GUIDE_TOL = 4; // px tolerance for snap-to-edge alignment

    var state = {
        pageCount: 0,
        seq: 0,
        snap: true,
        snapPx: 8,
        selected: null,        // DOM element of selected field
        suppressPropSync: false
    };

    pdfjsLib.GlobalWorkerOptions.workerSrc =
        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    // -----------------------------------------------------------------
    // Boot
    // -----------------------------------------------------------------
    $(function () {
        wireToolbar();
        wirePropertiesPanel();
        wireKeyboard();
        wirePageClickToDeselect();
        loadAndRender();
    });

    function wireToolbar() {
        $('#addText').on('click', function () { addNewField('text'); });
        $('#addDate').on('click', function () { addNewField('date'); });
        $('#addSign').on('click', function () { addNewField('sign'); });
        $('#addInitials').on('click', function () { addNewField('initials'); });
        $('#saveFields').on('click', onSave);

        $('#saveAsTemplate').on('click', function () {
            var fields = collectFields();
            if (!fields.length) {
                window.DSToast.show('Add at least one field before saving as template', 'error');
                return;
            }
            // Save fields first, then mark as template
            var $btn = $('#saveFields').attr('aria-busy', 'true');
            $.ajax({
                url: '/documents/fields',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ doc_id: DOC_ID, render_scale: RENDER_SCALE, fields: fields })
            }).done(function (res) {
                $btn.attr('aria-busy', 'false');
                if (!res || res.result !== 'success') {
                    window.DSToast.show((res && res.message) || 'Save failed', 'error');
                    return;
                }
                // Now mark as template
                if (window.DSLoader) (window.DSLoader.show || window.DSLoader.start).call(window.DSLoader);
                fetch('/templates/' + encodeURIComponent(DOC_ID) + '/mark', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': $('meta[name=csrf-token]').attr('content'),
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin'
                })
                    .then(function (r) { return r.json().then(function (j) { return { ok: r.ok, body: j }; }); })
                    .then(function (tplRes) {
                        if (window.DSLoader) (window.DSLoader.hide || window.DSLoader.done).call(window.DSLoader);
                        if (!tplRes.ok || !tplRes.body || tplRes.body.result !== 'success') {
                            window.DSToast.show((tplRes.body && tplRes.body.message) || 'Failed to save as template', 'error');
                            return;
                        }
                        isTemplate = true;
                        updateApiSection();
                        window.DSToast.show('Saved as template! You can now enable API access.', 'success');
                    })
                    .catch(function () {
                        if (window.DSLoader) (window.DSLoader.hide || window.DSLoader.done).call(window.DSLoader);
                        window.DSToast.show('Failed to save as template', 'error');
                    });
            }).fail(function () {
                $btn.attr('aria-busy', 'false');
                window.DSToast.show('Save failed — cannot create template', 'error');
            });
        });

        $('#snapToggle').on('change', function () {
            state.snap = this.checked;
            try { localStorage.setItem('ds_snap', state.snap ? '1' : '0'); } catch (e) {}
        });
        $('#snapSize').on('change', function () {
            state.snapPx = parseInt(this.value, 10) || 8;
            try { localStorage.setItem('ds_snap_px', String(state.snapPx)); } catch (e) {}
        });
        try {
            var s = localStorage.getItem('ds_snap');
            if (s !== null) { state.snap = s === '1'; $('#snapToggle')[0].checked = state.snap; }
            var sp = parseInt(localStorage.getItem('ds_snap_px') || '8', 10);
            if (sp) { state.snapPx = sp; $('#snapSize').val(String(sp)); }
        } catch (e) {}

        wireRecipients();
    }

    // -----------------------------------------------------------------
    // Recipients — load, add, remove, send invites, status tracking
    // -----------------------------------------------------------------
    var EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    var recipientsData = []; // Array of {id, email, name, status, sent_at, signed_at}


    function buildChip(r) {
        // Special chip for the admin sentinel
        if (r.email === '__admin__') {
            var removeBtn = '<button type="button" class="ds-chip__x js-remove-recipient" data-id="' + r.id + '" title="Remove">&times;</button>';
            // Show linked customer email when one has been set via customer search
            var linkedCust = _linkedCustomers['__admin__'] || null;
            var custEmailLine = '';
            if (linkedCust && linkedCust.email) {
                custEmailLine = '<span style="font-size:10px;color:#2563eb;opacity:0.8;max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' +
                    escapeHtml(linkedCust.email) + '</span>';
            }
            return '<span class="ds-chip" data-id="' + r.id + '" data-email="__admin__" ' +
                'style="background:#eff6ff;border:1.5px solid #2563eb;color:#1d4ed8;flex-direction:column;align-items:flex-start;gap:1px;padding:4px 8px;">' +
                '<span style="display:flex;align-items:center;gap:5px;">' +
                    '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>' +
                    '<span class="ds-chip__email" style="font-weight:600;">Admin (You)</span>' +
                    '<span class="ds-badge" style="font-size:9px;padding:1px 5px;background:#2563eb;color:#fff;">Finalize</span>' +
                    removeBtn +
                '</span>' +
                custEmailLine +
            '</span>';
        }

        var statusDot = '<span class="ds-chip__status ds-chip__status--' + r.status + '"></span>';
        var statusLabel = r.status.charAt(0).toUpperCase() + r.status.slice(1);
        var statusBadge = '<span class="ds-badge ds-badge--' + r.status + '" style="font-size:9px;padding:1px 5px;">' + statusLabel + '</span>';

        var removeBtn = '<button type="button" class="ds-chip__x js-remove-recipient" data-id="' + r.id + '" title="Remove">&times;</button>';

        return '<span class="ds-chip ds-chip--' + r.status + '" data-id="' + r.id + '" data-email="' + escapeHtml(r.email) + '">' +
            statusDot +
            '<span class="ds-chip__email">' + escapeHtml(r.email) + '</span>' +
            statusBadge +
            (r.status === 'signed' ? '' : '<button type="button" class="ds-btn ds-btn--ghost ds-btn--sm js-send-invite" data-id="' + r.id + '" style="padding:0 6px;height:20px;font-size:10px;">' + (r.status === 'pending' ? 'Send invite' : 'Resend') + '</button>') +
            removeBtn +
        '</span>';
    }

    function renderRecipients() {
        var html = '';
        recipientsData.forEach(function (r) { html += buildChip(r); });
        $('#recipientsList').html(html);
        updateRecipientCounts();
        // Keep the assigned-to dropdown in sync with the current recipient list
        var currentAssigned = state.selected ? (state.selected.getAttribute('data-assigned-to') || '') : '';
        refreshAssignedToSelect(currentAssigned);
    }

    // Rebuild the "Assigned to" dropdown from the current recipient list.
    // currentVal — the currently stored value to re-select (email string or '').
    function refreshAssignedToSelect(currentVal) {
        var $sel = $('#propAssignedTo').empty();
        $sel.append('<option value="">All recipients</option>');
        // Admin slot is always available — fields assigned to admin are filled during finalization.
        $sel.append('<option value="__admin__">Admin (You) — filled on finalize</option>');
        recipientsData.forEach(function (r) {
            if (r.email === '__admin__') return; // already added above
            $sel.append('<option value="' + escapeHtml(r.email) + '">' + escapeHtml(r.email) + '</option>');
        });
        $sel.val(currentVal || '');
    }

    function updateRecipientCounts() {
        // Exclude __admin__ from counts shown in badge/summary
        var realRecipients = recipientsData.filter(function (r) { return r.email !== '__admin__'; });
        var hasAdmin = recipientsData.some(function (r) { return r.email === '__admin__'; });
        var total = realRecipients.length;
        var pending = realRecipients.filter(function (r) { return r.status === 'pending'; }).length;
        var signed = realRecipients.filter(function (r) { return r.status === 'signed'; }).length;

        $('#recipientCountBadge').text(total + (hasAdmin ? '+A' : ''));

        var summary = total + ' recipient' + (total === 1 ? '' : 's');
        if (hasAdmin) summary += ' + Admin slot';
        if (signed > 0) summary += ' · ' + signed + ' signed';
        if (pending > 0) summary += ' · ' + pending + ' unsent';
        $('#recipientSummary').text(summary);

    }

    function collectRecipientEmails() {
        return recipientsData.map(function (r) { return r.email; });
    }

    function saveRecipients(callback) {
        $.ajax({
            url: '/documents/' + encodeURIComponent(DOC_ID) + '/recipients',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ recipients: collectRecipientEmails() })
        }).done(function (res) {
            if (res && res.result === 'success') {
                recipientsData = res.recipients || [];
                renderRecipients();
            }
            if (callback) callback();
        }).fail(function (xhr) {
            var msg = 'Failed to save recipients';
            try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
            window.DSToast.show(msg, 'error');
        });
    }

    function addRecipientFromInput() {
        var $inp = $('#recipientInput');
        var email = $inp.val().trim().toLowerCase();
        if (!email) {
            window.DSToast.show('Please enter an email address', 'error');
            return;
        }
        if (!EMAIL_RE.test(email)) {
            window.DSToast.show('Invalid email address', 'error');
            return;
        }
        var exists = recipientsData.some(function (r) { return r.email === email; });
        if (exists) {
            window.DSToast.show('Already added', 'error');
            return;
        }
        // Optimistically add then save
        recipientsData.push({ id: 0, email: email, name: '', status: 'pending', sent_at: null, signed_at: null });
        $inp.val('');
        saveRecipients();
    }

    function wireRecipients() {
        // Load recipients from server
        $.getJSON('/documents/' + encodeURIComponent(DOC_ID) + '/recipients').done(function (res) {
            recipientsData = res.recipients || [];
            renderRecipients();
        });

        // Toggle panel visibility
        $('#openRecipients').on('click', function () {
            var $panel = $('#recipientsPanel');
            if ($panel.is(':visible')) {
                $panel.slideUp(150);
            } else {
                $panel.slideDown(150);
                $('#recipientInput').focus();
            }
        });
        $('#closeRecipients').on('click', function () {
            $('#recipientsPanel').slideUp(150);
        });

        // Add recipient
        $('#addRecipient').on('click', addRecipientFromInput);
        $('#recipientInput').on('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); addRecipientFromInput(); }
        });

        // Remove recipient
        $('#recipientsList').on('click', '.js-remove-recipient', function () {
            var id = parseInt($(this).data('id'), 10);
            recipientsData = recipientsData.filter(function (r) { return r.id !== id; });
            saveRecipients();
        });

        // Send / resend invite email
        $('#recipientsList').on('click', '.js-send-invite', function () {
            var $btn = $(this).prop('disabled', true);
            $.post('/documents/' + encodeURIComponent(DOC_ID) + '/invite/' + parseInt($btn.data('id'), 10)).done(function (res) {
                if (res && res.result === 'success') { window.DSToast.show('Invitation sent', 'success'); saveRecipients(); }
                else { $btn.prop('disabled', false); window.DSToast.show((res && res.message) || 'Could not send invitation', 'error'); }
            }).fail(function (xhr) {
                $btn.prop('disabled', false);
                window.DSToast.show((xhr.responseJSON && xhr.responseJSON.message) || 'Could not send invitation', 'error');
            });
        });

        wireCustomerSearch();
    }

    // ------------------------------------------------------------------
    // Customer search
    // ------------------------------------------------------------------
    var CUSTOMER_FIELD_MAP = {
        email:        'email',
        firstname:    'name',
        first_name:   'name',
        name:         'name',
        lastname:     'lastname',
        last_name:    'lastname',
        surname:      'lastname',
        company:      'companyName',
        company_name: 'companyName',
        companyname:  'companyName',
        organization: 'companyName',
        address:      'fullAddress',
        full_address: 'fullAddress',
        fulladdress:  'fullAddress',
        userid:       'UserID',
        user_id:      'UserID',
        customerid:   'UserID',
        customer_id:  'UserID',
    };

    // Tracks all linked customers keyed by lowercase email (one per recipient)
    var _linkedCustomers = {};

    var _customerSearchTimer = null;

    function wireCustomerSearch() {
        $('#customerSearchInput').on('input', function () {
            clearTimeout(_customerSearchTimer);
            var q = $(this).val().trim();
            if (q.length < 2) {
                $('#customerSearchResults').hide().empty();
                return;
            }
            _customerSearchTimer = setTimeout(function () { searchCustomers(q); }, 300);
        });

        $('#customerSearchInput').on('keydown', function (e) {
            if (e.key === 'Escape') {
                $(this).val('');
                $('#customerSearchResults').hide().empty();
            }
        });

        $('#customerSearchResults').on('click', '.js-add-recipient', function (e) {
            e.stopPropagation();
            var idx = parseInt($(this).data('idx'), 10);
            if (!isNaN(idx) && window._customerSearchCache && window._customerSearchCache[idx]) {
                selectCustomer(window._customerSearchCache[idx]);
            }
        });
        $('#customerSearchResults').on('click', '.js-add-admin', function (e) {
            e.stopPropagation();
            var idx = parseInt($(this).data('idx'), 10);
            if (!isNaN(idx) && window._customerSearchCache && window._customerSearchCache[idx]) {
                selectCustomerAsAdmin(window._customerSearchCache[idx], $(this));
            }
        });
    }

    function searchCustomers(q) {
        var key = $('#customerSearchKey').val() || 'all';
        var $results = $('#customerSearchResults');
        $results.html('<div class="ds-customer-result--loading">Searching…</div>').show();

        $.ajax({
            url: '/customers/search',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ search: q, searchKey: key }),
            global: false, // inline "Searching" indicator instead of the page loader
        }).done(function (res) {
            window._customerSearchCache = res.data || [];
            renderCustomerResults(res.data || []);
        }).fail(function () {
            $results.html('<div class="ds-customer-result--empty">Search failed. Please try again.</div>');
        });
    }

    function renderCustomerResults(customers) {
        var $results = $('#customerSearchResults');
        if (!customers.length) {
            $results.html('<div class="ds-customer-result--empty">No customers found.</div>').show();
            return;
        }
        var html = '';
        customers.forEach(function (c, i) {
            var displayName = [c.name, c.lastname].filter(Boolean).join(' ') || c.email;
            var meta = [c.email, c.companyName].filter(Boolean).join(' · ');
            // Templates only get the Admin slot — real recipients are created when the template is used
            var addBtn = isTemplate ? '' : '<div class="ds-customer-result__add js-add-recipient" data-idx="' + i + '">+ Add</div>';
            html += '<div class="ds-customer-result" data-idx="' + i + '">' +
                '<div>' +
                    '<div class="ds-customer-result__name">' + escHtml(displayName) + '</div>' +
                    '<div class="ds-customer-result__meta">' + escHtml(meta) + '</div>' +
                '</div>' +
                '<div style="display:flex;gap:6px;flex-shrink:0;">' +
                    addBtn +
                    '<div class="ds-customer-result__add js-add-admin" data-idx="' + i + '" ' +
                        'style="background:#eff6ff;color:#1d4ed8;border-color:#2563eb;" ' +
                        'title="Add this customer\'s info to the Admin slot — their data will auto-fill admin-assigned fields during finalization">' +
                        '🛡 Admin' +
                    '</div>' +
                '</div>' +
            '</div>';
        });
        $results.html(html).show();
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function selectCustomer(customer) {
        var email = (customer.email || '').toLowerCase().trim();
        if (!email) { window.DSToast.show('Customer has no email address.', 'warn'); return; }

        var $btn = $('.ds-customer-result[data-idx]').filter(function () {
            return window._customerSearchCache && window._customerSearchCache[parseInt($(this).data('idx'),10)] === customer;
        });
        $btn.find('.ds-customer-result__add').text('Adding…');

        $.ajax({
            url: '/documents/add-customer/' + encodeURIComponent(DOC_ID),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ email: email, customer_data: customer }),
        }).done(function (res) {
            if (res.result === 'success') {
                _linkedCustomers[email] = customer;
                recipientsData = res.recipients || [];
                renderRecipients();
                updateFieldPreviews(customer);
                // Re-sync open properties panel to show customer-key row
                if (state.selected) syncFormFromElement();
                $('#customerSearchInput').val('');
                $('#customerSearchResults').hide().empty();
                window._customerSearchCache = null;
                window.DSToast.show('Customer added as recipient.', 'success');
            } else {
                window.DSToast.show(res.message || 'Could not add customer.', 'error');
                $btn.find('.ds-customer-result__add').text('+ Add');
            }
        }).fail(function () {
            window.DSToast.show('Request failed. Please try again.', 'error');
            $btn.find('.ds-customer-result__add').text('+ Add');
        });
    }

    // Set a customer as the Admin slot — their data auto-fills __admin__-assigned fields at finalize
    function selectCustomerAsAdmin(customer, $btn) {
        $btn.text('Adding…');
        $.ajax({
            url: '/documents/add-customer/' + encodeURIComponent(DOC_ID),
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ email: '__admin__', customer_data: customer }),
        }).done(function (res) {
            if (res.result === 'success') {
                _linkedCustomers['__admin__'] = customer;
                recipientsData = res.recipients || [];
                renderRecipients();
                updateFieldPreviews(customer, '__admin__');
                if (state.selected) syncFormFromElement();
                $('#customerSearchInput').val('');
                $('#customerSearchResults').hide().empty();
                window._customerSearchCache = null;
                var displayName = [customer.name, customer.lastname].filter(Boolean).join(' ') || customer.email;
                window.DSToast.show(displayName + ' set as Admin — their info will auto-fill admin fields at finalization.', 'success');
            } else {
                window.DSToast.show(res.message || 'Could not set as admin.', 'error');
                $btn.text('🛡 Admin');
            }
        }).fail(function () {
            window.DSToast.show('Request failed. Please try again.', 'error');
            $btn.text('🛡 Admin');
        });
    }

    function updateFieldPreviews(customer, targetEmail) {
        // targetEmail overrides the assignment-match email (used for __admin__ slot)
        var custEmail = targetEmail ? targetEmail.toLowerCase() : (customer.email || '').toLowerCase().trim();
        if (!custEmail) return;
        // Only touch fields assigned to THIS customer — other customers' fields are unaffected
        $('#pdfStack .df-field').each(function () {
            var el = this;
            var assignedTo = (el.getAttribute('data-assigned-to') || '').toLowerCase().trim();
            if (assignedTo !== custEmail) return;

            $(el).removeClass('df-field--customer-mapped');
            $(el).find('.df-field__autofill').remove();

            var customerKey = el.getAttribute('data-customer-key') || '';
            if (!customerKey) {
                var fieldName = (el.getAttribute('data-name') || '').toLowerCase().replace(/[^a-z0-9_]/g, '_');
                customerKey = CUSTOMER_FIELD_MAP[fieldName] || '';
            }
            var savedValue = el.getAttribute('data-customer-value') || '';
            var liveValue  = customerKey ? (customer[customerKey] || '') : '';
            var displayVal = savedValue || liveValue;
            if (customerKey && (displayVal || liveValue)) {
                $(el).addClass('df-field--customer-mapped');
                var $span = $('<span class="df-field__autofill"></span>').text(displayVal || liveValue);
                el.setAttribute('data-customer-value', displayVal);
                $(el).append($span);
                requestAnimationFrame(function () { builderAutoShrink($span[0]); });
            }
        });
    }

    var _builderMeasureEl = null;
    function measurer() {
        if (!_builderMeasureEl) {
            _builderMeasureEl = $('<span></span>').css({
                position: 'absolute', visibility: 'hidden', whiteSpace: 'pre',
                top: '-9999px', left: '-9999px', pointerEvents: 'none'
            }).appendTo(document.body)[0];
        }
        return _builderMeasureEl;
    }

    function builderAutoShrink(el) {
        var $el = $(el);
        // Admin-set font size pins the preview — skip auto-fit entirely.
        var $field = $el.closest('.df-field');
        var custom = parseFloat($field.attr('data-font-size'));
        if (custom > 0) { $el.css('font-size', custom + 'px'); return; }
        var minSize = 7, maxSize = 13;
        var text = $el.is('input,textarea') ? ($el.val() || $el.attr('placeholder') || '') : ($el.text() || '');
        if (!text) { $el.css('font-size', maxSize + 'px'); return; }
        var w = $el.parent().width() || $el.width();
        if (!w) return;
        var m = measurer();
        $(m).css({ fontFamily: $el.css('font-family'), fontWeight: $el.css('font-weight'), whiteSpace: 'pre', width: 'auto' }).text(text);
        var size = maxSize;
        while (size > minSize) {
            $(m).css('font-size', size + 'px');
            if (m.offsetWidth <= w - 20) break;
            size -= 0.5;
        }
        $el.css('font-size', size + 'px');
    }

    function applyFieldFontSize($field, px) {
        if (px && +px > 0) $field.css('font-size', px + 'px');
        else $field.css('font-size', '');
    }

    function wirePageClickToDeselect() {
        $('#pdfStack').on('mousedown', function (e) {
            if ($(e.target).closest('.df-field').length) return;
            select(null);
        });
    }

    // -----------------------------------------------------------------
    // Load + render PDF, then overlay any existing fields
    // -----------------------------------------------------------------
    function loadAndRender() {
        $.getJSON('/documents/' + DOC_ID + '/fields').done(function (res) {
            RENDER_SCALE = res.render_scale || 1.5;
            // Restore all linked customers from server (survives page reload)
            var loadedCustomers = res.customers_data || (res.customer_data ? [res.customer_data] : []);
            loadedCustomers.forEach(function (c) {
                var em = (c.email || '').toLowerCase().trim();
                if (em) _linkedCustomers[em] = c;
            });
            // Restore admin-linked customer so admin-assigned field previews survive reload
            if (res.admin_customer_data) {
                _linkedCustomers['__admin__'] = res.admin_customer_data;
            }
            renderPdf('/documents/' + DOC_ID + '/serve/original').then(function () {
                (res.fields || []).forEach(function (f) {
                    var $page = $('#pdfStack .page-wrap[data-page="' + f.page + '"]');
                    if (!$page.length) return;
                    addFieldBox($page, f.type, f.x, f.y, f.w, f.h, {
                        id:             f.id,
                        name:           f.name,
                        label:          f.label,
                        required:       f.required,
                        assigned_to:    f.assigned_to || '',
                        customer_key:   f.customer_key || '',
                        customer_value: f.customer_value || '',
                        font_size:      (f.font_size && +f.font_size > 0) ? +f.font_size : null,
                        default_value:  f.default_value || ''
                    });
                });
                refreshPagesSelect();
                refreshFieldList();
                // Show autofill previews for all linked customers
                // Use '__admin__' override for the admin sentinel so assignment matching works
                Object.keys(_linkedCustomers).forEach(function (em) {
                    updateFieldPreviews(_linkedCustomers[em], em === '__admin__' ? '__admin__' : null);
                });
                // Re-render recipient chips now that _linkedCustomers is populated.
                // Fixes race where wireRecipients() completed first and buildChip()
                // rendered the admin chip without the linked customer email.
                if (recipientsData.length) renderRecipients();
            });
        }).fail(function () {
            window.DSToast.show('Failed to load fields', 'error');
        });
    }

    function renderPdf(url) {
        $('#pdfStack').empty();
        return pdfjsLib.getDocument(url).promise.then(function (pdf) {
            state.pageCount = pdf.numPages;
            var chain = Promise.resolve();
            for (var i = 1; i <= pdf.numPages; i++) {
                (function (n) {
                    chain = chain.then(function () {
                        return pdf.getPage(n).then(function (page) { return renderPage(page, n); });
                    });
                })(i);
            }
            return chain;
        });
    }

    function renderPage(page, pageNum) {
        var viewport = page.getViewport({ scale: RENDER_SCALE });
        var $wrap = $('<div class="page-wrap"></div>')
            .css({ width: viewport.width + 'px', height: viewport.height + 'px' })
            .attr('data-page', pageNum);
        $wrap.append('<span class="page-wrap__num">Page ' + pageNum + '</span>');
        var canvas = $('<canvas></canvas>').attr({ width: viewport.width, height: viewport.height })[0];
        var $overlay = $('<div class="field-layer"></div>').css({
            position: 'absolute', inset: 0, pointerEvents: 'auto'
        });
        $wrap.append(canvas).append($overlay);
        $('#pdfStack').append($wrap);
        return page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;
    }

    // -----------------------------------------------------------------
    // Field creation
    // -----------------------------------------------------------------
    function suggestName(type) {
        var prefix = type === 'sign' ? 'sig' : (type === 'initials' ? 'initials' : type);
        var existing = collectFields().map(function (f) { return f.name; });
        var n = 1;
        while (existing.indexOf(prefix + '_' + n) !== -1) n++;
        return prefix + '_' + n;
    }

    function defaultLabel(type) {
        if (type === 'sign')     return 'Signature';
        if (type === 'initials') return 'Initials';
        if (type === 'date')     return 'Date';
        return 'Text';
    }

    // Returns the page-wrap whose centre is closest to the visible
    // centre of #pdfStack.  Uses getBoundingClientRect so it works
    // regardless of which element is the actual scroll container.
    function getMostVisiblePage() {
        var container = document.getElementById('pdfStack');
        if (!container) return $('#pdfStack .page-wrap').first();

        var cRect = container.getBoundingClientRect();
        var cMid  = cRect.top + cRect.height / 2;

        var pages = container.querySelectorAll('.page-wrap');
        if (!pages.length) return $();

        var best = pages[0];
        var bestDist = Infinity;

        for (var i = 0; i < pages.length; i++) {
            var pRect = pages[i].getBoundingClientRect();
            var pMid  = pRect.top + pRect.height / 2;
            var dist  = Math.abs(cMid - pMid);
            if (dist < bestDist) {
                bestDist = dist;
                best = pages[i];
            }
        }

        return $(best);
    }

    // Given viewport coordinates, return the .page-wrap element at that point.
    function getPageAtPoint(clientX, clientY) {
        var pages = document.querySelectorAll('#pdfStack .page-wrap');
        for (var i = 0; i < pages.length; i++) {
            var r = pages[i].getBoundingClientRect();
            if (clientY >= r.top && clientY <= r.bottom &&
                clientX >= r.left && clientX <= r.right) {
                return pages[i];
            }
        }
        return null;
    }

    function addNewField(type) {
        var $page = getMostVisiblePage();
        if (!$page || !$page.length) {
            window.DSToast.show('Upload a document first', 'error');
            return;
        }
        var w = type === 'initials' ? 120 : 240;
        var h = (type === 'sign' || type === 'initials') ? 80 : 36;
        var opts = {
            name: suggestName(type),
            label: defaultLabel(type),
            required: false
        };
        // Default font size only for text/date — meaningless for sign/initials images.
        if (type === 'text' || type === 'date') opts.font_size = 14;
        var el = addFieldBox($page, type, 40, 40, w, h, opts);
        if (el) el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function addFieldBox($page, type, x, y, w, h, opts) {
        opts = opts || {};
        state.seq++;
        var id = opts.id || ('f_' + Date.now() + '_' + state.seq);
        var $box = $('<div class="df-field"></div>')
            .addClass('df-field--' + type)
            .attr('data-id', id)
            .attr('data-type', type)
            .attr('data-x', x)
            .attr('data-y', y)
            .attr('data-w', w)
            .attr('data-h', h)
            .attr('data-name', opts.name || '')
            .attr('data-label', opts.label || defaultLabel(type))
            .attr('data-required', opts.required ? '1' : '0')
            .attr('data-assigned-to', opts.assigned_to || '')
            .attr('data-customer-key', opts.customer_key || '')
            .attr('data-customer-value', opts.customer_value || '')
            .attr('data-font-size', (opts.font_size && +opts.font_size > 0) ? opts.font_size : '')
            .attr('data-default-value', opts.default_value || '')
            .css({ left: x + 'px', top: y + 'px', width: w + 'px', height: h + 'px' });

        applyFieldFontSize($box, opts.font_size);

        $box.html(
            '<span class="df-field__grip" title="Drag to move">' +
                '<svg width="8" height="12" viewBox="0 0 8 12" fill="currentColor">' +
                    '<circle cx="2" cy="2" r="1.2"/><circle cx="6" cy="2" r="1.2"/>' +
                    '<circle cx="2" cy="6" r="1.2"/><circle cx="6" cy="6" r="1.2"/>' +
                    '<circle cx="2" cy="10" r="1.2"/><circle cx="6" cy="10" r="1.2"/>' +
                '</svg>' +
            '</span>' +
            '<span class="df-field__label"></span>' +
            '<span class="df-field__type">' + type + '</span>' +
            '<img class="df-field__sig-preview" src="" alt="default signature" style="display:none;">' +
            '<span class="df-handle df-handle--nw"></span>' +
            '<span class="df-handle df-handle--ne"></span>' +
            '<span class="df-handle df-handle--sw"></span>' +
            '<span class="df-handle df-handle--se"></span>'
        );

        if (opts.required) $box.addClass('df-field--required');
        if (opts.customer_key) $box.addClass('df-field--customer-mapped');
        $box.find('.df-field__label').text(opts.name || defaultLabel(type));

        // Show saved default signature preview immediately if one exists
        if ((type === 'sign' || type === 'initials') && opts.default_value) {
            refreshFieldBoxSigPreview($box[0]);
        }

        $page.find('.field-layer').append($box);
        wireInteract($box[0]);
        $box.on('mousedown', function (e) {
            if ($(e.target).is('.df-handle')) return;
            select($box[0]);
        });

        select($box[0]);
        refreshFieldList();
        return $box[0];
    }

    // -----------------------------------------------------------------
    // Drag + resize via interact.js (with absolute left/top, snap, guides)
    // -----------------------------------------------------------------
    function wireInteract(el) {
        interact(el)
            .draggable({
                inertia: false,
                ignoreFrom: '.df-handle',
                listeners: {
                    start: function (event) {
                        select(el);
                        var r = el.getBoundingClientRect();
                        el._grabX = event.clientX - r.left;
                        el._grabY = event.clientY - r.top;
                    },
                    move: function (event) {
                        // Cross-page: move field to whichever page the pointer is over
                        var page = $(el).closest('.page-wrap')[0];
                        var target = getPageAtPoint(event.clientX, event.clientY);
                        if (target && target !== page) {
                            $(target).find('.field-layer').append(el);
                            page = target;
                        }

                        // Absolute position: pointer minus page rect minus grab offset
                        var pRect = page.getBoundingClientRect();
                        var x = event.clientX - pRect.left - el._grabX;
                        var y = event.clientY - pRect.top  - el._grabY;

                        var maxX = page.clientWidth  - el.offsetWidth;
                        var maxY = page.clientHeight - el.offsetHeight;
                        x = clamp(x, 0, maxX);
                        y = clamp(y, 0, maxY);
                        if (state.snap) {
                            x = Math.round(x / state.snapPx) * state.snapPx;
                            y = Math.round(y / state.snapPx) * state.snapPx;
                        }
                        var snapped = snapToSiblings(el, x, y);
                        x = snapped.x; y = snapped.y;
                        el.style.left = x + 'px';
                        el.style.top  = y + 'px';
                        el.setAttribute('data-x', x);
                        el.setAttribute('data-y', y);
                        showGuides(el);
                        if (state.selected === el) syncFormFromElement();
                    },
                    end: function () { hideGuides(); refreshFieldList(); }
                }
            })
            .resizable({
                edges: {
                    left:   '.df-handle--nw, .df-handle--sw',
                    right:  '.df-handle--ne, .df-handle--se',
                    top:    '.df-handle--nw, .df-handle--ne',
                    bottom: '.df-handle--sw, .df-handle--se'
                },
                listeners: {
                    start: function () { select(el); },
                    move: function (event) {
                        var x = parseFloat(el.getAttribute('data-x'));
                        var y = parseFloat(el.getAttribute('data-y'));
                        var w = event.rect.width;
                        var h = event.rect.height;
                        x += event.deltaRect.left;
                        y += event.deltaRect.top;
                        if (state.snap) {
                            w = Math.max(20, Math.round(w / state.snapPx) * state.snapPx);
                            h = Math.max(14, Math.round(h / state.snapPx) * state.snapPx);
                        }
                        el.style.width  = w + 'px';
                        el.style.height = h + 'px';
                        el.style.left   = x + 'px';
                        el.style.top    = y + 'px';
                        el.setAttribute('data-x', x);
                        el.setAttribute('data-y', y);
                        el.setAttribute('data-w', w);
                        el.setAttribute('data-h', h);
                        showGuides(el);
                        if (state.selected === el) syncFormFromElement();
                        var $autofill = $(el).find('.df-field__autofill');
                        if ($autofill.length) builderAutoShrink($autofill[0]);
                    },
                    end: function () { hideGuides(); }
                },
                modifiers: [
                    interact.modifiers.restrictSize({ min: { width: 20, height: 14 } })
                ]
            });
    }

    function clamp(v, lo, hi) { return Math.min(Math.max(v, lo), hi); }

    function snapToSiblings(el, x, y) {
        // Best-effort edge snap to other fields on the same page (within tolerance).
        var page = el.parentNode.parentNode;
        var w = el.offsetWidth, h = el.offsetHeight;
        var bestX = x, bestY = y, dxBest = GUIDE_TOL + 1, dyBest = GUIDE_TOL + 1;
        $(page).find('.df-field').each(function () {
            if (this === el) return;
            var ox = parseFloat(this.getAttribute('data-x'));
            var oy = parseFloat(this.getAttribute('data-y'));
            var ow = this.offsetWidth, oh = this.offsetHeight;
            // Vertical edges (left, right, center)
            [
                [ox, x],
                [ox + ow - w, x],
                [ox + (ow - w) / 2, x]
            ].forEach(function (pair) {
                var d = Math.abs(pair[0] - pair[1]);
                if (d < dxBest) { dxBest = d; bestX = pair[0]; }
            });
            [
                [oy, y],
                [oy + oh - h, y],
                [oy + (oh - h) / 2, y]
            ].forEach(function (pair) {
                var d = Math.abs(pair[0] - pair[1]);
                if (d < dyBest) { dyBest = d; bestY = pair[0]; }
            });
        });
        return {
            x: dxBest <= GUIDE_TOL ? bestX : x,
            y: dyBest <= GUIDE_TOL ? bestY : y
        };
    }

    function showGuides(el) {
        hideGuides();
        var page = el.parentNode.parentNode; // page-wrap
        var x = parseFloat(el.getAttribute('data-x'));
        var y = parseFloat(el.getAttribute('data-y'));
        var w = el.offsetWidth, h = el.offsetHeight;
        var $page = $(page);
        $page.find('.df-field').each(function () {
            if (this === el) return;
            var ox = parseFloat(this.getAttribute('data-x'));
            var oy = parseFloat(this.getAttribute('data-y'));
            var ow = this.offsetWidth, oh = this.offsetHeight;
            // Vertical guides
            [[ox, x], [ox + ow, x + w], [ox + ow / 2, x + w / 2]].forEach(function (pair) {
                if (Math.abs(pair[0] - pair[1]) < 1) {
                    $('<div class="df-guide df-guide--v"></div>')
                        .css({ left: pair[0] + 'px' })
                        .appendTo(page);
                }
            });
            [[oy, y], [oy + oh, y + h], [oy + oh / 2, y + h / 2]].forEach(function (pair) {
                if (Math.abs(pair[0] - pair[1]) < 1) {
                    $('<div class="df-guide df-guide--h"></div>')
                        .css({ top: pair[0] + 'px' })
                        .appendTo(page);
                }
            });
        });
    }
    function hideGuides() { $('#pdfStack .df-guide').remove(); }

    // -----------------------------------------------------------------
    // Selection + properties panel
    // -----------------------------------------------------------------
    function select(el) {
        if (state.selected === el) return;
        $('#pdfStack .df-field--selected').removeClass('df-field--selected');
        state.selected = el;
        if (!el) {
            $('#propsForm').addClass('hidden');
            $('#propsEmpty').show();
            return;
        }
        $(el).addClass('df-field--selected');
        $('#propsEmpty').hide();
        $('#propsForm').removeClass('hidden');
        refreshPagesSelect();
        syncFormFromElement();
    }

    function syncFormFromElement() {
        var el = state.selected;
        if (!el) return;
        state.suppressPropSync = true;
        $('#propName').val(el.getAttribute('data-name') || '');
        $('#propLabel').val(el.getAttribute('data-label') || '');
        $('#propRequired').prop('checked', el.getAttribute('data-required') === '1');
        $('#propX').val(parseFloat(el.getAttribute('data-x')).toFixed(0));
        $('#propY').val(parseFloat(el.getAttribute('data-y')).toFixed(0));
        $('#propW').val(parseFloat(el.getAttribute('data-w')).toFixed(0));
        $('#propH').val(parseFloat(el.getAttribute('data-h')).toFixed(0));
        var page = parseInt(el.parentNode.parentNode.getAttribute('data-page'), 10);
        $('#propPage').val(String(page));
        refreshAssignedToSelect(el.getAttribute('data-assigned-to') || '');
        var elType = el.getAttribute('data-type') || '';
        var isTextOrDate = elType === 'text' || elType === 'date';
        var isSignOrInitials = elType === 'sign' || elType === 'initials';
        // Font size only applies to text/date — hide row for sign/initials.
        $('#propFontSizeRow').toggle(isTextOrDate);
        if (isTextOrDate) {
            $('#propFontSize').val(el.getAttribute('data-font-size') || '');
        }
        var assignedTo = (el.getAttribute('data-assigned-to') || '').toLowerCase().trim();
        var fieldCustomer = assignedTo ? (_linkedCustomers[assignedTo] || null) : null;
        // Always show the customer key selector for text/date fields — needed for API templates
        // even when no live customer is linked yet.
        $('#propCustomerKeyRow').toggle(isTextOrDate);
        if (isTextOrDate) {
            $('#propCustomerKey').val(el.getAttribute('data-customer-key') || '');
        }
        // Only show the override value row when a customer IS linked and a key is chosen
        var hasCustomerKey = isTextOrDate && (el.getAttribute('data-customer-key') || '') !== '';
        var showValueRow = hasCustomerKey && fieldCustomer !== null;
        $('#propCustomerValueRow').toggle(showValueRow);
        if (showValueRow) {
            $('#propCustomerValue').val(el.getAttribute('data-customer-value') || '');
        }
        // Default signature row: sign/initials fields assigned to __admin__ only
        var showDefaultSign = isSignOrInitials && assignedTo === '__admin__';
        $('#propDefaultSignRow').toggle(showDefaultSign);
        if (showDefaultSign) { refreshDefaultSignPreview(el); }
        validateName($('#propName').val(), el);
        state.suppressPropSync = false;
    }

    function validateName(name, ignoreEl) {
        var ok = /^[a-z][a-z0-9_]{0,119}$/.test(name);
        var dup = false;
        if (ok) {
            $('#pdfStack .df-field').each(function () {
                if (this === ignoreEl) return;
                if ((this.getAttribute('data-name') || '') === name) dup = true;
            });
        }
        var $help = $('#propNameHelp');
        var $inp  = $('#propName');
        if (!ok) {
            $inp.addClass('ds-input--error');
            $help.addClass('ds-help--error').text('Use only a-z, 0-9, _, must start with a letter');
            return false;
        }
        if (dup) {
            $inp.addClass('ds-input--error');
            $help.addClass('ds-help--error').text('Another field already uses this identifier');
            return false;
        }
        $inp.removeClass('ds-input--error');
        $help.removeClass('ds-help--error').text('Lowercase letters, digits and underscores. Must start with a letter.');
        return true;
    }

    // ------------------------------------------------------------------
    // Default signature for admin sign/initials fields
    // ------------------------------------------------------------------

    // Updates the small inline preview img INSIDE the field box on the canvas
    function refreshFieldBoxSigPreview(el) {
        if (!el) return;
        var dataUrl = el.getAttribute('data-default-value') || '';
        var $img = $(el).find('.df-field__sig-preview');
        var $typeLabel = $(el).find('.df-field__type');
        if (dataUrl) {
            $img.attr('src', dataUrl).show();
            $typeLabel.hide(); // hide the "SIGN" text when preview is visible
        } else {
            $img.attr('src', '').hide();
            $typeLabel.show();
        }
    }

    // Updates the properties panel Default Signature preview (the one in the right sidebar)
    function refreshDefaultSignPreview(el) {
        var dataUrl = el ? (el.getAttribute('data-default-value') || '') : '';
        if (dataUrl) {
            $('#propDefaultSignImg').attr('src', dataUrl).show();
            $('#propDefaultSignEmpty').hide();
        } else {
            $('#propDefaultSignImg').hide().attr('src', '');
            $('#propDefaultSignEmpty').show();
        }
        // Also sync the on-canvas field box preview
        refreshFieldBoxSigPreview(el);
    }

    function openDefaultSigModal() {
        var el = state.selected;
        if (!el) return;
        var type = el.getAttribute('data-type') || 'sign';
        var label = type === 'initials' ? 'Draw your initials' : 'Draw your signature';

        // Build modal content
        var $content = $('<div style="padding:20px;min-width:360px;"></div>');
        var $heading = $('<div style="font-weight:600;font-size:14px;margin-bottom:12px;color:var(--ds-text);">' + label + '</div>');
        var $canvasWrap = $('<div style="border:1.5px solid var(--ds-border,#e2e8f0);border-radius:8px;background:#fff;overflow:hidden;"></div>');
        var canvas = $('<canvas width="380" height="140" style="display:block;touch-action:none;"></canvas>')[0];
        $canvasWrap.append(canvas);
        var $actions = $('<div style="display:flex;gap:8px;margin-top:12px;">');
        var $clearBtn = $('<button type="button" class="ds-btn ds-btn--ghost">Clear</button>');
        var $saveBtn  = $('<button type="button" class="ds-btn ds-btn--primary" style="margin-left:auto;">Save default</button>');
        $actions.append($clearBtn).append($saveBtn);
        $content.append($heading).append($canvasWrap).append($actions);

        var modal = window.DSModal.open($content[0], { persistent: true });

        var pad = new SignaturePad(canvas, { penColor: '#0f172a' });
        // Pre-load existing if any
        var existing = el.getAttribute('data-default-value') || '';
        if (existing) { pad.fromDataURL(existing, { width: 380, height: 140 }); }

        $clearBtn.on('click', function () { pad.clear(); });
        $saveBtn.on('click', function () {
            if (pad.isEmpty()) {
                window.DSToast.show('Please draw a signature first', 'error');
                return;
            }
            var dataUrl = pad.toDataURL('image/png');
            el.setAttribute('data-default-value', dataUrl);
            refreshDefaultSignPreview(el);
            modal.close();
            window.DSToast.show('Default signature saved — it will auto-fill during finalization.', 'success');
        });
    }

    function wirePropertiesPanel() {
        $('#propName').on('input', function () {
            if (state.suppressPropSync || !state.selected) return;
            var val = this.value.trim();
            if (!validateName(val, state.selected)) return;
            state.selected.setAttribute('data-name', val);
            $(state.selected).find('.df-field__label').text(val);
            refreshFieldList();
        });
        $('#propLabel').on('input', function () {
            if (state.suppressPropSync || !state.selected) return;
            state.selected.setAttribute('data-label', this.value);
        });
        $('#propRequired').on('change', function () {
            if (state.suppressPropSync || !state.selected) return;
            state.selected.setAttribute('data-required', this.checked ? '1' : '0');
            $(state.selected).toggleClass('df-field--required', this.checked);
        });
        var dimHandler = function () {
            if (state.suppressPropSync || !state.selected) return;
            var x = parseFloat($('#propX').val()) || 0;
            var y = parseFloat($('#propY').val()) || 0;
            var w = Math.max(20, parseFloat($('#propW').val()) || 20);
            var h = Math.max(14, parseFloat($('#propH').val()) || 14);
            state.selected.style.left = x + 'px';
            state.selected.style.top  = y + 'px';
            state.selected.style.width = w + 'px';
            state.selected.style.height = h + 'px';
            state.selected.setAttribute('data-x', x);
            state.selected.setAttribute('data-y', y);
            state.selected.setAttribute('data-w', w);
            state.selected.setAttribute('data-h', h);
            var $autofill = $(state.selected).find('.df-field__autofill');
            if ($autofill.length) builderAutoShrink($autofill[0]);
        };
        $('#propX, #propY, #propW, #propH').on('input', dimHandler);

        $('#propFontSize').on('input', function () {
            if (state.suppressPropSync || !state.selected) return;
            var raw = $(this).val();
            var v   = parseFloat(raw);
            // Empty / invalid / out-of-range → clear back to auto-fit.
            if (!raw || isNaN(v) || v < 6 || v > 48) {
                state.selected.setAttribute('data-font-size', '');
                applyFieldFontSize($(state.selected), null);
            } else {
                state.selected.setAttribute('data-font-size', v);
                applyFieldFontSize($(state.selected), v);
            }
            // Re-run autofit so the autofill preview matches the new state
            // (it short-circuits when data-font-size is set, otherwise shrinks).
            var $autofill = $(state.selected).find('.df-field__autofill');
            if ($autofill.length) builderAutoShrink($autofill[0]);
        });

        $('#propAssignedTo').on('change', function () {
            if (state.suppressPropSync || !state.selected) return;
            state.selected.setAttribute('data-assigned-to', this.value);
            // Refresh autofill previews — use '__admin__' override for the admin sentinel
            Object.keys(_linkedCustomers).forEach(function (em) {
                updateFieldPreviews(_linkedCustomers[em], em === '__admin__' ? '__admin__' : null);
            });
            syncFormFromElement();
        });

        $('#propCustomerKey').on('change', function () {
            if (state.suppressPropSync || !state.selected) return;
            var key = this.value;
            state.selected.setAttribute('data-customer-key', key);
            $(state.selected).find('.df-field__preview').remove();
            $(state.selected).find('.df-field__autofill').remove();
            $(state.selected).removeClass('df-field--customer-mapped');
            var assignedEmail = (state.selected.getAttribute('data-assigned-to') || '').toLowerCase().trim();
            var linkedCust = assignedEmail ? (_linkedCustomers[assignedEmail] || null) : null;
            if (!key) {
                state.selected.setAttribute('data-customer-value', '');
                $('#propCustomerValueRow').hide();
            } else {
                // Show live value override row only when a customer is linked
                if (linkedCust) {
                    updateFieldPreviews(linkedCust, assignedEmail === '__admin__' ? '__admin__' : null);
                    $('#propCustomerValueRow').show();
                    $('#propCustomerValue').val(state.selected.getAttribute('data-customer-value') || '');
                } else {
                    // No customer linked yet — key is saved for API template use, no override row needed
                    $('#propCustomerValueRow').hide();
                }
                // Show a visual indicator on the field that a mapping is set
                $(state.selected).addClass('df-field--customer-mapped');
            }
        });

        $('#propCustomerValue').on('input', function () {
            if (state.suppressPropSync || !state.selected) return;
            var val = this.value;
            state.selected.setAttribute('data-customer-value', val);
            var $autofill = $(state.selected).find('.df-field__autofill');
            if ($autofill.length) {
                $autofill.text(val);
                builderAutoShrink($autofill[0]);
            }
        });

        $('#propPage').on('change', function () {
            if (state.suppressPropSync || !state.selected) return;
            var pageNum = parseInt(this.value, 10);
            var $page = $('#pdfStack .page-wrap[data-page="' + pageNum + '"]');
            if ($page.length) $page.find('.field-layer').append(state.selected);
        });

        $('#propDuplicate').on('click', function () { duplicateSelected(); });
        $('#propDelete').on('click', function () { deleteSelected(); });

        // Default signature buttons
        $('#propSetDefaultSign').on('click', function () { openDefaultSigModal(); });
        $('#propClearDefaultSign').on('click', function () {
            var el = state.selected;
            if (!el) return;
            el.removeAttribute('data-default-value');
            refreshDefaultSignPreview(el);
        });
    }

    function refreshPagesSelect() {
        var $sel = $('#propPage').empty();
        for (var i = 1; i <= state.pageCount; i++) {
            $sel.append('<option value="' + i + '">Page ' + i + '</option>');
        }
        if (state.selected) {
            var page = parseInt(state.selected.parentNode.parentNode.getAttribute('data-page'), 10);
            $sel.val(String(page));
        }
    }

    function refreshFieldList() {
        var rows = collectFields();
        var $host = $('#fieldList').empty();
        if (!rows.length) {
            $host.append('<div style="color:var(--ds-muted);font-size:11px;">No fields yet — click + Text, + Date or + Signature.</div>');
            return;
        }
        rows.forEach(function (f) {
            var dotColor = f.type === 'sign'     ? '#16a34a' :
                           f.type === 'initials' ? '#f59e0b' :
                           f.type === 'date'     ? '#9333ea' : '#2563eb';
            var assigneeBadge = f.assigned_to
                ? '<span style="color:#0055ff;font-size:9px;max-width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="' + escapeHtml(f.assigned_to) + '">→ ' + escapeHtml(f.assigned_to.split('@')[0]) + '</span>'
                : '';
            var $row = $(
                '<div class="ds-flex ds-row-gap" style="align-items:center;padding:4px 6px;border-radius:4px;cursor:pointer;">' +
                    '<span style="display:inline-block;width:6px;height:6px;border-radius:50%;flex-shrink:0;background:' + dotColor + '"></span>' +
                    '<span style="font-family:ui-monospace,monospace;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' + escapeHtml(f.name || '(unnamed)') + '</span>' +
                    assigneeBadge +
                    '<span style="color:var(--ds-muted);font-size:11px;flex-shrink:0;">p' + f.page + '</span>' +
                    (f.required ? '<span class="ds-badge ds-badge--required" style="font-size:9px;">REQ</span>' : '') +
                '</div>'
            );
            $row.on('click', function () {
                var el = document.querySelector('.df-field[data-id="' + f.id + '"]');
                if (el) {
                    select(el);
                    el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            });
            $host.append($row);
        });
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // -----------------------------------------------------------------
    // Keyboard
    // -----------------------------------------------------------------
    function wireKeyboard() {
        $(document).on('keydown', function (e) {
            if (!state.selected) return;
            var tag = (e.target.tagName || '').toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select') return;
            var step = e.shiftKey ? 10 : 1;
            var el = state.selected;
            var x = parseFloat(el.getAttribute('data-x'));
            var y = parseFloat(el.getAttribute('data-y'));
            if (e.key === 'ArrowLeft')  { x -= step; e.preventDefault(); }
            else if (e.key === 'ArrowRight') { x += step; e.preventDefault(); }
            else if (e.key === 'ArrowUp')    { y -= step; e.preventDefault(); }
            else if (e.key === 'ArrowDown')  { y += step; e.preventDefault(); }
            else if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'd') {
                duplicateSelected(); e.preventDefault(); return;
            }
            else if (e.key === 'Backspace' || e.key === 'Delete') {
                deleteSelected(); e.preventDefault(); return;
            }
            else { return; }
            el.style.left = x + 'px';
            el.style.top  = y + 'px';
            el.setAttribute('data-x', x);
            el.setAttribute('data-y', y);
            syncFormFromElement();
        });
    }

    function duplicateSelected() {
        if (!state.selected) return;
        var src = state.selected;
        var page = $(src).closest('.page-wrap');
        var clone = addFieldBox(
            page,
            src.getAttribute('data-type'),
            parseFloat(src.getAttribute('data-x')) + 16,
            parseFloat(src.getAttribute('data-y')) + 16,
            parseFloat(src.getAttribute('data-w')),
            parseFloat(src.getAttribute('data-h')),
            {
                name:        suggestName(src.getAttribute('data-type')),
                label:       src.getAttribute('data-label'),
                required:    src.getAttribute('data-required') === '1',
                assigned_to: src.getAttribute('data-assigned-to') || '',
                font_size:   parseFloat(src.getAttribute('data-font-size')) || null
            }
        );
        select(clone);
    }

    function deleteSelected() {
        if (!state.selected) return;
        var el = state.selected;
        $(el).fadeOut(120, function () { $(this).remove(); refreshFieldList(); });
        select(null);
    }

    // -----------------------------------------------------------------
    // Save
    // -----------------------------------------------------------------
    function collectFields() {
        var out = [];
        $('#pdfStack .page-wrap').each(function () {
            var page = parseInt($(this).attr('data-page'), 10);
            $(this).find('.df-field').each(function () {
                out.push({
                    id:          this.getAttribute('data-id'),
                    name:        this.getAttribute('data-name') || '',
                    page:        page,
                    type:        this.getAttribute('data-type'),
                    x:           parseFloat(this.getAttribute('data-x')) || 0,
                    y:           parseFloat(this.getAttribute('data-y')) || 0,
                    w:           parseFloat(this.getAttribute('data-w')) || 0,
                    h:           parseFloat(this.getAttribute('data-h')) || 0,
                    label:       this.getAttribute('data-label') || '',
                    required:       this.getAttribute('data-required') === '1',
                    assigned_to:    this.getAttribute('data-assigned-to') || null,
                    customer_key:   this.getAttribute('data-customer-key') || '',
                    customer_value: this.getAttribute('data-customer-value') || '',
                    font_size:      parseFloat(this.getAttribute('data-font-size')) || null,
                    default_value:  this.getAttribute('data-default-value') || ''
                });
            });
        });
        return out;
    }

    function onSave() {
        var fields = collectFields();
        if (!fields.length) {
            window.DSToast.show('Add at least one field first', 'error');
            return;
        }
        // Validate identifiers locally before we hit the server.
        var seen = {};
        for (var i = 0; i < fields.length; i++) {
            var n = fields[i].name;
            if (!/^[a-z][a-z0-9_]{0,119}$/.test(n)) {
                window.DSToast.show('Field "' + (n || '(empty)') + '" has an invalid identifier', 'error');
                return;
            }
            if (seen[n]) {
                window.DSToast.show('Duplicate identifier: ' + n, 'error');
                return;
            }
            seen[n] = true;
        }
        var $btn = $('#saveFields').attr('aria-busy', 'true');
        $.ajax({
            url: '/documents/fields',
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify({ doc_id: DOC_ID, render_scale: RENDER_SCALE, fields: fields })
        }).done(function (res) {
            $btn.attr('aria-busy', 'false');
            if (res && res.result === 'success') {
                $('#goToFill').removeClass('hidden').attr('href', '/documents/' + encodeURIComponent(DOC_ID) + '/fill');
                window.DSToast.show('Saved ' + res.count + ' field' + (res.count === 1 ? '' : 's'), 'success');
            } else {
                window.DSToast.show((res && res.message) || 'Save failed', 'error');
            }
        }).fail(function (xhr) {
            $btn.attr('aria-busy', 'false');
            window.DSToast.show((xhr.responseJSON && xhr.responseJSON.message) || 'Save failed', 'error');
        });
    }
    // -----------------------------------------------------------------
    // API Template section
    // -----------------------------------------------------------------
    var isTemplate    = $('#pdfStack').data('is-template') === 1;
    var isApiTemplate = $('#pdfStack').data('is-api-template') === 1;

    function updateApiSection() {
        if (!isTemplate) {
            $('#apiTemplateSection').hide();
            return;
        }
        $('#apiTemplateSection').css('display', 'inline-flex');
        if (isApiTemplate) {
            $('#apiToggleBtn').removeClass('ds-btn--ghost').addClass('ds-btn--primary');
            $('#apiToggleLabel').text('API Enabled');
        } else {
            $('#apiToggleBtn').addClass('ds-btn--ghost').removeClass('ds-btn--primary');
            $('#apiToggleLabel').text('Enable API');
        }
    }
    updateApiSection();

    $('#apiToggleBtn').on('click', function () {
        var $btn = $(this).attr('aria-busy', 'true');
        $.post('/api/templates/' + encodeURIComponent(DOC_ID) + '/toggle')
            .done(function (res) {
                if (res && res.result === 'success') {
                    isApiTemplate = !!res.is_api_template;
                    updateApiSection();
                    window.DSToast.show(
                        isApiTemplate ? 'API template enabled' : 'API template disabled',
                        'success'
                    );
                } else {
                    window.DSToast.show((res && res.message) || 'Toggle failed', 'error');
                }
            })
            .fail(function () {
                window.DSToast.show('Failed to toggle API template', 'error');
            })
            .always(function () {
                $btn.attr('aria-busy', 'false');
            });
    });

    // Webhook URL — notified when documents created from this template complete
    var webhookUrl = $('#pdfStack').data('webhook-url') || '';
    $('#webhookBtn').on('click', function () {
        window.DSModal.prompt(
            'We will POST a JSON notification to this URL when a document from this template is fully signed. Leave empty to disable.',
            webhookUrl,
            function (url) {
                url = (url || '').trim();
                $.ajax({
                    url: '/api/templates/' + encodeURIComponent(DOC_ID) + '/webhook',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ webhook_url: url || null }),
                }).done(function (res) {
                    if (res.result === 'success') {
                        webhookUrl = res.webhook_url || '';
                        window.DSToast.show(webhookUrl ? 'Webhook saved.' : 'Webhook removed.', 'success');
                    } else {
                        window.DSToast.show(res.message || 'Could not save webhook.', 'error');
                    }
                }).fail(function (xhr) {
                    var msg = 'Could not save webhook.';
                    if (xhr.responseJSON && xhr.responseJSON.errors && xhr.responseJSON.errors.webhook_url) {
                        msg = xhr.responseJSON.errors.webhook_url.join(' ');
                    }
                    window.DSToast.show(msg, 'error');
                });
            }
        );
    });

})(jQuery);

