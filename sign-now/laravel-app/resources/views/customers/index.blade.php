<x-layouts.app title="Customers — Sign Now">
<x-app-shell>
    <x-slot name="topbar">
        <div class="ds-topbar__titles">
            <h1 class="ds-topbar__title">Customers</h1>
            <p class="ds-topbar__sub"><span id="totalCountLabel">0</span> total</p>
        </div>
        <div class="ds-topbar__actions">
            <span id="customerListSpinner" class="ds-inline-spinner" role="status" aria-label="Loading customers" hidden></span>
            <label class="ds-sr-only" for="customerSearch">Search customers</label>
            <input type="search" id="customerSearch" class="ds-input" placeholder="Search name, email, company..." autocomplete="off">
            <button type="button" class="ds-btn ds-btn--primary" id="addCustomerBtn">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add customer
            </button>
        </div>
    </x-slot>

    <div class="ds-card">
        <div class="ds-table-wrap">
            <table class="ds-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th class="ds-hide-sm">Company</th>
                        <th class="ds-hide-sm">Phone</th>
                        <th class="ds-hide-sm">Location</th>
                        <th class="ds-hide-sm">User ID</th>
                        <th class="ds-hide-sm">Created</th>
                        <th class="ds-text-right">Actions</th>
                    </tr>
                </thead>
                <tbody id="customersBody" aria-live="polite"></tbody>
            </table>
        </div>
        <div class="ds-pagination" id="paginationContainer">
            <span class="ds-pagination__info" id="paginationInfo"></span>
            <nav class="ds-pagination__btns" id="paginationBtns" aria-label="Pagination"></nav>
        </div>
    </div>
</x-app-shell>

{{-- Add / edit customer modal content (opened with DSModal) --}}
<div id="customerModalContent" hidden>
    <div class="ds-modal__header" id="customerModalTitle">Add customer</div>
    <div class="ds-modal__body">
        <div class="ds-form-grid">
            <div class="ds-field">
                <label class="ds-label" for="cName">First name</label>
                <input type="text" class="ds-input" id="cName" autocomplete="off">
            </div>
            <div class="ds-field">
                <label class="ds-label" for="cLastname">Last name</label>
                <input type="text" class="ds-input" id="cLastname" autocomplete="off">
            </div>
            <div class="ds-field">
                <label class="ds-label" for="cEmail">Email <span class="ds-required" aria-hidden="true">*</span><span class="ds-sr-only">(required)</span></label>
                <input type="email" class="ds-input" id="cEmail" required autocomplete="off">
            </div>
            <div class="ds-field">
                <label class="ds-label" for="cPhone">Phone</label>
                <input type="text" class="ds-input" id="cPhone" inputmode="tel" autocomplete="off">
            </div>
            <div class="ds-field">
                <label class="ds-label" for="cCompany">Company</label>
                <input type="text" class="ds-input" id="cCompany" autocomplete="off">
            </div>
            <div class="ds-field">
                <label class="ds-label" for="cUserId">External user ID</label>
                <input type="text" class="ds-input" id="cUserId" placeholder="Optional" autocomplete="off">
            </div>
            <div class="ds-field">
                <label class="ds-label" for="cStreet">Street</label>
                <input type="text" class="ds-input" id="cStreet" autocomplete="off">
            </div>
            <div class="ds-field">
                <label class="ds-label" for="cApt">Apt / Suite</label>
                <input type="text" class="ds-input" id="cApt" autocomplete="off">
            </div>
            <div class="ds-field">
                <label class="ds-label" for="cCity">City</label>
                <input type="text" class="ds-input" id="cCity" autocomplete="off">
            </div>
            <div class="ds-field">
                <label class="ds-label" for="cState">State</label>
                <input type="text" class="ds-input" id="cState" autocomplete="off">
            </div>
            <div class="ds-field">
                <label class="ds-label" for="cZip">ZIP</label>
                <input type="text" class="ds-input" id="cZip" autocomplete="off">
            </div>
            <div class="ds-field">
                <label class="ds-label" for="cCountry">Country</label>
                <input type="text" class="ds-input" id="cCountry" autocomplete="off">
            </div>
        </div>
        <div id="customerModalError" class="ds-alert ds-alert--error ds-mt-3" role="alert" hidden></div>
    </div>
    <div class="ds-modal__footer">
        <button type="button" class="ds-btn ds-btn--ghost" id="cancelCustomer">Cancel</button>
        <button type="button" class="ds-btn ds-btn--primary" id="saveCustomer">Save customer</button>
    </div>
</div>

@push('scripts')
<script>
$.ajaxSetup({ headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' } });

(function () {
    var page = 1;
    var search = '';
    var editingId = null;
    var modal = null;
    var loaded = false;
    var reqSeq = 0;
    var rowsById = {};

    var FIELDS = {
        name: '#cName', lastname: '#cLastname', email: '#cEmail', phone: '#cPhone',
        company: '#cCompany', user_id: '#cUserId', street: '#cStreet', apt: '#cApt',
        city: '#cCity', state: '#cState', zip: '#cZip', country: '#cCountry',
    };

    function dash(v) { return v ? String(v) : '—'; }

    function skeletonRows(cols, n) {
        var $rows = $();
        for (var i = 0; i < n; i++) {
            var $tr = $('<tr>');
            for (var c = 0; c < cols; c++) {
                var $td = $('<td>').append($('<span>').addClass('ds-skeleton ' + (c < 2 ? 'ds-skeleton--lg' : 'ds-skeleton--md')));
                if (c >= 2 && c < cols - 1) $td.addClass('ds-hide-sm');
                $rows = $rows.add($tr.append($td));
            }
        }
        return $rows;
    }
    function stateRow($content, isError) {
        return $('<tr>').addClass('ds-state-row' + (isError ? ' ds-state-row--error' : ''))
            .append($('<td>').attr('colspan', 8).append($content));
    }

    function load() {
        var seq = ++reqSeq;
        $('#customerListSpinner').prop('hidden', false);
        if (!loaded) $('#customersBody').empty().append(skeletonRows(8, 5));
        $.ajax({
            url: '{{ route("customers.data") }}',
            data: { page: page, search: search },
            dataType: 'json',
            global: false, // inline spinner instead of the page loader
        }).done(function (res) {
            if (seq !== reqSeq) return;
            loaded = true;
            $('#totalCountLabel').text(res.total);
            renderRows(res.data || []);
            renderPagination(res.current_page, res.last_page, res.total);
        }).fail(function () {
            if (seq !== reqSeq) return;
            loaded = false;
            var $c = $('<div>').append($('<p>').text('Could not load customers.'))
                .append($('<button type="button" class="ds-btn ds-btn--ghost ds-btn--sm ds-mt-3 js-retry">').text('Retry'));
            $('#customersBody').empty().append(stateRow($c, true));
            $('#paginationInfo').text('');
            $('#paginationBtns').empty();
        }).always(function () {
            if (seq === reqSeq) $('#customerListSpinner').prop('hidden', true);
        });
    }

    function renderRows(rows) {
        var $tbody = $('#customersBody').empty();
        rowsById = {};
        if (!rows.length) {
            var $c = $('<div class="ds-empty" style="padding:24px;">');
            if (search) {
                $c.append($('<div class="ds-empty__title">').text('No customers found'));
                $c.append($('<p class="ds-empty__text">').text('No customers match your search.'));
            } else {
                $c.append($('<div class="ds-empty__title">').text('No customers yet'));
                $c.append($('<p class="ds-empty__text">').text('Add a customer to quickly reuse their details as a recipient.'));
                $c.append($('<div class="ds-empty__cta">').append($('<button type="button" class="ds-btn ds-btn--primary js-add-first">').text('Add customer')));
            }
            $tbody.append(stateRow($c, false).addClass('ds-empty-row'));
            return;
        }
        rows.forEach(function (c) {
            rowsById[c.id] = c;
            var location = [c.city, c.country].filter(Boolean).join(', ');
            var $tr = $('<tr>').attr('data-id', c.id);
            $tr.append($('<td>').append($('<span class="font-medium">').text(dash(c.full_name))));
            $tr.append($('<td>').text(c.email || ''));
            $tr.append($('<td class="ds-hide-sm">').text(dash(c.company)));
            $tr.append($('<td class="ds-hide-sm">').text(dash(c.phone)));
            $tr.append($('<td class="ds-hide-sm">').text(dash(location)));
            $tr.append($('<td class="ds-hide-sm">').text(dash(c.user_id)));
            $tr.append($('<td class="ds-hide-sm">').text(c.created_at ? new Date(c.created_at).toLocaleDateString() : '—'));
            $tr.append($('<td class="ds-cell-actions">')
                .append($('<button type="button" class="ds-btn ds-btn--ghost ds-btn--sm js-edit">').text('Edit'))
                .append(document.createTextNode(' '))
                .append($('<button type="button" class="ds-btn ds-btn--danger ds-btn--sm js-delete">').text('Delete')));
            $tbody.append($tr);
        });
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

    function renderPagination(current, last, total) {
        $('#paginationInfo').text(total + ' customer' + (total !== 1 ? 's' : ''));
        var $btns = $('#paginationBtns').empty();
        if (last <= 1) return;
        $btns.append($('<button type="button" class="ds-btn ds-btn--ghost ds-btn--sm">').attr({ 'data-page': current - 1, 'aria-label': 'Previous page' }).prop('disabled', current <= 1).text('←'));
        pageItems(current, last).forEach(function (p) {
            if (p === '…') { $btns.append($('<span class="ds-pagination__ellipsis" aria-hidden="true">').text('…')); return; }
            var $b = $('<button type="button">').attr({ 'data-page': p, 'aria-label': 'Page ' + p })
                .addClass('ds-btn ds-btn--sm ' + (p === current ? 'ds-btn--primary' : 'ds-btn--ghost')).text(p);
            if (p === current) $b.attr('aria-current', 'page');
            $btns.append($b);
        });
        $btns.append($('<button type="button" class="ds-btn ds-btn--ghost ds-btn--sm">').attr({ 'data-page': current + 1, 'aria-label': 'Next page' }).prop('disabled', current >= last).text('→'));
    }

    function showError(msg) { $('#customerModalError').text(msg).prop('hidden', false); }

    function openModal(customer) {
        editingId = customer ? customer.id : null;
        $('#customerModalTitle').text(customer ? 'Edit customer' : 'Add customer');
        $('#customerModalError').prop('hidden', true).text('');
        Object.keys(FIELDS).forEach(function (key) {
            $(FIELDS[key]).val(customer ? (customer[key] || '') : '');
        });
        modal = DSModal.open(document.getElementById('customerModalContent'), {
            labelledBy: 'customerModalTitle',
            onClose: function () { editingId = null; modal = null; }
        });
        modal.el.style.maxWidth = '620px';
        $('#cName').trigger('focus');
    }

    function closeModal() { if (modal) modal.close(); }

    function saveCustomer() {
        var payload = {};
        Object.keys(FIELDS).forEach(function (key) {
            payload[key] = $(FIELDS[key]).val().trim();
        });
        if (!payload.email) {
            showError('Email is required.');
            $('#cEmail').trigger('focus');
            return;
        }

        var wasEditing = !!editingId;
        var url = editingId ? '/customers/' + editingId + '/update' : '/customers';
        var $save = $('#saveCustomer').attr('aria-busy', 'true');
        $.ajax({
            url: url,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload),
        }).done(function (res) {
            if (res.result === 'success') {
                closeModal();
                DSToast.success(wasEditing ? 'Customer updated' : 'Customer created');
                load();
            } else {
                showError(res.message || 'Could not save customer.');
            }
        }).fail(function (xhr) {
            var msg = 'Could not save customer.';
            if (xhr.responseJSON) {
                if (xhr.responseJSON.errors) {
                    msg = Object.values(xhr.responseJSON.errors).map(function (e) { return e.join(' '); }).join(' ');
                } else if (xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                }
            }
            showError(msg);
        }).always(function () {
            $save.removeAttr('aria-busy');
        });
    }

    $('#addCustomerBtn').on('click', function () { openModal(null); });
    $('#customersBody').on('click', '.js-add-first', function () { openModal(null); });
    $('#customersBody').on('click', '.js-retry', load);
    $('#cancelCustomer').on('click', closeModal);
    $('#saveCustomer').on('click', saveCustomer);
    $('#customerModalContent').on('keydown', 'input', function (e) {
        if (e.key === 'Enter') { e.preventDefault(); saveCustomer(); }
    });

    $('#customersBody').on('click', '.js-edit', function () {
        var customer = rowsById[$(this).closest('tr').attr('data-id')];
        if (customer) openModal(customer);
    });

    $('#customersBody').on('click', '.js-delete', function () {
        var id = $(this).closest('tr').attr('data-id');
        var customer = rowsById[id];
        var label = customer ? (customer.full_name || customer.email) : 'this customer';
        DSModal.confirm('Delete "' + label + '"? This cannot be undone.', function () {
            $.post('/customers/' + id + '/delete').done(function (res) {
                if (res.result === 'success') {
                    DSToast.success('Customer deleted');
                    load();
                } else {
                    DSToast.error(res.message || 'Could not delete customer.');
                }
            }).fail(function () {
                DSToast.error('Could not delete customer.');
            });
        }, null, { title: 'Delete customer', confirmText: 'Delete', danger: true });
    });

    var searchTimer;
    $('#customerSearch').on('input', function () {
        clearTimeout(searchTimer);
        var val = $(this).val();
        searchTimer = setTimeout(function () {
            search = val;
            page = 1;
            load();
        }, 300);
    });

    $('#paginationBtns').on('click', 'button[data-page]', function () {
        page = parseInt($(this).attr('data-page'), 10) || 1;
        load();
    });

    load();
})();
</script>
@endpush

</x-layouts.app>
