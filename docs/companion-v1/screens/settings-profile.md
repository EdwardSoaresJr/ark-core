# Screen spec — Settings & Profile

**ID:** `companion.screen.settings-profile`  
**Role(s):** All  
**Status:** 📝 draft — Edward review

---

## Job

Operator identity · notification preferences · **phone online status** · sign out — hidden until More, never clutters home.

---

## Profile screen

- Name · email · role badge
- Presence — Available · Away (P1)
- Station — read-only or change (P1)
- Edit profile → desktop-only P0 · rare mobile edits defer

---

## Settings screen

### Notifications

- Customer messages · calls · inspection uploads · estimate approvals
- Per-category toggles — shop defaults respected

### Phone / SIP (`phone-sip-status` section)

- Registration status — **Online** / **Offline** / **Connecting**
- Shop line name — not extension number jargon
- Troubleshoot — re-register · check network · link to support doc
- **Operator language:** "Front Counter phone" not "SIP endpoint"

### Appearance

- Dark mode — follow system default P0

### About

- Version · build · environment badge (internal)
- Help · contact shop admin

### Sign out

- Destructive · confirm

---

## Flows

More tab → Settings · Profile

Home status line `Phone offline` → phone section

---

## Data & API

**Existing:** mobile shell capabilities · voice registration status endpoint

---

## Edward sign-off

- [ ] Phone status understandable without SIP vocabulary
- [ ] Ready for Flutter
