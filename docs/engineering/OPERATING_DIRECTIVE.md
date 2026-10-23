# Operating Directive: Finish, Stabilize, Ship

Effective 23 October 2026. Applies to ARK Core, ARK Shop, ARK Landlord, and every agent working on them.

Deliver a stable, useful, commercially viable ARK SaaS platform to real automotive repair shops. Quality and operational safety remain essential, but documentation, architecture and infrastructure exist to support delivery, not to postpone it.

This directive does not replace [PRODUCT_BOUNDARY.md](../PRODUCT_BOUNDARY.md), [RELEASE_PROTOCOL.md](RELEASE_PROTOCOL.md), the [production-protection rule](../../.cursor/rules/production-protection.mdc), or the architectural rules in [STANDARDS.md](STANDARDS.md). Where they apply, they win.

## Rules

1. Complete the requested scope before introducing additional work.
2. Prefer small, independently testable changes.
3. Reuse existing architecture and patterns. No speculative abstractions, unnecessary refactors, or premature optimization.
4. Distinguish release blockers from nonblocking improvements.
5. Record unrelated discoveries as follow-up issues. Expand the current task only for an immediate security, data-integrity, or production risk.
6. Use existing documentation and test evidence. Do not repeat an investigation unless something material changed.
7. Separate genuine regressions from known baseline failures, with evidence. Never hide a failure or weaken a security control to ship faster.
8. Preserve production functionality and tenant isolation.
9. Finish what you start: implementation, tests, PR, CI. Delivery stops at the authorized boundary.
10. Stop when the acceptance criteria are met.

## Default workflow for an approved task

1. Inspect the existing implementation.
2. State concise acceptance criteria.
3. Implement the smallest correct solution.
4. Add or update tests.
5. Compare failures with the baseline.
6. Open a focused PR and run CI.
7. Report the actual outcome and any genuine blocker.

Do not ask again for ordinary, reversible development actions the approved task already covers.

## Authorization

| Activity | Default |
| --- | --- |
| Research and code inspection | Proceed |
| Small feature or bug fix within approved scope | Implement |
| Automated tests | Run |
| Open a PR | Proceed |
| Merge a passing PR | Repository policy and explicit project authorization |
| Production deployment, production database migration, destructive operation, new infrastructure expense | Explicit approval, per the release protocol and production-protection rule |

## Priorities

1. Protect what works: LugsNPlugs production, customer data, tenant isolation, security, recovery.
2. Finish work already started, and release approved improvements without enlarging them.
3. Shop workflow improvements and small fixes; tenant onboarding and account management; Landlord cockpit usability; ARK-managed communications and usage metering; subscription billing and payments.
4. Bounded infrastructure workstreams, such as the Landlord MySQL migration, proceed by their approved milestones. Only real dependencies, security issues, or data-integrity risks interrupt product work.

## Definition of done

A task is done when its acceptance criteria are met, relevant tests pass or known baseline failures are documented, and the change has reached its authorized delivery boundary. Do not keep improving a finished task without a new, justified requirement.

---

ARK Shop and ARK Landlord link to this file on the `v2/native-messaging` branch. Update those links if this file moves or that branch is renamed or merged into `main`.
