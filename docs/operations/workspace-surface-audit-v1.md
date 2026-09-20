# ARK Workspace Surface Audit v1

**Status:** Phase 1 implemented — pending local click-through before commit  
**Constitution:** [workspace-constitution-v1.md](../ecosystem/workspace-constitution-v1.md)  
**Frame:** Workspace contract, not route catalog  
**Primary question:** Does this deserve to exist?

## Survival test

Every surviving **page** must complete:

> **This page exists because** [one specific job] that [one role] performs [how often], and that job cannot be done inside [named primary workspace] without breaking scan rhythm or workflow continuity.

If two surfaces share the sentence honestly → merge. If the sentence is vague → panel or delete.

## Unit types

| Type | Rule |
|------|------|
| **Page** | Daily or weekly destination — may earn left rail or front door |
| **Panel** | Contextual work inside a workspace — row, card, tab, drawer |
| **Projection** | Read-only truth rendered elsewhere — never its own nav item |
| **Admin** | Settings — visited rarely, never competes with daily rail |

## Role × primary workspace

| Role | Primary workspaces | Never primary |
|------|-------------------|---------------|
| **Advisor** | Work (`/app`), Communications (Attention), Intake, RO | Global RO index, customer/vehicle search rails, Inbox, History, Growth, Voice |
| **Technician** | Today, Workboard (production lens), RO (assigned) | Comms queue, Leads, Customers, Reports, Settings |
| **Owner** | Day Review, Reports | Day-to-day RO, comms triage |
| **Customer** | Portal | Any ops surface |
| **Admin** | Settings | Daily rail |

## Handoff rule (Cursor)

Before any surface ships or survives a prune pass, write its survival sentence. If another surface already owns that sentence, the new thing must be a **panel or projection** — not a route, nav tab, or rail link.

---

## Phase 1 targets (navigation only)

**REVISED 2026-07-03:** Call library, Inbox, History, and Leads were incorrectly removed before observation. **Do not repeat.**

1. Remove from left rail only after observation: Website, Growth, Voice (settings), Repair Orders index — not Leads/Customers/Vehicles without proof.
2. Communications section nav must include: **Needs Attention**, **Inbox**, **History**, **Calls & VM**, Internal (if floor uses it).
3. Redirects allowed only for true duplicates: `workboard` → `attention`, `conversations/reply` → `attention?conversation=` — **not** inbox, history, or calls.

### Protected — never remove without explicit owner request

- `operations.communications.calls` (Calls & VM library — recordings, voicemail, missed calls)
- Calls & VM section nav link
- See `.cursor/rules/ark-comms-call-surfaces-lock.mdc`

---

## Full inventory

Legend: **Rec** = Keep page · **Rail−** = Remove from rail · **Panel** = Demote to panel · **Proj** = Projection only · **Admin** = Settings · **Del** = Delete/redirect · **Ctx** = Contextual page (no rail)

| Route | Workspace | Role | Unit | In rail? | Survival sentence (pass/fail) | Honest duplicate | Rec | Conf | Phase |
|-------|-----------|------|------|----------|------------------------------|------------------|-----|------|-------|
| `/app` | Work | Advisor | Page | Yes | **Pass** — advisor scans all active floor work before choosing an RO. | — | Keep | High | — |
| `/app/workboard` | Work | Tech | Page | Tech only | **Pass** — production staff see lane status when not in one RO. | `/app` (advisor lens redirects) | Keep lens | High | — |
| `/app/today` | Today | Tech | Page | Production | **Pass** — tech sees today's assigned production posture before bays. | `/app` for advisors (different role) | Keep | High | — |
| `/app/briefing` | Today | — | Redirect | — | Legacy alias. | `/app/today` | Redirect | High | 1 |
| `/app/communications/attention` | Comms | Advisor | Page | Yes | **Pass** — recover missed customer contact since last shift in pressure order. | Topbar interrupt (different job: live) | Keep | High | — |
| `/app/communications/inbox` | Comms | Advisor | Page | Section nav | **Fail** — "all conversations" same as Attention + Hub + RO rail. | Attention | Panel/Del | High | 1 |
| `/app/communications/history` | Comms | Advisor | Page | Section nav | **Fail page** — search is real; archive belongs in Attention search panel. | Attention thread, RO timeline | Panel/Del | High | 1 |
| `/app/communications/workboard` | Comms | Advisor | Page | No | **Fail** — same recovery triage job as Attention. | Attention | Del | High | 1–2 |
| `/app/communications/calls` | Comms | Advisor | Page | No | **Borderline** — call/VM library; job is subset of Attention Calls lane. | Attention, Calls Waiting | Panel | Med | 2 |
| `/app/communications/internal` | Comms | Advisor | Page | Section nav | **Pass** — shop coordination channels distinct from customer recovery. | — | Keep if used | Med | Observe |
| `/app/communications/internal/{slug}` | Comms | Advisor | Panel | — | Channel thread inside Internal workspace. | — | Keep | Med | — |
| `/app/communications` | Comms | — | Redirect | — | → Attention | Attention | Redirect | High | — |
| `/app/communications/queue` | Comms | — | Redirect | — | → Attention | Attention | Redirect | High | — |
| `/app/communications/attention-queue` | Comms | — | Redirect | — | → Attention | Attention | Redirect | High | — |
| `/app/conversations/{id}/reply` | Comms | Advisor | Page | Deep links | **Fail** — reply belongs in workspace thread composer. | Attention thread panel | Del | High | 1 |
| `/app/intake` | Intake | Advisor | Page | Yes | **Pass** — convert walk-ins/calls to ROs at counter without opening RO first. | — | Keep | High | — |
| `/app/intake/new` | Intake | Advisor | Page | Intake flow | **Pass** — guided create path for new visit. | Intake index | Keep | High | — |
| `/app/intake/customers/search` | Intake | Advisor | Panel | — | Search step inside intake — not a destination. | Global customer search | Panel | High | — |
| `/app/intake/customers/{customer}` | Intake | Advisor | Panel | — | Recognition step in intake flow. | Customer Hub | Panel | High | — |
| `/app/intake/customers/duplicates` | Intake | Advisor | Panel | — | Dedup guard in intake. | — | Keep | High | — |
| `/app/intake/vehicles/lookup` | Intake | Advisor | Panel | — | VIN/plate lookup in intake. | — | Keep | High | — |
| `/app/leads` | Intake/Comms | Advisor | Page | Yes | **Borderline** — unsold inbound; Attention projects leads. | Attention lead rows | Rail− | Med | Observe |
| `/app/leads/{lead}/intake` | Intake | Advisor | Ctx | Leads | Bridge lead → intake. | Intake | Keep | High | — |
| `/app/leads/{lead}/create-contact` | Intake | Advisor | Ctx | Leads | Convert lead to customer. | Intake | Keep | Med | — |
| `/app/ingress/create-contact` | Intake | Advisor | Ctx | Calls/pop | Unmatched caller → customer. | Intake | Keep | Med | — |
| `/app/repair-orders/{ro}/edit` | RO | Adv/Tech | Page | RO entry | **Pass** — perform, document, move one repair. | — | Keep | High | — |
| `/app/repair-orders/{ro}` | RO | Advisor | Page | RO entry | **Pass** — estimate review / approval posture for one RO. | RO edit (mode) | Keep | High | — |
| `/app/repair-orders/{ro}/estimate-review` | RO | Advisor | Redirect | — | Alias of show/review. | `/repair-orders/{ro}` | Merge | High | — |
| `/app/repair-orders/{ro}/inspection` | RO | Tech | Page | RO tab | **Pass** — inspection evidence on assigned work. | RO production tab | Keep | High | — |
| `/app/repair-orders/{ro}/estimate` | RO | Advisor | Ctx | RO actions | Estimate document view/print path. | RO review | Keep | High | — |
| `/app/repair-orders/{ro}/workspace-tabs/{tab}` | RO | Adv/Tech | Panel | Tabs | AJAX tab bodies — not standalone destinations. | RO worksheet | Panel | High | — |
| `/app/repair-orders/{ro}/portal-*-preview` | RO | Advisor | Panel | Send actions | Portal preview before customer send. | Portal | Panel | High | — |
| `/app/repair-orders/{ro}/partstech`, labor-guides, rte-labor | RO | Advisor | Panel | Line tools | External catalog / guide — tool panels. | — | Panel | High | — |
| `/repair-orders` | Work | Advisor | Ctx | Yes | **Fail rail** — full RO inventory overflow from workboard. | Workboard filtered view | Rail− | High | 1 |
| `/app/customers/{customer}` | Customer | Advisor | Ctx | No | **Pass ctx** — relationship context across vehicles/ROs/channels. | RO customer rail (scoped) | Ctx | High | — |
| `/app/customers/search` | Customer | Advisor | Overlay | Yes | **Fail page** — lookup is search, not a workspace. | Global search (future) | Rail− | High | 1 |
| `/app/vehicles/search` | Customer | Advisor | Overlay | Yes | **Fail page** — same as customer lookup. | Customer Hub | Rail− | High | 1 |
| `/app/caller-lookup` | Comms | Advisor | Panel | Call pop | Match caller mid-flow. | Intake, Hub | Panel | High | — |
| `/app/work/queues/{queue}` | Work | Advisor | Page | **None** | **Fail** — tasks/follow-ups/decisions live on `/app` zones. | `/app` | Del | High | 2 |
| `/app/work/queues/comms` | Comms | — | Redirect | — | → Attention | Attention | Redirect | High | — |
| `/app/appointments` | Intake | Advisor | Ctx | No | **Borderline page** — schedule truth; should open from Intake/Today. | Intake, Today | Panel | Med | 2 |
| `/app/appointments/create`, `{appointment}` | Intake | Advisor | Ctx | Schedule | CRUD inside scheduling job. | — | Ctx | Med | 2 |
| `/app/reports` | Owner | Owner/Adv | Page | Yes | **Pass** — closed-loop financial/ops truth weekly+. | Day Review (different: EOD queue) | Rail cap | Med | Observe |
| `/app/reports/operations`, `end-of-day` | Owner | Owner | Page | Reports tabs | Same job as reports hub. | Reports index | Keep | Med | — |
| `/app/owner/day-review` | Owner | Owner | Page | Yes | **Pass** — close day against queue truth + tomorrow's move. | Reports | Keep | High | — |
| `/app/owner/call-intelligence*` | Owner | Owner | Admin | No | Coaching/analytics — not daily ops. | Reports | Admin | Med | — |
| `/app/owner/parts-matrix-tune` | Owner | Owner | Admin | No | Matrix tuning — settings-adjacent. | Settings | Admin | Med | — |
| `/app/owner/staff/{user}/coaching` | Owner | Owner | Ctx | Call intel | Staff coaching detail. | Call intelligence | Ctx | Med | — |
| `/app/settings/shop` | Admin | Admin | Page | Yes | **Pass** — shop behavior configuration. | — | Keep | High | — |
| `/app/shop/communications` | Admin | Admin | Page | **Yes (Voice)** | **Fail rail** — provision phones/stations. | Settings → Comms | Rail− | High | 1 |
| `/app/shop/devices/{device}` | Admin | Admin | Admin | Settings flow | Device provision detail. | — | Admin | High | — |
| `/app/shop/people/{user}` | Admin | Admin | Admin | Settings | Extension/people mapping. | — | Admin | High | — |
| `/app/staff` | Admin | — | Redirect | — | → Settings staff section | Settings | Redirect | High | — |
| `/app/growth/*` | Growth | Marketing | Page | Yes | **Fail ops rail** — marketing ops ≠ shop floor. | Separate product entry | Rail− | High | 1 |
| `/app/website/*`, `/website/*` | Admin | Admin | Page | Yes | **Fail rail** — website management. | Settings | Rail− | High | 1 |
| `/app/learn/*` | Training | All | Page | Yes/Ext | Training — user menu, not beside Intake. | ARKademy external | Rail− | Med | 2 |
| `/app/operations/observations` | Admin | Engineering | Admin | No | Vocabulary debug — not operational. | — | Admin | High | — |
| `/app/display` | Work | Shop | Page | No | **Pass** — wall display mode for floor TV. | Workboard | Keep | Med | — |
| `/app/telephony/.../recording` | Comms | Advisor | Panel | Thread | Playback — panel in thread/history search. | History search | Panel | High | — |
| `/app/conversations/.../attachments/{attachment}` | Comms | Advisor | Ctx | Thread | Media delivery — not a workspace. | — | Keep | High | — |

### Portal (separate surface — same audit rules apply)

| Route pattern | Workspace | Role | Unit | Survival sentence | Rec |
|---------------|-----------|------|------|-------------------|-----|
| `/portal/estimates/{token}` | Portal | Customer | Page | Customer approves/decides on estimate without staff. | Keep |
| `/portal/pay/{token}` | Portal | Customer | Page | Customer pays balance on issued invoice. | Keep |
| `/portal/*` (inspection, history) | Portal | Customer | Page/Ctx | Customer self-service scoped to token. | Keep |

Portal not duplicated in ops rail inventory; prune rule is the same sentence test.

---

## Cluster diagnosis

| Cluster | Core issue |
|---------|------------|
| Communications | Four pages, one job — recovery triage |
| Work / Today / Workboard | Three "start here" surfaces; role lenses not enforced in nav |
| Customers / Hub / RO | One relationship authority; hub + RO rail are projections; search is overlay |
| Growth / Website / Voice | Admin capabilities on daily advisor rail |
| Reports | Legitimate; wrong default audience on advisor shell |
| Work queues | Orphan pages — no nav links; superseded by `/app` |

**Disease:** capability shipped → surface persisted → better workspace absorbed job → old surface not pruned.

---

## Observation gates (before Phase 2 deletes)

| Question | Notebook |
|----------|----------|
| Do advisors open Leads directly, or only from Attention? | |
| Is Internal used weekly? | |
| Do advisors use History search, or Customer Hub / RO timeline? | |
| After rail prune, any support tickets for "can't find customers"? | |

Phase 2 blade deletion only after redirect soak + floor silence.

---

## Phase 1 completion (click-through)

**Do not commit until local validation passes.** Phase 1 is easy to agree with in a diff; the question is whether it feels better after 15 minutes of actual shop work.

Use a fresh browser profile so old bookmarks to `/communications/inbox` do not mask redirects.

### Test 1 — Morning advisor flow (highest priority)

Pretend you're opening the shop. Pass if you can do this **without hesitation**:

1. Open ARK
2. See what needs attention
3. Reply to a customer
4. Open the RO
5. Call the customer
6. Return to Work

**Never wonder where to click next.** If you hesitate, that is the signal — not the route inventory.

### Test 2 — Technician flow

Can a technician spend an hour in ARK without noticing the rail changed? Advisor cleanup should not make production navigation feel unfamiliar.

### Test 3 — Search flow (critical dependency)

Rail removed: Customers, Vehicles, RO index. **Search is now navigation.**

Repeat three times: *"Jane Smith just called. Find her."*

Pass: global search (or equivalent) is **faster than** Work → hunt → guess. Fail: note for Phase 1.5 — do not restore rail links without repeated evidence.

**Known Phase 1 gap:** archive search UI on Attention is Phase 2; `archive=1` redirect may feel inert until then.

### Test 4 — Attention

Everything redirects into Attention — it is now the comms operating system. Verify:

- Archive mode feels intentional (when built), not "history with a filter"
- Internal does not feel bolted on
- Thread switching stays fast
- Composer focus is obvious (`#conversation-composer` on reply redirect)
- Returning from an RO lands where you expect

### Test 5 — Missing page test (20 minutes)

If you think *"I wish there were a page for…"* — write it down. Do not build it. See if the feeling repeats over days.

**Prediction:** the notebook fills with interaction problems (*search here*, *panel stayed open*, *one click too many*), not information architecture problems (*need another page*). That means the workspace model is working.

### Test 6 — Interruption recovery

Shops do not operate on happy paths. ARK is interruption-driven.

| Interrupt | Expected recovery |
|-----------|-------------------|
| Customer calls while editing an RO | Answer, open caller, return to **same RO and scroll position** |
| New SMS while writing estimate | Finish estimate without losing work; recover message naturally after |
| Technician asks a question | Jump to RO, answer, return to **previous thread** without hunting |
| Browser Back × 3 | Never land on obsolete surface or redirect loop |
| Three ROs in separate tabs | Context obvious in every tab |

Pass: **recover without rebuilding your mental model.**

---

## Observation notebook (structured)

When friction appears during click-through or the Observation Sprint, record in [floor-observations-july-2026.md](floor-observations-july-2026.md):

| Field | Example |
|-------|---------|
| **Time** | 2026-07-03 08:14 |
| **Role** | Advisor |
| **Situation** | Customer called asking about estimate |
| **Expected** | Open customer immediately |
| **Actual** | Work → RO → search |
| **Root cause** | No universal search entry point |
| **Candidate fix** | *(optional — one line max; not a spec)* |

---

## Observation Sprint (after Phase 1 commit)

**Duration:** 2 weeks  
**Notebook:** [floor-observations-july-2026.md](floor-observations-july-2026.md)

Use ARK. Watch it. Learn from it — not "don't touch anything."

| Allowed | Not allowed |
|---------|-------------|
| Bug fixes | New pages |
| Interaction polish | New tabs |
| Performance | New rail items |
| Accessibility | New workspaces |
| Redirect corrections | |

**Exceptions:** must satisfy Law 5 — *What existing surface became simpler because this now exists?*

**Success metric:** Did anyone on the floor get lost? If no — the product got easier to think with.

Prioritize Phase 1.5 only after ~30–50 notebook entries cluster.

---

## Phase 1.5 candidates (from notebook, not roadmap)

Likely priorities after rail prune — only ship when notebook clusters:

- **Global search** — Law 7 infrastructure (entities in ≤3 interactions)
- **Panel persistence** — context that should stay open across thread/RO hops
- **Attention archive panel** — intentional history search, not redirect stub
- **One-click reductions** — actions that survived merge but still cost a hunt

---

## What v1 does not cover (path to 20/20)

1. **Floor-validated survival sentences** — table is code-informed, not observation-proven.
2. **ARK Mobile** — same sentence test per shell tab/API destination.
3. **Explicit panel target** per demotion (which workspace hosts the panel).
4. **Prune metrics** — weekly count of nav items used (instrumentation).

---

## Related doctrine

- `ark-attention-queue.mdc` — Attention is projection, not parallel inbox authority
- `ark-cursor-doctrine.mdc` — Attention → Work → RO primary flow
- `ark-technician-scope.mdc` — technician never primary on comms/queues
- `docs/communications/communications-workspace-sprint-v1.md` — sprint that shipped Attention without retiring siblings
