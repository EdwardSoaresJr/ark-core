# Shop Memory — Runtime Authority

**Status:** Shop Memory v1 COMPLETE · selective enablement  
**Capability:** Shop Memory  
**Doctrine:** Shop Memory remembers. Suggestions surface. Advisors decide. Authorities persist.

**Companion:** Suggestions may accelerate authorship; they never become authorship.

---

## Layers

```text
Shop Memory (capability)
    ↓
Suggestion Engine
    ↓
Providers (gated by ShopMemoryFeatures)
    ↓
Projections (Concern · Labor · …)
```

| Layer | Owns | Does not own |
| --- | --- | --- |
| **Shop Memory** | Concept, doctrine, enablement, future learning | Direct UI queries |
| **Suggestion Engine** | Search orchestration, ranking, provider registration, suggestion identity | Concerns, labor, persistence |
| **Providers** | `suggest($context)` for one corpus source | Other providers, merge, rank |
| **Projections** | Which corpus a surface asks for | Authority writes |

UI never queries Shop Memory directly. Surfaces ask projections; projections ask the engine.

---

## v1 deliverables

| Area | Status |
| --- | --- |
| Suggestion Engine + pipeline + identity + failure isolation | Done |
| Capability registry (`shop_memory` JSON) | Done |
| Diagnostics (catalog vs enablement vs registration) | Done |
| Historical Labor (live) | Done |
| Add Concern popup (single entry; editor populate only) | Done |
| Problem-language providers (gated OFF) | Done |
| AI Rewrite (sibling action, gated OFF) | Done |
| Behavior events (write-only, frozen outcomes) | Done |

---

## Enablement matrix (production defaults)

Stored in `shop_settings.shop_memory` JSON via [`ShopMemoryFeatures`](../../app/Ark/ShopMemory/ShopMemoryFeatures.php).

| Capability | Default |
| --- | --- |
| Historical Labor | **ON** |
| Add Concern popup | **ON** |
| Historical Concern | OFF |
| Technician Observation | OFF |
| Inspection Finding | OFF |
| Customer Intake | OFF |
| AI Rewrite | OFF |

Disabled providers are **not** registered with `SuggestionEngine`. Diagnostics compare catalog vs enablement vs registration — disabled ≠ missing/broken.

```text
php artisan tinker
>>> app(\App\Ark\ShopMemory\Suggestion\SuggestionEngine::class)->diagnostics()->lines()
```

---

## Authority

Suggestions are **not** authority. `RepairOrderConcern` and `RepairOrderLine` remain the only persistence for concerns and labor.

No provider may silently create or overwrite operational truth. Only explicit advisor acceptance creates authority.

AI Rewrite is a **sibling action** (`AiRewriteAction`) — never on blur, never engine search.

---

## Behavior events (write-only)

Table: `shop_memory_suggestion_events`

Frozen outcomes ([`SuggestionOutcome`](../../app/Ark/ShopMemory/Suggestion/SuggestionOutcome.php)):

| Outcome | Meaning |
| --- | --- |
| `accepted_unchanged` | Selected suggestion became authority without textual modification |
| `accepted_edited` | Selected suggestion was modified before authority creation |
| `ignored` | Suggestions shown; advisor typed different authority |
| `dismissed` | Surface closed/cleared without authority creation |

One terminal outcome per interaction. Do not infer dismissed after successful create. **Not consumed by ranking.**

---

## Runtime surfaces

- Labor: `GET …/labor-memory-suggest` · Type → Suggest → ↓ Enter
- Concern: Add Concern **popup** (vocabulary interim while HC off) · `GET …/concern-memory-suggest` when problem-language providers on
- Events: `POST …/shop-memory/suggestion-events`
- Rewrite: `POST …/ai-rewrite` (404 when disabled)

`ScopeEntryVocabularyQuery` remains until Historical Concern is enabled and observed.

---

## Observation gate

Observation controls **behavioral evolution** (learning / ranking), not whether code may exist.

Future expansion is enablement only:

```text
Observe → Enable Historical Concern → Observe → …
→ Enable Technician Observations → …
→ Enable Inspection → …
→ Enable Intake → …
→ Enable AI Rewrite
```

Do not auto-enable providers. Flip `shop_memory` JSON after the notebook earns each capability.

Notebook: [`docs/operations/shop-memory-observations.md`](../operations/shop-memory-observations.md)

### Future rule (protect now — do not implement yet)

**Shop Memory learns from advisor behavior, not from persistence alone.**
