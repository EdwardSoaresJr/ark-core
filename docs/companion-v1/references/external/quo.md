# External reference — Quo (formerly OpenPhone)

**Product:** [Quo](https://www.quo.com/) — business phone + SMS. Rebrand from OpenPhone; docs still say "formerly OpenPhone" in places.

**Doctrine:** Reference · not clone — steal **call UX craft**, not a second inbox product.

**Assets:** [`quo/`](quo/) · catalog [`CATALOG.md`](CATALOG.md)

---


|---|-----|-----|
| **Strength** | CRM in-call grid · location · transfer | **Best-in-class phone UX** · incoming modal · inbox clarity |
| **Weakness for shop** | Generic CRM · pipeline noise | No vehicle · RO · estimate |
| **ARK use** | Workflow breadth reference | **Communications habit-forming reference** |


---

## P0 reference screens (downloaded)

### Incoming call — `incoming-phone-menu-insight.png`

From [Quo phone menu blog](https://www.quo.com/blog/phone-menu/) (Nov 2024 feature).

**Steal:**
- Full-screen or card modal · Accept green · Decline red
- **Context before answer:** inbox name + emoji · caller name · **why they're calling** (menu option `3`)
- Footnote: shared inbox behavior ("Declining still allows others to pick up")

**ARK adds:** vehicle line · RO chip · estimate one-liner · last SMS — not menu option alone.

**Spec:** [`../../screens/incoming-call.md`](../../screens/incoming-call.md)

### Caller ID — `caller-id-marketing.webp`

From [call screening product page](https://www.quo.com/product/call-management/call-screening).

**Steal:** Quo Audio label · which business line · contact name · inbox emoji

**ARK:** Shop name + station optional · customer + vehicle primary.

### Inbox — `quo-threads.png` · `screensdesign-*.webp`

**Steal:** Thread density · avatar · preview · calm spacing

**ARK:** Automotive row (vehicle · RO · badges) on [`conversation-list.md`](../../screens/conversation-list.md)

### In-call — ScreensDesign showcase notes

Showcase calls out **clean in-call UI @ 03:39** — see `screensdesign-4.webp` (large frame). Transfer tagged "Business" — reference tier gating, not ARK P0.

---

## Official docs (patterns, not images)

| Topic | URL |
|-------|-----|
| Receiving calls | https://support.quo.com/core-concepts/calling/receiving-calls |
| Call screening | https://www.quo.com/product/call-management/call-screening |
| Phone menu → incoming insight | https://www.quo.com/blog/phone-menu/ |
| Changelog (mobile tasks, menu insights) | https://support.quo.com/changelog |

---

## Reject as ARK product · still reference

- Sona AI answering · call flow builder admin (`phone-menu-blog.png`)
- Generic second-number onboarding (ScreensDesign 1–3)
- Business-plan upsell tags on in-call features

---

## Comparison row (product review)

| Quo screen | ARK screen | Better? Why? |
|------------|------------|--------------|
| Incoming modal | Incoming call | ARK when vehicle+RO+estimate on ring |
| Inbox threads | Communications | ARK when every row is shop-native |
| In-call (minimal) | Active call | ARK when shop grid beats CRM grid |
| Search/contact | Global search | ARK when Emma → call·text·RO·pay |
