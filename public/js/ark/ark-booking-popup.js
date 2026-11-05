/*
 * Book Appointment popup for any website: the shop's Core site, ARK Sites templates, or an
 * imported site. Load it from the shop's booking host:
 *
 *   <script src="https://SHOP-HOST/book/popup.js" defer></script>
 *
 * Every link to SHOP-HOST/book, and every element with data-ark-book, opens the booking form in
 * a dialog. data-concern="..." or data-service="slug" (or the same query parameters on the link)
 * prefill it. Modified clicks, and browsers where the dialog cannot open, follow the link to the
 * full booking page instead.
 */
(function () {
    'use strict';

    var config = window.ArkBookingConfig || {};
    if (window.__arkBookingPopup || !config.base || config.enabled === false) { return; }
    window.__arkBookingPopup = true;

    var base = String(config.base).replace(/\/+$/, '');
    var baseOrigin;
    try { baseOrigin = new URL(base).origin; } catch (e) { return; }

    var dialog = null;
    var frame = null;
    var opener = null;
    var lastFocus = null;

    function style() {
        var css = document.createElement('style');
        css.textContent = [
            '.ark-book-dialog{border:0;padding:0;margin:auto;width:min(44rem,100vw - 2rem);height:min(52rem,100dvh - 2rem);max-width:none;max-height:none;border-radius:1rem;box-shadow:0 30px 80px rgb(15 23 42/.35);background:#fff;overflow:hidden;color:#0f172a}',
            '.ark-book-dialog::backdrop{background:rgb(15 23 42/.55);backdrop-filter:blur(4px)}',
            '.ark-book-dialog__frame{display:block;width:100%;height:100%;border:0}',
            '.ark-book-dialog__close{position:absolute;top:.6rem;right:.6rem;z-index:2;width:2.75rem;height:2.75rem;border:0;border-radius:999px;background:rgb(255 255 255/.92);color:#0f172a;font:600 1.5rem/1 system-ui,sans-serif;cursor:pointer;box-shadow:0 1px 4px rgb(15 23 42/.2)}',
            '.ark-book-dialog__close:focus-visible{outline:3px solid #2563eb;outline-offset:2px}',
            '.ark-book-dialog__loading{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font:500 1rem system-ui,sans-serif;color:#475569;background:#fff}',
            '.ark-book-dialog__loading[hidden]{display:none}',
            '.ark-book-float{position:fixed;right:1rem;bottom:calc(1rem + env(safe-area-inset-bottom,0px));z-index:2147483000;border:0;border-radius:999px;padding:.85rem 1.3rem;font:700 .95rem/1.2 system-ui,sans-serif;cursor:pointer;color:#fff;background:#0b5ed7;box-shadow:0 10px 30px rgb(15 23 42/.3)}',
            '@media (max-width:640px){.ark-book-dialog{width:100vw;height:100dvh;border-radius:0}}',
            '@media (prefers-reduced-motion:no-preference){.ark-book-dialog[open]{animation:ark-book-in .2s ease-out}@keyframes ark-book-in{from{opacity:0;transform:translateY(12px)}to{opacity:1;transform:none}}}'
        ].join('');
        document.head.appendChild(css);
    }

    function bookingUrl(source) {
        var params = new URLSearchParams();
        if (source && source.href) {
            try {
                new URL(source.href, window.location.href).searchParams.forEach(function (value, key) {
                    if (key === 'concern' || key === 'service') { params.set(key, value); }
                });
            } catch (e) {}
        }
        if (source && source.getAttribute) {
            if (source.getAttribute('data-concern')) { params.set('concern', source.getAttribute('data-concern')); }
            if (source.getAttribute('data-service')) { params.set('service', source.getAttribute('data-service')); }
        }
        params.set('source_host', window.location.host);
        params.set('source_page', window.location.pathname.slice(0, 60));
        var full = base + '/book?' + params.toString();
        params.set('embed', '1');
        return { full: full, embed: base + '/book?' + params.toString() };
    }

    function build() {
        if (dialog) { return; }
        dialog = document.createElement('dialog');
        dialog.className = 'ark-book-dialog';
        dialog.setAttribute('aria-label', 'Book an appointment');

        var close = document.createElement('button');
        close.type = 'button';
        close.className = 'ark-book-dialog__close';
        close.setAttribute('aria-label', 'Close booking');
        close.textContent = '\u00d7';
        close.addEventListener('click', hide);

        var loading = document.createElement('div');
        loading.className = 'ark-book-dialog__loading';
        loading.textContent = 'Loading…';

        frame = document.createElement('iframe');
        frame.className = 'ark-book-dialog__frame';
        frame.title = 'Book an appointment';
        frame.setAttribute('allow', 'clipboard-write');
        frame.addEventListener('load', function () { loading.hidden = true; });

        dialog.appendChild(close);
        dialog.appendChild(loading);
        dialog.appendChild(frame);
        dialog.addEventListener('close', restore);
        dialog.addEventListener('click', function (event) {
            if (event.target === dialog) { hide(); }
        });
        document.body.appendChild(dialog);
    }

    // The booking page only lets the shop's own host and its listed websites frame it.
    function mayFrame() {
        if (window.location.origin === baseOrigin) { return true; }
        var host = window.location.hostname.toLowerCase().replace(/^www\./, '');
        return (config.allowedHosts || []).indexOf(host) !== -1;
    }

    function show(source) {
        var urls = bookingUrl(source);
        if (typeof HTMLDialogElement !== 'function' || !mayFrame()) {
            window.location.href = urls.full;
            return;
        }
        build();
        opener = source;
        lastFocus = document.activeElement;
        dialog.querySelector('.ark-book-dialog__loading').hidden = false;
        frame.src = urls.embed;
        document.documentElement.style.overflow = 'hidden';
        try {
            dialog.showModal();
        } catch (e) {
            window.location.href = urls.full;
            return;
        }
        dialog.querySelector('.ark-book-dialog__close').focus();
    }

    function hide() {
        if (dialog && dialog.open) { dialog.close(); }
    }

    function restore() {
        document.documentElement.style.overflow = '';
        if (frame) { frame.src = 'about:blank'; }
        var target = lastFocus || opener;
        if (target && typeof target.focus === 'function') { target.focus(); }
    }

    function isBookingLink(el) {
        if (!el || el.tagName !== 'A' || !el.href || el.target === '_blank' || el.hasAttribute('download')) { return false; }
        try {
            var url = new URL(el.href, window.location.href);
            return url.origin === baseOrigin && url.pathname.replace(/\/+$/, '') === '/book';
        } catch (e) {
            return false;
        }
    }

    document.addEventListener('click', function (event) {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) { return; }
        var trigger = event.target.closest ? event.target.closest('[data-ark-book], a[href]') : null;
        if (!trigger || trigger.closest('.ark-book-dialog')) { return; }
        if (!trigger.hasAttribute('data-ark-book') && !isBookingLink(trigger)) { return; }
        event.preventDefault();
        show(trigger);
    });

    window.addEventListener('message', function (event) {
        if (event.origin !== baseOrigin || !event.data || event.data.source !== 'ark-booking') { return; }
        if (event.data.type === 'ark-book:close' || event.data.type === 'ark-book:escape') { hide(); }
    });

    function start() {
        style();
        if (config.floating && !document.querySelector('[data-ark-book-float]')) {
            var button = document.createElement('button');
            button.type = 'button';
            button.className = 'ark-book-float';
            button.setAttribute('data-ark-book', '');
            button.setAttribute('data-ark-book-float', '');
            button.textContent = config.label || 'Book an appointment';
            if (config.brand) { button.style.background = config.brand; }
            if (config.onBrand) { button.style.color = config.onBrand; }
            document.body.appendChild(button);
        }
    }

    window.ArkBooking = { open: function (options) {
        var el = document.createElement('a');
        el.href = base + '/book';
        if (options && options.concern) { el.setAttribute('data-concern', options.concern); }
        if (options && options.service) { el.setAttribute('data-service', options.service); }
        show(el);
    }, close: hide };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', start);
    } else {
        start();
    }
})();
