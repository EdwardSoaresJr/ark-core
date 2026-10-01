# Operational Documents

Estimate documents are **living** until the repair order is **Closed**.

- Open repair orders: snapshot and PDF refresh from current RO state and current shop settings whenever staff view the estimate HTML or PDF.
- Closed repair orders: snapshot and PDF freeze at closeout; later RO or settings changes do not alter the document.

PDF rendering uses `EstimateDocument::snapshot_json` when it is a full v2 snapshot. Legacy import rows keep a minimal `legacy_import` snapshot for invoice totals; PDF/HTML rendering builds a live snapshot from the repair order for display without overwriting that stored import payload.

## PDF Runtime

PDFs are rendered through `spatie/browsershot`, backed by Puppeteer/Chromium.

Runtime requirements:

- PHP can execute Node through Browsershot.
- Node dependencies are installed with `npm install`.
- Puppeteer has access to a Chromium binary in the runtime environment.
- Browsershot's Puppeteer runtime may require `npx puppeteer browsers install chrome` (macOS/Herd) or `npx puppeteer browsers install chrome-headless-shell` (Linux production).
- **Local Herd:** PHP-FPM does not inherit your shell `PATH`, so `node`/`npm` are not found unless you set `PDF_NODE_BINARY` / `PDF_NPM_BINARY` or leave them empty and let `PdfRuntimePaths` auto-discover Herd's NVM Node plus Puppeteer Chrome under `~/.cache/puppeteer`.
- Production: `infra/releasepanel/install-pdf-runtime.sh` (post-deploy) installs Chrome into `production/shared/puppeteer-cache` and sets `PDF_CHROME_PATH` / `PDF_NO_SANDBOX=true`. Ubuntu also needs Chromium system libraries (`libasound`, `libnss3`, etc.); the install script installs them when run as root.
- Storage disk `local` is writable because generated PDFs are stored under `storage/app/private/estimate-documents`.

Do not use `window.print()`, DomPDF, mPDF, or live repair order views as PDF authority.
