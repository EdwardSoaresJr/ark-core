# Active PR

**Track:** Reporting-release paperwork  
**Status:** Frozen  
**Surface:** Docs only

Platform `325dc92` and Demo `a42b5c64` are live on the box. Do not repeat those deploys. Systems still shows Demo release verification pending; a heartbeat does not close that.

Record: [DEMO_REPORTING_RELEASE.md](DEMO_REPORTING_RELEASE.md) · [`ops/releases/distribution.yaml`](../../ops/releases/distribution.yaml)

## Next (separately gated)

- Reconcile LNP’s stale host record against live `149.28.249.13`
- Establish LNP file-volume backup and rollback
- Capture authenticated Attention and RO Builder traces before the poller
- Trusted Compose observation for Systems `deploy_verified`

Fleet automation stays disabled. This paperwork does not authorize Platform, Demo, LNP, poller, or Laravel 13.32 deploys.
