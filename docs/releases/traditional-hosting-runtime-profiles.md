# Traditional hosting — runtime profiles and release ZIP

Runtime profiles describe **infrastructure availability**. They are not commercial entitlements and do not silently disable essential shop workflows.

| Profile | Typical host | Cache / session | Queue | PDF | Finalize |
| --- | --- | --- | --- | --- | --- |
| `full` | Docker Compose / production VPS | Redis | Horizon | Chromium | Detached CLI |
| `standard` | VPS with SSH + Composer + Node | file/database or Redis | database + cron or Redis | Chromium or native | Detached when allowed |
| `constrained` | Shared hosting from release ZIP | file/database | sync or cron-bounded `queue:work` | **native mPDF required** | In-request when `exec` blocked |

Essential workflows (repair orders, estimates/invoices, portal authorization, inspections, statements, native email, PDF generation, auth/password recovery, and appointment scheduling notifications) must remain available. Infrastructure limits are reported in `/setup` requirements — not used to hide features.

## PDF

- Prefer Chromium when Node + Chrome are present.
- Fall back to mPDF + `native-document` template when Chromium is absent.
- Soft-fail / “check Chromium” is not acceptable as the final shared-host path for estimate/invoice PDFs.

## Release ZIP

`scripts/build-release-zip.sh` (CI: `.github/workflows/release-zip.yml`) builds from a **clean committed git archive** (dirty trees refused):

- Locked `composer install --no-dev`
- Compiled `public/build` from a throwaway build host
- `RELEASE.json`, `.ark-source-commit`, `MANIFEST.sha256`, `COMPOSER_LICENSES.json`
- Secret/sensitive-path scan; no `.env`, keys, uploads, backups, or `node_modules`
- Archive extract self-test before publishing checksums

Document root must be `public/`. Browser setup remains `/setup`. End users of a compatible shared host must not need Docker, SSH, root, Composer, or Node.

See also: `constrained-hosting-cron.md`, `PUBLIC_INSTALL_ACCEPTANCE_CHECKLIST.md`.
