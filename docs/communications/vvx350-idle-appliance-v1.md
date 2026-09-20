# VVX350 Idle Station Appliance v1

**Status:** Active doctrine  
**Hardware:** Poly VVX 300/350/400 (screensaver microbrowser)  
**Product:** Front Counter **station** — renderer is VVX today; may be Android desk phone, touch kiosk, or wallboard tomorrow.

## Role (not a miniature ARK)

The VVX350 is an **idle station appliance**, not a Front Counter continuity device.

Do not fight Poly firmware during calls. Do not render incoming-call UI in the microbrowser on 3xx/4xx.

Rich interaction lives in **ARK Staff** (Flutter). The VVX quietly shows station context when the desk is idle.

## Screen ownership

| State | Owner | What shows |
|-------|--------|------------|
| Idle | **ARK** | Operator, Ready/Busy, waiting approvals, next arrival, station health |
| Ringing | **Poly** | Native Incoming Call — caller ID, line state |
| On call | **Poly** | Native active call UI |
| Idle after call | **ARK** | Appliance resumes on next screensaver GET |

Think **screen ownership**, not refresh tricks.

## Idle payload (target UX)

```
Front Counter
Edward
────────────
Ready
2 waiting approvals
Next arrival 3:30 PM
────────────
Available
```

## Implementation constraints

- **Server-rendered HTML** on every GET — VVX microbrowser often does not run JS.
- **&lt;10 KB** total page — minimal CSS, minimal JS, one font (Arial), no framework, no icons.
- **Screensaver model** — `mb.idleDisplay` + `screenSaver.type=2`; `refresh=5` on 3xx.
- **Hot path** — `GET /voice/device-screen/{token}/posture` (tiny JSON).
- **Cold path** — `GET /voice/device-screen/{token}/screen` on operator/posture change (JS enhancement).
- **Rollback** — `GET /voice/device-screen/{token}/legacy` (pre-appliance continuity page).

Architecture stack (renderer-agnostic):

```
StationOrientationProjection
        ↓
StationPostureProjection
        ↓
StationScreenProjection::idleApplianceForDevice()
        ↓
   VVX | wallboard | kiosk
```

## Engineering Certified — VVX350 Appliance

Mark **Engineering Certified** when floor verifies (Right Phone first):

| Criterion | Pass |
|-----------|------|
| Idle appears immediately (screensaver) | |
| Idle content correct (station, operator, Ready) | |
| Operator unlock switch updates without manual phone reboot | |
| Idle resumes immediately after call ends | |
| Page is tiny and stable (no TLS, no blank 15s) | |

Not required for cert:

- Custom incoming-call takeover on VVX screen
- Moments / observation stream on phone
- Sub-second ring → custom HTML (Poly owns ring)

## Future research (native call enrichment)

Do **not** replace incoming-call UI. Investigate enriching **Poly’s** native screen:

- VVX XML applications / UC Software parameters
- Push URLs / action URLs
- BLF XML hooks
- SIP caller name / enterprise directory injection

Goal: native screen shows **Sarah Johnson · 2018 Ford F-150 · RO #4821** instead of only `719-555-1234` — higher value than fighting the browser.

## Ambient announcements (station continuity)

When a Front Counter observation occurs, the idle screen **briefly becomes a station announcement** — then auto-expires back to idle (90s) without dismissal.

Examples: `CUSTOMER REPLIED` · Sarah Johnson · Just now

Implemented via `StationContinuityProjection` (projection only) — not VVX-specific. Same projection can drive wall displays, Android desk phones, e-ink.

Poly still owns ring/active call. Announcements only paint when firmware returns to idle screensaver.


- Treating VVX microbrowser as a modern web app (WebSocket, SPA, full continuity)
- `mb.idleDisplay.refresh=0` on 3xx (blank screensaver)
- JS-only first paint
- Engineering time on ring/on-call HTML layouts for VVX350

## Companions

- [vvx-microbrowser-audit-v1.md](./vvx-microbrowser-audit-v1.md) — latency history and stack detail
- [ark-operator-continuity-doctrine.md](../mobile/ark-operator-continuity-doctrine.md) — full continuity on mobile/desktop
- [front-counter.md](../product/certifications/front-counter.md) — operational certification
