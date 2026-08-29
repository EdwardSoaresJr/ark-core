# VVX Continuity Appliance Audit v2

**Date:** 2026-06-27  
**Supersedes:** v1 (web-page optimization framing)  
**Device:** Poly VVX350 — Right Phone, ext 101, station Right

---

## Mental model (correct)

The VVX is **not** a miniature ARK. It is a **continuity appliance**.

It exists to preserve continuity while your hands are busy at the Front Counter.

Wrong question: *How do we make the microbrowser faster?*

Right question: *What is the absolute minimum context a person standing at this station needs right now?*

The operation follows the operator. The VVX is the **Front Counter station** — whoever unlocks the workstation owns the screen. Molly signs in → same HTML, different projection → DOM patch. No reload.

## VVX 350 hardware ceiling (floor-verified)

Poly: **VVX 3xx/4xx use the microbrowser as screensaver only when idle** — not during calls. When a call rings, firmware shows the native Incoming Call screen. ARK cannot replace that with a custom full-screen takeover on VVX350. Ring urgency = audio + line key + desktop pop. Microbrowser value = **idle continuity between calls**.

Implications:
- `ringing` / `on_call` postures are for JS-capable refresh between states; they will not paint during active ring on 3xx.
- First paint must be **server-rendered HTML** — VVX microbrowser often does not run JS reliably.
- `mb.idleDisplay.refresh="5"` + `screenSaver.waitTime="0"` — phone re-GETs the URL; `refresh="0"` caused ~15s blank on floor.

---

## Product rule: interrupts own the screen

When the phone rings, nobody cares about three waiting approvals.

**Highest-priority posture wins the entire display.**

| Interrupt | Screen behavior |
|-------------|-----------------|
| Incoming call | Full takeover — caller, vehicle, RO, one headline |
| On call | Minimal — customer, RO, note, duration |
| (future) Customer arrived, emergency | Same rule — everything else disappears |

Idle is **not** a dashboard. No lists, moments, cards, scrolling, logo chrome.

---

## Postures (not pages)

One static HTML instrument cluster. Five postures:

| Posture | Meaning |
|---------|---------|
| `idle` | Signed in, no live call — minimal continuity |
| `ringing` | Incoming — **full interrupt** |
| `on_call` | Active call — minimal |
| `locked` | Station ready, no operator |
| `offline` | Device not registered / no workstation |

Future: `call_ended` (log note / dismiss) before returning to idle.

---

## Architecture (implemented)

```
StationOrientationProjection (call overlay, extension match)
          │
          ▼
StationPostureProjection     ← HOT: posture + revision only
          │
          ▼
StationScreenProjection      ← COLD: posture-specific tiny payload
          │
   ┌──────┼──────────┐
   ▼      ▼          ▼
 VVX    Wallboard   Kiosk (future)
```

### Two-tier poll (no full page reload)

1. **Poll** `GET /voice/device-screen/{token}/posture` every 400ms (ringing) … 10s (locked/offline)
   - Returns: `{ "posture": "ringing", "revision": 43, "poll_ms": 400, "operator_id": 12 }`
   - No continuity, no observation stream, no moments

2. **Only on posture / revision / operator change:** fetch `GET /voice/device-screen/{token}/screen`
   - Posture-specific payload only

### Payload examples

**Idle:**

```json
{
  "posture": "idle",
  "station": "Front Counter",
  "operator": "Edward",
  "approvals": 2,
  "arrival": "3:30 PM",
  "availability": "Available"
}
```

**Ringing:**

```json
{
  "posture": "ringing",
  "customer": "Sarah Johnson",
  "vehicle": "2018 Ford F-150",
  "ro": "4821",
  "headline": "Waiting Approval"
}
```

**On call:**

```json
{
  "posture": "on_call",
  "customer": "Sarah Johnson",
  "ro": "4821",
  "note": "Brakes",
  "duration": "00:03:21"
}
```

### Static shell

- `resources/views/communications/device-appliance.blade.php`
- Never `location.reload()`. Never meta refresh.
- `mb.idleDisplay.refresh="0"` in Poly provision (serialization v9)
- Layout swap + `textContent` patches only

---

## Idle layout (target UX)

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

## Ringing layout (target UX)

```
INCOMING CALL

Sarah Johnson
2018 Ford F-150
RO #4821
Waiting on approval
```

No logo. No navigation. No moments. The call is the moment.

---

## Latency budget (revised)

| Step | Target |
|------|--------|
| Posture poll | 400ms during ring |
| Posture endpoint server | &lt;20ms |
| Screen fetch | Only on transition |
| Screen endpoint server | &lt;80ms (no observation stream) |
| DOM patch | &lt;50ms on VVX |
| **Perceived ring → screen** | **&lt;1s** |

Previous v1 bottleneck (full continuity every 1s + full reload) is **removed**.

---

## What is deliberately not on VVX

- Observation stream / moments list
- Shop today pulse narrative
- ARK branding bar
- Continuity badge math
- Scrollable attention queue
- Anything that competes with an interrupt

Mobile continuity API remains the rich surface. VVX is the **appliance slice**.

---

## Floor checklist

| Test | Pass |
|------|------|
| Ring → `ringing` posture ≤1s | |
| Screen shows customer + vehicle | |
| Answer → `on_call`, duration ticks | |
| Molly unlock → operator changes without HTML reload | |
| Lock station → `locked` | |
| `curl` posture endpoint &lt;50ms | |
| Re-provision after deploy (serialization v9) | |
| Traefik HTTP route after container recreate | |

---

## Implementation order (status)

| Step | Status |
|------|--------|
| `StationPostureProjection` | ✅ |
| Tiny posture endpoint | ✅ |
| Static HTML shell | ✅ |
| DOM patch only | ✅ |
| Full-screen posture layouts | ✅ (ringing / on_call interrupt) |
| Thin posture payloads | ✅ |
| Remove idle chrome / moments | ✅ |
| Measure latency on real VVX | ⏳ floor |
| `call_ended` posture + dismiss | 🔜 |
| Wallboard / kiosk renderer | 🔜 |

---

## Key files

| Path | Role |
|------|------|
| `StationPosture.php` | Posture enum + poll intervals |
| `StationPostureProjection.php` | Hot path |
| `StationScreenProjection.php` | Cold posture payloads |
| `device-appliance.blade.php` | Static instrument cluster |
| `CommunicationDeviceMicrobrowserPostureController.php` | `/posture` |
| `CommunicationDeviceMicrobrowserScreenController.php` | `/screen` |
| `PolyPhoneProvDeviceConfigBuilder.php` | `idleDisplay.refresh=0` |

---

## Continuity appliance notes (historical)

```
VVX is a continuity appliance, not a web app. Static HTML never reloads.
Poll GET /voice/device-screen/{token}/posture (hot). On posture/revision/operator change only, fetch /screen and DOM-patch.
Postures: idle, ringing, on_call, locked, offline. Interrupts own full screen.
Station follows operator (workstation current_operator_user_id). No observation stream on VVX path.
Next: call_ended posture, floor latency measure, re-provision VVX serialization v9.
```
