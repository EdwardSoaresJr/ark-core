/**
 * Real Chromium interaction covering dirty/autosave races for PR #293.
 * Run: node --test tests/JavaScript/ark-workspace-dirty-browser.test.mjs
 */
import assert from 'node:assert/strict';
import { createServer } from 'node:http';
import { readFileSync } from 'node:fs';
import { dirname, extname, join } from 'node:path';
import { createRequire } from 'node:module';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '../..');
const require = createRequire(join(root, 'package.json'));
const puppeteer = (() => {
    try {
        return require('puppeteer');
    } catch {
        // Worktree checkouts often omit node_modules; reuse sibling Herd Core install.
        return createRequire(join(root, '../ark-core/package.json'))('puppeteer');
    }
})();

const MIME = {
    '.html': 'text/html; charset=utf-8',
    '.js': 'text/javascript; charset=utf-8',
};

const harnessHtml = `<!doctype html>
<html><body>
<form id="f">
  <textarea id="concern" name="concern"></textarea>
</form>
<script type="module">
  import { plainControlChanged } from '/resources/js/ark-form-unsaved.js';
  import {
    adoptRenderedBaselines,
    adoptSnapshotAsBaseline,
    captureFormBaselineSnapshot,
  } from '/resources/js/ark-worksheet-draft.js';
  import { dirtyAfterSaveAttempt } from '/resources/js/ark-worksheet-autosave.js';

  const field = document.getElementById('concern');
  const form = document.getElementById('f');
  const touched = new WeakSet();
  window.__ARK_RACE__ = {
    plainControlChanged: () => plainControlChanged(field),
    dirtyAfterSaveAttempt,
    type(value) {
      field.value = value;
      field.dispatchEvent(new Event('input', { bubbles: true }));
      touched.add(field);
    },
    hydrate(value) {
      field.value = value;
    },
    adoptBoot() {
      adoptRenderedBaselines(document.body, { skipFields: touched });
    },
    snapshot() {
      window.__SNAP__ = captureFormBaselineSnapshot(form);
    },
    settleOkFromSnap() {
      adoptSnapshotAsBaseline(form, window.__SNAP__);
    },
    value() { return field.value; },
    defaultValue() { return field.defaultValue; },
  };
</script>
</body></html>`;

async function withBrowser(run) {
    const server = createServer((req, res) => {
        try {
            if (req.url === '/' || req.url?.startsWith('/?')) {
                res.writeHead(200, { 'content-type': MIME['.html'] });
                res.end(harnessHtml);

                return;
            }

            const rel = decodeURIComponent(req.url.split('?')[0]);
            const filePath = join(root, rel.replace(/^\//, ''));
            if (!filePath.startsWith(root)) {
                res.writeHead(403);
                res.end('forbidden');

                return;
            }

            const body = readFileSync(filePath);
            res.writeHead(200, { 'content-type': MIME[extname(filePath)] || 'application/octet-stream' });
            res.end(body);
        } catch {
            res.writeHead(404);
            res.end('missing');
        }
    });

    await new Promise((resolve) => server.listen(0, '127.0.0.1', resolve));
    const { port } = server.address();
    const browser = await puppeteer.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox'],
    });

    try {
        const page = await browser.newPage();
        page.on('pageerror', (err) => {
            throw err;
        });
        await page.goto(`http://127.0.0.1:${port}/`, { waitUntil: 'networkidle0' });
        await page.waitForFunction(() => window.__ARK_RACE__);
        await run(page);
    } finally {
        await browser.close();
        await new Promise((resolve) => server.close(resolve));
    }
}

test('browser A: early edit before deferred adopt stays dirty', async () => {
    await withBrowser(async (page) => {
        await page.evaluate(() => {
            window.__ARK_RACE__.hydrate('server');
            window.__ARK_RACE__.type('early edit');
            window.__ARK_RACE__.adoptBoot();
        });

        const state = await page.evaluate(() => ({
            dirty: window.__ARK_RACE__.plainControlChanged(),
            value: window.__ARK_RACE__.value(),
            def: window.__ARK_RACE__.defaultValue(),
        }));

        assert.equal(state.value, 'early edit');
        assert.equal(state.def, '');
        assert.equal(state.dirty, true);
    });
});

test('browser B: save A then edit B — success of A leaves B dirty', async () => {
    await withBrowser(async (page) => {
        await page.evaluate(() => {
            window.__ARK_RACE__.hydrate('prior');
            window.__ARK_RACE__.adoptBoot();
            window.__ARK_RACE__.type('A');
            window.__ARK_RACE__.snapshot();
            window.__ARK_RACE__.type('B');
            window.__ARK_RACE__.settleOkFromSnap();
        });

        const state = await page.evaluate(() => ({
            dirty: window.__ARK_RACE__.plainControlChanged(),
            value: window.__ARK_RACE__.value(),
            def: window.__ARK_RACE__.defaultValue(),
            failedKeepsDirty: window.__ARK_RACE__.dirtyAfterSaveAttempt({
                ok: false,
                hadUnsavedChanges: true,
                formDiffers: true,
            }),
        }));

        assert.equal(state.value, 'B');
        assert.equal(state.def, 'A');
        assert.equal(state.dirty, true);
        assert.equal(state.failedKeepsDirty, true);
    });
});

test('browser D/E: failed write keeps draft; untouched hydrate is clean', async () => {
    await withBrowser(async (page) => {
        const clean = await page.evaluate(() => {
            window.__ARK_RACE__.hydrate('loaded concern');
            window.__ARK_RACE__.adoptBoot();

            return {
                dirty: window.__ARK_RACE__.plainControlChanged(),
                def: window.__ARK_RACE__.defaultValue(),
            };
        });

        assert.equal(clean.dirty, false);
        assert.equal(clean.def, 'loaded concern');

        const failed = await page.evaluate(() => {
            window.__ARK_RACE__.type('unsaved');

            return {
                dirty: window.__ARK_RACE__.plainControlChanged(),
                keep: window.__ARK_RACE__.dirtyAfterSaveAttempt({
                    ok: false,
                    hadUnsavedChanges: true,
                    formDiffers: true,
                }),
            };
        });

        assert.equal(failed.dirty, true);
        assert.equal(failed.keep, true);
    });
});
