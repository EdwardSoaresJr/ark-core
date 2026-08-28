# Screen spec — Concern Detail

**ID:** `companion.screen.concern-detail`  
**Role(s):** Advisor · Technician  
**Status:** 📝 draft — Edward review

---

## Job

**One customer concern on the RO** — findings · estimate lines · inspection link · production status — bridge between inspection and money.

---

## Layout

### Header

- Concern title — `Customer states brake noise`
- RO · vehicle chips

### Sections

1. **Findings** — inspection items linked to this concern · tap → inspection item
2. **Estimate lines** — type · description · sell · total · scan rhythm from RO workspace doctrine
3. **Production** — tech notes · labor status · parts pressure chip (read projection)
4. **Media** — thumbnail grid → photo viewer

### Actions

- Advisor: **Message customer** · **Send estimate** (if concern ready)
- Tech: **Add finding** · **Add photo**

---

## Flows

RO workspace → concern row → here  
Photo push on concern → photo viewer or this screen

---

## Data & API

**Needs:** concern projection with lines · findings · parts pressure once per load

---

## Edward sign-off

- [ ] Advisor sees money + evidence without desktop RO tab
- [ ] Ready for Flutter
