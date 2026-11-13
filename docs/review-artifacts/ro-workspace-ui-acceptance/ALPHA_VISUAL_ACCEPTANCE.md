# RO workspace UI — Alpha visual acceptance

**Status:** READY FOR OWNER VISUAL ACCEPTANCE  
**Production:** ON HOLD (do not deploy)

| Pin | SHA |
| --- | --- |
| SaaS (Alpha) | `09fd774d3a474ff0b13ebdd78d374f8e0bf12819` |
| Core | `8cb7b2ce3d42efa014384d9c6c75fa870cbb136b` |
| Host | `https://alpha.staging.arksms.com` |
| Demo RO | `#40` — `/app/repair-orders/40` |
| Core PR | https://github.com/EdwardSoaresJr/ark-core/pull/192 (checks green) |
| SaaS PR | https://github.com/EdwardSoaresJr/ark-stancl/pull/222 |

## Browser proof (desktop + mobile 375)

| Evidence | File |
| --- | --- |
| Header Print menu open | `desktop-header-print-menu.png` |
| Footer Print dropup open (identical options, opens upward) | `desktop-footer-print-menu.png` |
| Tax jurisdiction modal from Sales Tax row | `desktop-tax-jurisdiction-modal.png` |
| Mobile header Print | `mobile-375-header-print-menu.png` |
| Mobile footer Print dropup | `mobile-375-footer-print-menu.png` |

### Print parity (RO #40, estimate)

Header and footer both project the same five items in the same order:

1. Print Key Tag  
2. Print Oil Sticker  
3. Check In sheet  
4. Tech sheet  
5. Estimate PDF  

Clicking Print opens the menu only (no immediate print/navigation). Footer menu opens upward above the sticky footer on desktop and mobile.

### Also visible in captures

- Sales Tax is a normal totals-row control (`Colorado Springs Tax · 0%`); modal reuses tax-profile save flow.
- Concern headers use a uniform primary-accent tint with `CONCERN N` ordinals (not priority-colored).
- Billing-class minimum shows once in the totals panel as a minimal met line (`Retail RO minimum met …`); no blue banner spam.

## Checks

- Core PR #192: lint, focused, targeted, security, communications-boundary, gitleaks — pass  
- Alpha deploy health: OK (`APP_ENV=staging`)
