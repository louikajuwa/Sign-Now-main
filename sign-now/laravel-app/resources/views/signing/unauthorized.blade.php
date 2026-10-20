<x-layouts.signing title="Access Denied">

<div class="bg-[var(--ds-bg)] min-h-screen flex items-center justify-center p-4 sm:p-6">
    <div style="max-width:480px;width:100%;text-align:center;">

        <div style="margin-bottom:24px;">
            <img src="{{ asset('favicon.ico') }}"
                 alt="Sign-Now" style="width:100%;max-width:360px;height:auto;margin:0 auto 24px;border-radius:12px;">
        </div>

        <div class="ds-card ds-status-card" role="status" style="padding:40px 32px;">
            <div style="width:72px;height:72px;border-radius:50%;background:#fef3c7;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                <svg width="36" height="36" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                </svg>
            </div>

            <h1 style="font-size:22px;font-weight:700;color:var(--ds-text,#0f172a);margin:0 0 12px;">
                Access denied
            </h1>

            <p style="font-size:14px;color:var(--ds-muted,#64748b);line-height:1.6;margin:0 0 8px;">
                You don't have permission to view this document.
            </p>
            <p style="font-size:14px;color:var(--ds-muted,#64748b);line-height:1.6;margin:0 0 24px;">
                If you received a signing invitation, please use the link from your email. Access links expire after 1 hour of inactivity.
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
