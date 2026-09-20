# ARK Mobile Projection v1

**Status:** Production Workspace v1 milestone — see [ark-mobile-production-workspace-v1.md](./ark-mobile-production-workspace-v1.md)  
**Product doctrine:** [ark-mobile-workflow-doctrine.md](./ark-mobile-workflow-doctrine.md) — workflow engine, not CRUD screens  
**Sequence:** Authority (ARK V2) → **Mobile projection** → Production Workspace UX

**Interaction language:** [ark-workspace-interaction-language-v1.md](../ecosystem/ark-workspace-interaction-language-v1.md)

ARK Mobile is **not** a separate product authority. It is the **production workspace** for technicians — a phone/tablet projection of existing ARK V2 truth.

**Production Workspace:** [ark-mobile-production-workspace-v1.md](./ark-mobile-production-workspace-v1.md)  
**Communications transport lock:** [ark-mobile-communications-authority-contract.md](./ark-mobile-communications-authority-contract.md)  
**Notification authority lock:** [ark-mobile-notification-doctrine.md](./ark-mobile-notification-doctrine.md)

## Audiences

| Audience | Phase 1 |
|----------|---------|
| Technician | My Work, assigned RO, findings, assigned comms |
| Advisor | Work + shop communications |
| Owner / manager | Same API; owner surfaces later |

## Principle

```
Authority (ARK V2) → Projection (Mobile API) → Flutter UI
```

- No duplicate users, permissions, or workflow state
- No mobile-only tables for operational truth
- Laravel + Spatie remain permission authority
- Flutter hides/shows UI from `/api/mobile/me` permissions

## Authentication

- **Laravel Sanctum** bearer tokens
- `POST /api/mobile/auth/login` → token
- `Authorization: Bearer {token}` on all other routes
- Tokens stored in Flutter via `flutter_secure_storage` — never SharedPreferences
- Same `users` table as web; customer role blocked

## Phase 1 API

| Method | Path | Purpose |
|--------|------|---------|
| POST | `/api/mobile/auth/login` | Issue Sanctum token |
| POST | `/api/mobile/auth/logout` | Revoke current token |
| GET | `/api/mobile/me` | User shell: roles, permissions, capabilities |
| POST | `/api/mobile/device` | Register device (observability only) |
| GET | `/api/mobile/work` | My Work cards |
| GET | `/api/mobile/repair-orders/{ro}` | RO detail projection |
| POST | `/api/mobile/repair-orders/{ro}/findings` | Create `InspectionItem` finding |
| GET | `/api/mobile/repair-orders/{ro}/inspection-photos/{photo}` | Stream finding photo |
| GET | `/api/mobile/communications` | Thread list |
| GET | `/api/mobile/communications/{conversation}` | Thread timeline |
| POST | `/api/mobile/communications/{conversation}/messages` | Customer SMS (ops access) |
| POST | `/api/mobile/communications/{conversation}/internal-notes` | Internal note |
| GET | `/api/mobile/notifications` | Polling v1 (derived, no new authority) |
| GET | `/api/mobile/attention` | Advisor Attention slice (decision pressure + comms queue) |
| POST | `/api/mobile/tools/vin-decode` | Vehicle identity (NHTSA/PartsTech via V2) |
| GET/POST | `/api/mobile/intake/*` | Advisor check-in (V2 Intake authority) |
| PATCH | `/api/mobile/repair-orders/{ro}/concerns/{concern}/production-status` | Scope production status (approved scopes) |

Route names: `api.mobile.*`

### Communications (projection only)

Mobile comms endpoints wrap **`Conversation`**, **`ConversationMessage`**, and **`UnifiedOperationalTimeline`**. Outbound reply uses `SendOutboundMessageAction` on the server — never Twilio from Flutter.

See [ark-mobile-communications-authority-contract.md](./ark-mobile-communications-authority-contract.md).

### `/api/mobile/me` shell

Flutter shapes navigation from authority — not hardcoded roles:

```json
{
  "user": { "id", "name", "email", "display_phone", "role_labels" },
  "roles": ["technician"],
  "permissions": ["production.access", "repair_orders.view", "..."],
  "capabilities": {
    "mobile": true,
    "repair_orders": true,
    "findings": true,
    "communications": true,
    "customer_reply": false,
    "internal_notes": true,
    "intake": false,
    "attention": false
  }
}
```

Login returns the same shell fields plus `token` and `token_type`.

### Device registration

`POST /api/mobile/device` — device observability and optional push token hint:

- `device_name`, `platform` (`ios`|`android`|`ipados`|`other`), `app_version`
- Upserts by user + device_name; updates `last_seen_at`
- Optional `fcm_token` — transport hint stored on `mobile_devices` when push is enabled later (ARK-owned device truth)

Push transport is **deferred**. See [ark-mobile-notification-doctrine.md](./ark-mobile-notification-doctrine.md). When observation justifies it, configure **Settings → Communications → Mobile** (Firebase project + service account JSON, or optional `FIREBASE_CREDENTIALS` file path).

## Technician scope

Technicians discover **assigned work only**:

- My Work filters `assigned_technician_id = me`
- RO detail gated by assignment
- Findings use `InspectionCaptureLinks::canRecord`
- Communications: conversations linked to assigned ROs only — not shop inbox
- Customer reply requires `operations.access` (advisor/admin)

## Flutter client (separate repo)

Legacy `arksms_shop` is reference only — not port wholesale.

New Flutter app should use:

- **Riverpod** for state (document in app README)
- Layers: `api/` → `repositories/` → `models/` → `screens/`
- Phone-first layout; tablet-friendly; large touch targets
- Dark-shop-friendly contrast
- Camera → compress → upload → ARK stores attachment

Phase 3 alerts: advisors use `/api/mobile/attention` (pull + `poll_after_seconds`); technicians poll `/api/mobile/notifications`. Push transport (APNs/FCM) is **deferred** per [ark-mobile-notification-doctrine.md](./ark-mobile-notification-doctrine.md).

## Non-goals (mobile client)

- Customer portal app
- Scheduling, payments, estimate builder
- Full advisor desktop replacement
- Mobile-only permissions or users
- New inspection / finding authority
- Provider SDKs in Flutter (Twilio, Asterisk, SIP, Telnyx)
- Parallel SMS/telephony inbox authority on device

## Acceptance test

**Production Workspace v1 floor test:** Can a technician complete an entire repair beside the vehicle without returning to desktop?

**Technician**

1. Login → token
2. My Work shows assigned RO
3. Open RO → concerns, approved work, findings
4. Add finding with photo + measurement
5. Read comms on assigned RO; internal note if permitted

**Advisor**

1. Login
2. Communications list with customer replies
3. Open thread → reply or internal note
4. Open related RO from work list

## Implementation

- PHP: `app/Ark/Mobile/`
- Routes: `routes/api.php`
- Tests: `tests/Feature/Mobile/MobileApiTest.php`
