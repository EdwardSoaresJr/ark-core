/**
 * ARK-SMS — Brother QL key-tag helpers for QZ Tray.
 *
 * Loaded before print-helpers inline script. Core print flow stays in
 * resources/views/components/print-helpers.blade.php; this file owns detection
 * + debug hooks so operators and developers have a single drop-in module.
 */
(function (window) {
    'use strict';

    var NS = (window.ArkQzKeyTag = window.ArkQzKeyTag || {});

    NS.VERSION = 1;

    /**
     * True for Brother QL series printers (Windows name as shown in QZ).
     * Broader than substring "ql" to avoid false positives.
     */
    NS.printerLooksLikeQl = function (printerName) {
        var s = String(printerName || '').toUpperCase();
        if (!s) {
            return false;
        }
        if (/\bQL[- ]?[0-9]{2,4}\b/.test(s)) {
            return true;
        }
        if (s.indexOf('BROTHER') !== -1 && s.indexOf('QL') !== -1) {
            return true;
        }
        var n = String(printerName || '').toLowerCase();
        return (
            n.indexOf('ql-') !== -1 ||
            n.indexOf('ql ') !== -1 ||
            n.indexOf('brother ql') !== -1
        );
    };

    NS.clientLooksLikeMacOs = function () {
        try {
            if (typeof window.navigator === 'undefined') {
                return false;
            }
            var ua = String(window.navigator.userAgent || '');
            if (/Mac OS X/i.test(ua) || /Macintosh/i.test(ua)) {
                return true;
            }
            var p = String(window.navigator.platform || '');
            if (p === 'MacIntel' || p === 'MacPPC' || p === 'Mac68K') {
                return true;
            }
        } catch (e) {}
        return false;
    };

    NS.logPipeline = function (step, payload) {
        if (window.ARK_DEBUG_PRINT) {
            console.log('[ArkQzKeyTag:' + String(step) + ']', payload || {});
        }
    };
})(typeof window !== 'undefined' ? window : this);
