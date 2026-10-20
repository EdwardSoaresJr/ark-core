---
title: Estimates, inspections, and documents
description: Authorization, digital inspections, the customer portal, and PDFs in ARK Core.
---

# Estimates, inspections, and documents

<!-- source: app/Ark/Operations/Inspections/InspectionChecklistStatus.php -->

## Estimates and approval

An estimate is the priced work on a repair order. Totals, tax, and authorization are server-side. The browser displays the result.

Customer authorization is recorded through the approval actions in `app/Ark/Operations/Approvals`. An approval event row is written when that record is created. Mobile push for an approved estimate listens to that model event. It does not mean a phone can charge a card.

The customer portal (`routes/portal.php`) lets a customer open an estimate, approve, see the inspection, photos, and documents through a portal session or a token link. Portal token reads are limited to 60 requests per minute per IP. Portal token writes are limited to 20 per minute per IP. Portal pages send a noindex header.

Staff capabilities `estimates.view`, `estimates.manage`, and `estimate_documents.manage` gate the staff side. The portal does not use those staff capabilities. It uses portal access tokens and the portal guard.

## Inspections

A digital vehicle inspection hangs off the repair order. Checklist item statuses in source are:

| Value | Label |
| --- | --- |
| `good` | Good |
| `monitor` | Monitor |
| `needs_attention` | Needs Attention |
| `failed` | Failed |
| `na` | N/A |

Technicians record findings with notes and measurements. The Tech API can update an item, including an optional photo file on that request. The staff web inspection controller stores inspection photos through `InspectionEvidenceStore`.

Inspection photos and video on that store allow JPEG, PNG, WebP, MP4, WebM, and QuickTime, up to 102400 KB. PDF is not in the inspection photo list. General repair-order evidence additionally allows PDF.

Prior-visit history is part of the inspection workflow in the product. Templates live in Core. A template editor in the staff UI is the shop's copy of those templates, not a marketplace.

## Documents

Estimate and invoice PDFs are rendered server-side through the `PdfRenderer` binding, which points at a headless Chromium renderer. If Chromium is missing, PDF generation fails. The document is not assembled in the browser.

Printing settings and QZ Tray label printing are Core features after the shop supplies its own certificate and private key. Core does not ship those secrets.

## Limitations

- Portal display of an amount owed is not payment capture.
- Sending the estimate link as a text or ARK-operated email requires the Hosted messaging services. The link itself can exist without that send.
- Inspection photo bytes on the mobile show route are read from the `local` disk. Moving only the `s3` disk does not automatically relocate those reads.
