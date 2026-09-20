# ARK Mobile Notification Doctrine

**Status:** v1  
**Sequence:** Authority → Observation → Transport (when proven)

## Rule

ARK owns notification authority.

Third-party notification providers are **transport only**.

The mobile application must never depend on Firebase services for business logic, identity, storage, permissions, communications, operational truth, or workflow execution.

---

## Allowed

Provider transport only:

- Apple Push Notification Service (APNs)
- Firebase Cloud Messaging (FCM) — transport layer only
- Future notification transports

Example:

```
ARK
  → Notification Authority
  → Push Provider
  → Device
```

---

## Forbidden

- Firebase Auth
- Firestore
- Realtime Database
- Cloud Functions
- Firebase Analytics as business authority
- Firebase Remote Config as operational authority
- Any workflow that requires Firebase to determine ARK behavior

---

## ARK Authority

ARK remains authority for:

- Users
- Permissions
- Devices
- Repair Orders
- Communications
- Conversations
- Findings
- Notifications
- Attention
- Telephony
- Call Sessions

Push providers deliver notifications only.

---

## Notification authority vs transport

**Notifications are authority events.** ARK creates them from operational truth — not from Firebase, APNs, or poll mechanics.

**Push delivery is transport.** Business code speaks in ARK terms; only the transport implementation names the provider.

```
Inbound SMS (authority)
        │
DispatchMobilePushForInboundMessage
        │
MobilePushService (resolves device tokens, shop/user already decided)
        │
PushTransport::send(...)
        │
FirebasePushTransport (today — APNs direct, OneSignal, etc. tomorrow)
        │
Staff mobile app
```

One **ARK Staff** Firebase project for the single App Store / Play Store binary — not per shop. Every device authenticates to ARK; ARK decides `device → shop → user → role → send`. Firebase knows only: deliver this packet to this device token.

| Layer | Responsibility |
| --- | --- |
| `MobilePushService` | Who gets which ARK-authored packet |
| `PushTransport` | Deliver packet to device token |
| `FirebasePushTransport` | FCM HTTP v1 (implementation detail) |

Examples of notification **authority** (vocabulary may grow):

| Notification (authority) | Source truth |
| --- | --- |
| Customer Replied | Conversation / inbound message |
| Work Approved | Estimate approval / concern disposition |
| Parts Received | Parts procurement / RO line |
| Incoming Call | CallSession |
| Mentioned In Note | Internal note / handoff |

**Delivery mechanisms** are not authority:

- Polling (`/api/mobile/attention`, `/api/mobile/notifications`)
- `PushTransport` implementations (Firebase/FCM today)
- APNs (future direct transport)
- Email
- SMS

Notifications are not created by Firebase. They are created by ARK.

---

## Device Registration

Device registration remains inside ARK:

`POST /api/mobile/device`

ARK owns:

- device id
- platform
- push token
- last seen
- user assignment

Push providers do not own device truth.

Optional `fcm_token` on register is a **transport hint** stored on `mobile_devices` — not device authority and not required for login, comms, or workflow.

---

## Architectural Test

Question:

**If Firebase disappeared tomorrow, what breaks?**

Desired answer:

Push delivery stops until another transport is configured.

Everything else continues functioning.

If login, communications, permissions, repair orders, findings, or workflow stop functioning, provider details have leaked across the authority boundary.

That is a bug.

---

## Current status (Demo Auto Repair)

**Transport enabled on production** (2026-06-27) after Portable Station Phase 1 observation. Advisors still have Attention polling when push fails or device is unregistered.

Setup wiring: [firebase-mobile-push-setup-doctrine-v1.md](./firebase-mobile-push-setup-doctrine-v1.md)

---

## Related

- [firebase-mobile-push-setup-doctrine-v1.md](./firebase-mobile-push-setup-doctrine-v1.md)
- [ark-mobile-projection-v1.md](./ark-mobile-projection-v1.md)
- [ark-mobile-communications-authority-contract.md](./ark-mobile-communications-authority-contract.md)
- ark-pressure-first.mdc — observe before automate
