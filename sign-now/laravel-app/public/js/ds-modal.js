/* DSModal — accessible modal dialogs. No Tailwind / jQuery dependency (styles live in docsign.css).
 *
 *   DSModal.open(contentEl, { persistent, title, labelledBy, onClose }) -> { close(), el }
 *   DSModal.close()                                   closes the topmost modal
 *   DSModal.confirm(msg, onYes, onNo, { title, confirmText, danger })
 *   DSModal.prompt(msg, defaultVal, onOk, onCancel, { type: 'text'|'password', title })
 *
 * All text is inserted with textContent. */
(function (w) {
    'use strict';

    var stack = [];
    var uid = 0;
    var savedOverflow = '';
    var FOCUSABLE = 'a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])';

    function make(tag, cls, text) {
        var n = document.createElement(tag);
        if (cls) n.className = cls;
        if (text != null) n.textContent = text;
        return n;
    }

    function focusables(root) {
        var list = root.querySelectorAll(FOCUSABLE);
        var out = [];
        for (var i = 0; i < list.length; i++) {
            var n = list[i];
            if (n.offsetWidth || n.offsetHeight || n.getClientRects().length) out.push(n);
        }
        return out;
    }

    function onKeydown(e) {
        var top = stack[stack.length - 1];
        if (!top) return;
        if (e.key === 'Escape' || e.key === 'Esc') {
            if (!top.persistent) {
                e.preventDefault();
                e.stopPropagation();
                top.dismiss();
            }
            return;
        }
        if (e.key === 'Tab') {
            var items = focusables(top.modal);
            if (!items.length) { e.preventDefault(); top.modal.focus(); return; }
            var first = items[0], last = items[items.length - 1], active = document.activeElement;
            if (!top.modal.contains(active)) {
                e.preventDefault();
                first.focus();
            } else if (e.shiftKey && (active === first || active === top.modal)) {
                e.preventDefault();
                last.focus();
            } else if (!e.shiftKey && active === last) {
                e.preventDefault();
                first.focus();
            }
        }
    }

    function open(contentEl, opts) {
        opts = opts || {};
        var previous = document.activeElement;
        var backdrop = make('div', 'ds-modal-backdrop');
        var modal = make('div', 'ds-modal');
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        modal.tabIndex = -1;

        if (opts.labelledBy) {
            modal.setAttribute('aria-labelledby', opts.labelledBy);
        } else if (!opts.title) {
            modal.setAttribute('aria-label', 'Dialog');
        } else {
            var titleId = 'dsModalTitle' + (++uid);
            var head = make('div', 'ds-modal__header', opts.title);
            head.id = titleId;
            modal.appendChild(head);
            modal.setAttribute('aria-labelledby', titleId);
        }

        // Content may live (hidden) elsewhere in the page: remember where, and put it back on close.
        var origParent = contentEl.parentNode;
        var origNext = contentEl.nextSibling;
        var wasHidden = contentEl.hidden;
        var origDisplay = contentEl.style.display;
        contentEl.hidden = false;
        if (origDisplay === 'none') contentEl.style.display = '';
        modal.appendChild(contentEl);
        backdrop.appendChild(modal);
        document.body.appendChild(backdrop);

        var closed = false;
        var entry = {
            modal: modal,
            persistent: !!opts.persistent,
            close: function () { close(); },
            dismiss: function () {
                if (closed) return;
                if (typeof opts.onDismiss === 'function') opts.onDismiss();
                close();
            }
        };

        function close() {
            if (closed) return;
            closed = true;
            var idx = stack.indexOf(entry);
            if (idx !== -1) stack.splice(idx, 1);
            if (!stack.length) {
                document.removeEventListener('keydown', onKeydown, true);
                document.body.style.overflow = savedOverflow;
            }
            backdrop.classList.remove('ds-modal-backdrop--show');
            setTimeout(function () {
                if (origParent) {
                    contentEl.hidden = wasHidden;
                    contentEl.style.display = origDisplay;
                    origParent.insertBefore(contentEl, origNext && origNext.parentNode === origParent ? origNext : null);
                }
                if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
            }, 160);
            if (previous && typeof previous.focus === 'function' && document.contains(previous)) previous.focus();
            if (typeof opts.onClose === 'function') opts.onClose();
        }

        if (!stack.length) {
            savedOverflow = document.body.style.overflow;
            document.body.style.overflow = 'hidden';
            document.addEventListener('keydown', onKeydown, true);
        }
        stack.push(entry);

        backdrop.addEventListener('mousedown', function (e) {
            if (e.target === backdrop && !entry.persistent) entry.dismiss();
        });

        void backdrop.offsetWidth; // reflow so the transition runs
        backdrop.classList.add('ds-modal-backdrop--show');

        var auto = modal.querySelector('[data-ds-autofocus]') || focusables(modal)[0] || modal;
        auto.focus();
        if (auto.select && auto.tagName === 'INPUT') { try { auto.select(); } catch (err) { /* ignore */ } }

        return { close: close, el: modal, modal: modal };
    }

    function closeTop() {
        var top = stack[stack.length - 1];
        if (top) top.close();
    }

    function confirmDialog(msg, onYes, onNo, opts) {
        opts = opts || {};
        var uidn = ++uid;
        var wrap = make('div');
        var body = make('div', 'ds-modal__body ds-modal__body--msg', msg);
        body.id = 'dsConfirmMsg' + uidn;
        var footer = make('div', 'ds-modal__footer');
        var no = make('button', 'ds-btn ds-btn--ghost', 'Cancel');
        no.type = 'button';
        var yes = make('button', 'ds-btn ' + (opts.danger ? 'ds-btn--danger' : 'ds-btn--primary'), opts.confirmText || 'Confirm');
        yes.type = 'button';
        yes.setAttribute('data-ds-autofocus', '');
        footer.appendChild(no);
        footer.appendChild(yes);
        wrap.appendChild(body);
        wrap.appendChild(footer);

        var answered = false;
        var handle = open(wrap, {
            title: opts.title || (opts.danger ? 'Are you sure?' : 'Please confirm'),
            onDismiss: function () { if (!answered) { answered = true; if (onNo) onNo(); } }
        });
        handle.el.setAttribute('aria-describedby', body.id);
        yes.addEventListener('click', function () {
            if (answered) return;
            answered = true;
            handle.close();
            if (onYes) onYes();
        });
        no.addEventListener('click', function () {
            if (answered) return;
            answered = true;
            handle.close();
            if (onNo) onNo();
        });
        return handle;
    }

    function promptDialog(msg, defaultVal, onOk, onCancel, opts) {
        opts = opts || {};
        var inputId = 'dsPromptInput' + (++uid);
        var wrap = make('form');
        wrap.setAttribute('novalidate', 'novalidate');
        var body = make('div', 'ds-modal__body');
        var label = make('label', 'ds-modal__body--msg', msg);
        label.setAttribute('for', inputId);
        label.style.display = 'block';
        var input = make('input', 'ds-input');
        input.id = inputId;
        input.type = opts.type === 'password' ? 'password' : 'text';
        input.value = defaultVal == null ? '' : String(defaultVal);
        if (input.type === 'password') input.setAttribute('autocomplete', 'current-password');
        input.setAttribute('data-ds-autofocus', '');
        body.appendChild(label);
        body.appendChild(input);
        var footer = make('div', 'ds-modal__footer');
        var no = make('button', 'ds-btn ds-btn--ghost', 'Cancel');
        no.type = 'button';
        var ok = make('button', 'ds-btn ds-btn--primary', 'OK');
        ok.type = 'submit';
        footer.appendChild(no);
        footer.appendChild(ok);
        wrap.appendChild(body);
        wrap.appendChild(footer);

        var answered = false;
        var handle = open(wrap, {
            title: opts.title || 'Enter a value',
            onDismiss: function () { if (!answered) { answered = true; if (onCancel) onCancel(); } }
        });
        wrap.addEventListener('submit', function (e) {
            e.preventDefault();
            if (answered) return;
            answered = true;
            var v = input.value;
            handle.close();
            if (onOk) onOk(v);
        });
        no.addEventListener('click', function () {
            if (answered) return;
            answered = true;
            handle.close();
            if (onCancel) onCancel();
        });
        return handle;
    }

    w.DSModal = { open: open, close: closeTop, confirm: confirmDialog, prompt: promptDialog };
})(window);
