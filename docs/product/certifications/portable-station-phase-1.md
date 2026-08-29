# Certification record — Portable Station Phase 1

**Certification:** Portable Station Phase 1  
**Track:** B — Portable Station  
**Owner:** Alex Rivera  
**Scenario source:** [advisor.md](../day-in-the-life/advisor.md) · 7:55 AM + 8:10 AM

## Why this matters

Edward can leave the Front Counter without leaving the operation — phone opens into orientation, not a raw inbox.

---

## PASS levels

| Level | Status | Date | Signed by |
|-------|--------|------|-----------|
| Engineering Certified | ✓ | 2026-06-27 | API + Flutter shipped |
| Operationally Certified | ⬜ | | 8:10 AM scenario on device |
| Production Certified | ⬜ | | One week daily use |

## Capability checklist

| Check | Status | Date | Evidence | Proof |
|-------|--------|------|----------|-------|
| `GET /api/mobile/orientation` composes Attention + shell | ✓ | 2026-06-27 | `MobileOrientationProjection` | `MobileApiTest` |
| Advisor/manager home tab is orientation briefing | ✓ | 2026-06-27 | `OrientationHomeScreen` default tab | ark-mobile |
| `POST /api/mobile/conversations/{id}/read` | ✓ | 2026-06-27 | `MobileConversationMarkReadController` | `MobileApiTest` |
| MMS attachments on mobile thread | ✓ | 2026-06-27 | Sanctum-gated attachment route | `MobileApiTest` |
| FCM token registration on device | ✓ | 2026-06-27 | `MobileDeviceRegisterController` | `MobileApiTest` |
| Push deep-link handler stubbed | ✓ | 2026-06-27 | `PushRegistrationService` + `ArkFirebaseMessagingService` | Firebase client config on device; server operational 2026-06-27 |

## Operational checklist

| Check | Status | Date | Evidence | Proof |
|-------|--------|------|----------|-------|
| 7:55 AM — open app → orientation home, not Conversations | ⬜ | | | |
| 8:10 AM — tap text → oriented thread → reply → desktop parity | ⬜ | | | |
| Push notification opens conversation context | ⬜ | | | Server operational; device rebuild + APNs (iOS) + floor test pending |

## Notes

- Engineering ships API + Flutter orientation home; Operational requires Edward's device + floor 8:10 scenario.
- Production FCM transport enabled 2026-06-27 (`demo-auto-ark-mobile`). See `docs/mobile/firebase-mobile-push-setup-doctrine-v1.md`.
- Technician profile uses assigned RO items on orientation home (not Attention).

## Corrections

- **2026-06-28:** Operational target superseded by [Customer Arrival Workflow](./customer-arrival-workflow.md) per [workflow-completion-certification.md](../../engineering/workflow-completion-certification.md). Phase 1 engineering foundation remains valid; certify complete workflows, not screens.
