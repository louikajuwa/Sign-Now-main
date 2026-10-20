<x-layouts.app title="Documents — Sign Now">
<x-app-shell>
    <x-slot name="topbar">
        <div class="ds-topbar__titles">
            <h1 class="ds-topbar__title">Documents</h1>
            <p class="ds-topbar__sub"><span id="totalCountLabel">0</span> total</p>
        </div>
        <div class="ds-topbar__actions">
            <button type="button" class="ds-btn ds-btn--primary" id="uploadBtn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Upload document
            </button>
        </div>
    </x-slot>

    <div class="ds-card" id="mainCard">
        <div class="ds-card__header">
            <div class="ds-tabs" id="dashTabs" role="tablist" aria-label="Library">
                <button type="button" class="ds-tab ds-tab--active" data-tab="documents" role="tab" id="tabDocuments" aria-selected="true" aria-controls="documentsPanel">
                    Documents <span class="ds-tab__count" id="docCount">0</span>
                </button>
                @if(auth()->user()->is_admin)
                <button type="button" class="ds-tab" data-tab="templates" role="tab" id="tabTemplates" aria-selected="false" aria-controls="templatesPanel">
                    Templates <span class="ds-tab__count" id="templateCount">0</span>
                </button>
                @endif
            </div>

            <form class="ds-filters" id="filterForm" role="search" onsubmit="return false;">
                <label class="ds-sr-only" for="searchInput">Search</label>
                <input type="search" name="q" id="searchInput" placeholder="Search by title or filename" class="ds-input" autocomplete="off">
                <span id="docFilters" class="ds-filters">
                    <label class="ds-sr-only" for="statusFilter">Filter by status</label>
                    <select name="status" id="statusFilter" class="ds-select">
                        <option value="all">All statuses</option>
                        <option value="in_progress">In progress</option>
                        <option value="done">Done</option>
                    </select>
                    <label class="ds-sr-only" for="sortSelect">Sort by</label>
                    <select name="sort" id="sortSelect" class="ds-select">
                        <option value="recent">Most recent</option>
                        <option value="name">Name (A→Z)</option>
                        <option value="pages">Most pages</option>
                        <option value="created_asc">Created (oldest)</option>
                        <option value="created_desc">Created (newest)</option>
                    </select>
                </span>
            </form>

            <div class="ds-meta" id="docSubCounts">
                <span id="pendingCountLabel">0</span> in progress,
                <span id="completedCountLabel">0</span> done
            </div>
        </div>

        {{-- Documents panel --}}
        <div id="documentsPanel" role="tabpanel" aria-labelledby="tabDocuments">
            <div class="ds-table-wrap">
                <table class="ds-table">
                    <thead>
                        <tr>
                            <th>Document</th>
                            <th>Pages</th>
                            <th class="ds-hide-sm">Fields</th>
                            <th>Status</th>
                            <th class="ds-hide-sm">From template</th>
                            <th class="ds-hide-sm">Created</th>
                            <th class="ds-hide-sm">Last activity</th>
                            <th class="ds-text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="documentsBody" aria-live="polite"></tbody>
                </table>
            </div>
            <div class="ds-pagination" id="paginationContainer">
                <span class="ds-pagination__info" id="paginationInfo"></span>
                <nav class="ds-pagination__btns" id="paginationBtns" aria-label="Pagination"></nav>
            </div>
        </div>

        {{-- Templates panel --}}
        @if(auth()->user()->is_admin)
        <div id="templatesPanel" role="tabpanel" aria-labelledby="tabTemplates" hidden>
            <div class="ds-table-wrap">
                <table class="ds-table">
                    <thead>
                        <tr>
                            <th>Template</th>
                            <th>Pages</th>
                            <th>Fields</th>
                            <th class="ds-hide-sm">Created</th>
                            <th class="ds-text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="templatesBody" aria-live="polite"></tbody>
                </table>
            </div>
        </div>
        @endif
    </div>
</x-app-shell>

{{-- Upload modal content (opened with DSModal) --}}
<div id="uploadModalContent" hidden>
    <div class="ds-modal__header" id="uploadModalTitle">Upload document</div>
    <div class="ds-modal__body">
        <div class="ds-drop" id="dropZone" role="button" tabindex="0" aria-label="Choose a file to upload">
            <input type="file" id="fileInput" accept=".pdf,.doc,.docx" tabindex="-1">
            <div class="ds-drop__title">Drop your file here or click to browse</div>
            <p class="ds-meta" style="margin-top:4px;">PDF, DOC, or DOCX — max 25 MB</p>
        </div>
    </div>
    <div class="ds-modal__footer">
        <button type="button" class="ds-btn ds-btn--ghost" id="cancelUpload">Cancel</button>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var currentPage = 1;
    var currentTab = 'documents';
    var loaded = false;
    var reqSeq = 0;
    var uploadModal = null;
    var csrf = '{{ csrf_token() }}';
    var wsUrl = @json(env('WEBSOCKET_URL', 'ws://localhost:3027'));
    var hasTemplates = !!document.getElementById('templatesPanel');

    var STATUS_LABELS = {
        draft: 'Draft', ready: 'Ready', filled: 'Filled', completed: 'Completed',
        in_progress: 'In progress', done: 'Done', pending: 'Pending'
    };
    var ICONS = {
        edit: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
        fill: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>',
        view: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        rename: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M17 3a2.828 2.828 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5L17 3z"/></svg>',
        del: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
        upload: '<svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><polyline points="9 15 12 12 15 15"/></svg>'
    };

    function statusLabel(s) {
        s = String(s || '');
        if (STATUS_LABELS[s]) return STATUS_LABELS[s];
        var t = s.replace(/_/g, ' ');
        return t.charAt(0).toUpperCase() + t.slice(1);
    }
    function fmtDate(v) { return v ? new Date(v).toLocaleDateString() : '—'; }

    function iconLink(href, label, icon) {
        return $('<a>').addClass('ds-iconbtn').attr({ href: href, 'aria-label': label, title: label }).html(icon);
    }
    function iconButton(cls, label, icon) {
        return $('<button type="button">').addClass('ds-iconbtn ' + cls).attr({ 'aria-label': label, title: label }).html(icon);
    }

    /* ---------- table states ---------- */
    function skeletonRows(cols, n) {
        var $rows = $();
        for (var i = 0; i < n; i++) {
            var $tr = $('<tr>');
            for (var c = 0; c < cols; c++) {
                $tr.append($('<td>').append($('<span>').addClass('ds-skeleton ' + (c === 0 ? 'ds-skeleton--lg' : 'ds-skeleton--sm'))));
            }
            $rows = $rows.add($tr);
        }
        return $rows;
    }
    function stateRow(cols, $content, isError) {
        return $('<tr>').addClass('ds-state-row' + (isError ? ' ds-state-row--error' : ''))
            .append($('<td>').attr('colspan', cols).append($content));
    }
    function errorRow(cols, text, retryClass) {
        var $c = $('<div>').append($('<p>').text(text))
            .append($('<button type="button">').addClass('ds-btn ds-btn--ghost ds-btn--sm ds-mt-3 ' + retryClass).text('Retry'));
        return stateRow(cols, $c, true);
    }

    /* ---------- documents ---------- */
    function filtersActive() {
        return ($('#searchInput').val() || '').trim() !== '' || ($('#statusFilter').val() || 'all') !== 'all';
    }

    function loadDashboard() {
        var q = $('#searchInput').val() || '';
        var status = $('#statusFilter').val() || 'all';
        var sort = $('#sortSelect').val() || 'recent';
        var seq = ++reqSeq;

        if (!loaded) { $('#documentsBody').empty().append(skeletonRows(8, 5)); }
        else { $('#mainCard').addClass('ds-card--loading'); }

        $.getJSON('{{ route("dashboard.data") }}', { q: q, status: status, sort: sort, page: currentPage }, function (data) {
            if (seq !== reqSeq) return;
            loaded = true;
            $('#mainCard').removeClass('ds-card--loading');
            $('#totalCountLabel').text(data.total);
            $('#docCount').text(data.total);
            $('#pendingCountLabel').text(data.pendingCount);
            $('#completedCountLabel').text(data.completedCount);
            $('#templateCount').text(data.templateCount);
            currentPage = data.page || currentPage;
            renderDocuments(data.documents);
            renderPagination(data.page, data.totalPages, data.total);
        }).fail(function () {
            if (seq !== reqSeq) return;
            loaded = false;
            $('#mainCard').removeClass('ds-card--loading');
            $('#documentsBody').empty().append(errorRow(8, 'Could not load documents.', 'js-retry-docs'));
            $('#paginationInfo').text('');
            $('#paginationBtns').empty();
        });
    }

    function renderDocuments(docs) {
        var $tbody = $('#documentsBody').empty();
        if (!docs.length) {
            var $c = $('<div class="ds-empty" style="padding:24px;">');
            $c.append($('<div class="ds-empty__icon">').html(ICONS.upload));
            if (filtersActive()) {
                $c.append($('<div class="ds-empty__title">').text('No matching documents'));
                $c.append($('<p class="ds-empty__text">').text('Try a different search or clear the filters.'));
                $c.append($('<div class="ds-empty__cta">').append($('<button type="button" class="ds-btn ds-btn--ghost js-clear-filters">').text('Clear filters')));
            } else {
                $c.append($('<div class="ds-empty__title">').text('No documents yet'));
                $c.append($('<p class="ds-empty__text">').text('Upload a PDF or Word document to place fields and collect signatures.'));
                $c.append($('<div class="ds-empty__cta">').append($('<button type="button" class="ds-btn ds-btn--primary js-upload-trigger">').text('Upload a document')));
            }
            $tbody.append(stateRow(8, $c, false).addClass('ds-empty-row'));
            return;
        }
        docs.forEach(function (d) {
            var uuid = encodeURIComponent(d.uuid);
            var badgeClass = 'ds-badge--' + String(d.status).replace(/_/g, '-').replace(/[^a-z0-9-]/gi, '');
            var $tr = $('<tr>').attr({ 'data-uuid': d.uuid, 'data-title': d.title });

            $tr.append($('<td>')
                .append($('<div class="ds-cell-title">').text(d.title))
                .append($('<div class="ds-cell-sub">').text(d.original_filename)));
            $tr.append($('<td>').text(d.page_count));
            $tr.append($('<td class="ds-hide-sm">').text(d.field_count));
            $tr.append($('<td>').append($('<span>').addClass('ds-badge ' + badgeClass).text(statusLabel(d.status))));
            $tr.append($('<td class="ds-hide-sm">').append(d.template_id ? $('<span class="ds-badge ds-badge--draft">').text('From template') : document.createTextNode('—')));
            $tr.append($('<td class="ds-hide-sm">').text(fmtDate(d.created_at)));
            $tr.append($('<td class="ds-hide-sm">').text(fmtDate(d.last_activity_at)));
            $tr.append($('<td class="ds-cell-actions">')
                .append(iconLink('/documents/' + uuid + '/edit', 'Edit fields', ICONS.edit))
                .append(iconLink('/documents/' + uuid + '/fill', 'Fill and sign', ICONS.fill))
                .append(iconLink('/documents/' + uuid + '/view', 'View', ICONS.view))
                .append(iconButton('js-rename', 'Rename', ICONS.rename))
                .append(iconButton('ds-iconbtn--danger js-delete', 'Delete', ICONS.del)));
            $tbody.append($tr);
        });
    }

    /* ---------- pagination ---------- */
    function pageItems(page, total) {
        var set = {}, out = [], last = 0;
        [1, total, page - 1, page, page + 1].forEach(function (p) { if (p >= 1 && p <= total) set[p] = true; });
        Object.keys(set).map(Number).sort(function (a, b) { return a - b; }).forEach(function (p) {
            if (last && p - last > 1) out.push('…');
            out.push(p);
            last = p;
        });
        return out;
    }

    function renderPagination(page, totalPages, total) {
        $('#paginationInfo').text('Page ' + page + ' of ' + totalPages + ' (' + total + ' total)');
        var $btns = $('#paginationBtns').empty();
        if (totalPages <= 1) return;
        $btns.append($('<button type="button" class="ds-btn ds-btn--ghost ds-btn--sm">').attr({ 'data-page': page - 1, 'aria-label': 'Previous page' }).prop('disabled', page <= 1).text('←'));
        pageItems(page, totalPages).forEach(function (p) {
            if (p === '…') { $btns.append($('<span class="ds-pagination__ellipsis" aria-hidden="true">').text('…')); return; }
            var $b = $('<button type="button">').attr({ 'data-page': p, 'aria-label': 'Page ' + p })
                .addClass('ds-btn ds-btn--sm ' + (p === page ? 'ds-btn--primary' : 'ds-btn--ghost')).text(p);
            if (p === page) $b.attr('aria-current', 'page');
            $btns.append($b);
        });
        $btns.append($('<button type="button" class="ds-btn ds-btn--ghost ds-btn--sm">').attr({ 'data-page': page + 1, 'aria-label': 'Next page' }).prop('disabled', page >= totalPages).text('→'));
    }

    $('#paginationBtns').on('click', 'button[data-page]', function () {
        currentPage = parseInt($(this).attr('data-page'), 10) || 1;
        loadDashboard();
    });

    /* ---------- row actions ---------- */
    function renameDoc(uuid, currentTitle) {
        DSModal.prompt('New title', currentTitle, function (val) {
            val = (val || '').trim();
            if (!val || val === currentTitle) return;
            $.post('/documents/' + encodeURIComponent(uuid) + '/rename', { title: val, _token: csrf }, function () {
                DSToast('Document renamed', 'success');
                loadDashboard();
            }).fail(function () { DSToast('Could not rename document', 'error'); });
        }, null, { title: 'Rename document' });
    }

    function deleteDoc(uuid, title) {
        DSModal.confirm('Delete "' + title + '"? This action cannot be undone.', function () {
            $.post('/documents/' + encodeURIComponent(uuid) + '/delete', { _token: csrf }, function () {
                DSToast('Document deleted', 'success');
                loadDashboard();
            }).fail(function () { DSToast('Could not delete document', 'error'); });
        }, null, { title: 'Delete document', confirmText: 'Delete', danger: true });
    }

    $('#documentsBody').on('click', '.js-rename', function () {
        var $tr = $(this).closest('tr');
        renameDoc($tr.attr('data-uuid'), $tr.attr('data-title'));
    });
    $('#documentsBody').on('click', '.js-delete', function () {
        var $tr = $(this).closest('tr');
        deleteDoc($tr.attr('data-uuid'), $tr.attr('data-title'));
    });
    $('#documentsBody').on('click', '.js-retry-docs', loadDashboard);
    $('#documentsBody').on('click', '.js-upload-trigger', function () { $('#uploadBtn').trigger('click'); });
    $('#documentsBody').on('click', '.js-clear-filters', function () {
        $('#searchInput').val('');
        $('#statusFilter').val('all');
        currentPage = 1;
        loadDashboard();
    });

    /* ---------- tabs / templates ---------- */
    $('#dashTabs').on('click', '.ds-tab', function () {
        var tab = $(this).data('tab');
        currentTab = tab;
        $('#dashTabs .ds-tab').removeClass('ds-tab--active').attr('aria-selected', 'false');
        $(this).addClass('ds-tab--active').attr('aria-selected', 'true');
        if (tab === 'documents') {
            $('#documentsPanel').prop('hidden', false);
            $('#templatesPanel').prop('hidden', true);
            $('#docFilters, #docSubCounts').show();
        } else {
            $('#documentsPanel').prop('hidden', true);
            $('#templatesPanel').prop('hidden', false);
            $('#docFilters, #docSubCounts').hide();
            loadTemplates();
        }
    });

    function loadTemplates() {
        var $tbody = $('#templatesBody').empty().append(skeletonRows(5, 4));
        $.getJSON('{{ route("templates.data") }}', { search: $('#searchInput').val() || '' }, function (data) {
            $tbody.empty();
            if (!data.templates.length) {
                $tbody.append(stateRow(5, $('<div class="ds-empty__title">').text('No templates'), false).addClass('ds-empty-row'));
                return;
            }
            data.templates.forEach(function (t) {
                var $tr = $('<tr>').attr('data-uuid', t.uuid);
                $tr.append($('<td>').append($('<div class="ds-cell-title">').text(t.title)));
                $tr.append($('<td>').text(t.page_count));
                $tr.append($('<td>').text(t.field_count));
                $tr.append($('<td class="ds-hide-sm">').text(fmtDate(t.created_at)));
                $tr.append($('<td class="ds-cell-actions">')
                    .append(iconLink('/documents/' + encodeURIComponent(t.uuid) + '/edit', 'Edit template', ICONS.edit))
                    .append($('<button type="button" class="ds-btn ds-btn--ghost ds-btn--sm js-use-template">').text('Use')));
                $tbody.append($tr);
            });
        }).fail(function () {
            $tbody.empty().append(errorRow(5, 'Could not load templates.', 'js-retry-templates'));
        });
    }
    $('#templatesBody').on('click', '.js-retry-templates', loadTemplates);
    $('#templatesBody').on('click', '.js-use-template', function () {
        var uuid = $(this).closest('tr').attr('data-uuid');
        $.post('/templates/' + encodeURIComponent(uuid) + '/use', { _token: csrf }, function (data) {
            if (data.result === 'success') { window.location.href = data.edit_url; }
        }).fail(function () { DSToast('Could not create a document from this template', 'error'); });
    });

    /* ---------- upload ---------- */
    var uploadContent = document.getElementById('uploadModalContent');
    var fileInput = document.getElementById('fileInput');
    var dropZone = document.getElementById('dropZone');

    $('#uploadBtn').on('click', function () {
        uploadModal = DSModal.open(uploadContent, { labelledBy: 'uploadModalTitle', onClose: function () { uploadModal = null; } });
    });
    $('#cancelUpload').on('click', function () { if (uploadModal) uploadModal.close(); });
    $(dropZone).on('click', function () { fileInput.click(); });
    $(dropZone).on('keydown', function (e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
    });
    $(dropZone).on('dragover dragenter', function (e) { e.preventDefault(); $(this).addClass('ds-drop--over'); });
    $(dropZone).on('dragleave drop', function (e) { e.preventDefault(); $(this).removeClass('ds-drop--over'); });
    $(dropZone).on('drop', function (e) {
        var files = e.originalEvent.dataTransfer && e.originalEvent.dataTransfer.files;
        if (files && files.length) uploadFile(files[0]);
    });
    $(fileInput).on('click', function (e) { e.stopPropagation(); });
    $(fileInput).on('change', function () {
        if (this.files[0]) uploadFile(this.files[0]);
        this.value = '';
    });

    function uploadFile(file) {
        if (file.size > 25 * 1024 * 1024) { DSToast('File is larger than 25 MB', 'error'); return; }
        var formData = new FormData();
        formData.append('file', file);
        formData.append('_token', csrf);
        DSLoader.show('Uploading document…');
        $.ajax({
            url: '{{ route("documents.upload") }}',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function (data) {
                DSLoader.hide();
                if (uploadModal) uploadModal.close();
                if (data.result === 'success') {
                    window.location.href = data.edit_url;
                }
            },
            error: function () {
                DSLoader.hide();
                DSToast('Upload failed', 'error');
            }
        });
    }

    /* ---------- search / filters ---------- */
    var searchTimeout;
    $('#searchInput, #statusFilter, #sortSelect').on('input change', function () {
        clearTimeout(searchTimeout);
        currentPage = 1;
        searchTimeout = setTimeout(currentTab === 'templates' && hasTemplates ? loadTemplates : loadDashboard, 300);
    });

    /* ---------- WebSocket (real-time updates, reconnects with backoff) ---------- */
    var ws = null, wsRetry = 0, wsStop = false;

    function onWsMessage(e) {
        var msg;
        try { msg = JSON.parse(e.data); } catch (err) { return; }
        if (!msg || ['recipient_signed', 'document_finalized', 'status_changed'].indexOf(msg.event) === -1) return;
        var d = msg.data || {};
        loadDashboard();
        if (msg.event === 'recipient_signed') {
            DSToast(d.recipient_email + ' signed "' + d.doc_title + '"', 'success');
        } else if (msg.event === 'document_finalized') {
            DSToast('"' + d.doc_title + '" has been finalized', 'success');
        }
    }

    function connect() {
        if (wsStop) return;
        try { ws = new WebSocket(wsUrl); } catch (err) { console.warn('WebSocket not available:', err); return; }
        ws.onopen = function () { wsRetry = 0; };
        ws.onmessage = onWsMessage;
        ws.onclose = function () {
            if (wsStop) return;
            var delay = Math.min(30000, 1000 * Math.pow(2, wsRetry++));
            setTimeout(connect, delay);
        };
        ws.onerror = function () { try { ws.close(); } catch (err) { /* ignore */ } };
    }
    window.addEventListener('beforeunload', function () { wsStop = true; if (ws) { try { ws.close(); } catch (err) { /* ignore */ } } });
    connect();

    loadDashboard();
})();
</script>
@endpush

</x-layouts.app>
