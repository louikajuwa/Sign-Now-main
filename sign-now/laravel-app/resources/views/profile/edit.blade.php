<x-layouts.app title="Profile — Sign Now">
<x-app-shell>
    <x-slot name="topbar">
        <div class="ds-topbar__titles">
            <p class="ds-topbar__sub">Account</p>
            <h1 class="ds-topbar__title">Profile</h1>
        </div>
    </x-slot>

    @php
        $flashMessages = [
            'profile-updated' => 'Profile updated successfully.',
            'password-updated' => 'Password changed successfully.',
            'signing-updated' => 'Signing profile saved.',
        ];
        $flash = $flashMessages[session('status', '')] ?? null;
        $checkIcon = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="20 6 9 17 4 12"/></svg>';
    @endphp

    <div class="ds-narrow ds-stack" style="gap:var(--ds-space-5);">

        {{-- Flash messages --}}
        @if($flash)
        <div class="ds-alert ds-alert--success" role="status">
            {!! $checkIcon !!}
            <span>{{ $flash }}</span>
        </div>
        @endif

        {{-- ── Profile Information ─────────────────────────────── --}}
        <section class="ds-card ds-card--pad" aria-labelledby="profileInfoTitle">
            <div class="ds-section-head">
                <h2 class="ds-section-title" id="profileInfoTitle">Profile information</h2>
                <p class="ds-section-sub">Update your name and email address.</p>
            </div>

            <form method="POST" action="{{ route('profile.update') }}" class="ds-stack" data-busy>
                @csrf
                @method('patch')

                <div class="ds-field">
                    <label class="ds-label" for="name">Name</label>
                    <input id="name" name="name" type="text" class="ds-input @error('name') ds-input--error @enderror" value="{{ old('name', $user->name) }}" required autocomplete="name" @error('name') aria-invalid="true" aria-describedby="name-error" @enderror>
                    @error('name')
                        <div class="ds-help ds-help--error" id="name-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="ds-field">
                    <label class="ds-label" for="email">Email</label>
                    <input id="email" name="email" type="email" class="ds-input @error('email') ds-input--error @enderror" value="{{ old('email', $user->email) }}" required autocomplete="email" @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                    @error('email')
                        <div class="ds-help ds-help--error" id="email-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <button type="submit" class="ds-btn ds-btn--primary">
                        <span class="ds-spinner" aria-hidden="true"></span>
                        <span>Save changes</span>
                    </button>
                </div>
            </form>
        </section>

        {{-- ── Signing Profile ─────────────────────────────────── --}}
        <section class="ds-card ds-card--pad" aria-labelledby="signingProfileTitle">
            <div class="ds-section-head">
                <h2 class="ds-section-title" id="signingProfileTitle">Signing profile</h2>
                <p class="ds-section-sub">
                    Save your signature and details once — they auto-fill admin-assigned fields when you finalize documents.
                </p>
            </div>

            <form method="POST" action="{{ route('profile.signing') }}" id="signingProfileForm" class="ds-stack" data-busy>
                @csrf

                <div class="ds-form-grid">
                    <div class="ds-field">
                        <label class="ds-label" for="sp_lastname">Last name</label>
                        <input id="sp_lastname" name="lastname" type="text" autocomplete="family-name" class="ds-input" value="{{ old('lastname', $user->lastname) }}">
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="sp_company">Company</label>
                        <input id="sp_company" name="company" type="text" autocomplete="organization" class="ds-input" value="{{ old('company', $user->company) }}">
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="sp_phone">Phone</label>
                        <input id="sp_phone" name="phone" type="tel" autocomplete="tel" class="ds-input" value="{{ old('phone', $user->phone) }}">
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="sp_street">Street</label>
                        <input id="sp_street" name="street" type="text" autocomplete="address-line1" class="ds-input" value="{{ old('street', $user->street) }}">
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="sp_apt">Apt / Suite</label>
                        <input id="sp_apt" name="apt" type="text" autocomplete="address-line2" class="ds-input" value="{{ old('apt', $user->apt) }}">
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="sp_city">City</label>
                        <input id="sp_city" name="city" type="text" autocomplete="address-level2" class="ds-input" value="{{ old('city', $user->city) }}">
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="sp_state">State</label>
                        <input id="sp_state" name="state" type="text" autocomplete="address-level1" class="ds-input" value="{{ old('state', $user->state) }}">
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="sp_zip">ZIP</label>
                        <input id="sp_zip" name="zip" type="text" autocomplete="postal-code" inputmode="text" class="ds-input" value="{{ old('zip', $user->zip) }}">
                    </div>
                    <div class="ds-field">
                        <label class="ds-label" for="sp_country">Country</label>
                        <input id="sp_country" name="country" type="text" autocomplete="country-name" class="ds-input" value="{{ old('country', $user->country) }}">
                    </div>
                </div>

                <div>
                    <span class="ds-label" id="sigLabel">Saved signature</span>
                    @if($user->signature)
                    <div id="savedSignatureWrap" class="ds-sigsaved">
                        <img src="{{ $user->signature }}" alt="Your saved signature">
                        <button type="button" class="ds-btn ds-btn--ghost ds-btn--sm" id="clearSignatureBtn">Remove</button>
                    </div>
                    @endif
                    <div id="sigPadWrap" class="ds-sigpad">
                        <canvas id="sigPadCanvas" role="img" aria-label="Signature drawing area"></canvas>
                        <span id="sigPadHint" class="ds-sigpad__hint">Draw your signature here</span>
                    </div>
                    <div class="ds-mt-3">
                        <button type="button" class="ds-btn ds-btn--ghost ds-btn--sm" id="sigPadClear">Clear pad</button>
                    </div>
                </div>

                <input type="hidden" name="signature" id="signatureInput">
                <input type="hidden" name="clear_signature" id="clearSignatureInput" value="0">

                <div>
                    <button type="submit" class="ds-btn ds-btn--primary">
                        <span class="ds-spinner" aria-hidden="true"></span>
                        <span>Save signing profile</span>
                    </button>
                </div>
            </form>
        </section>

        {{-- ── Change Password ──────────────────────────────────── --}}
        <section class="ds-card ds-card--pad" aria-labelledby="passwordTitle">
            <div class="ds-section-head">
                <h2 class="ds-section-title" id="passwordTitle">Change password</h2>
                <p class="ds-section-sub">Use a strong password of at least 8 characters.</p>
            </div>

            <form method="POST" action="{{ route('password.update') }}" class="ds-stack" data-busy>
                @csrf
                @method('put')

                <div class="ds-field">
                    <label class="ds-label" for="current_password">Current password</label>
                    <input id="current_password" name="current_password" type="password" class="ds-input @error('current_password', 'updatePassword') ds-input--error @enderror" autocomplete="current-password" @error('current_password', 'updatePassword') aria-invalid="true" aria-describedby="current_password-error" @enderror>
                    @error('current_password', 'updatePassword')
                        <div class="ds-help ds-help--error" id="current_password-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="ds-field">
                    <label class="ds-label" for="password">New password</label>
                    <input id="password" name="password" type="password" class="ds-input @error('password', 'updatePassword') ds-input--error @enderror" autocomplete="new-password" @error('password', 'updatePassword') aria-invalid="true" aria-describedby="password-error" @enderror>
                    @error('password', 'updatePassword')
                        <div class="ds-help ds-help--error" id="password-error">{{ $message }}</div>
                    @enderror
                </div>

                <div class="ds-field">
                    <label class="ds-label" for="password_confirmation">Confirm new password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" class="ds-input @error('password_confirmation', 'updatePassword') ds-input--error @enderror" autocomplete="new-password" @error('password_confirmation', 'updatePassword') aria-invalid="true" aria-describedby="password_confirmation-error" @enderror>
                    @error('password_confirmation', 'updatePassword')
                        <div class="ds-help ds-help--error" id="password_confirmation-error">{{ $message }}</div>
                    @enderror
                </div>

                <div>
                    <button type="submit" class="ds-btn ds-btn--primary">
                        <span class="ds-spinner" aria-hidden="true"></span>
                        <span>Update password</span>
                    </button>
                </div>
            </form>
        </section>

        {{-- ── Delete Account ───────────────────────────────────── --}}
        <section class="ds-card ds-card--pad ds-card--danger" aria-labelledby="deleteTitle">
            <div class="ds-section-head">
                <h2 class="ds-section-title" id="deleteTitle">Delete account</h2>
                <p class="ds-section-sub">Permanently deletes your account and all data. This cannot be undone.</p>
            </div>

            <button type="button" class="ds-btn ds-btn--danger" id="deleteAccountBtn">Delete my account</button>

            {{-- Hidden delete form submitted via JS after password confirmation --}}
            <form method="POST" action="{{ route('profile.destroy') }}" id="deleteAccountForm" hidden>
                @csrf
                @method('delete')
                <input type="password" name="password" id="deletePasswordInput" tabindex="-1" autocomplete="off" aria-hidden="true">
            </form>
        </section>

    </div>
</x-app-shell>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    var canvas = document.getElementById('sigPadCanvas');
    var hint = document.getElementById('sigPadHint');
    if (!canvas) return;

    // Match the canvas buffer to its on-screen size for crisp strokes
    function resizeCanvas() {
        var ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext('2d').scale(ratio, ratio);
    }
    resizeCanvas();

    var pad = new SignaturePad(canvas, { penColor: '#0f172a' });
    pad.addEventListener('beginStroke', function () { hint.style.display = 'none'; });

    document.getElementById('sigPadClear').addEventListener('click', function () {
        pad.clear();
        hint.style.display = 'flex';
    });

    var clearBtn = document.getElementById('clearSignatureBtn');
    if (clearBtn) {
        clearBtn.addEventListener('click', function () {
            document.getElementById('clearSignatureInput').value = '1';
            document.getElementById('savedSignatureWrap').classList.add('ds-sigsaved--removing');
            DSToast.info('Saved signature will be removed when you save.');
        });
    }

    document.getElementById('signingProfileForm').addEventListener('submit', function () {
        if (!pad.isEmpty()) {
            document.getElementById('signatureInput').value = pad.toDataURL('image/png');
        }
    });

    window.addEventListener('resize', function () {
        var data = pad.toData();
        resizeCanvas();
        pad.fromData(data);
    });
})();

// Submit button loading state
document.querySelectorAll('form[data-busy]').forEach(function (form) {
    form.addEventListener('submit', function () {
        var btn = form.querySelector('[type="submit"]');
        if (!btn) return;
        setTimeout(function () { btn.setAttribute('aria-busy', 'true'); }, 0);
    });
});
window.addEventListener('pageshow', function (e) {
    if (e.persisted) document.querySelectorAll('form[data-busy] [type="submit"]').forEach(function (b) { b.removeAttribute('aria-busy'); });
});

document.getElementById('deleteAccountBtn').addEventListener('click', function () {
    DSModal.prompt(
        'This is permanent and cannot be undone. Enter your password to confirm.',
        '',
        function (pwd) {
            if (!pwd.trim()) { DSToast.error('Password is required.'); return; }
            document.getElementById('deletePasswordInput').value = pwd;
            document.getElementById('deleteAccountForm').submit();
        },
        null,
        { type: 'password', title: 'Delete account?' }
    );
});
</script>
@endpush

</x-layouts.app>
