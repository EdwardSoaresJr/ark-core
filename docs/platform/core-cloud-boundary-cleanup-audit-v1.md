# ARK Core → Platform/Cloud Boundary Cleanup Audit

**Status:** Phase 1 locked (2026-09-15). Steps 4+ frozen until PartsTech pull-quote proof and RO 1737 reprint. Do not start Phase 2 deletion from this file.  
**Date:** 2026-09-15  
**Scope:** Core `arksmsv2` navigation, Settings, routes, models, migrations, `.env`, provider clients  
**Companion:** [partstech-entitlement-audit-v1.md](partstech-entitlement-audit-v1.md)

Locked architecture used for classification:

| Layer | Owns |
| --- | --- |
| **Core** | Shop OS and operational workflow: customers, vehicles, ROs, inspections, appointments, documents, ledger, estimating, staff, operational preferences, durable shop-local records |
| **Cloud** | Managed provider execution and secrets: credentials, adapters, capture, catalog APIs, telephony provider, recording durability |
| **Platform** | Control plane: entitlements, service administration, hosting, domains, shop/system lifecycle, Cloud-managed service configuration |

Classifications: **KEEP CORE** · **MOVE/PLATFORM** · **MOVE/CLOUD** · **DELETE LEGACY** · **MIXED/SPLIT** · **INVESTIGATE**

---

## Executive verdict

Core still works as the shop OS. The leak is operator Settings and a leftover control-plane namespace: provider secrets, webhook URLs, terminal pairing, and an in-repo Cloud funnel sit next to legitimate shop preferences.

Do **not** delete dual-path provider code while LNP still uses any Core branch (outbound Voice Twilio, Postmark email, PartsTech HTTP). Hide or Hosted-gate the operator UI first. Move secrets after the Cloud replacement is proven. Delete unused cluster/provisioning spine last.

LNP today: Square capture is Platform; outbound Voice and most email are still Core; PartsTech is Core-gated on credentials; company site `autorepairkeeper.com` still renders from Core.

---

## 1. Navigation — Core “Platform” section

**File:** `resources/views/components/operations/app.blade.php`  
**Gate:** `$hasPlatformNav` = production/advisor shell **or** `settings.manage`

| Nav label | Route | Job | Class | If removed today |
| --- | --- | --- | --- | --- |
| Section label **Platform** | — | Implies control plane | **DELETE LEGACY** (label only) | Cosmetic. Rename to **Shop** / fold into System. |
| ARKademy | `ArkademyUrls::staffNavUrl()` → `operations.learn.*` or BookStack | Staff training | **KEEP CORE** | Advisors lose guides / snooze banners. Not a control plane. |
| Voice | `operations.shop.communications` | Stations, devices, provision config | **KEEP CORE** as floor setup; **misnamed and misfiled** | Cannot name stations or add desk phones. Does **not** duplicate Attention / Calls & VM. |

**Communications** (ops rail) is a different product: Attention, Inbox, Calls & VM, History. **KEEP CORE.** Protected by communications call-surfaces lock.

**Cleanup:** drop the Platform heading; move ARKademy next to Settings or under System; rename Voice → **Stations & Phones** (already the Settings sidebar label).

---

## 2. Shop Settings domains

**UI:** `resources/views/operations/settings/shop.blade.php`  
**Controller:** `ShopSettingsPageController`

| Domain | Class | Notes |
| --- | --- | --- |
| Shop Identity | **KEEP CORE** | NAP, logo, timezone. `website` URL is identity, not DNS. |
| Financial Rules | **KEEP CORE** | Labor, tax, fees, deposits, parts matrices, billing classes. |
| Square Payments | **MIXED/SPLIT** | Surface toggles KEEP. Tokens, environment, Core webhook URL, device-code pairing → Cloud. |
| PartsTech | **MIXED/SPLIT** | Catalog/import/matrix stay Core. Shop username/password/API key → Cloud after transport ships. Optional per-advisor seats may stay Core. |
| Communications | **MIXED/SPLIT** | Message-action copy, channel enable flags KEEP. Twilio/Postmark/OpenAI/Meta secrets + webhook URLs → Cloud. Hours/recording/ring already refused when Platform-connected. |
| Stations & Phones | **KEEP CORE** | Same surface as nav Voice. |
| Growth (sidebar link) | **KEEP CORE** | Shop Growth product, not Platform. Shortcut only. |
| Shop Overhead | **KEEP CORE** | |
| Owner Targets & Reporting | **KEEP CORE** | |
| Documents / Disclaimers | **KEEP CORE** | |
| Workflow Defaults | **KEEP CORE** | Status catalog, job-board lanes, inspections, saved work. |
| Operations | **KEEP CORE** | Appointments, bays, CSV import, operational profile. |
| Label Printing | **KEEP CORE** | QZ / Brother. Printing stays Core. |
| Staff | **KEEP CORE** | |
| Dragon Memory | **KEEP** infra; **UI leakage** | See §8. Do not delete `dragon_agent_memories`. |
| Runtime health (footer) | **KEEP CORE** as troubleshooting, not a primary domain | Clocks, Reverb. Not Platform hosting. |

---

## 3. Square Payments

LNP: Platform merchant connected; staff Terminal proven. Core Settings still tells the operator to paste Square tokens and shows `route('webhooks.square')`.

| Finding | Class | Break if removed today |
| --- | --- | --- |
| Toggles: `square_enabled`, `_terminal_`, `_keyed_`, `_portal_pay_`, `_email_pay_` | **KEEP CORE** | Capture surfaces disappear from RO / portal / email. |
| `square_terminal_device_id` | **MIXED/SPLIT** | Self-host Terminal fails. Hosted uses Platform `available_devices` (`CardPresentCaptureProjection`). |
| App ID, access token, location, webhook key, environment | **MOVE/CLOUD** | Self-host Square dies. Hosted capture can continue if `ManagedPaymentsGate` stays on. |
| Settings credential form + “Save Square API credentials here” | **MOVE/CLOUD** (UI) | Operators cannot onboard self-host. Hosted operators get a lying form. |
| `SquareTerminalDeviceCodeController` | **MOVE/CLOUD** | Core pairing dies. Hosted already pairs on Platform. |
| `ArkPaymentsClient` / `ManagedPaymentsGate` | **KEEP CORE** | Hosted card capture stops. |
| RO initiate/poll/cancel + mobile equivalents | **KEEP CORE** | Counter charge UX dies. Ledger stays Core. |
| Portal `/portal/pay/{token}` and `/go/{code}` | **KEEP CORE** | Customer pay links die. Capture may still be Platform. |
| `POST /webhooks/square` | **MOVE/CLOUD** for Hosted; **KEEP** until self-host closed | Self-host terminal completion via webhook stops. |
| `.env` `SQUARE_*` despite `ARK_PAYMENTS_PLATFORM_CAPTURE=true` | **MOVE/CLOUD** | Fallback for self-host / tests. |

**Do not** delete `InitiateSquarePaymentAction` Core branch until self-host is an explicit non-goal. Hosted-gate the Settings UI first.

---

## 4. PartsTech

Full inventory: companion addendum. Summary for this cleanup:

| Finding | Class | Break if removed today |
| --- | --- | --- |
| RO catalog, prepare, Pull Quote, import, matrices, concern attach | **KEEP CORE** | Estimating parts workflow dies. |
| `PartsTechQuoteImporter` / line snapshots / events | **KEEP CORE** | |
| Shop Settings PartsTech form + `PARTSTECH_*` + `shop_settings.partstech_*` | **MOVE/CLOUD** after real transport | Buttons hide (`partsTechCatalogConfigured()`). Floor catalog gone until Cloud ships. |
| Profile per-advisor seats | **INVESTIGATE** | Concurrent cart lock; operational identity, not commercial entitlement. |
| VIN/plate `PartsTechProvider` | **MIXED/SPLIT** | Decode quality drops to NHTSA. Planned Platform `vin` is later. |
| Platform stub `/api/v1/services/parts/*` | **Do not cut LNP over** | Fake oil-filter lines. |

---

## 5. Communications / Voice / Twilio

| Finding | Class | Break if removed today |
| --- | --- | --- |
| Communications workspace, Attention, Calls & VM, conversations, quick reply, screen pop | **KEEP CORE** | Floor comms dies. |
| Message-action copy (tow/Wi‑Fi/pickup) | **KEEP CORE** | |
| `ManagedCommunicationsGate` / `ArkCommunicationsClient` / Fabric ingress | **KEEP CORE** (client) | Hosted SMS send/inbox dies. |
| Core Twilio **messaging** webhooks | **MOVE/CLOUD** (Hosted already rejectable) | Self-host inbound SMS dies. Hosted should already reject Core messaging webhooks. |
| Core Twilio **voice** webhooks (incoming, SIP outbound, status, conference, callback, recording, voicemail, client) | **KEEP CORE until outbound Voice is Platform** | LNP callback / WP820 / SIP outbound still Core. Removing now breaks production Voice. |
| Settings Twilio SID/token | **MOVE/CLOUD** for Hosted SMS; **KEEP** while Core outbound Voice needs shop Twilio | Empty SID/token already broke Hosted callback once; live copy restored from env. |
| Hours / recording / ring when `PlatformConnection::isConnected()` | Already Cloud-gated | Saving those tabs is refused. Local endpoint list is stale on Hosted. |
| Postmark token Settings | **MOVE/CLOUD** | Estimate/invoice email dies until Core→Platform mail client exists. **Missing bridge today.** |
| OpenAI key (call intelligence) | **MOVE/CLOUD** | Transcription/summaries stop. Voice/SMS continue. |
| Messenger page token vs Meta App env | **MIXED/SPLIT** | Page connect is shop; Meta App is platform-managed (already documented in UI). |
| Mobile push enable + device list | **KEEP CORE** | |
| Twilio Client API key / TwiML App SIDs in Mobile tab | **MOVE/CLOUD** | ARK Phone self-host registration breaks. Hosted shows managed. |

---

## 6. Stations & Phones

**Route:** `GET /app/shop/communications` · `CommunicationsShopController`  
**Projection:** `CommunicationsShopProjection`

| Finding | Class | Break if removed today |
| --- | --- | --- |
| Workstation names, locations, operator orientation | **KEEP CORE** | Floor “which station is this” gone. |
| Device registry (MAC, model, assignment, certify) | **KEEP CORE** | Desk phones unmanageable. |
| Generated provision XML / URLs | **MIXED/SPLIT** | Device intent KEEP. SIP registrar/passwords: `VoiceTransportConfiguration` (deploy transport, not operator-typed). |
| `ShopBaseUrl` vs `VoiceTransportConfiguration` | **KEEP CORE** both | Correct HTTP vs SIP split. |
| Twilio account / SIP domain / DID provisioning | **MOVE/CLOUD** | Not this page’s job. |

---

## 7. Legacy control plane in Core

**Namespace:** `app/Ark/Platform/**` — name collision with the real product in `ark-cloud`.

### KEEP CORE — Hosted clients (do not delete)

`PlatformConnection`, `PlatformPairingClient`, `ArkBoxHeartbeatClient`, `BoxRuntimeObservation`, `FabricIngressController`, `VerifyPlatformFabricSignature`, managed comms/payments clients and gates, `ShopBaseUrl`, `VoiceTransport*`, `CoreApplicationOrigin`.  
Commands: `ark:platform-pair`, `ark:platform-heartbeat`.  
Columns: `shop_settings.platform_*` (pairing), not `platform_shops`.

### MOVE/CLOUD — company funnel still LIVE on Core host

`CloudExperienceController`, `CloudAccount`, `CloudShop`, `CloudUrls`, `routes/cloud.php`, `resources/views/cloud/**`.  
Serves `autorepairkeeper.com` from Core Traefik. Foundry (`lugsnplugs.com`) is the shop site.

**If deleted today:** company marketing/trial 404s. Shop website and `/app` survive.

### DELETE LEGACY / MOVE/PLATFORM — unused shop-provisioning spine

| Piece | Notes | Break if removed today |
| --- | --- | --- |
| `GET /app/platform/clusters` `platform.clusters.index` | Master-admin, **not in nav** | Hidden URL + tests. |
| `platform_shops`, `clusters`, `platform_deployments`, `platform_cluster_assignments`, `platform_provisioning_requests` | Written by Cloud funnel + tests, not RO identity | Dropping tables while Cloud funnel still on Core breaks trial/`User::ownedShop()`. |
| `ProvisioningOrchestrator` + StubDns/Email/Stancl/Bootstrap/Coolify | `COOLIFY_ENABLED` default false | Only `ark:provisioning:run` / tests. **Not** LNP Mac deploy. |
| Core `Provisioning/Coolify/*` | Not Coolify UI at `platform.autorepairkeeper.com` | `ark:coolify:check` only. |
| `ReportVoiceInboundPolicyAction` | Deprecated noop | None. |

LNP ship path is Mac `deploy-production.sh`. Core Coolify adapter is not that path.

---

## 8. Website / domains

| Surface | Class | Break if removed today |
| --- | --- | --- |
| `/app/website` Manage / Performance / page media | **KEEP CORE** | Homepage copy, photos, FAQs stop. Content, not hosting. |
| Growth opportunities / SEO health | **KEEP CORE** | Owner Growth product. |
| `shop_settings.website` URL | **KEEP CORE** | Identity metadata. |
| Domain / DNS / Coolify / Traefik lifecycle UI in Website admin | **None present** | Already Platform-shaped. |
| Stub DNS provisioning | **DELETE LEGACY** | Tests/orchestrator only. |
| Foundry shop public site | **KEEP** (separate image) | |

---

## 9. Dragon Memory (product leakage, not Platform)

**Do not delete** `dragon_agent_memories`, store/recall/forget, or chat teach/forget.

| Finding | Class |
| --- | --- |
| Memory table + agent tools | **KEEP CORE** (or later Cloud Dragon entitlement — not this cleanup) |
| Settings section “Hosted Dragon” | **UI leakage** — remove from Operational Domains |
| Query loads latest 200 including `superseded_at` | Exposes forgotten/superseded ledger to shop users |
| Correct / Forget mutating `provenance` with `|settings:correct` | Engineering lifecycle on a shop Settings page |

Shop users should not manage raw memory resources. A later owner surface can say “what Dragon knows” in shop language, without superseded rows.

**If Settings UI removed today:** Dragon still learns in conversation. No RO/comms/Square break.

---

## 10. Provider secrets still in Core

**Encrypted `shop_settings`:** `platform_credential` (KEEP pairing), Twilio tokens, Square tokens, PartsTech password/api_key, Postmark token, OpenAI key, Messenger tokens, Firebase JSON, Growth Google JSON.

**`.env.example` still documents:** `SQUARE_*`, `TWILIO_*`, `POSTMARK_TOKEN`, `PARTSTECH_*`, `OPENAI_API_KEY`, `META_MESSENGER_*`, `COOLIFY_*`, `FIREBASE_*`, `AWS_*`.

Comment already says Hosted Core should not hold Square secrets. The Settings form and env fallback still do.

`ShopIntegrationRuntimeConfig` still mirrors PartsTech into `config('services.partstech.*')`.

---

## 11. Ordered cleanup sequence

Do not skip ahead. Each step assumes the previous did not break LNP floor proof.

1. **Nav/copy only** — **Done.** Rail **Platform** heading retired. Voice → Stations & Phones. ARKademy stays as shop training.

2. **Dragon Memory Settings UI** — **Done.** Removed from Operational Domains. Table, retrieval, and forget/update routes remain.

3. **Square Settings Hosted-gate** — **Done.** When `ManagedPaymentsGate::platformCapture()`, hide credential paste, Core webhook URL, and Core device-code pairing. Capture-surface toggles stay. Core Square SDK path remains.

4. **Do not touch PartsTech credentials** until Cloud `PartsCatalogTransport` is real and entitled. Restoring PartsTech is a Platform v1 feature, not this cleanup. See companion addendum.

5. **Do not remove Core Voice Twilio webhooks or shop Twilio SID/token** until Platform owns outbound PSTN. Hours/recording/ring already Cloud-gated.

6. **Do not remove Postmark Settings** until Core calls Platform mail. Email still Core; UI already blocks if token empty.

7. **Hide leftover control-plane URLs** — ` /app/platform/clusters` can 404 for everyone including master-admin once tests drop. Do not drop `platform_shops` while `routes/cloud.php` still creates shops.

8. **Extract company Cloud funnel** off Core host (`autorepairkeeper.com`) when a real Cloud/Platform public site exists. Until then it is LIVE. Not an LNP shop cleanup.

9. **After Hosted-only is certified for a provider**, delete that provider’s Core Settings secret fields, env fallbacks, and webhook routes. Order: Square (closest), SMS (already Platform send), Mail (after bridge), Voice (last), PartsTech (after Cloud transport).

10. **Delete provisioning stubs / Core Coolify adapter** only after confirming no shop uses `ark:provisioning:run`. Independent of floor workflow.

---

## 12. What not to do in this cleanup

- Cut LNP PartsTech to Platform stub catalog.
- Delete Core Square capture actions or `/go` / portal pay.
- Delete Calls & VM or Communications workspace.
- Put ARKademy on Platform as a control-plane product.
- Drop `platform_shops` while company Cloud still runs on Core.
- Move QZ printing, documents, financial rules, or staff to Platform.
- “Clean” Twilio out of Core while callback still uses shop Twilio REST.

---

## 13. INVESTIGATE (do not guess-close)

| Item | Why |
| --- | --- |
| Whether LNP production `shop_settings` still holds Square tokens alongside Platform merchant | Dual secrets; Settings form may still be writable. |
| Whether leftover Core Square webhooks still fire on Hosted Terminal | Could double-complete or no-op. |
| Per-advisor PartsTech seats after Cloud shop account | Cart lock vs Cloud session. |
| Growth Google service-account JSON | Shop Growth product vs Cloud-held Google. |
| Messenger inbound on Hosted | Page token in Core vs Platform Meta app. |
| Runtime health vs Platform “ARK last checked” | Different clocks; do not merge into Platform overview without a product decision. |

---

## 14. Phase 1 lock

Hosted Square uses `ManagedPaymentsGate::platformCapture()`, not “this Core is hosted.” Platform connected is not a global Cloud-managed flag. PartsTech, mail, SMS, and Voice each need their own managed-service truth before any later secret-surface removal.

Do not start steps 4–10 until PartsTech pull-quote certification and RO 1737 reprint are done. Next cleanup is per-provider: prove the Cloud path, then hide the obsolete Core surface.

### CommunicationsShopWorkspaceTest — pre-existing, not Phase 1

Leave these until a deliberate Communications audit. Do not “fix” them under nav / Square / Dragon work.

| Test | What fails |
| --- | --- |
| `assign_extension_rejects_real_conflict_without_mutating_either_workstation` | Response does not contain `already assigned elsewhere` |
| `shop_communications_surfaces_attention_when_a_device_is_offline` | Asserts `Attention`; page copy is `Needs attention` |
| `device_workspace_tells_operational_truth_without_transport_details` | Asserts `Right now` while idle activity is omitted |
| `device_provisioning_generates_downloadable_config_without_exposing_credentials_in_ui` | `secret-101` appears in serialized config preview |
| `shop_communications_manual_device_entry_is_limited_to_master_admin_support` | `Manual device entry (support)` not always rendered |
| `device_workspace_surfaces_provisioning_observability_for_bench_certification` | Throws when `VOICE_SIP_REGISTRAR` is unset |
| `device_projection_preview_stays_hidden_from_non_master_admins` | Same registrar requirement |
| `person_workspace_lists_assigned_devices_with_name_only_add_form` | `CommunicationDevice` missing after store |
