# Core recovery transfer

Local overlay from private `arksmsv2` onto public ARK `2facd90` (already 4 local commits ahead of `origin/main` `c24eb69`). First copy used private HEAD `6eeaa50e`; later index-card commits through `e56b967e` were overlaid after production moved. Histories are unrelated; this is not a merge.

Copied committed Core shop files: website records and signed read API, confirmation mail on `ArkMailClient`, PDF/UI, scheduling, inspection/recommendations, communications workspace, PartsTech/catalog toolbar, hosted mail/payments/parts clients used by Core.

Left in `arksmsv2` or skipped: production secrets, Coolify/LNP infra, backups, LugsNPlugs bootstrap UUID, Growth CMS, Core website editor UI, Twilio-named send overlay, Square webhook overlay, uncommitted draft-save (`WebsiteManagementDraftController` / PATCH). Publishing remains a future milestone.

Public sanitization kept: `OutboundSmsTransport` / `ArkTexting`, installer, `App\Ark\Mail\ArkMailClient`, no `public.leads.store`, no Growth CMS schema.
