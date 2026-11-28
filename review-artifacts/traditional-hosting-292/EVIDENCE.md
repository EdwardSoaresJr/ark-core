# Core #292 — Traditional Hosting Final Acceptance Evidence

**Gate:** Owner review. Merge / tag / SaaS pin **withheld**.

## Candidate

| Field | Value |
| --- | --- |
| Tip SHA | `6f42b9dc160347718909279b53608c63d73c0852` |
| PR | https://github.com/EdwardSoaresJr/ark-core/pull/292 |
| Label | `1.0.0-rc.1` (not tagged) |

## 1. Release archive

Built with `scripts/build-release-zip.sh` from a clean committed tree at the tip SHA.

| Artifact | Path |
| --- | --- |
| ZIP | `dist/ark-core-1.0.0-rc.1-6f42b9dc.zip` (local; also CI `ark-core-release-zip` artifact) |
| Checksum | `dist/ark-core-1.0.0-rc.1-6f42b9dc.zip.sha256` |

Verified:

- Clean `git archive` provenance (dirty tree refused)
- `composer install --no-dev`
- Compiled `public/build/manifest.json`
- `RELEASE.json` + `MANIFEST.sha256` + `.ark-source-commit`
- No `.env`, secrets, customer data, or dumps
- Extracted tree boots; `scripts/verify-release-zip-install.sh` performs migrate + native PDF bytes + interrupted-install recovery under `disable_functions=exec,proc_open,...`

PR workflow: `.github/workflows/release-zip.yml` (pull_request path filters + `workflow_dispatch`).

## 2. PDF acceptance

Paired Chromium + native mPDF artifacts under `pdfs/`; PNG page previews under `previews/`.

| Document | Native | Chromium |
| --- | --- | --- |
| Estimate | `estimate-native.pdf` | `estimate-chromium.pdf` |
| Invoice | `invoice-native.pdf` | `invoice-chromium.pdf` |
| Inspection | `inspection-native.pdf` | `inspection-chromium.pdf` |
| Statement | `statement-native.pdf` | `statement-chromium.pdf` |

Fixtures: realistic RO closeout + Unicode shop name (`Fidelity Auto — 東京`), authorization language, financial lines. Pixel parity **not** required.

Mailpit: `InstallationMailMailpitAcceptanceTest` — estimate, invoice, inspection, document, statement attachments accepted on SMTP `:2587` / API `:18025`.

## 3. Constrained runtime

Isolated extract of the Release ZIP:

- No Docker / Node / Chromium / Redis / Horizon / Reverb / Composer at runtime
- PHP with `disable_functions` including `exec` + `proc_open`
- Database queue + `ark:queue-work-bounded` + `schedule:list`
- Native PDF generation succeeded (`native_pdf_bytes>0`, `detached_finalize=n`)
- `InstallationState::recoverInterruptedProgress` after forced `IN_PROGRESS`

## 4. Regression

Watch PR #292 Regression / Tests / Release ZIP / Secret scan on tip `6f42b9dc`.

Docker / VPS / hosted SaaS code paths remain in-tree; no SaaS pin change.

## 5. Owner package

- Instructions: `docs/releases/OWNER_ACCEPTANCE_TRADITIONAL_HOSTING.md`
- Checklist: `docs/releases/PUBLIC_INSTALL_ACCEPTANCE_CHECKLIST.md`
- Cron: `docs/releases/constrained-hosting-cron.md`

**Do not** publish GitHub Release, create tag, merge #292, or advance SaaS Core pin without separate authorization.
