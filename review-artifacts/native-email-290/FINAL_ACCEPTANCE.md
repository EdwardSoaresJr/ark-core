# Core #290 Final Acceptance Evidence

## Candidate

- Branch: `feature/native-installation-email`
- Tip before acceptance commit: `198f2f8562e20ebb3b5a1a091971120ca7ff3ca4`
- Final SHA: see git after acceptance commit on this branch.

## 1. Mailpit SMTP (isolated standalone)

- Transport: Docker Mailpit `127.0.0.1:2587` → container `1025`, UI `18025`
- Evidence: `mailpit/ACCEPTANCE.json`, `mailpit-messages-summary.txt`
- Workflows verified (receive + content + links + PDF where applicable): estimate, invoice, inspection, document, statement
- No production customers contacted

## 2. Settings → Email walkthrough (logged-in Tabler UI)

- Screenshots: `settings-email-full.png`, `settings-email-test-section.png`, `settings-email-after-test.png`, `email-settings-ui.png`
- Shows: Installation Settings / Environment / Disabled modes, credential mask + replace checkbox, encryption None for null+host, test-send section, last test `transport_accepted`

## 3. Composed SaaS regression (isolated Stancl @ origin/main #380 + candidate Core path pin)

- Environment: `/tmp/ark-stancl-native-email-290` (detached `8ced4e96` = merge #380), `ark/core` path-pinned to candidate working tree
- Suite: `TenantStaffPasswordResetTest` + `ProcessMemoryIsolationTest`
- Result: **8/9 pass**, 1 fail (structural)
  - Pass: PlatformAwareMailManager tenant vs central, staff reset via array mailer, cross-tenant token isolation, central subscriber isolation, production outbound safety, suppression cancels Laravel mail, process memory isolation
  - Fail: `test_customer_transactional_mail_stays_on_ark_shop_not_laravel_mail` expects constructor type `App\Ark\Platform\Mail\ArkMailClient`; candidate types `CustomerOutboundMail` (routes to Platform on hosted; refuses installation SMTP via `hostedIsolated()`)
- Core behavioral hosted isolation: `InstallationMailHostedIsolationTest` + related pest — **16 passed** (`core-hosted-upgrade-pest.txt`)
- Log excerpt of early false failures: missing `resources/views/vendor/mail` in path mirror (env artifact); fixed before scored run

## 4. Skipped regression workflow

- `workflow_dispatch` https://github.com/EdwardSoaresJr/ark-core/actions/runs/46104826642
- PR labeled full-regression https://github.com/EdwardSoaresJr/ark-core/actions/runs/46104831261
- Status at report time: see CI conclusion below / update when complete

## 5. Fresh install + upgrade

- Covered by `InstallationMailUpgradeAndFreshInstallTest` (included in 16-passed suite above)
- Migration `2026_11_27_130000_*` adds mode columns; env-managed mode reads MAIL_* without shop SMTP fallback when disabled

## Explicitly out of scope (follow-up)

- Appointment notifications, lead confirmations, staff verification email

## Remaining blockers before merge authorization

1. Wait for `regression.yml` conclusion on the candidate SHA (including acceptance commit).
2. SaaS pin advance must update Stancl structural assertion to `CustomerOutboundMail` (or equivalent) — behavioral hosted isolation already green in Core.
3. No merge / production deploy until owner authorizes.
