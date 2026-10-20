<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Document Signing' }}</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/docsign.css') }}">
    @stack('styles')
</head>
<body class="bg-[var(--ds-bg)] min-h-screen">
    <div id="ds-loader-overlay" role="status" aria-live="polite">
        <div id="ds-loader-card">
            <div class="ds-loader-spinner">
                <div class="ds-loader-spinner__ring ds-loader-spinner__ring--outer"></div>
                <div class="ds-loader-spinner__ring ds-loader-spinner__ring--inner"></div>
                <div class="ds-loader-spinner__dot"></div>
            </div>
            <p class="ds-loader-msg" id="ds-loader-msg">Processing your request…</p>
        </div>
    </div>

    {{ $slot }}

    <div class="ds-toast-host" id="toastHost"></div>
    <div id="modalContainer"></div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script>
    window.DSLoader = (function () {
        var count = 0, timer = null, active = false;
        return {
            show: function () { count++; if (active) return; timer = setTimeout(function () { active = true; var o = document.getElementById('ds-loader-overlay'); if (o) o.classList.add('ds-loader--visible'); }, 1000); },
            hide: function () { count = Math.max(0, count - 1); if (count > 0) return; clearTimeout(timer); active = false; var o = document.getElementById('ds-loader-overlay'); if (o) o.classList.remove('ds-loader--visible'); }
        };
    })();
    window.DSToast = function (msg, type, dur) {
        type = type || 'info'; dur = dur || 3500;
        var host = document.getElementById('toastHost'); if (!host) return;
        var el = document.createElement('div'); el.className = 'ds-toast ds-toast--' + type;
        el.innerHTML = '<span class="ds-toast__dot"></span>' + msg;
        host.appendChild(el);
        requestAnimationFrame(function () { el.classList.add('ds-toast--show'); });
        setTimeout(function () { el.classList.remove('ds-toast--show'); setTimeout(function () { el.remove(); }, 200); }, dur);
    };
    </script>
    @stack('scripts')
</body>
</html>
