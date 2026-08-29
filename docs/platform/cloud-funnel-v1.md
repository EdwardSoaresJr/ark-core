# Cloud Funnel v1

**Status:** Experience validated — replace implementations, not the journey  
**Host:** `autorepairkeeper.com`  
**Controller:** `CloudExperienceController` · session key `ark_cloud_trial`

## Principle (guardrail — not guidance)

**Replace implementations, not experiences.** *(Design rule — what to do.)*

**The user journey is the contract; underlying authorities mature incrementally.** *(Architectural rationale — why.)*

**Milestones are defined by the authority they mature, not by the amount of functionality they deliver.**

That last sentence is the anti-“while we're here…” rule. If a proposed change requires maturing **two** authorities, it is probably **two milestones**.

Stricter than a vertical slice:

| | Role |
| --- | --- |
| **Experience** | The slice — continuity the user feels |
| **Authority** | The unit of implementation — one per milestone |

The user experiences continuity while internal truth becomes progressively more real.

| Milestone | Experience contract | Authority matured |
| --- | --- | --- |
| **M1 ✅** | Account journey unchanged | Identity (`User`) |
| **M2 ✅** | Shop journey unchanged | `Shop` |
| **M3** | Launch / Open Workspace unchanged | Workspace Launch ([brief](cloud-m3-workspace-launch-brief-v1.md)) |
| **M4** | Provisioning timeline UI unchanged | `ProvisioningRequest` + execution |
| **M5** | Billing posture unchanged | Subscription / Stripe |
| **M6** | Adoption / claim unchanged | Existing shop claim |

If a future milestone starts **changing the funnel** to accommodate infrastructure, question the **milestone scope** — do not redesign the experience.

## Journey (stable)

```text
Visitor
  ↓
Interest (marketing)
  ↓
Create Shop
  ↓
Workspace (slug)
  ↓
Account
  ↓
Provisioning Experience
  ↓
Arrive (Welcome + mission)
  ↓
Cloud Dashboard
  ↓
Workspace handoff
```

## Replace ladder → SaaS critical path

Experience ladder (funnel): Phase 1 shipped. Implementation backlog is **M1–M6** only:

→ **[cloud-saas-critical-path-v1.md](cloud-saas-critical-path-v1.md)**

| Mile | Replace |
| --- | --- |
| **M1 ✅** | Session account → real Account |
| **M2 ✅** | Intent → real Shop |
| **M3** | `/app` handoff → Workspace Launch (shop workspace) |
| **M4** | Alpine timers → ProvisioningRequest events |
| **M5** | Stripe after workspace success |
| **M6** | Existing shop claim (Demo Auto Repair = #1) |

## Not on the roadmap

- Rewrite the funnel
- Redesign onboarding
- Homepage / billing / domain / cluster polish before M3 works

## Companions

- [cloud-saas-critical-path-v1.md](cloud-saas-critical-path-v1.md) — **execute this**
- [cloud-m3-workspace-launch-brief-v1.md](cloud-m3-workspace-launch-brief-v1.md) — M3 brief
- [multi-tenant-development-strategy-v1.md](multi-tenant-development-strategy-v1.md) — prove platform; migrate Demo Auto Repair last
- [NEXT.md](NEXT.md) — Monday pointer
- [PRODUCT-TRACK.md](PRODUCT-TRACK.md) — sellable track
- Platform orchestrator — **M4**, not before

## Arrive mission (day-one)

After provisioning experience, do **not** lead with Billing / Domains.

Honest copy while provisioning is simulated:

- “Let’s get your shop ready.”
- “Your workspace is waiting.”
- Free trial badge — no ambiguity

Not: “Your shop has been created” / “{{ shop }} is ready” until Phase 3–4 make that true.

```text
Welcome to ARK.
Let’s get {shop} ready.
Your workspace is waiting.

Next:
□ Add your first customer
□ Add your first vehicle
□ Create your first repair order

0 / 3 Complete
```

## Instrumentation (v1)

| Event | When |
| --- | --- |
| `cloud_funnel_homepage_cta` | Start Free Trial click (client) |
| `cloud_funnel_trial_started` | GET `/trial` |
| `cloud_funnel_shop_completed` | POST shop name |
| `cloud_funnel_workspace_completed` | POST slug |
| `cloud_funnel_account_completed` | POST account |
| `cloud_funnel_completed` | Arrive / welcome |
| `cloud_funnel_open_workspace` | Open Workspace |

Server: `Log::info('cloud_funnel', …)` · Client: `gtag` / `dataLayer` when Ads/GA4 configured.

## Not on the roadmap (experience)

- Rewrite the funnel
- Redesign onboarding
- Revisit information architecture

Those are validated at the experience level. Implementation backlog: [cloud-saas-critical-path-v1.md](cloud-saas-critical-path-v1.md).
