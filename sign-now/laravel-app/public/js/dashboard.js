
/* docsign dashboard — AJAX pagination, upload, rename, delete, template actions. */
(function ($) {
    'use strict';

    var ALLOWED = /\.(pdf|docx?|DOC|DOCX|PDF)$/;
    var currentPage  = 1;
    var currentXHR   = null;
    var templatePage = 1;
    var templateXHR  = null;
    var templatesLoaded = false;
    var activeTab = 'documents';

    // -----------------------------------------------------------------
    // SVG icon constants (shared across JS-rendered rows)
    // -----------------------------------------------------------------
    var IC = {
        edit: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
        fill: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><polyline points="9 13 11 15 15 11"/></svg>',
        view: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>',
        download: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
        rename: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="14 2 18 6 7 17 3 17 3 13 14 2"/><line x1="3" y1="22" x2="21" y2="22"/></svg>',
        del: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>',
        tpl: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>',
        tplPurple: '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="#9333ea" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:inline;vertical-align:-1px;margin-right:4px;"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>',
        x: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>',
        send: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>',
        resend: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>',
        revert: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M19 6l-1 12a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><line x1="12" y1="11" x2="12" y2="17"/></svg>',
        finalize: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/><polyline points="20 6 9 17 4 12" style="opacity:0.4;"/></svg>',
        email: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>'
    };

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function fmtDate(v) {
        if (!v) return '—';
        var ts;
        if (typeof v === 'number' || /^\d+$/.test(String(v))) {
            ts = parseInt(v, 10) * 1000;
        } else {
            ts = (new Date(v)).getTime();
        }
        if (isNaN(ts) || ts === 0) return '—';
        var diff = Math.floor((Date.now() - ts) / 1000);
        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
        var d = new Date(ts);
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return months[d.getMonth()] + ' ' + d.getDate() + ', ' + d.getFullYear();
    }

    // -----------------------------------------------------------------
    // Documents — AJAX data loading
    // -----------------------------------------------------------------
    function loadDashboard(page) {
        page = page || 1;
        currentPage = page;

        var params = {
            q:      $('#searchInput').val() || '',
            status: $('#statusFilter').val() || 'all',
            sort:   $('#sortSelect').val() || 'recent',
            page:   page
        };

        if (currentXHR) currentXHR.abort();
        $('#mainCard').addClass('ds-card--loading');

        currentXHR = $.ajax({
            url: '/sign/dashboarddata',
            method: 'GET',
            data: params,
            dataType: 'json',
            success: function (data) {
                renderDocumentsTable(data.documents || []);
                renderPagination(data.page, data.totalPages, data.total);
                updateCounts(data);
            },
            error: function (xhr, status) {
                if (status !== 'abort') {
                    window.DSToast.show('Failed to load data', 'error');
                }
            },
            complete: function () {
                $('#mainCard').removeClass('ds-card--loading');
                currentXHR = null;
            }
        });
    }

    // -----------------------------------------------------------------
    // Templates — AJAX data loading (separate endpoint)
    // -----------------------------------------------------------------
    function loadTemplates(page) {
        page = page || 1;
        templatePage = page;

        var params = {
            q:       $('#searchInput').val() || '',
            sort:    'recent',
            page:    page,
            perPage: 15
        };

        if (templateXHR) templateXHR.abort();
        $('#mainCard').addClass('ds-card--loading');

        templateXHR = $.ajax({
            url: '/sign/templatedata',
            method: 'GET',
            data: params,
            dataType: 'json',
            success: function (data) {
                renderTemplatesTable(data.templates || []);
                renderTemplatePagination(data.page, data.totalPages, data.total);
                $('#templateCount').text(data.total || 0);
                templatesLoaded = true;
            },
            error: function (xhr, status) {
                if (status !== 'abort') {
                    window.DSToast.show('Failed to load templates', 'error');
                }
            },
            complete: function () {
                $('#mainCard').removeClass('ds-card--loading');
                templateXHR = null;
            }
        });
    }

    // -----------------------------------------------------------------
    // Table rendering
    // -----------------------------------------------------------------
    function buildDocumentRow(d) {
        var uuid = escapeHtml(d.uuid);
        var title = escapeHtml(d.title);
        var filename = escapeHtml(d.filename);
        var tplName = d.template_name ? escapeHtml(d.template_name) : '—';
        var badgeClass = 'ds-badge--in-progress';
        var badgeLabel = 'In Progress';
        if (d.display_status === 'completed') {
            badgeClass = 'ds-badge--completed';
            badgeLabel = 'Completed';
        } else if (d.display_status === 'done') {
            badgeClass = 'ds-badge--done';
            badgeLabel = 'Done';
        }
        // Recipient progress badge
        var recipientBadge = '';
        if (d.recipient_total > 0) {
            recipientBadge = '<span class="ds-badge ds-badge--recipient" style="font-size:10px;padding:1px 6px;margin-left:4px;">' +
                d.recipient_signed + '/' + d.recipient_total + ' signed</span>';
        }
        var downloadWhich = 'latest'; // resolvefilepath() falls back to original if no rendered PDF exists
        var allSigned = (d.recipient_total || 0) > 0 && (d.recipient_signed || 0) >= (d.recipient_total || 0);
        var isClosedStatus = (d.display_status === 'completed' || d.display_status === 'done');

        var actions = '';
        // Edit Fields: hidden once document is completed/done, or when created via API (fields locked)
        if (!isClosedStatus && !d.is_api_doc) {
            actions += '<a href="/sign/edit/' + uuid + '" class="ds-iconbtn" title="Edit fields">' + IC.edit + '</a>';
        }
        actions += '<a href="/sign/view/' + uuid + '" class="ds-iconbtn" title="View">' + IC.view + '</a>';
        actions += '<button type="button" class="ds-iconbtn js-download" title="Download" data-url="/sign/download/' + uuid + '/' + downloadWhich + '">' + IC.download + '</button>';
        actions += '<button type="button" class="ds-iconbtn js-rename" title="Rename" data-uuid="' + uuid + '" data-title="' + title + '">' + IC.rename + '</button>';
        actions += '<button type="button" class="ds-iconbtn ds-iconbtn--danger js-delete" title="Delete" data-uuid="' + uuid + '" data-title="' + title + '">' + IC.del + '</button>';
        if (d.fields > 0) {
            actions += '<button type="button" class="ds-iconbtn js-make-template" title="Save as template" data-uuid="' + uuid + '" data-title="' + title + '">' + IC.tpl + '</button>';
        }
        if (d.recipient_total > 0 && d.recipient_pending > 0) {
            actions += '<button type="button" class="ds-iconbtn js-send-links" title="Send links to ' + d.recipient_pending + ' pending" data-uuid="' + uuid + '" data-title="' + title + '" data-pending="' + d.recipient_pending + '">' + IC.send + '</button>';
        }
        // Resend: only while there are unsigned recipients who have already been sent a link
        var hasSent = ((d.recipient_total || 0) - (d.recipient_pending || 0)) > 0;
        var unsignedSent = (d.recipient_total || 0) - (d.recipient_pending || 0) - (d.recipient_signed || 0);
        if (hasSent && unsignedSent > 0 && d.status !== 'completed') {
            actions += '<button type="button" class="ds-iconbtn js-resend-links" title="Resend to ' + unsignedSent + ' unsigned recipient' + (unsignedSent === 1 ? '' : 's') + '" data-uuid="' + uuid + '" data-title="' + title + '" data-resendable="' + unsignedSent + '">' + IC.resend + '</button>';
        }
        // Finalize: show when all recipients signed (or status = completed) and admin hasn't finalized yet
        if ((d.status === 'completed' || allSigned) && d.admin_submission === 0) {
            actions += '<a href="/sign/finalize/' + uuid + '" class="ds-iconbtn" title="Finalize document (admin sign)" style="color:var(--ds-success,#16a34a);">' + IC.finalize + '</a>';
        }
        if (d.admin_submission > 0) {
            actions += '<button type="button" class="ds-iconbtn js-revert-admin" title="Revert admin finalization" data-uuid="' + uuid + '" data-title="' + title + '" style="color:var(--ds-danger,#dc2626);">' + IC.revert + '</button>';
            actions += '<button type="button" class="ds-iconbtn js-send-complete" title="Send NDA complete email" data-uuid="' + uuid + '" data-title="' + title + '" style="color:var(--ds-accent,#2563eb);">' + IC.email + '</button>';
        }

        return '<tr data-uuid="' + uuid + '">' +
            '<td><div class="ds-cell-title" title="' + title + '">' + title + '</div><div class="ds-cell-sub">' + filename + '</div></td>' +
            '<td>' + (d.pages || 0) + '</td>' +
            '<td>' + (d.fields || 0) + '</td>' +
            '<td><span class="ds-badge ' + badgeClass + '">' + badgeLabel + '</span>' + recipientBadge + '</td>' +
            '<td>' + tplName + '</td>' +
            '<td>' + fmtDate(d.created) + '</td>' +
            '<td>' + fmtDate(d.last_activity) + '</td>' +
            '<td class="ds-cell-actions">' + actions + '</td>' +
        '</tr>';
    }

    function renderDocumentsTable(docs) {
        var $body = $('#documentsBody');
        if (!docs.length) {
            $body.html(
                '<tr class="ds-empty-row"><td colspan="8">' +
                '<div class="ds-empty" style="padding:32px 24px;">' +
                '<div class="ds-empty__icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="15" x2="15" y2="15"/><line x1="12" y1="12" x2="12" y2="18"/></svg></div>' +
                '<div class="ds-empty__title">No documents found</div>' +
                '<div>Try adjusting your search or filters, or upload a new document.</div>' +
                '<div style="margin-top:14px;"><button class="ds-btn ds-btn--primary" id="uploadBtnEmpty">+ Upload document</button></div>' +
                '</div></td></tr>'
            );
            return;
        }
        var html = '';
        docs.forEach(function (d) { html += buildDocumentRow(d); });
        $body.html(html);
    }

    function buildTemplateRow(t) {
        var uuid = escapeHtml(t.uuid);
        var title = escapeHtml(t.title);
        var filename = escapeHtml(t.filename);
        var isApi = t.is_api_template;
        var isMainNda = t.main_nda_api;

        var apiBadge = isApi
            ? '<span class="ds-badge ds-badge--ready" style="font-size:10px;padding:1px 6px;margin-left:6px;">API</span>'
            : '';
        var mainNdaBadge = '';
        if (isApi && isMainNda) {
            mainNdaBadge = '<button type="button" class="js-set-main-nda" data-uuid="' + uuid + '" style="display:inline-flex;align-items:center;gap:3px;font-size:10px;padding:2px 8px;margin-left:4px;border-radius:10px;border:1px solid #6ee7b7;background:#d1fae5;color:#065f46;font-weight:600;cursor:pointer;transition:all 0.15s;" title="Click to remove Main NDA status">★ Main NDA</button>';
        } else if (isApi) {
            mainNdaBadge = '<button type="button" class="js-set-main-nda" data-uuid="' + uuid + '" style="display:inline-flex;align-items:center;gap:3px;font-size:10px;padding:2px 8px;margin-left:4px;border-radius:10px;border:1px solid var(--ds-border);background:transparent;color:var(--ds-muted);font-weight:600;cursor:pointer;transition:all 0.15s;" title="Set as Main NDA">☆ Set Main NDA</button>';
        }
        var apiBtn = isApi
            ? '<button type="button" class="ds-iconbtn js-api-info" title="View API info" data-uuid="' + uuid + '">' +
                  '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>' +
              '</button>' +
              '<button type="button" class="ds-iconbtn ds-iconbtn--danger js-api-toggle" title="Disable API" data-uuid="' + uuid + '">' +
                  '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18.36 6.64A9 9 0 0 1 20.77 15M2 12C2 6.48 6.48 2 12 2"/><line x1="1" y1="1" x2="23" y2="23"/></svg>' +
              '</button>'
            : '<button type="button" class="ds-btn ds-btn--ghost ds-btn--sm js-api-toggle" title="Enable API access" data-uuid="' + uuid + '" style="font-size:11px;padding:3px 8px;gap:4px;">' +
                  '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>' +
                  'API' +
              '</button>';

        // API templates can only be triggered via the API endpoint — block front-end creation
        var newFromTplBtn = isApi
            ? '<span title="API-only template — use the API to create documents" style="display:inline-flex;align-items:center;gap:5px;font-size:11px;color:var(--ds-muted);padding:3px 8px;border:1px solid var(--ds-border);border-radius:6px;cursor:not-allowed;">' +
                  '<svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>' +
                  'API only' +
              '</span>'
            : '<button type="button" class="ds-btn ds-btn--primary ds-btn--sm js-use-template" data-uuid="' + uuid + '" title="Create new document from this template">+ New from template</button>';

        return '<tr data-uuid="' + uuid + '">' +
            '<td><div class="ds-cell-title" title="' + title + '">' + IC.tplPurple + title + apiBadge + mainNdaBadge + '</div><div class="ds-cell-sub">' + filename + '</div></td>' +
            '<td>' + (t.pages || 0) + '</td>' +
            '<td>' + (t.fields || 0) + '</td>' +
            '<td>' + fmtDate(t.created_at) + '</td>' +
            '<td class="ds-cell-actions">' +
                newFromTplBtn +
                apiBtn +
                '<a href="/sign/edit/' + uuid + '" class="ds-iconbtn" title="Edit template fields">' + IC.edit + '</a>' +
                '<button type="button" class="ds-iconbtn ds-iconbtn--danger js-untemplate" title="Remove from templates" data-uuid="' + uuid + '" data-title="' + title + '">' + IC.x + '</button>' +
            '</td>' +
        '</tr>';
    }

    function renderTemplatesTable(templates) {
        var $body = $('#templatesBody');
        if (!templates.length) {
            $body.html(
                '<tr class="ds-empty-row"><td colspan="5">' +
                '<div class="ds-empty" style="padding:32px 24px;">' +
                '<div class="ds-empty__title">No templates yet</div>' +
                '<div>Save a document as a template to reuse its field layout.</div>' +
                '</div></td></tr>'
            );
            return;
        }
        var html = '';
        templates.forEach(function (t) { html += buildTemplateRow(t); });
        $body.html(html);
    }

    // -----------------------------------------------------------------
    // Pagination rendering
    // -----------------------------------------------------------------
    function buildPaginationHtml(page, totalPages, pageClass) {
        var html = '';
        if (page > 1) {
            html += '<button type="button" class="ds-btn ds-btn--ghost ds-btn--sm ' + pageClass + '" data-page="1" title="First page">&laquo;</button>';
            html += '<button type="button" class="ds-btn ds-btn--ghost ds-btn--sm ' + pageClass + '" data-page="' + (page - 1) + '" title="Previous page">&lsaquo; Prev</button>';
        }
        var start = Math.max(1, page - 2);
        var end   = Math.min(totalPages, page + 2);
        for (var p = start; p <= end; p++) {
            var cls = p === page ? 'ds-btn--primary' : 'ds-btn--ghost';
            html += '<button type="button" class="ds-btn ds-btn--sm ' + pageClass + ' ' + cls + '" data-page="' + p + '">' + p + '</button>';
        }
        if (page < totalPages) {
            html += '<button type="button" class="ds-btn ds-btn--ghost ds-btn--sm ' + pageClass + '" data-page="' + (page + 1) + '" title="Next page">Next &rsaquo;</button>';
            html += '<button type="button" class="ds-btn ds-btn--ghost ds-btn--sm ' + pageClass + '" data-page="' + totalPages + '" title="Last page">&raquo;</button>';
        }
        return html;
    }

    function renderPagination(page, totalPages, total) {
        $('#paginationInfo').text('Page ' + page + ' of ' + totalPages + ' (' + total + ' documents)');
        $('#paginationBtns').html(buildPaginationHtml(page, totalPages, 'js-page'));
    }

    function renderTemplatePagination(page, totalPages, total) {
        $('#templatePaginationInfo').text('Page ' + page + ' of ' + totalPages + ' (' + total + ' templates)');
        $('#templatePaginationBtns').html(buildPaginationHtml(page, totalPages, 'js-tpl-page'));
    }

    function updateCounts(data) {
        $('#totalCountLabel').text(data.total);
        $('#docCount').text(data.total);
        $('#pendingCountLabel').text(data.pendingCount);
        $('#completedCountLabel').text(data.completedCount);
        $('#templateCount').text(data.templateCount || 0);
    }

    // -----------------------------------------------------------------
    // Upload modal
    // -----------------------------------------------------------------
    function openUploadModal() {
        var node = document.createElement('div');
        node.innerHTML =
            '<div class="ds-modal__header">Upload document</div>' +
            '<div class="ds-modal__body">' +
                '<label class="ds-drop" id="dropZone">' +
                    '<div class="ds-empty__icon" style="margin-bottom:8px;">' +
                        '<svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>' +
                    '</div>' +
                    '<div class="ds-drop__title">Click to choose or drop a file</div>' +
                    '<div>PDF, DOC or DOCX, up to 25 MB. DOC files convert automatically.</div>' +
                    '<div id="fileChosen" style="margin-top:10px;font-size:12px;color:var(--ds-text);font-weight:500;"></div>' +
                    '<input type="file" name="document" accept=".pdf,.doc,.docx" required>' +
                '</label>' +
                '<div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--ds-border,#e2e8f0);">' +
                    '<div style="font-weight:600;font-size:13px;margin-bottom:8px;color:var(--ds-text,#0f172a);">Or create from a template</div>' +
                    '<div id="modalTplList" style="display:flex;flex-direction:column;gap:6px;">' +
                        '<span class="ds-spinner"></span> Loading templates...' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="ds-modal__footer">' +
                '<button type="button" class="ds-btn ds-btn--ghost" data-action="cancel">Cancel</button>' +
                '<button type="button" class="ds-btn ds-btn--primary" data-action="upload" disabled>' +
                    '<span class="ds-spinner"></span><span>Upload</span>' +
                '</button>' +
            '</div>';

        var instance = window.DSModal.open(node, { persistent: false });

        /* Fetch latest 10 templates via AJAX */
        $.ajax({
            url: '/sign/templatedata',
            method: 'GET',
            data: { page: 1, perPage: 10, sort: 'recent' },
            dataType: 'json',
            success: function (data) {
                // Exclude API-only templates — they cannot be used from the front end
                var tpls = (data.templates || []).filter(function (t) { return !t.is_api_template; });
                var listEl = node.querySelector('#modalTplList');
                if (!listEl) return;
                if (!tpls.length) {
                    listEl.innerHTML = '<div style="font-size:12px;color:var(--ds-muted,#94a3b8);">No templates available.</div>';
                    return;
                }
                var html = '';
                tpls.forEach(function (t) {
                    html += '<button type="button" class="ds-btn ds-btn--ghost ds-tpl-pick" data-uuid="' + escapeHtml(t.uuid) + '" style="justify-content:flex-start;text-align:left;gap:8px;">' +
                        '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#9333ea" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="9" y1="21" x2="9" y2="9"/></svg>' +
                        '<span>' + escapeHtml(t.title) + '</span>' +
                        '<span style="margin-left:auto;font-size:11px;color:var(--ds-muted,#94a3b8);">' + (t.pages || 0) + 'p / ' + (t.fields || 0) + ' fields</span>' +
                    '</button>';
                });
                listEl.innerHTML = html;
            },
            error: function () {
                var listEl = node.querySelector('#modalTplList');
                if (listEl) listEl.innerHTML = '<div style="font-size:12px;color:var(--ds-danger,#dc2626);">Could not load templates.</div>';
            }
        });

        /* Template picker click */
        $(node).on('click', '.ds-tpl-pick', function () {
            var $b = $(this);
            var uuid = $b.attr('data-uuid');
            $b.attr('aria-busy', 'true');
            $.post('/sign/usetemplate/' + encodeURIComponent(uuid))
                .done(function (res) {
                    window.DSToast.show('Created from template', 'success');
                    setTimeout(function () { window.location.href = res.fill_url; }, 350);
                })
                .fail(function (xhr) {
                    var msg = 'Failed to create from template';
                    try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
                    window.DSToast.show(msg, 'error');
                    $b.attr('aria-busy', 'false');
                });
        });

        var drop  = node.querySelector('#dropZone');
        var input = drop.querySelector('input[type="file"]');
        var chosen = node.querySelector('#fileChosen');
        var btn   = node.querySelector('[data-action="upload"]');

        function setFile(file) {
            if (!file) return;
            if (!ALLOWED.test(file.name)) {
                window.DSToast.show('Only PDF, DOC, DOCX allowed', 'error');
                return;
            }
            chosen.textContent = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
            btn.disabled = false;
        }
        input.addEventListener('change', function () { setFile(input.files[0]); });
        drop.addEventListener('dragover', function (e) { e.preventDefault(); drop.classList.add('ds-drop--over'); });
        drop.addEventListener('dragleave', function () { drop.classList.remove('ds-drop--over'); });
        drop.addEventListener('drop', function (e) {
            e.preventDefault(); drop.classList.remove('ds-drop--over');
            if (e.dataTransfer.files && e.dataTransfer.files[0]) { input.files = e.dataTransfer.files; setFile(e.dataTransfer.files[0]); }
        });
        node.querySelector('[data-action="cancel"]').addEventListener('click', instance.close);
        btn.addEventListener('click', function () {
            if (!input.files[0]) return;
            var fd = new FormData();
            fd.append('document', input.files[0]);
            btn.setAttribute('aria-busy', 'true');
            $.ajax({ url: '/sign/upload', method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json' })
                .done(function (res) {
                    if (!res || res.result !== 'success') {
                        window.DSToast.show((res && res.message) || 'Upload failed', 'error');
                        btn.setAttribute('aria-busy', 'false');
                        return;
                    }
                    window.DSToast.show('Uploaded — opening builder', 'success');
                    setTimeout(function () { window.location.href = res.edit_url; }, 350);
                })
                .fail(function () {
                    window.DSToast.show('Upload failed — network error', 'error');
                    btn.setAttribute('aria-busy', 'false');
                });
        });
    }

    // -----------------------------------------------------------------
    // Action handlers (used via event delegation)
    // -----------------------------------------------------------------
    function refreshTemplatesIfVisible() {
        if (activeTab === 'templates') loadTemplates(templatePage);
    }

    function handleRename(btn) {
        var $btn = $(btn);
        var uuid = $btn.attr('data-uuid');
        var current = $btn.attr('data-title') || '';
        var node = document.createElement('div');
        node.innerHTML =
            '<div class="ds-modal__header">Rename document</div>' +
            '<div class="ds-modal__body">' +
                '<label class="ds-properties__label" for="renameInput">Title</label>' +
                '<input id="renameInput" class="ds-input" maxlength="255" />' +
            '</div>' +
            '<div class="ds-modal__footer">' +
                '<button class="ds-btn ds-btn--ghost" data-action="cancel">Cancel</button>' +
                '<button class="ds-btn ds-btn--primary" data-action="save">Save</button>' +
            '</div>';
        var inst = window.DSModal.open(node);
        var inp = node.querySelector('#renameInput');
        inp.value = current;
        setTimeout(function () { inp.focus(); inp.select(); }, 30);
        node.querySelector('[data-action="cancel"]').addEventListener('click', inst.close);
        node.querySelector('[data-action="save"]').addEventListener('click', function () {
            var title = inp.value.trim();
            if (!title) { inp.classList.add('ds-input--error'); return; }
            $.ajax({
                url: '/sign/rename/' + encodeURIComponent(uuid),
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({ title: title })
            }).done(function () {
                inst.close();
                window.DSToast.show('Renamed', 'success');
                loadDashboard(currentPage);
            }).fail(function (xhr) {
                var msg = 'Rename failed';
                try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
                window.DSToast.show(msg, 'error');
            });
        });
    }

    function handleDelete(btn) {
        var $btn = $(btn);
        var uuid  = $btn.attr('data-uuid');
        var title = $btn.attr('data-title') || 'this document';
        window.DSModal.confirm({
            title: 'Delete document?',
            body: '"' + escapeHtml(title) + '" and any signed copies will be permanently removed.',
            confirmLabel: 'Delete',
            confirmKind: 'danger'
        }).then(function (ok) {
            if (!ok) return;
            $.post('/sign/delete/' + encodeURIComponent(uuid))
                .done(function () {
                    window.DSToast.show('Deleted', 'success');
                    loadDashboard(currentPage);
                })
                .fail(function (xhr) {
                    var msg = 'Delete failed';
                    try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
                    window.DSToast.show(msg, 'error');
                });
        });
    }

    function handleMakeTemplate(btn) {
        var $btn = $(btn);
        var uuid  = $btn.attr('data-uuid');
        var title = $btn.attr('data-title') || 'this document';
        window.DSModal.confirm({
            title: 'Save as template?',
            body: '"' + escapeHtml(title) + '" will be saved as a reusable template.',
            confirmLabel: 'Save as template',
            confirmKind: 'primary'
        }).then(function (ok) {
            if (!ok) return;
            $.post('/sign/template/' + encodeURIComponent(uuid))
                .done(function () {
                    window.DSToast.show('Saved as template', 'success');
                    loadDashboard(currentPage);
                    refreshTemplatesIfVisible();
                })
                .fail(function (xhr) {
                    var msg = 'Failed to save template';
                    try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
                    window.DSToast.show(msg, 'error');
                });
        });
    }

    function handleSendLinks(btn) {
        var $btn = $(btn);
        var uuid    = $btn.attr('data-uuid');
        var title   = $btn.attr('data-title') || 'this document';
        var pending = parseInt($btn.attr('data-pending'), 10) || 0;
        window.DSModal.confirm({
            title: 'Send signing links?',
            body: 'Send invitation emails to ' + pending + ' pending recipient' + (pending === 1 ? '' : 's') + ' for "' + escapeHtml(title) + '".',
            confirmLabel: 'Send',
            confirmKind: 'primary'
        }).then(function (ok) {
            if (!ok) return;
            $.post('/sign/sendall/' + encodeURIComponent(uuid))
                .done(function (res) {
                    if (res && res.result === 'success') {
                        window.DSToast.show(res.message || 'Links sent', 'success');
                        loadDashboard(currentPage);
                    } else {
                        window.DSToast.show((res && res.message) || 'Send failed', 'error');
                    }
                })
                .fail(function (xhr) {
                    var msg = 'Failed to send links';
                    try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
                    window.DSToast.show(msg, 'error');
                });
        });
    }

    function handleResendLinks(btn) {
        var $btn = $(btn);
        var uuid       = $btn.attr('data-uuid');
        var title      = $btn.attr('data-title') || 'this document';
        var resendable = parseInt($btn.attr('data-resendable'), 10) || 0;
        window.DSModal.confirm({
            title: 'Resend signing links?',
            body: 'Resend invitation emails to ' + resendable + ' unsigned recipient' + (resendable === 1 ? '' : 's') + ' for "' + escapeHtml(title) + '".',
            confirmLabel: 'Resend',
            confirmKind: 'primary'
        }).then(function (ok) {
            if (!ok) return;
            $.post('/sign/resendall/' + encodeURIComponent(uuid))
                .done(function (res) {
                    if (res && res.result === 'success') {
                        window.DSToast.show(res.message || 'Links resent', 'success');
                        loadDashboard(currentPage);
                    } else {
                        window.DSToast.show((res && res.message) || 'Resend failed', 'error');
                    }
                })
                .fail(function (xhr) {
                    var msg = 'Failed to resend links';
                    try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
                    window.DSToast.show(msg, 'error');
                });
        });
    }

    function handleRevertAdmin(btn) {
        var $btn  = $(btn);
        var uuid  = $btn.attr('data-uuid');
        var title = $btn.attr('data-title') || 'this document';
        window.DSModal.confirm({
            title: 'Revert admin finalization?',
            body: 'This will remove your admin signature and finalized PDF for "' + escapeHtml(title) + '". All recipient signatures are preserved. You can re-finalize the document afterwards.',
            confirmLabel: 'Revert',
            confirmKind: 'danger'
        }).then(function (ok) {
            if (!ok) return;
            $.post('/sign/revertadmin/' + encodeURIComponent(uuid))
                .done(function (res) {
                    if (res && res.result === 'success') {
                        window.DSToast.show(res.message || 'Admin finalization reverted', 'success');
                        loadDashboard(currentPage);
                    } else {
                        window.DSToast.show((res && res.message) || 'Revert failed', 'error');
                    }
                })
                .fail(function (xhr) {
                    var msg = 'Failed to revert finalization';
                    try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
                    window.DSToast.show(msg, 'error');
                });
        });
    }

    function handleSendComplete(btn) {
        var $btn  = $(btn);
        var uuid  = $btn.attr('data-uuid');
        var title = $btn.attr('data-title') || 'this document';
        window.DSModal.confirm({
            title: 'Send NDA Complete Email',
            body: 'Send a completion notification email to all signed recipients of "' + escapeHtml(title) + '"?',
            confirmLabel: 'Send Email'
        }).then(function (ok) {
            if (!ok) return;
            $.post('/sign/sendcomplete/' + encodeURIComponent(uuid))
                .done(function (res) {
                    if (res && res.result === 'success') {
                        window.DSToast.show(res.message || 'Email sent', 'success');
                    } else {
                        window.DSToast.show((res && res.message) || 'Failed to send', 'error');
                    }
                })
                .fail(function (xhr) {
                    var msg = 'Failed to send completion email';
                    try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
                    window.DSToast.show(msg, 'error');
                });
        });
    }

    function handleUntemplate(btn) {
        var $btn = $(btn);
        var uuid  = $btn.attr('data-uuid');
        var title = $btn.attr('data-title') || 'this template';
        window.DSModal.confirm({
            title: 'Remove template?',
            body: '"' + escapeHtml(title) + '" will no longer appear as a template.',
            confirmLabel: 'Remove',
            confirmKind: 'danger'
        }).then(function (ok) {
            if (!ok) return;
            $.post('/sign/untemplate/' + encodeURIComponent(uuid))
                .done(function () {
                    window.DSToast.show('Template removed', 'success');
                    loadDashboard(currentPage);
                    refreshTemplatesIfVisible();
                })
                .fail(function (xhr) {
                    var msg = 'Failed to remove template';
                    try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
                    window.DSToast.show(msg, 'error');
                });
        });
    }

    function handleUseTemplate(btn) {
        var $btn = $(btn);
        var uuid = $btn.attr('data-uuid');
        $btn.attr('aria-busy', 'true');
        $.post('/sign/usetemplate/' + encodeURIComponent(uuid))
            .done(function (res) {
                window.DSToast.show('Created from template', 'success');
                setTimeout(function () { window.location.href = res.fill_url; }, 350);
            })
            .fail(function (xhr) {
                var msg = 'Failed to create from template';
                try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
                window.DSToast.show(msg, 'error');
                $btn.attr('aria-busy', 'false');
            });
    }

    // -----------------------------------------------------------------
    // Main NDA toggle handler
    // -----------------------------------------------------------------
    function handleSetMainNda(btn) {
        var $btn = $(btn);
        var uuid = $btn.attr('data-uuid');
        var isCurrentlyMain = $btn.text().indexOf('★') !== -1;
        window.DSModal.confirm({
            title: isCurrentlyMain ? 'Remove Main NDA?' : 'Set as Main NDA?',
            body: isCurrentlyMain
                ? 'This document will no longer be the Main NDA.'
                : 'This will become the Main NDA. Any existing Main NDA will be unset.',
            confirmLabel: isCurrentlyMain ? 'Remove' : 'Set as Main NDA',
            confirmKind: isCurrentlyMain ? 'danger' : 'primary'
        }).then(function (ok) {
            if (!ok) return;
            $btn.css('opacity', '0.5').css('pointer-events', 'none');
            $.post('/sign/setmainnda/' + encodeURIComponent(uuid))
                .done(function (res) {
                    loadTemplates(templatePage);
                    if (res.main_nda_api) {
                        window.DSToast.show('Set as Main NDA', 'success');
                    } else {
                        window.DSToast.show('Main NDA removed', 'success');
                    }
                })
                .fail(function (xhr) {
                    var msg = 'Failed to update Main NDA';
                    try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
                    window.DSToast.show(msg, 'error');
                })
                .always(function () { $btn.css('opacity', '').css('pointer-events', ''); });
        });
    }

    // -----------------------------------------------------------------
    // API Template handlers
    // -----------------------------------------------------------------
    function handleApiToggle(btn) {
        var $btn = $(btn);
        var uuid = $btn.attr('data-uuid');
        $btn.attr('aria-busy', 'true');
        $.post('/sign/apitoggle/' + encodeURIComponent(uuid))
            .done(function (res) {
                loadTemplates(templatePage);
                if (res.is_api_template) {
                    window.DSToast.show('API access enabled', 'success');
                    // Fetch full info (including customer_key mappings) to show in modal
                    $.get('/sign/apitemplateinfo/' + encodeURIComponent(uuid))
                        .done(function (info) {
                            showApiInfoModal(null, info.endpoint, info.fields, info.sample_payload);
                        });
                } else {
                    window.DSToast.show('API access disabled', 'success');
                }
            })
            .fail(function (xhr) {
                var msg = 'Failed to toggle API template';
                try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
                window.DSToast.show(msg, 'error');
            })
            .always(function () { $btn.attr('aria-busy', 'false'); });
    }

    function handleApiInfo(btn) {
        var uuid = $(btn).attr('data-uuid');
        $.get('/sign/apitemplateinfo/' + encodeURIComponent(uuid))
            .done(function (res) {
                showApiInfoModal(res.api_key, res.endpoint, res.fields, res.sample_payload);
            })
            .fail(function (xhr) {
                var msg = 'Failed to load API info';
                try { msg = JSON.parse(xhr.responseText).message || msg; } catch (e) {}
                window.DSToast.show(msg, 'error');
            });
    }

    // Human-readable labels for customer_key values
    var CUSTOMER_KEY_LABELS = {
        'name':        'First Name',
        'lastname':    'Last Name',
        'fullName':    'Full Name',
        'email':       'Email',
        'phone':       'Phone Number',
        'street':      'Street Address',
        'city':        'City',
        'state':       'State / Province',
        'zip':         'ZIP / Postal Code',
        'country':     'Country',
        'fullAddress': 'Full Address',
        'companyName': 'Company Name',
        'UserID':      'Customer ID'
    };

    function showApiInfoModal(apiKey, endpoint, fields, samplePayload) {
        var hasMappings = (fields || []).some(function (f) { return f.customer_key; });

        var fieldsHtml = '<table style="width:100%;font-size:12px;border-collapse:collapse;">' +
            '<tr style="text-align:left;border-bottom:1px solid var(--ds-border);background:var(--ds-bg);">' +
                '<th style="padding:8px 10px;font-weight:600;white-space:nowrap;">Field Name</th>' +
                '<th style="padding:8px 10px;font-weight:600;">Label</th>' +
                '<th style="padding:8px 10px;font-weight:600;">Type</th>' +
                '<th style="padding:8px 10px;font-weight:600;">Required</th>' +
                '<th style="padding:8px 10px;font-weight:600;">Maps To</th>' +
            '</tr>';

        (fields || []).forEach(function (f) {
            var mapsTo = '';
            if (f.customer_key) {
                var friendly = CUSTOMER_KEY_LABELS[f.customer_key] || f.customer_key;
                mapsTo = '<span style="display:inline-flex;align-items:center;gap:4px;background:#dbeafe;color:#1d4ed8;border-radius:4px;padding:2px 7px;font-size:11px;font-weight:600;">' +
                    '<svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>' +
                    escapeHtml(friendly) +
                '</span>';
            } else {
                mapsTo = '<span style="color:var(--ds-muted);font-size:11px;">— not mapped</span>';
            }

            fieldsHtml += '<tr style="border-bottom:1px solid var(--ds-border);">' +
                '<td style="padding:7px 10px;font-family:monospace;font-size:11px;white-space:nowrap;">' + escapeHtml(f.name) + '</td>' +
                '<td style="padding:7px 10px;">' + escapeHtml(f.label) + '</td>' +
                '<td style="padding:7px 10px;color:var(--ds-muted);">' + escapeHtml(f.type) + '</td>' +
                '<td style="padding:7px 10px;">' + (f.required ? '<span style="color:var(--ds-danger);font-weight:600;">Yes</span>' : '<span style="color:var(--ds-muted);">No</span>') + '</td>' +
                '<td style="padding:7px 10px;">' + mapsTo + '</td>' +
            '</tr>';
        });
        fieldsHtml += '</table>';

        // Warning if some fields have no customer_key mapping
        var unmapped = (fields || []).filter(function (f) { return !f.customer_key && f.type !== 'sign' && f.type !== 'initials'; });
        var warningHtml = unmapped.length
            ? '<div style="margin-top:6px;padding:8px 10px;border-radius:6px;background:#fef9c3;border:1px solid #fde047;font-size:11px;color:#854d0e;">' +
                  '⚠ ' + unmapped.length + ' field(s) are not mapped to any customer attribute. Go to <strong>Edit Template</strong> and assign a customer key to each field so they auto-populate.' +
              '</div>'
            : '';

        var payloadJson = samplePayload
            ? JSON.stringify(samplePayload, null, 2)
            : JSON.stringify({ template_id: '(template uuid)', user_id: '12345' }, null, 2);

        var endpointUrl = (endpoint || 'https://api.example.com/sign/apitrigger').replace(/^POST\s+/i, '');

        var node = document.createElement('div');
        node.style.cssText = 'display:flex;flex-direction:column;flex:1;min-height:0;overflow:hidden;';
        node.innerHTML =
            // Fixed header
            '<div style="padding:20px 20px 16px;border-bottom:1px solid var(--ds-border);flex-shrink:0;">' +
                '<h3 style="margin:0 0 2px;font-size:15px;font-weight:600;color:var(--ds-text);">API Template Configuration</h3>' +
                '<p style="margin:0;font-size:12px;color:var(--ds-muted);">Trigger this template via Postman or any HTTP client.</p>' +
            '</div>' +

            // Scrollable body
            '<div style="padding:16px 20px;overflow-y:auto;flex:1;min-height:0;">' +

                '<div style="margin-bottom:14px;">' +
                    '<div style="font-size:10px;font-weight:700;color:var(--ds-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:5px;">Endpoint</div>' +
                    '<div style="display:flex;align-items:center;gap:8px;padding:8px 12px;border:1px solid var(--ds-border);border-radius:6px;background:var(--ds-bg);">' +
                        '<span style="font-size:10px;font-weight:700;background:#2563eb;color:#fff;padding:2px 6px;border-radius:4px;flex-shrink:0;">POST</span>' +
                        '<code style="font-size:12px;color:var(--ds-text);word-break:break-all;">' + escapeHtml(endpointUrl) + '</code>' +
                    '</div>' +
                '</div>' +

                '<div style="margin-bottom:14px;">' +
                    '<div style="font-size:10px;font-weight:700;color:var(--ds-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:5px;">Fields &amp; Customer Data Mapping</div>' +
                    '<div style="border:1px solid var(--ds-border);border-radius:6px;overflow:hidden;">' +
                        fieldsHtml +
                    '</div>' +
                    warningHtml +
                '</div>' +

                '<div>' +
                    '<div style="font-size:10px;font-weight:700;color:var(--ds-muted);text-transform:uppercase;letter-spacing:0.6px;margin-bottom:5px;">Sample Payload (Postman)</div>' +
                    '<pre style="margin:0;padding:14px 16px;border-radius:6px;background:#0f172a;color:#94a3b8;font-size:12px;line-height:1.7;overflow-x:auto;white-space:pre;">' + escapeHtml(payloadJson) + '</pre>' +
                '</div>' +

            '</div>' +

            // Fixed footer
            '<div style="padding:12px 20px;border-top:1px solid var(--ds-border);display:flex;justify-content:flex-end;flex-shrink:0;">' +
                '<button type="button" class="ds-btn ds-btn--primary" style="padding:8px 20px;">Close</button>' +
            '</div>';

        var instance = window.DSModal.open(node);

        // Override the modal width — default CSS caps at 460px which is too narrow for this table
        if (instance && instance.modal) {
            instance.modal.style.maxWidth = '700px';
            instance.modal.style.width = '100%';
            instance.modal.style.display = 'flex';
            instance.modal.style.flexDirection = 'column';
            instance.modal.style.maxHeight = '85vh';
            instance.modal.style.overflow = 'hidden';
        }

        node.querySelector('button').addEventListener('click', function () {
            if (instance && instance.close) instance.close();
        });
    }

    // -----------------------------------------------------------------
    // Boot — event delegation + filter wiring
    // -----------------------------------------------------------------
    $(function () {
        // Upload buttons
        $('#uploadBtn').on('click', openUploadModal);
        $(document).on('click', '#uploadBtnEmpty', openUploadModal);

        // Event delegation on documents table
        $('#documentsBody')
            .on('click', '.js-rename',         function () { handleRename(this); })
            .on('click', '.js-delete',         function () { handleDelete(this); })
            .on('click', '.js-make-template',  function () { handleMakeTemplate(this); })
            .on('click', '.js-send-links',     function () { handleSendLinks(this); })
            .on('click', '.js-resend-links',   function () { handleResendLinks(this); })
            .on('click', '.js-revert-admin',   function () { handleRevertAdmin(this); })
            .on('click', '.js-send-complete',  function () { handleSendComplete(this); })
            .on('click', '.js-download',       function (e) {
                e.preventDefault();
                window.location.href = $(this).attr('data-url');
            });

        // Event delegation on templates table
        $('#templatesBody')
            .on('click', '.js-use-template',   function () { handleUseTemplate(this); })
            .on('click', '.js-untemplate',      function () { handleUntemplate(this); })
            .on('click', '.js-api-toggle',     function () { handleApiToggle(this); })
            .on('click', '.js-api-info',       function () { handleApiInfo(this); })
            .on('click', '.js-set-main-nda',   function () { handleSetMainNda(this); });

        // Document pagination
        $('#paginationBtns').on('click', '.js-page', function () {
            var p = parseInt($(this).attr('data-page'), 10);
            if (p && p !== currentPage) loadDashboard(p);
        });

        // Template pagination
        $('#templatePaginationBtns').on('click', '.js-tpl-page', function () {
            var p = parseInt($(this).attr('data-page'), 10);
            if (p && p !== templatePage) loadTemplates(p);
        });

        // Main search — debounced, routes to active tab
        var debounceTimer;
        $('#searchInput').on('input', function () {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                if (activeTab === 'documents') loadDashboard(1);
                else loadTemplates(1);
            }, 300);
        }).on('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault(); clearTimeout(debounceTimer);
                if (activeTab === 'documents') loadDashboard(1);
                else loadTemplates(1);
            }
        });

        // Documents filter/sort
        $('#statusFilter, #sortSelect').on('change', function () { loadDashboard(1); });

        // Tab switcher
        $('#dashTabs').on('click', '.ds-tab', function () {
            var tab = $(this).data('tab');
            if (tab === activeTab) return;
            activeTab = tab;
            $('#dashTabs .ds-tab').removeClass('ds-tab--active');
            $(this).addClass('ds-tab--active');
            if (tab === 'documents') {
                $('#documentsPanel').show();
                $('#templatesPanel').hide();
                $('#docSubCounts').show();
                $('#docFilterControls').show();
                loadDashboard(currentPage);
            } else {
                $('#documentsPanel').hide();
                $('#templatesPanel').show();
                $('#docSubCounts').hide();
                $('#docFilterControls').hide();
                if (!templatesLoaded) loadTemplates(1);
                else loadTemplates(1); // refresh with current search
            }
        });

        // Load documents on page open
        loadDashboard(1);
    });
})(jQuery);

