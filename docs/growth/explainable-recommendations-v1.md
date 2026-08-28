# Explainable Recommendations v1

**Status:** v1 complete — Growth SEO audit  
**Parent:** [ark-projection-rule.mdc](../../.cursor/rules/ark-projection-rule.mdc) · [ark-explainability-doctrine.mdc](../../.cursor/rules/ark-explainability-doctrine.mdc)

## Problem

A single “SEO audit” mixed three incompatible truth sources:

1. **Configuration / authority** — known immediately after deploy (footer, CTA, problem template)
2. **Runtime crawl** — only knowable by fetching live HTML (broken links, canonical tags, load time)
3. **Search Console** — only knowable after Google reports (clicks, impressions, indexing)

When the audit derived findings from a **stale content registry**, it violated projection doctrine: it was observing a projection as if it were truth.

```
Wrong:  Code → Deploy → ??? → Registry (maybe) → Audit guesses
Right:  Recommendation → declared authority source → evidence
```

## Rule

**Every recommendation declares its authority source.**

| Source | Examples | When truth updates |
| --- | --- | --- |
| **Configuration** | CTA label, footer, problem authority template, metadata in `CommonProblemRegistry` | Immediately on deploy |
| **Registry** | Published path inventory, sync timestamps | After registry sync |
| **Crawl** | Broken links, alt text, canonical HTML, H1 count, load time | After nightly crawl |
| **Search Console** | Clicks, impressions, CTR, coverage | After Google ingest |

Search audit **never** shares a list with structural or runtime audit. It lives on Opportunities.

## Three audit channels

### 1. Structural audit

Derived from authorities. **No crawl. No stale registry guess.**

| Check | Authority |
| --- | --- |
| CTA consistency | `PublicLeadFormCopy::SUBMIT_LABEL` |
| Footer | `CustomerSurfaceFooterData` via customer shell |
| Trust chips | `PublicTrustSignalsProjection` partial wiring |
| Problem template | `CommonProblemAuthorityProjection` partial |
| Problem depth | `CommonProblemAuthorityWordCount` (symptoms, causes, FAQ, diagnostic process, repairs) |
| Titles / descriptions | `CommonProblemRegistry` + `public_seo` config |
| Schema generation | `PublicContentRegistrySyncService` registration |

Structural findings may **pass** with evidence — not only failures.

### 2. Runtime audit

Requires crawler metadata on `GrowthContent`. Silent when crawl has not run.

Broken links, alt text, canonical HTML mismatch, slow pages, orphan pages, multiple H1.

### 3. Search audit

Search Console ingest → Opportunities queue. Separate surface, separate authority.

## Explainability shape

Each finding exposes:

- **What** — title + pass/fail
- **Why** — evidence string (authority path, constant, deployment ref)
- **Authority** — Configuration / Crawl / Registry / Search Console
- **Heals on deploy?** — yes for configuration; no for crawl/search

## Implementation

- `SeoAuditEngine` — structural analyzers + runtime analyzers, grouped in `summarize()['channels']`
- `app/Ark/Growth/Seo/Audit/Structural/*` — configuration-derived
- `app/Ark/Growth/Seo/Audit/Analyzers/*` — crawl-metadata-derived
- `/growth/audit` — two sections; link to Opportunities for search

## Anti-patterns

- Crawling HTML to see if footer exists when `CustomerSurfaceFooterData` is the authority
- Counting `meta_description` length as “thin content” when problem authority fields exist
- One mixed recommendation list for deploy-time fixes and Google lag
- Presenting registry rows as truth without declaring them as registry-sourced projections

## Platform pattern (not Growth-only)

Anywhere ARK makes an operational claim:

```
Question
  ↓
Authority type (Configuration · Crawl · Registry · Search Console · …)
  ↓
Evidence
  ↓
Confidence (v2)
  ↓
Projection
```

Same grammar applies to Companion recommendations, advisor suggestions, parts observations, technician diagnostics, business dashboards, and AI explanations. Growth SEO audit is the first shipped instance — not a special case.

## v1 complete — remaining work is observational

v1 architecture is frozen. Next work **expands the evidence base**, not redesigns authority:

| Work | Purpose |
| --- | --- |
| Nightly crawler | Populate runtime metadata (broken links, alt, canonical HTML, load time) |
| `APP_DEPLOY_REF` | Deployment label on production images without `.git` |
| Search Console ingest | Continue on Opportunities — separate from structural/runtime |

**Runtime silence until crawl exists is intentional.** Unknown is not failure. A false warning trains operators to ignore the dashboard.

## Deferred — v2 refinements

### Confidence state

Every recommendation should eventually expose confidence, not only pass/fail:

| Outcome | Example |
| --- | --- |
| PASS + Certain | Configuration authority — `PublicLeadFormCopy::SUBMIT_LABEL` |
| PASS + Observed | Crawl authority — observed 7 hours ago |
| FAIL + Verified | Search Console — Google verified |
| UNKNOWN | Crawl not yet executed — **not the same as FAIL** |

### Exact authority source

Surface the class or projection name alongside authority type — for operators rarely, for engineers always:

```
Authority:  Configuration
Source:     PublicLeadFormCopy::SUBMIT_LABEL
```

```
Authority:  Configuration
Source:     CustomerSurfaceFooterData
```

```
Authority:  Observation
Source:     CommonProblemAuthorityProjection
```

When something is wrong, the developer knows exactly where to look without re-tracing the audit.
