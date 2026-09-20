# ARK Mobile — Authority vs Configuration Audit v1

**Status:** Observation artifact — not a backlog  
**Date:** 2026-06-15  
**Doctrine:** doctrine `ark-authority-vs-configuration.mdc`  
**Companion:** [ark-mobile-projection-v1.md](ark-mobile-projection-v1.md) · [ark-mobile-migration-audit-v1.md](ark-mobile-migration-audit-v1.md)  
**Flutter repo:** `ark-mobile` (sibling to `arksmsv2`)  
**Backend shell:** `app/Ark/Mobile/MobileUserPresenter.php` → `GET /api/mobile/me`

---

## Purpose

Snapshot of where the Authority → Observation → Projection → Configuration doctrine **holds** and **leaks** on mobile — before anyone asks six months from now:

> "Why is mobile behaving differently than the doctrine says?"

This document records observations only. No tasks. No implementation commitment.

**Headline finding:** Capabilities are mostly correct. Configuration projection is incomplete. That is a different problem than "mobile architecture is wrong."

---

## Mobile doctrine health (summary)

| Posture | Assessment |
|---------|------------|
| **Strong** | Authority → Capabilities → Flutter shell largely works; nav is capability-driven; server scoping owns visibility; no obvious `if (shop == "Demo Auto Repair")` rot in production Dart |
| **Weak** | Configuration does not reach the shell consistently; intake/tab semantics partially hardcoded; push/poll hints exist but are not consumed; shop mobile surface toggles do not exist; attention/call deep-linking incomplete |

**Next evolution (conceptual, not scheduled):** Make **configuration a first-class projection** the same way capabilities already are — not rebuild mobile architecture.

---

## Current compliance

What already matches the doctrine.

### Authority → Capabilities → shell

- `MobileUserPresenter` builds `/api/mobile/me` from `MobileStaffAccess` and Spatie permissions. Flutter is instructed to shape navigation from this payload, not hardcoded roles.
- Login returns the same shell fields as `/me` (`MobileAuthLoginController`).
- Bottom nav in `ark-mobile/lib/screens/home_shell.dart` gates tabs on `capabilities.intake`, `.repairOrders`, `.communications`, `.attention` — not `isAdvisor` / `isTechnician` branching in production `lib/`.
- Finding capture FAB gates on `capabilities.findings` (`repair_order_detail_screen.dart`, `concern_detail_screen.dart`).
- Cold start restores shell via `/me` (`auth_repository.dart`).

### Authority → Projection → screens

- **Work list:** `MobileWorkProjection` scopes technicians to assigned ROs server-side.
- **Communications:** `MobileCommunicationsProjection` separates shop inbox vs assigned-RO threads; thread payload exposes `can_reply` / `can_internal_note`.
- **Compose:** `CommunicationThreadScreen` uses thread-level flags, not shell guesses.
- **Production status:** `ConcernProductionStatusPicker` uses `production.can_update` and `production.options` from API.
- **API boundary:** All traffic through `/api/mobile/*`; no transport SDKs in Flutter.

### Settings pattern (partial — one good example)

- **Mobile push:** Shop behavior in Settings (`mobile_push` on `shop_settings`); `config/mobile.php` is deployment fallback only (`FIREBASE_CREDENTIALS`). Device register exposes `push_enabled` from shop settings.

### Tests encode the contract

- `tests/Feature/Mobile/MobileApiTest.php` asserts advisor vs technician capability differences on `/me` and related endpoints.

### What we did not find (production)

- No runtime shop slug checks in Flutter `lib/`.
- No scattered `if (isAdvisor) showCommsTab()` nav pattern.
- Demo Auto Repair appears in **compile-time** defaults (API URL, debug email, bundle id, demo data) — deployment/branding posture, not per-request shop behavior.

---

## Doctrine leaks

Gaps between doctrine and current implementation. Each is a **leak**, not a defect ticket.

| Area | Leak | Layer |
|------|------|-------|
| Visit modes | Labels and chips hardcoded in Flutter; backend validates same slugs but does not project vocabulary | Configuration not projected |
| Intake customer types | Backend accepts `billing_class` from `ShopSettings`; mobile check-in never displays or sends | Configuration not projected |
| Tab labels & order | Check-in · My Work · Comms · Attention fixed in Dart | Configuration / presentation |
| Fourth tab | Always present; technicians without `attention` see notifications but tab label stays "Attention" | Configuration / semantics |
| Shell fields | `customer_reply`, `internal_notes` parsed on shell, unused (thread projection is authoritative) | Redundant surface / drift risk |
| RO quick_actions | Backend hardcodes `add_finding`; Flutter uses shell `findings` instead — inconsistent authority surface | Projection inconsistency |
| Poll / push hints | `poll_after_seconds`, `push_enabled` returned on feeds and device register; Flutter hardcodes 45s or ignores | Configuration not consumed |
| Push stub | `PushRegistrationService` stub; device register response not read for `push_enabled` | Configuration not consumed |
| Attention deep links | Backend emits `deep_link: 'call'`; Flutter handles RO and conversation only | Projection consumption incomplete |
| Intake capability bundle | Shell `intake` = `repair_orders.manage`; sub-endpoints need `customers.manage` / `vehicles.manage` separately | Capabilities coarse vs gates |
| Shop mobile toggles | Surface visibility is permissions-only; no Settings knob to disable mobile intake/comms per shop | Configuration surface missing |
| Technician attention | `/attention` returns 403 for technicians; client falls back to `/notifications` — different API shape | Projection shape inconsistency |

---

## Observation queue

Repeated pattern → earned change. **Observe first.** No shop-specific mobile configuration screens until floor evidence.

---

### O1 — Visit mode labels hardcoded in Flutter

**Observation**  
`check_in_screen.dart` defines Waiting, Drop-off, Shuttle, Tow-in chips with fixed labels and values. Web intake may already reflect shop vocabulary from Settings.

**Doctrine impact**  
Configuration not projected from authority/settings. Shops that rename visit language or disable modes would still see mobile defaults.

**Decision**  
Observe until intake/mobile usage (advisors, counter) proves vocabulary mismatch matters. Do not build mobile intake config UI until then.

---

### O2 — Customer type / billing class absent on mobile check-in

**Observation**  
`MobileIntakeStoreController` accepts `billing_class` from `ShopSettings::customerTypeRows()`. Flutter check-in never collects or displays it.

**Doctrine impact**  
Shop intake behavior exists in authority/settings but is not projected to mobile. Mobile intake may create incomplete customer records vs web.

**Decision**  
Observe whether mobile check-in is used for full intake or quick capture only. Compare to web intake adoption before projecting customer type rows.

---

### O3 — Bottom nav labels and order are compile-time

**Observation**  
`home_shell.dart` uses fixed tab order and English labels. Capabilities gate visibility; presentation does not.

**Doctrine impact**  
Partial compliance — visibility is capability-driven; vocabulary and order are configuration leaks.

**Decision**  
Observe. Rename/order only matters if shops disagree on nav semantics or non-English floor language becomes a requirement.

---

### O4 — Attention tab shown when capability is false

**Observation**  
Fourth tab always renders. When `capabilities.attention` is false, content is `NotificationsScreen` but label remains "Attention".

**Doctrine impact**  
Technician mental model may not match tab name. Configuration/semantics leak, not authority leak.

**Decision**  
Observe with Landon (technician) and advisors — does the label confuse daily use? Fix presentation only if observation proves friction.

---

### O5 — Coarse `intake` capability vs finer endpoint gates

**Observation**  
Shell shows Check-in when `intake` is true (`repair_orders.manage`). Customer or vehicle create may 403 without `customers.manage` / `vehicles.manage`.

**Doctrine impact**  
Capabilities partially correct; bundle coherence leak. Future shops with custom permission matrices may hit silent failures.

**Decision**  
Observe permission shapes in production roles. If no shop splits intake permissions, document bundle as intentional. Split shell capabilities only if repeated 403s appear in support/observation.

---

### O6 — Poll and push hints ignored in Flutter

**Observation**  
Backend returns `poll_after_seconds` and `push_enabled` on attention, notifications, and device register. Flutter hardcodes 45s polling; push registration is stubbed; `AttentionFeed.pushEnabled` unused.

**Doctrine impact**  
Configuration exists server-side but is not consumed — same class of leak as telephony before settings wiring, but mobile push **settings** already exist server-side.

**Decision**  
Observe push vs poll reliance on floor. Wire transport only when mobile push settings are enabled <strong>and</strong> observation proves polling failed — per notification doctrine. Do not wire <code>firebase_messaging</code> in Flutter preemptively.

---

### O7 — No shop-level mobile surface toggles

**Observation**  
Unlike `mobile_push`, there is no Settings control to disable mobile intake, comms, or attention per shop. Visibility is entirely Spatie permissions.

**Doctrine impact**  
Fails deploy test for *shop preference* (two shops disabling a surface without permission surgery). May be acceptable if mobile is always permission-gated platform-wide.

**Decision**  
Observe. Do **not** build shop mobile configuration screens until repeated sentences prove shops want different mobile surfaces. Pressure First / Authority Adoption apply.

---

### O8 — Call rows inattention deep links

**Observation**  
`MobileAttentionProjection` can emit `deep_link: 'call'`. Flutter `AttentionScreen` handles repair order and conversation only.

**Doctrine impact**  
Projection emitted but not consumed. Incomplete mobile attention recovery for calls.

**Decision**  
Observe whether advisors attempt call actions from mobile attention. Hide call rows or implement handling only when mobile call UX is in scope — not parallel to desk pop authority.

---

### O9 — Demo Auto Repair compile-time defaults in ark-mobile

**Observation**  
Default API URL, debug login prefill, Android bundle id, and demo data reference Demo Auto Repair. Not runtime tenant branching.

**Doctrine impact**  
Product/deployment posture — not shop configuration. Matters for Shop #2 onboarding and Arkify builds, not for Authority vs Configuration per se.

**Decision**  
Observe at second-shop provisioning. Neutral defaults + build flavors when fleet grows; no urgency for single-shop floor.

---

### O10 — `quick_actions` vs shell capabilities divergence

**Observation**  
`MobileRepairOrderProjection` / `MobileConcernProjection` expose `quick_actions.add_finding`; Flutter gates on shell `capabilities.findings` instead.

**Doctrine impact**  
Two authority surfaces for the same question. Drift risk if they disagree.

**Decision**  
Observe. Prefer single projection source when next mobile RO/concern pass touches compose affordances — not a standalone project.

---

## Governance sequence (this audit)

```text
Authority        — MobileStaffAccess, projections, CallSession, Conversation, RO
    ↓
Observation      — this document; floor usage (Landon, Molly, advisors)
    ↓
Repeated pattern — which leaks actually hurt daily work?
    ↓
Earned change    — configuration projection or settings surface, not audit-driven backlog
```

---

## Review triggers

Re-read this audit when:

- A second shop provisions mobile with different intake or comms expectations
- Someone asks why mobile differs from web intake or operations settings
- Flutter nav or check-in changes are proposed without a projection source
- Shop-level "disable mobile X" is requested before observation notes exist

---

## Changelog

| Date | Note |
|------|------|
| 2026-06-15 | v1 — read-only audit after Authority vs Configuration doctrine (`f329dfe4`). Telephony left alone; mobile is first cross-domain audit. |
