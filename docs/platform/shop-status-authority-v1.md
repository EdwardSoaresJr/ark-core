# Shop Status Authority v1

**Status:** Locked — lifecycle before Features / provisioning / billing automation  
**Date:** 2026-07-19  
**Companions:** [shop-authority-v1.md](shop-authority-v1.md) · [domain-contract-v1.md](domain-contract-v1.md) · [shop-registry-implementation-design-v1.md](../deployment/shop-registry-implementation-design-v1.md)

## Principle

The **Shop owns its lifecycle**.

Provisioning, billing, deployment, onboarding, DNS, welcome emails, suspensions, upgrades, and migrations **react to Shop status**. They do not invent parallel lifecycle state. Wizard steps are not status.

```text
Shop.status     = business existence / entitlement to operate
Deployment health = operational projection (healthy / failed / …)
```

Those layers must never be collapsed.

---

## States

```text
Prospect
    │
    ▼
PendingProvision
    │
    ▼
Provisioning
    │
    ▼
Active
    │
    ├─────────────┐
    ▼             ▼
Suspended     Maintenance
    │             │
    └──────┬──────┘
           ▼
         Active

Active
    │
    ▼
PendingDeletion
    │
    ▼
Archived
```

| Status | Meaning |
| --- | --- |
| **Prospect** | Marketing / onboarding pipeline only. No Stancl tenant. No deployment. No DNS. |
| **PendingProvision** | Shop record exists. Slug reserved. Operations Domain reserved. Deployment Profile chosen. Waiting for the provisioning engine. |
| **Provisioning** | Infrastructure is being projected: Stancl, databases, containers, DNS, migrations, first admin. Owned by the **provisioning engine**, not the wizard. |
| **Active** | Shop is operational. Staff may work. |
| **Maintenance** | Temporary operational hold (migration, deploy, restore, infra work). **Not** a business suspension. Returns to Active. |
| **Suspended** | Business decision (billing, fraud, customer request). Infrastructure **may still exist**. Staff/customer use blocked by policy. |
| **PendingDeletion** | Destruction scheduled. Grace period. Restorable to Active (or Suspended per policy). |
| **Archived** | Terminal. No active deployment. Historical Shop record remains. |

---

## Allowed transitions (v1)

| From | To | Notes |
| --- | --- | --- |
| Prospect | PendingProvision | Shop create / slug reserved / profile chosen |
| PendingProvision | Provisioning | Provisioning engine starts |
| Provisioning | Active | Provision succeeded |
| Provisioning | PendingProvision | Provision failed — **retryable**; not a half-created Shop |
| Active | Suspended | Business hold |
| Active | Maintenance | Temporary infra / migration work |
| Active | PendingDeletion | Deletion scheduled |
| Suspended | Active | Reinstate |
| Suspended | PendingDeletion | End of life after hold |
| Maintenance | Active | Work complete |
| PendingDeletion | Active | Restored within grace |
| PendingDeletion | Archived | Grace expired / teardown complete |
| Archived | \* | **Forbidden** — terminal |

**Forbidden:** billing, Stripe, Coolify, or Stancl inventing statuses outside this set. External systems may **request** transitions; Arkify / Shop authority **applies** them.

---

## Status vs Deployment health (locked)

| Layer | Question | Examples |
| --- | --- | --- |
| **Shop.status** | Should this business exist / operate? | Active, Suspended, PendingProvision |
| **Deployment health** | Is infrastructure OK? | Healthy, Failed, Degraded |

### Examples

| Status | Deployment | Meaning |
| --- | --- | --- |
| Active | Failed | Shop should exist; infrastructure is broken → repair deployment, **do not** Suspend |
| Suspended | Healthy | Infrastructure may still run; policy blocks use |
| Provisioning | Partial | Expected while projecting |
| PendingProvision | Failed / None | Last provision attempt failed or never started → **Retry** |

Failure contract:

```text
Provisioning
  → deployment failed
  → Status = PendingProvision
  → Retry
```

Never leave a “half-created tenant” as a business status. Stancl/DB/DNS leftovers are Infrastructure cleanup under PendingProvision or operator tools — not a new Shop status.

---

## Provisioning contract

```text
Create Shop
  → Status = PendingProvision
  → Provision
  → success → Status = Active
  → failure → Status = PendingProvision (retry)
```

While work runs: **Provisioning**.

Welcome email, DNS cutover, first-login enablement, and billing “live” signals fire on transitions into **Active** (and reverse on Suspended / PendingDeletion) — not on wizard completion.

---

## Subscription vs Status

| Layer | Question |
| --- | --- |
| **Shop.status** | Where is this Shop in life? |
| **Subscription** | What does billing say? (trialing, past_due, …) |

Billing may **request** Suspended or influence eligibility toward PendingProvision. It must not set Active without a successful provision path. Subscription plan (Trial / Professional / …) remains orthogonal to Deployment Profile ([shop-authority-v1.md](shop-authority-v1.md)).

---

## Who may change status

| Actor | May |
| --- | --- |
| Provisioning engine | PendingProvision ↔ Provisioning → Active (or back to PendingProvision on failure) |
| Platform operators | Suspended, Maintenance, PendingDeletion, Archived; emergency overrides with audit |
| Billing reconcile | **Request** Suspended / reinstate → Active only when deployment allows and policy says so |
| Onboarding wizard | Create Prospect / drive to PendingProvision only — **never** Active |
| Deployment health monitors | Update Deployment health only — **never** Shop.status |

---

## Explicit non-goals (v1)

- Feature entitlements (next freeze)  
- Deployment Authority / health enum freeze  
- Exact grace-period hours for PendingDeletion  
- Mapping every Stripe event → transition (policy later)  
- Parallel “onboarding_step” as lifecycle authority  

---

## Freeze order (platform)

| # | Authority | Status |
| --- | --- | --- |
| 1 | [Domain Contract v1](domain-contract-v1.md) | ✅ Locked |
| 2 | [Shop Authority v1](shop-authority-v1.md) | ✅ Locked |
| 3 | **Shop Status Authority v1** (this file) | ✅ Locked |
| 4 | Features / Entitlements | Later (optional) |
| 5 | [Deployment Authority v1](deployment-authority-v1.md) | ✅ Locked |
| 5b | [Cluster Authority v1](cluster-authority-v1.md) | ✅ Scaffolding |
| 5c | [Cluster Assignment Authority v1](cluster-assignment-authority-v1.md) | ✅ Scaffolding |
| 5d | [Provisioning Request Authority v1](provisioning-request-authority-v1.md) | ✅ Scaffolding |
| 6 | Provisioning v1 (Shop → Deployment → Assignment → STOP) | ✅ **Frozen** |
| 6a | [Adapter Rule v1](adapter-rule-v1.md) | ✅ **Locked** |
| — | [Architecture Phase 1 complete](architecture-phase-1-complete.md) | ✅ Closed |
| 6b | Provisioning Orchestrator (stub steps) | ✅ Sprint 1 |
| 6c | [Orchestrator Rule v1](orchestrator-rule-v1.md) | ✅ Locked |
| — | [Engineering principles](engineering-principles.md) | Practice (not doctrine) |
| — | **Doctrine freeze** — no new authority without pressure | Active |
| — | [Engineering Phase 1 — Adapters](engineering-phase-1-adapters.md) | **Active** |
| 6d | [Sprint 2 Coolify adapter](sprint-2-coolify-adapter.md) — prove contract | Active (milestone-gated) |
| 7 | Billing | Later |
| 8 | Self-service onboarding | Later |

Lifecycle is **existence**. Features are **entitlements**. Freeze Features only after this.

---

## Relation to shop-registry lifecycle

[shop-registry-implementation-design-v1.md](../deployment/shop-registry-implementation-design-v1.md) §4 is **superseded for product status vocabulary** by this document.

| Registry (older) | Shop Status Authority v1 |
| --- | --- |
| `prospect` | Prospect |
| `trial_eligible` | Fold into Prospect → PendingProvision (eligibility is Subscription / pipeline, not a Shop status) |
| `provisioning` | Provisioning |
| `active` | Active |
| `suspended` | Suspended |
| — | **Maintenance** (new) |
| — | **PendingProvision**, **PendingDeletion** (new) |
| `archived` | Archived |
| `decommissioned` | Infra teardown after Archived — projection / operator action, not a separate Shop status in v1 |

Registry remains useful for control-plane storage sketches; **status names and transition rules above win**.
