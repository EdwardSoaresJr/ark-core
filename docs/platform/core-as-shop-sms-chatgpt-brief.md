# Core is a shop. Everything else is not.

Paste this whole file into ChatGPT only as a map of the repo. The ownership contract is [core-platform-ownership-contract-v1.md](core-platform-ownership-contract-v1.md). Do not propose Square cleanup from this file. A fallback is not ownership.

**As of:** 2026-09-18. Live shop: LugsNPlugs. Core image commit: `f3e82498`. Host: `lugsnplugs.arksms.com`. Control plane: `https://platform.autorepairkeeper.com`.

---

## How you (ChatGPT) should behave

You are helping Edward decide what stays in **Core** and what leaves. Core is the software a shop uses to run today's cars. Platform is the software that creates shops, hosts them, and holds provider accounts.

Rules:

1. Judge every folder by one question: **does a service advisor, technician, or shop owner need this to run a repair order today?** If no, it is not Core.
2. Do not recommend deleting a live LugsNPlugs path because it "belongs on Platform." Name the replacement that already works in production, then the deletion.
3. Do not invent a second SMS system, a second inbox, a second payment ledger, or a second repair-order status list.
4. Do not tell him to click Coolify Deploy, use GitHub Actions to ship, or run `migrate:fresh`.
5. Prefer "hide the screen" before "delete the code" when the floor still uses the code.
6. When he asks "can we remove X," answer: who uses it today, what breaks on LugsNPlugs if it disappears tomorrow, and the next safe step. Not a redesign.

Vocabulary he already uses:

| Word | Means |
| --- | --- |
| **Core** | This repo's shop runtime. Customers, vehicles, repair orders, estimates, inspections, payments recorded on the RO, staff, the conversation with that customer. |
| **Platform** | Control plane. Shops, ARK Boxes, services, billing, hosting, domains, entitlements. Lives at `platform.autorepairkeeper.com`. Not this repo's `/app` screens. |
| **Cloud** | Older name for "Platform executes the provider." Twilio send, Square capture, mail send. Core should call Cloud. Core should not store the provider secret once Cloud is proven. |
| **LugsNPlugs** | The one live shop. It is a tenant, not the product model. |
| **Foundry** | The public shop website image (`lugsnplugs.com`). Separate from Core. |
| **Floor** | What advisors and techs do between the car and the customer. If the floor breaks, the change was wrong. |

There is already a locked cleanup note: `docs/platform/core-cloud-boundary-cleanup-audit-v1.md` (2026-09-15). Steps 1–3 of that note are done (nav label, Dragon settings UI, Square credential form hidden when Platform captures cards). Do not restart that list. Continue from the locks below.

---

## The one sentence

**Core should be the shop management system: the customer, the vehicle, the repair order, the estimate, the inspection, the payment on that repair order, and the conversation about that work.** Creating shops, hosting them, DNS, Coolify, trial signup, company marketing, and provider account secrets are Platform.

One Laravel process currently runs both. That is the mess. The fix is extraction, not a new framework.

---

## What "shop management system" includes

These stay in Core even if a class name says Platform, Cloud, or Growth. They are shop work.

| Shop job | Where it lives now | Keep |
| --- | --- | --- |
| Who is the customer, what car, what plate/VIN | `app/Ark/Operations` customers/vehicles, `app/Ark/Vehicles` | Yes |
| Repair order, status, mileage in/out, technician, concerns, lines | `app/Ark/Operations/RepairOrders` | Yes |
| Estimate, labor rate snapshot, parts on the line, approval | Estimate pricing + parts import | Yes |
| Inspection, photos, findings | Operations inspections | Yes |
| Invoice, payment recorded, balance | Ledger on the repair order | Yes |
| Print estimate, invoice, key tag, worksheet | Operations documents / printing | Yes |
| Attention, texts, calls, voicemail, who owns the conversation | Operations communications. Calls & VM is protected. Do not fold it into Attention. | Yes |
| Customer links: estimate, pay, inspection | `routes/portal.php`. Customer-facing. Same shop. | Yes |
| Public shop site content the owner edits (hours, photos, FAQs) | `/app/website`, Growth content | Yes, as shop content. Not DNS. |
| Staff, permissions, stations, desk phones as "which phone is this" | Runtime ACL, Stations & Phones | Yes |
| Appointments, bays, job board | Operations | Yes |
| Mobile and tech screens that show assigned work | `app/Ark/Mobile`, `app/Ark/Tech` | Yes. They are projections of the shop, not a second product. |
| Shop settings that change how this shop behaves (tax, status mileage, note defaults, canned reply text) | Settings | Yes |

Core is allowed to **ask Platform to send a text, charge a card, or decode a VIN**. Core is not allowed to **be** the phone company, the card processor, or the hosting panel.

---

## What is in this repo that is not a shop

PHP file counts under `app/Ark` (2026-09-18). Operations is 1,666 files. That is the shop. The rest is the leak, plus a few shop projections that were given their own folder.

| Folder | Files | Verdict |
| --- | --- | --- |
| `Operations` | 1666 | The shop. Leave it. |
| `Growth` | 200 | Mixed. Owner looking at this shop's site and revenue: stay. Google credentials, sitemap hosting, SEO machinery: Platform or a later cut. Do not move Revenue Explorer off the shop; the dollars are repair orders. |
| `Mobile` | 140 | Shop, on a phone. Stay. |
| `Platform` | 95 | **Name collision.** Some of this is the shop calling Platform (keep). Some of this is a fake control plane (leave). See the split below. |
| `Dragon` | 90 | Shop assistant on top of repair orders. Not Platform. Do not put it in Settings as a raw memory admin. |
| `Runtime` | 41 | Auth, media, health. Stays. It is the app shell, not a product. |
| `Communications` | 37 | Check before moving. Conversation authority is Operations. This folder must not become a second inbox. |
| `ShopMemory` | 31 | Dragon's memory of the shop. Stays with Dragon. Not a control plane. |
| `Station` | 22 | Floor screens (glass, desk). Shop. |
| `Vehicles` | 22 | VIN/plate decode. Shop identity. PartsTech call can later go through Platform; the vehicle record stays here. |
| `Tech` | 15 | Technician assigned work. Shop. |
| `Import` | 15 | One-time history load. Not a product. Delete when imports are done. |
| `Desk` | 11 | Shop desk projection. |
| `Website` | 7 | Owner editing this shop's public pages. Content stays. Hosting does not. |
| `Customer` | 5 | Customer application shell. Stay. |
| `Orientation` | 5 | Morning briefing. Shop. |
| `Voice` | 3 | Mostly lab. Production phones are Operations telephony + Twilio webhooks, not this folder. |
| `Install` | 2 | First-run shop install. Borderline. A new shop should be created by Platform, then Core boots empty. |
| `Application`, `Domain`, `Infrastructure` | 0 | Empty. Do not fill them with a new architecture. |

---

## The `app/Ark/Platform` split

This folder is the main confusion. It is **not** the Platform product. The Platform product is a different app. This folder is leftover control-plane code plus the clients Core uses to talk to the real Platform.

### Keep in Core - the shop calling out

These are the phone line from the shop to Platform. Deleting them stops LugsNPlugs texts, card capture, or pairing.

- `PlatformConnection`, `PlatformPairingClient`, `ArkBoxHeartbeatClient`, `BoxRuntimeObservation`
- `FabricIngressController`, `VerifyPlatformFabricSignature`
- `ArkCommunicationsClient`, `ManagedCommunicationsGate`
- `ArkPaymentsClient`, `ManagedPaymentsGate`
- `ArkMailClient`, `ManagedMailGate`
- `ArkPartsClient`, `ManagedPartsGate`, `PartsTechPlatformGateway` (do not point LugsNPlugs catalog at a stub)
- `CoreApplicationOrigin`, `ShopBaseUrl` (HTTP URLs for this shop)
- Commands `ark:platform-pair`, `ark:platform-heartbeat`
- `shop_settings.platform_*` pairing columns

### Get out of Core - control plane that should not be here

| Thing | What it is | If you delete it tomorrow |
| --- | --- | --- |
| `routes/cloud.php`, `CloudExperienceController`, `CloudAccount`, `CloudShop` | Company site and trial signup (`autorepairkeeper.com`) running on the shop server | Company marketing 404s. The shop at `/app` survives. |
| `ProvisioningOrchestrator` and Stub DNS/Email/Stancl/Bootstrap/Coolify steps | Fake "create a shop" pipeline | Tests and `ark:provisioning:run` only. **Not** how LugsNPlugs ships. |
| `Provisioning/Coolify/*` | A Coolify client inside Core | `ark:coolify:check` only. Production ship is `./infra/build-runner/mac/deploy-production.sh` on a Mac. Never Coolify's Deploy button. |
| `Cluster`, `Deployment`, `platform_shops`, cluster admin `/app/platform/clusters` | Unused multi-shop admin inside the shop app | Hidden URL. Do not drop `platform_shops` while `routes/cloud.php` still creates trial shops. |
| `routes/platform-website.php` and `Platform/Website/*` draft authority | A second website editor trying to live inside Core | Conflicts with `/app/website`. Website closeout is **uncommitted local work**. Do not ship it. Do not treat it as live. |
| `ReportVoiceInboundPolicyAction` | Deprecated no-op | Nothing. |

---

## Provider secrets still sitting in the shop

Core Settings and `shop_settings` still hold keys that Platform should hold once that service is actually Platform-backed.

| Secret | Today | Safe move |
| --- | --- | --- |
| Square app token, webhook key, device-code pairing | Platform already captures cards for LugsNPlugs. Settings form is hidden when that gate is on. Core still has the charge buttons and the ledger. | Do not delete `InitiateSquarePaymentAction` or `/portal/pay`. Next: stop writing Core Square tokens. |
| Twilio SID/token and voice webhooks | **Still production.** Desk phones and callback use Core Twilio. SMS send is largely Platform. | Do not remove voice webhooks. Hours/recording/ring already refuse to save when Platform is connected. |
| Postmark token | Estimate/invoice email still sends from Core. There is no working "Core asks Platform to send mail" path that replaces it. | Do not remove the token until that path exists. |
| PartsTech username, API key, password | Catalog, quotes, and (as of `f3e82498`) VIN/plate decode run from Core against PartsTech. | Do not move credentials until a real Platform parts transport is what the floor uses. VIN decode may later be a Platform service. Until then Core calls PartsTech, then NHTSA if PartsTech fails. |
| OpenAI key | Call summaries. Voice and SMS still work without it. | Can leave the shop later. Not first. |
| Messenger page token, Firebase JSON, Growth Google JSON | Shop-connected accounts stored in Core. | Investigate before moving. Do not guess. |

---

## Routes that are not the shop

| File | Job | Verdict |
| --- | --- | --- |
| `routes/operations/**`, `routes/auth.php` | Staff shop | Keep |
| `routes/portal.php` | Customer looking at their car, estimate, invoice | Keep |
| `routes/public.php` | This shop's public pages (book, contact, problems) | Keep as shop site content |
| `routes/website.php` (`/app/website`) | Owner edits that content | Keep |
| `routes/growth.php` (`/app/growth`) | Owner looks at this shop's marketing and closed-RO revenue | Keep the shop questions. Do not turn it into a hosting panel. |
| `routes/api.php` mobile | Staff app | Keep |
| `routes/cloud.php` | ARK company funnel on the shop host | Leave Core |
| `routes/platform-website.php` | Second website authority | Leave. Uncommitted. Not production. |
| `routes/oidc.php` | Core acting as a login server | Platform concern if anything consumes it. Do not rip it out without naming the client. |
| `routes/cloud-ingress.php` | Platform calling into this shop (fabric) | Keep. This is the socket, not the control plane. |
| Square and Twilio webhooks in `routes/web.php` | Provider calling the shop | Voice webhooks stay until Platform places calls. Square webhook stays until self-host Square is explicitly dead. |

---

## Production locks (do not "clean" these)

LugsNPlugs is on one VPS, one Core container (`b38otdn2epypspy0jadbgfl0-core`), MySQL and Redis beside it. Coolify owns the stack definition. **Core releases are: Mac build, load the image, recreate the Core container only.**

Do not:

- Use Coolify Deploy or `POST /api/v1/deploy` on `lugsnplugs-stack`.
- Ship with GitHub Actions.
- Remove Calls & VM (`/app/communications/calls`).
- Remove repair-order workflow, estimate totals, or the payment ledger from Core.
- Point parts catalog at the Platform parts stub.
- Remove Twilio voice webhooks while desk phones still ring through Core.
- Treat LugsNPlugs extension maps, shop name, or labor rates as defaults for every future shop.
- Commit or deploy the dirty website-closeout files (see below).

Shipping `f3e82498` (2026-09-18) turned on mileage gates that were already configured: In Progress wants mileage in; Quality Check, Completed, and Ready for Pickup want mileage out. That is shop workflow. It is not a Platform change.

### Still dirty on the Mac, not in `f3e82498`

These website-closeout files were left uncommitted on purpose:

- `app/Ark/Platform/Website/WebsiteDraftDocumentMerger.php`
- `resources/views/platform/website/manage.blade.php`
- `tests/Feature/Platform/PlatformWebsiteDraftP1Test.php`
- `tests/Unit/Platform/WebsiteDraftDocumentMergerTest.php`
- `docs/engineering/CURRENT_MILESTONE.md` (stale pin, ignore)
- `infra/coolify/lugsnplugs-stack.docker-compose.yml`

Do not tell Edward to commit these as part of "making Core a shop." They are the opposite: more website authority inside Core.

---

## How to proceed

Work in this order. Each step is done only when LugsNPlugs can still open a repair order, text a customer, and take a payment.

### 0. Stop adding control plane to Core

No new cluster screens, no new trial funnel, no new Coolify client, no second website editor. New shop-creation work goes to the Platform app.

### 1. Make the boundary visible, do not delete yet

- Any Settings screen that pastes a provider secret gets a single line: "Platform holds this" when the managed gate is on, and the fields disappear.
- Square is the pattern already shipped. Copy that pattern. Do not copy it onto Voice or PartsTech until those gates are true in production.
- Rename anything staff see that says Platform when it means the shop. Staff should see Shop, Stations & Phones, Website. Engineers can keep the class names until a real move.

### 2. Move one provider at a time, after the floor uses the new path

Order, because this is how close each one is:

1. **Square secrets.** Capture already runs on Platform. Next step is proving Core no longer needs the token, then delete the Settings fields and the Core webhook. Ledger and the charge button stay.
2. **SMS send secrets.** Send already goes through Platform for hosted shops. Inbound webhooks on Core can go after inbound is proven on Platform. Conversation rows stay in Core.
3. **Mail.** Blocked. Build the Core → Platform send call first. Then remove the Postmark token. Do not build a second mail product.
4. **Parts catalog and VIN.** Blocked. Floor still uses Core PartsTech. A Platform `vin` service is an idea, not a replacement. Plate decode has no NHTSA fallback, so a broken Platform VIN/plate service means the counter cannot decode a plate.
5. **Voice.** Last. Desk phones are the production phone system. Mobile apps do not get to destabilize them. Platform owns outbound calls only after a desk phone still rings without Core Twilio.

### 3. Lift the company site off the shop server

`routes/cloud.php` (pricing, trial, login, provisioning) should be served by Platform, not by `lugsnplugs.arksms.com`. Do this after step 2 has a quiet week, or sooner if it is only DNS and the code stays deployed somewhere else. Deleting the routes before the company site has a new home 404s `autorepairkeeper.com`.

### 4. Delete the dead control plane inside Core

Only after step 3, and only after `ark:provisioning:run` is confirmed unused:

- Stub provisioning steps
- Core Coolify adapter
- `/app/platform/clusters`
- Then, and only then, tables `platform_shops`, `clusters`, `platform_deployments` if nothing in the shop reads them

### 5. Leave these alone while doing 1–4

Growth revenue questions, website copy, ARKademy, printing, staff, job board, mileage rules, note defaults, canned replies, mobile, technician screens, Dragon learning from conversations.

---

## What "done" looks like

A new engineer can open this repo and find only:

- A shop (Operations + the customer site for that shop + staff auth)
- Thin clients that call Platform for text, mail, cards, and catalog
- No trial signup, no Coolify, no cluster admin, no second website authority

Platform, in its own app, has:

- Shops, boxes, billing, domains, provider accounts
- The company website
- The buttons that create or suspend a shop

The shop database still holds customers, vehicles, repair orders, messages, and payments. Platform does not become a second copy of those tables.

---

## If Edward asks you to implement

Ask which step above he means. Default answer if he says "clean up Core":

Do step 1 for the next provider that is already Platform-backed (Square token fields, if any remain writable). Do not start Voice, PartsTech, or the cloud funnel in the same change.

Refuse:

- A new `PlatformManager` or provider interface with one implementation
- Moving repair orders, conversations, or the ledger to Platform
- A rewrite, a new app split, or a monorepo extraction as the first PR
- Deploying unrelated website closeout because it was dirty in the working tree
