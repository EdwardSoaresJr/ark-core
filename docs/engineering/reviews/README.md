# Engineering Reviews

Architecture and scope reviews — **engineering judgment**, not ADRs.

| Artifact | Captures |
|----------|----------|
| [adr/](../adr/) | Frozen decisions (what we chose) |
| [reviews/](.) | Review records (why we approved, what we worried about) |
| [IMPLEMENTATION_LOG.md](../IMPLEMENTATION_LOG.md) | What shipped |

ADRs are immutable once accepted. Reviews are append-only records of human and agent review at a point in time.

## When to Write a Review

- PR scope audit before merge (e.g. PR1 compliance)
- Architecture review before accepting an ADR proposal
- Milestone gate (e.g. VVX350 Connected floor test)

## Naming

```
YYYY-MM-DD-{subject}-review.md
```

Examples:

- `2026-06-26-pr1-scope-review.md`
- `2026-07-15-zero-touch-provisioning-gate-review.md`

## Template

```markdown
# {Subject} Review

**Date:** YYYY-MM-DD  
**Status:** Approved | Approved with concerns | Blocked  
**Reviewer:** {name or role}  
**Related:** PR1 | ADR-0005 | CURRENT_MILESTONE

## Reason

Why this passed or failed review.

## Scope verified

- {checklist}

## Future concerns

- {items to watch — not blockers}

## Doctrine alignment

| File / area | Doctrine |
|-------------|----------|
| | |
```

---

<!-- Append new reviews below this line -->
