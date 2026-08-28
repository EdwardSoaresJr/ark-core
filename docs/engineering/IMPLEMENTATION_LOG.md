### 2026-08-28 — Backfill estimate companions from closed tickets

**PR:** (local)

**Files:** `BackfillEstimateCompanionPatternsAction`; `ark:estimate-companions:backfill`; `SeedEstimateCompanionPatterns`; Pest

**Reason:** Catalog was forward-only from sent estimates. Historical closed/posted labor+part tickets should teach companions once.

**Architecture impact:** Disposable catalog. `--fresh` resets observed rows and re-seeds timing fluids before ingest.

**Outstanding questions:** Watch noisy unique SKU companions after first production backfill.

---



**PR:** (local)

**Files:** `estimate_companion_patterns` migration; `EstimateCompanionCompletenessProjection`; `LearnEstimateCompanionPatternsAction`; send/email ingest; Dragon `estimates.get` copy

**Reason:** Timing oil/coolant should not stay a hardcoded regex. The shop catalog starts from that floor miss and learns other labor+part companions from sent tickets. Continue-anyway weakens a pattern.

**Architecture impact:** Projection over disposable catalog rows. Authority remains estimate lines.

**Outstanding questions:** Watch noisy part companions (unique SKUs) on the floor.

---



**PR:** (local)

**Files:** `TimingJobCompanionFluidProjection.php`; `RepairOrder::ensureEstimateSendAllowed`; send-estimate / email / schedule payload; estimate totals banner; Quick Reply / portal / email warnings; `EstimatesGetTool`; `DragonEmployeeContext`; Pest coverage

**Reason:** Floor miss (RO1701-class): timing job presented without oil and coolant (~$100). Catch on the estimate and in Dragon before the customer sees it.

**Architecture impact:** Disposable projection. Acknowledge override like missing VIN. Dragon does not write lines.

**Outstanding questions:** Observe whether more jobs earn companion-fluid rules.

---



**PR:** (local)

**Files:** `InitiatePortalEstimateDepositAction.php`; `PortalEstimateDepositProjection.php`; portal estimate pay partials; `PortalInvoicePayShowController.php`; `PortalSmsLinkBody.php`; `PortalEstimateAuthorizationTest.php`

**Reason:** After a deposit was on file, the customer estimate page hid Square and blocked a second charge. Remaining owe-today is now payable in the portal the same way as the first deposit.

**Architecture impact:** Still ledger deposits pre-invoice. Suggested deposit remains first-wave cap.

**Outstanding questions:** None.

---

### 2026-08-25 — Additional deposits after suggested deposit is on file

**PR:** (local)

**Files:** `RepairOrderDepositRecordingGuard.php`; `RepairOrderFinancialPresenter.php`; financial payment strip / Square / manual deposit partials; mobile deposit projections; `DepositPortalLinkContext.php`; Pest deposit tests; `ACTIVE_PR.md`

**Reason:** A second deposit (pay-off while work continues) was blocked once shop suggested deposit was covered. Remaining owe-today is now collectable without issuing a final invoice.

**Architecture impact:** Suggested deposit remains the first-collection cap. After it is covered, deposits cap at Financial Position owe-today. Ledger authority unchanged.

**Outstanding questions:** None.

---

### 2026-08-24 — ARK Desk first vertical (Glass parked)

**PR:** (local)

**Files:** `apps/ark_desk` · `/api/desk/*` · `DeskWorkProjection` · `ArkDeskApiTest` · milestone/docs realignment

**Reason:** A shared 1920×1080 Glass cannot own personal calls, Dragon, or microphone. Desk is the personal Windows advisor command center.

**Architecture impact:** Shop Glass parked, not deleted. Staff Sanctum for Desk. Hosted Dragon unchanged. ARK Tech unchanged.

**Outstanding questions:** Windows Release build requires a Windows machine.

---

### 2026-08-24 — Hosted Dragon Level 3 controlled memory

**PR:** (local)

**Files:** `dragon_agent_memories` scope columns · `RecallDragonMemory` · `StoreDragonMemory` · `ForgetDragonMemory` · `HandleDragonMemoryIntent` · `memory.propose` · Settings Dragon Memory · `DragonMemoryLevel3Test`

**Reason:** Durable memory must be teachable, scoped to company/station/user, recall-on-demand, correctable, and forgettable without injecting every fact into the system prompt.

**Architecture impact:** Memory stays in ARK MySQL. Intra-tenant place is `workstations` (ARK has no Location table). No `shop_id`. OpenAI remains replaceable. No second agent.

**Outstanding questions:** Geographic multi-site Location authority still does not exist; station memory is workstation-scoped until that authority exists.

---



**PR:** (local)

**Files:** `DragonEmployeeContext.php` · `DragonHostedAgentTest.php`

**Reason:** Ask Dragon answered “what’s ugly” as a numbered KPI brief. Voice now leads with one floor sentence and forbids consultant decks.

**Architecture impact:** None. Tools and evidence rules unchanged.

**Outstanding questions:** None.

---

### 2026-08-24 — Dragon memory.recall is in the hosted tool set

**PR:** (local)

**Files:** `DragonHostedAgentTest.php`

**Reason:** Cover that hosted chat can recall a taught shop standard (alternator) through `memory.recall`.

**Architecture impact:** None.

**Outstanding questions:** None.

---

### 2026-08-24 — Common Job leads the estimate compose row

**PR:** (local)

**Files changed:** `repair-order-concern-work-section.blade.php`, `app.css`, `RepairOrderBuilderWorkspaceModalTest`.

**Reason:** Splitting Common Job away from Labor/Part/Note broke the compose row. The buttons are one group again, with Common Job first.

**Architecture impact:** None.

**Outstanding questions:** None.

---

### 2026-08-24 — Dragon uses the shop clock, not model calendar

**PR:** (local)

**Files:** `DragonEmployeeContext.php` · `ShopFinancialSnapshotTool.php` · `DragonHostedAgentTest.php`

**Reason:** Glass Ask Dragon treated August 2026 as the future after “this month,” because the model’s training cutoff overrode the shop date. The financial tool also only returned today.

**Architecture impact:** None. Operational Report metrics stay the authority. Dragon now prints the shop clock in the employee prompt and can snapshot today, MTD, or a named month on or before that clock.

**Outstanding questions:** None.

---

### 2026-08-24 — Glass Dragon continues the live station thread

**PR:** (local)

**Files:** `ChatDragonAgentAction.php` · `DragonEmployeeContext.php` · `DragonAgentLoop.php` · `AdvisorStationApiTest.php`

**Reason:** Ask Dragon could look like an active chat while each Ask started a new hosted conversation when `conversation_id` was missing. Follow-ups then had no prior turns.

**Architecture impact:** None. Station tokens still own Glass conversations. Glass now resumes the latest conversation for that station token, stores the operator’s actual words, and skips fast-fact shortcuts after the first turn.

**Outstanding questions:** None.

---

### 2026-08-24 — Glass Ask Dragon keeps the station thread

**PR:** (local)

**Files:** `ask_dragon_sheet.dart` · `main.dart` · `station_settings.dart` · `prefs_station_settings.dart` · `widget_test.dart`

**Reason:** Closing Ask Dragon disposed `conversation_id`, so the next Ask started a new hosted conversation and Dragon appeared to forget what it was told.

**Architecture impact:** None. Station Dragon still writes `DragonAgentConversation` keyed by station token. Glass now holds the thread on the shell and prefs so reopen and restart continue the same conversation.

**Outstanding questions:** None.

---

### 2026-08-24 — Glass callback status stays in context

**PR:** (local)

**Files:** `main.dart` · `widget_test.dart`

**Reason:** Callback claim confirmation used a transient bottom snackbar, detached from the shared command-center context.

**Architecture impact:** None. CallSession ownership and handled state are unchanged; Glass now shows the confirmation inline and preserves the claim → Calls → Handled loop.

**Outstanding questions:** None.

---

### 2026-08-24 — Shared advisor work stays centered on Glass

**PR:** (local)

**Files:** `desk.dart` · `widget_test.dart`

**Reason:** On the two-advisor shared display, genuinely shared work belongs physically between the two advisor-owned lanes.

**Architecture impact:** None. Existing task ownership remains authoritative; only the Glass lane projection order changed.

**Outstanding questions:** None.

---

### 2026-08-24 — Saved Work leads Estimate Builder actions

**PR:** (local)

**Files:** `repair-order-concern-work-section.blade.php` · `app.css` · `RepairOrderBuilderWorkspaceModalTest`

**Reason:** Saved Work was the last action on the right even though it is the fastest path for adding established shop work.

**Architecture impact:** None. Existing Saved Work authority and modal flow are unchanged; only action order and alignment changed.

**Outstanding questions:** None.

---

### 2026-08-24 — New RO carries its source RO reference

**PR:** (local)

**Files:** `AdvisorIntakeCreateController` · `operational-identity-band-document.blade.php` · `RepairOrderMentionTest` · `WorkspaceCommandLanguageTest`

**Reason:** Opening New RO from an existing repair order required the advisor to manually type the prior `@RO` reference.

**Architecture impact:** The New RO link carries the source repair order ID. Intake verifies the same customer and vehicle, then preloads `Previous RO: @RO####` into the visit reason. The existing plain-text mention remains authority.

**Outstanding questions:** None.

---

### 2026-08-24 — Estimate builder Generate is clickable

**PR:** (local)

**Files:** `ark-dragon-service-advisor.js` · `present-panels.blade.php` · `workspace-modal.blade.php` · `ark-workspace-modal.js` · `app.css` · `RepairOrderBuilderWorkspaceModalTest`

**Reason:** Generate rewrite stayed grey on line notes because the nested Alpine panel never read the workspace modal `lineId`. Empty-field gating hid the same button on concerns with no saved narrative yet. Floor label is Generate.

**Architecture impact:** Dragon rewrite authority unchanged. The builder only reads existing modal context and estimate notes.

**Outstanding questions:** None.

---

### 2026-08-24 — ARK Tech dynamic brake DVI renderer

**PR:** (this slice)

**Files:** `TechDviTaskProjector` · `TechSchemaSpeechParser` · `TechInspectionShowController` · `apps/ark_tech` `dvi_slice_page.dart` · `appearance.dart` · Tech/widget tests

**Reason:** The first Tech DVI screen was a CRUD form over InspectionItem (two progress counters, pad fields on axle-type items, Fill fields from text, Dragon-branded actions). Technicians inspect; shops configure templates.

**Architecture impact:** No DVI v2 tables. GET `/api/tech/.../inspection` now projects living records plus an `interaction` kind derived from measurement slots / axle gate / condition options. Voice parses against the current item's slots only. Appearance follows `users.display_theme` / accent from login. Brake slice still opens from My Work → RO; renderer is not brake-ID-specific.

**Outstanding questions:** Floor-cert the new Save & Next / hold-to-talk path on the shop tablet. Grouped multi-item screens wait until a template uses `builder_meta.group`.

---

### 2026-08-24 — Shop Glass missed-call desk vertical

**PR:** (this slice)

**Files:** `StationGlassDeskProjection` · `StationGlassCallHandleController` · `desk.dart` · station dashboard `desk` payload · Flutter Desk home

**Reason:** Screens exist to finish front-counter moments. First loop: missed call → customer workspace → Return call claims work without `worked_at` → Handled sets `worked_at` → desk clears. Today/Shop/Calls remain scaffolding.

**Architecture impact:** Unclaimed missed calls project into Shared; ownership is `advisor_tasks.assigned_user_id`. No station dialer (none exists). Unknown callers stay unknown.

**Outstanding questions:** Floor question — would Molly rather handle the next missed call here?

---

### 2026-08-24 — Shop Glass product correction (application, not AI dashboard)

**PR:** (local — not shipped)

**Files:** `StationDashboardProjection` · `StationCallsProjection` · `StationGlassConfig` · `StationGlassTasksProjection` · `StationGlassRepairOrderProjection` · station settings/task routes · `AdvisorTasksQueryTool` · `apps/advisor_station` Today/Shop/Calls/RO workspace · Pest + Flutter 1920×1080 tests

**Reason:** Floor screenshots showed an AI-dashboard shell, placeholder Calls copy, and an RO card that sent advisors back to a browser. Glass is a shared 1920×1080 application.

**Architecture impact:** Reused `advisor_tasks` as Glass TODOs (assigned vs shared). Call facts from `CallSession` / `CallSessionQueue`. Station `glass_config` on the device token. Completing a task does not change RO status. No browser launch on the production Glass path.

**Outstanding questions:** Live 1920×1080 station certification (Today / Shop / Calls / RO / Ask Dragon / Dragon down) still required before calling this complete.

---

### 2026-08-24 — Shop Glass live surface (no Refresh babysitting)

**PR:** (local)

**Files:** `StationGlassCallProjection` · `StationGlassCallShowController` · `apps/advisor_station` poll/freshness/Shop filter/RO layout/Calls context

**Reason:** Screenshots still read as a dashboard with a permanent Refresh button, duplicate Shop lists, and empty RO/Call panes.

**Architecture impact:** Quiet 8–15s snapshot polling (no OpenAI on poll). Shop is one filterable `repair_orders.items` list. Calls GET `/api/station/calls/{id}` projects caller context. Missed call can create an advisor TODO. Manual Retry only when ARK is interrupted.

**Outstanding questions:** Physical 1920×1080 floor cert still required.

---

### 2026-08-24 — Dragon rewrite on customer estimate portal

**PR:** (this ship)

**Files:** `portal/partials/_estimate-concern-card.blade.php` · `PortalEstimateSnapshot` · `DragonServiceAdvisorTest`

**Reason:** Applied Dragon rewrites updated the concern and the PDF snapshot, but the customer portal card only printed the concern title. Findings and recommendations never appeared, so rewrite looked like it did not land.

**Architecture impact:** None. Portal still projects the customer estimate snapshot. Cards now render the same narrative the PDF already had.

**Outstanding questions:** None.

---



**PR:** (this ship)

**Files:** `CompleteHostedDragonAssistAction` review/recall JSON schemas · `OpenAiDragonProvider` error body · `tests/Unit/Dragon/OpenAiStrictJsonSchemaTest.php`

**Reason:** Review this concern posted a strict `json_schema` that listed optional keys (`customer_readiness`, proposal `concern_id` / `line_id` / `reason`) without putting them in `required`. OpenAI rejects that with HTTP 400. Advisors saw `hosted_unavailable`.

**Architecture impact:** None. Same hosted Dragon path. Schema now matches OpenAI strict rules (every property required; optionals are `null` unions). Failures include the OpenAI error text.

**Outstanding questions:** Production PHP must ship before Review notes works on the floor.

---



**PR:** (this ship)

**Files:** `apps/ark_tech` speech_to_text · `TechBrakeSpeechParser` · Tech API voice error handling

**Reason:** DVI “voice” was a text box labeled like PTT. The mic never opened, failures were silent, and some spoken word orders bound the wrong side.

**Architecture impact:** No new authority. Speech becomes a transcript, then the existing propose/confirm write gate.

**Outstanding questions:** Needs a Tech reinstall on the phone. Production PHP parser should ship with this.

---

### 2026-08-23 — Customer Display is a counter kiosk

**PR:** (this ship)

**Files:** `CustomerDisplayProjection` · `RepairOrderCustomerDisplayController` · `RepairOrderFooterProjection` · customer-display views · `RepairOrderCustomerDisplayTest`

**Reason:** Customer Display on the RO opened the customer portal estimate (staff preview). The floor needs a kiosk-style front-counter monitor for this visit, not the portal.

**Architecture impact:** Portal preview stays on “Preview customer estimate.” Customer Display is a read-only operations kiosk projection of the same customer-facing snapshot. No GET writes. No portal token.

**Outstanding questions:** None.

---


**PR:** (this ship)

**Files:** `AdvisorHomeCardSurfaceProjection` · `home-card.blade.php` · `app.css` · `TechMyWorkController` · tests

**Reason:** Edwin Bedburdick’s tomorrow slot lived on the vehicle with `repair_order_id` null, so the Job Board card never showed it. Tech My Work listed every open DVI RO for admins.

**Architecture impact:** Appointments stay schedule authority. The board projects the next active appointment by RO link, then vehicle. Tech stays assigned-work-only.

**Outstanding questions:** None.

---

### 2026-08-23 — Job Board shows the next appointment

**PR:** (this ship)

**Files:** `AdvisorHomeCardSurface` · `AdvisorHomeCardSurfaceProjection` · `home-card.blade.php` · `app.css` · `AdvisorHomeBoardTest`

**Reason:** Scheduled cars looked like any other estimate. Promise time is when we said it would be done, not the calendar slot.

**Architecture impact:** Appointments stay the schedule authority. The Job Board only projects the next active appointment (scheduled / confirmed / checked in).

**Outstanding questions:** None.

---

### 2026-08-23 — ARK Tech My Work: admin shop floor + DVI statuses

**PR:** (this ship)

**Files:** `TechMyWorkController` · `RepairOrderStatus::techDviQueueValues` · `TechApiTest` · `apps/ark_tech` My Work empty copy

**Reason:** Phone showed no work because My Work only listed ROs assigned to the signed-in user in a few production statuses. Admins testing DVI and techs on estimate-stage inspections saw an empty list.

**Architecture impact:** No new authority. Admin Tech session lists open DVI-queue ROs; technicians still only see `assigned_technician_id`.

**Outstanding questions:** Reinstall Tech only if empty copy matters; production PHP is required for the list to fill.

---

### 2026-08-23 — Job Board cards show concern and next move

**PR:** (this ship)

**Files:** `AdvisorHomeCardSurface` · `AdvisorHomeCardSurfaceProjection` · `WorkboardTriageCard` · `home-card.blade.php` · `app.css` · `AdvisorHomeBoardTest` · `AdvisorHomeCardSurfaceTest`

**Reason:** Job Board cards showed money, age, and empty promise/labor chrome, but not why the car is here or what to do next.

**Architecture impact:** Card surface stays a projection. Concern and next move come from the existing triage card; empty promise and 0% labor stay hidden.

**Outstanding questions:** Whether parts ETAs or richer next-move copy earn a later slice after floor use.

---

### 2026-08-23 — Hosted Dragon only (remote bridge removed)

**PR:** (this ship)

**Files:** `CompleteHostedDragonAssistAction` · `DragonBridgeDispatcher` (hosted dispatch only) · removed `/api/dragon/*` machine + bridge routes, `tools/dragon-bridge`, node connect/heartbeat/assist ACK APIs

**Reason:** Shop runs ARK-hosted Dragon only. Remote arkai/node connections left rewrite hanging. Chat, rewrite, review, and recall assist complete in ARK via hosted OpenAI. Same apply gates.

**Architecture impact:** No second Dragon runtime. `dragon_nodes` may remain unused.

**Outstanding questions:** None.

---

### 2026-08-23 — Saved Work money actually lands on the RO

**PR:** (hotfix)

**Files:** `WorkTemplateSettingsController` · `ApplyWorkTemplateAction` · `work-template-settings.blade.php` · `WorkTemplatesTest`

**Reason:** Production Saved Work still had null prices. Number inputs dropped `$`/commas, and apply ignored labor rates then could zero part sell. Parse dollars, keep money fields in the form, stamp blueprint cents onto authored lines.

**Architecture impact:** Saved Work remains a disposable blueprint. Labor policy still resolves first; a stored template rate then overwrites the authored line as a menu/package price.

**Outstanding questions:** Existing Saved Work rows must be re-saved once so prices exist in the blueprint.

---

### 2026-08-23 — RO Dragon rewrite hosted fallback

**PR:** (hotfix)

**Files:** `CompleteHostedServiceAdvisorRewriteAction` · `DragonBridgeDispatcher` · `DragonAssistLifecycle` (nullable node) · `ark-dragon-service-advisor.js` fail-on-create

**Reason:** Service Advisor rewrite waited on a local Dragon bridge node. Hosted Dragon is the production brain, so the RO spinner never completed. Hosted OpenAI structured rewrite now runs when no node is online; fact-preservation still rejects bad proposals.

**Architecture impact:** Bridge node remains preferred when online. Hosted path is preview-only (same apply gate).

**Outstanding questions:** None.

---

### 2026-08-23 — Saved Work persists sell price and part cost

**PR:** (local)

**Files:** `WorkTemplateSettingsController` · `ApplyWorkTemplateAction` · `work-template-settings.blade.php` · `tests/Feature/Operations/WorkTemplatesTest.php`

**Reason:** Settings only stored fee `unit_price_cents`. Create UI had no part sell field. Apply then forced part sell to `$0` on manual posture, so adding Saved Work to an RO showed zero.

**Architecture impact:** Template lines remain a disposable blueprint. Apply still uses shop labor authority unless a saved rate is later an explicit override. Part sell on the blueprint is applied as manual pricing.

**Outstanding questions:** Whether saved labor rate should become a coded labor override. Not in this fix.

---

### 2026-08-23 — Production 500s: shop overhead class + stale ops layout

**PR:** (hotfix)

**Files:** `ShopOperationsSettingsController` import `ShopExcellenceTargets`; production `view:clear`

**Reason:** Today’s HTTP 500s were (1) missing import so Settings → Overhead died, (2) compiled ops layout still calling dropped `operations.community.giveaways.index`. Layout source already had the nav removed; cache cleared on VPS.

**Architecture impact:** None.

**Outstanding questions:** None.

---

### 2026-08-23 — Handheld voice lab (WAV → STT only)

**PR:** (this ship)

**Files:** `POST /api/voice/lab/utterance` · `app/Ark/Voice/Lab/*` · `config/voice.php` · `firmware/voice-terminal` · `docs/voice/handheld-v0.1.md`

**Reason:** ESP walkie needs a secret, off-by-default transcription endpoint. Not VoiceDevice, not Dragon, not TTS. Production stays dark unless `VOICE_LAB_ENABLED` is set.

**Architecture impact:** None on Glass, Tech, or Dragon chat. Lab scores laterality; it does not store audio or call the agent.

**Outstanding questions:** Physical mic phrase test (right-rear 2mm / left-rear 3mm) before any VoiceDevice work.

---

### 2026-08-23 — Shop Glass hosted Ask Dragon (station token)

**PR:** (local → production)

**Files:** `POST /api/station/dragon/chat` · `StationDragonChatController` · `ChatDragonAgentAction::handleForStation` · `StationDragonStatusProjection` · migration `station_device_token_id` on `dragon_agent_conversations` · `apps/advisor_station` Ask Dragon overlay

**Reason:** Shared glass cannot use a staff PAT. Ask Dragon must be ARK-hosted on the station device token. Production 404ed the route until this slice shipped.

**Architecture impact:** Station conversations may have null `user_id`. Dashboard Dragon chip is an ARK projection (no OpenAI on poll). Glass stays a glance board; work stays in ARK at each desk.

**Outstanding questions:** Physical 1920×1080 floor pass on the shared display. arkai off-day still gated on product feel.

---

### 2026-08-23 — ARK Tech v0.1 (separate product, brake slice)

**PR:** (local)

**Files:** `app/Ark/Tech/*` · `routes/api.php` `/api/tech` · `apps/ark_tech` · `tests/Feature/Tech/*` · `docs/tech/client-realignment-v1.md`

**Reason:** Lock four clients. Shop Glass stays advisor 1080p. ARK Tech is technician DVI (My Work → brakes → photo → voice proposal → confirm). One ARK backend, one Dragon. No ARK Mobile this slice.

**Architecture impact:** Thin `/api/tech` over existing inspection actions. Staff Sanctum only. Voice does not write until confirm. Manual DVI if Dragon/STT is down.

**Outstanding questions:** Install debug APK on issued LugsNPlugs tablets and fill `docs/tech/hardware-learning-log.md`.

---

### 2026-08-23 — ARK Tech direction lock (DVI handheld, not ESP)


**PR:** (direction)

**Files:** `docs/tech/ark-tech-direction.md` · `docs/voice/handheld-v0.1.md` (R&D) · `docs/engineering/CURRENT_MILESTONE.md` (queued)

**Reason:** Voice is an input. DVI is the technician product. Rugged/tablet Android already has camera, battery, OS. ESP walkie is not Landon’s platform. Shop Glass stays the active milestone; ARK Tech is locked, not started.

**Architecture impact:** Separate Flutter app `ark_tech` later; reuse `/api/mobile` staff Sanctum + inspection authority. No `stn_`/`drg_`/`vce_` for techs. No custom PCB.

**Outstanding questions:** Tablet floor test of Look → Photo → Talk → Confirm. Hardware buy only after that.

---

### 2026-08-23 — Voice handheld lab (capture + STT only)

**PR:** (local)

**Files:** `docs/voice/handheld-v0.1.md` · `firmware/voice-terminal/` · `app/Ark/Voice/Lab/*` · `POST /api/voice/lab/utterance` · `voice:lab-transcribe`

**Reason:** Prove noisy-shop measurement transcription on a raise-to-face PTT brick with swappable I2S mics before any VoiceDevice domain, Dragon, TTS, dock, or PCB.

**Architecture impact:** Lab route is secret-gated and off by default. Does not authenticate as staff/station/Dragon. Does not persist audio. Does not call Dragon.

**Outstanding questions:** Which capsule survives impact-gun laterality on the gold phrase. Battery and dock are later gates.

---

### 2026-08-23 — RO scheduling floor-certified; freeze

**PR:** (ops cert, no code)

**Files:** production RO `#1457` · appointment `#35` created/rescheduled/canceled · Glass `coming_in` · hosted `appointments.query`

**Reason:** Waiting-parts + return visit stayed `waiting_parts`. Same truth in ARK, Glass, hosted Dragon. Dragon did not own the calendar.

**Architecture impact:** None. Scheduling closed. Next milestone is Shop Glass command center + Dragon WEAK cases. Do not start reminders.

**Outstanding questions:** Backlog only — Arrival Posture should not keep “Canceled” as current posture with no upcoming appointment.

---

### 2026-08-23 — Dragon investigates before shop advice (hosted gpt-4o)

**PR:** (shipping)

**Files:** `DragonOpenAiFunctionNames` · `DragonToolRegistry` · `DragonAgentLoop` traces · `DragonEmployeeContext` · tool when-to-use descriptions · Pest · phpunit sqlite force

**Reason:** Untuned gpt-4o baseline was strong on specific tool-backed questions and weak on broad owner questions (no tools → consultant speak). OpenAI also rejects dotted function names; production had a container-only hotfix.

**Architecture impact:** Canonical tool ids stay dotted. Provider names are underscored. No regex router, no catalog edits, no model change, arkai stays on.

**Outstanding questions:** Floor recertify the frozen 30 after deploy. arkai-off is not earned yet.

---

### 2026-08-23 — First-class RO appointment scheduling (workflow stays separate)

**PR:** (shipping)

**Files:** `appointments` kind + `no_show_at` · `ScheduleRepairOrderAppointment` · Arrival Posture prefers upcoming return · `AppointmentsBoardProjection` · Shop Glass `coming_in` · Dragon `appointments.query` (read-only) · Pest

**Reason:** Waiting-parts cars must be bookable to return without rewriting repair workflow status. Appointment lifecycle is a separate dimension from RO status.

**Architecture impact:** Extends existing Appointment authority. Does not add a second calendar. Dragon cannot schedule. SMS reminders not in this slice.

**Outstanding questions:** Floor-certify waiting-parts return on a live RO.

---



**PR:** (shipping)

**Files:** Organization JSON-LD · `PublicLlmsTxtDocument` · Entity Health canonical street · footer/header NAP fallbacks · public `displayName()` · 24/24 shop warranty chips · contact FAQ

**Reason:** Suite format, Organization schema, and `llms.txt` drifted from the Chelton Loop Google street line. Search/AI need one NAP plus the shop vs RepairPal warranty split. Visible footer phone/street must match JSON-LD even when shop settings are empty.

**Architecture impact:** Publication identity only. Shop settings remain address authority.

**Outstanding questions:** Third-party citation cleanup is next — not this PR.

### 2026-08-23 — RepairPal warranty is 12 months / 12,000 miles

**PR:** (shipping)

**Files:** public RepairPal + shop warranty views · `config/public_seo.php` · RepairPal FAQ schema in `SeoEngine` · `public/llms.txt` · Pest

**Reason:** Site copy attributed 24/24,000 to RepairPal Certified coverage. That program is 12/12,000. Shop warranty stays 24/24,000.

**Architecture impact:** Publication copy only. No warranty authority change.

**Outstanding questions:** None.

### 2026-08-23 — Purge Community Giveaways

**PR:** (shipping)

**Files:** `App\Ark\Operations\Community\*` · public `/community/giveaways` · admin rail · `public-community-giveaway.js` · drop `community_giveaways` / `community_giveaway_entries`

**Reason:** Campaign product is not shop operations. Purge rather than leave a dead public form and rail link.

**Architecture impact:** Historical create-migrations kept. Drop migration retires tables. Public URL 404s.

**Outstanding questions:** None.

### 2026-08-23 — Dragon memory import uses dump ids, not overlapping words

**PR:** (shipping)

**Files:** `ImportArkaiDragonDumpAction` · hosted agent tests · arkai min dump fixture

**Reason:** Content-word keys let “how the shop answers” overwrite the alternator standard. Durable memories now key as `arkai:{dump-id}` and stale inferred arkai rows are superseded.

**Architecture impact:** None. Import hygiene only.

**Outstanding questions:** Production re-import verification. OpenAI floor cert still later.



**PR:** (shipping)

**Files:** knowledge tables · `ImportArkaiDragonDumpAction` · `knowledge.search` · `shop.financial_snapshot` · `estimates.advisor_context` · `history.search` · Shop Glass no longer pings appliance `/api/dragon/me`

**Reason:** Architecture decision is ARK-hosted Dragon. Migrate useful knowledge/memory. Financial tools use Operational Report language only. Estimate advisor is proposal-only. Do not shut arkai down in this pass.

**Architecture impact:** Dragon lives in ARK. OpenAI is the model provider. arkai remains a recoverable appliance until certification. No new bridge/router/vector stack.

**Outstanding questions:** Live 30-task hosted bake-off, physical arkai-off day, credential revoke — not done. Financial net profit still RED/missing.



**PR:** (shipping)

**Files:** `App\Ark\Dragon\Agent\*` · `config/dragon.php` · dragon agent tables · `POST /api/dragon-agent/chat` · Shop Glass `hostedChat()` · `DragonHostedAgentTest`

**Reason:** Dragon should reason inside ARK with OpenAI and ARK service tools. arkai/Qwen stays online for bake-off. No writes, no SQL, no appliance HTTP for shop truth.

**Architecture impact:** New bounded Dragon Agent module. OpenAI is a provider, not the product. Tools wrap `DragonWorkProjection`, `DragonQueryExecutor`, estimate/inspection reads. Fast facts still short-circuit cheap counts.

**Outstanding questions:** Live 30-task OpenAI vs Qwen bake-off needs a shop OpenAI key. Website/ARKademy corpus still on arkai until migrated.

### 2026-08-23 — Dragon floor bake-off catalog (no cutover)

**PR:** (shipping)

**Files:** `DragonFloorBakeoffCatalog` · `dragon:floor-bakeoff` · `DragonFloorBakeoffTest` · `storage/app/private/dragon-bakeoff/`

**Reason:** Architecture is settled. Next proof is floor work: 30 real LugsNPlugs questions, OpenAI vs Qwen, scored as “competent employee,” not row wins. arkai stays up.

**Architecture impact:** None. Eval harness only. No new agent runtime.

**Outstanding questions:** Needs shop OpenAI key + live arkai URL. Human still scores employee-feel.


**PR:** (shipping)

**Files:** Open House `Event*` controllers/models · public `/kiosk` + photo routes · views · `ark-event-kiosk.js` · drop `events` / `event_attendees` / `event_photos` · nav / command palette · `qrcode` npm dep

**Reason:** One-off Open House kiosk is not shop operations. Purge rather than leave a dead product in the rail.

**Architecture impact:** Platform event contracts in `App\Ark\Operations\Events` remain. Historical create-migrations kept; drop migration retires tables.

**Outstanding questions:** None.



**PR:** (shipping)

**Files:** `DragonHistoryProjection` · `DragonHistoryApiTest`

**Reason:** Imported closed ROs are a mix of paid (177) and lost (370). Lost jobs still have estimate lines; 299 have no lost reason. Dragon needs the full closed set, labeled.

**Architecture impact:** History projection includes all `status=closed`. Lost vs paid is explicit. No PII/money. Similar-repair retrieval still skips lost unless asked.

**Outstanding questions:** None.

### 2026-08-22 — Shop Glass command center v0

**PR:** (in progress)

**Files:** `StationDashboardProjection` · `apps/advisor_station/lib/main.dart` · `open_in_ark.dart` · widget + station API tests

**Reason:** Plumbing glass was ARK Lite. Shared screen needs shop now, needs action, Open in ARK.

**Architecture impact:** Glass orients; ARK edits. Briefing is ARK-derived, not Dragon. Financial KPIs omitted until F1.

**Outstanding questions:** Production PHP briefing/open_in_ark_url ships on next ARK deploy; Flutter already builds Open in ARK URLs from shop origin.

### 2026-08-22 — Shop Glass is command center, not ARK Lite

**PR:** (product lock)

**Files:** `docs/station/shop-glass-product-v1.md` · `ACTIVE_PR.md` · `apps/advisor_station/README.md`

**Reason:** Mac glass proved station token + dashboard counts. That is plumbing. A shared 1920×1080 between two advisors is not a second ARK.

**Architecture impact:** Glass orients (shop now, needs action, ambient calls, Open in ARK). ARK remains the transactional editor. Dragon belongs on cards / one shop paragraph, not a destination tab. Financial lines wait on F1.

**Outstanding questions:** Command-center v0 slice from existing projections only — not started.

### 2026-08-22 — Dragon history list cap 250

**PR:** (shipping)

**Files:** `DragonHistoryProjection` · `DragonHistoryApiTest`

**Reason:** Closed-RO sample for Dragon was capped at 100 while the shop has ~177 paid closes plus ~370 lost closes that still carry estimate lines.

**Architecture impact:** Read-only history API still privacy-minimized. Lost vs paid is labeled. No new authority.

**Outstanding questions:** None.

### 2026-08-22 — Review request Contact Us uses public site

**PR:** (shipping)

**Files:** `ReviewRequestCopy` · `RepairOrderReviewRequestTest`

**Reason:** SMS used `SHOP_BASE_URL` (`app.demo-auto.test/contact`). Customers should land on the public site `https://demo-auto.test/contact`.

**Architecture impact:** Contact Us in review copy is `PublicMarketingUrl`, not shop HTTP origin.

**Outstanding questions:** None.

### 2026-08-22 — Shop Glass Windows installable v0.1

**PR:** (in progress)

**Files:** pairing + Credential Manager vault · Inno Setup · `shop-glass-windows.yml` · `StationMeController` · `docs/station/INSTALL-SHOP-GLASS.md`

**Reason:** Edward must download/install/pair on a shop PC without Flutter.

**Architecture impact:** Installed glass stores `stn_…` in Windows Credential Manager. Dragon remains not configured. ARK `GET /api/station/me` returns device name only.

**Outstanding questions:** Whether GitHub-hosted Windows minutes are available for the release workflow.

### 2026-08-22 — Station device token (not staff Sanctum)

**PR:** (in progress)

**Files:** `station_device_tokens` · `StationDeviceToken` · `AuthenticateStationDevice` · `station:token-issue` / `station:token-revoke`

**Reason:** A fixed glass between advisors must not carry a generic employee PAT. Scope is GET `/api/station/*` only.

**Architecture impact:** Station auth matches Dragon machine tokens: hashed secret, shop identity, revoke, no User.

**Outstanding questions:** Whether the glass should also bind a workstation_id later.

**Certification (Pest `AdvisorStationApiTest`):** dashboard + RO show; no Dragon/mobile/mutations; staff PAT rejected; revoke; wrong shop; last_used without leaking plaintext.

### 2026-08-22 — Advisor station: ARK core, Dragon augment

**PR:** (in progress)

**Files:** `StationDashboardProjection` · `/api/station/*` · `apps/advisor_station` ArkClient + DragonClient

**Reason:** Front-counter glass must keep shop buckets if Qwen/arkai is down. Dragon is intelligence, not the data plane.

**Architecture impact:** Station projection is ARK. Dragon `/station` still aliases the same buckets for the agent. Flutter no longer loads shop state from Dragon.

**Outstanding questions:** Dedicated station user vs advisor Sanctum PAT on the mini-PC.

### 2026-08-22 — Advisor touchscreen station v0.2

**PR:** (in progress)

**Files:** `DragonStationProjection` · `DragonStationController` · `apps/advisor_station`

**Reason:** Counter glass between advisors needs live shop buckets and a Dragon panel without becoming a chat app or calling ARK directly.

**Architecture impact:** New disposable station projection on the Dragon API. Flutter is a Dragon client only.

**Outstanding questions:** Windows mini-PC vs Android panel for the physical display.

### 2026-08-22 — False "Couldn't save" on line add

**PR:** (shipping)

**Files:** `ark-worksheet-continuity.js` · `RepairOrderBuilderWorkspaceModalTest`

**Reason:** Adding a line saved, but the modal treated the returned Builder HTML as failure because Dragon rewrite slots use `text-rose-700` while empty. Retry posted a second line. Detect real field errors only; keep the idempotency key until the save is acknowledged.

**Architecture impact:** Continuity only. Line store authority unchanged.

**Outstanding questions:** Whether flash is still needed once `data-ark-line-id` is present.

### 2026-08-22 — Mention previous visits with @RO

**PR:** (shipping)

**Files:** `RepairOrderMention` · `PriorVisitMentionProjection` · visit-reason / concern / intake composers · `ark-repair-order-mention.js`

**Reason:** New RO from an existing visit needs a way to point at the prior order without copying the estimate. `@RO1677` in the visit reason or concern, with same-customer suggestions.

**Architecture impact:** Tokens stay on existing text fields. Links are a presentation projection. No mention table.

**Outstanding questions:** Whether chips are used more than typing `@`.

### 2026-08-22 — New RO from existing repair order

**PR:** (shipping)

**Files:** `operational-identity-band-document` · `repair-order-identity-customer-inline` · `WorkspaceCommandLanguageTest`

**Reason:** Advisors already on an RO need the next visit without going through Customer Hub. Same Check In path, customer and vehicle already recognized.

**Architecture impact:** Projection/link only. Intake remains the create authority.

**Outstanding questions:** Whether service-lane density still hides the command until someone looks at the customer name row.

### 2026-08-21 — Schedule Day / Week / Month boards

**PR:** (shipping)

**Files:** `ScheduleBoardView` · `ScheduleBoardPreference` · `users.schedule_board_view` · board-view POST · week/month/day partials; `ShopSettingsSeeder` enables appointments; Pest preference + month horizon.

**Reason:** Two-week strip used too much vertical space. Floor needed full Month and Week boards without losing the Day time-slot schedule.

**Architecture impact:** Same Appointment authority. Board view is operator preference (POST persist). GET does not write. Persist writes the user column only when it exists.

**Outstanding questions:** Whether Week becomes the floor default after a week of use.

### 2026-08-21 — Dragon historical closed-RO read API

**PR:** (shipping)

**Files:** `DragonHistoryProjection`; `GET /api/dragon/history/summary|repair-orders|{id}`; Pest `DragonHistoryApiTest`; `/me` capability `history`.

**Reason:** Dragon Historical Repair Intelligence needs a privacy-minimized closed-RO ingest surface. Live open work stays on existing Dragon work/show routes.

**Architecture impact:** Read projection only. Closed, non-lost ROs. No customer PII, full VIN, or money. Dragon agent tool `historical.repair.search` consumes this after ingest; empty index is pending, not ARK disconnected.

**Outstanding questions:** Production deploy of these routes before live ingest; sample size after first ingest.

### 2026-08-21 — Schedule two-week horizon + header overlap

**PR:** (shipping)

**Files:** `SchedulingWorkspaceProjection` 14-day `week_strips`; schedule index Prev/Next week + two-week strip; `ops-cal-horizon` / unstick `ops-cal-lane__head`; Pest horizon test.

**Reason:** Floor could not see next week without paging day-by-day. Agenda lane header was sticky and sat under the week strip.

**Architecture impact:** Same Appointment authority. Horizon is a disposable projection (this week + next week). Soft capacity unchanged.

**Outstanding questions:** Whether 14 days is enough, or a month board is earned.

### 2026-08-21 — Whole-RO Review proposes visit reason + line notes

**PR:** (shipping)

**Files:** ReviewEstimateNotes schema/enricher/apply for `visit_reason` + `line_note`; context `note_lines`; review modal labels; bridge fake/Ollama prompts; Pest + bridge tests; minor Dragon work-card label polish + rewrite tone.

**Reason:** Whole-RO Review Estimate Notes still could not propose the same targets solo Rewrite already covered.

**Architecture impact:** One review task type; proposals target RO / concern / note line via shared Apply/Revert. Scoped review still excludes visit-reason proposals.

**Outstanding questions:** Floor volume of visit/line proposals; whether Ollama proposal quality needs prompt tuning.

### 2026-08-21 — Notes Rewrite Surfaces v0.3

**PR:** (shipping)

**Files:** Visit reason + line note Service Advisor paths; scoped `review_estimate_notes` by `concern_id`; audit `concern_id` nullable + `repair_order_line_id`; UI Rewrite / Review this concern; Pest.

**Reason:** Advisors needed solo rewrite on visit reason and line notes, plus per-concern review — not only whole-RO / narrative fields.

**Architecture impact:** Same Apply/Revert audit grammar across RO / concern / note-line targets. Dragon still never writes until Apply.

**Outstanding questions:** Floor density of three entry points; whether whole-RO should later propose visit reason / line notes.

### 2026-08-21 — Notes Review + Apply v0.2

**PR:** `66d7e98b` (main/production)

**Files:** ReviewEstimateNotes proposals schema + enricher + apply endpoint; shared `ApplyNarrativeFieldRewriteAction`; review modal Apply/Edit/Skip; Narrative per-field Rewrite; bridge proposals; Pest + bridge tests.

**Reason:** Whole-RO critique needed applyable rewrites; advisors also needed Rewrite next to each narrative field.

**Architecture impact:** Completing review still does not mutate RO text. Apply reuses Service Advisor audit/revert. Dragon never writes authority until Apply.

**Outstanding questions:** Floor density of proposals; visit-reason rewrite still deferred.

### 2026-08-21 — Review Estimate Notes v0.1

**PR:** (local)

**Files:** `app/Ark/Dragon/ReviewEstimateNotes/*`; task type `review_estimate_notes`; toolbar + modal + `ark-review-estimate-notes.js`; bridge capability; Pest `DragonReviewEstimateNotesTest`; docs.

**Reason:** Advisors need a whole-estimate narrative critique before presenting — without Dragon writing RO authority.

**Architecture impact:** Assist Bridge gains a third capability. Critique-only; Service Advisor remains the field-rewrite path.

**Outstanding questions:** Floor density of critique copy; whether later to deep-link suggested actions into per-field Service Advisor.

### 2026-08-21 — Dragon Service Advisor v0.1

**PR:** (local)

**Files:** `app/Ark/Dragon/ServiceAdvisor/*`; task type `service_advisor_rewrite`; migration `dragon_service_advisor_applications`; Narrative card + modal + `ark-dragon-service-advisor.js`; bridge multi-capability `tools/dragon-bridge/`; Pest `DragonServiceAdvisorTest`; docs completion report.

**Reason:** Advisors need on-demand, human-gated rewrite of concern narrative fields with before/after preview, Apply/Revert, and fact preservation — without Dragon writing authority.

**Architecture impact:** Assist Bridge gains a second capability. Apply/Revert are staff writes with audit. Completions remain advisory until Apply. Model stays qwen3:14b.

**Outstanding questions:** Floor live Apply/Revert after production deploy; Review Estimate Notes as next step.

### 2026-08-20 — Dragon Assist Bridge + Historical Work Recall Assist

**PR:** (local)

**Files:** `DragonNode` / assist request+result authority; lifecycle + dispatcher; bridge connect/heartbeat/broadcast-auth + assist ACK/complete/failed; Historical Work Recall assist action/controllers; Saved Work picker UI poll; `dragon:bridge-status`; migration `dragon_nodes` / `dragon_assist_requests` / `dragon_assist_results`.

**Reason:** Advisors need durable, privacy-minimized Dragon review of Historical Work Recall without mutating RO/labor authority. Dragon nodes register via service token and complete advisory assist only.

**Architecture impact:** Assist is advisory. Deterministic Historical Work Recall GET stays zero-write. Assist POST creates request + optional bridge dispatch. Completions never rewrite estimates. Machine POSTs are lifecycle writebacks only (not RO mutation).

**Outstanding questions:** Floor certification when a live Dragon node is online; whether more task types earn allowlist entries.

### 2026-08-20 — Dragon Assist Bridge v1 (Historical Work Recall Assist)

**PR:** (local — do not commit until asked)

**Files:** `app/Ark/Dragon/Assist/*`, `app/Ark/Dragon/Bridge/*`, `app/Ark/Dragon/HistoricalRecall/*`; migration `2026_08_20_220000_create_dragon_assist_bridge_tables`; bridge HTTP API; Saved Work picker async Assist; `tools/dragon-bridge/`; `docs/dragon/dragon-assist-bridge-v1.md`; Pest `DragonAssistBridgeTest`.

**Reason:** Low-latency durable advisory assist from arkai without Dragon becoming operational authority. First consumer: Historical Work Recall review.

**Architecture impact:** Reuses Reverb for ARK→node push + HTTPS connect/heartbeat/ACK/complete. Deterministic Historical Recall unchanged (GET zero-write). Assist is POST + poll. No RO/labor mutation from Dragon.

**Outstanding questions:** Floor-validate Assist copy density; optional Reverb client on arkai vs HTTP-only pending path; production systemd unit for dragon-bridge when arkai is reachable.

### 2026-08-20 — Historical Work Recall · drivetrain correctness pass

**PR:** (local — do not commit until asked)

**Files:** `HistoricalDrivetrainKey`; `ResolveHistoricalWorkRecall` (recall-only drivetrain compare); demo seeder 2WD fixture; Pest `HistoricalDrivetrainKeyTest` + expanded `HistoricalWorkRecallTest`.

**Reason:** Shared `DrivetrainNormalizer` maps generic 2WD→FWD. That manufactures specificity Historical Recall must not use (e.g. 2WD Tacoma is RWD).

**Architecture impact:** Global normalizer left unchanged (import / VehicleNormalizer still use 2WD→FWD). Recall uses a separate token: fwd · rwd · awd · 4wd · 2wd · unknown. No drivetrain decoding subsystem.

**Outstanding questions:** When (if ever) to open a global CanonicalDrivetrain migration that adds `2wd` and retires 2WD→FWD for imports.

### 2026-08-20 — Historical Work Recall v1 (Saved Work)

**PR:** (local — do not commit until asked)

**Files:** `ResolveHistoricalWorkRecall`; `HistoricalWorkRecallProjection`; `HistoricalMatchTier`; recall GET controller; Saved Work picker/preview; apply labor override + event provenance; `DemoHistoricalWorkRecallSeeder`; Pest `HistoricalWorkRecallTest`.

**Reason:** Advisors should see shop-history labor confidence (Exact/Likely/Possible) when selecting Saved Work — observational guidance only, never OEM/book time.

**Architecture impact:** Disposable projection. Zero writes on preview. Apply may prepare labor hours for Exact/confirmed Likely; Possible is reference only. Template/history rows are never live authority after apply.

**Outstanding questions:** Generation tables (year ±1 = Likely without platform invent). Transmission/body not in Exact matcher yet (insufficient reliability without false precision).


**PR:** (local — do not commit until asked)

**Files:** `WorkTemplate` / `WorkTemplateLine`; `ApplyWorkTemplateAction`; apply/search/settings controllers; migration `work_templates` + `created_from_template_id` provenance on work groups; Workspace Modal Saved Work picker; Settings → Workflow → Saved Work; `DemoWorkTemplatesSeeder` (not in DatabaseSeeder); Pest `WorkTemplatesTest`.

**Reason:** Bay Boss–class one-action repeat jobs without a Service Catalog. Template authors Concern?/Repair Action + ordinary lines, then owns nothing.

**Architecture impact:** New disposable shop-config authority. Pricing still via `RepairOrderLinePricing` / LaborAuthority / parts matrix. Engine Oil and Testing Package unchanged. Provenance FK is historical only (`nullOnDelete`).

**Outstanding questions:** Whether Add Work from a Concern row should deep-link `concernId` into Saved Work (v1 supports query/context when present; chooser path creates a new concern). Cross-shop `shop_id` when multi-tenant lands.


**PR:** (local)

**Files:** `app.css`; `ark-workspace-tabs.js`.

**Reason:** Orphaned limit-modal styles after auto-eviction. Active RO could sit off-scroll after open.

**Architecture impact:** None. No mobile strip, pin, or drag changes.

**Outstanding questions:** None.

### 2026-08-20 — Final Invoice refreshes note / content drift

**PR:** (local)

**Files:** `InvoiceSnapshotBuilder`; `RefreshCustomerInvoiceAction`; `RefreshLivingInvoiceSnapshotAction`; Pest `FinancialCoreAuthorityTest`.

**Reason:** Editing customer-visible notes (or other $0 bill content) after Issue Final Invoice left the invoice snapshot stale because refresh only ran when approved totals changed.

**Architecture impact:** None. Same living-invoice refresh path. Fingerprint compares customer-facing concerns/lines/intake/totals; refresh when totals **or** content drift. Presentation / settlement guards unchanged.

**Outstanding questions:** None.

### 2026-08-20 — Close labor memory suggestions after select / blur

**PR:** (local)

**Files:** `ark-labor-memory-suggest.js`; labor entry / edit blades; node test.

**Reason:** Add Labor suggestion dropdown stayed open after picking a row (re-focus re-fetched) or tabbing to hours, covering fields below.

**Architecture impact:** None. Same Shop Memory suggest endpoint. Close aborts in-flight fetch; selection suppresses one focus refetch; blur closes the list.

**Outstanding questions:** Whether concern-summary Shop Memory intake has the same reopen-on-focus pattern (separate component).

### 2026-08-18 — Dragon query truncation flag and fail-closed extras

**PR:** (local)

**Files:** `DragonQueryExecutor`; `DragonReadQuery`; Pest `DragonApiTest`.

**Reason:** PHP array union (`+`) left `truncated` stuck false. Extra keys such as `update` were ignored instead of rejected.

**Architecture impact:** None. Same read-only query surface. `array_merge` so row-list truncation is honest. Unknown top-level keys 422.

**Outstanding questions:** None.


**PR:** (local)

**Files:** `dragon_service_tokens` migration; `DragonServiceToken`; `AuthenticateDragonService`; `DragonWorkProjection`; `/api/dragon/{me,work,summary,repair-orders/{id}}`; `dragon:token-issue` / `dragon:token-revoke`; Pest `DragonApiTest`; `config/shop.php` identity + work items limit.

**Reason:** Dragon needs a machine credential that can read shop-floor state without staff Sanctum mutation rights.

**Architecture impact:** Separate token table (not User PAT). GET-only route namespace. Summary counts are uncapped; item cards may truncate with flag. Oldest RO uses `opened_at ?? created_at`. No write surface.

**Outstanding questions:** Multi-tenant shop_identity binding when Cloud tenancy ships.

### 2026-08-18 — Constrained Dragon repair-order query API

**PR:** (local)

**Files:** `DragonReadQuery`; `DragonQueryExecutor`; `POST /api/dragon/query`; `AuthenticateDragonService` POST exception for query only; `DragonWorkProjection::allOpenCards()`; Pest query cases.

**Reason:** Dragon needs a validated, fail-closed, semantically read-only way to ask unanticipated repair-order questions without SQL or mutation.

**Architecture impact:** Option A dedicated endpoint. Executes against the full open-RO set (same production-status definition as summary). Row lists cap at 50; counts/groups are complete. Repair orders only.

**Outstanding questions:** Whether later entities (customers, payments) ever earn a second query surface.

### 2026-08-18 — Full vehicle identity + VIN decode on RO modal

**PR:** (local)

**Files:** `workspace-vehicle-fields` partial; RO `vehicle-identity` panel; Customer Hub vehicle edit/create; `ark-workspace-modal.js` helper; Pest identity assertions.

**Reason:** RO vehicle modal only exposed year/make/model/plate/VIN/color. Trim, engine, drive, transmission, and notes were hidden. VIN decode existed on intake/add-vehicle only.

**Architecture impact:** Same `Vehicle` update + `operations.vehicles.decode-vin` authority. Shared field partial; no new vehicle store.

**Outstanding questions:** Whether decoded canonical fields (engine_code, drivetrain enum) should surface as read-only evidence on this modal.

### 2026-08-17 — Document email send log on viewer

**PR:** (local)

**Files:** `DocumentEventType::Emailed`; `DocumentEmailLogProjection`; viewer Email log; list/modal last-sent hint; delivery writes `Emailed` (legacy Presented/channel=email still listed); Pest assert log; doctrine timeline vocab.

**Reason:** Advisors need a running record of who received paperwork (customer vs warranty).

**Architecture impact:** Projection over `document_events` — disposable. No new authority table.

**Outstanding questions:** Full Document Timeline UI still later; this is email-only surface.

### 2026-08-17 — Email paperwork from document viewer / lists

**PR:** (local)

**Files:** `DocumentEmailDelivery`, `DocumentEmailController`, `DocumentCustomerMail`, `mail/document-customer`; route `operations.customers.documents.email`; viewer + `document-list` + RO Paperwork modal Email affordances; Pest in `DocumentsAuthorityTest`.

**Reason:** Advisors open RO paperwork and need to send the file to the customer without leaving the view surface.

**Architecture impact:** Documents authority stays source of bytes. Email attaches `storage_path` from local disk. Records `DocumentEventType::Presented` (channel email) + conversation outbound email. No new communication type. Estimate/invoice email paths unchanged.

**Outstanding questions:** Whether portal “customer visible” should auto-offer after email — observe first.

### 2026-08-17 — Auto worksheet line order inside concerns

**PR:** (local)

**Files:** `RepairOrderLineWorksheetOrder`; Concern/WorkGroup/RO `lines()` relations; estimate snapshot + operational sheet + concern work Blade; Pest `RepairOrderLineWorksheetOrderTest`.

**Reason:** Floor pressure — labor landed between parts because lines sorted by create id. Advisors want Labor → Parts → Sublets → Notes.

**Architecture impact:** Display/projection order only. Type rank then id. No drag-reorder authority. Totals unchanged.

**Outstanding questions:** Observe whether package/fee placement needs tuning; manual rearrange only if floor still asks after auto-order.

### 2026-08-15 — ARK Phone iOS Client Recovery: production-readiness hardening

**PR:** shipping (backend + mobile recovery commits)

**Files:**
- Backend: `MobileDevice` + migration `voice_ready_at`; `MobileVoiceEndpointRegistrar`; `RegisterMobileDeviceAction`; `TelephonyRingGroup`; `MobileTelephonyVoiceRegistrationEventController`; `MobileVoiceCredentials` / `TwilioMobileVoiceTransport` / `TelephonyHealth` / mobile-push settings (boolean health); tests `MobileVoiceReadinessTest`, `MobileCoveragePresenceTest`, `TelephonyCallFlowTest` (mobile Client cases); `IMPLEMENTATION_LOG.md`.
- Mobile (`ark-mobile`): vendored `third_party/twilio_voice-0.3.2+2` (path pin); `tool/verify_twilio_voice_patches.sh`; entitlement Debug/Release split; `TwilioVoiceTransport` + `VoiceDialerBootstrap` voice_ready reporting; Companion incoming host skips Flutter UI when Twilio.

**Reason:** Three ship blockers before Twilio credential provisioning / Edward iPhone install — mutable pub-cache patches, hardcoded `aps-environment=development`, and generic device registration marking ring presence.

**Architecture impact:** MobileApp `<Client>` rings only with recent `mobile_devices.voice_ready_at` evidence (Twilio Client register success / refresh via `phase=voice_ready`) plus TwiML App + platform push SIDs. Generic FCM/APNs device rows do not mark presence. WP820 Sip + cell dial unchanged. CallKit remains sole incoming UI for Twilio transport (implementation-path verification only). Fail-closed until SIDs are saved in Settings.

**Outstanding questions:** Physical CallKit dismissal matrix on locked/terminated iPhone still requires floor certification after APNs VoIP credential + TwiML App SIDs are provisioned.

### 2026-08-15 — Reuse settlement projection in deposit recording guard

**PR:** (local — do not ship until review)

**Files:** `RepairOrderDepositRecordingGuard.php`; `RepairOrderFinancialPresenter.php`; `RepairOrderDepositRecordingGuardTest.php`; `IMPLEMENTATION_LOG.md`.

**Reason:** RO show projected BalanceDue once in the controller, then DepositRecordingGuard called `forRepairOrder()` twice (remaining + satisfied), producing FinancialPresenter BalanceDue = 6 instead of the ≤2 budget.

**Architecture impact:** Optional request-scoped `?BalanceDueResult $balance` on `remainingSuggestedDepositCents` / `suggestedDepositSatisfied`. Presenter passes already-loaded settlement and derives satisfied from remaining. Fallback without preload unchanged for validateAmount / mutations / mobile. No calculator, F1, gate, or label changes.

**Outstanding questions:** Mobile deposit projections still call the guard without preload (standalone GET paths) — intentional; out of scope for this RO-show slice.

### 2026-08-15 — Schedule free-text SMS replies (Tomorrow Morning)

**PR:** (shipping)

**Files:** `ScheduledOutboundMessageType::SmsReply`; `ScheduleOutboundSmsReplyAction`; `DispatchScheduledOutboundSmsReplyJob`; `ScheduledOutboundSmsProjection`; cancel/controller; `SendConversationMessageController` timing; migration `customer_id` + nullable `repair_order_id`; quick-reply Blade/JS; Pest `ScheduledOutboundSmsReplyTest`; milestone note.

**Reason:** Floor pressure — advisors could schedule estimates but not typed SMS replies after hours.

**Architecture impact:** Same Communications scheduled-intent rails. Body + recipient phone snapshotted. Send Now cancels pending reply. Job supersedes if shop already replied after `requested_at`. Attachments not schedulable. No date picker / campaigns / inbox.

**Outstanding questions:** Observe Tomorrow Morning on replies; MMS schedule only if earned.

### 2026-08-14 — Name owe-today vs settlement on financial presenter

**PR:** (local — do not ship until review)

**Files:** `RepairOrderFinancialPresenter.php`; financial rail/strip/payment/square/posture Blade; `RepairOrderFinancialPresenterBalanceAxesTest.php`; `FinancialWorkflowUiTest.php` (label asserts).

**Reason:** Presenter `balanceDue*` silently used Financial Position (owe today) while pay/waive/close/post gates used BalanceDueResult — same rail could disagree under drift without naming either axis.

**Architecture impact:** Two-axis vocabulary on the existing presenter only:
- **Owe today** — `oweToday*` / `customerOwesTodayCents` ← `FinancialPositionProjection`
- **Settlement balance** — `settlementBalanceDue*` / `isPaid` ← already-loaded `BalanceDueResult`
- **Posted sales** — `isPosted` / `postedAtLabel` ← `posted_at` (unchanged)
Legacy `balanceDue*` now aliases settlement when an invoice is issued. Gates unchanged (still BalanceDue). No new presenter/calculator. Workboard/Attention/mobile/mirrors untouched.

**Outstanding questions:** Estimate totals panel still says “Balance Due” (separate surface). F1 still locks to invoice total after issuance, so owe-today and settlement usually match post-invoice until F1 evolves. Pre-existing `ro show financial presenter balance due is projected once per request` expects BalanceDue ≤2 but measures 6 on the WaitingApproval fixture (Posture/other `isPaid()` paths) — not introduced by this naming slice; do not widen that budget here.


**PR:** (local — review before ship)

**Files:** `WorkboardCardProjection.php`; `WorkboardCardProjectionPaymentFootnoteTest.php`.

**Reason:** Unpaid footnote used `paymentStatus()->label()` while card decision used `$this->isPaid` (BalanceDue) — stale mirror paid produced “Paid · collect before close”.

**Architecture impact:** Footnote paid/unpaid wording now uses the same `$this->isPaid` batch result from BalanceDueCalculator. No mirror read. Attention/PostureSync/Mobile unchanged.

**Outstanding questions:** None for this slice.

### 2026-08-14 — Attention paid exclusion is ledger-only

**PR:** (local — review before ship)

**Files:** `CustomerDecisionPressure.php`; `CustomerDecisionPressureTest.php`.

**Reason:** `isPaid()` used `paymentStatus() === paid OR BalanceDue` — stale mirror `paid` created false calm and hid ROs from Attention.

**Architecture impact:** Attention exclusion now uses `RepairOrder::isPaid()` only (BalanceDueCalculator / ledger). Intentional behavior correction: stale paid mirrors can no longer hide unpaid work. Mirrors, PostureSync, Workboard, Mobile, events, snapshots untouched.

**Outstanding questions:** Workboard footnote still labels via `paymentStatus()` when unpaid (slice 1b). Stale mirror backfill (RO1544/1553) still separate. Unrelated pre-existing test: `attention surfaces customer decision pressure sorted by dollars at risk` fails asserting page copy "Customer Decisions" — do not fix in this commit.

### 2026-08-14 — Coolify deploy script: POST not GET

**PR:** (local — ship when approved)

**Files:** `infra/build-runner/mac/deploy-production.sh`

**Reason:** Coolify `/api/v1/deploy` is POST-only; script still used GET → HTTP 405. Forced manual POST + VPS recreate on f41e6841.

**Architecture impact:** Mac ship path only. Same URL, UUID query, Bearer token. Failures exit nonzero with HTTP status; response body truncated, token not logged.

**Outstanding questions:** Live Coolify POST verification deferred until the next normal production release (no deploy for this commit). GHCR `:production` tag 401 after SHA push left unchanged — track as separate deployment-hardening item (stale docker login / dual-tag push). GitHub Actions / self-hosted yml still GET (out of this slice).

### 2026-08-14 — Payment recon: future-posted only in advance_pay

**PR:** (local — ship when approved)

**Files:** `OperationalReportPaymentReconciliation.php`; `OperationalReportPaymentReconciliationTest.php`.

**Reason:** June–Jul 2026 audit — payments with `posted_at` after range end were subtracted in both `advance_pay` and `cleared_from_ar` (June −$428.08 entirely; Jun–Jul −$4,691.20 double-count).

**Architecture impact:** Report math only. `cleared_from_ar` = in-range payments on ROs with `posted_at < from`. Future-posted stays solely in `advance_pay`. Ledger / mirrors / Square / posted sales unchanged.

**Outstanding questions:** Remaining Jun–Jul residual after fix is RO1647 courtesy deposit ($691.48) vs $0 posted sales — separate slice.



**PR:** (shipping)

**Files:** `RefreshCustomerInvoiceAction.php`; `RepairOrderFinancialPresenter.php`; `financial-rail.blade.php`; Pest.

**Reason:** After Final Invoice + partial payment, adding approved work left Financial Position on the frozen snapshot. Refresh was blocked by “settlement,” and the drift banner was hidden because `invoiceNeedsRefresh` required no settlement.

**Architecture impact:** Explicit Refresh still archives prior snapshot. Payments/deposits stay applied. Write-off / refund / credit still block refresh. Silent living refresh unchanged.

**Outstanding questions:** Supplemental / revised invoice product path (F9) still future.

### 2026-08-11 — Estimate line delete blocked by flag recognition FK

**PR:** (local — ship when approved)

**Files:** `DestroyRepairOrderLine.php`; `RepairOrderLineDestroyController.php`; `LaborAuthority.php` (category change re-resolves rate); `RepairOrderConcernProductionStatusController.php` (redirect `edit` → `show`); Pest coverage.

**Reason:** Deleting labor on RO 1659 returned 500 — `technician_flag_recognition_lines.tfrl_line_fk` RESTRICT. Courtesy category change left `$150` because rate snapshots were preserved across category edits. Production status redirect still named deleted `operations.repair-orders.edit` route.

**Architecture impact:** Line delete detaches recognition rows (and empty parent recognitions) before delete — shared web/mobile path. Category change is intentional pricing context (re-resolve); hours-only edits still preserve snapshot. No migration / cascade change.

**Outstanding questions:** None for floor delete. Optional: surface “rate refreshed” when category changes.

### 2026-08-08 — Authoring philosophy + deepest-context notes

**PR:** (shipping)

**Files:** `docs/ark-v2-interface-constitution.md` §0; `ark-interface-constitution.mdc`; concern work-section compose; `ark-workspace-modal.js` note helper; line-card Note vs Concern Note; note audience last-view hint; CSS hide `.ops-line-entry-actions` in workspace modal; `RepairOrderBuilderWorkspaceModalTest`.

**Reason:** Freeze Users express intent / ARK resolves structure; compose follows deepest existing work context; footer-only modal actions. Remove dual Scope note vs Note chooser. Explain when the last audience cannot be cleared.

**Architecture impact:** Constitution only + presentation/compose chrome. No note authority or migration. Body never submits in Workspace Modal.

**Outstanding questions:** None.

### 2026-08-08 — Documents Authority v1

**PR:** (local only — DO NOT COMMIT / PUSH / DEPLOY until approved)

**Files:** `App\Ark\Operations\Documents\{Document,DocumentEvent,*Action,*Controller,DocumentProjection}`; migration `documents` + `document_events`; Customer Hub Documents tab; RO Paperwork chip + workspace modal; viewer rotate; attach search; Pest `DocumentsAuthorityTest`; doctrine `docs/operations/documents-authority-v1.md`.

**Reason:** Durable paperwork authority (not Evidence). Customer ownership is a relationship; RO presentation says Paperwork.

**Architecture impact:** Constitutional freeze — preserve paperwork / do not interpret; once-exists + never duplicate bytes for relationships; presented by many / authored once; other authorities may reference, Documents never inherits their jobs. Sources: upload / scan / generated. Document Timeline named as projection over `document_events` (write now, UI later). Rotate = new rendition. Evidence + EstimateDocument PDF engine untouched.

**Outstanding questions:** Floor gate (real warranty scan + alignment upload). Then: Portal Documents; generated EstimateDocument → Document bridge; tablet present-to-sign; many-to-many RO attach if earned.

### 2026-08-08 — Note audiences: Advisor / Technician / Customer

**PR:** (shipping)

**Files:** `NoteAudience`; migration audience flags on `repair_order_lines`; store/update + tech sheet + customer boundary; privacy field / badges; Pest.

**Reason:** Replace binary private checkbox with Advisor (default), Technician (tech sheet), Customer (PDF/portal) audiences.

**Architecture impact:** `is_private` remains synced as `!visible_to_customer` for legacy reads. Existing private notes backfill Advisor+Technician.

**Outstanding questions:** None.

### 2026-08-07 — Inspection walk-link staff send via Twilio + mail

**PR:** (shipping)

**Files:** `SendInspectionWalkLinkAction`; `RepairOrderInspectionWalkLinkSendController`; `InspectionWalkLinkStaffMail`; inspect rail handoff form; route; Pest.

**Reason:** SMS/Email handoff opened local `sms:`/`mailto:` apps; advisors need shop Twilio + Laravel mail for technician walk links.

**Architecture impact:** Staff handoff only — no customer ConversationMessage. Projection adds `send_url`.

**Outstanding questions:** None.

### 2026-08-07 — Review Request no-gating + equal Contact Us

**PR:** (local only — observation period; do not ship until floor earns it)

**Files:** `docs/communications/review-request-no-gating-v1.md`; `.cursor/rules/ark-review-request-no-gating.mdc`; `docs/growth/review-schema-solicitation-compliance-v1.md`; `ReviewRequestCopy` / projection / SMS / email / mail blade / panel; Pest.

**Reason:** Freeze “ARK never gates public reviews.” Every send includes honest feedback + Google link (settings) + equal Contact Us. CSAT stays a separate future intent, not a gate.

**Architecture impact:** ConversationMessage remains authority. No review_requests table. No sentiment routing.

**Outstanding questions:** Floor observation; later customer-window dedupe; later CSAT as independent Conversation Intent.

### 2026-08-07 — Compose button ARKv1 semantic colors

**PR:** (shipping) Restore Labor/Part/Note colored fills on Repair Action compose

**Files:** `app.css` compose-btn modifiers; concern-work-section modifier classes.

**Reason:** Color was the remaining muscle-memory cue after glyph restoration.

**Architecture impact:** None. Presentation only.

**Outstanding questions:** None.

### 2026-08-07 — Compose icons, footer oil sticker, schedule without vehicle

**PR:** (shipping) Floor polish pass

**Files:** `compose-icon.blade.php`; compose CSS spacing; `RepairOrderFooterProjection` + footer PRINT oil sticker; appointment create/show/schedule-row; `CustomerStoreController` return_to=schedule; Learn soft-capacity; related Pest.

**Reason:** Restore ARKv1 compose muscle memory; match header Print Oil Sticker in footer; allow scheduling when vehicle is not set yet.

**Architecture impact:** None. Presentation / schedule UX only. Appointment vehicle remains nullable authority.

**Outstanding questions:** Whether soft V1 action tints are needed after floor observation.

### 2026-08-05 — RO Builder Slice 1 polish (pre-commit)

**PR:** (local only — do not ship until Herd walk)

**Files:** Removed dead inline edit branches from concern-line-row; empty RA instructional copy; quieter compose + stronger RA titles; modal mobile chrome (dvh/safe-area/overscroll); validation-aware save ack (no ✓ Saved on error); Alpine listener cleanup on host replace; debt doc updated.

**Reason:** Finish interaction reset — Builder presents truth; modal authors; continuity stays boring.

**Architecture impact:** None.

**Outstanding questions:** Edward Herd walk before commit.

### 2026-08-05 — RO Builder authoring chrome tighten (pre-commit)

**PR:** (local only — do not ship until Herd walk)

**Files:** Add Work radio chooser; icon-only Repair Action compose; `Evidence (N)` entry (no empty Evidence chrome); save ack ~180ms before continuity replace; UI copy without “Workspace Modal”; `docs/operations/ro-builder-workspace-modal-compatibility-debt.md`.

**Reason:** Product-owner pass — Advisor language, Builder reads as work, trust on save, document Builder-edits-itself debt before any commit.

**Architecture impact:** None. Same authorities. Presentation / authoring split unchanged.

**Outstanding questions:** Local walk hesitation notebook before commit/push/deploy.

### 2026-08-05 — RO Builder Workspace Modal Reset (Slice 1)

**PR:** (shipping) Builder interaction reset — presentation + Workspace Modal authoring

**Files:** `x-operations.workspace-modal`; `ark-workspace-modal.js`; CSS chrome; Builder host + quiet panels (oil/testing/evidence); `+ Add Work` chooser; Repair Action compose → modal entry points; continuity panel `workspace-modal-host`; strip/header Review↔Builder links (Edit/View toggle removed); Pest `RepairOrderBuilderWorkspaceModalTest` + related expectation updates.

**Reason:** Remove page-wide Edit/View and permanent creation ads; one modal grammar authors through existing authorities.

**Architecture impact:** Presentation-only Builder. No authority expansion. Routes retained. Financial RED untouched.

**Outstanding questions:** Floor muscle memory on `+ Add Work`; when to migrate remaining inline narrative/owner/status edits; retire `/edit` naming vs presentation route later.

### 2026-08-05 — Work Authorization v1 Testing Package slice

**PR:** (shipping) Prove permission exists — Authorize + Outcome only

**Files:** migration `work_authorizations`; `WorkAuthorization` + enums; `AuthorizeTestingPackageAction` / `RecordTestingPackageOutcomeAction`; routes + RO panel + outcome form; Pest `WorkAuthorizationTestingPackageTest`; doctrine status update.

**Reason:** Smallest code that proves Work Authorization — not Level labor SKUs, not pricing.

**Architecture impact:** Permission not execution. Package line $0 until Pricing Policy. Repair Action attached. Outcomes operational. No Levels/escalate-create/portal yet.

**Outstanding questions:** Floor notebook — advisor language · tech ignore · Escalate rate · pricing creep. Target ~20–30 real authorizations before Levels/Pricing.

### 2026-08-05 — Work Authorization v1 doctrine (Testing Package first)

**PR:** (doctrine only) No implementation

**Files:** `docs/operations/work-authorization-v1.md`; `CURRENT_MILESTONE` board row; `ACTIVE_PR` observation stop.

**Reason:** Level Testing PDF craft → ARK grammar: customers authorize packages (Testing first), not diagnostic hours. Escalation between packages; outcomes are operational truth.

**Architecture impact:** Freezes Work Authorization as shared package grammar; Testing Package v1 first consumer; Maintenance remains peer. Pricing Policy and flag hours stay outside. Observation gate before any schema/UI.

**Outstanding questions:** Floor notebook — do advisors still sell “one hour of diagnostics”? When does Escalate beat “another hour”? Invariant frozen: Work Authorization owns permission, not execution.

### 2026-08-04 — R1.1 Repair Action Operational Communication

**PR:** (shipping) Status + Latest Update on Repair Actions; deprecate RO Primary Tech from production surfaces

**Files:** migration `2026_08_04_010000_add_repair_action_status_and_latest_update`; `RepairActionStatus`; `UpdateRepairActionCommunicationAction`; communication route/controller; builder + review + tech sheet + landing; workboard/My Work ownership posture; Pest `RepairActionCommunicationR11Test`.

**Reason:** Customer asks “what’s happening?” — advisor must answer from Repair Actions without interrupting the technician.

**Architecture impact:** Completes R1 shift of technician work off the RO. `assigned_technician_id` transitional only (seed/default). No chat, timeline, R2/R3, Financial.

**Outstanding questions:** Observe floor. Do not open R2/R3 from momentum.

### 2026-08-03 — Fix repair_action_ownership_events FKs (MySQL identifier length)

**PR:** (hotfix) Short-named FKs + index after production create aborted

**Files:** `2026_08_03_220000_fix_repair_action_ownership_event_constraints`; harden `2026_08_03_210000` create to use `ra_own_evt_wg_fk` / `ra_own_evt_actor_fk`.

**Reason:** Default FK name exceeded MySQL 64 chars; table created without FKs/index while columns + backfill later completed manually.

**Architecture impact:** None — schema integrity only. Behavior unchanged.

**Outstanding questions:** None.

### 2026-08-03 — Platform observe board locked

**PR:** (docs) No new foundational authorities; run the shop; notebook friction

**Files:** `CURRENT_MILESTONE.md` platform posture board.

**Reason:** Foundational blocking work is in place (Inspection · Maintenance · Evidence · Arrival · Portal · Guest book · Ownership R1 · Financial RED · Customer Recognition frozen). Architecture leading implementation — next value is floor observation.

**Architecture impact:** Explicit do-not-start list (R3, assists, Financial F1, catalogs, Companion expansion). Dispatch-by-Repair-Action noted as future **projection**, not a new authority. Pattern: freeze → slice → floor → observe → earn.

**Outstanding questions:** None for code. Notebook awkward / expected sentences for 2–4 weeks.

### 2026-08-03 — R1 Repair Action Ownership shipped

**PR:** (shipping) RepairActionOwner on work packages; transfers; package tech sheets

**Files:** migration `2026_08_03_210000_add_repair_action_ownership`; `AssignRepairActionOwnerAction`; `RepairActionOwnershipEvent`; work-group owner route/UI; `OperationalSheetPresenter` package sheets; WorkboardLens / MobileWork / technician landing owned packages; Pest `RepairActionOwnershipR1Test`.

**Reason:** Shop friction — multi-tech ROs and package worksheets. RO cannot own technician work.

**Architecture impact:** First technician-owned unit of work. Primary Technician on RO is visibility only. Ownership transfers with history. Financial / Labor Recognition unchanged.

**Outstanding questions:** Floor observation before R2/R3. Unowned Repair Actions (no Primary Technician) need advisor assign.

### 2026-08-03 — Repair Action Assignment & Labor Recognition doctrine frozen

**PR:** (docs) RepairActions organize technician work; RepairActionOwner transfers; sheets from actions

**Files:** `docs/operations/repair-action-assignment-and-labor-recognition-v1.md`; `.cursor/rules/ark-repair-action-assignment.mdc`; `CURRENT_MILESTONE.md` R0–R5.

**Reason:** Multi-tech ROs and Friday-diag / Monday-repair collide when ownership hangs off the RO. Bare `owner_technician_id` would block Team/Vendor/Unassigned later. Dual owners and RO tech packets destroy deterministic pending work.

**Architecture impact:** **Repair Orders organize customer work. Repair Actions organize technician work.** `RepairActionOwner` (R1: Technician only) · transfers never copies · Completed by = current owner · tech never receives an RO. R1 = first technician-owned work unit — not “assign techs on ROs.” Orthogonal to Financial RED.

**Outstanding questions:** Resume R1 when prioritized.

### 2026-08-03 — Financial Authority v2 frozen

**PR:** (docs) Freeze Estimate / Ledger / Final Invoice; suspend living-invoice work

**Files:** `docs/ARK-FINANCIAL-AUTHORITY-V2.md`; supersede pointers in `docs/ARK-FINANCIAL-AUTHORITY-AND-CLOSEOUT.md` + `app/Ark/Operations/Financial/README.md`; `.cursor/rules/ark-financial-authority.mdc`; milestone F0–F6 in `CURRENT_MILESTONE.md`.

**Reason:** Advisor Refresh Invoice can lose the shop money. Invoice should not be a second living contract. RO1642-class drift came from issuing invoices too early, then patching sync.

**Architecture impact:** Target = Estimate (living) · Ledger (money) · Invoice (historical Final Invoice at closeout). Principle #4: invoice is consequence of closeout, not prerequisite. Living-invoice questions collapse when there is no living invoice. **Hard gate: F1 Financial Position before any invoice logic.** F2 advisor UI has no Invoice tab until Issue Final Invoice at Closeout Ready.

**Outstanding questions:** F1 Financial Position design when work resumes; open early-invoice migration after F1–F3.

### 2026-08-03 — Invoice sync: deposits reserve, payments settle

**PR:** (shipping) Living invoice stays aligned with approved work until settlement; customer-shown bills require explicit refresh

**Files:** `RefreshLivingInvoiceSnapshotAction`; `RefreshCustomerInvoiceAction` + controller/route; `BalanceDueResult::hasSettlementActivity`; `estimate_documents.customer_presented_at` + `snapshot_revisions_json`; financial rail out-of-date banner; email/PDF mark presented; tests.

**Reason:** Deposits were freezing the invoice the same as final payments, so mid-job approved work (RO1642 Oil Seal R&R) drifted from the bill. Deposits reserve work; payments/write-offs/refunds/credits settle.

**Architecture impact:** Three truths stay distinct — Approved Work · Invoice Snapshot · Ledger. Silent living refresh while only deposits exist. After customer presentation (email/PDF), drift surfaces as **Refresh Invoice** and archives the prior snapshot for audit.

**Outstanding questions:** **Superseded by Financial Authority v2** — treat this path as transitional compatibility; do not extend. RO1642 corrected on production via explicit refresh (data fix).

### 2026-08-02 — Book email identity = lead verify + shop mail branding

**PR:** (shipping) Email OTP for any lead; stop Laravel From/Thanks on customer mail

**Files:** `EmailVerification*` + `email_verifications` migration; book email send/check; `PublicBookExperienceMode` / `PublicLeadStoreController` email proof; acquaintance copy/CSS; `ShopMailBranding` (never Laravel); customer-message footer; `BookIdentityCodeMail`; tests; Phone Verification Authority (below).

**Reason:** Book email assumed portal customers (“email we have for you”). Appointment confirms showed From/Thanks **Laravel** when `shop_name` empty fell through to `APP_NAME`.

**Architecture impact:** Email possession is lead identity like SMS — known → portal recognition, unknown → guest. Appointment gate accepts phone **or** email proof. Portal `/access` stays known-customer-only. Mail From/Thanks use `ShopMailBranding::shopName()`.

**Outstanding questions:** Set production `shop_settings.shop_name` if empty; migrate `phone_verifications` + `email_verifications` after deploy.

### 2026-08-02 — Phone Verification Authority v1

**PR:** (shipping) ARK-owned OTP; Twilio delivers SMS only

**Files:** `phone_verifications` migration; `PhoneVerificationAuthority` / `PhoneVerification` / `PhoneVerificationNotification` / `PhoneVerificationException`; `config/phone_verification.php`; Book/lead adapters (`LeadPhoneVerification`, send/check controllers); deleted `TwilioVerifyClient`; tests.

**Reason:** Twilio Verify (~6¢/success) was provider authority. ARK now owns codes, hashes, expiry, attempts, rate limits, and short-lived verified sessions. Programmable Messaging from the shop number delivers OTPs (~1¢). Authority never knows why (book / portal / approve / mobile).

**Architecture impact:** Platform capability — `issue` · `verify` · `consumeVerifiedSession` · `ready`. Callers decide post-success. Book identity and website leads consume via `LeadPhoneVerification` adapter. No customer creation, login, or scheduling inside the authority.

**Outstanding questions:** Migrate production after deploy. Portal phone login can move onto the same authority later. Coolify `TWILIO_VERIFY_SERVICE_SID` unused — remove when Book smoke passes.

### 2026-08-02 — Book modal: force viewport popup (unavailable included)

**PR:** (shipping) Hard-fix `/book` overlay so it cannot render in document flow

**Files:** `public/book.blade.php` critical inline CSS + `position:fixed` style; `public-book-wizard.js` re-parents overlay to `document.body`; CSS z-index 400.

**Reason:** Production showed the unavailable card appended under homepage content when fixed positioning failed/stale CSS applied — not a popup.

**Architecture impact:** Presentation only.

### 2026-08-02 — Book Appointment presentation: modal overlay

**PR:** (shipping) `/book` is a route; presentation is a modal over the public homepage

**Files:** `PublicHomePageData`; `PublicBookController` / `PublicHomeController`; `public/book.blade.php` + `home-body` / `book-experience-panel` partials; overlay CSS (dim/blur, ~576px dialog, mobile bottom sheet); `closePublicBookOverlay` (history.back / Escape / backdrop); homepage symptoms + financing open Book with `concern`; identity complete preserves concern query.

**Reason:** Full-page blank canvas made booking feel like leaving the website. Restore AutoOps-style overlay: website stays visible, modal answers a few questions, close returns without a blank stage.

**Architecture impact:** Presentation only. Same Lead / identity / recognition authority. URL remains `/book`. Underlay is the public homepage shell for deep links and Book CTAs.

**Outstanding questions:** Observe whether customers expect Common Problems pages from homepage symptom taps (now Book-first). Identity gate readiness is shop Twilio + inbound SMS number (Phone Verification Authority). Unavailable-gate copy is customer-focused (call/text path), not infrastructure jargon.

### 2026-08-02 — Book universal SMS identity gate

**PR:** (shipping) Identity precedes scheduling for everyone on `/book`

**Files:** phone-first identity screen; `PublicBookIdentityCompleteController`; Twilio Verify send/check accepts `book_identity` even when homepage lead flag is off; guest wizard only after session proof; known phone → portal login + concierge; appointment store uses `consumeBookProof`; email secondary via portal access.

**Reason:** New customers had less friction than returning ones and could spam leads without proving the phone. Universal SMS verify; recognition decides the post-verify experience.

**Architecture impact:** Book Service closed again after this gate. No customer creation on unknown verify. Guest wizard remains presentation-only after proof.

**Outstanding questions:** Confirm production Twilio Verify SID is configured (gate shows Call/Contact if not). Observe spam drop for 1–2 weeks.

### 2026-08-02 — Book Concierge (recognized path)

**PR:** (shipping) Recognized `/book` is relationship-first, not a nicer form

**Files:** Vehicle Home concierge (`Welcome back` · last here · radar · last service · While it’s here · intent chips); known intents one-tap continue; recognized schedule = when + confirm only; `CustomerRecognitionProjection`; acquaintance + OTP return `/book`; guest wizard frozen as `I’m new here` fallback.

**Reason:** Restore recognition → relationship → intent → scheduling. Fast path: intent tap → when → confirm. Context path: absorb radar, optionally check While it’s here, then intent.

**Architecture impact:** Book Service consumer of Customer Recognition only. Observational projection. Same Lead + appointment-request authority. **Freeze booking after ship** — next features earned from customer behavior, not AutoOps parity.

**Outstanding questions:** Production phone smoke. Observe for 1–2 weeks before any booking expansion.

### 2026-08-02 — Customer Recognition Layer (Book Service consumer)

**PR:** (pending) Identity → Vehicle Home → Schedule Service on `/book`

**Files:** `CustomerRecognitionProjection`; `PublicBookExperienceMode`; `PublicBookController`; book acquaintance / vehicle pick / vehicle home partials; portal intended URL allows `/book`; book-friendly OTP copy; recognized schedule wizard mode; `still_on_radar` lead metadata (presentation only); CSS; tests (`CustomerRecognitionProjectionTest` + book/portal intake updates).

**Reason:** Guest wizard was a good form, not a relationship experience. Returning customers prove identity first, see Vehicle Home (summary, not workspace), then Schedule Service through existing Lead + appointment-request authority.

**Architecture impact:** Recognition is a disposable projection (Customer / Vehicles / Relationship / Context). Observational only — no customer/vehicle/RO/maintenance mutation. “Still on our radar” = deferred concerns only. Guest wizard remains unrecognized fallback (`?guest=1` / session). Stop after Book Service consumer.

**Outstanding questions:** Real-phone OTP → Vehicle Home smoke on production. Whether advisors want still-on-radar items surfaced on Lead interrupt UI beyond metadata.

### 2026-08-02 — Book Appointment v2 (presentation reset)

**PR:** (pending) Resurrect original ARK customer booking experience on today’s Lead / appointment-request authority

**Files:** `resources/views/public/book.blade.php`; `partials/public/book-wizard.blade.php`; `resources/js/public-book-wizard.js`; `vite.config.js` + lead-intake Vite order; `PublicBookWizardConcerns`; book wizard CSS; appointment thanks copy soften; `PublicLeadStoreController` nullable last_name for single-name book; tests (`LeadIntakeTest`, `LeadThanksProjectionTest`, `AppointmentRequestAvailabilityTest`, `SignedInLeadFormPrefillTest`, `PublicBookWizardConcernsTest`).

**Reason:** `/book` felt like a long ops form. Customers should answer a few tap-first questions. Same `public.leads.store` path, availability projection, preferred date/period, and Lead metadata.

**Architecture impact:** Presentation only. No Appointment authority, availability engine, schema, or route changes. Draft progress in `localStorage` (`ark.public.book.draft.v1`, 4h TTL) until submit or Start over. Single-name submits omit surname from `contact_name` (no `—` placeholder). Known vehicles only when portal session is signed in.

**Outstanding questions:** Real-phone interaction cert before production deploy. Whether Book CTAs should open an in-page overlay without navigating to `/book` — observe if `/book` as centered wizard is enough on the floor.

### 2026-08-02 — Wrong inspection template correction preserves history

**PR:** (pending) Template change with confirmation; supersede prior points

**Files:** migration `inspection_items.superseded_at` + inspection correction audit columns; `AssignRepairOrderInspectionTemplateAction`; Apply/checklist/posture filters; Builder template select confirm UX; `InspectionTemplatesV1Test`.

**Reason:** Advisors/techs who pick Standard vs Pre-Purchase incorrectly must correct without destroying captured condition/evidence.

**Architecture impact:** Still one Inspection per RO. Captured points are superseded (retained), not deleted. Active walk/coverage reads non-superseded items only.

**Outstanding questions:** Whether retained history needs a dedicated review surface on the walk — observe if advisors ask to see superseded points.

### 2026-08-02 — RO Builder Stability Incident (continuity close-out)

**PR:** (pending commit) Server-canonical Builder continuity + abuse tests

**Files:** `ark-worksheet-continuity.js` (POST body apply, no-store, cloneNode, Saving…/Saved/Couldn't save, idempotency key); `WorksheetMutationIdempotency` + Concern/Line store recall; Builder status flash + compose clarity; `RepairOrderBuilderStabilityIncidentTest`; `RepairOrderBuilderAdvisorTortureTest`.

**Reason:** Close the last UI-state gaps after proving no DB corruption — double-submit, ambiguous flash, stale mixed projections, compose/refresh mystery.

**Architecture impact:** Mutations remain redirect-to-canonical-Builder-HTML; UI replaces regions from server. No client financial math. Oil package stays action-idempotent; Concern/Line store short-window request idempotency.

**Outstanding questions:** Deploy + production floor cert. Local abuse pass done on RO 4849 (500ms–1s latency): double Concern/Oil gated; compose refresh discarded; disposition→totals 307.45→321.04→415.49 matched `EstimateTotalsCalculator`.

### 2026-08-02 — RO Builder Stability Incident

**PR:** (pending commit) Continuity totals refresh + visible mutation failures

**Files:** `resources/js/ark-worksheet-continuity.js`; Builder/review `continuityPanelIds`; Add Concern popup/error surfacing; Engine Oil continuity submit; `RepairOrderBuilderStabilityIncidentTest`.

**Reason:** Intermittent stale totals and silent Concern/line failures on the live Builder path.

**Architecture impact:** No authority reopen. Canonical totals still from `EstimateTotalsCalculator` via full Builder HTML refresh. Continuity now applies redirected POST bodies (`cache: no-store`, cloneNode panel replace) and surfaces busy/validation failures instead of silent no-ops.

**Outstanding questions:** Superseded by close-out entry above.

### 2026-08-01 — Repair Portal QR Slice 1

**PR:** Durable Repair Portal + Estimate PDF advertisement

**Files:** `docs/operations/repair-portal-v1.md`; migration `repair_order_portal_accesses`; `RepairOrderPortalAccess` + CreateOrReuse / Resolve; hub + evidence stream controllers; `RepairPortalHubProjection` / `RepairPortalAdvertisementProjection`; portal routes `/r/{code}`; `DocumentPdfPresenter` + estimate footer QR; Pest `RepairPortalAccessTest`; `ACTIVE_PR`.

**Reason:** Customer documents advertise one durable vehicle doorway instead of printing photo grids or minting per-document portals.

**Architecture impact:** Portal owns access; Evidence and Estimate PDF are consumers. Append-only codes (revoke replaces). Hub presentation may record first view / evidence presented; media stream does not.

**Outstanding questions:** Observe Estimate QR on the floor before invoice/SMS/sticker consumers; keep EstimateAccessToken until migration earns it.

### 2026-08-01 — Evidence Authority v1 Slice 1

**PR:** Foundational Evidence authority — Concern + RO General

**Files:** `docs/operations/evidence-authority-v1.md`; migration `evidence` / `evidence_attachments` / `evidence_visibility_history`; domain under `app/Ark/Operations/Evidence/`; staff gallery; Portal Shared + presentation viewed; mobile store/show; Pest `EvidenceAuthorityTest`; `ACTIVE_PR`.

**Reason:** One proof authority for advisors/techs/customers instead of inventing concern photos and repeating media systems across inspection/maintenance/portal.

**Architecture impact:** Immutable file proof; audited visibility; Primary on attachment; soft-retire retains bytes; customer viewed from presentation only; RO boundary invariant. Inspection photos remain parallel until migrate slice.

**Outstanding questions:** Observe floor upload/share rhythm before inspection migrate and retention purge.

### 2026-08-01 — Oil sticker with tech ticket + orphan re-add

**PR:** Floor fix — sticker print without Confirm Installed; re-add after deleted oil concern

**Files:** `OilChangeStickerGate` / `OilChangeStickerPrintController` / print menu; `AddEngineOilServiceAction` orphan cancel; `MaintenanceService::isLinkedAlive` / `markCancelledOrphan`; line/concern destroy hooks; `MaintenanceEngineOilServiceTest`; `maintenance-service-v1.md`.

**Reason:** Stickers ship with the tech ticket from Prepared; Confirm Installed remains history authority. Deleting the oil concern left an Active zombie that blocked Add.

**Architecture impact:** Print prefers event → Prepared → RO inference. History/Auto Detect still event-only. Idempotent Add only when session is linked-alive.

**Outstanding questions:** None for this fix.

### 2026-08-01 — Maintenance Service v1 (Engine Oil vertical slice)

**PR:** MaintenanceService + MaintenanceServiceEvent + PACKAGE line

**Files:** `docs/operations/maintenance-service-v1.md`; `RepairOrderLineType::Package` + `EstimateTotals` package cents (excluded from labor rollup / flag hours); migration `maintenance_services` / `maintenance_service_events` + `shop_settings.maintenance_engine_oil`; actions Add / Confirm / Cancel / ResolvePrepared; sticker Print gate (409 without current event); advisor panel + Confirm Installed page; `VehicleEngineOilHistoryProjection` on mobile vehicle workspace; Pest `MaintenanceEngineOilServiceTest`.

**Reason:** Advisors need one-click oil service without building $0 part lines; technicians need a fast Confirm Installed path; history/stickers must read immutable evidence, not preparation guesses.

**Architecture impact:** New Maintenance capability (Prepare → Install → append-only `MaintenanceServiceEvent` with `service_sequence` + supersession). PACKAGE is first-class sold semantics. Vehicle Specification NOT IMPLEMENTED. Principles: Unknown is better than wrong; historical events are evidence, not estimates. Operation Authority untouched.

**Outstanding questions:** Observe 20–30 real oil services before other kinds (transmission/coolant/…). PDF/portal Includes bullets from Prepared/Installed still thin vs panel UI — earn polish from floor use. Shop settings UI for oil brand/package price uses JSON defaults until Settings surface earns it.

### 2026-07-31 — Corner Inspection v1.1 technician flow polish

**PR:** Progressive disclosure on section walk (Green compact / Yellow·Red expand)

**Files:** `section-workspace.blade.php`; `ark-inspection-section-walk.js`; `app.css` point card disclosure.

**Reason:** Green should be effortless (condition + measurements only). Yellow/Red expand documentation per Builder `expand_when` without erasing prior notes/photos. Remove “Needs Outer…” instructional copy; missing slots highlighted on fields.

**Architecture impact:** Projection/UI only. InspectionItem truth unchanged. Builder metadata remains expand authority.

**Outstanding questions:** Point-mode walk-workspace polish deferred — section walk is tablet primary.

### 2026-07-31 — Inspection walk runtime placement snapshot

**PR:** Walk authority — snapshot Builder placement onto InspectionItem at apply

**Files:** migration `walk_section` on `inspection_items`; `ApplyInspectionTemplateAction` snapshots from `InspectionTemplatePointMeta::walkSection`; `InspectionPhysicalSectionMap::placementForItem`; `InspectionSectionWalkProjection` reads runtime snapshot only; Corner Builder meta `walk_section` / rear-axle meta; Pest snapshot + live-Builder isolation + legacy null path.

**Reason:** Walk must not couple to category labels or live Builder after apply. Technician placement is frozen at apply; category remains reporting language.

**Architecture impact:** New nullable runtime field `InspectionItem.walk_section` (placement authority, not condition truth). Null = legacy category projection. No historical backfill. Live Builder changes after apply do not move points.

**Outstanding questions:** Non-corner Standard points still leave `walk_section` null until those stages define Builder placement.

### 2026-07-31 — Corner Inspection v1.0 (Standard Phase 2A)

**PR:** Freeze + implement Corner Inspection into Builder metadata path

**Files:** `docs/inspection/corner-inspection-v1-freeze.md`; `FrozenInspectionTemplateDefinitions` (Standard corners LF→LR→RR→RF); `InspectionPhysicalSectionMap` (Corner Inspection first stage); `InspectionTemplatePointMeta` / `InspectionObservationLibraries`; `builder_meta` + `selected_observations` migration; catalog `rebuildStandardCornerInspectionV1`; section walk / living record / completion / brake comparison / point update + JS/CSS observation chips; Pest `CornerInspectionV1Test` + related updates; `.cursor/rules/ark-inspection.mdc` pointer.

**Reason:** Standard walk must follow technician physical corner order with measured tire/pads and GYR projection from Builder metadata — not hardcoded Tires+Brakes sections.

**Architecture impact:** Runtime authority unchanged (`InspectionItem`). Template configuration gains `builder_meta` (palette, photo policy, observation library). Colors remain projection labels over `observed_state`. Open inspections keep prior points; new applies use Corner v1. Steering / Under Vehicle / etc. not redesigned.

**Outstanding questions:** Full Builder admin UI deferred; brake-fluid correlation prompts deferred; mobile checklist parity with GYR/observations not in this slice.

### 2026-07-28 — Community Giveaways admin edit

**PR:** Editable campaign content in Operations

**Files:** `UpdateCommunityGiveawayAction`; admin edit/update controllers + routes; `edit.blade.php`; Edit Campaign / index Edit links; Pest.

**Reason:** Campaign copy and schedule lived only in seed/DB — operators could run winners but could not change public content without code.

**Architecture impact:** Authority remains `CommunityGiveaway`. Slug fixed after create (Events pattern). Acknowledgement keys preserved by index.

**Outstanding questions:** Image upload UI deferred — path string is enough for now.

### 2026-07-28 — Community Giveaways nginx path collision fix

**PR:** Move giveaway artwork off `/community/giveaways` static dir

**Files:** `public/assets/community/giveaways/window-ac.webp` (was `public/community/…`); seed path update; migrate existing `image_path` / `og_image_path`; Pest asset assert.

**Reason:** Production nginx served `public/community/giveaways/` as a static directory (301 → trailing slash → 403), shadowing the Laravel public route. App handled Host correctly; edge never reached PHP.

**Architecture impact:** None. Artwork must never live under a public route prefix that nginx can treat as a directory.

**Outstanding questions:** None — verify live `/community/giveaways` returns 200 after deploy + migrate.

### 2026-07-28 — Technician Time Clock: Lunch + Auto Day

**PR:** Rebuild wiped Time Clock lunch + admin-assigned auto day

**Files:** migration `add_time_clock_lunch_and_auto_day` (`technician_time_sessions.close_reason`/`origin`; `users.auto_clock_enabled`/`auto_lunch_minutes`); `TechnicianTimeSessionCloseReason` (`lunch` · `end_of_day`); `TechnicianTimeSessionOrigin` (`manual` · `auto`); `EnsureAutoClockSessionsAction`; `UpdateTechnicianAutoClockPolicyAction`; `SyncTechnicianTimeClockCommand` (`time-clock:sync-auto`); `TelephonyCallFlowSettings::openAtForDay`/`closeAtForDay`; `ClockInTechnicianAction`/`ClockOutTechnicianAction` (canBeClocked, optional origin/close reason, optional backdated timestamp); `RecomputeTechnicianCompensableDayAction` (auto-lunch deduction when unpunched); `TechnicianTimeSession` (`closeReasonEnum()`/`originEnum()`); `TechnicianTimeClockProjection` (`canBeClocked`, `canSelfPunch`, `staffForClockList`, `awaiting_lunch_return`); `TechnicianTimeClockController` (lunch flag on clock-out, `updateAutoClock`, ensure-auto on index/staff show); routes (`operations.time-clock.staff.auto-clock`); `bootstrap/app.php` schedule (`time-clock:sync-auto` every 5 min); `User` fillable/casts; rewritten `index.blade.php`/`staff.blade.php` (Out for Lunch / Back from Lunch / admin auto-clock form); `app.blade.php` nav via `TechnicianTimeClockProjection::canAccess`; appended Pest coverage to `TechnicianTimeClockPhase1Test.php`.

**Reason:** Time Clock v1 shipped proxy punch only. The shop needs lunch as a real unpaid gap (not a guessed deduction) and admin-assigned auto days for staff whose hours simply track Business Hours — without inventing attendance/PTO machinery.

**Architecture impact:** No new authority tables — `close_reason`/`origin` extend the existing punch row; auto day is materialized through the same `ClockIn`/`ClockOutTechnicianAction` write path, backdated to the Business Hours boundary rather than sync time. Self-punch and proxy-punch both expanded from technician-only to `canBeClocked` (technician · advisor · admin). Explicit punches always win — auto sync never reopens a session while awaiting a lunch return and never re-closes a day that already ended.

**Outstanding questions:** Observe whether shops want auto day per-weekday overrides (currently follows Business Hours as configured); no attendance/PTO scope creep intended.

### 2026-07-28 — Community Giveaways (Window A/C first campaign)

**PR:** Reusable community giveaway capability

**Files:** migration `community_giveaways` / `community_giveaway_entries` (status + attribution + duplicate_attempts); models + Active resolver; store (honeypot + 5/IP/hour RateLimiter); winner lifecycle select/confirm/decline/redraw; stats projection; public `/community/giveaways` (+ singular redirect) with countdown + awarded/closed postures; admin index/show/export/workflow; seed `window-ac-2026`; Pest.

**Reason:** Permanent Community Giveaways infrastructure — first campaign is Window A/C, future turkey/backpack/brake-job campaigns are data only.

**Architecture impact:** Entry `status` preserves history across redraws. Public path is plural. Email confirmation deferred. Gallery image fields deferred.

**Outstanding questions:** Confirm closing date copy with shop before launch; drop in final product photo if generated artwork is not preferred.

### 2026-07-27 — Inspection Section Walk Phase 1

**PR:** Inspection rapid section walk (anti-tunnel-vision)

**Files:** `InspectionPhysicalSectionMap`; `InspectionSectionWalkProjection`; `InspectionWalkWorkspaceProjection` (sections primary / `?point=` deep); `InspectionCoverageProjection` remaining CTA; `section-workspace.blade.php` + `show.blade.php`; `ark-inspection-section-walk.js` (+ walk save-error); CSS; `ValidatesInspectionScope` `return=sections`; Phase 1 Pest + updated Authority/ProductionEntry/QueryBudget.

**Reason:** One-point walk encouraged tunnel vision and made healthy vehicles slow. Section walk keeps whole-vehicle coverage visible while preserving observation authority.

**Architecture impact:** Projection/workflow only. Mapping: Tires+Brakes → On the lift; Under vehicle → On the lift; Under hood → Ground; Arrival/outside → Exterior & Safety; Road/operational → optional Road test stage. No schema change. Mark Remaining Good not shipped.

**Outstanding questions:** Observe floor speed + incomplete honesty before Phase 2 batch-good. Rebuild Vite assets for section Alpine component in deploys.

### 2026-07-27 — Inspection Integrity Phase 0

**PR:** Inspection Integrity Phase 0 (before section walk)

**Files:** `InspectionFindingLabelCollision`; `StoreInspectionFindingAction` collision guard; `InspectionReportCollidingFindingResolver` + `InspectionReportProjection` extras filter/merge; `UpdateInspectionChecklistItemAction::resolveMeasurementUnit`; `RepairOrderInspectionMeasurementStoreController` slot unit fallback; `tests/Feature/Operations/InspectionIntegrityPhase0Test.php`; minor `InspectionProductionEntryPhase1Test` assert casing.

**Reason:** Close contradictory Other Finding + template-point dual conditions before accelerating technician capture UX; persist template slot units (e.g. PSI) when clients omit unit.

**Architecture impact:** No schema change. Capture validates; customer projection prefers template-linked condition and merges orphan evidence without rewriting stored rows. Freeform Other Findings remain for genuine vocabulary gaps.

**Outstanding questions:** Observe whether shop labels collide on near-matches (e.g. “LF brake pads” vs “LF brake”) — Phase 0 is exact/normalized only. Phase 1 section UI not started.

### 2026-07-28 — Arrival Posture v1 (frozen)

**PR:** Arrival Posture v1 — Frozen

**Files:** `ArrivalPosture` / `ArrivalPostureProjection`; `ApplyAppointmentStatus`; `appointments.arrived_at` migration; desktop + mobile status controllers + update path; RO identity-band partial; `ArrivalPostureTest`.

**Reason:** Advisors must not pick a fake RO “Scheduled” status. Booking and check-in are calendar truth; the RO only projects Arrival Posture beside Visit Posture.

**Architecture impact:** Disposable read model only — no RO lifecycle mutation, no posture cache on repair_orders. `arrived_at` is evidence (first stamp, never cleared), not current state (`AppointmentStatus::Arrived` remains authority).

**Outstanding questions:** none — stop. Job Board lanes / orientation / automation need separate proposals.

### 2026-07-27 — Portal estimate view gate (SMS / iMessage previews)

**PR:** fix(portal): ignore link-preview bots for EstimateViewed

**Files:** `PortalCustomerViewGate`; wired into `PortalEstimatePage` / `PortalInspectionPage` `shouldRecordCustomerView`; `ResolveEstimateAccessTokenAction` / `ResolveInspectionAccessTokenAction` default `touchViewed: false`; `PortalCustomerViewGateTest`.

**Reason:** SMS/iMessage link previews hit the portal URL immediately and were recording EstimateViewed / `last_viewed_at` before the customer tapped. iMessage uses a Safari-shaped UA that embeds `facebookexternalhit` / Facebot / Twitterbot (not Applebot).

**Architecture impact:** Page still renders for crawlers (OG / preview OK). Attribution only when the gate allows. No JS beacon yet — escalate only if floor still sees false opens after deploy.

**Outstanding questions:** Watch Android Messages / RCS previews that may use cleaner UAs.

### 2026-07-27 — Inspection Reporting v1

**PR:** Inspection Reporting v1

**Files:** `InspectionReportProjection`, `InspectionCustomerEvidenceAllowlist`, `InspectionPhotoPurpose::isCustomerFacing`; `PortalInspectionPage` (auth/token/print); `PortalVehicleInspectionController` + print/pdf controllers/routes; `CustomerReportQrCode` (bacon/bacon-qr-code); portal blades `inspection-report` + `inspection-report-print` + finding/measurement partials; `CustomerVehicleDetailProjection` discovery links; tests `InspectionReportingV1Test` + visual fixture test; `SendInspectionLinkTest` updates; deleted unused `PortalInspectionSnapshot`.

**Reason:** Approved customer DVI reporting — one projection for Portal Simple/Detailed, Print, and PDF; allowlist evidence boundary; auth without token mint; QR only on safe share URLs.

**Architecture impact:** Projections only — no report authority tables. Failed groups into Simple headline Needs Attention without rewriting finding state. Print/PDF share presentation; video is link-only outside Portal.

**Outstanding questions:** Visual STOP review of fixtures under `storage/app/inspection-report-review/` before ship.

### 2026-07-28 — Time Clock lunch + admin-assigned auto day

**PR:** Time Clock lunch punches and auto day

**Files:** migration `2026_07_28_180000_*` (`close_reason`, `origin`, `users.auto_clock_*`); lunch Out/Back UI; `EnsureAutoClockSessionsAction`; `UpdateTechnicianAutoClockPolicyAction`; `time-clock:sync-auto` schedule; recompute lunch deduction for auto users; self-punch for advisor/admin; Pest.

**Reason:** Floor — lunch must be one-tap; some staff (admin-chosen) should not punch every open/close during Business Hours.

**Architecture impact:** Punches remain sole hours authority. Auto day materializes punches from Business Hours; lunch deduction is a documented auto-policy adjustment only when no lunch punch exists. Manual lock unchanged.

**Outstanding questions:** Observe forgotten Back from Lunch and whether auto lunch minutes match real floor lunch.

### 2026-07-27 — Technician Time Clock proxy punch (admin/advisor)

**PR:** Time Clock staff punch for technicians

**Files:** `TechnicianTimeClockProjection::canPunchForStaff` / `canAccess`; `TechnicianTimeClockController::{clockInForStaff,clockOutForStaff}`; staff in/out routes; index + staff Blade Clock In/Out; rail nav for advisors; Pest.

**Reason:** Floor friction — Landon forgets; advisors/admins need to punch him without Correct/Delete authority.

**Architecture impact:** Same `ClockInTechnicianAction` / `ClockOutTechnicianAction` with actor attribution. Correct/delete remain admin-only.

**Outstanding questions:** none — continue observation.

### 2026-07-27 — Technician Time Clock delete punch

**PR:** Time Clock delete punch (floor friction)

**Files:** `DeleteTechnicianTimeSessionAction`; `TechnicianTimeSessionStatus::Deleted`; `TechnicianTimeSession::scopeActive` / `openForTechnician` excludes deleted; recompute + projection skip deleted; `TechnicianTimeClockController::destroy`; staff blade Delete panel; Pest coverage.

**Reason:** Accidental test punches during ship verify had no void path — only Correct. Owner needed to remove a punch from compensable hours without inventing a fake zero-duration correction.

**Architecture impact:** Delete is a void (`status=deleted`) with required reason audit row (`field=deleted`), not a hard delete. Punch authority remains; Phase 1B hours recompute ignores deleted sessions. Manual-locked days unchanged.

**Outstanding questions:** none — continue Time Clock observation.

### 2026-07-27 — Technician Time Clock v1

**PR:** Technician Time Clock v1

**Files:** migrations `2026_07_27_220000_create_technician_time_sessions_tables`, `2026_07_27_220100_add_source_to_technician_compensable_time_entries`; `TechnicianTimeSessionStatus`, `TechnicianCompensableTimeSource` enums; `TechnicianTimeSession` + `TechnicianTimeSessionCorrection` models; `RecomputeTechnicianCompensableDayAction`, `MarkOvernightOpenSessionsAction`, `ClockInTechnicianAction`, `ClockOutTechnicianAction`, `CorrectTechnicianTimeSessionAction`; `UpsertTechnicianCompensableWeekAction` updated to inject recompute + manual lock; `TechnicianTimeClockProjection` + `TechnicianTimeClockController`; `resources/views/operations/time-clock/{index,staff}.blade.php`; nav link in `components/operations/app.blade.php`; `technician-production/show.blade.php` link; routes in `web.php`; `TechnicianTimeClockPhase1Test` (22 tests / 113 assertions passing alongside `TechnicianProductionAssistPhase1BTest`).

**Reason:** Phase 1B manual daily hour entry was the observed friction point (`docs/operations/technician-compensation-flag-floor-v1.md` Phase 1B notebook). Time clock replaces manual daily entry with real punches while keeping the Phase 1B daily-entry contract (`technician_compensable_time_entries`) unchanged.

**Architecture impact:** Punch authority is separate from the compensable-hours projection it feeds. Manual override remains authoritative and locked when set; punch-derived recompute never overwrites a manual-locked day. Overnight punches never invent hours on a prior day — they flip to `needs_resolution` and only accrue against shop-local today until closed/corrected. No breaks, PTO, geofencing, or payroll export — first UI doorway only (`/app/time-clock`); Companion/kiosk call the same actions later.

**Outstanding questions:** Observe floor adoption of self clock-in/out vs continued manual entry; watch `needs_resolution` volume before considering geofencing/reminders.

### 2026-07-27 — Inspection Templates v1 (frozen proposal)

**PR:** Inspection Templates v1

**Files:** migration `2026_07_27_230000_*`; Inspections seed/apply/assign/slots/completion/coverage/walk/brake comparison/road-test gate; Builder template select; walk Blade + `ark-inspection-walk.js`; `InspectionTemplatesV1Test` + related updates; `ACTIVE_PR.md`

**Reason:** Binding implement of `docs/inspection/inspection-templates-v1-frozen-proposal.md` — Standard default, PPI replaces, SM required, Disc/Drum, Δ prompts observation-only, scan + road-test gates.

**Architecture impact:** One Inspection per RO; RO owns required template; evidence blocks destructive template switch. No Finish Inspection / auto Concern / checklist expansion.

**Outstanding questions:** Floor — Landon uncoached Start Inspection friction (measurements, Disc/Drum, Δ prompts, road-test gate).

### 2026-07-26 — Home customer review per-refresh rotation

**PR:** Social proof — remove Greg Powell name lock

**Files:** `PublicFeaturedReviewProjection::rotating`; Home controller + proof/testimonial partials; Pest

**Reason:** Home hard-picked Greg Powell; review never changed despite a multi-review list.

**Architecture impact:** Home picks a random review from `customer_reviews` on each request. Common Problem pages keep theme/`forPage` matching. No Manage CMS expansion.

**Outstanding questions:** none

### 2026-07-26 — Public Surface composition photo roles

**PR:** Media-selection authority only (Home Theme v1 media half)

**Files:** `PublicSurfaceSettings` (`composition_photos` + `photoForComposition`); Home partials consume named roles; Website Manage gallery vs composition assignment UI; Pest

**Reason:** `shopPhotos[1]` meant diagnostic evidence only by accident. Photography is UI language — roles must be named.

**Architecture impact:** Gallery (`shop_photos`) stays. Composition roles point at gallery indexes. Unset roles fall back to legacy 0/1/2 so production imagery does not swap. Alt comes from the assigned gallery asset (conservative fallback if empty).

**Outstanding questions:** Production: explicitly assign three Home roles + correct pressure-test gallery alt. Then propagate Contact/CP.

### 2026-07-26 — Tech RO landing — inspection-first

**PR:** Earned Phase 1 friction — pure technician View → production work order, not Estimate Review

**Files:** `RepairOrderProductionLandingGate`; `RepairOrderProductionLandingProjection`; `RepairOrderProductionLandingController`; `technician-landing.blade.php`; branch in `RepairOrderEstimateReviewController`; `InspectionCoverageProjection` CTA labels (Start / Continue / Open); workspace strip label; tech Today **Open RO**; tech Workboard **Open work order**; Pest `TechRoProductionLandingTest` + Phase 1 / strip updates; `ACTIVE_PR.md`

**Reason:** Technicians opening assigned ROs from Workboard landed in advisor Estimate Review and had to decide what to do next — against inspect-first discipline.

**Architecture impact:** Projection/workspace routing only. Same Inspection authority. Walk stays on `/inspection`. Advisors/admins/multi-role unchanged. No Finish Inspection, lifecycle, or Phase 1b token.

**Outstanding questions:** Floor — is Workboard → Work order → Start Inspection instinctive? Reopen Finish Inspection only after entry hesitation is gone.

### 2026-07-26 — Flag + Floor Production Assist v1 — CLOSED / OBSERVATION

**PR:** Capability closed after production verify · observe shop friction

**Files:** Milestone + doctrine status only (no product code)

**Reason:** Mark v1 CLOSED / OBSERVATION. Shop generates requirements: clock entry habit, Completed timing, assignee accuracy, unassigned annoyance, whether the screen answers the management question. Friction chooses next feature — not Phase 2 speculation.

**Architecture impact:** None. Leave the capability alone.

**Outstanding questions:** Notebook only — time capture · Complete Work · assignment · compensation layer when earned.

### 2026-07-26 — Technician Production Assist Phase 1B — CLOSED

**PR:** Phase 1B — management visibility + base compensation assist

**Files:** `technician_compensable_time_entries`; `UpsertTechnicianCompensableWeekAction`; `TechnicianProductionAssistProjection` / `Controller`; owner views; routes + Reports catalog + rail link; config OT + `recognition_authority_starts_at`; Pest Phase1B; doctrine + milestone

**Reason:** Compose daily time + 1A recognitions + pending projection + effective-dated agreements into a shop instrument that answers “why is recognized flag low?” before dollars. Unknown ≠ zero for pre-adoption periods.

**Architecture impact:** Period is a projection range, not payroll-period authority. Pending is not a ledger. Assist is not a paycheck. Phase 0/1A untouched.

**Outstanding questions:** Floor observation on Completed timing; counsel before anyone treats assist as payroll; do not start time clock / OT $ / export / Phase 2.

### 2026-07-26 — Flag Recognition Authority Phase 1A — CLOSED

**PR:** Immutable flag recognition + compensation agreement history

**Files:** `technician_flag_recognitions` / `_lines`; `technician_compensation_agreements`; `RecognizeConcernFlagProductionAction`; `RecordTechnicianCompensationAgreementAction`; wired into concern production status (ops + mobile) and Staff; Pest Phase1A; doctrine + milestone

**Reason:** Audit found no trustworthy earn-time fact. Recognition freezes tech + flag hours per labor line on Completed. Agreement history versions Staff rate changes. No settlement/WIP/assist UI.

**Architecture impact:** Production recognition authority established. Phase 1B can compose without inventing earn timing.

**Outstanding questions:** Counsel on completion-earned flag; Phase 1B daily time + assist when prompted.

### 2026-07-26 — Flag + Floor Compensation Inputs — CLOSED

**PR:** Phase 0 closed — Herd migrate + Vite rebuild + milestone CLOSED

**Files:** Phase 0 implementation as prior entry; `CURRENT_MILESTONE.md` CLOSED block; doctrine status CLOSED

**Reason:** Local MySQL migration applied; Vite rebuilt; calculator verified (flag $25 / floor $15.16: 85% → $32 est., 50% → $38.81 est.); agreement persistence covered by tests. Phase 1 not started.

**Architecture impact:** None beyond Phase 0 close. Settlement/WIP deferred.

**Outstanding questions:** Phase 1 build prompt when ready (daily time, recognition, WIP, assist).

### 2026-07-26 — Technician Flag + floor compensation Phase 0

**PR:** Phase 0 only — compensation agreement vs estimated labor cost

**Files:** `flag_rate_cents` / `floor_rate_cents` migration; `config/technician_compensation.php`; `TechnicianFloorWageSuggestion`; `LoadedLaborCostCalculator` (max flag vs floor÷util); Staff UI + JS calculator; `docs/operations/technician-compensation-flag-floor-v1.md`; unit + StaffMemberManagement tests

**Reason:** Separate what the tech is paid under (flag + floor) from margin planning (`labor_cost_cents` = Estimated labor cost). Dated floor suggestion seeds new Flag techs and surfaces review — never silently rewrites stored agreements. Uses existing 85% utilization authority.

**Architecture impact:** No payroll settlement, WIP, top-up, pay periods, or OT. Phase 1 remains planned in the doctrine note.

**Outstanding questions:** Counsel gate before Phase 1 settle; preferred earning event = tech completes assigned labor.

---

**PR:** Milestone closure — theme authority across Home · task surfaces · Common Problems

**Files:** `docs/engineering/CURRENT_MILESTONE.md` (CLOSED block + reopen criteria); prior Phase 1–4 implementation already shipped

**Reason:** Visual grammar survived three customer surface kinds. Final corpus audit found content/SEO debt, not design-system failure. Further aesthetic iteration has diminishing returns.

**Architecture impact:** Presentation system frozen. Content enrichment and SEO H1 decisions are separate capabilities. Attention returns to operational work (e.g. Inspection / DVI).

**Outstanding questions:** None. Reopen only from usability friction, conversion evidence, a11y/responsive defects, or a new public surface needing grammar extension.

### 2026-07-26 — Public Theme System v1 Phase 4 complete (CP corpus exception audit)

**PR:** Calibration approved; corpus exception hunt; Theme v1 milestone frozen

**Files:** `CommonProblemAuthorityProjection` (`can_drive_is_safety`); `common-problem-authority.blade.php` (amber drive callout only for driveability headings); CommonProblemsTest; CURRENT_MILESTONE Theme complete

**Reason:** Shared shell already covered ~45 pages. Audit found one presentation leak: transactional “what’s included / why us” reused `can_drive_*` and inherited amber drive-safety chrome. Fixed without copy rewrite.

**Architecture impact:** Presentation only. Content/SEO freezes untouched. No Home/Phase 3 reopen.

**Outstanding questions:** Closed into Theme v1 CLOSED entry above.

### 2026-07-26 — Public Theme System v1 Phase 4 calibration (Common Problems)

**PR:** Phase 3 frozen; CP reference grammar on index · CEL · P0171 · Electrical (shell applies corpus-wide; STOP before deliberate bulk polish)

**Files:** `common-problems/show.blade.php` (answer-first identity; kill opening trust pills/reviews; quiet proof late); `common-problems/index.blade.php` (editorial list); `common-problem-authority.blade.php`; `CommonProblemAuthorityProjection` (dtc_code / plain_english_meaning); `app.css` (`.public-cp-*`); TrustChipsStructuralAnalyzer; FeaturedMediaTest; CURRENT_MILESTONE

**Reason:** Common Problems must read as diagnostic reference, not marketing pages. Same Theme v1 tokens; different composition.

**Architecture impact:** Presentation only. URLs, H1 topics, DTC IDs, SEO, schema, concern prefill, progressive-depth copy unchanged.

**Outstanding questions:** Calibration approved — corpus exception audit closed Phase 4.

### 2026-07-26 — Public Theme System v1 Phase 3 (inner surfaces)

**PR:** Home frozen; Book/Contact/Financing/Warranty/RepairPal aligned to Theme v1 grammar

**Files:** `app.css` (`.public-link`, panel/form native, RepairPal nav editorial, financing programs, contact reach/FAQ); `contact.blade.php`; `financing.blade.php` + conversion rail; `warranty.blade.php`; `repairpal/*` + authority-nav/profile-cta; `book.blade.php` (reassure dedupe + process densify); `public-mobile-contact-bar.js` (`#send-a-message`); CURRENT_MILESTONE freeze note

**Reason:** Propagate approved Home grammar without inventing per-page visual systems. Forms should feel native; chips/hex/card theater removed.

**Architecture impact:** Presentation only. Copy, SEO, lead forms, financing vendors, warranty/RepairPal language unchanged. Common Problems untouched.

**Outstanding questions:** Phase 3 approved — frozen.

### 2026-07-26 — Inspection tablet bay surface

**PR:** Real tablet view (`?surface=tablet`) — chromeless shell, fat condition taps, sticky Prev/Next, camera-first photo

**Files:** `InspectionWorkspaceUrl::tablet` / `InspectionCaptureLinks::tabletUrl`; walk projection surface threading; `components/layouts/inspection-tablet`; production show branches; walk-workspace tablet mode; photo redirect preserves surface; tablet CSS; Pest Phase 1 tablet assertions

**Reason:** Open Tablet View was the same URL as Open Inspection. Bay needs a distinct capture surface, not ops chrome.

**Architecture impact:** Projection/workspace query flag only. Same Inspection authority. Standard walk URL unchanged.

**Outstanding questions:** Whether bay tablets earn passwordless Phase 1b after authenticated tablet use proves the loop.

### 2026-07-26 — Inspection Recovery UX boundary (RO control vs walk host)

**PR:** Separate RO Inspection tab (control/review) from dedicated `/inspection` walk; fix condition labels

**Files:** `InspectionControlCenterProjection`; rail tab inspect Blade; walk-body + production show shell; condition label fixes in walk/living-record/prior-visit; finding-capture Other Findings copy; Pest authority + Phase 1 tests

**Reason:** Embedded RO walk + dedicated host mashed two jobs; Next left the RO shell mid-walk. Replace×2 was recommendation language leaking into condition projection.

**Architecture impact:** Projection/workspace only. Same Inspection authority. Device distribution on RO; walk stays on `/inspection`.

**Outstanding questions:** Floor observation for Finish Inspection / Phase 1b passwordless capture.

### 2026-07-26 — Inspection Recovery Phase 1 (Production Entry + Device Handoff)

**PR:** Inspection/DVI production path restored; Estimate Review is not the tech walk host

**Files:** `InspectionCoverageProjection`; `InspectionWorkspaceUrl` / `InspectionCaptureLinks` → production `/inspection`; `RepairOrderInspectionShowController` hosts walk; `inspection/show.blade.php` + entry-posture + device-handoff; RO edit + estimate-review entry bands; workboard / tech lanes / workspace strip; Pest `InspectionProductionEntryPhase1Test` + related updates

**Reason:** Authority and walk already existed; techs were not pulled into DVI. Phase 1 reconnects entry + coverage + safe device handoff without widening customer portal tokens or inventing completion.

**Architecture impact:** Projection/workspace only. No migrations. `InspectionAccessToken` remains customer read-only. Coverage ≠ completion ≠ lifecycle (`completed_at` still unwritten).

**Outstanding questions:** Whether passwordless bay capture (Phase 1b scoped tech token) is earned after floor use of authenticated Companion/browser handoff.

### 2026-07-26 — ARK-WEB Plain English v1 (closed · observing)

**PR:** Site-wide plain-English rewrite + review-schema compliance; shipped `94d3eef6`

**Files:** Public/Portal Blade + projections; `config/common_problems.php` + DTC codes; `public_seo.php` / `llms.txt`; `ShopSeoContext` / `AutoRepairSchema` (remove self-serving `aggregateRating`); review-request suppress placeholder; Pest SEO/trust/CP/Portal; docs `review-schema-solicitation-compliance-v1.md`, `portal-projection-friction-v1.md`

**Reason:** Customer-facing copy needed cognitive ease without changing policy, IA, routes, or SEO intent. Google Review Snippet rules forbid self-serving AggregateRating on the business’s own LocalBusiness/AutoRepair markup; visible Google ratings stay as trust proof only.

**Architecture impact:** None on authorities. Schema emission narrowed. Reviews workflow not redesigned — Closed + Paid capture remains; free-text suppress noted as future Eligible Review Request pressure.

**Outstanding questions:** None for website copy. Observe Search Console / GBP / leads before further public edits. Eligible Review Request only when Reviews reopens as its own capability.

### 2026-07-25 — Scheduling hours inherit Business Hours

**PR:** Staff booking follows shop Business Hours; Settings can blacklist/narrow days

**Files:** `SchedulingHours` (inherit helpers); `ShopSettings::schedulingHours()`; `TelephonyCallFlowSettings::fromShopSettings` (merge defaults when `weekly_hours` missing); appointments settings Blade + `updateAppointments`; legacy null migration; request-availability fallback; runtime doc; Pest unit + settings cases

**Reason:** Floor could not book Saturday — seeded Mon–Fri `scheduling_hours` blocked the guard, and the day checkboxes in Settings only controlled public `/book`, not staff scheduling. Partial telephony JSON without `weekly_hours` also resolved as every day closed.

**Architecture impact:** Business Hours become the default scheduling windows. Custom `scheduling_hours` are optional deviations only (null = inherit).

**Outstanding questions:** Whether holiday `closed_dates` should also block staff booking (telephony already has them).

### 2026-07-25 — PartsTech per-scope Open + Pull default

**PR:** Open PartsTech from a scope; Pull Quote defaults assignment to that concern

**Files:** `show.blade.php` (preferred concern + prepare body); scope settings PartsTech button; toolbar pull dispatch; `ark-partstech-quote-import.js` (preferred over payload default); CSS; Pest catalog + worksheet cases; `ACTIVE_PR.md`

**Reason:** Advisors source parts per concern; RO-only PartsTech left them re-picking scope on every pull.

**Architecture impact:** None. Preferred concern is worksheet posture, not PartsTech or estimate authority.

**Outstanding questions:** None for this slice.

### 2026-07-25 — PartsTech vehicle link by YMM

**PR:** Link PartsTech cart vehicle from year/make/model when VIN missing

**Files:** `PartsTechCatalogLauncher` (YMM identity + launch query); `PartsTechCartPreparer` (`vehicleSuggest` resolve + optional VIN on link); Pest catalog cases

**Reason:** Floor ROs often have YMM without VIN; VIN-only prepare left advisors picking the vehicle by hand.

**Architecture impact:** None. Still GraphQL cart prep; VIN preferred when present.

**Outstanding questions:** Whether ambiguous trim/engine needs advisor pick UI (currently best-match + warning).

### 2026-07-25 — PartsTech pull-quote tighten

**PR:** Stay on prepare + Pull Quote; make cart lock / login / VIN failures visible

**Files:** `PartsTechCartPreparer` (foreign-hold throw on syncOnly; VIN warnings); catalog + import controllers; quote reader + cart locator messages; `show.blade.php` open notice; `ark-partstech-quote-import.js`; Pest; deleted `public/_pt_iframe_probe.html`

**Reason:** URL params and iframe do not unlock Tekmetric-style punchout. Floor friction was silent PO skip on foreign cart and opaque login/VIN misses.

**Architecture impact:** None. Still shop GraphQL prepare + pull — not SMS partner punchout.

**Outstanding questions:** PartsTech SMS partner onboarding for Submit Quote (separate track).

### 2026-07-24 — Public header Book CTA + Contact inquiry form

**PR:** Book as primary action (not nav); Contact becomes general message form

**Files:** `CustomerSurfaceNavigation`; `site-header`; public header CSS (sticky + Book CTA); `contact.blade.php`; `contact-inquiry-form`; `ContactPageProjection`; `PublicLeadStoreController` contact inquiry path; Pest contact hub + nav tests; `IMPLEMENTATION_LOG`

**Reason:** Nav “Book” competed with the conversion action; Contact still collected vehicle intake. Separate intents: Book = vehicle work; Contact = talk to the business.

**Architecture impact:** None. Contact still creates Lead via existing recorder; vehicle/appointment fields remain on `/book`. No Appointment Truth from public.

**Outstanding questions:** None for this slice.

### 2026-07-24 — Appointment Request Availability (public /book)

**PR:** Weekly request defaults + horizon/notice + date exceptions; `/book` projects requestable days

**Files:** migration `appointment_request_availability` + `appointment_request_exceptions`; `AppointmentRequestAvailability` / `Projection` / `Exception` + controller; Settings Appointments UI; Schedule day toggle partial; `PublicAppointmentRequest` + `PublicLeadStoreController` + book/lead-form; Pest `AppointmentRequestAvailabilityTest` + LeadIntake/Thanks updates; `scheduling-runtime-authority.md`

**Reason:** Shop may be open Saturday while not accepting appointment *requests*. `/book` must only offer days staff are willing to intake — still Lead request, not Appointment Truth.

**Architecture impact:** New Configuration under Appointments. Independent of telephony Business Hours and soft-capacity Appointment guard. No public self-scheduling; no capacity math on `/book`.

**Outstanding questions:** Whether Morning/Afternoon should later map to clock windows from scheduling_hours (not required for v1).

### 2026-07-24 — Parts labels: print-all received + per-qty copies

**PR:** Batch print received parts labels; qty N → N stickers with `1/N`…`N/N`

**Files:** `PartsLabelPrintContext` / controller / pdf renderer; parts rail Print all; line card batch; `arkPrintPartsLabelsBatch`; `PartsLabelPrintTest`; `IMPLEMENTATION_LOG`

**Reason:** Floor needs one click for all received bags, and each physical unit needs its own sticker.

**Architecture impact:** None beyond shop-label print queue. Still no PO/inventory authority.

**Outstanding questions:** Whether Installed should join Print all (currently Received only).

### 2026-07-24 — Parts label printing (receive workflow)

**PR:** Parts labels on QL via QZ — RO# · YMM · PART# · description · qty

**Files:** `PartsLabelPrintContext` / `PartsLabelPdfRenderer` / `PartsLabelPrintController`; `parts-label.blade.php`; `PrintRoutingService` + `PrintRoutingController`; print-helpers `parts_label`; parts rail + line card Print label; `PartsLabelPrintTest`; `ACTIVE_PR`; `IMPLEMENTATION_LOG`

**Reason:** Floor was printing key tags as a workaround when parts arrive. Parts need bag/box identity (RO + YMM + part# + description + qty), not vehicle key-tag fields.

**Architecture impact:** New shop-label document type on existing label infrastructure. Same QL printer as key tags. No procurement authority change.

**Outstanding questions:** Whether auto-print on mark Received earns itself after floor use.

### 2026-07-24 — Public photography authenticity (defaults)

**PR:** Replace AI/stock default shop photos with real LugsNPlugs photography

**Files:** `public/shop-photos/{shop-bay,scan-data,lift-inspection,verifying-findings}.webp`; `public/assets/cloud/product/{shop-bay,scan-data,lift-inspection}.webp`

**Reason:** Repo fallbacks were AI/generic automotive imagery. Production already served real uploaded `public-surface-photos`; local/fallback paths still showed fabrications. Authenticity is part of diagnostic-authority positioning.

**Architecture impact:** None. Asset swap only. Settings uploads remain authoritative when present.

**Outstanding questions:** Wider bay/diagnostic hero stills would reduce reuse of the same four frames.

### 2026-07-24 — Homepage polish pass (editorial reduction)

**PR:** Reduce competing homepage stories; Book primary; Common Problem → /book concern handoff

**Files:** `public/home.blade.php`; `partials/public/home-*` + `home-second-opinion` + `home-common-problems` + `common-problem-book-cta`; `site-footer`; `CustomerSurfaceFooterData`; `CommonProblemRegistry` homepage featured; `CommonProblemLeadForm` / `CommonProblemFormCopy`; common-problem show/index; `ContactPageProjection` + contact form copy; `TelephonyBusinessHoursLabel` closed-weekend suffix; `app.css` brand-moment / compact problems; Pest public surface + hours tests; `IMPLEMENTATION_LOG`

**Reason:** After booking-first ship, homepage still carried directory density, gallery, selling footer, and Common Problem “Talk to a Service Advisor” posture. Polish removes competition and aligns conversion language without reopening funnel architecture.

**Architecture impact:** None. Lead intake + `/book` Appointment Request unchanged. No public Appointment writes. Hours still from telephony call-flow via `PublicSurfaceSettings`.

**Outstanding questions:** None for this slice. Production telephony Saturday disabled separately (Closed Saturday); scheduling capacity left independent.

### 2026-07-24 — Public /book: appointment-request UX honesty

**PR:** Preferred visit window + confirmation copy that does not imply Appointment Truth

**Files:** `PublicAppointmentRequest`; `PublicLeadStoreController`; book + lead-form partials; `LeadThanksProjection` / `success.blade.php`; `WebsiteLeadConfirmationCopy`; homepage book/process CTAs; Pest LeadIntake / LeadThanks / PublicSeo; `IMPLEMENTATION_LOG`

**Reason:** `/book` reused Lead intake but still read like a generic concern form. Customer must express shop-time intent and understand the shop confirms before anything is reserved.

**Architecture impact:** None. Preferred availability lives in Lead `metadata` + concern text for intake visibility. No Appointment rows from public submit.

**Outstanding questions:** None for this slice.

### 2026-07-24 — Public homepage: booking-first visual redesign

**PR:** Homepage conversion hierarchy + `/book` appointment-request (Lead intake)

**Files:** `public/home.blade.php`; `public/book.blade.php`; `partials/public/home-*`; `partials/customer/site-header`; mobile sticky bar + JS; `PublicBookController`; `PublicHomeController` (`?concern=` → `/book`); `CustomerSurfaceNavigation` / footer / breadcrumbs; `PublicSurfaceSettings` defaults; `public_legacy_redirects` (`/appointment|/schedule` → `/book`); `app.css` public design tokens; Pest public surface tests; `IMPLEMENTATION_LOG`

**Reason:** Generic web-lead rail was the homepage primary path. Visual redesign follows booking mockups (photo hero, red Book CTAs, process + testimonial) while preserving SEO Common Problems and Lead → intake authority. No public self-scheduling / Appointment writes.

**Architecture impact:** Presentation + conversion projection only. Appointment Truth remains staff soft-capacity. `/book` posts to existing `public.leads.store`.

**Outstanding questions:** Whether advisors want public capacity slot picking once floor soft-capacity is trusted (currently non-goal).

### 2026-07-24 — Estimate Workspace: rail scroll + deposit recommended work

**PR:** Unpin Estimate Total rail; include Recommended in deposit quote/breakdown

**Files:** `app.css` (ops-review-rail--pinned); `RepairOrderDefaultDepositCalculator`; `RepairOrderDefaultDepositTest`; `ACTIVE_PR` / `IMPLEMENTATION_LOG`

**Reason:** Sticky rail scrolled separately from the worksheet. Deposit used Estimate Total billable lines, which drop Recommended once Approved work exists — so Breakdown hid the parts advisors quote for deposit.

**Architecture impact:** None. Estimate Total still excludes Recommended when Approved exists. Deposit is a separate projection over Approved + Recommended lines.

**Outstanding questions:** Whether portal deposit prefill should also prefer recommended-inclusive quote when approved amount is zero.

### 2026-07-24 — Estimate Workspace: Waiting Approval send feedback

**PR:** Surface silent Draft/Estimate → Waiting Approval after estimate send

**Files:** `MarkEstimateAwaitingCustomerApprovalAction`; `SendEstimateLinkAction`; `EstimateDocumentEmailDelivery`; `SendEstimateDeliveryAction`; send controllers; `ark-conversation-quick-reply.js`; `SendEstimateLinkTest`; `RemoteSellWorkflowTest`; `ACTIVE_PR` / `IMPLEMENTATION_LOG`

**Reason:** Auto-move already existed but was silent and poorly tested (fixture started Waiting Approval). Advisors could not tell when send moved status vs when it was already Waiting Approval or blocked.

**Architecture impact:** Projection/feedback only. Same lifecycle authority path; JSON adds disposable `awaiting_approval` outcome.

**Outstanding questions:** Whether Draft → Waiting Approval should allow advisors (catalog is admin-only today).

### 2026-07-24 — Estimate Workspace: Approval Forecast presentation

**PR:** Invoice-style customer forecast; compact advisor forecast (shared projection)

**Files:** `repair-order-approval-forecast` (audience labels); PDF `_document-footer` + `document.blade.php` CSS; `estimate-totals-breakdown` quiet final; portal `_estimate-summary-panel` + `estimate.blade.php`; `ApprovalForecastProjection` docblock; `ApprovalForecastTest`; `CustomerAuthorizationTest`; friction notebook; `ACTIVE_PR`

**Reason:** Nested forecast card competed with Approved Total. Customer story is Approved Work → Additional Recommendations → If All Approved; advisors keep a compact dashboard strip.

**Architecture impact:** None. Projection Rule #1 — same disposable projection, different presentation.

**Outstanding questions:** None. Floor — confirm PDF destination weight vs Approved breakdown.

### 2026-07-24 — RO dock identity + posture; pinned Estimate Total

**PR:** Orientation dock carries VIN/posture; Estimate Total stays pinned in the right rail

**Files:** `RepairOrderWorkspaceStripProjection` (+vin); orientation header + workspace strip identity; `repair-order-rail-posture` dock layout; Builder/Review rails (`ops-review-rail--pinned`); `app.css` dock/posture/rail pin; `RepairOrderWorkspaceStripTest`.

**Reason:** Advisors need identity + Persistent Context while scrolling the estimate; money must stay glanceable without sticky-child failures when the rail is taller than the viewport.

**Architecture impact:** None. Posture still derived from `RepairOrderPosture`; no new authority. Projection Rule: strip packages VIN once.

**Outstanding questions:** None. Floor — confirm dock height vs content padding.

### 2026-07-24 — Estimate Workspace: reliable Diagnostic-first concern sort

**PR:** PDF/portal/worksheet use shared priority sort so Diagnostic leads and same types stay together

**Files:** `RecommendationIntent::sortedModels` / `sortedSnapshotConcerns`; PDF `document.blade.php`; portal `estimate.blade.php`; worksheet `show.blade.php`; unit test

**Reason:** PDF still used fragile `sortBy([...callbacks])`, so Diagnostic did not reliably pin first. Software already knows via Recommendation Intent — no new option.

**Architecture impact:** None. Sort projection only.

**Outstanding questions:** Whether advisors need a louder UI cue when a diagnostic concern is still set to Maintenance.

### 2026-07-24 — Estimate Workspace: strengthen PDF repair-action hierarchy

**PR:** Repair action titles dark/bold (what we're selling); finding stays why — not washed-out uppercase

**Files:** `document.blade.php` (repair-action-title / line-head-work); `_pdf-concern-chrome` (concern title weight); `CustomerRepairActionIncludes::groupHeading` title case; tests; friction + ACTIVE_PR / log

**Reason:** After flatten, concern findings dominated and repair actions demoted to light gray. Estimate scan order should be Priority → Problem → Repair → Components → Decision.

**Architecture impact:** None. Projection craft only.

**Outstanding questions:** Whether portal concern cards should use the same title-case repair headings.

### 2026-07-24 — Estimate Workspace: flatten priority containers

**PR:** Concern is the visual unit; priority is badge + sort order — not group wrappers

**Files:** `RecommendationIntent` (`displayEntries*`, `worksheetPinSortKey`); PDF `document` + `_pdf-concern` + chrome/theme; `estimate-review` / `estimates/show` / `show` worksheet; `scope-header`; portal concern card + sort; friction audit #4; tests; `ACTIVE_PR` / `IMPLEMENTATION_LOG`

**Reason:** Intent group containers caused empty headers across page breaks. Priority already lives on each concern — project that.

**Architecture impact:** None. Authority unchanged. Presentation deletion of group chrome.

**Outstanding questions:** Whether ops-intent-group CSS can be deleted later once unused.

### 2026-07-24 — Estimate Workspace: PDF estimate summary craft

**PR:** Tighten Estimate Summary PDF UI; keep Important Information in the summary frame

**Files:** `operations/documents/pdf/document.blade.php` (footer CSS); `_document-footer.blade.php` (structure); paired with Preliminary Estimate removal

**Reason:** Summary felt sparse (empty signature columns) and nested (totals card inside decision box). One quieter composition scans faster for customers.

**Architecture impact:** None. Projection chrome only.

**Outstanding questions:** Whether portal summary should match the same denser signature craft later.

### 2026-07-24 — Estimate Workspace: remove Preliminary Estimate notice

**PR:** Drop customer-facing Preliminary Estimate disclaimer from portal + PDF

**Files:** `portal/partials/_estimate-visit-context.blade.php`; `operations/documents/pdf/document.blade.php`; deleted `PreliminaryEstimateNotice.php` + unit test; `VisitReasonPhase01PolishTest`; `ACTIVE_PR` / `IMPLEMENTATION_LOG`

**Reason:** Advisors do not want the preliminary disclaimer on customer estimates; Reason for Visit and Important Information remain.

**Architecture impact:** None. Projection chrome removed; no authority change.

**Outstanding questions:** None.

### 2026-07-24 — Customers: store email lowercase

**PR:** Normalize customer email to trimmed lowercase on every write; backfill existing rows

**Files:** `Customer.php` (email mutator); migration `2026_07_24_180000_lowercase_customer_emails.php`; `tests/Feature/Operations/CustomerEmailNormalizationTest.php`; `ACTIVE_PR` / `IMPLEMENTATION_LOG`

**Reason:** Mixed-case emails cause duplicate-match friction and inconsistent display; advisors should not have to care about case.

**Architecture impact:** Same authority boundary as phone normalization — mutator owns storage shape. Import mapper already lowercased; Customer path now matches.

**Outstanding questions:** None.

### 2026-07-24 — Estimate Workspace: honest unsaved-changes guard

**PR:** RO Builder dirty tracking compares live fields to baselines; reverts clear the scare

**Files:** `ark-form-unsaved.js` (new); `ark-workspace-dirty-bindings.js`; `ark-ro-mode-control.js`; `ark-workspace-tabs.js`; `ark-worksheet-continuity.js`; `ACTIVE_PR` / `IMPLEMENTATION_LOG`

**Reason:** Sticky `markDirty` on any input left session dirty after advisors reverted edits, so View (V) and tab switches falsely warned “Unsaved changes detected.”

**Architecture impact:** None. Projection of form state only. Document/estimate PDF dirty (`EstimateDocumentService::markDirtyForRepairOrder`) unchanged.

**Outstanding questions:** Whether empty compose forms that never had a server baseline still over-prompt (observe on floor).

### 2026-07-24 — Estimate Workspace: Approval Forecast everywhere

**PR:** Show Approval Forecast in review mode, customer portal, and estimate PDF

**Files:** `ApprovalForecastProjection` (unchanged); `repair-order-approval-forecast.blade.php` (staff/customer/pdf variants); `RepairOrderEstimateReviewController`; `estimate-review.blade.php`; `EstimateSnapshotBuilder`; `EstimateDocumentPdfSnapshot`; portal `_estimate-summary-panel` + `estimate.blade.php`; PDF `_document-footer` + document CSS; `ApprovalForecastTest`; `EstimateDocumentPdfSnapshotTest`; `ACTIVE_PR` / `IMPLEMENTATION_LOG`

**Reason:** Forecast existed only on Edit. Advisors need the same conversation prep in Review; customers need Approved / Needs your approval / If you approve on portal + PDF without staff jargon.

**Architecture impact:** Projection-only. Snapshot gains disposable `approval_forecast`; frozen PDFs refresh forecast live via `withLiveApprovalForecast`. No new authority.

**Outstanding questions:** Whether customers should see concern count as “concerns” vs “items” (shipped as items).

### 2026-07-24 — Estimate Workspace: Waiting Approval send feedback

**PR:** Surface silent Draft/Estimate → Waiting Approval after estimate send

**Files:** `MarkEstimateAwaitingCustomerApprovalAction`; `SendEstimateLinkAction`; `EstimateDocumentEmailDelivery`; `SendEstimateDeliveryAction`; send controllers; `ark-conversation-quick-reply.js`; `SendEstimateLinkTest`; `RemoteSellWorkflowTest`; `ACTIVE_PR` / `IMPLEMENTATION_LOG`

**Reason:** Auto-move already existed but was silent and poorly tested (fixture started Waiting Approval). Advisors could not tell when send moved status vs when it was already Waiting Approval or blocked.

**Architecture impact:** Projection/feedback only. Same lifecycle authority path; JSON adds disposable `awaiting_approval` outcome.

**Outstanding questions:** Whether Draft → Waiting Approval should allow advisors (catalog is admin-only today).

### 2026-07-24 — Estimate Workspace: Diagnostic label + pin first

**PR:** Rename Diagnostic Required → Diagnostic; pin Diagnostic scopes first on builder and estimate

**Files:** `RecommendationIntent` (label, `pdfGroupOrder`, `worksheetPinSortKey`); `show.blade.php` worksheet sort; learn scopes-and-intent; unit + CustomerAuthorization tests; `ACTIVE_PR` / `IMPLEMENTATION_LOG`

**Reason:** Shorter intent label; diagnose-before-recommend scan order on builder and printed estimate.

**Architecture impact:** Presentation order only. Stored enum value unchanged (`diagnostic`). Deferred follow-up strength order unchanged (Immediate Attention still strongest).

**Outstanding questions:** Whether ↑↓ across Diagnostic vs other intents should warn advisors that Diagnostic re-pins on refresh.

### 2026-07-23 — Estimate Workspace: flatten RO builder nesting

**PR:** Match PDF flatten craft on Edit/Review estimate — compose panels + intent-group nesting

**Files:** `resources/css/app.css` (line-entry panels, labor-category text, intent-group scopes, review repair titles); `ACTIVE_PR` / `IMPLEMENTATION_LOG`

**Reason:** Worksheet repair chrome was flattened earlier; compose still opened colored nested cards, and Review stacked intent-group panel + slate inset concerns. Same “box inside box” advisors rejected on PDF.

**Architecture impact:** None. CSS only.

**Outstanding questions:** Whether procurement chips should quiet next (left alone — operational scan).

### 2026-07-23 — Estimate Workspace: flatten estimate PDF nesting

**PR:** Match worksheet craft on customer estimate PDF — no box-in-box repairs

**Files:** `operations/documents/pdf/document.blade.php`; `_pdf-concern-chrome.blade.php`; `CustomerPartPresentationTest` flatten assertions; `ACTIVE_PR` / `IMPLEMENTATION_LOG`

**Reason:** PDF still nested repair panels (border + gray header + tinted rows) and bordered type pills inside the concern — same friction advisors saw on Edit Estimate.

**Architecture impact:** None. Print CSS only. Intent groups and concern tint headers kept for customer urgency scan.

**Outstanding questions:** Whether portal HTML estimate card badges should quiet next (separate surface).

### 2026-07-23 — Estimate Workspace: retire Advisor Note

**PR:** Remove parallel concern Advisor Note; Note / Supporting Note owns staff context

**Files:** concern store/update controllers; show + review blades; meaning presentation; OperationalSheetPresenter; EstimateSnapshotBuilder; PDF/intake/internal estimate views; migration `2026_07_23_220000_migrate_concern_advisor_notes_to_private_note_lines`; note privacy / sheet / meaning tests; learn note-privacy

**Reason:** Advisors already have Note / Supporting Note with visibility. A second always-private scope field was vocabulary friction (box + accordion).

**Architecture impact:** Presentation + data migration only. `concerns.notes` cleared after copy to private note lines; column kept nullable. Snapshots stop surfacing concern notes.

**Outstanding questions:** Drop `repair_order_concerns.notes` after production migrate proves empty.

### 2026-07-23 — Estimate Workspace: flatten concern nesting

**PR:** Craft pass — remove box-inside-box on Edit Estimate worksheet

**Files:** `resources/css/app.css` (concern / repair-action / line-card / type-picker); `repair-order-concern-work-section.blade.php`

**Reason:** Concern cards still read as nested panels (left rail on repair actions, slate compose bands, bordered type chips). Advisors scan one workspace; chrome was leaking nested-card structure.

**Architecture impact:** None. Presentation only — one concern frame; repair titles as section labels; lines as rows; add-line actions as quiet links.

**Outstanding questions:** Floor check whether one concern border is still one border too many.

### 2026-07-23 — Estimate Workspace: Approval Forecast

**PR:** Live Approval Forecast on Edit Estimate totals rail

**Files:** `ApprovalForecastProjection`; `repair-order-approval-forecast` partial; `estimate-totals-panel`; `RepairOrderShowController`; `ApprovalForecastTest`; friction notebook pause #3

**Reason:** After an approved diagnostic, advisors pause while adding recommendations — they cannot answer “if approved today, what’s my total?” without mental math. ARKv1 had this answer visible.

**Architecture impact:** Projection only — `approvedTotalsForRead` + `recommendedTotalsForRead`. No new authority. Sticky rail updates via existing worksheet continuity.

**Outstanding questions:** Continue friction walk (Steps 6–11). Intake→Operational Choice cluster remains handoff-localized / unfixed.

### 2026-07-23 — Estimate Workspace: Friction Discovery (opened)

**PR:** Observation milestone — advisor friction audit only

**Files:** `docs/operations/estimate-workspace-friction-audit.md`; `ACTIVE_PR.md`; `CURRENT_MILESTONE.md`

**Reason:** Shop Memory removed typing friction. Next gains are decision/cognitive load. Capture pauses before naming any Estimate Workspace capability.

**Architecture impact:** None. Zero product code. Notebook is authority for next steps.

**Outstanding questions:** Where does momentum stop (intake → approval)? Clusters earn the next capability — not ambition.

### 2026-07-23 — Shop Memory v1 (complete)

**PR:** Shop Memory v1 — capability registry, gated providers, Add Concern popup, observation events

**Files:** `ShopMemoryFeatures` · catalog · gated `ShopMemoryServiceProvider` · problem-language providers · Add Concern popup · suggestion events · AI Rewrite action · routes/JS · Feature tests · runtime authority

**Reason:** Full v1 architecture may exist while observation still controls what is operationally active and what may influence ranking.

**Architecture impact:** Defaults — Historical Labor + Add Concern popup ON; other providers OFF and not engine-registered. Diagnostics compare catalog vs enablement vs registration. Events write-only with frozen outcomes. Vocabulary remains interim until HC earned. Milestone closed as **Shop Memory v1**.

**Outstanding questions:** None. Flip providers via `shop_memory` JSON only after notebook evidence.

### 2026-07-23 — Shop Memory Phase 2.5 (foundation hardening)

**PR:** Harden Shop Memory platform — no new surfaces

**Files:** Provider diagnostics/metadata/version; `SuggestionPipeline` (normalize→dedupe→rank); `SuggestionIdentity`; presentation contract; engine failure isolation + debug traces; expanded unit tests; runtime authority

**Reason:** Strengthen the platform before Phase 3 so new providers register without engine changes. Observation gate for problem-language remains closed.

**Architecture impact:** Ranking becomes replaceable; providers expose diagnostics; projections own presentation. Advisor labor suggest payload unchanged.

**Outstanding questions:** None. Still observe Phase 2 on the floor before Phase 3.

### 2026-07-23 — Shop Memory Phase 2 (historical labor language)

**PR:** Teach Shop Memory historical labor language

**Files:** `HistoricalLaborProvider`; `RepairOrderLaborMemorySuggestController`; `ark-labor-memory-suggest.js`; labor entry/edit Blade; CSS; `ShopMemoryServiceProvider` registration; Feature + Unit tests; runtime authority

**Reason:** Eliminate repetitive labor description typing via instant historical reuse. Type → Suggest → ↓ Enter. No AI. Shop Memory remains invisible — the advisor feels like the software remembers.

**Architecture impact:** First production Shop Memory provider (`historical_labor`, work-language corpus). Engine unchanged. Observation gate before Phase 3.

**Outstanding questions:** None. After floor use: observe accept / edit / ignore / dismiss before Phase 3.

### 2026-07-23 — Shop Memory Phase 1 (Suggestion Engine infrastructure)

**PR:** Shop Memory capability — engine only

**Files:** `app/Ark/ShopMemory/**`; `ShopMemoryServiceProvider`; `tests/Unit/ShopMemory/SuggestionEngineTest.php`; `docs/runtime/shop-memory-runtime-authority.md`; runtime README; `ACTIVE_PR.md`

**Reason:** ARK remembers how the shop works. Suggestions surface memory; advisors author; authorities persist. Phase 1 ships reusable infrastructure with no UI and no providers so Historical Labor (Phase 2) is a provider registration, not a new architecture.

**Architecture impact:** New capability `App\Ark\ShopMemory`. Providers never call each other; engine owns composition. Concern/Labor consume via projections and corpora (`problem_language` / `work_language`). AI Rewrite deferred as one future provider.

**Outstanding questions:** None for Phase 1. Next: Historical Labor provider + labor entry wire-up.

### 2026-07-22 — DayLens UI (projection chips)

**PR:** Schedule day board — reusable DayLens perspectives

**Files:** `DayLens.php`; `SchedulingWorkspaceProjection` (chips + filter); `AppointmentIndexController` (`?lens=`); appointments index + `ops-day-lens` CSS; SoftCapacity tests; runtime doctrine already frozen

**Reason:** One Appointment day board; projection owns chips/filtering; surface chooses default lens. Lens reduces visibility only — never reinterprets truth. No assignment UI bundled.

**Architecture impact:** Platform projection capability. Companion / Bay TV can reuse the same keys later.

**Outstanding questions:** None. Assignment UX remains a separate reopen.

### 2026-07-22 — DayLens projection contract (frozen)

**PR:** Doctrine only — no code

**Files:** `docs/runtime/scheduling-runtime-authority.md`

**Reason:** Stable projection contract for one day board with reusable lenses: projection-owned chips/filtering, surface-owned default lens, no assignment UI bundled, lens may reduce visibility but never reinterpret truth. Consumers: Advisor Schedule · Companion · Bay/TV.

**Architecture impact:** None until DayLens UI ships under the two-surface implementation gate.

**Outstanding questions:** None. Code when ≥2 surfaces benefit from the same projection.

### 2026-07-22 — Schedule card hover detail (dense columns)

**PR:** Projection polish — readable appointment detail on hover

**Files:** `calendar-card.blade.php`; `app.css` `.ops-cal-card__detail`; SoftCapacity test

**Reason:** Packed soft-capacity columns truncate card text. Hover/focus expands a detail panel from projection fields (customer, concern, time, labor, status) without reopening bay/tech surfaces.

**Architecture impact:** None. View/CSS only.

**Outstanding questions:** None.

### 2026-07-22 — Schedule is Agenda-only (no bay/tech surfaces)

**PR:** Soft-capacity scheduler identity — capacity + time only

**Files:** `AppointmentIndexController` forces agenda; appointments index/create/show + calendar-card (removed Bay/Tech UI); `docs/runtime/scheduling-runtime-authority.md`; SoftCapacity tests

**Reason:** Soft capacity made bay/tech lane boards the wrong product. Scheduler answers “can we fit this?” — not which bay/tech. Floor Planner deferred until earned.

**Architecture impact:** None. Columns may remain on `appointments`; scheduling UI no longer assigns or projects them.

**Outstanding questions:** None. Watch Molly on Agenda + capacity rail.

### 2026-07-22 — Bays as Floor Planner (projection honesty)

**PR:** Active bays only · natural bay sort · lane-owned location on cards

**Files:** `SchedulingWorkspaceProjection` (`finalizeFloorPlannerLanes`, `empty_lanes`); `AppointmentStaffOptions` natural sort; `AppointmentIndexController`; appointments index + calendar-card; `docs/runtime/scheduling-runtime-authority.md`; `SoftCapacitySchedulingTest`

**Reason:** Soft capacity made Agenda the scheduler. Ten empty bay columns were spreadsheet theater. Bays UI label stays; doctrine identity is Floor Planner — where work happens, not whether today has room.

**Architecture impact:** None. Projection-only. Appointment/capacity authority unchanged.

**Outstanding questions:** Observe Molly — does she live in Agenda and open Bays only for “where does this truck go?” Morning huddle (no timeline) stays later.

### 2026-07-22 — Agenda calendar cards pack side-by-side

**PR:** Projection polish — overlapping day-view cards

**Files:** `SchedulingWorkspaceProjection` (`packLaneCards`); `calendar-card.blade.php`; `app.css` `.ops-cal-card`; `SoftCapacitySchedulingTest`

**Reason:** Agenda cards spanned the full lane, so concurrent appointments stacked invisibly. Pack overlapping cards into columns; Agenda reserves three column slots so a lone card stays width-limited.

**Architecture impact:** None. Projection layout only — appointment authority unchanged.

**Outstanding questions:** None. Observe whether three preferred columns is enough on floor monitors.

### 2026-07-22 — Projection Rule #1 (audience language) written down

**PR:** Doctrine capture only — extend existing projection rule; no new platform doctrine file

**Files:** `.cursor/rules/ark-projection-rule.mdc`; `docs/ecosystem/ark-truth-stack-v1.md`

**Reason:** Soft-capacity scheduling, technician work order, and estimate UX all removed implementation leaks from projections while leaving authorities alone. Protect the mature loop: hesitate → suspect projection → simplify → leave authority.

**Architecture impact:** None. Clarifies audience language vs domain language; forbids renaming authorities to match UI copy.

**Outstanding questions:** None. Apply next in Communications, Inspections, ARK Voice.

### 2026-07-22 — Estimate UX simplification (presentation only)

**PR:** Advisor labels + suppress duplicate single-labor descriptions

**Files:** `LaborDescriptionPresentation`; concern-work-section / labor-line-edit / simple-line-entry; review + customer labor presenters; `_customer-line-description`; tech sheet presenter/blade; unit/feature tests. No migrations.

**Reason:** Advisors answer “what are we doing?” — not construct Operation vs Labor objects. Single matching labor description is suppressed in default UI; Advanced preserves full edit. Multiple labor lines unchanged.

**Architecture impact:** None. Presentation only. Stored descriptions untouched.

**Labor-rate test note (unrelated to this slice):** `RepairOrderEstimateWorkspaceTest` still expected pre–LaborAuthority behavior (`unit_price` alone = rate). Confirmed failing with UX changes stashed. Aligned three labor assertions to policy resolution + explicit `labor_rate_overridden` + reason. Separate pre-existing `assertSee` copy drift on part matrix chips remains outside this freeze.

**Outstanding questions:** None. Freeze — shop-trial before deeper estimate-engine changes. Prefer growing `LaborDescriptionPresentation` into a broader `LaborPresentation` only if more labor UI decisions accumulate.

### 2026-07-22 — Soft-capacity scheduling FROZEN

**PR:** Accepted architecture freeze after soft-capacity slice

**Files:** `docs/runtime/scheduling-runtime-authority.md` — mental model Appointment→capacity→ops assignment; policy-driven scheduling; capacity as reusable authority; workload ownership table (appointment expected vs estimate proposed vs RO approved); reopen = defect or earned pressure only.

**Reason:** Corrects bay-bound mental model to match floor: “We’ve got room Thursday” before bay/tech. Resist Dispatch / RO bay until pressure.

**Architecture impact:** None — documentation freeze of shipped behavior.

**Outstanding questions:** None. Stop.

### 2026-07-22 — Soft-capacity scheduling

**PR:** Bay-first hard scheduling → soft shop-capacity scheduling

**Files:** `SchedulingCapacityCalculator` / `SchedulingCapacitySnapshot`; shop_settings capacity basis · target % · warn/block; `AppointmentScheduleGuard` optional bay/tech + capacity enforcement; Day default Agenda; capacity rail shop snapshot; settings/forms/Learn/runtime; appointment + soft-capacity tests.

**Reason:** Scheduling answers whether the shop can fit the work. Bay and technician are optional operational planning, not appointment prerequisites. Shops may intentionally overpack via target %.

**Architecture impact:** Appointments no longer require `workstation_id`. Capacity math centralized. Communications station isolation unchanged. No Dispatch / RO bay / pricing reopen.

**Outstanding questions:** None for this cut — stop before Dispatch or RO workstation assignment.

### 2026-07-22 — Scheduler / bay architecture freeze

**PR:** Soft-remove + Comms isolation; retire orphan lane; runtime freeze

**Files:** `RemoveScheduleBayController` clears `workstation_id` on open appointments; `CommunicationsShopProjection` excludes schedule bays; `AppointmentStaffOptions::workstationsForAppointmentSelect`; Day lanes = bays + Unassigned only (no orphan lane); appointments settings confirm / Add-form `old()`; `docs/runtime/scheduling-runtime-authority.md` (protected sentence, soft-remove freeze, pressure gates); `AppointmentSettingsTest`.

**Reason:** Soft-remove is permanence; shared Workstation stays; orphan lane was corruption theater once clear-on-remove ships. Freeze: *Appointments reserve capacity. Repair Orders consume work.*

**Architecture impact:** None. Same authority. Orphan lane removed from projection.

**Outstanding questions:** None — RO bay assignment and tech↔bay matrix gated on floor pressure.

### 2026-07-21 — Send Deposit Request (custom amount pay link)

**PR:** Conversation-rail deposit request via portal Square link

**Files:** `amount_cents` + nullable `financial_document_id` on `customer_document_access_tokens`; `CreateCustomerDepositPayTokenAction` / `pay_deposit` scope; portal `/portal/pay` deposit branch + `PortalDepositRequest` capture surface → ledger deposit; `SendDepositRequest*` delivery (SMS/email/both); Quick Reply amount + Send Deposit; `SendDepositRequestTest`.

**Reason:** Advisors need to request a custom deposit before final invoice — parallel to Send Pay Link, not balance-due invoice pay.

**Architecture impact:** Token authority extended (not a new pay URL). Ledger deposits via existing `CompleteSquareDepositAction`. No counter deposit policy changes.

**Outstanding questions:** None for this cut. Mobile parity deferred.

### 2026-07-21 — Schedule Bays (not Communications Stations)

**PR:** Operator Bay vocabulary + Appointments settings surface

**Files:** Schedule/capacity/create/show Bay copy; `OperationalCapacityProjection` / `AppointmentScheduleGuard` hints; Settings → Appointments Bays (`Store`/`Update`/`RemoveScheduleBayController`); strip Accepts scheduled work from Communications Stations; `UpdateWorkstationController` preserves bay flag when field absent; runtime scheduling doc; appointment/capacity/settings/comms tests.

**Reason:** Bays are the scheduler’s noun. Phone stations stay under Communications; schedule resources are managed under Appointments.

**Architecture impact:** None. Same `workstations` + `accepts_scheduled_work` + `appointments.workstation_id` authority. No Bay table.

**Outstanding questions:** None. Stop — no tech↔bay matrix or RO bay assignment.

### 2026-07-21 — Bay-first Schedule (Work Stations default)

**PR:** Schedule Day defaults to work stations; appointment occupies a bay

**Files:** `AppointmentIndexController` / `SchedulingWorkspaceProjection` default `lanes=workstation`; Stations Settings `accepts_scheduled_work`; require `workstation_id` on create/update; capacity rail Work Stations label + Technician-role bars; calendar card Tech: label in station lanes; runtime scheduling docs; appointment/capacity/shop tests.

**Reason:** Shop floor question is where the vehicle goes, not whose calendar — bay is scarce; tech is assignment.

**Architecture impact:** Appointment authority already had `workstation_id`; now required for new saves. No tech↔bay matrix. Owners without Technician role stay off capacity bars.

**Outstanding questions:** Floor — enable Accepts scheduled work on Bay 1/2. Defer authorized bays / multi-tech.

### 2026-07-21 — Technician Work Order document refresh

**PR:** Tech sheet presentation-only redesign

**Files:** `OperationalSheetPresenter::tech()` production payload; `sheets/tech.blade.php` + `_tech-work-order-styles`; removed `_tech-pull-table-cols`; `OperationalSheetTest`; Learn `tech-production-sheet`.

**Reason:** Bay document still read like a customer estimate. Redesign as clipboard production work order with prominent Approved Flag Hours (shop-issued hours on approved work — not tech acceptance / not pay).

**Architecture impact:** None. Hour math unchanged. No pricing, payroll, RO bay assignment, or intake/customer document changes.

**Outstanding questions:** None. Stop — do not continue into compensation or bay-assignment authority.

### 2026-07-21 — Labor rate override reason on RO Builder

**PR:** Worksheet UI for Estimate Pricing labor rate overrides

**Files:** `repair-order-labor-authority-fields` (reason select + Use policy rate); Alpine `laborRateOverrideReason` / `usePolicyLaborRate`; labor line edit + concern work-section old() wiring; `LaborAuthorityTest` worksheet custom-rate store.

**Reason:** LaborAuthority required a coded override reason after Phase 5, but Builder had no field — custom rates failed validation / looked broken.

**Architecture impact:** None. Pricing engine unchanged; fills the outstanding worksheet gap.

**Outstanding questions:** None.

### 2026-07-21 — Scheduled Outbound Messages v1 frozen

**PR:** Scheduled Outbound Messages Phase 1 — approved freeze

**Files:** Capability marked Built · Frozen in `CURRENT_MILESTONE.md`; implementation already landed under Communications.

**Reason:** Discipline held — Estimate requests, Communications owns intent, job executes via existing `SendEstimateDeliveryAction`, Conversation becomes truth. No calendar/campaign leak.

**Architecture impact:** None further. Observe Tomorrow Morning vs Send Now on the floor; Phase 2 only if pressure clusters.

**Outstanding questions:** None. No further work until operational pressure earns it.

### 2026-07-21 — Scheduled Outbound Messages Phase 1 (Send Estimate)

**PR:** Communications-owned schedule intent for estimate delivery

**Files:** `scheduled_outbound_messages` migration; `ScheduledOutboundMessage` + status/type; `TomorrowMorningSchedule`; `ScheduleOutboundEstimateAction`; `CancelScheduledOutboundMessagesAction`; `DispatchScheduledOutboundEstimateJob`; send-estimate `timing`; Quick Reply after-hours prompt + Tomorrow Morning; tests.

**Reason:** Advisors finish web-lead estimates late without texting after hours or forgetting tomorrow morning. Intent ≠ Sent.

**Architecture impact:** Communications owns scheduled intent; job owns execution; only send path remains `SendEstimateDeliveryAction` → ConversationMessage + EstimateSent. No draft status. No date picker. Snapshot delivery mode + recipient phone/email. Next calendar morning 08:00 shop-local (not next open day).

**Outstanding questions:** None for Phase 1. Freeze — do not continue into configurable times, campaigns, or other message types until earned.

### 2026-07-20 — Visit Reason Phase 0.1 parked polish

**PR:** Customer estimate presentation polish (parked follow-ups)

**Files changed:** `CustomerRepairActionIncludes`; portal visit context + concern Includes; portal Important Information footer partial; PDF/browser `_document-footer` reorder; PDF visit “Customer reported:”; `VisitReasonPhase01PolishTest` + unit Includes tests.

**Reason:** Finish parked presentation items — trust prefix on Visit Reason, customer-readable Includes from R&R titles, Important Information after approval/decision UI.

**Architecture impact:** Presentation only. No Scope / pricing / approval authority changes.

**Outstanding questions:** None — ship when asked.

### 2026-07-20 — Visit Reason Phase 0 (+ propose/accept)

**PR:** Visit Reason (Phase 0) — intake correction + concern proposals

**Files changed:** `visit_reason` field; intake writes visit_reason only; `ProposeConcernsFromVisitReason` (heuristics + parser; OpenAI optional flag); accept/dismiss controllers; RO builder Suggested concerns UI; tests.

**Reason:** Preserve customer words as intake truth; restore advisor assistance via propose → Accept without auto-seeding or rewriting Visit Reason.

**Architecture impact:** None to Scope capability. Concern editor unchanged.

**Outstanding questions:** Enable OpenAI propose (`useOpenAi: true`) behind an explicit action when floor wants LLM titles beyond heuristics.

### 2026-07-20 — Bob v2 ship corrections

**PR:** Estimate Pricing / Operation Authority — review corrections

**Files:** removed `flatColumnPostures` sync; Labor Policies default is read-only + link to Labor Categories; fail-loud on missing shop-default Operation; preserve `operation_id` with snapshot on non-reprice edit; tests + doctrine

**Reason:** Engine must not encode program flat rates as invariant; default category ownership stays in Labor Categories; no silent Mechanical fallback.

**Architecture impact:** Matrix cells independent again. Operation Authority does not own shop default.

**Outstanding questions:** None for these corrections — commit when asked; production still held.

### 2026-07-20 — Estimate Pricing / Operation Authority floor hardening

**PR:** Rock-solid pass before ship

**Files:** `Operation::forLine` no silent wrong-class fallback; `UpsertLaborPolicyAction` flat-column sync (RepairPal/Warranty/Comeback/Internal); Settings copy; `EstimatePricingFloorIntegrityTest`; Draft status check via `RepairOrderWorkflowStatus::from`

**Reason:** Floor integrity — wrong Operation Class stamps, program rate drift across cells, and create-path status cast edge case.

**Architecture impact:** Flat program postures stay one rate for every Operation Class when Settings save one cell. Unknown operation codes fail loudly.

**Outstanding questions:** None for foundations.

### 2026-07-20 — Operation Authority v1 — Bob review follow-ups (docs/TODOs only)

**PR:** Operation Authority / Estimate Pricing — review capture

**Files:** `Operation::forLine` migration TODO; `LaborAuthority::resolveBillingPosture` transitional TODO; operations seed comment (not catalog); doctrine richness sentence; milestone follow-ups

**Reason:** Approve-with-cleanup from review. Mark transitional paths so they do not freeze as permanent architecture.

**Architecture impact:** None. Behavior unchanged.

**Outstanding questions:** None — execute follow-ups only when `operation_id` / explicit posture are universal.

### 2026-07-20 — Operation Authority v1 finish (tighten)

**PR:** Operation Authority — Phase 1 closed

**Files:** `Operation` (`operationClassKey()`, `forLine()`); deleted `OperationResolver`; stripped `implies_billing_posture` / `uses_legacy_category_rate` from Operation; posture + courtesy legacy rate live in `LaborAuthority`; `ark-operation-authority.mdc`; milestone freeze; tests

**Reason:** One question for the authority — `$operation->operationClassKey()`. No resolver. No pricing metadata on Operation.

**Architecture impact:** Foundational authority Complete · Closed. Reopen for verified defects only. Service Catalog is a separate capability (not on roadmap).

**Outstanding questions:** None for this authority.

### 2026-07-20 — Operation Authority Phase 1 (catalog + retire map)

**PR:** Operation Authority — Phase 1

**Files:** migration `operations` + `repair_order_lines.operation_id`; `Operation`; `LaborAuthority` consumes Operation class; deleted `LaborCategoryOperationClassMap`; `OperationAuthorityTest`; milestone

**Reason:** Operation owns Operation Class. Estimate Pricing remains stable consumer — only class source changed.

**Architecture impact:** Pricing resolver/snapshot doctrine untouched. Labor categories still drive hours/minimums and lookup Operation by code until `operation_id` is set.

**Outstanding questions:** Superseded by Operation Authority v1 finish — authority closed; catalog UI is out of scope.

### 2026-07-20 — Pricing snapshot immutability

**PR:** Estimate Pricing — snapshot immutability

**Files:** `ark-pricing-snapshot-immutability.mdc`, `LaborAuthority` (preserve snapshot on update), `RepairOrderLinePricing` / update controllers pass existing line, `reprice_labor` flag, `PricingSnapshotImmutabilityTest`

**Reason:** Policy changes and incidental line edits must not rewrite historical or open estimate rates. Snapshots are evidence. Reprice only when explicit.

**Architecture impact:** Closed ROs already blocked via `ensureOpenForEditing`. Open line updates preserve `labor_rate_cents` + policy snapshot unless `reprice_labor` or rate override. Labor policy Settings save never touches lines.

**Outstanding questions:** Bulk “reprice selected open ROs” UI later; parts matrix same invariant if not already.

### 2026-07-20 — Estimate Pricing Engine Phases 4–5

**PR:** Estimate Pricing Engine — Phase 4–5

**Files:** `RepairOrderLinePricing` (concern posture), `LaborAuthority` override audit, `LaborRateOverrideReason`, migration override audit columns, line persistence, tests, milestone

**Reason:** Explicit billing posture from concern into resolver. Rate overrides require coded reason + user/timestamp; preserve policy-resolved rate on the line.

**Architecture impact:** Default concern posture still defers to category→policy map (RepairPal/courtesy categories). Non-default postures win. Phase 6 Operation catalog not started.

**Outstanding questions:** Worksheet UI for override reason select · Operation catalog.

### 2026-07-20 — Estimate Pricing Engine Phase 3 (Labor Policies Settings)

**PR:** Estimate Pricing Engine — Phase 3

**Files:** `LaborPoliciesMatrixProjection`, `UpsertLaborPolicyAction`, `LaborPolicyResolverPreview`, `LaborPolicySettingsController`, settings partial + shop tab, route, `change_reason` migration, `LaborPolicySettingsTest`, milestone

**Reason:** Owners configure Billing Posture × Operation Class rates without code. Matrix + single-cell editor + Resolver Preview. No history/bulk/import.

**Architecture impact:** Settings UI only. Resolver remains sole rate path. Labor categories tab unchanged (hours/minimums).

**Outstanding questions:** Phase 4 concern posture · Phase 5 overrides.

### 2026-07-20 — Estimate Pricing Engine Phase 2 (LaborAuthority wire-in)

**PR:** Estimate Pricing Engine — Phase 2

**Files:** `LaborAuthority`, `LaborCategoryOperationClassMap`, `RepairOrderLinePricing`, `RepairOrderLineLaborInput`, `LaborAuthorityTest`

**Reason:** Labor rates resolve through `LaborPolicyResolver`. Temporary category→class/posture map until Operation catalog + Phase 4 RO posture. Courtesy keeps legacy category rate. Line snapshots persist policy identity.

**Architecture impact:** Rate path no longer reads `labor_categories.rate_cents` for non-courtesy categories. Hours/minimums/rounding still category-owned.

**Outstanding questions:** Phase 3 Settings UI · Phase 4 concern billing posture · Operation catalog.

### 2026-07-20 — Estimate Pricing Engine Phase 1 (LaborPolicyResolver)

**PR:** Estimate Pricing Engine — Phase 1

**Files:** migration `2026_07_20_191200_create_estimate_pricing_labor_policy_tables.php`; `OperationClass`, `LaborPolicy`, `LaborRateType`, `ResolvedLaborRate`, `LaborPolicyResolver`; `RepairOrderLine` snapshot fillable; `LaborPolicyResolverTest`; parked note rename in `CURRENT_MILESTONE.md`

**Reason:** Build deterministic labor rate resolution (Billing Posture × Operation Class) before UI. Seed operation classes + policies. Line snapshot columns ready; not wired into `LaborAuthority` yet.

**Architecture impact:** No Operation catalog exists — resolver takes posture + operation class key (not Customer/Operation models). Existing labor categories unchanged. `LaborAuthority` still owns live rate path until Phase 2.

**Outstanding questions:** Where Operation Class lives until a shop operation catalog exists (line input vs category→class map). Phase 2 wire-in.

### 2026-07-20 — Portal estimate financing apply options

**PR:** Portal estimate financing

**Files:** `resources/views/portal/estimate.blade.php`, `tests/Feature/Operations/SendEstimateLinkTest.php`

**Reason:** Customers reviewing estimates only saw a soft financing note. Enable `showProgramButtons` so Wisetack prequalify and Synchrony Apply appear beside totals on the portal estimate.

**Architecture impact:** Presentation only — reuses existing public financing projection and merchant URLs. No payment ledger / financing capture path.

**Outstanding questions:** Whether estimate SMS should also mention financing; observe click-through before PDF/email parity.

### 2026-07-19 — Global Command Palette (⌘K / Ctrl+K)

**PR:** Interaction sprint — command palette

**Files:** `OperationsCommand`, `OperationsCommandRegistry`, `RegisterCoreOperationsCommands`, `AppServiceProvider` scoped registry, `global-search.blade.php`, `ark-ops-global-search.js`, overlay CSS group/disabled rows, `OperationsCommandRegistryTest`, `OperationsCommandPaletteTest`

**Reason:** Borrow Linear/VS Code/Shopmonkey interaction craft — existing capabilities reachable from anywhere without authority or workflow changes. Extends existing ⌘K global search; does not add a second palette.

**Architecture impact:** Disposable command registry (interaction surface only). Modules register via scoped `OperationsCommandRegistry`. Permissions reuse `ArkCapability`. Substring filter only.

**Outstanding questions:** Observe advisor muscle memory before AI/fuzzy/history. RO-scoped print/pay need open RO context.

### 2026-07-19 — Contribution capability: inspection photo → Featured Media

**PR:** Contribution first ship

**Files:** `ContributeEvidence`, `ContributeInspectionPhotoController`, Featured Media normalize/trace, inspection finding-detail UI, `ContributeEvidenceTest`, `ACTIVE_PR.md`

**Reason:** First-class Contribution — human says evidence belongs in public. Featured Media is today's projection only. Traceable to RO + media.

**Architecture impact:** New Contribution domain action; no Shop Experience, GBP, or invented counts.

**Outstanding questions:** Observe floor use before GBP/social draft projections.

### 2026-07-19 — Interaction Pattern Library v1 (living companion)

**PR:** Design ops

**Files:** `docs/ecosystem/ark-interaction-pattern-library-v1.md`, `docs/ecosystem/reviews/*`, `.cursor/rules/ark-interaction-craft.mdc`

**Reason:** Living pattern inventory + append-only review stubs + template-pressure notebook. Patterns are identities; products are evidence. Frozen doctrine untouched. Workboard patterns validated from Wrenchy remain pending ship until requested.

**Rules:** No invented craft without screenshots/walkthrough. Convergence tracked per pattern across ARK surfaces.

**Outstanding questions:** Await Shopmonkey (then queue) evidence. Ship Workboard glance/density/Persistent Context when asked.

### 2026-07-19 — Restore Job Board card anatomy (marketing contract)

**PR:** Job Board visual restore

**Files:** `home-card.blade.php`, `card.blade.php`, `app.css` (job/workboard card blocks), `AdvisorHomeBoardTest`, pattern library note

**Reason:** Glance-rhythm ship (`a79ac071`) made Job Board cards worse than `job-board.png` on autorepairkeeper.com. Restored Tekmetric card anatomy (RO · status · customer · vehicle · money · promise · age). Kept Persistent Context rail.

**Architecture impact:** None. Presentation only.

**Outstanding questions:** None — screenshot is the contract until floor evidence earns a change.

### 2026-07-19 — M3 brief + multi-tenant development strategy (docs only)

**PR:** Cloud docs — no product code

**Files:** `docs/platform/cloud-m3-workspace-launch-brief-v1.md`, `docs/platform/multi-tenant-development-strategy-v1.md`, `.cursor/rules/ark-multi-tenant-development.mdc`, NEXT / critical-path / funnel pointers

**Reason:** Unlock M3 only after an explicit authority boundary and production acceptance gate. Freeze strategy: prove platform with new shops; migrate LugsNPlugs last.

**Architecture impact:** None in runtime. M3 code remains closed until brief accepted.

**Outstanding questions:** Accept M3 brief before implementation.

### 2026-07-19 — M2 production acceptance closed (live walkthrough)

**Evidence:** Company host cookies host-only; POST `/trial/shop` → 302 workspace; account → Shop Prospect `m2-acceptance-garage`; welcome/dashboard; fresh login resumes Shop. Ops `app.demo-auto.test` still `Domain=.demo-auto.test`. `/up` 200.

**M3 unlocked** — do not start until intentional; scope remains Workspace launch only.

### 2026-07-19 — M2 production acceptance: host-aware session cookies

**PR:** Cloud Funnel M2 acceptance

**Files:** `SessionCookieDomain`, `ConfigureSessionCookieDomain`, `config/session.php` (`host_shared_domain`), `bootstrap/app.php`, `SessionCookieDomainTest`

**Reason:** Production `SESSION_DOMAIN=.demo-auto.test` made browsers reject cookies on `autorepairkeeper.com` (POST /trial → 419). Company product host now gets host-only cookies; LugsNPlugs ops keep shared Domain.

**Architecture impact:** Experience contract unblocked on company host. No Tenant / Shop / M3 changes.

**Outstanding questions:** Re-run live funnel walkthrough; then close M2 acceptance and unlock M3.

### 2026-07-19 — Cloud Funnel M2: User owns Shop

**PR:** Cloud SaaS critical path M2

**Files:** `CloudShop.php`, `CloudAccount.php`, `Shop` + `User::ownedShop`, migration `owner_user_id` / identity columns on `platform_shops`, `CloudExperienceController` slug unique, `CloudClickthroughTest`, platform NEXT / critical-path docs

**Reason:** Replace session-only shop pretence with platform Shop owned by User. Funnel UI unchanged. No Tenant, ProvisioningRequest, Stripe, DNS.

**Architecture impact:** Shop authority now has an owner. Provisioning / workspace launch remain M3+.

**Outstanding questions:** None for M2. Do not open M3 until funnel Shop path is verified on production.

### 2026-07-19 — Workboard pattern ship: Glance · Density · Persistent Context

**PR:** Interaction Pattern Library — first Workboard ship

**Files:** `home-card.blade.php`, `card.blade.php`, `repair-order-rail-posture.blade.php`, RO show/review rail order, `app.css`, `AdvisorHomeBoardTest`, pattern library statuses

**Reason:** Ship three validated patterns only — Card Glance Rhythm, Workboard Scan Density, Persistent Context (existing posture presentation). No authority, workflow, hover, or new projection work.

**Architecture impact:** Presentation only. Board directs decisions; RO rail pins posture continuously.

**Outstanding questions:** Collect Shopmonkey screenshots/video next for falsifying reviews.

### 2026-07-19 — Cloud Funnel M1: Real Accounts

**PR:** Cloud SaaS critical path M1

**Files:** `CloudAccount.php`, `CloudExperienceController.php`, `User` (`MustVerifyEmail` + `cloud_funnel_draft`), migration, `cloud/login.blade.php`, `CloudClickthroughTest.php`, platform NEXT / critical-path docs

**Reason:** Replace session-only trial account with real User (hash, verify, login, forgot password, resume draft). Funnel UI and fake shop/provisioning unchanged.

**Architecture impact:** Account is authority; shop/tenant/provisioning remain session stubs until M2+.

**Outstanding questions:** None for M1. Do not open M2 until funnel account path is verified on production.

### 2026-07-19 — Freeze Interaction Craft vs Product Doctrine v1

**PR:** Design doctrine

**Files:** `docs/ecosystem/ark-interaction-craft-vs-product-doctrine-v1.md`, `.cursor/rules/ark-interaction-craft.mdc`

**Reason:** Freeze governing doctrine for competitor reviews — borrow interaction, never inherit mental models; Workboard Doctrine; Persistent Context; standing review format + checklist. Wrenchy is the worked example.

**Architecture impact:** None (doctrine only). Process: do not edit v1 during a review; notebook pressure → v2 only on clusters.

**Outstanding questions:** Validate template via Shopmonkey → Tekmetric → AutoLeap → Mitchell → Fullbay → Shop-Ware reviews.

### 2026-07-19 — Rotating public form placeholders (six-set wink catalog)

**PR:** Public lead / sign-in polish

**Files:** `PublicLeadFormPlaceholders.php`, `PortalAccessEasterEgg.php`, lead/sign-in blades, related tests

**Reason:** Rotate demo placeholders — Jenny, Hackers (Dade/Kate), Venkman, Ferris, Neo — with matching sign-in easter eggs.

**Architecture impact:** None — decorative placeholders only.

**Outstanding questions:** None.

### 2026-07-19 — Suppress FatalError ad-hoc CLI exception emails

**PR:** Runtime exception reporting

**Files:** `ExceptionReporter.php`, `ExceptionReportingTest.php`

**Reason:** Uncaught tinker/`php -r` failures become Symfony `FatalError` with empty `getTrace()`; `Command line code` only appears in the message. Reports `94774d71` / `8bca3ed2` emailed noise for bad ad-hoc SQL, not shop traffic.

**Architecture impact:** None — alert filter only.

**Outstanding questions:** None.

### 2026-07-19 — Product Milestone 1 priority flip

**PR:** Product track

**Files:** `PRODUCT-TRACK.md` / `NEXT.md` — First Self-Service Shop; funnel metric; product weeks 1–4; platform stubs underneath.

**Reason:** Bottleneck moved from architecture to “can a shop succeed without us?” Stancl is not the next product bet.

**Outstanding questions:** None.

### 2026-07-19 — NEXT.md (stub burndown)

**PR:** Daily pointer

**Files:** `docs/platform/NEXT.md` — remaining stubs metric; one stub at a time; spine litmus.

**Reason:** Tomorrow’s question is which stub disappears, not what we’re building.

**Outstanding questions:** None.

### 2026-07-19 — Sprint 2 frozen; Reality pending

**PR:** Sprint 2 exit criteria

**Files:** Sprint 2 exit criteria (Architecture ✅ / Engineering ✅ / Reality Pending); live milestone checklist; Sprint 3 sameness note.

**Reason:** Freeze code path; complete only when live Coolify proves contract. No Stancl until 1–3 are boring.

**Outstanding questions:** Live milestones 1→2→3 against control plane.

### 2026-07-19 — Sprint 2 complete + CoolifyExecutionStore rename

**PR:** Sprint 2 Coolify checkpoint

**Files:** Rename `CoolifyStepStateStore` → `CoolifyExecutionStore` (execution artifacts, not workflow state). Engineering Phase 1 roadmap: Sprint 2 ✅, Sprint 3 Stancl after live Coolify milestone walk.

**Reason:** Checkpoint — zero architectural decisions. Naming matches frozen language.

**Outstanding questions:** Live Coolify milestones 1–3 before deploy/Stancl.

### 2026-07-19 — Sprint 2 Coolify adapter complete (contract)

**PR:** Sprint 2 Coolify

**Files added:** Coolify DTOs, `CoolifyDeploymentMapper`, `CoolifyExecutionStore`, `HttpCoolifyClient`, `FakeCoolifyClient`, `CoolifyAdapter`, `ark:coolify:check`, `config/ark-platform.php`, expanded `CoolifyAdapterTest`.

**Files removed:** `config/coolify.php` (moved under ark-platform).

**Spine unchanged:** `ProvisioningOrchestrator`, `ProvisioningStep`, `ProvisioningStepResult`, request/shop/assignment schemas.

**Reason:** Replace stub with real adapter under milestone gate. Zero architectural decisions.

**Outstanding questions:** Confirm Coolify deploy response shape against live control plane when advancing past milestone 1.

### 2026-07-19 — Pressure reviews + boundary sentence

**PR:** Review culture

**Files changed:** engineering-principles (boundary + pressure); engineering-phase-1 pressure-review sequence; Sprint 2 celebrate criterion.

**Reason:** Post–Phase 1 interaction = pressure reviews. No pressure → keep the stub. Defend upward/downward leaks.

**Architecture impact:** Docs only.

**Outstanding questions:** None.

### 2026-07-19 — ARK Platform Manifesto v1

**PR:** Platform culture

**Files changed:** `docs/platform/ark-platform-manifesto-v1.md` — discover vs invent; freeze language; replace infra not Shop; stub not spine.

**Reason:** Team culture, not new doctrine. Center questions: truth, workflow, replaceability, responsibility.

**Architecture impact:** Docs only.

**Outstanding questions:** None.

### 2026-07-19 — Engineering Phase 1 declared

**PR:** Engineering Phase 1

**Files changed:** `docs/platform/engineering-phase-1-adapters.md` — earn confidence via adapters; DoD checklist; sticky note; Forge swap test.

**Reason:** Architecture finished discovering; engineering earns confidence. Success = replace infra without changing the model.

**Architecture impact:** Docs only. Mindset shift, not new authorities.

**Outstanding questions:** None.

### 2026-07-19 — Sprint 2 Coolify adapter (prove contract)

**PR:** Coolify adapter contract

**Files changed:** `sprint-2-coolify-adapter.md`; `CoolifyClient` / `FakeCoolifyClient` / `HttpCoolifyClient` / `CoolifyAdapter`; `config/coolify.php`; orchestrator uses adapter; swap + milestone tests.

**Reason:** Sprint 2 proves adapter contract under-built (milestone 1 = authenticate). HTTP behind client. Orchestrator/Shop/Assignment unchanged.

**Architecture impact:** Coolify is transport. Fake client when no token / testing.

**Outstanding questions:** Coolify API path accuracy for deploy/status when advancing milestones.

### 2026-07-19 — Engineering principles + doctrine freeze

**PR:** Platform practice doc

**Files changed:** `docs/platform/engineering-principles.md`; phase-1 + freeze board pointers.

**Reason:** Enough authorities. No new doctrine without repeated pressure. Guardrails for adapter sprints (not new architecture).

**Architecture impact:** Docs only. Sprint 2 = Coolify step.

**Outstanding questions:** None.

### 2026-07-19 — Orchestrator Rule + provisioning events

**PR:** Orchestrator Rule v1

**Files changed:** `orchestrator-rule-v1.md`; `ProvisioningStep::execute`; lifecycle events (Started/Step*/Completed/Failed); orchestrator emits events; tests.

**Reason:** Orchestrator coordinates only. Uniform adapter contract. Observability before Shop #27 fails at 2 AM.

**Architecture impact:** No Coolify yet. Sprint 2 = replace StubCoolifyStep only.

**Outstanding questions:** None.

### 2026-07-19 — Architecture Phase 1 closed + Provisioning Orchestrator Sprint 1

**PR:** Platform Phase 1 → orchestrator

**Files changed:** `architecture-phase-1-complete.md`; `ProvisioningOrchestrator` + stub steps; `completed_steps` JSON; `ark:provisioning:run`; `ProvisioningOrchestratorTest`.

**Reason:** Language finished. Sprint 1 proves orchestration (all stubs succeed, skip-completed retry). Adapters replace stubs later without changing the orchestrator contract.

**Architecture impact:** Only orchestrator marks Completed. No Coolify/Stancl yet.

**Outstanding questions:** None.

### 2026-07-19 — Adapter Rule v1 frozen

**PR:** Adapter Rule doctrine

**Files changed:** `docs/platform/adapter-rule-v1.md`; companions on provisioning-request + deployment-flow; freeze board.

**Reason:** Everything below ProvisioningRequest is replaceable. Orchestrator-only Completed; retry the request, not naked adapters. Last major domain freeze before adapter engineering.

**Architecture impact:** Docs only. No Coolify/Stancl code.

**Outstanding questions:** None.

### 2026-07-19 — Provisioning Request Authority + freeze Provisioning v1

**PR:** ProvisioningRequest scaffolding

**Files changed:** `provisioning-request-authority-v1.md`; `ProvisioningRequest` model/enums/migration; `deployment-flow-v1.md` freezes Shop→Deployment→Assignment→STOP; tests assert v1 does not create requests.

**Reason:** Provisioning is a workflow authority, not a Shop field. v1 establishes placement truth only; adapters hang off ProvisioningRequest later.

**Architecture impact:** No jobs/Coolify/Stancl. Layer: Shop → Deployment → ClusterAssignment → ProvisioningRequest → infra.

**Outstanding questions:** None.

### 2026-07-19 — Cluster Assignment Authority scaffolding

**PR:** Cluster Assignment before Provisioning v1

**Files changed:** `cluster-assignment-authority-v1.md`; `ClusterAssignment` + `ClusterAssignmentPolicy`; migration `accepting_new_shops` + `platform_cluster_assignments`; `DeploymentProfile` = Shared|Dedicated only; updated cluster/deployment-flow docs + tests.

**Reason:** Placement is a decision with history (why/who/when/previous). Enterprise stays a subscription plan, not infra enum. Policy chooses Healthy + assignable + lowest utilization.

**Architecture impact:** Provisioning v1 = call policy and stop. v2 = job after assignment. No Coolify/Stancl yet.

**Outstanding questions:** None for scaffolding.

### 2026-07-19 — Cluster Authority scaffolding

**PR:** Cluster Authority v1

**Files changed:** `docs/platform/cluster-authority-v1.md`, `deployment-flow-v1.md`; `App\Ark\Platform\{Cluster,Shop,Deployment}` + enums; migration `platform_cluster_authority_tables`; `ClusterSeeder` (opt-in); hidden `GET /app/platform/clusters`; `ClusterAuthorityTest`.

**Reason:** Establish Cluster as first-class authority provisioning will consume. No Coolify/Stancl/DNS/routing. Capacity and shop counts are observations (computed), not columns.

**Architecture impact:** Additive tables only. LugsNPlugs production flow untouched. Hierarchy: Shop → Deployment → Cluster → Coolify → Docker.

**Outstanding questions:** Capacity policy location when needed; when Platform Shop rows coexist with single-tenant LugsNPlugs.

### 2026-07-18 — Remove Job Board dark cockpit strip

**PR:** Job Board chrome trim

**Files changed:** `operations/home.blade.php` (drop `home-cockpit-strip` include).

**Reason:** Same sticky dark KPI strip as Today — Active Cars / Pipeline / Biggest Pending — added chrome without improving board action. Filter toolbar + columns remain.

**Architecture impact:** View only. `AdvisorHomeCockpitProjection` still built for column constraint cues.

**Outstanding questions:** None.

### 2026-07-18 — Shop Dashboard drill-downs + drop black bar

**PR:** Shop Dashboard actionable

**Files changed:** Today Blade (light toolbar, remove sticky black cockpit); `ShopDashboardProjection`/`Builder` drill URLs; `RepairOrderIndexController` `open` + `disposition` filters; RO index banner; CSS link styles; `TodaySurfaceTest`.

**Reason:** Black Tekmetric cockpit strip added chrome without value. KPIs were dead numbers — advisors need to open the ROs behind a lane or money bucket and act.

**Architecture impact:** Projection URLs only. Inventory filters compose existing RO index; no new authority.

**Outstanding questions:** Whether disposition filter should match line-dollar cohorts more tightly than concern disposition presence.

### 2026-07-18 — Today becomes Tekmetric Shop Dashboard

**PR:** Shop Dashboard Today

**Files changed:** `ShopDashboardProjection` + builder; `declinedTotalsForRead`; `TodayProjectionBuilder` (Owner/Advisor → dashboard, Technician → assigned lanes); Today Blade KPI strip + status bars + money table; CSS; `TodaySurfaceTest`.

**Reason:** Pressure/attention theater on Today was unused. Floor needs Tekmetric-shaped numbers: Car Count, Pending/Declined/Approved Sales, ARO, Close Ratio, status breakdown — from open-queue disposition authority.

**Architecture impact:** Projection only. Disposition $ via `EstimateTotalsCalculator` GET-safe reads. Posted sales stay on Reports/Bookend/Business.

**Outstanding questions:** Whether advisor dashboard should also offer a Service Writer filter; employee breakdown later.

### 2026-07-18 — Today Tekmetric density pass

**PR:** Today craft

**Files changed:** `operations/today/index.blade.php`; `app.css` (`.ops-today*` cockpit + dense lanes).

**Reason:** Post-v1 Today still read as stacked SaaS briefing cards. Match Job Board Tekmetric craft — sticky dark cockpit strip, signal counts, fixed-column scan rows, status chips — without changing composition/authority.

**Architecture impact:** View/CSS only.

**Outstanding questions:** Floor density after a morning with real pressure volume.

### 2026-07-18 — Role-aware Today Workspace v1

**PR:** Today + Business cockpit

**Files changed:** `TodayOperationalComposer` (replaces Owner/Advisor/Technician composers); `TodayProjection` / `TodayProjectionBuilder` / `TodaySection` (per-section caps + View all); `BusinessWorkspaceAccess` + middleware + `BusinessCockpitController` / projection / Blade; rail Home→Today + Business Cockpit; `StaffFrontDoor` lands all staff on Today; `EnsureAdvisorCommsCleared` exempts Today/Business; Today Blade (collapsed Why?); `TodaySurfaceTest`.

**Reason:** Universal Home mixed operational attention with owner metrics (Market Pressure, Growth, Yesterday). Advisors need orientation; owners need a Business cockpit that adds responsibility without replacing Today.

**Architecture impact:** Projection/surface only. No new authority, roles, or Operator Switcher. Business route + rail use one policy: `growth.access || financial.view || OwnerWorkspaceAccess`.

**Outstanding questions:** Whether advisors with `financial.view` seeing Business Cockpit (Yesterday KPIs) is right long-term; Operator Switcher later.

### 2026-07-18 — Merge Leads surface into Communications

**PR:** Leads → Comms merge

**Files changed:** `AdvisorLeadIndexController` (redirect to Needs); deleted `LeadIndexProjection` + leads index Blade; ops rail / Work surface / workboard header / interrupt panel; `CommunicationsWorkspaceContextBuilder` + context-panel disposal actions; `WebsitePerformanceProjection` spam observation; `lead-intake-authority-v1.md`; lead/comms tests.

**Reason:** Dual Leads page + Comms Inbox split opportunity triage. Advisors already see shop-turn website/SMS threads in Needs attention; Keep Lead authority, retire the advisor Leads surface.

**Architecture impact:** Surface-only. Lead model, states, intake, and create-contact unchanged. Spam stays out of Inbox (Website Performance).

**Outstanding questions:** Whether historical workboard lead cards remain useful; observe floor after merge.

### 2026-07-17 — Job Board productivity patterns across ops surfaces

**PR:** Cross-surface Move-to + destinations

**Files changed:** `x-operations.lifecycle-status-menu`; `RepairOrderLifecycleSelectProjection::statusTone`; Comms context (`CommunicationsWorkspaceContextBuilder` + context-panel); Customer Hub open-RO rows (`conversation-context-panel`); Calls & VM Text (`CallLibraryProjection` + calls index); Intake qualification cards (destination split + Move-to); legacy workboard `card.blade.php` parity; CSS; CallLibrary test.

**Reason:** Job Board Move-to / split destinations / lifecycle chip proved floor-useful; same friction existed on Comms, Hub, Calls, Intake, and legacy workboard cards.

**Architecture impact:** None. Same lifecycle PATCH + hub compose deep-links; projection/UX only.

**Outstanding questions:** Observe whether Hub blade-side `boardMoves()` cost matters with many open ROs.

### 2026-07-17 — Move complete scope to new RO

**PR:** Scope split / move concern

**Files changed:** `MoveRepairOrderConcernToNewRepairOrderAction`; `RepairOrderConcernMoveToNewRepairOrderController`; route `operations.repair-orders.concerns.move-to-new-ro`; `OperationalEventName::ConcernMovedToNewRepairOrder`; scope-settings + show blade (`Move to new RO`); `app.css`; `MoveRepairOrderConcernToNewRepairOrderTest`.

**Reason:** Advisors needed to peel a complete concern onto its own visit/estimate without retyping lines; deferred/supplemental work was stuck on the wrong RO.

**Architecture impact:** Reparent (not clone) of concern + lines onto a new Draft RO — same customer/vehicle; blocked after issued final invoice; lifecycle retreat when source loses approved work. No new authority store.

**Outstanding questions:** Whether move-into-existing-RO is earned later; post-invoice supplemental policy still open in financial docs.

### 2026-07-17 — Job Board card lifecycle choices match RO select

**PR:** Job Board lifecycle parity

**Files changed:** `RepairOrderLifecycleSelectProjection` (`forCatalogTargets`, `boardMoves`); `AdvisorHomeCardSurface` / `AdvisorHomeCardSurfaceProjection` (card menu uses same projection as RO lifecycle select); `home-card.blade.php` (disabled rows + close deep-links `?lifecycle=`); `ark-worksheet-continuity.js` (`applyLifecycleQueryIntent` opens paid/lost panels); `app.css` (disabled menu item); `AdvisorHomeBoardTest`.

**Reason:** Cards only listed status targets; RO select also shows close variants and blocked options. Advisors expected the same choices on both surfaces.

**Architecture impact:** None. Same lifecycle authority; close confirmation still runs on the RO workspace via query intent.

**Outstanding questions:** None.

### 2026-07-17 — Job Board menu/transition hygiene pass

**PR:** Job Board polish

**Files changed:** `ark-floating-comms-menu.js` (viewport flip upward + resize); `home-card` / `home-card-actions` (status + ⋮ menus use floating teleport panels); `home.blade.php` / `home-column` (live filtered column counts; remove fake "Sort by: Pressure"); `RepairOrderStatusCatalogDefaults` (stop full transition matrix; `deactivateNonCanonicalTransitions`; add estimate/waiting_approval → closed); `RepairOrderStatusCatalogUpdater` (custom status no longer reopens full matrix); migration `2026_07_17_212000_deactivate_non_canonical_ro_status_transitions`; `ConversationQuickReplyTest` (seed `PhoneSmsCapability`); catalog/home tests updated; recreate testing sqlite when malformed.

**Reason:** Card menus clipped in scroll columns; status Move-to listed every lifecycle jump; filter counts lied; Sort label was fake; Quick Reply tests 422'd without SMS capability; full matrix recreation would undo the prune.

**Architecture impact:** Transition catalog still Settings-configurable; non-canonical system↔system edges deactivated once. Custom statuses keep their edges. Same lifecycle controller.

**Outstanding questions:** Whether Settings → Workflow should surface a "Reset to ARK defaults" button after shops re-enable wide transitions.

### 2026-07-17 — Job Board card status moves + chip color separation

**PR:** Job Board card interactions

**Files changed:** `AdvisorHomeCardSurface` (+`statusMoves`); `AdvisorHomeCardSurfaceProjection` (catalog-allowed lifecycle targets per card via `RepairOrderStatusCatalog::allowedTargetSlugs` — in-memory, no per-card blockingReason queries; chip tones split: parts=violet, reply=sky, viewed=indigo, progress=blue, unassigned=slate, warn stays authorization); `home-card-actions.blade.php` (⋮ menu gains "Move to" PATCH forms to `operations.repair-orders.lifecycle.update`, no concurrency field — guard skips when absent); `home-card.blade.php` (passes RO model); `home.blade.php` (lifecycle error banner on redirect-back); `app.css` (new chip tones, menu heading/divider/move styles); `AdvisorHomeBoardTest` (+card move test).

**Reason:** Advisors had to open each RO to change status, and every chip was amber — no scan distinction between parts holds, customer replies, authorization waits, and active work.

**Architecture impact:** None. Same `RepairOrderLifecycleController` authority path enforces blocking reasons server-side; blocked moves bounce back with the reason banner (verified: "Assign a technician before starting work."). Close (paid/lost/review) intentionally excluded from cards — those confirmation flows stay on the RO workspace.

**Outstanding questions:** Observe whether advisors want close-from-card next, and whether the menu needs viewport flip on bottom-row cards.

**Follow-up (same day):** Status moves relocated from the ⋮ menu to a dropdown on the status pill itself (pill gains ▾ caret, opens "Move to" panel); ⋮ menu returns to + Finding / Customer hub / Text customer / Open RO only.

### 2026-07-17 — Job Board 5-column status split

**PR:** Job Board column expansion

**Files changed:** `WorkboardSwimlaneCatalog` (`advisorHomeBoardColumns` 3 → 5: Estimates / Waiting Approval / Waiting Parts / Work in Progress / Completed; lane→column mapping and per-column inventory URLs); `AdvisorHomeCockpitProjection::homeColumnKeyForStage` (Flow constraint highlight follows new keys); `AdvisorHomeCardSurfaceProjection::laborProgress` (also shows on `parts` column — those cards previously lived in WIP); `app.css` (desktop grid `repeat(3)` → `repeat(5)`); `AdvisorHomeBoardTest` + `WorkboardTriageTest` column assertions.

**Reason:** Three columns mixed unrelated work — estimates being built, estimates sitting on customer decisions (the stale/no-response pile), and parts holds all landed in two long lists with no real distinction. Splitting Waiting Approval and Waiting Parts into their own columns isolates customer-decision pressure and supplier pressure at a glance.

**Architecture impact:** None. Projection-only change on existing `RepairOrderStatus` authority — no new lifecycle states, no schema change. Column keys derive from the same lane map the workboard already uses.

**Outstanding questions:** Observe whether Estimates (draft + building) should further split, and whether 5 columns stay readable on shop tablets (<1024px falls back to horizontal scroll).

### 2026-07-17 — Comms workspace header/footer footprint

**PR:** Communications thread panel consolidation

**Files changed:** `thread-panel.blade.php` (identity header ~9 stacked lines → 2 flex rows: name·phone·email + chip/Mark handled; vehicle·RO·status·activity + inline next actions; story label removed); `composer-panel.blade.php` (Reply/Internal tabs and SMS/Email/Call transport merged into one bar; textareas 3→2 rows); `conversation-quick-reply.blade.php` (Send Address/Pickup/Hours/Callback collapsed into `More ▾` teleport menu, helper text removed, quick replies inline beside Send); `conversation-contact-quick-reply.blade.php` (2-row textarea); `quick-replies.blade.php` (label dropped, chips only); `ark-conversation-quick-reply.js` (moreMenu state + floating menus flip upward near viewport bottom with `top:auto`); `app.css` (identity-row styles, tighter header/composer padding, dead `next-actions`/`story-label`/`identity-line` rules removed).

**Reason:** Header and composer consumed most of the thread panel; conversations were barely visible. Consolidation keeps every action reachable while giving the timeline the majority of vertical space.

**Architecture impact:** None. Same identity projection, same composer routes/actions, same `ConversationMessage` send path. Blade + CSS + Alpine menu state only.

**Outstanding questions:** Observe whether the `More ▾` grouping of Send Address / Pickup Info / Hours / Callback slows advisors who used them frequently.

### 2026-07-16 — Job Board rows (Tekmetric-density pass)

**PR:** Job Board cleanup

**Files changed:** `home-attention-card.blade.php` (card → single dense row: identity · status chip · reason · money · age · actions); `app.css` attention zone/card styles (zone grid → bordered row list, table rhythm, narrow-viewport stacking); `tailwind.config.js` safelist for `ops-attention-card__(status|stale|age)--*` (dynamically composed tone classes were being purged, which is why status chips rendered unstyled in production); stale `WorkboardTriageTest`/`AdvisorTodayTest` assertions updated.

**Reason:** Card grid read poorly at 20+ ROs — five columns of tall cards, 3 buttons each, ragged heights. Advisor scan is top-to-bottom; rows with fixed columns restore Tekmetric-style scan rhythm.

**Architecture impact:** None. Same `AdvisorHomeAttentionBoardProjection` zones/rows, same search/filter dataset attributes, same status tone classes. Blade + CSS only.

**Outstanding questions:** Observe on floor whether the hidden reason column at narrow widths matters on shop tablets.

### 2026-07-16 — Growth opportunity preview `$authority` (5a077210)

**PR:** Production hotfix

**Files changed:** `GrowthOpportunityPreviewController`; `GrowthOpportunityContentExecutionTest` (staff preview regression).

**Reason:** Staff preview (`growth.opportunities.preview`) reuses `public.common-problems.show` but omitted `CommonProblemAuthorityProjection` — `Undefined variable $authority` on production.

**Architecture impact:** None. Preview now packages the same disposable authority projection as the public show path.

**Outstanding questions:** None — deploy when ready.

### 2026-07-16 — Messenger Platform Separation (PR 1)

**PR:** Messenger Platform Separation

**Files changed:** `MetaMessengerPlatformConfiguration`, `LegacyMetaMessengerPlatformCredentialsResolver`, `MessengerShopConnection`, `MessengerChannelConnection`, `MessengerShopPageResolver`, webhook/controller/parser/health/sender, `CommunicationsChannelSettings`, shop settings save + migration (`messenger_page_id`, encrypted `messenger_page_access_token`), Settings Messenger Blade, `ark:messenger:export-platform-env`, tests, `ACTIVE_PR`, roadmap note.

**Reason:** Establish platform Meta App vs shop Page ownership before production Messenger data / second-shop onboarding. Avoid single-shop `ShopSettings::current()` on webhooks.

**Architecture impact:** Conversation/PSID authority unchanged. Webhook verifies platform signature on raw body first, then routes each `entry[]` by Page ID. Health timestamps scoped by Page ID. Shop UI no longer edits App Secret / Verify Token.

**Outstanding questions:** Promote LugsNPlugs legacy secrets into Coolify `META_MESSENGER_*` via `php artisan ark:messenger:export-platform-env --show-secrets`. PR 2 = OAuth Connect Facebook.

**Follow-up (same day):** Permanent SaaS gate suite `tests/Feature/Operations/MessengerSaasGateTest.php` — Page `111111`→Shop A only; `222222`→Shop B only; unknown Page ack/no ingest; mixed `entry[]` independent routing. Inbound stores `metadata.page_id` for Page-scoped health. Floor connect LugsNPlugs before OAuth.

### 2026-07-16 — Sprint 1 slice 2 · RO Comms same timeline

**PR:** Sprint 1 Communications Workspace

**Files changed:** `ConversationRelationshipTimelineResolver::resolveForRepairOrder`; `UnifiedOperationalTimeline::forRepairOrderRelationship` (oldest→newest); strict `CallSessionTimeline::forRepairOrder`; `RepairOrderWorkspaceTabPresenter` (`timelineEvents` + message-based `hasConversationHistory`); RO Comms Blade → `event-bubble`, Estimate activity removed; quick-reply refresh via `data-timeline-refresh=comms-tab` (no hub-row append); Pest relationship timeline + tab/query-budget updates.

**Reason:** RO Comms was a messages+calls mashup with hub-event-row and customer-time-window call leakage. Continuity guardrail requires same renderer, strict RO links.

**Architecture impact:** Conversation/CallSession authority unchanged. RO projects linked evidence only; unlinked stays on customer continuity workspace. Realtime/send refreshes canonical RO tab instead of injecting legacy HTML.

**Outstanding questions:** Customer Hub → event-bubble later; floor demo screenshot of SMS+call+EstimateViewed on one RO.

### 2026-07-16 — Sprint 1 slice 1 · list identity + Next Actions

**PR:** Sprint 1 Communications Workspace

**Files changed:** `list-row.blade.php` (phone always + email/`No email`); `thread-panel.blade.php` (Next Actions label, Conversation copy); `app.css`; `CommunicationsOneInboxNeedsYouTest`; `CURRENT_MILESTONE` / `ACTIVE_PR` (four-sprint ladder + ship posture).

**Reason:** Stop doctrine churn; ship Sprint 1 acceptance. List was hiding email; actions strip lacked Next Actions label.

**Architecture impact:** None. Projection already enriched email — Blade now renders it.

**Outstanding questions:** Slice 2 — RO Comms same timeline/renderer.

### 2026-07-16 — Customer Continuity Workspace Guardrail (engineering)

**PR:** (docs / Cursor rule — not frozen Conversations doctrine)

**Files changed:** `docs/engineering/communications-workspace-rules.md`; `.cursor/rules/ark-communications-workspace-guardrail.mdc`; `docs/engineering/README.md`; bounded-context Cursor pointer.

**Reason:** Channel-first and message-first PRs drift one merge at a time. Architecture stays in frozen Conversations doctrine; this guardrail locks UI/implementation: continuity over messaging, relationship state above Conversation, identity never collapses, left list is not CRM.

**Architecture impact:** None to Conversation/CallSession authority. Workspace intent reframed to customer continuity (bounded context name may remain Communications). Design bar: answer a call without opening the RO. Transport is decoration (why → when → how). Prefer Conversation over Thread; reject Inbox/CRM list chrome.

**Outstanding questions:** Nav/product rename away from “Communications” only if floor earns it — guardrail vocabulary first, not a rename sprint.

### 2026-07-16 — Identity-First Communications Workspace

**PR:** (this ship)

**Files changed:** `CommunicationsWorkspaceIdentityProjection`; batch `CustomerCallContextResolver::mapForAttentionList`; `CommunicationsWorkspaceProjection` unified inbox filters; controller/fragment/redirect/NeedsYou; workspace Blade (list/thread/context/nav); `ark-comms-workspace.js` composer continuity; CSS control center; Pest One Inbox / H1–H3 / shell / prune / queue tests; engineering docs.

**Reason:** Advisors lose phone/email behind name-only rows and split Needs You / Waiting destinations. Popular comms products keep identity beside the thread; ARK must also keep vehicle/RO status visible.

**Architecture impact:** Conversation/CallSession authority unchanged. Needs attention remains a filter/sort over the same relationship projection — not a parallel inbox. Calls & VM stays evidence.

**Outstanding questions:** Floor-validate sticky header density; batch appointment/last-visit into context pane only after repeated ask; Companion identity parity later.

### 2026-07-15 — RO Courtesy / Trade · retail preserved

**PR:** (this ship)

**Files changed:** migration `collection_disposition`; `RepairOrderCollectionDisposition`; `WaiveRepairOrderBalanceAction`; `RepairOrderLedgerWriteOffController`; financial presenter/rail/payment strip; `InvoicePdfFinancialSnapshot` + document footer; `OperationalReportTotals` + Growth dispatch exclusions; Pest `RepairOrderCourtesyTradeWaiverTest`; financial authority doc.

**Reason:** Advisors need whole-RO courtesy/trade while still seeing what the work would have cost — zeroing lines or Lost close destroys that signal.

**Architecture impact:** Invoice snapshot remains retail authority. Write-offs clear AR. Disposition is configuration of collection outcome, not a parallel invoice total.

**Outstanding questions:** Partial waive UI only if floor asks; observe whether bad-debt posting needs a separate report bucket.

### 2026-07-15 — Needs You / Job Board list N+1 pass

**PR:** (follow-on speed)

**Files changed:** `CustomerCallContextResolver::resolveForAttentionList` (no orientation/timeline); `ShopTurnAttentionPresenter` uses it; `ConversationLeadResolver::mapForConversations`; `LeadConfirmationAuditConversation` audit fast-path + full reload when auditing; `ShopTurnAttentionQueue` batches leads; `WorkboardCardProjection` batches `mapForRepairOrders` balances.

**Reason:** Needs You still paid per-row call-context orientation + lead queries; Job Board N× balance lookups.

**Architecture impact:** Selection/context paths still use full `resolve()`. List pressure stays projection over Conversation authority.

**Outstanding questions:** Batch phone→customer matching across shop-turn rows; RO Inspect query budget already over 105 baseline.

### 2026-07-15 — App-wide speed · GET money reads + page hotspots

**PR:** (follow-on to shell perf)

**Files changed:** `TodayPipelineProjection`, `OperationalFlowProjectionBuilder`, `CustomerDecisionPressure`, `AdvisorWorkProjection`, `ServiceLaneIdentityPresenter`, auth rail Blade — `approvedTotalsForRead` instead of mutating `totalsForApprovedWork` on GET; `WorkboardTriageProjection` card memo across home passes; Needs You / Attention defer `AttentionCandidate` to selected row; Waiting on Customer loads customer-turn conversations only; Customer Hub lighter eager loads + smaller timeline until Comms tab.

**Reason:** Advisor home and RO rails were recalculating and writing approved totals on every GET; Needs You rebuilt timeline observations per list row.

**Architecture impact:** Write authority for approved totals stays on mutation paths. List pressure uses Attention queue placeholders; selected row still gets explainable reasons.

**Outstanding questions:** Measure QueryBudgetTest budgets after floor soak; RO show composition report next if still slow.

### 2026-07-15 — Ops shell performance · shared GET path

**PR:** (shell perf follow-on)

**Files changed:** `call-queue.blade.php` (poller → `resolveAttention`); `bootstrap/app.php` (presence before comms gate); `WorkstationBrowserBinding::touchSeen` throttle; `CommunicationWorkboardProjection::resolveLayoutCounts` (pass last-seen + SQL `needs_shop` COUNT); `LeadPressure::resolveOpenCount` + ops layout rail; `AdvisorCommsPressure` passes last-seen into layout counts.

**Reason:** Every `/app` page paid duplicate Attention composition (poller `resolve()` + gate/layout cache key mismatch), GET writes on workstation bindings, and six lead aggregates for a nav badge.

**Architecture impact:** Projections still compose Attention; cheaper paths for chrome counts. Binding `last_seen_at` still authoritative — updates every 5 minutes on GET or via heartbeat.

**Outstanding questions:** P1 page budgets (Job Board totals, Customer Hub over-fetch, Needs You candidate N+1) — measure before more shell work.

### 2026-07-15 — Public Contact Hub · `/contact`

**PR:** (this ship)

**Files changed:** `ContactPageProjection`, `PublicContactController`, `public/contact` view; route + legacy redirect; nav/breadcrumbs; `SeoEngine::forContactPage`; `contact_visit_notes` / `contact_faqs` in Website Manage; Pest.

**Reason:** Phone / address / hours searches need one Get in Touch destination — contact was only embedded on home and in the footer (`/contact` previously redirected home).

**Architecture impact:** Projection over ShopSettings + PublicSurfaceSettings + ShopSocialProfiles. Same `POST /leads` form. No new contact authority.

**Outstanding questions:** Floor copy on parking notes and FAQ answers; paste social URLs if not already set.

### 2026-07-15 — Public website · Connect With Us (social profiles)

**PR:** (this ship)

**Files changed:** `ShopSocialProfiles`; `PublicSurfaceSettings` + Website Manage form/controller; `CustomerSurfaceFooterData` + footer + `connect-with-us` component; `LeadThanksProjection` + thanks page; `ShopSeoContext` / `AutoRepairSchema` / homepage Organization schema; CSS; Pest.

**Reason:** Public presence needs official social/review profiles as a trust surface (why-click copy), not tiny footer icons — one settings source for website, portal shell footer, and SEO `sameAs`.

**Architecture impact:** Configuration in `public_surface_settings.social_profiles`; projections only. Google reviews URL unchanged. Blank channels omitted from UI and schema.

**Outstanding questions:** Paste real LugsNPlugs Facebook / Instagram / Nextdoor URLs in Website → Manage before launch looks complete.

### 2026-07-15 — Schedule command language on primary workspaces

**PR:** (this ship)

**Files changed:** Customer Hub Call/Text/Schedule/Create RO cluster; conversation quick-reply + contact Call peers; RO identity Message + Schedule Follow-up; vehicle commands aria; Pest `WorkspaceCommandLanguageTest`; ACTIVE_PR / milestone.

**Reason:** Schedule must be one of the core actions wherever the advisor already is — not a destination to hunt for.

**Architecture impact:** Reuses `ScheduleUrl` / `#communication-rail` hash→comms tab. No new authority. Photos deferred until vehicle media route exists.

**Outstanding questions:** Floor cadence — do advisors use Hub Call/Text or stay on Comms tab? Vehicle Photos when earned.

### 2026-07-15 — Schedule entry · `/app/schedule` + ScheduleContextResolver

**PR:** (this ship)

**Files changed:** `ScheduleContext`, `ScheduleContextResolver`, `ScheduleUrl`, `ScheduleEntryController`, `PresentAppointmentCreateForm`; route `operations.schedule`; CTAs on RO identity, Customer Hub (+ focused vehicle), fleet vehicle row, Conversation Quick Reply / contact composer; create search preserves context via `/app/schedule`; Pest `ScheduleEntryTest`; ACTIVE_PR / milestone.

**Reason:** Advisors were hunting for Schedule. Entry points must deep-link one workflow with context ARK already owns — conversation is entry only, never authority.

**Architecture impact:** Same Appointment create form underneath. No new authority. Legacy `/app/appointments/create` retained for existing links (redirect later).

**Outstanding questions:** Floor proof of four paths (Customer / Vehicle / RO / Conversation). Next: first-class Schedule action language on every workspace; Phase 2 migrate remaining `appointments.create` CTAs.

### 2026-07-15 — One Inbox performance hot fix

**PR:** (follow-on)

**Files changed:** `CommunicationsWorkspaceProjection` (Needs You skips relationship inbox hydrate); `AdvisorCommsPressure` + `CommunicationsNavPressure` + ops layout share one `resolveAttention` cache key via `previousLastSeenAt`.

**Reason:** Front door Became Needs You and accidentally ran Attention **plus** full open conversation/lead/call list presenters every request; layout also rebuilt Attention twice (null vs last-seen keys).

**Architecture impact:** Projection-only; same chrome; fewer questions per render.

**Outstanding questions:** Still measure hot surfaces after deploy — candidate N+1 on Needs You rows remains opportunity.

### 2026-07-15 — Communications v3 · One Inbox Shell (WIP · uncommitted)

**PR:** (local)

**Files changed:** `CommunicationsNeedsYou`; workspace chrome; Attention composition in Needs You; legacy redirects — not in this Schedule ship.

**Outstanding questions:** Finish isolate commit for One Inbox separately.

### 2026-07-15 — Message Actions v1 (WIP · uncommitted)

**PR:** (local)

**Files changed:** Message Action grammar + Send Address / reply interpreter — not in this Schedule ship.

### 2026-07-15 — Station bind “Not now” cookie

**PR:** (this ship)

**Files changed:** `WorkstationPresence` dismiss cookie (1y); `BindWorkstationController@dismiss` sets cookie + JSON 204; bind clears dismiss cookie; JS clears bind overlay class; Pest.

**Reason:** “Not now” only set a session flag — logins/session expiry brought *Where are you working?* back on stationary terminals.

**Architecture impact:** Browser still binds via `ark_workstation_binding` when Continue is used; dismiss is a long-lived browser preference, not station authority.

**Outstanding questions:** Prefer operators bind the actual station once on desk terminals rather than dismiss.

### 2026-07-14 — Portal deposit complete URL corruption

**PR:** (uncommitted)

**Files changed:** `_estimate-deposit-panel` + `invoice-pay` use `attempt => __ATTEMPT__` (not `str_replace('0', …)`); staff-preview deposit gate; JSON exception rendering; Pest.

**Reason:** Lymon Deniord (RO #1633) authorized and created 6 *pending* deposit attempts — initiate worked; complete URL was corrupted whenever the estimate token contained `0` (almost all `Str::random` tokens).

**Architecture impact:** None.

**Outstanding questions:** Ship ASAP so customer can retry Pay deposit on the estimate link.

### 2026-07-14 — Portal deposit staff-preview CORS

**PR:** (uncommitted)

**Files changed:** `_estimate-deposit-panel` staff-preview gate + same-origin deposit paths; `bootstrap/app.php` `shouldRenderJsonWhen` honors `expectsJson`/`ajax`; deposit abort messages; Pest coverage.

**Reason:** Staff preview on `app.*` fetched deposit APIs on `demo-auto.test` → browser CORS → “Online deposit is unavailable.” Square/portal pay was already enabled.

**Architecture impact:** None — payment authority unchanged. Preview mirrors invoice-pay (card disabled on operations host).

**Outstanding questions:** Confirm customer estimate link deposit on production after this ships.

### 2026-07-15 — Schedule H2 Call/Text on rows and day cards

**PR:** (this ship)

**Files changed:** `AppointmentScheduleRowPresenter` (`call_url` / `text_url`); `schedule-row` + `calendar-card` blades; cal-card CSS; Pest day-card Call/Text cases; milestone/ACTIVE_PR.

**Reason:** Advisors already learned Schedule; same Call/Text interruption path as appointment show and Job Board — no new messaging authority.

**Architecture impact:** Projection fields only. Text still opens Customer Hub compose → `ConversationMessage`.

**Outstanding questions:** Whether hover-only card links are discoverable enough on the floor.

### 2026-07-15 — Verify Schedule TZ + H1 Call/Text plan closed

**PR:** (docs / verification)

**Files changed:** `CURRENT_MILESTONE.md`, `ACTIVE_PR.md` (this entry).

**Reason:** Plan `ship_tz_then_h1` was already shipped in `4a3a9b02`; confirm Pest Call/Text cases green, `main`≡`production`, `/up` 200 — record closure so Schedule H1 is not re-opened as unfinished work.

**Architecture impact:** None. Conversation remains the send authority; Schedule only deep-links Hub compose.

**Outstanding questions:** Observe whether advisors use Schedule Text before Hub before any schedule-row CTAs (optional H2).

### 2026-07-13 — H1–H3 Conversations workspace

**PR:** (this ship)

**Files changed:** `CommunicationsWorkspaceProjection` turn filter/counts + Reason + Identity; list/thread/context Blade; `OperationsGlobalSearchProjection` + ⌘K compose; Pest `ConversationsH1H3WorkspaceTest`; milestone/ACTIVE_PR.

**Reason:** Owner override — ship Operational Inbox / workspace stack / compose-search; pivot from floor later.

**Architecture impact:** Projection-only. Turns remain computed. Calls & VM untouched. Search opens existing destinations or `ConversationResolver::forCustomer`.

**Outstanding questions:** Floor notebook still valuable for Reason copy and five-second rhythm; appointment SMS remains parked.

### 2026-07-13 — Parts Capture OCR fallback for scanned PDFs

**PR:** (this ship)

**Files changed:** `DealerQuoteOcr` (pdftoppm + tesseract); `DealerQuoteTextExtractor` fallback; Dockerfile installs `tesseract-ocr` + `poppler-utils`; quote photo upload; OCR dash/Q-number normalization; Pest OCR coverage.

**Reason:** Penkhus dealer quotes arrive as image-only PDFs — embedded text extraction returns empty.

**Architecture impact:** Capture path unchanged; OCR is an extraction fallback, not a new authority.

**Outstanding questions:** Real Penkhus layout accuracy on floor; confidence UI still V2.

### 2026-07-13 — Parts Capture v1 (Capture Dealer Quote)

**PR:** (this ship)

**Files changed:** `dealer_quotes` / `dealer_quote_lines` + `repair_order_lines.dealer_quote_line_id`; `DealerQuoteParser` / `CaptureDealerQuoteAction`; RO Capture Quote UI; Source → quote show/download; `smalot/pdfparser`; Pest `DealerQuoteCaptureTest`; `docs/operations/parts-capture-v1.md`.

**Reason:** Advisor has paper/PDF dealer quotes (e.g. Penkhus) and needs parts on the estimate without PartsTech. Capture provenance as authority, not `ImportedFromPDF`.

**Architecture impact:** Dealer Quote is authority; estimate lines project from quote lines. Orthogonal to PartsTech cart import. Explicit H0 park exception for floor urgency.

**Outstanding questions:** Real Penkhus PDF layout may need parser tweaks; V2 confidence / V3 part memory parked.

### 2026-07-13 — Missed Call Rescue (TextBack) restored

**PR:** (this ship)

**Files changed:** `TelephonyCallFlowSettings` + shop defaults; Communications → Hours UI; `ScheduleMissedCallRescueAction` from `ProcessCallStatusAction`; `SendMissedCallRescueSmsJob` / `SendMissedCallRescueSmsAction` / `MissedCallRescueCopy`; Pest `MissedCallRescueTest`.

**Reason:** Restore old ARK-SMS Missed Call Rescue — the shop-configured delayed SMS after a missed inbound call that drove recovery business. Suggestion cards remain for cases automation cannot send.

**Architecture impact:** Configuration on `telephony_call_flow` (not new authority). Send is system outbound SMS → `ConversationMessage` only. Cooldown + opt-out + enable gate. No other auto-sends added.

**Outstanding questions:** Enable for LugsNPlugs in Settings after deploy; observe whether delay default (120s) feels right on the floor.

### 2026-07-13 — Twilio Lookup SMS capability gate

**PR:** (this ship)

**Files changed:** `phone_sms_capabilities` migration; `TwilioPhoneLookupClient` (Lookup v2 `line_type_intelligence`); `ResolvePhoneSmsCapabilityAction`; classifier; eligibility + outbound/rescue/lead send gates; inbound SMS marks capable; delivery failure 21614/30006 marks not capable; customer contact line reason; Pest.

**Reason:** Avoid burning SMS attempts on landlines; persist why a number cannot receive texts.

**Architecture impact:** Phone-keyed capability is disposable projection of Twilio Lookup + inbound/delivery evidence. Conversation remains send authority. No Lookup on GET.

**Outstanding questions:** Whether VoIP/`tollFree` should ever be forced-off per shop; refresh window (90d) on the floor.

### 2026-07-13 — H0.2.1 Communication event precedence

**PR:** (this ship)

**Files changed:** `ConversationTurnPrecedence`, `SyncConversationTurnAction`, `ConversationPosture`, `ConversationRecorder` portal sync, `CallSession::saved` hook; H0 precedence Pest; Sarah/break-it green.

**Reason:** Turn must follow newest unresolved inbound customer communication — not last SMS write. Missed call after outbound was the proof.

**Architecture impact:** Turn is computed (projection from messages + CallSessions). Resolution = shop outbound SMS/Messenger, `worked_at`, or outbound call. Transport-agnostic.

**Outstanding questions:** Floor validation (H0.4) before H1.

### 2026-07-13 — H0 Conversations proof started (inventory + saga RED)

**PR:** (this ship)

**Files changed:** H0 failure inventory + floor protocol; `ConversationsH0` probe; Sarah saga + break-it Pest; milestone exit checklist.

**Reason:** Prove The Six Ones before any Conversations UI. Capture failures as gates, not polish.

**Architecture impact:** None yet — tests encode Thread Turn conflict when CallSession is unworked after outbound SMS posture.

**Outstanding questions:** First fix = recompute Turn on inbound/missed call (no Blade).

### 2026-07-13 — H Conversations doctrine freeze

**PR:** (this ship)

**Files changed:** `docs/communications/ark-conversations-v1.md`; `CURRENT_MILESTONE.md`; `ACTIVE_PR.md`.

**Reason:** Refocus H from channel features to Conversations as operational memory. Freeze doctrine so engineering proves The Six Ones instead of rewriting product language.

**Architecture impact:** Thread = projection from existing authorities (Conversation, CallSession, CommunicationEvent). Status ≠ Turn. Story chronological/immutable. No new SMS/inbox authority. Appointment SMS parked.

**Outstanding questions:** None on doctrine. Next = H0 inventory + brutal saga tests + floor validation.

### 2026-07-12 — Appointment confirmation SMS + opt-in reminders

**PR:** (this ship)

**Files changed:** appointment SMS columns migration; `AppointmentSmsCopy`; confirmation/reminder send actions; reminder settings + confirmation controllers; show panel; `appointments:send-reminders` every 5m; Pest `AppointmentSmsTest`.

**Reason:** Advisors need prompted confirmation and day-before / hours-before reminders without silent auto-send or a new SMS inbox.

**Architecture impact:** Conversation remains messaging authority. Reminder intent is appointment configuration; sent timestamps are event markers on the appointment. Scheduler only fires opted-in due windows (~90 min).

**Outstanding questions:** Floor — default reminder checkboxes vs always opt-in; cancel/reschedule SMS later if earned.

### 2026-07-12 — Customer estimate dark mode + shared theme cookie + trust footer

**PR:** (this ship)

**Files changed:** `theme-init` + `public-surface-theme.js` sync `ark_display_theme` cookie with localStorage; portal estimate dark CSS; trust footer always on customer shell (fixes staff preview compact footer).

**Reason:** Estimate dark mode unreadable; website/portal themes diverged by origin; staff preview showed legacy compact footer.

**Architecture impact:** Customer theme uses existing ecosystem cookie domain (`.demo-auto.test`). One customer application chrome.

**Outstanding questions:** None.

### 2026-07-12 — Schedule from RO + appointment time-step setting

**PR:** (this ship)

**Files changed:** `appointment_slot_minutes` migration/settings; `AppointmentSlotMinutes`; date+time selects (no minute scroll); RO identity **Schedule** link; create/show forms; tests; local Vite rebuild.

**Reason:** Advisors need RO→follow-up booking without bay conflict; native datetime-local forced 1-minute scrolling.

**Architecture impact:** Configuration (slot minutes) in ShopSettings. Appointments without tech/bay still do not conflict with bay work. Appointment remains authority.

**Outstanding questions:** Visual blocked slots on calendar — still deferred.

### 2026-07-12 — Schedule: remove drag/resize; edit-only moves

**PR:** (this ship)

**Files changed:** deleted `ark-scheduling-workspace.js`; calendar card → link with `?edit=1`; show opens Reschedule details; CSS grab/resize removed; runtime doc; AppointmentTest.

**Reason:** Accidental moves outweigh board drag convenience. Reschedule through appointment edit; conflicts still reject overlapping tech/bay.

**Architecture impact:** Appointment authority unchanged. PATCH reschedule endpoint remains for tests/API; UI no longer uses it.

**Outstanding questions:** Visual blocked slots on edit form — deferred; save-time conflicts remain.

### 2026-07-12 — Schedule TZ + H1 Call/Text on appointment show

**PR:** (this ship)

**Files changed:** `ShopDisplayTimezone`, appointment schedule guard/projections/presenter/index, Living Demo seeder, `app.css` time/capacity columns, communication/conversations/telephony migration FK order, appointment show Call/Text deep-links, `AppointmentTest`, milestone docs.

**Reason:** Observed Schedule times showed UTC wall-clock as shop local (2 AM vs 8 AM). Ship fix, freeze E polish, open H1 — attach Call/Text to appointment show via existing Hub compose=text (no new messaging authority).

**Architecture impact:** Display TZ is projection/presentation; UTC remains authority storage. H1 reuses Conversation Hub quick-reply only.

**Outstanding questions:** Observe whether advisors text from Schedule before Hub; only then consider H2 on compact schedule rows.

### 2026-07-12 — Deploy sellability harden + observed-hesitation freeze

**PR:** `06101498` + docs

**Files changed:** `operator-adoption-pass-v1.md`, `CURRENT_MILESTONE.md`, `ACTIVE_PR.md`, `IMPLEMENTATION_LOG.md`.

**Reason:** Ship sellability hardening to production; freeze E against hypothetical polish. Partial Parts on advisor Job Board stays watch-only.

**Architecture impact:** None. Process/runtime note + deploy.

**Outstanding questions:** Silent floor ask with Molly/Ben — close E if notebook empty/trivial, then open H.

### 2026-07-12 — Docs: synchronize engineering knowledge and product doctrine

**PR:** knowledge sync

**Files changed:** `CURRENT_MILESTONE.md`, `ACTIVE_PR.md`, `IMPLEMENTATION_LOG.md`, `docs/runtime/scheduling-runtime-authority.md`, `docs/runtime/README.md`, `docs/engineering/README.md`.

**Reason:** Fresh Cursor sessions must answer ARK P0, Scheduling authority, capacity/Bay Load as projection, Living Demo every-milestone rule, Floor Proof ladder, and WeiDA sibling P0 from the repository — not chat history.

**Architecture impact:** Documentation only.

**Outstanding questions:** None — continue Track E.

### 2026-07-12 — Sellable solidity audit: A–E harden

**PR:** `06101498`

**Files changed:** parts tests retarget Job Board + match form redirects / part event filter; appointment create errors; empty-board Schedule coaching gated; capacity empty hints; Living Demo RepairShop profile; CSV session-bound token; Tekmetric/Learn Post copy; settings 403→Job Board redirect assertions; DevRolePretend settings deny → `operations.index`.

**Reason:** Sellability hardening — CI lied on retired workboard URL and bare 403s; coaching/capacity/import/demo gaps undercut adoption.

**Architecture impact:** None. Solidity only.

**Outstanding questions:** Floor observation still the E gate. Job Board advisor cards still omit Partial Parts chip (projection exists; technician-lanes render it) — observe before surfacing.

### 2026-07-12 — Sellable Track E: Floor gate — engineering stands down

**PR:** (pending)

**Files changed:** `operator-adoption-pass-v1.md`, CURRENT_MILESTONE, ACTIVE_PR.

**Reason:** Exit criterion is floor observation (“Can you book today's work in ARK?”), not another slice. Expectation bucket notebook-only. After E clears: freeze workflow → H; reopen E only for specific H-surfaced problems.

**Architecture impact:** None. Process/runtime note only. Board stays frozen.

**Outstanding questions:** Floor observation with Molly/Ben/advisor — classify after watching.

### 2026-07-12 — Sellable Track E2: Remaining medium hesitation notes

**PR:** (pending)

**Files changed:** parts receive → status next-step copy; Comms rail Send Estimate discovery; Post to sales + closeout confidence copy; Lifecycle → Status history; hesitation category buckets in operator-adoption runtime note; tests.

**Reason:** Clear the last named E friction before H. Classify hesitations so patterns drive the fix layer.

**Architecture impact:** None. Operator copy / next-step cues only.

**Outstanding questions:** Real operator pass before declaring E exit criterion met.

### 2026-07-12 — Sellable Track E1: Operator adoption pass (hesitation bugs)

**PR:** (pending)

**Files changed:** appointment create search (no Customer ID), Schedule rail + hub CTAs, Checked in + intake bridge, Job Board rail rename, hot-path vocabulary, empty-state coaching, OperatorAdoptionPassTest, runtime note.

**Reason:** E redefined — another shop can run a day without tribal knowledge. Fix friction only; do not start H yet.

**Architecture impact:** None. Copy/CTA/discovery only. Appointment/Customer authority unchanged.

**Outstanding questions:** Re-run E1–E4 after floor use; remaining medium notes (parts two-step, send-estimate discovery) for next E slice.

### 2026-07-12 — Sellable Track D: Operational profiles

**PR:** (pending)

**Files changed:** `OperationalProfile`, `ApplyOperationalProfileDefaults`, migration, Settings → Operations picker, Pest tests.

**Reason:** Onboarding defaults for Repair / Solo / Mobile — not separate products. Strengthens Settings configuration path.

**Architecture impact:** Configuration only (`shop_settings.operational_profile` + existing columns). `SoloShopOperations` remains staff-derived.

**Outstanding questions:** Station seeding deferred until shops ask; do not invent bay inventory from a profile click.

### 2026-07-12 — Sellable Track C: Shop CSV import

**PR:** (pending)

**Files changed:** `ShopCsvImporter`, `ShopCsvImportController`, Settings → Operations partial, template download, Pest feature tests, `docs/imports/shop-csv-import-v1.md`.

**Reason:** Sales path — demo → import familiar customers/vehicles → signup. Strengthens onboarding without new authority.

**Architecture impact:** Writes `Customer` / `Vehicle` only. Preview then commit. Match phone → email. Legacy MySQL importer unchanged for full history.

**Outstanding questions:** Competitor-specific column packs only if shops ask repeatedly.

### 2026-07-12 — Sellable Track B: Operational Capacity rail

**PR:** (pending)

**Files changed:** `OperationalCapacityProjection`, capacity rail/bar Blade + CSS, wired into `SchedulingWorkspaceProjection`, Living Demo labor loads, capacity Pest test, baseline checklist.

**Reason:** Schedule authority → Operational Capacity Projection → Capacity Rail. No placeholder math — assigned labor vs shop-hours capacity; ≥90% advisor warnings.

**Architecture impact:** Disposable projection only. Same family for bays and technicians (room for travel/lanes later without rename).

**Outstanding questions:** Record baseline video per checklist when convenient. Dispatch suggestions still deferred.

### 2026-07-12 — Sellable Track A2: Scheduling Workspace calendar

**PR:** (pending)

**Files changed:** `SchedulingWorkspaceProjection`, `AppointmentRescheduleController`, appointments index Blade + calendar CSS/JS, card presenter fields (tech/bay/labor/arrival), AppointmentTest updates.

**Reason:** Advisors plan tomorrow on one screen — day/week lanes, slot→create, drag/resize with server conflict authority; card shape leaves room for sellable fields.

**Architecture impact:** Appointment remains authority; calendar is projection + write paths through schedule guard. Capacity rail intentionally empty until Track B projection (no placeholder math).

**Outstanding questions:** Month view deferred; Floor Proof after LugsNPlugs uses the workspace.

### 2026-07-12 — Sellable Track Day 1: Scheduling A1 + Living Demo

**PR:** (pending)

**Files changed:** scheduling migrations; `Appointment*` guard/conflict/hours; Workstation `accepts_scheduled_work`; `LivingDemoSeeder` + `ark:living-demo:reset`; `AppointmentScheduleGuardTest`; `docs/runtime/scheduling-runtime-authority.md`; S1 checklist; milestone/ACTIVE_PR.

**Reason:** Execute frozen Sellable board — Scheduling Workspace foundations (not Appointment module++) and Living Demo from Day 1 so demos do not rot.

**Architecture impact:** Appointment remains authority; hours/conflicts enforced on write; Capacity still future projection; SaaS S1 documented Manual First path.

**Outstanding questions:** None for A1. Next: A2 calendar UI without reopening the board.

### 2026-07-11 — Comms workspace thread shows MMS photos

**PR:** (pending)

**Files changed:** `event-bubble.blade.php`, `message-attachments.blade.php`, `conversation-message.blade.php`, `ConversationMessageEventMapper.php`, `CommunicationsMessageQueuePresenter.php`, `CommunicationsWorkspaceShellTest.php`.

**Reason:** Production MMS photos (719-641-6535 Jun 30) were stored and serving, but Attention workspace bubbles only printed `(attachment)` — no `<img>`. Customer Hub already rendered thumbs.

**Architecture impact:** Timeline bubbles reuse shared attachment partial; mapper labels MMS and omits placeholder body. Queue snippet shows Photo / N attachments instead of `(attachment)`.

**Outstanding questions:** None — deploy with shop-turn age-window fix so the thread is reachable from Needs Attention.

### 2026-07-11 — Shop-turn Needs Attention no longer ages out

**PR:** (pending)

**Files changed:** `ShopTurnAttentionQueue.php`, `ShopTurnAttentionPresenter.php`, `queue-comms-attention.blade.php`, `CommunicationsQueueTest.php`.

**Reason:** Production MMS from 719-641-6535 (Jun 30 10:42 MDT, conv 169) was open + `waiting_on=shop` but invisible on Needs Attention because shop-turn shared the 8-hour live window. Unreplied customer messages must stay until the shop replies.

**Architecture impact:** Calls + unread interrupt keep `CommunicationsQueueWindow` (8h). Shop-turn recovery matches workboard Needs Shop (no age window). MMS kind/label preserved on shop-turn rows.

**Outstanding questions:** None — deploy to restore aged unreplied SMS/MMS on Attention / Companion.

### 2026-07-11 — Open House kiosk layout + fast success reset

**PR:** (deploy via production)

**Files changed:** `kiosk.blade.php`, `ark-event-kiosk.js`, `EventKioskProjection.php`, `EventsKioskTest.php`.

**Reason:** Tighten floor kiosk — logos (LugsNPlugs top-left, ACES top-right, ARK SMS bottom-left, QR bottom-right), Lunch & Learn branding merged above form, brief success overlay then auto-reset form for next guest.

**Architecture impact:** Projection adds `platform_logo_url`; success is overlay-on-idle (no photo/tips detour). Disposable UI projection only.

**Outstanding questions:** None — floor-test signup → thank-you → empty form cadence.

### 2026-07-11 — Lunch & Learn kiosk copy polish

**PR:** (deploy via production)

**Files changed:** `OpenHouseEventSeeder.php`, `EventKioskProjection.php`, `kiosk.blade.php`, `kiosk-check-in.blade.php`, `EventsKioskTest.php`.

**Reason:** Event-day copy polish — newsletters (plural), ticket sponsorship (one from LugsNPlugs + one from ACES), LugsNPlugs & ACES Lunch & Learn naming, B-Rad / Small Business Showcase, I Heart Mac & Cheese.

**Architecture impact:** Seeder + projection copy only. Production event row updated via tinker to match.

**Outstanding questions:** None.

### 2026-07-11 — RepairPal authority pages (hub + certified / reviews / warranty)

**PR:** (pending)

**Files changed:** `routes/public.php`, `PublicRepairPal*Controller.php`, `resources/views/public/repairpal/*`, repairpal partials, `PublicTrustSignalsProjection.php`, `CustomerSurfaceFooterData.php`, `CustomerSurfaceBreadcrumbProjection.php`, `SeoEngine.php`, `config/public_seo.php`, `public/warranty.blade.php`, PublicSeo/TrustSignals tests

**Reason:** RepairPal chips previously sent visitors off-site. Supporting authority pages keep the trust journey on demo-auto.test, with the official profile as verification CTA.

**Architecture impact:** Projection/trust chips and footer link to `/repairpal-certified`. Existing `/warranty` and Google reviews remain. Hub at `/repairpal` parents three child pages. No invented RepairPal review excerpts (earned authority).

**Outstanding questions:** None — observe whether advisors or Search Console want hub in header nav later.

### 2026-07-11 — Open House mobile check-in QR

**PR:** (deploy via production)

**Files changed:** `EventKioskCheckInController.php`, `kiosk-check-in.blade.php`, `EventKioskUrls.php`, `EventAttendeeStoreController.php`, `EventKioskController.php`, `routes/web.php`, `EventsKioskTest.php`, `ark-event-kiosk.js`, `kiosk.blade.php`

**Reason:** QR "Continue on your phone" opened the full 1920×1080 kiosk; phones need a form-only page.

**Architecture impact:** Token-gated GET `/kiosk/{slug}/check-in`; QR encodes check-in URL; mobile posts with `check_in_surface=mobile` and redirects to thank-you on that page.

**Outstanding questions:** None — floor-test QR handoff on Open House day.


**Files changed:** `common_problems.php`, `CommonProblemRegistry.php`, symptom chooser + index blades, `app.css`, `lead-form-fields.blade.php`, `second-opinion-work.blade.php`, `PublicSurfaceSettings.php`, `PublicFeaturedReviewProjection.php`, tests

**Reason:** Competitive gap P0 — raise Common Problems CTR, reinforce diagnostics on the form, strengthen second-opinion emotion, and feature reviews that prove the brand promise.

**Architecture impact:** `card_teaser` is optional authority copy on problem config (not a new page type). Homepage featured-review keywords prefer diagnosis-proof quotes. No layout reshuffle.

**Outstanding questions:** Competitive-gap canvas remains review-only before structural/P1 work.

### 2026-07-10 — Signed-in lead form vehicle picker

**PR:** (pending)

**Files changed:** `PublicLeadFormContactPrefill.php`, `PublicLeadStoreController.php`, `LeadRecorder.php`, `lead-form-fields.blade.php`, `SignedInLeadFormPrefillTest.php`

**Reason:** After contact prefill, signed-in customers still typed year/make/model by hand even when vehicles were already on file.

**Architecture impact:** Prefill projection lists owned vehicles. Form radios select an owned vehicle, “different vehicle,” or skip. Store resolves `vehicle_id` only for the signed-in owner and copies YMM/VIN onto the lead.

**Outstanding questions:** None.

### 2026-07-10 — Prefill lead form from signed-in customer

**PR:** (pending)

**Files changed:** `PublicLeadFormContactPrefill.php`, `PublicLeadStoreController.php`, `lead-form-fields.blade.php`, `public-lead-phone.js`, `SignedInLeadFormPrefillTest.php`

**Reason:** Signed-in customers were retyping name/phone/email on the public lead form. One customer application should unlock that identity.

**Architecture impact:** Projection packages contact prefill from portal auth. Concern stays empty unless problem-page / old input. Store trusts account OTP when submitted phone matches; links `customer_id`. Alpine treats account phone as already verified and re-requires a code if the number changes.

**Outstanding questions:** None — vehicle picker from My Vehicles still deferred.

### 2026-07-10 — Shop services list + Auto repair search

**PR:** (pending)

**Files changed:** `PublicSurfaceSettings.php`, `ShopPublicSurfaceSettingsController.php`, `WebsiteManageController.php`, `manage-form.blade.php`, `common-problems-local-services.blade.php`, `PublicHomeController.php`, `PublicCommonProblemsIndexController.php`, `home.blade.php`, `common-problems/index.blade.php`, Website/Lead/CommonProblems tests

**Reason:** Auto repair panel needed live search and an owner-editable “services we provide” list seeded from local SEO pages, without inventing a CMS or Service authority.

**Architecture impact:** `shop_services` lives in `public_surface_settings` (Website Manage). Public grids render enabled rows; search catalog unions shop services with transactional common-problem pages. Page copy stays in `CommonProblemRegistry`.

**Outstanding questions:** None — deploy when ready; owners can add free-form labels (e.g. Oil Changes) before SEO pages exist.

### 2026-07-09 — Google Ads conversion tag on public shell

**PR:** cfb4dadd

**Files changed:** `GoogleAdsTag.php`, `google-ads-tag.blade.php`, `lead-intake.blade.php`, `config/growth.php`, `.env.example`, `PublicSeoTest.php`

**Reason:** Google Ads Submit lead form goal could not fire — no gtag on demo-auto.test. Conversion event `ads_conversion_submit_lead_form` created in Ads (URL contains `/leads/thanks`).

**Architecture impact:** Public marketing shell loads gtag when `GOOGLE_ADS_TAG_ID` is set; optional linked GA4 via `GOOGLE_ADS_GA4_MEASUREMENT_ID`. Tag ID `AW-17485686048`.

**Outstanding questions:** Set production Coolify env vars and verify with Tag Assistant after first real lead thanks pageview.

### 2026-07-08 — Common-problem featured media performance

**PR:** (pending)

**Files changed:** `CommonProblemFeaturedMediaOptimizer.php`, `CommonProblemFeaturedMedia.php`, `OptimizeCommonProblemFeaturedMediaCommand.php`, featured media Blade partials, `SeoEngine.php`, `Dockerfile` (GD/WebP), `ark-post-deploy.sh`, tests

**Reason:** Problem page hero images were full-size uploads (up to 5MB) with lazy-load on the LCP element.

**Architecture impact:** Uploads generate `-display.webp` (960px) and `-large.webp` (1440px) variants; hero loads display eagerly with fetchpriority high; lightbox uses large. Post-deploy command backfills legacy uploads.

**Outstanding questions:** None — observe Lighthouse/LCP on a page with featured media after deploy.

### 2026-07-08 — Google-matched public street address (NAP)

**PR:** f072fb50

**Files changed:** `ShopSettings.php` (`googleMatchedStreetAddress()`), `ShopSeoContext.php`, `CustomerSurfaceFooterData.php`, `site-header.blade.php`, `EntityHealthEngine.php`, `ShopSettingsGoogleMatchedStreetAddressTest.php`, `GrowthEntityHealthTest.php`, `PublicSeoTest.php`

**Reason:** Google Business Profile shows `100 Main Street Suite A`; JSON-LD and public surfaces only projected line 1, causing suite drift in Entity Health and citation mismatch risk.

**Architecture impact:** Single projection method on shop settings authority; JSON-LD, header, footer, and entity-health comparisons consume it. Shop settings still store line 1 + line 2 separately.

**Outstanding questions:** Re-upload directory logos separately; GSC staging property removal is operator-side.

### 2026-07-08 — Portal estimate view popup flash loop

**PR:** (pending)

**Files changed:** `PortalCustomerActivityInterruptDismissal.php`, `PortalCustomerActivityInterruptDismissController.php`, `PortalCustomerActivityBroadcaster.php`, `CommsInterruptResolver.php`, `MarkConversationReadController.php`, `EnsureAdvisorCommsCleared.php`, `ark-comms-interrupt.js`, `comms-interrupt-panel.blade.php`, `app.blade.php`, `routes/web.php`, `PortalCustomerActivityInterruptTest.php`

**Reason:** Opening an estimate portal link flashed the advisor interrupt repeatedly. Call-queue refreshes omit cache-backed portal rows and cleared `activeMessage`; interrupt poll then re-showed the same unread cache entry.

**Architecture impact:** Portal interrupts follow website-lead pattern — per-advisor dismissal, cache clear + broadcast clear on dismiss, clear on conversation mark-read. Client preserves portal/`website_lead` active messages when queue payloads omit them.

**Outstanding questions:** None — verify on floor when customer opens estimate link.

### 2026-07-07 — Website lead advisor interrupt

**PR:** (pending)

**Files changed:** `WebsiteLeadInterruptPresenter.php`, `WebsiteLeadInterruptBroadcaster.php`, `WebsiteLeadInterruptDismissal.php`, `WebsiteLeadInterruptDismissController.php`, `PublicLeadStoreController.php`, `CommsInterruptResolver.php`, `CommsInterruptBroadcast.php`, `RecordLeadFirstContactAction.php`, `AdvisorLeadStateController.php`, `ark-comms-interrupt.js`, `comms-interrupt-panel.blade.php`, `app.blade.php`, `routes/web.php`, `WebsiteLeadInterruptTest.php`

**Reason:** Public website leads were landing on `/app/leads` with no realtime alert — advisors only discovered them by manually checking the leads table.

**Architecture impact:** Reuses existing Comms interrupt channel (`operations.comms-interrupts`): popup, chime, browser notification, and 5–15s poll backup. Broadcast fires on non-spam public lead submit; clears on first contact, state change, or per-advisor dismiss. No new notification authority.

**Outstanding questions:** None — observe floor adoption; tune escalation delay if advisors report noise.

**Follow-up (same session):** Website leads wired into `CommsEscalationRunner` — SMS to active advisors with phones after existing escalation delay (default 3 min) if lead still uncontacted.

### 2026-07-07 — Inbound ring: PSTN cell only, Companion excluded

**PR:** (pending)

**Files changed:** `TelephonyRingGroup.php`, `NotifyMobileInboundCallAction.php`, `TelephonyCallFlowTest.php`

**Reason:** Cell phone was not ringing with desk phones; Companion voice is not floor-ready. Inbound ring must stay desk SIP + PSTN cell only — no Twilio Client / mobile app legs or inbound push wake.

**Architecture impact:** `MobileApp` endpoints are excluded from inbound TwiML dial. `NotifyMobileInboundCallAction` is a no-op until Companion voice earns certification. Cell endpoints with `ring_schedule: always` continue to ring during open hours without desktop presence. Desk SIP path unchanged.

**Outstanding questions:** Re-enable Companion inbound ring after observation sprint certifies voice on the floor.

### 2026-07-07 — Market Operations Foundation

**Milestone:** Entity Health v1 (`ec13ca3a`) + Featured Media v2 (this commit)

**Entity Health v1:** Observe public identity without SEO theater — Notebook, Canonical Identity, Audiences, Identity Observations. Manual audience verification; explainable drift; no scores, sync, AI, or Google APIs.

**Featured Media v2:** Evolve single featured image into `list<FeaturedMedia>` gallery per common-problem page. Admin reorder/upload/delete; random hero on reload; lightbox only when 2+ images; `primaryForDisplay()` for OG/Twitter, `featuredForDisplay()` for visitors. Legacy single-object storage normalizes on read.

**Reason:** Same doctrine, two audiences — operational evidence becomes public evidence (customer-facing); canonical identity should match market-facing identity (operator-facing). Phase 4: ensure the market sees the same truth the shop is.

**Architecture impact:** `common_problem_featured_media[slug]` is now a collection. Entity Health frozen terminology: Notebook · Canonical Identity · Audiences · Identity Observations.

**Outstanding questions:** Notebook timeline (human + machine entries); image type metadata for Shop Learns; Identity Campaign concept — all observation-gated.

### 2026-07-07 — Featured Media v2 gallery (earned media)

**PR:** (pending)

**Files changed:** `CommonProblemFeaturedMedia.php`, `WebsiteCommonProblemMediaController.php`, `entity-health`-adjacent page media views, `common-problem-featured-media.blade.php`, `ark-featured-media-gallery.js`, `CommonProblemAuthorityProjection.php`, `SeoEngine.php`, `CommonProblemFeaturedMediaTest.php`

**Reason:** Evolve single featured image into per-page gallery collection — admin reorder/upload/delete, random hero on reload, lightbox with keyboard/swipe, primary image for OG/Twitter only.

**Architecture impact:** Storage is `list<{id, path, alt, caption}>` per slug with legacy single-object normalization on read. First item is primary; public hero uses `featuredForDisplay()` (random); SEO uses `primaryForDisplay()`.

**Outstanding questions:** Inspection photo promotion workflow; before/after and video deferred until floor earns them.

### 2026-07-07 — Entity Health v1 (observe public identity)

**PR:** (pending)

**Files changed:** `EntityHealthEngine.php`, `EntityHealthFinding.php`, `CanonicalEntityProjection.php`, `AudienceSurfaceVerifications.php`, `GrowthEntityHealthController.php`, `MarkAudienceSurfaceVerifiedController.php`, `config/entity_health.php`, `entity-health.blade.php`, `subnav.blade.php`, `routes/growth.php`, `GrowthEntityHealthTest.php`

**Reason:** Market operations observation surface — one canonical identity authority, manual audience verification dates, explainable identity observations (no scores, sync, or Google APIs). Notebook block uses clean 0/1/N language; sections: Canonical Identity, Audiences, Identity Observations.

**Architecture impact:** Read-only drift analyzers compare ShopSettings to ARK projections (JSON-LD, footer, legacy redirects, branding scan). Audience `last_verified_at` stored in `public_surface_settings.audience_surface_verifications`. Separate from SEO Audit.

**Outstanding questions:** Project telephony hours into JSON-LD if observation earns it; Market Coverage matrix deferred.

### 2026-07-07 — Public trust footer mature (frozen)

**PR:** d685db79 (production)

**Files changed:** `CustomerSurfaceFooterData.php`, `site-footer.blade.php`, `shell.blade.php`, `app.css`, `PublicSeoTest.php`

**Reason:** Footer v3 — three-column trust surface (contact, helpful links, why choose us), closing verify-first band, Google rating, sticky-footer layout fix (removed shell padding below footer), ~10% tighter rhythm, conversational closing copy. Surface frozen until observation earns revision.

**Architecture impact:** Public pages use `CustomerSurfaceFooterData` variant `trust`; portal keeps compact footer. Layout: `body` flex column + `main.flex-1` + `footer.mt-auto`. No new authority — projection only.

**Outstanding questions:** None for footer. Next engineering focus: market authority / qualified traffic, not presentation polish.

### 2026-07-07 — Common problem featured media (optional per-page photos)

**PR:** bb5412a7 (production)

**Files changed:** `CommonProblemFeaturedMedia.php`, `ShopPublicSurfaceRaw.php`, `WebsiteCommonProblemMediaController.php`, page-media admin views, `common-problem-featured-media.blade.php`, `SeoEngine.php`, `CommonProblemRegistry.php`, `CommonProblemAuthorityProjection.php`, tests

**Reason:** Support real repair photos on common-problem / local-intent pages without placeholders or layout shift when media is absent; OG/Twitter image follows featured media when live.

**Architecture impact:** Shop overrides in `public_surface_settings.common_problem_featured_media[slug]`; Website → Page media is public page media authority; `featured_media` naming leaves room for video/gallery later.

**Outstanding questions:** Growth Content Builder inline editor; ARK inspection-photo → knowledge base promotion workflow; media library metadata (photographer, date, vehicle).

### 2026-07-07 — Market Authority doctrine frozen (no further code until realigned)

**PR:** (pending — doctrine only)

**Files changed:** `docs/ecosystem/ark-market-authority-v1.md`, `.cursor/rules/ark-market-authority.mdc`, cross-refs in `ark-earned-authority-v1.md`, `ark-truth-stack-v1.md`

**Reason:** Freeze causal model before extending v1 Growth Pressure — opportunity created on successful work (not review ask), market witnesses, observations, projections; Law + Corollary + Becoming meta-principle; "Market Authority" naming to avoid DDD collision.

**Architecture impact:** v1 review-request / ledger implementation is disposable projection misaligned with frozen doctrine. Next code: `MarketAuthorityOpportunityCreated` on paid close, invitation as investment, advisor human language.

**Outstanding questions:** Event schema timing; Selection Pressure vocabulary observation on floor.

### 2026-07-07 — Owner Today Market Pressure + effort/earned authority ledger

**PR:** (pending)

**Files changed:** `TodayMarketPressureComposer.php`, `MarketPressureAdvisorBreakdown.php`, `GrowthPressureProjection::forOwnerToday()`, `AuthorityLedgerProjection` (effort vs earned split), `TodaySection` panel support, `operations/today/partials/market-pressure-panel.blade.php`, `config/growth_authority.php` (`why_care` copy), `market-pressure.blade.php`, tests

**Reason:** Wire market authority into Owner Today morning rhythm — paid closes vs review requests, advisor breakdown, owner-language "why should I care?" on every row. Split ledger: review requests are effort (inputs), returning customers / website-attributed ROs are earned (outputs).

**Architecture impact:** Owner Today now answers operational pressure + market pressure in one surface. Selection Pressure and competitor automation explicitly deferred.

**Outstanding questions:** Selection Pressure from lost reasons; Google review velocity auto-sync; 14-day pending-review follow-up rows.

### 2026-07-07 — Authority capability: Market Pressure + review capture at close

**PR:** (pending)

**Files changed:** `app/Ark/Growth/Authority/GrowthPressureProjection.php`, `AuthorityLedgerProjection.php`, `config/growth_authority.php`, `RepairOrderLifecycleController`, `RepairOrderLifecycleTransition`, `repair-order-lifecycle-select.blade.php`, `ark-worksheet-continuity.js`, `WebsitePerformanceProjection`, `website/partials/market-pressure.blade.php`, migration `2026_07_07_100000_add_review_request_to_repair_orders.php`, tests

**Reason:** Shift from SEO tactics to operational market authority — measure missed review opportunities, selection/retention signals, and GBP/GSC trends with Attention-style explainable rows. One checkbox at paid close: review requested yes/no.

**Architecture impact:** First projection under **Authority** (not marketing). Growth Pressure composes Operations truth (closed ROs), GBP sync, and GSC snapshots. Authority Ledger gives operational language for trust accumulation. Review API deferred.

**Outstanding questions:** Snapshot monthly review count for velocity delta; Owner Today row; pending-review follow-up after 14 days when Google review sync exists.

### 2026-07-06 — Earned Authority doctrine + public marketing v1 closed

**PR:** (pending)

**Files changed:** `docs/ecosystem/ark-earned-authority-v1.md`, `.cursor/rules/ark-earned-authority.mdc`, `ark-truth-stack-v1.md`, `ark-constitution-v1.md`, `ark-website-doctrine-v1.md`, `docs/growth/DOCTRINE.md`, companion cursor rules

**Reason:** Codify the exit gate between shop truth and every outbound ARK surface. Mark public marketing v1 closed — homepage frozen; site evolves when the shop earns new knowledge.

**Architecture impact:** Foundational stack completes: Pressure → Observation → Vocabulary → Authority → Projection → **Earned Authority**. Governs website, Companion, AI, ARKademy, reports, social, email.

**Outstanding questions:** Wire `CommonProblemShopExperienceResolver` when slug ↔ closed RO matching earns observation.

### 2026-07-06 — Dial-complete trusts ring state, not cell-screening answered_at

**PR:** (pending)

**Files changed:** `TelephonyDialCompleteWebhookController.php`, `TelephonyCallFlowTest.php`

**Reason:** First voicemail fix still hung up callers because Twilio parent status sets `answered_at` when a cell whisper leg connects; `wasCallAnswered()` treated that as a real answer.

**Architecture impact:** While ring state exists, dial-complete Hangup requires `TelephonyRingState.answered`; session `answered_at` alone is ignored during cell screening.

**Outstanding questions:** VVX SIP registration still separate floor issue; test from non-shop cell after deploy.

### 2026-07-06 — Inbound voicemail after cell whisper false completed

**PR:** (pending)

**Files changed:** `TelephonyDialCompleteWebhookController.php`, `TelephonyCallFlowTest.php`

**Reason:** Production callers heard 3–4 rings then disconnect with no voicemail after Edward's cell was added to the inbound ring group. Cell whisper legs report `DialCallStatus=completed` without bridging the caller; Sunday's unconditional Hangup on `completed` ended the parent call.

**Architecture impact:** Dial-complete now Hangups only when ring state or `CallSession` shows the call was actually answered; otherwise voicemail TwiML continues.

**Outstanding questions:** VVX SIP registration dropped separately (~8 PM) — floor reboot required; observe external no-answer path after deploy.

### 2026-07-06 — Public surface Tier 1: CTA, hero trust chips, footer

**PR:** (pending)

**Files changed:** `PublicLeadFormCopy.php`, `PublicTrustSignalsProjection.php`, `CustomerSurfaceFooterData.php`, `site-footer.blade.php`, `home-hero-positioning.blade.php`, `hero-trust-chips.blade.php`, warranty/privacy/terms pages + controllers, `CommonProblemFormCopy.php`, lead form partials, `public.php`, `public_seo.php`, `public_legacy_redirects.php`, `app.css`, `PublicSeoTest.php`, `CommonProblemFormCopyTest.php`

**Reason:** Strategic shift from homepage polish to problem-authority growth — finish the house first: one CTA label, trust chips under hero, real footer with shop contact and legal links.

**Architecture impact:** Footer data projected once via `CustomerSurfaceFooterData`; hero chips from `PublicTrustSignalsProjection::hero_chips`. Form submit label centralized in `PublicLeadFormCopy::SUBMIT_LABEL`. No form length change (qualification data still needed before progressive form).

**Outstanding questions:** Tier 2 scaffold shipped (`CommonProblemAuthorityProjection`, shop experience resolver stub). Wire closed RO matching when observation earns it.

### 2026-07-06 — Problem authority template (Common Problems Tier 2 scaffold)

**PR:** (pending)

**Files changed:** `CommonProblemAuthorityProjection.php`, `CommonProblemShopExperienceResolver.php`, `common-problem-authority.blade.php`, `common-problem-shop-experience.blade.php`, `show.blade.php`, `CommonProblemRegistry.php`, `config/common_problems.php` (Subaru exemplar), `PublicCommonProblemShowController.php`, `app.css`, `CommonProblemAuthorityProjectionTest.php`

**Reason:** Shift from generic symptom pages to problem authorities — explicit diagnostic process, typical repairs, and a shop-experience block ready for ARK-verified repair outcomes.

**Architecture impact:** Projection packages page sections once per render; `CommonProblemShopExperienceResolver` returns null until closed RO linking is observed. Config may override `shop_experience` for manual seeds; resolver wins when implemented.

**Outstanding questions:** Define slug ↔ concern matching rules from floor observation before populating verified repair counts from authority.

### 2026-07-06 — Public surface Tier 1: CTA, hero trust chips, footer

**PR:** (pending)

**Files changed:** `GrowthMaintenancePipeline.php`, `AutoCommonProblemPublisher.php`, `SearchEngineNotificationService.php`, `GoogleSearchConsoleAdapter.php`, `GoogleIndexingNotifier.php`, `IndexNowNotifier.php`, `growth_generated_common_problems` migration, `CommonProblemRegistry.php`, `config/growth.php`, Growth integrations UI, `GrowthSeoAutomationTest.php`

**Reason:** User asked for daily Search Console monitoring, automatic common-problem page creation from striking-distance queries, and automated sitemap/URL notification to major search engines — not manual GSC clicks each ship.

**Architecture impact:** Nightly pipeline now: ingest GSC → recalculate opportunity queue → auto-publish up to 2 qualified Create opportunities/night (min impressions, position band, acceptance gates) into `growth_generated_common_problems` merged into `CommonProblemRegistry`; then notify IndexNow (Bing/Yandex partners), Google Search Console sitemap submit, and optional Google Indexing API URL_UPDATED. Config + Growth → Integrations toggles. IndexNow key served at `/indexnow-{token}.txt`.

**Outstanding questions:** Wire production service account as Search Console Owner; enable integrations; run migration; first nightly run observation.

### 2026-07-06 — SEO striking-distance common problem pages (P0171, Subaru, Honda belt, wheel bearing CTR)

**PR:** (pending)

**Files changed:** `config/common_problems.php`, `CommonProblemRegistry.php`, `SeoEngine.php`, tests

**Reason:** Production Search Console showed 12.9k impressions at position 8.4 for wheel bearing noise (low CTR), plus missing pages for p0171, subaru overheating, and honda timing belt queries in the 8–14 band.

**Architecture impact:** Common Problems authority only — new indexable pages, improved meta titles/descriptions, internal cross-links. No new CMS.

**Outstanding questions:** Run `growth:sync-public-content` on production after deploy; request re-indexing in GSC for new URLs.

### 2026-07-06 — Journey Explorer first-touch query uses concern summary column

**PR:** (pending)

**Files changed:** `JourneyExplorerProjection.php`, `OperationalJourneyTest.php`

**Reason:** First touch by concern 500'd on production — query referenced nonexistent `repair_order_concerns.title`; column is `summary`. Empty keyword now defaults to `brake`.

**Architecture impact:** Query fix only.

**Outstanding questions:** None.

### 2026-07-06 — Journey Explorer shows shop RO number on high-revenue rows

**PR:** (pending)

**Files changed:** `JourneyExplorerProjection.php`, `OperationalJourneyTest.php`

**Reason:** High-revenue repairs displayed `growth_attributions.repair_order_id` (internal FK) instead of the shop-facing `repair_orders.repair_order_id`.

**Architecture impact:** Display-only projection fix; attribution storage unchanged.

**Outstanding questions:** None.

### 2026-07-06 — Growth Google credentials reuse server service account

**PR:** (pending)

**Files changed:** `GrowthIntegrationSettings`, `GrowthIntegrationsProjection`, integrations Blade, `GrowthIntegrationsSettingsTest.php`

**Reason:** LugsNPlugs already ships `firebase-mobile-service-account.json` on the server; Growth should not require pasting duplicate JSON for GBP.

**Architecture impact:** Credential resolution order: explicit `growth_google_service_account` → mobile push shop setting → server file. UI shows when using existing server account.

**Outstanding questions:** Service account must still be a Manager on the GBP listing and have Business Profile APIs enabled in GCP.

### 2026-07-06 — Public sitemap setting moved to shop settings

**PR:** (pending)

**Files changed:** `GrowthIntegrationSettings`, `UpdateGrowthIntegrationsAction`, `WebsiteHealthProjection`, `GrowthIntegrationsProjection`, growth sitemap controllers, integrations Blade, `config/growth.php`, `.env.example`, `GrowthIntegrationsSettingsTest.php`

**Reason:** Shop operators should not need `.env` changes for sitemap readiness; same pattern as Google Business Profile.

**Architecture impact:** `growth_integrations.public_sitemap.enabled` in shop settings. Website Health reads shop config. Sectional `/growth/sitemap.xml` routes gate on shop setting at request time; `/sitemap.xml` unchanged.

**Outstanding questions:** None.

### 2026-07-06 — Growth Integrations UI for Google Business Profile

**PR:** (pending)

**Files changed:** `GrowthIntegrationsController`, `UpdateGrowthIntegrationsController`, `GrowthIntegrationsDiscoverLocationsController`, `UpdateGrowthIntegrationsAction`, `GrowthIntegrationsProjection`, `GoogleBusinessProfileLocationLister`, `RunGrowthBusinessProfileBackfillJob`, `UpdateGrowthIntegrationsRequest`, `growth/integrations/index.blade.php`, `growth/partials/subnav.blade.php`, `growth/opportunities/index.blade.php`, `routes/growth.php`, `GrowthIntegrationsSettingsTest.php`

**Reason:** Operators need a Settings surface to paste Google service account JSON, discover GBP location IDs, enable sync, and queue backfill — without tinker or `.env`.

**Architecture impact:** Shop configuration only (`shop_settings.growth_integrations`, encrypted `growth_google_service_account`). Discover uses Account Management + Business Information APIs; sync path unchanged.

**Outstanding questions:** None.

### 2026-07-06 — Portal Call / Text contact pattern and estimate mobile fixes

**PR:** (pending)

**Files changed:** `portal/partials/_shop-call-text-actions.blade.php`, `_shop-contact-card.blade.php`, `_authorization-next-steps.blade.php`, `_customer-aside.blade.php`, `_shop-header.blade.php`, `estimate.blade.php`, `inspection.blade.php`, `invoice-pay.blade.php`, `PortalInvoicePayShowController.php`, `ark-portal-signature.js`, `app.css`; active-staff filter in `CommunicationsWorkspaceContextBuilder.php`, `AppointmentStaffOptions.php`

**Reason:** Mobile customers needed explicit Call and Text actions (not tel-only links) on estimate, What happens next, inspection, invoice pay, and customer aside. Estimate summary width and signature-on-scroll regressions fixed. Inactive staff (e.g. test advisors) filtered from assign dropdowns.

**Architecture impact:** Shared portal contact partials only; shop phone from snapshot / ShopSettings. No authority changes. Signature canvas preserves stroke across viewport resize.

**Outstanding questions:** None.

### 2026-07-06 — Communications workspace context panel scroll and dedupe

**PR:** (pending)

**Files changed:** `CommunicationsWorkspaceContextBuilder.php`, `context-panel.blade.php`, `app.css`

**Reason:** Context column clipped Actions with no scroll; field list repeated RO/customer/channel facts already shown in headline and RO card.

**Architecture impact:** Projection-only trim — RO summary carries estimate-view signal; no authority changes. CSS scroll on `.ops-comms-workspace__context-body` matches thread column pattern.

**Outstanding questions:** None.

### 2026-07-06 — VVX desk phone provisioning: Twilio SIP, clock, optional artifacts

**PR:** (pending)

**Files changed:** `PolyPhoneProvServerProvisioning.php`, `PolyPhoneClockProvisioning.php`, `PolyPhoneProvDeviceConfigBuilder.php`, `ServeEndpointProvisionArtifactAction.php`, `config/telephony.php`, `.env.example`, migrations `2026_07_06_020000_*`, `2026_07_06_030000_reconcile_lnp_front_desk_phone_inventory.php`, provision tests

**Reason:** Front Desk Left/Right VVX350 phones failed provision (404 on license/calls), wrong clock (London city ID), and SIP overwrite to legacy `voice.demo-auto.test` after successful provision pull. Floor-certified: both phones register on Twilio, correct MDT time, provisioning successful after reboot.

**Architecture impact:** Provision pins `device.prov.serverName` to shop `/provision/`; clock mirrors working VVX profile (device city ID + tcpIpApp offset, DST off); optional Poly artifacts return empty shell instead of 404. SIP registrar remains deployment env (`VOICE_SIP_REGISTRAR`).

**Outstanding questions:** None — deploy locks ephemeral production patches.

### 2026-07-06 — Companion Milestone 7: Production Feel

**PR:** (pending)

**Files changed (backend):** `app/Ark/Mobile/Push/MobileAwarePushCopy.php`, `NotifyMobileLifecyclePushAction.php`, `DispatchMobilePushForOperationalEvent.php`, `MobilePushStaffAudience.php`; hooks in `ProcessCallStatusAction`, `ProcessCallRecordingAction`, `RecordPortalEstimateViewAction`, `AppServiceProvider`; `tests/Feature/Mobile/MobileAwarePushCopyLifecycleTest.php`

**Files changed (Flutter):** `companion_motion.dart`, message outbox store/processor/provider, `companion_composer_draft_store.dart`, `companion_pending_message_bubble.dart`; updates to shell (thread transition), inbox swipe, thread screen/timeline/composer, media player, app lifecycle drain, `companion_app.dart` text scaling

**Reason:** M7 production feel — no new product features. Lifecycle-aware pushes (estimate approved/viewed, missed call, voicemail, parts arrived, waiting parts, vehicle ready). Offline outbox for SMS/MMS/internal notes. Motion polish, accessibility, composer draft persistence.

**Architecture impact:** Push copy remains transport layer; lifecycle hooks read existing authority events. Outbox is client-local queue only — server authority unchanged.

**Outstanding questions:** Two-week pocket observation sprint before Companion v1 sign-off.

### 2026-07-05 — Companion Milestone 2: Conversation Thread (Flutter + API)

**PR:** (pending)

**Files changed:** `app/Ark/Mobile/Http/MobileConversationMessageStoreController.php`; `ark-mobile/lib/companion/screens/companion_conversation_thread_screen.dart`, `widgets/companion_thread_identity_band.dart`, `widgets/companion_thread_timeline.dart`, `widgets/companion_thread_context_cards.dart`, `widgets/companion_ai_summary_card.dart`, `widgets/companion_thread_composer.dart`, `lib/models/conversation.dart`, `lib/api/mobile_api.dart`, `lib/repositories/conversations_repository.dart`

**Reason:** Build Milestone 2 thread — Quo-style unified timeline (SMS/MMS/calls/workflow events), identity band with vehicle/RO/lifecycle, inline context cards, collapsed AI summary, fast composer with attach/camera/call/templates, poll + pull refresh, MMS outbound via mobile API.

**Architecture impact:** Mobile message store accepts optional attachment (mirrors desktop SendConversationMessage). Thread UI consumes existing ConversationProjection timeline; no new authority.

**Outstanding questions:** Edward floor review — does thread feel production quality before Milestone 3?

### 2026-07-05 — Companion Milestone 1: Inbox (Flutter + API)

**PR:** (pending)

**Files changed:** `app/Ark/Mobile/MobileConversationsProjection.php`; `ark-mobile/lib/companion/screens/companion_conversations_screen.dart`, `widgets/companion_inbox_row.dart`, `widgets/companion_inbox_skeleton.dart`, `providers/companion_inbox_providers.dart`, `services/companion_pinned_conversations.dart`, `shell/companion_shell.dart`, `lib/models/conversation.dart`; `tests/Feature/Mobile/MobileCompanionApiTest.php`

**Reason:** Build Milestone 1 inbox — dense list, advisor awareness (posture, vehicle, RO, advisor), filters, swipe actions, pin, search, FAB call, poll refresh, unread badge. API enriched with posture_label, assigned_label, automotive context via ConversationLink fallback.

**Architecture impact:** Mobile projection enrichment only; no new authority. Pinned threads are client-local until observation earns server persistence.

**Outstanding questions:** Edward floor review on Razr — does inbox feel Quo-grade before Milestone 2.

### 2026-07-05 — Companion design philosophy (advisor awareness)

**PR:** (pending)

**Files changed:** `docs/companion-v1/MISSION.md`, `docs/companion-v1/README.md`, `docs/communications/communications-foundational-doctrine-v1.md`, `.cursor/rules/ark-companion-communications.mdc`

**Reason:** Add frozen design philosophy — *every tap should reduce uncertainty* — competitive frame (advisor awareness vs conversations/CRM/ROs), inbound-call example, ARKv2-as-operations-workspace relationship, sacred deep-link boundary.

**Architecture impact:** Product doctrine only.

**Outstanding questions:** None.

### 2026-07-05 — Companion product doctrine shift (mission-first Cursor input)

**PR:** (pending)

**Files changed:** `docs/companion-v1/MISSION.md` (new), `docs/companion-v1/README.md`, `docs/engineering/CURRENT_MILESTONE.md`, `docs/engineering/ACTIVE_PR.md`, `docs/companion-v1/08-flutter-build-order.md` (superseded note), `docs/communications/communications-foundational-doctrine-v1.md`, `.cursor/rules/ark-companion-communications.mdc` (new), `.cursor/rules/ark-communications-bounded-context.mdc`

**Reason:** Architecture stable — shift Cursor guidance from screen-by-screen implementation to frozen product mission, Quo UX benchmark, 7 milestones, and deep-link boundary. Rename internally to **ARK Companion: Communications**.

**Architecture impact:** Documentation and Cursor input model only — no runtime behavior change. Screen specs demoted to reference library.

**Outstanding questions:** Milestone 1 (Inbox) execution against Quo benchmark on Razr.

### 2026-07-05 — Advisor-first communications identity (workstation freeze)

**PR:** doctrine

**Decision:** Post transport reset, communications ownership is **advisor identity**, not station → phone → advisor. Required: Advisor · Device · Conversation. Optional: Station (metadata only).

**Freeze until Companion production quality:** No IP mapping, lock screens, station inference, or browser-binding ownership work. Existing workstation code stays; do not extend for comms.

**Vocabulary:** Prefer *active advisor* on new surfaces over *current operator at station*.

**Architecture impact:** Companion and `/api/mobile/*` project advisor ownership; stations remain orientation metadata per station doctrine.

**Outstanding questions:** Revisit station automation only after Companion floor cert.

### 2026-07-05 — Retire ARKv2 station lock screen

**PR:** (pending)

**Files changed:** `WorkstationPresence`, `WorkstationOperatorController`, `BindWorkstationBrowserAction`, `AssignWorkstationOperatorAction`, `AuthenticatedSessionController`, `app.blade.php`, `workstation-presence.blade.php`, `ark-workstation-presence.js`, settings/profile copy, workstation presence tests.

**Reason:** Full-screen PIN lock (Step away, idle lock, privacy gate) interrupted floor work more than it protected customer context.

**Architecture impact:** Session stays in ops shell at bound stations; operator assigned on bind/login without PIN gate. Unlock API retained for optional handoff. `operationalPrivacyActive()` always false.

**Outstanding questions:** None.

### 2026-07-05 — Remove GoHighLevel references

**PR:** (pending)

**Files changed:** Deleted `docs/companion-v1/references/ghl/`, `references/external/ghl/`, `product-review/ghl-ark-comparison.md`, `06-ghl-reference-index.md`, `public/companion-preview/refs/ghl-*.png`; scrubbed companion specs, mobile audits, engineering docs, cutover script rollback, `CompanionPreviewController`.

**Reason:** Shop no longer uses GoHighLevel; GHL must not appear in product docs, rollback paths, or local preview assets.

**Architecture impact:** Documentation and infra only — no runtime behavior change.

**Outstanding questions:** None.

### 2026-07-05 — Twilio native communications reset

**PR:** (pending)

**Files changed:** Removed Asterisk PHP/routes/infra; `TelephonyProgrammableVoiceGuard` always active; `TelephonyProviderManager` Twilio-only; `MobileVoiceTransportManager` → `TwilioMobileVoiceTransport`; `VoiceTransportConfiguration` Twilio SIP registrar only; Poly provisioning → `telephony.sip_provisioning`; inbox default landing; `MobileCompanionShellProjection` comms-first; `ConversationActivityPresenter` `activity_type`; `OperationalEventKind` recording/ai_summary; ADR-0005; cursor rule updates; `TwilioNativeVoiceResetTest`; migration `2026_07_05_100000_migrate_telephony_to_twilio_native`.

**Reason:** Retire shop Asterisk PBX transport; restore Twilio Programmable Voice as sole voice path while keeping CallSession/Conversation authority.

**Architecture impact:** Transport reset only — domain timelines unchanged. Desk VVX → Twilio Elastic SIP. Mobile → Twilio Client SDK. PSTN Voice URL must point at Twilio webhooks (Console cutover).

**Outstanding questions:** Production floor cert (VVX-on-Twilio-SIP + inbound PSTN webhook) before deleting any remaining Asterisk VPS routing.

### 2026-07-05 — Production PV cutover executed (Twilio Console)

**PR:** (manual — not in code)

**Actions:** `infra/coolify/configure-twilio-production-pv-cutover.sh cutover` — removed `+17194136227` from Elastic SIP trunk `TK5dbba9690227fe7c4d3448232aabfd4a`; disabled origination `sip:voice.demo-auto.test:5060`; set Voice URL → `https://app.demo-auto.test/webhooks/communications/twilio/voice/incoming` and status → `/voice/status`. Production ring group: `desk1` + `desk2` SIP endpoints on `lnp-chelton.sip.twilio.com`; VVX at `.131` / `.171` registered.

**Rollback:** `./infra/coolify/configure-twilio-production-pv-cutover.sh rollback-asterisk` — emergency PSTN → Asterisk trunk only; does not restore legacy third-party voice URLs.

**Architecture impact:** PSTN ingress is ARK Programmable Voice only. Asterisk trunk path inactive.

**Outstanding questions:** Floor cert checklist — inbound PSTN ring, outbound desk dial, missed, VM, recording on Twilio path.

### 2026-07-05 — Website lead cross-channel comms linking

**PR:** (pending)

**Files changed:** `LeadConfirmationAuditConversation.php`, `ConversationLeadResolver.php`, `SendWebsiteLeadConfirmationAction.php`, `RecordLeadFirstContactAction.php`, `CommunicationWorkboardProjection.php`, `ShopTurnAttentionQueue.php`, `CommunicationsWorkspaceProjection.php`, `CommunicationsWorkspaceContextBuilder.php`, `ConversationAttentionCandidateBuilder.php`, `ReconcileLeadConfirmationAuditThreadsCommand.php`, `tests/Feature/Leads/LeadCrossChannelConversationTest.php`, `routes/api.php` (missing import fix for test bootstrap).

**Reason:** Website leads with phone + email created sibling email audit threads that stayed Open · Needs shop while advisors worked the SMS thread (Patty / LEAD-520).

**Architecture impact:** Lead authority links email threads by `contact_email`; confirmation audit threads auto-resolve on send or first advisor contact; Needs shop projections suppress ghost audit rows. No AI identity layer.

**Outstanding questions:** Run `communications:reconcile-lead-audit-threads` on production once after deploy.

### 2026-07-05 — Companion floor-test wiring (SIP · search · comms)

**Files:** `CompanionVoiceIncomingHost` · in-app answer + post-call · thread Call · search History · notification pop · calls list VM play · login Herd shortcut.

**Reason:** Close P0 experience gaps for Edward Razr floor cert — real ring → context → answer → post-call without hunting.

**Outstanding:** Production deploy · device SIP registration · sprint board sign-off on floor.

### 2026-07-05 — Companion Sprint 1 completion pass (Flutter + mobile API)

**PR:** (pending)

**Files changed:** Call detail screen · outbound `launchCompanionCall` · search/RO call actions · incoming-call deep link fix · my-work rows · `customer_display_phone` on RO header · mark-handled API · build order doc sync.

**Reason:** Close Sprint 1 vertical slices — search starts work, calls recovery, deploy-ready API surface.

**Outstanding:** Deploy `arksmsv2` to production · floor cert on Razr · voicemail playback P1.

### 2026-07-05 — Companion v1 vehicle · calls · voice bootstrap (ark-mobile)

**PR:** (pending — `ark-mobile` repo)

**Files changed:** `companion_vehicle_workspace_screen.dart`, `companion_calls_screen.dart`, `companion_more_screen.dart`, `companion_session.dart` (telephony + `toMobileShell()`), `companion_providers.dart` (voice bootstrap, vehicle/calls providers), `companion_shell.dart`, `mobile_api.dart` (`callsLibraryJson`), `README.md`. Backend (local): `MobileCompanionShellProjection.php` phone status fix + test assertion.

**Reason:** Continue Sprint 1 vertical slice — search/customer vehicle taps, Calls & VM recovery surface, More tab with phone retry, wire ARK Voice bootstrap after Companion auth.

**Architecture impact:** Reuses legacy `VoiceDialerBootstrap` + `VehicleWorkspace` models against same `/api/mobile` authority. No parallel SMS or client-side payment math.

**Outstanding questions:** Square terminal in payment sheet · real Answer/Decline on device · deploy backend phone_status_label fix to production.

### 2026-07-04 — Companion v1 Flutter Slice 1–2 (ark-mobile)

**PR:** (pending — `ark-mobile` repo)

**Files changed:** `ark-mobile/lib/companion/**` — new entry `main_companion.dart`, auth, tab shell from `companion` API payload, home continuity wired to `GET /api/mobile/continuity`. Legacy `lib/main.dart` untouched.

**Reason:** Edward approved design preview — ship real installable Companion shell without patching frozen Workspace UI.

**Architecture impact:** Parallel Flutter product surface; reuses `ApiClient` + shop connection; no SIP bootstrap on auth. Deep link routing stubbed for Slice 3.

**Outstanding questions:** Floor test on Razr · Slice 3 push deep links · Slice 4 conversation thread.

### 2026-07-04 — Companion v1 API layer + Figma handoff

**PR:** (pending)

**Files changed:** `app/Ark/Mobile/MobileCompanionDeepLink.php`, `MobileCompanionShellProjection.php`, `MobileIncomingCallContextProjection.php`, `MobileCallsLibraryProjection.php`, HTTP controllers, enhanced `MobileUserPresenter`, `MobileConversationsProjection`, `MobileNotificationsProjection`, `MobileObservationStreamProjection`, `routes/api.php`, `tests/Feature/Mobile/MobileCompanionApiTest.php`, `docs/companion-v1/design-system/figma-handoff-gate-screens.md`, `product-review/edward-sign-off-checklist.md`, `07-api-projection-backlog.md`, `08-flutter-build-order.md`, `ACTIVE_PR.md`.

**Reason:** Blow up Companion v1 — backend now exposes incoming-call context, calls library, companion shell navigation, and `companion://` deep links on comms/notifications/continuity. Gate-screen Figma handoff for Edward/designer.

**Architecture impact:** Projections only — reuses `CustomerCallContextResolver`, `CallLibraryQuery`, `MobileEstimateProjection`. Legacy mobile nav unchanged; `companion` block parallel for new app.

**Outstanding questions:** Edward P0 sign-off · new Flutter module per build order.

### 2026-07-04 — Companion v1 screen specs + review pack (complete draft)

**PR:** (pending)

**Files changed:** `docs/companion-v1/screens/*` (35 specs), `01-screen-inventory.md`, `02-flows.md`, `04-navigation-hierarchy.md`, `07-api-projection-backlog.md`, `design-system/screen-component-map.md`, `product-review/edward-sign-off-checklist.md`, external Quo assets + catalogs.

**Reason:** Week 1 design sprint — every inventory row has a production spec; Quo reference library downloaded; API backlog and Edward checklist ready for sign-off before Flutter.

**Architecture impact:** None until sign-off. `/api/mobile` extensions documented in `07-api-projection-backlog.md`.

**Outstanding questions:** Edward P0 sign-off on checklist · first Flutter PR = incoming call context + push deep links per backlog.

### 2026-07-04 — ARK Companion v1 product discovery space

**PR:** (pending)

**Files changed:** `docs/companion-v1/*` (README, screen inventory, flows, role maps, hierarchy, components, frozen legacy), `docs/engineering/ACTIVE_PR.md`, `docs/engineering/CURRENT_MILESTONE.md`, `docs/mobile/companion-sprint-1-run-the-shop.md`.

**Reason:** Course correction — legacy Flutter was incrementally modified without product definition. New product: ARK Companion v1. Week 1 = product design sprint (screens, flows, roles, components). Legacy app frozen.

**Architecture impact:** None this week. Event architecture remains archived. Flutter forbidden until discovery sign-off.

**Outstanding questions:** Edward completes P0 sign-off checklist before Flutter build.

### 2026-07-04 — Companion Sprint 1 mission (course correction)

**PR:** (pending)

**Files changed:** `docs/mobile/companion-sprint-1-run-the-shop.md`, `docs/engineering/ACTIVE_PR.md`, `docs/engineering/CURRENT_MILESTONE.md`, `docs/mobile/companion-event-architecture-sprint-v1.md` (frozen), `docs/mobile/contract-realization-register-v1.md` (frozen).

**Reason:** Product pivot — architecture no longer the bottleneck; Edward must love using the Razr. New active mission: run the shop from the phone. Experience-based sprint board replaces document-driven E1 progress.

**Architecture impact:** Event architecture sprint archived (not deleted). Payment Received E1 slice remains in code. All new work filtered through: one tap to correct workspace.

**Outstanding questions:** First PR — incoming call context + notification deep links (Flutter + `/api/mobile`).

### 2026-07-04 — Events kiosk public mode + pre-July-11 polish

**PR:** (pending)

**Files changed:** `kiosk_access_token` + `kiosk_last_heartbeat_at` migrations, `EventKioskUrls`, `EnsureEventKioskAccess`, public `/kiosk/{slug}` routes, `EventKioskPresenceProjection`, `EventKioskStatusController`, dashboard status chip, idle photo wall + mobile QR on kiosk, Events nav link, `tests/Feature/Operations/EventsKioskTest.php`, `tests/Unit/Events/EventKioskPresenceProjectionTest.php`.

**Reason:** Touchscreen must run without staff login; dashboard needs live/offline visibility; line guests should check in from phones; photo booth output should become a living idle scrapbook.

**Architecture impact:** Kiosk is a token-gated public projection; admin dashboard polls heartbeat derived from kiosk state polls (authority stays on `events` + attendees/photos). Event Builder explicitly deferred post-July-11.

**Outstanding questions:** Event Builder wizard after floor observation; optional dedicated photo-wall display route for a second monitor.

### 2026-07-04 — Events Event Mode live dashboard

**PR:** (pending)

**Files changed:** `live_mode_*` migration, `EventLiveController`, start/end live controllers, `EventLiveProjection`, `event-live` layout, `operations/events/live.blade.php`, show page Start Event, draw-winner redirect when live.

**Reason:** On event day the operator runs an event, not a repair shop — hide nav and surface only check-ins, kiosk heartbeat, draw winner, health, and photo count.

**Architecture impact:** Event authority gains `live_mode_started_at` / `live_mode_ended_at` timestamps; Event Mode is a separate staff projection route (`/app/events/{slug}/live`), not a global app flag.

**Outstanding questions:** Post-July-11 analytics export; Event Builder.

### 2026-07-04 — E1 Payment Received contract realization

**PR:** (pending)

**Files changed:** `app/Ark/Operations/Events/EventContract.php`, `EventStreamScope.php`, `EventContractScopeMembership.php`, `app/Ark/Operations/Financial/EmitPaymentReceivedEvent.php`, `RecordLedgerEntryAction.php`, payment recorders, `CompleteSquarePaymentAction.php`, timeline mappers, `tests/Feature/EventContracts/PaymentReceivedContractRealizationTest.php`, `docs/mobile/contract-realization-register-v1.md`.

**Reason:** First E1 vertical slice — prove Payment Received has exactly one Financial emitter, projects through Customer/RO timelines with business language, and clears waiting-on-payment authority posture. Deposits no longer emit Payment Received.

**Architecture impact:** `EmitPaymentReceivedEvent` is sole recorder for `RepairOrderPaymentReceived` transport event. Payload includes `event_contract: payment_received`. Timeline headlines use **Payment Received**. Architecture verification tests enforce single emitter and projection trace to authority.

**Outstanding questions:** Shop Feed projection surface not yet wired — scope membership verified mechanically. Next contract: Payment Requested (separate PR).


**PR:** (pending)

**Files changed:** `docs/ecosystem/ark-event-native-platform-v1.md`, `docs/ecosystem/ark-business-language-v1.md`, `docs/ecosystem/ark-scoped-event-streams-v1.md`, `docs/mobile/companion-event-architecture-sprint-v1.md`, `docs/mobile/contract-realization-register-v1.md`, `docs/mobile/event-contracts-v1.md`, `docs/mobile/companion-timeline-scopes-v1.md`, `docs/mobile/ark-companion-product-convergence-v1.md`.

**Reason:** Architecture phase closed after E0–E0.6. User renamed E1 from "Mapper Alignment" to **Contract Realization** — prove every business event contract has exactly one implementation, not mapper correctness. Added doctrine stack, updated platform sentence ("models how an automotive repair shop actually works"), vocabulary conformity rule, and vertical-slice validation (contract → implementation → projection → observation).

**Architecture impact:** Doctrine frozen until implementation proves model gap. E1 work is spreadsheet-boring verification via Contract Realization Register. No new architecture docs unless model cannot express reality.

**Outstanding questions:** Begin first vertical slice (Payment Received?) when user asks to start PHP implementation pass.


**PR:** (pending)

**Files changed:** `database/migrations/2026_07_04_100000_create_events_tables.php`, `app/Ark/Operations/Events/*`, `resources/views/operations/events/*`, `resources/views/components/layouts/event-kiosk.blade.php`, `database/seeders/OpenHouseEventSeeder.php`, `routes/web.php`, `tests/Feature/Operations/EventsKioskTest.php`.

**Reason:** July 11 LugsNPlugs Open House needs a full-screen touchscreen check-in kiosk with giveaway drawing — not a Google Form.

**Architecture impact:** New operational module under `App\Ark\Operations\Events`. `events` + `event_attendees` authority stores; admin dashboard is a projection; kiosk is standalone layout with Alpine state machine and JSON poll for live attendee count + winner reveal animation.

**Outstanding questions:** Event CRUD admin, logo uploads, customer/lead linking, email export, and nav link placement deferred until floor observation.

### 2026-07-04 — ARK Events kiosk Phase 2 (alive + photo booth)

**PR:** (pending)

**Files changed:** Phase 2 migration, `EventKioskProjection`, `EventPhoto*`, `ark-event-kiosk.js`, cinematic kiosk Blade, admin dashboard charts, `OpenHouseEventSeeder` schedule/tips/backgrounds, public photo share routes, `qrcode` npm dep.

**Reason:** Kiosk should invite guests emotionally — motion, live feed, schedule, cinematic photos, photo booth with QR share, dramatic winner draw, and ARK Ambassador framing.

**Architecture impact:** Kiosk is standalone Alpine bundle (`ark-event-kiosk.js`). Photos stored on `public` disk; share page + QR are public routes. Admin dashboard adds referral conic chart + hourly sparkline.

**Outstanding questions:** Replace placeholder `/shop-photos/` with floor photography; optional ambient audio on drawing (browser autoplay limits); theme presets for future Ambassador modes.

### 2026-07-04 — Communications voice cleanup Phase A (owner UI)

**PR:** refactor(comms): Phase A voice authority cleanup — owner language only

**Files changed:** `telephony-settings.blade.php`, `mobile-push-settings.blade.php`, `communications-general-overview.blade.php`, `TelephonyHealth.php`, `TelephonyHealthSettingsTest.php`.

**Reason:** Retire Twilio Programmable Voice from owner-facing settings without changing production call routing, PJSIP, dialplan, or VVX behavior. Align UI with Extension = identity / Endpoint = device north star.

**Architecture impact:** UI-only. Removed PV credential fields, primary provider selector, and Twilio SIP desk-phone guide from Communications settings. Renamed ARK Voice → ARK Phone, Ring Group tab → Call routing, Push transport → Push notifications. Backend voice stack, ring group authority, and Asterisk execution unchanged.

**Outstanding questions:** Phase B (Flutter transport erasure), Phase C (CallRoutingPolicy + dialplan compiler parity), Phase D (delete PV webhooks/TwiML). See [voice-runtime-inventory-v1.md](../../docs/communications/voice-runtime-inventory-v1.md).

### 2026-07-04 — Voice runtime inventory (pre–Phase B)

**PR:** docs(comms): voice runtime inventory v1 — classify Asterisk / PV / messaging / dead code

**Files changed:** `docs/communications/voice-runtime-inventory-v1.md`

**Reason:** Phase A cleaned owner Settings; repo-wide search shows substantial Twilio PV stack, provider selection seam, and learn-copy gaps remain. Inventory before Flutter and backend erasure.

**Architecture impact:** None — documentation only. Confirms target graph: Voice → Asterisk; Twilio → carrier + messaging only.

**Outstanding questions:** Phase B (ark-mobile transport erasure only). Backend provider/webhook collapse waits for boring phones + closed rollback window.

### 2026-07-04 — Subsystem lifecycle doctrine (Pressure → Evolution; three states)

**PR:** docs(runtime): ark-subsystem-lifecycle — Converging / Observing / Evolving

**Files changed:** `.cursor/rules/ark-subsystem-lifecycle.mdc`, `docs/runtime/README.md`

**Reason:** Sprint close — repeatable ARK engineering lifecycle. Three states for every subsystem. One-page runtime catalog template. Anti-ceremony rule.

**Architecture impact:** Documentation and process only. Voice enters **Observing** — no sprint work until baseline earns trust.

### 2026-07-04 — Known defects, evolution gate, PR phrase ban

**PR:** docs(runtime): baseline defects section; Production earns the right to evolve

**Files changed:** `voice-baseline-v1.md`, `docs/runtime/README.md`, `ark-cleanup-sprint-discipline.mdc`

**Reason:** Baseline = operationally trusted, not feature complete. Known defects list. Monday certifies baseline (aircraft-style log). Ban "while I was in there" voice PR phrases.

**Architecture impact:** Documentation and review discipline only.

### 2026-07-04 — Observation log + Stabilize gate before Phase D

**PR:** docs(runtime): Monday success = boring; daily observation log; stabilize week

**Files changed:** `voice-baseline-v1.md`, `voice-runtime-authority.md`, `communications-voice-cleanup-sprint-v1.md`, `docs/runtime/README.md`

**Reason:** Natural stopping point — observation beats commits. Roadmap: Observe → Stabilize (1 week) → Baseline frozen → Phase D. Baseline gets append-only observation log.

**Architecture impact:** Documentation only. No voice code until stabilize passes.

### 2026-07-04 — Runtime catalog + voice baseline v1 (architecture commit)

**PR:** docs(runtime): convergence lifecycle, voice baseline, archive plan

**Files changed:** `docs/runtime/README.md`, `voice-runtime-authority.md`, `voice-baseline-v1.md`, `phase-b-voice-cleanup-mission-v1.md`, sprint updates, `docs/archive/voice-cleanup/README.md`, mobile report/inventory cross-links, `ark-cleanup-sprint-discipline.mdc`

**Reason:** Promote runtime docs from sprint artifacts to permanent architecture. Add operations baseline template for post-observation sign-off. Document Authority→Baseline convergence lifecycle for all subsystems.

**Architecture impact:** Documentation only.

**Outstanding questions:** Monday observation → fill baseline known-good date. Phase D → archive sprint evidence.

### 2026-07-04 — Runtime Authority catalog + observation gate before Phase D

**PR:** docs(runtime): permanent voice-runtime-authority; entry points metric; observe gate

**Files changed:** `docs/runtime/README.md`, `docs/runtime/voice-runtime-authority.md`, sprint/mission/report updates, `ark-cleanup-sprint-discipline.mdc`

**Reason:** Runtime Authority Reports become permanent catalog pages. Add Voice runtime entry points: 1. Phase D blocked until shop observation validates Phase B did not expose hidden dependencies.

**Architecture impact:** Documentation and process only.

**Outstanding questions:** Run the shop (Mon+). No voice code during observation.

### 2026-07-04 — Phase B complete (ark-mobile runtime collapse)

**PR:** refactor(mobile): Phase B — collapse voice runtime to ArkVoiceTransport only

**Files changed (ark-mobile):** `ark_voice_dialer.dart`, `ark_voice_transport.dart`, deleted Twilio/Noop/VoiceTransport seam, bootstrap/shell/incoming_call_host/snapshot, pubspec, Android Gradle/ProGuard/settings, demo fixture, docs. **Files changed (arksmsv2):** mission, inventory, runtime authority report, sprint status.

**Reason:** APPROVED Phase B — mechanical erasure per B1 inventory. Collapse runtime selection; remove twilio_voice package last; zero behavior change.

**Architecture impact:** ark-mobile has exactly one voice path: `ArkVoiceDialer → ArkVoiceTransport → sip_ua → Asterisk`. Backend PV stack unchanged (Phase D).

**PR footer:** Deleted 7 files · Renamed 0 symbols · Behavior changes: 0

**Outstanding questions:** Run the shop. Backend `TwilioMobileVoiceTransport` + PV webhooks remain for Phase D.

### 2026-07-04 — Phase B1 complete (ark-mobile voice inventory)

**PR:** docs(mobile): Phase B1 ark-mobile voice cleanup inventory — zero Unknown

**Files changed:** `docs/mobile/ark-mobile-voice-cleanup-inventory-v1.md`, `docs/communications/phase-b-voice-cleanup-mission-v1.md`, `communications-voice-cleanup-sprint-v1.md`

**Reason:** APPROVED Phase B mission. B1 walk of `/Users/edwardsoares/Herd/ark-mobile` — classified every PV/Twilio/transport artifact with Proof and Runtime Authority; production + dead graphs; recommended B2 PR sequence. No code changes.

**Architecture impact:** Documentation only.

**Outstanding questions:** Reviewer sign-off → B2 mechanical erasure in ark-mobile; backend PV deletion per voice-runtime-inventory (separate PRs).

### 2026-07-04 — B1 Unknown escalation, Runtime Authority, dual graphs, B2 closure block

**PR:** docs(mobile): Unknown must resolve; Runtime Authority column; production + dead graphs; Behavior changes: 0

**Files changed:** `ark-mobile-voice-cleanup-inventory-v1.md`, `communications-voice-cleanup-sprint-v1.md`, `ark-cleanup-sprint-discipline.mdc`

**Reason:** Unknown is investigation-only (escalate to classify); inventory adds Runtime Authority; B1 requires production graph + dead graph (empty dead graph = Phase B done); every B2 PR must state Deleted/Renamed counts and exactly zero behavior changes.

**Architecture impact:** Documentation and review discipline only.

**Outstanding questions:** Execute Phase B1 in ark-mobile when user requests.

### 2026-07-04 — B1 classification schema + Proof column + dependency graph

**PR:** docs(mobile): B1 inventory template — Unknown gate, Proof, B2 PR limits, pubspec-last

**Files changed:** `ark-mobile-voice-cleanup-inventory-v1.md` (new template), `communications-voice-cleanup-sprint-v1.md`, `ark-cleanup-sprint-discipline.mdc`, `ark-two-implementations.mdc`

**Reason:** Tighten B1 before execution: six classifications including Unknown; mandatory Proof column and delete gate (who calls / behavior / replacement); B1 dependency graph; B2 PR allowlist; `twilio_voice` code-first then pubspec.

**Architecture impact:** Documentation and review discipline only.

**Outstanding questions:** Execute Phase B1 in ark-mobile when user requests.

### 2026-07-04 — Phase B B1/B2 split + cleanup sprint discipline rule

**PR:** docs(comms): B1 inventory before B2 erasure; no-behavior-change cleanup rule

**Files changed:** `communications-voice-cleanup-sprint-v1.md`, `.cursor/rules/ark-cleanup-sprint-discipline.mdc`, `.cursor/rules/ark-two-implementations.mdc`

**Reason:** Phase B must not delete code until full ark-mobile inventory is classified. Add permanent rule: cleanup sprints may rename/move/delete only — never change runtime behavior. Strengthen acceptance to 30-second engineer test; ban `AsteriskVoiceTransport` rename.

**Architecture impact:** Documentation and review discipline only.

**Outstanding questions:** Execute Phase B1 inventory in ark-mobile when user requests.

### 2026-07-04 — Voice cleanup sequencing lock + two-implementations rule

**PR:** docs(comms): lock Phase B–D sequencing; ark-two-implementations platform rule

**Files changed:** `communications-voice-cleanup-sprint-v1.md`, `.cursor/rules/ark-two-implementations.mdc`

**Reason:** Phase A/A½ shipped without wire change. Codify locked sequencing (Run the shop between B→C and C→D), Phase B ark-mobile audit protocol, and permanent rule: no Manager/Strategy/Factory without two production implementations.

**Architecture impact:** Documentation and review discipline only.

**Outstanding questions:** Phase B execution in `ark-mobile` sibling repo when user requests.

### 2026-07-04 — Communications voice cleanup Phase A½ (learn + health dead code)

**PR:** refactor(comms): Phase A½ — stale learn copy + TelephonyHealth PV mobile strings

**Files changed:** `communications-setup.blade.php`, `incoming-calls-floor.blade.php`, `TelephonyHealth.php`.

**Reason:** Close Phase A owner-vocabulary work before Phase B. Remove stale ARK Voice / Twilio-now-PBX-later learn copy and dead Programmable Voice health strings without changing telephony behavior.

**Architecture impact:** Copy and dead-code removal only. No routing, PJSIP, dialplan, or webhook changes.

**Outstanding questions:** Phase B (ark-mobile only).

### 2026-07-04 — After-hours test caller bypass

**PR:** feat(comms): allow listed test numbers to ring through closed hours

**Files changed:** `TelephonyCallFlowSettings`, `TelephonyIncomingCallFlow`, `TelephonyRingGroup`, `TwilioTelephonyProvider`, `TelephonyWebhookController`, `MobileVoiceInboundDialplanSync`, `ShopCommunicationsSettingsController`, telephony settings Hours tab, tests.

**Reason:** Shop owners need to test inbound call routing after hours from their own cell without opening the line to all callers.

**Architecture impact:** `hours_bypass_numbers` lives in existing `telephony_call_flow` shop settings. Twilio ingress and Asterisk inbound dialplan both honor caller-specific bypass before closed-hours voicemail. All other callers unchanged.

**Outstanding questions:** None.

### 2026-07-03 — Workspace Surface Audit Phase 1 (navigation prune)

**PR:** refactor(ops): workspace surface Phase 1 — rail jobs-only + legacy comms redirects

**Files changed:** `CommunicationsLegacySurfaceRedirectController`, `CommunicationsWorkspaceRedirect`, `CommunicationsWorkspaceProjection`, `CommunicationsQueueChannelProjection`, `app.blade.php` (left rail), comms section nav, `communications-workspace.blade.php`, settings shop sidebar, routes, tests; docs: `workspace-constitution-v1.md`, `workspace-surface-audit-v1.md`, `TECHNICAL_DEBT.md` (Persistent Surface anti-pattern).

**Reason:** Workspace Surface Audit crossed design threshold — ARK accumulated discoverable pages after Attention/Job Board absorbed their jobs. Phase 1 enforces Workspace Constitution Law 4 (navigation = jobs) and Law 6 (deletion as feature) without removing authority.

**Architecture impact:** Inbox, History, Workboard, and standalone reply **routes redirect** to Attention (query preserved). Left rail drops admin/capability links (Voice, Website, Growth, Customers, Vehicles, RO index, Leads) → Settings or contextual entry. Comms section nav: Needs Attention + Internal only. Phase 2: delete blades + re-home history search panel.

**Outstanding questions:** Floor observation on Leads demotion; history archive search panel on Attention (Phase 2).

### 2026-07-03 — Pay link send fix + staff payment portal preview

**PR:** fix(comms): restore Send Pay Link SMS/email; add Portal tab payment preview

**Files changed:** `SendPaymentLinkController`, `SendConversationPaymentLinkController`, `SendPaymentDeliveryAction`, `SendPaymentLinkAction`, `RepairOrderConversationSendProjection`, `RepairOrderPortalPaymentPreviewController`, `RepairOrderPaymentPortalLinkController`, `payment-portal-link.blade.php`, `invoice-pay.blade.php` (staff preview), comms composer + quick-reply JS, routes, tests.

**Reason:** Pay link sends failed when customer email on file was invalid — validation ran on SMS-only requests. Advisors also needed staff-side preview of the customer payment page before texting.

**Architecture impact:** Email validation scoped to email/both delivery; conversation-scoped payment send records on active thread; staff preview uses short-lived pay token with card entry disabled (same pattern as estimate/inspection preview).

**Outstanding questions:** None.

### 2026-07-02 — Dedicated Calls & Voicemail library surface

**PR:** feat(comms): Calls & VM library with inline playback

**Files changed:** `CallLibraryQuery`, `CallLibraryProjection`, `CommunicationsCallLibraryController`, calls index Blade, comms nav, route, middleware exempt, feature test.

**Reason:** Advisors had no dedicated surface to find and listen to voicemails/recordings — playback links were buried on history/queue rows when media URLs existed.

**Architecture impact:** Read-only projection over existing `CallSession` authority; playback via existing `CallRecordingPlayback` routes. Comms gate exempt for direct access.

**Outstanding questions:** Recordings bind mount still lost on Coolify compose regenerate — run `infra/coolify/lugsnplugs/mount-voice-recordings-on-arksms.sh` post-deploy until persistent storage config ships.

### 2026-07-02 — Voice transport deploy defaults; Asterisk duplicate event guard

**PR:** fix(production): default bridge/WSS from voice stack mount; ignore duplicate Asterisk call SID 500s

**Files changed:** `VoiceTransportConfiguration`, `AsteriskCallEventController`, unit tests.

**Reason:** Coolify redeploys drop `VOICE_BRIDGE_RELOAD_URL` / `VOICE_SIP_WSS_URI` from container env; bridge fell back to public HTTPS (30s timeout ERROR logs). Duplicate inbound Asterisk `unique_id` still returned HTTP 500 on race.

**Architecture impact:** When `VOICE_ASTERISK_STATIC_DIR` is mounted, bridge reload defaults to internal `ami-bridge:8090` without env. WSS URI defaults from resolved registrar in `applyRuntimeConfig`. Asterisk ingress returns 200 on duplicate constraint instead of 500.

**Outstanding questions:** None — verify bridge `isConfigured()` true after next deploy without manual env.

### 2026-07-02 — Mobile voice WSS default, WAV fmt fix, voicemail dialplan hardening

**PR:** fix(voice): default WSS on :443; repair greeting WAV fmt; voicemail beep+record; internal bridge reload only

**Files changed:** `VoiceTransportConfiguration`, `AsteriskVoiceBridgeClient`, `AsteriskTelephonyWavNormalizer`, `MobileVoiceInboundDialplanSync`, `sync-arksms-voice-transport.sh`, unit tests.

**Reason:** VVX in/out worked; mobile in/out failed (810x unregistered — app fell back to blocked `:8089` WSS). No-answer path hit corrupt `pcm_u8` greetings (`buildWav` 14-byte fmt chunk) then silent `Record(...,q)` → caller hangup. Bridge reload still timed out when env wiped on redeploy because client fell back to public HTTPS.

**Architecture impact:** `sipWssUri()` defaults to `wss://{registrar}/asterisk/ws` without port. Bridge reload requires explicit `VOICE_BRIDGE_RELOAD_URL` (env/shared secrets) — no public HTTPS fallback. Inbound dial uses `Dial(...,gU(...))` so voicemail runs after no-answer; greeting miss falls through to beep + 30s max record. WAV normalizer emits 16-byte PCM fmt and repairs u8 sources.

**Outstanding questions:** Do 810x endpoints register after mobile app restart? Does no-answer PSTN reach beep + VM recording on floor test?

### 2026-07-02 — Inbound voice hours timezone + Asterisk greeting format + bridge reload

**PR:** fix(voice): shop timezone in inbound hours check; normalize TTS WAV to 8 kHz; harden call-session reload path

**Files changed:** `MobileVoiceInboundDialplanSync`, `AsteriskTelephonyWavNormalizer`, `AsteriskVoiceGreetingSync`, `ReloadMobileVoiceAsteriskConfigJob`, `CallSessionRecorder`, `infra/coolify/asterisk/docker-compose.yml`, unit tests.

**Reason:** VVX inbound appeared dead — calls routed after-hours because `GotoIfTime` used Asterisk UTC; after-hours voicemail greeting failed (OpenAI 24 kHz WAV vs Asterisk 8 kHz) so callers heard silence then hangup. Voice config sync logged 500-class errors when bridge reload hit public HTTPS (30s timeout); production now uses internal `VOICE_BRIDGE_RELOAD_URL`.

**Architecture impact:** Business hours projection uses shop timezone from `TelephonyCallFlowSettings`. Greeting sync normalizes WAV before publish so mobile PJSIP sync cannot regress playback. Bridge reload failures stay warning-level; duplicate inbound `CallSession` create races are idempotent in fallback recorder.

**Outstanding questions:** Did inbound PSTN ring 101/102 during open hours after deploy? Coolify asterisk stack needs `TZ=America/Denver` recreate to pick up compose change.

### 2026-06-27 — Twilio webhooks decoupled from shop primary provider

**PR:** fix(telephony): route Twilio HTTP webhooks through TwilioTelephonyProvider when shop primary is Asterisk

**Files changed:** `TelephonyProviderManager` (`twilio()` helper), `TelephonyCallbackAnswerWebhookController`, `TelephonyWebhookController`, `TelephonyStatusWebhookController`, `TelephonyClientIncomingWebhookController`, `TelephonyClientOutboundWebhookController`, `TelephonyDialCompleteWebhookController`, `TelephonySipOutboundWebhookController`, `TelephonyCallbackTest`.

**Reason:** Production error `f233b1e1` — mobile shop_callback answered on advisor cell; Twilio POSTed `callback-answer` but controller called `parseCallbackAnswerRequest()` on `AsteriskTelephonyProvider` because shop primary is Asterisk. Mobile dial chain (ARK Voice → Twilio shop_callback → native) is correct; only webhook provider resolution was wrong.

**Architecture impact:** Shop primary provider (Asterisk for ingress/PSTN) is unchanged. Twilio-signed HTTP webhooks always resolve `TwilioTelephonyProvider` — transport-specific parsing stays on the transport that sent the payload.

**Outstanding questions:** Why was `in_app_ready` false for the advisor who hit shop_callback? Floor checklist: device register, ARK Voice enabled, extension sync, `/api/mobile/me` `voice.block_reason`.

### 2026-07-02 — ARK Voice mobile WSS on Traefik :443

**PR:** infra(asterisk): proxy mobile SIP WebSocket on voice host :443; sync VOICE_SIP_WSS_URI

**Files changed:** `infra/coolify/asterisk/docker-compose.yml`, `ensure-asterisk-wss-traefik-route.sh`, `sync-arksms-voice-transport.sh`.

**Reason:** Mobile `sip_ua` could not register — `wss://voice.demo-auto.test:8089` blocked upstream (cloud firewall). Port 8089 unreachable externally; UFW alone insufficient. Traefik routes `wss://voice.demo-auto.test/asterisk/ws` → Asterisk HTTP WS :8088. Asterisk must join `coolify` network for Traefik discovery. `VOICE_SIP_WSS_URI` added to voice transport sync (no `:8089` in mobile payload).

**Architecture impact:** Mobile ARK Voice registration uses shop HTTPS edge (443), not a separate WSS port. `:8089` remains for direct desk/lab use only. Production host patched before commit; scripts encode repeatable cutover.

**Outstanding questions:** Did mobile extension show Avail after force-quit + in-app call test?

## Rules

- **Never rewrite history.** Correct mistakes by appending a follow-up entry that references the prior entry.
- **Append only.** Do not edit or delete existing entries.
- **Every entry must include:**
  - Date
  - PR
  - Files changed
  - Reason
  - Architecture impact
  - Outstanding questions

### 2026-06-29 — Mac build runner v1 frozen + ark-build CLI

**PR:** chore(infra): freeze Mac build runner v1 — ark-build up/down, session guard, metrics log

**Files changed:** `ark-build`, `ARK_BUILDER_ENABLED` guard, `build-history.log`, workflow rename to `docker-publish-mac-shadow.yml`, `ghcr-credentials.md`, Phase A/B/C file map frozen.

**Reason:** Stop designing; start installing. Single morning command; refuse builds when session down; dedicated GHCR PAT doc; metrics for future regression detection.

**Architecture impact:** v1 complete. Production `docker-publish.yml` untouched until Phase C git revert path.

**Outstanding questions:** Complete Docker Desktop install (sudo), register runner, push workflows, Phase A validation.


**PR:** chore(infra): refine Mac build runner — ark-build-01, Phase A/B/C, ~/ARK layout

**Files changed:** `check-build-runner.sh`, `ensure-buildx.sh`, `~/ARK` path convention, shadow workflow, `production-builder` label, explicit linux/amd64 Buildx, Docker Desktop settings doc, phased cutover docs.

**Reason:** User review — no day-one cutover; replaceable runner name; intentional cache location; offline queue acceptable; morning/evening session scripts.

**Architecture impact:** Runner is disposable commodity under `~/ARK/github-runner/ark-build-01`. Workflows target labels not machine identity. Future Arkify Build Service swaps worker without workflow redesign.

**Outstanding questions:** Phase A validation runs → Phase B shadow digests → Phase C swap `docker-publish.yml`.

### 2026-06-29 — Mac self-hosted GitHub build runner (prepared, not cut over)

**PR:** chore(infra): prepare Mac self-hosted build runner for GHCR

**Files changed:** `docs/deployment/mac-pro-build-runner-v1.md`, `infra/build-runner/mac/*`, `.github/workflows/docker-publish-mac-validation.yml`, comment on `docker-publish.yml`.

**Reason:** Replace GitHub-hosted Actions minutes with Edward's Mac (M5 Max, 48 GiB) as build worker. Runtime VPSes stay pull-only; GHCR + Coolify unchanged.

**Architecture impact:** Build authority moves off GitHub cloud and off production VPS. Workflow must build `linux/amd64` on Apple Silicon. Ephemeral runner posture documented. Long-term path: Arkify Build Service replaces Mac without workflow redesign.

**Outstanding questions:** Install Docker Desktop → register runner → run validation workflow → swap `docker-publish.yml` from `docker-publish.self-hosted.yml`.


**PR:** chore(infra): relocate Stinson ops to stinson-api repo

**Files changed:** Removed `infra/coolify/stinson/`, `infra/backups/firebase/stinson/`, `infra/backups/google/stinson/`, `infra/scripts/stinson-firebase-push-setup.sh` from arksmsv2; mirrored in `stinson-api/infra/`.

**Reason:** Stinson Rides is a separate product. ARK SMS must not own Stinson credentials, Coolify automation, or Firebase backups.

**Architecture impact:** Product boundary enforced. ARK `infra/backups/` is LugsNPlugs/ARK only. Stinson uses the same backup doctrine in `stinson-api`.

**Outstanding questions:** Control-plane cron or runbooks that still reference `arksmsv2/infra/coolify/stinson` need path updates on the server.

### 2026-06-29 — ARK Truth Stack + Explainability Doctrine (platform)

**PR:** docs: promote projection and explainability to ARK-wide platform doctrine

**Files changed:** `docs/ecosystem/ark-truth-stack-v1.md`, `.cursor/rules/ark-explainability-doctrine.mdc`, expanded `ark-projection-rule.mdc`, `ark-constitution-v1.md`, `ark-constitution.mdc`, `docs/growth/DOCTRINE.md` (dedupe).

**Reason:** Operational Journey proved a platform pattern — Events → Projections → Narratives → Evidence. What/Why/Show me is ARK UX grammar. Explainability Rule guards against black-box AI.

**Architecture impact:** All projections (Attention, Workboard, Revenue Explorer, Briefing, etc.) are explicitly disposable and rebuildable. Operations Briefing grammar documented as narrative-not-dashboard.

**Outstanding questions:** Operations Briefing implementation. Extend evidence layer to Attention observations and future AI insights.

### 2026-06-29 — Journey Evidence + explainable narrative doctrine

**PR:** feat(growth): journey evidence layer, identity confidence signals, projection doctrine

**Files changed:** `JourneyEvidenceItem`, `JourneyEvidenceSource`, expandable RO/Hub card UI, `IdentityConfidence` signals/facts, doctrine updates (`ark-projection-rule.mdc`, `docs/growth/DOCTRINE.md`).

**Reason:** Story is summary; evidence one click away. Operational Journey is the explainable narrative between Growth, Voice, Operations, and Finance — not magic.

**Architecture impact:** Every milestone links to immutable source authority. Operations Briefing documented as next surface (not Command Center dashboard).

**Outstanding questions:** Operations Briefing implementation. Dense operations grid when cohort data matures.

### 2026-06-29 — ARK Growth Phase 2A (Operational Journey projection)

**PR:** feat(growth): operational journey projection, identity confidence evidence, journey explorer

**Files changed:** `OperationalJourneyProjection`, `JourneyStoryComposer`, `JourneyComparisonProjection`, `JourneyExplorerProjection`, `IdentityConfidenceResolver`, identity confidence migration on `growth_sessions`, RO workspace + Customer Hub cards, `/app/growth/journey-explorer`, `OperationalJourneyTest`.

**Reason:** Projection-first Phase 2 — compose acquisition → call → estimate → decision → repair → revenue as story milestones from Growth + Operations + Voice authority. UI renders projection; it does not derive truth.

**Architecture impact:** Single composer answers the north-star question for RO workspace and Customer Hub. Identity confidence stores score + reason + evidence for debugging and future explanations. Journey Explorer is internal analytics before any Google integration.

**Outstanding questions:** Review milestone when review authority exists. Growth Command Center (Phase 3). Visitor cookie hardening for cross-session stitching.

### 2026-06-29 — ARK Growth Phase 2 (Public Surface Intelligence)

**PR:** feat(growth): public surface intelligence — server page views, content binding, session debugger

**Files changed:** `PublicGrowthSurfaceRecorder`, `RecordPublicGrowthPageView` middleware, content FK on sessions/touchpoints migration, session admin viewer (`/app/growth/sessions`), fail-safe dispatch on public surface bridge, `GROWTH_PUBLIC_SURFACE_SERVER_PAGE_VIEWS`.

**Reason:** Turn Growth from ready architecture into live acquisition truth — every public GET records a session touchpoint, content registry binds to paths, first touch immutable, last touch updates per activity.

**Architecture impact:** Server-side page views are Growth-owned (middleware, post-response, never blocks). Operations `PublicSurfaceEvent` bridge remains contract-only with try/catch on both sides. Admin session viewer is read-only debug surface.

**Outstanding questions:** Visitor cookie on public responses (cross-session stitching). Customer Growth Timeline on Customer Hub.

### 2026-06-27 — ARK Growth Phase 1.25 (operational attribution)

**PR:** (local — not yet committed)

**Files changed:** `growth_sessions`, `growth_touchpoints`, `growth_last_touches` migrations; `GrowthSessionResolver`; `PublicSurfaceActivityRecorded` + `LeadConvertedForGrowth` contracts; bridge from `PublicSurfaceEventRecorder`; `growth_session_id` on leads, conversations, repair orders; 26×16 revenue heatmap on dashboard; expanded tests.

**Reason:** Growth must prove Visitor → Lead → RO → Revenue with first-touch immutability, last-touch table, touchpoint history, and public surface → Growth events — before external analytics.

**Architecture impact:** Session is the spine. Operations dispatches three contract events only (`PublicSurfaceActivityRecorded`, `LeadConvertedForGrowth`, `RepairOrderClosedForGrowth`). First touch never overwritten; last touch in `growth_last_touches`.

**Outstanding questions:** Phase 2 — Customer Growth Timeline on Customer Hub. Visitor cookie middleware on public responses (optional hardening).

### 2026-06-27 — ARK Growth Foundation (Phase 1)

**PR:** feat(growth): introduce ARK Growth foundation (`fe507106`)

**Files changed:** `app/Ark/Growth/**`, `app/Ark/Operations/Growth/DispatchRepairOrderClosedForGrowth.php`, `config/growth.php`, `routes/growth.php`, `database/migrations/2026_06_27_120000_create_growth_foundation_tables.php`, `resources/views/growth/**`, `ArkCapability::GrowthAccess`, `GrowthServiceProvider`, `RepairOrderPosting` growth dispatch, `tests/Feature/Growth/GrowthFoundationTest.php`.

**Reason:** First-class Growth product for acquisition intelligence — SEO engine, schema registry, sitemap, redirects, content registry, event model, attribution framework, explainable health scores, integration scaffolding, Revenue Explorer — isolated from Operations via contract events.

**Architecture impact:** Growth owns public content registry, events, attribution, and owner-facing decision surfaces. Operations dispatches `RepairOrderClosedForGrowth` on paid post via `DispatchRepairOrderClosedForGrowth`. Existing `PublicSeo` remains on public surface until Growth engine cutover; `growth:sync-public-content` seeds registry from common problems.

**Outstanding questions:** Cutover public sitemap via `GROWTH_PUBLIC_SITEMAP=true`? Bridge `PublicSurfaceEvent` into Growth events without duplicating authority? Wire public pages to `SeoEngine` / `growth/partials/seo-meta`?

### 2026-06-29 — ARK Voice MediaLocator + capture visibility

**PR:** `26b6053c` ARK Voice media parity (MediaLocator, capture status, lifecycle test)

**Files changed:** `CallSessionMediaLocator`, Twilio/ArkVoice media sources, `CallSessionMediaCaptureStatus`, capture metadata migration, `ProcessAsteriskCallMediaAction` failure paths, `AsteriskCallMediaLifecycleTest`.

**Reason:** Media parity must not fail silently; operators need pending/available/failed visibility and one resolver for all media URIs.

**Architecture impact:** Playback and Whisper resolve media through `CallSessionMediaLocator` (`ark-voice://`, Twilio HTTPS). Capture status + JSON metadata stored on `call_sessions`. Integration test proves full chain through transcript job dispatch.

**Outstanding questions:** Staging validation (5 call scenarios) before production deploy — do not ship to production until floor proof.

### 2026-06-29 — Remove VVX ambient microbrowser idle screens

**PR:** (local — user request)

**Files changed:** Removed microbrowser routes/controllers/views, station ambient projection stack, Poly provisioning microbrowser/screensaver attrs, device token generation, `ShopBaseUrl::deviceScreen*`; bumped `PolyProvisionBuilder::SERIALIZATION_VERSION` to 14; updated tests.

**Reason:** Floor decision — custom VVX idle appliance adds no value over native Poly phone screens.

**Architecture impact:** VVX provisioning is SIP + shop timezone clock only. Operator continuity stays on desktop/mobile. `microbrowser_token` column retained but unused.

**Outstanding questions:** Reprovision production VVX after deploy; optional removal of Traefik `/voice/device-screen` route.

### 2026-06-29 — ARK Voice Asterisk media parity (recording + voicemail)

**PR:** (pending) ARK Voice media parity

**Files changed:** `MobileVoiceInboundDialplanSync.php`, `AsteriskVoiceGreetingSync.php`, `AsteriskVoiceRecordingStorage.php`, `CallSessionMediaReference.php`, `ProcessAsteriskCallMediaAction.php`, `AsteriskCallMediaController.php`, `ProcessAsteriskCallEventAction.php`, `CallRecordingPlayback.php`, `CallRecordingPlaybackController.php`, `CallSessionAudioFetcher.php`, `MobileVoiceAsteriskRuntimePublisher.php`, `infra/coolify/asterisk/*` (docker-compose, bridge, ensure env), `routes/web.php`, `config/telephony.php`, `docs/communications/production-voice-cutover-v1.md`, `tests/Feature/Operations/AsteriskCallMediaTest.php`.

**Reason:** PSTN cutover to Asterisk trunk dropped Twilio TwiML recording/voicemail. Lifecycle-only ingress left `recording_url` / `voicemail_url` empty on all Asterisk CallSessions.

**Architecture impact:** Asterisk dialplan now routes inbound through `[ark-inbound-router]` with business-hours check, MixMonitor on answer (when `record_inbound_calls`), and Record-based voicemail on no-answer/closed. WAV files land on shared volume; ARK ingests `ark-voice://` references on `ended` events and via `POST /voice/call-media`. Existing playback UI and Whisper pipeline are source-aware (Twilio proxy unchanged).

**Outstanding questions:** Mount `ark-voice-recordings` on production `arksms` container (`VOICE_RECORDINGS_PATH`). Rebuild/redeploy `ark-ami-bridge` image. Run mobile voice dialplan sync + bridge reload. OpenAI TTS greeting sync requires shop OpenAI key — falls back to beep-only when absent.

### 2026-07-02 — Voice recordings mount + call media reconcile

**PR:** fix(voice): mount Asterisk recordings into arksms; reconcile VM/recording ingest

**Files changed:** `ReconcileAsteriskCallMediaCommand`, `AsteriskVoiceRecordingStorage`, `infra/coolify/lugsnplugs/mount-voice-recordings-on-arksms.sh`, unit test.

**Reason:** Voicemails were captured on Asterisk (`vm-*.wav`) but advisors had no playback — app container lacked shared recordings mount; all ingest failed with "Voice recordings volume is not mounted."

**Architecture impact:** When `VOICE_ASTERISK_STATIC_DIR` is mounted, default `recordingsRoot()` probes `/var/spool/asterisk/ark-recordings`. `ark:voice:reconcile-call-media` backfills `call_sessions` from on-disk WAVs. Playback surfaces (Comms queue, history, timeline) show Voicemail/Recording links when media is available.

**Outstanding questions:** Coolify redeploy must preserve bind mount — run `mount-voice-recordings-on-arksms.sh` after app recreate. Answered-call MixMonitor recordings (`call-*.wav`) — verify on next answered inbound.


**PR:** Stinson production env lock (Coolify DB sync)

**Files changed:** `infra/coolify/stinson/*` (sync, lock, ensure, install scripts, README), `infra/backups/google/stinson/README.md`, `PhpstormProjects/stinson-api/DEPLOYMENT.md`, `stinson-api` config/controllers/views for `GOOGLE_MAPS_BROWSER_KEY`.

**Reason:** Hand-editing Stinson Coolify `.env` caused repeated production breakage (keys wiped on recreate, `HOST=` line corruption, maps/SMS vars lost). Mirror ARK SMS: secrets on runtime host, authoritative env in Coolify DB, cron sync from control plane.

**Architecture impact:** `/data/stinson-shared/secrets/stinson-production.env` + `stinson-sync-production-env.sh` on `203.0.113.20` for app `jc91buansvfy5hogxlgxh6dh`. Split Google keys: server IP-restricted vs browser website-restricted. Installed on production + control plane; initial sync deployed.

**Outstanding questions:** Add `TWILIO_*` to secrets file (currently missing from Coolify DB — SMS off until restored). Commit stinson-api split-key changes to git if not already on deploy branch.

### 2026-06-29 — VVX350 ambient instrument cluster (mockup renderer)

**PR:** Build VVX350 ambient instrument cluster from approved mockup

**Files changed:** `StationAmbientScreenType`, `StationAmbientPriorityBand`, `StationAttentionEngine` (structured `attention.screen`), `StationContinuityObservationScope`, `StationContinuityProjection` (duplicate import fix), `device-appliance.blade.php`, unit + feature tests.

**Reason:** Right Counter VVX must read as a station instrument cluster from across the counter — one priority-owned condition, dark 320×160 layout, color-coded urgency — not a tiny ARK dashboard. Production 500 from duplicate `use` import blocked all polls until fixed.

**Architecture impact:** `attention.screen` packages type, band, accent, layout, copy, and footer action for any station renderer; VVX Blade is first consumer. Priority: urgent → attention → info → ready; oldest unresolved within band.

**Outstanding questions:** Floor verify on ext 101 after NOTIFY; tune idle vs alert copy when shop has zero open ROs.

### 2026-06-28 — Station attention engine (VVX operational instrument)

**PR:** VVX ambient appliance — attention-owned display

**Files changed:** `StationAttentionEngine`, `StationAttentionContext/State/Tier`, `ShopAmbientPulseProjection`, `StationContinuityProjection`, `device-appliance.blade.php`, `Workstation`, tests; removed carousel `StationIdleScanProjection` / instrument path.

**Reason:** Timer-driven slides are a kiosk, not a shop instrument. Display must answer one question: what needs attention at this station now? Priority stack replaces rotation; flash + memory for payments; operational language instead of "Ready."

**Architecture impact:** `StationAttentionEngine → attention payload → renderer`. Same decision can drive VVX, wallboards, tablets later. Observations + calls + approvals compete by personality-specific priority; one posture owns screen until state changes.

**Outstanding questions:** Floor verify interrupt hold vs resolve on ext 101; payment memory 5min window observation on production.

### 2026-06-28 — VVX idle scan projection (structured counter rhythm)

**PR:** Station appliance polish (VVX Right Counter)

**Files changed:** `StationIdleScanProjection.php`, `StationContinuityProjection.php`, `device-appliance.blade.php`, `StationIdleScanProjectionTest.php`, `CommunicationDeviceMicrobrowserContinuityTest.php`

**Reason:** Three ad-hoc pulse strings (`pulse`, `pulse_detail`, `pulse_context`) read like debug fragments on a 4-line phone; doctrine calls for one scannable counter rhythm (READY/ATTENTION → approvals → arrival → shop load → AVAILABLE).

**Architecture impact:** `StationIdleScanProjection` packages scan lines with styles; continuity projection composes once; Blade/JSON render `scan_lines`. Next-arrival resolves via hydrated Carbon comparison (SQLite shop-local `starts_at`) and `appointments_enabled` gate. Arrival display uses `ShopDisplayTimezone::format()`.

**Outstanding questions:** Floor verify on ext 101 after deploy; appointment UTC vs shop-local storage remains a broader appointments authority question.

### 2026-06-28 — Mobile customer workspace depth: money, open work, quick actions

**PR:** ARK Staff full app buildout (mobile)

**Files changed:** arksmsv2: `MobileCustomerWorkspaceProjection`, `MobileCustomerWorkspaceLayoutEngine`, `MobileCustomerMessageStoreController`, `routes/api.php`, `tests/Feature/Mobile/MobileApiTest.php` (+2). ark-mobile: `customer_workspace.dart`, `customer_workspace_screen.dart`, `customer_workspace_blocks.dart`, `mobile_api.dart`, `intake_repository.dart`.

**Reason:** Customer Hub on phone stopped at orientation blocks — advisors still opened desktop for estimate totals, multiple open ROs, send estimate/payment, and first text to customers with no thread.

**Architecture impact:** Money via existing `MobileEstimateProjection` (advisor profile only; technicians 403 on workspace). `quick_actions` and `open_work` blocks are server projections. First SMS uses `POST /customers/{id}/messages` → `SendOutboundMessageAction` (write path; no conversation on GET).

**Outstanding questions:** Production smoke on a customer with two open ROs; Edward APK install.

### 2026-06-28 — Mobile vehicle workspace API + live service history

**PR:** ARK Staff full app buildout (mobile)

**Files changed:** arksmsv2: `MobileVehicleWorkspaceProjection`, `MobileVehicleWorkspaceController`, `MobileStaffAccess::canViewVehicle`, `RepairOrderWorkspaceProjection` (`vehicle_id` in header), `routes/api.php`, `tests/Feature/Mobile/MobileApiTest.php` (+1). ark-mobile: `vehicle_workspace.dart`, `vehicle_workspace_screen.dart` (API-driven), RO/customer vehicle navigation via `vehicle_id`.

**Reason:** Vehicle screen was placeholder-only; advisors opening a vehicle from Customer Hub or RO chips saw static copy instead of open work, history, and deferred concerns.

**Architecture impact:** Vehicle workspace mirrors customer pattern — open ROs + closed service history + deferred concerns from authority; money advisor-only. Technicians see assigned ROs only via `canViewVehicle`.

**Outstanding questions:** Production deploy; APK install; CheckIn vehicle preselect from vehicle workspace.

### 2026-06-28 — Mobile RO: edit estimate lines + FilledButton theme fix

**PR:** ARK Staff full app buildout (mobile)

**Files changed:** arksmsv2: `UpdateRepairOrderLine`, `MobileRepairOrderLineUpdateController`, `MobileEstimateProjection` (`can_edit`, `edit_inputs`), `routes/api.php`, `tests/Feature/Mobile/MobileApiTest.php` (+2), `docs/mobile/ark-mobile-ro-audit-v1.md`. ark-mobile: tap-to-edit estimate rows, `_AddEstimateLineDialog` edit mode, `updateEstimateLine` API, `ark_theme.dart` FilledButton min size fix.

**Reason:** Add/delete closed most estimate building on phone; advisors still needed desktop to change hours, description, or part cost after a line exists.

**Architecture impact:** Shared `UpdateRepairOrderLine` mirrors store/destroy (pricing matrix, totals recalc, RTE labor override observation). Mobile sends `edit_inputs` for form prefill only — never derives sell price client-side.

**Outstanding questions:** APK install; production smoke editing labor hours on an open RO.

### 2026-06-28 — Mobile RO workspace polish: overview landing, concern subtotals, timeline rhythm

**PR:** ARK Staff full app buildout (mobile)

**Files changed:** arksmsv2: `RepairOrderWorkspaceProjection` (default Overview), `MobileEstimateProjection` / `MobileRepairOrderProjection` (`subtotal_label` on concern cards), `tests/Feature/Mobile/MobileApiTest.php`, `docs/mobile/ark-mobile-ro-audit-v1.md`. ark-mobile: `my_work_screen.dart` (stop auto-opening concern detail), `workspace_overview_intelligence.dart` (day-grouped timeline), `repair_order_workspace_screen.dart` (concern subtotal on tiles).

**Reason:** Audit follow-ups after estimate line entry — inconsistent RO entry from Work list, information-poor concern cards, and dense timeline scan rhythm.

**Architecture impact:** Projection-only reads for concern subtotals via `EstimateTotalsCalculator`; default section stays orienting while NEXT banner retains routing intent. No new authority.

**Outstanding questions:** APK install with estimate lines + polish; production smoke.

### 2026-06-28 — Mobile RO: add and delete estimate lines from the phone

**PR:** ARK Staff full app buildout (mobile)

**Files changed:** arksmsv2: `StoreRepairOrderLine`, `DestroyRepairOrderLine`, `MobileRepairOrderLineStoreController`, `MobileRepairOrderLineDestroyController`, `MobileEstimateProjection` (`estimate_editing`, line `can_delete`), `MobileRepairOrderProjection`, `RepairOrderWorkspaceProjection` (`add_estimate_line` command), `routes/api.php`, `tests/Feature/Mobile/MobileApiTest.php` (+5), `docs/mobile/ark-mobile-ro-audit-v1.md`. ark-mobile: `mobile_api.dart`, `work_repository.dart`, `repair_order_command_bar.dart`, `repair_order_workspace_screen.dart` (add/delete line UI).

**Reason:** Advisors could view estimate totals and send estimate links on the phone but could not add labor/parts lines — the largest remaining desktop trip in the RO audit.

**Architecture impact:** Writes delegate to shared `StoreRepairOrderLine` / `DestroyRepairOrderLine` (same pricing, lifecycle Draft→Estimate, events, and `EstimateTotalsCalculator` recalc as desktop). Mobile v1 limits types to labor + part; technicians gated out. Flutter never computes money — only renders server labels and posts authoritative fields.

**Outstanding questions:** Production smoke on Alex Rivera RO; APK rebuild/install.

### 2026-06-28 — Mobile RO: add and remove concerns from the phone

**PR:** ARK Staff full app buildout (mobile)

**Files changed:** arksmsv2: `MobileConcernStoreController`, `MobileConcernDestroyController`, `routes/api.php`, `MobileConcernProjection` (`scope_management`), `RepairOrderWorkspaceProjection` (`add_concern` command), `tests/Feature/Mobile/MobileApiTest.php` (+5), `docs/mobile/ark-mobile-ro-audit-v1.md`. ark-mobile: `api_client.dart` (`deleteJson`), `mobile_api.dart`, `work_repository.dart`, `repair_order_command_bar.dart`, `repair_order_workspace_screen.dart`, `concern_detail_screen.dart` (`_ScopeDeleteControl`).

**Reason:** Advisors could update concern inspection fields and dispositions on the phone but could not add a new concern to an open RO or remove an empty one — forcing a desktop trip for basic scope entry after intake.

**Architecture impact:** Writes reuse desktop authority paths (`RepairOrderConcern` create/delete, estimate mutation recording, concurrency guard, same delete-when-lines-exist rule). Read path adds `scope_management` on concern detail and `add_concern` on the advisor command bar. Technicians gated out (`RepairOrdersManage` / disposition permission family).

**Outstanding questions:** Backend Pest; Flutter analyze; APK install on device; production smoke against Alex Rivera RO.

### 2026-06-28 — Mobile RO: assign technician from the command bar

**PR:** ARK Staff full app buildout (mobile)

**Files changed:** arksmsv2: `app/Ark/Mobile/RepairOrderWorkspaceProjection.php` (`technician_assignment` projection + `assign_technician` command for advisor/owner profiles; reuses `SoloShopOperations::assignableTechnicians()`), `tests/Feature/Mobile/MobileApiTest.php` (+2 tests, command_bar assertion), `docs/mobile/ark-mobile-ro-audit-v1.md`. ark-mobile: `lib/models/repair_order_workspace.dart` (`RepairOrderTechnicianAssignment`), `lib/repositories/work_repository.dart` (`assignTechnician`), `lib/widgets/repair_order_command_bar.dart` (icon), `lib/screens/repair_order_workspace_screen.dart` (bottom-sheet picker + PATCH).

**Reason:** Advisors could assign a technician from the Work list or desktop RO, but not from the RO workspace command bar — a small desktop trip on every assignment change. The phone now offers Assign/Reassign tech beside Message and Send Estimate, using the same assignable list and `AssignRepairOrderTechnician` authority as intake.

**Architecture impact:** Projection-only on the read path; write reuses existing mobile PATCH `/api/mobile/repair-orders/{ro}/technician-assignment`. Technicians never see the control (`canPerformIntake` gate). No new settings or parallel assignment store.

**Outstanding questions:** Backend Pest for new workspace tests; Flutter analyze; on-device verify picker + header refresh against Alex Rivera production RO.

### 2026-06-28 — Solo / mobile-solo: auto-assign the lone operator as technician

**PR:** ARK Staff full app buildout (mobile)

**Files changed:** `app/Ark/Operations/Staff/SoloShopOperations.php` (`soleStaffUser()` / `isSingleUserShop()` — exactly one active staff user; `canAssignAsTechnician()` and `assignableTechnicians()` now treat that sole user as the technician regardless of role), `app/Ark/Operations/RepairOrders/AssignRepairOrderTechnician.php` (private `applyAssignment()`; new `assignSoleStaffUserIfApplicable()` records `auto_assigned: true`), `app/Ark/Operations/Intake/AdvisorIntakeService.php` (auto-assign on create when no technician chosen), `app/Ark/Operations/RepairOrders/RepairOrderDraftStoreController.php` (same, customer-hub draft path), tests: `tests/Feature/Operations/SoloShopOperationsTest.php` (+4), `tests/Feature/Operations/AdvisorIntakeTest.php` (+2).

**Reason:** A solo or mobile-solo operator is the advisor *and* the technician. Requiring them to hunt for a "default technician" setting — or leaving every RO "Unassigned tech" — is exactly the forgotten-configuration trap. The lone operator should never have to declare what reality already states.

**Architecture impact:** Authority vs Configuration doctrine — assignment is **derived from authority** (there is exactly one active staff user) rather than a stored default that drifts. No new column, no setting, no shop-slug branching. Auto-assignment routes through the existing `AssignRepairOrderTechnician` write/event path (`auto_assigned` provenance in the event payload). Deliberately scoped to *exactly one* staff user: the moment a second staff member exists, "the individual" is ambiguous and a human must choose, so auto-assignment stops. Advisor identity needs no change — it already derives from the estimate document's acting user, which in a single-user shop is always the lone operator. Mobile is covered because `MobileIntakeStoreController` shares `AdvisorIntakeService`.

**Outstanding questions:** Backend verified via Pest (SoloShopOperations 8/8, intake auto-assign 2/2). Pre-existing intra-file pollution in `RepairOrderLifecycleQueueTest` (8/19 fail as a whole, pass in isolation) is unrelated — identical with these changes stashed. On-device verification against Alex Rivera (production, single-user) pending.

### 2026-06-28 — Mobile RO: in-place lifecycle transitions + close-out (audit item 6)

**PR:** ARK Staff full app buildout (mobile)

**Files changed:** arksmsv2: `app/Ark/Mobile/MobileStaffAccess.php` (`canChangeRepairOrderLifecycle` — advisor/owner via `RepairOrdersManage`), `app/Ark/Mobile/RepairOrderWorkspaceProjection.php` (inject `RepairOrderStatusCatalog`; new `lifecycleControl()` builds current status + allowed moves + close variants + lost reasons from `RepairOrderLifecycleSelectProjection`; added `lifecycle` to workspace payload), `app/Ark/Mobile/Http/MobileRepairOrderLifecycleController.php` (new — PATCH endpoint reusing `RepairOrderLifecycleTransition`), `routes/api.php` (`PATCH /api/mobile/repair-orders/{ro}/status`), `tests/Feature/Mobile/MobileApiTest.php` (5 lifecycle tests), `docs/mobile/ark-mobile-ro-audit-v1.md` (gap matrix). ark-mobile: `lib/api/mobile_api.dart` + `lib/repositories/work_repository.dart` (`changeRepairOrderStatus`), `lib/models/repair_order_workspace.dart` (`RepairOrderLifecycleControl`/`Option`/`LostReason` models + parse), `lib/widgets/repair_order_lifecycle_control.dart` (new — current status card, move bottom sheet, lost-reason dialog), `lib/screens/repair_order_workspace_screen.dart` (render control atop Overview).

**Reason:** RO audit item 6 (`docs/mobile/ark-mobile-ro-audit-v1.md`) — "Change RO status / lifecycle transition" and "Close / invoice the RO" were desktop-only, the last advisor lifecycle steps forcing a browser after approvals shipped. The phone now moves the RO forward/back and closes it (Paid / Lost with required reason) in place.

**Architecture impact:** No new authority — the mobile controller and projection reuse the same `RepairOrderLifecycleTransition` (blocking reasons, final invoice issue, posting, lifecycle event) and `RepairOrderLifecycleSelectProjection` the desktop toolbar uses, so the phone can never offer a move the desktop would reject. Server stays authoritative; financial/invoice math unchanged. Scope decision: mobile lifecycle is advisor/owner-only (`RepairOrdersManage`); technicians keep their focused concern production-status flow rather than RO-level lifecycle (technician-scope doctrine) even though desktop grants them `RepairOrdersLifecycle` for production moves.

**Outstanding questions:** Backend verified via Pest (49/49 mobile tests green incl. 5 new); Flutter via `flutter analyze` (clean). On-device verification against Alex Rivera (production) pending. Remaining audit gap: in-person payment capture (Square Terminal / card-present — product decision) and estimate building on the phone.

### 2026-06-28 — VVX idle appliance polish: ambient tone, station label, faster refresh

**PR:** VVX Front Counter idle appliance polish

**Files changed:** `StationAmbientTone.php`, `StationContinuityProjection.php`, `Workstation.php` (appliance display name from extension), `device-appliance.blade.php`, `CommunicationDeviceMicrobrowserController.php` (HTTP posture/screen URLs), `ShopBaseUrl.php`, `StationPosture.php` (poll intervals), `PolyPhoneProvDeviceConfigBuilder.php` (`refresh=2`), `PolyProvisionBuilder::SERIALIZATION_VERSION=11`, `apply-microbrowser-http-route.sh` (Traefik `@docker` backend), tests.

**Reason:** Floor feedback — idle screen was mid: wrong station short name, first-name-only operator, static dark box. Added dynamic green/amber/red ambient backgrounds from calls waiting, approvals, and front-counter observation pressure; full extension display name + ext; consolidated pulse line; faster Poly/JS refresh.

**Architecture impact:** Station continuity projection only — `ambient_tone`/`ambient_bg` packaged with pulse and station_label. No VVX-specific rules in tone logic. Traefik HTTP route survives container recreate via Coolify docker service reference.

**Outstanding questions:** Deploy + reprovision (serialization v11); re-run Traefik script if HTTP 502 after deploy; floor tune `StationAmbientTone` thresholds if amber too eager.

### 2026-06-28 — VIN decode: OCR speed, editable trim/engine, provider diagnostics

**PR:** ARK Staff full app buildout (mobile)

**Files changed:** ark-mobile: `lib/services/vin_ocr_image.dart` (prep runs in a background isolate via `compute`; downscale to ≤2000px long edge instead of upscaling to 4096; 2 variants instead of 4; JPEG quality 85), `lib/screens/vin_scan_screen.dart` (cap capture at 2400px / quality 85), `lib/screens/check_in_screen.dart` (editable Trim + Engine fields; decode pre-fills them; draft merges operator edits over decode-only detail), `lib/models/decoded_vehicle.dart` (`manual` factory accepts trim/engine; trim shown in detail lines). arksmsv2: `app/Ark/Vehicles/VehicleIntelligenceManager.php` (debug log per provider — name/usable/source).

**Reason:** Operator feedback — (1) VIN OCR took ~20s: root cause was pure-Dart `image` ops (4096px upscale + 4 quality-98 JPEG encodes + 4 barcode + 4 OCR passes) on the UI isolate over a full 12MP capture. (2) Trim/Engine were decoded by NHTSA and shown read-only but could not be captured or corrected (manual entry dropped them entirely). (3) "Why NHTSA only?" — PartsTech is already a registered provider that runs first and merges, but no-ops without credentials; added diagnostics so the active provider is visible once credentials are entered in Settings.

**Architecture impact:** No backend behavior change (logging only); PartsTech↔NHTSA merge architecture already existed (`VehicleIntelligenceManager`, `AppServiceProvider` binding). Mobile changes are presentation/capture-layer only. No new authority, schema, or dependency.

**Outstanding questions:** OCR speed-up verified by reasoning + `flutter analyze` (clean); not yet timed on a physical device — recommend an on-device check that the ~20s → ~1–2s holds and that downscaling to 2000px still decodes the door-jamb pdf417 barcode reliably. PartsTech decode endpoint (`GET /api/v2/vehicles/decode`) is assumed by `PartsTechProvider` and should be confirmed against live credentials.

### 2026-06-28 — Mobile design system: tokens, ArkCard, global a11y/touch targets

**PR:** ARK Staff full app buildout (mobile)

**Files changed:** ark-mobile: `lib/theme/ark_theme.dart` (spacing/radius/touch tokens; `onSurfaceVariant` muted tier raised to slate-600 / light-slate for sunlight contrast; global component themes give every button + chip + icon-button class a 48px minimum target with `MaterialTapTargetSize.padded`), `lib/widgets/ark_card.dart` (new — `ArkCard` + `ArkSection` shared primitives), `lib/screens/my_work_screen.dart` (Work cards adopt `ArkCard`; attention rows use the emphasized accent border).

**Reason:** Continuing the UX audit redesign order (`docs/mobile/ark-mobile-ux-audit-v1.md`). Rather than restyle screens one by one, fix the system: SYS-3 (one spacing/type/touch scale), SYS-4 (one card language), and the Outdoor Usability gate (gloved, one-handed, bright light → 48px targets + contrast-safe muted text). Theme-level changes propagate to every screen without per-screen churn; Work cards are the first `ArkCard` consumer.

**Architecture impact:** Establishes shared mobile design tokens + primitives as the substrate future screens consume. No backend change, no new dependency, no new authority. Pure presentation-layer consolidation consistent with ark-cursor-doctrine (calm, dense, review-first).

**Outstanding questions:** Remaining audit items — context-aware command bar (SYS-5) and broad `ArkCard`/`ArkSection` adoption across Intake, Customer, and concern surfaces — still pending. Verified via `flutter analyze` (clean); local `flutter run -d web-server` again failed to bind the port (recurring tooling hang), so an on-device visual pass is still recommended before further visual tuning.

### 2026-06-28 — Mobile UX audit + navigation-shell fixes (stacked back buttons, customer route)

**PR:** ARK Staff full app buildout (mobile)

**Files changed:** arksmsv2: `routes/api.php` (register `GET /api/mobile/customers/{customer}` → `MobileCustomerWorkspaceController`), `docs/mobile/ark-mobile-ux-audit-v1.md` (new — design + a11y audit). ark-mobile: `lib/screens/concern_detail_screen.dart` (`embedded` mode, de-duplicated/normal-case concern header, inline Finding), `lib/screens/repair_order_workspace_screen.dart` (pass `embedded: true` to concern + conversation, single back path, persistent compact identity while drilled in).

**Reason:** Edward asked for a product-design audit instead of more features — the app "has two back buttons stacked, big-and-small layout." Audited the live app as an operator and clustered ~30 complaints into 6 design-system root causes. Then fixed the highest-friction ones: (1) P0 — Customer workspace returned `route api/mobile/customers/{id} could not be found`; the controller existed but was never routed (app calls `/customers/{id}`, only `/intake/customers/{id}` existed). (2) SYS-1 — the workspace embedded `ConcernDetailScreen`/`CommunicationThreadScreen` which each carried their own `Scaffold`/`AppBar`, stacking AppBars/back buttons; sub-views now render body-only via `embedded`, and the three back affordances collapse to one. (3) SYS-2 — identity persists (compact header) while inside a concern/conversation. (4) SYS-6 — concern title was all-caps and restated verbatim below; now normal-case with the duplicate block suppressed.

**Architecture impact:** Establishes the direction for a single navigation shell (sub-views are bodies, not screens) without a framework change — consistent with ark-cursor-doctrine (review-first, calm, scoped continuity). No new authority, no new dependency. Backend change is a single route registration; no schema change.

**Outstanding questions:** Full design-system pass (one `ArkCard`/spacing+type ramp, context-aware command bar, a11y tokens — 48px targets, contrast tier, verb+object labels, textScaler verification) is documented in the audit (§7–§10) and proposed as the redesign order; not yet implemented. Local `flutter run -d web-server` hung on debug-service connect during verification — changes verified via `flutter analyze` (clean) and `route:list`; recommend an on-device visual pass.

### 2026-06-28 — ARKademy linked in the mobile Apps launcher (Level 1)

**PR:** ARK Staff full app buildout (mobile)

**Files changed:** arksmsv2: `app/Ark/Mobile/MobileUserPresenter.php` (shell `learning` block), `tests/Feature/Mobile/MobileApiTest.php`. ark-mobile: `lib/models/mobile_shell.dart` (`MobileLearning`), `lib/screens/apps_screen.dart` (ARKademy tile + browser launch).

**Reason:** Edward asked to link ARKademy in the app, sketching five levels (launcher link → context-aware learning → deep links → observation-tied → continuity surface). Per ark-earned-intelligence ("what repeated sentence earned it?"), we ship Level 1 only: an ARKademy tile in the Apps launcher that opens the existing BookStack instance in the device browser (reusing any SSO session). Access + URL reuse `EcosystemSwitcherProjection` so mobile and web agree on who sees ARKademy and where it lives (per-shop configuration, not a hardcoded LugsNPlugs URL). Levels 2–5 are deferred until we observe operators wanting learning in context.

**Architecture impact:** Mobile consumes ARKademy; it does not rebuild BookStack (ark-dependency-policy — no webview package added, `url_launcher` already present). No new authority. `learning.arkademy_enabled` derives from OIDC product access (admin/advisor/technician get it by default).

**Outstanding questions:** In-app webview with token-passing SSO is a deliberate Level-2 follow-up if the external-browser hop proves friction. Context-aware learning (vehicle/concern/system → relevant articles) needs the observation→article mapping, which should be earned, not pre-built.

### 2026-06-28 — In-app light/dark (per-device), accent stays account-set

**PR:** ARK Staff full app buildout (mobile)

**Files changed:** ark-mobile: `lib/services/display_mode_store.dart` (new), `lib/providers/display_mode_provider.dart` (new), `lib/app.dart`, `lib/screens/apps_screen.dart`.

**Reason:** Edward noted light/dark is a phone/context preference, not an account one (some operators run their phone on auto/scheduled). Accent color remains an account identity set on the web; light/dark is now chosen in the app (Profile → Appearance: Auto / Light / Dark) and persisted per-device via secure storage, defaulting to System (follows the phone, including auto). `app.dart` now drives `themeMode` from the device-local `displayModeProvider` instead of the shell's `display_mode`.

**Architecture impact:** Splits the two preferences by their natural owner — account vs device. Backend still emits `theme.display_mode` (unused by mobile now; harmless, documents the contract and keeps web parity). No backend deploy required for this change.

**Outstanding questions:** Web `display_theme` and the app's device light/dark are now independent by design; if a shop ever wants the account default to seed first-run on a new device, that would be a deliberate follow-up.

### 2026-06-28 — Per-operator accent color applied in mobile app

**PR:** ARK Staff full app buildout (mobile)

**Files changed:** arksmsv2: `app/Models/User.php` (added `accentHexResolved()`, `presenceAvatarColor()` now delegates), `app/Ark/Mobile/MobileUserPresenter.php` (shell `theme` block), `tests/Feature/Mobile/MobileApiTest.php`. ark-mobile: `lib/models/mobile_shell.dart` (`MobileTheme`), `lib/theme/ark_theme.dart` (accent param + `colorFromHex`), `lib/app.dart` (apply accent + display mode).

**Reason:** Edward asked whether the app can use the operator's chosen theme color ("I like ARK blue, my wife likes pink"). The web already stores per-user `accent_theme`/`accent_color`/`display_theme` (Authority-vs-Configuration: a pure preference that points at no authority); it was simply never surfaced to mobile. The shell payload now carries the resolved accent hex + display mode, and the Flutter theme seeds `primary`, filled buttons, and the nav indicator from it. ARK cerulean / system remain the default until the shell loads.

**Architecture impact:** No new authority, no migration, no new endpoint. `accentHexResolved()` is now the single source of truth for the operator's effective accent (web data-accent, avatars, mobile theme). Tone colors on the moment feed are intentionally NOT accent-driven — they remain semantic (urgent/waiting/positive/info).

**Outstanding questions:** No in-app picker yet — operators set the accent on the web. Live pink demo requires either a deploy or pointing the dev app at a local backend (current dev app targets production, which lacks the `theme` payload). Optional follow-up: a mobile Appearance picker (needs a `PATCH /mobile/me` preferences endpoint).

### 2026-06-28 — Mobile Home collapsed to one dense triage feed

**PR:** ARK Staff full app buildout (mobile)

**Files changed:** ark-mobile: `lib/screens/orientation_home_screen.dart`, `lib/widgets/operational_moment.dart`

**Reason:** Home was a long scroll through seven stacked "split areas" (station header, moment feed, Next Best Action card, Today placeholder, Quick-actions grid, Active ROs, Recent customers). This read as low-signal SaaS whitespace and undermined trust. Per ark-cursor-doctrine ("prefer one operational workspace with sectional rhythm over stacked SaaS widgets") and Edward's directive ("Home should open on a feed of operational moments — not charts, not KPIs"), Home is now compact identity + status + the moment feed only. Action shortcuts (New intake, Scan VIN, Find customer) moved into the FAB. `MomentTile` tightened ~20% (avatar 36→30, padding 12→9, single-line subtitle).

**Architecture impact:** No authority/projection change — purely surface composition. Removed widgets (`_NextBestActionCard`, `_TodaySection`, `_QuickActionsSection`, `_RecentWorkSection`, `_RecentCustomersSection`) and their now-unused imports. Recent work lives on the Work tab; customers via search. Moment feed remains the single home projection consumer.

**Outstanding questions:** Backend orientation/decision-pressure rows still arrive with `tone: 'info'` and no `observation`, so they render as the default gray bell and never reach the "Needs action now" group. Next: have `MobileOrientationProjection` emit an explainable tone (urgent/waiting/positive/info) + observation per pressure row so color + grouping light up.

### 2026-06-27 — Firebase mobile push production + setup doctrine

**PR:** Firebase transport enablement (local)

**Files changed:** `infra/scripts/firebase-mobile-push-setup.sh`, `docs/mobile/firebase-mobile-push-setup-doctrine-v1.md`, `docs/mobile/firebase-push-setup-checklist.md`, `docs/mobile/ark-mobile-notification-doctrine.md`, `.cursor/rules/ark-firebase-mobile-push-setup.mdc`, `.cursor/rules/ark-mobile-notification-doctrine.mdc`, `resources/views/operations/learn/admin/ark-mobile-push-setup.blade.php`, `docs/product/certifications/portable-station-phase-1.md`; ark-mobile: `docs/firebase-transport-only.md`

**Reason:** Enable LugsNPlugs production FCM transport after Portable Station Phase 1; document repeatable setup doctrine and fix script `ark-mobile` path resolution.

**Architecture impact:** Push remains transport-only; credentials encrypted in `shop_settings` with optional mounted file fallback. One Firebase project (`lugsnplugs-ark-mobile`) for the single App Store binary. No Firebase authority leakage.

**Outstanding questions:** APNs `.p8` upload for iOS; floor verify 8:10 AM push → conversation on device.

### 2026-06-27 — Portable Station Phase 1 shipped (engineering)

**PR:** Portable Station Phase 1

**Files changed:** `app/Ark/Mobile/MobileOrientationProjection.php`, `MobileOrientationController.php`, `MobileConversationMarkReadController.php`, `MobileConversationAttachmentShowController.php`, `ConversationProjection.php`, `ConversationMessageEventMapper.php`, `routes/api.php`, `tests/Feature/Mobile/MobileApiTest.php`, `docs/product/certifications/portable-station-phase-1.md`; ark-mobile: orientation home, mark read, MMS, push stub

**Reason:** Ship Track B engineering for advisor 7:55/8:10 scenarios — orientation home API, mark read, MMS URLs, Flutter default tab.

**Architecture impact:** Orientation composes existing Attention/Work projections; mark read reuses `ConversationReadTracker`; mobile attachment route is Sanctum-gated read path only.

**Outstanding questions:** Firebase `google-services.json` for operational push cert; floor 8:10 AM scenario on Edward's device.

### 2026-06-27 — Mobile RO workspace profile for multi-role operators

**PR:** RO1578 mobile workspace fix

**Files changed:** `app/Ark/Mobile/MobileUserPresenter.php`, `MobileRepairOrderRouteId.php`, `RepairOrderWorkspaceProjection.php`, `RepairOrderWorkspaceIntelligenceProjection.php`, `MobileAttentionProjection.php`, `tests/Feature/Mobile/MobileApiTest.php`

**Reason:** Operators with admin+advisor+technician roles were routed into technician inspection workspace on estimate ROs (e.g. RO1578), defaulting to inspection instead of advisor estimate review.

**Architecture impact:** RO workspace profile now follows shell operational profile (`manager` → `advisor`); mobile `repair_order_id` payloads normalize to shop numbers for deep links.

**Outstanding questions:** None.

### 2026-06-27 — Front Counter: ARK Voice health UI + operational certification

**PR:** Front Counter wrap-up (uncommitted)

**Files changed:** `app/Ark/Operations/Telephony/TelephonyHealth.php`, `app/Ark/Operations/Communications/CommunicationsShopProjection.php`, `resources/views/operations/settings/partials/communications-general-overview.blade.php`, `resources/views/operations/settings/partials/telephony-settings.blade.php`, `tests/Feature/Operations/TelephonyHealthSettingsTest.php`, `docs/product/certifications/front-counter.md`, `docs/product/certifications/voice-transport.md`, `docs/product/certifications/README.md`, `docs/engineering/operator-steps-removed.md`, `docs/engineering/ACTIVE_PR.md`

**Reason:** Voice cutover to Asterisk trunk is live on production; Settings and Shop → Communications still showed Twilio webhook posture. Align health projection and settings UI with ARK Voice when `telephony_provider=asterisk`. File Engineering + Operational certification records from Jun 27 floor evidence.

**Architecture impact:** TelephonyHealth gains provider-aware voice ingress/signal methods; no authority change. CommunicationsShopProjection health rows use Asterisk ingress when primary. Operational notes skip legacy Twilio voice webhook warnings on Asterisk path.

**Outstanding questions:** Production Certified (one week trunk observation) not yet earned. Deploy app changes so production Settings UI matches floor reality. Portable Station Phase 1 remains parked.

### 2026-06-27 — Product identity + Cursor agent brief (framework closed)

**Files changed:** `docs/ecosystem/ark-constitution-v1.md`, `docs/product/cursor-agent-brief.md`, `docs/product/operational-certifications.md`, `.cursor/rules/ark-operational-certification.mdc`

**Reason:** Lock product identity sentence in Constitution. One-page agent brief: five questions before code, floor priorities, frozen principles. Framework closed — earn certifications on the floor.

**Architecture impact:** None.

**Outstanding questions:** None. Next work is floor certification (VVX, Front Counter, Portable Station push, first ARK call).

### 2026-06-27 — Product: Operational certifications v3 (frozen — dependency chain)

**Files changed:** `docs/product/operational-certifications.md`, `docs/product/certifications/_template.md`, `docs/product/certifications/README.md`, `.cursor/rules/ark-operational-certification.mdc`

**Reason:** Final governance refinements. PASS levels are a dependency chain (Engineering break suspends Operational/Production). Split evidence (what happened) vs proof (verifiable artifacts). Add "why this matters" per cert. Coarse catalog only (~9). Framework marked complete — govern decisions, do not expand.

**Architecture impact:** None.

**Outstanding questions:** None for framework. Next action is floor certification, not more process docs.

### 2026-06-27 — Product: Operational certifications v2 (capability/operational + PASS levels)

**Files changed:** `docs/product/operational-certifications.md`, `docs/product/certifications/README.md`, `docs/product/certifications/_template.md`, `.cursor/rules/ark-operational-certification.mdc`, `docs/product/day-in-the-life/README.md`, `docs/product/orientation-platform.md`

**Reason:** Split each certification into Capability vs Operational layers; add three PASS levels (Engineering → Operational → Production); historical record template (owner, date, evidence); release-notes format. Backlog filter unchanged: which certification gets closer to PASS?

**Architecture impact:** No runtime change. Engineering may sign Engineering Certified without claiming Operational.

**Outstanding questions:** First Engineering record to file; first Operational sign-off likely Portable Station Phase 1 or Front Counter capability rows.

### 2026-06-27 — Product: Operational certifications framework

**Files changed:** `docs/product/operational-certifications.md`, `.cursor/rules/ark-operational-certification.mdc`, `docs/product/orientation-platform.md`, `docs/product/day-in-the-life/README.md`

**Reason:** Replace sprint language with floor-verifiable certification milestones (Front Counter Certified, Portable Station Phase 1, etc.). Add fourth platform rule: every capability must be certifiable by completing real shop work. Tie certifications to day-in-the-life scenarios as regression suites.

**Architecture impact:** No runtime change. Product progress measure: certifications PASS on floor, not feature checklist.

**Outstanding questions:** First target cert to close: Portable Station Phase 1 (8:10 AM scenario) or Front Counter Certified — shop priority.

### 2026-06-27 — Product: Orientation Platform contract + day-in-the-life scenarios

**Files changed:** `docs/product/orientation-platform.md`, `docs/product/day-in-the-life/README.md`, `docs/product/day-in-the-life/advisor.md`, `docs/product/day-in-the-life/technician.md`, `docs/product/day-in-the-life/owner.md`, `docs/product/day-in-the-life/parts.md`, `.cursor/rules/ark-orientation-pattern.mdc`

**Reason:** Lock shared contract across three parallel tracks (Operations Platform, Portable Station, Orientation Platform). Reframe Orientation as platform service — not a shippable product. Freeze payload fields and verb order. Replace user-story thinking with operational day scenarios as cross-surface acceptance tests.

**Architecture impact:** No runtime change. Product gate for all briefing surfaces: consume Orientation Platform contract; measure progress by scenarios completed (see advisor checklist). Track B (Portable Station) defaults should follow Edward's day, not inbox-first nav.

**Outstanding questions:** Normalize `OperationalOrientation` field names to contract at API boundary; `GET /api/mobile/orientation` for Portable Station home; first scenario to close end-to-end: 8:10 AM customer text.

### 2026-06-27 — Doctrine: Station product compass

**Files changed:** `.cursor/rules/ark-station-doctrine.mdc`

**Reason:** Lock product doctrine for Communications certification — Stations (not phones/extensions) as operator vocabulary; transport as implementation detail; ARK owns experience, providers own PSTN/SMS plumbing.

**Architecture impact:** No code authority change. Documents that `Workstation` is current code name for **Station**; `CommunicationDevice` is infrastructure evidence, not primary product object. Reinforces Transport → SessionEvent → CallSession → Conversation → Orientation stack.

**Outstanding questions:** UI pass to de-PBX Shop → Communications; rename surface from Voice to Communications; workstation presence → station operator on microbrowser; floor First Contact checklist.

### 2026-06-27 — Doctrine: Station as Operations authority

**Files changed:** `.cursor/rules/ark-station-doctrine.mdc`, `.cursor/rules/ark-communications-bounded-context.mdc`

**Reason:** Tighten Station doctrine — Stations belong to **Operations**, not Communications. Communications is one capability at a station. Capabilities layer (payments, printing, orientation) sits above devices/transport.

**Architecture impact:** Product classification only. `Workstation` remains code authority for Station. Prevents Communications-from-telephony drift (device-first, extension-first onboarding).

**Outstanding questions:** Operations nav/surface ownership for Stations vs Communications; capability registry pattern when payments/printing attach to stations.

### 2026-06-27 — Doctrine: Permanence rule + station orientation

**Files changed:** `.cursor/rules/ark-station-doctrine.mdc`

**Reason:** Document why Stations anchor the stack — permanence rule (years → hours → months → replaceable); station-specific Orientation projections; architectural compass (work vs implementation).

**Architecture impact:** Product doctrine only. Station Orientation composes from authorities; does not own them. Onboarding framed as "show me your shop."

**Outstanding questions:** `StationOrientationProjection` shape per station type; when to build vs certify voice first.

### 2026-06-27 — Doctrine: orthogonal capabilities + authorities/orientation split

**Files changed:** `.cursor/rules/ark-station-doctrine.mdc`, `.cursor/rules/ark-orientation-service.mdc`

**Reason:** Capabilities removed from permanence ladder (orthogonal to Shop→Station→Devices→Transport). Shop added (decades). "Authorities own truth. Stations own orientation." One orientation pattern across RO, Attention, Station. Feature design test (authority / station / orientation).

**Architecture impact:** Product doctrine — doctrine is now ARK OS anchor, not Communications-only.

**Outstanding questions:** Station experience designs per station type; when to implement Station orientation projection vs voice certification.

### 2026-06-27 — Doctrine frozen: Orientation pattern + projection distinction

**Files changed:** `.cursor/rules/ark-orientation-pattern.mdc` (replaces `ark-orientation-service.mdc`), `.cursor/rules/ark-station-doctrine.mdc`, `.cursor/rules/ark-projection-rule.mdc`

**Reason:** Freeze ARK vocabulary — Projection (computational) vs Orientation (human briefing). Product loop: Authority → Projection → Orientation → Action. "Orientation exists to reduce reconstruction." Rename service → pattern.

**Architecture impact:** Frozen primitives: Authority, Projection, Orientation, Station. No new architectural primitives expected for most features.

**Outstanding questions:** None for doctrine; implementation follows pattern for Station orientation when floor earns it.

### 2026-06-27 — Station-first Communications UI (vertical slice prep)

**Files changed:** Shop communications Blade partials, `CommunicationsShopProjection`, `CommunicationsShopWorkstationRow`, `CommunicationsShopWorkspaceTest`

**Reason:** De-PBX operator UI — Communications not Voice; Workstation → Station labels; stations-first index; device page shows station status/operator/attached devices; infrastructure behind master admin.

**Architecture impact:** Product surfacing only. Extension auto-assign on station create unchanged.

**Outstanding questions:** Floor — register VVX, operator loop, internal call, Twilio trunk (ops checklist).

## Entry Template

```markdown
### YYYY-MM-DD — PR{N}: {title}

**Files changed:** {list}

**Reason:** {why this work was done}

**Architecture impact:** {what changed in authority/projection boundaries, if anything}

**Outstanding questions:** {open items for next PR or floor observation}
```

---

### 2026-06-26 — PR1 + PR2: ARK Voice endpoint architecture and dynamic provisioning

**Files changed:** `app/Ark/Communications/Provisioning/*`, `app/Ark/Communications/Telephony/AssignExtensionToWorkstationAction.php`, endpoint migrations, `docs/communications/ark-voice-endpoint-architecture-v1.md`, `docs/engineering/TECHNICAL_DEBT.md`, `docs/communications/first-contact-floor-checklist.md`, shop device views, `GET /provision/{mac}.cfg`

**Reason:** Milestone 1 First Contact — factory-reset VVX350 obtains config from ARK and registers without manual `.cfg` transfer.

**Architecture impact:** EndpointConfigurationProjection is first-class read model with immutable history. Telephony owns extension identity; provisioning projects it. Legacy provision builder explicitly marked debt.

**Outstanding questions:** Floor proof on physical VVX350; PR3B assignment UX after First Contact.

### 2026-06-26 — PR3A: Provisioning observability UI

**Files changed:** `CommunicationDeviceWorkspaceProjection`, shop communications Blade views, structured provision logging

**Reason:** Bench testing requires visible MAC, provision URL, projection fingerprint, and admin config preview before assignment workflow.

**Architecture impact:** None — projection-only UI; no new authority.

**Outstanding questions:** Whether projection preview should redact SIP password for non-master-admin roles (currently master-admin infrastructure section only).

### 2026-06-27 — PR3A: Observability test coverage

**Files changed:** `tests/Feature/Communications/CommunicationsShopWorkspaceTest.php`, `docs/engineering/ACTIVE_PR.md`, `docs/engineering/CURRENT_MILESTONE.md`

**Reason:** Close PR3A with regression coverage for bench-facing observability fields before First Contact floor certification.

**Architecture impact:** None — tests assert projection rendering only.

**Outstanding questions:** Floor proof on physical VVX350 remains the milestone gate.

### 2026-06-27 — Poly VVX provisioning XML fix (First Contact G5)

**Files changed:** `PolyProvisionBuilder.php`, `PolyPhoneConfigDocument.php`, `EndpointConfigurationInputsFingerprint.php`, `poly-provisioning-instructions.blade.php`, `EndpointProvisionTest.php`

**Reason:** Production floor test — Poly VVX350 reported Update Configuration successful but SIP fields never populated. Production served plain key=value lines without `<PHONE_CONFIG>` XML and overwrote provisioning server URL with SIP host.

**Architecture impact:** Serialization-only — projection output format and fingerprint version bump (`poly_cfg_serialization_version`); no authority changes.

**Outstanding questions:** Re-run G5–G7 on physical VVX350 after production deploy; confirm cached projections regenerate on next phone fetch.

### 2026-06-27 — Poly phoneprov file naming parity (First Contact G5)

**Files changed:** `EndpointProvisionArtifact.php`, `EndpointProvisionFilename.php`, `ServeEndpointProvisionArtifactAction.php`, `PolyPhoneProv*Builder.php`, `EndpointProvisionController.php`, routes, tests, `infra/coolify/asterisk/phoneprov/README.md`

**Reason:** Floor logs showed Poly requesting `48256730757f-phone.cfg` and `config/{mac}` — Asterisk phoneprov naming — while ARK only served `{MAC}.cfg` PHONE_CONFIG. Port production Asterisk template sequence into Laravel `/provision/*`.

**Architecture impact:** Serve path only — multiple artifacts per device; projection still stores `config/{mac}` phone1 body. Fingerprint version 3.

**Outstanding questions:** Confirm VVX350 registers after Update Configuration with new artifact routes live.

### 2026-06-27 — Portable Station Phase 1: orientation home, mark read, MMS display

**PR:** Portable Station Phase 1 (staff app)

**Files changed:** `app/Ark/Mobile/MobileOrientationProjection.php`, `app/Ark/Mobile/Http/MobileOrientationController.php`, `app/Ark/Mobile/Http/MobileConversationMarkReadController.php`, `app/Ark/Mobile/Http/MobileConversationAttachmentShowController.php`, `routes/api.php`, `ConversationMessageEventMapper.php`, `ConversationProjection.php`, `tests/Feature/Mobile/MobileApiTest.php`; ark-mobile: `orientation_home_screen.dart`, `models/orientation.dart`, `home_shell.dart`, `communication_thread_screen.dart`, `conversation_timeline_event_card.dart`, `push_registration_service.dart`, `docs/PRODUCT-WALKTHROUGH.md`

**Reason:** Close Portable Station Phase 1 engineering gaps — orientation home instead of inbox-first nav, mark read parity with desktop, MMS attachment display on mobile threads.

**Architecture impact:** Track B consumes Track C orientation contract via composed Attention/Work projections; no new authority. Mark read reuses `ConversationReadTracker`. Attachments served through Sanctum-gated mobile route.

**Outstanding questions:** Wire Firebase in Flutter (`google-services.json`) to complete push → deep link floor cert. File first Portable Station certification record after 8:10 AM customer text scenario on device.

### 2026-06-27 — Station identity: session switch, roster, rate limit, privacy gate

**PR:** Fix Station Identity Before Floor Testing (in progress)

**Files changed:** `SwitchWorkstationOperatorSessionAction.php`, `UnlockWorkstationOperatorAction.php`, `WorkstationBrowserRoster.php`, `WorkstationOperatorEligibility.php`, `WorkstationUnlockRateLimiter.php`, `WorkstationStaffRoleHierarchy.php`, `WorkstationOperatorController.php`, `WorkstationPresence.php`, `CreateWorkstationOperatorPinAction.php`, `BindWorkstationBrowserAction.php`, `BindWorkstationController.php`, `AuthenticatedSessionController.php`, `WorkstationBrowserBinding.php`, `bootstrap/app.php`, migration `2026_06_27_150000_add_known_operator_user_ids_to_workstation_browser_bindings.php`, `tests/Feature/Workstations/WorkstationOperatorPresenceTest.php`; privacy gate files from prior pass (`app.blade.php`, comms interrupt controllers/JS)

**Reason:** PIN unlock must become a full session switch — one identity for auth, permissions, audit, and station operator. Browser roster + role peers-and-below enforce unlock picker server-side. Rate limit failed PIN attempts per binding/operator. Step Away privacy gate hides operational shell until unlock.

**Architecture impact:** Station unlock calls `Auth::login()` + `session()->regenerate()` then sets `current_operator_user_id`. Roster on `workstation_browser_bindings.known_operator_user_ids` populated on ARK login, bind, and successful unlock. Identity drift (session user ≠ workstation operator) forces lock. Binding cookie excluded from encryption (token is the secret).

**Outstanding questions:** Floor cert after deploy + migration on production. Logout-on-lock hardening deferred post-certification.

### 2026-06-28 — Mobile push: PushTransport abstraction (Firebase is transport only)

**PR:** (local — not yet committed)

**Files changed:** `app/Ark/Mobile/Push/PushTransport.php`, `MobilePushService.php`, `Transport/Firebase/FirebasePushTransport.php`, `Transport/Firebase/FcmAccessTokenProvider.php`, `DispatchMobilePushForInboundMessage.php`, deleted `MobilePushDispatcher.php`; `AppServiceProvider.php` binds `PushTransport` → `FirebasePushTransport`; `resources/views/operations/settings/partials/mobile-push-settings.blade.php`; `tests/Feature/Mobile/MobilePushServiceTest.php`; `docs/mobile/ark-mobile-notification-doctrine.md`, `.cursor/rules/ark-mobile-notification-doctrine.mdc`

**Reason:** Reinforce ARK-as-authority for mobile notifications. Business layer speaks `MobilePushService` / `PushTransport`; Firebase is one implementation that knows only "deliver this packet to this device token." One ARK Staff Firebase project for the single store binary — not per shop.

**Architecture impact:** Same transport pattern as telephony/SMS: ARK decides who/what/when; infrastructure delivers. Swapping FCM for direct APNs or OneSignal changes one binding, not product code.

**Outstanding questions:** Firebase project + service account floor setup; operational push certification after inbound SMS → push → conversation deep link on device.

### 2026-06-28 — Operator continuity: badge metric + doctrine

**PR:** (local — not yet committed)

**Files changed:** `OperatorContinuityProjection.php`, `MobileContinuityBadgeController.php`, `OperationalObservationStream::activeCount()`, `MobileObservationStreamProjection`, `MobileOrientationProjection`, `MobilePushService` (continuity comment), `routes/api.php`, `docs/mobile/ark-operator-continuity-doctrine.md`, `.cursor/rules/ark-mobile-notification-doctrine.mdc`, tests

**Reason:** Reframe mobile from notification system to continuity system. Badge counts unresolved observations (`operational_continuity`), not unread messages. Push remains one transport.

**Architecture impact:** Authority → Event → Observation → Operator Continuity → Surface. Same observations, many surfaces (home, badge, push, future station display).

**Outstanding questions:** Role-specific observation routing for technicians; Flutter app icon badge wiring; Live Activities when floor earns them.

### 2026-06-28 — Continuity snapshot API (projection-only guardrail)

**PR:** (local — not yet committed)

**Files changed:** `OperatorContinuityProjection` (full snapshot, priority/age meta), `MobileContinuityController`, `routes/api.php` (`GET /api/mobile/continuity`), `MobileOrientationProjection`, doctrine + cursor rule, tests

**Reason:** Continuity must stay composition — no continuity database. One snapshot for badge, moments, next action, future widgets/watch/VVX. North star: build continuity surfaces, not notification features.

**Architecture impact:** Operator Continuity is explicitly projection-only; VVX microbrowser should consume continuity, not telephony.

**Outstanding questions:** `today` and `station` slots in snapshot; VVX idle screen consumer; Flutter poll `/continuity` for badge + widgets.

### 2026-06-28 — Workflow Completion Certification (milestone shift)

**PR:** (docs only — no code)

**Files changed:** `docs/engineering/CURRENT_MILESTONE.md`, `ACTIVE_PR.md`, `workflow-completion-certification.md`, `docs/product/certifications/customer-arrival-workflow.md`, `certifications/README.md`, `portable-station-phase-1.md` (correction), `ark-mobile-workflow-doctrine.md`, `docs/engineering/README.md`, `ark-pr-doctrine-review.mdc`

**Reason:** Shift certification from screens/infrastructure to complete shop workflows. Next target: Customer Arrival Operationally Certified. Mobile PR gate: which shop work can finish on phone that couldn't yesterday?

**Architecture impact:** None — continuity projection frozen. Engineering effort redirects to workflow completion on device.

**Outstanding questions:** Gap analysis for Customer Arrival Flutter path; floor operational script.

### 2026-06-28 — Primary execution surface + five workflow certs + Phone-First Shop

**PR:** (docs only)

**Files changed:** `workflow-completion-certification.md`, `phone-first-shop.md`, workflow certs 1–5, `customer-arrival-workflow.md`, `CURRENT_MILESTONE.md`, `ACTIVE_PR.md`, `ark-mobile-workflow-doctrine.md`, certifications README, `ark-pr-doctrine-review.mdc`

**Reason:** Phone is primary execution surface — not "mobile," not Desktop Lite. Five shop-native workflow certs + Phone-First Shop meta cert. Flutter gate: what can Edward finish standing next to a vehicle?

**Architecture impact:** None. Product and certification framing only.

**Outstanding questions:** Shop Walk station projection design; Vehicle Pickup mobile payment path on floor.

### 2026-06-28 — North star: operation follows the operator

**PR:** (docs only)

**Files changed:** `docs/product/operation-follows-operator-v1.md`, `CURRENT_MILESTONE.md`, `ACTIVE_PR.md`, `workflow-completion-certification.md`, `phone-first-shop.md`, certifications README, `ark-mobile-workflow-doctrine.md`, engineering README

**Reason:** Pull product one level above Phone-First Shop. Operation follows the operator across stations/devices. Bottleneck is muscle memory, not architecture. Certify by filming five workflows — video as product regression test.

**Architecture impact:** None. Stop architecture commits; earn floor muscle memory.

**Outstanding questions:** Record Customer Arrival video; Edward/Molly/Landon one-week floor trial.

### 2026-06-28 — Operation videos as primary engineering artifact

**PR:** (docs only)

**Files changed:** `docs/operations/README.md`, `docs/operations/videos/.gitkeep`, `.gitignore`, `operation-follows-operator-v1.md`, `workflow-completion-certification.md`, `CURRENT_MILESTONE.md`, `ACTIVE_PR.md`, certifications README, engineering README, `ark-pr-doctrine-review.mdc`, `ark-mobile-workflow-doctrine.md`

**Reason:** Stop filming certifications; film operations ("how Edward actually works"). Ten-operation catalog as reference implementations. PR acceptance: re-record operation — smoother = better. Engineer sentence: if you can't point to a real operation improved, probably not improving ARK.

**Architecture impact:** None. Development process shift only.

**Outstanding questions:** Record `01-customer-arrival.mp4`; store link in operations index.

### 2026-06-27 — Surfaces doctrine: explainable moment tone, Work role sections, mobile thread order

**PR:** (local — ARK Staff full app buildout, surfaces pass)

**Files changed:** `OperationalObservationType.php` (added `tone()`), `MobileObservationStreamProjection.php` (surface `tone`/`category`), `ConversationProjection.php` (mobile thread chronological order), ark-mobile: `models/attention.dart` (tone/category), `widgets/identity_strip.dart` (reconstructed `primary`/`segments` `PreferredSizeWidget`), `screens/my_work_screen.dart` (role sections, attention-first)

**Reason:** Continue "surfaces, not screens" — the moment feed needs an explainable tone (urgent/waiting/positive/info, not a score); Work surface should answer "what am I responsible for" via role sections; the mobile conversation thread read newest-first instead of oldest→newest (caught by an existing eager-load test).

**Architecture impact:** Tone lives in the observation vocabulary (authority), surfaces consume it — they do not re-derive meaning. Thread order fix is mobile-only; desktop uses its own `array_reverse` path. Much of the surfaces doctrine (moment-feed Home, app-bar global search, quick-action FABs, identity strips, Shop) was already present; this pass tightened and corrected it.

**Outstanding questions:** Global search beyond customers/RO# (vehicles, VIN, plate, invoices, parts) awaits backend search endpoints; 28 desktop comms/timeline test failures are pre-existing from the in-progress push/continuity refactor (verified failing with this change stashed) — out of scope here.

### 2026-06-27 — Tone is continuity (push + home placement) + act-without-navigate

**PR:** (local — ARK Staff, action-first pass)

**Files changed:** `Push/MobilePushMessage.php` (tone + `deliversImmediately`/`makesSound`), `Push/Transport/Firebase/FirebasePushTransport.php` (android/apns priority + sound by tone), `Push/DispatchMobilePushForInboundMessage.php` (authoritative tone), `tests/Feature/Mobile/MobilePushServiceTest.php`; ark-mobile: `widgets/operational_moment.dart` (`MomentInlineAction`), `screens/orientation_home_screen.dart` (tone-grouped feed + inline reply)

**Reason:** Operator directive — stop polishing surfaces; make `tone` drive continuity (not color) and reduce navigation. Tone now decides push delivery loudness (urgent rings + high priority; waiting high/quiet; positive/info normal/silent) and home placement (Needs action now vs Earlier). Customer replies can be answered inline from the home feed via the existing reply sheet + `sendMessage` — no navigation.

**Architecture impact:** Same observation tone vocabulary consumed by three surfaces (push, badge-adjacent home ordering, feed) — surfaces consume, don't re-derive. Inline reply reuses `conversation_composer_sheet` + `conversationsRepository.sendMessage`; no new send pipeline.

**Outstanding questions:** Next unit of work is complete-workflow certification (Customer Arrival, Phone Call, Pickup, Payment…) — which slice to certify first; VVX/badge tone wiring still to follow the same table; inline actions to extend to work cards (swipe/quick actions).

### 2026-06-27 — Customer Arrival workflow: continuous, leaveable, no re-search

**PR:** (local — ARK Staff, workflow certification: Customer Arrival)

**Files changed:** ark-mobile: `screens/check_in_screen.dart` (Scaffold/AppBar + `initialCustomerId` preseed), `screens/customer_workspace_screen.dart` ("Check in" FAB + preseed), `screens/vehicle_workspace_screen.dart` ("Start RO" preseed); `docs/product/certifications/customer-arrival-workflow.md`

**Reason:** Shift to certifying complete mobile workflows. Arrival spine already existed (identify → customer → open RO → assign tech, ending in RO workspace) but `CheckInScreen` rendered as a bare `ListView` when pushed (no back/title) and could only be started by re-searching. Now it owns a Scaffold (customer name = persistent identity) and starts from a customer/vehicle already open via `initialCustomerId`.

**Architecture impact:** No new authority/endpoints; reuses `IntakeRepository.loadCustomer` + existing intake submit. Entry points converge on one `CheckInScreen`.

**Outstanding questions:** Engineering Certified blocked on **photo capture at arrival** (Vehicle "Add photo" is a coming-soon stub; intake has no photo step) — next concrete step for this slice. Then on-device end-to-end recording + desktop-reflects-truth check.

### 2026-06-27 — Customer Arrival: Vehicle Walk-Around (photo gap closed)

**PR:** (local — ARK Staff, Customer Arrival walk-around)

**Files changed:** ark-mobile: `screens/vehicle_walk_around_screen.dart` (new — camera-first wizard), `screens/check_in_screen.dart` (walk-around runs after RO opens), `screens/vehicle_workspace_screen.dart` ("Walk-around" FAB), `models/finding.dart` (`observation` intent); `tests/Feature/Mobile/MobileApiTest.php` (advisor walk-around test); `docs/product/certifications/customer-arrival-workflow.md`

**Reason:** Finish the arrival operation as it actually happens — "documenting the vehicle," not an "add photo" feature. Camera opens per angle (Front/Driver/Passenger/Rear/Odometer + optional Damage), auto-attaches to the RO, advances. No gallery/source picker/upload dialog.

**Architecture impact:** No new authority. Condition photos reuse the **existing inspection evidence** path (`StoreInspectionFindingAction` + `InspectionItemPhoto`) via the `Observation` (document-only) intent — consolidation, not a parallel DVI/media module (ark-inspection doctrine). Advisor-permitted (`InspectionCaptureLinks::canRecord`). Backend unchanged. Mobile `FindingIntent` gained `observation` to match backend enum.

**Outstanding questions:** Engineering Certified now pending only on-device end-to-end recording + desktop-reflects-truth check. Then record `01-customer-arrival` operation video; next slice: Customer Phone Call.

### 2026-06-27 — VVX continuity projections (station orientation + microbrowser)

**PR:** (local — VVX continuity surfaces)

**Files changed:** `StationOrientationProjection.php`, `ShopTodayPulseProjection.php`, `CommunicationDeviceMicrobrowserProjection.php`, `OperatorContinuityProjection.php` (`today` + `station` slots), `resources/views/communications/device-screen.blade.php`, `tests/Feature/Communications/CommunicationDeviceMicrobrowserContinuityTest.php`, `CommunicationsShopWorkspaceTest.php`

**Reason:** VVX idle screen consumes operator continuity — station name, signed-in operator, observation moments, next action, call overlay — not telephony-only posture.

**Architecture impact:** Station orientation is projection-only; microbrowser composes `OperatorContinuityProjection` + `StationOrientationProjection`. Mobile `/continuity` now exposes `today` (open RO + waiting counts) and `station` when operator is signed in at a workstation.

**Outstanding questions:** Floor verify on physical VVX350; bay tablet consumer reuses same station projection; Shop Walk cert ties station grammar to walk path.

### 2026-06-28 — VVX continuity appliance (posture projection + static DOM patch)

**PR:** Add VVX continuity appliance posture projection

**Files changed:** `StationPosture.php`, `StationPostureProjection.php`, `StationScreenProjection.php`, posture/screen/legacy microbrowser controllers, `device-appliance.blade.php`, `routes/web.php`, `PolyPhoneProvDeviceConfigBuilder.php` (`idleDisplay.refresh=0`), `PolyProvisionBuilder::SERIALIZATION_VERSION=9`, `docs/communications/vvx-microbrowser-audit-v1.md`, tests.

**Reason:** VVX as continuity appliance — hot `/posture` poll, cold `/screen` on transition only, static HTML + DOM patch (never reload). Legacy `/device-screen/{token}/legacy` retained for rollback.

**Architecture impact:** VVX bypasses `OperatorContinuityProjection` on hot path. `StationPostureProjection` → `StationScreenProjection` stack for future wallboard/kiosk.

**Outstanding questions:** Floor stopwatch on Right Phone only after deploy; re-provision serialization v9; Traefik HTTP route after container recreate; `call_ended` posture later.

### 2026-06-28 — Mobile RO: money on the RO + advisor command bar (audit items 1–4)

**PR:** (local — ARK Staff mobile RO redesign 1–4)

**Files changed:** arksmsv2: `app/Ark/Mobile/MobileEstimateProjection.php` (new — read-only money projection), `MobileRepairOrderProjection.php` (estimate detail, technician money gate), `MobileConcernProjection.php` (priced recommendation lines), `RepairOrderWorkspaceProjection.php` (header money summary, Estimate section, role-aware command bar), `RepairOrderWorkspaceIntelligenceProjection.php` (timeline open-event de-dup), `tests/Feature/Mobile/MobileApiTest.php` (advisor money/send-actions + technician-hidden tests). ark-mobile: `widgets/repair_order_workspace_header.dart` (money row), `screens/repair_order_workspace_screen.dart` (Estimate section + send_estimate/send_payment commands, removed nav-dup More menu), `screens/concern_detail_screen.dart` (priced recommendation tiles), `widgets/workspace_overview_intelligence.dart` (relabel "Recommendations" → "Inspection follow-ups").

**Reason:** RO audit gaps — the mobile RO showed no money (headline Shop-In-A-Phone blocker), the command bar was technician-shaped for advisors, "More" only duplicated tabs, and "Recommendations" meant two different things on Overview vs Concern. (1) Money: header now shows estimate total + balance due, a new Estimate section lists priced line items grouped by concern with parts/labor/fees/tax breakdown, and concern recommendation tiles show price. All dollars flow through `EstimateTotalsCalculator`/`BalanceDueCalculator` (server authority); GET-safe (reads persisted totals, never recalculates). (2) Command bar is role-aware: advisors get Message / Send estimate / Send payment (reusing existing `send-estimate`/`send-payment` endpoints with a confirm dialog), technicians keep Photo/Finding/Complete. (3) Nav-duplicate More menu removed. (4) Overview intelligence queue relabeled "Inspection follow-ups"; duplicate "Repair order opened" timeline entry de-duped.

**Architecture impact:** No new authority, no new endpoints, no migrations. `MobileEstimateProjection` is a projection over existing financial authority, consumed by both the workspace and RO projections (compute-once). Technician-scope honored — money payload omitted for technicians, not just hidden. Mobile renders dollars; never derives them.

**Outstanding questions:** Items 5–6 (in-place approvals, payment capture, lifecycle transitions) introduce new mobile write authority + product decisions — deferred pending confirmation. Send-payment is gated to ROs with an outstanding issued-invoice balance; revisit if advisors want to request deposits pre-invoice.

### 2026-06-28 — Mobile RO: in-place customer-decision approvals (audit item 5)

**PR:** (local — ARK Staff mobile RO approvals)

**Files changed:** arksmsv2: `app/Ark/Operations/RepairOrders/UpdateConcernDispositionAction.php` (new — shared authoritative disposition write), `RepairOrderConcernDispositionController.php` (desktop now delegates to the action), `app/Ark/Mobile/Http/MobileConcernDispositionController.php` (new — mobile PATCH endpoint), `app/Ark/Mobile/MobileConcernProjection.php` (`disposition_control`: current + label + `can_update` + options), `app/Ark/Mobile/MobileStaffAccess.php` (`canSetConcernDisposition` — `RepairOrdersManage`, non-terminal RO), `routes/api.php` (PATCH `/repair-orders/{ro}/concerns/{concern}/disposition`), `tests/Feature/Mobile/MobileApiTest.php` (advisor approve in place + technician-forbidden; relaxed advisor-money assertion to be shop-tax/fee independent). ark-mobile: `api/mobile_api.dart` + `repositories/work_repository.dart` (`setConcernDisposition`), `screens/concern_detail_screen.dart` (`_DispositionControl` — advisor-only ChoiceChip control under the concern, self-invalidating).

**Reason:** Audit item 5 — advisors could see the estimate on the phone but could not record the customer's decision there; approve/decline/defer required the desktop. The estimate decision is now in-place beside the vehicle.

**Architecture impact:** No new authority and no duplicated decision logic — desktop and mobile both call the single `UpdateConcernDispositionAction` (disposition write, production reset, operational event, estimate-version bump, totals recalc, lifecycle retreat). Mobile is a projection of the same authority. Technician-scope honored: `can_update` (and the control) is omitted for technicians; the client degrades gracefully when the server omits `disposition_control` (forward/backward compatible).

**Outstanding questions:** Item 6 (mobile payment capture) still introduces card-present/terminal product decisions — deferred. Watch whether advisors want a confirm step before Approve (currently direct, matching desktop and the production-status picker).

### 2026-06-28 — Mobile RO: approved vs. waiting (ARO) split on the Estimate card

**PR:** (local — ARK Staff mobile RO money finish)

**Files changed:** arksmsv2: `app/Ark/Operations/Financial/EstimateTotalsCalculator.php` (new `approvedTotalsForRead` + `recommendedTotalsForRead` — GET-safe, no recalc/persist), `app/Ark/Mobile/MobileEstimateProjection.php` (`summary()` adds `approved_total_*`, `waiting_total_*`, `has_unapproved_work`), `tests/Feature/Mobile/MobileApiTest.php` (mixed-disposition approved-vs-waiting test). ark-mobile: `screens/repair_order_workspace_screen.dart` (Estimate card Approved/Waiting rows + `_MoneyLine`).

**Reason:** Finishing audit item 1 — the money read surface showed the estimate total and per-concern dispositions but no aggregate answer to the core advisor question "what is approved vs. waiting?" (Cecil ARO doctrine: recommended/deferred work is follow-up revenue and must be visible). Added the approved-work total and the recommended ("waiting on customer") total as independent buckets. Critical nuance: the estimate total drops recommended work once anything is approved (`RepairOrderConcernDisposition::countsTowardEstimateTotal`), so waiting is computed from the recommended bucket — never `estimate − approved`. Rendered on the Estimate card only (kept the header calm) and only when recommended work exists.

**Architecture impact:** No new authority, endpoints, or migrations. Two read-only calculator methods mirror `totalsForApprovedWork` without its `recalculateRepairOrder` write, honoring the read/write rule on the GET show/workspace path. Mobile renders the dollars; the calculator remains the single financial authority.

**Outstanding questions:** Whether to also surface a Waiting figure on the header glance (left off to avoid crowding three money values); revisit if advisors ask for at-a-glance ARO without opening the Estimate tab.

### 2026-06-28 — Mobile: global search, attention polish, check-in vehicle preselect

**PR:** (local — ARK Staff mobile navigation slice)

**Files changed:** arksmsv2: `MobileGlobalSearchProjection.php` + `MobileGlobalSearchController.php` (new — `GET /api/mobile/search?q=`), `VehicleSearchQuery::matching()`, `MobileAttentionProjection.php` (`customer_id` on decision + comms rows), `routes/api.php`, `tests/Feature/Mobile/MobileApiTest.php`. ark-mobile: `models/global_search.dart`, `global_search_screen.dart` (unified API — customers, vehicles, `#RO`), `check_in_screen.dart` (`initialVehicleId` → skip to open-RO step), `vehicle_workspace_screen.dart` (start RO passes vehicle), `attention_screen.dart` (`ForegroundPoll` + tone dot), `mobile_api.dart`, `intake_repository.dart`.

**Reason:** Close remaining desktop trips for advisor navigation — universal search jumps straight to customer/vehicle/RO workspaces; vehicle workspace "Start RO" preselects the vehicle in check-in; attention rows carry `customer_id` so comms/decision tiles open customer workspace on tap; attention polls while foregrounded.

**Architecture impact:** Search composes existing `CustomerSearchQuery` + `VehicleSearchQuery` — no parallel search authority. RO hint validates shop number exists before surfacing. Attention remains a projection-only slice; `customer_id` enables existing deep-link grammar without new stores.

**Outstanding questions:** RO number search does not fuzzy-match partial numbers beyond `#1234` pattern; extend only if floor asks. Scheduling and in-person Square payment remain desktop-only.

### 2026-06-28 — Mobile: Square terminal capture + schedule authority

**PR:** (local — ARK Staff mobile payments and scheduling)

**Files changed:** arksmsv2: `MobileSquarePaymentProjection.php`, `MobileScheduleProjection.php`, `MobileAppointmentRowProjection.php`, mobile HTTP controllers (square initiate/poll/cancel, schedule index, appointment store), `MobileStaffAccess` (`canRecordPayment`, `canManageAppointments`), `RepairOrderWorkspaceProjection` (`square_payment`, `charge_terminal` command), `MobileCustomerWorkspaceProjection` (upcoming appointment + schedule quick action), `routes/api.php`, `tests/Feature/Mobile/MobileApiTest.php`. ark-mobile: schedule + square models/repos, `schedule_screen.dart`, `square_terminal_charge_sheet.dart`, `appointment_create_sheet.dart`, RO command bar + customer workspace wiring.

**Reason:** Close the last major advisor desktop trips — collect payment on the paired Square reader at pickup and manage the day’s appointments from the phone without opening ARK web.

**Architecture impact:** Square remains a capture rail — mobile delegates to `InitiateSquarePaymentAction` / `PollSquareTerminalCheckoutAction` / `CancelSquarePaymentAttemptAction`; ledger truth unchanged. Schedule composes existing `Appointment` authority + `AppointmentScheduleRowPresenter` sort; no parallel calendar store. Keyed card entry stays desktop-only (Web Payments SDK).

**Outstanding questions:** Schedule “+” from the day view still asks for customer ID — wire customer search next if advisors use it. Square keyed capture on Flutter only if floor asks (would need Square mobile SDK).

### 2026-06-28 — Mobile: manual payment + schedule status polish

**PR:** (local — ARK Staff mobile ledger payment and appointment actions)

**Files changed:** arksmsv2: `MobileManualPaymentProjection.php`, `MobileRepairOrderPaymentStoreController.php` (`PATCH /api/mobile/repair-orders/{repairOrder}/payment`), `MobileAppointmentStatusController.php` (`PATCH /api/mobile/appointments/{appointment}/status`), `MobileAppointmentRowProjection.php` (`status_actions`), `RepairOrderWorkspaceProjection.php` (`manual_payment`, `record_payment` command), `MobileCustomerWorkspaceLayoutEngine.php` (schedule copy), `routes/api.php`, `tests/Feature/Mobile/MobileApiTest.php`. ark-mobile: `manual_payment.dart`, `record_payment_sheet.dart`, `customer_picker_sheet.dart`, schedule status + customer picker wiring, RO command bar `record_payment`, API/repo patches.

**Reason:** Close remaining advisor desktop trips for pickup — record cash/check/card on issued invoices from the RO workspace, update appointment status from the day schedule, and book appointments with customer search instead of typing IDs.

**Architecture impact:** Manual payment delegates to `RepairOrderLedgerPaymentRecorder` — same ledger authority as desktop; no client-side money math. Appointment status uses existing `Appointment` lifecycle; schedule remains projection-only with actionable rows.

**Outstanding questions:** Refunds, deposits, and Square keyed entry remain desktop-only. Owner reports and inspection link send still not on mobile.

### 2026-06-28 — Mobile: owner bookend pulse

**PR:** (local — ARK Staff owner end-of-day on phone)

**Files changed:** arksmsv2: `MobileOwnerBookendProjection.php`, `MobileOwnerBookendController.php` (`GET /api/mobile/owner/bookend`), `MobileStaffAccess` + `MobileUserPresenter` (`owner_bookend` capability), `MobileOrientationProjection` (manager bookend quick action), `routes/api.php`, `tests/Feature/Mobile/MobileApiTest.php`. ark-mobile: `owner_bookend.dart`, `owner_bookend_screen.dart`, `owner_repository.dart`, More screen entry for admins.

**Reason:** Edward (owner/admin) should bookend the day from the phone — sales posted, cash collected, ELR/ARO pulse, and tomorrow's queue pressure — without opening `/app/owner/bookend` in a browser.

**Architecture impact:** Read-only projection through `EndOfDayReportProjection` + `OwnerOperationalPulse` + `ShopBehaviorPulse`; admin gate matches `OwnerWorkspaceAccess`. No parallel owner metrics authority.

**Outstanding questions:** Full operational report tabs (margin health, owner P&L) remain desktop-only. Square keyed capture and refunds still not on mobile.

### 2026-06-28 — Mobile: deposit capture before invoice

**PR:** (local — ARK Staff mobile deposit on reader and manual)

**Files changed:** arksmsv2: `MobileSquareDepositProjection.php`, `MobileManualDepositProjection.php`, deposit HTTP controllers (`PATCH deposit`, `POST square-deposits`), `RepairOrderWorkspaceProjection` (`collect deposit` / `record deposit` commands), `MobileStaffAccess::canRecordDeposit`, `routes/api.php`, `tests/Feature/Mobile/MobileApiTest.php`. ark-mobile: `deposit.dart`, `record_deposit_sheet.dart`, generalized `square_terminal_charge_sheet.dart` for deposit intent, RO command bar wiring.

**Reason:** Advisors collect suggested deposits at approval from the phone — Square terminal and manual cash/check/card — without waiting for final invoice or desktop financial rail.

**Architecture impact:** Deposits delegate to `InitiateSquareDepositAction` / `RepairOrderLedgerDepositRecorder`; terminal poll/cancel reuses existing `PaymentGatewayAttempt` paths via `SquareAttemptCompleter`. Keyed deposit stays desktop-only (Web Payments SDK).

**Outstanding questions:** Refunds and Square keyed card entry remain desktop-only. Send inspection link not built on desktop yet.

### 2026-06-28 — Mobile: record refund on paid RO

**PR:** (local — ARK Staff mobile ledger refund)

**Files changed:** arksmsv2: `MobileManualRefundProjection.php`, `MobileRepairOrderRefundStoreController.php` (`PATCH /api/mobile/repair-orders/{repairOrder}/refund`), `MobileStaffAccess::canRecordRefund`, `RepairOrderWorkspaceProjection` (`manual_refund`, `record_refund` command), `routes/api.php`, `tests/Feature/Mobile/MobileApiTest.php`. ark-mobile: `manual_refund.dart`, `record_refund_sheet.dart`, RO workspace wiring, API/repo patch.

**Reason:** Edward should issue partial refunds from the phone after payment — same ledger path as desktop Record Refund — without opening the RO financial rail in a browser.

**Architecture impact:** Refunds delegate to `RecordLedgerEntryAction::recordRefund` + `NotifyRepairOrderFinancialChange`; projection gates on issued invoice + payments applied. Square keyed capture and full operational report remain desktop-only.

**Outstanding questions:** Void ledger entry and Square keyed card entry remain desktop-only. Send inspection link not built on desktop yet.

### 2026-06-28 — Mobile: owner operational report pulse

**PR:** (local — ARK Staff mobile operational report)

**Files changed:** arksmsv2: `MobileOwnerOperationalReportProjection.php`, `MobileOwnerOperationalReportController.php` (`GET /api/mobile/owner/operational-report`), `MobileStaffAccess::canViewOwnerOperationalReport`, `MobileUserPresenter` (`owner_operational_report` capability), `routes/api.php`, `tests/Feature/Mobile/MobileApiTest.php`. ark-mobile: `owner_operational_report.dart`, `owner_operational_report_screen.dart`, More screen entry, API/repo/provider wiring.

**Reason:** Edward needs daily KPIs, margin health vs targets, and financial mix on the phone — beyond bookend's end-of-day queue review — without opening desktop operational report tabs.

**Architecture impact:** Read-only projection through existing `OperationalReportRangeMetrics` + `OperationalReportPaymentReconciliation`; admin gate matches `OwnerWorkspaceAccess`. Owner P&L tab and production/advisor row tables remain desktop-only.

**Outstanding questions:** Owner P&L tab and production throughput tables still desktop-only. Square keyed capture remains desktop-only.

### 2026-06-28 — Mobile: void ledger entry on RO

**PR:** (local — ARK Staff mobile payment history void)

**Files changed:** arksmsv2: `MobileLedgerProjection.php`, `MobileRepairOrderLedgerVoidController.php` (`DELETE /api/mobile/repair-orders/{repairOrder}/ledger-entries/{entry}`), `MobileStaffAccess::canVoidLedgerEntry` + `canManageLedgerEntries`, `RepairOrderWorkspaceProjection` (`ledger`, `payment_history` command), `routes/api.php`, `tests/Feature/Mobile/MobileApiTest.php`. ark-mobile: `ledger.dart`, `payment_history_sheet.dart`, RO command bar wiring.

**Reason:** Closeout authority (owner/admin) should void mistaken deposits, payments, or refunds from the phone — same `VoidLedgerEntryAction` as desktop — without opening the financial rail.

**Architecture impact:** Void gated on `RepairOrdersCloseout` and the same entry types as desktop (deposit, payment, refund, store credit issuance). Advisors with manage-only still see payment history read-only when entries exist.

**Outstanding questions:** Square keyed card entry remains desktop-only. Owner P&L and production tables remain desktop-only. Send inspection link not built on desktop yet.

### 2026-06-28 — Mobile: operational report Owner P&L + Production tabs

**PR:** (local — extend mobile owner operational report)

**Files changed:** arksmsv2: `MobileOwnerOperationalReportProjection.php` (`owner_pl`, `production` sections), `tests/Feature/Mobile/MobileApiTest.php`. ark-mobile: extended `owner_operational_report.dart`, `owner_operational_report_screen.dart` (5 tabs).

**Reason:** Edward needs management P&L estimate and live production pressure on the phone — the remaining desktop operational report tabs — without opening `/app/reports/operational`.

**Architecture impact:** Read-only through `OperationalReportRangeMetrics::ownerPlSummary()`, advisor/technician rows, and queue pressure logic aligned with desktop controller. Square keyed capture remains desktop-only.

**Outstanding questions:** Square keyed card entry remains desktop-only. Send inspection link not built on desktop yet.

### 2026-06-28 — Phase 2B: Send inspection link (portal + desktop + mobile)

**PR:** (local — inspection portal link)

**Files changed:** arksmsv2: `inspection_access_tokens` migration, `InspectionAccessToken` + create/resolve actions, `PortalInspectionSnapshot`/`PortalInspectionPage`/`PortalInspectionShowController`/`PortalInspectionPhotoShowController`, `SendInspectionLinkAction`/`SendInspectionLinkController`, `RepairOrderConversationSendProjection` (inspection channel), `RepairOrderWorkspaceProjection` (`send_inspection` command), portal routes + `portal/inspection.blade.php`, conversation quick-reply + JS, `routes/web.php`/`routes/api.php`, `tests/Feature/Operations/SendInspectionLinkTest.php`, `tests/Feature/Mobile/MobileApiTest.php`. ark-mobile: `sendInspectionLink` API/repo, RO workspace command + icon.

**Reason:** Close desktop-trip gap for advisors texting inspection findings to customers — same Phase 2 pattern as estimate/payment links (portal token → SMS → `ConversationMessage`).

**Architecture impact:** New portal token authority mirrors estimate access tokens. Customer portal is read-only snapshot of recorded findings with token-scoped photo routes. No parallel SMS/inspection subsystem. Desktop conversation rail and mobile RO command bar both call the same send action.

**Outstanding questions:** Square keyed card entry remains desktop-only.

### 2026-06-28 — Mobile: send inspection from customer hub and conversation composer

**PR:** (local — inspection link surfaces)

**Files changed:** arksmsv2: `MobileCustomerWorkspaceProjection`, `ConversationProjection` (composer `inspection` action), `tests/Feature/Mobile/MobileApiTest.php`. ark-mobile: `customer_workspace_screen.dart`, `communication_thread_screen.dart`.

**Reason:** Advisors initiate inspection sharing from Customer Hub and conversation threads — not only the RO command bar.

**Architecture impact:** Same `SendInspectionLinkAction` and `RepairOrderConversationSendProjection` inspection channel; projection-only expansion.

**Outstanding questions:** Square keyed card entry remains desktop-only.

### 2026-06-28 — Inspection portal staff preview (desktop + mobile browser)

**PR:** (local — inspection staff preview)

**Files changed:** arksmsv2: `CreateOrReuseInspectionAccessTokenAction` (`forStaffPreview`), `PortalInspectionPage` (explicit `staffPreview`), `RepairOrderPortalInspectionPreviewController`, `RepairOrderInspectionPortalLinkController`, `MobileRepairOrderInspectionPortalPreviewController`, `inspection-portal-link.blade.php`, portal rail include, routes, `RepairOrderWorkspaceProjection` (`preview_inspection`), tests. ark-mobile: preview API + RO command + `url_launcher`.

**Reason:** Advisors preview the customer inspection portal before texting — same pattern as estimate staff preview and copy-link on the Portal rail.

**Architecture impact:** Staff preview tokens expire in one hour and do not replace customer tokens. Mobile opens token URL in external browser (no session cookie required).

**Outstanding questions:** Square keyed card entry remains desktop-only.

### 2026-06-28 — Mobile Comms first-class tab + hub

**PR:** (local — comms hub)

**Files changed:** arksmsv2: `MobileUserPresenter` (server `navigation`), `MobileAttentionProjection` (`commsForUser`), `MobileCommsHubProjection`, `MobileCommsHubController`, `routes/api.php`, `tests/Feature/Mobile/MobileApiTest.php`. ark-mobile: `comms_hub_screen.dart`, `voice_posture_banner.dart`, `incoming_call_host.dart`, `comms_hub.dart`, `comms_repository.dart`, `home_shell.dart`, `apps_screen.dart`, `mobile_shell.dart`, providers/API wiring.

**Reason:** Communications on the phone should be first-class — advisors/managers get a Comms tab with voice posture, calls waiting, since-last-shift rows, and conversation inbox. Technicians stay RO-scoped (no global Comms tab).

**Architecture impact:** In-app voice remains Twilio Programmable Voice SDK via `/api/mobile/telephony/voice-*` — webhooks are server transport, not a Flutter gap. Incoming VoIP calls surface through `IncomingCallHost`. No raw SIP in Flutter; Asterisk remains future transport behind the same API.

**Outstanding questions:** Production in-app voice requires Twilio voice client settings + device registration with FCM/VoIP tokens. Badge counts on Comms tab not yet wired.

### 2026-06-28 — ARK Voice mobile transport (Twilio PV retired)

**PR:** (local — ARK Voice mobile)

**Files changed:** arksmsv2: `AsteriskMobileVoiceTransport`, `EnsureMobileVoiceExtensionAction`, `MobileVoiceRegistrationProjection`, `MobileVoicePjsipSync`, `SyncMobileVoicePjsipCommand`, `MobileVoiceTransportManager`, migration `mobile_device_id` on `telephony_extensions`, `RegisterMobileDeviceAction`, `MobileTelephonyDialProjection`, `config/voice-transport.php`, `infra/coolify/asterisk/config/templates/pjsip.conf` (WSS transport + mobile include), `extensions.conf` (8100–8999), `tests/Feature/Mobile/MobileVoiceSessionTest.php`, `docs/mobile/ark-mobile-communications-authority-contract.md`. ark-mobile: `ark_voice_dialer/` transport adapter (`sip_ua` + `flutter_webrtc`), `voice_dialer_bootstrap.dart`, `incoming_call_host.dart`, `home_shell.dart`.

**Reason:** Mobile in-app voice must ride shop Asterisk (ARK Voice), not Twilio Programmable Voice. Flutter consumes opaque `/api/mobile/telephony/voice-*` payloads only — no shop SIP settings in the app.

**Architecture impact:** One `TelephonyExtension` per registered mobile device (8100–8999) with generated PJSIP stanzas. Session returns `transport: ark_voice`, extension secret as `access_token`, and `registration` blob (WSS URI, SIP URI, display name). Twilio PV transport kept deprecated for rollback. Inbound PSTN still rings ext 101 until ring-group work earns floor observation.

**Outstanding questions:** Copy `storage/app/private/voice/pjsip-mobile-endpoints.generated.conf` into Asterisk volume + `pjsip reload` after provisioning (see RUNBOOK). WSS TLS cert on `:8089`. PSTN → mobile extension ring policy not wired. Remove `twilio_voice` Flutter dep after floor certification.

### 2026-06-28 — Mobile Comms voice transport correction

**PR:** (follow-up to comms hub entry above)

**Reason:** Prior log line incorrectly stated Twilio Programmable Voice as the mobile in-app path. Same PR batch pivoted to ARK Voice — see entry **ARK Voice mobile transport** above.

**Architecture impact:** `IncomingCallHost` now binds to `ArkVoiceDialer.callStates` for `ark_voice` and Twilio events only when `transport == twilio'`. Session warm runs for any `in_app_ready` transport.

**Outstanding questions:** None beyond ARK Voice mobile entry.

### 2026-06-28 — ARK Voice mobile inbound ring + Asterisk sync automation

**PR:** (local — ARK Voice mobile ops)

**Files changed:** `MobileVoiceInboundDialplanSync`, `MobileVoiceAsteriskConfigSync`, `SyncMobileVoicePjsipCommand`, `AsteriskMobileVoiceTransport`, `RegisterMobileDeviceAction`, `extensions.conf` (from-trunk → `ark-mobile-inbound-ring`), static dialplan placeholder, `sync-mobile-voice-to-asterisk.sh`, `http.conf` WSS TLS, `docker-compose.yml` (8089 + cert mount), RUNBOOK, `MobileVoiceSessionTest`, ark-mobile `in_app_call_overlay.dart`.

**Reason:** Mobile voice is useless on the floor until Asterisk actually rings registered mobile legs on inbound PSTN and operators can sync generated config without hand-editing six paths.

**Architecture impact:** Device register / voice-session now regenerates both PJSIP endpoints and inbound dialplan (`Dial(PJSIP/101&PJSIP/81xx&…)`). Production script copies artifacts from arksms storage into Asterisk static config and reloads pjsip + dialplan. WSS TLS appended in entrypoint when `/data/ark-shared/asterisk/certs/` is mounted.

**Outstanding questions:** Copy/sync script must run on production after first mobile device registers (or add to deploy pipeline). Cert files at `/data/ark-shared/asterisk/certs/` must exist before Asterisk starts with TLS enabled.

### 2026-06-28 — ARK Voice mobile inbound continuity

**PR:** (local — inbound call ownership + push)

**Files changed:** `MobileInboundCallProjection`, `NotifyMobileInboundCallAction`, `ProcessIncomingCallAction`, `MobileCommsHubProjection`, `MobileTelephonyVoiceAnswerController` (`claim_active`), `MobileInboundCallTest.php`, ark-mobile push/deep-link/voice-answer wiring, `ensure-asterisk-wss-certs.sh`, `mobile-voice-floor-checklist.md`, Android WebRTC ProGuard rules.

**Reason:** PSTN inbound must ring mobile extensions, surface on Comms hub while ringing, claim call ownership when advisor answers, and wake the app via FCM when backgrounded.

**Architecture impact:** Inbound Asterisk events targeting mobile extensions (8100–8999) send urgent FCM with `deep_link: inbound_call`. Comms hub exposes `active_inbound_call`. Mobile answers with `claim_active: true` without Twilio custom params.

**Outstanding questions:** VoIP push (iOS CallKit) not built — FCM is best-effort wake only. Floor checklist in `infra/coolify/asterisk/mobile-voice-floor-checklist.md`.

### 2026-06-28 — ARK Voice mobile Comms hub inbound UI + production deploy script

**PR:** (local — comms inbound banner + deploy orchestration)

**Files changed:** ark-mobile: `MobileActiveInboundCall`, `ActiveInboundCallBanner`, `comms_hub_screen.dart`, `home_shell.dart` (push → refresh WSS session), iOS `audio` background + mic copy, Android ProGuard WebRTC. arksmsv2: `deploy-mobile-voice-production.sh`, unit test `MobileVoiceInboundDialplanSyncTest`, `MobileApiTest` hub structure, RUNBOOK + floor checklist updates.

**Reason:** Advisors need a visible Comms recovery path when PSTN rings their mobile extension — not only the SIP overlay. Production needs a single script to sync stack, certs, and mobile Asterisk artifacts.

**Architecture impact:** Comms hub polls faster refresh voice session when `active_inbound_call` is present. Push `inbound_call` opens Comms hub and warms ARK Voice registration before answer.

**Outstanding questions:** Run `deploy-mobile-voice-production.sh` on production when ready to ship backend + asterisk together.

### 2026-06-28 — Operations Briefing (Phase 3)

**PR:** (local — narrative morning briefing projection)

**Files changed:** `app/Ark/Operations/Briefing/` (`OperationsBriefingProjection`, `BriefingStoryComposer`, `BriefingEvidenceResolver`, `BriefingRepository`, DTOs, explainable rules), `config/briefing.php`, `OperationsBriefingController`, `resources/views/operations/briefing.blade.php`, route `operations.briefing`, nav link, `tests/Feature/Operations/OperationsBriefingTest.php`.

**Reason:** Ship the first narrative projection that composes existing operational projections and explainable attention rules to answer *"What deserves my attention today?"* — not a dashboard.

**Architecture impact:** Disposable projection layer; no new authority. Rules consume `EndOfDayReportProjection`, `OperationalReportRangeMetrics`, `ShopBehaviorPulse`, `ConversationAttentionCandidateBuilder`, Growth sessions, `CallSession`, and communication events. Every item exposes What/Why/Show me via `BriefingConfidence` + expandable evidence. Rebuilds on each GET.

**Outstanding questions:** Floor observation — does advisors open `/app/briefing` before Attention without being told to? Revenue spike/drop on quiet days may need prior-week baseline tuning after notebook review.

### 2026-06-29 — Growth Sprint 1: Opportunity Queue + Search Console ingest + SEO cutover

**PR:** growth/sprint-1-opportunity-queue

**Files changed:** `database/migrations/2026_06_30_100000_growth_opportunity_queue_and_search_snapshots.php`, `app/Ark/Growth/Opportunities/` (rules, projection, repository, enums), `app/Ark/Growth/Models/GrowthOpportunity.php`, `app/Ark/Growth/Integrations/SearchConsoleIngestService.php`, `SearchMetricsAggregator.php`, `FixtureSearchConsoleAdapter.php`, `SyncSearchConsoleCommand.php`, `app/Ark/Growth/PublicSurface/PublicMarketingUrl.php`, `SeoEngine` extensions, removed `PublicSeo.php`, growth routes/views/nav, `config/growth.php`, `tests/Feature/Growth/GrowthOpportunityQueueTest.php`, public SEO test updates.

**Reason:** Sprint 1 success metric — ARK can name the next five highest-value SEO tasks for LugsNPlugs with evidence. Growth lands on Opportunities, not dashboard. Deterministic closed loop: Search Console → Opportunity → Publish → Measure → Validate.

**Architecture impact:** First Growth improvement loop. Immutable daily GSC snapshots (`report_date`); deterministic create/improve rules with estimated lift, effort, and growth lifecycle status. Single public SEO engine (`SeoEngine` + `PublicMarketingUrl`); legacy `PublicSeo` removed. No AI scoring.

**Outstanding questions:** `Published → Measuring → Validated` automation (before/after GSC deltas + revenue) ships in a follow-up once first pages publish. Real Google Search Console adapter still returns empty — fixture path is operational for dev/test.

### 2026-06-30 — Growth content execution gate: acceptance criteria + content builder

**PR:** growth/content-execution-gate

**Files changed:** migration (`acceptance_criteria`, `content_draft`), `OpportunityAcceptanceCriteriaTemplate`, `OpportunityAcceptanceEvaluator`, `ContentBuilderSchema`, `ContentBuilderProjection`, build/content controllers, queue posture counts, validated badge, publish gate, `build.blade.php`, tests.

**Reason:** Before publishing, "Published" must mean something. Each opportunity gets explicit done-means checklist; content builder is a structured checklist (not WYSIWYG). Validated badge + posture counts tell the continuous improvement story.

**Architecture impact:** Execution layer on top of opportunity queue. Builder-derived criteria auto-satisfy from draft completeness; external checks (index, mobile score) remain manual until instrumentation earns automation. Publish transition blocked until all required criteria pass.

**Outstanding questions:** First real page (Wheel Bearing Noise) is human content — ARK gates and measures; it does not write copy. Report card population waits on 60-day measurement window after publish.

### 2026-06-30 — Ship first Growth page: Wheel Bearing Noise (LugsNPlugs)

**PR:** content/wheel-bearing-noise-colorado-springs

**Files changed:** `config/common_problems.php` (tier-1 page), `CommonProblemRegistry.php`, `show.blade.php` (optional FAQ/confusion/repair sections), `SeoEngine.php` (FAQ JSON-LD from page faqs), `WheelBearingNoisePageTest.php`, queue test updates.

**Reason:** Stop improving the machine; ship the work the queue asked for. First live content execution page for Colorado Springs wheel bearing searches.

**Architecture impact:** Page lives in existing Common Problems authority — no CMS, no new publish pipeline. Queue correctly stops recommending create-page for queries that now match registry. Confidence metric deferred until post-60-day queue review.

**Outstanding questions:** Run `growth:sync-public-content` after deploy. Mark opportunity Published/Measuring in ARK when ready to start the 60-day clock. Next page comes from the queue, not the roadmap.

### 2026-07-01 — Growth maintenance automation (operators never run artisan)

**PR:** growth/maintenance-automation

**Files changed:** `growth_sync_tasks` migration, `GrowthMaintenancePipeline`, sync task recorder/projection, `PublicContentRegistrySyncService`, nightly/publish/content jobs + events, scheduler entry (`02:00` shop TZ), opportunities sync status UI + rebuild action, artisan commands refactored to `[Maintenance]` delegates, tests.

**Reason:** If an operator must remember `growth:sync-*` commands, Growth is not operationally trustworthy. Business actions (publish, edit content) trigger system actions (registry sync, SEO audit, queue recalc). External integrations sync on schedule.

**Architecture impact:** Operators perform business actions; ARK performs system actions. Artisan `growth:*` remains for developers, CI, and disaster recovery only. Opportunity Queue becomes self-maintaining via nightly pipeline + publish/content event hooks.

**Outstanding questions:** Operations Briefing rebuild is stubbed (skipped status) until digest integration. GBP sync records skipped until adapter is configured. Report cards still wait on 60-day measurement after publish.

### 2026-06-27 — Today surface: role-aware front door

**PR:** operations/today-surface-v1

**Files changed:** `app/Ark/Operations/Today/Surface/*` (projection, lens composers, controller), `resources/views/operations/today/index.blade.php`, `routes/web.php`, `StaffFrontDoor`, operations nav (`Briefing` → `Today`), `OperationsBriefingProjection::contextFor()`, `tests/Feature/Operations/TodaySurfaceTest.php`, briefing/advisor front-door test updates.

**Reason:** Products answer what's true; Today answers what should I do next. One surface (`/app/today`) composes Operations, This week (Growth), and Voice for owners; advisor and technician lenses get their own section mix. Attention before Yesterday; every recommendation is a deep link with a human owner.

**Architecture impact:** Today is a read-only projection layer — no new authority. Briefing redirects to Today; login lands on Today for all staff roles (including technicians). Modules remain products; Today is the first projection, not a product.

**Outstanding questions:** Competitive progress stats, playbook learning, and broader continuous-improvement domains stay deferred. Observe floor adoption: do advisors click recommendations instead of hunting modules?

### 2026-06-27 — Recommendation Lifecycle v1: Estimate Follow-up

**PR:** operations/today-recommendation-lifecycle-v1

**Files changed:** `app/Ark/Operations/Today/Lifecycle/*` (contract, `EstimateFollowUpLifecycle`, completion recorder/listener, history projection), `today_recommendation_completions` migration, Today composers wired to lifecycle, `EstimateFollowUpBriefingRule` delegates to lifecycle, Today view completed-yesterday section, tests.

**Reason:** Today should feel like a living work queue, not a report. Completion belongs to authority — communication logged, approval recorded — Today observes and retires recommendations. One lifecycle contract; Estimate Follow-up is the first implementation.

**Architecture impact:** `TodayRecommendationLifecycle` interface: `candidates()`, `completionAuthority()`, `completionEvents()`, `retireReason()`, `retirementFromOperationalEvent()`. `OperationalEvent::created` listener records completions when pressure resolves. Today never owns Done — operations records truth; Today projects what still needs attention plus completed-yesterday memory.

**Outstanding questions:** Interrupt-driven partial refresh (recommendation slides away without reload) after floor proof. Next lifecycles: Customer Arrival, Operational Handoff, Vehicle Pickup.

### 2026-06-27 — Recommendation resolutions + Parts Arrival lifecycle

**PR:** operations/recommendation-resolutions-parts-arrival

**Files changed:** Renamed `today_recommendation_completions` → `recommendation_resolutions`; platform classes under `App\Ark\Operations\Recommendations\` (`RecommendationResolution`, `RecommendationResolutionRecorder`, `RecommendationHistoryProjection`, `RecommendationWorkCompletionListener`); `PartsArrivalLifecycle` (second lifecycle); composers dedupe stale waiting-parts briefing when parts arrival is active; tests.

**Reason:** Resolution history is a platform primitive — Briefing, Attention, Growth, Voice, and mobile will all generate recommendations. Parts Arrival is daily, high-frequency shop pain: "Did the parts come in?" should become "Receive parts" on Today until inventory authority records receive.

**Architecture impact:** One authority (procurement/`part_received` events), multiple projections (Today advisor/owner waiting parts, workboard, tech when unblocked). Completion retires recommendation without Today owning Done. Outcome taxonomy (Approved, Installed, Validated) deferred until floor observation.

**Outstanding questions:** Customer Arrival, Operational Handoff, Vehicle Pickup — same contract. Impact summary on Yesterday ("5 approvals, 3 parts received") after outcome vocabulary earns evidence.

### 2026-06-27 — ARK Website Doctrine v2 (one customer app, three applications)

**PR:** docs/ark-website-doctrine-v2 + customer-shell-unification

**Files changed:** Doctrine docs/rules; `x-customer.shell` as sole customer layout; `lead-intake` delegates to shell; public views stripped of duplicate chrome; customer nav/breadcrumb vocabulary (Sign In, not Portal); removed `layouts/portal.blade.php`; tests updated.

**Reason:** Lock simplifying mental model — one LugsNPlugs customer application with two auth states; three ARK applications total (customer, operations, admin). Sign in unlocks capability; never "portal" in customer UI. One shell reduces drift and maintenance.

**Architecture impact:** Anonymous and authenticated both render through `x-customer.shell`. `x-portal.app` and `x-public.lead-intake` are thin delegates. Customer copy: Sign In, My Account, My Vehicles. Enforceable contract in `docs/engineering/STANDARDS.md` — no customer page bypasses shell without documented exception.

**Status:** **Locked.** Customer application architecture is stable.

---

### 2026-07-01 — Milestone repoint: Communications Workspace Sprint v1

**PR:** docs/communications-workspace-sprint-v1

**Files changed:** `docs/communications/communications-workspace-sprint-v1.md`, `docs/engineering/CURRENT_MILESTONE.md`, `docs/engineering/ACTIVE_PR.md`, `docs/engineering/STANDARDS.md`.

**Reason:** Lead conversion sits between Growth and revenue. One vertical slice — website lead → Needs Attention → one composer (SMS/phone/email) → same thread → estimate → approve → RO → Today retires. Not Communications v2 or generic CRM clone.

**Metric:** Median first-response time (lead submitted → advisor first outbound). Target ≤ 12 minutes.

**Philosophy:** Communications = reactive primary workspace; Today = strategic. Growth measures demand; Communications converts it.

**Architecture impact:** Doctrine/scope only. Conversation authority unchanged. Email = ConversationMessage transport. Transport-agnostic for slice (Twilio OK).

**Status:** Active sprint. Stop after floor definition of done.

### 2026-07-01 — Sprint success tightened (advisor effort + turn rhythm)

**PR:** docs/communications-workspace-sprint-v1 (revision)

**Reason:** Lock measurable success — advisor effort ≤30s (one click → composer → send), median first-response ≤12min (one KPI). First screen = Needs Attention ("who needs a reply?"). Turn-based: waiting for customer vs advisor turn — not resolved/unread. Quick Replies yes; AI auto-replies no.

**Architecture impact:** Turn state is projection on conversation — not new authority. Full lead→revenue path is stretch after reply proves out.

### 2026-06-27 — Communications Workspace Sprint v1 slice (website lead → Needs Attention)

**PR:** (pending)

**Files changed:** `ShopTurnAttentionQueue`, `ShopTurnAttentionPresenter`, `ConversationTurnReason`, `CommunicationsQuickReplyTemplates`, `CommunicationsFirstResponseMedian`, `CommunicationsFirstResponseMedianCommand`, `RecordLeadFirstContactAction`; `ConversationRecorder` (website lead posture + first-contact hook), `CommunicationsQueueResolver`, `CommunicationsWorkspaceProjection`, `CommunicationsWorkspaceContextBuilder`, `StaffFrontDoor`; composer partials + CSS; `tests/Feature/Communications/CommunicationsWorkspaceSprintTest.php`; queue/front-door test updates.

**Reason:** Ship sprint v1 vertical slice — website leads project into turn-based Needs Attention, one-click thread + SMS composer with quick replies, advisor lands on `/app/communications/attention`, first-response measured via `leads.first_contacted_at`.

**Architecture impact:** Needs Attention composes calls + unread SMS + **shop-turn conversations** (`waiting_on = shop`). No parallel inbox authority. Mark-read no longer clears shop-turn rows (turn clarity over unread badges). Median KPI: `php artisan ark:communications:first-response-median`.

**Outstanding questions:** Email transport from workspace composer; floor timed 30s effort test; Today recommendation retire on portal approve (stretch remainder).

### 2026-06-27 — Communications sprint stretch (same thread → estimate)

**PR:** (pending)

**Files changed:** `CommunicationsLeadConversationRedirect`, `SendConversationEstimateLinkController`, `SendEstimateLinkAction` (optional conversation), `CommunicationsWorkspaceContextBuilder` (lead RO/customer), `CommunicationsMessageQueuePresenter` (Customer replied), composer quick replies on customer path, route `operations.communications.conversations.send-estimate`.

**Reason:** After first reply, advisor stays in one thread — lead URLs redirect to conversation; linked RO enables Send Estimate on the same `conversation_id`. `ConversationLeadResolver` + reconciler thread reuse fix duplicate SMS leads breaking **Customer replied** labels.

**Architecture impact:** No parallel estimate messaging subsystem. `SendEstimateLinkAction` optionally records on the active conversation. Turn labels prefer contacted lead on shared conversation threads.

**Outstanding questions:** ~~Today recommendation retire on portal approve~~ — fixed 2026-06-27 (see next entry).

### 2026-06-27 — Estimate follow-up Today retirement (stretch complete)

**PR:** (pending)

**Files changed:** `EstimateFollowUpLifecycle` (`whereKey` aggregate lookup, `status->is()` for catalog-backed workflow status, `completionEventFromLifecycle` arity fix), `PartsArrivalLifecycle` (`whereKey`); `TodayRecommendationLifecycleTest.php`, `CommunicationsWorkspaceSprintTest.php` (conversation-thread + lifecycle event retirement).

**Reason:** Same thread → estimate sent → customer approves must retire **Call …** on Today. Pressure detection compared `RepairOrderWorkflowStatus` to `RepairOrderStatus` enum (`!==`) and always returned inactive; lifecycle retirement also looked up RO by `repair_order_id` instead of primary key.

**Architecture impact:** Today retirement stays on existing `RepairOrderLifecycleChanged` → `RecommendationWorkCompletionListener` path — no new Today authority. Portal approve flows through `AdvanceRepairOrderAfterCustomerAuthorizationAction` → same lifecycle event.

**Outstanding questions:** Full portal HTTP authorize test still flaky in CI shell (pre-existing); HTTP-heavy sprint tests may need env isolation.

### 2026-06-30 — Website admin Phase 1 (owner product shell)

**PR:** (pending)

**Files changed:** `app/Ark/Website/**`, `routes/website.php`, `WebsiteServiceProvider`, `bootstrap/providers.php`, `resources/views/website/**`, nav in `components/operations/app.blade.php`, moved public surface form from shop settings, redirects from `?section=public-surface`, doctrine updates (`ark-website-admin-v1.md`, `ark-website-admin.mdc`), `tests/Feature/Website/WebsiteAdminTest.php`, updated `PublicSurfaceSettingsTest.php`.

**Reason:** Owners think “manage my website,” not Shop Settings + Growth. Phase 1 adds Admin Platform **Website** product (Manage + Performance) without new authority — re-homes `PublicSurfaceSettings`, summarizes via projections, deep-links to Growth.

**Architecture impact:** Presentation/publishing UX only. `PublicSurfaceSettings` authority unchanged. Performance cards are read-only projections (`WebsitePerformanceProjection`, health checklist, publish queue). Doctrine rule: **Website never explains why** — Growth owns analysis.

**Outstanding questions:** Navigation/footer editor when floor earns it; common-problem editing surface vs Growth content registry link only.

### 2026-07-03 — VVX inbound: desk-first dialplan + TCP SIP for NAT

**PR:** (pending)

**Files changed:** `MobileVoiceInboundDialplanSync` (desk 101/102 first, mobile second phase), `CommunicationDeviceProvisionConfigBuilder` (`TCPOnly`), `infra/coolify/asterisk/config/templates/pjsip.conf` (transport-tcp, qualify on desk AORs), `docker-compose.yml` (5060/tcp), `ensure-coolify-asterisk-mobile-voice.sh`, `reconcile-voice-production.sh`, `MobileTelephonyVoiceRegistrationEventController`, dialplan test.

**Reason:** Inbound PSTN reached Asterisk but INVITEs to VVX UDP contact (`71.196.200.50:5060`) got zero SIP response — shop NAT. Simultaneous dial with unregistered mobile legs added noise. TCP registration path fixes cloud-PBX + NAT desk phones.

**Architecture impact:** Inbound dialplan authority unchanged (generated sync); desk phones provisioned TCP to voice host. Post-deploy `reconcile-voice-production.sh` re-syncs stack + dialplan + recordings mount.

**Outstanding questions:** VVX must re-download provisioning config and reboot once. Router UDP forward remains fallback if shop stays on UDPOnly.

### 2026-07-03 — Rollback: desk phones UDP (Regression Desk Transport TCP)

**PR:** (pending)

**Regression ID:** Desk Transport TCP

**Previous production:** VVX → UDP (RUNBOOK, stable registration)

**Changed (2026-07-03 AM, ARK Phone work):** VVX provisioning `UDPOnly` → `TCPOnly`; PJSIP endpoints 101/102 `transport-tcp`

**Observed:** REGISTER → 401 → REGISTER → 200 OK; contact removed ~30–60s later (`due to shutdown`); no `Expires: 0`; inbound failed

**Rollback:** VVX → `UDPOnly`; PJSIP desk endpoints → `transport-udp`

**Expected:** Stable registration >5 minutes; then inbound/outbound/hold/transfer certification

**If UDP still unstable:** transport eliminated as root cause — investigate Poly provisioning, NAT/router, SIP keepalive, registration interval, firmware, packet capture. Do not flip transport again without floor evidence.

**Endpoint transport contract:** VVX desk = UDP; mobile WebRTC = WSS. Do not unify.

**Files changed:** `PolyPhoneProvDeviceConfigBuilder`, `CommunicationDeviceProvisionConfigBuilder`, `infra/coolify/asterisk/config/templates/pjsip.conf`, `EndpointProvisionTest`, `RUNBOOK.md`, `reconcile-voice-production.sh`

**Architecture impact:** Desk-only rollback. Twilio, mobile WSS, dialplan, passwords, recording unchanged.

**Outstanding questions:** Floor cert after VVX reboot — confirm phone Transport=UDP and `pjsip show endpoint 101` → `transport-udp`; SIP logger shows REGISTER over UDP.

### 2026-06-30 — Scope summary preserves front/rear when vocabulary collapses

**PR:** (pending)

**Files changed:** `ScopeEntrySummaryResolver`, `RepairOrderConcernStoreController`, `ScopeEntryVocabularyQuery`, `ark-scope-entry-intake.js`, scope intake tests.

**Reason:** Choosing "Rear brakes" from shop vocabulary (or Enter on top match) stored canonical **Brakes** as the scope headline — axle position lived only in customer wording subline.

**Architecture impact:** Operational scope summary keeps position qualifiers; concept linking unchanged. Vocabulary alias rows surface the more specific observed label when it matches the query.

**Outstanding questions:** None.

### 2026-07-05 — ARK Companion Milestone 3: Calling (Twilio-native)

**PR:** (pending)

**Files changed (ark-mobile):** `TwilioVoiceTransport`, `VoiceTransportBridge`, `ArkVoiceDialer` transport routing, `voice_dialer_bootstrap`, `CompanionCallPlacer`, `CompanionActiveCallScreen`, `CompanionCallKeypad`, `companion_in_app_answer`, `companion_call_launch`, thread/inbox/search/RO/customer/call-detail call sites, `pubspec.yaml` (`twilio_voice`).

**Files changed (arksmsv2):** `docs/engineering/CURRENT_MILESTONE.md`, `ACTIVE_PR.md`, `IMPLEMENTATION_LOG.md`.

**Reason:** Milestone 3 — OpenPhone-grade calling in Companion. Backend already projects Twilio Client (`transport: twilio`); mobile had to adopt Twilio SDK instead of SIP-only `ArkVoiceTransport` for in-app calls.

**Architecture impact:** Companion uses dedicated call placer + active/incoming screens (not legacy `InAppCallOverlay`). Transport selected by server shell posture. Post-call note saves internal `ConversationMessage`; return-to-thread via deep link. No transfers, queues, or station UX.

**Outstanding questions:** iOS/Android device certification with Twilio push + CallKit; macOS is UI preview only.

### 2026-07-05 — ARK Companion Milestone 4: Voicemail / Recordings

**PR:** (pending)

**Files changed (ark-mobile):** `CompanionCallMediaPlayer`, `CompanionVoicemailPlayerSheet`, refactored `CompanionCallRecordingPlayButton`, thread timeline inline player, Calls & VM rows with counts + missed·VM grouping, call detail player, `ConversationActivity` metadata accessors.

**Files changed (arksmsv2):** `MobileCallsLibraryProjection` (`analysis_summary`, `needs_voicemail_attention`), `CallLibraryQuery` (`unhandled_voicemail` count), `CallSessionEventMapper` (duration + summary metadata), engineering + mission docs (floor certification + quality bar).

**Reason:** Milestone 4 — OpenPhone-grade voicemail/recording playback. Inline player in thread; expanded sheet with scrubbing and speed; AI summary when call analysis exists.

**Architecture impact:** Playback remains Sanctum-proxied `/api/mobile/calls/{id}/recording`. No parallel media authority. Mark handled reuses existing CallSession `worked_at` path.

**Outstanding questions:** Waveform deferred; download/share optional later. Floor cert on device with real Twilio voicemail artifacts.

### 2026-07-05 — ARK Companion Milestone 5: Advisor Awareness

**PR:** (pending)

**Files changed (arksmsv2):** `MobileAdvisorBriefProjection`, `MobileAdvisorBriefSuggestionFeedbackController`, `ConversationProjection` (`advisor_brief` payload), `ConversationActivityPresenter` (`advisor_brief` activity type), mobile API route.

**Files changed (ark-mobile):** `CompanionAdvisorBrief`, thread screen integration, `AdvisorBrief` models, suggestion feedback on send, removed `CompanionAiSummaryCard`.

**Reason:** Milestone 5 — replace generic AI summary with Advisor Brief. Operational awareness from existing nudge/analysis/observation authorities. One recommendation, promises, one-tap editable suggested replies. Learning via `advisor_nudge_responses`.

**Architecture impact:** Brief is projection-only on thread API. No chat authority. AI remains implementation detail behind brief fields.

**Outstanding questions:** Floor cert on threads with/without RO context; tune commitment extraction from shop messages.

### 2026-07-05 — ARK Companion Milestone 6: Operational Context

**PR:** (pending)

**Files changed (arksmsv2):** `MobileOperationalContextProjection`, `ConversationProjection` (`operational_context` payload), `MobileCompanionDeepLink::repairOrderInspection`.

**Files changed (ark-mobile):** `CompanionOperationalContext`, `OperationalContext` models, thread screen integration; removed legacy `CompanionThreadContextCards`.

**Reason:** Milestone 6 — operational situational awareness inside the thread. Progressive disclosure from existing RO, estimate, inspection, parts, appointment, and warranty authorities. Companion informs; deep-link to operate.

**Architecture impact:** Operational context is projection-only on thread API. Visibility rules prevent empty dashboard cards. Information hierarchy: Identity → Conversation → Advisor Brief → Operational Context.

**Outstanding questions:** Floor cert across RO lifecycle states; parts ETA when vendor fields sparse.

### 2026-07-05 — ARK Companion Milestone 7: Production Feel (started)

**PR:** (pending)

**Files changed (arksmsv2):** `MobileAwarePushCopy`, push dispatchers, `MobileGlobalSearchProjection` (concern/RO matches), `docs/companion-v1/09-production-feel.md`.

**Files changed (ark-mobile):** `CompanionHaptics`, `CompanionEmptyState`, lifecycle-aware inbox polling, thread keepAlive + prefetch, search/empty state improvements.

**Reason:** Milestone 7 renamed from Polish — production feel is what makes advisors choose ARK over OpenPhone. Aware notifications, performance discipline, useful empty states, subtle haptics.

**Architecture impact:** No new authority. Push copy and UX infrastructure only.

**Outstanding questions:** Offline message outbox; motion polish from two-week pocket observation; lifecycle event pushes (parts arrived, estimate approved).

### 2026-07-05 — GSC legacy URL redirects → Common Problems

**PR:** (pending)

**Files changed:** `config/public_legacy_redirects.php`, `PublicLegacyRedirect.php`, `PublicCommonProblemShowController.php`, `PublicSeoTest.php`, `docs/deployment/public-surface-seo-v1.md`.

**Reason:** GSC still shows top traffic on old Botble `/blog/*` and root concern slugs. Targeted 301s now land on matching `/common-problems/{slug}` pages instead of the homepage.

**Architecture impact:** Redirect config only; resolver reads `CommonProblemRegistry` for slug validation. Invalid Botble slugs under `/common-problems/` redirect via show controller fallback.

**Outstanding questions:** Re-crawl timing in GSC; remove stale `sitemap_index.xml` property manually in Search Console.

### 2026-07-06 — Explainable Recommendations: authority-sourced audit channels

**PR:** (pending)

**Files changed:** `SeoAuditEngine`, `SeoAuditFinding`, `SeoAuditAuthoritySource`, `SeoAuditChannel`, `SeoAuditVerification`, `RuntimeSeoAuditFinding`, structural analyzers (`CtaConsistency`, `Footer`, `TrustChips`, `ProblemAuthorityTemplate`, `ProblemAuthorityDepth`, `PublicSeoMetadata`, `Schema`), runtime analyzers retagged, `CommonProblemAuthorityWordCount`, `PublicContentRegistrySyncService`, `CommonProblemFormCopy::SUBMIT_LABEL`, `resources/views/growth/audit.blade.php`, `docs/growth/explainable-recommendations-v1.md`, `tests/Feature/Growth/GrowthSeoAuditStructuralTest.php`. Deleted registry-guessing analyzers (`ThinContent`, `MissingTitle`, `MissingDescription`, `DuplicateTitle`, `MissingSchema`).

**Reason:** Audit was deriving truth from a potentially stale `GrowthContent` registry — projection acting as observer. Redesigned around declared authority sources: structural checks read configuration/authorities instantly after deploy; runtime checks require crawl metadata; search stays on Opportunities (Search Console).

**Architecture impact:** Each finding exposes authority source, channel, pass/fail, and evidence (What / Why). CTA, footer, problem template, and authority word count no longer depend on HTML crawl or stale registry. `body_word_count` sync uses `CommonProblemAuthorityWordCount`.

**Outstanding questions:** Nightly crawl enrichment still needed for broken links/alt/canonical runtime findings; optional `APP_DEPLOY_REF` for deployment label on production images without `.git`.


### 2026-07-11 — Open House kiosk ACES co-branding

**PR:** (deploy via production)

**Files changed:** `OpenHouseEventSeeder.php`, `EventKioskProjection.php`.

**Reason:** Co-brand July 11 Open House kiosk with Auto Care Experience Solutions (ACES) — partner name, logo, spotlight, headline/subheadline, tips, and idle “With ACES” feature card.

**Architecture impact:** Projection-only / seeder content. No new authority.

**Outstanding questions:** Re-seed production Open House event row if existing DB still has placeholder partner copy.

### 2026-07-11 — Open House kiosk home redesign (LugsNPlugs × ACES signup)

**PR:** (deploy via production)

**Files changed:** `kiosk.blade.php`, `kiosk-check-in.blade.php`, `ark-event-kiosk.js`, `EventKioskProjection.php`, `OpenHouseEventSeeder.php`, `EventsKioskTest.php`.

**Reason:** Redesign Open House kiosk home for signup-first: LugsNPlugs left, form center always visible, ACES right. Removed schedule rail, touch-to-begin CTA, and rotating feature cards. Giveaway is two ACES class tickets; copy matches newsletter + drawing intent.

**Architecture impact:** Projection adds `host_highlights` / `partner_highlights`; idle screen layout only. No new authority.

**Outstanding questions:** None — production event row updated via tinker for giveaway/subheadline/marketing text.

### 2026-07-16 — Test-suite health + hot-path query reduction

**PR:** (pending — Sprint 1 follow-up)

**Files changed:** `ApplyInspectionTemplateAction`, `DefaultInspectionTemplateCatalog`, `SchemaPresence` (new), `CommunicationWorkboardProjection`, `CommunicationsWorkspaceProjection`, `ShopTurnAttentionQueue`, `LeadPressure`, `CommunicationsAttentionDedupe`, `CallSessionQueue`, `ShopSettings` (fee label), `LegacyRepairOrderTimeline` (`->is()` fatal), `QueryBudgetTest`, `CommunicationsQueueTest` (helper rename), `MessagingIngressTest`, three unit tests bound to `Tests\TestCase`.

**Reason:** The full suite could not run — duplicate `messagingCustomer()` helpers fataled collection. Once runnable, pre-existing failures surfaced: queue attention rows collapsed calls into snippet-less shop-turn message rows, handled calls left stale shop-turn posture, `5.000% fees` label, `RepairOrderStatus::is()` fatal on non-terminal import statuses, and app-less Eloquent unit tests. Performance: inspect-tab first visit dropped 126 → 51 queries (batched checklist scaffolding), steady-state 30 → 24 (memoized schema guards), zero mutations on revisit.

**Architecture impact:** No new authority. `SchemaPresence` memoizes positive schema checks per process (negative answers re-check, safe mid-deploy). Attention dedupe now prefers the call row over a shop-turn row for the same customer — call rows are the richer projection of the same pressure. `markCallerHandled` / `markCustomerOrPhoneHandled` resync conversation turn after bulk updates that skip model events.

**Outstanding questions:** Inspect-tab GET still materializes the inspection + checklist on first visit (intentional ensure design, now batched); consider moving scaffolding to an explicit write path if composition reports flag it.

### 2026-07-16 — Inbox clear-path follow-up: conversation-first rows, live-call preservation

**PR:** (deploy via production)

**Files changed:** `CommunicationsWorkspaceProjection`, `EnsureAdvisorCommsCleared`, `list-row.blade.php`, `CommunicationsAttentionTuningTest`, `CommunicationsCallLibraryTest`, `CommunicationsWorkspaceOutboundCallTest`, `MissedCallRescueTest`.

**Reason:** Follow-up to the Mark handled clear path. Needs-attention call rows for a phone with an open conversation now thread into that conversation row (one row per relationship), except live ringing/answered calls which stay call rows — the interrupt state is the point. Owner call-intelligence routes exempted from the comms attention gate. List rows prefer the message preview over status labels so the list reads like an inbox. Test fixes: valid Twilio recording SID fixture, business-hours travel for missed-call rescue copy, `firstOrCreate` for auto-created conversations, conversation-first composer expectation.

**Architecture impact:** No new authority. The left list is a relationship list — call pressure projects onto the conversation row when one exists; `conversationKeyForCallSession` refuses to remap live calls.

**Outstanding questions:** 10 pre-existing failures in `CommunicationsShopWorkspaceTest` / `DiscoveredDeviceAssignTest` (device provisioning surface — stale copy assertions and `VOICE_SIP_REGISTRAR` config in tests) predate this work and need a separate device-surface pass.

### 2026-07-16 — Job Board rebuilt as Tekmetric-style kanban (Estimates / Work in Progress / Completed)

**PR:** (deploy via production)

**Files changed:** `home.blade.php`, `home-card.blade.php`, `home-column.blade.php`, `AdvisorHomeCardSurface`, `AdvisorHomeCardSurfaceProjection`, `app.css`, `tailwind.config.js`; deleted `home-attention-card.blade.php` / `home-attention-zone.blade.php`; test updates in `AdvisorHomeBoardTest`, `AdvisorHomeAttentionBoardTest`, `WorkboardTriageTest`, `AdvisorTodayTest`, `OperationsHomeTest`, `RepairOrderLifecycleQueueTest`.

**Reason:** Two row-based iterations of the attention board still did not match what advisors expect from a job board. Rebuilt `/app` as the industry-standard three-column kanban — Estimates, Work in Progress, Completed — with Tekmetric-anatomy cards: solid status chip, tech-initials avatar, RO link + actions, customer name with inline phone, vehicle with icon, total / balance-due block, promise time, labor progress bar, and an "RO created Xd ago" + estimate Viewed/Sent footer sourced from `CommunicationEvent`.

**Architecture impact:** No new authority. `AdvisorHomeCardSurfaceProjection` gained one batched query for latest estimate sent/viewed events per RO (no N+1). Old attention-zone partials and CSS deleted; `WorkboardSwimlaneCatalog` remains the column authority. Lifecycle test event assertion scoped to `repair_order_lifecycle_changed` (front-door landing event on GET is unrelated).

**Outstanding questions:** Card sort within columns is pressure-based; observe whether advisors ask for custom ordering before building it.

### 2026-08-08 — Restore direct RO mileage + adhoc labor override UX

**PR:** (deploy via production)

**Files changed:** `LaborRateOverrideReason`, `repair-order-mileage-inline.blade.php`, `repair-order-labor-authority-fields.blade.php`, `ark-workspace-modal.js`, `workspace-modal.blade.php`, `show.blade.php`, `app.css`, `RepairOrderMileageTest`.

**Reason:** Mileage in/out had been switched into the workspace modal; floor needs click-to-edit again. Flat menu/package labor (e.g. \$199 PPI) failed silently because footer submit bypassed HTML5 required on rate override reason — added Menu / package price reason, amber cue, and modal validation strip.

**Architecture impact:** No new authority. Pricing snapshot override provenance unchanged; one new override reason enum case.

**Outstanding questions:** None — observe whether advisors still need a true flat-dollar line without hours.

### 2026-08-08 — Inspection print path + Repair Portal inspection doorway

**PR:** (local — ship when approved)

**Files changed:** `PortalInspectionPage`, `PortalInspectionPrintController`, `PortalInspectionPdfController`, `RepairPortalInspectionController`, `RepairOrderInspectionPrintController`, `RepairPortalHubProjection`, `RepairPortalAdvertisementProjection`, footer/print menus, portal routes, `inspection-report-print`, tests.

**Reason:** Staff had no PRINT path for completed Standard/PPI inspections. Printed QR from staff preview encoded a 1-hour token that later 404ed. Repair Portal hub advertised Inspection but always showed the empty stub — estimate/doc QR never reached the report.

**Architecture impact:** No new authority. Inspection report projects through `/r/{code}/inspection` (Repair Portal doorway). Staff print QR uses that doorway; token print/PDF QR refuses ephemeral preview tokens.

**Outstanding questions:** Observe whether advisors prefer Simple vs Detailed as the default staff print.

### 2026-08-24 — Shop Glass advisor-work composition

**PR:** (local — floor certification pending)

**Files changed:** `StationDashboardProjection`, `StationGlassDeskProjection`, `StationGlassConfig`, `StationAttentionProjection`, `AppointmentsBoardProjection`, `desk.dart`, `main.dart`, `ro_workspace.dart`, `shop_mode.dart`, Shop Glass widget tests, `AdvisorStationApiTest`.

**Reason:** Replace endpoint-shaped Desk / Today presentation with one advisor-work projection: configured advisor ownership, genuinely shared work, compact shop pressure, and actionable RO rows on the 1920×1080 station. Remove Today from navigation, preserve the full-width navigation fix, and reuse the customer call workspace in Calls. Callback claiming now lists every configured active advisor explicitly; disabled staff are excluded. Station window lock/unlock accurately restores native resizing and title-bar behavior. Save/claim feedback appears briefly at screen center without intercepting input. Advisor lanes collapse around actual work, the shop pulse names the next arrival and compact approval exposure, and Desk pressure is capped at five ranked exceptions with overflow leading to Shop.

**Architecture impact:** Projection-only. Existing advisor tasks, call sessions, attention rows, appointments, repair orders, and financial projections remain authoritative. No new models, tables, workflow states, or write paths.

**Outstanding questions:** Floor-certify at 1920×1080: can Molly and Edward identify owned work, shared work, and shop pressure without opening Shop?

### 2026-08-24 — Common Job estimate-builder language

**PR:** (local)

**Files changed:** `repair-order-concern-work-section.blade.php`, workspace modal host and JavaScript, Common Job settings copy, `RepairOrderBuilderWorkspaceModalTest`.

**Reason:** “Saved Work” sounded like completed work. Estimate Builder now calls reusable shop work a “Common Job” and places it immediately after Labor on the left side of the diagnostic compose row.

**Architecture impact:** None. Internal `saved-work` task names and work-template authority remain unchanged; this is operator language and placement only.

**Outstanding questions:** None.
