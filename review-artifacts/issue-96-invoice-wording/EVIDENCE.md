# Issue #96 — invoice summary wording

## Before (main @ `4c2f1c25`)

**Estimate authorization (correct on estimates):**

> By approving this estimate, I authorize {shop_name} to perform the approved repairs and services described above.
>
> I acknowledge that additional repairs may require separate authorization and agree to pay for authorized work performed, including diagnostic charges where applicable.

**Issued invoice (incorrect — same estimate text in snapshot + PDF):**

> By approving this estimate, I authorize …

## After (`fix/invoice-wording-96`)

**Estimate:** unchanged (shop `authorization_language` / default).

**Issued invoice summary:**

> This invoice reflects the authorized repairs and services described above.
>
> I agree to pay the amount due for work performed, including applicable taxes and fees.

## Regression coverage

- `tests/Feature/Operations/InvoiceDocumentSummaryWordingTest.php`
- `tests/Feature/Operations/DocumentDisclaimerTest.php` (invoice immutability)
- `tests/Unit/Operations/DocumentFooterPresenterTest.php` (estimate vs invoice footer)

## Migrations

None.

## Legacy issued invoices (display)

Frozen `snapshot_json.documents.authorization_language` is not rewritten in the database.

`DocumentFooterPresenter` substitutes `DEFAULT_INVOICE_SUMMARY_LANGUAGE` when presenting an **invoice** whose frozen text still matches estimate-approval phrasing (`approving this estimate`). Estimates are unchanged.
