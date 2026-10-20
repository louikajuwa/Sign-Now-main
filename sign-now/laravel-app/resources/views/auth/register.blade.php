<x-guest-layout>
    <h1 class="ds-auth-title">Create your account</h1>
    <p class="ds-auth-subtitle">Start sending documents for signature in minutes.</p>

    <form method="POST" action="{{ route('register') }}" data-ds-loading>
        @csrf

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <div class="ds-mt-3">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="ds-mt-3">
            <x-input-label for="password" :value="__('Password')" />
            <div class="ds-pw">
                <x-text-input id="password" type="password" name="password" required autocomplete="new-password" />
                <button type="button" class="ds-pw__toggle" data-ds-pw-toggle="password" aria-pressed="false" aria-label="Show password">
                    <svg class="ds-pw__on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="ds-pw__off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="ds-mt-3">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />
            <div class="ds-pw">
                <x-text-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
                <button type="button" class="ds-pw__toggle" data-ds-pw-toggle="password_confirmation" aria-pressed="false" aria-label="Show password">
                    <svg class="ds-pw__on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                            <svg class="ds-pw__off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/></svg>
                </button>
            </div>
            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        <button type="submit" class="ds-btn ds-btn--primary ds-btn--lg ds-btn--block ds-mt-4">
            <span class="ds-spinner" aria-hidden="true"></span>
            <span>{{ __('Create account') }}</span>
        </button>

        <p class="ds-auth-alt" style="font-size:var(--ds-text-sm);">
            {{ __('Already registered?') }}
            <a class="ds-auth-link" href="{{ route('login') }}">{{ __('Log in') }}</a>
        </p>
    </form>
</x-guest-layout>
