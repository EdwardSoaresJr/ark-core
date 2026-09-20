# Screen spec — Launch & Login

**ID:** `companion.screen.launch-login`  
**Role(s):** All staff  
**Status:** 📝 draft — Edward review

---

## Job

**Open Companion → authenticated → right role home** in under 10 seconds on a cold start — no admin jargon.

---

## Product quality gate

| | Reference CRM | ARK Companion |
|---|-----|---------------|
| **Verdict** | Agency sub-account switcher · CRM login | **Target: Yes** |
| **Why** | Multi-location CRM complexity | **One shop (Demo Auto Repair P0)** · staff Breeze login · lands on **role home** |

---

## Screens in this spec (one flow)

### 1. Launch / splash

- ARK mark · shop name optional
- Load session token · capabilities · phone registration state
- **No** marketing carousel
- Max ~1.5s branded splash then route

**Routes:**

- Valid session → role home
- Expired → Login
- First install → Login then Permissions

### 2. Login

- Email · password — same credentials as `/app/login`
- **Sign in** — primary full-width
- Forgot password → in-app browser to staff reset URL
- Error — inline · no toast-only failures


### 3. Shop select (P1 — spec now, ship later)

- Only when user belongs to multiple shops
- List shop names · last used pinned
- P0: **skip** — auto single tenant

### 4. PIN / station unlock (P1)

- Shared bay tablet pattern — not Edward's phone P0
- Document: 4–6 digit · operator name shown after unlock

### 5. Permissions prompt (first run + upgrade)

Sequential sheets — not one scary wall:

| Permission | Copy (operator language) |
|------------|---------------------------|
| Notifications | "Know when customers reply or inspections upload" |
| Microphone | "Talk on shop calls from your phone" |
| Phone / calls | "Answer the shop line from Companion" |

**Not now:** contacts upload · location · camera (request in inspection flow)

Each: **Allow** · **Not now** · skip does not block login — degrades features with calm banner

---

## Role routing after auth

| Role | Default home |
|------|--------------|
| Advisor · Owner (counter mode) | [`home-continuity.md`](home-continuity.md) |
| Technician | [`my-work.md`](my-work.md) |
| Owner (owner mode P1) | Owner pulse |

Capabilities payload from server — **no hardcoded role tabs in Flutter**

---

## Interaction patterns

| Gesture | Behavior |
|---------|----------|
| Background resume | Biometric re-lock P1 · P0 returns to last workspace if session valid |
| Logout | Settings → confirm → Login |
| Session expired mid-flow | Sheet "Sign in again" · preserve deep link after login |

---

## States

| State | UX |
|-------|-----|
| Loading | Splash only |
| Offline at login | "Can't reach shop" · retry |
| Wrong password | Inline error |
| Phone not registered | Login succeeds · banner on home → phone settings |

---

## Flows

Cold start → splash → login → permissions (first run) → Home

Push tap while logged out → login → **deep link target** (not Home)

Link: all P0 flows assume authenticated session

---

## Data & API

**Existing:** `POST /api/mobile/auth/login` · Sanctum token · capabilities shell payload

**Needs:**

- Role + default route in shell response
- Push token registration after permissions
- SIP registration trigger after phone permission (advisor)

---

## Edward sign-off

- [ ] Login feels like staff app · not SaaS admin
- [ ] Lands on continuity home for advisor
- [ ] Ready for Flutter
