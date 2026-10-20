---
title: Commands, jobs, and events
description: Artisan commands, scheduled tasks, queue jobs, and events an extender can see in ARK Core.
---

# Commands, jobs, and events

<!-- source: bootstrap/app.php -->

## Commands you can run without a special import

Signatures below were read from the command classes. Other classes in `app/Console/Commands` are maintenance, import, and backfill tools. Open the class before you run one. Several of them rewrite shop rows.

| Command | Class | Effect |
| --- | --- | --- |
| `ark:install-status` | `ArkInstallStatusCommand` | Prints installer state. |
| `ark:install-recover --force` | `ArkInstallRecoverCommand` | Clears an interrupted in-progress install. Does not unlock a finished install. |
| `station:token-issue {name=front-counter-glass}` | `StationTokenIssueCommand` | Prints a station device token once. |
| `station:token-revoke {id}` | `StationTokenRevokeCommand` | Revokes that token id. |

`php artisan schedule:run`, `migrate`, `horizon`, and `queue:work` are Laravel. `composer test:parallel` is the test entry in `composer.json`.

## Schedule

Registered in `bootstrap/app.php`:

| Command | Cadence | Guard |
| --- | --- | --- |
| `shop-excellence:owner-digest` | Daily at the shop digest time | `ShopExcellenceTargets::ownerDigestEnabled()` |
| `communications:daily-coaching-digest` | Daily | Coaching digest enabled |
| `comms:escalate-unhandled` | Every minute | |
| `time-clock:sync-auto` | Every five minutes | |
| `comms:reconcile-stale-call-sessions` | Every minute | |
| `appointments:send-reminders` | Every five minutes | Appointments feature enabled |
| `ark:platform-heartbeat` | Every five minutes | Platform heartbeat. Not required for a stock shop to open repair orders. |

Host installs must cron `schedule:run`. Compose starts the scheduler in the app container.

## Queue jobs

These classes implement `ShouldQueue`. They run only if a worker is running, unless the queue connection is `sync`.

| Job | Area |
| --- | --- |
| `DispatchMobilePushForInboundMessage` | Push after an inbound message |
| `DispatchMobilePushForOperationalEvent` | Push after an operational event |
| `DispatchScheduledOutboundSmsReplyJob` | Scheduled SMS reply. Needs a configured SMS transport. |
| `DispatchScheduledOutboundEstimateJob` | Scheduled estimate send |
| `SendMissedCallRescueSmsJob` | Telephony follow-up SMS |
| `RingTelephonyEndpointJob`, `ExpandStaggeredRingJob`, `CompleteUnansweredInboundCallJob` | Call ringing. Stock telephony provider is not configured. |
| `AnalyzeCallSessionJob`, `AnalyzeConversationSmsIntelligenceSliceJob` | Analysis jobs |
| `SendWebsiteLeadConfirmationJob` | Website lead mail |

## Events an extender can listen for

| Event | Registered in |
| --- | --- |
| `ConversationMessageReceived` | `Event::listen` in `AppServiceProvider` |
| `Illuminate\Mail\Events\MessageSending` | Reply-to address, same provider |
| `OperationalEvent` created | Mobile push and recommendation completion listener |
| `ApprovalEvent` created | Mobile lifecycle push for an approved estimate |

There is no generated event index. A class named `*Event` under `app/Ark` is often an Eloquent model, not a Laravel event. `ApprovalEvent` is a model. Listening uses the Eloquent `created` hook.
