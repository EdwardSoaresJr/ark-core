# PR #186 — Parts Matrix autosave commit contract

**Staging Core:** `788818309236e30a1b654eba62b741f8e18057f5`  
**SaaS:** `eb859c21`  
**Host:** `alpha.staging.arksms.com`

## Contract

Successful autosave requires JSON:

```json
{ "ok": true, "committed": true, "matrix_key": "...", "parts_matrices": [/* fresh persisted */] }
```

Returned only after `ShopSettings` persistence. HTML redirects, `opaqueredirect`, 422, 401/403, and non-JSON 200 bodies do **not** count as success.

Autosave requests are **serialized** (`saveTail` chain). Each request builds FormData at execution time so an older payload cannot overwrite a newer one.

## Regression evidence

| Check | Result |
| --- | --- |
| JSON success + fresh server `parts_matrices` echo | Pass (Pest + staging browser 200/`committed:true`) |
| 422 validation, no `committed` | Pass (Pest + staging browser) |
| Guest / unauthenticated JSON | Pass (Pest `401`; staging without CSRF/session is non-commit) |
| Advisor authorization | Pass (Pest; no `committed`) |
| Ordered commits last-write-wins | Pass (Pest 171 → 174) |
| Opaque redirect treated as failure | Pass (client `parseAutosaveCommit`) |
| HTML 200 without JSON commit | Pass (client reject) |
| Network error → Save needed | Pass (staging browser) |
| Rapid edits + matrix switch | Pass (Saved; siblings intact) |
| Fresh DB read after restore | aft 180, filters 130, oem 90, fluids 150, warranty 0 |

Pricing calculations, Billing Class associations, and historical snapshots unchanged.
