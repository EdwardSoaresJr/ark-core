# Documents Authority v1

**Status:** Local implementation · not shipped until approved  
**Classification:** First-class operational authority (alongside Evidence, Repair Actions, Work Authorization, Financial)

## Constitutional sentences (freeze forever)

> **Documents preserve paperwork. They do not interpret it.**

OCR, VIN parsing, warranty extraction, and AI summaries are **consumers**. They never become Document responsibilities.

> **A document exists once. Relationships determine where it appears. The physical document is never duplicated merely to satisfy a relationship.**

> **Documents may be presented by many surfaces but are authored once.**

Presentation multiplies. Authority does not. Same pattern as Financial Position, Customer Recognition, Footer Projection.

> **Documents preserve paperwork. Relationships determine visibility. Other authorities may reference documents, but Documents never inherit their responsibilities.**

That sentence protects the boundary for the next decade.

## Owns

Durable paperwork / files associated with operational entities — the bytes and the quiet history of what happened to them.

Today ownership is:

```text
Document
 ├── belongs to Customer (required)
 └── may belong to Repair Order (optional)
```

Tomorrow may also project onto Vehicle, Warranty Claim, Vendor — **without duplicating bytes**.

## Does not own

| Concern | Owner |
| --- | --- |
| Repair proof | **Evidence** |
| Scope / labor structure | **Repair Actions** |
| Customer authorization of work | **Work Authorization** |
| Money | **Financial** |
| Interpretation (OCR, extraction, AI) | Future consumers — never Documents |

Estimate/invoice *generation engines* may later emit a Document with `source=generated`. Generation logic stays elsewhere; Documents preserves the resulting paperwork.

## Sources

| Source | Meaning |
| --- | --- |
| `upload` | Advisor uploaded a file |
| `scan` | Multi-page camera capture assembled to one PDF |
| `generated` | ARK-produced PDF (estimate, inspection, invoice, signed authorization, warranty claim, customer statement — future) |

`generated` is permanent vocabulary. Do not reopen `import`.

## Audience language

| Surface | Label |
| --- | --- |
| Authority / engineering | **Documents** |
| Customer Hub | Documents |
| Repair Order presentation | **Paperwork** |

Same pattern as Repair Actions → Work on the floor. Do not expose the internal noun when the floor already has a better one.

## Long-term RO composition

```text
Repair Order
    │
    ├── Evidence
    ├── Paperwork          ← Documents authority, advisor language
    ├── Work Authorization
    ├── Financial Position
    └── Repair Actions
```

Each authority answers one question. Documents answers: *what durable paperwork exists, and where does it appear?*

## Invariants

1. One physical storage object per Document row for a given rendition — never copy bytes to satisfy Customer vs RO (or future Vehicle) visibility.
2. Relationships are pointers; visibility is projected from relationships.
3. Private storage only; authenticated staff streams.
4. Soft retire — do not casually hard-delete.
5. Wrong file → retire and upload another.
6. **Rotation creates a new storage object** (new rendition). Never mutate uploaded bytes in place. Protects auditability.
7. Evidence remains Evidence — a Document may later be *referenced* by Evidence if earned; Documents does not become proof authority.

## Projections (disposable)

| Projection | Question |
| --- | --- |
| Document list (Hub / Paperwork chip) | What paperwork is here? |
| **Document Timeline** | What happened to this document? |
| Attach search | Which existing document matches warranty / registration / alignment…? |

Document Timeline is authority events packaged for an audience — same philosophy as Vehicle Timeline, Financial Timeline, Inspection Timeline. v1 **writes** `document_events`; operator timeline UI ships when earned.

## Timeline events (quiet)

Uploaded · Scanned · Generated · Presented · Emailed · Attached · Shared · Unshared · Rotated · Retired.

`Presented` is reserved for tablet / customer presentation.  
`Emailed` records each outbound send (recipient + actor) for the document email log.

## Floor gate (before ship)

Do not expand features. Certify on the desk:

1. Scan a real two-page warranty contract  
2. Attach it to an RO  
3. Upload a real alignment PDF  
4. Retrieve both later  

If the desk pile starts disappearing and the rhythm feels natural, Documents has earned its place in ARK.
