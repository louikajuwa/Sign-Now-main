<x-layouts.app title="View — {{ $docTitle }}">
<x-app-shell>
    <x-slot name="topbar">
        <a href="{{ route('dashboard') }}" class="ds-iconbtn" aria-label="Back to dashboard" title="Back to dashboard">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
        <div class="ds-topbar__titles">
            <p class="ds-topbar__sub">View document</p>
            <h1 class="ds-topbar__title" style="font-size:var(--ds-text-lg);overflow-wrap:anywhere;">{{ $docTitle }}</h1>
        </div>
        <div class="ds-topbar__actions">
            @if($hasSubmissions)
            <div class="ds-tabs" id="viewTabs">
                <button type="button" class="ds-tab ds-tab--active" data-view="signed" aria-pressed="true">Signed</button>
                <button type="button" class="ds-tab" data-view="original" aria-pressed="false">Original</button>
            </div>
            @endif
            <a href="{{ route('documents.download', [$docId, 'original']) }}" class="ds-btn ds-btn--ghost ds-btn--sm">Download original</a>
            @if($hasSubmissions)
            <a href="{{ route('documents.download', [$docId, 'latest']) }}" class="ds-btn ds-btn--primary ds-btn--sm">Download signed</a>
            @endif
            @if($canSendComplete)
            <button type="button" id="sendCompleteBtn" class="ds-btn ds-btn--ghost ds-btn--sm">Send completion email</button>
            @endif
            <button type="button" id="activityBtn" class="ds-btn ds-btn--ghost ds-btn--sm" aria-haspopup="dialog">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Activity
            </button>
        </div>
    </x-slot>

    <div id="pdfViewer" class="ds-pdf-viewer" aria-live="polite" aria-busy="true">
        <div class="ds-pdf-state"><span class="ds-spinner" aria-hidden="true"></span><span>Loading document…</span></div>
    </div>
</x-app-shell>

{{-- Activity / audit trail slide-over --}}
<div id="activityPanel" class="ds-slideover" hidden>
    <div id="activityBackdrop" class="ds-slideover__backdrop"></div>
    <aside class="ds-slideover__panel" role="dialog" aria-modal="true" aria-labelledby="activityTitle" tabindex="-1" id="activityDialog">
        <div class="ds-slideover__head">
            <div>
                <h2 class="ds-slideover__title" id="activityTitle">Activity</h2>
                <div class="ds-meta">Full audit trail for this document</div>
            </div>
            <button type="button" id="activityClose" class="ds-iconbtn" aria-label="Close activity panel">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div id="activityList" class="ds-slideover__body" aria-live="polite"></div>
    </aside>
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
(function () {
    var currentView = '{{ $hasSubmissions ? "signed" : "original" }}';
    var docId = '{{ $docId }}';
    var viewer = document.getElementById('pdfViewer');
    var currentPdf = null;
    var renderToken = 0;
    var lastWidth = 0;

    if (window.pdfjsLib) {
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }

    function showState(html, isError) {
        viewer.innerHTML = '';
        viewer.setAttribute('aria-busy', 'false');
        var box = document.createElement('div');
        box.className = 'ds-pdf-state';
        if (isError) {
            var msg = document.createElement('span');
            msg.textContent = 'Could not load this document.';
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'ds-btn ds-btn--ghost ds-btn--sm';
            btn.textContent = 'Retry';
            btn.addEventListener('click', function () { loadPdf(currentView === 'signed' ? 'latest' : 'original'); });
            box.appendChild(msg);
            box.appendChild(btn);
        }
        viewer.appendChild(box);
    }

    function availableWidth() {
        var cs = getComputedStyle(viewer);
        return viewer.clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight);
    }

    // Render pages one after another, in order, fit to container width
    async function renderPages() {
        if (!currentPdf) return;
        var token = ++renderToken;
        var width = availableWidth();
        lastWidth = width;
        viewer.setAttribute('aria-busy', 'true');
        var frag = [];
        try {
            for (var n = 1; n <= currentPdf.numPages; n++) {
                var page = await currentPdf.getPage(n);
                if (token !== renderToken) return;
                var base = page.getViewport({ scale: 1 });
                var scale = Math.min(1.5, width / base.width);
                var viewport = page.getViewport({ scale: scale });
                var wrap = document.createElement('div');
                wrap.className = 'page-wrap';
                wrap.style.width = viewport.width + 'px';
                wrap.style.height = viewport.height + 'px';
                var canvas = document.createElement('canvas');
                canvas.width = viewport.width;
                canvas.height = viewport.height;
                wrap.appendChild(canvas);
                frag.push(wrap);
                if (n === 1) { viewer.innerHTML = ''; }
                viewer.appendChild(wrap);
                await page.render({ canvasContext: canvas.getContext('2d'), viewport: viewport }).promise;
                if (token !== renderToken) return;
                if (n === 1) {
                    // first page replaced any previous render; clear leftovers on re-render
                }
            }
            viewer.setAttribute('aria-busy', 'false');
        } catch (err) {
            if (token === renderToken) showState('', true);
        }
    }

    function loadPdf(which) {
        var url = '/documents/' + docId + '/serve/' + which;
        renderToken++;
        currentPdf = null;
        viewer.setAttribute('aria-busy', 'true');
        viewer.innerHTML = '<div class="ds-pdf-state"><span class="ds-spinner" aria-hidden="true"></span><span>Loading document…</span></div>';
        pdfjsLib.getDocument(url).promise.then(function (pdf) {
            currentPdf = pdf;
            renderPages();
        }).catch(function () {
            showState('', true);
        });
    }

    var resizeTimer;
    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function () {
            if (currentPdf && Math.abs(availableWidth() - lastWidth) > 2) {
                viewer.innerHTML = '';
                renderPages();
            }
        }, 250);
    });

    @if($hasSubmissions)
    $('#viewTabs').on('click', '.ds-tab', function () {
        var view = $(this).data('view');
        currentView = view;
        $('#viewTabs .ds-tab').removeClass('ds-tab--active').attr('aria-pressed', 'false');
        $(this).addClass('ds-tab--active').attr('aria-pressed', 'true');
        loadPdf(view === 'signed' ? 'latest' : 'original');
    });
    @endif

    @if($canSendComplete)
    $('#sendCompleteBtn').on('click', function () {
        DSModal.confirm('Send the completion email with the signed PDF to all signed recipients?', function () {
            $.post('/documents/' + docId + '/send-complete', { _token: '{{ csrf_token() }}' })
                .done(function (res) {
                    if (res.result === 'success') {
                        DSToast.success(res.message || 'Completion email sent.');
                    } else {
                        DSToast.error(res.message || 'Could not send completion email.');
                    }
                })
                .fail(function (xhr) {
                    var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Could not send completion email.';
                    DSToast.error(msg);
                });
        }, null, { title: 'Send completion email', confirmText: 'Send' });
    });
    @endif

    loadPdf(currentView === 'signed' ? 'latest' : 'original');

    // ── Activity / audit trail ──
    var ACTION_LABELS = {
        document_uploaded: 'Document uploaded',
        fields_saved: 'Fields updated',
        invite_sent: 'Invitation email sent',
        recipient_opened: 'Recipient opened the signing link',
        document_signed: 'Document signed',
        nda_complete_sent: 'Completion email sent',
        api_template_triggered: 'Created via API trigger',
        customer_recipient_added: 'Customer added as recipient',
        document_deleted: 'Document deleted',
        webhook_sent: 'Webhook notification sent',
        webhook_failed: 'Webhook notification failed'
    };

    function loadActivity() {
        var $list = $('#activityList').empty()
            .append($('<div class="ds-pdf-state">').append($('<span class="ds-spinner" aria-hidden="true">')).append($('<span>').text('Loading activity…')));
        $.getJSON('/documents/' + docId + '/audit', function (res) {
            $list.empty();
            var logs = res.logs || [];
            if (!logs.length) {
                $list.append($('<div class="ds-pdf-state">').text('No activity recorded yet.'));
                return;
            }
            logs.forEach(function (log) {
                var type = String(log.performed_by_type || '');
                var dotClass = (type === 'admin' || type === 'recipient') ? ' ds-activity__dot--' + type : '';
                var label = ACTION_LABELS[log.action] || String(log.action || '').replace(/_/g, ' ');
                var when = log.created_at ? new Date(log.created_at).toLocaleString() : '';
                var meta = [when];
                if (log.performed_by) meta.push(log.performed_by + (type ? ' (' + type + ')' : ''));
                if (log.ip_address) meta.push(log.ip_address);
                var bits = [];
                if (log.meta) {
                    if (log.meta.recipient_email) bits.push('to ' + log.meta.recipient_email);
                    if (log.meta.sent_count) bits.push(log.meta.sent_count + ' recipient(s)');
                    if (log.meta.trigger) bits.push('trigger: ' + log.meta.trigger);
                    if (log.meta.field_count != null) bits.push(log.meta.field_count + ' field(s)');
                }
                var $body = $('<div style="min-width:0;flex:1;">')
                    .append($('<div class="ds-activity__title">').text(label))
                    .append($('<div class="ds-activity__meta">').text(meta.filter(Boolean).join(' · ')));
                if (bits.length) $body.append($('<div class="ds-activity__meta">').text(bits.join(' · ')));
                $list.append($('<div class="ds-activity">')
                    .append($('<div class="ds-activity__dot' + dotClass.replace(/[^a-z_\- ]/gi, '') + '">'))
                    .append($body));
            });
        }).fail(function () {
            $list.empty().append($('<div class="ds-pdf-state">')
                .append($('<span>').text('Could not load activity.'))
                .append($('<button type="button" class="ds-btn ds-btn--ghost ds-btn--sm">').text('Retry').on('click', loadActivity)));
        });
    }

    var panel = document.getElementById('activityPanel');
    var dialog = document.getElementById('activityDialog');
    var opener = null;

    function openActivity() {
        opener = document.activeElement;
        panel.hidden = false;
        document.getElementById('activityClose').focus();
        loadActivity();
    }
    function closeActivity() {
        if (panel.hidden) return;
        panel.hidden = true;
        if (opener && typeof opener.focus === 'function') opener.focus();
    }

    $('#activityBtn').on('click', openActivity);
    $('#activityClose, #activityBackdrop').on('click', closeActivity);
    document.addEventListener('keydown', function (e) {
        if (panel.hidden) return;
        if (e.key === 'Escape') { e.preventDefault(); closeActivity(); return; }
        if (e.key === 'Tab') {
            var items = dialog.querySelectorAll('button, a[href], [tabindex]:not([tabindex="-1"])');
            if (!items.length) return;
            var first = items[0], last = items[items.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }
    });
})();
</script>
@endpush

</x-layouts.app>
