# Cutover runbook (NOT executed)

## Artifacts

- Core: `5dda85ece5c4a2e328e7a37a1344dada5c4b5d62`
- SaaS: `a718b2c42f19dfba9a8961c2811d4d07e167bd0b` (`release/pricing-core-5dda85ec`)

## Pre-flight

1. Confirm live prod still on SaaS `7b5f180` / Core `affb186` (or re-discover).
2. Take/verify nightly + confirm binlog ship healthy.
3. Pin Alpha to SaaS RC; smoke tax 8.200, RO, estimate PDF preview, portal (synthetic), no live sends.
4. Owner message: **Deploy the approved release** naming both SHAs and `lugsnplugs.arksms.com`.

## Deploy

1. Run Stancl `deploy.sh` for `a718b2c` on `ark-saas-prod-1` only when authorized.
2. Confirm tenant migrations for ready tenants; do not invent Molly DB.
3. Post-deploy: Lugs settings tax rate, one living RO totals, `/up`, Horizon active.

## Rollback honesty

- Revert SaaS/Core pin if code defect and schema still compatible.
- Do **not** restore an older encrypted backup over shops that took new work after cutover without an explicit recovery plan.
