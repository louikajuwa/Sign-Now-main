<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Document Signing' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="{{ asset('css/docsign.css') }}">
    <link rel="stylesheet" href="/css/docsign-fill.css">
    @stack('styles')
</head>
<body class="bg-[var(--ds-bg)] min-h-screen">

    {{-- Loader overlay --}}
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

    {{-- Toast host --}}
    <div class="ds-toast-host" id="toastHost" role="status" aria-live="polite"></div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="{{ asset('js/ds-modal.js') }}"></script>
    <script>
    /* ── DSLoader — overlay appears only when work exceeds 1 second ── */
    window.DSLoader = (function () {
        var count = 0, timer = null, active = false;
        return {
            show: function (msg) {
                count++;
                var m = document.getElementById('ds-loader-msg');
                if (m && msg) m.textContent = msg;
                if (active || timer) return;
                timer = setTimeout(function () {
                    active = true;
                    var o = document.getElementById('ds-loader-overlay');
                    if (o) o.classList.add('ds-loader--visible');
                }, 1000);
            },
            hide: function () {
                count = Math.max(0, count - 1);
                if (count > 0) return;
                clearTimeout(timer);
                timer = null;
                active = false;
                var o = document.getElementById('ds-loader-overlay');
                if (o) o.classList.remove('ds-loader--visible');
                var m = document.getElementById('ds-loader-msg');
                if (m) m.textContent = 'Processing your request…';
            }
        };
    })();
    DSLoader.start = DSLoader.show;
    DSLoader.done  = DSLoader.hide;

    /* Every jQuery AJAX call gets the loader automatically */
    $(document)
        .on('ajaxSend.dsloader',     function () { DSLoader.show(); })
        .on('ajaxComplete.dsloader', function () { DSLoader.hide(); });

    /* ── DSToast ── */
    window.DSToast = function (msg, type, dur) {
        type = type || 'info'; dur = dur || 3500;
        var host = document.getElementById('toastHost');
        if (!host) return;
        var el = document.createElement('div');
        el.className = 'ds-toast ds-toast--' + type;
        var dot = document.createElement('span');
        dot.className = 'ds-toast__dot';
        dot.setAttribute('aria-hidden', 'true');
        var text = document.createElement('span');
        text.className = 'ds-toast__msg';
        text.textContent = msg == null ? '' : String(msg);
        el.appendChild(dot);
        el.appendChild(text);
        host.appendChild(el);
        requestAnimationFrame(function () { el.classList.add('ds-toast--show'); });
        setTimeout(function () {
            el.classList.remove('ds-toast--show');
            setTimeout(function () { el.remove(); }, 200);
        }, dur);
    };
    DSToast.success = function (m, d) { DSToast(m, 'success', d); };
    DSToast.error   = function (m, d) { DSToast(m, 'error', d); };
    DSToast.info    = function (m, d) { DSToast(m, 'info', d); };
    DSToast.show    = function (m, t, d) { DSToast(m, t, d); };
    </script>
    @stack('scripts')
</body>
</html>
