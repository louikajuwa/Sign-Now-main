<x-guest-layout>
    <h1 class="ds-auth-title">Email verification</h1>
    <p class="ds-auth-subtitle">
        {{ __('Email verification is not required for this application. You can continue to your dashboard.') }}
    </p>

    <a href="{{ route('dashboard') }}" class="ds-btn ds-btn--primary ds-btn--lg ds-btn--block">{{ __('Go to dashboard') }}</a>

    <form method="POST" action="{{ route('logout') }}" class="ds-auth-alt" style="font-size:var(--ds-text-sm);">
        @csrf
        <button type="submit" class="ds-auth-link" style="background:none;border:none;cursor:pointer;">
            {{ __('Log Out') }}
        </button>
    </form>
</x-guest-layout>
