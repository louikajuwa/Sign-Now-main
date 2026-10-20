<x-guest-layout>
    <h1 class="ds-auth-title">Reset your password</h1>
    <p class="ds-auth-subtitle">Enter your email and we'll send you a link to choose a new password.</p>

    <x-auth-session-status :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}" data-ds-loading>
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <button type="submit" class="ds-btn ds-btn--primary ds-btn--lg ds-btn--block ds-mt-4">
            <span class="ds-spinner" aria-hidden="true"></span>
            <span>{{ __('Email Password Reset Link') }}</span>
        </button>

        <p class="ds-auth-alt" style="font-size:var(--ds-text-sm);">
            <a class="ds-auth-link" href="{{ route('login') }}">{{ __('Back to log in') }}</a>
        </p>
    </form>
</x-guest-layout>
