---
title: What a plugin system would still need
description: The gaps between Laravel package extension and a stable ARK Core plugin ecosystem.
---

# What a plugin system would still need

<!-- source: app/Ark/Operations/Commands/OperationsCommandRegistry.php -->

Today's extension story is "install a Composer package and use Laravel." A plugin ecosystem that third parties can build on without reading a Core diff every release would need contracts Core does not have yet. None of the following exist. They are requirements, not a roadmap commitment and not a hidden feature.

| Gap | Why it matters |
| --- | --- |
| A discovered plugin manifest | Packages work only if the operator runs Composer. There is no drop-in directory, enable switch, or per-shop plugin list. |
| A compatibility version | Routes and interfaces change with the Core commit. There is no `ark-core` extension API version separate from the application. |
| Stable public events | A handful of events and model hooks exist. Most shop writes do not dispatch a documented event. Listeners cannot see what is not dispatched. |
| View and navigation regions | No widget zones, menu registration, or theme API. Command-palette registration is an internal list built in one class. |
| Wrapped domain actions | Creating a repair order, approving an estimate, and posting a ledger entry are PHP classes. They are not hooks with before/after points. |
| Schema ownership | Packages must not migrate Core tables. There is no extension-column facility. |
| Permission contribution | Capabilities are a PHP enum. A package cannot add a capability without editing `ArkCapability` and the role map. |
| API versioning | `/api/mobile` and the other prefixes are unversioned. A plugin cannot rely on a frozen response schema. |
| Isolation | A package runs in-process with the shop. It can read the database and the filesystem. There is no sandbox. |
| Official package index | Nothing in this repository lists, signs, or reviews third-party packages. |

Until those exist, document your package against a Core commit SHA. Pin that commit. Re-test on upgrade. That is the honest compatibility policy.

If you need one of those gaps filled inside Core, propose it as a Core change with tests. Do not describe the proposal as an existing hook. The [proposed tutorials](/tutorials/proposed) are examples of ideas that are not shippable against today's source.
