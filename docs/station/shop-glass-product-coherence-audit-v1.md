# Shop Glass + Hosted Dragon — user-journey audit (Phase 0)

**Walked as:** Edward / Molly, 2026-08-23  
**Product lock:** ARK = workplace. Hosted Dragon = employee brain. Shop Glass = shared command center. arkai = fallback until off-day.  
**Starting commit:** `ace29858`

Judged **user-visible** completeness, not backend capability counts.

| # | Journey | Verdict | What happens today |
|---|---------|---------|-------------------|
| 1 | Open ARK in the morning | **WORKS** | `/app` Today / repair orders. |
| 2 | Look at Shop Glass | **PARTIAL** | Paired glass shows Floor, Needs action, Coming in from `/api/station/dashboard`. Header treated Dragon as appliance-off. |
| 3 | Ask “what needs attention?” | **PARTIAL** | Glass Attention queue works. Ask Dragon toasted / did not open hosted chat. |
| 4 | Ask “how are we doing today?” | **DEAD END** | Money tools exist on staff Sanctum `/api/dragon-agent/chat` only. Not on glass. |
| 5 | “Where are we leaving money on the table?” | **NOT EXPOSED** | Same. Hosted agent can investigate; glass could not invoke it. |
| 6 | Ask about a specific vehicle | **PARTIAL** | Queue / Coming in labels. Ask Dragon dead. ARK search works. |
| 7 | Identify that RO | **GLANCE** | Tap stays on the shared board. Do not open a browser on the glass PC. |
| 8 | Rewrite a concern/note | **WORKS** (ARK) | Rewrite → Original vs Dragon proposal → Apply / Cancel. Not silent. |
| 9 | Review an estimate | **PARTIAL** | “Review this concern / Review notes” exists; not a finished whole-estimate critique surface. |
| 10 | Schedule / inspect a return | **WRONG SURFACE** | Scheduling lives in ARK (certified). Glass must not become a scheduler. |
| 11 | Return to Shop Glass | **WORKS** | Pairing persists; dashboard refresh. |
| 12 | Follow-up question | **DEAD END** | No glass `conversation_id` overlay. |

**Auth (pre-change):** Station `stn_…` → GET `/api/station/*` (plus attention-nudge POST in routes, blocked by GET-only middleware). Hosted Dragon required staff Sanctum. Glass must not smuggle a staff PAT.

**Not a second inbox / left-rail Dragon app.** Overlay only.
