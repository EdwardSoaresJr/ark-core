# #239 shop identity vs historical documents — evidence

Rendered evidence is asserted by `tests/Feature/Operations/ShopIdentityHistoricalDocumentsTest.php`
(HTML of the issued-invoice document view contains the original name/address after a settings change
and does not contain the new ones; original logo asset is retained and re-embedded).

Scenario matrix (all PASS, 8 tests / 74 assertions):

| State | After renaming shop, new address, new logo |
|---|---|
| Living estimate (open RO) | snapshot refreshed to new identity, PDF flagged for refresh |
| Closed RO estimate (workspace) | stored snapshot untouched; workspace presents current profile |
| Issued invoice (closed RO) | stored row byte-identical; original name/address/logo rendered |
| Older invoice snapshot with only logo path | original logo rebuilt from retained asset, no live backfill |
| New invoice after change | stamped with the new identity; earlier invoices keep theirs |
| Replaced/removed logo | deleted only when no stored snapshot names it |
