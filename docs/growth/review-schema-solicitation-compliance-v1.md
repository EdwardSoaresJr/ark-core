# Review Schema & Solicitation Compliance v1

**Status:** Audited 2026-07-26 · schema fix shipped with Plain English v1 (`94d3eef6`) · **Reviews capability not open**  
**Sources:** [Google Review Snippet guidelines](https://developers.google.com/search/docs/appearance/structured-data/review-snippet) · [Search Engine Land summary](https://searchengineland.com/google-says-dont-include-fake-or-undisclosed-incentivized-reviews-in-review-snippet-structured-data-483456) · Google Maps Fake Engagement / requesting reviews guidance

---

## Structured data (website)

### Before

`ShopSeoContext` emitted `aggregateRating` (ratingValue / reviewCount from Settings Google fields) onto **AutoRepair** JSON-LD on public pages.

### Verdict

**Self-serving under Google’s rules.** A LocalBusiness / AutoRepair / Organization must not claim review-snippet eligibility from ratings about itself that it controls or displays on its own site (including third-party widgets and Google ratings entered in Settings).

### After (this release)

| Emitted | Status |
| --- | --- |
| AutoRepair / Organization / LocalBusiness (name, url, address, telephone, sameAs, …) | Preserved |
| `AggregateRating` on business schema | **Removed** |
| `Review` JSON-LD about LugsNPlugs | Not emitted (builder exists unused) |

### Visible trust (preserved)

- Google rating chip / review cards / featured Google excerpts on the public site
- Settings `google_rating` / `google_review_count` / `google_reviews_url` for **display only**
- RepairPal review pages (links + explanation — no business AggregateRating markup)

Display ≠ review-snippet structured data.

### Incentivized reviews in schema

No review bodies are marked up in structured data. No incentivized-review schema exposure found.

---

## Review solicitation (ARK-SMS operations)

### Current behavior (Review Request v1)

- Advisors may **send** a review request (Text / Email / both) or choose Not now — not a paid-close bookkeeping checkbox.
- Outbound copy always includes **honest feedback** + Google review link (settings) + equal **Contact Us** path.
- Truth is `ConversationMessage` with `metadata.kind = review_request`.
- Binding doctrine: [review-request-no-gating-v1.md](../communications/review-request-no-gating-v1.md).

### Compliance findings

| Item | Verdict |
| --- | --- |
| Same Google opportunity for every send | Required |
| Equal Contact Us in the same message | Required |
| No star-gating / happiness routing | Required |
| No incentives / five-star asks | Required |
| CSAT | Separate future intent — never a Google gate |

### Frozen rules (product)

1. Eligibility = objective lifecycle / advisor send action — never predicted satisfaction.
2. No incentives for Google reviews.
3. **No review gating.**
4. Ask for an honest review — never five stars.
5. No suggested review wording / employee mentions.
6. Contact Us and Google review are siblings in the same outbound — not branches.
7. Private CSAT (if built later) must not decide who gets the Google link.

---

## Earned next capability (frozen name — do not build yet)

### Eligible Review Request automation

```text
Closed + Paid → objective suppression check → same request sent to every eligible customer
```

| Rule | Meaning |
| --- | --- |
| Eligibility | Objective only — never satisfaction, NPS, advisor judgment, complaint status |
| Suppression | Operational only (no contact, opted out, customer-window duplicate, …) |
| Ask | Honest review + Contact Us — never five stars, incentives, or suggested wording |

**Do not open automation** until floor observation earns it. Manual advisor send is the v1 path.

### CSAT (parked — independent of Google)

3–7 days after pickup private feedback survey may earn its own Conversation Intent. Not a gate. Not before the public review opportunity.

### Other follow-ups (still parked)

- Customer-window deduplication (~30 days) with explicit advisor override
- Conversation Intent vocabulary (Review Request, Vehicle Ready, Thank You, …)
