<x-layouts.signing title="Sign Document — {{ $docTitle }}">

<div class="bg-[var(--ds-bg)] min-h-screen flex flex-col ds-signing-page">
    <div class="ds-topbar">
        <div class="flex flex-wrap items-center gap-3 px-3 sm:px-6 py-3">
            <div class="min-w-0">
                <div class="text-xs text-[var(--ds-muted)]">Document signing</div>
                <h1 class="text-base font-semibold text-[var(--ds-text)] truncate">{{ $docTitle }}</h1>
            </div>
            <div class="ml-auto flex flex-wrap items-center gap-3 min-w-0" style="flex:1;justify-content:flex-end;">
                <div class="ds-zoom" role="group" aria-label="Zoom">
                    <button type="button" id="zoomOut" class="ds-iconbtn" title="Zoom out" aria-label="Zoom out">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </button>
                    <button type="button" id="zoomFit" class="ds-zoom__fit" title="Fit to width" aria-label="Fit to width"><span id="zoomLabel">100%</span> Fit</button>
                    <button type="button" id="zoomIn" class="ds-iconbtn" title="Zoom in" aria-label="Zoom in">
                        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    </button>
                </div>
                {{-- Desktop: progress + actions live in the topbar; on phones the bottom bar takes over --}}
                <div id="progressWrap" class="ds-signing-desktop flex items-center gap-2" style="font-size:12px;color:var(--ds-muted);">
                    <span id="progressText" aria-live="polite">0 of 0 complete</span>
                    <div class="ds-progress" id="progressBar"><div class="ds-progress__bar"></div></div>
                </div>
                <button id="nextField" type="button" class="ds-btn ds-btn--ghost ds-signing-desktop" aria-label="Go to next required field">Next field</button>
                <button id="generatePdf" type="button" class="ds-btn ds-btn--success ds-signing-desktop">
                    <span class="ds-spinner"></span>
                    <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    <span>Complete Signing</span>
                </button>
            </div>
        </div>
    </div>

    <div id="pdfStack" class="flex-1 px-3 sm:px-6 py-4 sm:py-6 bg-[var(--ds-bg)] overflow-auto ds-signing-stack"
         data-doc-id="{{ $docId }}"
         data-base-url="/signing"
         data-pdf-base="/signing"
         data-recipient-id="{{ $recipientId }}"
         data-recipient-email="{{ $recipientEmail }}"
         data-access-token="{{ $accessToken }}"></div>

    {{-- Sticky bottom bar (phones, <640px) --}}
    <div class="ds-signbar" role="region" aria-label="Signing progress">
        <div class="ds-signbar__progress">
            <span class="js-progress-text ds-signbar__text" aria-live="polite">0 of 0 complete</span>
            <div class="ds-progress js-progress-bar"><div class="ds-progress__bar"></div></div>
        </div>
        <button type="button" class="ds-btn ds-btn--ghost js-next-field" aria-label="Go to next required field">Next field</button>
        <button type="button" class="ds-btn ds-btn--success js-generate">
            <span class="ds-spinner"></span>
            <span>Complete signing</span>
        </button>
    </div>
</div>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4/dist/signature_pad.umd.min.js"></script>
<script>
$.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
</script>
<script src="{{ asset('js/fill.js') }}"></script>
@endpush

</x-layouts.signing>
