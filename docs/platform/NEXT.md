# Next

**Company product:** `autorepairkeeper.com` is ARK Cloud.

**v1 finish line:** Shop owner discovers ARK → signs up → workspace → first repair order → keeps paying — without talking to Edward.

## Build Monday morning

**Only:** [cloud-saas-critical-path-v1.md](cloud-saas-critical-path-v1.md)

| Done | **M1 — Real Accounts** · **M2 — Real Shops** |
| --- | --- |
| Brief | **[M3 — Workspace Launch](cloud-m3-workspace-launch-brief-v1.md)** — written; **code closed** until accepted |
| Next | M4 Provisioning → M5 Stripe → M6 Adoption |

M2 closed (authority + production acceptance). M3 one question: *Can a real Shop enter a real Workspace?*

Strategy (frozen): [multi-tenant-development-strategy-v1.md](multi-tenant-development-strategy-v1.md) — prove platform with new shops; migrate Demo Auto Repair last.

## Host split

| Host | Role |
| --- | --- |
| `autorepairkeeper.com` | Cloud Funnel + marketing |
| `app.autorepairkeeper.com` | Auth + Cloud dashboard (M1+) |
| `{shop}.arksms.com` | Shop workspace (M3+) |
| `platform.autorepairkeeper.com` | Arkify (internal) |

## Stop

Homepage polish · billing dashboards · domain/cluster/Coolify UI · admin reports — until visitor→paying owner works.

Do not start M3 code until the brief’s authority boundary and production acceptance gate are accepted.

## Local

- Product: [https://autorepairkeeper.com](https://autorepairkeeper.com)
- Without `COMPANY_DOMAIN`: [https://app.demo-auto.test/cloud](https://app.demo-auto.test/cloud)
