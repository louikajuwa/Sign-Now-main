<x-layouts.app title="Edit Fields — {{ $docTitle }}">

<x-app-shell :fullbleed="true">
<x-slot:topbar>
        <div class="flex flex-wrap items-center gap-3 px-6 py-3">
            <a href="{{ route('dashboard') }}" class="ds-iconbtn" title="Back to dashboard" aria-label="Back to dashboard">
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
            </a>
            <div>
                <div class="text-xs text-[var(--ds-muted)]">Editing fields</div>
                <h1 class="text-base font-semibold text-[var(--ds-text)]" id="docTitle">{{ $docTitle }}</h1>
            </div>
            <div class="ml-auto flex flex-wrap items-center gap-2">
                <label class="ds-toggle" title="Snap dragging to grid">
                    <input type="checkbox" id="snapToggle" checked>
                    <span class="ds-toggle__track"></span>
                    <span>Snap</span>
                </label>
                <select id="snapSize" aria-label="Snap grid size" class="ds-select" style="width:70px;">
                    <option value="4">4 px</option>
                    <option value="8" selected>8 px</option>
                    <option value="16">16 px</option>
                </select>
                <button id="addText" class="ds-btn ds-btn--ghost" type="button">+ Text</button>
                <button id="addDate" class="ds-btn ds-btn--ghost" type="button">+ Date</button>
                <button id="addSign" class="ds-btn ds-btn--ghost" type="button">+ Signature</button>
                <button id="addInitials" class="ds-btn ds-btn--ghost" type="button">+ Initials</button>
                <button id="saveFields" class="ds-btn ds-btn--success" type="button">
                    <span class="ds-spinner"></span>
                    <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                    <span>Save fields</span>
                </button>
                <button id="saveAsTemplate" class="ds-btn ds-btn--ghost" type="button">
                    <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>
                    <span>Save as template</span>
                </button>
                @if($isTemplate)
                <span id="apiTemplateSection" class="flex items-center gap-1">
                    <span style="width:1px;height:20px;background:var(--ds-border);margin:0 2px;"></span>
                    <button id="apiToggleBtn" type="button" class="ds-btn ds-btn--ghost" style="font-size:12px;gap:5px;">
                        <span id="apiToggleLabel">{{ $isApiTemplate ? 'Disable API' : 'Enable API' }}</span>
                    </button>
                    <button id="webhookBtn" type="button" class="ds-btn ds-btn--ghost" style="font-size:12px;gap:5px;" title="Notify a URL when documents from this template are completed">
                        Webhook
                    </button>
                </span>
                @endif
                <a id="goToFill" class="ds-btn ds-btn--primary hidden" href="{{ route('documents.fill', $docId) }}">Test fill →</a>
                <span style="width:1px;height:24px;background:var(--ds-border,#e2e8f0);margin:0 4px;"></span>
                <button type="button" id="openRecipients" class="ds-btn ds-btn--ghost ds-btn--sm" title="Manage recipients">
                    <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    <span>Recipients</span>
                    <span class="ds-badge" id="recipientCountBadge" style="font-size:10px;padding:1px 6px;">0</span>
                </button>
            </div>
        </div>
</x-slot:topbar>

<div class="ds-page-fill ds-page-fill--builder">

    {{-- Recipients panel --}}
    <div id="recipientsPanel" class="ds-recipients-panel" style="display:none;">
        <div class="flex items-center gap-3 px-6 py-3" style="border-bottom:1px solid var(--ds-border,#e2e8f0);">
            <span class="text-sm font-semibold text-[var(--ds-text)]">Recipients</span>
            <span class="text-xs text-[var(--ds-muted)]" id="recipientSummary">0 recipients</span>
            <div class="ml-auto flex items-center gap-2">
                @if(!$isTemplate)
                <input type="email" id="recipientInput" aria-label="Recipient email" autocomplete="off" spellcheck="false" class="ds-input" placeholder="Add email address..." style="width:240px;height:32px;font-size:13px;">
                <button type="button" id="addRecipient" class="ds-btn ds-btn--primary ds-btn--sm">+ Add</button>
                @else
                <span class="text-xs text-[var(--ds-muted)]">Recipients are added automatically when this template is used — only the Admin slot is set here.</span>
                @endif
                <button type="button" id="closeRecipients" class="ds-iconbtn" title="Close panel" aria-label="Close recipients panel">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
            </div>
        </div>
        <div id="recipientsList" class="px-6 py-3" style="display:flex;flex-wrap:wrap;gap:8px;max-height:200px;overflow-y:auto;"></div>

        {{-- Customer search --}}
        <div style="border-top:1px solid var(--ds-border,#e2e8f0);padding:12px 24px;">
            <div class="flex items-center gap-2" style="margin-bottom:8px;">
                <svg aria-hidden="true" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--ds-accent,#2563eb)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <span style="font-size:12px;font-weight:600;color:var(--ds-text);">Add Existing Customer</span>
                <a href="{{ route('customers.index') }}" target="_blank" style="font-size:11px;color:var(--ds-accent,#2563eb);margin-left:auto;">Manage customers</a>
            </div>
            <div class="flex items-center gap-2">
                <input type="text" id="customerSearchInput" aria-label="Search customers" autocomplete="off" class="ds-input" placeholder="Search customers..."
                       style="flex:1;height:32px;font-size:13px;">
                <select id="customerSearchKey" aria-label="Search by" class="ds-select" style="width:110px;height:32px;font-size:13px;">
                    <option value="all">All fields</option>
                    <option value="email">Email</option>
                    <option value="company">Company</option>
                    <option value="name">Name</option>
                    <option value="UserID">User ID</option>
                </select>
            </div>
            <div id="customerSearchResults" class="ds-customer-results" style="display:none;"></div>
        </div>
    </div>

    <div class="ds-builder flex" style="flex:1; min-height:0;">
        <div id="pdfStack" class="flex-1 p-6 bg-[var(--ds-bg)] overflow-auto"
             data-doc-id="{{ $docId }}"
             data-is-template="{{ $isTemplate ? '1' : '0' }}"
             data-is-api-template="{{ $isApiTemplate ? '1' : '0' }}"
             data-webhook-url="{{ $webhookUrl }}"
             tabindex="0"></div>

        <aside class="ds-properties" id="properties">
            <div class="ds-properties__placeholder" id="propsEmpty">
                Select a field on the page to edit its identifier, label, size and position.
                <br><br>
                <strong style="color:var(--ds-text);">Tips</strong><br>
                <span style="font-family:ui-monospace,monospace;">←↑→↓</span> nudge 1 px<br>
                <span style="font-family:ui-monospace,monospace;">Shift + arrow</span> nudge 10 px<br>
                <span style="font-family:ui-monospace,monospace;">Cmd/Ctrl + D</span> duplicate<br>
                <span style="font-family:ui-monospace,monospace;">Delete</span> remove
            </div>
            <div id="propsForm" class="ds-props-form hidden">
                <div class="ds-properties__row">
                    <label class="ds-properties__label" for="propName">Identifier</label>
                    <input id="propName" class="ds-input" placeholder="customer_name" maxlength="120" autocomplete="off" style="font-family:ui-monospace,SFMono-Regular,Menlo,monospace;">
                    <div class="ds-help" id="propNameHelp">Lowercase letters, digits and underscores. Must start with a letter.</div>
                </div>
                <div class="ds-properties__row">
                    <label class="ds-properties__label" for="propLabel">Display label</label>
                    <input id="propLabel" autocomplete="off" class="ds-input" placeholder="What the filler sees" maxlength="200">
                </div>
                <div class="ds-properties__row">
                    <label class="ds-toggle"><input type="checkbox" id="propRequired"><span class="ds-toggle__track"></span><span>Required</span></label>
                </div>
                <div class="ds-properties__row">
                    <label class="ds-properties__label" for="propAssignedTo">Assigned to</label>
                    <select id="propAssignedTo" class="ds-select"><option value="">All recipients</option></select>
                    <div class="ds-help">Restrict this field to one specific recipient</div>
                </div>
                <div class="ds-properties__row" id="propCustomerKeyRow" style="display:none;">
                    <label class="ds-properties__label" for="propCustomerKey">Maps to customer data</label>
                    <select id="propCustomerKey" class="ds-select">
                        <option value="">— None —</option>
                        <optgroup label="Name"><option value="name">First name</option><option value="lastname">Last name</option><option value="fullName">Full name</option></optgroup>
                        <optgroup label="Contact"><option value="email">Email</option><option value="phone">Phone</option></optgroup>
                        <optgroup label="Address"><option value="street">Street</option><option value="city">City</option><option value="state">State</option><option value="zip">ZIP</option><option value="country">Country</option><option value="fullAddress">Full address</option></optgroup>
                        <optgroup label="Company"><option value="companyName">Company name</option></optgroup>
                        <optgroup label="Other"><option value="UserID">Customer ID</option></optgroup>
                    </select>
                </div>
                <div class="ds-properties__row" id="propCustomerValueRow" style="display:none;">
                    <label class="ds-properties__label" for="propCustomerValue">Autofill value</label>
                    <input id="propCustomerValue" class="ds-input" placeholder="Override auto-fill value" maxlength="500" autocomplete="off">
                </div>
                <div class="ds-properties__row">
                    <label class="ds-properties__label" for="propPage">Page</label>
                    <select id="propPage" class="ds-select"></select>
                </div>
                <div class="ds-properties__row">
                    <label class="ds-properties__label">Position &amp; size</label>
                    <div class="ds-properties__grid2">
                        <div><div class="ds-help" aria-hidden="true">X</div><input id="propX" aria-label="X position" class="ds-input" type="number" step="1"></div>
                        <div><div class="ds-help" aria-hidden="true">Y</div><input id="propY" aria-label="Y position" class="ds-input" type="number" step="1"></div>
                        <div><div class="ds-help" aria-hidden="true">Width</div><input id="propW" aria-label="Width" class="ds-input" type="number" step="1" min="20"></div>
                        <div><div class="ds-help" aria-hidden="true">Height</div><input id="propH" aria-label="Height" class="ds-input" type="number" step="1" min="14"></div>
                    </div>
                </div>
                <div class="ds-properties__row" id="propFontSizeRow">
                    <label class="ds-properties__label" for="propFontSize">Font size (px)</label>
                    <input id="propFontSize" class="ds-input" type="number" min="6" max="48" step="1" placeholder="auto">
                </div>
                <div class="ds-properties__row" id="propDefaultSignRow" style="display:none;">
                    <label class="ds-properties__label">Default signature</label>
                    <div id="propDefaultSignPreview" style="min-height:44px;border:1.5px dashed var(--ds-border,#e2e8f0);border-radius:6px;display:flex;align-items:center;justify-content:center;margin-bottom:6px;overflow:hidden;background:#f8fafc;">
                        <span id="propDefaultSignEmpty" style="font-size:11px;color:var(--ds-muted,#94a3b8);">No default</span>
                        <img id="propDefaultSignImg" style="display:none;max-width:100%;max-height:56px;object-fit:contain;" alt="">
                    </div>
                    <div class="flex gap-2">
                        <button id="propSetDefaultSign" class="ds-btn ds-btn--ghost ds-grow" type="button">Draw default</button>
                        <button id="propClearDefaultSign" aria-label="Clear default signature" class="ds-btn ds-btn--ghost" type="button" style="padding:0 10px;">&times;</button>
                    </div>
                </div>
                <div class="flex gap-2">
                    <button id="propDuplicate" class="ds-btn ds-btn--ghost ds-grow" type="button">Duplicate</button>
                    <button id="propDelete" class="ds-btn ds-btn--danger ds-grow" type="button">Delete</button>
                </div>
            </div>
            <div style="margin-top:auto;border-top:1px solid var(--ds-border);padding-top:12px;">
                <div class="ds-properties__label" style="margin-bottom:8px;">All fields</div>
                <div id="fieldList" style="display:flex;flex-direction:column;gap:4px;font-size:12px;"></div>
            </div>
        </aside>
    </div>
</div>
</x-app-shell>

@push('scripts')
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/interactjs/dist/interact.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4/dist/signature_pad.umd.min.js"></script>
<script>
$.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });
</script>
<script src="{{ asset('js/builder.js') }}"></script>
@endpush

</x-layouts.app>
