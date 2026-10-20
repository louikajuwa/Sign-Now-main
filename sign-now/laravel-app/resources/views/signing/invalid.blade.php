<x-layouts.signing title="Invalid Link">

<div class="bg-[var(--ds-bg)] min-h-screen flex items-center justify-center p-4 sm:p-6">
    <div style="max-width:480px;width:100%;text-align:center;">

        <div style="margin-bottom:24px;">
            <img src="{{ asset('favicon.ico') }}"
                 alt="Sign-Now" style="width:100%;max-width:360px;height:auto;margin:0 auto 24px;border-radius:12px;">
        </div>

        <div class="ds-card ds-status-card" role="status" style="padding:40px 32px;">
            <div style="width:72px;height:72px;border-radius:50%;background:#fef2f2;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    <line x1="15" y1="9" x2="9" y2="15"/>
                    <line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
            </div>

            <h1 style="font-size:22px;font-weight:700;color:var(--ds-text,#0f172a);margin:0 0 12px;">
                Invalid signing link
            </h1>

            <p style="font-size:14px;color:var(--ds-muted,#64748b);line-height:1.6;margin:0 0 8px;">
                This link is invalid or has been removed.
            </p>
            <p style="font-size:14px;color:var(--ds-muted,#64748b);line-height:1.6;margin:0 0 24px;">
                Please check that you've copied the full URL from your invitation email, or contact the sender for a new link.
            </p>

            <a href="mailto:{{ config('mail.from.address') }}"
               class="ds-btn ds-btn--primary"
               style="display:inline-flex;align-items:center;gap:8px;padding:10px 24px;font-size:14px;">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
                Contact Support
            </a>
        </div>

        <p style="margin-top:20px;font-size:11px;color:var(--ds-muted,#94a3b8);">
            &copy; {{ date('Y') }} Sign-Now. All Rights Reserved.
        </p>
    </div>
</div>

</x-layouts.signing>
