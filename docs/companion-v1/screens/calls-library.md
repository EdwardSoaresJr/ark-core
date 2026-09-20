# Screen spec — Voicemail & Calls Library

**ID:** `companion.screen.calls-library`  
**Role(s):** Advisor  
**ARK doctrine:** Desktop **Calls & VM** surface is protected — mobile mirrors job, not Attention queue  
**Status:** 📝 draft — Edward review

---

## Job

**Recovery, not interrupt** — listen to voicemail · see missed calls · mark handled · call back with customer + vehicle context.

---

## Product quality gate

| | Reference CRM | Quo | ARK Companion |
|---|-----|-----|---------------|
| **Verdict** | Call log exists | Missed call UX clean | **Target: Yes** |
| **Why** | Generic | No RO context | **Customer · vehicle · RO on every row** · handled syncs with shop authority |

---

## Layout

### Shell

- **Title:** Calls
- **Segments:** Voicemail · Missed · Recent · All
- **Search** → global search phone scope

### Row

- Customer · time
- Vehicle · RO if known
- Voicemail: duration · playback scrubber inline or detail
- Badge: unhandled · handled

### Detail

- Playback · transcript (P1)
- **Call back** · **Text** · **Mark handled** · note

---

## Interaction patterns

| Gesture | Behavior |
|---------|----------|
| Tap row | Detail + play |
| Swipe left | Mark handled |
| Swipe right | Call back |

---

## Flows

Communications → Calls segment OR More tab

Missed call continuity row → this list filtered

Mark handled → sync `CallSession` authority

---

## Data & API

**Existing:** CallSession · voicemail metadata on desktop Calls surface  
**Need:** `/api/mobile/calls` projection with automotive context

---

## Edward sign-off

- [ ] Matches desktop Calls & VM job in pocket
- [ ] Ready for Flutter
