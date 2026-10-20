<x-guest-layout>
    <h1 class="ds-auth-title">Welcome back</h1>
    <p class="ds-auth-subtitle">Log in to manage your documents and signatures.</p>

    <x-auth-session-status :status="session('status')" />

    <form method="POST" action="{{ route('login') }}" data-ds-loading>
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" spellcheck="false" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="ds-mt-3">
            <x-input-label for="password" :value="__('Password')" />
            <div class="ds-pw">
                <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
                <button type="button" class="ds-pw__toggle" data-ds-pw-toggle="password" aria-pressed="false" aria-label="Show password">
                    <svg class="ds-pw__on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="ds-pw__off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="ds-row-between ds-mt-3">
            <label for="remember_me" class="ds-checkbox">
                <input id="remember_me" type="checkbox" name="remember">
                <span>{{ __('Remember me') }}</span>
            </label>
            @if (Route::has('password.request'))
                <a class="ds-auth-link" href="{{ route('password.request') }}">
                    {{ __('Forgot your password?') }}
                </a>
            @endif
        </div>

        <button type="submit" class="ds-btn ds-btn--primary ds-btn--lg ds-btn--block ds-mt-4">
            <span class="ds-spinner" aria-hidden="true"></span>
            <span>{{ __('Log in') }}</span>
        </button>

        @if (Route::has('register'))
            <p class="ds-auth-alt" style="font-size:var(--ds-text-sm);">
                {{ __("Don't have an account?") }}
                <a class="ds-auth-link" href="{{ route('register') }}">{{ __('Sign up') }}</a>
            </p>
        @endif

        @if (Route::has('admin.login'))
            <p class="ds-auth-alt">
                {{ __('Administrator?') }}
                <a class="ds-auth-link" href="{{ route('admin.login') }}">{{ __('Sign in here') }}</a>
            </p>
        @endif
    </form>
</x-guest-layout>
