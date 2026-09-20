# Parts Capture v1 — Capture Dealer Quote

**Status:** Shipped (floor path) · V2/V3 parked  
**Authority:** `DealerQuote` + `DealerQuoteLine`  
**Projection:** Estimate part lines (`repair_order_lines.dealer_quote_line_id`)

## Operator job

Advisor has a dealer quote (PDF or pasted text) and wants parts on the estimate. They do not care about import plumbing.

**Estimate toolbar → Import Quote** → upload PDF or paste text → **Analyze Quote** → uncheck junk → mass-assign scope / repair action / matrix (or per row) → **Add N Parts to Estimate**.

## What ARK stores

Not `ImportedFromPDF=true`.

| Authority | Fields |
| --- | --- |
| Dealer Quote | supplier, quote number, vehicle, VIN, dealer total, original PDF path, raw text, captured by/at |
| Dealer Quote Line | qty, part number, description, unit cost |
| Estimate line | points at quote line; vendor/sourcing notes mirror quote; classification OEM |

**Source** on a part line opens the quote page → **View Original Quote** when PDF was uploaded.

## Non-goals (v1)

- OCR confidence gates (V2)
- “Seen this part before” / cost history (V3)
- Supplier comparison, PO generation, receiving
- Replacing PartsTech catalog pull (orthogonal path)

## OCR (scanned PDFs)

When embedded PDF text is empty/unusable, ARK falls back to **pdftoppm + tesseract** (also accepts quote photos). Production image installs `tesseract-ocr` and `poppler-utils`.

## Architecture

```
Dealer Quote (authority)
 ↓
Estimate parts / future PO / cost history (projections)
```

PartsTech remains the live cart path. Capture is the paper/PDF path. Same advisor job; different authorities.
