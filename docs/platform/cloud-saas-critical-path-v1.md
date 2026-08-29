# Cloud SaaS Critical Path

**Status:** Binding — build only this until v1 finish line  
**Funnel experience:** [cloud-funnel-v1.md](cloud-funnel-v1.md) (stable — do not redesign)

## Finish line (v1)

A shop owner discovers ARK, signs up, gets a workspace, creates their first repair order, and decides to keep paying — **without talking to Edward**.

Everything else is 1.1+.

## Execute — nothing else

| Mile | Build | Days | Not yet |
| --- | --- | --- | --- |
| **M1 ✅** | Real **Accounts** — `users`, email verification, password hash, login, forgot password. Replace session-only account fields. Funnel draft JSON on user for resume. | shipped | Funnel UI unchanged; no Shop |
| **M2 ✅** | Real **Shops** — platform `Shop` + `owner_user_id` (User hasOne). Dashboard / resume load Shop. | shipped | Still no provisioning / Tenant / Stripe |
| **M3** | Real **Workspace launch** — Shop enters a real workspace (launch authority). Brief: [cloud-m3-workspace-launch-brief-v1.md](cloud-m3-workspace-launch-brief-v1.md). | brief | Not Coolify / DNS / Stripe / ProvisioningRequest |
| **M4** | **Provisioning** — wire `ProvisioningRequest` into the existing timeline; replace Alpine timers with events. UI unchanged. | after M3 | |
| **M5** | **Stripe** — only after someone can reach a workspace. Trial → workspace → 14 days → subscribe. Never charge before success. | after M3 | |
| **M6** | **Production adoption** — existing shop claims ownership → verify domain/email → attach existing tenant. Demo Auto Repair migration is a later **proof**, not this milestone alone. | after M3 | |

**Current:** **M2 closed.** **M3 brief written — code closed** until authority boundary + production acceptance gate are accepted. Strategy: [multi-tenant-development-strategy-v1.md](multi-tenant-development-strategy-v1.md).

## First five minutes (after login)

```text
Welcome
  ↓
Add Customer
  ↓
Add Vehicle
  ↓
Create Repair Order
  ↓
Invite Employee
```

Mission checklist already on Arrive. Earn the last two boxes by wiring real workspace actions — not by building admin chrome.

## Stop building

Do **not** touch until the critical path works end to end:

- More homepage polish
- Billing dashboards
- Domain management UI
- Cluster management
- Coolify UI
- Admin reports
- Multi-cluster / edge refinements

Nobody buying ARK cares yet.

## Discipline (binding)

```text
Stable experience  →  replaceable implementation
One milestone     →  one authority matured
```

Milestones are scoped by **authority**, not feature volume. Full guardrail: [cloud-funnel-v1.md](cloud-funnel-v1.md) § Principle.

**M3 review (when code opens)** — every proposed change must answer yes / yes:

1. Does this make Workspace Launch more real for an owned `Shop`?
2. Can the user still not tell where the implementation changed?

No to (1) → out of M3. No to (2) → implementation is leaking into experience.

Also: prove with a **brand-new** Cloud shop — never by migrating Demo Auto Repair. See [multi-tenant-development-strategy-v1.md](multi-tenant-development-strategy-v1.md).

Cut ruthlessly. Only build pieces that move **visitor → successful shop owner**.

## Companions

- [NEXT.md](NEXT.md) — host split + pointer here
- [cloud-funnel-v1.md](cloud-funnel-v1.md) — funnel journey (do not rewrite)
- [cloud-m3-workspace-launch-brief-v1.md](cloud-m3-workspace-launch-brief-v1.md) — M3 brief (code closed until accepted)
- [multi-tenant-development-strategy-v1.md](multi-tenant-development-strategy-v1.md) — prove platform; migrate Demo Auto Repair last
- Platform orchestrator — enters at **M4**, not before
