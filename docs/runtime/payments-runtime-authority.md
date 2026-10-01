# Payments Runtime Authority

**State:** Observing (ledger) · Processor connectivity extracted from Core  
**Companion:** [ark-payments-boundary-v1.md](../platform/ark-payments-boundary-v1.md)

## Authority

- Repair-order **ledger** owns payment truth (`RecordLedgerEntryAction`, balance due, deposits, refunds).
- Portal **pay tokens** identify view/balance links (`CustomerDocumentAccessToken`).
- Core does **not** own managed card processors.

## Runtime

1. Advisor records cash / card (external) / check on the RO financial rail.
2. Optional: Send Pay Link / deposit request → customer sees amount + shop contact (no card form).
3. Managed online pay / terminal capture → ARK Platform Payments (future), not this Core tree.

## Baseline

External and manual capture on the floor. Portal links remain valid for balance visibility.

## Known defects

- Online card capture is intentionally absent from public Core.
- Historical `payment_gateway_attempts` / Square settings columns may exist; Core stops writing processor attempts.

## Observation gate

Before reintroducing any processor into Core: prove Cloud Payments ownership and keep Core ledger as sole payment truth.
