# Overnight testing backlog

Recorded 2026-10-13. These items do not block daytime development. Do not treat them as a release gate.

## How to reproduce the clean-pin suite

Core commits on top of `f196457923c850e98962c920acfa1c0bec4170b9` are test-harness only. Application source is that pin. SaaS pin stays `f196457923c850e98962c920acfa1c0bec4170b9`. SaaS HEAD is `99e8dc489d5b00abf98723a89953deb9cb087ddd`.

```bash
cd /Users/edwardsoares/Herd/ark-core-pin-721c9c1
git rev-parse HEAD   # 75c4f7b
php -d memory_limit=2048M bin/pest --no-coverage
```

Use `php bin/pest`, not `vendor/bin/pest`. Do not symlink the shop `.env` (database `ark` on port 3306). The launcher creates `storage/framework/views` and `storage/framework/sessions`, sets `APP_BASE_PATH` to the worktree, and retargets a symlinked `vendor/` classmap at this checkout.

Last completed run, 2026-10-13, about 35 minutes:

| | |
| --- | --- |
| Tests | 3594 |
| Passed | 3425 |
| Failed | 118 |
| Errors | 29 |
| Skipped | 22 |
| Assertions | 19809 |

Log: `/tmp/ark-core-pin-75c4f7b.log`. That file is local and may disappear.

## Harness: fixed enough to run, still easy to misuse

Severity: test harness. Does not block feature work if tests are launched with `php bin/pest` from the checkout under test.

`vendor/bin/pest` computes its project root from the real path of `vendor/`. A worktree whose `vendor` is a symlink to the main checkout binds Pest's root, `Tests\TestCase`, and Laravel's application path to the main checkout. Symptoms when that happens: `Target class [config] does not exist`, `seed()` missing, `connection() on null`.

`bin/pest` is the workaround that is committed (`721c9c1` through `75c4f7b`). `vendor/bin/pest` is still wrong for a symlinked vendor. A clean checkout also has no `storage/framework/views` or `storage/framework/sessions` because those directories are gitignored, so Blade's `realpath()` cache path is empty and pages return 500 (`Please provide a valid cache path`) unless the launcher creates them. PHPUnit's `memory_limit` of 512M is not enough for the full suite; the launcher raises it to 2048M in a temporary configuration.

`fakePlatformThread()` was declared twice on `f196457` and aborted the suite before any test. Renamed in `2ef6150`.

## Application and test failures on the pin

Severity: mixed. None of these blocked the isolation work. Investigate overnight. Do not change production code only to satisfy a harness quirk. The failures below happened after the harness could boot the pin's own application and tests.

Clusters worth separating first:

- Twilio webhook routes are not registered (`webhooks.communications.twilio.voice.recording`, `.status`, `.ring-status`, `.incoming`, `.messaging.incoming`). Tests still call them. Stock Core does not own Twilio.
- `shop_settings` has no `twilio_account_sid` or `postmark_token` columns. Several send-projection and mailer tests write those columns.
- `App\Ark\Operations\Leads\Public\LeadThanksProjection` is missing.
- `Route [robots.txt]` is missing.
- `VOICE_SIP_REGISTRAR is not configured` on two device-workspace tests.
- Many communications and operations pages no longer contain the string the test searches for (missing copy or a redirect). That is the largest group.
- `Response::assertFalse` is called on a response in the operations left-rail test.

Full list from that run:

- [failure] workflow communications saves settings and sends inspection through platform — Expected response status code [200] but received 422.
- [failure] password can be updated — Failed asserting that false is true.
- [failure] self registration is disabled for the staff only shop model — Expected response status code [404] but received 405.
- [failure] fabric ingress rejects replay nonce — Expected response status code [200] but received 422.
- [failure] fabric ingress accepts voice incoming started and broadcasts interrupts — Expected response status code [200] but received 422.
- [failure] attention workspace shows mark handled nudge for unhandled completed call — Suggested next step
- [failure] attention list shows call channel label for unhandled calls — >Call<
- [failure] dismissed nudge stays hidden for eight hours then reappears — Mark call handled
- [failure] missed call shows mark handled nudge with higher priority than completed call analysis — Mark call handled
- [failure] assigned conversation with sms analysis shows follow up nudge and draft — SMS follow-up suggested
- [failure] analysis insight panel shows when mark handled blocks call analysis nudge — Mark call handled
- [failure] dismissed mark handled nudge reveals call analysis nudge with draft — Follow-up suggested
- [failure] communications inbox excludes feature code call sessions — Failed asserting that an array contains 'call:1'.
- [failure] communications inbox threads one row per customer when conversation and calls overlap — Failed asserting that actual size 0 matches expected size 1.
- [failure] waiting queue does not auto open the relationship workspace — No current visit
- [failure] explicit open loads the existing relationship workspace — Shop Context
- [failure] assign extension rejects real conflict without mutating either workstation — already assigned elsewhere
- [failure] shop communications surfaces attention when a device is offline — Attention
- [failure] device workspace tells operational truth without transport details — Right now
- [failure] device provisioning generates downloadable config without exposing credentials in ui — Failed asserting that '<!DOCTYPE html>\n
- [failure] shop communications manual device entry is limited to master admin support — Manual device entry (support)
- [failure] website lead appears in needs attention with turn label — Expected response status code [201, 301, 302, 303, 307, 308] but received 404.
- [failure] new ro from unmatched website lead carries lead  id so intake prefills name — Expected response status code [201, 301, 302, 303, 307, 308] but received 404.
- [failure] advisor reply records first contact and keeps Needs attention until follow up — Expected response status code [201, 301, 302, 303, 307, 308] but received 404.
- [failure] customer inbound sms returns conversation to needs attention — Expected response status code [201, 301, 302, 303, 307, 308] but received 404.
- [failure] lead selection redirects to conversation thread — Expected response status code [201, 301, 302, 303, 307, 308] but received 404.
- [failure] send estimate from conversation thread stays on same conversation — Expected response status code [201, 301, 302, 303, 307, 308] but received 404.
- [failure] conversation thread send payment records sms on the active conversation — Expected response status code [200] but received 422.
- [failure] empty communications workspace keeps the three column landmarks — ops-comms-workspace__thread-header
- [failure] explicit open still loads the composer without changing queue architecture — ops-comms-workspace__visit-strip
- [failure] empty fragment still returns the same workspace landmarks — ops-comms-workspace__visit-strip
- [failure] fragment open returns thread and context without requiring a second queue architecture — ops-comms-workspace__visit-strip
- [failure] a missed call appears once in the thread ahead of a later text — Missed call
- [failure] the latest missed call is explained in the thread it labels — Missed call
- [failure] an answered call shows authoritative talk time and its recording is not voicemail — 2m 5s
- [failure] mark handled changes only that call — Missed call
- [failure] hosted missed call rescue sends through platform without a core sms row — Failed asserting that false is true.
- [failure] legacy imported estimate can render pdf without overwriting imported snapshot — Failed asserting that two arrays are identical.
- [failure] lead linked inbox context shows check in and disposal actions — Mark contacted
- [failure] lead thanks page shows personalized onboarding after submit — Expected response status code [201, 301, 302, 303, 307, 308] but received 404.
- [failure] direct thanks page visit without lead shows generic onboarding — Expected response status code [200] but received 404.
- [failure] conversations index exposes advisor awareness fields for inbox — Failed asserting that an array has the key 0.
- [failure] people lists shop names with opaque refer uri and excludes caller mobile leg — Expected response status code [200] but received 422.
- [failure] mobile staff devices projection lists registered devices with extensions — Expecting null not to be null .
- [failure] advisor can open schedule create from a repair order — labor hours - used for daily shop capacity
- [failure] work page renders ark manager brief instead of coming soon — ARK Manager brief
- [failure] legacy messenger settings url redirects away from removed core transport ui — Expected response status code [201, 301, 302, 303, 307, 308] but received 200.
- [failure] work home shows communications metric without queue sections — Open queue
- [failure] handled call disappears from needs attention — Failed asserting that 1 is identical to 0.
- [failure] estimate review renders relationship context with fleet open ros — Relationship Context
- [failure] customer hub shows text customer affordance when no conversation history — Attach Picture?
- [failure] caller lookup page renders resolved context — Awaiting Approval
- [failure] customer hub comms tab renders unified timeline filters — Attach Picture?
- [failure] the shop can edit customer and vehicle details from the customer page — Save Customer
- [failure] the shop can decode a plate via partstech — Expected response status code [200] but received 422.
- [failure] plate decode says when partstech is not configured — Failed asserting that two strings are identical.
- [failure] customer vehicle hub retains deferred work as vehicle continuity — Safety/drivability recommendation should be revisited calmly.
- [failure] current deferred work feeds queue pressure and document snapshots — Failed asserting that two strings are identical.
- [failure] shop settings can save document disclaimer fields — Failed asserting that two strings are equal.
- [failure] resending estimate link creates a new token row without invalidating prior links — Expected response status code [200] but received 422.
- [failure] terminal repair order statuses snapshot a final pdf that no longer auto refreshes — Failed asserting that false is true.
- [failure] inspection handoff lets advisor choose staff for sms or email — http://localhost/app/repair-orders/1/inspection/walk-link
- [failure] seed creates Standard default and PPI  archives GVI — Expecting null not to be null .
- [failure] builder shows Standard and PPI selection — data-inspection-template-select
- [failure] confirm installed creates append only event  sticker print works before and after — Expected response status code [200] but received 500.
- [failure] viewed estimate and customer reply drive advisor next action in queue and review — Follow up viewed estimate
- [failure] ready pickup communication tracks customer notification posture — Pickup notified · Phone
- [failure] repair order review header shows service lane identity band without presentation card — ops-identity-column-link
- [failure] it schedules without asking for a customer id — Find the customer first
- [failure] outbound sms appears on customer hub timeline after send — Failed asserting that two strings are identical.
- [failure] parts matrix tune assistant simulates markup changes on closed sample — Failed asserting that true is false.
- [failure] customer can access portal with sms code — Expected response status code [201, 301, 302, 303, 307, 308] but received 500.
- [failure] record portal estimate view action broadcasts interrupt once — The expected [App\Ark\Operations\Communications\Events\CommsInterruptReceived] event was dispatched 2 times instead of 1 time.
- [failure] advisor builder workflow keeps projections and totals coherent through approve path — data-compose-save-clarity
- [failure] two overlapping writers cannot both mutate from the same estimate version — Failed asserting that null is of type array.
- [failure] payment send projection explains missing issued invoice — Failed asserting that two strings are identical.
- [failure] estimate send projection allows sms when customer and twilio are ready — Failed asserting that false is true.
- [failure] ready pickup requires payment before terminal closeout — Balance due at pickup
- [failure] technician assignment is lightweight server owned execution context — Unassigned
- [failure] solo owner shop without advisor staff can start work without technician assignment — Failed asserting that false is true.
- [failure] technician assignment cannot be cleared while work is in progress — Session is missing expected key [errors].
- [failure] assigned work can move into progress and snapshot execution posture — Failed asserting that false is true.
- [failure] operations home survives dense active queue without full estimate graph dependency — Dense 45
- [failure] ro comms tab uses event bubble and conversation message history for reply label — Text Customer
- [failure] ro show financial presenter balance due is projected once per request — Failed asserting that 14 is equal to 2 or is less than 2.
- [failure] repair order workspace exposes vehicle change affordance before scopes or line items — http://localhost/app/repair-orders/1/vehicle
- [failure] advisor can update visit posture from repair order workspace — ops-visit-mode-pill
- [failure] send inspection link creates access token and sends sms conversation message — Expected response status code [200] but received 422.
- [failure] resending inspection link reuses the same short url — Expected response status code [200] but received 422.
- [failure] hosted platform inspection send records inspection  sent without a core conversation message — Expected response status code [200] but received 422.
- [failure] connected shop does not apply leftover postmark tokens to laravel mail — Failed asserting that two strings are identical.
- [failure] unassigned appointments appear on the agenda scheduler — ops-cal-card__detail
- [failure] invited staff can use signed link to set password and reach operations — Failed asserting that two strings are equal.
- [failure] advisor and admin keep estimate review on show — ops-estimate-workspace--review
- [failure] multi role advisor technician keeps estimate review — ops-estimate-workspace--review
- [failure] mark worked removes call from queue without deleting session — Failed asserting that 1 is identical to 0.
- [failure] settings managers can test incoming call from telephony page route — Expected response status code [200] but received 503.
- [failure] settings managers can save primary telephony provider per shop — Failed asserting that two variables reference the same object.
- [failure] backfill command attaches twilio recordings onto matching call sessions — Expected status code 0 but received 1.
- [failure] technician lens still surfaces assigned work lanes — Failed asserting that an array contains 'Assigned work'.
- [failure] vehicle intelligence merges nhtsa only into missing partstech fields — Failed asserting that two strings are identical.
- [failure] partstech and nhtsa payloads normalize to the same canonical key — Failed asserting that two strings are identical.
- [failure] vehicle intelligence decodes plate via partstech and enriches from vin when returned — Expecting null not to be null .
- [failure] printed estimate pdf omits preliminary notice when VIN is on record — Reason for Visit
- [failure] advisor worksheet presents visit reason as intake context with add concern — ops-visit-reason
- [failure] communications section nav lists inbox calls and history in that order — Inbox
- [failure] site foundation exposes services  problems  and an appointment request — href=
- [failure] diagnostics brakes and maintenance share one service page pattern — href=
- [failure] complaint and code pages keep their own presentations — href=
- [failure] contact corrects three answers and financing keeps one set of destinations — href=
- [failure] preferred custom domain is the public canonical on both hosts — href=
- [failure] useful legacy urls redirect and homepage dumps do not — Header [Location] was found, but value [http://lugsnplugs.com/book?concern=Brakes] does not match [https://lugsnplugs.com/book?concern=Brakes].
- [failure] stock core runs the shop without saas  twilio  or platform — Failed asserting that true is false.
- [failure] a shop that is not the public demo keeps its own front door — Expected response status code [404] but received 302.
- [failure] configured app url is used when not localhost — Failed asserting that 'https://app.lugsnplugs.test/error-reports/abc123/copy?expires=1792003012&signature=415862c40d4c847704179f6887ff970eee6ae508e15c127e148cd9a1caa1cef7' starts w
- [failure] portal access is served on the customer apex host — Expected response status code [200] but received 500.
- [failure] workstation lock screen retired   operational shell stays visible — Failed asserting that null is identical to false.
- [failure] workstation staff list includes avatar metadata for operator tiles — Unable to find JSON fragment: 
- [error] gate  calls SMS delivery and recording routes remain registered — Route [webhooks.communications.twilio.voice.recording] not defined.
- [error] a hosted repair order send does not write a second core message — Outbound SMS is not configured.
- [error] person workspace lists assigned devices with name only add form — No query results for model [App\Ark\Operations\Communications\CommunicationDevice].
- [error] device workspace surfaces provisioning observability for bench certification — VOICE_SIP_REGISTRAR is not configured for this deployment.
- [error] device projection preview stays hidden from non master admins — VOICE_SIP_REGISTRAR is not configured for this deployment.
- [error] handled call stays in inbox with full thread including outbound messages — No query results for model [App\Ark\Operations\Conversations\Conversation].
- [error] attention workspace thread renders inbound mms image attachments — Route [webhooks.communications.twilio.messaging.incoming] not defined.
- [error] lead thanks projection personalizes request summary — Class "App\Ark\Operations\Leads\Public\LeadThanksProjection" not found
- [error] appointment request thanks projection does not imply a scheduled appointment — Class "App\Ark\Operations\Leads\Public\LeadThanksProjection" not found
- [error] it lets a completed installation start Cloud pairing without a Core rollout flag — Finish installing ARK before connecting to ARK Platform.
- [error] overlapping same key deposits and payments record one effect and different keys stay distinct — Trying to access array offset on null
- [error] operational events record after successful server mutations with minimal payloads — No query results for model [App\Ark\Operations\RepairOrders\RepairOrder].
- [error] robots txt disallows portal crawling — Route [robots.txt] not defined.
- [error] estimate send projection allows sms via platform without core twilio — SQLSTATE[HY000]: General error: 1 no such column: twilio_account_sid (Connection: sqlite, Database: :memory:, SQL: update "shop_settings" set "platform_status" = connected, "platfo
- [error] estimate send projection allows email via platform without core postmark — SQLSTATE[HY000]: General error: 1 no such column: postmark_token (Connection: sqlite, Database: :memory:, SQL: update "shop_settings" set "telephony_inbound_number" = 7195559999, "
- [error] payment send projection allows email via platform without core postmark — SQLSTATE[HY000]: General error: 1 no such column: postmark_token (Connection: sqlite, Database: :memory:, SQL: update "shop_settings" set "square_enabled" = 1, "square_portal_pay_e
- [error] deposit send projection allows email via platform without core postmark — SQLSTATE[HY000]: General error: 1 no such column: postmark_token (Connection: sqlite, Database: :memory:, SQL: update "shop_settings" set "square_enabled" = 1, "square_portal_pay_e
- [error] customer can open inspection portal with token — Missing required parameter for [Route: portal.inspections.show] [URI: portal/inspections/{token}] [Missing parameter: token].
- [error] broken postmark mailer falls back to log when no core token exists — SQLSTATE[HY000]: General error: 1 no such column: postmark_token (Connection: sqlite, Database: :memory:, SQL: update "shop_settings" set "postmark_token" = ?, "updated_at" = 2026-
- [error] disconnected shop keeps postmark when a core token exists — SQLSTATE[HY000]: General error: 1 no such column: postmark_token (Connection: sqlite, Database: :memory:, SQL: update "shop_settings" set "postmark_token" = shop-postmark-token, "u
- [error] ring leg answer stamps answered  at even when the endpoint has no owner — Route [webhooks.communications.twilio.voice.ring-status] not defined.
- [error] child in progress status callback sets answered  at on the parent session — Route [webhooks.communications.twilio.voice.status] not defined.
- [error] losing child no answer does not create a session or miss the parent — Route [webhooks.communications.twilio.voice.status] not defined.
- [error] twilio webhook records last successful webhook timestamp — Route [webhooks.communications.twilio.voice.incoming] not defined.
- [error] recording webhook attaches media using parent call sid — Route [webhooks.communications.twilio.voice.recording] not defined.
- [error] recording webhook returns 404 when the call session is unknown so Twilio retries — Route [webhooks.communications.twilio.voice.recording] not defined.
- [error] recording webhook records failure instead of silent success when completed without a url — Route [webhooks.communications.twilio.voice.recording] not defined.
- [error] diagnostic repair order can be created without vin — No query results for model [App\Ark\Operations\RepairOrders\RepairOrder].
- [error] staff with admin permissions see full operations left rail destinations — Method Illuminate\Http\Response::assertFalse does not exist.

## Already accepted, do not rerun as a gate

MySQL on 127.0.0.1:3307, Redis DB 15, a real `queue:work` process, and live Reverb on 127.0.0.1:8091 passed in SaaS `99e8dc4`. That is evidence for continued development, not a production certification.

SaaS checks that passed against pin `f196457` on 2026-10-13: `saas/tests/contracts.php`, `saas/tests/payments.php`, `saas/tests/entitlement.php`, `saas/tests/estimate-mail.php --core` that pin, `guard/self-test.php` (6/6). Tenant isolation, Square credentials, and Dragon registration: 11 tests, 98 assertions, via `php bin/pest` on the harness worktree.

Not done, and not a daytime task: Horizon master process, a real S3-compatible service (AWS SDK is not installed; MinIO pull was denied), hosted image build.

## Shop provider selection outside mail

Recorded 2026-10-13 during the mail settings pass. Not re-verified.

Texting, Square card capture, voice, parts, and Dragon already store provider mode per shop in `saas_provider_modes`. Each also has a node-wide `saas.*.delivery` switch (`record`, `unavailable`, `provider`) that is the same kind of safety switch as `ARK_SAAS_MAIL_DELIVERY`. Confirm those switches are not being used as the shop's provider choice, and that an incomplete BYO account does not fall back to the shared managed credential. Mail was checked. The others were not.
