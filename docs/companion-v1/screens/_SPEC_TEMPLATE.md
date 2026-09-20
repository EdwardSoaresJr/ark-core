# Screen spec — [Screen name]

**ID:** `companion.screen.[kebab-name]`  
**Role(s):** Advisor · Technician · …  
**Status:** ⬜ draft · 📝 review · ✅ Edward signed

---

## Job (one sentence)

What job does this screen do on the floor?

---

## Product quality gate


| | Reference CRM | ARK Companion |
|---|-----|---------------|
| **Verdict** | | **Not yet / Yes** |
| **Why** | | |

---

## Layout (production spec)

Describe as if handing to Figma — top to bottom, fixed regions.

### Shell (persistent)

- **Status bar:** …
- **Identity strip:** customer · vehicle · RO · status chip (when applicable)
- **Nav:** back · title · actions

### Body

- **Section 1:** …
- **Section 2:** …

### Footer / action zone

- Primary action · secondary · tab bar visibility

**Safe areas · scroll behavior · keyboard:** …

---

## Typography & density

| Element | Style | Example |
|---------|-------|---------|
| Primary identity | Display | Skylar Hathorn |
| Secondary | Body | 2019 Honda Civic · RO #1599 |
| Metadata | Label | 3 min ago |

Operational density — calm under pressure, not SaaS whitespace.

---

## Components

From [`../design-system/components.md`](../design-system/components.md):

- …

---

## Interaction patterns

From [`../design-system/interaction-patterns.md`](../design-system/interaction-patterns.md):

| Gesture | Behavior on this screen |
|---------|-------------------------|
| Tap | … |
| Swipe left | … |
| Swipe right | … |
| Long press | … |
| Pull to refresh | … |
| Bottom sheet | … |

---

## States

| State | What user sees |
|-------|----------------|
| Default | |
| Loading | |
| Empty | |
| Error / offline | |

---

## Flows (tap sequences)

**Entry:**

- From … → …

**Exit:**

- … → pocket / next screen

Link: [`../02-flows.md`](../02-flows.md#…)

---

## Data & API

**Projection needs (authoritative from server):**

- …

**Existing API:** `/api/mobile/...` or **New endpoint needed:** …

Backend is **not frozen** — spec drives API additions.

---

## Edward sign-off

- [ ] Ready for Flutter
