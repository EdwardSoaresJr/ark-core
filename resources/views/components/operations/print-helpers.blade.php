@php
    $arkPrintLocId = null;
    $arkPrintKeyTagName = \App\Ark\Operations\Printing\ShopPrintingSettings::keyTagPrinter();
    $arkPrintOilStickerName = \App\Ark\Operations\Printing\ShopPrintingSettings::oilStickerPrinter();
    $arkPrintRoName = $arkPrintKeyTagName;
    $arkPrintLocationLabel = trim((string) (\App\Ark\Operations\Settings\ShopSettings::current()->shop_name ?? '')) ?: null;
    $arkPrintClientMetricsUrl = '';
    $arkQzSignUrl = route('operations.printing.qz.sign');
    $arkQzServerSigning = \App\Ark\Operations\Printing\QzTraySigning::isFullyConfigured();
    $arkQzServerCert = $arkQzServerSigning ? \App\Ark\Operations\Printing\QzTraySigning::certificateContents() : null;
    $arkQzServerSigning = $arkQzServerSigning
        && is_string($arkQzServerCert)
        && str_contains($arkQzServerCert, 'BEGIN CERTIFICATE')
        && str_contains($arkQzServerCert, 'END CERTIFICATE');
    if ($arkQzServerSigning) {
        $arkQzServerCert = trim($arkQzServerCert);
        if ($arkQzServerCert === ''
            || str_contains($arkQzServerCert, "\0")
            || stripos($arkQzServerCert, '</script>') !== false
        ) {
            $arkQzServerSigning = false;
            $arkQzServerCert = null;
        }
    }
    $arkQzJsSignAlgo = \App\Ark\Operations\Printing\QzTraySigning::javascriptSignatureAlgorithm();
    $arkKeyTagQzPage = \App\Ark\Operations\Printing\ShopPrintingSettings::keyTagQzPage();
    $arkKeyTagMediaType = \App\Ark\Operations\Printing\ShopPrintingSettings::keyTagMediaType();
    $arkKeyTagQzOrientation = \App\Ark\Operations\Printing\ShopPrintingSettings::keyTagQzOrientation();
    $arkKeyTagRasterDpi = app(\App\Ark\Operations\Printing\RasterDpiResolver::class)->resolve(request()->userAgent());
    $arkQlKeyTagScaleContent = \App\Ark\Operations\Printing\ShopPrintingSettings::qlKeyTagScaleContent();
    $arkOilStickerQzPage = \App\Ark\Operations\Printing\ShopPrintingSettings::oilStickerQzPage();
    $arkOilStickerQzOrientation = \App\Ark\Operations\Printing\ShopPrintingSettings::oilStickerQzOrientation();
    $arkQlOilStickerScaleContent = \App\Ark\Operations\Printing\ShopPrintingSettings::qlOilStickerScaleContent();
    $arkKeyTagQzDefaults = [
        'width_mm' => (float) config('printing.key_tag_qz_page.width_mm', 62),
        'height_mm' => (float) config('printing.key_tag_qz_page.height_mm', 38.1),
    ];
    $arkQzWizardUrl = route('operations.settings.shop.edit', ['section' => 'printing']);
    $arkPreflightKeyTagUrl = '';
    $arkPrintJsJsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
    $arkPrintJsMsgNeedKeyTagPrinter = json_encode(__('Configure a key tag printer first'), $arkPrintJsJsonFlags);
    $arkPrintJsMsgLearnedCleared = json_encode(__('Learned printer profile cleared for this browser'), $arkPrintJsJsonFlags);
    $arkPrintJsMsgOpeningPreview = json_encode(__('Opening print preview. Print once from your browser if needed; the next key tag print from ARK will try the label printer again.'), $arkPrintJsJsonFlags);
    $arkUserIsAdminForPrintBanner = auth()->user()?->can(\App\Ark\Runtime\Authorization\ArkCapability::SettingsManage->value) ?? false;
    $arkQzTrayScriptUrl = asset('vendor/qz/qz-tray.js');
    $arkQzLoadImmediately = false;
    $arkPrintingQlForceRaster = filter_var(config('printing.ql_force_raster', false), FILTER_VALIDATE_BOOLEAN);
    $arkQlKeyTagLockReferenceRaster = filter_var(config('printing.ql_key_tag_lock_reference_raster', true), FILTER_VALIDATE_BOOLEAN);
    $arkQlKeyTagLockReferencePx = config('printing.ql_key_tag_lock_reference_px', [203 => ['w' => 496, 'h' => 304], 300 => ['w' => 732, 'h' => 450]]);
    $arkQlLabelReferenceMm = config('printing.ql_label_reference_mm', ['width' => 62.0, 'height' => 38.1]);
    $arkPrintingPrinterResolveUrl = route('operations.printing.printer');
    $arkPdfJsVersion = '3.11.174';
    $arkPdfJsDistBase = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@'.$arkPdfJsVersion.'/legacy/build/';
@endphp
{{-- QZ Tray: load qz-tray.js immediately only on Printer Setup Wizard; otherwise defer to first print (arkEnsureQzLoaded) so we do not websocket.connect on every admin page. --}}
<script>
window.ARK_PRINT_LOCATION_LABEL = {!! \Illuminate\Support\Js::from($arkPrintLocationLabel) !!};
window.ARK_PRINT_LOCATION_ID = window.ARK_PRINT_LOCATION_ID ?? {!! \Illuminate\Support\Js::from($arkPrintLocId) !!};
window.ARK_PRINTERS = window.ARK_PRINTERS || {
    keyTag: {!! \Illuminate\Support\Js::from($arkPrintKeyTagName) !!},
    oilSticker: {!! \Illuminate\Support\Js::from($arkPrintOilStickerName) !!},
    default: {!! \Illuminate\Support\Js::from($arkPrintRoName) !!},
};
window.ARK_KEY_TAG_QZ_PAGE = window.ARK_KEY_TAG_QZ_PAGE || {!! \Illuminate\Support\Js::from($arkKeyTagQzPage) !!};
window.ARK_OIL_STICKER_QZ_PAGE = window.ARK_OIL_STICKER_QZ_PAGE || {!! \Illuminate\Support\Js::from($arkOilStickerQzPage) !!};
window.ARK_OIL_STICKER_QZ_ORIENTATION = window.ARK_OIL_STICKER_QZ_ORIENTATION || {!! \Illuminate\Support\Js::from($arkOilStickerQzOrientation) !!};
window.ARK_QL_OIL_STICKER_SCALE_CONTENT = window.ARK_QL_OIL_STICKER_SCALE_CONTENT !== undefined ? window.ARK_QL_OIL_STICKER_SCALE_CONTENT : {!! \Illuminate\Support\Js::from($arkQlOilStickerScaleContent) !!};
window.ARK_KEY_TAG_MEDIA_TYPE = window.ARK_KEY_TAG_MEDIA_TYPE || {!! \Illuminate\Support\Js::from($arkKeyTagMediaType) !!};
window.ARK_KEY_TAG_QZ_ORIENTATION = window.ARK_KEY_TAG_QZ_ORIENTATION || {!! \Illuminate\Support\Js::from($arkKeyTagQzOrientation) !!};
window.ARK_KEY_TAG_RASTER_DPI = window.ARK_KEY_TAG_RASTER_DPI || {!! \Illuminate\Support\Js::from($arkKeyTagRasterDpi) !!};
window.ARK_KEY_TAG_QZ_DEFAULTS = window.ARK_KEY_TAG_QZ_DEFAULTS || {!! \Illuminate\Support\Js::from($arkKeyTagQzDefaults) !!};
window.ARK_QZ_WIZARD_URL = window.ARK_QZ_WIZARD_URL || {!! \Illuminate\Support\Js::from($arkQzWizardUrl) !!};
window.ARK_PREFLIGHT_KEY_TAG_URL = window.ARK_PREFLIGHT_KEY_TAG_URL || {!! \Illuminate\Support\Js::from($arkPreflightKeyTagUrl) !!};
window.ARK_USER_IS_ADMIN = window.ARK_USER_IS_ADMIN ?? {!! \Illuminate\Support\Js::from($arkUserIsAdminForPrintBanner) !!};
@if ($arkQzServerSigning)
window.ARK_QZ_MODE = 'prod';
@else
window.ARK_QZ_MODE = window.ARK_QZ_MODE || 'dev';
@endif
window.ARK_DEBUG_PRINT = window.ARK_DEBUG_PRINT !== undefined ? window.ARK_DEBUG_PRINT : {!! \Illuminate\Support\Js::from(config('app.debug')) !!};
window.ARK_PRINT_CLIENT_METRICS_URL = {!! \Illuminate\Support\Js::from($arkPrintClientMetricsUrl) !!};
window.ARK_QZ_SIGN_MESSAGE_URL = {!! \Illuminate\Support\Js::from($arkQzSignUrl) !!};
window.ARK_PRINT_QUEUE = window.ARK_PRINT_QUEUE || [];
window.ARK_PRINT_QUEUE_RUNNING = !!window.ARK_PRINT_QUEUE_RUNNING;
window.ARK_PRINT_QUEUE_STOP = !!window.ARK_PRINT_QUEUE_STOP;
window.ARK_PRINT_QUEUE_LAST_HARD_ERROR = window.ARK_PRINT_QUEUE_LAST_HARD_ERROR || null;
window.ARK_PRINT_QUEUE_LAST_FAIL_URL = window.ARK_PRINT_QUEUE_LAST_FAIL_URL || null;
window.ARK_FAILED_PRINTS = window.ARK_FAILED_PRINTS || [];
window.ARK_RETRYING = !!window.ARK_RETRYING;
window.ARK_PRINT_STATS = window.ARK_PRINT_STATS || {
    queued: 0,
    succeeded: 0,
    failed: 0,
    retried: 0,
    cancelled: 0
};
window.ARK_PRINTING_QL_FORCE_RASTER = window.ARK_PRINTING_QL_FORCE_RASTER !== undefined
    ? window.ARK_PRINTING_QL_FORCE_RASTER
    : {!! \Illuminate\Support\Js::from($arkPrintingQlForceRaster) !!};
window.ARK_QL_KEY_TAG_LOCK_REFERENCE_RASTER = window.ARK_QL_KEY_TAG_LOCK_REFERENCE_RASTER !== undefined
    ? window.ARK_QL_KEY_TAG_LOCK_REFERENCE_RASTER
    : {!! \Illuminate\Support\Js::from($arkQlKeyTagLockReferenceRaster) !!};
window.ARK_QL_KEY_TAG_LOCK_REFERENCE_PX = window.ARK_QL_KEY_TAG_LOCK_REFERENCE_PX || {!! \Illuminate\Support\Js::from($arkQlKeyTagLockReferencePx) !!};
window.ARK_QL_KEY_TAG_REFERENCE_MM = window.ARK_QL_KEY_TAG_REFERENCE_MM || {!! \Illuminate\Support\Js::from([
    'width' => (float) ($arkQlLabelReferenceMm['width'] ?? 62.0),
    'height' => (float) ($arkQlLabelReferenceMm['height'] ?? 38.1),
]) !!};
window.ARK_PRINTING_PRINTER_RESOLVE_URL = window.ARK_PRINTING_PRINTER_RESOLVE_URL || {!! \Illuminate\Support\Js::from($arkPrintingPrinterResolveUrl) !!};
</script>
<script src="{{ asset('js/ark/ark-qz-key-tag.js') }}"></script>
<script>
(function() {
    var src = {!! \Illuminate\Support\Js::from($arkQzTrayScriptUrl) !!};
    var loadImmediately = {!! \Illuminate\Support\Js::from($arkQzLoadImmediately) !!};
    function arkQzNeedsDefer() {
        return !loadImmediately;
    }
    window.__arkQzScriptSrc = src;
    window.__arkQzDeferLoading = arkQzNeedsDefer();
    window.__arkQzLoadPromise = null;
    window.arkEnsureQzLoaded = function(cb) {
        if (typeof qz !== 'undefined') {
            if (typeof arkInitQzSecurityOnce === 'function') {
                try {
                    arkInitQzSecurityOnce();
                } catch (e) {
                    console.error(e);
                }
            }
            if (cb) {
                cb();
            }
            return;
        }
        if (!window.__arkQzLoadPromise) {
            window.__arkQzLoadPromise = new Promise(function(resolve, reject) {
                var s = document.createElement('script');
                s.src = window.__arkQzScriptSrc;
                s.async = false;
                s.onload = function() {
                    try {
                        if (typeof arkInitQzSecurityOnce === 'function') {
                            arkInitQzSecurityOnce();
                        }
                    } catch (e2) {
                        console.error(e2);
                    }
                    resolve();
                };
                s.onerror = function() {
                    reject(new Error('QZ_TRAY_JS_LOAD_FAILED'));
                };
                (document.head || document.body || document.documentElement).appendChild(s);
            });
        }
        window.__arkQzLoadPromise.then(function() {
            if (cb) {
                cb();
            }
        }).catch(function(err) {
            if (cb) {
                cb(err);
            }
        });
    };
    if (!window.__arkQzDeferLoading) {
        var s2 = document.createElement('script');
        s2.src = src;
        s2.async = false;
        s2.onerror = function() {
            console.error('QZ Tray JS failed to load');
        };
        (document.head || document.body || document.documentElement).appendChild(s2);
    }
})();

var arkPrintLockOwnerHeld = null;
var arkLastQzSigningFailEventAt = 0;
var arkLastQzSigningSuccessEventAt = 0;

/** Fired after a successful signing response; debounced (symmetric with failure). */
function arkDispatchQzSigningSuccess() {
    var now = Date.now();
    if (now - arkLastQzSigningSuccessEventAt < 3500) {
        return;
    }
    arkLastQzSigningSuccessEventAt = now;
    if (window.ARK_DEBUG_PRINT) {
        console.info('ARK QZ: server signing succeeded; dispatched qz-signing-success');
    }
    try {
        window.dispatchEvent(new CustomEvent('qz-signing-success', { bubbles: true }));
    } catch (e) {}
}

/** Fired when POST /qz/sign (or equivalent) fails so UIs can react; debounced. */
function arkDispatchQzSigningFailed() {
    var now = Date.now();
    if (now - arkLastQzSigningFailEventAt < 3500) {
        return;
    }
    arkLastQzSigningFailEventAt = now;
    if (window.ARK_DEBUG_PRINT) {
        console.warn('ARK QZ: server signing failed; dispatched qz-signing-failed');
    }
    try {
        window.dispatchEvent(new CustomEvent('qz-signing-failed', { bubbles: true }));
    } catch (e) {}
}

function arkBumpPrintStat(key) {
    try {
        if (!window.ARK_PRINT_STATS || window.ARK_PRINT_STATS[key] === undefined) {
            return;
        }
        window.ARK_PRINT_STATS[key]++;
    } catch (e) {}
}

function arkShouldSyncPrinterProfile() {
    return false;
}

async function arkSyncPrinterProfile(printerName, profile) {
    return;
}

function arkPrintCsrfToken() {
    var m = document.querySelector('meta[name="csrf-token"]');
    return m ? m.getAttribute('content') : '';
}
function arkInitQzSecurityOnce() {
    if (typeof qz === 'undefined') {
        return;
    }
    if (window.__ARK_QZ_SECURITY_INIT) {
        return;
    }
    window.__ARK_QZ_SECURITY_INIT = true;
    var serverCert = {!! \Illuminate\Support\Js::from($arkQzServerSigning ? $arkQzServerCert : null) !!};
    var serverSigning = {!! \Illuminate\Support\Js::from($arkQzServerSigning) !!};
    if (serverCert && typeof serverCert === 'string') {
        if (
            serverCert.indexOf('BEGIN CERTIFICATE') === -1 ||
            serverCert.indexOf('END CERTIFICATE') === -1
        ) {
            if (window.ARK_DEBUG_PRINT) {
                console.warn('ARK QZ: server certificate missing PEM boundaries; signing disabled for this page load.');
            }
            serverCert = null;
        }
    }
    if (serverSigning && !serverCert) {
        serverSigning = false;
    }
    var signAlgo = {!! \Illuminate\Support\Js::from($arkQzJsSignAlgo) !!};
    var pem = window.ARK_QZ_CERT && String(window.ARK_QZ_CERT).indexOf('BEGIN CERTIFICATE') !== -1;
    var signUrlConfigured = window.ARK_QZ_SIGN_MESSAGE_URL && String(window.ARK_QZ_SIGN_MESSAGE_URL).trim() !== '';

    function arkAttachQzFetchSigner(certPem, algorithm) {
        qz.security.setCertificatePromise(function(resolve, reject) {
            resolve(certPem);
        });
        if (algorithm && typeof qz.security.setSignatureAlgorithm === 'function') {
            qz.security.setSignatureAlgorithm(algorithm);
        }
        qz.security.setSignaturePromise(function(toSign) {
            return function(resolve, reject) {
                fetch(window.ARK_QZ_SIGN_MESSAGE_URL, {
                    cache: 'no-store',
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': arkPrintCsrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                        Accept: 'application/json'
                    },
                    body: JSON.stringify({ data: toSign })
                })
                    .then(function(res) {
                        return res.json().then(function(body) {
                            return { res: res, body: body };
                        });
                    })
                    .then(function(pair) {
                        if (!pair.res.ok || !pair.body || typeof pair.body.signature !== 'string') {
                            var detail =
                                (pair.body && pair.body.error) ? pair.body.error :
                                (pair.body && pair.body.message) ? pair.body.message : '';
                            arkDispatchQzSigningFailed();
                            reject(new Error(detail ? 'SIGNING_FAILED: ' + detail : 'SIGNING_FAILED'));
                            return;
                        }
                        arkDispatchQzSigningSuccess();
                        resolve(pair.body.signature);
                    })
                    .catch(function(err) {
                        arkDispatchQzSigningFailed();
                        reject(err);
                    });
            };
        });
    }

    if (serverSigning && serverCert && String(serverCert).indexOf('BEGIN CERTIFICATE') !== -1) {
        arkAttachQzFetchSigner(serverCert, signAlgo);
    } else if (window.ARK_QZ_MODE === 'prod' && pem && typeof window.ARK_QZ_SIGN === 'function') {
        qz.security.setCertificatePromise(function(resolve, reject) {
            resolve(window.ARK_QZ_CERT);
        });
        if (typeof qz.security.setSignatureAlgorithm === 'function') {
            qz.security.setSignatureAlgorithm(signAlgo);
        }
        qz.security.setSignaturePromise(function(toSign) {
            return function(resolve, reject) {
                Promise.resolve(window.ARK_QZ_SIGN(toSign)).then(resolve).catch(reject);
            };
        });
    } else if (window.ARK_QZ_MODE === 'prod' && pem && signUrlConfigured) {
        arkAttachQzFetchSigner(window.ARK_QZ_CERT, signAlgo);
    } else if (pem) {
        qz.security.setCertificatePromise(function(resolve, reject) {
            resolve(window.ARK_QZ_CERT);
        });
        qz.security.setSignaturePromise(function(toSign) {
            return function(resolve, reject) {
                if (typeof window.ARK_QZ_SIGN === 'function') {
                    Promise.resolve(window.ARK_QZ_SIGN(toSign)).then(resolve).catch(reject);
                } else {
                    resolve();
                }
            };
        });
    } else {
        qz.security.setCertificatePromise(function(resolve, reject) {
            resolve("-----BEGIN CERTIFICATE-----\nDEV\n-----END CERTIFICATE-----");
        });
        qz.security.setSignaturePromise(function(toSign) {
            return function(resolve, reject) {
                resolve();
            };
        });
    }
}
arkInitQzSecurityOnce();

var arkLastPrintSuccessToastAt = 0;
var arkLastPrinterHealthWarnAt = 0;
var arkPrinterCache = { list: null, at: 0 };
var arkRecentPrints = new Set();

function arkPrintKey(printer, url) {
    return String(printer || '') + '|' + String(url || '');
}

function arkNormalizePrinterName(name) {
    return String(name || '')
        .toLowerCase()
        .replace(/[-_]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

function arkMakePrintJobId() {
    return 'print_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7);
}

function arkMakeBatchId() {
    return 'batch_' + Date.now() + '_' + Math.random().toString(36).slice(2, 7);
}

function arkComputeLockTtl(opts) {
    opts = opts || {};
    var count = opts.count !== undefined ? Number(opts.count) : 1;
    var bytes = opts.bytes !== undefined ? Number(opts.bytes) : 0;
    if (count < 1 || isNaN(count)) {
        count = 1;
    }
    if (isNaN(bytes) || bytes < 0) {
        bytes = 0;
    }
    var perItem = 1500;
    var base = 8000;
    var sizeFactor = Math.min(8000, Math.floor(bytes / 50000) * 500);
    return Math.min(120000, base + count * perItem + sizeFactor);
}

function arkParseRoPublicFromPrintUrl(url) {
    try {
        var u = new URL(url, window.location.origin);
        var path = u.pathname;
        var m = path.match(/\/repair-orders\/([^/]+)\/print-key-tag/);
        if (m) {
            return m[1];
        }
        m = path.match(/\/repair-orders\/pdf\/([^/]+)/);
        if (m) {
            return m[1];
        }
    } catch (e) {}
    return null;
}

function arkPostPrintClientMetric(type, payload) {
    var u = window.ARK_PRINT_CLIENT_METRICS_URL;
    if (!u || !type) {
        return;
    }
    payload = payload || {};
    payload.type = type;
    if (payload.print_source === undefined) {
        payload.print_source = 'manual';
    }
    fetch(u, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': arkPrintCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json'
        },
        body: JSON.stringify(payload)
    }).catch(function() {});
}

function arkLevenshtein(s, t) {
    if (s.length > 64 || t.length > 64) {
        return 99;
    }
    var n = s.length;
    var m = t.length;
    var v0 = new Array(m + 1);
    var v1 = new Array(m + 1);
    var i, j, tmp, cost;
    for (j = 0; j <= m; j++) {
        v0[j] = j;
    }
    for (i = 0; i < n; i++) {
        v1[0] = i + 1;
        for (j = 0; j < m; j++) {
            cost = s[i] === t[j] ? 0 : 1;
            v1[j + 1] = Math.min(v1[j] + 1, v0[j + 1] + 1, v0[j] + cost);
        }
        tmp = v0;
        v0 = v1;
        v1 = tmp;
    }
    return v0[m];
}

function arkScorePrinterMatch(configuredName, candidateName) {
    var na = arkNormalizePrinterName(configuredName);
    var nb = arkNormalizePrinterName(candidateName);
    if (!na || !nb) {
        return 99;
    }
    var fw = na.split(' ')[0] || na;
    var prefix = nb.indexOf(fw) === 0 ? -2 : 0;
    return arkLevenshtein(na, nb) + prefix;
}

function arkSuggestSimilarPrinter(printers, configuredName) {
    var normConfigured = arkNormalizePrinterName(configuredName);
    if (!normConfigured) {
        return null;
    }
    var parts = normConfigured.split(' ').filter(function(w) {
        return w.length > 1;
    });
    var i, j, pnorm;
    for (i = 0; i < parts.length; i++) {
        var token = parts[i];
        for (j = 0; j < printers.length; j++) {
            pnorm = arkNormalizePrinterName(printers[j]);
            if (pnorm.indexOf(token) !== -1) {
                return printers[j];
            }
        }
    }
    var best = null;
    var bestScore = 99;
    for (j = 0; j < printers.length; j++) {
        pnorm = arkNormalizePrinterName(printers[j]);
        if (!pnorm) {
            continue;
        }
        var d = arkScorePrinterMatch(configuredName, printers[j]);
        if (d < bestScore) {
            bestScore = d;
            best = printers[j];
        }
    }
    return bestScore <= 5 ? best : null;
}

function arkQzLocationStorageKey(prefix) {
    var id = window.ARK_PRINT_LOCATION_ID;
    if (id !== null && id !== undefined && id !== '' && !isNaN(Number(id))) {
        return prefix + ':' + String(id);
    }
    return prefix + ':default';
}

/** Per-location verified record key (uses location id when available, not display name — renames don’t orphan verification). */
function arkGetVerificationKey() {
    return arkQzLocationStorageKey('ark_qz_verified');
}

window.arkGetVerificationKey = arkGetVerificationKey;

function arkToastrWizardPrintingHint(bodyText, title) {
    var url = window.ARK_QZ_WIZARD_URL || '';
    var html =
        bodyText +
        (url
            ? ' <a href="' +
              url.replace(/"/g, '&quot;') +
              '" class="font-weight-bold text-decoration-underline ml-1">Run Setup Wizard</a>'
            : '');
    if (window.toastr && typeof window.toastr.warning === 'function') {
        window.toastr.warning(html, title || 'Printing', { timeOut: 17000, escapeHtml: false, tapToDismiss: true });
    }
}

function arkCopyPrintDiagnostics(override) {
    var d = override !== undefined && override !== null ? override : window.__ARK_PRINT_LAST_DIAG;
    if (!d) {
        if (window.toastr && typeof window.toastr.warning === 'function') {
            window.toastr.warning('Run a test print first, then copy diagnostics.');
        }
        return;
    }
    var text = JSON.stringify(d, null, 2);
    if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
        navigator.clipboard.writeText(text).then(function() {
            if (window.toastr && typeof window.toastr.success === 'function') {
                window.toastr.success('Diagnostics copied');
            }
        }).catch(function() {
            try {
                window.prompt('Copy diagnostics (Ctrl+C):', text);
            } catch (e2) {
                console.warn(d);
            }
        });
    } else {
        try {
            window.prompt('Copy diagnostics (Ctrl+C):', text);
        } catch (e3) {
            console.warn(d);
        }
    }
}

async function arkShowPrintTestDiagnostics(printerName) {
    var lines = [];
    lines.push('Printed to: ' + (printerName || '—'));
    if (window.ARK_PRINT_LOCATION_LABEL) {
        lines.push('Location: ' + window.ARK_PRINT_LOCATION_LABEL);
    }
    var qzOk = typeof qz !== 'undefined' && qz.websocket.isActive();
    lines.push('QZ: ' + (qzOk ? 'Connected' : 'Not connected'));
    var queue = '—';
    var printersNorm = [];
    if (qzOk) {
        try {
            var list = await arkGetPrintersCached(0);
            var k;
            for (k = 0; k < list.length; k++) {
                printersNorm.push({
                    name: list[k],
                    normalized: arkNormalizePrinterName(list[k])
                });
            }
            var target = printerName || (window.ARK_PRINTERS ? window.ARK_PRINTERS.keyTag : '');
            var found = target && arkPrinterMatchesList(list, target);
            queue = found ? 'Printer found in QZ list' : 'Name not in QZ list (check spelling)';
        } catch (e) {
            queue = 'Could not list printers';
        }
    }
    lines.push('Queue: ' + queue);
    var prof = arkReadQzProfileOk();
    var learnProf = printerName ? arkLoadPrinterProfile(printerName) : null;
    var resolvedMedia = printerName ? arkResolveMediaType(printerName) : null;
    var usingLearned = false;
    if (learnProf && learnProf.mediaType && Number(learnProf.failureCount || 0) < 3) {
        var lm0 = String(learnProf.mediaType).trim();
        usingLearned = lm0 === resolvedMedia;
    }
    var resolvedLabel =
        resolvedMedia === 'red_black'
            ? usingLearned
                ? {!! \Illuminate\Support\Js::from(__('Black / red (learned)')) !!}
                : {!! \Illuminate\Support\Js::from(__('Black / red (settings)')) !!}
            : usingLearned
                ? {!! \Illuminate\Support\Js::from(__('Standard (learned)')) !!}
                : {!! \Illuminate\Support\Js::from(__('Standard (settings)')) !!};
    lines.push('Resolved media: ' + resolvedLabel);
    var msg = lines.join('. ');
    var lockInf = typeof arkCurrentLockInfo === 'function' ? arkCurrentLockInfo() : null;
    try {
        window.__ARK_PRINTER_PROFILE = learnProf;
    } catch (eLp) {}
    window.__ARK_PRINT_LAST_DIAG = {
        timestamp: new Date().toISOString(),
        printer: printerName || null,
        resolved_printer: printerName || null,
        location: window.ARK_PRINT_LOCATION_LABEL || null,
        location_label: window.ARK_PRINT_LOCATION_LABEL || null,
        location_id: window.ARK_PRINT_LOCATION_ID != null ? window.ARK_PRINT_LOCATION_ID : null,
        mediaTypeResolved: resolvedMedia,
        resolved_key_tag_media: resolvedMedia,
        learnedProfile: learnProf,
        tenant_key_tag_media: window.ARK_KEY_TAG_MEDIA_TYPE || null,
        printer_learn_profile: learnProf,
        lastErrorCode: learnProf && learnProf.lastErrorCode != null ? learnProf.lastErrorCode : null,
        qzMode: window.ARK_QZ_MODE || null,
        qz_mode: window.ARK_QZ_MODE || null,
        qzConnected: qzOk,
        qz_connected: qzOk,
        availablePrintersNormalized: printersNorm,
        printers_normalized: printersNorm,
        configuredPrinterNames: {
            key_tag: window.ARK_PRINTERS ? window.ARK_PRINTERS.keyTag : null,
            ro: window.ARK_PRINTERS ? window.ARK_PRINTERS.default : null
        },
        configured_key_tag: window.ARK_PRINTERS ? window.ARK_PRINTERS.keyTag : null,
        configured_ro: window.ARK_PRINTERS ? window.ARK_PRINTERS.default : null,
        keyTagPage: arkKeyTagQzPageOrFallback(),
        key_tag_mm: arkKeyTagQzPageOrFallback(),
        printLockInfo: lockInf,
        queueLength: (window.ARK_PRINT_QUEUE || []).length,
        queueStats: window.ARK_PRINT_STATS ? Object.assign({}, window.ARK_PRINT_STATS) : null,
        queue_hint: queue,
        qz_profile_ok_saved: prof,
        qz_profile_matches_current: prof ? arkQzProfileMatchesCurrent(prof) : false,
        at_iso: new Date().toISOString()
    };
    if (window.toastr && typeof window.toastr.info === 'function') {
        window.toastr.info(msg, 'Print test', { timeOut: 10000 });
    }
}

function arkMarkPrinterCheckOk() {
    try {
        localStorage.setItem('ark_printer_ok_at', String(Date.now()));
    } catch (e) {}
}

function arkShouldSkipPrinterHealth() {
    try {
        var raw = localStorage.getItem('ark_printer_ok_at');
        if (!raw) {
            return false;
        }
        var t = parseInt(raw, 10);
        if (isNaN(t)) {
            return false;
        }
        return (Date.now() - t) < 300000;
    } catch (e) {
        return false;
    }
}

function arkPrinterMatchesList(printers, configuredName) {
    var t = arkNormalizePrinterName(configuredName);
    for (var i = 0; i < printers.length; i++) {
        if (arkNormalizePrinterName(printers[i]) === t) {
            return true;
        }
    }
    return false;
}

function arkBadgeLocationSuffix() {
    if (window.ARK_PRINT_LOCATION_LABEL) {
        return ' (' + window.ARK_PRINT_LOCATION_LABEL + ')';
    }
    return '';
}

/** Fallback if ARK_KEY_TAG_QZ_PAGE is missing — uses window.ARK_KEY_TAG_QZ_DEFAULTS from config/printing.php. */
function arkKeyTagQzPageOrFallback() {
    var p = window.ARK_KEY_TAG_QZ_PAGE || {};
    var d = window.ARK_KEY_TAG_QZ_DEFAULTS || {};
    var w = Number(p.width_mm);
    var h = Number(p.height_mm);
    if (!(w > 0 && h > 0)) {
        w = Number(d.width_mm) > 0 ? Number(d.width_mm) : 62;
        h = Number(d.height_mm) > 0 ? Number(d.height_mm) : 38.1;
    }
    return { width_mm: w, height_mm: h };
}

function arkIsQlLabelDoc(document) {
    var d = String(document || '');
    return (
        d === 'key_tag' ||
        d === 'print_test_key_tag' ||
        d === 'oil_change_sticker' ||
        d === 'print_test_oil_change_sticker' ||
        d === 'parts_label'
    );
}

function arkLabelQzPageForDocument(document) {
    var d = String(document || '');
    if (d === 'oil_change_sticker' || d === 'print_test_oil_change_sticker') {
        var o = window.ARK_OIL_STICKER_QZ_PAGE || {};
        var ow = Number(o.width_mm);
        var oh = Number(o.height_mm);
        if (ow > 0 && oh > 0) {
            return { width_mm: ow, height_mm: oh };
        }
    }
    return arkKeyTagQzPageOrFallback();
}

function arkLabelQzOrientationForDocument(document) {
    var d = String(document || '');
    var o =
        d === 'oil_change_sticker' || d === 'print_test_oil_change_sticker'
            ? window.ARK_OIL_STICKER_QZ_ORIENTATION
            : window.ARK_KEY_TAG_QZ_ORIENTATION;
    var s = String(o || 'auto').toLowerCase();
    return s === 'portrait' || s === 'landscape' || s === 'auto' ? s : 'auto';
}

var ARK_PRINTER_PROFILE_VERSION = 1;

/** Per location id + normalized printer name (stable across display name changes). */
function arkPrinterLearningKey(printerName) {
    var n = arkNormalizePrinterName(printerName || '');
    return arkQzLocationStorageKey('ark_printer_profile') + ':' + (n || 'unknown');
}

function arkLoadPrinterProfile(printerName) {
    var key = arkPrinterLearningKey(printerName);
    try {
        var raw = localStorage.getItem(key);
        if (!raw) {
            return null;
        }
        var parsed = JSON.parse(raw);
        if (!parsed || parsed.version !== ARK_PRINTER_PROFILE_VERSION) {
            try {
                localStorage.removeItem(key);
            } catch (eRm) {}
            return null;
        }
        var maxAge = 30 * 24 * 60 * 60 * 1000;
        var uat = Number(parsed.updated_at);
        if (!uat || Date.now() - uat > maxAge) {
            try {
                localStorage.removeItem(key);
            } catch (eRm2) {}
            return null;
        }
        return parsed;
    } catch (e) {
        try {
            localStorage.removeItem(key);
        } catch (e2) {}
        return null;
    }
}

function arkSavePrinterProfile(printerName, patch) {
    try {
        var key = arkPrinterLearningKey(printerName);
        var prev = arkLoadPrinterProfile(printerName);
        if (!prev) {
            prev = {};
        }
        var next = Object.assign({}, prev, patch, {
            version: ARK_PRINTER_PROFILE_VERSION,
            printerName: printerName,
            updated_at: Date.now()
        });
        localStorage.setItem(key, JSON.stringify(next));
        if (typeof arkShouldSyncPrinterProfile === 'function' && arkShouldSyncPrinterProfile()) {
            Promise.resolve(arkSyncPrinterProfile(printerName, next)).catch(function() {});
        }
    } catch (e) {}
}

/** Learned profile overrides tenant when trusted (failure streak &lt; 3). */
function arkResolveMediaType(printerName) {
    var learned = arkLoadPrinterProfile(printerName);
    var fails = learned && learned.failureCount !== undefined ? Number(learned.failureCount) : 0;
    if (learned && learned.mediaType && fails < 3) {
        var lm = String(learned.mediaType).trim();
        if (lm === 'red_black' || lm === 'mono') {
            return lm;
        }
    }
    return String(window.ARK_KEY_TAG_MEDIA_TYPE || 'mono').trim() === 'red_black' ? 'red_black' : 'mono';
}

function arkLearnFromSuccess(printerName) {
    var media = arkResolveMediaType(printerName);
    var prev = arkLoadPrinterProfile(printerName) || {};
    var sc = Number(prev.successCount || 0) + 1;
    arkSavePrinterProfile(printerName, {
        mediaType: media,
        success: true,
        successCount: sc,
        failureCount: 0,
        lastErrorCode: null,
        auto_detected: false
    });
}

function arkResetLearnedPrinterProfile(printerName) {
    var p = printerName;
    if (p === undefined || p === null || String(p).trim() === '') {
        p = window.ARK_PRINTERS && window.ARK_PRINTERS.keyTag ? window.ARK_PRINTERS.keyTag : '';
    }
    if (!p || String(p).trim() === '') {
        if (window.toastr && typeof window.toastr.warning === 'function') {
            window.toastr.warning({!! $arkPrintJsMsgNeedKeyTagPrinter !!});
        }
        return;
    }
    try {
        localStorage.removeItem(arkPrinterLearningKey(p));
    } catch (e) {}
    try {
        window.__ARK_PRINTER_PROFILE = null;
    } catch (e2) {}
    if (window.toastr && typeof window.toastr.success === 'function') {
        window.toastr.success({!! $arkPrintJsMsgLearnedCleared !!});
    }
}

window.arkLoadPrinterProfile = arkLoadPrinterProfile;
window.arkResolveMediaType = arkResolveMediaType;
window.arkResetLearnedPrinterProfile = arkResetLearnedPrinterProfile;
window.arkCurrentLockInfo = arkCurrentLockInfo;

/**
 * QZ 2.x: colorType is color | grayscale | blackwhite.
 * Brother QL: full WxH (mm). Windows: red_black uses color + density. macOS + QL: always blackwhite (driver/QZ dual-color is unreliable).
 *
 * @param {string} printerName
 * @param {boolean} [keyTagPayloadIsImage] True = raster PNG (QZ rasterize). False = PDF (driver renders page; QZ rasterize off).
 */
function arkGetQzPrintConfig(printerName, keyTagPayloadIsImage, documentType) {
    if (typeof qz === 'undefined' || !qz.configs || typeof qz.configs.create !== 'function') {
        throw new Error('QZ_DISCONNECTED');
    }
    var page = arkLabelQzPageForDocument(documentType || 'key_tag');
    var media = arkResolveMediaType(printerName);
    var pn = String(printerName || '');
    var isQl = arkPrinterNameSuggestsBrotherQl(pn);
    var isMac = arkClientLooksLikeMacOs();
    if (isQl && window.ARK_QL_KEY_TAG_LOCK_REFERENCE_RASTER === true) {
        var ref = window.ARK_QL_KEY_TAG_REFERENCE_MM || {};
        page = {
            width_mm: Number(ref.width) > 0 ? Number(ref.width) : 62,
            height_mm: Number(ref.height) > 0 ? Number(ref.height) : 38.1
        };
    }
    var payloadIsImage = keyTagPayloadIsImage === true;
    var doc = String(documentType || '');
    var qlScale =
        doc === 'oil_change_sticker' || doc === 'print_test_oil_change_sticker'
            ? window.ARK_QL_OIL_STICKER_SCALE_CONTENT === true
            : window.ARK_QL_KEY_TAG_SCALE_CONTENT === true;
    var cfg = {
        units: 'mm',
        copies: 1,
        size: { width: page.width_mm, height: page.height_mm }
    };
    if (isQl) {
        /* PDF to QZ: rasterize false — OS/driver interpret page geometry. PNG: rasterize true + nearest-neighbor. */
        cfg.rasterize = payloadIsImage;
        /* PNG: opt-in stretch if driver needs it. PDF: let the queue render the page (no QZ scale). */
        cfg.scaleContent = payloadIsImage && qlScale;
        cfg.rotation = 0;
        if (payloadIsImage) {
            cfg.interpolation = 'nearest-neighbor';
        }
        var orientMode = arkLabelQzOrientationForDocument(documentType || 'key_tag');
        if (orientMode === 'portrait' || orientMode === 'landscape') {
            cfg.orientation = orientMode;
        } else {
            /* Auto: PDF page matches die-cut label (e.g. 62×38.1 mm) — portrait on Brother QL-800. */
            if (Number(page.width_mm) >= Number(page.height_mm)) {
                cfg.orientation = 'portrait';
            } else {
                cfg.orientation = 'landscape';
            }
        }
    } else {
        cfg.rasterize = true;
    }
    if (media === 'red_black' && !(isQl && isMac)) {
        cfg.colorType = 'color';
        cfg.density = 3;
    } else {
        cfg.colorType = 'blackwhite';
    }
    return qz.configs.create(printerName, cfg);
}

function arkSaveQzProfileOk(printerName) {
    try {
        var page = arkKeyTagQzPageOrFallback();
        var payload = JSON.stringify({
            printer: printerName,
            page: { width_mm: page.width_mm, height_mm: page.height_mm },
            at: Date.now()
        });
        var key = arkQzLocationStorageKey('ark_qz_profile_ok');
        localStorage.setItem(key, payload);
    } catch (e) {}
}

function arkReadQzProfileOk() {
    try {
        var key = arkQzLocationStorageKey('ark_qz_profile_ok');
        var raw = localStorage.getItem(key);
        if (!raw) {
            raw = localStorage.getItem('ark_qz_profile_ok');
        }
        if (!raw) {
            return null;
        }
        return JSON.parse(raw);
    } catch (e) {
        return null;
    }
}

function arkReadQzVerified() {
    try {
        var key = arkQzLocationStorageKey('ark_qz_verified');
        var raw = localStorage.getItem(key);
        if (!raw) {
            raw = localStorage.getItem('ark_qz_verified');
        }
        if (!raw) {
            return null;
        }
        return JSON.parse(raw);
    } catch (e) {
        return null;
    }
}

window.arkReadQzVerifiedForLocation = arkReadQzVerified;

function arkQzProfileMatchesCurrent(prof) {
    if (!prof || !prof.page || !window.ARK_PRINTERS) {
        return false;
    }
    var kt = window.ARK_PRINTERS.keyTag;
    if (!kt || String(kt).trim() === '') {
        return false;
    }
    if (arkNormalizePrinterName(prof.printer) !== arkNormalizePrinterName(kt)) {
        return false;
    }
    var cur = arkKeyTagQzPageOrFallback();
    var pw = Number(prof.page.width_mm);
    var ph = Number(prof.page.height_mm);
    if (!(pw > 0 && ph > 0)) {
        return false;
    }
    return Math.abs(cur.width_mm - pw) < 0.05 && Math.abs(cur.height_mm - ph) < 0.05;
}

/**
 * Client-side QZ wizard verification state for the current location (localStorage only).
 * Returns shape: @{{ verified: boolean, stale?: boolean, mismatch?: boolean, data?: object }}
 */
function arkGetVerificationStatus() {
    try {
        if (!window.ARK_PRINTERS || !String(window.ARK_PRINTERS.keyTag || '').trim()) {
            return { verified: true };
        }
        var raw = localStorage.getItem(arkGetVerificationKey());
        if (!raw) {
            raw = localStorage.getItem('ark_qz_verified');
        }
        if (!raw) {
            return { verified: false };
        }
        var data = JSON.parse(raw);
        if (!data || typeof data.timestamp !== 'number') {
            return { verified: false };
        }
        var maxAge = 7 * 24 * 60 * 60 * 1000;
        var ageMs = Date.now() - data.timestamp;
        if (ageMs > maxAge) {
            return { verified: false, stale: true, data: data };
        }
        if (!data.printer || !data.page || !arkQzProfileMatchesCurrent({ printer: data.printer, page: data.page })) {
            return { verified: false, mismatch: true, data: data };
        }
        return { verified: true, data: data };
    } catch (e) {
        return { verified: false };
    }
}

window.arkGetVerificationStatus = arkGetVerificationStatus;

/**
 * Optional yellow banner (only when #ark-qz-print-setup-banner-host exists).
 * No fallback onto RO action bars — repair order pages omit the host on purpose.
 */
function arkRenderPrinterBanner() {
    if (window.ARK_USER_IS_ADMIN === false) {
        return;
    }
    var host = document.getElementById('ark-qz-print-setup-banner-host');
    if (!host) {
        return;
    }
    var st = arkGetVerificationStatus();
    if (st.verified) {
        return;
    }
    if (!window.ARK_PRINTERS || !String(window.ARK_PRINTERS.keyTag || '').trim()) {
        return;
    }
    if (host.getAttribute('data-ark-qz-banner-done') === '1') {
        return;
    }
    if (host.querySelector && host.querySelector('.ark-qz-print-setup-banner')) {
        return;
    }
    var msg = st.stale
        ? 'Printer setup may be outdated for this location.'
        : 'Printer not verified for this location.';
    var url = window.ARK_QZ_WIZARD_URL || '#';
    var wrap = document.createElement('div');
    wrap.className =
        'ark-qz-print-setup-banner alert alert-warning mb-2 d-flex flex-wrap justify-content-between align-items-center';
    wrap.setAttribute('role', 'alert');
    wrap.innerHTML =
        '<div class="mb-1 mb-md-0 pr-md-2"><strong>Printing Setup Required:</strong> ' + msg + '</div>' +
        '<div class="flex-shrink-0"><a href="' +
        String(url).replace(/"/g, '&quot;') +
        '" class="btn btn-sm btn-primary">Run Setup Wizard</a></div>';
    host.appendChild(wrap);
    host.setAttribute('data-ark-qz-banner-done', '1');
}

/** Hide [data-ark-qz-until-verified] until Printer wizard verification (localStorage) passes. */
function arkApplyQzUntilVerifiedVisibility() {
    if (typeof arkGetVerificationStatus !== 'function') {
        return;
    }
    var st = arkGetVerificationStatus();
    document.querySelectorAll('[data-ark-qz-until-verified="1"]').forEach(function(el) {
        if (st.verified) {
            el.classList.remove('d-none');
        } else {
            el.classList.add('d-none');
        }
    });
}
window.arkApplyQzUntilVerifiedVisibility = arkApplyQzUntilVerifiedVisibility;

window.arkRenderPrinterBanner = arkRenderPrinterBanner;

/** Wizard “known good” + same payload as ark_qz_profile_ok for badge. */
function arkQzWizardMarkVerified(printerName) {
    arkSaveQzProfileOk(printerName);
    try {
        var page = arkKeyTagQzPageOrFallback();
        localStorage.setItem(
            arkQzLocationStorageKey('ark_qz_verified'),
            JSON.stringify({
                printer: printerName,
                page: { width_mm: page.width_mm, height_mm: page.height_mm },
                timestamp: Date.now()
            })
        );
    } catch (e) {}
}

window.arkQzWizardMarkVerified = arkQzWizardMarkVerified;

/** Suffix for badge when a recent successful key-tag print matches current printer + mm settings. */
function arkQzProfileReadySuffix() {
    var prof = arkReadQzProfileOk();
    if (!prof || !arkQzProfileMatchesCurrent(prof)) {
        return '';
    }
    var p = arkKeyTagQzPageOrFallback();
    var w = Math.round(p.width_mm);
    var h = Math.round(p.height_mm * 10) / 10;
    var loc = window.ARK_PRINT_LOCATION_LABEL ? ' · ' + window.ARK_PRINT_LOCATION_LABEL : '';
    return ' · Key tag OK (' + w + '×' + h + ' mm' + loc + ')';
}

function arkClassifyError(err) {
    var raw = err && err.message ? err.message : String(err || '');
    if ((!raw || raw === 'undefined') && err && err.stack) {
        raw = err.stack;
    }
    var m = raw.toLowerCase();
    if (m.indexOf('monochrome media') !== -1) {
        return 'MEDIA_MISMATCH';
    }
    if (m.indexOf('black/red') !== -1 && (m.indexOf('monochrome') !== -1 || m.indexOf('change it') !== -1)) {
        return 'MEDIA_MISMATCH';
    }
    if (m.indexOf('does not match the one selected in the application') !== -1) {
        return 'DRIVER_MEDIA_MISMATCH';
    }
    if (m.indexOf('roll of labels') !== -1 && m.indexOf('match') !== -1) {
        return 'DRIVER_MEDIA_MISMATCH';
    }
    if (m.indexOf('media mismatch') !== -1 || m.indexOf('media does not match') !== -1) {
        return 'DRIVER_MEDIA_MISMATCH';
    }
    if (m.indexOf('duplicate print suppressed') !== -1) {
        return 'DUPLICATE';
    }
    if (m.indexOf('another print job is running') !== -1 || m === 'print_lock') {
        return 'PRINT_LOCK';
    }
    if (m.indexOf('signing_failed') !== -1) {
        return 'SIGNING_FAILED';
    }
    if (m.indexOf('qz_disconnected') !== -1) {
        return 'QZ_DISCONNECTED';
    }
    if (m.indexOf('qz') !== -1 && (m.indexOf('sign') !== -1 || m.indexOf('signature') !== -1)) {
        return 'SIGNING_FAILED';
    }
    if (m.indexOf('not running') !== -1 || m.indexOf('qz tray is not') !== -1) {
        return 'QZ_OFFLINE';
    }
    if (m.indexOf('printer not found') !== -1) {
        return 'PRINTER_MISSING';
    }
    if (m.indexOf('not configured') !== -1) {
        return 'PRINTER_MISSING';
    }
    if (m.indexOf('pdf_invalid_empty') !== -1) {
        return 'PDF_INVALID';
    }
    if (m.indexOf('invalid response') !== -1 || m.indexOf('too small') !== -1) {
        return 'PDF_INVALID';
    }
    if (m.indexOf('timeout') !== -1) {
        return 'TIMEOUT';
    }
    if (m.indexOf('could not read pdf') !== -1) {
        return 'PDF_INVALID';
    }
    if (m.indexOf('key_tag_mac_ql_raster_failed') !== -1) {
        return 'MAC_QL_RASTER';
    }
    if (m.indexOf('key_tag_ql_raster_failed') !== -1) {
        return 'QL_RASTER';
    }
    if (m.indexOf('print_router_failed') !== -1) {
        return 'PRINT_ROUTER';
    }
    return 'UNKNOWN';
}

function arkNotifyPrintError(err) {
    if (err && err._arkSkipNotify) {
        console.warn('ARK print: preview fallback handled', err);
        return;
    }
    if (err && err._arkSilent) {
        console.warn('Silent print error (invisible recovery):', err);
        return;
    }
    var code = arkClassifyError(err);
    if (code === 'DUPLICATE') {
        console.warn('Print suppressed (duplicate within window)');
        return;
    }
    if (code === 'PRINT_LOCK') {
        if (window.toastr && typeof window.toastr.warning === 'function') {
            window.toastr.warning('A print is already in progress. Wait for it to finish or use another tab after it completes.');
        } else {
            console.warn('Print lock active');
        }
        return;
    }
    if (code === 'SIGNING_FAILED') {
        console.error(code, err);
        if (window.toastr && typeof window.toastr.warning === 'function') {
            window.toastr.warning(
                'Server signing failed or QZ could not validate the job. Approve printing in QZ Tray if prompted, check <strong>Advanced → Trusted Sites</strong>, or confirm signing under Repair Order Settings. If prompts repeat after “Always Allow”, the site certificate may have changed.',
                'Printing',
                { timeOut: 18000, escapeHtml: false, tapToDismiss: true }
            );
        }
        return;
    }
    if (code === 'MAC_QL_RASTER' || code === 'QL_RASTER') {
        console.error(code, err);
        if (window.toastr && typeof window.toastr.error === 'function') {
            window.toastr.error(
                'Key tag image step failed (PDF.js → PNG). This only runs when PRINTING_QL_FORCE_RASTER=true. ' +
                    'Allow the PDF toolkit CDN, disable blockers, or set PRINTING_QL_FORCE_RASTER=false so QZ prints the PDF directly. ' +
                    (code === 'MAC_QL_RASTER'
                        ? 'On Mac: also set the Brother queue to 62 mm continuous and turn off fit-to-page.'
                        : '')
            );
        }
        return;
    }
    if (code === 'PRINT_ROUTER') {
        console.error(code, err);
        if (window.toastr && typeof window.toastr.error === 'function') {
            window.toastr.error('Could not resolve printer from server. Refresh and try again.');
        }
        return;
    }
    if (code === 'DRIVER_MEDIA_MISMATCH' || code === 'PRINTER_MISSING' || code === 'MEDIA_MISMATCH') {
        console.warn(code, err);
        var base =
            code === 'PRINTER_MISSING'
                ? 'Printer issue: device not found or name mismatch. Check USB, power, Windows printer list, and Repair Order Settings → key tag printer (exact spelling).'
                : code === 'MEDIA_MISMATCH' && err && err._arkHint === 'exhausted_media'
                    ? 'Label media still mismatched after an automatic retry. Check Label media type in Repair Order Settings, load the correct roll, or use Reset learned printer profile on this computer.'
                    : code === 'MEDIA_MISMATCH'
                        ? 'Label media mismatch (e.g. red/black roll vs standard). Set Label media type in Repair Order Settings or the Printer wizard; this browser learns the working mode after a successful print.'
                        : 'Printer media mismatch (Brother driver vs label size). An admin can fix Windows → Devices & Printers → label printer → Printing Preferences: continuous tape matching your roll and paper size Continuous / Auto. Label mm in ARK-SMS must match the physical tape.';
        arkToastrWizardPrintingHint(base, code === 'PRINTER_MISSING' ? 'Printing' : 'Key tag print');
        return;
    }
    console.error(code, err);
    var detail = err && err.message ? err.message : String(err || '');
    var msg = 'Print failed (' + code + ')' + (detail ? ': ' + detail : '');
    if (window.toastr && typeof window.toastr.error === 'function') {
        window.toastr.error(msg);
        return;
    }
    console.error(msg);
}

function arkNotifyPrintSuccess(message, jobId) {
    var now = Date.now();
    if (now - arkLastPrintSuccessToastAt < 1200) {
        return;
    }
    arkLastPrintSuccessToastAt = now;
    var text = message;
    if (window.ARK_DEBUG_PRINT && jobId) {
        text = message + ' (' + jobId + ')';
    }
    if (window.toastr && typeof window.toastr.success === 'function') {
        window.toastr.success(text);
    }
}

function arkNotifyPrinterHealthWarning(message) {
    var now = Date.now();
    if (now - arkLastPrinterHealthWarnAt < 8000) {
        return;
    }
    arkLastPrinterHealthWarnAt = now;
    if (window.toastr && typeof window.toastr.warning === 'function') {
        window.toastr.warning(message);
    } else {
        console.warn(message);
    }
}

function arkSetBadgeQZOfflineHint() {
    var el = document.getElementById('ark-qz-printer-status');
    if (!el) {
        return;
    }
    el.classList.remove('badge-success', 'badge-warning');
    el.classList.add('badge-secondary');
    el.textContent = 'QZ Tray idle — start app' + arkBadgeLocationSuffix();
    el.style.display = '';
    el.removeAttribute('hidden');
}

function arkBlobToBase64(blob) {
    return new Promise(function(resolve, reject) {
        var reader = new FileReader();
        reader.onloadend = function() {
            var result = reader.result;
            if (typeof result === 'string' && result.indexOf(',') !== -1) {
                resolve(result.split(',')[1]);
            } else {
                reject(new Error('Could not read PDF data'));
            }
        };
        reader.onerror = function() {
            reject(new Error('Could not read PDF data'));
        };
        reader.readAsDataURL(blob);
    });
}

/** See public/js/ark/ark-qz-key-tag.js — ArkQzKeyTag.printerLooksLikeQl */
function arkPrinterNameSuggestsBrotherQl(printerName) {
    if (window.ArkQzKeyTag && typeof window.ArkQzKeyTag.printerLooksLikeQl === 'function') {
        return window.ArkQzKeyTag.printerLooksLikeQl(printerName);
    }
    var s = String(printerName || '').toUpperCase();
    if (!s) {
        return false;
    }
    if (/\bQL[- ]?[0-9]{2,4}\b/.test(s)) {
        return true;
    }
    return s.indexOf('BROTHER') !== -1 && s.indexOf('QL') !== -1;
}

/** See public/js/ark/ark-qz-key-tag.js — ArkQzKeyTag.clientLooksLikeMacOs */
/** Effective QL raster DPI: server seed on page load, then optional API merge; else Mac/203 heuristic. */
function arkKeyTagRasterDpiForRender() {
    var d = Number(window.ARK_KEY_TAG_RASTER_DPI);
    if (d === 203 || d === 300) {
        return d;
    }
    return 300;
}

/**
 * Brother QL: optional locked PNG size (px) matching ql_label_reference_mm for strict driver feed (see config printing.ql_key_tag_lock_reference_raster).
 * Return value: null, or an object with numeric w and h (pixels).
 */
function arkQlKeyTagLockedRasterSizePx() {
    if (window.ARK_QL_KEY_TAG_LOCK_REFERENCE_RASTER !== true) {
        return null;
    }
    var tbl = window.ARK_QL_KEY_TAG_LOCK_REFERENCE_PX || {};
    var dpi = arkKeyTagRasterDpiForRender();
    var row = tbl[dpi] || tbl[String(dpi)];
    if (!row || row.w === undefined || row.h === undefined) {
        return null;
    }
    var w = Number(row.w);
    var h = Number(row.h);
    if (!(w > 0 && h > 0)) {
        return null;
    }
    return { w: Math.round(w), h: Math.round(h) };
}

function arkClientLooksLikeMacOs() {
    if (window.ArkQzKeyTag && typeof window.ArkQzKeyTag.clientLooksLikeMacOs === 'function') {
        return window.ArkQzKeyTag.clientLooksLikeMacOs();
    }
    try {
        if (typeof navigator === 'undefined') {
            return false;
        }
        var ua = String(navigator.userAgent || '');
        if (/Mac OS X/i.test(ua) || /Macintosh/i.test(ua)) {
            return true;
        }
        var p = String(navigator.platform || '');
        if (p === 'MacIntel' || p === 'MacPPC' || p === 'Mac68K') {
            return true;
        }
    } catch (e) {}
    return false;
}

function arkQzKeyTagLog(step, payload) {
    if (window.ArkQzKeyTag && typeof window.ArkQzKeyTag.logPipeline === 'function') {
        window.ArkQzKeyTag.logPipeline(step, payload);
    } else if (window.ARK_DEBUG_PRINT) {
        console.log('[ArkQzKeyTag:' + String(step) + ']', payload || {});
    }
}

var arkPdfJsLoadPromise = null;

function arkEnsurePdfJs() {
    if (window.ARK_DISABLE_QL_IMAGE_BYPASS === true) {
        return Promise.reject(new Error('QL image bypass disabled'));
    }
    if (window.pdfjsLib && typeof window.pdfjsLib.getDocument === 'function') {
        return Promise.resolve(window.pdfjsLib);
    }
    if (arkPdfJsLoadPromise) {
        return arkPdfJsLoadPromise;
    }
    var base = {!! \Illuminate\Support\Js::from($arkPdfJsDistBase) !!};
    arkPdfJsLoadPromise = new Promise(function(resolve, reject) {
        var s = document.createElement('script');
        s.src = base + 'pdf.min.js';
        s.async = true;
        s.onload = function() {
            try {
                var lib = window.pdfjsLib;
                if (!lib || typeof lib.getDocument !== 'function') {
                    arkPdfJsLoadPromise = null;
                    reject(new Error('pdf.js missing getDocument'));
                    return;
                }
                lib.GlobalWorkerOptions.workerSrc = base + 'pdf.worker.min.js';
                resolve(lib);
            } catch (e) {
                arkPdfJsLoadPromise = null;
                reject(e);
            }
        };
        s.onerror = function() {
            arkPdfJsLoadPromise = null;
            reject(new Error('pdf.js script failed to load'));
        };
        document.head.appendChild(s);
    });
    return arkPdfJsLoadPromise;
}

/**
 * Renders first PDF page to PNG (base64, no data: prefix) for QZ pixel/image printing.
 */
async function arkRenderPdfBlobToPngBase64(blob, scale) {
    if (scale === undefined || scale === null) {
        scale = 2;
    }
    var pdfjsLib = await arkEnsurePdfJs();
    var ab = await blob.arrayBuffer();
    var pdf = await pdfjsLib.getDocument({ data: ab }).promise;
    try {
        var page = await pdf.getPage(1);
        var viewport = page.getViewport({ scale: Number(scale), rotation: 0 });
        var canvas = document.createElement('canvas');
        var ctx = canvas.getContext('2d');
        canvas.width = Math.max(1, Math.floor(viewport.width));
        canvas.height = Math.max(1, Math.floor(viewport.height));
        var renderTask = page.render({ canvasContext: ctx, viewport: viewport });
        await renderTask.promise;
        var dataUrl = canvas.toDataURL('image/png');
        var idx = dataUrl.indexOf(',');
        if (idx === -1) {
            throw new Error('png encode failed');
        }
        return dataUrl.slice(idx + 1);
    } finally {
        try {
            await pdf.cleanup();
        } catch (eC) {}
        try {
            await pdf.destroy();
        } catch (eD) {}
    }
}

/**
 * Key-tag raster for Brother QL: PNG is exactly finalW × finalH (mm×DPI or reference lock).
 * High-res PDF.js render, then scale to label WIDTH and center-crop (or center letterbox) vertically so the
 * bitmap is never taller than one QZ label — avoids Brother splitting the job across two feeds.
 */
async function arkRenderKeyTagPdfToPngForQz(blob, documentType) {
    var dpi = arkKeyTagRasterDpiForRender();
    var pageMm = arkLabelQzPageForDocument(documentType || 'key_tag');
    var lockedPx = arkQlKeyTagLockedRasterSizePx();
    var finalW;
    var finalH;
    if (lockedPx) {
        finalW = lockedPx.w;
        finalH = lockedPx.h;
    } else {
        finalW = Math.max(1, Math.round((Number(pageMm.width_mm) * dpi) / 25.4));
        finalH = Math.max(1, Math.round((Number(pageMm.height_mm) * dpi) / 25.4));
    }
    var pdfRenderScale = 3;
    var pdfjsLib = await arkEnsurePdfJs();
    var ab = await blob.arrayBuffer();
    var pdf = await pdfjsLib.getDocument({ data: ab }).promise;
    try {
        var page = await pdf.getPage(1);
        var baseViewport = page.getViewport({ scale: pdfRenderScale });
        var canvas = document.createElement('canvas');
        var ctx = canvas.getContext('2d');
        canvas.width = Math.max(1, Math.floor(baseViewport.width));
        canvas.height = Math.max(1, Math.floor(baseViewport.height));
        await page.render({ canvasContext: ctx, viewport: baseViewport }).promise;

        var finalCanvas = document.createElement('canvas');
        var finalCtx = finalCanvas.getContext('2d');
        finalCanvas.width = finalW;
        finalCanvas.height = finalH;
        finalCtx.fillStyle = '#ffffff';
        finalCtx.fillRect(0, 0, finalW, finalH);

        var cw = Math.max(1, canvas.width);
        var ch = Math.max(1, canvas.height);
        var scaleW = finalW / cw;
        var scaledHeight = ch * scaleW;
        var sy;
        var sh;
        if (scaledHeight > finalH) {
            var cropY = 0;
            sy = cropY / scaleW;
            sh = finalH / scaleW;
            if (sy < 0) {
                sy = 0;
            }
            if (sy + sh > ch) {
                sy = Math.max(0, ch - sh);
            }
            if (sy + sh > ch) {
                sh = Math.max(1, ch - sy);
            }
            finalCtx.drawImage(canvas, 0, sy, cw, sh, 0, 0, finalW, finalH);
        } else {
            sy = 0;
            sh = ch;
            var destH = Math.round(scaledHeight);
            var destY = 0;
            finalCtx.drawImage(canvas, 0, 0, cw, sh, 0, destY, finalW, destH);
        }

        try {
            var refMm = window.ARK_QL_KEY_TAG_REFERENCE_MM || {};
            window.__ARK_LAST_KEY_TAG_RASTER = {
                finalWidth: finalCanvas.width,
                finalHeight: finalCanvas.height,
                dpi: dpi,
                width_mm: Number(pageMm.width_mm),
                height_mm: Number(pageMm.height_mm),
                rasterLockReference: lockedPx !== null,
                qzReferenceMm: { width: Number(refMm.width), height: Number(refMm.height) },
                srcWidth: cw,
                srcHeight: ch,
                rasterCrop: scaledHeight > finalH ? 'top_vertical' : 'top_letterbox',
                scaleW: scaleW,
                scaledHeight: Math.round(scaledHeight * 1000) / 1000
            };
        } catch (eMeta) {}

        if (window.ARK_DEBUG_PRINT) {
            console.log('[ArkQzKeyTag] FINAL SIZE', finalW, finalH, 'dpi', dpi, 'pageMm', Number(pageMm.width_mm), Number(pageMm.height_mm), 'srcPx', cw, ch, 'scaledH', Math.round(scaledHeight));
            finalCtx.strokeStyle = '#ff0000';
            finalCtx.lineWidth = 1;
            finalCtx.strokeRect(0.5, 0.5, finalW - 1, finalH - 1);
        }
        var dataUrl = finalCanvas.toDataURL('image/png');
        var idx = dataUrl.indexOf(',');
        if (idx === -1) {
            throw new Error('png encode failed');
        }
        return dataUrl.slice(idx + 1);
    } finally {
        try {
            await pdf.cleanup();
        } catch (eC) {}
        try {
            await pdf.destroy();
        } catch (eD) {}
    }
}

function arkLockKey() {
    return 'ark_print_lock';
}

function arkEnsureTabId() {
    try {
        var k = 'ark_tab_id';
        var id = sessionStorage.getItem(k);
        if (!id) {
            id = Math.random().toString(36).slice(2);
            sessionStorage.setItem(k, id);
        }
        return id;
    } catch (e) {
        return 'tab_' + Math.random().toString(36).slice(2);
    }
}

function arkCurrentLockInfo() {
    try {
        var raw = localStorage.getItem(arkLockKey());
        if (!raw) {
            return null;
        }
        var data = JSON.parse(raw);
        var exp = Number(data.expiresAt);
        var now = Date.now();
        return {
            owner: data.owner,
            expiresAt: exp,
            createdAt: data.createdAt != null ? Number(data.createdAt) : null,
            reason: data.reason || null,
            expired: !exp || now > exp
        };
    } catch (e) {
        return null;
    }
}

function arkAcquireLock(ttlMs, reason) {
    if (ttlMs === undefined) {
        ttlMs = 15000;
    }
    if (reason === undefined || reason === null) {
        reason = 'print';
    }
    var now = Date.now();
    var owner = arkEnsureTabId();
    var raw;
    try {
        raw = localStorage.getItem(arkLockKey());
    } catch (e) {
        raw = null;
    }
    if (raw) {
        try {
            var prev = JSON.parse(raw);
            if (prev.expiresAt && prev.expiresAt > now) {
                throw new Error('PRINT_LOCK');
            }
        } catch (e) {
            if (e.message === 'PRINT_LOCK') {
                throw e;
            }
        }
    }
    var payload = {
        owner: owner,
        expiresAt: now + ttlMs,
        createdAt: now,
        reason: String(reason)
    };
    try {
        localStorage.setItem(arkLockKey(), JSON.stringify(payload));
        arkPrintLockOwnerHeld = owner;
    } catch (e) {
        throw new Error('PRINT_LOCK');
    }
    return owner;
}

function arkReleaseLock(owner) {
    var raw;
    try {
        raw = localStorage.getItem(arkLockKey());
    } catch (e) {
        return;
    }
    if (!raw) {
        return;
    }
    try {
        var data = JSON.parse(raw);
        if (data.owner === owner) {
            localStorage.removeItem(arkLockKey());
            if (arkPrintLockOwnerHeld === owner) {
                arkPrintLockOwnerHeld = null;
            }
        }
    } catch (e) {
        try {
            localStorage.removeItem(arkLockKey());
            if (arkPrintLockOwnerHeld === owner) {
                arkPrintLockOwnerHeld = null;
            }
        } catch (e2) {}
    }
}

function arkHandlePrintLockUnload() {
    try {
        if (arkPrintLockOwnerHeld) {
            arkReleaseLock(arkPrintLockOwnerHeld);
        }
    } catch (e) {}
}

window.addEventListener('pagehide', arkHandlePrintLockUnload);
window.addEventListener('beforeunload', arkHandlePrintLockUnload);

function arkClearPrintOfflineClass() {
    try {
        document.body.classList.remove('ark-print-offline');
    } catch (e) {}
}

async function ensureQZConnection(retries, delay) {
    if (retries === undefined) {
        retries = 2;
    }
    if (delay === undefined) {
        delay = 300;
    }
    if (typeof qz === 'undefined') {
        await new Promise(function(resolve, reject) {
            if (typeof window.arkEnsureQzLoaded !== 'function') {
                reject(new Error('QZ Tray JS is not loaded'));
                return;
            }
            window.arkEnsureQzLoaded(function(err) {
                if (err) {
                    reject(err instanceof Error ? err : new Error('QZ Tray JS failed to load'));
                    return;
                }
                if (typeof qz === 'undefined') {
                    reject(new Error('QZ Tray JS is not loaded'));
                    return;
                }
                resolve();
            });
        });
    }
    if (qz.websocket.isActive()) {
        arkClearPrintOfflineClass();
        return;
    }
    try {
        await qz.websocket.connect();
        arkClearPrintOfflineClass();
    } catch (e) {
        if (retries <= 0) {
            throw new Error('QZ Tray is not running (start QZ Tray on this computer)');
        }
        await new Promise(function(r) { setTimeout(r, delay); });
        return ensureQZConnection(retries - 1, delay * 2);
    }
}

async function arkResponseLooksLikePdf(response) {
    if (!response.ok) {
        return false;
    }
    var ct = (response.headers.get('content-type') || '').toLowerCase();
    if (ct.indexOf('pdf') !== -1) {
        return true;
    }
    if (ct.indexOf('octet-stream') !== -1) {
        return true;
    }
    var buf = await response.clone().arrayBuffer();
    var header = new TextDecoder().decode(buf.slice(0, 5));
    return header === '%PDF-';
}

function arkTimeout(promise, ms) {
    if (ms === undefined) {
        ms = 8000;
    }
    return Promise.race([
        promise,
        new Promise(function(_, rej) {
            setTimeout(function() { rej(new Error('Print timeout')); }, ms);
        })
    ]);
}

function arkTimeoutForBytes(bytes) {
    if (bytes < 50000) {
        return 6000;
    }
    if (bytes < 300000) {
        return 8000;
    }
    return 12000;
}

async function arkGetPrintersCached(ttlMs) {
    if (ttlMs === undefined) {
        ttlMs = 60000;
    }
    var now = Date.now();
    if (ttlMs > 0 && arkPrinterCache.list && (now - arkPrinterCache.at) < ttlMs) {
        return arkPrinterCache.list;
    }
    var list = await qz.printers.find();
    arkPrinterCache = { list: list, at: now };
    return list;
}

function arkUpdatePrinterStatusBadge(printers) {
    var el = document.getElementById('ark-qz-printer-status');
    if (!el || !window.ARK_PRINTERS) {
        return;
    }
    var kt = window.ARK_PRINTERS.keyTag;
    var df = window.ARK_PRINTERS.default;
    var hasKt = arkPrinterMatchesList(printers, kt);
    var hasDf = arkPrinterMatchesList(printers, df);
    var suf = arkBadgeLocationSuffix();
    el.classList.remove('badge-success', 'badge-warning', 'badge-secondary');
    var profileSuf = arkQzProfileReadySuffix();
    if (hasKt && hasDf) {
        el.textContent = 'Printers ready' + suf + profileSuf;
        el.classList.add('badge-success');
    } else if (!hasKt && !hasDf) {
        el.textContent = 'Check printers' + suf;
        el.classList.add('badge-warning');
    } else if (!hasKt) {
        el.textContent = 'Check key tag printer' + suf;
        el.classList.add('badge-warning');
    } else {
        el.textContent = 'Check RO printer' + suf;
        el.classList.add('badge-warning');
    }
    el.style.display = '';
    el.removeAttribute('hidden');
}

async function arkEnsurePrinterExists(name) {
    if (!name || String(name).trim() === '') {
        throw new Error('Printer name is not configured (Repair Order Settings → QZ Tray)');
    }
    var printers = await arkGetPrintersCached();
    if (!arkPrinterMatchesList(printers, name)) {
        var suggestion = arkSuggestSimilarPrinter(printers, name);
        if (suggestion) {
            throw new Error('Printer not found: ' + name + '. Did you mean: ' + suggestion + '?');
        }
        throw new Error('Printer not found: ' + name);
    }
}

function arkInitQZPrinterHealthHint() {
    if (typeof qz === 'undefined' || !window.ARK_PRINTERS) {
        return;
    }
    setTimeout(function() {
        if (typeof qz !== 'undefined' && !qz.websocket.isActive()) {
            arkSetBadgeQZOfflineHint();
        }
    }, 500);
    if (arkShouldSkipPrinterHealth()) {
        return;
    }
    setTimeout(function() {
        ensureQZConnection(2, 300).then(function() {
            return arkGetPrintersCached();
        }).then(function(printers) {
            arkUpdatePrinterStatusBadge(printers);
            var kt = window.ARK_PRINTERS.keyTag;
            var df = window.ARK_PRINTERS.default;
            var hasKt = arkPrinterMatchesList(printers, kt);
            var hasDf = arkPrinterMatchesList(printers, df);
            if (hasKt && hasDf) {
                arkMarkPrinterCheckOk();
                return;
            }
            var parts = [];
            if (!hasKt) {
                parts.push('Key tag printer missing: ' + kt);
            }
            if (!hasDf) {
                parts.push('RO printer missing: ' + df);
            }
            arkNotifyPrinterHealthWarning(parts.join('. ') + '. Check Repair Order Settings, location overrides, and QZ Tray.');
        }).catch(function() {
            arkSetBadgeQZOfflineHint();
        });
    }, 1500);
}

document.addEventListener('DOMContentLoaded', function() {
    arkInitQZPrinterHealthHint();
    setTimeout(function() {
        if (typeof qz === 'undefined') {
            return;
        }
        if (!qz.websocket.isActive()) {
            try {
                document.body.classList.add('ark-print-offline');
            } catch (e) {}
        }
    }, 800);
});

window.addEventListener('beforeunload', function() {
    document.querySelectorAll('[data-printing="1"]').forEach(function(el) {
        el.dataset.printing = '';
        el.disabled = false;
    });
});

var arkPrintQueueDrainWaiters = [];

function arkQueueStatusColor(status) {
    switch (status) {
        case 'queued':
            return 'secondary';
        case 'printing':
            return 'primary';
        case 'success':
            return 'success';
        case 'failed':
            return 'danger';
        default:
            return 'light';
    }
}

function arkQueueEscapeHtml(s) {
    return String(s)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function arkRenderPrintQueue() {
    var panel = document.getElementById('ark-print-queue-panel');
    if (!panel) {
        return;
    }
    var q = window.ARK_PRINT_QUEUE || [];
    if (q.length === 0) {
        panel.classList.add('d-none');
        panel.innerHTML = '';
        return;
    }
    panel.classList.remove('d-none');
    var pqTitle = panel.getAttribute('data-ark-pq-title') || 'Print queue';
    var pqCancel = panel.getAttribute('data-ark-pq-cancel') || 'Cancel';
    var html = [];
    html.push(
        '<div class="d-flex justify-content-between align-items-center mb-2">' +
            '<strong>' +
            arkQueueEscapeHtml(pqTitle) +
            '</strong>' +
            '<button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="arkCancelPrintQueue(); return false;">' +
            arkQueueEscapeHtml(pqCancel) +
            '</button>' +
            '</div>'
    );
    var i;
    for (i = 0; i < q.length; i++) {
        var item = q[i];
        if (!item || typeof item.run !== 'function') {
            continue;
        }
        var st = item.status || 'queued';
        var col = arkQueueStatusColor(st);
        html.push(
            '<div class="mb-2 ark-print-queue-row" data-job-id="' +
                arkQueueEscapeHtml(item.id || '') +
                '">' +
                '<small class="d-block text-break">' +
                arkQueueEscapeHtml(item.label || '') +
                '</small>' +
                '<span class="badge badge-' +
                col +
                '">' +
                arkQueueEscapeHtml(st) +
                '</span>' +
                '</div>'
        );
    }
    var stt = window.ARK_PRINT_STATS;
    if (stt) {
        html.push(
            '<div class="small text-muted mt-2 pt-2 border-top">' +
                'Session: ok ' +
                String(stt.succeeded) +
                ' · fail ' +
                String(stt.failed) +
                ' · retry ' +
                String(stt.retried) +
                ' · cancelled ' +
                String(stt.cancelled) +
                '</div>'
        );
    }
    panel.innerHTML = html.join('');
}

function arkMakeQueueItem(printer, url, options, wrapRun) {
    options = options || {};
    var doc = options.document || 'print';
    var item = {
        id: 'job_' + Date.now() + '_' + Math.random().toString(36).slice(2, 9),
        label: doc + ' → ' + String(printer || ''),
        status: 'queued',
        run: function() {
            var p = arkPrintWithRecovery(printer, url, options);
            if (typeof wrapRun === 'function') {
                return wrapRun(p);
            }
            return p;
        }
    };
    return item;
}

function arkEnqueuePrintItem(item) {
    if (!item || typeof item.run !== 'function') {
        return;
    }
    window.ARK_PRINT_QUEUE.push(item);
    arkBumpPrintStat('queued');
    arkRenderPrintQueue();
    if (!window.ARK_PRINT_QUEUE_RUNNING) {
        arkRunPrintQueue();
    }
}

function arkCancelPrintQueue() {
    try {
        var n = (window.ARK_PRINT_QUEUE || []).length;
        if (n > 0 && window.ARK_PRINT_STATS) {
            window.ARK_PRINT_STATS.cancelled += n;
        }
    } catch (e) {}
    window.ARK_PRINT_QUEUE_STOP = true;
    window.ARK_PRINT_QUEUE = [];
    arkRenderPrintQueue();
    if (window.toastr && typeof window.toastr.warning === 'function') {
        window.toastr.warning({!! \Illuminate\Support\Js::from(__('Print queue cancelled')) !!});
    }
}

window.arkCancelPrintQueue = arkCancelPrintQueue;

function arkReprintFromHistory(btn) {
    if (!btn || btn.dataset.printing === '1') {
        return;
    }
    var doc = btn.dataset.document;
    var url = btn.dataset.reprintUrl;
    var batchRaw = btn.dataset.batchId ? String(btn.dataset.batchId) : '';
    if (!doc || !url) {
        console.error('Missing reprint data');
        return;
    }
    btn.dataset.printing = '1';
    btn.disabled = true;
    var printer = null;
    if (doc === 'key_tag' || doc === 'parts_label') {
        printer = window.ARK_PRINTERS && window.ARK_PRINTERS.keyTag;
    } else if (doc === 'oil_change_sticker') {
        printer = window.ARK_PRINTERS && window.ARK_PRINTERS.oilSticker;
    } else if (doc === 'repair_order_pdf') {
        printer = window.ARK_PRINTERS && window.ARK_PRINTERS.default;
    } else {
        btn.dataset.printing = '';
        btn.disabled = false;
        console.warn('Unknown document type for reprint:', doc);
        return;
    }
    if (!printer || String(printer).trim() === '') {
        if (window.toastr && typeof window.toastr.warning === 'function') {
            window.toastr.warning('Printer not configured for this document type');
        }
        btn.dataset.printing = '';
        btn.disabled = false;
        return;
    }
    var opts = {
        skipLock: true,
        document: doc,
        printSource: 'reprint'
    };
    if (batchRaw.indexOf('batch_') === 0) {
        opts.batchId = batchRaw;
    }
    arkEnqueuePrintItem(arkMakeQueueItem(printer, url, opts));
    if (window.toastr && typeof window.toastr.info === 'function') {
        window.toastr.info('Reprint queued');
    }
    setTimeout(function() {
        btn.dataset.printing = '';
        btn.disabled = false;
    }, 2000);
}

window.arkReprintFromHistory = arkReprintFromHistory;

function arkPrintHistoryRowVisible(row) {
    if (!row) {
        return false;
    }
    var g = row.closest ? row.closest('.ark-ph-group') : null;
    if (g && g.style && g.style.display === 'none') {
        return false;
    }
    return true;
}

function arkReprintFailed(btn) {
    if (!btn || btn.dataset.printing === '1') {
        return;
    }
    var root = btn.closest('.ark-print-history');
    if (!root) {
        return;
    }
    btn.dataset.printing = '1';
    setTimeout(function() {
        btn.dataset.printing = '0';
    }, 2000);

    var rows = root.querySelectorAll('.ark-print-history-row');
    var queued = 0;
    var i;
    var row;
    var st;
    var cst;
    var isFailed;
    var doc;
    var url;
    var printer;
    var batchRaw;
    var opts;
    for (i = 0; i < rows.length; i++) {
        row = rows[i];
        if (!arkPrintHistoryRowVisible(row)) {
            continue;
        }
        st = row.dataset.status || '';
        cst = row.dataset.clientStatus || '';
        isFailed = st === 'print_failed' || cst === 'print_failed';
        if (!isFailed) {
            continue;
        }
        doc = row.dataset.document || '';
        url = row.dataset.reprintUrl || '';
        if (!url || (doc !== 'key_tag' && doc !== 'parts_label' && doc !== 'oil_change_sticker' && doc !== 'repair_order_pdf')) {
            continue;
        }
        printer = null;
        if (doc === 'key_tag' || doc === 'parts_label') {
            printer = window.ARK_PRINTERS && window.ARK_PRINTERS.keyTag;
        } else if (doc === 'oil_change_sticker') {
            printer = window.ARK_PRINTERS && window.ARK_PRINTERS.oilSticker;
        } else if (doc === 'repair_order_pdf') {
            printer = window.ARK_PRINTERS && window.ARK_PRINTERS.default;
        }
        if (!printer || String(printer).trim() === '') {
            continue;
        }
        batchRaw = row.dataset.batchId ? String(row.dataset.batchId) : '';
        opts = {
            skipLock: true,
            document: doc,
            printSource: 'reprint_failed'
        };
        if (batchRaw.indexOf('batch_') === 0) {
            opts.batchId = batchRaw;
        }
        arkEnqueuePrintItem(arkMakeQueueItem(printer, url, opts));
        queued++;
    }

    if (queued === 0) {
        if (window.toastr && typeof window.toastr.info === 'function') {
            window.toastr.info('No failed print jobs to reprint');
        }
        return;
    }
    if (window.toastr && typeof window.toastr.info === 'function') {
        window.toastr.info('Reprinting ' + queued + ' failed job(s)…');
    }
}

window.arkReprintFailed = arkReprintFailed;

function arkFlushPrintQueueWaiters() {
    var waiters = arkPrintQueueDrainWaiters;
    arkPrintQueueDrainWaiters = [];
    for (var wi = 0; wi < waiters.length; wi++) {
        try {
            waiters[wi]();
        } catch (e) {}
    }
}

function arkWaitPrintQueueIdle() {
    return new Promise(function(resolve) {
        if (!window.ARK_PRINT_QUEUE_RUNNING && window.ARK_PRINT_QUEUE.length === 0) {
            resolve();
            return;
        }
        arkPrintQueueDrainWaiters.push(resolve);
    });
}

async function arkRunPrintQueue() {
    if (window.ARK_PRINT_QUEUE_RUNNING) {
        return;
    }
    window.ARK_PRINT_QUEUE_RUNNING = true;
    window.ARK_PRINT_QUEUE_STOP = false;

    while (window.ARK_PRINT_QUEUE.length > 0 && !window.ARK_PRINT_QUEUE_STOP) {
        var job = window.ARK_PRINT_QUEUE[0];
        if (!job || typeof job.run !== 'function') {
            window.ARK_PRINT_QUEUE.shift();
            arkRenderPrintQueue();
            continue;
        }
        job.status = 'printing';
        arkRenderPrintQueue();
        try {
            await job.run();
            job.status = 'success';
            arkBumpPrintStat('succeeded');
        } catch (err) {
            var code = arkClassifyError(err);
            if (code === 'DUPLICATE') {
                job.status = 'success';
                arkBumpPrintStat('succeeded');
            } else if (code === 'QZ_OFFLINE' || code === 'QZ_DISCONNECTED' || code === 'PRINT_LOCK') {
                window.ARK_PRINT_QUEUE_LAST_HARD_ERROR = err;
                window.ARK_PRINT_QUEUE_STOP = true;
                window.ARK_PRINT_QUEUE = [];
                arkBumpPrintStat('failed');
                arkRenderPrintQueue();
                break;
            } else {
                try {
                    await new Promise(function(r) {
                        setTimeout(r, 500);
                    });
                    arkBumpPrintStat('retried');
                    await job.run();
                    job.status = 'success';
                    arkBumpPrintStat('succeeded');
                } catch (retryErr) {
                    console.error('Queue job failed after retry', retryErr);
                    job.status = 'failed';
                    arkBumpPrintStat('failed');
                }
            }
        }
        arkRenderPrintQueue();
        await new Promise(function(r) {
            setTimeout(r, 800);
        });
        if (window.ARK_PRINT_QUEUE.length > 0 && window.ARK_PRINT_QUEUE[0] === job) {
            window.ARK_PRINT_QUEUE.shift();
        }
        arkRenderPrintQueue();
        await new Promise(function(r) {
            setTimeout(r, 150);
        });
    }

    window.ARK_PRINT_QUEUE_RUNNING = false;
    window.ARK_PRINT_QUEUE_STOP = false;
    arkFlushPrintQueueWaiters();
    arkRenderPrintQueue();
}

var ARK_FAILED_PRINTS_MAX = 25;

function arkFailedPrintDedupeKey(printerName, url, options) {
    options = options || {};
    return (
        String(printerName || '') +
        '\n' +
        String(url || '') +
        '\n' +
        String(options.document || '') +
        '\n' +
        String(options.batchId || '')
    );
}

function arkRecordFailedPrintForReconnect(printerName, url, options) {
    options = options || {};
    if (!options.document) {
        return;
    }
    var ps = options.printSource || 'manual';
    if (ps === 'auto_retry') {
        return;
    }
    window.ARK_FAILED_PRINTS = window.ARK_FAILED_PRINTS || [];
    var key = arkFailedPrintDedupeKey(printerName, url, options);
    var list = window.ARK_FAILED_PRINTS;
    var i;
    for (i = 0; i < list.length; i++) {
        if (list[i]._dk === key) {
            return;
        }
    }
    list.push({
        printer: printerName,
        url: url,
        options: {
            batchId: options.batchId || null,
            document: options.document,
            printSource: ps,
            skipLock: !!options.skipLock
        },
        _dk: key
    });
    while (list.length > ARK_FAILED_PRINTS_MAX) {
        list.shift();
    }
}

function arkRetryFailedPrints() {
    if (window.ARK_RETRYING) {
        return;
    }
    window.ARK_RETRYING = true;
    window.setTimeout(function() {
        window.ARK_RETRYING = false;
    }, 5000);

    window.ARK_FAILED_PRINTS = window.ARK_FAILED_PRINTS || [];
    var jobs = window.ARK_FAILED_PRINTS.slice();
    window.ARK_FAILED_PRINTS = [];
    if (!jobs.length) {
        return;
    }
    if (window.toastr && typeof window.toastr.info === 'function') {
        window.toastr.info('Retrying ' + jobs.length + ' failed print(s) after QZ reconnect…');
    }
    var j;
    for (j = 0; j < jobs.length; j++) {
        (function(job) {
            var base = job.options || {};
            var o = {
                batchId: base.batchId || null,
                document: base.document,
                printSource: 'auto_retry',
                skipLock: true
            };
            arkEnqueuePrintItem(arkMakeQueueItem(job.printer, job.url, o));
        })(jobs[j]);
    }
}

function arkMonitorQZReconnect() {
    if (window.ARK_QZ_RECONNECT_TIMER) {
        return;
    }
    var wasOffline = false;
    window.ARK_QZ_RECONNECT_TIMER = window.setInterval(function() {
        try {
            if (typeof qz === 'undefined') {
                wasOffline = true;
                return;
            }
            if (!qz.websocket.isActive()) {
                wasOffline = true;
                return;
            }
            if (wasOffline) {
                wasOffline = false;
                if (window.ARK_FAILED_PRINTS && window.ARK_FAILED_PRINTS.length > 0) {
                    arkRetryFailedPrints();
                }
                setTimeout(function() {
                    if (typeof arkScheduleKeyTagPreflight === 'function') {
                        arkScheduleKeyTagPreflight();
                    }
                }, 2000);
            }
        } catch (e) {
            wasOffline = true;
        }
    }, 2000);
}

var ARK_PREFLIGHT_COOLDOWN_MS = 10 * 60 * 1000;

function arkPreflightSessionStorageKey(printerName) {
    try {
        return 'ark_preflight_sess_v1_' + String(arkPrinterLearningKey(printerName || '')).replace(/[^a-z0-9_:-]/gi, '_');
    } catch (e) {
        return 'ark_preflight_sess_v1_fallback';
    }
}

/**
 * Background key-tag test print (once per session / cooldown) to learn mono vs red_black before the user prints.
 * Requires QZ already connected; skips if queue is busy, profile already successful, or recent preflight ok.
 */
async function arkPreflightPrinter(printerName) {
    printerName = printerName && String(printerName).trim();
    if (!printerName || !window.ARK_PREFLIGHT_KEY_TAG_URL) {
        return;
    }
    if (window.ARK_PRINT_QUEUE_RUNNING) {
        return;
    }
    if (window.__ARK_PREFLIGHT_IN_FLIGHT) {
        return;
    }
    if (typeof qz === 'undefined' || !qz.websocket.isActive()) {
        return;
    }

    var existing = arkLoadPrinterProfile(printerName);
    if (existing && Number(existing.successCount || 0) >= 1) {
        return;
    }
    var preflightOkAt = existing && Number(existing.preflightOkAt);
    if (preflightOkAt && Date.now() - preflightOkAt < ARK_PREFLIGHT_COOLDOWN_MS) {
        return;
    }
    var sessKey = arkPreflightSessionStorageKey(printerName);
    try {
        if (sessionStorage.getItem(sessKey)) {
            return;
        }
    } catch (e0) {}

    window.__ARK_PREFLIGHT_IN_FLIGHT = true;
    var url = window.ARK_PREFLIGHT_KEY_TAG_URL;
    var markSessionDone = false;

    function finishSession() {
        if (markSessionDone) {
            return;
        }
        markSessionDone = true;
        try {
            sessionStorage.setItem(sessKey, '1');
        } catch (eS) {}
        window.__ARK_PREFLIGHT_IN_FLIGHT = false;
    }

    try {
        await arkPrintPDFCore(printerName, url, {
            document: 'print_test_key_tag',
            printSource: 'preflight',
            preflight: true,
            silent: true,
            skipLock: true,
            retryAttempt: 0
        });
        var media = arkResolveMediaType(printerName);
        var prevOk = existing && existing.successCount != null ? Number(existing.successCount) : 0;
        if (isNaN(prevOk) || prevOk < 0) {
            prevOk = 0;
        }
        arkSavePrinterProfile(printerName, {
            mediaType: media,
            preflightOkAt: Date.now(),
            successCount: Math.max(1, prevOk),
            auto_detected: true,
            failureCount: 0,
            lastErrorCode: null
        });
        finishSession();
    } catch (err) {
        var code = arkClassifyError(err);
        if (code !== 'MEDIA_MISMATCH' && code !== 'DRIVER_MEDIA_MISMATCH') {
            window.__ARK_PREFLIGHT_IN_FLIGHT = false;
            return;
        }
        var current = arkResolveMediaType(printerName);
        var next = current === 'mono' ? 'red_black' : 'mono';
        var exist2 = arkLoadPrinterProfile(printerName) || {};
        arkSavePrinterProfile(printerName, {
            mediaType: next,
            auto_detected: true,
            successCount: 0,
            failureCount: Number(exist2.failureCount || 0) + 1,
            lastErrorCode: code
        });
        await new Promise(function(r) {
            setTimeout(r, 300);
        });
        try {
            await arkPrintPDFCore(printerName, url, {
                document: 'print_test_key_tag',
                printSource: 'preflight',
                preflight: true,
                silent: true,
                skipLock: true,
                retryAttempt: 1
            });
            arkSavePrinterProfile(printerName, {
                mediaType: next,
                preflightOkAt: Date.now(),
                successCount: 1,
                auto_detected: true,
                failureCount: 0,
                lastErrorCode: null
            });
        } catch (retryErr) {
            console.warn('ARK print: preflight did not complete; recovery will run on first user print', retryErr);
        }
        finishSession();
    }
}

function arkScheduleKeyTagPreflight() {
    if (window.ARK_PRINT_QUEUE_RUNNING) {
        return;
    }
    var p = window.ARK_PRINTERS && window.ARK_PRINTERS.keyTag;
    if (!p || String(p).trim() === '') {
        return;
    }
    if (typeof arkPreflightPrinter !== 'function') {
        return;
    }
    arkPreflightPrinter(String(p).trim());
}

/**
 * Invisible key-tag recovery: first QZ attempt may fail on wrong mono/red_black mode;
 * flip learned media, brief delay, one retry; preview tab only if both fail. Non–key-tag jobs use core only.
 */
async function arkPrintWithRecovery(printerName, url, options) {
    options = options || {};
    var isKeyTag = arkIsQlLabelDoc(options.document);
    if (!isKeyTag) {
        return await arkPrintPDFCore(printerName, url, options);
    }
    try {
        return await arkPrintPDFCore(printerName, url, Object.assign({}, options, { silent: true, retryAttempt: 0 }));
    } catch (err) {
        var code = arkClassifyError(err);
        if (code !== 'MEDIA_MISMATCH' && code !== 'DRIVER_MEDIA_MISMATCH') {
            throw err;
        }
        var current = arkResolveMediaType(printerName);
        var next = current === 'mono' ? 'red_black' : 'mono';
        var exist = arkLoadPrinterProfile(printerName) || {};
        console.warn('ARK print: auto-switching media mode (silent retry)', { from: current, to: next, code: code });
        arkSavePrinterProfile(printerName, {
            mediaType: next,
            auto_detected: true,
            failureCount: Number(exist.failureCount || 0) + 1,
            lastErrorCode: code
        });
        arkBumpPrintStat('retried');
        await new Promise(function(r) {
            setTimeout(r, 300);
        });
        try {
            return await arkPrintPDFCore(printerName, url, Object.assign({}, options, { silent: true, retryAttempt: 1 }));
        } catch (retryErr) {
            var rc = arkClassifyError(retryErr);
            if (rc !== 'MEDIA_MISMATCH' && rc !== 'DRIVER_MEDIA_MISMATCH') {
                throw retryErr;
            }
            console.error('ARK print: silent recovery retry failed', retryErr);
            if (url) {
                try {
                    window.open(url, '_blank');
                } catch (eOpen) {}
            }
            if (window.toastr && typeof window.toastr.info === 'function') {
                window.toastr.info({!! $arkPrintJsMsgOpeningPreview !!});
            }
            return undefined;
        }
    }
}

/**
 * Align Brother QL raster dimensions + DPI with a location before printing (matches server PDF mm for that RO).
 */
async function arkEnsureQzLabelContextForLocation(locationId, document) {
    if (!window.ARK_PRINTING_PRINTER_RESOLVE_URL) {
        return;
    }
    var lid = locationId;
    if (lid === undefined || lid === null || lid === '' || isNaN(Number(lid)) || Number(lid) <= 0) {
        return;
    }
    var doc = String(document || '');
    var resolveType = 'key_tag';
    if (doc === 'print_test_key_tag') {
        resolveType = 'print_test_key_tag';
    } else if (doc === 'oil_change_sticker') {
        resolveType = 'oil_change_sticker';
    } else if (doc === 'print_test_oil_change_sticker') {
        resolveType = 'print_test_oil_change_sticker';
    } else if (doc === 'key_tag') {
        resolveType = 'key_tag';
    } else {
        return;
    }
    var qs =
        'type=' + encodeURIComponent(resolveType) + '&location_id=' + encodeURIComponent(String(lid));
    var r = await fetch(window.ARK_PRINTING_PRINTER_RESOLVE_URL + '?' + qs, {
        credentials: 'same-origin',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            Accept: 'application/json'
        }
    });
    if (!r.ok) {
        return;
    }
    var j = await r.json();
    if (j && j.width_mm != null && j.height_mm != null) {
        var page = {
            width_mm: Number(j.width_mm),
            height_mm: Number(j.height_mm)
        };
        if (resolveType === 'key_tag' || resolveType === 'print_test_key_tag') {
            window.ARK_KEY_TAG_QZ_PAGE = page;
        }
        if (resolveType === 'oil_change_sticker' || resolveType === 'print_test_oil_change_sticker') {
            window.ARK_OIL_STICKER_QZ_PAGE = page;
        }
    }
    if (j) {
        var rd = Number(j.dpi);
        if (rd === 203 || rd === 300) {
            window.ARK_KEY_TAG_RASTER_DPI = rd;
        }
        if (j.key_tag_orientation) {
            var ko = String(j.key_tag_orientation).toLowerCase();
            if (ko === 'auto' || ko === 'portrait' || ko === 'landscape') {
                if (resolveType === 'key_tag' || resolveType === 'print_test_key_tag') {
                    window.ARK_KEY_TAG_QZ_ORIENTATION = ko;
                }
                if (resolveType === 'oil_change_sticker' || resolveType === 'print_test_oil_change_sticker') {
                    window.ARK_OIL_STICKER_QZ_ORIENTATION = ko;
                }
            }
        }
    }
}

async function arkEnsureKeyTagClientContextForLocation(locationId) {
    return arkEnsureQzLabelContextForLocation(locationId, 'key_tag');
}

async function arkPrintPDFCore(printerName, url, options) {
    options = options || {};
    if (arkIsQlLabelDoc(options.document) && !options.keyTagContextApplied) {
        var ktLid = options.keyTagLocationId;
        if (ktLid === undefined || ktLid === null || ktLid === '') {
            try {
                var __arkKtUrl = new URL(url, window.location.origin);
                var __arkKtQ = __arkKtUrl.searchParams.get('location_id');
                if (__arkKtQ !== null && __arkKtQ !== '' && !isNaN(Number(__arkKtQ)) && Number(__arkKtQ) > 0) {
                    ktLid = Number(__arkKtQ);
                }
            } catch (__arkKtErr) {}
        }
        if (ktLid !== undefined && ktLid !== null && ktLid !== '' && !isNaN(Number(ktLid)) && Number(ktLid) > 0) {
            try {
                await arkEnsureQzLabelContextForLocation(Number(ktLid), options.document);
            } catch (__arkCtxErr) {}
        }
    }
    var idemKey =
        arkPrintKey(printerName, url) + '#' + String(options.retryAttempt || 0) + (options.preflight ? '#pf' : '');
    if (arkRecentPrints.has(idemKey)) {
        throw new Error('Duplicate print suppressed');
    }
    arkRecentPrints.add(idemKey);
    setTimeout(function() {
        arkRecentPrints.delete(idemKey);
    }, 3000);

    var lockOwner = null;
    if (!options.skipLock) {
        try {
            lockOwner = arkAcquireLock(
                arkComputeLockTtl({
                    count: options.lockCount !== undefined ? options.lockCount : 1,
                    bytes: options.lockBytesEstimate !== undefined ? options.lockBytesEstimate : 0
                })
            );
        } catch (e) {
            if (e.message === 'PRINT_LOCK') {
                arkRecentPrints.delete(idemKey);
            }
            throw e;
        }
    }

    var jobId = null;
    try {
        await ensureQZConnection(2, 300);
        await arkEnsurePrinterExists(printerName);

        jobId = arkMakePrintJobId();
        var printSource = options.printSource || 'manual';
        var idempotencyHeader = String(printerName || '') + '|' + String(url || '') + '|' + jobId;
        var headers = {
            'X-Requested-With': 'XMLHttpRequest',
            'X-Print-Job-Id': jobId,
            'X-Print-Source': printSource,
            'X-Print-Printer': printerName || '',
            'X-Print-Idempotency-Key': idempotencyHeader
        };
        if (options.batchId) {
            headers['X-Print-Batch-Id'] = options.batchId;
        }

        var response = await fetch(url, {
            credentials: 'same-origin',
            headers: headers
        });
        if (!await arkResponseLooksLikePdf(response)) {
            throw new Error('Failed to load PDF (session expired or invalid response)');
        }
        var blob = await response.blob();
        if (blob.size < 800) {
            throw new Error('PDF_INVALID_EMPTY');
        }
        var base64 = await arkBlobToBase64(blob);

        if (typeof qz === 'undefined' || !qz.websocket.isActive()) {
            throw new Error('QZ_DISCONNECTED');
        }

        var qlRasterForced = window.ARK_PRINTING_QL_FORCE_RASTER !== false;
        var isKeyTagDoc = arkIsQlLabelDoc(options.document);
        var isQlPrinter = arkPrinterNameSuggestsBrotherQl(printerName);
        var useQlImage = isKeyTagDoc && isQlPrinter && qlRasterForced;
        if (isKeyTagDoc) {
            arkQzKeyTagLog('job_start', {
                printerName: printerName,
                detectedType: isQlPrinter ? 'QL' : 'STANDARD',
                isMac: arkClientLooksLikeMacOs(),
                document: options.document,
                mode: useQlImage ? 'RASTER' : 'PDF',
                qlRasterForced: qlRasterForced,
                keyTagMm: arkLabelQzPageForDocument(options.document)
            });
        }
        var payloadB64 = base64;
        var payloadIsImage = false;
        if (useQlImage) {
            try {
                window.__ARK_LAST_KEY_TAG_RASTER = null;
            } catch (eClr) {}
            try {
                var pngB64 = await arkRenderKeyTagPdfToPngForQz(blob, options.document);
                if (pngB64 && pngB64.length > 200) {
                    payloadB64 = pngB64;
                    payloadIsImage = true;
                }
            } catch (eQlImg) {
                if (isQlPrinter && arkClientLooksLikeMacOs()) {
                    console.error('ARK print: key tag raster failed (PRINTING_QL_FORCE_RASTER=true); PDF fallback disabled on this path.', eQlImg);
                    throw new Error('KEY_TAG_MAC_QL_RASTER_FAILED');
                }
                if (qlRasterForced && isQlPrinter) {
                    console.error('ARK print: key tag raster failed (PRINTING_QL_FORCE_RASTER=true).', eQlImg);
                    throw new Error('KEY_TAG_QL_RASTER_FAILED');
                }
                console.warn('ARK print: Brother QL image bypass failed, using PDF', eQlImg);
            }
        }
        if (useQlImage && isQlPrinter && !payloadIsImage) {
            if (arkClientLooksLikeMacOs()) {
                throw new Error('KEY_TAG_MAC_QL_RASTER_FAILED');
            }
            if (qlRasterForced) {
                throw new Error('KEY_TAG_QL_RASTER_FAILED');
            }
        }

        if (arkIsQlLabelDoc(options.document)) {
            arkQzKeyTagLog('payload_ready', {
                mode: payloadIsImage ? 'raster' : 'pdf',
                payloadChars: payloadB64 ? String(payloadB64).length : 0
            });
        }

        var config;
        if (arkIsQlLabelDoc(options.document)) {
            config = arkGetQzPrintConfig(printerName, payloadIsImage, options.document);
        } else {
            config = qz.configs.create(printerName);
        }

        var data = payloadIsImage
            ? [{ type: 'pixel', format: 'image', flavor: 'base64', data: payloadB64 }]
            : [{ type: 'pixel', format: 'pdf', flavor: 'base64', data: payloadB64 }];
        if (payloadIsImage && isKeyTagDoc && window.__ARK_LAST_KEY_TAG_RASTER) {
            try {
                var _k = window.__ARK_LAST_KEY_TAG_RASTER;
                console.info('[ArkQzKeyTag] send_to_qz', {
                    finalWidth: _k.finalWidth,
                    finalHeight: _k.finalHeight,
                    dpi: _k.dpi,
                    width_mm: _k.width_mm,
                    height_mm: _k.height_mm,
                    srcWidth: _k.srcWidth,
                    srcHeight: _k.srcHeight
                });
            } catch (eLog) {}
        }
        var ms = payloadIsImage
            ? Math.max(arkTimeoutForBytes(blob.size), 12000)
            : arkTimeoutForBytes(blob.size);
        await arkTimeout(qz.print(config, data), ms);
        if (!options.preflight) {
            arkMarkPrinterCheckOk();
        }
        if (!options.preflight && arkIsQlLabelDoc(options.document)) {
            arkLearnFromSuccess(printerName);
            try {
                window.__ARK_PRINTER_PROFILE = arkLoadPrinterProfile(printerName);
            } catch (eProf) {}
            arkSaveQzProfileOk(printerName);
            setTimeout(function() {
                if (typeof qz !== 'undefined' && qz.websocket.isActive()) {
                    arkGetPrintersCached(0)
                        .then(function(printers) {
                            arkUpdatePrinterStatusBadge(printers);
                        })
                        .catch(function() {});
                }
            }, 150);
        }
        if (window.ARK_PRINTERS && arkNormalizePrinterName(printerName) === arkNormalizePrinterName(window.ARK_PRINTERS.keyTag)) {
            try {
                localStorage.setItem('ark_last_printer_keytag', printerName);
            } catch (e2) {}
        }
        if (options.document && !options.preflight) {
            arkPostPrintClientMetric('print_success', {
                document: options.document,
                print_job_id: jobId,
                print_batch_id: options.batchId || null,
                repair_order_public_id: arkParseRoPublicFromPrintUrl(url),
                print_source: printSource,
                printer_name: printerName
            });
        }
        return jobId;
    } catch (err) {
        var c = arkClassifyError(err);
        var isKeyTagDoc = arkIsQlLabelDoc(options.document);
        var mediaRecoverable =
            c === 'MEDIA_MISMATCH' || c === 'DRIVER_MEDIA_MISMATCH';
        var skipFailMetric =
            !!options.preflight
            || (options.silent
                && isKeyTagDoc
                && mediaRecoverable
                && (options.retryAttempt || 0) === 0);
        if ((c === 'QZ_OFFLINE' || c === 'QZ_DISCONNECTED') && options.document && !options.preflight) {
            arkRecordFailedPrintForReconnect(printerName, url, options);
        }
        if (c === 'PRINTER_MISSING') {
            arkPrinterCache = { list: null, at: 0 };
        }
        if (c !== 'DUPLICATE' && options.document && !skipFailMetric) {
            var clientErr = '';
            try {
                clientErr = err && err.message ? String(err.message) : String(err || '');
            } catch (eMsg) {
                clientErr = 'print_failed';
            }
            arkPostPrintClientMetric('print_failed', {
                document: options.document,
                print_job_id: jobId,
                print_batch_id: options.batchId || null,
                repair_order_public_id: arkParseRoPublicFromPrintUrl(url),
                print_source: options.printSource || 'manual',
                printer_name: printerName,
                client_error: clientErr
            });
        }
        if (options.silent && isKeyTagDoc && mediaRecoverable) {
            try {
                err._arkSilent = true;
            } catch (eS) {}
        }
        throw err;
    } finally {
        if (lockOwner) {
            arkReleaseLock(lockOwner);
        }
    }
}

/**
 * Preferred entry when the caller knows document type but not printer: set options.resolvePrinter=true, options.document, optional options.locationId.
 * Otherwise identical to arkPrintPDF.
 */
async function arkPrintDocument(printerName, url, btn, options) {
    options = options || {};
    var resolved = printerName;
    var keyTagContextApplied = false;
    if (options.resolvePrinter === true && options.document && window.ARK_PRINTING_PRINTER_RESOLVE_URL) {
        try {
            var lid = options.locationId;
            if (lid === undefined || lid === null) {
                lid = window.ARK_PRINT_LOCATION_ID;
            }
            var qs = 'type=' + encodeURIComponent(String(options.document));
            if (lid !== undefined && lid !== null && lid !== '' && !isNaN(Number(lid))) {
                qs += '&location_id=' + encodeURIComponent(String(lid));
            }
            var r = await fetch(window.ARK_PRINTING_PRINTER_RESOLVE_URL + '?' + qs, {
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json'
                }
            });
            if (!r.ok) {
                throw new Error('PRINT_ROUTER_FAILED');
            }
            var j = await r.json();
            if (j && j.printer) {
                resolved = j.printer;
            }
            if (j && arkIsQlLabelDoc(options.document)) {
                var rd = Number(j.dpi);
                if (rd === 203 || rd === 300) {
                    window.ARK_KEY_TAG_RASTER_DPI = rd;
                }
                if (j.width_mm != null && j.height_mm != null) {
                    var pg = {
                        width_mm: Number(j.width_mm),
                        height_mm: Number(j.height_mm)
                    };
                    if (
                        options.document === 'oil_change_sticker' ||
                        options.document === 'print_test_oil_change_sticker'
                    ) {
                        window.ARK_OIL_STICKER_QZ_PAGE = pg;
                    } else {
                        window.ARK_KEY_TAG_QZ_PAGE = pg;
                    }
                    keyTagContextApplied = true;
                }
                if (j.key_tag_orientation) {
                    var ko = String(j.key_tag_orientation).toLowerCase();
                    if (ko === 'auto' || ko === 'portrait' || ko === 'landscape') {
                        if (
                            options.document === 'oil_change_sticker' ||
                            options.document === 'print_test_oil_change_sticker'
                        ) {
                            window.ARK_OIL_STICKER_QZ_ORIENTATION = ko;
                        } else {
                            window.ARK_KEY_TAG_QZ_ORIENTATION = ko;
                        }
                    }
                }
            }
        } catch (eR) {
            console.error('ARK print: printer resolve failed', eR);
            throw new Error('PRINT_ROUTER_FAILED');
        }
    }
    var forwardLid = options.locationId;
    if (forwardLid === undefined || forwardLid === null) {
        forwardLid = window.ARK_PRINT_LOCATION_ID;
    }
    return arkPrintPDF(resolved, url, btn, Object.assign({}, options, {
        keyTagLocationId: forwardLid,
        keyTagContextApplied: keyTagContextApplied
    }));
}

/** Millimeters for diagnostic toast: fixed dot decimal, trim unnecessary trailing .0 (matches label math, not OS locale). */
function arkFormatMmForPrintToast(value) {
    var n = Number(value);
    if (!isFinite(n)) {
        return String(value);
    }
    var r = Math.round(n * 10) / 10;
    if (Math.abs(r - Math.round(r)) < 1e-6) {
        return String(Math.round(r));
    }
    return r.toFixed(1);
}

/**
 * Location → QZ diagnostic: POST prepare (printer, dpi, mm, pdf_url) then print with same raster pipeline as key tags.
 */
async function arkPrintLocationDiagnosticTestLabel(btn, prepareUrl, locationId) {
    if (!prepareUrl || !locationId) {
        throw new Error('BAD_ARGS');
    }
    var tokenMeta = document.querySelector('meta[name="csrf-token"]');
    var token = tokenMeta ? tokenMeta.getAttribute('content') : '';
    if (window.toastr && typeof window.toastr.info === 'function') {
        window.toastr.info({!! \Illuminate\Support\Js::from(__('Printing test label…')) !!});
    }
    var r = await fetch(prepareUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-TOKEN': token
        },
        body: JSON.stringify({ location_id: locationId })
    });
    var j = null;
    try {
        j = await r.json();
    } catch (eJ) {
        j = null;
    }
    if (!r.ok) {
        var msg = {!! \Illuminate\Support\Js::from(__('Could not prepare test label.')) !!};
        if (j) {
            if (j.message) {
                msg = j.message;
            } else if (j.errors && typeof j.errors === 'object') {
                var vals = Object.values(j.errors);
                if (vals.length && Array.isArray(vals[0]) && vals[0][0]) {
                    msg = vals[0][0];
                }
            }
        }
        if (window.toastr && typeof window.toastr.error === 'function') {
            window.toastr.error(msg);
        }
        throw new Error('PREPARE_FAILED');
    }
    if (!j || !j.pdf_url || !j.printer) {
        if (window.toastr && typeof window.toastr.error === 'function') {
            window.toastr.error({!! \Illuminate\Support\Js::from(__('Invalid response from server.')) !!});
        }
        throw new Error('BAD_PAYLOAD');
    }
    window.ARK_KEY_TAG_RASTER_DPI = j.dpi;
    window.ARK_KEY_TAG_QZ_PAGE = { width_mm: Number(j.width_mm), height_mm: Number(j.height_mm) };
    window.ARK_PRINT_LOCATION_ID = locationId;
    try {
        var jobId = await arkPrintPDF(j.printer, j.pdf_url, btn || null, {
            document: 'print_test_key_tag',
            printSource: 'test',
            locationId: locationId,
            showTestDiagnostics: true,
            throwOnError: true,
            skipSuccessToast: true
        });
        if (window.toastr && typeof window.toastr.success === 'function') {
            var _rd = Number(window.ARK_KEY_TAG_RASTER_DPI);
            var _jd = Number(j.dpi);
            var dpiShown;
            if (_rd === 203 || _rd === 300) {
                dpiShown = _rd;
            } else if (_jd === 203 || _jd === 300) {
                dpiShown = _jd;
            } else if (isFinite(_rd) && _rd > 0) {
                dpiShown = Math.round(_rd);
            } else if (isFinite(_jd) && _jd > 0) {
                dpiShown = Math.round(_jd);
            } else {
                dpiShown = 0;
            }
            var _pg = window.ARK_KEY_TAG_QZ_PAGE || {};
            var wNum = Number(_pg.width_mm != null ? _pg.width_mm : j.width_mm);
            var hNum = Number(_pg.height_mm != null ? _pg.height_mm : j.height_mm);
            var _toastTpl = {!! \Illuminate\Support\Js::from(__('✔ Printed using :printer · :dpi DPI · :w × :h mm')) !!};
            var _dpiLabel = dpiShown > 0 ? dpiShown : (isFinite(_jd) && _jd > 0 ? Math.round(_jd) : 0);
            var _toastMsg = String(_toastTpl)
                .replace(':printer', String(j.printer))
                .replace(':dpi', String(_dpiLabel))
                .replace(':w', arkFormatMmForPrintToast(wNum))
                .replace(':h', arkFormatMmForPrintToast(hNum));
            window.toastr.success(_toastMsg);
            if (window.ARK_DEBUG_PRINT && window.toastr && typeof window.toastr.info === 'function') {
                var _dForPx = dpiShown > 0 ? dpiShown : ((isFinite(_jd) && _jd > 0) ? Math.round(_jd) : 203);
                var pxW = Math.max(1, Math.round((isFinite(wNum) ? wNum : 0) * _dForPx / 25.4));
                var pxH = Math.max(1, Math.round((isFinite(hNum) ? hNum : 0) * _dForPx / 25.4));
                window.toastr.info('Raster: ' + pxW + ' × ' + pxH + ' px', '', { timeOut: 8000 });
            }
        }
        return jobId;
    } catch (ePrint) {
        throw ePrint;
    }
}
window.arkPrintLocationDiagnosticTestLabel = arkPrintLocationDiagnosticTestLabel;

async function arkPrintPDF(printerName, url, btn, options) {
    options = options || {};
    if (btn) {
        if (btn.dataset.printing === '1') {
            return undefined;
        }
        btn.dataset.printing = '1';
        btn.classList.add('loading');
        btn.setAttribute('aria-busy', 'true');
    }
    try {
        var jobId = await arkPrintWithRecovery(
            printerName,
            url,
            Object.assign({}, options, {
                batchId: options.batchId || null,
                document: options.document || null,
                printSource: options.printSource || 'manual'
            })
        );
        if (!options.skipSuccessToast && jobId) {
            arkNotifyPrintSuccess('Sent to printer', jobId);
        }
        if (options.showTestDiagnostics) {
            await arkShowPrintTestDiagnostics(printerName);
        }
        return jobId;
    } catch (err) {
        console.error(err);
        var ec = arkClassifyError(err);
        if ((ec === 'QZ_OFFLINE' || ec === 'QZ_DISCONNECTED') && url) {
            window.open(url, '_blank');
            if (window.toastr && typeof window.toastr.info === 'function') {
                window.toastr.info('Opened PDF in a new tab (QZ Tray unavailable)');
            }
            return undefined;
        }
        arkNotifyPrintError(err);
        if (options.throwOnError) {
            throw err;
        }
        return undefined;
    } finally {
        if (btn) {
            btn.classList.remove('loading');
            btn.removeAttribute('aria-busy');
            btn.dataset.printing = '';
        }
    }
}

window.arkPrintDocument = arkPrintDocument;

async function arkPrintAll(keyTagUrl, roUrl, btn) {
    if (btn) {
        if (btn.dataset.printing === '1') {
            return;
        }
        btn.dataset.printing = '1';
        btn.classList.add('loading');
        btn.setAttribute('aria-busy', 'true');
    }
    var lockOwner = null;
    try {
        window.ARK_PRINT_QUEUE_LAST_HARD_ERROR = null;
        window.ARK_PRINT_QUEUE_LAST_FAIL_URL = null;
        lockOwner = arkAcquireLock(arkComputeLockTtl({ count: 2, bytes: 0 }));
        var batchId = arkMakeBatchId();
        if (window.toastr && typeof window.toastr.info === 'function') {
            window.toastr.info('Printing 2 items (key tag + RO, queued)…');
        }
        arkEnqueuePrintItem(
            arkMakeQueueItem(window.ARK_PRINTERS.keyTag, keyTagUrl, {
                batchId: batchId,
                skipLock: true,
                document: 'key_tag',
                printSource: 'print_all'
            }, function(p) {
                return p.catch(function(err) {
                    var c = arkClassifyError(err);
                    if (c === 'QZ_OFFLINE' || c === 'QZ_DISCONNECTED') {
                        window.ARK_PRINT_QUEUE_LAST_FAIL_URL = keyTagUrl;
                    }
                    throw err;
                });
            })
        );
        arkEnqueuePrintItem(
            arkMakeQueueItem(window.ARK_PRINTERS.default, roUrl, {
                batchId: batchId,
                skipLock: true,
                document: 'repair_order_pdf',
                printSource: 'print_all'
            }, function(p) {
                return p.catch(function(err) {
                    var c = arkClassifyError(err);
                    if (c === 'QZ_OFFLINE' || c === 'QZ_DISCONNECTED') {
                        window.ARK_PRINT_QUEUE_LAST_FAIL_URL = roUrl;
                    }
                    throw err;
                });
            })
        );
        await arkWaitPrintQueueIdle();
        var hard = window.ARK_PRINT_QUEUE_LAST_HARD_ERROR;
        if (hard) {
            var pec = arkClassifyError(hard);
            if (pec === 'QZ_OFFLINE' || pec === 'QZ_DISCONNECTED') {
                if (keyTagUrl) {
                    window.open(keyTagUrl, '_blank');
                }
                if (roUrl) {
                    window.setTimeout(function() {
                        window.open(roUrl, '_blank');
                    }, 400);
                }
                if (window.toastr && typeof window.toastr.info === 'function') {
                    window.toastr.info('Opened PDFs in new tabs (QZ Tray unavailable)');
                }
            } else {
                arkNotifyPrintError(hard);
            }
        } else {
            arkNotifyPrintSuccess('Sent key tag and RO to printer', null);
        }
    } catch (err) {
        console.error(err);
        arkNotifyPrintError(err);
    } finally {
        if (lockOwner) {
            arkReleaseLock(lockOwner);
        }
        if (btn) {
            btn.classList.remove('loading');
            btn.removeAttribute('aria-busy');
            btn.dataset.printing = '';
        }
    }
}

async function arkPrintKeyTagsBatch(urls, btn, batchOptions) {
    batchOptions = batchOptions || {};
    if (!urls || !urls.length) {
        return;
    }
    if (typeof window.ARK_PRINTERS === 'undefined' || !window.ARK_PRINTERS.keyTag || String(window.ARK_PRINTERS.keyTag).trim() === '') {
        if (window.toastr && typeof window.toastr.warning === 'function') {
            window.toastr.warning('Key tag printer is not configured');
        }
        return;
    }
    if (btn) {
        btn.disabled = true;
    }
    var lockOwner = null;
    var ok = 0;
    try {
        window.ARK_PRINT_QUEUE_LAST_HARD_ERROR = null;
        window.ARK_PRINT_QUEUE_LAST_FAIL_URL = null;
        var lockTtl = arkComputeLockTtl({ count: urls.length, bytes: 0 });
        lockOwner = arkAcquireLock(lockTtl);
        var sec = Math.ceil(lockTtl / 1000);
        if (window.toastr && typeof window.toastr.info === 'function') {
            window.toastr.info(
                'Printing ' +
                    urls.length +
                    ' key tag' +
                    (urls.length === 1 ? '' : 's') +
                    ' (queued)… (~' +
                    sec +
                    's)'
            );
        }
        var batchId =
            batchOptions.batchId && String(batchOptions.batchId).trim() !== ''
                ? String(batchOptions.batchId).trim()
                : arkMakeBatchId();
        var n = urls.length;
        var i;
        for (i = 0; i < n; i++) {
            (function(url, idx) {
                arkEnqueuePrintItem(
                    arkMakeQueueItem(window.ARK_PRINTERS.keyTag, url, {
                        batchId: batchId,
                        skipLock: true,
                        document: 'key_tag',
                        printSource: 'batch'
                    }, function(p) {
                        return p
                            .then(function() {
                                ok++;
                                if (
                                    idx > 0 &&
                                    (idx + 1) % 5 === 0 &&
                                    window.toastr &&
                                    typeof window.toastr.info === 'function'
                                ) {
                                    window.toastr.info('Printed ' + (idx + 1) + '/' + n + ' key tags…');
                                }
                            })
                            .catch(function(err) {
                                var c = arkClassifyError(err);
                                if (c === 'QZ_OFFLINE' || c === 'QZ_DISCONNECTED') {
                                    window.ARK_PRINT_QUEUE_LAST_FAIL_URL = url;
                                }
                                throw err;
                            });
                    })
                );
            })(urls[i], i);
        }
        await arkWaitPrintQueueIdle();
        var hard = window.ARK_PRINT_QUEUE_LAST_HARD_ERROR;
        var stoppedHard = !!hard;
        var pec = hard ? arkClassifyError(hard) : '';
        if (stoppedHard && (pec === 'QZ_OFFLINE' || pec === 'QZ_DISCONNECTED')) {
            if (window.toastr && typeof window.toastr.error === 'function') {
                window.toastr.error(
                    ok > 0
                        ? 'Batch stopped: printer connection lost after ' +
                              ok +
                              ' item' +
                              (ok === 1 ? '' : 's')
                        : 'Batch stopped: printer connection lost before any item printed'
                );
            }
            var failUrl = window.ARK_PRINT_QUEUE_LAST_FAIL_URL;
            if (failUrl) {
                window.open(failUrl, '_blank');
            }
            if (window.toastr && typeof window.toastr.info === 'function') {
                window.toastr.info('Opened last PDF in a new tab. Connect QZ Tray to resume batch printing.');
            }
        } else if (stoppedHard && pec === 'PRINT_LOCK') {
            if (window.toastr && typeof window.toastr.warning === 'function') {
                window.toastr.warning('Print queue stopped (another print is in progress).');
            }
        }
        arkLastPrintSuccessToastAt = 0;
        var fail = n - ok;
        if (!stoppedHard) {
            if (ok === 0 && fail === 0) {
                // nothing
            } else if (fail > 0 && ok > 0) {
                if (window.toastr && typeof window.toastr.warning === 'function') {
                    window.toastr.warning('Printed ' + ok + ', failed or skipped ' + fail);
                }
            } else if (fail > 0 && ok === 0) {
                if (window.toastr && typeof window.toastr.error === 'function') {
                    window.toastr.error('Batch print did not complete (' + fail + ' issue(s))');
                }
            } else {
                var doneMsg =
                    'All print jobs completed — ' +
                    ok +
                    ' key tag' +
                    (ok === 1 ? '' : 's') +
                    ' sent to printer';
                if (window.toastr && typeof window.toastr.success === 'function') {
                    window.toastr.success(doneMsg);
                }
            }
        }
    } catch (err) {
        console.error(err);
        arkNotifyPrintError(err);
    } finally {
        if (lockOwner) {
            arkReleaseLock(lockOwner);
        }
        if (btn) {
            btn.disabled = false;
        }
    }
}

window.arkPrintKeyTagsBatch = arkPrintKeyTagsBatch;

async function arkPrintPartsLabelsBatch(urls, btn, batchOptions) {
    batchOptions = batchOptions || {};
    if (!urls || !urls.length) {
        return;
    }
    if (typeof window.ARK_PRINTERS === 'undefined' || !window.ARK_PRINTERS.keyTag || String(window.ARK_PRINTERS.keyTag).trim() === '') {
        if (window.toastr && typeof window.toastr.warning === 'function') {
            window.toastr.warning('Label printer is not configured');
        }
        return;
    }
    if (btn) {
        if (btn.dataset.printing === '1') {
            return;
        }
        btn.dataset.printing = '1';
        btn.disabled = true;
    }
    var lockOwner = null;
    var ok = 0;
    try {
        window.ARK_PRINT_QUEUE_LAST_HARD_ERROR = null;
        window.ARK_PRINT_QUEUE_LAST_FAIL_URL = null;
        var lockTtl = arkComputeLockTtl({ count: urls.length, bytes: 0 });
        lockOwner = arkAcquireLock(lockTtl);
        var sec = Math.ceil(lockTtl / 1000);
        if (window.toastr && typeof window.toastr.info === 'function') {
            window.toastr.info(
                'Printing ' +
                    urls.length +
                    ' parts label' +
                    (urls.length === 1 ? '' : 's') +
                    ' (queued)… (~' +
                    sec +
                    's)'
            );
        }
        var batchId =
            batchOptions.batchId && String(batchOptions.batchId).trim() !== ''
                ? String(batchOptions.batchId).trim()
                : arkMakeBatchId();
        var n = urls.length;
        var i;
        for (i = 0; i < n; i++) {
            (function(url, idx) {
                arkEnqueuePrintItem(
                    arkMakeQueueItem(window.ARK_PRINTERS.keyTag, url, {
                        batchId: batchId,
                        skipLock: true,
                        document: 'parts_label',
                        printSource: batchOptions.printSource || 'parts_labels_batch'
                    }, function(p) {
                        return p
                            .then(function() {
                                ok++;
                                if (
                                    idx > 0 &&
                                    (idx + 1) % 5 === 0 &&
                                    window.toastr &&
                                    typeof window.toastr.info === 'function'
                                ) {
                                    window.toastr.info('Printed ' + (idx + 1) + '/' + n + ' parts labels…');
                                }
                            })
                            .catch(function(err) {
                                var c = arkClassifyError(err);
                                if (c === 'QZ_OFFLINE' || c === 'QZ_DISCONNECTED') {
                                    window.ARK_PRINT_QUEUE_LAST_FAIL_URL = url;
                                }
                                throw err;
                            });
                    })
                );
            })(urls[i], i);
        }
        await arkWaitPrintQueueIdle();
        var hard = window.ARK_PRINT_QUEUE_LAST_HARD_ERROR;
        var stoppedHard = !!hard;
        var pec = hard ? arkClassifyError(hard) : '';
        if (stoppedHard && (pec === 'QZ_OFFLINE' || pec === 'QZ_DISCONNECTED')) {
            if (window.toastr && typeof window.toastr.error === 'function') {
                window.toastr.error(
                    ok > 0
                        ? 'Batch stopped: printer connection lost after ' +
                              ok +
                              ' label' +
                              (ok === 1 ? '' : 's')
                        : 'Batch stopped: printer connection lost before any label printed'
                );
            }
        } else if (stoppedHard && pec === 'PRINT_LOCK') {
            if (window.toastr && typeof window.toastr.warning === 'function') {
                window.toastr.warning('Print queue stopped (another print is in progress).');
            }
        }
        arkLastPrintSuccessToastAt = 0;
        var fail = n - ok;
        if (!stoppedHard) {
            if (fail > 0 && ok > 0) {
                if (window.toastr && typeof window.toastr.warning === 'function') {
                    window.toastr.warning('Printed ' + ok + ', failed or skipped ' + fail);
                }
            } else if (fail > 0 && ok === 0) {
                if (window.toastr && typeof window.toastr.error === 'function') {
                    window.toastr.error('Parts label batch did not complete (' + fail + ' issue(s))');
                }
            } else if (ok > 0) {
                var doneMsg =
                    'All print jobs completed — ' +
                    ok +
                    ' parts label' +
                    (ok === 1 ? '' : 's') +
                    ' sent to printer';
                if (window.toastr && typeof window.toastr.success === 'function') {
                    window.toastr.success(doneMsg);
                }
            }
        }
    } catch (err) {
        console.error(err);
        arkNotifyPrintError(err);
    } finally {
        if (lockOwner) {
            arkReleaseLock(lockOwner);
        }
        if (btn) {
            btn.disabled = false;
            btn.dataset.printing = '';
        }
    }
}

window.arkPrintPartsLabelsBatch = arkPrintPartsLabelsBatch;

document.addEventListener('DOMContentLoaded', function() {
    if (typeof arkMonitorQZReconnect === 'function') {
        arkMonitorQZReconnect();
    }
    if (typeof arkApplyQzUntilVerifiedVisibility === 'function') {
        arkApplyQzUntilVerifiedVisibility();
    }
    setTimeout(function() {
        if (typeof arkRenderPrinterBanner === 'function') {
            arkRenderPrinterBanner();
        }
        if (typeof arkApplyQzUntilVerifiedVisibility === 'function') {
            arkApplyQzUntilVerifiedVisibility();
        }
    }, 300);
    setTimeout(function() {
        if (typeof arkScheduleKeyTagPreflight === 'function') {
            arkScheduleKeyTagPreflight();
        }
    }, 1500);
});
</script>
<style>
    /** QZ not connected after initial check — optional styling for admins (see arkInitQZPrinterHealthHint / ensureQZConnection). */
    body.ark-print-offline {
        box-shadow: inset 0 3px 0 0 rgba(255, 193, 7, 0.9);
    }
    .ark-print-queue-panel {
        position: fixed;
        bottom: 20px;
        right: 20px;
        width: 320px;
        max-height: 400px;
        overflow-y: auto;
        background: #fff;
        border: 1px solid #ddd;
        border-radius: 8px;
        z-index: 9999;
        padding: 10px;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    body.dark-mode .ark-print-queue-panel,
    body.arksms-dark .ark-print-queue-panel {
        background: #1e293b;
        border-color: #334155;
        color: #e2e8f0;
    }
</style>
