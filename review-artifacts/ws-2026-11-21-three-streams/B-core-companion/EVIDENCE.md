# Core SMS companion evidence (Stancl #167/#166 / #315)

## Branch
`fix/core-sms-eligibility-companion-167-166` from Core `8e5ec0b0`

## Compatibility with Stancl #315 (`c5d349ea` + companion follow-up)
- Contract: `contactPermission.texting` may be `unavailable` with optional `unavailable_reason` (`landline|service_disabled|unsupported`).
- Stancl `ConversationSmsEligibility` / `WorkspaceSignals` emit those fields (follow-up commit on #315 branch).
- Core presenter still promotes legacy `unknown` + landline/service source/notes to unavailable labels for older hosts.

## Before / after
| Case | Before | After |
|---|---|---|
| Landline destination | Often “Texting not confirmed” / looked allowed via consent-only paths | `Texting unavailable (landline)` |
| Permanent fail 21610/30006 | “call or send it again” | STOP/landline recovery; no retry invite |
| Platform/conversation callers | Some used primary only | Destination phone passed into `CustomerSmsSendEligibility::for` |
| Standalone Core | Unchanged composer setup copy | No forced unavailable badge when messaging unset |

## Tests
```
./vendor/bin/pest tests/Feature/Communications/SmsEligibilityCompanionTest.php \
  tests/Feature/Communications/CommunicationsWorkspaceSignalsTest.php
```
**13 passed**
