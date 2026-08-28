# Architecture Pivot — Dashboard → Workbench + Runtime

**Date:** 2026-06-26  
**Status:** Accepted (Agent 2 review)

## What changed

| Before | After |
|--------|-------|
| Engineering Dashboard | **Engineering Workbench** |
| Flutter reads repo directly | **Flutter = views only** |
| Daemon deferred to v0.2 | **Forge Runtime = v0.1** |
| Daemon as optimization | **Runtime as workstation capability authority** |

## Why

The runtime is not premature infrastructure. It is the **stable capability API** for the engineering workstation.

- Flutter must not know how Git works on Windows vs macOS
- Cursor, ChatGPT, and future agents become runtime **clients**
- Dashboard is one projection; workbench is the product

## Two authorities

| Authority | Owns |
|-----------|------|
| Git + `docs/engineering/` | Engineering truth |
| Forge Runtime | Local machine capabilities |

Runtime derives engineering state. It never stores milestone / PR / review truth.

## Supersedes

Flutter-only v0.1 approach documented 2026-06-26 earlier same day. Complexity still earns existence for AI/MCP — not for the runtime capability boundary.
