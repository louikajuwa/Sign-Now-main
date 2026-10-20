<x-layouts.app title="Templates — Sign Now">
<x-app-shell>
    <x-slot name="topbar">
        <div class="ds-topbar__titles">
            <p class="ds-topbar__sub">Library</p>
            <h1 class="ds-topbar__title">Templates</h1>
        </div>
        <div class="ds-topbar__actions">
            <label class="ds-sr-only" for="templateSearch">Search templates</label>
            <input type="search" id="templateSearch" class="ds-input" placeholder="Search templates..." autocomplete="off">
        </div>
    </x-slot>

    <div id="templateGrid" class="ds-grid-cards" aria-live="polite"></div>
    <div id="templateEmpty" class="ds-empty" hidden>
        <div class="ds-empty__icon">
            <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"/>
                <line x1="3" y1="9" x2="21" y2="9"/>
                <line x1="9" y1="21" x2="9" y2="9"/>
            </svg>
        </div>
        <div class="ds-empty__title" id="templateEmptyTitle">No templates found</div>
        <p class="ds-empty__text" id="templateEmptyText">Mark a document as a template from the field builder to reuse it here.</p>
    </div>
    <div id="templateError" class="ds-alert ds-alert--error" role="alert" hidden>
        <span>Could not load templates.</span>
        <button type="button" class="ds-btn ds-btn--ghost ds-btn--sm" id="templateRetry">Retry</button>
    </div>
    <nav id="templatePagination" class="ds-pagination" aria-label="Pagination" style="justify-content:center;"></nav>
</x-app-shell>

@push('scripts')
<script>
$.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

(function () {
    var page = 1;
    var search = '';
    var reqSeq = 0;
    var $grid = $('#templateGrid');
    var $empty = $('#templateEmpty');
    var $error = $('#templateError');
    var $pagination = $('#templatePagination');

    var ICON = '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>';

    function plural(n, word) { return n + ' ' + word + (n !== 1 ? 's' : ''); }

    function skeletonCards(n) {
        var $set = $();
        for (var i = 0; i < n; i++) {
            $set = $set.add($('<div class="ds-card ds-tpl" aria-hidden="true">')
                .append($('<span class="ds-skeleton ds-skeleton--lg">'))
                .append($('<span class="ds-skeleton ds-skeleton--md">'))
                .append($('<span class="ds-skeleton" style="height:30px;">')));
        }
        return $set;
    }

    function pageItems(current, total) {
        var set = {}, out = [], last = 0;
        [1, total, current - 1, current, current + 1].forEach(function (p) { if (p >= 1 && p <= total) set[p] = true; });
        Object.keys(set).map(Number).sort(function (a, b) { return a - b; }).forEach(function (p) {
            if (last && p - last > 1) out.push('…');
            out.push(p);
            last = p;
        });
        return out;
    }

    function renderPagination(current, total) {
        $pagination.empty();
        if (!total || total <= 1) return;
        $pagination.append($('<button type="button" class="ds-btn ds-btn--ghost ds-btn--sm">').attr({ 'data-page': current - 1, 'aria-label': 'Previous page' }).prop('disabled', current <= 1).text('←'));
        pageItems(current, total).forEach(function (p) {
            if (p === '…') { $pagination.append($('<span class="ds-pagination__ellipsis" aria-hidden="true">').text('…')); return; }
            var $b = $('<button type="button">').attr({ 'data-page': p, 'aria-label': 'Page ' + p })
                .addClass('ds-btn ds-btn--sm ' + (p === current ? 'ds-btn--primary' : 'ds-btn--ghost')).text(p);
            if (p === current) $b.attr('aria-current', 'page');
            $pagination.append($b);
        });
        $pagination.append($('<button type="button" class="ds-btn ds-btn--ghost ds-btn--sm">').attr({ 'data-page': current + 1, 'aria-label': 'Next page' }).prop('disabled', current >= total).text('→'));
    }

    function load() {
        var seq = ++reqSeq;
        $empty.prop('hidden', true);
        $error.prop('hidden', true);
        $grid.empty().append(skeletonCards(6));

        $.getJSON('/templates/data', { page: page, search: search }, function (res) {
            if (seq !== reqSeq) return;
            $grid.empty();
            if (!res.templates || res.templates.length === 0) {
                $('#templateEmptyTitle').text(search ? 'No matching templates' : 'No templates yet');
                $('#templateEmptyText').text(search ? 'Try a different search term.' : 'Mark a document as a template from the field builder to reuse it here.');
                $empty.prop('hidden', false);
                $pagination.empty();
                return;
            }
            res.templates.forEach(function (tpl) {
                var fieldCount = tpl.field_count || 0;
                var pageCount = tpl.page_count || 0;
                var $body = $('<div class="ds-tpl__body">')
                    .append($('<div class="ds-tpl__title">').text(tpl.title).attr('title', tpl.title))
                    .append($('<div class="ds-tpl__meta">').text(plural(fieldCount, 'field') + ' · ' + plural(pageCount, 'page')));
                if (tpl.is_api_template) {
                    $body.append($('<span class="ds-chip ds-chip--info ds-mt-3">').text('API enabled'));
                }
                var $card = $('<div class="ds-card ds-tpl">').attr('data-uuid', tpl.uuid)
                    .append($('<div class="ds-tpl__head">')
                        .append($('<span class="ds-tpl__icon">').html(ICON))
                        .append($body))
                    .append($('<div class="ds-tpl__actions">')
                        .append($('<a class="ds-btn ds-btn--ghost ds-btn--sm">').attr('href', '/documents/' + encodeURIComponent(tpl.uuid) + '/edit').text('Edit fields'))
                        .append($('<button type="button" class="ds-btn ds-btn--primary ds-btn--sm js-use">').text('Use template')));
                $grid.append($card);
            });
            renderPagination(res.page, res.totalPages);
        }).fail(function () {
            if (seq !== reqSeq) return;
            $grid.empty();
            $pagination.empty();
            $error.prop('hidden', false);
        });
    }

    function useTemplate(uuid, $btn) {
        $btn.attr('aria-busy', 'true');
        DSLoader.show();
        $.post('/templates/' + encodeURIComponent(uuid) + '/use', function (res) {
            DSLoader.hide();
            if (res.result === 'success' && res.edit_url) {
                window.location.href = res.edit_url;
            } else {
                DSToast.success('Document created from template');
                window.location.href = '/dashboard';
            }
        }).fail(function () {
            DSLoader.hide();
            $btn.removeAttr('aria-busy');
            DSToast.error('Failed to create document from template');
        });
    }

    $grid.on('click', '.js-use', function () {
        useTemplate($(this).closest('.ds-card').attr('data-uuid'), $(this));
    });
    $('#templateRetry').on('click', load);

    var searchTimer;
    $('#templateSearch').on('input', function () {
        clearTimeout(searchTimer);
        var val = $(this).val();
        searchTimer = setTimeout(function () {
            search = val;
            page = 1;
            load();
        }, 300);
    });

    $pagination.on('click', 'button[data-page]', function () {
        page = parseInt($(this).attr('data-page'), 10) || 1;
        load();
    });

    load();
})();
</script>
@endpush

</x-layouts.app>
