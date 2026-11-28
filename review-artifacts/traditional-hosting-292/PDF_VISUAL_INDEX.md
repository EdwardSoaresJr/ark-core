# PDF visual acceptance index (owner review)

Pixel-perfect Chromium ↔ native parity is **not** required. Review for readability, completeness, financial accuracy, authorization wording, and print suitability.

Artifacts: `pdfs/` and `previews/` (page-1 PNG). Clean-room copies under `/tmp/ark-cleanroom-6fdb98c9/extract/cleanroom-*.pdf` (local rehearsal).

| Document | Chromium preview | Native preview | Verdict |
| --- | --- | --- | --- |
| Estimate | `previews/estimate-chromium-1.png` | `previews/estimate-native-1.png` | **Accept** — totals $150.00 match; authorization + important information present; native layout table-oriented and print-suitable |
| Invoice | `previews/invoice-chromium-1.png` | `previews/invoice-native-1.png` | **Accept** — labor/parts/fees/tax/total and payments & balance present; authorization wording present |
| Inspection | `previews/inspection-chromium-1.png` | `previews/inspection-native-1.png` | **Accept after layout fix** — native no longer blank; header, summary chips, empty-findings copy, footer readable |
| Statement | `previews/statement-chromium-1.png` | `previews/statement-native-1.png` | **Accept with notes** — shop/customer/as-of/balances present; empty AR fixture shows “No outstanding invoices” |

## Owner visual checklist

- [ ] Estimate: shop identity, customer/vehicle, line items, $ totals, authorization language
- [ ] Invoice: same + payments & balance due
- [ ] Inspection: shop header, vehicle/RO, status summary, body/footer (not blank)
- [ ] Statement: shop, customer, balance summary, invoice table
- [ ] Financial figures agree between Chromium and native for the same fixture
- [ ] No clipped/overlapping critical sections on Letter

## Known visual limitations (RC.1)

1. **CJK / uncommon Unicode in shop names** (e.g. `東京`) may render as missing-glyph boxes in native mPDF (DejaVu). Latin shop names are fine. Chromium uses system fonts.
2. Native estimate/invoice use a **table-oriented** template (not identical visual design to Chromium) — financial and disclosure content are equivalent.
3. Statement flex spacing can look slightly tighter on native (`Balance due` vs Chromium). Content complete.
4. Empty inspection fixtures correctly show “No inspection findings…” — not a defect.
5. Photos in inspections: require recorded inspection photos in the fixture; empty RO fixture has none.

## Byte proof alone is insufficient

Earlier native inspection PDFs produced valid `%PDF` bytes but blank pages under flex CSS. Fixed by mPDF compat styles (`HtmlPdfBuilder::nativeCompatStyles`). Visual review of PNG previews is mandatory for acceptance.
