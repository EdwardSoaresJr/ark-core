# Core–Platform ownership contract

**Status:** Ownership finalized 2026-09-18. Not a license to move, delete, or deploy anything.  
**Live shop:** LugsNPlugs. Core image: `f3e82498`.  
**Map of the repo:** [core-as-shop-sms-chatgpt-brief.md](core-as-shop-sms-chatgpt-brief.md). This file is the contract. Future work obeys it. It is not a task list.

A fallback is not ownership. A locked row is not permission to change code.

**How to read State**

| State | Means |
| --- | --- |
| Locked | Owner is decided. Later work must follow it. The code may still disagree. Disagreement is not a migration. |
| Locked target | Owner is decided, and the current code is known not to match. Still not a migration. |
| Unresolved | Not decided. Do not design around a guess. |
| Open design | Owner is already decided. The mechanism is not. Not a build order. |

---

## 1. Product identities — locked

**Core** is the single-shop operating system. It is the authority for shop records: customer, vehicle, repair order, estimate, inspection, invoice, repair-order ledger, and shop workflow. When Communications is connected, Core owns shop conversation workflow: advisor assignment, attention, waiting, follow-ups, and resolution. A shop must be able to run Core without purchasing Communications or any other managed Platform service. Without that product, Core does not receive Platform communications history.

**Platform** is the control plane, the managed-service provider, and the Communications product. It owns shop creation, hosting, deployment administration, domains, subscriptions, entitlements, provider accounts, and execution of managed services. It owns canonical communications history. Communications is a paid product and must run without a Core installation. It does not become a second repair-order system. When connected to Core, it uses Core’s workflow. It does not create a competing one.

One Hosted shop is one Core installation on its own machine. Core is not multi-tenant. That hosting rule is already accepted in `docs/deployment/ark-complete-hosted-hosting-model-v1.md`.

---

## 2. Integration contract — locked

One authoritative owner per responsibility.

Core may call Platform to perform a managed service. Platform may return a result or deliver an event. Neither side copies the other's records.

- No second repair-order database in Platform.
- No competing conversation workflow in Platform.
- No second payment ledger.
- No new provider-account administration inside Core.
- No second independently editable communications history. Platform holds the canonical history. Core must not keep a competing one.
- Existing Core message and call records stay until an explicit compatibility or projection contract says how they relate to Platform history. This lock does not delete them.
- Standalone Voice and Mobile must not reconstruct communications history from transport events. They read Platform history.

These are constraints. They are not an order to delete the legacy code that still runs LugsNPlugs.

An ownership decision does not authorize an implementation change.

---

## 3. Ownership matrix

### Shop records — locked

| Responsibility | Owner | Current implementation | Discrepancy |
| --- | --- | --- | --- |
| Customer, vehicle, repair order, estimate lines, inspection | Core | `app/Ark/Operations` | None |
| Invoice, amount due, payment recorded on the repair order | Core | Ledger. Capture result is applied in Core after Platform or legacy Square returns it | None |
| Charge button and amount | Core | `InitiatePaymentCaptureAction` starts the attempt and hands capture to Platform | None |
| Staff, permissions, stations as “which desk” | Core | Runtime ACL, Stations & Phones | None |
| Shop behavior (tax, status mileage, note defaults, canned reply text) | Core | Settings | None |
| Job board, appointments, printing | Core | Operations | None |
| Mobile and technician screens | Core | Projections of the same shop | None |

### Payments

| Responsibility | Owner | Current implementation | Discrepancy | State |
| --- | --- | --- | --- | --- |
| Managed card capture, poll, cancel | Platform | `RequestPlatformPaymentCaptureAction` when `ManagedPaymentsGate::platformCapture()` | None on the live LugsNPlugs charge path | Locked |
| Square credentials, webhook secret, reader pairing for a managed shop | Platform | Settings fields hide when Platform capture is on. Token and signature key can still sit in `shop_settings` and `.env` | Stored fallback is not administration. Do not add credential UI | Locked |
| Reader list for managed capture | Platform | `CardPresentCaptureProjection` reads Platform devices when capture is managed | None | Locked |
| Whether this shop allows online pay, email pay, or a deposit | Core. Shop rule. Not the Square account | Toggles sit next to provider fields. Hosted card config forces email pay off inside `CardPresentCaptureProjection` | Rule and provider config are mixed in one form. Do not move the shop rule to Platform to “clean up Square” | Locked |
| Mobile charge button and invoice pay link | Core owns the shop action. When capture is managed, “can we take a card” comes from Platform capability, not from a Core token | Both still call `SquareConfiguration`, which is true only if the Core token exists | Violation of the locked rule. Leave the code until a later decision | Locked |
| Legacy self-hosted Square | Removed | Core no longer contacts Square when Platform is disconnected. Record Payment remains | None | Closed |

No payment code, credentials, settings, or fallback behavior changes are authorized.

### Communications — locked

Communications is a paid Platform product. It must run with no Core installation. Standalone Core runs without it and does not receive that history. When the two are connected, Platform uses Core’s workflow. It does not invent a second one.

| Responsibility | Owner | Current implementation | Discrepancy | State |
| --- | --- | --- | --- | --- |
| SMS history, call history, message content, delivery records | Platform | Core still stores `ConversationMessage` and `CallSession`. `ManagedCommunicationsGate` already claims Platform message state when paired | Core records are not the authority. They stay until a projection contract says what they are. Do not delete them under this lock | Locked |
| Communications provider accounts and execution | Platform | Managed send exists. Core Twilio voice webhooks and some inbound messaging webhooks still run | Target for voice transport is below. History ownership does not remove those webhooks | Locked |
| Conversation workflow for a Core-connected shop: advisor assignment, attention, waiting, follow-ups, resolution | Core | Attention, advisor ownership, Calls & VM as shop work | Workflow stays Core. History behind it is Platform. Calls & VM must not become a second history | Locked |
| Words the shop chooses (hours, tow, pickup, canned replies) | Core | Communications settings | None | Locked |
| How Communications, deployed alone, runs its own workflow, then yields that workflow when a Core shop connects | Not an ownership fight | Not designed | Future design. Must not create a competing workflow authority once Core is connected | Open design |

### Voice, mail, parts — locked targets

The owner is decided. The code does not yet match. That is not a build order.

| Responsibility | Owner | Current implementation | Discrepancy | State |
| --- | --- | --- | --- | --- |
| Call as shop work: missed, handled, whose follow-up | Core | Those screens are in Core, including Calls & VM | The screen is workflow. The call history it shows is Platform’s. Do not turn the screen into a second archive | Locked |
| Managed voice transport | Platform, when a shop uses managed voice | Production ring, callback, and desk phones still use Core Twilio webhooks | Target only. History ownership does not remove those webhooks | Locked target |
| Shop action “send this estimate or invoice” | Core | Estimate and invoice email start in Core | None on the action | Locked |
| Mail account and delivery, when mail is managed | Platform | `ManagedMailGate::platformSend()` exists. Postmark token is still in Core Settings. The floor path is not proven | Gate is ahead of the working send | Locked target |
| Part the advisor put on the estimate | Core | Line snapshot | None | Locked |
| Vehicle identity (the VIN and plate on the vehicle) | Core | `Vehicle` | None on the record | Locked |
| Catalog search, quote transport, VIN/plate decode provider call, when those services are managed | Platform | `ManagedPartsGate` exists. LugsNPlugs catalog, quotes, and decode call PartsTech from Core | Live path is Core. Do not point the floor at a Platform parts stub | Locked target |

### Website

| Responsibility | Owner | Current implementation | Discrepancy | State |
| --- | --- | --- | --- | --- |
| This shop’s public copy, photos, hours, FAQs | Core | `/app/website`, Growth content | None as a shop job | Locked |
| Hosting, DNS, and the public website runtime | Platform | Foundry (`lugsnplugs.com`) is a separate image. It renders the shop site | Foundry must render Core content. It must not become a second editor. The handoff is not built as that contract yet | Locked target |
| Company site and trial signup | Platform | `routes/cloud.php` still serves `autorepairkeeper.com` from the shop host | Misplaced and live. Ownership is Platform. Deletion waits until that site has a home | Locked |
| A second website editor inside Core | Prohibited | `app/Ark/Platform/Website/*`, `routes/platform-website.php`. Uncommitted. Not in `f3e82498` | Must not ship. Must not become the website boundary | Locked prohibition |

### Control plane — locked

| Responsibility | Owner | Current implementation | Discrepancy |
| --- | --- | --- | --- |
| Create, host, suspend, or bill a shop | Platform | Trial funnel, provisioning stubs, `/app/platform/clusters` still in this repo | Not Core. Not the LugsNPlugs ship path. Deletion is a later decision |
| Pairing this installation to Platform | Core holds the socket. Platform holds the account | `PlatformConnection`, `ark:platform-pair`, fabric ingress | Keep the socket. It is not a control panel |

---

## 4. What Core can do alone — locked

Core stays single-shop and usable without Platform.

| Mode | Without Platform | Not required to open a repair order |
| --- | --- | --- |
| Standalone Core | Customers, vehicles, repair orders, estimates, inspections, the ledger, staff, printing, shop workflow | Communications history, managed capture, managed mail, managed catalog. The shop still runs. It does not get the paid history |
| Communications without Core | Message history, call history, delivery records, provider execution | Repair orders, the shop ledger, Core workflow |
| Hosted shop with Communications connected | Core shop records and Core workflow, Platform history | Platform does not replace the repair order. Core does not replace the history |

Self-hosted provider keys are a compatibility path for standalone Core. They are not the Settings experience for a shop whose capture, SMS, or mail is managed.

Existing standalone fallbacks remain until a separate decision addresses each one. This contract does not retire them.

---

## 5. What this contract does not do

- No extraction, migration, deletion, credential change, or deployment.
- No payment, Voice, PartsTech, mail, or website code change.
- No commit of the uncommitted website closeout.
- No new provider interface, second ledger, or second message history.
- No move of shop business rules to Platform because a provider setting sits beside them.

---

## 6. Communications modes — design, not a build

Ownership is closed. This section only says how the two products switch modes. No code change follows from it.

| Mode | Workflow authority | History authority |
| --- | --- | --- |
| Communications alone | Platform | Platform |
| Communications connected to Core | Core | Platform |
| Core without Communications | Core | No paid Communications history |

Platform must not run two workflow authorities at once for the same connected shop. When Core is connected, Platform uses Core’s workflow. When Core is not connected, Communications uses its own. A blip in the connection is not a disconnect.

### What already exists

Connected mode is already sketched in this repo. Do not add a second workflow engine.

- History is read through `ArkCommunicationsClient` (`listConversations`, `showConversation`, `sendConversationMessage`) against Platform.
- Workflow is `ConversationWork` and `ConversationPosture` on the Core `Conversation`: assign, follow up, wait, resolve, reopen. `PlatformConversationWorkController` writes that and does not write message history.
- `PlatformCommunicationsInboxProjection` joins them. Platform supplies the thread. Core supplies the lane and the advisor. If Platform history cannot be listed, `coreFallbackInbox()` shows Core workflow rows only. It does not become a second history editor.

Communications alone is not in this repository. The client above does not boot without a paired Core installation (`PlatformConnection`). Independence cannot be proven from Core code.

### Still to design

These are mechanism questions. They do not reopen who owns history or workflow.

- **Disconnect.** When a shop disconnects on purpose, what happens to assignments, attention, follow-ups, and resolution that lived in Core? Communications must have its own workflow in that mode. It must not copy Core’s open work and then keep editing it.
- **Outage.** A failed Platform list or a failed Core call is not a mode switch. Do not create a second workflow. Do not overwrite Core assignments, attention, follow-ups, or resolution from Platform. The current list fallback stays on Core workflow and must keep doing only that.
- **Reconnect.** Core workflow is authoritative again. Platform history is authoritative again. Neither side replays the outage into the other’s store.

Existing Core `ConversationMessage` and `CallSession` rows stay until a separate compatibility contract defines them as projections or leftovers. This design does not migrate or delete them.
