# ARK Staff — Product Reframe Audit v2

**Status:** Observation only — no Flutter implementation in this pass.  
**Canonical audit:** **`ark-staff-moments-audit-v3.md`** — moments, first 30s, thumb travel, emotional audit, NextActions. This v2 doc keeps the **screen inventory + SYS-1–6 appendix** only.

**Posture:** Architecture is good enough. Next milestone = **product**, not framework.

| Old instinct | New rule |
|---|---|
| Take one workspace to 100% | Take **every major capability to ~70%** |
| New engines, abstractions, doctrine | **One design system + connected surfaces** |
| Module tabs / CRUD screens | **Dashboards that answer: happened · needs me · next · connected** |
| Empty = honest | **Incomplete = framed** (identity, related objects, expected actions) |

**Success metric:** Edward opens ARK Staff and **instinctively prefers it over generic CRM mobile** — not because it has more features, but because it feels **purpose-built for running an automotive shop**.

---

## 1. Why generic CRM mobile feels complete while ARK feels incomplete

**Not feature parity.** UX parity.

| reference CRM feels… | Because… | ARK today… |
|---|---|---|
| **Dense** | Every pixel carries status, next action, or channel | Tab roots + Intake + Work empty states waste 30–50% viewport |
| **Connected** | Contact ↔ conversation ↔ calendar ↔ pipeline in one thumb reach | Customer ↔ RO ↔ thread requires nested stacks; search opens in wrong tab |
| **Always actionable** | Call / text / email on every record surface | Comms strong in thread + customer when loaded; absent on vehicle, schedule row, intake tile |
| **Never a dead end** | Even stub modules show sample structure | Apps grid: **9× "Soon"** snackbars; search partial types; "opens in ARK web" fallbacks |
| **One chrome language** | Same header, back, spacing everywhere | Tab AppBar + workspace AppBar + embedded faux headers (SYS-1) |
| **Persistent context** | You always know *who* you're helping | Identity strip on RO overview; **lost on Concern drill-in** |
| **Communications = heartbeat** | Inbox badge, thread preview, one-tap reply everywhere | Comms tab exists but feels like a **module**, not ambient pulse |
| **Forgiving of interruption** | Bottom sheets, inline edit, undo | Full-screen pushes; ambiguous back stack; raw error strings |

**One sentence:** reference CRM removes *decisions per task*. ARK still asks the operator to understand *navigation architecture*.

**Automotive guardrail:** Keep vehicle + customer + RO hierarchy. Adopt reference CRM's **speed patterns** (sheets, swipe, persistent actions, inline reply) — not CRM channel thinking.

---

## 2. Six system bugs (still the root cause)

From v1 — unchanged, still wearing ~30 costumes:

| ID | Bug | Operator symptom |
|---|---|---|
| **SYS-1** | No navigation shell — every screen owns `Scaffold`/`AppBar` | Double headers, triple back, "screens inside screens" |
| **SYS-2** | Identity not persistent in shell | "Whose car am I touching?" after drill-in |
| **SYS-3** | Two spacing scales (giant + cramped) | Intake tiles huge; Concern heading clips findings |
| **SYS-4** | Four card languages | Work `ArkCard` vs Schedule `Card` vs Intake outlined tiles vs Home moments |
| **SYS-5** | Command bar not context-aware | `Complete Item` on overview; duplicate `+ Finding` |
| **SYS-6** | Duplicated content on one screen | Concern text twice (display + label) |

**Do not fix 27 screens.** Fix these six once, then horizontal 70% pass.

---

## 3. Screen-by-screen audit (27 screens)

For each: **Production? · Sparse? · Dead end? · Comms visible? · Notes**

| Screen | File | Production feel | Sparse | Dead end | Comms/SIP |
|---|---|:---:|:---:|:---:|---|
| Splash | `app.dart` | OK | — | — | — |
| Login | `login_screen.dart` | OK | — | — | — |
| **Home / Orientation** | `orientation_home_screen.dart` | **Strong** | Low | No | Inline Reply on moments; call obs → customer |
| **Comms hub** | `comms_hub_screen.dart` | Good | Medium when empty | No | **Hub** — attention + threads + voice banners |
| **Work** | `my_work_screen.dart` | OK | **Yes** (tall cards) | No | None on row |
| **Intake hub** | `intake_hub_screen.dart` | OK | **Yes** (6 empty tiles) | No | None |
| **Shop / Attention** | `attention_screen.dart` | Good | When empty | Partial (rows depend on deep link) | Call/text via attention rows |
| **Schedule** | `schedule_screen.dart` | Good | Empty state OK | No | **Missing** on appointment rows |
| **More** | `more_screen.dart` | OK | Low | No | — |
| **Apps** | `apps_screen.dart` | **Weak** | Grid OK | **Yes — 9 Soon tiles** | Duplicates Comms launcher |
| **Customers** (list) | `customers_screen.dart` | OK | Search empty | No | Indirect |
| **Global search** | `global_search_screen.dart` | OK | — | Partial types | — |
| **Customer workspace** | `customer_workspace_screen.dart` | **Good when loaded** | — | Was P0 404 — **route now exists** | Call + composer + quick actions |
| **Vehicle workspace** | `vehicle_workspace_screen.dart` | Partial | Placeholder cards | Soft dead ends | **Missing** action strip |
| **RO workspace** | `repair_order_workspace_screen.dart` | **Best surface** | Overview good | Voice → "Phase 3"; web fallbacks | Thread embed + command bar |
| **Concern detail** | `concern_detail_screen.dart` | **Worst UX** | Inverted hierarchy | SYS-1 stack | None |
| **Comm thread** | `communication_thread_screen.dart` | Good | — | Unknown actions → web | Call banner, voicemail play |
| **Check-in** | `check_in_screen.dart` | Strong flow | Form dense | No | Could add text customer |
| **VIN scan** | `vin_scan_screen.dart` | OK | — | No | — |
| **Walk-around** | `vehicle_walk_around_screen.dart` | OK | — | No | — |
| **Finding capture** | `finding_capture_screen.dart` | OK | — | No | — |
| **Finding detail** | `finding_detail_screen.dart` | OK | Read-only | No | — |
| **Conversations** | `conversations_screen.dart` | OK | Hidden (Apps only) | Orphan route | Duplicate of Comms tab |
| **Owner bookend** | `owner_bookend_screen.dart` | OK | Active queue empty | Wrapped AppBar | — |
| **Owner ops report** | `owner_operational_report_screen.dart` | OK | Advisor list sparse | More → wrapper | — |

---

## 4. Dead ends & hallways (remove list)

### Hard dead ends (operator hits a wall)

| Location | Behavior | Fix direction |
|---|---|---|
| Apps: Vehicles, Inspections, Estimates, Payments, Photos, Documents, Tasks, Internal, Settings | Tap → **"Soon"** snackbar | **Frame, don't hide:** open real surface at 70% (e.g. Payments → RO payment status read-only) or remove tile until reachable |
| RO command: voice input | "Phase 3" snackbar | Hide until ready or show disabled with reason |
| Thread / RO unknown actions | "Opens in ARK web" | Deep link to web **with context**, or inline 70% action |
| Global search | Partial result types message | Finish or hide chip until live |
| Customer load failure | Raw error, no retry (P0-B pattern) | `AsyncStateView` + retry everywhere |

### Soft hallways (empty / placeholder / duplicate nav)

| Location | Issue |
|---|---|
| Apps tab | Implemented in Flutter but **not in API nav** — unreachable launcher |
| Conversations screen | Parallel to Comms hub — **two paths to same authority** |
| Intake 6-tile grid | Walk-in / drop-off / tow-in → **same check-in** — tiles pretend differentiation |
| Work empty | One line centered — no connected objects |
| Vehicle workspace | `_PlaceholderCard` for history/deferred — reads as unfinished |
| More → Bookend / Ops report | Extra AppBar wrapper on body-only screens |

### Navigation traps

| Trap | Effect |
|---|---|
| Search opens in **current tab's Navigator** | Customer from Intake stays in Intake stack |
| Work row → **Concern**, not RO overview | Inconsistent mental model |
| RO drill-in → nested full `Scaffold` | Triple back (SYS-1) |

---

## 5. Sparse screens (wasted whitespace)

| Screen | Problem | Target density |
|---|---|---|
| Intake hub | 1 hero + 6 oversized outlined tiles | 2-column compact grid; show today's intakes + appointments |
| Work (empty) | Single centered sentence | Show shop pulse: 0 assigned + link to Attention / Schedule |
| Work (populated) | ~3 cards/viewport | Compress to reference CRM-style row: identity + status + one action |
| Comms (empty) | OK copy but no adjacent context | Show schedule arrivals + missed call count |
| Customer (error) | Full screen whitespace + raw error | Branded error + retry + last customer |
| Concern | Giant H1 + clipped findings | One concern line + findings list above fold |
| Apps Soon tiles | Visual grid promises capability | Grey out **or** show framed 70% preview — never snackbar-only |

---

## 6. Consistency audit (checklist)

| Element | Variants found | Standard (proposed) |
|---|---|---|
| **AppBar height / title** | Tab label · RO # · generic "Customer" · "Concern" | Shell-owned; title = identity strip secondary line |
| **Back affordance** | Icon · text "Back to workspace" · double icon | One `Navigator.pop` owned by shell |
| **Corner radius** | 10 · 12 · 14 on tiles | `ArkTheme.radius` / `radiusSm` only |
| **Card padding** | 10 · 12 · 16 · 24 ad hoc | `space3`/`space4` via `ArkCard` |
| **Section headers** | ALL CAPS bands · `titleSmall` · display | One `ArkSectionHeader` |
| **Primary buttons** | Full-width giant · inline text · FAB | One primary scale + one tertiary |
| **List row height** | Work tall · Comms compact · Schedule medium | One `ArkListRow` density |
| **Empty states** | Icon+copy · plain text · `AsyncStateView` | Always `AsyncStateView` |
| **Error states** | Raw exception · friendly message | Friendly + retry + support id |
| **Bottom command** | RO command bar · FAB on customer · none elsewhere | **`ContactActionStrip`** on every customer/RO/vehicle surface |

---

## 7. Communications — first-class (not bolted on)

### Today

- **Strong:** Comms tab, thread, inbound SIP overlay, customer workspace composer, home inline Reply
- **Weak:** Schedule rows, Work rows, Vehicle workspace, Intake hub, Finding detail — **no ambient comms**

### Target: `ContactActionStrip` (reserved on every customer-facing surface)

Not modules — one horizontal strip, same order, same icons:

| Action | v1 ship | v2 ship |
|---|---|---|
| Call | Native / in-app / callback | ✓ |
| Text | Composer sheet | ✓ |
| Voicemail | Play from timeline | List badge when unread |
| Photo | Finding / walk-around | Attach to thread |
| Navigate | Maps to shop/customer | Address from customer |
| Payment | Send payment link | Read balance + link |

**Rule:** Disabled state + label beats missing control. "Payment — balance $240" beats no button.

### Desktop parity gap (mobile still missing)

- Calls Waiting queue as triage surface (Attention projection)
- Mark call handled
- Send estimate / inspection link from **every** thread entry point (not only RO embed)

---

## 8. Horizontal 70% — capability matrix

Every row must **exist, feel intentional, connect** — not perfect.

| Capability | ~70% definition | Current ~% | Gap |
|---|---|---:|---|
| Home / Today | Moments + inline actions | 75 | — |
| Work / RO production | Workspace + inspection + findings | 70 | Drill-in UX |
| Comms | Hub + thread + inbound voice | 65 | No queue; orphan Conversations |
| Intake / check-in | Full walk-in flow | 70 | Sparse hub |
| Customer / vehicle | Workspace + identity | 60 | Vehicle placeholders |
| Schedule | Day view + status | 55 | No comms on row |
| Search | All entity types | 50 | Partial types |
| Attention / shop | Manager triage | 65 | — |
| Payments | View balance + send link | 30 | Apps Soon |
| Estimates | View + send link | 40 | Web fallback |
| Photos / docs | View on RO | 45 | Apps Soon |
| Settings / profile | Appearance + sign out | 40 | Settings Soon |

---

## 9. Proposed redesign order (product pass, not architecture)

**Phase 0 — Screenshot board (mandatory)**  
Capture all 27 screens @ 390×844 + tablet width. Lay out on one board. Mark SYS-1/2/4 violations in red. **No code until board review.**

**Phase 1 — Shell (unblocks everything)**  
1. `WorkspaceShell` — one AppBar, one back, persistent `IdentityStrip`  
2. Sub-views as **bodies or bottom sheets** — never nested `Scaffold`  
3. Global search results open in shell navigator, not tab navigator  

**Phase 2 — Design system enforcement**  
4. `ArkCard` + `ArkSection` + `ArkListRow` — migrate tab roots (Work, Intake, Schedule)  
5. `AsyncStateView` everywhere — kill raw errors  
6. Context-aware command bar (SYS-5)  

**Phase 3 — Remove hallways (horizontal 70%)**  
7. Replace Apps **Soon** tiles with framed surfaces or remove  
8. `ContactActionStrip` on Customer · Vehicle · RO · Schedule row  
9. Merge Conversations → Comms (one path)  
10. Intake hub density + show live queue  

**Phase 4 — reference CRM speed patterns (automotive)**  
11. Bottom sheets: Concern, Finding, Log call  
12. Swipe Work row: Assign · Message · Open RO  
13. Optimistic assign + undo snackbar  

**Phase 5 — Owner / manager**  
14. Bookend + ops report use same shell  
15. Attention → always lands in meaningful workspace  

---

## 10. Mobile-first reality checklist

Edward: beside vehicle, flashlight, gloves, interrupted every 20s.

| Requirement | Status |
|---|---|
| Primary action thumb-reachable | Partial (command bar bottom — good) |
| 48×48 targets | Theme defined; chips/buttons often smaller |
| Sunlight contrast | Muted tier improved in theme; not verified all screens |
| One-handed back | Broken when triple-back |
| Resume after interrupt | Push + continuity doctrine exists; shell must preserve identity |
| No desktop squeezed into phone | Concern display heading = desktop habit |

---

## 11. reference CRM pattern → ARK adoption map

| reference CRM pattern | ARK automotive adoption |
|---|---|
| Persistent record header | `IdentityStrip`: Customer · Vehicle · RO status |
| Bottom sheet actions | Concern / finding / call log over workspace |
| Swipe triage | Work + Attention rows |
| Inline reply | Already on Home — extend to Comms list |
| Unified inbox badge | Comms tab badge from attention count |
| Quick actions row | `ContactActionStrip` |
| Search omnibox | Finish entity types; open in shell |
| Pipeline stages | RO lifecycle chips (already) — not CRM stages |

---

## 12. Screenshot board protocol (do this next)

1. **Device:** iPhone 14 Pro logical size + one Android mid-size.  
2. **Account:** Production or staging with real Demo Auto Repair data (not empty tenant).  
3. **Role:** Capture as **Advisor** (Edward) — full tab set.  
4. **States per screen:** Populated · Empty · Error (where safe).  
5. **Layout:** Figma/FigJam or printed grid — rows = capability, columns = screen state.  
6. **Annotate:** Red = dead end; Yellow = sparse; Blue = inconsistency; Green = reference surfaces (RO overview, Home).  

**Reference surfaces to clone:** RO workspace Overview · Home moments · Customer workspace (loaded).

---

## 13. Relation to v1 audit

- `docs/mobile/ark-mobile-ux-audit-v1.md` — live walkthrough + SYS-1–6 + P0 list  
- **P0-A customer route** — fixed (`GET /api/mobile/customers/{customer}` in `routes/api.php`)  
- **This v2 doc** — milestone reframe, horizontal 70%, hallway removal, comms-first, reference CRM UX study, screenshot gate  

**Next step:** Screenshot board review with Edward → then Phase 1 shell implementation in `ark-mobile` only.
