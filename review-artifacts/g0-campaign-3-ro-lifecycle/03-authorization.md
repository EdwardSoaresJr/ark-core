# Authorization (RO #20)

## Partial authorization — Pass

- Method: synthetic **In person** staff Record approval (no customer SMS/email outside allowlist).
- Approved by: GZero CampaignOne
- Authorized amount: **$259.50**
- Scope: CEL concern approved; Testing Package **Declined**
- `approved_work_scopes` #6 line_ids `[53, 54]`
  - Labor #53 $165.00
  - Part #54 approved at **$94.50** (AFT matrix)
- `approval_events` ceiling **$259.50**
- Estimate document #6 snapshot total **$259.50**

## Hostile post-auth edits — Pass

| Action | Result |
| --- | --- |
| Add labor after auth (#55 G0 post-auth R&R O2, 0.50h @ $165) | **Needs authorization** $82.50 |
| Increase approved part #54 sell $94.50 → $120.00 (Manual Price) | **Needs authorization** Increase · approved at $94.50 **+$25.50** |
| Approval Forecast | Approved **$259.50** · Needs auth **+$108.00** · If approved $367.50 |
| Decline Testing Package | Already declined; remains declined |
| Remove approved line | **Not Run** (preserved approved scope for invoice reconciliation) |

## Defects observed

- **DEF-G0-04** (Medium): After part add, Record approval amount defaulted to stale $165.00; advisor had to set $259.50 manually.
- Sticky footer click intercept residual (Campaign 2 DEF-G0-03 incomplete) — worked around via CDP/`pointerEvents`.

Screenshots: `screenshots/02-post-auth-needs-authorization.png`
