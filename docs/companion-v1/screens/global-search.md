# Screen spec — Global Search

**ID:** `companion.screen.global-search`  
**Role(s):** Advisor · Owner  
**Quo ref:** contact search rhythm in `screensdesign-2.webp` — reference only  
**Status:** 📝 draft — Edward review

---

## Job

**Emma → act in one tap** — find customer · vehicle · RO · phone · then call · text · open RO · pay · schedule without hunting tabs.

---

## Product quality gate

| | Reference CRM | Quo | ARK Companion |
|---|-----|-----|---------------|
| **Verdict** | "Search across all Apps" · launcher | Contact/number search | **Target: Yes** |
| **Why** | CRM app grid | No RO · pay · schedule from search | **Command palette for the shop** — results are **actions**, not just records |

---

## Layout (production spec)

### Mode A — Search entry (empty / focused)

**Shell**

- **Search field** — autofocus · clear (×) · cancel/back
- **Placeholder:** `Customer · vehicle · RO · phone`

**Body (empty state)**

- **Recent searches** — last 5–8 · tap refills query
- **Quick filters (chips):** Customers · Vehicles · Repair orders · Phone numbers
- **Shortcuts row (optional):** New walk-in · Scan VIN — P1

### Mode B — Results (typing ≥2 chars)

**Result groups** — section headers sticky on scroll:

1. **Customers** — name match · phone suffix
2. **Vehicles** — YMM · plate · VIN last 6
3. **Repair orders** — `#1599` · customer · status chip
4. **Phone numbers** — if direct dial match

**Customer result row (~64pt):**

- Avatar · **Emma Hathorn**
- Subline · `2019 Civic · RO #1599 open`
- Trailing · chevron

**Tap row** → expands **inline action rail** OR navigates to **Result detail sheet** (prefer sheet — stay in search):

| Action | Destination |
|--------|-------------|
| **Call** | [`outgoing-call.md`](outgoing-call.md) or direct dial |
| **Text** | [`conversation-thread.md`](conversation-thread.md) |
| **Open RO** | [`repair-order-workspace.md`](repair-order-workspace.md) |
| **Pay** | [`payment-sheet.md`](payment-sheet.md) |
| **Schedule** | Appointment sheet |
| **History** | [`customer-workspace.md`](customer-workspace.md) |

**≤2 taps from search field to any action** — Edward gate.

### Mode C — Numeric entry (dialer path)

When query is all digits / formatted phone:

- Top result: **Call this number** · **Text this number**
- Below: matching customers if any

### Tab bar

- Search tab highlighted when entered from bottom nav
- When opened from in-screen icon · modal presentation · no tab switch

---

## Typography & density

| Element | Example |
|---------|---------|
| Query | Emma |
| Primary hit | Emma Hathorn |
| Secondary | 2019 Honda Civic · (512) 555-0142 |
| RO line | RO #1599 · Waiting approval |
| Section | CUSTOMERS |

Tight list — 3–4 results visible without scroll for common names.

---

## Interaction patterns

| Gesture | Behavior |
|---------|----------|
| Type | Debounced search · 200ms |
| Tap result | Action sheet with shop actions |
| Tap action | Execute · dismiss search or stack workspace |
| Swipe result left | Call shortcut |
| Swipe result right | Text shortcut |
| Clear field | Back to Mode A |
| Cancel | Dismiss modal / previous tab |
| Pull to refresh | Re-run query |

**Persistent affordance:** magnifying glass on Communications · Home quick field · hardware keyboard `/` shortcut — P1

---

## States

| State | UX |
|-------|-----|
| Empty query | Recents + chips |
| No results | "No matches" · offer create customer / walk-in |
| Loading | Skeleton rows in each section |
| Offline | Search local recents + cached customers only · banner |
| Single exact match | Auto-highlight top row · actions visible without second tap — optional |

---

## Flows

**Entry:**

- Search tab
- Communications search icon
- Home quick field (P1)

**Exit:**

- Action complete → pocket or prior workspace
- Back → cancel without mutation

Link: [`../02-flows.md`](../02-flows.md#search--act-emma)

---

## Data & API

**Needs:**

- Unified search endpoint — customers · vehicles · ROs · phones
- Recent searches (local + optional server sync)
- Result DTO includes open RO id · vehicle summary · balance flag for Pay action visibility

**May need:** `GET /api/mobile/search?q=` with grouped sections  
**Existing:** customer search on desktop — extend ranking for mobile (phone · plate · RO #)

---

## Edward sign-off

- [ ] Faster than Quo for "find Emma's RO and take payment"
- [ ] Ready for Flutter
