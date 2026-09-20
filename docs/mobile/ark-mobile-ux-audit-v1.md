# ARK Staff — Mobile UX Audit v1

**Status:** Design audit — observation only. No code changed.
**Auditor posture:** Senior product designer + service advisor (Edward) operating the live shop.
**Method:** Walked the running app (`localhost:5151`, pointed at production data) and graded each
reachable screen against the Product Standard. Every finding below is grounded in a real screen or a
cited source file — not intuition.

## Product Standard (the bar)

Every screen must answer, in under one second:

1. **Where am I?** (identity)
2. **What is happening?** (current situation)
3. **What should I do next?** (next best action)
4. **How do I do it with the fewest interactions?** (action density)

The operator should never feel lost. ARK Staff is the **primary execution surface** — not a mobile
viewer for the desktop.

---

## 0. The one sentence that matters

> **Most of what feels broken is not 30 screen bugs. It is ~6 design-system bugs wearing 30 costumes.**

Fix the *system* and dozens of individual complaints disappear at once. This audit is organized
system-first on purpose, exactly per the directive: *"No screen gets redesigned. The design system
gets redesigned."*

---

## 1. Root-cause design-system bugs (fix these, not the screens)

### SYS-1 — There is no navigation shell. Every screen brings its own `Scaffold` + `AppBar`.

This is the source of the "two stacked back buttons," nested AppBars, and "screens inside screens."

**Evidence (cited):** `repair_order_workspace_screen.dart` wraps the workspace in its own
`Scaffold`/`AppBar` (`RO #1599` + a `Back to workspace` `TextButton`), then renders a **sub-screen
inside its body** that *also* has its own `Scaffold`/`AppBar`:

- `lib/screens/repair_order_workspace_screen.dart:101` — workspace `Scaffold`
- `:102` — `AppBar(title: 'RO …')` + `:106` `TextButton('Back to workspace')`
- `:166` — body renders `ConcernDetailScreen(...)` (a full screen with its **own** `Scaffold`+`AppBar`)

Live result (RO #1599 → Concern): **two headings** (`RO #1599`, `Concern`) and **three back
affordances** (`Back`, `Back to workspace`, `Back`) on one screen — confirmed in the accessibility
tree.

**Why it matters operationally:** the operator can't tell what "back" does, the vehicle/customer
identity vanishes, and the screen wastes ~120px of vertical space on redundant chrome before any
content.

**The fix is one thing:** a single `WorkspaceShell` owns the AppBar/identity. Sub-views
(Concern, Conversation, Inspection item) are **bodies, not screens** — they never carry their own
`Scaffold`/`AppBar`. (Do not remove one back button on one screen; remove the *pattern*.)

### SYS-2 — Identity is not persistent. The AppBar title is the object name on some screens and a generic noun on others.

- RO workspace AppBar says `RO #1599` (number, not who/what).
- Customer workspace AppBar says `Customer` (generic noun — not "Skylar Hathorn").
- Concern sub-screen AppBar says `Concern` and **drops the vehicle/customer entirely**.

There is a real identity strip on the RO *overview* (name + vehicle + status chips), but it does
**not follow you** into Concern/Inspection. The moment you drill in, you no longer know whose car
you're touching.

**The fix is one thing:** a single persistent `IdentityStrip` owned by the shell (SYS-1), shown on
every workspace sub-view. Customer/Vehicle/RO all feed the same strip.

### SYS-3 — Two competing spacing scales: "giant" and "cramped" on the same screen.

The Concern screen renders the concern summary in a **6-line all-caps display heading**, then a
full-width oversized primary button, while "Findings (0)" is squeezed and clipped between them.
Intake renders **six oversized empty tiles** with large dead whitespace. Meanwhile Work cards are
tall with generous padding but low information-per-card.

There is no shared spacing/type ramp — each screen picks its own. This is why the app feels
simultaneously "too big" and "too little."

**The fix is one thing:** a single spacing + typography scale (e.g. 4/8/12/16/24 spacing; one
display/title/body/label ramp). Apply via shared building blocks, not per screen.

### SYS-4 — Cards/sections have inconsistent treatments (the "floating card" problem).

Work rows are big bordered cards; RO overview uses bordered chips + plain sections; Intake uses
large outlined squares; Search uses outlined pill chips. Five surfaces, five card languages.

**The fix is one thing:** one `ArkCard` / `ArkSection` primitive with a single border/elevation/
padding rule. (Doctrine: dense but calm, ~15–20% tighter — `ark product doctrine`.)

### SYS-5 — The action bar is not context-aware (and duplicates content).

The bottom command bar (`Photo · Finding · Conversation · Complete Item · More`) persists across the
RO **overview** and the **concern** — but `Complete Item` is meaningless on the overview (no item
selected). The same actions also appear as inline buttons elsewhere (e.g. `+ Finding` is both a
giant inline button *and* a command-bar item on the Concern screen).

**The fix is one thing:** one command-bar component whose actions are driven by the current
shell context (overview vs concern vs inspection item), so an action only appears where it's valid.

### SYS-6 — Duplicated information within a single screen.

Concern screen shows the concern text **twice**: once as a giant all-caps display heading, then
again verbatim under a "Customer concern" label in normal case. Same string, two treatments,
stacked.

**The fix is one thing:** a content rule — a value is rendered once, at one weight. (This is a
symptom of SYS-3 + no shared content components.)

---

## 2. P0 — Functional break found during the walkthrough (not just UX)

### P0-A — Customer workspace is broken in production: route does not exist.

Opening any customer (Search → "Skylar Hathorn") spins ~10s, then errors:

> **The route api/mobile/customers/337 could not be found.**

**Root cause (cited):**
- App calls `GET /api/mobile/customers/{id}` — `lib/api/mobile_api.dart:261`
  (`_client.getJson('/customers/$customerId$query')`).
- Backend registers **no** such route. `routes/api.php` only exposes `/api/mobile/intake/customers/{customer}`
  (`MobileIntakeCustomerShowController`, line 119). `MobileCustomerWorkspaceController` is **not
  referenced anywhere** in `routes/api.php`.

So the "Customer — who am I helping?" surface — one of the five core surfaces — has effectively
never worked against production. This is a P0 functional break, surfaced as a raw technical error
(not an operator-friendly empty state).

> Per audit rules I did **not** fix this. Flagging as the first thing to fix after the audit is
> accepted. Likely a one-line route registration + route cache clear.

### P0-B — Loading/error states are dead ends with raw text.

The customer load showed: blank spinner with no context for ~10s → raw framework error string. No
identity, no retry, no "back to where I was." Same untrusting feeling the operator reported.

---

## 3. Screen-by-screen audit (core operator path)

Rated against: Identity / Navigation / Density / Hierarchy / Continuity / Actions.

### Home (Today)
- **Identity:** Clear — it's "what changed / needs me." Good.
- **Navigation:** Bottom nav persists (good). Tab root AppBar present.
- **Density:** Improved, but still split sections; Moments tiles are tone-colored (good).
- **Hierarchy:** Tone color does real work now. Strong.
- **Actions:** Inline Reply on moments (good — "act, don't navigate").
- **Verdict:** Closest to the standard. Use it as the reference for the rest.

### Work (Active repair orders)
- **Identity:** Header "Work" + `NEEDS ATTENTION (4)` band (red) — good.
- **Navigation:** Bottom nav persists; search icon top-right.
- **Density:** **Too sparse.** Each card is tall (name, vehicle, concern, status, age, "Needs
  technician assignment", "Assign technician", arrow) — ~3 cards per viewport. Scanning the queue
  requires scrolling.
- **Hierarchy:** Customer name is strongest; status (`Ready for Work`) is muted gray and easy to
  miss vs the red "Needs technician assignment." Two competing status signals per card.
- **Continuity:** Subtitle reads *"What needs attention across the shop?"* — the **Home** question
  bleeding onto Work. Identity confusion between Today vs Work.
- **Actions:** `Assign technician` inline (good); tapping the row jumps **straight into Concern**
  (see SYS-1), not the overview — surprising.
- **Verdict:** Right idea, ~40% too much vertical per row; fix via SYS-3/SYS-4.

### RO Workspace — Overview
- **Identity:** Strong — `Skylar Hathorn / 2016 Ram 1500 ST`, `Approved` + `Tow incoming` chips,
  `1 Concerns · 35 Inspection Items`, progress `0/35`, `Next: LF tire tread`, `Last activity 1h ago`,
  customer + vehicle chips. This is the best "current situation" surface in the app.
- **Navigation:** One AppBar here (good). Section tabs (Overview/Concerns/Inspection/Conversations/
  History) scroll horizontally.
- **Density:** Correct-ish, slightly airy.
- **Hierarchy:** Good: identity → status → progress → next → sections → recommendations → timeline.
- **Continuity:** Fine **until you drill in** (SYS-1/SYS-2).
- **Actions:** Command bar present.
- **Verdict:** Keep this. It proves the problem is *drill-in behavior*, not the workspace.

### RO Workspace — Concern (the headline)
- **Identity:** **Lost.** AppBar says `Concern`; vehicle/customer gone.
- **Navigation:** **3 back affordances stacked** (SYS-1). Worst screen in the app.
- **Density:** Giant all-caps title (6 lines) + duplicated body (SYS-6) + oversized button (SYS-3);
  "Findings (0)" clipped.
- **Hierarchy:** Inverted — the *least* actionable thing (restated concern text) is the largest;
  the action (`+ Finding`) competes with the command bar.
- **Continuity:** "How did I get here / where's back" is genuinely ambiguous.
- **Verdict:** This single screen demonstrates SYS-1, SYS-2, SYS-3, SYS-5, SYS-6 simultaneously.

### Intake
- **Identity:** Clear ("Who is coming in?"). Good.
- **Navigation:** Bottom nav persists; search top-right.
- **Density:** **Too sparse** — one big blue primary + six oversized empty tiles, lots of dead space.
- **Hierarchy:** Good (one primary "Start new intake", then quick-start grid).
- **Actions:** All action, no clutter — best-structured *action* screen. Just oversized.
- **Verdict:** Right content, wrong scale (SYS-3).

### Global Search
- **Identity:** Clear; search-first with category chips (Customers/ROs/Vehicles/Phone/VIN/Invoices).
- **Navigation:** Opens **within the current tab** — so a customer opened from Search while on Intake
  lands *inside the Intake navigator*. Cross-tab context leak.
- **Density:** Correct.
- **Actions:** Type → jump (good). But results are half-built: *"Vehicles, VINs, plates, invoices
  and parts are coming from ARK"* (partial "coming soon" in a first-class surface).
- **Verdict:** Strong bones; finish the result types; fix where results open (SYS-1 shell).

### Customer Workspace
- **Broken (P0-A).** Cannot be assessed beyond the error state.

### Apps / Profile
- **Identity:** Clear (launcher + appearance + ARKademy + sign-out).
- **Density:** Correct.
- **Verdict:** Fine. Lowest priority.

---

## 4. Cross-cutting consistency findings

### Duplicate / divergent UI components
- **AppBars:** at least 3 patterns — tab-root AppBar, workspace AppBar with `Back to workspace`
  TextButton, and per-sub-screen AppBars (`Concern`, `Customer`). → collapse to **one shell AppBar**.
- **Cards:** Work card vs RO overview chip vs Intake tile vs Search pill — 4 card languages → **one
  `ArkCard`**.
- **Back affordances:** arrow-back, second arrow-back, and text "Back to workspace" coexist → **one
  back model** owned by the shell.
- **Primary buttons:** giant full-width (`+ Finding`, `Start new intake`) vs inline text buttons
  (`Assign technician`, `Reply`) → **one button scale**.
- **Identity strip:** present on RO overview, absent on Concern, replaced by generic noun on Customer
  → **one persistent `IdentityStrip`**.

### Navigation inconsistencies
- Tapping a Work row → **Concern**, not Overview (inconsistent entry point).
- Section change (tab) **swaps body**, but opening a concern **renders a nested full screen** — two
  different mental models for "go deeper."
- Search results open inside whichever tab you searched from (context leak).
- Stray **"Work" tooltip bubble** floats above the bottom nav on multiple screens (Intake, Search,
  Customer, Concern) — a stuck label-render artifact.

### Density inconsistencies
- Too sparse: Intake tiles, Work cards, Customer error whitespace.
- Too dense / clipped: Concern "Findings (0)" pinched under a giant heading + giant button.
- Oversized: all-caps concern display heading; full-width buttons.

---

## 5. Workflow interruption audit (core slices)

Counts are for the happy path, today, against production.

| Workflow | Screen transitions | Searches | Back presses to recover | Context switches | Desktop required? |
|---|---|---|---|---|---|
| **Customer Arrival** | Intake → quick-start → (VIN/customer) → vehicle → walk-around → RO | 0–1 | low | moderate | No (walk-around shipped) |
| **Customer Phone Call** | n/a — no call-pop / log-call surface reached from phone | — | — | — | **Yes** (not executable yet) |
| **Inspection** | Work → RO → Inspection tab → item (nested screen, SYS-1) | 0 | high (stacked back) | high | Partial |
| **Estimate** | RO → Concern/Findings → … | 0 | high | high | Likely (no full estimate edit observed) |
| **Vehicle Pickup** | not reachable as one flow (payment/close/handover) | — | — | — | **Yes** |
| **Open a Customer** | Search → result → **ERROR** (P0-A) | 1 | dead end | — | **Yes (broken)** |

**Biggest interruption is not steps — it's recovery.** SYS-1 forces operators to think about
"which back," and SYS-2 makes them re-establish "whose car am I in" after every drill-in.

---

## 6. Why generic CRM mobile feels faster (operationally) — and the automotive-first adaptation

reference CRM isn't prettier; it removes *decisions per task*. Mapping each pattern to ARK:

| reference CRM pattern | Why it's faster | ARK automotive-first adoption |
|---|---|---|
| **Persistent actions** | Primary action never scrolls away | Shell-owned command bar (SYS-5), context-aware per RO state |
| **Bottom sheets** | Act without leaving context | Open Concern/Finding/Log-call as a **sheet over the workspace**, not a nested screen (kills SYS-1) |
| **Search everywhere** | One box navigates the whole system | Finish global search result types; open results in the **shell**, not the current tab |
| **Inline editing** | No detail-page round trip | Edit concern/finding/status in place on the workspace body |
| **Fewer confirm dialogs** | Fewer taps, undo instead | Optimistic actions + snackbar undo for assign/status |
| **Context menus** | Many actions, zero navigation | Long-press a Work row → Assign / Message / Open |
| **Swipe actions** | One-gesture triage | Swipe a Work/queue row → Assign tech / Mark ready |
| **Floating actions** | Thumb-reach primary | One FAB per surface, consistent placement/behavior |

The automotive-first guardrail: ARK still leads with **vehicle + customer + decision**, not channels
or generic CRM records. Adopt the *speed patterns*, keep the *operational hierarchy*.

---

## 7. Top problems ranked by operator friction

Ranked by how much *thinking* they force on the operator (P0 = blocks work / breaks trust).

**P0 — blocks work or breaks trust**
1. Customer workspace 404 (P0-A) — core surface broken in production.
2. Stacked back buttons / nested AppBars in RO drill-in (SYS-1).
3. Identity disappears on drill-in (SYS-2) — "whose car am I touching?"
4. Loading→raw-error dead ends, no retry (P0-B).
5. Duplicated concern text, giant + clipped layout on Concern (SYS-3/SYS-6).

**P1 — high friction, frequent**
6. Two mental models for "go deeper" (tab swaps vs nested screen) (SYS-1).
7. Command bar not context-aware (`Complete Item` on overview) (SYS-5).
8. Work row opens Concern, not Overview (inconsistent entry).
9. Work cards too tall — queue not scannable (SYS-3/SYS-4).
10. Inspection item drill-in inherits the stacked-back problem.
11. Search results open inside the wrong tab (context leak).
12. No Phone Call workflow reachable from the phone (slice gap).
13. No Vehicle Pickup / payment-close flow reachable (slice gap).

**P2 — visible inconsistency / polish**
14. Four card languages (SYS-4).
15. Three AppBar patterns (SYS-1).
16. Two button scales (SYS-3).
17. Intake tiles oversized / sparse (SYS-3).
18. Home vs Work share the same subtitle question (identity blur).
19. Stray "Work" tooltip bubble artifact above bottom nav.
20. Partial "coming soon" inside Search results.

**P3 — lower priority**
21. RO overview slightly airy.
22. Section tabs horizontally scroll (discoverability of later tabs).
23. Apps/Profile minor spacing.

> This list is intentionally **system-clustered** rather than padded to 100 isolated tickets. Each
> P0/P1 above maps to one of the 6 root causes; closing the 6 systems collapses ~30+ individual
> complaints. Extending to the remaining surfaces (Schedule, Conversations, Inspection detail,
> Estimate edit, Payment) is the v2 pass once the shell exists.

---

## 8. Proposed redesign order (remove the most operator thinking first)

Each step is a **system**, not a screen. Order chosen so trust returns fastest.

1. **Fix P0-A (route).** Customer workspace must load. Trust precondition. (1 small change.)
2. **SYS-1 — Build the `WorkspaceShell`.** One Scaffold/AppBar; sub-views become bodies/sheets; one
   back model. *Eliminates stacked back buttons everywhere at once.*
3. **SYS-2 — Persistent `IdentityStrip` in the shell.** Customer/Vehicle/RO never lose "whose car."
4. **SYS-3 — One spacing + type ramp.** Kill giant/cramped; standardize buttons & headings.
5. **SYS-4 — One `ArkCard`/`ArkSection`.** Collapse the 4 card languages; densify Work + Intake.
6. **SYS-5 — Context-aware command bar.** Actions appear only where valid; remove inline duplicates.
7. **SYS-6 + states — Content + loading/error rules.** Render values once; real empty/error/retry.
8. **Speed patterns (reference CRM).** Sheets for Concern/Finding/Log-call, swipe/long-press on queue rows,
   optimistic + undo. Then close the **Phone Call** and **Vehicle Pickup** slices on the new shell.

After step 2 alone, the app should already *feel* like one product instead of stacked screens.

---

## 9. Accessibility audit (a11y is the same audit, not a separate one)

**Verdict: Flutter is fully capable; ARK Staff implements very little of it today.** The limiting
factor is the design system, not the framework. Crucially, every a11y fix below *also* reduces
operator cognition (one-handed, gloved, sunlit, enlarged text) — accessibility and speed reinforce.

**Findings (cited):**

- **A11Y-1 — Text scaling unverified.** No `textScaler`/`textScaleFactor` handling anywhere
  (grep: 0 matches in `ark-mobile/lib`). Good news: the app respects OS font size by default. Bad
  news: layouts are untested at large sizes, and SYS-3 guarantees breakage — the all-caps 6-line
  concern heading and the already-clipped "Findings (0)" will overflow at 130–150%.
- **A11Y-2 — Semantics largely absent.** Only 4 files reference `Semantics`/`semanticLabel`/
  `tooltip` (`home_shell`, `check_in`, `customer_workspace`, `schedule`) across ~25 screens/widgets.
  Icon-only controls (top-right search, AppBar back arrows, FAB) get default/empty announcements.
- **A11Y-3 — Generic action labels.** Three controls named `Back`/`Back to workspace` on one screen
  (SYS-1); command actions named `Call` / `Conversation` with no object. Target: *"Call customer
  Sarah Johnson"*, *"Back to Skylar Hathorn's RO"*.
- **A11Y-4 — Touch targets below 48×48.** Command-bar `ActionChip`s and inline text buttons
  (`Assign technician`, `Reply`) are ~32–40px tall. Fails gloved/one-handed use.
- **A11Y-5 — Low outdoor contrast.** Status text is muted gray on light-gray surfaces
  (`Ready for Work`, `Approved · 1 day ago`). Tone red/green chips are strong; the *muted* tier is
  the problem in sunlight.
- **A11Y-6 — Reading order / focus.** Nested Scaffolds (SYS-1) produce ambiguous focus traversal and
  duplicate landmarks (two headings, three "Back"s) — screen readers can't present a clean order.
- **Positive:** `repair_order_command_bar.dart` pairs icon **+ text** on every chip — the correct
  pattern. Make it the rule, not the exception.

**Where these map:** A11Y-1→SYS-3, A11Y-2/3→SYS-2/SYS-5 (shell-owned labels), A11Y-4→SYS-3 (button
scale), A11Y-6→SYS-1. i.e. the **same 6 system fixes** carry most of accessibility — if labels,
targets, contrast tier, and reading order are defined **once in the shell + design tokens**.

### Bake a11y into the design tokens (so it can't regress)
- Min target 48×48 baked into the button/chip primitives (SYS-3/SYS-4).
- A contrast-safe "muted" tier (raise the lowest text tier to ≥4.5:1).
- Every action label = verb + object (`IdentityStrip` from SYS-2 supplies the object).
- Verify layouts at `textScaler` 1.0 / 1.3 / 1.5 as part of the shell work.

## 10. Proposed engineering gate — "Outdoor Usability" certification

Not new doctrine — a single quality check that rides the existing operational-certification model.
A workflow is **Outdoor-Usable** only if Edward can complete it:

- one-handed,
- in direct sunlight (contrast),
- wearing gloves (48×48 targets),
- with OS text at 150% (no clip/overflow),
- with VoiceOver/TalkBack reading a clean, labeled order,
- without hunting for the primary action.

Apply it to **Customer Arrival** first (it's the furthest along). It becomes a falsifiable gate that
also happens to enforce the speed goals.

## Appendix — Evidence (source files)

- Nav shell / stacked AppBars: `ark-mobile/lib/screens/repair_order_workspace_screen.dart:101-178`
- Concern embedded as nested screen: same file `:166` (`ConcernDetailScreen`)
- Customer route mismatch: `ark-mobile/lib/api/mobile_api.dart:261` vs `arksmsv2/routes/api.php:116-123`
- `MobileCustomerWorkspaceController` unreferenced in `routes/api.php` (grep: 0 matches)
- Screens captured live: Home, Work, RO overview, RO Concern, Intake, Search, Customer (error)
