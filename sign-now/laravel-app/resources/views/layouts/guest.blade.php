<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name', 'Sign Now') }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/docsign.css') }}">

    <style>
        .ds-auth-page {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            font-family: var(--ds-font);
            background:
                radial-gradient(1200px 600px at 85% -10%, rgba(37, 99, 235, .14), transparent 60%),
                radial-gradient(900px 500px at -10% 110%, rgba(37, 99, 235, .10), transparent 55%),
                var(--ds-bg, #f1f5f9);
        }
        .ds-auth-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 22px;
            text-decoration: none;
        }
        .ds-auth-brand__mark {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #1d4ed8, #3b82f6);
            box-shadow: 0 6px 16px rgba(37, 99, 235, .35);
        }
        .ds-auth-brand__name {
            font-size: 20px;
            font-weight: 700;
            color: var(--ds-text, #0f172a);
            letter-spacing: -.02em;
        }
        .ds-auth-card {
            width: min(440px, 100%);
            background: var(--ds-surface, #fff);
            border: 1px solid var(--ds-border, #e2e8f0);
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, .08);
            padding: 28px;
        }
        @media (max-width: 480px) {
            .ds-auth-card { padding: 20px 16px; }
        }
        .ds-auth-card .ds-input { height: 40px; font-size: var(--ds-text-base); }
        .ds-auth-card .ds-label { font-size: var(--ds-text-xs); }
        .ds-btn[disabled] { cursor: not-allowed; }
        .ds-auth-footer {
            margin-top: 18px;
            font-size: 12px;
            color: var(--ds-muted, #94a3b8);
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="ds-auth-page">
        <a href="/" class="ds-auth-brand" aria-label="Sign Now home">
            <span class="ds-auth-brand__mark">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 19l7-7 3 3-7 7-3-3z"/>
                    <path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/>
                    <path d="M2 2l7.586 7.586"/>
                    <circle cx="11" cy="11" r="2"/>
                </svg>
            </span>
            <span class="ds-auth-brand__name">Sign Now</span>
        </a>

        <main class="ds-auth-card">
            {{ $slot }}
        </main>

        <div class="ds-auth-footer">Secure document signing</div>
    </div>

    <script>
    (function () {
        // Show / hide password
        document.querySelectorAll('[data-ds-pw-toggle]').forEach(function (btn) {
            var input = document.getElementById(btn.getAttribute('data-ds-pw-toggle'));
            if (!input) return;
            btn.addEventListener('click', function () {
                var show = input.type === 'password';
                input.type = show ? 'text' : 'password';
                btn.setAttribute('aria-pressed', show ? 'true' : 'false');
                btn.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            });
        });

        // Submit button loading state
        function resetBusy() {
            document.querySelectorAll('form[data-ds-loading] [type="submit"]').forEach(function (b) {
                b.removeAttribute('aria-busy');
                b.disabled = false;
            });
        }
        document.querySelectorAll('form[data-ds-loading]').forEach(function (form) {
            form.addEventListener('submit', function () {
                var btn = form.querySelector('[type="submit"]');
                if (!btn) return;
                // Defer so the browser still submits the form before the button is disabled
                setTimeout(function () {
                    btn.setAttribute('aria-busy', 'true');
                    btn.disabled = true;
                }, 0);
            });
        });
        window.addEventListener('pageshow', function (e) { if (e.persisted) resetBusy(); });
    })();
    </script>
</body>
</html>
