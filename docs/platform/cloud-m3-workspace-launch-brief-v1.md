# Cloud Funnel M3 — Workspace Launch Brief

**Status:** Brief written — **code closed** until this brief is accepted  
**Milestone:** M3 only  
**Experience contract:** [cloud-funnel-v1.md](cloud-funnel-v1.md)  
**Critical path:** [cloud-saas-critical-path-v1.md](cloud-saas-critical-path-v1.md)  
**Platform strategy:** [multi-tenant-development-strategy-v1.md](multi-tenant-development-strategy-v1.md)

M1 and M2 are closed (authority + production acceptance).

Do not reopen Account or Shop.

---

## One question

**Can a real Shop enter a real Workspace?**

Not:

- Provision the tenant
- Deploy the tenant
- Configure DNS
- Wire Coolify
- Charge Stripe

Those are later authorities (M4+).

---

## Authority to mature

```text
User
  │
  owns
  │
Shop (Prospect)          ← M2 — done
  │
  enters via
  │
Workspace Launch         ← M3 — this milestone
```

**One authority:** Workspace Launch.

The authenticated owner of a platform `Shop` can leave the Cloud dashboard and enter a workspace that belongs to **that Shop** — not Demo Auto Repair production by accident.

### What “real” means in M3

| Real | Fake (allowed underneath) |
| --- | --- |
| Durable launch record tied to `Shop` | Full Coolify / Docker deploy |
| Shop-specific destination (URL / host identity) | DNS automation |
| Session / identity continues into that workspace | Complete tenant isolation hard-cutover |
| Open Workspace experience unchanged | Provisioning timeline events (M4) |

Implementation may still be thin. The milestone replaces the **placeholder handoff** with a **launch authority**.

---

## Experience contract (frozen)

Today the user clicks **Open Workspace** and leaves Cloud.

After M3:

- Same button
- Same dashboard copy posture
- Same sense of “I’m going into my shop”
- **No** funnel redesign
- **No** SaaS wizard rewrite

If the user notices implementation changing the journey, M3 failed.

**Litmus (same as M1/M2):**

1. Does this make Workspace Launch real?
2. Can the user tell implementation changed?

Expected: **Yes** / **No**.

---

## Current placeholder (replace this)

`CloudExperienceController::openWorkspace` redirects authenticated users to Demo Auto Repair ops `/app` (or app host login).

That is a fake handoff. It does not prove Shop → Workspace.

```text
Old:
  Dashboard → Open Workspace → /app  (Demo Auto Repair)

New:
  Dashboard → Open Workspace → Workspace Launch → shop workspace
```

Page sequence and Cloud UI stay identical. Destination authority changes.

---

## Non-goals (hard constraints)

Do **not** implement in M3:

| Out | Belongs |
| --- | --- |
| `ProvisioningRequest` execution / orchestrator | M4 |
| Coolify / Docker deploy | M4+ |
| DNS automation | M4+ |
| Stripe / billing | M5 |
| Existing-shop claim / Demo Auto Repair migration | M6 / migration proof |
| Multi-tenant middleware explosion “for later” | only what launch requires |
| Funnel / dashboard redesign | never for M3 |

If you need one of those to “finish” M3, stop — the milestone is scoped wrong or the fake underneath isn’t allowed enough.

---

## Relationship to Tenant

M3 may introduce a **minimal tenant identity** only if Workspace Launch cannot be real without it (e.g. shop-scoped URL or login target).

That is **not** permission to build:

- Full tenancy product
- Tenant switching UI
- Memberships / orgs / invitations
- Cluster assignment
- Provisioning pipeline

Tenant as deployable runtime is matured later. M3 owns **launch**, not **provision**.

Prefer the smallest durable record that answers: *this Shop has a workspace destination and can enter it.*

---

## Production strategy (binding)

Per [multi-tenant-development-strategy-v1.md](multi-tenant-development-strategy-v1.md):

- Prove launch by onboarding a **brand-new** shop on Cloud.
- Do **not** migrate or risk Demo Auto Repair production as the experiment.
- Demo Auto Repair stays on the monolithic production app until the platform is proven.

---

## Deliverables (when code opens)

Keep the PR focused:

- Workspace Launch authority (model / migration / relationship to `Shop` as needed)
- Wire **Open Workspace** to launch (minimal controller change)
- Auto-login / session continuity into the shop workspace (as real as M3 allows without M4)
- Tests: launch is real; no ProvisioningRequest / Stripe / DNS / Coolify
- Minimal Blade changes (ideally none)
- Production acceptance walkthrough (below)

No UI redesign.

---

## Production acceptance gate

Authority green is not enough. M3 closes only when both pass.

### A — Authority

A reviewer answers **YES**:

- Does a real `Shop` have a durable Workspace Launch path?
- Does Open Workspace use that path — not a hardcoded Demo Auto Repair `/app` shortcut?

### B — Experience + production

Walk as a **brand-new** Cloud user on `autorepairkeeper.com`:

1. Complete funnel through dashboard (M1/M2 path)
2. Click **Open Workspace**
3. Land in a workspace that is **for that Shop**
4. Identity continues (no “who am I?” break)
5. Return / login later still reaches the same Shop’s launch path
6. Demo Auto Repair ops (`app.demo-auto.test`) remains unaffected for the production shop

Visual regressions or “why did this page change?” → fail.

---

## Explicit stop

Do not write M3 implementation code until:

1. This brief is accepted
2. Authority boundary above is unchanged
3. Production acceptance gate is agreed

When code starts: one authority, one PR scope, stop.

---

## After M3

| Next | Authority |
| --- | --- |
| **M4** | ProvisioningRequest + execution behind the existing timeline |
| **M5** | Stripe after workspace success |
| **M6** | Existing shop claim — still not “migrate Demo Auto Repair” as an experiment |

Migration of Demo Auto Repair remains a **proof**, not a milestone — see strategy doc.
