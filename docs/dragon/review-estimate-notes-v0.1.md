# Review Estimate Notes v0.1

**Status:** Shipped  
**Model:** qwen3:14b (unchanged)  
**Companion:** Dragon Service Advisor (field rewrite) · Assist Bridge

## Product

Whole-estimate **critique only**. Advisors click **Review Estimate Notes** on the RO review toolbar → Dragon reads visit reason + all concern narratives → returns gaps, inconsistencies, and suggested actions. **Nothing is written** to RO authority.

## Entry

`#review-toolbar` → **Review Estimate Notes** → workspace modal `review-estimate-notes`.

## Task type

`review_estimate_notes`

## Result shape

`summary`, `strengths[]`, `gaps[]`, `inconsistencies[]`, `customer_readiness`, `suggested_actions[]`, `warnings[]`, `confidence`

Prohibited: prices, parts, labor, mutations, status, proposal/rewrites.

## Explicit non-goals

- Auto Apply across fields  
- Field rewrite (use Dragon Service Advisor)  
- Price/parts/labor advice as authority  
- Companion surface  
