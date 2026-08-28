# ARK Runtime

This directory is the runtime boundary for ARK.

## Laws

- Operational code belongs inside operational domains.
- Runtime infrastructure must remain replaceable.
- Financial calculations must have one authoritative path.
- UI must not own business logic.
- Workflows are primary; CRUD is secondary.
- Prefer direct code over abstraction.
- Prefer deletion over configuration.
- Avoid generic systems unless operationally necessary.
- Realtime is opt-in, not default architecture.
- Build for one repair shop first.
- Every new dependency must solve a real operational problem.

## Structure

- `Runtime`: replaceable app services such as auth, ACL, settings, media, health, and notifications.
- `Operations`: shop workflows and operational domains.
- `Domain`: shared domain concepts that are truly cross-operational.
- `Application`: orchestration for use cases that cross boundaries.
- `Infrastructure`: adapters for external services and framework integration.

Start with one complete operational workflow before adding dashboards, reporting, realtime, AI, plugin systems, or generic workflow engines.

## Operational UI Laws

- Operational speed is more important than visual cleverness.
- Workflow rhythm is more important than dashboard density.
- Every screen must reduce cognitive load.
- Primary workflows should require minimal clicks.
- CRUD screens are secondary to operational workspaces.
- Realtime is optional unless operationally necessary.
- Avoid modal-heavy interaction patterns.
- Prefer direct manipulation over hidden menus.
- Search and navigation speed are critical.
- The system should feel calm under operational pressure.

## Dependency Policy

- Every dependency must solve a real operational problem.
- Prefer Laravel-native solutions first.
- Avoid packages that introduce architectural gravity.
- Avoid frontend framework escalation.
- Avoid generated architecture systems.
- Runtime dependencies should remain minimal and understandable.
- Operational workflows are more important than developer convenience.
- Prefer direct code over meta-frameworks.
- Realtime is opt-in, not foundational.
- If a package increases cognitive load more than operational speed, reject it.
