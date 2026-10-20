@props(['fullbleed' => false])
@php
    $user = auth()->user();
    $isAdmin = (bool) ($user->is_admin ?? false);
    $displayName = $user->name ?: $user->email;
    $initial = mb_strtoupper(mb_substr($displayName, 0, 1));
    $nav = [
        ['label' => 'Dashboard', 'href' => route('dashboard'), 'active' => request()->routeIs('dashboard') || request()->routeIs('documents.*'), 'admin' => false,
         'icon' => '<rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/>'],
        ['label' => 'Templates', 'href' => route('templates.index'), 'active' => request()->routeIs('templates.*'), 'admin' => true,
         'icon' => '<rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/>'],
        ['label' => 'Customers', 'href' => route('customers.index'), 'active' => request()->routeIs('customers.*'), 'admin' => true,
         'icon' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'],
        ['label' => 'Profile', 'href' => route('profile.edit'), 'active' => request()->routeIs('profile.*'), 'admin' => false,
         'icon' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>'],
    ];
@endphp

<a href="#main" class="ds-skip-link">Skip to main content</a>

<div class="ds-shell {{ $fullbleed ? 'ds-shell--fullbleed' : '' }}" id="dsShell">
    <aside class="ds-sidebar" id="dsSidebar">
        <div class="ds-sidebar__bar">
            <a href="{{ route('dashboard') }}" class="ds-brand" aria-label="Sign Now dashboard">
                <span class="ds-brand__mark">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/>
                    </svg>
                </span>
                <span class="ds-brand__name">Sign Now</span>
            </a>
            <button type="button" class="ds-iconbtn ds-sidebar__toggle" id="dsSidebarToggle" aria-controls="dsSidebarPanel" aria-expanded="false" aria-label="Toggle navigation menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>
        </div>

        <div class="ds-sidebar__panel" id="dsSidebarPanel">
            <nav class="ds-nav" aria-label="Main">
                @foreach($nav as $item)
                    @if(!$item['admin'] || $isAdmin)
                        <a href="{{ $item['href'] }}" class="ds-nav__link" @if($item['active']) aria-current="page" @endif title="{{ $item['label'] }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $item['icon'] !!}</svg>
                            <span class="ds-nav__label">{{ $item['label'] }}</span>
                        </a>
                    @endif
                @endforeach
            </nav>

            @if($isAdmin)
                <div class="ds-sidebar__admin"><span class="ds-badge ds-badge--admin">Admin</span></div>
            @endif

            <div class="ds-usermenu" id="dsUserMenu">
                <button type="button" class="ds-usermenu__btn" id="dsUserMenuBtn" aria-haspopup="menu" aria-expanded="false" aria-controls="dsUserMenuList">
                    <span class="ds-usermenu__avatar" aria-hidden="true">{{ $initial }}</span>
                    <span class="ds-usermenu__name">{{ $displayName }}</span>
                    <svg class="ds-usermenu__chev" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="18 15 12 9 6 15"/></svg>
                    <span class="ds-sr-only">Account menu</span>
                </button>
                <div class="ds-usermenu__menu" id="dsUserMenuList" role="menu" aria-labelledby="dsUserMenuBtn" hidden>
                    <div class="ds-usermenu__who">
                        <strong>{{ $user->name }}</strong>
                        <span>{{ $user->email }}</span>
                    </div>
                    <a href="{{ route('profile.edit') }}" class="ds-usermenu__item" role="menuitem">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                        Profile
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="ds-usermenu__item ds-usermenu__item--danger" role="menuitem">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                            Log out
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </aside>

    <main id="main" class="ds-main" tabindex="-1">
        @isset($topbar)
            <div class="ds-topbar">
                <div class="ds-topbar__inner">
                    {{ $topbar }}
                </div>
            </div>
        @endisset

        <div class="ds-content">
            {{ $slot }}
        </div>
    </main>
</div>

<script>
(function () {
    var sidebar = document.getElementById('dsSidebar');
    var toggle = document.getElementById('dsSidebarToggle');
    var menuBtn = document.getElementById('dsUserMenuBtn');
    var menu = document.getElementById('dsUserMenuList');
    var menuWrap = document.getElementById('dsUserMenu');

    function setSidebar(open) {
        sidebar.classList.toggle('is-open', open);
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }
    toggle.addEventListener('click', function () {
        setSidebar(!sidebar.classList.contains('is-open'));
    });

    function menuItems() { return Array.prototype.slice.call(menu.querySelectorAll('[role="menuitem"]')); }
    function setMenu(open, focusFirst) {
        menu.hidden = !open;
        menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open && focusFirst) { var items = menuItems(); if (items.length) items[0].focus(); }
    }
    menuBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        setMenu(menu.hidden, false);
    });
    menuBtn.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); setMenu(true, true); }
    });
    menu.addEventListener('keydown', function (e) {
        var items = menuItems();
        var i = items.indexOf(document.activeElement);
        if (e.key === 'ArrowDown') { e.preventDefault(); items[(i + 1) % items.length].focus(); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); items[(i - 1 + items.length) % items.length].focus(); }
        else if (e.key === 'Home') { e.preventDefault(); items[0].focus(); }
        else if (e.key === 'End') { e.preventDefault(); items[items.length - 1].focus(); }
        else if (e.key === 'Tab') { setMenu(false, false); }
    });
    document.addEventListener('click', function (e) {
        if (!menu.hidden && !menuWrap.contains(e.target)) setMenu(false, false);
    });
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (!menu.hidden) { setMenu(false, false); menuBtn.focus(); }
        else if (sidebar.classList.contains('is-open')) { setSidebar(false); toggle.focus(); }
    });
})();
</script>
