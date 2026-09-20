# Multi-Tenant Development Strategy v1

**Status:** Frozen until proven  
**Companions:** [cloud-saas-critical-path-v1.md](cloud-saas-critical-path-v1.md) · [cloud-funnel-v1.md](cloud-funnel-v1.md) · [NEXT.md](NEXT.md)  
**Doctrine: ** doctrine `ark-multi-tenant-development.mdc`

## Decision

We are **not** building a generic SaaS platform first.

We are building the multi-tenant platform **around** the real Demo Auto Repair production shop until the platform proves itself.

The proving-ground shop remains the reference tenant.

Only after the complete multi-tenant architecture is stable and production-proven will Demo Auto Repair itself migrate onto the new tenant architecture.

This is intentional.

## Guiding principle

**Prove the platform before migrating the business that depends on it.**

The production shop should never become the experiment.

It should become the first successful migration.

## Current → target

```text
Today:

Demo Auto Repair Production
        │
        ▼
Monolithic production application


Target:

                 Platform
                     │
     ┌───────────────┼───────────────┐
     ▼               ▼               ▼
 Demo Auto Repair      Shop A         Shop B
    Tenant        Tenant         Tenant
```

Do **not** migrate Demo Auto Repair until the platform is ready.

## Development strategy

Until migration day, build:

- Cloud onboarding
- Shop authority
- Workspace launch
- Tenant
- Provisioning
- Billing
- Deployment
- Multi-tenancy

Assume these create **new shops**.

Not replacing Demo Auto Repair.

Every milestone should create another production-capable tenant while Demo Auto Repair continues operating normally.

## Validation rule

Every new capability should be proven by creating a **new tenant**.

Never by risking the production shop.

Questions should always be:

- Can a brand-new shop onboard?
- Can a brand-new workspace launch?
- Can a brand-new tenant deploy?
- Can a brand-new customer succeed?

Only after those answers are consistently **yes** should Demo Auto Repair migrate.

## Migration philosophy

**Migration is not a milestone.**

**Migration is a proof.**

When it happens, it should feel boring.

The goal is that moving Demo Auto Repair is simply changing where it runs — not redesigning how it operates.

## Engineering guardrail

Do **not** add temporary code paths that exist only because Demo Auto Repair is still on the legacy application.

Instead:

1. Build the correct platform.
2. Keep Demo Auto Repair on the existing production system.
3. Migrate only when the new platform is demonstrably complete.

Avoid long-lived compatibility layers unless they are required for the eventual migration itself.

## Success criteria (platform proven)

The platform is considered proven when:

- New shops can onboard end-to-end
- Workspace launches automatically
- Tenant provisioning is reliable
- Billing works
- Deployment works
- Upgrades work
- Monitoring works
- Backups work
- Existing tenant operations are stable

Only then should Demo Auto Repair become another tenant on the platform.

## Architectural principle

**Demo Auto Repair is the proving-ground shop, not the prototype.**

We validate the platform against real operational needs every day, but we do not move the business onto the new infrastructure until the infrastructure has earned that trust.

```text
Build the platform first.
Migrate the business second.
Do not reverse that order.
```
