# Operator Adoption (Track E)

**Definition:** Another shop can run a day without tribal knowledge.

**Exit criterion:** A repair shop owner with no prior ARK experience can operate a normal day without asking the product team how to use the software.

## Floor observation (not a scripted “pass”)

Engineering stops here. Do **not** tell the advisor they are evaluating software. Do **not** mention buckets or what changed.

Ask only:

> Can you book today's work in ARK?

Watch. If they hesitate, classify **afterward**.

## Freeze after sellability hardening

**Do not fix hypothetical hesitations. Only fix observed hesitations.**

That keeps E from sliding back into polish. Projection existence (e.g. Partial Parts on advisor Job Board cards) is not permission to add UI — wait for a real operator sentence.

Record only what the floor showed. If the notebook stays empty or only has trivial items, **close E** and open **H**.

## Classify hesitations (runtime)

Patterns decide the fix — do not redesign the wrong layer.

| Category | Question | Typical fix |
|----------|----------|-------------|
| **Discoverability** | "I couldn't find it." | Put the action where operators look |
| **Vocabulary** | "I didn't know what this meant." | Rename to shop language |
| **Workflow** | "I didn't know what came next." | Explicit next-step / bridge CTA |
| **Confidence** | "I wasn't sure this was the right action." | Clarify what the button does |

If most notes are Discoverability → don't redesign workflows.  
If most are Vocabulary → don't build new capabilities.  
If most are Workflow → teach the next step; don't add screens.  
If most are Confidence → clarify the action; don't invent parallel authority.

### Notebook only (not runtime doctrine)

| Category | Question |
|----------|----------|
| **Expectation** | "I expected this to work differently." |

They found it, understood the words, knew the next step — mental model still mismatched (e.g. customer name → Hub, Enter opens the first result, drag sends a notification). Highest-value refinements; keep in the floor notebook, not the runtime table.

## After E clears

**Freeze the workflow.** Start **H** (Customer Communication).

Reopen E only if H exposes a **specific** workflow problem. Do not keep polishing a day that already meets the exit criterion.

## Engineering slices already shipped

| Slice | Examples |
|-------|----------|
| 1 | Schedule search; rail/hub Schedule; Checked in → Intake; Job Board; empty states |
| 2 | Parts receive → status; Send Estimate discovery; Post to sales; Status history |

## Ladder (repeatable beyond Scheduling)

```text
Capability → Engineering → Runtime → Operator adoption → Floor proof → Sellable
```

Same pattern for Communications, Estimates, Inspections, Payments, Staff mobile, Voice — without inventing a new certification each time.

**Tests ≠ Sellable.**
