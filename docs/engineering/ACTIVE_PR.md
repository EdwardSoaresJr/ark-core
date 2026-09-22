# Active PR

**Track:** LNP reporting readiness  
**Status:** Demo independently verified. LNP prepared, not deployed.  
**Surface:** Production inventory, backup, and UI baseline only

| Stream | Status |
| --- | --- |
| Platform observation | Live `0d5f640` / `sha256:23d8e3d4…` · rollback `sha256:c5a48ae…` |
| Demo reporting | Verified by host inspect + `/up`, not heartbeat |
| LNP reporting | Awaiting approval |
| Fleet automation | Disabled |
| Git consolidation | Separate from production · commit/push/integrate only · no deploys |

Three performance streams stay separate. Do not mix them in one image or one recreate.

| Stream | Status | Doc |
| --- | --- | --- |
| Reporting-only LNP | Prepared · not authorized | [LNP_REPORTING_READINESS.md](LNP_REPORTING_READINESS.md) |
| Call-queue poller | Frozen (`cf169e76`) | — |
| RO request-time | Brief only · not started | [RO_REQUEST_TIME.md](RO_REQUEST_TIME.md) |

Repeat [LNP_PERFORMANCE_BASELINE.md](LNP_PERFORMANCE_BASELINE.md) after each approved stream.

## Closed

- Platform observe image `0d5f640` / `sha256:23d8e3d4ade3bc5c2877edacd6dd19a8cf4fe2ddaab3468976b6329ddb8e8acd` — do not republish or recreate Platform for Demo
- Isolated `e2910fd` image `sha256:630a7601…` — sidecar tests only; not deployed; do not use it to redo Demo observation
- Demo reporting-only Core `a42b5c64` / `sha256:f4abe244…` — do not republish or recreate. Systems **Verified**.
- Checklist: [DEMO_REPORTING_RELEASE.md](DEMO_REPORTING_RELEASE.md)

## Out of scope

- Recreate LNP `core`
- Coolify Deploy / Fleet automation / DNS
- Laravel 13.32
- Call-queue poller (`cf169e76` remains frozen)
- RO schema/query/Blade optimization
- Isolated `e2910fd` / `sha256:630a7601…` Platform image
- Another Demo or Platform image rebuild

Git consolidation may preserve, commit, push, and merge code. It does not authorize Demo, Platform, or LNP deploys.
