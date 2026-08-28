# Figma handoff — gate screens (Companion v1)

**Purpose:** Pixel-level layout notes for the four screens that gate Sprint 1 floor certification. Pair with specs + reference PNGs.

**Canvas:** 390×844 (iPhone 14 / Razr logical) · safe area respected  
**Brand:** ARK cerulean `#0099cc` · ecosystem mark  

---

## 1. Incoming call

**Spec:** [`../screens/incoming-call.md`](../screens/incoming-call.md)  
**API:** `GET /api/mobile/incoming-call/context`

```
┌─────────────────────────────────────┐
│         (status bar)                │
│                                     │
│         Emma Hathorn          28pt  bold
│         (512) 555-0199        15pt  muted
│                                     │
│    2019 Honda Civic · ABC123  17pt
│    ┌─────────────────────────┐
│    │ RO #1599 · Waiting approval │ chip
│    └─────────────────────────┘
│                                     │
│  ┌─ Estimate ────────────────────┐  │
│  │ Sent · viewed 2× · $1,847.00   │  │
│  └────────────────────────────────┘  │
│  ┌─ Last message ─────────────────┐  │
│  │ "Can I pick up at 5?" · 2:14 PM│  │
│  └────────────────────────────────┘  │
│                                     │
│  ┌──────── Decline ────────┐        │
│  │                         │        │
│  │      ANSWER (green)     │  56pt  │
│  │                         │        │
│  └─────────────────────────┘        │
│         Message (text)              │
└─────────────────────────────────────┘
```

**Steal from Quo:** Accept/Decline rhythm · modal clarity  
**ARK wins:** Vehicle + RO + estimate cards above buttons — not inbox emoji alone

---

## 2. Conversation thread + Manage sheet

**Spec:** [`../screens/conversation-thread.md`](../screens/conversation-thread.md)  

**Thread frame**

```
┌─────────────────────────────────────┐
│ ←  Emma Hathorn          📞  ⋯      │
│     2019 Civic · RO #1599 · chip    │
├─────────────────────────────────────┤
│            Today                    │
│  ┌ inbound ─────────────┐           │
│  │ Can I pick up at 5?   │           │
│  └───────────────────────┘           │
│           ┌ outbound ────┐          │
│           │ Yes — see you! │          │
│           └──────────────┘          │
├─────────────────────────────────────┤
│ [Estimate] [Pay] [Inspection]       │ quick row
│ 📎  Message Emma…            Send   │
└─────────────────────────────────────┘
```

**Manage sheet (half height)**

| Row | Icon | Label |
|-----|------|-------|
| 1 | RO | Open repair order |
| 2 | ↗ | Send estimate |
| 3 | $ | Take payment |
| 4 | 📅 | Schedule |
| 5 | ✓ | Mark handled |


---

## 3. Payment sheet

**Spec:** [`../screens/payment-sheet.md`](../screens/payment-sheet.md)  

```
┌─────────────────────────────────────┐
│ ─── drag handle                     │
│ Emma Hathorn · 2019 Civic           │
│ RO #1599 · Balance due              │
│                                     │
│ $ 1,847.00                    32pt  │
│                                     │
│ [ Cash ] [ Card ] [ Terminal ] [Link]│
│                                     │
│  1   2   3                          │
│  4   5   6        (keypad 64pt keys)│
│  7   8   9                          │
│  ⌫   0   .                          │
│                                     │
│ ┌──── Record payment ────────────┐  │
└─────────────────────────────────────┘
```

**Authority:** Amount from server · never editable above balance without override

---

## 4. Inspection item (Ben push)

**Spec:** [`../screens/inspection-item.md`](../screens/inspection-item.md)  
**Deep link:** `companion://repair-orders/{id}/inspection/items/{itemId}`

```
┌─────────────────────────────────────┐
│ ←  Brake pads — front               │
│     RO #1599 · 2019 Civic           │
│     Item 4 of 12 · Needs review     │
├─────────────────────────────────────┤
│ FAIL · Finding text…                │
│ ┌────┐ ┌────┐ ┌────┐               │
│ │img │ │img │ │ +  │  photo strip   │
│ └────┘ └────┘ └────┘               │
├─────────────────────────────────────┤
│ Advisor: internal note field        │
│ [ Open RO ]  [ Mark reviewed ]      │
└─────────────────────────────────────┘
```

**Push rule:** Tap notification → **this screen** — never Home

---

## Component tokens (all four)

| Token | Value |
|-------|-------|
| Screen padding | 16 |
| Identity strip height | 56–72 |
| Primary button height | 48–56 |
| List row | 72–80 |
| Chip height | 32 |
| Sheet corner radius | 16 top |

Full map: [`components/screen-component-map.md`](screen-component-map.md)
