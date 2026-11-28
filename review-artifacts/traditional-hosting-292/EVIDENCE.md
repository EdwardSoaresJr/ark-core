# Core #292 — Traditional Hosting Final Acceptance Evidence

**Gate:** Owner review. Merge / tag / SaaS pin **withheld**.

## Candidate

| Field | Value |
| --- | --- |
| PR tip SHA | _(see git; may include docs-only commits after packaging)_ |
| **Release artifact source SHA** | `6fdb98c95824daa8c21fba935aaf14d0a9132acd` |
| PR | https://github.com/EdwardSoaresJr/ark-core/pull/292 |
| Label | `1.0.0-rc.1` (not tagged) |
| **Minimum PHP** | **8.4.1** (`composer.json` `^8.4.1`, `/setup` fails closed below that, `RELEASE.json` `"php":"^8.4.1"`) |

## 1. Release archive

| Artifact | Path |
| --- | --- |
| ZIP | `dist/ark-core-1.0.0-rc.1-6fdb98c9.zip` |
| SHA-256 | `4647ccd68f146bc8f8b216a568ee1a4463a38187e23d72b185519a95b3ad2e29` |

Verified: clean git provenance, `--no-dev` Composer, compiled frontend, `RELEASE.json` / `MANIFEST.sha256` / `.ark-source-commit`, AGPL license, no `.env`/secrets/customer data, install without Composer/Node/Chromium at runtime.

Cancelled Regression run `46121049231` was superseded by successful full pest on the same packaging tip; current tip re-ran Tests + Release ZIP + Regression.

## 2. PDF acceptance

See `PDF_VISUAL_INDEX.md`. Paired Chromium/native PDFs + PNG previews in `pdfs/` / `previews/`.

Mailpit: estimate attachment send accepted (`casey@cleanroom.test` — Estimate from Cleanroom Auto). Prior suite: `mailpit-ACCEPTANCE.json`.

## 3. Constrained + clean-room

- `scripts/verify-release-zip-install.sh` — migrate + native PDF + interrupted recovery under `disable_functions`
- `scripts/cleanroom-zip-install-rehearsal.sh` — `/setup` → shop/admin → installed; login at `/app/login`; storage probe; schedule + bounded queue; SQL+storage backup/restore
- Clean-room workflows on installed ZIP tree: estimate/invoice PDF, inspection PDF, payment to $0 balance, statement PDF, SMTP via Mailpit

## 4. Product boundary

No SaaS registration, entitlements, hosted credentials, or Core pin changes in this train.

## Owner acceptance items

- [ ] Independent ZIP install using only public docs
- [ ] PHP 8.4.1+ host confirmed
- [ ] Visual PDF index reviewed (`PDF_VISUAL_INDEX.md`)
- [ ] Mailpit / SMTP acceptable
- [ ] Cron notes understood
- [ ] Authorize merge of #292 separately
- [ ] Authorize tag / GitHub Release / SaaS pin separately
