<x-layouts.app title="Finalize — {{ $docTitle }}">

<x-app-shell :fullbleed="true">
<x-slot:topbar>
    <div class="flex flex-wrap items-center gap-3 px-3 sm:px-6 py-3">
        <a href="{{ route('dashboard') }}" class="ds-iconbtn" title="Back to dashboard" aria-label="Back to dashboard">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
        </a>
        <div class="min-w-0">
            <div class="text-xs text-[var(--ds-muted)]">Admin finalization</div>
            <h1 class="text-base font-semibold text-[var(--ds-text)] truncate">{{ $docTitle }}</h1>
        </div>
        <div class="ml-auto flex flex-wrap items-center gap-2 sm:gap-3 min-w-0" style="flex:1;justify-content:flex-end;">
            <div class="ds-zoom" role="group" aria-label="Zoom">
                <button type="button" id="zoomOut" class="ds-iconbtn" title="Zoom out" aria-label="Zoom out">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </button>
                <button type="button" id="zoomFit" class="ds-zoom__fit" title="Fit to width" aria-label="Fit to width"><span id="zoomLabel">100%</span> Fit</button>
                <button type="button" id="zoomIn" class="ds-iconbtn" title="Zoom in" aria-label="Zoom in">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </button>
            </div>
            <div class="ds-addbar" role="group" aria-label="Add a field to the document">
                <button type="button" class="ds-btn ds-btn--ghost ds-addfield-btn" data-field-type="text" aria-label="Add text field">+ Text</button>
                <button type="button" class="ds-btn ds-btn--ghost ds-addfield-btn" data-field-type="date" aria-label="Add date field">+ Date</button>
                <button type="button" class="ds-btn ds-btn--ghost ds-addfield-btn" data-field-type="sign" aria-label="Add signature field">+ Signature</button>
                <button type="button" class="ds-btn ds-btn--ghost ds-addfield-btn" data-field-type="initials" aria-label="Add initials field">+ Initials</button>
            </div>
            <div id="progressWrap" class="flex items-center gap-2" style="font-size:12px;color:var(--ds-muted);">
                <span id="progressText" aria-live="polite">0 of 0 complete</span>
                <div class="ds-progress" id="progressBar"><div class="ds-progress__bar"></div></div>
            </div>
            <button id="generatePdf" type="button" class="ds-btn ds-btn--success">
                <span class="ds-spinner"></span>
                <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                <span>Finalize Document</span>
            </button>
        </div>
    </div>
    <div id="placementHint" class="ds-placement-hint" role="status">
        <span>Click on the page to place the new field.</span>
        <button type="button" id="cancelPlacement" class="ds-btn ds-btn--ghost ds-btn--sm">Cancel</button>
    </div>
</x-slot:topbar>

<div class="ds-page-fill ds-finalize">
    <div id="pdfStack" class="flex-1 p-3 sm:p-6 bg-[var(--ds-bg)] overflow-auto"
         data-doc-id="{{ $docId }}"
         data-base-url="/documents"
         data-pdf-base="/documents"
         data-is-finalize="1"
         data-base-pdf-url="{{ route('documents.serve', [$docId, 'latest']) }}"
         data-recipient-id="0"
         data-recipient-email=""
         data-access-token=""></div>

    <aside id="finalizePanel" class="ds-fpanel" aria-label="Field details">
        <div id="finalizePanelEmpty" class="ds-fpanel__empty">
            Select a field on the document to review or edit its value.
            <br><br>
            Use <strong>+ Text</strong>, <strong>+ Date</strong>, <strong>+ Signature</strong> or <strong>+ Initials</strong> above to add extra fields.
        </div>
        <div id="finalizePanelEditor" class="ds-fpanel__editor" style="display:none;">
            <div class="ds-fpanel__head">
                <div class="min-w-0">
                    <div id="finalizePanelType" class="ds-fpanel__type"></div>
                    <div id="finalizePanelLabel" class="ds-fpanel__label"></div>
                </div>
                <button type="button" id="finalizePanelClose" class="ds-iconbtn" title="Close" aria-label="Close field panel">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
            <div id="finalizePanelBody" class="ds-fpanel__body"></div>
        </div>
    </aside>
</div>
</x-app-shell>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4/dist/signature_pad.umd.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>
<script>
$.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
</script>
<script src="{{ asset('js/fill.js') }}"></script>
@endpush

</x-layouts.app>
