import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import test from 'node:test';
import { arkLinkedPartsQuote } from './ark-linked-parts-quote.js';

const require = createRequire(import.meta.url);

function quoteConfig(overrides = {}) {
    return {
        showUrl: '/show',
        refreshUrl: '/refresh',
        candidatesUrl: '/candidates',
        linkUrl: '/link',
        currentUrl: '/current',
        csrf: 'token',
        ...overrides,
    };
}

function savedQuote(overrides = {}) {
    return {
        supplier: "O'Reilly",
        purchase_order: 'R1788',
        item_count: 7,
        status: 'CREATED',
        reference_tail: '2104',
        confirmed: true,
        lines: [
            { part_number: '41-601', quantity: '6.00', description: 'Spark plug' },
        ],
        ...overrides,
    };
}

function panel(current = savedQuote(), history = []) {
    return {
        available: true,
        provider_label: 'PartsTech',
        open_note: 'Opening the catalog does not open this saved quote.',
        current,
        history,
    };
}

test('the compact row shows the linked quote without a catalog id', () => {
    const api = arkLinkedPartsQuote(quoteConfig({ panel: panel() }));

    assert.equal(api.summaryTitle(), "PartsTech · O'Reilly");
    assert.equal(api.summaryDetail(), 'PO R1788 · 7 items · Created · ···2104');
    assert.equal(api.summaryDetail().includes('cart-'), false);
});

test('an empty association stays a single compact summary', () => {
    const api = arkLinkedPartsQuote(quoteConfig({
        panel: panel(null),
    }));

    assert.equal(api.summaryTitle(), 'PartsTech Quote');
    assert.equal(api.summaryDetail(), 'No quote linked');
    assert.equal(api.open, false);
});

test('an action response is not stored as the quote', () => {
    const api = arkLinkedPartsQuote(quoteConfig({
        panel: { ok: true, message: 'Quote refreshed' },
    }));

    assert.equal(api.panel, null);
});

test('a failed refresh keeps the last saved quote', async () => {
    const api = arkLinkedPartsQuote(quoteConfig({ panel: panel() }));
    api.request = async () => ({ ok: false, message: 'PartsTech did not return this linked quote.' });

    await api.refresh();

    assert.equal(api.panel.current.supplier, "O'Reilly");
    assert.equal(api.panel.current.reference_tail, '2104');
    assert.equal(api.cached, true);
    assert.equal(api.notice, 'PartsTech did not return this linked quote.');
    assert.equal(api.panel.message, undefined);
});

test('a successful refresh reloads the saved quote instead of the action message', async () => {
    const api = arkLinkedPartsQuote(quoteConfig({ panel: panel() }));
    const calls = [];
    api.request = async (url) => {
        calls.push(url);

        if (url === '/refresh') {
            return { ok: true, message: 'Quote refreshed' };
        }

        return panel(savedQuote({ item_count: 8 }));
    };

    await api.refresh();

    assert.deepEqual(calls, ['/refresh', '/show']);
    assert.equal(api.notice, 'Quote refreshed');
    assert.equal(api.panel.current.item_count, 8);
    assert.equal(api.panel.ok, undefined);
    assert.equal(api.cached, false);
});

test('choosing a cart saves that association and does not replace the visible quote first', async () => {
    const api = arkLinkedPartsQuote(quoteConfig({ panel: panel() }));
    const calls = [];
    api.request = async (url, method, body) => {
        calls.push({ url, method, body });

        if (url === '/candidates') {
            return {
                ok: true,
                message: '',
                candidates: [{
                    external_id: 'cart-1788-2104',
                    supplier: "O'Reilly",
                    status: 'CREATED',
                    purchase_order: 'R1788',
                    item_count: 7,
                    reference_tail: '2104',
                }],
            };
        }

        if (url === '/link') {
            return { ok: true, message: 'Quote linked' };
        }

        return panel();
    };

    await api.choose();

    assert.equal(api.panel.current.supplier, "O'Reilly");
    assert.equal(api.candidates[0].reference_tail, '2104');
    assert.equal(JSON.stringify(api.panel).includes('cart-1788-2104'), false);

    await api.link(api.candidates[0].external_id);

    assert.equal(calls.at(-2).url, '/link');
    assert.deepEqual(calls.at(-2).body, { external_id: 'cart-1788-2104' });
    assert.equal(calls.at(-1).url, '/show');
    assert.equal(api.notice, 'Quote linked');
    assert.equal(api.choosing, false);
});

test('history can designate a different saved quote', async () => {
    const api = arkLinkedPartsQuote(quoteConfig({
        panel: panel(savedQuote(), [
            { id: '9', supplier: 'Worldpac', status: 'ARCHIVED', purchase_order: 'R100', item_count: 1, reference_tail: '1001', is_current: false },
        ]),
    }));
    api.request = async (url, method, body) => {
        if (url === '/current') {
            assert.deepEqual(body, { link_id: '9' });

            return { ok: true, message: 'Current quote updated' };
        }

        return panel(savedQuote({ supplier: 'Worldpac', purchase_order: 'R100', item_count: 1, status: 'ARCHIVED', reference_tail: '1001' }));
    };

    await api.makeCurrent('9');

    assert.equal(api.panel.current.supplier, 'Worldpac');
    assert.equal(api.summaryDetail(), 'PO R100 · 1 item · Archived · ···1001');
    assert.equal(api.notice, 'Current quote updated');
});

test('manage opens and closes without leaving the repair order', () => {
    const api = arkLinkedPartsQuote(quoteConfig({ panel: panel(null) }));

    api.openManage();
    assert.equal(api.open, true);
    api.close();
    assert.equal(api.open, false);
    assert.equal(api.choosing, false);
});

test('the linked quote component is registered before Alpine starts', () => {
    const app = readFileSync(new URL('./app.js', import.meta.url), 'utf8');
    const blade = readFileSync(new URL('../views/operations/repair-orders/partials/repair-order-linked-quote.blade.php', import.meta.url), 'utf8');
    const registration = app.indexOf("Alpine.data('arkLinkedPartsQuote'");
    const start = app.indexOf('Alpine.start()');

    assert.ok(registration > -1);
    assert.ok(registration < start);
    assert.equal(blade.includes('<script'), false);
    assert.equal(blade.includes('window.arkLinkedPartsQuote'), false);
    assert.equal(app.includes("import { arkLinkedPartsQuote } from './ark-linked-parts-quote';"), true);
});

test('a replaced repair-order fragment does not bind linked-quote clicks until Alpine.data is registered', async (t) => {
    let puppeteer;
    let alpineSource;

    try {
        puppeteer = require('puppeteer');
        alpineSource = readFileSync(require.resolve('alpinejs/dist/cdn.min.js'), 'utf8');
    } catch {
        t.skip('puppeteer and alpinejs are required to exercise the repair-order fragment');

        return;
    }

    const factory = `window.arkLinkedPartsQuote = function () {
        return { clicks: 0, refresh() { this.clicks += 1; } };
    };`;
    const oldMarkup = `<div x-data="arkLinkedPartsQuote({})">
        <button type="button" id="go" @click="refresh()">Refresh quote</button>
        <span id="count" x-text="clicks"></span>
    </div>`;
    const browser = await puppeteer.launch({ headless: true });

    try {
        const broken = await browser.newPage();
        const brokenLogs = [];
        broken.on('console', (msg) => brokenLogs.push(msg.text()));
        await broken.setContent(`<!doctype html><body><div id="host" x-data="{ worksheet: true }"></div><script>${alpineSource}</script></body>`, { waitUntil: 'domcontentloaded' });
        await broken.evaluate((fragment) => {
            const parsed = new DOMParser().parseFromString(`<div id="estimate-lines">${fragment}</div>`, 'text/html');
            const next = parsed.getElementById('estimate-lines');
            document.getElementById('host').appendChild(next);
            window.Alpine.initTree(next);
        }, `<script>${factory}</script>${oldMarkup}`);
        await broken.click('#go');
        const brokenState = await broken.evaluate(() => ({
            defined: typeof window.arkLinkedPartsQuote,
            count: document.getElementById('count')?.textContent ?? '',
        }));

        assert.equal(brokenState.defined, 'undefined');
        assert.equal(brokenState.count, '');
        assert.equal(brokenLogs.some((line) => line.includes('arkLinkedPartsQuote is not defined')), true);
        assert.equal(brokenLogs.some((line) => line.includes('refresh is not defined')), true);
        await broken.close();

        const fixed = await browser.newPage();
        const fixedLogs = [];
        fixed.on('console', (msg) => fixedLogs.push(msg.text()));
        fixed.on('pageerror', (err) => fixedLogs.push(err.message));
        const component = readFileSync(new URL('./ark-linked-parts-quote.js', import.meta.url), 'utf8')
            .replace('export function arkLinkedPartsQuote', 'function arkLinkedPartsQuote');
        await fixed.setContent(`<!doctype html><head><style>[x-cloak]{display:none !important} [data-linked-parts-quote]{display:flex;align-items:center;gap:12px;border:1px solid #e2e8f0;border-radius:8px;padding:8px 12px;width:640px}</style></head><body>
            <div id="host" x-data="{ worksheet: true }"></div>
            <script>${component}</script>
            <script>
                document.addEventListener('alpine:init', () => {
                    Alpine.data('arkLinkedPartsQuote', (config = {}) => arkLinkedPartsQuote(config));
                });
            </script>
            <script>${alpineSource}</script>
        </body>`, { waitUntil: 'domcontentloaded' });
        await fixed.evaluate(() => {
            window.__calls = [];
            window.fetch = async (url, options) => {
                window.__calls.push({ url: String(url), method: options?.method || 'GET', body: options?.body || null });
                const path = String(url);
                const json = path.endsWith('/show')
                    ? { available: true, provider_label: 'PartsTech', open_note: '', current: { supplier: "O'Reilly", purchase_order: 'R1788', item_count: 7, status: 'CREATED', reference_tail: '2104', confirmed: true, lines: [] }, history: [] }
                    : path.endsWith('/refresh')
                        ? { ok: false, message: 'PartsTech did not return this linked quote.' }
                        : path.endsWith('/candidates')
                            ? { ok: true, message: '', candidates: [] }
                            : { ok: false, message: 'unused' };

                return { json: async () => json };
            };
        });
        const markup = `<div data-linked-parts-quote x-data='arkLinkedPartsQuote({"showUrl":"/show","refreshUrl":"/refresh","candidatesUrl":"/candidates","linkUrl":"/link","currentUrl":"/current","csrf":"token","panel":{"available":true,"provider_label":"PartsTech","open_note":"","current":null,"history":[]}})'>
            <p id="title" x-text="summaryTitle()"></p>
            <p id="detail" x-text="summaryDetail()"></p>
            <button type="button" id="manage" @click="openManage($event)">Manage</button>
            <div id="dialog" x-show="open">
                <p id="notice" x-text="notice"></p>
                <button type="button" id="refresh" :disabled="loading || !panel?.current" @click="refresh()">Refresh quote</button>
                <button type="button" id="change" @click="choose()">Change linked cart</button>
                <button type="button" id="close" @click="close()">Close</button>
            </div>
        </div>`;
        await fixed.evaluate((fragment) => {
            const parsed = new DOMParser().parseFromString(`<div id="estimate-lines">${fragment}</div>`, 'text/html');
            const next = parsed.getElementById('estimate-lines');
            document.getElementById('host').appendChild(next);
            window.Alpine.initTree(next);
        }, markup);
        const row = await fixed.$eval('[data-linked-parts-quote]', (el) => ({
            height: el.getBoundingClientRect().height,
            title: document.getElementById('title').textContent,
            detail: document.getElementById('detail').textContent,
        }));
        assert.equal(row.title, 'PartsTech Quote');
        assert.equal(row.detail, 'No quote linked');
        assert.ok(row.height < 80, `collapsed row is ${row.height}px`);
        assert.equal(await fixed.$eval('#refresh', (el) => el.disabled), true);

        await fixed.click('#manage');
        const opened = await fixed.evaluate(() => {
            const data = document.querySelector('[data-linked-parts-quote]')._x_dataStack?.[0];
            const dialog = document.getElementById('dialog');

            return {
                open: data?.open ?? null,
                display: dialog ? getComputedStyle(dialog).display : null,
                hasOpenManage: typeof data?.openManage,
            };
        });
        assert.equal(opened.open, true, JSON.stringify({ opened, fixedLogs }));
        await fixed.evaluate(() => {
            const data = document.querySelector('[data-linked-parts-quote]')._x_dataStack[0];
            data.panel.current = {
                supplier: "O'Reilly",
                purchase_order: 'R1788',
                item_count: 7,
                status: 'CREATED',
                reference_tail: '2104',
                confirmed: true,
                lines: [],
            };
        });
        await fixed.waitForFunction(() => document.getElementById('refresh').disabled === false);
        await fixed.click('#refresh');
        await fixed.click('#change');
        await fixed.click('#close');
        const after = await fixed.evaluate(() => ({
            notice: document.getElementById('notice').textContent,
            supplier: document.querySelector('[data-linked-parts-quote]')._x_dataStack[0].panel.current.supplier,
            open: document.querySelector('[data-linked-parts-quote]')._x_dataStack[0].open,
            calls: window.__calls.map((call) => call.url),
        }));

        assert.equal(after.notice, 'PartsTech did not return this linked quote.');
        assert.equal(after.supplier, "O'Reilly");
        assert.equal(after.open, false);
        assert.deepEqual(after.calls, ['/refresh', '/candidates']);
        await fixed.close();
    } finally {
        await browser.close();
    }
});
