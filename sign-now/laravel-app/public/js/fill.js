
/* docsign fill — overlay inputs/signature pads on rendered PDF, validate
   required fields, post values, download signed PDF. */
(function ($) {
    'use strict';

    var DOC_ID          = $('#pdfStack').data('doc-id');
    var ACCESS_TOKEN    = $('#pdfStack').data('access-token') || '';
    var RECIPIENT_ID    = parseInt($('#pdfStack').data('recipient-id'), 10) || 0;
    var RECIPIENT_EMAIL = ($('#pdfStack').data('recipient-email') || '').toLowerCase();
    // data-is-finalize / data-base-pdf-url → jQuery camelCase keys
    var IS_FINALIZE     = parseInt($('#pdfStack').data('isFinalize'), 10) === 1;
    var BASE_PDF_URL    = $('#pdfStack').data('basePdfUrl') || '';
    var BASE_URL        = $('#pdfStack').data('base-url') || '/documents';
    var PDF_BASE        = $('#pdfStack').data('pdf-base') || '/documents';
    var IS_SIGNING      = !!ACCESS_TOKEN;   // public recipient path (token-gated)

    // Coordinate model
    // ----------------
    // Field geometry (fields, admin fields, overrides) is ALWAYS expressed in
    // "base" pixels = PDF points * renderScale (the document's stored render_scale,
    // which is also what the server divides by). Each page's .field-layer is
    // base-sized and visually scaled with CSS transform: scale(k), where
    // k = actualPageScale / renderScale. So positions never need recomputing on
    // resize/zoom, and everything we POST is already in the server's unit.
    var renderScale     = 1.5;
    var zoom            = 1;          // user zoom multiplier on top of fit-to-width
    var MAX_FIT_SCALE   = 1.5;
    var pdfDoc          = null;
    var pageInfos       = [];         // [{page, baseW, baseH, w1, $wrap, $layer, canvas, task}]
    var renderGen       = 0;
    var lastStackWidth  = 0;
    var fields          = [];
    var fieldById       = {}; // field_uid → field object, for fast ownership lookups
    var sigPads         = {};
    var recipientValues = {}; // field_uid → value, loaded in finalize mode
    var signedValues    = {}; // field_uid → value, fields already signed by other recipients

    // Admin add-field (finalize mode only — temporary, not saved to DB)
    var adminFields       = [];   // [{id, type, page, x, y, w, h}]
    var adminSigPads      = {};   // id → {pad, watermark, wrap, _dataUrl}
    var placementMode     = null; // null | 'text' | 'sign' | 'initials' | 'date'
    var adminFieldCounter = 0;

    // Recipient field overrides (finalize mode) — admin repositions/edits existing fields
    var recipientOverrides = {}; // field_uid → {field_uid, page, x, y, w, h}
    // Position overrides for pre-defined fields dragged during finalize
    var fieldPositionOverrides = {}; // field_uid → {field_uid, page, x, y, w, h}

    // Right-panel state (finalize mode only)
    var finalizeFieldStates = {}; // field_uid → {type, label, value, $display, $hidden, $wrap}
    var currentPanelFieldId = null;

    pdfjsLib.GlobalWorkerOptions.workerSrc =
        'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    $(function () {
        $('#generatePdf, .js-generate').on('click', onGenerate);
        $('#nextField, .js-next-field').on('click', goToNextField);
        $('#zoomIn').on('click', function () { stepZoom(1); });
        $('#zoomOut').on('click', function () { stepZoom(-1); });
        $('#zoomFit').on('click', function () { setZoom(1); });
        $(window).on('resize orientationchange', debounce(onViewportChange, 200));
        loadAndRender();

        if (IS_FINALIZE) {
            $(document).on('click', '.ds-addfield-btn', function () {
                var type = $(this).attr('data-field-type');
                if (placementMode === type) { exitPlacementMode(); } else { enterPlacementMode(type); }
            });
            $(document).on('click', '#cancelPlacement', exitPlacementMode);
            $(document).on('keydown', function (e) { if (e.key === 'Escape' && placementMode) exitPlacementMode(); });
            $(document).on('click', '#pdfStack .page-wrap', function (e) {
                if (!placementMode) return;
                if ($(e.target).closest('.df-admin-field, .df-locked-field, .df-sign-wrap, .df-fill-input').length) return;
                placeAdminField($(this), e);
            });

            // Right panel close button
            $(document).on('click', '#finalizePanelClose', closeFinalizePanel);
        }
    });

    // -----------------------------------------------------------------
    // Admin field placement (finalize mode)
    // -----------------------------------------------------------------
    function enterPlacementMode(type) {
        placementMode = type;
        $('.ds-addfield-btn').removeClass('ds-btn--primary').addClass('ds-btn--ghost');
        $('.ds-addfield-btn[data-field-type="' + type + '"]').removeClass('ds-btn--ghost').addClass('ds-btn--primary');
        $('#placementHint').css('display', 'flex');
        $('#pdfStack').css('cursor', 'crosshair');
    }

    function exitPlacementMode() {
        placementMode = null;
        $('.ds-addfield-btn').removeClass('ds-btn--primary').addClass('ds-btn--ghost');
        $('#placementHint').css('display', 'none');
        $('#pdfStack').css('cursor', '');
    }

    function placeAdminField($page, e) {
        var pageNum = parseInt($page.attr('data-page'), 10);
        var $layer = $page.find('.field-layer');
        var k = layerScale($layer);
        var rect = $layer[0].getBoundingClientRect();
        var x = (e.clientX - rect.left) / k;   // → base px
        var y = (e.clientY - rect.top) / k;
        var isSig = (placementMode === 'sign' || placementMode === 'initials');
        var w = isSig ? 200 : 180;
        var h = isSig ? 80 : 38;
        var pageW = $layer.width();
        var pageH = $layer.height();
        x = Math.min(Math.max(0, x - Math.round(w / 2)), pageW - w);
        y = Math.min(Math.max(0, y - Math.round(h / 2)), pageH - h);
        var id = 'admin_' + (++adminFieldCounter);
        var field = { id: id, type: placementMode, page: pageNum, x: x, y: y, w: w, h: h };
        adminFields.push(field);
        renderAdminField(field);
        exitPlacementMode();
        updateProgress();
    }

    function renderAdminField(f) {
        var $page = $('#pdfStack .page-wrap[data-page="' + f.page + '"]');
        if (!$page.length) return;
        var $layer = $page.find('.field-layer');

        var $wrap = $('<div class="df-admin-field"></div>').css({
            position: 'absolute',
            left: f.x + 'px', top: f.y + 'px',
            width: f.w + 'px', height: f.h + 'px',
            boxSizing: 'border-box',
            border: '1.5px solid #6366f1',
            borderRadius: '4px',
            background: '#fff',
            zIndex: 20,
            userSelect: 'none'
        }).attr('data-admin-id', f.id);

        // ── Drag handle sits ABOVE the field — never covers content ───
        var typeLabel = { text: 'Text', sign: 'Signature', initials: 'Initials', date: 'Date' }[f.type] || 'Field';
        var $handle = $('<div class="df-admin-handle"></div>').css({
            position: 'absolute',
            bottom: '100%', left: '-1px', right: '-1px',
            height: '16px',
            background: '#6366f1',
            borderRadius: '3px 3px 0 0',
            cursor: 'move',
            display: 'flex', alignItems: 'center',
            padding: '0 4px', gap: '4px',
            zIndex: 4, userSelect: 'none'
        });
        $handle.append(
            $('<span></span>').css({
                fontSize: '9px', color: '#fff', fontWeight: 600,
                flex: 1, overflow: 'hidden',
                whiteSpace: 'nowrap', textOverflow: 'ellipsis',
                pointerEvents: 'none'
            }).text(typeLabel.toUpperCase())
        );
        var $del = $('<button type="button" title="Remove" aria-label="Remove field" class="df-admin-del"></button>').css({
            background: 'rgba(255,255,255,0.25)', border: 'none',
            color: '#fff', fontSize: '13px', lineHeight: '14px',
            width: '14px', height: '14px', borderRadius: '3px',
            cursor: 'pointer', padding: 0, flexShrink: 0,
            display: 'flex', alignItems: 'center', justifyContent: 'center'
        }).html('&times;');
        $del.on('click', function (e) {
            e.stopPropagation();
            adminFields = adminFields.filter(function (af) { return af.id !== f.id; });
            delete adminSigPads[f.id];
            $wrap.remove();
            updateProgress();
        });
        $handle.append($del);
        $handle.hide();
        $wrap.append($handle);
        $wrap.on('mouseenter', function () { $handle.show(); })
             .on('mouseleave', function () { $handle.hide(); });

        // ── Content area fills the ENTIRE field height ────────────────
        var $content = $('<div class="df-admin-content"></div>').css({
            position: 'absolute', inset: 0, overflow: 'hidden'
        });
        $wrap.append($content);

        // ── Resize handle (bottom-right triangle) ─────────────────────
        var $resizeCorner = $('<div class="df-admin-resize"></div>').css({
            position: 'absolute', bottom: 0, right: 0,
            width: '12px', height: '12px',
            cursor: 'se-resize',
            background: 'linear-gradient(135deg, transparent 50%, #6366f1 50%)',
            borderRadius: '0 0 3px 0',
            zIndex: 3
        });
        $wrap.append($resizeCorner);

        makeDraggable($handle, $wrap, f);
        makeResizable($resizeCorner, $wrap, $content, f);

        if (f.type === 'sign' || f.type === 'initials') {
            $content.css({ display: 'flex', alignItems: 'center', justifyContent: 'center' });
            renderAdminSignField($content, f);
        } else {
            renderAdminTextField($content, f);
        }

        $layer.append($wrap);
    }

    function renderAdminTextField($content, f) {
        var placeholder = f.type === 'date' ? 'YYYY-MM-DD' : 'Type here';
        // Use an absolutely-positioned input anchored to the bottom,
        // matching the same font/lineHeight/padding as all other field values.
        var $ta = $('<textarea></textarea>')
            .attr({ 'data-admin-field-id': f.id, placeholder: placeholder })
            .css({
                position: 'absolute',
                bottom: 0, left: 0, right: 0,
                width: '100%', height: 'auto',
                background: 'rgba(238,242,255,0.45)',
                border: 'none', resize: 'none', outline: 'none',
                padding: '0 3px',
                fontFamily: DISPLAY_FONT,
                fontSize: '12px',
                lineHeight: DISPLAY_LH,
                color: DISPLAY_COLOR,
                boxSizing: 'border-box'
            });
        $ta.on('input', updateProgress);
        $content.append($ta);
    }

    function renderAdminSignField($content, f) {
        var canvasH = Math.max(10, f.h);
        var $canvas = $('<canvas></canvas>').attr({ width: Math.round(f.w * 2), height: Math.round(canvasH * 2) })
            .css({ position: 'absolute', top: 0, left: 0, width: '100%', height: '100%', display: 'block' });
        var $watermark = $('<div></div>').css({
            position: 'absolute', inset: 0, pointerEvents: 'none',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            fontSize: '11px', color: '#6366f1', zIndex: 1
        }).text(f.type === 'initials' ? 'Click to add initials' : 'Click to sign');

        var pad = new SignaturePad($canvas[0], { penColor: '#0f172a' });
        pad.off();
        // rec.wrap → $content so openAdminSignatureModal can find the canvas via rec.wrap.find('canvas')
        adminSigPads[f.id] = { pad: pad, watermark: $watermark, wrap: $content, fieldW: f.w, fieldH: canvasH };

        $content.append($canvas).append($watermark);
        $content.attr({ tabindex: 0, role: 'button', 'aria-label': 'Add ' + (f.type === 'initials' ? 'initials' : 'signature') });
        $content.on('click', function () { openAdminSignatureModal(f.id); });
        $content.on('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openAdminSignatureModal(f.id); }
        });
    }

    function openAdminSignatureModal(fieldId) {
        var rec = adminSigPads[fieldId];
        if (!rec) return;
        // Same Draw / Upload modal as recipients; rec.wrap holds the preview canvas.
        openSignatureModal(fieldId, rec);
    }

    // ── Alignment guides (pink lines, same style as builder) ─────────
    var GUIDE_TOL = 4;

    // Show guide lines for the field being dragged only:
    // 3 vertical lines (left edge, centre, right edge) and
    // 3 horizontal lines (top edge, centre, bottom edge) that follow the field.
    function showGuides(el, page) {
        hideGuides();
        var x = parseFloat(el.style.left) || 0;
        var y = parseFloat(el.style.top)  || 0;
        var w = el.offsetWidth;
        var h = el.offsetHeight;

        [x, x + w / 2, x + w].forEach(function (pos) {
            $('<div class="df-guide df-guide--v"></div>')
                .css({ left: pos + 'px' }).appendTo(page);
        });
        [y, y + h / 2, y + h].forEach(function (pos) {
            $('<div class="df-guide df-guide--h"></div>')
                .css({ top: pos + 'px' }).appendTo(page);
        });
    }

    function hideGuides() { $('#pdfStack .df-guide').remove(); }

    // $handle - element the user grabs (sits above the field, never covers content)
    // $wrap   - the element that actually moves
    // f       - tracker object; coordinates are written back in base px (see Coordinate model)
    function makeDraggable($handle, $wrap, f) {
        var dragging = false, grabX = 0, grabY = 0;
        $handle.addClass('df-drag-handle').css('touchAction', 'none');
        $handle.on('pointerdown', function (e) {
            if ($(e.target).closest('button').length) return;
            var oe = e.originalEvent;
            var $layer = $wrap.closest('.field-layer');
            if (!$layer.length) return;
            var k = layerScale($layer);
            var wrapRect = $wrap[0].getBoundingClientRect();
            grabX = (oe.clientX - wrapRect.left) / k;
            grabY = (oe.clientY - wrapRect.top) / k;
            dragging = true;
            try { $handle[0].setPointerCapture(oe.pointerId); } catch (err) {}
            e.preventDefault();
            showGuides($wrap[0], $layer[0]);
        });
        $handle.on('pointermove', function (e) {
            if (!dragging) return;
            var oe = e.originalEvent;
            var $layer = $wrap.closest('.field-layer');
            if (!$layer.length) return;
            var k = layerScale($layer);
            var lRect = $layer[0].getBoundingClientRect();
            var newLeft = (oe.clientX - lRect.left) / k - grabX;
            var newTop  = (oe.clientY - lRect.top)  / k - grabY;
            $wrap.css({ left: newLeft + 'px', top: newTop + 'px' });
            f.x = newLeft; f.y = newTop;
            showGuides($wrap[0], $layer[0]);
        });
        $handle.on('pointerup pointercancel lostpointercapture', function () {
            if (!dragging) return;
            dragging = false;
            hideGuides();
        });
    }

    // $corner  - the resize handle element (bottom-right triangle)
    // $wrap    - the outer div whose width/height are resized
    // $content - inner content div (admin fields only; hosts the signature canvas)
    function makeResizable($corner, $wrap, $content, f) {
        var resizing = false, startX, startY, origW, origH;
        $corner.addClass('df-resize-handle').css('touchAction', 'none');
        $corner.on('pointerdown', function (e) {
            var oe = e.originalEvent;
            resizing = true;
            startX = oe.clientX; startY = oe.clientY;
            origW  = parseFloat($wrap.css('width'))  || f.w;
            origH  = parseFloat($wrap.css('height')) || f.h;
            try { $corner[0].setPointerCapture(oe.pointerId); } catch (err) {}
            e.preventDefault();
            e.stopPropagation();
        });
        $corner.on('pointermove', function (e) {
            if (!resizing) return;
            var oe = e.originalEvent;
            var k = layerScale($wrap);
            var newW = Math.max(40, origW + (oe.clientX - startX) / k);
            var newH = Math.max(20, origH + (oe.clientY - startY) / k);
            $wrap.css({ width: newW + 'px', height: newH + 'px' });
            f.w = newW; f.h = newH;
        });
        $corner.on('pointerup pointercancel lostpointercapture', function () {
            if (!resizing) return;
            resizing = false;
            // Re-sync the admin signature canvas (its pixel buffer is cleared on resize)
            if ($content) {
                var $canvas = $content.find('canvas');
                if ($canvas.length) {
                    $canvas.attr({ width: Math.round(f.w * 2), height: Math.round(f.h * 2) });
                    var rec = adminSigPads[f.id];
                    if (rec && rec._dataUrl) paintSignature($canvas[0], rec._dataUrl);
                }
            }
        });
    }


    function isAdminFieldFilled(f) {
        if (f.type === 'sign' || f.type === 'initials') {
            var rec = adminSigPads[f.id];
            return !!(rec && rec._dataUrl);
        }
        var $ta = $('textarea[data-admin-field-id="' + f.id + '"]');
        return $ta.length > 0 && $ta.val().trim() !== '';
    }

    function collectAdminFields() {
        return adminFields.map(function (f) {
            var value = '';
            if (f.type === 'sign' || f.type === 'initials') {
                var rec = adminSigPads[f.id];
                if (rec && rec._dataUrl) value = rec._dataUrl;
            } else {
                var $ta = $('textarea[data-admin-field-id="' + f.id + '"]');
                if ($ta.length) value = $ta.val();
            }
            return { id: f.id, type: f.type, page: f.page,
                     x: Math.round(f.x), y: Math.round(f.y),
                     w: Math.round(f.w), h: Math.round(f.h), value: value };
        });
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------
    function debounce(fn, ms) {
        var t = null;
        return function () {
            var self = this, args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(self, args); }, ms);
        };
    }

    // Append the signing token on the public (recipient) path.
    function withToken(url) {
        if (!ACCESS_TOKEN) return url;
        return url + (url.indexOf('?') >= 0 ? '&' : '?') + 't=' + encodeURIComponent(ACCESS_TOKEN);
    }

    function loaderShow() { var L = window.DSLoader; if (L) { (L.show || L.start || function () {}).call(L); } }
    function loaderHide() { var L = window.DSLoader; if (L) { (L.hide || L.done || function () {}).call(L); } }

    // Current CSS scale of a page's field layer (k = pageScale / renderScale).
    function layerScale($el) {
        var $l = $el.hasClass('field-layer') ? $el : $el.closest('.field-layer');
        return parseFloat($l.attr('data-k')) || 1;
    }

    // Paint a data URL into a canvas, aspect-fit (contain) and centred.
    function paintSignature(canvas, dataUrl, done) {
        var ctx = canvas.getContext('2d');
        var img = new Image();
        img.onload = function () {
            var cw = canvas.width, ch = canvas.height;
            ctx.setTransform(1, 0, 0, 1, 0, 0);
            ctx.clearRect(0, 0, cw, ch);
            var ratio = Math.min(cw / img.width, ch / img.height);
            var dw = img.width * ratio, dh = img.height * ratio;
            ctx.drawImage(img, (cw - dw) / 2, (ch - dh) / 2, dw, dh);
            if (done) done();
        };
        img.src = dataUrl;
    }

    function handleAuthError(xhr) {
        if (xhr.status === 419) {
            window.DSToast.show('Your session has expired. Please refresh the page and try again.', 'error');
            return true;
        }
        if (xhr.status === 403) {
            if (IS_SIGNING) {
                window.DSToast.show('Your signing session has expired. Redirecting...', 'error');
                setTimeout(function () { window.location.href = '/signing/unauthorized'; }, 1500);
            } else {
                window.DSToast.show('You do not have permission to do this. Please sign in again.', 'error');
            }
            return true;
        }
        return false;
    }

    function loadAndRender() {
        var fieldsReq = $.getJSON(withToken(BASE_URL + '/' + DOC_ID + '/fields'));
        // In finalize mode, also load recipient values so we can lock filled fields
        var valuesReq = IS_FINALIZE
            ? $.getJSON(BASE_URL + '/' + DOC_ID + '/recipient-values')
            : $.Deferred().resolve({ values: {} }).promise();

        $.when(fieldsReq, valuesReq).done(function (fieldsData, valuesData) {
            var res = fieldsData[0];
            renderScale = res.render_scale || 1.5;
            fields = res.fields || [];
            // Build fast lookup used by ownership checks
            fieldById = {};
            fields.forEach(function (f) { fieldById[f.id] = f; });
            recipientValues = (valuesData[0] && valuesData[0].values) ? valuesData[0].values : {};
            signedValues = res.signed_values || {};
            var customerData = res.customer_data || null;
            var adminCustomerData = res.admin_customer_data || null;
            var pdfUrl = (IS_FINALIZE && BASE_PDF_URL) ? BASE_PDF_URL : withToken(PDF_BASE + '/' + DOC_ID + '/serve/original');
            renderPdf(pdfUrl).then(function () {
                overlayFields();
                if (customerData) { autoFillCustomerFields(customerData); }
                // In finalize mode: auto-fill fields assigned to __admin__ with the linked customer's data
                if (IS_FINALIZE && adminCustomerData) {
                    autoFillCustomerFields(adminCustomerData, '__admin__');
                    if (adminCustomerData.signature) { autoFillAdminSignature(adminCustomerData.signature); }
                }
                updateProgress();
            }, function () {
                window.DSToast.show('Failed to load the document', 'error');
            });
        }).fail(function (xhr) {
            if (handleAuthError(xhr)) return;
            window.DSToast.show('Failed to load fields', 'error');
        });
    }

    // -----------------------------------------------------------------
    // PDF rendering: fit-to-width, zoom, debounced re-render
    // -----------------------------------------------------------------
    function stackWidth() {
        var w = Math.floor($('#pdfStack').width());
        return w > 0 ? w : Math.max(200, window.innerWidth - 24);
    }

    function pageScale(info) {
        var fit = Math.min(MAX_FIT_SCALE, stackWidth() / info.w1);
        return Math.max(0.1, fit * zoom);
    }

    // Size the page box + field layer. Field coordinates stay in base px; the
    // layer is visually scaled so positions never need recomputing.
    function applyPageScale(info) {
        var s = pageScale(info);
        var k = s / renderScale;
        info.s = s;
        var cssW = info.baseW * k, cssH = info.baseH * k;
        info.$wrap.css({ width: cssW + 'px', height: cssH + 'px' });
        $(info.canvas).css({ width: cssW + 'px', height: cssH + 'px' });
        info.$layer.attr('data-k', k).css({
            width: info.baseW + 'px', height: info.baseH + 'px',
            transformOrigin: '0 0', transform: 'scale(' + k + ')'
        });
        info.$layer[0].style.setProperty('--ds-k', k);
    }

    function applyAllScales() {
        pageInfos.forEach(applyPageScale);
        $('#zoomLabel').text(Math.round(zoom * 100) + '%');
    }

    function renderCanvases() {
        var gen = ++renderGen;
        var dpr = Math.min(2, window.devicePixelRatio || 1);
        pageInfos.forEach(function (info) {
            if (info.task) { try { info.task.cancel(); } catch (e) {} info.task = null; }
        });
        var chain = Promise.resolve();
        pageInfos.forEach(function (info) {
            chain = chain.then(function () {
                if (gen !== renderGen) return;
                var vp = info.page.getViewport({ scale: info.s * dpr });
                info.canvas.width = Math.round(vp.width);
                info.canvas.height = Math.round(vp.height);
                info.task = info.page.render({ canvasContext: info.canvas.getContext('2d'), viewport: vp });
                return info.task.promise.catch(function (err) {
                    if (!err || err.name !== 'RenderingCancelledException') throw err;
                });
            });
        });
        return chain;
    }

    function setZoom(z) {
        zoom = Math.min(3, Math.max(0.5, z));
        applyAllScales();
        renderCanvases();
    }

    function stepZoom(dir) {
        var steps = [0.5, 0.75, 1, 1.25, 1.5, 2, 3];
        var idx = 0;
        steps.forEach(function (v, i) { if (Math.abs(v - zoom) < Math.abs(steps[idx] - zoom)) idx = i; });
        idx = Math.min(steps.length - 1, Math.max(0, idx + dir));
        setZoom(steps[idx]);
    }

    function onViewportChange() {
        if (!pageInfos.length) return;
        var w = stackWidth();
        if (w === lastStackWidth) return;
        lastStackWidth = w;
        applyAllScales();
        renderCanvases();
    }

    function renderPdf(url) {
        $('#pdfStack').empty();
        pageInfos = [];
        loaderShow();
        return pdfjsLib.getDocument(url).promise.then(function (pdf) {
            pdfDoc = pdf;
            var reqs = [];
            for (var i = 1; i <= pdf.numPages; i++) { reqs.push(pdf.getPage(i)); }
            return Promise.all(reqs);
        }).then(function (pages) {
            pages.forEach(function (page, idx) { buildPage(page, idx + 1); });
            lastStackWidth = stackWidth();
            applyAllScales();
            renderCanvases();
            loaderHide();
        }, function (err) {
            loaderHide();
            return Promise.reject(err);
        });
    }

    function buildPage(page, pageNum) {
        var base = page.getViewport({ scale: renderScale });
        var w1 = page.getViewport({ scale: 1 }).width;
        var $wrap = $('<div class="page-wrap"></div>').attr('data-page', pageNum);
        $wrap.append('<span class="page-wrap__num">Page ' + pageNum + '</span>');
        var canvas = $('<canvas></canvas>').css({ display: 'block' })[0];
        var $layer = $('<div class="field-layer"></div>').css({ position: 'absolute', left: 0, top: 0 });
        $wrap.append(canvas).append($layer);
        $('#pdfStack').append($wrap);
        pageInfos.push({ page: page, baseW: base.width, baseH: base.height, w1: w1,
                         $wrap: $wrap, $layer: $layer, canvas: canvas, task: null, s: 1 });
    }

    // -----------------------------------------------------------------
    // Next field (scroll to + focus the next required, unfilled field)
    // -----------------------------------------------------------------
    var lastNextUid = null;

    function fieldControl(f) {
        if (f.type === 'sign' || f.type === 'initials') {
            var rec = sigPads[f.id];
            return rec && rec.wrap ? rec.wrap : null;
        }
        var $i = $('input.df-fill-input[data-field-id="' + f.id + '"]');
        return $i.length ? $i : null;
    }

    function goToNextField() {
        var list = fields.filter(function (f) {
            return f.required && fieldControl(f) && !isFieldFilled(f);
        }).sort(function (a, b) {
            return (a.page - b.page) || (a.y - b.y) || (a.x - b.x);
        });
        if (!list.length) {
            window.DSToast.show('All required fields are complete', 'success');
            var g = $('#generatePdf:visible, .js-generate:visible')[0];
            if (g) g.focus();
            return;
        }
        var pick = list[0];
        var cur = list.map(function (f) { return f.id; }).indexOf(lastNextUid);
        if (cur >= 0 && list.length > 1) { pick = list[(cur + 1) % list.length]; }
        lastNextUid = pick.id;
        var $el = fieldControl(pick);
        var el = $el[0];
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        try { el.focus({ preventScroll: true }); } catch (e) {}
        var $flash = $el.closest('.df-fill-field, .df-sign-wrap');
        if (!$flash.length) $flash = $el;
        $flash.addClass('df-next-flash');
        setTimeout(function () { $flash.removeClass('df-next-flash'); }, 1400);
    }

    // Returns true when this field belongs to a different recipient and should be locked.
    // Unassigned fields (no assigned_to) are editable by any recipient.
    function isOwnedByOther(f) {
        if (IS_FINALIZE || !RECIPIENT_EMAIL) return false;
        var owner = (f.assigned_to || '').toLowerCase();
        return owner !== '' && owner !== RECIPIENT_EMAIL;
    }

    function overlayFields() {
        fields.forEach(function (f) {
            var $page = $('#pdfStack .page-wrap[data-page="' + f.page + '"]');
            if (!$page.length) return;
            var $layer = $page.find('.field-layer');

            // Finalize mode: show already-filled recipient fields as editable/moveable
            if (IS_FINALIZE && recipientValues.hasOwnProperty(f.id)) {
                addEditableRecipientField($layer, f, recipientValues[f.id]);
                return;
            }

            // Signing mode: field already filled by another recipient — lock it
            if (!IS_FINALIZE && signedValues.hasOwnProperty(f.id)) {
                addLockedField($layer, f, signedValues[f.id]);
                return;
            }

            // Signing mode: field belongs to another recipient — show as locked placeholder
            if (isOwnedByOther(f)) {
                addOtherRecipientField($layer, f);
                return;
            }

            if (f.type === 'sign' || f.type === 'initials') {
                addSignaturePad($layer, f);
            } else {
                addTextField($layer, f);
            }
        });
    }

    // Render a greyed-out non-interactive placeholder for fields owned by another recipient.
    function addOtherRecipientField($layer, f) {
        var label = f.assigned_to ? f.assigned_to.split('@')[0] : 'Other recipient';
        var $wrap = $('<div class="df-other-field"></div>').css({
            position: 'absolute',
            left: f.x + 'px', top: f.y + 'px',
            width: f.w + 'px', height: f.h + 'px',
            pointerEvents: 'none',
            boxSizing: 'border-box',
            border: '1.5px dashed #cbd5e1',
            borderRadius: '4px',
            background: 'rgba(241,245,249,0.7)',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            overflow: 'hidden'
        });
        $wrap.append(
            $('<span></span>').css({
                fontSize: '10px', color: '#94a3b8',
                textAlign: 'center', padding: '2px 4px',
                lineHeight: '1.3', userSelect: 'none'
            }).text('For ' + label)
        );
        $layer.append($wrap);
    }

    var FILL_CUSTOMER_FIELD_MAP = {
        email:        'email',
        firstname:    'name',
        first_name:   'name',
        name:         'name',
        lastname:     'lastname',
        last_name:    'lastname',
        surname:      'lastname',
        company:      'companyName',
        company_name: 'companyName',
        companyname:  'companyName',
        organization: 'companyName',
        address:      'fullAddress',
        full_address: 'fullAddress',
        fulladdress:  'fullAddress',
        userid:       'UserID',
        user_id:      'UserID',
        customerid:   'UserID',
        customer_id:  'UserID',
    };

    function autoFillCustomerFields(customerData, targetEmail) {
        var filled = [];
        // targetEmail overrides the assignment-match key (used for __admin__ slot,
        // where assigned_to = '__admin__' but the customer has a real email)
        var matchEmail = targetEmail ? targetEmail.toLowerCase() : (customerData.email || '').toLowerCase().trim();

        // Only autofill fields explicitly assigned to the customer's email (or admin slot)
        function isAssignedToCustomer(f) {
            var assignedTo = (f.assigned_to || '').toLowerCase().trim();
            return matchEmail !== '' && assignedTo === matchEmail;
        }

        // Explicit mapping (set by admin per field in builder) takes priority
        fields.forEach(function (f) {
            if (!isAssignedToCustomer(f)) return;
            var key = f.customer_key || '';
            if (!key || (f.type !== 'text' && f.type !== 'date')) return;
            var value = f.customer_value || customerData[key];
            if (value == null || value === '') return;
            var $ta = $('input.df-fill-input[data-field-id="' + f.id + '"]');
            if ($ta.length && !$ta.val()) {
                $ta.val(String(value));
                filled.push($ta[0]);
            }
        });

        // Fallback: name-based matching for any unmapped text fields
        $('#pdfStack .df-fill-field[data-type="text"], #pdfStack .df-fill-field[data-type="date"]').each(function () {
            var fieldId = $(this).attr('data-field-id') || '';
            var fieldMeta = fieldId ? fields.find(function (f) { return f.id === fieldId; }) : null;
            if (!fieldMeta || !isAssignedToCustomer(fieldMeta)) return;
            if (fieldMeta.customer_key) return; // already handled by explicit pass
            var fieldName = ($(this).attr('data-name') || '').toLowerCase().replace(/[^a-z0-9]/g, '_');
            var mapKey = FILL_CUSTOMER_FIELD_MAP[fieldName];
            if (!mapKey) return;
            var value = customerData[mapKey];
            if (value == null || value === '') return;
            var $ta = $(this).find('input.df-fill-input');
            if ($ta.length && !$ta.val()) {
                $ta.val(String(value));
                filled.push($ta[0]);
            }
        });

        // Defer font-size calculation until the browser has laid out the elements,
        // then also notify progress so the counter reflects pre-filled values
        if (filled.length) {
            requestAnimationFrame(function () {
                filled.forEach(function (el) { autoShrink(el); });
                updateProgress();
            });
        }
    }

    function addLockedField($layer, f, value) {
        var $wrap = $('<div class="df-locked-field"></div>').css({
            position: 'absolute',
            left: f.x + 'px', top: f.y + 'px',
            width: f.w + 'px', height: f.h + 'px',
            pointerEvents: 'none',
            boxSizing: 'border-box',
            border: '1.5px solid rgba(22,163,74,0.35)',
            borderRadius: '3px',
            background: 'rgba(220,252,231,0.15)',
            overflow: 'hidden'
        });

        // Always show the value in the overlay. The finalize background is now the
        // original clean PDF (no longer a stamped preview), so locked recipient values
        // must be visible here so the admin can review what recipients filled in.
        if ((f.type === 'sign' || f.type === 'initials') && value && value.indexOf('data:image/') === 0) {
            $wrap.append(
                $('<img />').attr('src', value).css({
                    width: '100%', height: '100%',
                    objectFit: 'contain', display: 'block'
                })
            );
        } else {
            var fs = Math.max(9, (f.font_size || 12));
            $wrap.append(makeValueDisplay(fs).text(value));
        }

        // Small "signed" indicator
        $wrap.append(
            $('<div></div>').css({
                position: 'absolute', bottom: '1px', right: '2px',
                fontSize: '8px', color: 'rgba(22,163,74,0.7)',
                lineHeight: '1', pointerEvents: 'none'
            }).text('✓')
        );

        $layer.append($wrap);
    }

    // Render a recipient-filled field as draggable display box (finalize mode).
    // Clicking opens the right panel to edit the value.
    function addEditableRecipientField($layer, f, value) {
        var override = { field_uid: f.id, page: f.page, x: f.x, y: f.y, w: f.w, h: f.h };
        recipientOverrides[f.id] = override;

        var isSig = (f.type === 'sign' || f.type === 'initials') && value && value.indexOf('data:image/') === 0;

        var $wrap = $('<div class="df-recipient-override"></div>').css({
            position: 'absolute',
            left: f.x + 'px', top: f.y + 'px',
            width: f.w + 'px', height: f.h + 'px',
            boxSizing: 'border-box',
            border: '1.5px solid #0891b2',
            borderRadius: '4px',
            background: isSig ? 'rgba(224,247,250,0.18)' : 'rgba(224,247,250,0.18)',
            zIndex: 15, userSelect: 'none', cursor: 'pointer'
        }).attr('data-field-id', f.id);

        // Drag handle
        var $handle = $('<div></div>').css({
            position: 'absolute', bottom: '100%', left: '-1px', right: '-1px',
            height: '16px', background: '#0891b2', borderRadius: '3px 3px 0 0',
            cursor: 'move', display: 'flex', alignItems: 'center',
            padding: '0 4px', zIndex: 4, userSelect: 'none'
        });
        $handle.append($('<span></span>').css({
            fontSize: '9px', color: '#fff', fontWeight: 600,
            flex: 1, overflow: 'hidden', whiteSpace: 'nowrap',
            textOverflow: 'ellipsis', pointerEvents: 'none'
        }).text(f.name || f.type));
        $handle.hide();
        $wrap.append($handle);
        $wrap.on('mouseenter', function () { $handle.show(); })
             .on('mouseleave', function () { $handle.hide(); });

        // Display area (read-only, shows current value — uniform bottom-aligned)
        var $display;
        if (isSig) {
            $display = $('<div></div>').css({
                position: 'absolute', inset: 0, overflow: 'hidden', pointerEvents: 'none',
                display: 'flex', alignItems: 'center', justifyContent: 'center'
            });
            $display.append($('<img/>').attr('src', value).css({
                width: '100%', height: '100%', objectFit: 'contain', display: 'block'
            }));
        } else {
            var fs = Math.max(9, (f.font_size || 12));
            $display = makeValueDisplay(fs).text(value || '');
        }

        var $resize = $('<div></div>').css({
            position: 'absolute', bottom: 0, right: 0,
            width: '10px', height: '10px', cursor: 'se-resize',
            background: 'linear-gradient(135deg, transparent 50%, #0891b2 50%)',
            borderRadius: '0 0 3px 0', zIndex: 3
        });

        $wrap.append($display).append($resize);
        makeDraggable($handle, $wrap, override);
        makeResizable($resize, $wrap, null, override);
        $layer.append($wrap);

        // Register state for panel
        if (isSig) {
            sigPads[f.id] = {
                pad: {
                    isEmpty:   function () { return !sigPads[f.id]._dataUrl; },
                    toDataURL: function () { return sigPads[f.id]._dataUrl || ''; },
                    off:       function () {}
                },
                _dataUrl: value,
                watermark: { hide: function () {}, show: function () {} },
                wrap: $wrap, fieldW: f.w, fieldH: f.h,
                $display: $display,
                onApply: function (dataUrl) {
                    $display.empty().append($('<img/>').attr('src', dataUrl).css({
                        width: '100%', height: '100%', objectFit: 'contain', display: 'block'
                    }));
                    if (currentPanelFieldId === f.id) { refreshPanelSignature(f.id, dataUrl); }
                }
            };
            finalizeFieldStates[f.id] = {
                type: f.type, label: f.name || f.type,
                required: !!f.required, $wrap: $wrap
            };
        } else {
            var $hidden = $('<input type="hidden" class="df-fill-input" />').attr({
                'data-field-id': f.id
            }).val(value || '');
            $wrap.append($hidden);
            finalizeFieldStates[f.id] = {
                type: f.type, label: f.name || f.label || f.type,
                value: value || '', required: !!f.required,
                $display: $display, $hidden: $hidden, $wrap: $wrap
            };
        }

        $wrap.on('click', function (e) {
            if ($(e.target).closest('[style*="se-resize"], [style*="cursor: move"]').length) return;
            e.stopPropagation();
            openFinalizePanel(f.id);
        });
    }

    function collectRecipientOverrides() {
        return Object.keys(recipientOverrides).map(function (uid) {
            var ov = recipientOverrides[uid];
            return {
                field_uid: ov.field_uid,
                x: Math.round(ov.x),
                y: Math.round(ov.y),
                w: Math.round(ov.w),
                h: Math.round(ov.h)
            };
        });
    }

    // -----------------------------------------------------------------
    // Finalize right-side panel
    // -----------------------------------------------------------------

    function openFinalizePanel(fieldId) {
        currentPanelFieldId = fieldId;

        // Highlight selected field
        $('[data-field-id]').removeClass('df-finalize-selected');
        $('[data-field-id="' + fieldId + '"]').addClass('df-finalize-selected');

        var state = finalizeFieldStates[fieldId];
        var fieldType = (state && state.type) || 'field';
        var fieldLabel = (state && state.label) || fieldId;

        $('#finalizePanel').addClass('ds-fpanel--open');
        $('#finalizePanelEmpty').hide();
        $('#finalizePanelEditor').css('display', 'flex');
        $('#finalizePanelType').text(fieldType.charAt(0).toUpperCase() + fieldType.slice(1));
        $('#finalizePanelLabel').text(fieldLabel);

        var $body = $('#finalizePanelBody').empty();

        if (fieldType === 'sign' || fieldType === 'initials') {
            buildSignaturePanelBody($body, fieldId, fieldType);
        } else {
            buildTextPanelBody($body, fieldId, state);
        }
    }

    function closeFinalizePanel() {
        currentPanelFieldId = null;
        $('[data-field-id]').removeClass('df-finalize-selected');
        $('#finalizePanel').removeClass('ds-fpanel--open');
        $('#finalizePanelEmpty').show();
        $('#finalizePanelEditor').hide();
    }

    function buildTextPanelBody($body, fieldId, state) {
        var $lbl = $('<div></div>').css({
            fontSize: '11px', fontWeight: 600, color: 'var(--ds-muted)',
            marginBottom: '6px', textTransform: 'uppercase', letterSpacing: '0.6px'
        }).text('Value');

        var $ta = $('<textarea></textarea>').css({
            width: '100%', minHeight: '72px',
            border: '1.5px solid var(--ds-border)',
            borderRadius: '6px', padding: '8px 10px',
            fontSize: '13px', color: 'var(--ds-text)',
            background: 'var(--ds-bg,#fff)',
            resize: 'vertical', outline: 'none',
            boxSizing: 'border-box', fontFamily: 'inherit',
            lineHeight: 1.4, display: 'block'
        }).val(state ? (state.value || '') : '');

        $ta.on('focus', function () { $(this).css('border-color', 'var(--ds-accent)'); });
        $ta.on('blur',  function () { $(this).css('border-color', 'var(--ds-border)'); });
        $ta.on('input', function () {
            var v = $ta.val();
            if (state) {
                state.value = v;
                if (state.$display) state.$display.text(v);
                if (state.$hidden)  state.$hidden.val(v);
            }
            updateProgress();
        });

        // Auto-focus without scrolling the document
        setTimeout(function () { $ta[0] && $ta[0].focus({ preventScroll: true }); }, 60);

        var $hint = $('<div></div>').css({
            fontSize: '11px', color: 'var(--ds-muted)', marginTop: '8px', lineHeight: 1.5
        }).text('Value updates on the document in real time.');

        $body.append($lbl).append($ta).append($hint);
    }

    function buildSignaturePanelBody($body, fieldId, fieldType) {
        var rec = sigPads[fieldId];
        var dataUrl = rec ? rec._dataUrl : null;

        var $previewWrap = $('<div></div>').css({
            width: '100%', height: '90px',
            border: '1.5px solid var(--ds-border)',
            borderRadius: '6px', background: '#fff',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            marginBottom: '12px', overflow: 'hidden'
        }).attr('id', 'panelSigPreview_' + fieldId);

        if (dataUrl) {
            $previewWrap.append($('<img/>').attr('src', dataUrl).css({
                maxWidth: '100%', maxHeight: '100%', objectFit: 'contain'
            }));
        } else {
            $previewWrap.append($('<div></div>').css({
                fontSize: '11px', color: 'var(--ds-muted)'
            }).text('No ' + (fieldType === 'initials' ? 'initials' : 'signature') + ' yet'));
        }

        var $signBtn = $('<button type="button"></button>').addClass('ds-btn ds-btn--primary').css({
            width: '100%', marginBottom: '8px', justifyContent: 'center'
        }).html('<svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin-right:5px"><path d="M20 20H7L3 3"/><path d="M6.5 17.5l2-5.5 6 2-5 3.5z"/></svg>' +
            (fieldType === 'initials' ? 'Add Initials' : 'Sign Here'));
        $signBtn.on('click', function () { openSignatureModal(fieldId); });

        var $clearBtn = $('<button type="button"></button>').addClass('ds-btn ds-btn--ghost').css({
            width: '100%', justifyContent: 'center'
        }).text('Clear');
        $clearBtn.on('click', function () {
            if (rec) { rec._dataUrl = null; }
            if (rec && rec.$display) {
                rec.$display.empty().append($('<div></div>').css({
                    fontSize: '11px', color: 'rgba(22,163,74,0.6)'
                }).text(fieldType === 'initials' ? 'Click to add initials' : 'Click to sign'));
            }
            $previewWrap.empty().append($('<div></div>').css({
                fontSize: '11px', color: 'var(--ds-muted)'
            }).text('No ' + (fieldType === 'initials' ? 'initials' : 'signature') + ' yet'));
            updateProgress();
        });

        $body.append($previewWrap).append($signBtn).append($clearBtn);
    }

    function refreshPanelSignature(fieldId, dataUrl) {
        var $preview = $('#panelSigPreview_' + fieldId);
        if (!$preview.length) return;
        $preview.empty().append($('<img/>').attr('src', dataUrl).css({
            maxWidth: '100%', maxHeight: '100%', objectFit: 'contain'
        }));
    }

    // -----------------------------------------------------------------
    // Collect overrides
    // -----------------------------------------------------------------

    function collectFieldOverrides() {
        return Object.keys(fieldPositionOverrides).map(function (uid) {
            var ov = fieldPositionOverrides[uid];
            return {
                field_uid: ov.field_uid,
                x: Math.round(ov.x),
                y: Math.round(ov.y),
                w: Math.round(ov.w),
                h: Math.round(ov.h)
            };
        });
    }

    // Shared display styles so every field type looks identical on-screen
    // and matches the PDF output (TCPDF Cell + valign='B' + helvetica + lineHeight 1.2)
    var DISPLAY_FONT  = "'Helvetica Neue', Helvetica, Arial, sans-serif";
    var DISPLAY_LH    = 1.2;   // matches TCPDF fitfontsize() lineHeight factor
    var DISPLAY_COLOR = '#000';

    // Build uniform bottom-aligned value display div.
    // Matches PDF: font=helvetica, lineHeight=1.2, color=#000, bottom-aligned, left-aligned, no padding.
    function makeValueDisplay(fontSize) {
        return $('<div></div>').css({
            position: 'absolute', left: 0, right: 0, bottom: 0,
            overflow: 'hidden', whiteSpace: 'nowrap', textOverflow: 'ellipsis',
            fontFamily: DISPLAY_FONT,
            fontSize: fontSize + 'px',
            lineHeight: DISPLAY_LH,
            color: DISPLAY_COLOR,
            padding: '0 3px',
            boxSizing: 'border-box',
            pointerEvents: 'none',
            userSelect: 'none'
        });
    }

    function makeChip(f) {
        var chip = '<span class="df-fill-chip' + (f.required ? ' df-fill-chip--required' : '') + '">' +
                       escapeHtml(f.name || f.label || 'field') +
                       (f.required ? '<span class="df-fill-chip__star">*</span>' : '') +
                   '</span>';
        return chip;
    }

    function addTextField($layer, f) {
        if (IS_FINALIZE) {
            // ── Finalize: draggable display-only field, editing via right panel ──
            var posTrack = { field_uid: f.id, page: f.page, x: f.x, y: f.y, w: f.w, h: f.h };
            fieldPositionOverrides[f.id] = posTrack;

            var now = new Date();
            var initVal = f.customer_value ? String(f.customer_value)
                : (f.type === 'date' ? (now.getFullYear() + '-' + String(now.getMonth()+1).padStart(2,'0') + '-' + String(now.getDate()).padStart(2,'0')) : '');

            var $wrap = $('<div></div>').css({
                position: 'absolute',
                left: f.x + 'px', top: f.y + 'px',
                width: f.w + 'px', height: f.h + 'px',
                boxSizing: 'border-box',
                border: '1.5px solid #f59e0b',
                borderRadius: '4px',
                background: 'rgba(254,243,199,0.18)',
                zIndex: 15, userSelect: 'none', cursor: 'pointer'
            }).attr('data-field-id', f.id);

            var $handle = $('<div></div>').css({
                position: 'absolute', bottom: '100%', left: '-1px', right: '-1px',
                height: '16px', background: '#f59e0b', borderRadius: '3px 3px 0 0',
                cursor: 'move', display: 'flex', alignItems: 'center',
                padding: '0 4px', zIndex: 4, userSelect: 'none'
            });
            $handle.append($('<span></span>').css({
                fontSize: '9px', color: '#fff', fontWeight: 600,
                flex: 1, overflow: 'hidden', whiteSpace: 'nowrap',
                textOverflow: 'ellipsis', pointerEvents: 'none'
            }).text(f.name || f.type));
            $handle.hide();
            $wrap.append($handle);
            $wrap.on('mouseenter', function () { $handle.show(); })
                 .on('mouseleave', function () { $handle.hide(); });

            // Read-only value display (uniform bottom-anchored)
            var fs = Math.max(9, (f.font_size || 12));
            var $display = makeValueDisplay(fs).text(initVal);

            // Hidden input used by collectValues()
            var $hidden = $('<input type="hidden" class="df-fill-input" />').attr({
                'data-field-id': f.id,
                'data-required': f.required ? '1' : '0'
            }).val(initVal);

            var $resize = $('<div></div>').css({
                position: 'absolute', bottom: 0, right: 0,
                width: '10px', height: '10px', cursor: 'se-resize',
                background: 'linear-gradient(135deg, transparent 50%, #f59e0b 50%)',
                borderRadius: '0 0 3px 0', zIndex: 3
            });

            $wrap.append($display).append($hidden).append($resize);
            makeDraggable($handle, $wrap, posTrack);
            makeResizable($resize, $wrap, null, posTrack);
            $layer.append($wrap);

            // Register field state for panel
            finalizeFieldStates[f.id] = {
                type: f.type, label: f.name || f.label || f.type,
                value: initVal, required: !!f.required,
                $display: $display, $hidden: $hidden, $wrap: $wrap
            };

            $wrap.on('click', function (e) {
                if ($(e.target).closest('[style*="se-resize"], [style*="cursor: move"]').length) return;
                e.stopPropagation();
                openFinalizePanel(f.id);
            });
            return;
        }

        // ── Original non-finalize code ──
        var $wrap = $('<div class="df-fill-field"></div>').css({
            position: 'absolute', left: f.x + 'px', top: f.y + 'px',
            width: f.w + 'px', height: f.h + 'px'
        }).attr({ 'data-type': f.type, 'data-name': f.name, 'data-field-id': f.id });
        $wrap.append(makeChip(f));
        var $ta = $('<input type="text" class="df-fill-input" autocomplete="off" />')
            .attr({
                'data-field-id': f.id,
                'data-required': f.required ? '1' : '0',
                'data-font-size': f.font_size || '',
                'aria-label': (f.label || f.name || 'Field') + (f.required ? ' (required)' : ''),
                'aria-required': f.required ? 'true' : 'false',
                placeholder: f.type === 'date' ? 'YYYY-MM-DD' : (f.label || 'Type here')
            });
        if (f.customer_value) {
            $ta.val(String(f.customer_value));
            requestAnimationFrame(function () { autoShrink($ta[0]); });
        } else if (f.type === 'date') {
            var now = new Date();
            var mm  = String(now.getMonth() + 1).padStart(2, '0');
            var dd  = String(now.getDate()).padStart(2, '0');
            $ta.val(now.getFullYear() + '-' + mm + '-' + dd);
            requestAnimationFrame(function () { autoShrink($ta[0]); });
        }
        $wrap.append($ta);
        $layer.append($wrap);
        $ta.on('input', function () {
            autoShrink(this);
            $(this).removeClass('df-fill-input--missing');
            updateProgress();
        });
    }

    // Finalize mode: pre-sign empty __admin__ signature/initials fields with
    // the admin's saved signature from their signing profile.
    function autoFillAdminSignature(sigDataUrl) {
        fields.forEach(function (f) {
            if ((f.assigned_to || '') !== '__admin__') return;
            if (f.type !== 'sign' && f.type !== 'initials') return;
            var rec = sigPads[f.id];
            if (!rec || rec._dataUrl) return;
            rec._dataUrl = sigDataUrl;
            rec.onApply(sigDataUrl);
        });
    }

    function addSignaturePad($layer, f) {
        if (IS_FINALIZE) {
            // ── Finalize: draggable display-only, signing via right panel ──
            var posTrack = { field_uid: f.id, page: f.page, x: f.x, y: f.y, w: f.w, h: f.h };
            fieldPositionOverrides[f.id] = posTrack;

            var initDataUrl = f.default_value || null;

            var $wrap = $('<div></div>').css({
                position: 'absolute',
                left: f.x + 'px', top: f.y + 'px',
                width: f.w + 'px', height: f.h + 'px',
                boxSizing: 'border-box',
                border: '1.5px solid #16a34a',
                borderRadius: '4px',
                background: 'rgba(220,252,231,0.18)',
                zIndex: 15, userSelect: 'none', cursor: 'pointer'
            }).attr('data-field-id', f.id);

            var $handle = $('<div></div>').css({
                position: 'absolute', bottom: '100%', left: '-1px', right: '-1px',
                height: '16px', background: '#16a34a', borderRadius: '3px 3px 0 0',
                cursor: 'move', display: 'flex', alignItems: 'center',
                padding: '0 4px', zIndex: 4, userSelect: 'none'
            });
            $handle.append($('<span></span>').css({
                fontSize: '9px', color: '#fff', fontWeight: 600, pointerEvents: 'none'
            }).text(f.type === 'initials' ? 'INITIALS' : 'SIGNATURE'));
            $handle.hide();
            $wrap.append($handle);
            $wrap.on('mouseenter', function () { $handle.show(); })
                 .on('mouseleave', function () { $handle.hide(); });

            // Display area — shows signature image when signed
            var $display = $('<div></div>').css({
                position: 'absolute', inset: 0,
                display: 'flex', alignItems: 'center', justifyContent: 'center',
                overflow: 'hidden', pointerEvents: 'none'
            });
            if (initDataUrl) {
                $display.append($('<img/>').attr('src', initDataUrl).css({
                    width: '100%', height: '100%', objectFit: 'contain', display: 'block'
                }));
            } else {
                $display.append($('<div></div>').css({
                    fontSize: '11px', color: 'rgba(22,163,74,0.6)', textAlign: 'center'
                }).text(f.type === 'initials' ? 'Click to add initials' : 'Click to sign'));
            }

            var $resize = $('<div></div>').css({
                position: 'absolute', bottom: 0, right: 0,
                width: '10px', height: '10px', cursor: 'se-resize',
                background: 'linear-gradient(135deg, transparent 50%, #16a34a 50%)',
                borderRadius: '0 0 3px 0', zIndex: 3
            });

            $wrap.append($display).append($resize);
            makeDraggable($handle, $wrap, posTrack);
            makeResizable($resize, $wrap, null, posTrack);
            $layer.append($wrap);

            // Stub sigPad — compatible with openSignatureModal and collectValues()
            sigPads[f.id] = {
                pad: {
                    isEmpty:   function () { return !sigPads[f.id]._dataUrl; },
                    toDataURL: function () { return sigPads[f.id]._dataUrl || ''; },
                    off:       function () {}
                },
                _dataUrl: initDataUrl,
                watermark: { hide: function () {}, show: function () {} },
                wrap: $wrap,
                fieldW: f.w, fieldH: f.h,
                $display: $display,
                onApply: function (dataUrl) {
                    $display.empty().append($('<img/>').attr('src', dataUrl).css({
                        width: '100%', height: '100%', objectFit: 'contain', display: 'block'
                    }));
                    if (currentPanelFieldId === f.id) { refreshPanelSignature(f.id, dataUrl); }
                }
            };

            // Register state for panel
            finalizeFieldStates[f.id] = {
                type: f.type, label: f.name || f.type,
                required: !!f.required, $wrap: $wrap
            };

            if (initDataUrl) { updateProgress(); }

            $wrap.on('click', function (e) {
                if ($(e.target).closest('[style*="se-resize"], [style*="cursor: move"]').length) return;
                e.stopPropagation();
                openFinalizePanel(f.id);
            });
            return;
        }

        // ── Original non-finalize code ──
        var $wrap = $('<div class="df-sign-wrap"></div>')
            .css({ left: f.x + 'px', top: f.y + 'px', width: f.w + 'px', height: f.h + 'px' })
            .attr('data-field-id', f.id)
            .attr('data-required', f.required ? '1' : '0')
            .attr({
                tabindex: 0, role: 'button',
                'aria-label': (f.type === 'initials' ? 'Add initials: ' : 'Sign: ') + (f.label || f.name || 'field') + (f.required ? ' (required)' : '')
            });
        $wrap.append(makeChip(f));

        // Backing store at 2x so previews stay crisp when the page is zoomed in.
        var canvas = $('<canvas></canvas>')
            .attr({ width: Math.round(f.w * 2), height: Math.round(f.h * 2) })
            .css({ width: f.w + 'px', height: f.h + 'px', display: 'block', touchAction: 'none' })[0];
        var $watermark = $('<div class="df-sign-watermark">Click to sign</div>');
        var $tools = $(
            '<div class="df-sign-tools">' +
                '<button type="button" data-act="open" title="Open signature pad" aria-label="Open signature pad">Sign</button>' +
                '<button type="button" data-act="clear" title="Clear" aria-label="Clear signature">Clear</button>' +
            '</div>'
        );
        $wrap.append($watermark).append(canvas).append($tools);
        $layer.append($wrap);

        var previewPad = new SignaturePad(canvas, { penColor: '#0f172a' });
        previewPad.off();
        sigPads[f.id] = { pad: previewPad, watermark: $watermark, wrap: $wrap, fieldW: f.w, fieldH: f.h };

        if (f.default_value) {
            paintSignature(canvas, f.default_value);
            $watermark.hide();
            sigPads[f.id]._dataUrl = f.default_value;
            updateProgress();
        }

        $wrap.on('click', function (e) {
            if ($(e.target).closest('.df-sign-tools').length) return;
            openSignatureModal(f.id);
        });
        $wrap.on('keydown', function (e) {
            if (e.target !== $wrap[0]) return;
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openSignatureModal(f.id); }
        });
        $tools.find('[data-act="open"]').on('click', function () {
            openSignatureModal(f.id);
        });
        $tools.find('[data-act="clear"]').on('click', function () {
            previewPad.clear();
            $watermark.show();
            $wrap.removeClass('df-sign-wrap--missing');
            delete sigPads[f.id]._dataUrl;
            updateProgress();
        });
    }

    // -----------------------------------------------------------------
    // Signature modal — large canvas + image upload
    // -----------------------------------------------------------------
    // recOverride: optional record (admin-added fields keep theirs in adminSigPads).
    function openSignatureModal(fieldId, recOverride) {
        var rec = recOverride || sigPads[fieldId];
        if (!rec) return;

        // Canvas CSS size adapts to the viewport; backing store is DPR-scaled.
        function modalWidth() { return Math.max(220, Math.min(560, window.innerWidth - 48)); }
        var cssW = modalWidth();
        var cssH = Math.round(cssW * 0.4);

        var $content = $('<div style="padding:0;"></div>');
        $content.append(
            '<div style="padding:16px 20px 8px;display:flex;align-items:center;flex-wrap:wrap;gap:4px 12px;">' +
                '<span style="font-size:16px;font-weight:600;color:var(--ds-text);">Draw your signature</span>' +
                '<span style="font-size:12px;color:var(--ds-muted);">or upload an image</span>' +
            '</div>'
        );

        // Tab buttons
        var $tabs = $(
            '<div role="tablist" style="display:flex;gap:0;padding:0 20px;border-bottom:1px solid var(--ds-border);">' +
                '<button type="button" role="tab" aria-selected="true" class="ds-sig-tab ds-sig-tab--active" data-tab="draw" style="padding:8px 16px;font-size:13px;font-weight:500;border:none;background:none;border-bottom:2px solid var(--ds-accent);color:var(--ds-accent);cursor:pointer;">Draw</button>' +
                '<button type="button" role="tab" aria-selected="false" class="ds-sig-tab" data-tab="upload" style="padding:8px 16px;font-size:13px;font-weight:500;border:none;background:none;border-bottom:2px solid transparent;color:var(--ds-muted);cursor:pointer;">Upload Image</button>' +
            '</div>'
        );
        $content.append($tabs);

        // Draw panel
        var $drawPanel = $(
            '<div data-panel="draw" style="padding:16px 20px;">' +
                '<div style="position:relative;border:1.5px solid var(--ds-border-strong);border-radius:8px;background:#fff;overflow:hidden;line-height:0;">' +
                    '<canvas aria-label="Signature drawing area" style="display:block;touch-action:none;cursor:crosshair;"></canvas>' +
                    '<div class="df-sign-watermark" style="font-size:16px;">Draw your signature here</div>' +
                '</div>' +
                '<div style="display:flex;gap:8px;margin-top:10px;">' +
                    '<button type="button" class="ds-btn ds-btn--ghost" data-act="undo" aria-label="Undo last stroke" style="font-size:12px;padding:5px 10px;">Undo</button>' +
                    '<button type="button" class="ds-btn ds-btn--ghost" data-act="clear" aria-label="Clear drawing" style="font-size:12px;padding:5px 10px;">Clear</button>' +
                '</div>' +
            '</div>'
        );
        $content.append($drawPanel);

        // Upload panel (hidden initially). PNG/JPEG only: the server cannot stamp SVG.
        var $uploadPanel = $(
            '<div data-panel="upload" style="display:none;padding:16px 20px;">' +
                '<div class="ds-drop" style="position:relative;">' +
                    '<input type="file" accept="image/png,image/jpeg" aria-label="Upload signature image">' +
                    '<div class="ds-drop__title">Drop a signature image here</div>' +
                    '<div style="font-size:12px;color:var(--ds-muted);margin-top:4px;">PNG or JPG &middot; transparent background recommended</div>' +
                '</div>' +
                '<div class="ds-sig-upload-preview" style="display:none;margin-top:12px;text-align:center;">' +
                    '<img alt="Uploaded signature preview" style="max-width:100%;max-height:' + cssH + 'px;border:1px solid var(--ds-border);border-radius:8px;background:repeating-conic-gradient(#f1f5f9 0% 25%, #fff 0% 50%) 50% / 16px 16px;">' +
                    '<div style="margin-top:8px;"><button type="button" class="ds-btn ds-btn--ghost" data-act="remove-img" style="font-size:12px;padding:5px 10px;">Remove</button></div>' +
                '</div>' +
            '</div>'
        );
        $content.append($uploadPanel);

        // Footer
        $content.append(
            '<div style="display:flex;gap:8px;justify-content:flex-end;padding:12px 20px 18px;border-top:1px solid var(--ds-border);">' +
                '<button type="button" class="ds-btn ds-btn--ghost" data-act="cancel">Cancel</button>' +
                '<button type="button" class="ds-btn ds-btn--primary" data-act="apply">Apply Signature</button>' +
            '</div>'
        );

        var handle = window.DSModal.open($content[0], { persistent: true });
        // Widen the modal when the modal implementation exposes its element
        if (handle && handle.modal) { $(handle.modal).css({ maxWidth: (cssW + 48) + 'px' }); }

        function closeModal() {
            $(window).off('resize.sigmodal');
            if (handle && handle.close) { handle.close(); } else if (window.DSModal.close) { window.DSModal.close(); }
        }

        // Init the large drawing pad (DPR-aware)
        var bigCanvas = $drawPanel.find('canvas')[0];
        var $bigWatermark = $drawPanel.find('.df-sign-watermark');
        function sizeCanvas() {
            var dpr = Math.max(1, Math.min(3, window.devicePixelRatio || 1));
            bigCanvas.width = Math.round(cssW * dpr);
            bigCanvas.height = Math.round(cssH * dpr);
            bigCanvas.style.width = cssW + 'px';
            bigCanvas.style.height = cssH + 'px';
            bigCanvas.getContext('2d').scale(dpr, dpr);
        }
        sizeCanvas();
        var bigPad = new SignaturePad(bigCanvas, {
            penColor: '#0f172a',
            velocityFilterWeight: 0.7,
            minWidth: 1.5,
            maxWidth: 3
        });
        bigPad.addEventListener('beginStroke', function () { $bigWatermark.hide(); });

        // Re-open: load the existing signature (preview pad, stored data URL or finalize stub)
        var existing = rec._dataUrl || (!rec.pad.isEmpty() ? rec.pad.toDataURL('image/png') : null);
        if (existing) {
            bigPad.fromDataURL(existing, { width: cssW, height: cssH });
            $bigWatermark.hide();
        }

        // Orientation / window resize while open: resize canvas, keep the drawing
        $(window).on('resize.sigmodal', function () {
            if (!document.body.contains(bigCanvas)) { $(window).off('resize.sigmodal'); return; }
            var w = modalWidth();
            if (w === cssW) return;
            var snap = bigPad.isEmpty() ? null : bigPad.toDataURL('image/png');
            cssW = w; cssH = Math.round(w * 0.4);
            sizeCanvas();
            bigPad.clear();
            if (handle && handle.modal) { $(handle.modal).css({ maxWidth: (cssW + 48) + 'px' }); }
            if (snap) { bigPad.fromDataURL(snap, { width: cssW, height: cssH }); $bigWatermark.hide(); }
            else { $bigWatermark.show(); }
        });

        var uploadedDataUrl = null;
        var activeTab = 'draw';

        // Tab switching
        $tabs.on('click', '.ds-sig-tab', function () {
            activeTab = $(this).data('tab');
            $tabs.find('.ds-sig-tab').css({ borderBottomColor: 'transparent', color: 'var(--ds-muted)' }).removeClass('ds-sig-tab--active').attr('aria-selected', 'false');
            $(this).css({ borderBottomColor: 'var(--ds-accent)', color: 'var(--ds-accent)' }).addClass('ds-sig-tab--active').attr('aria-selected', 'true');
            $content.find('[data-panel]').hide();
            $content.find('[data-panel="' + activeTab + '"]').show();
        });

        // Draw tools
        $drawPanel.find('[data-act="undo"]').on('click', function () {
            var data = bigPad.toData();
            if (data && data.length) {
                data.pop();
                bigPad.fromData(data);
                if (!data.length) $bigWatermark.show();
            }
        });
        $drawPanel.find('[data-act="clear"]').on('click', function () {
            bigPad.clear();
            $bigWatermark.show();
        });

        // Upload: click-to-browse
        var $dropZone = $uploadPanel.find('.ds-drop');
        var $fileInput = $uploadPanel.find('input[type="file"]');
        var $preview = $uploadPanel.find('.ds-sig-upload-preview');
        var $previewImg = $preview.find('img');

        $dropZone.on('click', function () { $fileInput[0].click(); });
        $fileInput.on('click', function (e) { e.stopPropagation(); });
        $dropZone.on('dragover', function (e) { e.preventDefault(); $(this).addClass('ds-drop--over'); });
        $dropZone.on('dragleave drop', function () { $(this).removeClass('ds-drop--over'); });
        $dropZone.on('drop', function (e) {
            e.preventDefault();
            var file = e.originalEvent.dataTransfer.files[0];
            if (file) handleUploadedFile(file);
        });
        $fileInput.on('change', function () {
            if (this.files[0]) handleUploadedFile(this.files[0]);
        });

        function handleUploadedFile(file) {
            if (!/^image\/(png|jpeg)$/.test(file.type)) {
                window.DSToast.show('Only PNG or JPG images are allowed (SVG is not supported)', 'error');
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                window.DSToast.show('Image must be under 5 MB', 'error');
                return;
            }
            var reader = new FileReader();
            reader.onload = function (ev) {
                uploadedDataUrl = ev.target.result;
                $previewImg.attr('src', uploadedDataUrl);
                $preview.show();
                $dropZone.hide();
            };
            reader.readAsDataURL(file);
        }

        $uploadPanel.on('click', '[data-act="remove-img"]', function () {
            uploadedDataUrl = null;
            $previewImg.attr('src', '');
            $preview.hide();
            $dropZone.show();
            $fileInput.val('');
        });

        // Cancel
        $content.on('click', '[data-act="cancel"]', closeModal);

        // Apply
        $content.on('click', '[data-act="apply"]', function () {
            var dataUrl = null;

            if (activeTab === 'upload' && uploadedDataUrl) {
                dataUrl = uploadedDataUrl;
            } else if (activeTab === 'draw' && !bigPad.isEmpty()) {
                dataUrl = bigPad.toDataURL('image/png');
            }

            if (!dataUrl) {
                window.DSToast.show('Please draw or upload a signature first', 'error');
                return;
            }

            // Finalize panel fields use onApply; inline pads paint their canvas
            // (aspect-fit, centred, never stretched).
            rec._dataUrl = dataUrl;
            if (typeof rec.onApply === 'function') {
                rec.onApply(dataUrl);
                rec.watermark.hide();
                if (rec.wrap) { rec.wrap.removeClass('df-sign-wrap--missing'); }
                updateProgress();
            } else {
                var previewCanvas = rec.wrap ? rec.wrap.find('canvas')[0] : null;
                if (previewCanvas) {
                    paintSignature(previewCanvas, dataUrl, function () {
                        rec.watermark.hide();
                        rec.wrap.removeClass('df-sign-wrap--missing');
                        updateProgress();
                    });
                } else {
                    rec.watermark.hide();
                    updateProgress();
                }
            }

            closeModal();
            window.DSToast.show('Signature applied', 'success');
        });
    }

    // -----------------------------------------------------------------
    // Auto-shrink (mirrors server-side fitfontsize)
    // -----------------------------------------------------------------
    var measureEl = null;
    function measurer() {
        if (!measureEl) {
            measureEl = $('<span></span>').css({
                position: 'absolute', visibility: 'hidden', whiteSpace: 'pre',
                top: '-9999px', left: '-9999px', pointerEvents: 'none'
            }).appendTo(document.body)[0];
        }
        return measureEl;
    }
    function autoShrink(el) {
        var $el = $(el);
        var customFs = parseFloat($el.attr('data-font-size'));
        var minSize = 6, maxSize = (customFs > 0) ? customFs : 14;
        var text = $el.val() || '';
        if (!text) { $el.css('font-size', maxSize + 'px'); return; }
        // Use the field wrapper width, not the input's own width (which may be shrunk by padding)
        var w = $el.closest('.df-fill-field').innerWidth() || $el.innerWidth();
        if (!w) return;
        var m = measurer();
        $(m).css({
            fontFamily: $el.css('font-family'),
            fontWeight: $el.css('font-weight'),
            whiteSpace: 'pre',
            width: 'auto'
        }).text(text);
        var size = maxSize;
        while (size > minSize) {
            $(m).css('font-size', size + 'px');
            if (m.offsetWidth <= w - 10) break; // subtract horizontal padding
            size -= 0.5;
        }
        $el.css('font-size', size + 'px');
    }

    // -----------------------------------------------------------------
    // Progress
    // -----------------------------------------------------------------
    function isFieldFilled(f) {
        if (f.type === 'sign' || f.type === 'initials') {
            return sigPads[f.id] && (sigPads[f.id]._dataUrl || !sigPads[f.id].pad.isEmpty());
        }
        var $ta = $('input.df-fill-input[data-field-id="' + f.id + '"]');
        return $ta.length && $ta.val().trim() !== '';
    }

    function updateProgress() {
        var done = 0, total = 0;
        fields.forEach(function (f) {
            // Locked recipient fields are already done — exclude from progress
            if (IS_FINALIZE && recipientValues.hasOwnProperty(f.id)) return;
            // Fields already signed by another recipient — exclude from progress
            if (!IS_FINALIZE && signedValues.hasOwnProperty(f.id)) return;
            // Fields owned by another recipient don't count toward this recipient's progress
            if (isOwnedByOther(f)) return;
            total++;
            if (isFieldFilled(f)) done++;
        });
        adminFields.forEach(function (f) {
            total++;
            if (isAdminFieldFilled(f)) done++;
        });
        var pct = total > 0 ? Math.round((done / total) * 100) : 100;
        $('#progressBar .ds-progress__bar, .js-progress-bar .ds-progress__bar').css('width', pct + '%');
        $('#progressBar, .js-progress-bar').toggleClass('ds-progress--complete', pct === 100);
        var label = total > 0 ? (done + ' of ' + total + ' complete') : (IS_FINALIZE ? 'Ready to finalize' : 'Ready');
        $('#progressText, .js-progress-text').text(label);
    }

    // -----------------------------------------------------------------
    // Generate
    // -----------------------------------------------------------------
    function collectValues() {
        var values = {};
        $('.df-fill-input').each(function () {
            values[$(this).data('field-id')] = $(this).val();
        });
        Object.keys(sigPads).forEach(function (id) {
            var rec = sigPads[id];
            if (rec._dataUrl) {
                values[id] = rec._dataUrl;
            } else if (!rec.pad.isEmpty()) {
                values[id] = rec.pad.toDataURL('image/png');
            }
        });
        return values;
    }

    function highlightMissing(missingNames) {
        var nameToField = {};
        fields.forEach(function (f) { nameToField[f.name] = f; });
        var firstEl = null;
        missingNames.forEach(function (name) {
            var f = nameToField[name];
            if (!f) return;
            if (f.type === 'sign' || f.type === 'initials') {
                var rec = sigPads[f.id];
                if (rec) {
                    rec.wrap.addClass('df-sign-wrap--missing');
                    if (!firstEl) firstEl = rec.wrap[0];
                }
            } else {
                var $ta = $('input.df-fill-input[data-field-id="' + f.id + '"]');
                $ta.addClass('df-fill-input--missing');
                if (!firstEl) firstEl = $ta[0];
            }
        });
        if (firstEl) firstEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function onGenerate() {
        var values = collectValues();
        var payload = {
            values: values,
            admin_fields: IS_FINALIZE ? collectAdminFields() : [],
            recipient_overrides: IS_FINALIZE ? collectRecipientOverrides() : [],
            field_overrides: IS_FINALIZE ? collectFieldOverrides() : []
        };

        var genUrl = withToken(BASE_URL + '/' + DOC_ID + '/generate');
        if (!ACCESS_TOKEN && IS_FINALIZE) {
            genUrl += '?base=recipient';
        }

        var $btn = $('#generatePdf, .js-generate');
        if ($btn.first().attr('aria-busy') === 'true') return; // already submitting
        $btn.attr('aria-busy', 'true');
        $.ajax({
            url: genUrl,
            method: 'POST',
            contentType: 'application/json',
            data: JSON.stringify(payload)
        }).done(function (res) {
            $btn.attr('aria-busy', 'false');
            if (res && res.result === 'success') {
                showSuccessModal(res.view_url);
            } else {
                window.DSToast.show((res && res.message) || 'Generation failed', 'error');
            }
        }).fail(function (xhr) {
            $btn.attr('aria-busy', 'false');
            if (handleAuthError(xhr)) return;
            var json = xhr.responseJSON;
            if (xhr.status === 400 && json && Array.isArray(json.missing) && json.missing.length) {
                highlightMissing(json.missing);
                var n = json.missing.length;
                window.DSToast.show('Please fill ' + n + ' required field' + (n === 1 ? '' : 's'), 'error');
            } else {
                window.DSToast.show((json && json.message) || 'Generation failed', 'error');
            }
        });
    }

    function showSuccessModal(viewUrl) {
        var isRecipient = RECIPIENT_ID > 0;
        var title, subtitle;
        if (isRecipient) {
            title    = 'Signing Complete';
            subtitle = 'Your signature has been recorded successfully.';
        } else if (IS_FINALIZE) {
            title    = 'Document Finalized';
            subtitle = 'The document has been finalized with your admin signature.';
        } else {
            title    = 'PDF Generated Successfully';
            subtitle = 'Your signed document is ready.';
        }

        var actionsHtml = '';
        if (isRecipient) {
            // Recipients do not get a download link — admin reviews and emails the final document
            actionsHtml =
                '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:14px 16px;text-align:center;font-size:13px;color:#166534;line-height:1.5;">' +
                    'Admin will review these details and once completed, we will email you the final document.' +
                '</div>' +
                '<a href="' + '/signing/' + DOC_ID + '/done' + '" class="ds-btn ds-btn--ghost" style="justify-content:center;padding:12px;font-size:14px;text-decoration:none;margin-top:4px;">' +
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>' +
                    ' Done' +
                '</a>';
        } else {
            var backHref = '/dashboard';
            var secondBtn = '<a href="' + escapeHtml(backHref) + '" class="ds-btn ds-btn--ghost" style="justify-content:center;padding:12px;font-size:14px;text-decoration:none;">' +
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V9z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>' +
                    ' Back to Dashboard' +
                  '</a>';
            // generate returns {result, view_url}; downloads go through the download
            // endpoint (Content-Disposition: attachment).
            var downloadEndpoint = '/documents/' + DOC_ID + '/download/latest';
            var primaryHref = viewUrl || ('/documents/' + DOC_ID + '/view');
            actionsHtml =
                '<a href="' + escapeHtml(primaryHref) + '" class="ds-btn ds-btn--success" style="justify-content:center;padding:12px;font-size:14px;text-decoration:none;">' +
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>' +
                    ' View Document' +
                '</a>' +
                '<a href="' + escapeHtml(downloadEndpoint) + '" class="ds-btn ds-btn--ghost" style="justify-content:center;padding:12px;font-size:14px;text-decoration:none;">' +
                    '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>' +
                    ' Download PDF' +
                '</a>' + secondBtn;
        }

        var $content = $(
            '<div>' +
                '<div style="text-align:center;padding:28px 24px 12px;">' +
                    '<div style="width:56px;height:56px;margin:0 auto 14px;border-radius:50%;background:#dcfce7;display:flex;align-items:center;justify-content:center;">' +
                        '<svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>' +
                    '</div>' +
                    '<div style="font-size:18px;font-weight:600;color:var(--ds-text);margin-bottom:4px;">' + escapeHtml(title) + '</div>' +
                    '<div style="font-size:13px;color:var(--ds-muted);">' + escapeHtml(subtitle) + '</div>' +
                '</div>' +
                '<div style="display:flex;flex-direction:column;gap:8px;padding:16px 24px 24px;">' +
                    actionsHtml +
                '</div>' +
            '</div>'
        );
        window.DSModal.open($content[0], { persistent: true });
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
})(jQuery);

