
**Purpose:** Fill gaps in local screenshots — especially **incoming call** and **post-call** — using official docs and public demos.


**ARK specs:** [`../../screens/incoming-call.md`](../../screens/incoming-call.md) · [`../../screens/active-call.md`](../../screens/active-call.md) · [`../../screens/post-call.md`](../../screens/post-call.md)

---

## Incoming call (downloaded references)

| Asset | Path |
|-------|------|
| **Quo incoming modal** + menu option badge + Accept/Decline | `external/quo/incoming-phone-menu-insight.png` |
| Quo caller ID marketing pattern | `external/quo/caller-id-marketing.webp` |

Full index: [`CATALOG.md`](CATALOG.md)

| Pattern | Source | ARK reference use |
|---------|--------|-------------------|
| **Switch Location** mid-call | Same image | RO/customer context switch — not pipeline |
| **Quo incoming modal** | **`quo/incoming-phone-menu-insight.png`** | Accept/Decline rhythm · context line before answer |
| **Quo menu option on ring** | Same · [phone menu blog](https://www.quo.com/blog/phone-menu/) | ARK: estimate status / RO reason — not IVR digit |
| Spam label | **`multi-location-incoming-2.png`** | Unknown caller styling |



---

## Active call — local + official


**Observed layout (reference rhythm):**

- Top: back · **DND** · **Transfer**
- Center: callee name · number · **Ringing…** · avatar placeholder · subtle wave background
- Bottom card: outbound label + **Call from** number
- **Tool grid (5):** Calendars · Notes · Tasks · Payments · Profile
- **Controls row:** Earpiece · Mute · Hold · Keypad · **End** (red)

### Official feature list (in-call)


|------------------|-----------------|-----------------|
| Speaker / Mute / Hold / Keypad / End | Standard telephony row | Same |
| DND | Contact-level DND toggle | Shop-appropriate — defer or customer prefs |
| Calendars | Schedule without leaving call | **Schedule / book appointment** on RO |
| Notes | Real-time notes on contact | **RO / customer note** |
| Tasks | CRM task create | **Reject product** — reference sheet layout only |
| Payments | Payment hub | **Take payment** on RO balance |
| Profile | Contact CRM record | **Customer + vehicle + RO** strip |

**Transfer flow (reference):** Transfer button → staff / contacts / dial pad / recents → warm vs blind → participant **bottom sheet** (leave · remove · end all).

**ARK must beat:** Same "never leave the call" grid — swap CRM nouns for **Open RO · Text · Schedule · Pay · Add note**.

---

## Post-call (gap — document from web)


| Action | Source |
|--------|--------|
| Notes (finish in-call notes) | Outbound calling article |
| Calendars · Tags · Profile | Same |
| DND · Message · Call Back | Same |
| Tasks | Same — reference only |

**Disposition pattern:** Pick outcome at hangup · sub-account switch if mismatch · feeds workflows.

**ARK replacement:** Post-call sheet = **Add note · Send text · Schedule · Open RO · Mark handled** — no CRM disposition enum theater unless shop earns it.

**Spec:** [`../../screens/post-call.md`](../../screens/post-call.md)

---

## Dialer — local screenshots

| File | Screen | Patterns to steal |
|------|--------|-------------------|
| `IMG_2761.PNG` | Keypad | Large dial pad · Call from bar · Recents / Contacts / Keypad tabs |
| `IMG_2762.PNG` | Contacts search | Search by name · phone · email |
| `IMG_2763.PNG` | Recents | Direction icons · missed=red · info button · **Call from** banner |
| `IMG_2738.PNG` | Inbound settings | Radio cards · Active sub-account vs selected |


**ARK must beat:** Recents show **name + vehicle**, not `(719) xxx-xxxx` alone. Primary dial path = **search**, not keypad.

---

## Video walkthroughs (visual reference)

Watch for **call UI frames** — pause and ticket like screenshots.

| Video | URL | Notes |
|-------|-----|-------|
| LC Phone setup (2026) | https://www.youtube.com/watch?v=Aj6Kx0jcHpI | Telephony setup — less UI, more config |


---

## SIM vs VoIP (context only)



---

## Local presence / outbound ID


ARK: shop outbound number from settings — not a product surface for v1. Reference: "Call from" bar pattern (`2760`, `2763`).
